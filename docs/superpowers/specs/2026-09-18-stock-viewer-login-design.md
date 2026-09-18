# Stock Viewer Login — Design

## Goal

A new, dedicated login (converted from the existing `9952428768` account)
whose sole purpose is viewing company-wide stock: read-only insights,
proper breakdowns by company profile / warehouse / product, and clear
visualization (charts), reachable through its own minimal menu. No
invoicing, sales, customers, payments, or stock-writing capability of any
kind.

## Non-Goals

- No stock mutation (no Set Opening Stock, Add Input Stock, Internal
  Transfer, Stock Return, or any other write path) — purely a viewer.
- No new authentication mechanism — reuses the existing username/password
  login flow (`femi9/billing/login/`) and session (`admin_log`,
  `checksession.php`) unchanged.
- No scoping to a single godown — this login sees every company profile
  and warehouse, including `finance_only` ones (Neksomo, Healthcare),
  same breadth as the finance login.
- Not a replacement for `overall-stock.php` or any other existing report
  — those keep working exactly as they do today, for the logins that
  already use them.

## Architecture

### New usertype: `stockviewer`

`admin_log.usertype` gets a new value, `'stockviewer'`. The existing row
for username `9952428768` (id=21, currently `usertype='users'`) is
converted to this new type — same credentials, new role.

### Scope helper

`include/GodownAccess.php` gets a new function, mirroring the existing
`is_neksomo_login()`:

```php
function is_stockviewer_login($db_conn) {
    return get_login_usertype($db_conn) === 'stockviewer';
}
```

`godown_finance_filter_sql()` and `is_godown_allowed()` both treat
`stockviewer` the same as `finance` — full visibility, no godown
restriction (`1=1` / always allowed). This reuses the *existing*
`is_finance_login()` branch in each function rather than adding a new
branch, since the visibility rule is identical to finance's.

### Landing page & redirect

Right after `checksession.php` in `dashboard.php` (the page every login
currently lands on after signing in), add:

```php
if (is_stockviewer_login($db_conn)) {
    header("Location: stock-viewer-dashboard.php");
    exit;
}
```

This follows the same "redirect away from a page that isn't for you"
convention already used by `overall-stock.php` for the neksomo login
(`if (is_neksomo_login($db_conn)) { header("Location: dashboard.php"); exit; }`),
just in the opposite direction — stockviewer never actually sees the
normal dashboard.

### Menu

`femi_menu.php` currently builds one large shared `<ul>` with inline
`if ($LoginusertypeGET=="neksomo")` blocks scattered through it (neksomo
shares much of the menu shape but hides most sections). Stockviewer's
menu is much smaller and entirely disjoint from the rest, so instead of
scattering more inline conditionals, the top of the file's menu body
branches once:

```php
<?php if ($LoginusertypeGET === 'stockviewer'): ?>
<div class="app-menu">
    <ul class="accordion-menu">
        <li><a href="stock-viewer-dashboard.php"><i class="material-icons-two-tone">dashboard</i>Dashboard</a></li>
        <li><a href="stock-viewer-by-profile.php"><i class="material-icons-two-tone">store</i>Stock by Company Profile</a></li>
        <li><a href="stock-viewer-by-product.php"><i class="material-icons-two-tone">inventory_2</i>Stock by Product</a></li>
        <li><a href="stock-viewer-by-warehouse.php"><i class="material-icons-two-tone">warehouse</i>Stock by Warehouse</a></li>
    </ul>
</div>
<?php else: ?>
... existing menu body unchanged ...
<?php endif; ?>
```

### Page-level guard

Every one of the 4 new pages starts the same way (mirroring the Neksomo
pages' own gate):

```php
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['stockviewer', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}
```

`admin` is retained for oversight/support, same convention as every
other Neksomo-only page in this codebase.

### Shared data layer: `include/StockViewerData.php`

A small set of read-only query helpers, used by all 4 pages, so the
"stock joined with product/godown/warehouse names" query isn't
duplicated four times:

```php
// One row per (product, company profile, warehouse) with everything the
// 4 pages need to render: names, closing_qty, and enough of
// products/company_godown/warehouses to filter/group by.
function get_stock_viewer_rows(mysqli $db_conn, array $filters = []): array
```

`$filters` supports optional `product_id`, `company_godown_id`,
`warehouse_id` (each may be a scalar or array, matching the "apply
multiple filters at once" requirement) — the SQL builds `WHERE product_id
IN (...)` etc. only for the filters actually supplied. All 4 pages call
this one function and shape the result differently client-side (as
cards, as a flat table, or aggregated into chart data), so filtering
logic lives in exactly one place.

```php
// Aggregate helpers for the dashboard's summary cards + charts — total
// closing qty, distinct SKU count, per-profile totals, per-warehouse
// totals, top-N products by qty. Each is a thin GROUP BY over the same
// base query get_stock_viewer_rows() uses.
function get_stock_viewer_summary(mysqli $db_conn): array
function get_stock_viewer_totals_by_profile(mysqli $db_conn): array
function get_stock_viewer_totals_by_warehouse(mysqli $db_conn): array
function get_stock_viewer_top_products(mysqli $db_conn, int $limit = 10): array
```

Low-stock alert count uses a fixed threshold constant
(`STOCK_VIEWER_LOW_STOCK_THRESHOLD = 10`, defined in the same file) —
simplest option that satisfies "clear visualisation" without adding a
configuration UI nobody asked for.

### Pages

**1. `stock-viewer-dashboard.php`** — landing page.
- Summary cards: total closing qty (company-wide), distinct SKU count,
  active company-profile count, active warehouse count, low-stock alert
  count (products with company-wide closing_qty below the threshold).
- ApexCharts (already bundled in the theme —
  `assets/plugins/apexcharts/apexcharts.min.js`, already used on the
  existing `dashboard.php`):
  - Bar chart: total stock by company profile.
  - Bar chart: total stock by warehouse.
  - Bar chart: top 10 products by qty.
- Quick links into the other 3 pages.

**2. `stock-viewer-by-profile.php`** — per-company-profile breakdown.
Adapted from `overall-stock.php`'s existing per-warehouse-card rendering
(one card per warehouse per company profile, same product table shape),
but built on `get_stock_viewer_rows()` instead of `overall-stock.php`'s
own inline queries, and with the combined filter bar (see below).

**3. `stock-viewer-by-product.php`** — pick one product (dropdown/
autocomplete), see its stock across every company profile × warehouse
combination in one table, plus a small per-profile bar chart for that
product.

**4. `stock-viewer-by-warehouse.php`** — pick one physical warehouse, see
everything stored there across every company profile, product-level
detail table.

### Combined filtering

Pages 2–4 share a filter bar: product name (text search), company
profile (multi-select checkboxes), warehouse (multi-select checkboxes).
Same pattern as `overall-stock.php`'s existing `.warehouse-filter-check`
JS filtering — all filters are applied client-side over the
server-rendered row set, so they compose freely (e.g. product X, in
profile A and B, in warehouse H1) without additional page loads. This
means `get_stock_viewer_rows()` is called once per page load with no
filters (or only the ones meaningful server-side, like a specific
product on the by-product page), and the rest of the narrowing happens
in JS — consistent with how `overall-stock.php` already works.

## Data Flow Example

1. Stockviewer logs in with `9952428768` → `checksession.php` sets up
   the session as usual → lands on `dashboard.php` → immediately
   redirected to `stock-viewer-dashboard.php`.
2. `stock-viewer-dashboard.php` calls `get_stock_viewer_summary()` and
   the three `get_stock_viewer_totals_by_*`/`get_stock_viewer_top_products()`
   helpers, renders summary cards + 3 ApexCharts.
3. Stockviewer clicks "Stock by Product" → `stock-viewer-by-product.php`
   → picks "330mm XL Napkin" from the dropdown → page reloads with
   `?product_id=9` → `get_stock_viewer_rows($db_conn, ['product_id' => 9])`
   → table + chart for that product across every profile/warehouse.

## Testing Strategy

No `StockService` writes are involved (pure reads), so this doesn't fit
the existing disposable-schema `StockService` test convention. Testing
is:
- `php -l` on every new file.
- A small standalone test for `StockViewerData.php`'s query-building
  logic (disposable schema, seeded `stock`/`products`/`company_godown`/
  `warehouses` rows, asserting `get_stock_viewer_rows()` returns the
  right rows for each filter combination — single filters and combined
  filters together).
- Manual verification: log in as `9952428768` after the usertype
  conversion, confirm the menu shows only the 4 links, confirm each
  page loads and redirects correctly for a non-stockviewer/non-admin
  login, confirm charts render with real data.

## Migration Safety

Single-row `UPDATE admin_log SET usertype = 'stockviewer' WHERE username
= '9952428768'`, run once as part of the implementation plan (not a
schema migration — no new columns/tables needed for `admin_log` itself).
No other data changes. Fully reversible (`UPDATE ... SET usertype =
'users' WHERE username = '9952428768'`) if needed.

## Open Questions

None outstanding — scope, page set, filtering behavior, and visibility
were all confirmed directly with the user during brainstorming.
