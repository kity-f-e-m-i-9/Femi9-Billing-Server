# Warehouse Selection on Invoice/Sale Flows — Design

## Background

The per-godown split-stock project ([[per-godown-split-stock-project]],
full history in
`docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md`)
already shipped:

- Phase 1: `stock`'s identity key extended to
  `(product_id, user_type, user_id, warehouse_id)`; `StockService`'s core
  methods all accept an optional trailing `?int $warehouseId = null`.
- Phase 2: raw-SQL bypass workflows (Add Input Stock, Stock Return) fixed
  to scope correctly by `warehouse_id`.
- Phase 3 (partial): Add Input Stock and manual Internal Transfers both
  gained a real "pick a warehouse" UI, wired through to StockService.

That original spec explicitly called out sales/invoice deduction as "the
highest-uncertainty call site" and scoped it last, deliberately left
open. This document is that follow-up design, covering all 5 invoice/sale
creation flows the user identified: TP invoice, OT channel invoice, SS
invoice, customer invoice, shop invoice.

## Goals

- Every flow lets the user optionally pick a physical warehouse (H1/G1/G2
  etc.) in addition to the existing company-profile (`company_godown`)
  selection, so the resulting stock deduction is tagged to that specific
  warehouse via StockService's existing `warehouseId` parameter.
- Blank/unselected warehouse continues to behave exactly as today
  (`warehouse_id = NULL`, the "unassigned" bucket) — fully
  backward-compatible, no forced migration of existing data or workflows.
- Consistent UX: same warehouse dropdown look/options
  (`SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY
  code ASC`) everywhere it appears, matching Add Input Stock and Internal
  Transfer's existing pattern.

## Non-goals

- Per-company scoping of warehouses. Confirmed via
  `db_migrations/2026_09_17_warehouses.sql` and `manage-warehouses.php`:
  warehouses are global, with no FK to `company_godown`. The same
  warehouse dropdown/options list is shown regardless of which company
  profile was picked.
- Changing CP-sourced TP invoices, or any channel-partner/territory-partner
  *own* stock ledgers (`channel_partner_stock*`, `territory_partner_stock*`).
  Warehouses are a company-stock concept only; CP/TP's own stock pools are
  out of scope.
- Redesigning `invoice.php`'s session-derived source (no picker exists
  today) — only the picker-based customer invoice
  (`customer-user-invoice-add.php` family) gains warehouse selection.
- FIFO lot warehouse-awareness (`stock_lots`) — still Phase 4 of the
  original spec, unaffected by this document.
- Reports — still Phase 5 of the original spec.

## Key design facts confirmed during investigation

1. **Warehouses are global** — one dropdown, same options, everywhere.
2. **Three different deduction mechanisms exist today**, requiring
   different amounts of work per flow:
   - **Raw SQL, bypasses StockService entirely** — TP invoice's
     `debitGodownForTp()`/`lockAndGetGodownQtyForTp()`/
     `insertGodownLedgerForTp()` in `tp-invoice-action.php`.
   - **Direct StockService calls in a loop** — OT channel invoice
     (`otDeduct()` in `ot-sale-action.php`), customer invoice legacy path
     (`invoice-submit.php`'s `deduct()` call).
   - **Shared include, StockService-based** — `invoice-stock-update.php`,
     used by both shop invoice and picker-based customer invoice.
3. Every flow with a real `company_godown` picker stores the chosen id as
   `user_id` for stock lookups (`user_type='company', user_id=<godown_id>`).
   Warehouse selection is a second, independent key — not a replacement.
4. SS invoice has no company-profile picker at all (the SS's own account
   is the fixed source) — it needs a standalone warehouse dropdown, not a
   second step chained after a first one.

## Decisions (confirmed with user)

- **TP invoice**: refactor the godown-sourced path
  (`debitGodownForTp`/`lockAndGetGodownQtyForTp`/`insertGodownLedgerForTp`)
  to call `StockService::deduct()` instead of hand-rolled SQL. CP-sourced
  mode is untouched — no warehouse picker shown when `source_cp_id` is
  selected instead of `source_godown_id`.
- **SS invoice**: add a standalone, optional "Godown" dropdown (no
  preceding company-profile step needed, since the SS's own account is
  always the source).
- **Customer invoice**: only the picker-based version
  (`customer-user-invoice-add.php` family) gains the warehouse dropdown.
  The session-derived version (`invoice.php` family, no picker at all
  today) is left untouched.
- **Warehouse required vs optional**: optional everywhere. Blank stays
  `warehouse_id = NULL`, matching every prior phase's backward-compatible
  default.

## Behavior change flagged for TP invoice refactor

`debitGodownForTp()` today writes `stock_ledger` with
`action='transfer_out', ref_type='transfer'` — i.e. it models a TP
invoice's stock exit as an internal transfer, not a sale.
`StockService::deduct()` writes `action='deduct'` with `ref_type` taken
from the caller (would become `ref_type='tp_invoice'` to match the
pattern used by `otDeduct`/`invoice-stock-update.php` for their own
flows). This is a real behavior change in the ledger's audit trail.

Investigated whether anything downstream depends on the old
`action='transfer_out'` value for TP invoice rows specifically: no report
or reconciliation script filters `stock_ledger.action` for this purpose
today (`grep` across `femi9/billing/company/*.php` for `transfer_out`
found no read/report site, only other unrelated writers). Proceeding with
the StockService-standard `action='deduct', ref_type='tp_invoice'`
values — this is more correct going forward (a TP invoice is a sale, not
an internal transfer) and consistent with every other flow in this
document.

`sales_qty`/`closing_qty` bookkeeping is unaffected — `StockService::deduct()`
increments `sales_qty` and decrements `closing_qty` identically to the
current hand-rolled `debitGodownForTp()`.

---

## Per-flow design

### 1. TP invoice

**Files:** `femi9/billing/company/add-tp-invoice.php` (form),
`femi9/billing/company/tp-invoice-action.php` (submit — the refactor
target), `femi9/billing/company/edit-tp-invoice.php` /
`edit-tp-invoice-action.php` (edit flow — must mirror the same change).

**Schema:** add nullable `warehouse_id INT NULL` to `tp_invoices` (the
header table, alongside its existing `source_godown_id` column).

**UI (`add-tp-invoice.php`):** when "Send from Company Godown" mode is
selected (i.e. `source_godown_id` is chosen, not CP), reveal a "Godown
(physical)" dropdown, same options list as Add Input Stock. Hidden when
CP-sourced mode is active — CP stock has no warehouse concept. Read via a
new hidden/visible field `warehouse_id`.

**Action handler (`tp-invoice-action.php`) refactor:**
- Replace the three raw-SQL godown-path helpers
  (`getGodownQtyForTp`/`lockAndGetGodownQtyForTp`/`debitGodownForTp`/
  `insertGodownLedgerForTp`) with calls to
  `StockService::getClosingQty()` (pre-validation, line ~298) and
  `StockService::deduct()` (the debit, line ~372-376), passing
  `$warehouseId` (from `$_POST['warehouse_id']`, `filter_var(...,
  FILTER_VALIDATE_INT) ?: null`) as the trailing argument.
- `$refType` becomes `'tp_invoice'`, `$refId` stays `$inv_num`.
- CP-sourced path (`debitCp`/`lockAndGetCpQty`/`insertCpLedger`) is left
  completely untouched — no warehouse involved.
- Wrap the new `StockService::deduct()` call in the same
  `externalTransaction = true` pattern already used elsewhere in this
  file's surrounding transaction.
- Catch `StockException` the same way every other refactored flow does
  (insufficient stock → user-facing error, not a raw SQL failure).

**Edit flow (`edit-tp-invoice-action.php`):** must apply the same
`warehouse_id` threading wherever it re-derives/reverses the original
debit — read the current file before implementing to confirm its
reversal mechanism (likely also hand-rolled raw SQL mirroring
`tp-invoice-action.php`, needs the equivalent refactor).

### 2. OT channel invoice

**Files:** `femi9/billing/company/ot-sale-add.php` (form),
`femi9/billing/company/ot-sale-action.php` (submit — deduction at line
~253, edit-delta adjustment at lines ~476/478).

**Schema:** add nullable `warehouse_id INT NULL` to `ot_sales` (the table
that already stores `godownid` per row).

**UI (`ot-sale-add.php`):** add a "Godown (physical)" dropdown next to
the existing `godownid` company-profile dropdown (line ~240). Also:
while implementing this, apply the same `godown_finance_filter_sql()`
filter to the existing `godownid` query (line ~120) that every other
flow already has — this is directly touched by this task (same query
block gaining a sibling warehouse dropdown) rather than a drive-by fix
elsewhere, so it's in scope here.

**Action handler:** thread `warehouse_id` (from POST,
`filter_var(..., FILTER_VALIDATE_INT) ?: null`) into both `otDeduct()`
call sites (line ~253 for new sales, ~476/478 for edit-delta
reverse/re-deduct), matching `otDeduct`'s existing trailing
`?int $warehouseId = null` parameter. Drafts (`$isDraft`) continue to
skip stock deduction entirely — the warehouse field is still captured
and stored on `ot_sales`, but StockService isn't called until
confirm/non-draft submit, unchanged from today's logic.

### 3. SS (Super Stockist) invoice

**Files:** `femi9/billing/super-stockist/user-invoice-add.php` (form),
`user-invoice-submit.php` (deduction, via the shared
`invoice-stock-update.php` — see Flow 4/5 below, since SS invoice reuses
this same include).

**Schema:** SS invoice writes to the same `user_invoice` /
`user_invoice_items` tables as shop invoice — the `warehouse_id` column
added to `user_invoice` in Flow 5 below covers this flow too.

**UI:** add a standalone "Godown (physical)" dropdown on
`user-invoice-add.php` (no preceding company-profile step — the hidden
`godownid` field already fixes the source to the SS's own account,
unchanged). New field `warehouse_id`, optional.

**Wiring:** `user-invoice-submit.php` persists the picked `warehouse_id`
onto the `user_invoice` row it creates, alongside `from_user_type`/
`from_user_id` — `invoice-stock-update.php` (shared with shop invoice)
then reads it back and passes it to `StockService::deduct()`.

### 4 & 5. Customer invoice (picker-based) and Shop invoice

Both route through the same shared include,
`femi9/billing/company/invoice-stock-update.php` — one change covers
both flows' deduction side.

**Files:**
- `femi9/billing/company/customer-user-invoice-add.php` (form, dropdown
  at line ~1447), `customer-user-invoice-action.php` /
  `-action2.php` (submit steps), `customer-user-invoice-submit.php`
  (final step, includes the shared file).
- `femi9/billing/company/shop-user-invoice-add.php` (form, dropdown at
  line ~635), `shop-user-invoice-action.php` / `-action2.php`,
  `shop-user-invoice-submit.php` (includes the shared file).
- `femi9/billing/company/invoice-stock-update.php` (shared deduction
  logic — the one place to change for both flows).

**Schema:** add nullable `warehouse_id INT NULL` to both `invoice` (used
by the customer-invoice picker path via its `user_id`/`user_type`
columns) and `user_invoice` (used by shop invoice, and SS invoice per
Flow 3 above).

**UI (both add-pages):** add a "Godown (physical)" dropdown next to each
existing `godownid` company-profile dropdown. Persist the chosen value
through each flow's own action/action2/submit chain the same way
`godownid` itself is threaded today (read `git grep godownid` in each
action file to confirm the exact carry-through columns before
implementing — likely a hidden field re-posted between action steps,
mirroring the existing `godownid` pattern).

**Shared deduction change (`invoice-stock-update.php`):**
- For the customer-invoice branch (`$is_customer_invoice === true`):
  read `$inv['warehouse_id']` alongside the existing `$inv['user_type']`/
  `$inv['user_id']` (line ~31-32).
- For the shop/SS branch: read `$inv['warehouse_id']` alongside
  `$inv['from_user_type']`/`$inv['from_user_id']` (line ~48-49).
- Pass the resolved `$warehouseId` as the trailing argument to both the
  seller-side `deduct()` call (line ~103) and, since a warehouse is a
  seller-side physical location concept, NOT to the buyer-side `credit()`
  call (line ~108) — the buyer's own stock (if they maintain one) isn't
  necessarily received into the same physical warehouse naming scheme;
  leave buyer-side warehouse tagging out of scope for this document (no
  existing UI captures a buyer-side warehouse anywhere in this spec).

---

## Testing strategy

Following this repo's manual-test convention (disposable MySQL schema,
`php <file>.php`, no PHPUnit — see
`femi9/billing/includes/tests/InternalTransferWarehouseKeyTest.php` for
the most recent example):

- **TP invoice**: new test exercising the refactored
  `StockService::deduct()`-based godown path with a real `warehouse_id`,
  confirming (a) stock deducts from the correct warehouse row, (b) an
  unrelated warehouse/unassigned row for the same product+entity is
  untouched, (c) the ledger's `action`/`ref_type` values match the new
  `deduct`/`tp_invoice` convention, (d) the CP-sourced path is completely
  unaffected (regression check, not new behavior).
- **OT channel invoice**: extend or add a test around `otDeduct()`'s
  warehouse-tagged behavior (may already be partially covered by
  `StockServiceWarehouseKeyTest.php`'s generic StockService coverage —
  confirm before writing new assertions, avoid duplicating existing
  coverage).
- **Shared `invoice-stock-update.php`**: new test simulating both branches
  (customer-invoice and shop/SS-invoice) with a `warehouse_id` set on the
  `invoice`/`user_invoice` fixture row, confirming `deduct()` receives it
  and `credit()` does not.
- **Migration safety**: for each of the 4 tables gaining a
  `warehouse_id` column (`tp_invoices`, `ot_sales`, `invoice`,
  `user_invoice`), confirm via `SHOW INDEX`/row count check (same pattern
  used in every prior phase) that the live table's existing row count is
  unaffected by the additive, nullable column.

## Migration / rollout safety

All 4 new columns are nullable, additive `ALTER TABLE ... ADD COLUMN
warehouse_id INT NULL` statements — no default-value backfill needed,
no existing row is touched, consistent with every migration so far in
this project (`stock.warehouse_id`, `stock_ledger.warehouse_id`, and
this session's `tp_purchase_orders.preferred_cp_id` fix all follow this
same additive pattern).

## Open questions for implementation time

- **Edit-flow reversal correctness for TP invoice**: `edit-tp-invoice-action.php`
  hasn't been read in detail yet — its exact reversal mechanism (does it
  call the same raw-SQL helpers, or something else?) needs confirming
  before the refactor is applied there, since a mismatched
  warehouse-scoped reversal could under- or over-credit stock back.
- **Customer/shop invoice's multi-step action/action2/submit carry-through**:
  the exact mechanism each flow uses to pass `godownid` between its
  action steps (hidden field vs session vs DB round-trip) needs
  confirming per-flow before adding `warehouse_id` alongside it, to avoid
  it silently getting dropped on one of the intermediate steps.
