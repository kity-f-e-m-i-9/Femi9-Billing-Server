# Per-Godown Split Stock — Design

## Background

The company wants to track physical storage locations ("godowns" — e.g.
H1, G1, G2) as a dimension of stock, independent of which company entity
(Femi Nayan LLP / Femi Health Care / Neksomo Hygiene Industries) the stock
belongs to. A single godown can hold stock from any/all entities mixed
together.

A first slice of this shipped 2026-09-17:

- `warehouses` table (`id`, `code`, `name`, `is_active`) — company creates/
  manages godowns via `femi9/billing/company/manage-warehouses.php`.
- `stock.warehouse_id` — nullable column, added but **not** part of any
  unique key (informational tag only, not currently load-bearing).
- `warehouse_users` table + a new central-login type `warehouse` (see
  `femi9/billing/shared/user-config.php`) — a read-only login at
  `femi9/billing/warehouse/` that lets someone pick a godown and see
  `SUM(stock.closing_qty)` per product for stock rows tagged to it.

This was deliberately named "warehouse" in code/schema (UI may still say
"Godown") to avoid colliding with two unrelated existing uses of the word
"godown" already in this codebase: `company_godown` (the LLP/Healthcare/
Neksomo company entities themselves) and the stockist/super_distributor
internal-transfer "godown" workflow (`internal_transfer` table).

**The gap:** `stock` has a UNIQUE key `uq_stock_entity (product_id,
user_type, user_id)` — exactly one row per product per company entity,
today. `warehouse_id` on that row is just a label; it cannot represent a
product actually being split across two godowns at once (e.g. 300 units
physically in H1 and 200 in G1 for the same LLP product). All of
`StockService.php`'s read/write methods lock and update that single row
by the 3-column key, with no warehouse awareness.

This spec covers making that split real: the same product/entity can have
independent stock quantities in more than one godown simultaneously, and
every stock-mutating workflow correctly reads/writes the right godown's
row.

## Goals

- A product's stock for a given company entity can be split across
  multiple godowns, each with its own `closing_qty`.
- Every stock-mutating workflow (sales deduction, internal transfers,
  returns, demo/free, OT channel, Neksomo purchase, Add Input Stock)
  operates on the correct godown's row, not a blended total.
- FIFO cost lots (`stock_lots`) are consumed from the correct godown's
  inventory, so Gross Profit costing doesn't cross-contaminate godowns.
- Existing stock (pre-migration) keeps working with `warehouse_id = NULL`
  treated as a single implicit "unassigned" godown per entity — no forced
  data-entry event to keep using the system.
- The `warehouse` login's dashboard becomes accurate: it already sums by
  `warehouse_id`, so once splitting is real, its numbers reflect actual
  per-godown stock rather than a same-row snapshot.

## Non-goals

- No changes to how `company_godown` (LLP/Healthcare/Neksomo) or
  stockist/super_distributor's internal-transfer "godown" concept work —
  those remain as-is.
- No warehouse-transfer UI in the new `warehouse` login — it stays
  read-only (confirmed decision from the original brainstorm).
- No retroactive backfill that *infers* which existing stock belongs in
  which godown — pre-migration stock stays tagged `NULL` ("unassigned")
  until someone explicitly moves it.
- Not extending split-stock tracking to stockist/super_distributor/
  super-stockist/distributor login areas in this phase — scope is the
  company area only (LLP/Healthcare/Neksomo), where the godown concept
  was requested. Their copies of StockService are unaffected.

## Key design decision: extend the identity key

`stock`'s unique key becomes `(product_id, user_type, user_id,
warehouse_id)`, with `warehouse_id` nullable and a `NULL` treated as its
own distinct identity (MySQL already allows multiple `NULL`s in a unique
key, which conveniently matches "unassigned" being effectively "whichever
godown-less bucket this entity has always used").

Every method in `StockService.php` that currently locks/reads/writes
`stock` by `(product_id, user_type, user_id)` gains a fourth parameter,
`?int $warehouseId`, and includes it in the `WHERE`/`INSERT` clause.
Callers that don't yet have a meaningful godown to pass (most call sites,
initially) pass `null`, preserving today's behavior exactly for anyone
who never assigns a godown.

This is additive at the call-site level (new optional parameter,
defaulting to `null`) — it does not require every one of the ~90
call-sites to change behavior on day one, only to compile/run against the
new signature. Passing `null` everywhere reproduces current behavior
bit-for-bit, since `NULL = NULL` matching plus the existing 3-column
lookups is exactly today's key when no warehouse is involved.

## Phases

### Phase 1 — Schema + StockService core (foundation, no visible behavior change)

- Migration: drop `uq_stock_entity`, add `uq_stock_entity_warehouse
  (product_id, user_type, user_id, warehouse_id)`.
- Update `femi9/billing/company/include/StockService.php`:
  - `lockStockRow()`, `updateStockSnapshot()`, `writeLedger()` gain
    `?int $warehouseId = null` and include it in their WHERE/INSERT.
  - All 14 public mutating methods (`deduct`, `credit`, `reverseDeduct`,
    `reverseCredit`, `deductAndCredit`, `reverseAll`, `acceptReturn`,
    `rejectReturn`, `otDeduct`, `otReverse`, `transferOut`, `transferIn`,
    `reverseTransferOut`, `reverseTransferIn`) gain the same optional
    parameter, threaded through to the private helpers.
  - `getClosingQty()` and `hasLedgerEntry()` gain the same parameter for
    read-path consistency.
  - `stock_ledger` gains a nullable `warehouse_id` column so the audit
    trail records which godown a movement affected.
- No caller changes yet — every existing call site keeps compiling and
  behaving identically by relying on the `null` default.
- Unit/manual verification: confirm a fresh `deduct()`/`credit()` cycle
  with no warehouse still produces the exact same `stock` row as before.

### Phase 2 — Raw-SQL bypass workflows

Two workflows mutate `stock` directly instead of through StockService,
and both need the same key extended by hand:

- `femi9/billing/company/input-action.php` (Add Input Stock) — add a
  godown picker to `add-input.php`, thread `warehouse_id` through
  `stmtChkProd`/`stmtInsertStock`/`stmtGetStock`/`stmtUpdateStock` in
  `input-action.php`. (This is the fix for the bug caught during Phase 0
  prototyping: today's check/update statements ignore `warehouse_id`
  entirely, so a second input for the same product silently reuses
  whichever godown the first input happened to set — that bug does not
  exist once the key includes `warehouse_id`, since a different godown
  selection becomes a different row rather than an ambiguous match.)
- `femi9/billing/company/stock_return_update.php` (Stock Return) — same
  treatment for its raw `SELECT ... FOR UPDATE` / `UPDATE stock SET
  returnqty=..., closing_qty=...` pair. Needs a way to know which
  godown's stock the return applies to (likely: the godown recorded on
  the original outbound movement, via `stock_ledger.warehouse_id` from
  Phase 1 — needs confirmation against how returns look up their source
  movement).
- `femi9/billing/company/neksomo-manufacturer-purchase-action.php` — the
  `extra_pieces` raw `UPDATE stock SET extra_pieces=? WHERE product_id=?
  AND user_type='company' AND user_id=?` needs `warehouse_id` added to
  its WHERE once whole-pack `credit()` calls (same file) start passing a
  real godown.

### Phase 3 — Wire real godown selection into workflows

Once the mechanism exists (Phases 1–2), pick which workflows actually let
a user choose or infer a godown, in priority order to be confirmed with
the user:

1. Add Input Stock — explicit godown picker (the natural "stock arrives"
   moment).
2. Internal transfers (`internal_transfer_action.php`) — `transferOut`/
   `transferIn` already model movement between two things; extending
   that concept to also carry a godown-in/godown-out pair is a natural
   fit, but needs its own design pass since `internal_transfer` already
   has an unrelated "godown" meaning (stockist-side) that must not be
   confused with warehouses here.
3. Neksomo manufacturer purchase, LLP rate-entry-driven stock (if/when
   that path starts writing to `stock` directly rather than only
   `stock_lots`).
4. Sales/invoice deduction (`invoice-submit.php`) — needs a way to know
   *which* godown a sale draws down, e.g. FIFO-across-godowns, a
   default/primary godown per entity, or a required selection at
   invoice time. This is the highest-uncertainty call site and should be
   scoped last, after the simpler in/transfer flows validate the
   mechanism.

Each workflow's rollout is its own small design decision and can ship
independently once Phase 1–2 land — this phase is intentionally left
open rather than fully speced now, so real usage from Add Input Stock
and internal transfers can inform how sales deduction should pick a
godown.

### Phase 4 — FIFO lot warehouse-awareness

`stock_lots` and `stock_ledger_lot_consumption` currently have no
`warehouse_id` at all — a lot recorded when stock arrives in H1 could
today be FIFO-consumed by a sale physically drawn from G1, silently
mispricing Gross Profit once godowns are real dimensions.

- Add nullable `warehouse_id` to `stock_lots`.
- `StockLots::recordLot()` gains `?int $warehouseId`.
- `StockLots::consumeFifo()`'s lot-selection query adds `warehouse_id`
  to its `WHERE`/lock, so a sale from a specific godown only draws from
  that godown's remaining lots.
- Existing lots (recorded before this phase) have `warehouse_id = NULL`
  — same "unassigned" bucket convention as `stock` itself, so they're
  only consumed by sales that are themselves warehouse-unaware.

This phase depends on Phase 3 establishing which workflows actually pass
a real godown when crediting stock (a lot can only be tagged to a
godown if the crediting event knows one).

### Phase 5 — Reports

`femi9/billing/company/overall-stock.php` currently does `SELECT * FROM
stock WHERE user_type=... AND user_id=...` with no `GROUP BY`, assuming
one row per product. Once splitting is real, this returns multiple rows
per product (one per godown) and needs either:

- A `GROUP BY product_id` aggregation for the existing "entity-wide
  total" view (sum across all its godowns), plus
- An optional godown filter/breakdown view, mirroring what the
  `warehouse` login's dashboard already does from the other direction.

Other reports that query `stock` directly should be audited at
implementation time for the same one-row-per-product assumption; this
spec does not enumerate every report file since `overall-stock.php` is
confirmed as the primary one and others can be caught via the same
`GROUP BY`-aware pattern once established there.

## Migration / rollout safety

- Because `warehouse_id` defaults to `NULL` and `NULL` participates in
  the unique key as its own identity, Phase 1's key change is a pure
  additive migration — no existing row changes shape or meaning, no
  existing row becomes ambiguous, and no data backfill is required to
  deploy it safely.
- Each phase after Phase 1 is independently revertible: if Phase 3's
  invoice-deduction godown selection turns out to need more design work,
  Phases 1–2 (Add Input Stock, Stock Return, transfers) can ship and be
  used on their own — sales simply keep drawing from the `NULL`
  ("unassigned") bucket until that phase is ready.

## Open questions for implementation time

- **Stock Return godown inference** (Phase 2): does `stock_return_update.php`
  have enough context today (e.g. a link back to the original invoice's
  `stock_ledger` row) to know which godown to credit back into, or does
  it need an explicit picker too?
- **Sales deduction godown selection** (Phase 3, item 4): FIFO-across-
  godowns vs. explicit selection vs. a per-entity default godown — needs
  its own brainstorm once Phases 1–2 are live and there's real usage
  data on how godowns get populated.
- **Internal transfer naming collision**: `internal_transfer_action.php`
  already has its own "godown" concept (stockist-side send_from/send_to).
  Phase 3, item 2 needs a naming/UX pass so users aren't shown two
  different things both called "godown" in the same transfer flow.
