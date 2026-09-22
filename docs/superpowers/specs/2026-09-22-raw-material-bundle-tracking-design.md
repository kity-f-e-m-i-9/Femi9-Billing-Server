# Raw Material Bundle Tracking — Design

## Background

Raw material for Neksomo's mapped products (e.g. "330mm XL (9 PCS)" ←
raw product 18) currently enters the system via **Add Purchase from
Manufacturer** (`neksomo-manufacturer-purchase.php`), which credits an
exact, invoice-backed piece count straight onto the raw product's
`stock` row.

The business wants to switch to buying raw material as physical
**bundles**, each nominally holding a fixed piece count (e.g. 1000) —
but the actual count per bundle is never known at intake. It's only
discovered gradually, as that specific bundle is drawn down by Convert
Pieces↔Packs conversions: a bundle might run dry before nominal is
reached (**shortage**), or still have pieces left after nominal should
have been exhausted (**excess**) — and operators need the system to
surface this, not silently absorb it into a blended pool.

Confirmed operational detail: a bundle is normally dedicated to one
finished-product variant end-to-end, but can legitimately be split
across two variants mid-stream (e.g. half of a bundle converted into
330mm 9pcs, production pauses for a packing-material shortage, a new
bundle is opened for 330mm 6pcs, and the first bundle's remainder is
finished later).

## Goals

- Input Stock gains a "Raw Bundles" entry mode: pick a raw product,
  company profile, physical godown, nominal pieces per bundle, and
  bundle count — creates that many individually-trackable bundle
  records, each starting at nominal pieces remaining.
- Convert Pieces↔Packs, for a mapped finished product, requires picking
  a **specific open bundle** to convert from (scoped to that raw
  product + company profile + physical godown), instead of drawing from
  a blended company-wide raw pool as it does today.
- A bundle's `remaining_pieces` is decremented by each conversion drawn
  from it, and is allowed to go **negative** — a negative value is
  itself the excess signal (the bundle physically held more than
  nominal). No separate reconciliation step is needed to detect excess.
- An explicit **Close Bundle** action marks a bundle finished. At close:
  `remaining_pieces > 0` → shortage (bundle never reached nominal);
  `remaining_pieces < 0` → excess (bundle exceeded nominal);
  `remaining_pieces == 0` → exact. The closed bundle's final state is
  the permanent variance record — no further draws allowed after close.
- A bundle list page shows every bundle (open and closed), its raw
  product, remaining/nominal, and variance once closed — this is the
  business's shortage/excess visibility.
- Underlying `stock.closing_qty` for the raw product stays the single
  source of truth for "how much raw material is actually available
  company-wide" — bundle tracking is an *additional* layer on top,
  never a replacement for the existing stock/stock_ledger mechanism.
  Convert Pieces↔Packs' existing `StockException` on insufficient
  `stock` balance remains the true hard limit; a bundle's own
  `remaining_pieces` going negative is a separate, softer signal
  (excess *of that specific bundle*, not of overall stock).

## Non-goals

- No changes to `neksomo-manufacturer-purchase.php` (Add/Edit/Delete
  Purchase from Manufacturer) — left in place, unused for new intake
  going forward, but still fully functional for viewing/managing
  existing purchase history (vendor, invoice, GST records already
  entered). Not hidden, not disabled.
- No per-bundle GST/vendor/invoice tracking — bundles are a pure
  quantity/variance concept, not a purchase-accounting concept. (If
  vendor/invoice tracking is wanted for bundles later, that's a
  separate follow-up, out of scope here.)
- No automatic reconciliation or alerting (e.g. no email/notification
  when a bundle closes with variance) — the bundle list page showing
  variance is the extent of this phase's visibility.
- Bundle tracking is scoped to Neksomo's raw/mapped-product conversion
  flow only — no change to non-mapped products' existing Convert
  Pieces↔Packs behavior (a product with no `neksomo_product_mapping`
  entry keeps converting from its own `extra_pieces` exactly as today).
- No change to Input Stock's existing non-bundle entry mode — "Raw
  Bundles" is a new, additional mode alongside the current flat-quantity
  Add Input Stock flow, not a replacement for input stock in general
  (only for how *raw Neksomo material specifically* gets entered).

## Data model

### New table: `raw_material_bundles`

```sql
CREATE TABLE raw_material_bundles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  raw_product_id INT NOT NULL,
  company_godown_id INT NOT NULL,
  warehouse_id INT NULL,
  nominal_pieces INT NOT NULL,
  remaining_pieces INT NOT NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  input_ref_id VARCHAR(64) NULL,
  closed_by VARCHAR(100) NULL,
  closed_at TIMESTAMP NULL,
  created_by VARCHAR(100) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rmb_raw_product (raw_product_id, company_godown_id, warehouse_id, status)
);
```

- `remaining_pieces` starts equal to `nominal_pieces`, decremented by
  each conversion draw; signed (can go negative).
- `status` gates whether a bundle appears in the "pick a bundle to
  convert from" dropdown (`open` only) — a `closed` bundle is
  permanently read-only.
- Variance is *derived*, not stored redundantly: `remaining_pieces` at
  the moment of close (or currently, for an open bundle) IS the
  variance — positive-remaining-on-close = shortage of that amount,
  negative = excess of that amount, both read directly off the same
  column. No separate `variance_pieces` column needed — one number
  serves both "how much is left to draw" (while open) and "what the
  final variance was" (once closed), so the display layer's meaning
  simply depends on `status`.
- `input_ref_id` links back to whichever Input Stock submission created
  this bundle (for audit trail only, not used by any write logic).
- Self-migrating, same convention as every other table in this project.

## Input Stock — Raw Bundles entry mode

New page `input-stock-bundles.php` (separate from the existing
`add-input.php`, since this is materially a different form shape — no
per-row remarks/multi-product grid, just one raw product + bundle
count):

**Fields:** Raw Product (dropdown limited to `temp_id LIKE 'NKS-%'`
products, mirroring how Convert Pieces↔Packs already excludes/includes
these), Company Profile, Godown (physical, optional), Nominal Pieces
per Bundle (numeric, no fixed default — the business's nominal count
can differ per product/shipment), Number of Bundles.

**On submit** (`input-stock-bundles-action.php`):
1. Validate all fields, same guard style as `input-action.php`.
2. Credit `nominal_pieces * number_of_bundles` onto the raw product's
   `stock` row via `StockService::credit()` (same mechanism Add
   Purchase already uses for raw pieces) — this keeps the overall raw
   pool total correct and visible everywhere `stock.closing_qty`
   already is (Convert Pieces↔Packs' "raw pc available" figure,
   overall stock reports, etc.).
3. Insert `number_of_bundles` rows into `raw_material_bundles`, each
   with `nominal_pieces = remaining_pieces = <entered nominal>`,
   `status = 'open'`, sharing one `input_ref_id`.
4. All inside one transaction — commit only if both the stock credit
   and every bundle insert succeed.

## Convert Pieces↔Packs — bundle-scoped conversion

**Current behavior** (`neksomo-piece-pack-convert.php` /
`-action.php`, per this session's earlier work): for a mapped finished
product, "Current Stock" shows the raw product's pooled `stock.closing_
qty` at the selected (company profile, warehouse); converting deducts
directly from that pooled row via `StockService::deduct()`.

**New behavior:** when the selected finished product is mapped
(`get_neksomo_source_for_company_product()` returns non-null), the row
gains a **Bundle** picker — populated via a new AJAX endpoint querying
`raw_material_bundles WHERE raw_product_id = ? AND company_godown_id = ?
AND warehouse_id <=> ? AND status = 'open' ORDER BY created_at ASC`
(oldest-first, encouraging FIFO bundle consumption, though not
enforced — operator can pick any open bundle). Each option shows the
bundle's id/label and current `remaining_pieces`.

Selecting a bundle is **required** for a mapped product's Pieces→Pack
conversion (the direction already restricted to mapped products per
this session's earlier work) — there's no valid "which raw material did
this become" without it.

**On submit**, per mapped-product row with a chosen bundle:
1. Still deducts `piecesPerPack * packCount` from the raw product's
   pooled `stock` row via `StockService::deduct()`, exactly as today —
   this is the real, hard-limited stock movement; refuses via
   `StockException` if company-wide raw stock is insufficient,
   unchanged.
2. **Additionally**, decrements the chosen bundle's `remaining_pieces`
   by the same amount — allowed to go negative, no cap, no exception.
   This is bookkeeping on top of step 1, never a gate on it.
3. Credits `packCount` onto the finished product's `stock` row, exactly
   as today.

This means: the true availability check remains `stock.closing_qty`
(so a business-wide shortage still correctly blocks the conversion);
the bundle's own count is purely informational/diagnostic tracking
layered on top, confirming the "shortfall-tolerant, allow-negative"
decisions above without weakening the existing hard stock check.

## Close Bundle

New small admin action — a "Close" button per open bundle on the new
bundle list page (see below), confirmed via a simple confirm dialog
showing current `remaining_pieces` and what it'll be recorded as
(shortage/excess/exact). Sets `status = 'closed'`, `closed_by`,
`closed_at`. No further conversions can select this bundle afterward
(the open-bundle picker query naturally excludes it via `status = 'open'`).

## Bundle list page

New page `raw-material-bundles-manage.php` — every bundle, most recent
first, columns: Raw Product, Company Profile, Godown (physical),
Nominal, Remaining, Status, Variance (blank while open; "Short by N" /
"Excess N" / "Exact" once closed, colored red/amber/green respectively),
Created By/At, Closed By/At. Close-button per open row. Filterable by
raw product and status (open/closed) for a business with many
concurrent bundles.

## Access control

Finance-only, matching every other Internal Stock Transfer / Input
Stock area's established gate this session. New menu entries under the
finance branch: "Input Stock — Raw Bundles" and "Manage Raw Bundles",
placed near the existing "Input Stock" submenu (a sibling entry, not
nested inside the existing Add/Manage Input Stock items, since this is
a materially different concept per the user's own framing).

## Testing strategy

Following this repo's manual-test convention (disposable schema,
`php <file>.php`, no PHPUnit):

- **Bundle creation**: Input Stock bundle submission creates the right
  number of bundle rows, each with correct nominal/remaining, and
  credits the raw product's `stock` row by the correct total.
- **Bundle-scoped conversion — normal case**: converting from a bundle
  with enough remaining pieces decrements both the bundle and the
  pooled `stock` row correctly; a closed/wrong-raw-product bundle never
  appears in the picker's option list.
- **Shortage case**: closing a bundle while `remaining_pieces > 0`
  records/display as shortage of that exact amount.
- **Excess case**: converting past a bundle's nominal (remaining goes
  negative) succeeds without error as long as the underlying `stock`
  row has enough — confirms the two limits (bundle vs. stock) are
  independent, and closing then correctly shows excess.
- **Split-bundle case** (the explicitly confirmed operational scenario):
  one bundle drawn against by TWO different finished products in
  sequence (e.g. 330mm 9pcs then 330mm 6pcs) — confirms
  `remaining_pieces` correctly reflects the cumulative draw across both
  conversions, and both conversions' finished-product stock credits are
  independently correct.
- **Non-mapped product regression**: a product with no
  `neksomo_product_mapping` entry never shows a bundle picker and
  converts exactly as before (drawing from its own `extra_pieces`) —
  confirms this change is additive, not a behavior change for the
  existing non-bundle conversion path.

## No open bundle exists yet (confirmed)

Converting a mapped finished product's Pieces→Pack direction is
**blocked** until at least one open bundle exists for its raw source at
the selected (company profile, warehouse) — consistent with bundle
tracking being the sole path forward for raw intake per this design's
core intent. The Convert page shows a clear message ("No open raw
material bundle for this product yet — add one via Input Stock — Raw
Bundles first") instead of a picker, rather than silently falling back
to pooled deduction. This applies uniformly, including to any raw stock
that predates this feature — such stock remains usable only once a
bundle is created to represent it (a bundle can be entered with
`nominal_pieces` set to match the pre-existing balance if the business
wants to formally track it, though backfilling exact historical bundle
boundaries is not required — a single "legacy" bundle covering the
whole pre-existing balance is sufficient).

## Bundle labeling (confirmed)

No custom label field. Every bundle is identified purely by an
auto-generated label wherever shown: `"Bundle #<id> (<created_at date,
d-M-Y>)"`, e.g. `"Bundle #42 (22-Sep-2026)"`. Keeps the Input Stock
Bundles form minimal (raw product, company profile, godown, nominal
pieces per bundle, bundle count — no extra field).

## Migration / rollout safety

`raw_material_bundles` is a brand-new, self-migrating table — zero
impact on any existing data or workflow until the new Input Stock
Bundles page is actually used, EXCEPT for the one confirmed behavior
change above: Convert Pieces↔Packs' Pieces→Pack direction for mapped
products becomes blocked without an open bundle, effective immediately
once this ships (not just for new stock) — see "No open bundle exists
yet" above for how to unblock any pre-existing raw stock.
