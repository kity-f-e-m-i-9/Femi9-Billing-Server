# Warehouse-Aware Auto Transfer — Design

## Background

The per-godown split-stock project ([[per-godown-split-stock-project]])
introduced `warehouse_id` as a stock dimension independent of company
entity (`company_godown` — LLP / Healthcare / Neksomo). Today that
independence is total: any warehouse can hold any entity's stock, with no
recorded relationship between the two, and no UI anywhere lets a user
pick "this entity, in that warehouse" as a pair.

Auto Transfer for Orders (`internal_transfer_auto.php` /
`internal_transfer_auto_action.php`) moves stock through a fixed
two-leg chain — Neksomo → Healthcare → LLP — with **no warehouse
selection at all**: `getClosingQty()`/`transferOut()`/`transferIn()`
are called with `$warehouseId` omitted (defaults to `null`), so every
Auto Transfer run always operates on each entity's "unassigned" stock
row, regardless of which physical warehouse the goods are actually in.

Manual Internal Transfer (`internal_transfer.php` /
`internal_transfer_action.php`) already has `warehouse_from_id`/
`warehouse_to_id` pickers (Phase 3 of the original spec) — but they show
every active warehouse unfiltered, with no relationship to the two
company profiles (`send_from`/`send_to`) chosen alongside them.

This design closes both gaps: it introduces a real company-profile ↔
warehouse relationship, and uses it to filter (never hard-block) the
warehouse pickers on both Auto Transfer and manual Internal Transfer.

## Goals

- A new many-to-many mapping between `company_godown` rows and
  `warehouses` rows, editable via an admin UI.
- Auto Transfer for Orders gains two pickers per product row — Source
  Godown (physical) and Destination Godown (physical) — filtered by the
  mapping, and the row's Required/Available figures recompute against
  the selected source warehouse.
- Manual Internal Transfer's existing two pickers become filtered by the
  mapping (advisory, not a hard requirement) based on the `send_from`/
  `send_to` company profile already selected on that form.
- Both legs of an Auto Transfer run persist the warehouse actually used,
  so Transfer History displays it and Undo reverses against the correct
  warehouse.
- Every change here is additive and backward-compatible: existing data
  (all `warehouse_id = NULL`) and existing behavior (transfer with no
  warehouse picked) continue working exactly as today.

## Non-goals

- No hard enforcement anywhere. The mapping filters dropdown options; no
  backend write path refuses an (entity, warehouse) combination that
  isn't in the mapping. Existing/historical data with mismatched or
  NULL warehouse tags is never validated against the mapping.
- No backfill of historical `internal_transfer`/`stock_ledger` rows.
  Every Auto Transfer run before this ships keeps `warehouse_id = NULL`
  on both legs; Transfer History/Undo for those runs behaves exactly as
  it does today.
- No change to Convert Pieces↔Packs, Add/Edit Neksomo Purchase, OT
  sales, or invoice flows' own warehouse pickers — those already exist
  and already show the unfiltered warehouse list; this document does not
  retrofit the mapping onto them. (They may gain it in a later,
  separately-scoped pass.)
- No change to which company profiles Auto Transfer's chain itself uses
  (still hardcoded Neksomo → Healthcare → LLP by name lookup) — only
  *which warehouse* each leg uses becomes selectable, not the entities
  themselves.
- No FK constraints on the new mapping table — consistent with the rest
  of this schema, which uses application-level checks, not DB-level FKs.

## Data model

### New table: `company_godown_warehouses`

```sql
CREATE TABLE company_godown_warehouses (
  company_godown_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (company_godown_id, warehouse_id)
);
```

Many-to-many: one warehouse can be linked to several company profiles,
one company profile can be linked to several warehouses. No row means
"not linked" — filtering treats an unlinked pair as simply absent from a
given dropdown, never as an error.

**Self-migrating.** Created via a guard function
`ensure_company_godown_warehouses_table($db_conn)` in a new shared
include (`femi9/billing/company/include/GodownWarehouseMapping.php`),
called by every reader/writer — same convention as every other table
added in this project (e.g. `ensure_auto_transfer_skip_table()`), since
a manual-apply-only migration file has twice caused production
incidents on this exact feature area (`stock_lots.warehouse_id`,
`neksomo_manufacturer_purchases.warehouse_id`) this session.

A migration file is still checked in
(`db_migrations/2026_09_21_company_godown_warehouses.sql`) for
documentation/manual-apply convenience, but nothing depends on it having
run — every call site is guarded.

### Helper functions (`GodownWarehouseMapping.php`)

```php
function ensure_company_godown_warehouses_table(mysqli $db_conn): void;

// Warehouses linked to one company_godown id. Empty array = nothing
// linked yet (not an error — caller shows an unfiltered/empty list per
// its own fallback policy, see UI sections below).
function get_warehouses_for_godown(mysqli $db_conn, int $companyGodownId): array; // [{id, code, name}]

// Company profiles linked to one warehouse id — powers the admin
// editor's per-warehouse checkbox list.
function get_godowns_for_warehouse(mysqli $db_conn, int $warehouseId): array; // [{id, gname}]

// Replaces the full set of company_godown_ids linked to one warehouse
// (delete + re-insert, simplest correct approach for a small admin form).
function set_godowns_for_warehouse(mysqli $db_conn, int $warehouseId, array $companyGodownIds): void;
```

## Admin UI: extend `manage-warehouses.php`

Each warehouse's row in the existing management table gains a "Linked
Company Profiles" column — a small multi-select (checkboxes in a
dropdown, or an inline `<select multiple>`, matching this page's
existing plain-Bootstrap style) listing every company profile returned
by `godown_finance_filter_sql()` (the same non-finance-only filter
Convert Pieces↔Packs already uses for its Company Profile picker).

Submitting the existing per-row edit form (already posts `action=edit`
per warehouse) also posts the selected company profile ids; the action
handler calls `set_godowns_for_warehouse()` in the same request,
alongside the existing code/name update. No new page, no new route —
extends the current CRUD form.

## Auto Transfer for Orders — per-row warehouse pickers

### Server-side (`internal_transfer_auto.php`)

For each product row, two new dropdowns:

- **Source Godown (physical)** — options = union of
  `get_warehouses_for_godown($neksomoId)` and
  `get_warehouses_for_godown($healthcareId)` (either could be the actual
  source leg depending on split availability). If the union is empty
  (nothing mapped yet), fall back to the full active-warehouse list —
  same "advisory, don't hard-block, never show a dead end" policy as
  everywhere else in this design.
- **Destination Godown (physical)** — options =
  `get_warehouses_for_godown($llpId)`, same empty-fallback rule.

Both start unselected (no default warehouse) — matching every other
optional warehouse picker in this app. Required/Available figures for a
row are computed against `warehouse_id = NULL` (today's behavior)
**until the user picks a source warehouse**, at which point an AJAX call
recomputes them scoped to that warehouse.

### Client-side recompute

New endpoint `get-auto-transfer-row-availability.php` (mirrors
`get-piece-pack-stock.php`'s shape): given `product_id` +
`source_warehouse_id`, returns `{neksomo_avail, healthcare_avail}`
computed via `StockService::getClosingQty()` scoped to that warehouse.
On change of either row's Source Godown select, JS re-fetches and
updates that row's availability chips and re-runs the existing
`cap_auto_transfer_qty_by_source()` capping client-side (mirroring the
PHP logic already present for the initial page-load values) to refresh
the "Qty to Transfer" default.

### Submission (`internal_transfer_auto_action.php`)

Two new POST array fields, `warehouse_from[]` / `warehouse_to[]`,
one per product row (parallel to the existing `product_id[]`/`qty[]`/
`rate1[]`/`rate2[]`), each `filter_var(..., FILTER_VALIDATE_INT) ?: null`.

`$writeLeg` gains two trailing parameters,
`?int $sourceWarehouseId, ?int $destWarehouseId`:

```php
$available = $stockService->getClosingQty($pid, $Login_user_TYPEvl, $sendFrom, $sourceWarehouseId);
...
$outResult = $stockService->transferOut(
    $pid, $Login_user_TYPEvl, $sendFrom, $actualQty,
    'transfer', $tempid, $createdBy, true, $sourceWarehouseId
);
$stockService->transferIn(
    $pid, $Login_user_TYPEvl, $sendTo, $actualQty,
    'transfer', $tempid, $createdBy, true,
    $outResult['consumed_rate'] ?? null, $destWarehouseId
);
```

Called per-row as:
- Leg 1 (Neksomo → Healthcare): `$writeLeg($tempid1, $invNumber1, $neksomoId, $healthcareId, $pid, $requestedQty, $row['rate1'], $sourceWarehouseId, $sourceWarehouseId)` — the intermediate Healthcare leg lands in the **same physical warehouse** the source stock came from (goods don't teleport to a different building mid-transfer; only the owning company profile changes at this hop).
- Leg 2 (Healthcare → LLP): `$writeLeg($tempid2, $invNumber2, $healthcareId, $llpId, $pid, $legOneQty, $row['rate2'], $sourceWarehouseId, $destWarehouseId)` — source side is the same warehouse Leg 1 credited into; destination side is the user's chosen Destination Godown.

This makes "same warehouse for both legs" and "different warehouse at the
end" both fall out naturally from one consistent rule (each leg's source
= wherever the previous leg actually placed the stock), matching the
"separate picker per leg" decision while staying physically coherent —
stock is never invented in or vanished from a warehouse it never passed
through.

## Manual Internal Transfer — filter existing pickers

`internal_transfer.php`'s existing `warehouse_from_id`/`warehouse_to_id`
selects (currently: full unfiltered active-warehouse list, static HTML)
become populated via the same `get_warehouses_for_godown()` +
empty-fallback rule, re-filtered client-side whenever the `send_from`/
`send_to` company-profile select changes (an AJAX call returning the
filtered `{id, code, name}` list for the newly-picked company profile,
re-rendering that one `<select>`'s `<option>`s) — mirroring the
company-profile-driven filtering pattern this design already introduces
for Auto Transfer, applied to the one existing form that has the same
two-picker shape.

`internal_transfer_action.php` itself needs no change — it already
threads `$warehouseFromId`/`$warehouseToId` straight through to
`transferOut()`/`transferIn()`.

## Undo / Transfer History

No schema change. `stock_ledger.warehouse_id` is already written by
`transferOut()`/`transferIn()` on every call (today always `null` for
Auto Transfer runs, since no warehouse is passed). Once Auto Transfer
starts passing real values:

- `get_auto_transfer_history_for_date()`/`_grouped_for_date()` (already
  reading `qty_before`/`qty_after`/`created_at` from `stock_ledger`)
  gain `sl_out.warehouse_id AS neksomo_warehouse`,
  `sl_in2.warehouse_id AS llp_warehouse` to their existing SELECT list,
  surfaced in the Transfer History modal as an extra "Godown" column per
  run/product. `NULL` renders as "—" (unassigned), same convention as
  every other nullable figure that table already shows.
- `undo_auto_transfer()` already calls `reverseTransferIn()`/
  `reverseTransferOut()` scoped by `userId` — it gains the warehouse
  argument too, read back from the same `internal_transfer`/
  `stock_ledger` rows being undone (not re-derived from the UI), so an
  Undo always reverses against exactly the warehouse the original run
  actually used, regardless of what the warehouse pickers currently show
  on screen.

## Testing strategy

Following this repo's manual-test convention (disposable schema,
`php <file>.php`, no PHPUnit):

- **`GodownWarehouseMapping.php`**: new test —
  `ensure_company_godown_warehouses_table()` self-migrates and is
  idempotent; `get_warehouses_for_godown()`/`get_godowns_for_warehouse()`
  round-trip correctly; `set_godowns_for_warehouse()` correctly replaces
  (not appends) a warehouse's linked profiles.
- **Auto Transfer action**: extend or add a test confirming
  `$writeLeg`'s new warehouse parameters land in the right
  `stock`/`stock_ledger` rows — Leg 1's destination-side credit and Leg
  2's source-side deduct both land in the SAME warehouse row (the
  "stock doesn't teleport mid-hop" rule), and Leg 2's destination lands
  in the user's chosen Destination Godown, distinct from Leg 1's
  warehouse when they differ.
- **Undo with warehouse**: a run using two different warehouses
  (source ≠ destination) undoes correctly — both legs' stock is
  restored to the exact warehouse rows it came from, never blended into
  an unassigned row.
- **Manual Internal Transfer regression**: confirm existing
  `InternalTransferWarehouseKeyTest.php` coverage still passes unchanged
  (this design doesn't alter `internal_transfer_action.php`'s own
  logic, only the form's dropdown population) — no new assertions
  required there beyond a read-through check.

## Migration / rollout safety

The new table is additive (`CREATE TABLE IF NOT EXISTS`-equivalent via
the self-migrating guard) and starts empty — every existing warehouse
picker across the app falls back to the full unfiltered list until an
admin actually links profiles via `manage-warehouses.php`, so this ships
with zero behavior change until someone opts in by filling in the
mapping.

## Open questions for implementation time

- **Union-vs-per-side source list**: Source Godown's option list is
  specified as the union of Neksomo's and Healthcare's linked
  warehouses. If a future admin wants a stricter "only warehouses linked
  to Neksomo specifically" list, this may need revisiting — deferred
  since Auto Transfer's `$writeLeg` already re-validates actual
  available qty at commit time regardless of which entity the picker
  implies, so an overly-permissive source list can't cause incorrect
  stock movement, only a confusing dropdown in a rare edge case.
- **AJAX endpoint reuse**: whether
  `get-auto-transfer-row-availability.php` should be a genuinely new
  file or a small addition to the existing `get-piece-pack-stock.php`
  (which already does a near-identical "product + warehouse → available
  qty" lookup) — leaning new-file, since Auto Transfer's shape (two
  entities, not one) doesn't cleanly fit that endpoint's existing
  response contract, but worth a quick look at implementation time
  before committing to duplicating the lookup logic.
