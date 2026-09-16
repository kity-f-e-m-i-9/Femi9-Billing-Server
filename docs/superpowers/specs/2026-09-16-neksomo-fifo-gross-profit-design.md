# Neksomo FIFO Gross Profit — Design (Phase 1: company-side)

## Background

Gross Profit for the Neksomo login is calculated in
`company/mis-report.php` (lines ~362–1770). For every product, cost is
looked up as **"the most recent rate as of the report period's end date"**
from `neksomo_llp_piece_rates` (falling back to `femi9_llp_sale_rates`),
then multiplied by the **whole period's net quantity sold**:

```
GP = Σ (sale_rate_ex_gst − cost_rate_as_of(period_end)) × net_qty_sold
```

This is wrong whenever the cost rate changes mid-period, or whenever stock
purchased at an old rate is still being sold after a new rate takes effect.
Example: 100 packs bought at ₹20 on day 10, then 400 packs bought at ₹17 on
day 15, but 10 packs from the first lot don't sell until after day 15 —
today's calc costs those 10 packs at ₹17 (the newest rate). They should
still cost ₹20, because that's the actual lot they came from.

**Root cause:** `neksomo_llp_piece_rates` / `femi9_llp_sale_rates` are pure
rate-history tables — `(product_id, effective_date, rate_per_piece)`, no
quantity column. There's no way to know how much stock came in at each
rate, so there's nothing to run FIFO against. Stock quantity itself is
tracked separately and generically, in `stock`/`stock_ledger` via
`StockService.php`, which also has no rate/cost column — it's a pure
quantity audit trail.

## Scope of this phase

This is Phase 1 of a larger eventual system-wide FIFO costing effort. This
phase covers **only** the company-side `StockService.php` and the two
Neksomo/LLP rate-entry forms — enough to fix Neksomo Gross Profit
correctly. It deliberately does **not** touch:

- The four duplicated `StockService.php` copies (distributor, stockist,
  super-stockist, super_distributor).
- `channel-partner`/`territory-partner`, which don't use `StockService` at
  all today.
- The ~30 long-tail reversal/adjustment/return files outside the core
  credit/deduct/transfer paths.

Those are Phase 2+, tracked separately, once this phase proves the model
works for Neksomo.

## Data model

### New table: `stock_lots`

One row per stock-increasing event for a product/holder, at a specific
rate:

```sql
CREATE TABLE stock_lots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_type VARCHAR(32) NOT NULL,   -- matches stock.user_type convention
  user_id INT NOT NULL,             -- matches stock.user_id convention
  rate DECIMAL(10,2) NOT NULL,      -- cost per piece/pack, ex-GST
  qty_purchased INT NOT NULL,
  qty_remaining INT NOT NULL,
  purchase_date DATE NOT NULL,
  ref_type VARCHAR(32) NOT NULL,    -- 'llp_rate_entry' | 'neksomo_purchase' | 'transfer_in' | 'opening_balance'
  ref_id INT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (product_id, user_type, user_id, purchase_date, id)
);
```

FIFO order is `purchase_date ASC, id ASC` — ties on the same date consume
in entry order.

### `stock_ledger` gets a consumption breakdown

A single `deduct` can span multiple lots (e.g. selling 15 when only 10
remain in the oldest lot). New companion table:

```sql
CREATE TABLE stock_ledger_lot_consumption (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stock_ledger_id INT NOT NULL,
  stock_lot_id INT NOT NULL,
  qty_taken INT NOT NULL,
  rate DECIMAL(10,2) NOT NULL,       -- copied from the lot at consumption time
  INDEX (stock_ledger_id),
  INDEX (stock_lot_id)
);
```

Copying `rate` here (not just joining to `stock_lots.rate`) means a later
correction to a lot's rate never silently rewrites historical COGS —
consumption rows are immutable facts about what a sale actually cost at
the time it happened.

## Capturing purchase quantity

The two existing rate-entry forms — `llp-purchase-rate.php` (→
`llp-purchase-rate-action.php`, writes `femi9_llp_sale_rates`) and
`neksomo-llp-piece-sale.php` (→ `neksomo-llp-piece-sale-action.php`, writes
`neksomo_llp_piece_rates`) — get one new **required** field: **Quantity
Purchased**. The existing rate INSERT is unchanged; each submission
additionally inserts one `stock_lots` row (`ref_type = 'llp_rate_entry'`,
`ref_id` = the new rate-history row's id, `purchase_date` =
`effective_date`).

`neksomo-manufacturer-purchase-action.php` already captures
`quantity_packs` + `cost_per_piece` per line into `neksomo_purchase_items`
— it gets one addition: after its existing `StockService::credit()` call,
also insert a matching `stock_lots` row (`ref_type = 'neksomo_purchase'`).

Internal transfers (`internal_transfer_action.php`) already know `$rate`
at the point of `transferIn()` — that call gets a matching `stock_lots`
row too (`ref_type = 'transfer_in'`), so stock moved between godowns
carries its cost forward instead of losing lot identity.

## Consumption logic

Inside company's `StockService::deduct()` and `transferOut()` only
(distributor/stockist/etc. copies untouched — see Scope):

```
function consumeLotsFifo(product_id, user_type, user_id, qty_needed):
    lots = SELECT * FROM stock_lots
           WHERE product_id=? AND user_type=? AND user_id=? AND qty_remaining > 0
           ORDER BY purchase_date ASC, id ASC
           FOR UPDATE
    consumed = []
    remaining = qty_needed
    for lot in lots:
        if remaining == 0: break
        take = min(remaining, lot.qty_remaining)
        lot.qty_remaining -= take
        remaining -= take
        consumed.append((lot.id, take, lot.rate))
    if remaining > 0:
        # No lot data covers this qty (pre-migration stock, or a data gap).
        # Fall back to today's "latest effective rate as of now" lookup so
        # the deduct still succeeds and cost is still approximated —
        # never block a sale for missing cost data.
        fallback_rate = existing effective-date lookup
        consumed.append((null, remaining, fallback_rate))
    return consumed
```

After the existing ledger row is written, one
`stock_ledger_lot_consumption` row is written per `(lot_id, take, rate)`
tuple (a null `stock_lot_id` row represents the fallback-rate portion).

This runs inside the same DB transaction/row-lock (`lockStockRow`) that
`deduct()` already uses, so it's safe under concurrent sales.

## Reversal logic

`reverseDeduct`/`reverseTransferOut` look up the `stock_ledger_id`'s
`stock_ledger_lot_consumption` rows and add `qty_taken` back onto each
`stock_lot_id` (skipping null-lot fallback rows — there's no lot to
restock). This returns stock to the *exact* lot it came from, not the
newest lot, so a delete-and-recreate of an invoice doesn't quietly shift
cost basis.

## Migration / backfill (opening lots)

Existing rate-history rows have no quantity, so pre-cutover stock can't be
retroactively split into real lots. At migration time, for every
`(product_id, user_type, user_id)` with `stock.closing_qty > 0`, seed one
opening lot:

```sql
INSERT INTO stock_lots (product_id, user_type, user_id, rate, qty_purchased,
                         qty_remaining, purchase_date, ref_type)
SELECT product_id, user_type, user_id,
       <today's effective-date fallback rate for this product>,
       closing_qty, closing_qty, CURDATE(), 'opening_balance'
FROM stock
WHERE closing_qty > 0;
```

This makes the transition continuous: everything sold right after cutover
still costs correctly against the rate that was in effect, and every
purchase from that point forward creates a real lot.

## Gross Profit rewrite (`mis-report.php`)

Replace the single period-end rate subquery (`$gp_cost_rate_subq`,
lines 437–450) and its Napkin/Diaper equivalents with an aggregation over
`stock_ledger_lot_consumption`, joined through `stock_ledger` to the
period's date range and `ref_type IN ('invoice','user_invoice','ot_sale')`:

```
COGS = Σ (stock_ledger_lot_consumption.qty_taken × stock_ledger_lot_consumption.rate)
       for ledger rows in [period start, period end] for this product

GP = Σ sale_rate_ex_gst × qty_sold  −  COGS  −  (returns, as today)
```

Net effect: each sold unit is costed at the rate of the lot it actually
drew from, at the moment it was sold — reproducing the worked example
exactly (10 leftover packs cost ₹20; everything after costs ₹17).

## Testing

- Unit-level: `consumeLotsFifo()` against a synthetic lot table — exact
  reproduction of the worked example, plus edge cases (sale spanning 2+
  lots, sale exceeding all remaining lots → fallback, zero-qty lots
  skipped).
- Integration: a full purchase→sale→reverse cycle through
  `StockService::deduct()`/`reverseDeduct()`, asserting `stock_lots.
  qty_remaining` and `stock_ledger_lot_consumption` rows end up correct.
- Report-level: `mis-report.php` Gross Profit for a synthetic period
  spanning a rate change, matching a hand-computed FIFO expected value.
