# One-Click Auto Internal Transfer for Orders — Design

Date: 2026-09-18
Status: Approved, ready for implementation planning

## Problem

Fulfilling today's demand for Territory Partner (TP) purchase orders and
drafted OT channel orders currently requires staff to manually compute how
much of each product is needed, then manually run two separate internal
stock transfers: Neksomo → Healthcare, then Healthcare → LLP. This is
repetitive and error-prone.

## Goal

Add a one-click "Auto Transfer for Orders" action to the Internal Stock
Transfer menu that:

1. Computes required quantity per product from today's demand.
2. Shows the user an editable popup pre-filled with the (stock-capped)
   quantities.
3. On confirm, executes both transfer legs (Neksomo→Healthcare,
   Healthcare→LLP) in one action, reusing the existing `StockService`
   and `internal_transfer` / `internal_transfer_invoice` tables exactly
   as the manual flow does.

## Demand sources (per product, summed)

1. **TP purchase orders**: `tp_purchase_order_items` joined to
   `tp_purchase_orders` where `status = 'waiting'` AND
   `order_date = CURDATE()`. Sum `qty` grouped by `product_id`.
2. **Drafted OT channel orders for LLP**: `ot_sales` joined to
   `ot_sales_invoice` on `tempid`, where `ot_sales_invoice.status = 'draft'`
   AND `ot_sales.godownid = <LLP company_godown.id>` AND
   `ot_sales.date = CURDATE()`. Sum `qty` grouped by `prid`.

   Note: `ot_sales_invoice` has no `date` column — the date filter must be
   applied on `ot_sales.date`, not the invoice header.

Both sums are combined per product into a single required-quantity map.

## Godown resolution

There is no `companies` table — locations are rows in `company_godown`
(columns: `id`, `gname`, `finance_only`, `contact`). Resolve the three
fixed entities by `gname` once per request:

- Neksomo: reuse `get_neksomo_godown_id($db_conn)` from
  `include/NeksomoStockBridge.php` (existing helper).
- Healthcare: `SELECT id FROM company_godown WHERE gname = 'FEMI HEALTH CARE'`.
- LLP: `SELECT id FROM company_godown WHERE gname = 'FEMI NAYAN LLP'`.

These two new lookups are simple inline queries in the new page — not
worth a shared helper for just two call sites.

## Stock capping (pass-through cascade)

For each product with required qty `R`:

- `neksomo_avail = StockService::getClosingQty($pid, 'company', $neksomoId)`
- `healthcare_avail = StockService::getClosingQty($pid, 'company', $healthcareId)`
- `transfer_qty = MIN(R, neksomo_avail + healthcare_avail)`

This is what moves through both legs. Using `neksomo_avail + healthcare_avail`
(rather than just `neksomo_avail`) means Healthcare's own pre-existing stock
also counts toward fulfilling the requirement, so the two-hop transfer
doesn't undershoot when Healthcare already holds some units. The actual
Neksomo→Healthcare leg only ever moves `MIN(transfer_qty, neksomo_avail)`
from Neksomo (it cannot move what Neksomo doesn't have); the
Healthcare→LLP leg then moves the full `transfer_qty` (Healthcare now
holds enough after receiving the first leg).

Products with `transfer_qty <= 0` are excluded from the popup.

## UI flow

**Entry point**: new "Auto Transfer for Orders" link in the Internal Stock
Transfer flyout submenu, added next to "Add Internal Stock Transfer" /
"Manage Internal Stock Transfer" / "Credit Notes (Transfer Returns)" in all
4 duplicated blocks of `femi_menu.php`, gated by the same
`internal_transfer` permission check used at the existing entries (~L870).

**Page**: `internal_transfer_auto.php`
- On load, runs the aggregation query above and renders a table/modal:
  Product | Required Qty | Capped/Suggested Qty (editable `<input>`,
  pre-filled) | Available at Neksomo | Available at Healthcare.
- A single "Transfer Now" button submits all rows via POST to
  `internal_transfer_auto_action.php`.
- If no products have `transfer_qty > 0`, show "Nothing to transfer today."
  instead of an empty popup.

**Action**: `internal_transfer_auto_action.php`
- Re-validates each submitted qty against current `getClosingQty` at
  submit time (values may have shifted since page load — same
  pre-validation pattern as `internal_transfer_action.php:94-103`).
- Any row whose submitted qty now exceeds available stock is silently
  capped down to the currently available amount (consistent with the
  "auto-cap" decision — never hard-fail a row here either).
- For each product row, inside one DB transaction:
  1. Generate a `tempid` for the Neksomo→Healthcare leg (same tempid
     format/uniqueness guard as manual transfer, e.g.
     `AUTO-<YmdHis>-<n>`).
  2. Insert `internal_transfer_invoice` + `internal_transfer` rows for
     this leg (mirroring `internal_transfer_action.php:112-124`); GST
     values pulled from `products.gst` / `products.gst_type` at transfer
     time, same convention as the manual page and per the
     [[inclusive-gst-detax-convention]] memory.
  3. `StockService::transferOut($pid, 'company', $neksomoId, $qty, 'transfer', $tempid1, $createdBy, true)`
     then `transferIn($pid, 'company', $healthcareId, $qty, 'transfer', $tempid1, $createdBy, true, $outResult['consumed_rate'])`.
  4. Generate a second `tempid` for the Healthcare→LLP leg and repeat
     steps 2–3 with `transferOut($pid, 'company', $healthcareId, ...)` →
     `transferIn($pid, 'company', $llpId, ...)`.
- Commit once both legs for all rows succeed; on any `StockException`,
  roll back the whole batch (same as the manual page — this keeps the
  two legs atomic with each other, unlike the per-row skip used for the
  pre-submit stock check).
- Redirect to `internal_transfer_manage.php` with a success/summary
  message listing the two tempids created and any rows that were capped
  down from their originally requested qty.

## Error handling

- Pre-submit capping happens twice: once when building the popup (against
  qty available *now*), once at action-time (against qty available at
  *commit*). The second cap is authoritative; the first is just a UX
  convenience so users rarely see a difference.
- If Neksomo/Healthcare/LLP godown IDs cannot be resolved (e.g. `gname`
  missing from `company_godown`), fail the whole page load with a clear
  error rather than partially rendering.
- Standard `StockException` → rollback → session error message → redirect
  back to `internal_transfer_auto.php`, matching the manual flow's
  error-handling pattern.

## Testing

- `php -l` on both new files.
- Manual verification: seed one `tp_purchase_orders` row (status=waiting,
  order_date=today) with a line item, and one draft `ot_sales_invoice` +
  `ot_sales` row for LLP dated today, for the same product. Confirm the
  popup shows the summed required qty, confirm both transfer legs post to
  `internal_transfer` / `internal_transfer_invoice` / `stock_ledger`, and
  confirm final closing stock at LLP matches expectations.
- Verify capping: seed a requirement larger than Neksomo+Healthcare's
  combined available stock, confirm the popup and final transfer both cap
  correctly instead of erroring.

## Out of scope

- No changes to `tp_purchase_orders` or `ot_sales` status after transfer
  (this feature only moves stock into position; it does not mark POs as
  fulfilled or drafts as confirmed — that remains a separate manual step).
- No new `companies` abstraction; continues using `company_godown` by
  `gname` lookup, consistent with the rest of the codebase.
