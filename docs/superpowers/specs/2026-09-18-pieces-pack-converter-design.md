# Pieces ↔ Pack Converter — Design

## Background

Napkin/diaper products in this app are sold in packs, but purchased
piece-wise from Neksomo (the manufacturer). `products.pieces_per_pack`
already exists app-wide (set on ~15+ products, read by 21 files) as a
display/reporting ratio — but no explicit conversion action exists
anywhere. The only place piece→pack conversion actually happens today
is inside `neksomo-manufacturer-purchase-action.php`'s private helper
`neksomo_credit_pieces()`: it silently accumulates incoming pieces into
`stock.extra_pieces` (a hidden remainder column, never displayed
anywhere), and whenever that remainder reaches a full pack, credits one
pack to `stock.closing_qty` via `StockService::credit()`. There is no
UI for this, no way to see current loose-piece stock, no way to trigger
a conversion manually, and no reverse direction (breaking a pack back
into pieces).

This is architecturally distinct from the "composite item" idea
initially raised (a Zoho-style kit made of several *different*
products) — confirmed during brainstorming that what's wanted is
narrower: **the same product, tracked as pieces or as packs, with an
explicit conversion between the two**, not a multi-product bundle.

## Goals

- A manual, auditable "Convert Pieces ↔ Packs" action, in the Neksomo
  login, for any product with `pieces_per_pack > 1`.
- Both directions: assemble N loose pieces into 1 pack, or break 1 pack
  into N loose pieces.
- Warehouse-aware from the start — operates on `stock.extra_pieces`
  scoped by `(product_id, user_type, user_id, warehouse_id)`, the same
  key every other stock mutation in this app already uses.
- Every conversion writes a `stock_ledger` entry, matching the audit
  trail every other stock movement already has.
- Current loose-piece stock (`extra_pieces`) becomes visible on this
  new page (and only this page, for now — see Non-goals) instead of
  being a hidden internal remainder.

## Non-goals

- True multi-product kits/bundles (Zoho Composite Items) — explicitly
  ruled out during brainstorming; this is single-product unit
  conversion only.
- Company-wide availability — scoped to the Neksomo login only for
  this phase, per user decision. A company-wide "any warehouse, any
  product with a pack size" version is a possible future phase, not
  built now.
- Replacing `neksomo_credit_pieces()`'s existing auto-conversion inside
  the manufacturer purchase flow. That flow keeps working exactly as it
  does today — this is a separate, additive manual action a user can
  invoke any time, not a replacement of the automatic behavior that
  already runs on purchase entry.
- Surfacing `extra_pieces` on other existing reports (`overall-stock.php`,
  `neksomo-company-stock.php`, etc.) — those already display
  `ClosingStockPieces` computed as `(closing_qty * pieces_per_pack) +
  extra_pieces`, which already reflects any conversions made through
  this new page without further changes. No other report needs editing.

## Key design decision: reuse `extra_pieces`, don't add new schema

`extra_pieces` is already a real, warehouse-aware-capable column
(`stock.extra_pieces INT UNSIGNED NOT NULL DEFAULT 0`, sitting on the
same row as `closing_qty`, already keyed by `warehouse_id` since Phase 1
of the per-godown project). The only things it currently lacks are: (a)
any code path that scopes it by `warehouse_id`, (b) any UI that shows
it, (c) any way to write to it other than the one hidden purchase-flow
helper, (d) a reverse (pack→pieces) direction. This design closes all
four gaps without new tables or columns — `extra_pieces` graduates from
"private rounding remainder" to a first-class, visible, warehouse-scoped
quantity.

## Architecture

### New `StockService` method: `convertPiecesToPack()` / `convertPackToPieces()`

Two new public methods (not one bidirectional method — clearer call
sites, matches the existing `deduct`/`credit` and `transferOut`/
`transferIn` pairing convention already used throughout this class),
built entirely from existing private helpers (`lockStockRow()`,
`updateStockSnapshot()`, `writeLedger()`) — no new StockService
internals needed.

```php
/**
 * Assemble N loose pieces into 1 whole pack. N = piecesPerPack.
 * Decrements extra_pieces by piecesPerPack, increments closing_qty by 1.
 * Throws StockException if extra_pieces < piecesPerPack.
 */
public function convertPiecesToPack(
    int    $productId,
    string $userType,
    string $userId,
    int    $piecesPerPack,
    string $refId,
    string $createdBy,
    bool   $externalTransaction = false,
    ?int   $warehouseId = null
): array

/**
 * Break 1 whole pack into N loose pieces. N = piecesPerPack.
 * Decrements closing_qty by 1, increments extra_pieces by piecesPerPack.
 * Throws StockException if closing_qty < 1.
 */
public function convertPackToPieces(
    int    $productId,
    string $userType,
    string $userId,
    int    $piecesPerPack,
    string $refId,
    string $createdBy,
    bool   $externalTransaction = false,
    ?int   $warehouseId = null
): array
```

Both follow the exact transaction/lock/ledger shape already used by
every other StockService method:

1. `lockStockRow($productId, $userType, $userId, $warehouseId)` — `FOR
   UPDATE` lock. Throws `StockException` if no row exists (nothing to
   convert).
2. Validate sufficiency (`extra_pieces >= piecesPerPack` for
   pieces→pack; `closing_qty >= 1` for pack→pieces) — throws
   `StockException` on insufficient quantity, same pattern as
   `deduct()`/`transferOut()`.
3. `updateStockSnapshot()` with both fields changed in one call
   (`extra_pieces` and `closing_qty` move in opposite directions in the
   same row — no separate UPDATE statements needed).
4. `writeLedger()` with a new `action` value: `'pieces_to_pack'` or
   `'pack_to_pieces'`. `qty`/`qty_before`/`qty_after` record the
   **pack** quantity's before/after (matching every other ledger
   entry's convention of tracking `closing_qty`, the pack-based
   figure); the piece-side delta is implied by `piecesPerPack` and
   doesn't need its own ledger column.
5. No `StockLots`/FIFO interaction — a conversion doesn't change total
   value or create a new cost lot, it just reshapes an existing pack's
   worth of stock into pieces (or vice versa) at the same cost basis.
   This mirrors how `extra_pieces` conversions inside
   `neksomo_credit_pieces()` already don't touch `StockLots` for the
   piece-accumulation step, only for the completed-pack credit.

### `stock_ledger.action` values

Two new literal strings, `'pieces_to_pack'` and `'pack_to_pieces'` — no
schema change needed (`action` is already a free-text `VARCHAR`).

### New page: `femi9/billing/company/neksomo-piece-pack-convert.php`

Modeled on the existing Neksomo pages' structure (`checksession.php`,
`PermissionCheck.php`, `GodownAccess.php` includes; same Bootstrap/
SweetAlert2 UI as `neksomo-manufacturer-purchase.php`).

**Form fields:**
- Product picker — `<select>` populated from `products WHERE
  pieces_per_pack > 1 AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS
  NULL)` (same NKS-placeholder exclusion used throughout the Neksomo
  UI), searchable (Select2, matching the pattern established on other
  recently-touched pages).
- Warehouse picker — same `warehouses WHERE is_active = 1` dropdown
  used everywhere else in this project, "— Not tracked —" for
  unassigned, required to force a deliberate choice (this page is
  specifically about warehouse-aware stock, so defaulting to
  "unassigned" silently would undercut the point of building it
  warehouse-aware).
- Direction — two radio buttons or a toggle: "Pieces → Pack" / "Pack →
  Pieces".
- Quantity — for pieces→pack, "how many packs to assemble" (each pack
  consumes `piecesPerPack` pieces); for pack→pieces, "how many packs to
  break open" (each pack yields `piecesPerPack` pieces). Both directions
  take a pack-count, not a raw piece-count, since that's the unit the
  user is actually deciding on ("assemble 3 packs' worth" is a more
  natural ask than "consume 36 pieces").
- Live current-stock display — once product + warehouse are picked, an
  AJAX call (new small endpoint, or inline on page load per
  product+warehouse combo) shows current `closing_qty` (packs) and
  `extra_pieces` (loose pieces) for that exact row, so the user can see
  what's available before submitting.

**Action handler**: `neksomo-piece-pack-convert-action.php` — CSRF
check, re-validates ownership/permission, calls
`convertPiecesToPack()`/`convertPackToPieces()` in a loop for the
requested pack-count (or a single call with `$piecesPerPack *
$packCount` — see Open Questions), catches `StockException` for
insufficient-stock errors, redirects with a success/error flash message
matching every other action handler's convention in this codebase.

### Menu entry

`femi_menu.php`'s existing Neksomo "Stock" submenu (the same block
containing `neksomo-purchase-stock.php`) gains one new link: "Convert
Pieces ↔ Packs" → `neksomo-piece-pack-convert.php`.

## Data flow example

Product X has `pieces_per_pack = 12`. Current stock at warehouse H1:
`closing_qty = 5` (packs), `extra_pieces = 8` (loose pieces).

- User converts "1 pack" via Pieces → Pack, requesting to assemble
  enough pieces for 1 more pack: needs 12 pieces, only has 8 →
  `StockException("Insufficient pieces...")`, form shows the error,
  nothing changes.
- User instead converts "1 pack" via Pack → Pieces (breaking a pack
  open): `closing_qty` 5→4, `extra_pieces` 8→20. Ledger row:
  `action='pack_to_pieces', qty=1, qty_before=5, qty_after=4`.
- With `extra_pieces` now 20, a follow-up Pieces → Pack conversion of
  "1 pack" succeeds: needs 12, has 20 → `closing_qty` 4→5,
  `extra_pieces` 20→8. Ledger row: `action='pieces_to_pack', qty=1,
  qty_before=4, qty_after=5`.

## Testing strategy

Following this repo's manual-test convention (disposable MySQL schema,
`php <file>.php`, no PHPUnit):

- New `StockServicePiecesPackConvertTest.php` covering: successful
  pieces→pack conversion decrements `extra_pieces`/increments
  `closing_qty` correctly; successful pack→pieces does the reverse;
  insufficient pieces/packs throws `StockException` and changes
  nothing; warehouse scoping (converting in warehouse H1 doesn't touch
  G1's or the unassigned bucket's row for the same product); ledger
  entries record the correct `action`/before/after values.
- Manual browser verification of the new page + action handler once
  implemented (same precedent as every UI page in this project — skip
  only if MAMP isn't running, documented honestly if so).

## Migration / rollout safety

No schema migration needed — `extra_pieces` already exists on `stock`
with a safe default (`0`). This spec adds only new PHP code (two
StockService methods, one page, one action handler, one menu link).
Fully backward compatible: existing `neksomo_credit_pieces()` auto-
conversion behavior in the purchase flow is completely untouched.

## Open questions for implementation time

- **Single StockService call vs. loop for multi-pack conversions**: if
  a user requests "convert 3 packs' worth," should the action handler
  call `convertPiecesToPack()` three times (three ledger rows, matching
  how `deduct()` etc. are typically called once per line item
  elsewhere), or should the StockService methods themselves accept a
  `$packCount` parameter and do the multiply internally (one ledger row
  per conversion request, `qty` reflecting the full pack count)? Leaning
  towards the latter (one ledger row per user action is more legible in
  an audit trail than N identical rows), but worth confirming against
  how `writeLedger()`'s `qty` column is expected to read elsewhere
  before locking in the method signature's parameter list.
- **AJAX current-stock display**: whether to build a small dedicated
  endpoint (e.g. `get-piece-pack-stock.php?product_id=&warehouse_id=`)
  or inline the lookup via a page reload on product/warehouse change —
  the codebase has precedent for both patterns (`get-godown-products.php`-
  style AJAX vs. `loadopeningstock.php`-style page-embedded checks);
  pick whichever fits fastest once the page's actual JS interaction
  model is being built.
