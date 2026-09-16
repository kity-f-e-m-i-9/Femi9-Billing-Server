# CP Purchase Order — Invoicing & Delivery Note — Design

## Background

The CP (Channel Partner) purchase-order system
(`docs/superpowers/specs/2026-09-12-cp-purchase-order-design.md`) was built to
reuse the existing Godown⇄Location transfer machinery
(`pl_godown_transfers`/`pl_godown_transfer_items`) on approval, deliberately
avoiding a parallel "CP invoice" concept. `company/cp-po-action.php` today
only moves stock — it inserts no invoice-shaped record and generates no
document number of any kind.

This spec **reverses that decision**: CP purchase-order approval should
produce a real invoice, the same way `company/tp-invoice-action.php` turns a
`tp_purchase_orders` row into a `tp_invoices` row for Territory Partners.

For CP specifically, the invoice **is** the delivery note — one document, one
auto-generated number. There is no separate delivery-note record, table, or
number series. The single `cp_invoices` row/number is printed with both
"Tax Invoice" and "Delivery Note" framing on the same page. (The legacy
`delivery_note` table / `dlnote_action.php` is an unrelated manually-typed
free-text field for old-style `user`/`shop`/`customer` invoices, and is not
touched by this spec.)

## What TP has that CP will now get

| | TP (existing) | CP (new, this spec) |
|---|---|---|
| Approval artifact | `tp_invoices` + `tp_invoice_items` | `cp_invoices` + `cp_invoice_items` (new) |
| Invoice numbering | `tpInvoiceNextNumber()`, format `TP/{fy}/{seq}` | `cpInvoiceNextNumber()`, format `CPDN/{fy}/{seq}` (new service, same locking pattern) |
| Stock movement | Debit company godown/CP stock, credit `territory_partner_stock` | Debit company godown stock, credit `channel_partner_stock` — **unchanged**, already correct in `cp-po-action.php` |
| Delivery note | None | Same document as the invoice — same number, same row, no separate table |
| Invoice + DN print | `tp-invoice-print.php` + `shared/TpInvoiceHtml.php` | `cp-invoice-print.php` + `shared/CpInvoiceHtml.php` (new), one page labeled as both |

The existing `pl_godown_transfers` row is **kept**, not replaced — it remains
the system of record for stock ledger correctness and for
`manage-pl-godown-transfers.php` reporting. The new `cp_invoices` row is
layered on top as the commercial/paper-trail artifact, exactly as
`tp_invoices` already coexists with direct stock debits (TP has no separate
transfer table; CP already does, so we keep both here).

## New DB objects

**`cp_invoices`** (mirrors `tp_invoices`; this single row/number serves as
both the invoice and the delivery note):
- `id` PK, `invoice_number` VARCHAR(30) UNIQUE, `channel_partner_id`,
  `source_godown_id`, `product_type` ENUM('napkin','diaper'),
  `invoice_date`, `total_amount`, `discount_amount`,
  `use_default_delivery_address` TINYINT(1) DEFAULT 1,
  `custom_delivery_line1/2/city/district/state/country/pincode` (copied from
  the source PO, same shape as `tp_invoices`),
  `transfer_id` (FK → `pl_godown_transfers.id`, links back to the stock
  movement that backs this invoice),
  `created_by`, `created_by_user_type` DEFAULT `'company'`,
  `created_at`, `updated_at`.
- One row per approved PO. No `delivery_note_number` column — the
  `invoice_number` itself is what prints as the delivery note number too.

**`cp_invoice_items`** (mirrors `tp_invoice_items`):
- `id` PK, `cp_invoice_id` FK (CASCADE delete), `product_id`, `quantity`,
  `rate`, `amount`.

**`cp_inv_sequence`**: a single-purpose sequence table keyed by `source`
(mirrors `tp_inv_sequence`'s shape exactly), created via the same
self-migrating `CREATE TABLE IF NOT EXISTS` guard pattern used throughout.

**`channel_partner_purchase_orders`** gets one additive column:
`cp_invoice_id` (nullable FK → `cp_invoices.id`), set on approval alongside
the existing `transfer_id`. Both columns end up populated after a successful
approval; existing rows keep `cp_invoice_id` NULL.

All new tables follow the same self-migrating pattern already used
throughout (`SHOW COLUMNS` / `CREATE TABLE IF NOT EXISTS` guards at the top
of the entry-point file), consistent with `tpInvoiceEnsureSequenceSchema()`.

## New numbering service

**`shared/CpInvoiceNumberService.php`** — direct copy of
`shared/TpInvoiceNumberService.php`'s pattern: `cpInvoiceNextNumber($db,
$source, $invoiceDate, $padDigits=3)`, format `CPDN/{fy}/{seq}` (single source,
`'CO'`, since Company is CP's only approver — no per-SS branching needed).
Caller must already be inside a transaction. Uses `INSERT IGNORE` +
`SELECT ... FOR UPDATE` row lock on `cp_inv_sequence` scoped by source, with
the same self-healing `MAX(SUBSTRING_INDEX(invoice_number,'/',-1))`
cross-check against `cp_invoices` to recover from any counter drift.

This one number (`CPDN/26-27/00001`) is what prints as both the invoice number
and the delivery note number — no second sequence, no second table.

## Changes to `company/cp-po-action.php`

The existing approve-path transaction (lock/debit godown stock → lock/credit
CP stock → insert `pl_godown_transfers` + items → mark PO `completed`) stays
exactly as-is through the stock-movement steps. Two things are added inside
the same transaction, after stock movement and before commit:

1. `$inv_num = cpInvoiceNextNumber($db_conn, 'CO', $invoice_date);`
2. Insert `cp_invoices` header (carrying `invoice_number`,
   `transfer_id` from the transfer just inserted, delivery address fields
   copied from the PO exactly as `tp-invoice-action.php` copies them from
   `tp_purchase_orders`, `total_amount` summed from line amounts) + one
   `cp_invoice_items` row per line (reusing the same `quantity`/`rate`/`amount`
   values already computed for the transfer items — no re-derivation).
3. `UPDATE channel_partner_purchase_orders SET cp_invoice_id = ? WHERE id = ?`
   alongside the existing `transfer_id` update.

Any failure at any step (including invoice-number generation) rolls back
the whole transaction, same as today — no transfer without an invoice, no
invoice without a transfer.

The reject path is untouched.

## New print view

**`company/cp-invoice-print.php`** + **`shared/CpInvoiceHtml.php`** — thin
controller mirroring `tp-invoice-print.php`: decode invoice id, load via a
new `load_cp_invoice_data()` (joins `cp_invoices` → `cp_invoice_items` →
`products`, `channel_partners`), render via `render_cp_invoice_html()`. One
page, one number — headed "Tax Invoice cum Delivery Note", showing pricing
(rate/amount per line, total) as a normal invoice does, plus a signature/
goods-receipt block at the bottom for the receiving party to sign, so the
same printout that bills the CP also serves as their delivery
acknowledgment. Same action bar as TP's (Print / WhatsApp share / links to
CP invoice list).

## Guardrails (unchanged from the Sep 12 spec, extended)

- All new writes happen inside the same transaction and lock ordering the
  Sep 12 spec already established (godown row → CP row) — invoice/DN
  insertion is appended after those locks are already held, not a new
  locking concern.
- Number generation (`cpInvoiceNextNumber`) is transaction-scoped and
  lock-based, never a courtesy/suggestion value — same reasoning as why
  `tp-invoice-action.php` uses `TpInvoiceNumberService.php` instead of the
  suggestion-only `InvoiceNumberSuggest.php` pattern.
- `cp_invoices`/`cp_invoice_items` are pure additive records — nothing reads
  or writes them outside the new approval code path and the new print view,
  so this cannot regress existing CP PO, transfer, or stock-ledger behavior.

## Explicitly out of scope

- No changes to TP's own flow — TP still has no delivery note concept;
  extending this same pattern there is a natural follow-up but not part of
  this spec.
- No separate delivery-note table, number, or print page — by design, one
  document (`cp_invoices`) serves both purposes.
- No partial-shipment support — one invoice/delivery-note per approved PO,
  always.
- No changes to the legacy `delivery_note` table or `dlnote_action.php` —
  fully separate mechanism, untouched.
