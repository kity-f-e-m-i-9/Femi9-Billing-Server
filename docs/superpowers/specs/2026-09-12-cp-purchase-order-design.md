# CP (Channel Partner) Purchase Order System — Design

## Background

Territory Partners (TP) already have a full purchase-order flow: TP builds a
cart against a pre-funded advance wallet (`tp_advance_payments`), pays a
courier fee, submits a `tp_purchase_orders` row (`status='waiting'`), and
Company approves it by converting it into a real `tp_invoices` row that debits
either company godown stock or Channel-Partner stock
(`company/tp-invoice-action.php`).

Channel Partners (CP) are a different existing role: `channel_partners` +
`channel_partner_stock` / `channel_partner_stock_ledger`. A CP already holds
stock and already supplies TP invoices as a stock source
(`source_cp_id` on `tp_invoices`). A CP's only inbound stock path today is
Company manually running a Godown → Location transfer
(`company/pl-godown-transfer-action.php`, `transfer_type='godown_to_location'`).
There is no CP-initiated ordering flow. CP also already has a security deposit
concept, held per assigned location (`partner_location_nodes.deposit_amount`,
summed via `channel-partner-locations` — see
`company/cp-wallet-commission-calculator.php::getCpTotalDeposit()`), currently
used only for commission calculation.

This spec adds a CP-side purchase-order flow mirroring TP's, with the wallet
mechanic replaced by a **live inventory-value cap** derived from the deposit,
and Company approval reusing the existing Godown⇄Location transfer machinery
instead of inventing a parallel "CP invoice" concept.

## Balance rule (replaces TP's advance wallet)

CP does **not** submit any advance payment. Instead, at any moment:

```
held_stock_value  = Σ (channel_partner_stock.closing_qty × products.mrp)  for this CP
pending_po_value   = Σ (line qty × MRP) over this CP's own status='waiting' POs
cap                = total_deposit + 5000      (total_deposit = getCpTotalDeposit())
available_headroom = max(0, cap − held_stock_value − pending_po_value)
```

A new cart is accepted only if `new_cart_value ≤ available_headroom`,
evaluated fresh (never stored) both for the courtesy display on the order
form and authoritatively on submit. This directly implements the user's
example: deposit ₹3,00,000, cap ₹3,05,000 — CP can hold stock worth up to
₹3,05,000 at MRP at any time; once ₹1,00,000 of held stock sells out (CP's
`channel_partner_stock` debited via the existing TP-invoice-from-CP-stock
path), `held_stock_value` drops by that amount and the same ₹1,00,000 of
headroom reopens automatically — no separate wallet ledger to maintain.

Pricing: cart lines are priced at `products.mrp` (not `stockist_price` as TP
uses), since the cap itself is defined in MRP terms.

## New DB objects

`channel_partner_purchase_orders` (mirrors `tp_purchase_orders`, stripped of
advance/courier/SS-approver concepts):
- `id`, `channel_partner_id`, `product_type` (`ENUM('napkin','diaper')`),
  `order_date`, `status` (`ENUM('waiting','completed','cancelled')`),
  `cancel_reason`, `cancelled_at`, `cancelled_by`,
  `use_default_delivery_address`, `custom_delivery_line1/2/city/district/state/country/pincode`
  (same shape as TP's, defaulting to the CP's own registered branch/delivery
  address),
  `transfer_id` (nullable FK → `pl_godown_transfers.id`, set on approval).

`channel_partner_purchase_order_items`:
- `id`, `po_id`, `product_id`, `qty`, `price` (MRP at submit time), `amount`.

`pl_godown_transfers` gets one additive column: `source_po_id` (nullable FK →
`channel_partner_purchase_orders.id`), so an approval-created transfer links
back to the order that requested it. Existing manual transfers keep this
NULL; nothing about existing transfer behavior changes.

Both new tables follow the same self-migrating pattern already used
throughout (`SHOW COLUMNS` / `CREATE TABLE IF NOT EXISTS` guards at the top of
each entry-point file), consistent with `tpEnsureAdvanceWalletColumns` etc.

## CP-side pages (new, under `channel-partner/`)

- **`add-purchase-order.php`** — same napkin/diaper type-chooser landing as
  TP's, then a cart-builder form: product picker (full active catalog for the
  chosen type, priced at MRP, not editable by CP), qty, running total, a
  balance banner showing `available_headroom` (deposit + 5000 − held stock
  value − pending PO value), delivery-address block reusing the CP's own
  registered branch/delivery address (same "use existing / enter custom" toggle
  pattern as TP's). Submitting over headroom is blocked client-side (courtesy
  check only) and server-side (authoritative).
  No advance-payment step, no courier-payment step, no SS-approver picker —
  Company is the only approver.
- **`purchase-order-action.php`** — authoritative validation: recompute
  `available_headroom` server-side from live `channel_partner_stock` +
  `partner_location_nodes` + this CP's other waiting POs, re-classify cart
  product-type server-side (never trust the type the picker implied), reject
  over-cap carts with a clear message, otherwise insert the PO + items with
  `status='waiting'`.
- **`manage-purchase-orders.php`** — CP's own order history/status list,
  mirroring TP's page minus advance/courier columns. A still-`waiting` order
  can be deleted (no stock ever moved for it yet) via a
  **`delete-purchase-order.php`** mirroring TP's.

## Company-side approval

- A new queue page, **`company/cp-today-orders.php`**, mirroring
  `tp-today-orders.php`'s "active/waiting/completed/cancelled" queue, scoped to
  `channel_partner_purchase_orders`, with the napkin/diaper type filter kept.
  Each row shows the CP, requested items, and current `available_headroom` for
  that CP (so Company can see whether the CP's deposit still supports the
  order at approval time, since stock levels may have moved since submission).
- **Approve** (new **`company/cp-po-action.php`**, POST-only, mirrors
  `tp-invoice-action.php`'s shape): Company picks a source godown (same
  `company_godown` picker `add-tp-invoice.php` already uses via
  `godown_finance_filter_sql`), can adjust qty/rate per line same as TP's
  invoice screen, then submits. Inside one transaction:
  1. Re-validate `available_headroom` for this CP one more time (closes the
     race between queue-load and click).
  2. For each line: lock godown stock row (`SELECT ... FOR UPDATE`), verify
     sufficient qty, debit it (mirrors `debitGodown` in
     `pl-godown-transfer-action.php`), write a `stock_ledger` row.
  3. Lock the CP's stock row (`SELECT ... FOR UPDATE`), credit it (mirrors
     `creditCp`), write a `channel_partner_stock_ledger` row
     (`action='transfer_in'`, `ref_type='transfer'`).
  4. Insert one `pl_godown_transfers` header
     (`transfer_type='godown_to_location'`, `source_po_id` = this PO's id) +
     its `pl_godown_transfer_items`, exactly as a manual transfer would.
  5. Mark the PO `completed`, store the new transfer's id on
     `channel_partner_purchase_orders.transfer_id`.
  6. Any failure at any step rolls back the whole transaction — no partial
     stock movement, no PO left in a half-completed state.
- **Reject**: mark `cancelled` with a reason, identical shape to
  `cancel-tp-purchase-order.php`; no stock touched.
- The existing `company/manage-pl-godown-transfers.php` (transfer history/
  report) picks up PO-originated transfers automatically since they're real
  rows in the same table — a `source_po_id IS NOT NULL` badge is added there
  so Company can tell which transfers came from a CP request vs. a manual
  push.

## Guardrails ("unbreakable stock maintenance")

- Every stock-affecting write happens inside a DB transaction with
  `SELECT ... FOR UPDATE` locks on both the godown stock row and the CP stock
  row, in the same order `pl-godown-transfer-action.php` already uses —
  reused directly, not reinvented.
- Available quantity and available headroom are both re-read **inside** the
  locked transaction as the authoritative check; the queue-page numbers and
  the CP's own cart-builder numbers are courtesy-only and never trusted.
  This closes concurrent-approval and concurrent-order races the same way
  `purchase-order-action.php` already closes them for TP's advance balance.
- A CP cart is re-classified server-side against napkin/diaper product lists
  on submit (same `tpProductTypeOfProducts` guard TP's flow already uses) —
  a raw POST can't smuggle a mismatched product in.
- Deleting/cancelling a `waiting` PO never touches stock, since nothing was
  moved for it yet — only a `completed` PO has a real transfer behind it.
- No new code path can debit `channel_partner_stock` below zero: the
  pre-existing `pl-godown-transfer-action.php` guard pattern
  (`if ($item['qty'] > $before) throw ...`) is reused verbatim for the new
  approval action.

## Explicitly out of scope (per user's stated differences)

- No advance-payment submission/review for CP.
- No courier-payment step for CP (Company handles CP fulfillment purely as an
  internal godown→location transfer; nothing suggests CP pays courier the way
  TP does, and the user's brief didn't ask for it).
- No Super-Stockist-style alternate approver — Company is CP's only approver.
