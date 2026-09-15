# Internal Transfer Receipt → Tax Invoice — Design

## Background

`company/pl-godown-transfer-print.php` is the print page for an internal
stock transfer (`pl_godown_transfers`/`pl_godown_transfer_items`) — a plain
receipt showing product/quantity only, no pricing, no tax. It's the page
`manage-pl-godown-transfers.php`'s "Print Receipt" button falls back to for
any transfer that has no linked CP invoice (`cp_invoices`, from the
CP-purchase-order-invoicing feature) — today, that's every existing
transfer, since none predate that feature having invoices attached.

Separately, `company/tp-invoice-print.php` (backed by
`shared/TpInvoiceData.php` + `shared/TpInvoiceHtml.php`) renders a full GST
tax invoice for Territory Partner sales: CGST/SGST split, HSN-wise summary,
bank details, amount-in-words, signature block.

This spec makes the transfer receipt visually and structurally match the TP
tax invoice exactly, computing real GST on the transfer's line items.

## Why this needs new logic, not just a template swap

`pl_godown_transfer_items` stores only `product_id` and `quantity` — no
rate, amount, or GST%. There is no stored price for an internal transfer,
unlike `tp_invoice_items`, which carries `rate`/`amount`/`discount_*` set at
invoice-creation time. To render tax figures, the rate must be computed at
print time.

**Decision:** rate = `products.mrp`, looked up fresh at print time (not
stored). This matches the same base the CP purchase-order cart already
prices against elsewhere in the app. No schema change to
`pl_godown_transfer_items`.

## GST computation

Mirrors `TpInvoiceData.php`'s computation exactly:
- Per line: `gst_type` (`inclusive`/`exclusive`) from `products.gst_type`
  determines whether the MRP already includes tax; the taxable value and
  GST amount are carved out using the identical formula TP already uses.
- Split: **CGST + SGST only, GST% ÷ 2 each — never IGST.** This matches
  TP's existing behavior exactly (verified: `TpInvoiceHtml.php` has no IGST
  code path at all, regardless of buyer/seller state). Not a gap being
  introduced here — a deliberate parity choice with the template being
  matched.
- HSN-wise summary table: identical rollup logic (taxable value, GST%,
  GST amount) per HSN code.
- Heading: literally **"Tax Invoice"** (exact match to TP's text), or
  "Bill of Supply" when the total GST is zero — same fallback TP already
  has.
- No discount, no courier charges — neither concept exists for an internal
  transfer. Those template sections (present in TP's markup, conditional on
  a nonzero value) simply never render here, the same way they already
  conditionally skip on a TP invoice with no discount/courier.

## Buyer/seller data

- **Seller** (always): the source `company_godown` row — GSTIN, address,
  state/state-code, bank details (`acname`/`acnumber`/`bankname`/
  `branchname`/`ifsc`/`upinumber`). Identical fields TP's seller block
  already uses.
- **Buyer**: `COALESCE(channel_partners, partner_location_nodes)` keyed off
  `pl_godown_transfers.cp_id` / `.location_id` — the same
  mutually-exclusive-destination pattern already fixed in
  `pl-godown-transfer-print.php`'s header query and already used in
  `manage-pl-godown-transfers.php` / `get-transfer-items.php`. A CP buyer
  shows its GSTIN/branch address (from `channel_partners`); a location
  buyer shows just its name (no GSTIN/address fields exist on
  `partner_location_nodes` — those rows render without that detail, same
  as the report page already handles a location-only destination).

## New files (mirroring TP's three-file split exactly)

- **`shared/TransferInvoiceData.php`** — `load_transfer_invoice_data($db_conn,
  int $transfer_id): ?array`. Loads header + items, computes per-line/HSN/
  grand totals, returns the same shape of context array
  `TpInvoiceData.php` returns (minus discount/courier/carton-agnostic
  differences noted above), so `TransferInvoiceHtml.php` can reuse the same
  rendering shape.
- **`shared/TransferInvoiceHtml.php`** — `render_transfer_invoice_html(array
  $ctx, bool $show_carton_cols): string`. Byte-for-byte same CSS/table
  structure/classes as `TpInvoiceHtml.php`. Labels change from "Territory
  Partner" to the buyer's actual name (CP or location); "Invoice #" shows
  the transfer's `ref_number`; "Invoice Date" shows `transfer_date`.
- **`company/pl-godown-transfer-print.php`** — becomes a thin controller
  (like `tp-invoice-print.php`): loads data via the new function, renders
  via the new function, same Print-popup JS and page chrome it already has.

## Explicitly out of scope

- No change to `manage-pl-godown-transfers.php`'s existing logic that
  routes to `cp-invoice-print.php` instead of this page once a transfer has
  a real linked `cp_invoices` row (built in the CP-invoicing feature) —
  this spec only changes what a transfer **without** a CP invoice prints.
- No change to `pl_godown_transfer_items`' schema — rate stays computed
  from live `products.mrp`, never stored.
- No IGST support — matches TP's existing gap, not introducing a new one.
- No change to the CP invoice (`cp-invoice-print.php`) print page itself —
  that stays the deliberately simpler, non-GST document per the earlier
  CP-invoicing spec.
