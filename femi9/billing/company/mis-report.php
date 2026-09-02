<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('report');
include("config.php");
require_once("include/GodownAccess.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// ── MIS Type (only Sales is implemented) ────────────────────────────────────
$mis_type = 'sales';

// ── Date range & TP filter ─────────────────────────────────────────────────
$preset   = $_GET['preset'] ?? 'month';
$today    = date('Y-m-d');

// A 'neksomo' login only ever sees the entity-scoped Pieces Sold report (see
// below) — every other section on this page (KPIs, channel breakdown,
// product-wise revenue, state/district, TP performance) aggregates ALL three
// company entities together with no per-entity filter, so showing them to a
// neksomo login would leak Femi Health Care / Femi Nayan LLP figures.
$__viewerType   = get_login_usertype($db_conn);
$is_neksomo_view = ($__viewerType === 'neksomo');

// neksomo login: rate lookups stay mapping-only — only products with a
// neksomo_product_mapping row are priced, so Neksomo's own report never
// shows LLP-only products. admin/LLP login: the napkin Gross Profit cost
// lookup ($gp_cost_rate_subq below) falls back to the company product's own
// id/rate tables when there's no mapping row, so LLP-entered rates
// (llp-purchase-rate.php, for products with no Neksomo-side counterpart) are
// picked up too, on top of every mapped Neksomo product exactly as before.

// ── Scope: company (direct, all-channel) vs a single channel's transactions ─
$scope = $is_neksomo_view ? 'company' : ($_GET['scope'] ?? 'tp');
if (!in_array($scope, ['company', 'tp', 'super_stockiest', 'stockiest'], true)) $scope = 'tp';
$filter_tp = ($scope === 'tp') ? (int)($_GET['tp_id'] ?? 0) : 0;   // 0 = all TPs, only meaningful in tp scope

// ── Entity filter (company scope only) — which company_godown "sold" the
// goods (invoice/user_invoice/ot_sales all carry the godown id as the seller
// id when user_type='company'). Dropdown is pre-scoped by GodownAccess.php,
// so e.g. a regular admin/user only ever sees Femi Nayan LLP (the one
// non-finance-only entity), finance sees all three, and neksomo sees only
// Neksomo Hygiene Industries.
$all_entities = ($scope === 'company')
    ? call_rows($db_conn, "SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY gname ASC")
    : [];
$filter_entity = ($scope === 'company') ? (int)($_GET['entity_id'] ?? 0) : 0;
if ($filter_entity > 0 && !is_godown_allowed($db_conn, $filter_entity)) $filter_entity = 0;
if ($filter_entity === 0 && count($all_entities) === 1) $filter_entity = (int)$all_entities[0]['id'];
$entity_ids_subq = ($scope === 'company') ? godown_ids_subquery($db_conn) : '';

// The neksomo dashboard report specifically shows FEMI NAYAN LLP + FEMI
// HEALTH CARE's combined sales by default (per explicit request), not the
// neksomo login's own GodownAccess.php data scope (NEKSOMO HYGIENE
// INDUSTRIES) — general godown access for this login elsewhere in the app is
// intentionally left untouched, this override is local to this report only.
// $entity_ids_subq is repointed from the usual GodownAccess-scoped subquery
// to a literal id list of just these two godowns, so every existing
// "$filter_entity > 0 ? =X : IN ({$entity_ids_subq})" condition below picks
// up both entities for free without needing its own neksomo special-case.
// $all_entities now has 2 rows, so the entity-picker dropdown further down
// (guarded by count($all_entities) > 1) starts showing for this login too —
// re-read entity_id from $_GET directly (bypassing the is_godown_allowed
// reset at line 39, same bypass this report already relies on) so picking
// just LLP or just Healthcare from it actually narrows the report instead of
// always snapping back to the combined view.
if ($is_neksomo_view) {
    $__llpRow = crow($db_conn, "SELECT id, gname FROM company_godown WHERE gname = 'FEMI NAYAN LLP' LIMIT 1");
    $__hcRow  = crow($db_conn, "SELECT id, gname FROM company_godown WHERE gname = 'FEMI HEALTH CARE' LIMIT 1");
    $__neksomoPcsEntities = array_values(array_filter([$__llpRow, $__hcRow], function ($r) { return !empty($r['id']); }));
    if (!empty($__neksomoPcsEntities)) {
        $__neksomoPcsIds = array_map('intval', array_column($__neksomoPcsEntities, 'id'));
        $__requestedEntity = (int)($_GET['entity_id'] ?? 0);
        $filter_entity   = in_array($__requestedEntity, $__neksomoPcsIds, true) ? $__requestedEntity : 0;
        $all_entities    = $__neksomoPcsEntities;
        $entity_ids_subq = implode(',', $__neksomoPcsIds);
    }
}

switch ($preset) {
    case 'today':  $df = $today; $dt = $today; break;
    case 'week':   $df = date('Y-m-d', strtotime('monday this week')); $dt = date('Y-m-d', strtotime('sunday this week')); break;
    case 'year':   $df = date('Y-01-01'); $dt = date('Y-12-31'); break;
    default:       $df = date('Y-m-01'); $dt = date('Y-m-t');
}
$from = isset($_GET['from']) && $_GET['from'] ? date('Y-m-d', strtotime($_GET['from'])) : $df;
$to   = isset($_GET['to'])   && $_GET['to']   ? date('Y-m-d', strtotime($_GET['to']))   : $dt;

$days_diff = (strtotime($to) - strtotime($from)) / 86400;
$prev_from = date('Y-m-d', strtotime($from) - ($days_diff + 1) * 86400);
$prev_to   = date('Y-m-d', strtotime($from) - 86400);

// Scope = "invoices actually ISSUED BY this entity", checked identically
// against both tables:
//  - `invoice.user_type` is WHICH ENTITY sold to a customer (every row has
//    a customer_id — this table is <seller type> -> Customer, e.g. a Super
//    Stockist's or the company's own direct sale to a customer).
//  - `user_invoice.from_user_type` is WHICH ENTITY billed another business
//    (company -> SS/S/SD/D/Shop, or those entities reselling to each other).
// So both tables use the same plain `<type column> = ?` match against the
// scope's own type — 'company' maps to 'company' in both, 'tp' to
// 'territory_partner', etc. No special-casing needed between the two tables.
$scope_types = [
    'company'         => 'company',
    'tp'              => 'territory_partner',
    'super_stockiest' => 'super_stockiest',
    'stockiest'       => 'stockiest',
];
$utype = $scope_types[$scope] ?? 'territory_partner';

// ── DB helpers ─────────────────────────────────────────────────────────────
function cq($db, $sql, $types = '', $params = []) {
    if (!$types) {
        $r = $db->query($sql);
        return $r ?: null;
    }
    $s = $db->prepare($sql);
    if (!$s) return null;
    $s->bind_param($types, ...$params);
    $s->execute();
    $r = $s->get_result();
    $s->close();
    return $r;
}
function cval($db, $sql, $types = '', $params = []) {
    $r = cq($db, $sql, $types, $params);
    return $r ? ($r->fetch_row()[0] ?? 0) : 0;
}
function crow($db, $sql, $types = '', $params = []) {
    $r = cq($db, $sql, $types, $params);
    return $r ? ($r->fetch_assoc() ?? []) : [];
}
function call_rows($db, $sql, $types = '', $params = []) {
    $r = cq($db, $sql, $types, $params);
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

// Merge multiple state/district breakdown result sets (each a list of rows
// with a $name_key/'cnt'/'revenue' shape) by name, summing cnt/revenue for
// rows that name the same state or district, sorted by revenue descending.
function merge_geo_rows($name_key, ...$sources) {
    $map = [];
    foreach ($sources as $rows) {
        foreach ($rows as $r) {
            $name = $r[$name_key];
            if (!isset($map[$name])) $map[$name] = ['cnt' => 0, 'revenue' => 0.0];
            $map[$name]['cnt']     += (int)$r['cnt'];
            $map[$name]['revenue'] += (float)$r['revenue'];
        }
    }
    $out = [];
    foreach ($map as $name => $v) $out[] = [$name_key => $name, 'cnt' => $v['cnt'], 'revenue' => $v['revenue']];
    usort($out, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
    return $out;
}

// Build TP WHERE clause additions
function tp_cond_inv($tp_id) {
    return $tp_id > 0 ? " AND i.user_id={$tp_id}" : "";
}
function tp_cond_ui($tp_id) {
    return $tp_id > 0 ? " AND ui.from_user_id={$tp_id}" : "";
}
function tp_cond_ii($tp_id) {
    return $tp_id > 0 ? " AND ii.user_id={$tp_id}" : "";
}
function tp_cond_uii($tp_id) {
    return $tp_id > 0 ? " AND uii.from_user_id={$tp_id}" : "";
}

$tc_inv = $filter_tp > 0 ? " AND user_id={$filter_tp}"           : "";
$tc_ui  = $filter_tp > 0 ? " AND from_user_id={$filter_tp}"      : "";
$tc_ii  = $filter_tp > 0 ? " AND ii.user_id={$filter_tp}"        : "";
$tc_uii = $filter_tp > 0 ? " AND uii.from_user_id={$filter_tp}"  : "";

// tp_invoices = company or a super-stockist billing a TP (there is no
// from/to-type column — the issuer is inferred from created_by_user_type,
// added later than the table itself, see super-stockist/tp-invoice-action.php
// and company/tp-invoice-action.php). TP itself never issues one of these —
// a tp_invoice is always billed TO a TP — so only 'company' and
// 'super_stockiest' scopes ever pick any of them up.
// Both scopes match on created_by_user_type rather than source_cp_id/
// source_godown_id — those columns are only populated on invoices created
// after source tracking was added, so a handful of early company-created TP
// invoices (source_cp_id=0 AND source_godown_id=0, e.g. TP/26-27/001-003)
// were silently excluded from company-scope totals, AND double-counted into
// super-stockiest-scope totals (which matched on the same "neither source
// set" condition with no creator check) when filtered on source instead of
// on who actually created the invoice.
$tpinv_source_sql = null;
if ($scope === 'company') $tpinv_source_sql = "(created_by_user_type != 'super_stockiest')";
elseif ($scope === 'super_stockiest') $tpinv_source_sql = "(created_by_user_type = 'super_stockiest')";
$tc_tpi = $filter_tp > 0 ? " AND territory_partner_id={$filter_tp}" : "";

// ── Load all TPs for filter dropdown ──────────────────────────────────────
$all_tps = call_rows($db_conn,
    "SELECT id, name, tp_id FROM territory_partners WHERE is_active=1 ORDER BY name ASC");

// ═══════════════════════════════════════════════════════════════════════════
// 1. KPI — current & previous period
// ═══════════════════════════════════════════════════════════════════════════
$cust_row = crow($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total-courier_charges),0) rev FROM invoice
     WHERE user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_inv}",
    'sss', [$utype, $from, $to]);
$shop_row = crow($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total-courier_charges),0) rev FROM user_invoice
     WHERE from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_ui}",
    'sss', [$utype, $from, $to]);

$tpi_row = ['cnt' => 0, 'rev' => 0];
if ($tpinv_source_sql) {
    $tpi_row = crow($db_conn,
        "SELECT COUNT(*) cnt, COALESCE(SUM(total_amount-courier_charges),0) rev FROM tp_invoices
         WHERE {$tpinv_source_sql} AND invoice_date BETWEEN ? AND ?{$tc_tpi}",
        'ss', [$from, $to]);
}

$total_invoices = (int)$cust_row['cnt'] + (int)$shop_row['cnt'] + (int)$tpi_row['cnt'];
$total_revenue  = (float)$cust_row['rev'] + (float)$shop_row['rev'] + (float)$tpi_row['rev'];

$cust_units = (int)cval($db_conn,
    "SELECT COALESCE(SUM(ii.qty),0) FROM invoice_items ii
     JOIN invoice i ON i.inv_id = ii.inv_id
     WHERE i.user_type=? AND i.date BETWEEN ? AND ?".tp_cond_inv($filter_tp),
    'sss', [$utype, $from, $to]);
$shop_units = (int)cval($db_conn,
    "SELECT COALESCE(SUM(uii.qty),0) FROM user_invoice_items uii
     JOIN user_invoice ui ON ui.inv_id = uii.inv_id
     WHERE ui.from_user_type=? AND ui.date BETWEEN ? AND ?".tp_cond_ui($filter_tp),
    'sss', [$utype, $from, $to]);
$tpi_units = 0;
if ($tpinv_source_sql) {
    $tpi_units = (int)cval($db_conn,
        "SELECT COALESCE(SUM(tii.quantity),0) FROM tp_invoice_items tii
         JOIN tp_invoices ti ON ti.id=tii.tp_invoice_id
         WHERE {$tpinv_source_sql} AND ti.invoice_date BETWEEN ? AND ?{$tc_tpi}",
        'ss', [$from, $to]);
}
$total_units = $cust_units + $shop_units + $tpi_units;

// OT Channel sales (Amazon/Flipkart/Website/etc.) — folded only into the
// broad "Income to Company" view; it isn't a TP/Super-Stockist/Stockist
// attributed channel, so other scopes leave it out.
$ot_row = ['cnt' => 0, 'rev' => 0];
$ot_units = 0;
$ot_prev_rev = 0.0;
if ($scope === 'company') {
    // 'ID CONCEPT' rows (internal concept/sample orders) are counted as real
    // OT sales throughout this report, per request.
    $ot_row = crow($db_conn,
        "SELECT COUNT(DISTINCT tempid) cnt, COALESCE(SUM(total),0) rev FROM ot_sales
         WHERE date BETWEEN ? AND ?",
        'ss', [$from, $to]);
    $ot_units = (int)cval($db_conn,
        "SELECT COALESCE(SUM(qty),0) FROM ot_sales WHERE date BETWEEN ? AND ?",
        'ss', [$from, $to]);
    $ot_prev_rev = (float)cval($db_conn,
        "SELECT COALESCE(SUM(total),0) FROM ot_sales WHERE date BETWEEN ? AND ?",
        'ss', [$prev_from, $prev_to]);
}
$total_invoices += (int)($ot_row['cnt'] ?? 0);
$total_revenue  += (float)($ot_row['rev'] ?? 0);
$total_units    += $ot_units;

// For company scope, `user_id`/`from_user_id` on these rows is the company's
// own single account, not the counterparty — so count the actual counterparty
// columns instead (customer_id, and to_user_type+to_user_id combined).
if ($scope === 'company') {
    $active_customers = (int)cval($db_conn,
        "SELECT COUNT(DISTINCT customer_id) FROM invoice WHERE user_type='company' AND sub_total>0 AND `date` BETWEEN ? AND ?",
        'ss', [$from, $to]);
    // OT sales have no shared customer_id with `invoice`, so their distinct
    // buyers (by name — the closest thing to an identity OT rows carry) are
    // added on top, not deduped in.
    $active_customers += (int)cval($db_conn,
        "SELECT COUNT(DISTINCT customer_name) FROM ot_sales WHERE `date` BETWEEN ? AND ?",
        'ss', [$from, $to]);
    $active_businesses = (int)cval($db_conn,
        "SELECT COUNT(DISTINCT CONCAT(to_user_type,'-',to_user_id)) FROM user_invoice WHERE from_user_type='company' AND sub_total>0 AND `date` BETWEEN ? AND ?",
        'ss', [$from, $to]);
    // Territory Partners never appear in user_invoice (company never invoices
    // a TP through that table) — TP stock supply is tracked separately in
    // tp_invoices, keyed by territory_partner_id, live since 2026-06-01.
    // Without this, active_businesses goes blind exactly when TP volume
    // took over from the legacy Stockist/SS/Distributor channel.
    $active_businesses += (int)cval($db_conn,
        "SELECT COUNT(DISTINCT territory_partner_id) FROM tp_invoices WHERE total_amount>0 AND invoice_date BETWEEN ? AND ?",
        'ss', [$from, $to]);
    $active_tps = $active_customers + $active_businesses;
} else {
    $total_tps = (int)cval($db_conn,
        "SELECT COUNT(DISTINCT user_id) FROM invoice WHERE user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?",
        'sss', [$utype, $from, $to]);
    $total_tps2 = (int)cval($db_conn,
        "SELECT COUNT(DISTINCT from_user_id) FROM user_invoice WHERE from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?",
        'sss', [$utype, $from, $to]);
    $active_tps = max($total_tps, $total_tps2);
}

// user_return_stock has one row per line-item but repeats the whole return's
// total on every row, so it must be deduped by returnid before summing —
// otherwise a naive SUM(total) multiplies each return by its item count.
$returns_row = crow($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total),0) amount FROM (
        SELECT returnid, MAX(total) total FROM user_return_stock
        WHERE to_usertype=?".($filter_tp > 0 ? " AND to_userid={$filter_tp}" : "")." AND `date` BETWEEN ? AND ?
        GROUP BY returnid
     ) x",
    'sss', [$utype, $from, $to]);
// OT channel returns (ot_sales_return) — company scope only, same gating as
// OT sales above. Unlike user_return_stock, ot_sales_return's `total` is
// already a per-line amount (not repeated per return), so no dedup needed —
// same convention the per-product returns query below already uses.
$ot_returns_row = ['cnt' => 0, 'amount' => 0];
if ($scope === 'company') {
    $ot_returns_row = crow($db_conn,
        "SELECT COUNT(DISTINCT tempid) cnt, COALESCE(SUM(total),0) amount FROM ot_sales_return
         WHERE return_date BETWEEN ? AND ?",
        'ss', [$from, $to]);
}
$total_returns    = (int)$returns_row['cnt'] + (int)($ot_returns_row['cnt'] ?? 0);
$total_return_amt = (float)$returns_row['amount'] + (float)($ot_returns_row['amount'] ?? 0);
// $gross_revenue (Sales card) is pre-return; $total_revenue (Total Turnover
// card) is net of returns received back in the selected period.
$gross_revenue = $total_revenue;
$total_revenue -= $total_return_amt;

// Previous period (also net of that period's own returns, for a fair growth %)
$prev_return_amt = (float)cval($db_conn,
    "SELECT COALESCE(SUM(total),0) FROM (
        SELECT returnid, MAX(total) total FROM user_return_stock
        WHERE to_usertype=?".($filter_tp > 0 ? " AND to_userid={$filter_tp}" : "")." AND `date` BETWEEN ? AND ?
        GROUP BY returnid
     ) x",
    'sss', [$utype, $prev_from, $prev_to]);
if ($scope === 'company') {
    $prev_return_amt += (float)cval($db_conn,
        "SELECT COALESCE(SUM(total),0) FROM ot_sales_return WHERE return_date BETWEEN ? AND ?",
        'ss', [$prev_from, $prev_to]);
}
$tpi_prev_rev = 0.0;
if ($tpinv_source_sql) {
    $tpi_prev_rev = (float)cval($db_conn,
        "SELECT COALESCE(SUM(total_amount-courier_charges),0) FROM tp_invoices
         WHERE {$tpinv_source_sql} AND invoice_date BETWEEN ? AND ?{$tc_tpi}",
        'ss', [$prev_from, $prev_to]);
}
$prev_rev = (float)cval($db_conn,
    "SELECT COALESCE(SUM(total-courier_charges),0) FROM invoice
     WHERE user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_inv}",
    'sss', [$utype, $prev_from, $prev_to])
  + (float)cval($db_conn,
    "SELECT COALESCE(SUM(total-courier_charges),0) FROM user_invoice
     WHERE from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_ui}",
    'sss', [$utype, $prev_from, $prev_to])
  + $ot_prev_rev
  + $tpi_prev_rev
  - $prev_return_amt;
$revenue_growth = $prev_rev > 0
    ? round((($total_revenue - $prev_rev) / $prev_rev) * 100, 1) : 0;

// ═══════════════════════════════════════════════════════════════════════════
// 1b. GROSS PROFIT & NET PROFIT
//
// Gross Profit is computed per product on the same NET quantity that drives
// Total Turnover (qty sold − qty returned in the period), not as two
// separately-scoped sums of sold lines and returned lines:
//
//   net_qty   = qty_sold − qty_returned                      (per product)
//   sold_rate = total sale revenue / qty_sold                (per product,
//               the effective average price actually realized this period)
//   Gross Profit = Σ (sold_rate − llp_cost_rate) × net_qty    (across products)
//
// This mirrors Total Turnover exactly: same net_qty, same population of
// transactions, so a heavy-return period that pushes Total Turnover negative
// pushes Gross Profit in the same direction by construction, rather than by
// coincidence of two independently-scoped totals landing on the same sign.
//
// llp_cost_rate is the price at which Femi9 LLP/Neksomo "sells" the product
// into the company's own stock — i.e. the company's real cost basis —
// sourced from whichever rate table covers the product, looked up as of the
// period's end date ($to), same "latest effective_date <= as-of date"
// convention used everywhere else in this report:
//   - neksomo_llp_piece_rates (via neksomo_product_mapping) for products
//     mapped into the Neksomo piece-rate system. This rate is always
//     genuine per-single-piece (see the Pieces Sold section above), while
//     qty here is pack-counted for a piece-type company SKU — so this
//     branch is scaled by products.pieces_per_pack, same as the Pieces Sold
//     section's `qty * COALESCE(NULLIF(p.pieces_per_pack,0),1) * rate`.
//     Diaper-category Neksomo mappings are pack-based even though priced
//     via this same table (see DIAPER comment further down), so they're
//     excluded from the multiplier.
//   - femi9_llp_sale_rates for every other product, entered from the normal
//     company login's own Purchase Rate (LLP) page (llp-purchase-rate.php,
//     table name kept as-is — an internal implementation detail) as a
//     genuine per-piece cost when the product's unit_type is 'pieces' (the
//     page's own rate label switches to "Rate/Piece" vs "Rate/Pack" based on
//     that), same as neksomo_llp_piece_rates above — so it needs the exact
//     same products.pieces_per_pack scaling: a piece-type company SKU is
//     still sold/invoiced in pack quantities (see tp_invoice_items rows for
//     product 1/410mm — quantity=1 at rate=160 means 1 PACK), so comparing
//     an unscaled per-piece cost against a per-pack sold_rate understated
//     cost (and inflated Gross Profit) by a factor of pieces_per_pack.
//     A unit_type='pack' product has pieces_per_pack NULL/0, so the
//     COALESCE(NULLIF(...),1) multiplier is a no-op there — no regression.
// A product with no rate in either table as of the period end is excluded
// from Gross Profit entirely (no cost to subtract, so it can't be priced) —
// same "unrated == excluded" convention the piece-rate section already uses.
// GST is backed out of an inclusive-type rate so Gross Profit stays on a
// pre-tax basis, same convention as the piece-rate section.
//
// sold_rate is put on the same pre-tax basis. Every source feeding the
// `sold` union (invoice_items.total, user_invoice_items.total,
// tp_invoice_items.amount, ot_sales.total) stores a GST-inclusive line
// total by construction — an 'inclusive' product's entered rate already
// included GST (total = subtotal), and an 'exclusive' product has GST
// added on top (total = subtotal + gst_amount) — see user-invoice-action.php
// and customer-invoice-action.php's identical branching. So sold_rate is
// backed out via products.gst/gst_type using the exact same CASE-based
// formula as llp_cost_rate above, keyed off the same joined `p` row —
// without this, sold_rate stayed tax-inclusive while llp_cost_rate was
// already pre-tax, silently overstating Gross Profit by the GST margin.
//
// Net Profit = Gross Profit − Expense Tracker's net expense total for the
// same period, and is only meaningful for the "Income to Company" scope
// (expenses are a company-wide cost, not attributable to a single TP).
// ═══════════════════════════════════════════════════════════════════════════
// total is GST-inclusive on every source regardless of the product's own
// gst_type (see comment above), so the divisor is the same either way —
// unlike llp_cost_rate's CASE, there is no exclusive-rate branch here.
$gp_sold_rate_gst_divisor = "(1 + COALESCE(p.gst,0)/100)";
$gp_napkin_mapped_id = "(SELECT m.neksomo_product_id FROM neksomo_product_mapping m
                                WHERE m.company_product_id = p.id
                                  AND m.neksomo_product_id NOT IN (SELECT np.id FROM products np WHERE np.category = 'diaper')
                                LIMIT 1)";
$gp_napkin_product_match = $is_neksomo_view ? $gp_napkin_mapped_id : "COALESCE({$gp_napkin_mapped_id}, p.id)";
$gp_cost_rate_subq = "COALESCE(
        (SELECT CASE WHEN r.gst_type = 'inclusive' THEN r.rate_per_piece / (1 + r.gst_rate/100) ELSE r.rate_per_piece END
             * COALESCE(NULLIF(p.pieces_per_pack,0),1)
         FROM neksomo_llp_piece_rates r
         WHERE r.product_id = {$gp_napkin_product_match}
           AND r.effective_date <= ?
         ORDER BY r.effective_date DESC LIMIT 1),
        (SELECT CASE WHEN fr.gst_type = 'inclusive' THEN fr.rate_per_piece / (1 + fr.gst_rate/100) ELSE fr.rate_per_piece END
             * COALESCE(NULLIF(p.pieces_per_pack,0),1)
         FROM femi9_llp_sale_rates fr
         WHERE fr.product_id = p.id
           AND fr.effective_date <= ?
         ORDER BY fr.effective_date DESC LIMIT 1)
    )";
// OT channel sales are retail/direct-to-consumer. Company scope only.
$gp_ot_sold_union = '';
$gp_ot_ret_union = '';
$gp_params = [$utype, $from, $to, $utype, $from, $to];
if ($scope === 'company') {
    $gp_ot_sold_union = "UNION ALL SELECT os.prid pr_id, os.qty, os.total FROM ot_sales os WHERE os.date BETWEEN ? AND ?";
    $gp_ot_ret_union   = "UNION ALL SELECT osr.prid pr_id, osr.qty FROM ot_sales_return osr WHERE osr.return_date BETWEEN ? AND ?";
    $gp_params[] = $from;
    $gp_params[] = $to;
}
// See memory "neksomo-sold-by-company-calc".
// tpii.amount is the line's PRE-discount gross (qty*rate); reconciling it back
// to tp_invoices.total_amount (used by every other revenue figure on this
// report, e.g. the top Sales/Total Turnover KPI and Channel Breakdown) needs
// BOTH discount mechanisms TP invoices support:
//  - tpii.discount_amount: a per-line discount (see e.g. TP/26-27/573: items
//    summed 940400, line discounts totalled 56424, header total_amount
//    883976 — matching only once each line's own discount is subtracted).
//  - tpi.discount_amount: an invoice-wide discount some invoices carry
//    instead/on top (only super-stockist's tp-invoice-action.php and the
//    edit-tp-invoice-action.php pages still write this — the current
//    company-side create flow always stores 0 here per its own comment, but
//    older/edited rows can still have one — e.g. TP/26-27/091: items summed
//    43757, zero line discounts, but header discount_amount 19826, header
//    total_amount 23931 = 43757-19826). Since it isn't itemized per line,
//    it's allocated pro-rata by each line's share of the invoice's gross
//    subtotal (SUM(tpii.amount) for that tp_invoice_id) — the only
//    consistent split when the real per-line breakdown wasn't captured.
$gp_tp_net_line_amt = "(tpii.amount - COALESCE(tpii.discount_amount,0)
             - COALESCE(tpi.discount_amount,0) * tpii.amount
               / NULLIF((SELECT SUM(tpii2.amount) FROM tp_invoice_items tpii2 WHERE tpii2.tp_invoice_id = tpi.id), 0))";
$gp_tp_union = '';
if ($tpinv_source_sql) {
    $gp_tp_union = "UNION ALL SELECT tpii.product_id pr_id, tpii.quantity qty, {$gp_tp_net_line_amt} total
         FROM tp_invoice_items tpii JOIN tp_invoices tpi ON tpi.id=tpii.tp_invoice_id
         WHERE {$tpinv_source_sql} AND tpi.invoice_date BETWEEN ? AND ?{$tc_tpi}";
    $gp_params[] = $from;
    $gp_params[] = $to;
}
$gp_return_params = [$utype, $from, $to];
if ($scope === 'company') {
    $gp_return_params[] = $from;
    $gp_return_params[] = $to;
}
// $gp_cost_rate_subq is interpolated twice below (once in the SELECT's
// margin expression, once in the trailing WHERE ... IS NOT NULL filter),
// so its two `effective_date <= ?` placeholders need binding twice over —
// once ahead of $gp_params (the sold subquery) and once after
// $gp_return_params (the return subquery), matching placeholder order in
// the SQL text below exactly.
$gp_all_params = array_merge([$to, $to], $gp_params, $gp_return_params, [$to, $to]);
$gross_profit = (float)cval($db_conn,
    "SELECT COALESCE(SUM((sold.sold_rate / {$gp_sold_rate_gst_divisor} - {$gp_cost_rate_subq}) * (sold.qty_sold - COALESCE(ret.qty_returned,0))), 0)
     FROM (
         SELECT s.pr_id, SUM(s.qty) qty_sold, SUM(s.line_total)/NULLIF(SUM(s.qty),0) sold_rate
         FROM (
             SELECT ii.pr_id, ii.qty, ii.total AS line_total
             FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
             WHERE i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?{$tc_ii}
             UNION ALL
             SELECT uii.pr_id, uii.qty, uii.total AS line_total
             FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
             WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_uii}
             {$gp_ot_sold_union}
             {$gp_tp_union}
         ) s
         GROUP BY s.pr_id
     ) sold
     JOIN products p ON p.id = sold.pr_id
     LEFT JOIN (
         SELECT r.pr_id, SUM(r.qty) qty_returned
         FROM (
             SELECT ri.prid pr_id, ri.qty
             FROM user_return_stock_items ri
             WHERE ri.to_usertype=?".($filter_tp > 0 ? " AND ri.to_userid={$filter_tp}" : "")." AND ri.date BETWEEN ? AND ?
             {$gp_ot_ret_union}
         ) r
         GROUP BY r.pr_id
     ) ret ON ret.pr_id = sold.pr_id
     WHERE {$gp_cost_rate_subq} IS NOT NULL",
    str_repeat('s', count($gp_all_params)), $gp_all_params);

$total_expenses = 0.0;
$net_profit = null;
if ($scope === 'company') {
    $total_expenses = (float)cval($db_conn,
        "SELECT COALESCE(SUM(net_amount),0) FROM expense_imports
         WHERE company_id IN (SELECT id FROM company_godown WHERE gname LIKE '%Femi%' AND " . godown_finance_filter_sql($db_conn) . ")
         AND expense_month BETWEEN DATE_FORMAT(?, '%Y-%m-01') AND DATE_FORMAT(?, '%Y-%m-01')",
        'ss', [$from, $to]);
    $net_profit = $gross_profit - $total_expenses;
}

// ═══════════════════════════════════════════════════════════════════════════
// 1b-2. GROSS PROFIT split by Napkin / Diaper (company scope, non-Neksomo
// login only — a neksomo login gets its own dedicated split further down via
// $is_neksomo_view instead).
//
// Same population, same filters, same cost-rate sourcing as $gross_profit
// above (1b) — this just partitions that exact calculation into two buckets
// instead of one, so Napkin GP + Diaper GP always reconciles to the combined
// $gross_profit KPI card.
//
// Category comes from products.category, but only Neksomo "shadow" products
// (temp_id LIKE 'NKS-%') ever have it set — the real LLP catalog row never
// does. So a company product's category has to be read off whichever Neksomo
// product it's mapped to via neksomo_product_mapping, same indirection the
// $is_neksomo_view section uses at the top of this file. A company product
// with no Neksomo mapping at all has no category to resolve (np.category is
// NULL via the LEFT JOIN below) and is folded into the Napkin bucket —
// confirmed as intended: every unmapped legacy product (410mm, 320mm, Trial
// Pack, etc., priced via llp-purchase-rate.php since they have no
// Neksomo-side counterpart to map to) is a napkin in practice, never a
// diaper, so COALESCE(np.category,'') != 'diaper' already does the right
// thing once unmapped rows survive the join.
// ═══════════════════════════════════════════════════════════════════════════
$grand_gross_profit_llp = 0.0;
$grand_diaper_gross_profit_llp = 0.0;
$grand_combined_gross_profit_llp = 0.0;
$grand_combined_expense_llp = 0.0;
$grand_combined_net_profit_llp = 0.0;
if ($scope === 'company' && !$is_neksomo_view) {
    // LEFT JOIN (not JOIN) — a company product with no Neksomo mapping still
    // needs to survive into this result set so it can be priced via its own
    // direct LLP purchase/sale rate ($gp_cost_rate_subq's fallback) and
    // counted in the Napkin bucket below (COALESCE(np.category,'') != 'diaper'
    // already treats a NULL category, i.e. unmapped, as Napkin — every
    // unmapped legacy product is a napkin in practice, never a diaper).
    $gp_cat_join = "LEFT JOIN neksomo_product_mapping m ON m.company_product_id = p.id
         LEFT JOIN products np ON np.id = m.neksomo_product_id";

    // Diaper cost basis is NOT $gp_cost_rate_subq (that subquery is
    // deliberately napkin-only — see its comment above — and its first
    // branch explicitly excludes any mapping to a diaper-category Neksomo
    // product, falling through to femi9_llp_sale_rates, which has no rows
    // for anything). Diaper cost comes from neksomo_llp_piece_rates — the
    // rate Neksomo "sells" to Femi9 LLP, entered via the Neksomo login's own
    // "Sale to Femi9 LLP" page (neksomo-llp-piece-sale.php /
    // neksomo-llp-piece-sale-manage.php) — same as napkin's cost basis above,
    // NOT neksomo_llp_piece_purchase_rates (that table prices what Neksomo
    // itself buys from its manufacturer, a different, unrelated cost that
    // has nothing to do with Femi9 LLP's purchase cost). Same mapping, same
    // "latest effective_date <= as-of date" convention, but with NO
    // pieces_per_pack scaling — diaper is pack-based even though priced
    // through this piece-rate table, same convention the Neksomo view's own
    // diaper section uses (mis-report.php, $diaper_sold/$diaper_returned).
    // This whole block only ever runs for the admin/LLP view (guarded above),
    // so the direct-id fallback applies unconditionally — a neksomo login
    // never reaches this query.
    $gp_diaper_cost_rate_subq = "
        (SELECT CASE WHEN r.gst_type = 'inclusive' THEN r.rate_per_piece / (1 + r.gst_rate/100) ELSE r.rate_per_piece END
         FROM neksomo_llp_piece_rates r
         WHERE r.product_id = COALESCE((SELECT m.neksomo_product_id FROM neksomo_product_mapping m WHERE m.company_product_id = p.id LIMIT 1), p.id)
           AND r.effective_date <= ?
         ORDER BY r.effective_date DESC LIMIT 1)";
    $gp_diaper_all_params = array_merge([$to], $gp_params, $gp_return_params, [$to]);

    $grand_gross_profit_llp = (float)cval($db_conn,
        "SELECT COALESCE(SUM((sold.sold_rate / {$gp_sold_rate_gst_divisor} - {$gp_cost_rate_subq}) * (sold.qty_sold - COALESCE(ret.qty_returned,0))), 0)
         FROM (
             SELECT s.pr_id, SUM(s.qty) qty_sold, SUM(s.line_total)/NULLIF(SUM(s.qty),0) sold_rate
             FROM (
                 SELECT ii.pr_id, ii.qty, ii.total AS line_total
                 FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
                 WHERE i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?{$tc_ii}
                 UNION ALL
                 SELECT uii.pr_id, uii.qty, uii.total AS line_total
                 FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
                 WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_uii}
                 {$gp_ot_sold_union}
                 {$gp_tp_union}
             ) s
             GROUP BY s.pr_id
         ) sold
         JOIN products p ON p.id = sold.pr_id
         {$gp_cat_join}
         LEFT JOIN (
             SELECT r.pr_id, SUM(r.qty) qty_returned
             FROM (
                 SELECT ri.prid pr_id, ri.qty
                 FROM user_return_stock_items ri
                 WHERE ri.to_usertype=?".($filter_tp > 0 ? " AND ri.to_userid={$filter_tp}" : "")." AND ri.date BETWEEN ? AND ?
                 {$gp_ot_ret_union}
             ) r
             GROUP BY r.pr_id
         ) ret ON ret.pr_id = sold.pr_id
         WHERE {$gp_cost_rate_subq} IS NOT NULL AND COALESCE(np.category,'') != 'diaper'",
        str_repeat('s', count($gp_all_params)), $gp_all_params);

    $grand_diaper_gross_profit_llp = (float)cval($db_conn,
        "SELECT COALESCE(SUM((sold.sold_rate / {$gp_sold_rate_gst_divisor} - {$gp_diaper_cost_rate_subq}) * (sold.qty_sold - COALESCE(ret.qty_returned,0))), 0)
         FROM (
             SELECT s.pr_id, SUM(s.qty) qty_sold, SUM(s.line_total)/NULLIF(SUM(s.qty),0) sold_rate
             FROM (
                 SELECT ii.pr_id, ii.qty, ii.total AS line_total
                 FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
                 WHERE i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?{$tc_ii}
                 UNION ALL
                 SELECT uii.pr_id, uii.qty, uii.total AS line_total
                 FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
                 WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_uii}
                 {$gp_ot_sold_union}
                 {$gp_tp_union}
             ) s
             GROUP BY s.pr_id
         ) sold
         JOIN products p ON p.id = sold.pr_id
         {$gp_cat_join}
         LEFT JOIN (
             SELECT r.pr_id, SUM(r.qty) qty_returned
             FROM (
                 SELECT ri.prid pr_id, ri.qty
                 FROM user_return_stock_items ri
                 WHERE ri.to_usertype=?".($filter_tp > 0 ? " AND ri.to_userid={$filter_tp}" : "")." AND ri.date BETWEEN ? AND ?
                 {$gp_ot_ret_union}
             ) r
             GROUP BY r.pr_id
         ) ret ON ret.pr_id = sold.pr_id
         WHERE {$gp_diaper_cost_rate_subq} IS NOT NULL AND np.category = 'diaper'",
        str_repeat('s', count($gp_diaper_all_params)), $gp_diaper_all_params);

    $grand_combined_gross_profit_llp = $grand_gross_profit_llp + $grand_diaper_gross_profit_llp;
    $grand_combined_expense_llp      = $total_expenses;
    $grand_combined_net_profit_llp   = $grand_combined_gross_profit_llp - $grand_combined_expense_llp;

    // Sales / Return / Turnover per category — same product population as
    // the Gross Profit split above (only Neksomo-mapped products; an
    // unmapped product has no resolvable category and is excluded here too,
    // same convention), but summing sold/returned amount+qty directly
    // instead of computing a per-product margin. $gp_all_params is reused
    // as-is since this query has no cost-rate subquery at all (no `?`
    // placeholders to add), unlike the Gross Profit queries above.
    // $gp_ot_ret_union (built earlier for the Gross Profit queries) only
    // selects pr_id+qty — no amount column, since gross profit only needed
    // quantity for the returns side. This query needs the returned amount
    // too, so it gets its own 3-column OT-return union instead of reusing
    // that one (a UNION ALL with mismatched column counts would fail). It
    // has the same two `?` placeholders (return_date BETWEEN ? AND ?) in the
    // same position as $gp_ot_ret_union, so $gp_return_params (already built
    // with a trailing [$from,$to] for that fragment) supplies the right
    // values as-is — do NOT add extra params on top, or the bind count will
    // overrun the placeholder count.
    $gp_srt_ot_ret_union = $scope === 'company'
        ? "UNION ALL SELECT osr.prid pr_id, osr.qty, osr.total amt FROM ot_sales_return osr WHERE osr.return_date BETWEEN ? AND ?"
        : '';

    $gp_srt_sold_params = $gp_params;
    $gp_srt_ret_params  = $gp_return_params;
    // Single-column (pr_id only) versions of the sold/return OT+TP unions,
    // just for the $pop population subquery below — $gp_ot_sold_union /
    // $gp_tp_union / $gp_srt_ot_ret_union select 3 columns each (pr_id, qty,
    // amount), which can't UNION with a 1-column SELECT pr_id.
    $gp_srt_pop_ot_sold_union = $scope === 'company'
        ? "UNION ALL SELECT os.prid pr_id FROM ot_sales os WHERE os.date BETWEEN ? AND ?" : '';
    $gp_srt_pop_tp_union = $tpinv_source_sql
        ? "UNION ALL SELECT tpii.product_id pr_id
             FROM tp_invoice_items tpii JOIN tp_invoices tpi ON tpi.id=tpii.tp_invoice_id
             WHERE {$tpinv_source_sql} AND tpi.invoice_date BETWEEN ? AND ?{$tc_tpi}" : '';
    $gp_srt_pop_ot_ret_union = $scope === 'company'
        ? "UNION ALL SELECT osr.prid pr_id FROM ot_sales_return osr WHERE osr.return_date BETWEEN ? AND ?" : '';
    // Driving table is now the UNION of every pr_id that sold OR was returned
    // this period ($pop), not just $sold — the previous version drove off
    // $sold alone and LEFT JOINed $ret onto it, which silently dropped any
    // return for a product with zero sales in the period (e.g. a product
    // returned this month but last sold in an earlier one). $pop guarantees
    // every returned pr_id survives into the SUM even with no matching sale.
    $gp_srt_sql = "
        SELECT
            COALESCE(SUM(CASE WHEN COALESCE(np.category,'') != 'diaper' THEN sold.amt_sold ELSE 0 END),0) napkin_sold_amt,
            COALESCE(SUM(CASE WHEN COALESCE(np.category,'') != 'diaper' THEN sold.qty_sold ELSE 0 END),0) napkin_sold_qty,
            COALESCE(SUM(CASE WHEN COALESCE(np.category,'') != 'diaper' THEN ret.amt_returned ELSE 0 END),0) napkin_return_amt,
            COALESCE(SUM(CASE WHEN COALESCE(np.category,'') != 'diaper' THEN ret.qty_returned ELSE 0 END),0) napkin_return_qty,
            COALESCE(SUM(CASE WHEN np.category = 'diaper' THEN sold.amt_sold ELSE 0 END),0) diaper_sold_amt,
            COALESCE(SUM(CASE WHEN np.category = 'diaper' THEN sold.qty_sold ELSE 0 END),0) diaper_sold_qty,
            COALESCE(SUM(CASE WHEN np.category = 'diaper' THEN ret.amt_returned ELSE 0 END),0) diaper_return_amt,
            COALESCE(SUM(CASE WHEN np.category = 'diaper' THEN ret.qty_returned ELSE 0 END),0) diaper_return_qty
        FROM (
            SELECT s.pr_id FROM (
                SELECT ii.pr_id FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
                WHERE i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?{$tc_ii}
                UNION ALL
                SELECT uii.pr_id FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
                WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_uii}
                {$gp_srt_pop_ot_sold_union}
                {$gp_srt_pop_tp_union}
            ) s
            UNION
            SELECT r.pr_id FROM (
                SELECT ri.prid pr_id FROM user_return_stock_items ri
                WHERE ri.to_usertype=?".($filter_tp > 0 ? " AND ri.to_userid={$filter_tp}" : "")." AND ri.date BETWEEN ? AND ?
                {$gp_srt_pop_ot_ret_union}
            ) r
        ) pop
        JOIN products p ON p.id = pop.pr_id
        {$gp_cat_join}
        LEFT JOIN (
            SELECT s.pr_id, SUM(s.qty) qty_sold, SUM(s.line_total) amt_sold
            FROM (
                SELECT ii.pr_id, ii.qty, ii.total AS line_total
                FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
                WHERE i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?{$tc_ii}
                UNION ALL
                SELECT uii.pr_id, uii.qty, uii.total AS line_total
                FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
                WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_uii}
                {$gp_ot_sold_union}
                {$gp_tp_union}
            ) s
            GROUP BY s.pr_id
        ) sold ON sold.pr_id = pop.pr_id
        LEFT JOIN (
            SELECT r.pr_id, SUM(r.qty) qty_returned, SUM(r.amt) amt_returned
            FROM (
                SELECT ri.prid pr_id, ri.qty, ri.total amt
                FROM user_return_stock_items ri
                WHERE ri.to_usertype=?".($filter_tp > 0 ? " AND ri.to_userid={$filter_tp}" : "")." AND ri.date BETWEEN ? AND ?
                {$gp_srt_ot_ret_union}
            ) r
            GROUP BY r.pr_id
        ) ret ON ret.pr_id = pop.pr_id";
    // pop's params: sold-union params once, then return-union params once.
    // Then sold subquery repeats its own params, then ret subquery repeats
    // its own params — matching the SQL text's placeholder order exactly.
    $gp_srt_params = array_merge($gp_srt_sold_params, $gp_srt_ret_params, $gp_srt_sold_params, $gp_srt_ret_params);
    $gp_srt_row = crow($db_conn, $gp_srt_sql, str_repeat('s', count($gp_srt_params)), $gp_srt_params);

    $grand_napkin_sold_amt_llp    = (float)($gp_srt_row['napkin_sold_amt'] ?? 0);
    $grand_napkin_sold_qty_llp    = (float)($gp_srt_row['napkin_sold_qty'] ?? 0);
    $grand_napkin_return_amt_llp  = (float)($gp_srt_row['napkin_return_amt'] ?? 0);
    $grand_napkin_return_qty_llp  = (float)($gp_srt_row['napkin_return_qty'] ?? 0);
    $grand_napkin_turnover_amt_llp = $grand_napkin_sold_amt_llp - $grand_napkin_return_amt_llp;
    $grand_napkin_turnover_qty_llp = $grand_napkin_sold_qty_llp - $grand_napkin_return_qty_llp;

    $grand_diaper_sold_amt_llp    = (float)($gp_srt_row['diaper_sold_amt'] ?? 0);
    $grand_diaper_sold_qty_llp    = (float)($gp_srt_row['diaper_sold_qty'] ?? 0);
    $grand_diaper_return_amt_llp  = (float)($gp_srt_row['diaper_return_amt'] ?? 0);
    $grand_diaper_return_qty_llp  = (float)($gp_srt_row['diaper_return_qty'] ?? 0);
    $grand_diaper_turnover_amt_llp = $grand_diaper_sold_amt_llp - $grand_diaper_return_amt_llp;
    $grand_diaper_turnover_qty_llp = $grand_diaper_sold_qty_llp - $grand_diaper_return_qty_llp;

    $grand_combined_sold_amt_llp     = $grand_napkin_sold_amt_llp + $grand_diaper_sold_amt_llp;
    $grand_combined_sold_qty_llp     = $grand_napkin_sold_qty_llp + $grand_diaper_sold_qty_llp;
    $grand_combined_return_amt_llp   = $grand_napkin_return_amt_llp + $grand_diaper_return_amt_llp;
    $grand_combined_return_qty_llp   = $grand_napkin_return_qty_llp + $grand_diaper_return_qty_llp;
    $grand_combined_turnover_amt_llp = $grand_napkin_turnover_amt_llp + $grand_diaper_turnover_amt_llp;
    $grand_combined_turnover_qty_llp = $grand_napkin_turnover_qty_llp + $grand_diaper_turnover_qty_llp;
}

// ═══════════════════════════════════════════════════════════════════════════
// 1c. CHANNEL BREAKDOWN — only for the "Income to Company" scope, so it's
// visible that every channel (SS, S, SD, D, Customer, Shop, OT) is actually
// being counted in the totals above, not just a single opaque figure.
// ═══════════════════════════════════════════════════════════════════════════
$channel_labels = [
    'super_stockiest'   => 'Super Stockist',
    'stockiest'         => 'Stockist',
    'super_distributor' => 'Super Distributor',
    'distributor'       => 'Distributor',
    'company'           => 'Customer (Direct)',
    'shop'              => 'Shop',
    'territory_partner' => 'Territory Partner',
];
$channel_breakdown = [];
if ($scope === 'company') {
    // Income to Company = company's own direct customer sales (invoice
    // table, user_type='company' — every invoice row has a customer_id, so
    // this table is really "<seller type> -> Customer") plus the
    // user_invoice rows where the company is specifically the issuer
    // (from_user_type='company', i.e. company billing SS/S/SD/D/Shop).
    $ch_a = call_rows($db_conn,
        "SELECT user_type ch, COUNT(*) cnt, COALESCE(SUM(total-courier_charges),0) rev FROM invoice
         WHERE user_type='company' AND sub_total>0 AND `date` BETWEEN ? AND ?
         GROUP BY user_type", 'ss', [$from, $to]);
    $ch_b = call_rows($db_conn,
        "SELECT to_user_type ch, COUNT(*) cnt, COALESCE(SUM(total-courier_charges),0) rev FROM user_invoice
         WHERE from_user_type='company' AND sub_total>0 AND `date` BETWEEN ? AND ?
         GROUP BY to_user_type", 'ss', [$from, $to]);
    foreach (array_merge($ch_a, $ch_b) as $r) {
        $key = $r['ch'];
        if (!isset($channel_labels[$key])) continue; // skip stray/unlabelled types
        if (!isset($channel_breakdown[$key])) $channel_breakdown[$key] = ['cnt' => 0, 'rev' => 0.0];
        $channel_breakdown[$key]['cnt'] += (int)$r['cnt'];
        $channel_breakdown[$key]['rev'] += (float)$r['rev'];
    }
    // Territory Partner channel — the dedicated tp_invoices table (company's
    // own invoices to a TP; distinct from user_invoice, which TP billing no
    // longer uses at all). $tpi_row already reflects every company-created
    // TP invoice (see $tpinv_source_sql above), so this channel picks up the
    // same figures as the Overview KPI with no extra query needed here.
    if (!isset($channel_breakdown['territory_partner'])) $channel_breakdown['territory_partner'] = ['cnt' => 0, 'rev' => 0.0];
    $channel_breakdown['territory_partner']['cnt'] += (int)($tpi_row['cnt'] ?? 0);
    $channel_breakdown['territory_partner']['rev'] += (float)($tpi_row['rev'] ?? 0);
    // OT channel revenue net of its own returns (ot_sales_return) — same
    // $ot_returns_row already computed for the Overview Returns KPI above.
    $channel_breakdown['ot'] = [
        'cnt' => (int)($ot_row['cnt'] ?? 0),
        'rev' => (float)($ot_row['rev'] ?? 0) - (float)($ot_returns_row['amount'] ?? 0),
    ];
    $channel_labels['ot'] = 'OT Channel';

    // Net every non-OT channel against its own returns — user_return_stock
    // rows with to_usertype='company' are goods coming back to the company
    // from whichever channel originally bought them (from_usertype), so this
    // reconciles Channel Breakdown's per-row revenue to the same net-of-return
    // basis the top Sales/Total Turnover KPI already uses ($returns_row above).
    // Without this, every row here was shown gross (pre-return) while the KPI
    // card was net, so the two disagreed by the full company-bound return
    // total ($returns_row — see the Overview KPI section).
    $ch_returns = call_rows($db_conn,
        "SELECT from_usertype ch, COALESCE(SUM(total),0) amount FROM (
            SELECT returnid, from_usertype, MAX(total) total FROM user_return_stock
            WHERE to_usertype='company' AND `date` BETWEEN ? AND ?
            GROUP BY returnid, from_usertype
         ) x GROUP BY from_usertype",
        'ss', [$from, $to]);
    foreach ($ch_returns as $r) {
        // from_usertype='customer' is a direct customer return against a
        // company-issued invoice — the same population $ch_a groups under
        // invoice.user_type='company' (channel key 'company', labelled
        // "Customer (Direct)"). Every other from_usertype value already
        // matches a channel_labels key verbatim.
        $key = $r['ch'] === 'customer' ? 'company' : $r['ch'];
        if (!isset($channel_breakdown[$key])) continue; // no sale row for this channel to net against
        $channel_breakdown[$key]['rev'] -= (float)$r['amount'];
    }
}
$channel_total_rev = array_sum(array_column($channel_breakdown, 'rev')) ?: 1;

// ═══════════════════════════════════════════════════════════════════════════
// 2. DAILY TREND CHART DATA
// ═══════════════════════════════════════════════════════════════════════════
$dc = call_rows($db_conn,
    "SELECT `date` d, COALESCE(SUM(total-courier_charges),0) rev FROM invoice
     WHERE user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_inv}
     GROUP BY `date` ORDER BY `date`",
    'sss', [$utype, $from, $to]);
$ds = call_rows($db_conn,
    "SELECT `date` d, COALESCE(SUM(total-courier_charges),0) rev FROM user_invoice
     WHERE from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_ui}
     GROUP BY `date` ORDER BY `date`",
    'sss', [$utype, $from, $to]);
$dt = [];
if ($tpinv_source_sql) {
    $dt = call_rows($db_conn,
        "SELECT invoice_date d, COALESCE(SUM(total_amount-courier_charges),0) rev FROM tp_invoices
         WHERE {$tpinv_source_sql} AND invoice_date BETWEEN ? AND ?{$tc_tpi}
         GROUP BY invoice_date ORDER BY invoice_date",
        'ss', [$from, $to]);
}
// OT channel — company scope only, net of its own returns per day, same
// convention as the Channel Breakdown's OT row above.
$do = $dor = [];
if ($scope === 'company') {
    $do = call_rows($db_conn,
        "SELECT `date` d, COALESCE(SUM(total),0) rev FROM ot_sales
         WHERE `date` BETWEEN ? AND ? GROUP BY `date` ORDER BY `date`",
        'ss', [$from, $to]);
    $dor = call_rows($db_conn,
        "SELECT return_date d, COALESCE(SUM(total),0) rev FROM ot_sales_return
         WHERE return_date BETWEEN ? AND ? GROUP BY return_date ORDER BY return_date",
        'ss', [$from, $to]);
}
$dm = [];
foreach ($dc as $r) $dm[$r['d']]['c'] = (float)$r['rev'];
foreach ($ds as $r) $dm[$r['d']]['s'] = (float)$r['rev'];
foreach ($dt as $r) $dm[$r['d']]['t'] = (float)$r['rev'];
foreach ($do as $r) $dm[$r['d']]['o'] = ($dm[$r['d']]['o'] ?? 0) + (float)$r['rev'];
foreach ($dor as $r) $dm[$r['d']]['o'] = ($dm[$r['d']]['o'] ?? 0) - (float)$r['rev'];

// Net non-OT returns (user_return_stock, company-bound) into their day +
// channel, same reconciliation Channel Breakdown applies (see $ch_returns
// above) — without this the trend was net of only OT's own returns, so its
// summed total sat between the top KPI's gross Sales and net Total Turnover,
// matching neither. Company scope only, same gating as Channel Breakdown /
// the OT netting above ($returns_row's to_usertype=$utype check already
// covers every scope for the top KPI card, but this chart only carries
// per-day 'c'/'s'/'t' series that line up with a channel for company scope).
if ($scope === 'company') {
    $dret = call_rows($db_conn,
        "SELECT `date` d, from_usertype ch, COALESCE(SUM(total),0) amount FROM (
            SELECT returnid, `date`, from_usertype, MAX(total) total FROM user_return_stock
            WHERE to_usertype='company' AND `date` BETWEEN ? AND ?
            GROUP BY returnid, `date`, from_usertype
         ) x GROUP BY `date`, from_usertype",
        'ss', [$from, $to]);
    foreach ($dret as $r) {
        // Unlike Channel Breakdown (which splits $ch_b by to_user_type into
        // one row per business channel), this chart's "Shop" series ($ds) is
        // `user_invoice WHERE from_user_type='company'` summed with NO
        // to_user_type split — so it already includes Super Stockist,
        // Stockist, Distributor and Super Distributor revenue folded in
        // under that one line (confirmed: $ds's August total, 30,51,625,
        // equals Shop's 8,954 + Super Stockist's 30,42,671 combined). Every
        // from_usertype value other than 'customer'/'territory_partner'
        // therefore nets against that same 's' series, not just 'shop'.
        $series = match ($r['ch']) {
            'customer' => 'c',
            'territory_partner' => 't',
            default => 's',
        };
        $dm[$r['d']][$series] = ($dm[$r['d']][$series] ?? 0) - (float)$r['amount'];
    }
}
$chart_labels = $chart_cust = $chart_shop = $chart_tp = $chart_ot = [];
$ptr = strtotime($from); $end = strtotime($to);
while ($ptr <= $end) {
    $d = date('Y-m-d', $ptr);
    $chart_labels[] = date('d M', $ptr);
    $chart_cust[]   = $dm[$d]['c'] ?? 0;
    $chart_shop[]   = $dm[$d]['s'] ?? 0;
    $chart_tp[]     = $dm[$d]['t'] ?? 0;
    $chart_ot[]     = $dm[$d]['o'] ?? 0;
    $ptr = strtotime('+1 day', $ptr);
}

// ═══════════════════════════════════════════════════════════════════════════
// 3. PERIOD BREAKDOWN
// ═══════════════════════════════════════════════════════════════════════════
function company_period($db, $utype, $from, $to, $tc_inv, $tc_ui, $gfmt, $lfmt, $tpinv_source_sql, $tc_tpi, $scope) {
    $cust = call_rows($db,
        "SELECT DATE_FORMAT(`date`,'$gfmt') g, DATE_FORMAT(MIN(`date`),'$lfmt') lbl,
                COUNT(*) cnt, COALESCE(SUM(total-courier_charges),0) rev
         FROM invoice WHERE user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_inv}
         GROUP BY g ORDER BY g",
        'sss', [$utype, $from, $to]);
    $shop = call_rows($db,
        "SELECT DATE_FORMAT(`date`,'$gfmt') g, DATE_FORMAT(MIN(`date`),'$lfmt') lbl,
                COUNT(*) cnt, COALESCE(SUM(total-courier_charges),0) rev
         FROM user_invoice WHERE from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_ui}
         GROUP BY g ORDER BY g",
        'sss', [$utype, $from, $to]);
    $tp = [];
    if ($tpinv_source_sql) {
        $tp = call_rows($db,
            "SELECT DATE_FORMAT(invoice_date,'$gfmt') g, DATE_FORMAT(MIN(invoice_date),'$lfmt') lbl,
                    COUNT(*) cnt, COALESCE(SUM(total_amount-courier_charges),0) rev
             FROM tp_invoices WHERE {$tpinv_source_sql} AND invoice_date BETWEEN ? AND ?{$tc_tpi}
             GROUP BY g ORDER BY g",
            'ss', [$from, $to]);
    }
    // OT channel — company scope only, net of its own returns per bucket,
    // same convention as the Channel Breakdown / Daily Trend chart's OT figures.
    $ot = $otret = [];
    if ($scope === 'company') {
        $ot = call_rows($db,
            "SELECT DATE_FORMAT(`date`,'$gfmt') g, DATE_FORMAT(MIN(`date`),'$lfmt') lbl,
                    COUNT(DISTINCT tempid) cnt, COALESCE(SUM(total),0) rev
             FROM ot_sales WHERE `date` BETWEEN ? AND ?
             GROUP BY g ORDER BY g",
            'ss', [$from, $to]);
        $otret = call_rows($db,
            "SELECT DATE_FORMAT(return_date,'$gfmt') g, COALESCE(SUM(total),0) rev
             FROM ot_sales_return WHERE return_date BETWEEN ? AND ?
             GROUP BY g",
            'ss', [$from, $to]);
    }
    $map = [];
    foreach ($cust as $r) { $map[$r['g']]['lbl']=$r['lbl']; $map[$r['g']]['c']=(float)$r['rev']; $map[$r['g']]['cc']=(int)$r['cnt']; }
    foreach ($shop as $r) { $map[$r['g']]['lbl']=$map[$r['g']]['lbl']??$r['lbl']; $map[$r['g']]['s']=(float)$r['rev']; $map[$r['g']]['sc']=(int)$r['cnt']; }
    foreach ($tp as $r) { $map[$r['g']]['lbl']=$map[$r['g']]['lbl']??$r['lbl']; $map[$r['g']]['t']=(float)$r['rev']; $map[$r['g']]['tc']=(int)$r['cnt']; }
    foreach ($ot as $r) { $map[$r['g']]['lbl']=$map[$r['g']]['lbl']??$r['lbl']; $map[$r['g']]['o']=(float)$r['rev']; $map[$r['g']]['oc']=(int)$r['cnt']; }
    foreach ($otret as $r) { $map[$r['g']]['o']=($map[$r['g']]['o']??0)-(float)$r['rev']; }
    // Net non-OT returns (user_return_stock, company-bound) into the same
    // bucket + series they were originally sold under — same reconciliation
    // Channel Breakdown / Daily Trend chart apply (see $ch_returns / $dret),
    // and the same reason: without this every period bucket here was net of
    // only OT's own returns, so the table's grand total sat between the top
    // KPI's gross Sales and net Total Turnover, matching neither.
    if ($scope === 'company') {
        $ret = call_rows($db,
            "SELECT DATE_FORMAT(`date`,'$gfmt') g, DATE_FORMAT(MIN(`date`),'$lfmt') lbl, from_usertype ch, COALESCE(SUM(total),0) amount FROM (
                SELECT returnid, `date`, from_usertype, MAX(total) total FROM user_return_stock
                WHERE to_usertype='company' AND `date` BETWEEN ? AND ?
                GROUP BY returnid, `date`, from_usertype
             ) x GROUP BY g, from_usertype",
            'ss', [$from, $to]);
        foreach ($ret as $r) {
            // Same from_usertype -> series mapping as the Daily Trend chart's
            // $dret loop: 'customer' nets against 'c', 'territory_partner'
            // against 't', everything else (shop/super_stockiest/stockiest/
            // distributor/super_distributor) against 's' — $shop above is
            // already every non-customer, non-TP company-issued sale summed
            // with no to_user_type split, so it already carries all of those
            // channels folded in under one series.
            $series = match ($r['ch']) {
                'customer' => 'c',
                'territory_partner' => 't',
                default => 's',
            };
            // A bucket that only has a return (no sale) has no 'lbl' yet —
            // set it here too so cmp_period_table() shows the pretty label
            // instead of falling back to the raw group key.
            $map[$r['g']]['lbl'] = $map[$r['g']]['lbl'] ?? $r['lbl'];
            $map[$r['g']][$series] = ($map[$r['g']][$series] ?? 0) - (float)$r['amount'];
        }
    }
    ksort($map); return $map;
}
$daily_p   = company_period($db_conn,$utype,$from,$to,$tc_inv,$tc_ui,'%Y-%m-%d','%d %b',$tpinv_source_sql,$tc_tpi,$scope);
$weekly_p  = company_period($db_conn,$utype,$from,$to,$tc_inv,$tc_ui,'%Y-%u','W%u %Y',$tpinv_source_sql,$tc_tpi,$scope);
$monthly_p = company_period($db_conn,$utype,$from,$to,$tc_inv,$tc_ui,'%Y-%m','%b %Y',$tpinv_source_sql,$tc_tpi,$scope);
$yearly_p  = company_period($db_conn,$utype,$from,$to,$tc_inv,$tc_ui,'%Y','%Y',$tpinv_source_sql,$tc_tpi,$scope);

// ═══════════════════════════════════════════════════════════════════════════
// 4. PRODUCT-WISE SALES
// ═══════════════════════════════════════════════════════════════════════════
// OT channel (Amazon/Flipkart/Website/etc.) is folded in for company scope
// only, same as the Gross Profit union above. 'ID CONCEPT' rows are counted
// as real sales here, consistent with every other OT query on this report.
$ps_ot_union  = '';
$ps_params    = [$utype, $from, $to, $utype, $from, $to];
if ($scope === 'company') {
    $ps_ot_union = "UNION ALL
         SELECT os.prid, os.qty, os.total
         FROM ot_sales os WHERE os.date BETWEEN ? AND ?";
    $ps_params[] = $from;
    $ps_params[] = $to;
}
// tp_invoices: see memory "neksomo-sold-by-company-calc" — a company sale can
// land in invoice / user_invoice / tp_invoices, all three must be summed.
$ps_tp_union = '';
if ($tpinv_source_sql) {
    // Net against both the per-line and (pro-rated) invoice-level discount so
    // this reconciles to tp_invoices.total_amount, same as the Gross Profit
    // union above ($gp_tp_union / $gp_tp_net_line_amt) — see that comment for
    // the reconciliation examples.
    $ps_tp_union = "UNION ALL
         SELECT tpii.product_id, tpii.quantity, {$gp_tp_net_line_amt}
         FROM tp_invoice_items tpii JOIN tp_invoices tpi ON tpi.id=tpii.tp_invoice_id
         WHERE {$tpinv_source_sql} AND tpi.invoice_date BETWEEN ? AND ?{$tc_tpi}";
    $ps_params[] = $from;
    $ps_params[] = $to;
}
$product_sales = call_rows($db_conn,
    "SELECT p.id pid, p.productName,
            COALESCE(SUM(d.qty),0) total_qty,
            COALESCE(SUM(d.total),0) total_rev
     FROM (
         SELECT ii.pr_id, ii.qty, ii.total
         FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
         WHERE i.user_type=? AND i.date BETWEEN ? AND ?{$tc_ii}
         UNION ALL
         SELECT uii.pr_id, uii.qty, uii.total
         FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
         WHERE ui.from_user_type=? AND ui.date BETWEEN ? AND ?{$tc_uii}
         {$ps_ot_union}
         {$ps_tp_union}
     ) d JOIN products p ON p.id=d.pr_id
     GROUP BY p.id, p.productName ORDER BY total_qty DESC LIMIT 25",
    str_repeat('s', count($ps_params)), $ps_params);
$grand_qty = array_sum(array_column($product_sales, 'total_qty')) ?: 1;

// ═══════════════════════════════════════════════════════════════════════════
// PIECES SOLD — company-scope sales converted from pack qty to individual
// pieces (qty × products.pieces_per_pack), attributed to a single legal
// entity (company_godown) since invoice/user_invoice/ot_sales all carry the
// godown id as the seller id when user_type='company'. Products with no
// pieces_per_pack set fall back to 1 (pieces == pack qty), flagged in the UI.
// Entity-scoped by GodownAccess.php: $filter_entity picks one allowed
// godown, or falls back to every godown this login is allowed to see.
// ═══════════════════════════════════════════════════════════════════════════
$pieces_sold = [];
$grand_total_pieces = 0;
$grand_total_pack_qty = 0;
$grand_total_value = 0.0;
$grand_total_unrated_pieces = 0;
$grand_total_purchase_value = 0.0;
$grand_total_unpriced_pieces = 0;
$grand_gross_profit = 0.0;
$grand_total_expense = 0.0;
$grand_net_profit = 0.0;
$grand_total_return_qty = 0;
$grand_total_return_pieces = 0;
$grand_total_return_value = 0.0;
$grand_total_return_purchase_value = 0.0;
$grand_total_net_qty = 0;
$grand_total_net_pieces = 0;
$grand_total_gst_value = 0.0;
$grand_total_return_gst_value = 0.0;
$grand_total_purchase_gst_value = 0.0;
$grand_total_return_purchase_gst_value = 0.0;
$grand_total_output_gst = 0.0;
$grand_total_input_gst = 0.0;
$grand_total_net_gst = 0.0;
$selected_entity_name = 'All Visible Entities';
$diaper_sold = [];
$grand_diaper_pack_qty = 0;
$grand_diaper_return_qty = 0;
$grand_diaper_net_qty = 0;
$grand_diaper_value = 0.0;
$grand_diaper_return_value = 0.0;
$grand_diaper_purchase_value = 0.0;
$grand_diaper_return_purchase_value = 0.0;
$grand_diaper_unrated_qty = 0;
$grand_diaper_unpriced_qty = 0;
$grand_diaper_gst_value = 0.0;
$grand_diaper_return_gst_value = 0.0;
$grand_diaper_purchase_gst_value = 0.0;
$grand_diaper_return_purchase_gst_value = 0.0;
$grand_diaper_gross_profit = 0.0;
$grand_diaper_expense = 0.0;
$grand_diaper_net_profit = 0.0;
$grand_diaper_output_gst = 0.0;
$grand_diaper_input_gst = 0.0;
$grand_diaper_net_gst = 0.0;
$grand_combined_gross_profit = 0.0;
$grand_combined_expense = 0.0;
$grand_combined_net_profit = 0.0;
$grand_combined_net_gst = 0.0;
if ($is_neksomo_view) {
    // Self-migrating: this block is the only thing standing between a
    // neksomo login and dashboard.php's `include("mis-report.php")` — with
    // error_reporting(0) above, any of these tables/columns missing turns
    // into a silent white screen (query returns false, ->fetch_all() on
    // false is a fatal error) instead of a visible error. Every table below
    // is otherwise only created by a specific management page being visited
    // at least once (neksomo-product-map-list.php, expense-tracker.php) or
    // by manually running a db_migrations/*.sql file — neither is guaranteed
    // to have happened in every environment, so this report has to be able
    // to provision its own dependencies rather than assume they exist.
    require_once("include/NeksomoProductMapping.php");
    ensure_neksomo_product_mapping_table($db_conn);

    $db_conn->query("
        CREATE TABLE IF NOT EXISTS pl_godown_transfers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            transfer_type ENUM('godown_to_location','location_to_godown') NOT NULL,
            godown_id INT NOT NULL,
            location_id INT UNSIGNED DEFAULT NULL,
            cp_id INT UNSIGNED DEFAULT NULL,
            transfer_date DATE NOT NULL,
            ref_number VARCHAR(50) NOT NULL DEFAULT '',
            note VARCHAR(255) NOT NULL DEFAULT '',
            created_by VARCHAR(100) NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_plgt_godown (godown_id),
            KEY idx_plgt_location (location_id),
            KEY idx_plgt_type (transfer_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db_conn->query("
        CREATE TABLE IF NOT EXISTS neksomo_llp_piece_rates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            effective_date DATE NOT NULL,
            rate_per_piece DECIMAL(10,2) NOT NULL,
            created_by VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_product_date (product_id, effective_date),
            KEY idx_product (product_id),
            KEY idx_sale_date (effective_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db_conn->query("
        CREATE TABLE IF NOT EXISTS neksomo_llp_piece_purchase_rates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            effective_date DATE NOT NULL,
            rate_per_piece DECIMAL(10,2) NOT NULL,
            created_by VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_product_date (product_id, effective_date),
            KEY idx_product (product_id),
            KEY idx_effective_date (effective_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // expense_imports / expense_import_items may not exist at all yet on a
    // fresh environment (normally created by expense-tracker.php), and even
    // where they exist, period_from/period_to/date are later additions.
    $db_conn->query("
        CREATE TABLE IF NOT EXISTS expense_imports (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            expense_month DATE NOT NULL,
            source_filename VARCHAR(255) NOT NULL,
            group_name VARCHAR(255) DEFAULT NULL,
            period_label VARCHAR(255) DEFAULT NULL,
            total_debit DECIMAL(15,2) NOT NULL DEFAULT 0,
            total_credit DECIMAL(15,2) NOT NULL DEFAULT 0,
            net_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            uploaded_by VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_company_month (company_id, expense_month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $db_conn->query("
        CREATE TABLE IF NOT EXISTS expense_import_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            import_id INT UNSIGNED NOT NULL,
            particulars VARCHAR(255) NOT NULL,
            debit DECIMAL(15,2) NOT NULL DEFAULT 0,
            credit DECIMAL(15,2) NOT NULL DEFAULT 0,
            net_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            KEY idx_import (import_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    if (($__c = $db_conn->query("SHOW COLUMNS FROM expense_imports LIKE 'period_from'")) && $__c->num_rows === 0) {
        $db_conn->query("ALTER TABLE expense_imports ADD COLUMN period_from DATE DEFAULT NULL AFTER expense_month");
        $db_conn->query("ALTER TABLE expense_imports ADD COLUMN period_to DATE DEFAULT NULL AFTER period_from");
    }
    if (($__c = $db_conn->query("SHOW COLUMNS FROM expense_import_items LIKE 'date'")) && $__c->num_rows === 0) {
        $db_conn->query("ALTER TABLE expense_import_items ADD COLUMN date DATE DEFAULT NULL AFTER particulars");
    }

    $pcs_ii_cond  = $filter_entity > 0 ? " AND ii.user_id={$filter_entity}"       : " AND ii.user_id IN ({$entity_ids_subq})";
    $pcs_uii_cond = $filter_entity > 0 ? " AND uii.from_user_id={$filter_entity}" : " AND uii.from_user_id IN ({$entity_ids_subq})";
    $pcs_ot_cond  = $filter_entity > 0 ? " AND os.godownid={$filter_entity}"      : " AND os.godownid IN ({$entity_ids_subq})";
    // demofreedamage: stock given out as a demo, given away free, or written
    // off as damaged. It never generates an invoice, but the pieces still
    // left the godown for good — same as a sale, from Neksomo's piece-count
    // perspective (Femi9 LLP still owes Neksomo for pieces that left stock,
    // whatever the reason). 'Conversion' rows are a different concept
    // (internal stock reclassification, not stock leaving the business) and
    // are deliberately excluded here.
    $pcs_dfd_cond = $filter_entity > 0 ? " AND dfd.userid={$filter_entity}" : " AND dfd.userid IN ({$entity_ids_subq})";
    // tp_invoices: territory partner invoices, sourced either directly from a
    // company godown (source_godown_id) or through a channel partner
    // (source_cp_id). Any CP-sourced invoice counts as LLP+Healthcare
    // turnover unconditionally — no trace-back through pl_godown_transfers to
    // confirm which godown actually supplied that CP. This can pull in CP
    // sales that were never actually supplied by LLP/Healthcare stock, but
    // that's the intended behavior per explicit confirmation.
    // See memory "neksomo-sold-by-company-calc" — a company sale can land in any
    // of invoice / user_invoice / tp_invoices, all three must be summed.
    $pcs_tpi_cond = $filter_entity > 0
        ? " AND ( (tpi.source_cp_id=0 AND tpi.source_godown_id={$filter_entity}) OR tpi.source_cp_id>0 )"
        : " AND ( (tpi.source_cp_id=0 AND tpi.source_godown_id IN ({$entity_ids_subq})) OR tpi.source_cp_id>0 )";

    // Per-day granularity is kept through the first aggregation (d.pr_id,
    // d.date) so each day's pieces can be valued against whichever
    // neksomo_llp_piece_rates / neksomo_llp_piece_purchase_rates row was
    // effective ON THAT DATE (the latest effective_date <= the sale date) —
    // both the sale rate and the purchase rate take effect the day they're
    // entered and hold until a later effective_date for the same product
    // supersedes it. Only then is everything rolled up to one row per product.
    //
    // Rates are entered against the Neksomo piece-product (products.temp_id
    // LIKE 'NKS-%'), never against the company pack SKU that actually shows
    // up in invoice_items.pr_id (d.pr_id here) — see NeksomoProductMapping.php.
    // So every rate lookup must translate d.pr_id -> its mapped
    // neksomo_product_id via neksomo_product_mapping before hitting the rate
    // tables, the same way NeksomoStockHelper::get_neksomo_pieces_sold_via_llp_healthcare()
    // does it. Comparing r.product_id straight to d.pr_id compares two
    // different id spaces and silently matches nothing.
    //
    // Every card in this section is scoped to mapped products only: the
    // WHERE clause below drops sold/returned pack SKUs that have no Neksomo
    // mapping at all before they're ever summed, so an unmapped product can't
    // inflate Total Pack Qty Sold / Return Qty / Consolidated Qty while still
    // reading ₹0 for Sold Price and Gross Profit. It also excludes mappings
    // to a diaper-category Neksomo product (np.category='diaper') — those
    // belong in the Diaper section below, not here. Napkin's existing
    // Neksomo products (18/19/20) have category NULL (added before the
    // category field existed), so the filter is "not diaper", not "= napkin".
    $pieces_sold = call_rows($db_conn,
        "SELECT p.id pid, p.productName, p.pieces_per_pack,
                COALESCE(SUM(dp.day_qty),0) total_qty,
                COALESCE(SUM(dp.day_qty * COALESCE(NULLIF(p.pieces_per_pack,0),1)),0) total_pieces,
                COALESCE(SUM(CASE WHEN dp.rate IS NOT NULL THEN dp.day_qty * COALESCE(NULLIF(p.pieces_per_pack,0),1) * dp.rate ELSE 0 END),0) total_value,
                COALESCE(SUM(CASE WHEN dp.rate IS NULL THEN dp.day_qty * COALESCE(NULLIF(p.pieces_per_pack,0),1) ELSE 0 END),0) unrated_pieces,
                COALESCE(SUM(CASE WHEN dp.rate IS NOT NULL THEN dp.day_qty * COALESCE(NULLIF(p.pieces_per_pack,0),1) * dp.rate_gst_amt ELSE 0 END),0) total_gst_value,
                COALESCE(SUM(CASE WHEN dp.purchase_rate IS NOT NULL THEN dp.day_qty * COALESCE(NULLIF(p.pieces_per_pack,0),1) * dp.purchase_rate ELSE 0 END),0) total_purchase_value,
                COALESCE(SUM(CASE WHEN dp.purchase_rate IS NULL THEN dp.day_qty * COALESCE(NULLIF(p.pieces_per_pack,0),1) ELSE 0 END),0) unpriced_pieces,
                COALESCE(SUM(CASE WHEN dp.purchase_rate IS NOT NULL THEN dp.day_qty * COALESCE(NULLIF(p.pieces_per_pack,0),1) * dp.purchase_rate_gst_amt ELSE 0 END),0) total_purchase_gst_value
         FROM (
             SELECT d.pr_id, d.date, SUM(d.qty) day_qty,
                    -- GST is a pass-through tax, not revenue, so Gross Profit is always
                    -- computed on the taxable (pre-tax) rate: an exclusive-type rate is
                    -- already pre-tax as entered; an inclusive-type rate has GST backed
                    -- out of it here. gst_rate/gst_type are snapshotted onto the rate row
                    -- at entry time (see neksomo-llp-piece-sale-action.php), so this isn't
                    -- affected by a product's GST% changing later. The GST portion itself
                    -- (rate_gst_amt/purchase_rate_gst_amt) is computed alongside and summed
                    -- separately (total_gst_value/total_purchase_gst_value) — shown in the
                    -- report as its own figure, never folded into Gross Profit.
                    (SELECT CASE WHEN r.gst_type = 'inclusive' THEN r.rate_per_piece / (1 + r.gst_rate/100) ELSE r.rate_per_piece END
                     FROM neksomo_llp_piece_rates r
                     WHERE r.product_id = (SELECT m.neksomo_product_id FROM neksomo_product_mapping m WHERE m.company_product_id = d.pr_id LIMIT 1)
                       AND r.effective_date <= d.date
                     ORDER BY r.effective_date DESC LIMIT 1) rate,
                    (SELECT CASE WHEN r.gst_type = 'inclusive' THEN r.rate_per_piece - r.rate_per_piece / (1 + r.gst_rate/100) ELSE r.rate_per_piece * r.gst_rate/100 END
                     FROM neksomo_llp_piece_rates r
                     WHERE r.product_id = (SELECT m.neksomo_product_id FROM neksomo_product_mapping m WHERE m.company_product_id = d.pr_id LIMIT 1)
                       AND r.effective_date <= d.date
                     ORDER BY r.effective_date DESC LIMIT 1) rate_gst_amt,
                    (SELECT CASE WHEN pr.gst_type = 'inclusive' THEN pr.rate_per_piece / (1 + pr.gst_rate/100) ELSE pr.rate_per_piece END
                     FROM neksomo_llp_piece_purchase_rates pr
                     WHERE pr.product_id = (SELECT m.neksomo_product_id FROM neksomo_product_mapping m WHERE m.company_product_id = d.pr_id LIMIT 1)
                       AND pr.effective_date <= d.date
                     ORDER BY pr.effective_date DESC LIMIT 1) purchase_rate,
                    (SELECT CASE WHEN pr.gst_type = 'inclusive' THEN pr.rate_per_piece - pr.rate_per_piece / (1 + pr.gst_rate/100) ELSE pr.rate_per_piece * pr.gst_rate/100 END
                     FROM neksomo_llp_piece_purchase_rates pr
                     WHERE pr.product_id = (SELECT m.neksomo_product_id FROM neksomo_product_mapping m WHERE m.company_product_id = d.pr_id LIMIT 1)
                       AND pr.effective_date <= d.date
                     ORDER BY pr.effective_date DESC LIMIT 1) purchase_rate_gst_amt
             FROM (
                 SELECT ii.pr_id, ii.qty, i.date
                 FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
                 WHERE i.user_type=? AND i.date BETWEEN ? AND ?{$pcs_ii_cond}
                 UNION ALL
                 SELECT uii.pr_id, uii.qty, ui.date
                 FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
                 WHERE ui.from_user_type=? AND ui.date BETWEEN ? AND ?{$pcs_uii_cond}
                 UNION ALL
                 SELECT os.prid, os.qty, os.date
                 FROM ot_sales os
                 WHERE os.date BETWEEN ? AND ?{$pcs_ot_cond}
                 UNION ALL
                 SELECT tpii.product_id, tpii.quantity, tpi.invoice_date
                 FROM tp_invoice_items tpii JOIN tp_invoices tpi ON tpi.id=tpii.tp_invoice_id
                 WHERE tpi.invoice_date BETWEEN ? AND ?{$pcs_tpi_cond}
                 UNION ALL
                 SELECT dfd.product_id, dfd.qty, dfd.date
                 FROM demofreedamage dfd
                 WHERE dfd.usertype=? AND dfd.category IN ('Demo','Free','Damage') AND dfd.date BETWEEN ? AND ?{$pcs_dfd_cond}
             ) d
             WHERE d.pr_id IN (SELECT m.company_product_id FROM neksomo_product_mapping m
                               JOIN products np ON np.id = m.neksomo_product_id
                               WHERE COALESCE(np.category, '') != 'diaper')
             GROUP BY d.pr_id, d.date
         ) dp JOIN products p ON p.id = dp.pr_id
         GROUP BY p.id, p.productName, p.pieces_per_pack
         ORDER BY total_pieces DESC",
        'sssssssssssss', [$utype, $from, $to, $utype, $from, $to, $from, $to, $from, $to, $utype, $from, $to]);

    $grand_total_pieces          = (int) array_sum(array_column($pieces_sold, 'total_pieces'));
    $grand_total_pack_qty        = (int) array_sum(array_column($pieces_sold, 'total_qty'));
    $grand_total_value           = (float) array_sum(array_column($pieces_sold, 'total_value'));
    $grand_total_unrated_pieces  = (int) array_sum(array_column($pieces_sold, 'unrated_pieces'));
    $grand_total_gst_value       = (float) array_sum(array_column($pieces_sold, 'total_gst_value'));
    $grand_total_purchase_value  = (float) array_sum(array_column($pieces_sold, 'total_purchase_value'));
    $grand_total_unpriced_pieces = (int) array_sum(array_column($pieces_sold, 'unpriced_pieces'));
    $grand_total_purchase_gst_value = (float) array_sum(array_column($pieces_sold, 'total_purchase_gst_value'));

    // Expense — for a neksomo login specifically, always Neksomo's own
    // expense uploads, NOT LLP's — even though $filter_entity above is
    // deliberately overridden to LLP for the Pieces Sold table (LLP is who
    // actually issues the sales this report tracks; Neksomo's own godown
    // doesn't). Every other login keeps the prior behavior: this section's
    // own entity filter ($filter_entity or every entity it can see).
    if ($is_neksomo_view) {
        $__neksomoGodown = crow($db_conn, "SELECT id FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1");
        $pcs_expense_cond = "company_id = " . (int)($__neksomoGodown['id'] ?? 0);

        // Neksomo's date-detecting expense upload (expense-tracker-upload-
        // datewise-action.php) stamps each expense_import_items row with its
        // own date, so its expenses can be matched to the exact From/To range
        // selected here — not just whichever calendar month(s) it overlaps.
        // Older/undated rows (uploaded via the Tally Group Summary path,
        // date IS NULL) still fall back to the coarser expense_month bucket
        // so nothing already uploaded silently drops out of the total.
        $grand_total_expense = (float) cval($db_conn,
            "SELECT COALESCE(SUM(eii.net_amount),0)
             FROM expense_import_items eii
             JOIN expense_imports ei ON ei.id = eii.import_id
             WHERE ei.{$pcs_expense_cond}
               AND (
                     (eii.date IS NOT NULL AND eii.date BETWEEN ? AND ?)
                  OR (eii.date IS NULL AND ei.expense_month BETWEEN DATE_FORMAT(?, '%Y-%m-01') AND DATE_FORMAT(?, '%Y-%m-01'))
               )",
            'ssss', [$from, $to, $from, $to]);
    } else {
        $pcs_expense_cond = $filter_entity > 0 ? "company_id = {$filter_entity}" : "company_id IN ({$entity_ids_subq})";
        $grand_total_expense = (float) cval($db_conn,
            "SELECT COALESCE(SUM(net_amount),0) FROM expense_imports
             WHERE {$pcs_expense_cond}
             AND expense_month BETWEEN DATE_FORMAT(?, '%Y-%m-01') AND DATE_FORMAT(?, '%Y-%m-01')",
            'ss', [$from, $to]);
    }

    if ($filter_entity > 0) {
        $entity_names = array_column($all_entities, 'gname', 'id');
        $selected_entity_name = $entity_names[$filter_entity] ?? 'Selected Entity';
    } elseif ($is_neksomo_view) {
        $selected_entity_name = implode(' + ', array_column($all_entities, 'gname'));
    }

    // Return Qty/Pieces for the Pieces Sold card — same entity scope as the
    // sales figures above, same period. Mirrors overstock_datewise.php's
    // Return Qty formula (user_return_stock_items + ot_sales_return only —
    // TP credit notes aren't counted there either, kept consistent here).
    // Return value uses the same "rate effective on the transaction's own
    // date" lookup as sold value above — a return row doesn't record which
    // original sale it came from, so it's valued at whatever rate_per_piece
    // was in effect on the return date itself, not the original sale's rate.
    $pcs_uret_cond  = $filter_entity > 0 ? " AND ri.to_userid={$filter_entity}"  : " AND ri.to_userid IN ({$entity_ids_subq})";
    $pcs_otret_cond = $filter_entity > 0 ? " AND osr.godownid={$filter_entity}"  : " AND osr.godownid IN ({$entity_ids_subq})";
    $pieces_returned = call_rows($db_conn,
        "SELECT p.id pid,
                COALESCE(SUM(dp.day_qty),0) total_qty,
                COALESCE(SUM(dp.day_qty * COALESCE(NULLIF(p.pieces_per_pack,0),1)),0) total_pieces,
                COALESCE(SUM(CASE WHEN dp.rate IS NOT NULL THEN dp.day_qty * COALESCE(NULLIF(p.pieces_per_pack,0),1) * dp.rate ELSE 0 END),0) total_value,
                COALESCE(SUM(CASE WHEN dp.rate IS NOT NULL THEN dp.day_qty * COALESCE(NULLIF(p.pieces_per_pack,0),1) * dp.rate_gst_amt ELSE 0 END),0) total_gst_value,
                COALESCE(SUM(CASE WHEN dp.purchase_rate IS NOT NULL THEN dp.day_qty * COALESCE(NULLIF(p.pieces_per_pack,0),1) * dp.purchase_rate ELSE 0 END),0) total_purchase_value,
                COALESCE(SUM(CASE WHEN dp.purchase_rate IS NOT NULL THEN dp.day_qty * COALESCE(NULLIF(p.pieces_per_pack,0),1) * dp.purchase_rate_gst_amt ELSE 0 END),0) total_purchase_gst_value
         FROM (
             SELECT d.pr_id, d.date, SUM(d.qty) day_qty,
                    (SELECT CASE WHEN r.gst_type = 'inclusive' THEN r.rate_per_piece / (1 + r.gst_rate/100) ELSE r.rate_per_piece END
                     FROM neksomo_llp_piece_rates r
                     WHERE r.product_id = (SELECT m.neksomo_product_id FROM neksomo_product_mapping m WHERE m.company_product_id = d.pr_id LIMIT 1)
                       AND r.effective_date <= d.date
                     ORDER BY r.effective_date DESC LIMIT 1) rate,
                    (SELECT CASE WHEN r.gst_type = 'inclusive' THEN r.rate_per_piece - r.rate_per_piece / (1 + r.gst_rate/100) ELSE r.rate_per_piece * r.gst_rate/100 END
                     FROM neksomo_llp_piece_rates r
                     WHERE r.product_id = (SELECT m.neksomo_product_id FROM neksomo_product_mapping m WHERE m.company_product_id = d.pr_id LIMIT 1)
                       AND r.effective_date <= d.date
                     ORDER BY r.effective_date DESC LIMIT 1) rate_gst_amt,
                    (SELECT CASE WHEN pr.gst_type = 'inclusive' THEN pr.rate_per_piece / (1 + pr.gst_rate/100) ELSE pr.rate_per_piece END
                     FROM neksomo_llp_piece_purchase_rates pr
                     WHERE pr.product_id = (SELECT m.neksomo_product_id FROM neksomo_product_mapping m WHERE m.company_product_id = d.pr_id LIMIT 1)
                       AND pr.effective_date <= d.date
                     ORDER BY pr.effective_date DESC LIMIT 1) purchase_rate,
                    (SELECT CASE WHEN pr.gst_type = 'inclusive' THEN pr.rate_per_piece - pr.rate_per_piece / (1 + pr.gst_rate/100) ELSE pr.rate_per_piece * pr.gst_rate/100 END
                     FROM neksomo_llp_piece_purchase_rates pr
                     WHERE pr.product_id = (SELECT m.neksomo_product_id FROM neksomo_product_mapping m WHERE m.company_product_id = d.pr_id LIMIT 1)
                       AND pr.effective_date <= d.date
                     ORDER BY pr.effective_date DESC LIMIT 1) purchase_rate_gst_amt
             FROM (
                 SELECT ri.prid pr_id, ri.qty, ri.date
                 FROM user_return_stock_items ri
                 WHERE ri.to_usertype=? AND ri.date BETWEEN ? AND ?{$pcs_uret_cond}
                 UNION ALL
                 SELECT osr.prid pr_id, osr.qty, osr.return_date date
                 FROM ot_sales_return osr
                 WHERE osr.return_date BETWEEN ? AND ?{$pcs_otret_cond}
             ) d
             WHERE d.pr_id IN (SELECT m.company_product_id FROM neksomo_product_mapping m
                               JOIN products np ON np.id = m.neksomo_product_id
                               WHERE COALESCE(np.category, '') != 'diaper')
             GROUP BY d.pr_id, d.date
         ) dp JOIN products p ON p.id = dp.pr_id
         GROUP BY p.id",
        'sssss', [$utype, $from, $to, $from, $to]);
    $grand_total_return_qty            = (int) array_sum(array_column($pieces_returned, 'total_qty'));
    $grand_total_return_pieces         = (int) array_sum(array_column($pieces_returned, 'total_pieces'));
    $grand_total_return_value          = (float) array_sum(array_column($pieces_returned, 'total_value'));
    $grand_total_return_gst_value      = (float) array_sum(array_column($pieces_returned, 'total_gst_value'));
    $grand_total_return_purchase_value = (float) array_sum(array_column($pieces_returned, 'total_purchase_value'));
    $grand_total_return_purchase_gst_value = (float) array_sum(array_column($pieces_returned, 'total_purchase_gst_value'));

    // Consolidated (net) qty — sold minus returned, same entity/period scope.
    $grand_total_net_qty    = $grand_total_pack_qty - $grand_total_return_qty;
    $grand_total_net_pieces = $grand_total_pieces - $grand_total_return_pieces;

    // Fold per-product return qty/pieces/value into $pieces_sold rows (by
    // product id) for the Product-wise Pieces Sold table below — same
    // convention as $diaper_sold's fold-in.
    $pcs_return_qty_by_pid    = array_column($pieces_returned, 'total_qty', 'pid');
    $pcs_return_pieces_by_pid = array_column($pieces_returned, 'total_pieces', 'pid');
    $pcs_return_value_by_pid  = array_column($pieces_returned, 'total_value', 'pid');
    foreach ($pieces_sold as &$__ps_row) {
        $__ps_row['return_qty']    = (int) ($pcs_return_qty_by_pid[$__ps_row['pid']] ?? 0);
        $__ps_row['return_pieces'] = (int) ($pcs_return_pieces_by_pid[$__ps_row['pid']] ?? 0);
        $__ps_row['net_qty']       = (int) $__ps_row['total_qty'] - $__ps_row['return_qty'];
        $__ps_row['net_pieces']    = (int) $__ps_row['total_pieces'] - $__ps_row['return_pieces'];
        $__ps_row['return_value']  = (float) ($pcs_return_value_by_pid[$__ps_row['pid']] ?? 0);
        $__ps_row['net_value']     = (float) $__ps_row['total_value'] - $__ps_row['return_value'];
    }
    unset($__ps_row);

    // Per-product Demo/Free/Damage breakdown (packs), folded into $pieces_sold
    // by product id — shown as its own split in the Product-wise Pieces Sold
    // table below, on top of (not separate from) the Total Pack Qty Sold it's
    // already counted inside.
    $pcs_dfd_breakdown = call_rows($db_conn,
        "SELECT product_id pid,
                COALESCE(SUM(CASE WHEN category='Demo' THEN qty ELSE 0 END),0) demo_qty,
                COALESCE(SUM(CASE WHEN category='Free' THEN qty ELSE 0 END),0) free_qty,
                COALESCE(SUM(CASE WHEN category='Damage' THEN qty ELSE 0 END),0) damage_qty
         FROM demofreedamage dfd
         WHERE usertype=? AND category IN ('Demo','Free','Damage') AND date BETWEEN ? AND ?{$pcs_dfd_cond}
         GROUP BY product_id",
        'sss', [$utype, $from, $to]);
    $pcs_demo_qty_by_pid   = array_column($pcs_dfd_breakdown, 'demo_qty', 'pid');
    $pcs_free_qty_by_pid   = array_column($pcs_dfd_breakdown, 'free_qty', 'pid');
    $pcs_damage_qty_by_pid = array_column($pcs_dfd_breakdown, 'damage_qty', 'pid');
    foreach ($pieces_sold as &$__ps_row) {
        $__ps_row['demo_qty']   = (int) ($pcs_demo_qty_by_pid[$__ps_row['pid']] ?? 0);
        $__ps_row['free_qty']   = (int) ($pcs_free_qty_by_pid[$__ps_row['pid']] ?? 0);
        $__ps_row['damage_qty'] = (int) ($pcs_damage_qty_by_pid[$__ps_row['pid']] ?? 0);
    }
    unset($__ps_row);

    // Gross Profit nets returns on both sides: a returned piece is revenue
    // that never really landed (subtract it from Sold Value) and cost that
    // was never really incurred by this sale (subtract it from Purchase
    // Value too) — otherwise a return would look like pure loss instead of
    // a wash. Valued at whatever rate/purchase_rate was effective on the
    // return's own date (see $pieces_returned query above), not the
    // original sale's rate.
    $grand_gross_profit = ($grand_total_value - $grand_total_return_value)
                        - ($grand_total_purchase_value - $grand_total_return_purchase_value);
    $grand_net_profit   = $grand_gross_profit - $grand_total_expense;

    // GST shown separately from Gross Profit — never folded into it. Output
    // GST is what was collected on sales (net of returns); Input GST is what
    // was paid on purchases (net of returns) and is a credit against Output
    // GST, not a cost — standard GST accounting.
    $grand_total_output_gst = $grand_total_gst_value - $grand_total_return_gst_value;
    $grand_total_input_gst  = $grand_total_purchase_gst_value - $grand_total_return_purchase_gst_value;
    $grand_total_net_gst    = $grand_total_output_gst - $grand_total_input_gst;

    // ═══════════════════════════════════════════════════════════════════════
    // DIAPER — a diaper Neksomo product (products.category='diaper') is
    // itself pack-based (unit_type='pack') and maps 1:1 to a pack-based
    // company SKU, unlike the napkin/piece-based figures above. So the sold
    // metric here is the pack quantity actually sold — no pieces_per_pack
    // conversion. Cost is looked up from neksomo_llp_piece_rates — the same
    // "Sale to Femi9 LLP" table napkin's cost basis uses (that page accepts
    // both piece- and pack-based products) — NOT
    // neksomo_llp_piece_purchase_rates, which prices Neksomo's own purchase
    // from its manufacturer, a different, unrelated cost. For a pack-based
    // product the stored rate_per_piece column holds a per-PACK rate
    // instead, so day_qty * rate is already the correct value with no
    // conversion.
    // ═══════════════════════════════════════════════════════════════════════
    $diaper_mapped_ids_subq = "SELECT company_product_id FROM neksomo_product_mapping m
                                JOIN products np ON np.id = m.neksomo_product_id
                                WHERE np.category = 'diaper'";

    // Sold Value is the REAL invoiced amount for each line (ii.total /
    // uii.total / os.total / tpii.amount), not the internal Neksomo→LLP
    // transfer rate — that rate table only prices what the company bought
    // from Neksomo (used for Cost/purchase_rate below), it has nothing to
    // do with what the company actually charged its own customers/TPs, so
    // using it for Sold Value silently substituted the wrong number (and,
    // since that table's rate happened to differ from the real invoice
    // rate, looked like a GST-inclusion bug). GST is still excluded from
    // Sold Value the same way every other revenue figure in this report
    // is: the line's own gst_percentage/gst_type, snapshotted onto the
    // invoice row at billing time (see user-invoice-action.php /
    // invoice-action2.php) — an 'inclusive' line has GST baked into
    // total=subtotal already and it's backed out here; an 'exclusive'
    // line's total is subtotal+gst and backing out the same fraction
    // still recovers subtotal. Demo/Free/Damage rows carry no sale
    // amount at all, so they fall through with a NULL rate and are
    // excluded from Sold Value (same "unrated == excluded" convention
    // used everywhere else), while still counting toward Pack Qty Sold.
    $diaper_sold = call_rows($db_conn,
        "SELECT p.id pid, p.productName,
                COALESCE(SUM(dp.day_qty),0) total_qty,
                COALESCE(SUM(CASE WHEN dp.day_line_total IS NOT NULL THEN
                    dp.day_line_total / (1 + COALESCE(p.gst,0)/100) ELSE 0 END),0) total_value,
                COALESCE(SUM(CASE WHEN dp.day_line_total IS NULL THEN dp.day_qty ELSE 0 END),0) unrated_qty,
                COALESCE(SUM(CASE WHEN dp.day_line_total IS NOT NULL THEN
                    dp.day_line_total - (dp.day_line_total / (1 + COALESCE(p.gst,0)/100)) ELSE 0 END),0) total_gst_value,
                COALESCE(SUM(CASE WHEN dp.purchase_rate IS NOT NULL THEN dp.day_qty * dp.purchase_rate ELSE 0 END),0) total_purchase_value,
                COALESCE(SUM(CASE WHEN dp.purchase_rate IS NULL THEN dp.day_qty ELSE 0 END),0) unpriced_qty,
                COALESCE(SUM(CASE WHEN dp.purchase_rate IS NOT NULL THEN dp.day_qty * dp.purchase_rate_gst_amt ELSE 0 END),0) total_purchase_gst_value
         FROM (
             SELECT d.pr_id, d.date,
                    SUM(d.qty) day_qty,
                    -- Real invoiced amount for the day (GST-inclusive as
                    -- stored) — de-taxed against the company product's own
                    -- gst% in the outer SELECT above, once `p` is joined.
                    -- NULL when nothing sellable priced that day (only
                    -- Demo/Free/Damage rows), which correctly excludes it
                    -- from Sold Value while still counting toward Pack Qty.
                    SUM(d.line_total) day_line_total,
                    -- Diaper cost basis: neksomo_llp_piece_rates (Neksomo's
                    -- Sale to Femi9 LLP rate), not
                    -- neksomo_llp_piece_purchase_rates (Neksomo's own
                    -- purchase from its manufacturer — a different,
                    -- unrelated cost). Column alias kept as purchase_rate
                    -- for the outer SELECT's existing references.
                    (SELECT CASE WHEN r.gst_type = 'inclusive' THEN r.rate_per_piece / (1 + r.gst_rate/100) ELSE r.rate_per_piece END
                     FROM neksomo_llp_piece_rates r
                     WHERE r.product_id = (SELECT m.neksomo_product_id FROM neksomo_product_mapping m WHERE m.company_product_id = d.pr_id LIMIT 1)
                       AND r.effective_date <= d.date
                     ORDER BY r.effective_date DESC LIMIT 1) purchase_rate,
                    (SELECT CASE WHEN r.gst_type = 'inclusive' THEN r.rate_per_piece - r.rate_per_piece / (1 + r.gst_rate/100) ELSE r.rate_per_piece * r.gst_rate/100 END
                     FROM neksomo_llp_piece_rates r
                     WHERE r.product_id = (SELECT m.neksomo_product_id FROM neksomo_product_mapping m WHERE m.company_product_id = d.pr_id LIMIT 1)
                       AND r.effective_date <= d.date
                     ORDER BY r.effective_date DESC LIMIT 1) purchase_rate_gst_amt
             FROM (
                 SELECT ii.pr_id, ii.qty, ii.total AS line_total, i.date
                 FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
                 WHERE i.user_type=? AND i.date BETWEEN ? AND ?{$pcs_ii_cond}
                 UNION ALL
                 SELECT uii.pr_id, uii.qty, uii.total AS line_total, ui.date
                 FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
                 WHERE ui.from_user_type=? AND ui.date BETWEEN ? AND ?{$pcs_uii_cond}
                 UNION ALL
                 SELECT os.prid, os.qty, os.total AS line_total, os.date
                 FROM ot_sales os
                 WHERE os.date BETWEEN ? AND ?{$pcs_ot_cond}
                 UNION ALL
                 -- Net against both discount mechanisms so Sold Value
                 -- reconciles to tp_invoices.total_amount, same fix as the
                 -- Gross Profit / Product-wise Sales unions above.
                 SELECT tpii.product_id, tpii.quantity, {$gp_tp_net_line_amt} AS line_total, tpi.invoice_date
                 FROM tp_invoice_items tpii JOIN tp_invoices tpi ON tpi.id=tpii.tp_invoice_id
                 WHERE tpi.invoice_date BETWEEN ? AND ?{$pcs_tpi_cond}
                 UNION ALL
                 SELECT dfd.product_id, dfd.qty, NULL AS line_total, dfd.date
                 FROM demofreedamage dfd
                 WHERE dfd.usertype=? AND dfd.category IN ('Demo','Free','Damage') AND dfd.date BETWEEN ? AND ?{$pcs_dfd_cond}
             ) d
             WHERE d.pr_id IN ({$diaper_mapped_ids_subq})
             GROUP BY d.pr_id, d.date
         ) dp
         JOIN products p ON p.id = dp.pr_id
         GROUP BY p.id, p.productName
         ORDER BY total_qty DESC",
        'sssssssssssss', [$utype, $from, $to, $utype, $from, $to, $from, $to, $from, $to, $utype, $from, $to]);

    $grand_diaper_pack_qty       = (int) array_sum(array_column($diaper_sold, 'total_qty'));
    $grand_diaper_value          = (float) array_sum(array_column($diaper_sold, 'total_value'));
    $grand_diaper_unrated_qty    = (int) array_sum(array_column($diaper_sold, 'unrated_qty'));
    $grand_diaper_gst_value      = (float) array_sum(array_column($diaper_sold, 'total_gst_value'));
    $grand_diaper_purchase_value = (float) array_sum(array_column($diaper_sold, 'total_purchase_value'));
    $grand_diaper_unpriced_qty   = (int) array_sum(array_column($diaper_sold, 'unpriced_qty'));
    $grand_diaper_purchase_gst_value = (float) array_sum(array_column($diaper_sold, 'total_purchase_gst_value'));

    // Return Value — same fix as Sold Value above: the REAL returned amount
    // (user_return_stock_items.total / ot_sales_return.total), de-taxed via
    // the company product's own gst%, not the Neksomo transfer-rate table.
    $diaper_returned = call_rows($db_conn,
        "SELECT p.id pid,
                COALESCE(SUM(dp.day_qty),0) total_qty,
                COALESCE(SUM(CASE WHEN dp.day_line_total IS NOT NULL THEN
                    dp.day_line_total / (1 + COALESCE(p.gst,0)/100) ELSE 0 END),0) total_value,
                COALESCE(SUM(CASE WHEN dp.day_line_total IS NOT NULL THEN
                    dp.day_line_total - (dp.day_line_total / (1 + COALESCE(p.gst,0)/100)) ELSE 0 END),0) total_gst_value,
                COALESCE(SUM(CASE WHEN dp.purchase_rate IS NOT NULL THEN dp.day_qty * dp.purchase_rate ELSE 0 END),0) total_purchase_value,
                COALESCE(SUM(CASE WHEN dp.purchase_rate IS NOT NULL THEN dp.day_qty * dp.purchase_rate_gst_amt ELSE 0 END),0) total_purchase_gst_value
         FROM (
             SELECT d.pr_id, d.date, SUM(d.qty) day_qty, SUM(d.line_total) day_line_total,
                    -- Diaper cost basis: neksomo_llp_piece_rates (Neksomo's
                    -- Sale to Femi9 LLP rate) — see $diaper_sold above.
                    (SELECT CASE WHEN r.gst_type = 'inclusive' THEN r.rate_per_piece / (1 + r.gst_rate/100) ELSE r.rate_per_piece END
                     FROM neksomo_llp_piece_rates r
                     WHERE r.product_id = (SELECT m.neksomo_product_id FROM neksomo_product_mapping m WHERE m.company_product_id = d.pr_id LIMIT 1)
                       AND r.effective_date <= d.date
                     ORDER BY r.effective_date DESC LIMIT 1) purchase_rate,
                    (SELECT CASE WHEN r.gst_type = 'inclusive' THEN r.rate_per_piece - r.rate_per_piece / (1 + r.gst_rate/100) ELSE r.rate_per_piece * r.gst_rate/100 END
                     FROM neksomo_llp_piece_rates r
                     WHERE r.product_id = (SELECT m.neksomo_product_id FROM neksomo_product_mapping m WHERE m.company_product_id = d.pr_id LIMIT 1)
                       AND r.effective_date <= d.date
                     ORDER BY r.effective_date DESC LIMIT 1) purchase_rate_gst_amt
             FROM (
                 SELECT ri.prid pr_id, ri.qty, ri.total AS line_total, ri.date
                 FROM user_return_stock_items ri
                 WHERE ri.to_usertype=? AND ri.date BETWEEN ? AND ?{$pcs_uret_cond}
                 UNION ALL
                 SELECT osr.prid pr_id, osr.qty, osr.total AS line_total, osr.return_date date
                 FROM ot_sales_return osr
                 WHERE osr.return_date BETWEEN ? AND ?{$pcs_otret_cond}
             ) d
             WHERE d.pr_id IN ({$diaper_mapped_ids_subq})
             GROUP BY d.pr_id, d.date
         ) dp JOIN products p ON p.id = dp.pr_id
         GROUP BY p.id",
        'sssss', [$utype, $from, $to, $from, $to]);

    $grand_diaper_return_qty            = (int) array_sum(array_column($diaper_returned, 'total_qty'));
    $grand_diaper_return_value          = (float) array_sum(array_column($diaper_returned, 'total_value'));
    $grand_diaper_return_gst_value      = (float) array_sum(array_column($diaper_returned, 'total_gst_value'));
    $grand_diaper_return_purchase_value = (float) array_sum(array_column($diaper_returned, 'total_purchase_value'));
    $grand_diaper_return_purchase_gst_value = (float) array_sum(array_column($diaper_returned, 'total_purchase_gst_value'));
    $grand_diaper_net_qty                = $grand_diaper_pack_qty - $grand_diaper_return_qty;

    // Fold return qty/value into $diaper_sold rows (by product id) for the
    // product-wise breakdown table.
    $diaper_return_qty_by_pid   = array_column($diaper_returned, 'total_qty', 'pid');
    $diaper_return_value_by_pid = array_column($diaper_returned, 'total_value', 'pid');
    foreach ($diaper_sold as &$__ds_row) {
        $__ds_row['return_qty']   = (int) ($diaper_return_qty_by_pid[$__ds_row['pid']] ?? 0);
        $__ds_row['net_qty']      = (int) $__ds_row['total_qty'] - $__ds_row['return_qty'];
        $__ds_row['return_value'] = (float) ($diaper_return_value_by_pid[$__ds_row['pid']] ?? 0);
        $__ds_row['net_value']    = (float) $__ds_row['total_value'] - $__ds_row['return_value'];
    }
    unset($__ds_row);

    // Per-product Demo/Free/Damage breakdown (packs), folded into
    // $diaper_sold by product id — same convention as napkin's above.
    $diaper_dfd_breakdown = call_rows($db_conn,
        "SELECT product_id pid,
                COALESCE(SUM(CASE WHEN category='Demo' THEN qty ELSE 0 END),0) demo_qty,
                COALESCE(SUM(CASE WHEN category='Free' THEN qty ELSE 0 END),0) free_qty,
                COALESCE(SUM(CASE WHEN category='Damage' THEN qty ELSE 0 END),0) damage_qty
         FROM demofreedamage dfd
         WHERE usertype=? AND category IN ('Demo','Free','Damage') AND date BETWEEN ? AND ?{$pcs_dfd_cond}
           AND product_id IN ({$diaper_mapped_ids_subq})
         GROUP BY product_id",
        'sss', [$utype, $from, $to]);
    $diaper_demo_qty_by_pid   = array_column($diaper_dfd_breakdown, 'demo_qty', 'pid');
    $diaper_free_qty_by_pid   = array_column($diaper_dfd_breakdown, 'free_qty', 'pid');
    $diaper_damage_qty_by_pid = array_column($diaper_dfd_breakdown, 'damage_qty', 'pid');
    foreach ($diaper_sold as &$__ds_row) {
        $__ds_row['demo_qty']   = (int) ($diaper_demo_qty_by_pid[$__ds_row['pid']] ?? 0);
        $__ds_row['free_qty']   = (int) ($diaper_free_qty_by_pid[$__ds_row['pid']] ?? 0);
        $__ds_row['damage_qty'] = (int) ($diaper_damage_qty_by_pid[$__ds_row['pid']] ?? 0);
    }
    unset($__ds_row);

    // Diaper Gross Profit, same formula as napkin's. Expense is deliberately
    // 0 here — there's only one shared Neksomo expense pool (already counted
    // in full against napkin below), not a separate diaper allocation, so
    // adding it again here would double-count it in the combined total.
    $grand_diaper_gross_profit = ($grand_diaper_value - $grand_diaper_return_value)
                                - ($grand_diaper_purchase_value - $grand_diaper_return_purchase_value);
    $grand_diaper_expense    = 0.0;
    $grand_diaper_net_profit = $grand_diaper_gross_profit - $grand_diaper_expense;

    // GST shown separately from Diaper Gross Profit too — same convention as napkin.
    $grand_diaper_output_gst = $grand_diaper_gst_value - $grand_diaper_return_gst_value;
    $grand_diaper_input_gst  = $grand_diaper_purchase_gst_value - $grand_diaper_return_purchase_gst_value;
    $grand_diaper_net_gst    = $grand_diaper_output_gst - $grand_diaper_input_gst;

    // Combined — napkin + diaper, summed. Expense is not summed twice: it's
    // the same single Neksomo expense pool already reflected in
    // $grand_total_expense (napkin's), with $grand_diaper_expense fixed at 0.
    $grand_combined_gross_profit = $grand_gross_profit + $grand_diaper_gross_profit;
    $grand_combined_expense      = $grand_total_expense + $grand_diaper_expense;
    $grand_combined_net_profit   = $grand_net_profit + $grand_diaper_net_profit;
    $grand_combined_net_gst      = $grand_total_net_gst + $grand_diaper_net_gst;
}

// Per-product returns, scoped the same way as the Returns KPI above (same
// to_usertype/to_userid/date filters, no status filter — matches every
// return regardless of accept/pending/reject, for consistency with $total_returns).
// OT returns (ot_sales_return) are folded in for company scope only, same
// gating as OT sales above.
$pr_ot_union  = '';
$pr_params    = [$utype, $from, $to];
if ($scope === 'company') {
    $pr_ot_union = "UNION ALL
         SELECT osr.prid pid, osr.qty ret_qty, osr.total ret_amt
         FROM ot_sales_return osr
         WHERE osr.return_date BETWEEN ? AND ?";
    $pr_params[] = $from;
    $pr_params[] = $to;
}
$product_returns = call_rows($db_conn,
    "SELECT pid, COALESCE(SUM(ret_qty),0) ret_qty, COALESCE(SUM(ret_amt),0) ret_amt FROM (
        SELECT ri.prid pid, ri.qty ret_qty, ri.total ret_amt
        FROM user_return_stock_items ri
        WHERE ri.to_usertype=?".($filter_tp > 0 ? " AND ri.to_userid={$filter_tp}" : "")." AND ri.date BETWEEN ? AND ?
        {$pr_ot_union}
     ) x GROUP BY pid",
    str_repeat('s', count($pr_params)), $pr_params);
$returns_by_pid = [];
foreach ($product_returns as $r) {
    $returns_by_pid[(int)$r['pid']] = ['qty' => (float)$r['ret_qty'], 'amt' => (float)$r['ret_amt']];
}
// Return Quantity / net units for the Overview's consolidated Sales/Returns/
// Total Turnover cards — reuses this same per-product return data rather
// than a separate query, since it's already scoped identically to
// $total_returns/$total_return_amt above.
$total_return_qty = (float)array_sum(array_column($returns_by_pid, 'qty'));
$net_units         = $total_units - $total_return_qty;

// ═══════════════════════════════════════════════════════════════════════════
// 5. STATE / DISTRICT-WISE (shop invoices → shop → partner_location_nodes,
// plus TP invoices → the TP's own territory, plus OT sales → their own
// state_id field)
//
// `shop.state_id` is unreliable legacy data — for most shops it holds a
// district's node id (or free text like "Tamilnadu " / "தமிழ்நாடு" / blank),
// not a real state-depth node, which is why the State breakdown could show a
// district name. `shop.district_id` is comparatively trustworthy, so both
// State and District are derived by walking the location tree up from
// district_id to its depth-2 (state) / depth-3 (district) ancestor, with
// state_id used only as a fallback when district_id itself doesn't resolve.
//
// TP invoices have no shop/location link of their own, so each is attributed
// to its Territory Partner's own assigned territory instead — specifically
// the TP's earliest-assigned location (territory_partner_locations), walked
// up the same way. A TP can be assigned several locations, but in practice
// they fall within one state, so one representative location is treated as
// good enough for both State and District (unlike shop invoices, which carry
// a real per-invoice location).
//
// OT sales (`ot_sales.state_id`) have the exact same "actually a district id"
// issue as shop.state_id, so they're folded in as a fallback-only join too —
// no separate district_id column exists for OT the way shop has one. OT
// returns (`ot_sales_return`) carry no location data at all (only godownid),
// so unlike other sections' OT figures, this breakdown cannot net OT sales
// against their returns — it shows OT sales gross.
// ═══════════════════════════════════════════════════════════════════════════
$tc_ui_plain = $filter_tp > 0 ? " AND ui.from_user_id={$filter_tp}" : "";
$state_anc_cte = "WITH RECURSIVE anc AS (
    SELECT id AS node_id, id AS anc_id, name AS anc_name FROM partner_location_nodes WHERE depth=2
    UNION ALL
    SELECT c.id, a.anc_id, a.anc_name FROM partner_location_nodes c JOIN anc a ON c.parent_id=a.node_id
)";
// user_invoice.to_user_id can point at any of five different business-entity
// tables depending on to_user_type — shop, super_stockiest, stockiest,
// distributor, super_distributor — each with its own state_id/district_id
// pair (same shape as shop's). Joining ONLY `shop` (as this query used to)
// silently drops every non-Shop row entirely — no location, no fallback —
// which was a real data-loss bug, not just an unresolvable-location gap: a
// company-issued Super Stockist invoice has a perfectly good state_id/
// district_id on the super_stockiest row, it just never got looked up. Union
// across all five entity tables, keyed by the matching to_user_type, so
// every row that legitimately carries a location gets one.
$state_sales_shop = call_rows($db_conn,
    "{$state_anc_cte}
     SELECT COALESCE(a1.anc_name, a2.anc_name) state_name, COUNT(*) cnt, COALESCE(SUM(ui.total-ui.courier_charges),0) revenue
     FROM user_invoice ui
     JOIN (
         SELECT temp_id, district_id, state_id, 'shop' ch FROM shop
         UNION ALL SELECT temp_id, district_id, state_id, 'super_stockiest' FROM super_stockiest
         UNION ALL SELECT temp_id, district_id, state_id, 'stockiest' FROM stockiest
         UNION ALL SELECT temp_id, district_id, state_id, 'distributor' FROM distributor
         UNION ALL SELECT temp_id, district_id, state_id, 'super_distributor' FROM super_distributor
     ) s ON s.temp_id=ui.to_user_id AND s.ch=ui.to_user_type
     LEFT JOIN anc a1 ON a1.node_id=s.district_id
     LEFT JOIN anc a2 ON a2.node_id=s.state_id
     WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_ui_plain}
       AND COALESCE(a1.anc_name, a2.anc_name) IS NOT NULL
     GROUP BY COALESCE(a1.anc_id, a2.anc_id), state_name ORDER BY revenue DESC",
    'sss', [$utype, $from, $to]);
$state_sales_tp = [];
if ($tpinv_source_sql) {
    $state_sales_tp = call_rows($db_conn,
        "{$state_anc_cte}
         SELECT a.anc_name state_name, COUNT(*) cnt, COALESCE(SUM(x.total_amount),0) revenue
         FROM (
             SELECT (ti.total_amount-ti.courier_charges) total_amount,
                    (SELECT tpl.location_id FROM territory_partner_locations tpl
                     WHERE tpl.territory_partner_id=ti.territory_partner_id
                     ORDER BY tpl.assigned_at ASC, tpl.id ASC LIMIT 1) AS location_id
             FROM tp_invoices ti
             WHERE {$tpinv_source_sql} AND ti.invoice_date BETWEEN ? AND ?{$tc_tpi}
         ) x
         JOIN anc a ON a.node_id = x.location_id
         GROUP BY a.anc_id, a.anc_name",
        'ss', [$from, $to]);
}
$state_sales_ot = [];
if ($scope === 'company') {
    $state_sales_ot = call_rows($db_conn,
        "{$state_anc_cte}
         SELECT a.anc_name state_name, COUNT(DISTINCT os.tempid) cnt, COALESCE(SUM(os.total),0) revenue
         FROM ot_sales os JOIN anc a ON a.node_id = os.state_id
         WHERE os.date BETWEEN ? AND ?
         GROUP BY a.anc_id, a.anc_name",
        'ss', [$from, $to]);
}
$state_sales = merge_geo_rows('state_name', $state_sales_shop, $state_sales_tp, $state_sales_ot);

$dist_anc_cte = "WITH RECURSIVE danc AS (
    SELECT id AS node_id, id AS anc_id, name AS anc_name FROM partner_location_nodes WHERE depth=3
    UNION ALL
    SELECT c.id, a.anc_id, a.anc_name FROM partner_location_nodes c JOIN danc a ON c.parent_id=a.node_id
)";
// Same fix as state_sales_shop above (see its comment) — union across all
// five business-entity tables user_invoice.to_user_id can point at, keyed by
// to_user_type, instead of joining only `shop` and silently dropping every
// Super Stockist / Stockist / Distributor / Super Distributor row.
$district_sales_shop = call_rows($db_conn,
    "{$dist_anc_cte}
     SELECT danc.anc_name district_name, COUNT(*) cnt, COALESCE(SUM(ui.total-ui.courier_charges),0) revenue
     FROM user_invoice ui
     JOIN (
         SELECT temp_id, district_id, 'shop' ch FROM shop
         UNION ALL SELECT temp_id, district_id, 'super_stockiest' FROM super_stockiest
         UNION ALL SELECT temp_id, district_id, 'stockiest' FROM stockiest
         UNION ALL SELECT temp_id, district_id, 'distributor' FROM distributor
         UNION ALL SELECT temp_id, district_id, 'super_distributor' FROM super_distributor
     ) s ON s.temp_id=ui.to_user_id AND s.ch=ui.to_user_type
     JOIN danc ON danc.node_id=s.district_id
     WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_ui_plain}
     GROUP BY danc.anc_id, danc.anc_name",
    'sss', [$utype, $from, $to]);
$district_sales_tp = [];
if ($tpinv_source_sql) {
    $district_sales_tp = call_rows($db_conn,
        "{$dist_anc_cte}
         SELECT danc.anc_name district_name, COUNT(*) cnt, COALESCE(SUM(x.total_amount),0) revenue
         FROM (
             SELECT (ti.total_amount-ti.courier_charges) total_amount,
                    (SELECT tpl.location_id FROM territory_partner_locations tpl
                     WHERE tpl.territory_partner_id=ti.territory_partner_id
                     ORDER BY tpl.assigned_at ASC, tpl.id ASC LIMIT 1) AS location_id
             FROM tp_invoices ti
             WHERE {$tpinv_source_sql} AND ti.invoice_date BETWEEN ? AND ?{$tc_tpi}
         ) x
         JOIN danc ON danc.node_id = x.location_id
         GROUP BY danc.anc_id, danc.anc_name",
        'ss', [$from, $to]);
}
$district_sales_ot = [];
if ($scope === 'company') {
    $district_sales_ot = call_rows($db_conn,
        "{$dist_anc_cte}
         SELECT danc.anc_name district_name, COUNT(DISTINCT os.tempid) cnt, COALESCE(SUM(os.total),0) revenue
         FROM ot_sales os JOIN danc ON danc.node_id = os.state_id
         WHERE os.date BETWEEN ? AND ?
         GROUP BY danc.anc_id, danc.anc_name",
        'ss', [$from, $to]);
}
$district_sales = array_slice(
    merge_geo_rows('district_name', $district_sales_shop, $district_sales_tp, $district_sales_ot),
    0, 20
);

// ═══════════════════════════════════════════════════════════════════════════
// 6. TERRITORY PARTNER PERFORMANCE (= Salesperson Performance)
// Only meaningful in the TP-channel scope — always about actual TPs, so it's
// skipped entirely for the "Income to Company" (direct, non-TP) scope.
// ═══════════════════════════════════════════════════════════════════════════
// Revenue/units/count = invoices TP itself issued, from BOTH tables:
// user_invoice (TP reselling to SS/S/SD/D/Shop or another business) plus
// invoice (TP selling directly to a customer, user_type='territory_partner')
// — not from what company invoiced TO the TP (that's company's own invoice).
// Revenue is split Napkin/Diaper by the product-category mix of each TP's
// own item lines (uii.pr_id / ii.pr_id -> products.category), then that
// ratio is applied to the header-level revenue (which is already net of
// courier_charges) rather than summing item totals directly — this keeps
// the combined figure exactly matching the pre-split total while still
// giving an accurate split. target_amount has no Diaper equivalent (it's
// Napkin-only), so only the Napkin side is ever compared against target;
// Diaper is shown as a share of the TP's own total sales instead.
$tp_perf = ($scope === 'tp') ? call_rows($db_conn,
    "SELECT tp.id tp_id, tp.name tp_name, tp.tp_id tp_code,
            COALESCE(si.cnt,0) + COALESCE(ci.cnt,0) inv_cnt,
            COALESCE(si.rev,0) + COALESCE(ci.rev,0) revenue,
            COALESCE(si.units,0) + COALESCE(ci.units,0) units,
            COALESCE(tgt.target,0) target,
            COALESCE(sic.napkin_total,0) + COALESCE(cic.napkin_total,0) napkin_item_total,
            COALESCE(sic.diaper_total,0) + COALESCE(cic.diaper_total,0) diaper_item_total
     FROM territory_partners tp
     LEFT JOIN (
         SELECT from_user_id, COUNT(*) cnt, SUM(total-courier_charges) rev,
                (SELECT COALESCE(SUM(qty),0) FROM user_invoice_items uii WHERE uii.from_user_id=ui.from_user_id AND uii.from_user_type='territory_partner' AND uii.date BETWEEN '{$from}' AND '{$to}') units
         FROM user_invoice ui WHERE from_user_type='territory_partner' AND sub_total>0 AND `date` BETWEEN '{$from}' AND '{$to}'
         GROUP BY from_user_id
     ) si ON si.from_user_id = tp.id
     LEFT JOIN (
         SELECT user_id, COUNT(*) cnt, SUM(total-courier_charges) rev,
                (SELECT COALESCE(SUM(qty),0) FROM invoice_items ii WHERE ii.user_id=i.user_id AND ii.user_type='territory_partner' AND ii.date BETWEEN '{$from}' AND '{$to}') units
         FROM invoice i WHERE user_type='territory_partner' AND sub_total>0 AND `date` BETWEEN '{$from}' AND '{$to}'
         GROUP BY user_id
     ) ci ON ci.user_id = tp.id
     LEFT JOIN (
         SELECT uii.from_user_id,
                SUM(CASE WHEN COALESCE(p.category,'') != 'diaper' THEN uii.total ELSE 0 END) napkin_total,
                SUM(CASE WHEN p.category = 'diaper' THEN uii.total ELSE 0 END) diaper_total
         FROM user_invoice_items uii
         JOIN products p ON p.id = uii.pr_id
         WHERE uii.from_user_type='territory_partner' AND uii.date BETWEEN '{$from}' AND '{$to}'
         GROUP BY uii.from_user_id
     ) sic ON sic.from_user_id = tp.id
     LEFT JOIN (
         SELECT ii.user_id,
                SUM(CASE WHEN COALESCE(p.category,'') != 'diaper' THEN ii.total ELSE 0 END) napkin_total,
                SUM(CASE WHEN p.category = 'diaper' THEN ii.total ELSE 0 END) diaper_total
         FROM invoice_items ii
         JOIN products p ON p.id = ii.pr_id
         WHERE ii.user_type='territory_partner' AND ii.date BETWEEN '{$from}' AND '{$to}'
         GROUP BY ii.user_id
     ) cic ON cic.user_id = tp.id
     LEFT JOIN (
         SELECT tpl.territory_partner_id, COALESCE(SUM(pln.target_amount),0) target
         FROM territory_partner_locations tpl
         JOIN partner_location_nodes pln ON pln.id=tpl.location_id
         GROUP BY tpl.territory_partner_id
     ) tgt ON tgt.territory_partner_id = tp.id
     WHERE tp.is_active=1
     ORDER BY revenue DESC") : [];

foreach ($tp_perf as &$_tp) {
    $_itemTotal = (float)$_tp['napkin_item_total'] + (float)$_tp['diaper_item_total'];
    // No item-level category data for this TP in range (e.g. legacy rows) —
    // default to treating it all as Napkin, same safe-majority default used
    // elsewhere in the Napkin/Diaper split (see shared/TpProductType.php).
    $_napkinShare = $_itemTotal > 0 ? ((float)$_tp['napkin_item_total'] / $_itemTotal) : 1.0;
    $_tp['napkin_revenue'] = round((float)$_tp['revenue'] * $_napkinShare, 2);
    $_tp['diaper_revenue'] = round((float)$_tp['revenue'] - $_tp['napkin_revenue'], 2);
    $_tp['diaper_pct'] = (float)$_tp['revenue'] > 0 ? round($_tp['diaper_revenue'] / (float)$_tp['revenue'] * 100, 1) : 0;
}
unset($_tp);

$max_tp_rev  = (float)($tp_perf[0]['revenue'] ?? 1) ?: 1;
$total_target_all = array_sum(array_column($tp_perf, 'target'));
$total_achieved_all = array_sum(array_column($tp_perf, 'revenue'));
$total_napkin_achieved_all = array_sum(array_column($tp_perf, 'napkin_revenue'));
$total_diaper_achieved_all = array_sum(array_column($tp_perf, 'diaper_revenue'));
$overall_pct_all = $total_target_all > 0
    ? min(round($total_napkin_achieved_all / $total_target_all * 100, 1), 999) : 0;
$overall_diaper_pct_all = $total_achieved_all > 0
    ? round($total_diaper_achieved_all / $total_achieved_all * 100, 1) : 0;

// ═══════════════════════════════════════════════════════════════════════════
// 7. TOP SHOPS & TOP DISTRIBUTORS
// ═══════════════════════════════════════════════════════════════════════════
$top_shops = call_rows($db_conn,
    "SELECT s.name shop_name, COUNT(*) inv_cnt, COALESCE(SUM(ui.total-ui.courier_charges),0) revenue
     FROM user_invoice ui JOIN shop s ON s.temp_id=ui.to_user_id
     WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_ui_plain}
     GROUP BY s.temp_id, s.name ORDER BY revenue DESC LIMIT 10",
    'sss', [$utype, $from, $to]);

// Top Distributors — merges three wholesale/bulk-buyer entity types that are
// each billed by company but live in separate tables: Distributor, Super
// Distributor (both via user_invoice, joined by temp_id like Top Shops does
// for shop), and Territory Partner (via the dedicated tp_invoices table,
// same $tpinv_source_sql as every other section). Unlike Top Shops (a single
// entity type), these three are concatenated rather than merged by name —
// a Distributor and a TP sharing a name are still different entities.
$top_dist_d = call_rows($db_conn,
    "SELECT d.name dist_name, COUNT(*) inv_cnt, COALESCE(SUM(ui.total-ui.courier_charges),0) revenue
     FROM user_invoice ui JOIN distributor d ON d.temp_id=ui.to_user_id
     WHERE ui.from_user_type=? AND ui.to_user_type='distributor' AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_ui_plain}
     GROUP BY d.temp_id, d.name",
    'sss', [$utype, $from, $to]);
$top_dist_sd = call_rows($db_conn,
    "SELECT sd.name dist_name, COUNT(*) inv_cnt, COALESCE(SUM(ui.total-ui.courier_charges),0) revenue
     FROM user_invoice ui JOIN super_distributor sd ON sd.temp_id=ui.to_user_id
     WHERE ui.from_user_type=? AND ui.to_user_type='super_distributor' AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_ui_plain}
     GROUP BY sd.temp_id, sd.name",
    'sss', [$utype, $from, $to]);
$top_dist_tp = [];
if ($tpinv_source_sql) {
    $top_dist_tp = call_rows($db_conn,
        "SELECT tp.name dist_name, COUNT(*) inv_cnt, COALESCE(SUM(ti.total_amount-ti.courier_charges),0) revenue
         FROM tp_invoices ti JOIN territory_partners tp ON tp.id=ti.territory_partner_id
         WHERE {$tpinv_source_sql} AND ti.invoice_date BETWEEN ? AND ?{$tc_tpi}
         GROUP BY tp.id, tp.name",
        'ss', [$from, $to]);
}
$top_distributors = array_merge(
    array_map(fn($r) => $r + ['dist_type' => 'Distributor'], $top_dist_d),
    array_map(fn($r) => $r + ['dist_type' => 'Super Distributor'], $top_dist_sd),
    array_map(fn($r) => $r + ['dist_type' => 'Territory Partner'], $top_dist_tp)
);
usort($top_distributors, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
$top_distributors = array_slice($top_distributors, 0, 10);

// ═══════════════════════════════════════════════════════════════════════════
// 8. ORDER STATUS
// ═══════════════════════════════════════════════════════════════════════════
// The receipt subquery used to aggregate ALL receipts unfiltered (120k+
// rows) on every single dashboard load, regardless of the date/user_type
// filter above it — scoping it to only the invoices that actually match
// the outer WHERE (same conditions, just applied to the receipt side too)
// keeps the result identical while letting MySQL use the invoice indexes
// instead of a full-table GROUP BY every time.
$ord_c = call_rows($db_conn,
    "SELECT i.total, COALESCE(r.paid,0) paid
     FROM invoice i
     LEFT JOIN (
         SELECT rec.inv_id, SUM(rec.received) paid
         FROM receipt rec
         INNER JOIN invoice iz ON iz.inv_id = rec.inv_id AND iz.user_type=? AND iz.sub_total>0 AND iz.date BETWEEN ? AND ?{$tc_inv}
         GROUP BY rec.inv_id
     ) r ON r.inv_id=i.inv_id
     WHERE i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?{$tc_inv}",
    'ssssss', [$utype, $from, $to, $utype, $from, $to]);
$ord_s = call_rows($db_conn,
    "SELECT ui.total, COALESCE(r.paid,0) paid
     FROM user_invoice ui
     LEFT JOIN (
         SELECT rec.inv_id, SUM(rec.received) paid
         FROM receipt rec
         INNER JOIN user_invoice uiz ON uiz.inv_id = rec.inv_id AND uiz.from_user_type=? AND uiz.sub_total>0 AND uiz.date BETWEEN ? AND ?{$tc_ui}
         GROUP BY rec.inv_id
     ) r ON r.inv_id=ui.inv_id
     WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_ui}",
    'ssssss', [$utype, $from, $to, $utype, $from, $to]);
$os_paid=$os_part=$os_unpd=0; $os_paid_a=$os_part_a=$os_unpd_a=0;
foreach (array_merge($ord_c,$ord_s) as $o) {
    $t=(float)$o['total']; $p=(float)$o['paid'];
    if ($p>=$t&&$t>0) { $os_paid++; $os_paid_a+=$t; }
    elseif ($p>0&&$p<$t) { $os_part++; $os_part_a+=$t; }
    else { $os_unpd++; $os_unpd_a+=$t; }
}

// ═══════════════════════════════════════════════════════════════════════════
// 9. 6-MONTH GROWTH TREND
// ═══════════════════════════════════════════════════════════════════════════
// TP sales (tp_invoices, via $tpinv_source_sql) and OT channel sales, net of
// OT's own returns (ot_sales / ot_sales_return, company scope only), same
// sources folded into every other section's totals. The return contribution
// is summed as a negative amount so it nets out of SUM(rev) below, rather
// than needing a separate subtraction step.
$sm_tp_union = '';
if ($tpinv_source_sql) {
    $sm_tp_union = "UNION ALL
         SELECT invoice_date d, SUM(total_amount-courier_charges) rev, COUNT(*) cnt FROM tp_invoices
         WHERE {$tpinv_source_sql} AND invoice_date>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH){$tc_tpi}
         GROUP BY invoice_date";
}
$sm_ot_union = '';
if ($scope === 'company') {
    $sm_ot_union = "UNION ALL
         SELECT `date` d, SUM(total) rev, COUNT(DISTINCT tempid) cnt FROM ot_sales
         WHERE `date`>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH)
         GROUP BY `date`
         UNION ALL
         SELECT return_date d, -SUM(total) rev, 0 cnt FROM ot_sales_return
         WHERE return_date>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH)
         GROUP BY return_date";
}
$six_months = call_rows($db_conn,
    "SELECT DATE_FORMAT(d,'%Y-%m') mon, DATE_FORMAT(MIN(d),'%b %Y') lbl,
            SUM(rev) total_rev, SUM(cnt) total_cnt
     FROM (
         SELECT `date` d, SUM(total-courier_charges) rev, COUNT(*) cnt FROM invoice
         WHERE user_type=? AND sub_total>0 AND `date`>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH){$tc_inv}
         GROUP BY `date`
         UNION ALL
         SELECT `date` d, SUM(total-courier_charges) rev, COUNT(*) cnt FROM user_invoice
         WHERE from_user_type=? AND sub_total>0 AND `date`>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH){$tc_ui}
         GROUP BY `date`
         {$sm_tp_union}
         {$sm_ot_union}
     ) z GROUP BY DATE_FORMAT(d,'%Y-%m') ORDER BY mon",
    'ss', [$utype, $utype]);
$prev_m = null;
foreach ($six_months as &$m) {
    $m['growth'] = ($prev_m!==null&&$prev_m>0) ? round((($m['total_rev']-$prev_m)/$prev_m)*100,1) : null;
    $prev_m = (float)$m['total_rev'];
}
unset($m);

// ═══════════════════════════════════════════════════════════════════════════
// 10. RETURNS LIST
// ═══════════════════════════════════════════════════════════════════════════
// No "sales to TP" dimension applies to a returns list — this section only
// picks up the OT channel returns gap (ot_sales_return is a completely
// different table/shape than user_return_stock: per-product-line, no
// returnid/status/invoice-number workflow, tempid groups a whole order), so
// both sources are queried separately then normalized into one shared shape
// and merged/re-sorted, rather than trying to force one UNION query.
$returns_list_db = call_rows($db_conn,
    "SELECT urs.*, inv_num.inv_number, tp.name tp_name
     FROM user_return_stock urs
     LEFT JOIN (SELECT inv_id, inv_number FROM invoice UNION ALL SELECT inv_id, inv_number FROM user_invoice) inv_num ON inv_num.inv_id=urs.invnumber
     LEFT JOIN territory_partners tp ON tp.id=urs.to_userid AND urs.to_usertype='territory_partner'
     WHERE urs.to_usertype=?".($filter_tp>0?" AND urs.to_userid={$filter_tp}":"")." AND urs.date BETWEEN ? AND ?
     ORDER BY urs.date DESC",
    'sss', [$utype, $from, $to]);
$returns_list = array_map(fn($r) => [
    'returnid'   => $r['returnid'],
    'inv_number' => $r['inv_number'] ?? $r['invnumber'],
    'tp_name'    => $r['tp_name'],
    'from_label' => ucfirst(str_replace('_', ' ', $r['from_usertype'])),
    'date'       => $r['date'],
    'amount'     => (float)$r['total'],
    'status'     => $r['status'],
    'detail_url' => '../territory-partner/cnote_details.php?returnid=' . base64_encode($r['returnid']),
], $returns_list_db);

if ($scope === 'company') {
    // One row per whole return (tempid), matching user_return_stock's
    // per-return granularity — ot_sales_return is per-product-line.
    $ot_returns_db = call_rows($db_conn,
        "SELECT osr.tempid returnid, MIN(osr.return_date) rdate, SUM(osr.total) amount,
                (SELECT os.order_number FROM ot_sales os WHERE os.tempid=osr.tempid LIMIT 1) order_number
         FROM ot_sales_return osr
         WHERE osr.return_date BETWEEN ? AND ?
         GROUP BY osr.tempid ORDER BY rdate DESC",
        'ss', [$from, $to]);
    $ot_returns_list = array_map(fn($r) => [
        'returnid'   => $r['returnid'],
        'inv_number' => $r['order_number'] ?: $r['returnid'],
        'tp_name'    => null,
        'from_label' => 'OT Channel',
        'date'       => $r['rdate'],
        'amount'     => (float)$r['amount'],
        'status'     => null, // OT returns have no accept/pending workflow
        'detail_url' => null, // no OT-return detail page exists
    ], $ot_returns_db);
    $returns_list = array_merge($returns_list, $ot_returns_list);
    usort($returns_list, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));
}

// ═══════════════════════════════════════════════════════════════════════════
// JSON for charts
// ═══════════════════════════════════════════════════════════════════════════
$j_labels  = json_encode($chart_labels);
$j_cust    = json_encode($chart_cust);
$j_shop    = json_encode($chart_shop);
$j_tp      = json_encode($chart_tp);
$j_ot      = json_encode($chart_ot);
$j_glabels = json_encode(array_column($six_months,'lbl'));
$j_gvals   = json_encode(array_map('floatval', array_column($six_months,'total_rev')));
$j_plabels = json_encode(array_column($product_sales,'productName'));
$j_pqty    = json_encode(array_map('intval', array_column($product_sales,'total_qty')));
$j_tplabels= json_encode(array_column($tp_perf,'tp_name'));
// Napkin revenue (not combined) — target_amount is Napkin-only, so this is
// the figure that's actually comparable to it (see napkin_revenue split above).
$j_tprevs  = json_encode(array_map(fn($r)=>round($r['napkin_revenue'],0), $tp_perf));
$j_tptgts  = json_encode(array_map(fn($r)=>round($r['target'],0), $tp_perf));

// ═══════════════════════════════════════════════════════════════════════════
// NEKSOMO STANDALONE VIEW — a self-contained page showing only the
// entity-scoped Pieces Sold report, then exits before the full report below
// (which aggregates all company entities together with no per-entity filter)
// ever renders. See $is_neksomo_view comment near the top of this file.
// ═══════════════════════════════════════════════════════════════════════════
if ($is_neksomo_view) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pieces Sold Report : <?php echo $business_name; ?></title>
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        body { background:#f7f7f6; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:8px 11px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:7px 11px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .mt tr:hover td { background:#f7f7f6; }
        .kpi-card { background:#fff; border:1px solid rgba(11,11,11,0.10); border-radius:10px; padding:16px 18px; height:100%; }
        .kpi-t { font-size:11px; text-transform:uppercase; letter-spacing:.5px; font-weight:600; color:#52514e; }
        .kpi-v { font-size:24px; font-weight:700; margin-top:6px; color:#0b0b0b; }
        .equation-row { display:flex; align-items:stretch; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
        .equation-row .kpi-card { flex:1 1 180px; }
        .equation-op { display:flex; align-items:center; justify-content:center; font-size:26px; font-weight:300; color:#b5b3ab; flex:0 0 auto; padding:0 2px; }
        .equation-op.eq { color:#52514e; font-weight:600; }
        .kpi-multi { margin-top:8px; }
        .kpi-multi > div { display:flex; justify-content:space-between; align-items:baseline; padding:5px 0; border-bottom:1px dashed #e1e0d9; font-size:13px; color:#52514e; }
        .kpi-multi > div:last-child { border-bottom:none; padding-top:8px; font-size:15px; }
        .kpi-multi > div:last-child b { font-size:17px; color:#0b0b0b; }
        .kpi-multi b { font-weight:700; }
    </style>
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col">
                            <h1>
                                <i class="material-icons-outlined" style="vertical-align:middle;margin-right:6px;">inventory_2</i>
                                Pieces Sold Report — <?php echo htmlspecialchars($selected_entity_name); ?>
                            </h1>
                        </div>
                    </div>

                    <div style="background:#fff;border:1px solid rgba(11,11,11,.1);border-radius:10px;padding:14px 18px;margin-bottom:14px;">
                        <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">From</label>
                                <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control form-control-sm">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">To</label>
                                <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control form-control-sm">
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            </div>
                        </form>
                    </div>

                    <h3 style="font-size:19px;font-weight:700;margin:20px 0 4px;">Napkin</h3>
                    <p class="text-muted" style="font-size:12px;margin-bottom:10px;">Period: <?php echo date('d M Y', strtotime($from)); ?> – <?php echo date('d M Y', strtotime($to)); ?></p>

                    <div class="equation-row">
                        <div class="kpi-card">
                            <div class="kpi-t">Sold</div>
                            <div class="kpi-multi">
                                <div><span>Pack Qty</span><b><?php echo inr_format($grand_total_pack_qty, 0); ?></b></div>
                                <div><span>Pieces</span><b><?php echo inr_format($grand_total_pieces, 0); ?></b></div>
                                <div><span>Value</span><b>&#8377;<?php echo inr_format($grand_total_value, 2); ?></b></div>
                            </div>
                        </div>
                        <div class="equation-op">&minus;</div>
                        <div class="kpi-card">
                            <div class="kpi-t">Return</div>
                            <div class="kpi-multi">
                                <div><span>Pack Qty</span><b><?php echo inr_format($grand_total_return_qty, 0); ?></b></div>
                                <div><span>Pieces</span><b><?php echo inr_format($grand_total_return_pieces, 0); ?></b></div>
                                <div><span>Value</span><b>&#8377;<?php echo inr_format($grand_total_return_value, 2); ?></b></div>
                            </div>
                        </div>
                        <div class="equation-op eq">=</div>
                        <div class="kpi-card">
                            <div class="kpi-t">Overall Turnover</div>
                            <div class="kpi-multi">
                                <div><span>Pack Qty</span><b><?php echo inr_format($grand_total_net_qty, 0); ?></b></div>
                                <div><span>Pieces</span><b><?php echo inr_format($grand_total_net_pieces, 0); ?></b></div>
                                <div><span>Amount</span><b>&#8377;<?php echo inr_format($grand_total_value - $grand_total_return_value, 2); ?></b></div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="kpi-card"><div class="kpi-t">Napkin Gross Profit</div><div class="kpi-v" style="<?php echo $grand_gross_profit < 0 ? 'color:#dc2626;' : ''; ?>">&#8377;<?php echo inr_format($grand_gross_profit, 2); ?></div></div>
                        </div>
                    </div>
                    <p class="text-muted" style="font-size:11.5px;margin-top:-8px;margin-bottom:14px;">Gross Profit already nets out Purchase Value against Return Purchase Value on top of the Consolidated Amount above. Combined with Diaper and Expense at the bottom of this page.</p>

                    <?php if ($grand_total_unrated_pieces > 0): ?>
                    <div class="alert alert-warning" style="font-size:13px;"><?php echo inr_format($grand_total_unrated_pieces, 0); ?> pieces sold before any rate was set for their product — excluded from Sold Price. <a href="neksomo-llp-piece-sale.php">Add a rate</a> covering that period to include them.</div>
                    <?php endif; ?>
                    <?php if ($grand_total_unpriced_pieces > 0): ?>
                    <div class="alert alert-warning" style="font-size:13px;"><?php echo inr_format($grand_total_unpriced_pieces, 0); ?> pieces sold before any purchase rate was set for their product — treated as ₹0 cost, so Gross Profit may be overstated. <a href="neksomo-llp-piece-purchase-rate.php">Add a purchase rate</a> covering that period.</div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header"><h5 class="card-title">Product-wise Pieces Sold</h5></div>
                        <div class="card-body">
                            <div style="overflow-x:auto;">
                            <table class="mt">
                                <thead><tr><th>Product</th><th>Pack Qty Sold</th><th>Demo</th><th>Free</th><th>Damage</th><th>Return Qty</th><th>Net Qty</th><th>Pieces/Pack</th><th>Total Pieces Sold</th><th>Return Pieces</th><th>Net Pieces</th><th>Sold Value &#8377;</th><th>Return Value &#8377;</th><th>Net Value &#8377;</th></tr></thead>
                                <tbody>
                                <?php if (empty($pieces_sold)): ?>
                                    <tr><td colspan="14" style="text-align:center;color:#898781;">No sales in this period.</td></tr>
                                <?php else: foreach ($pieces_sold as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['productName']); ?></td>
                                        <td><?php echo inr_format((int)$row['total_qty'], 0); ?></td>
                                        <td><?php echo inr_format((int)$row['demo_qty'], 0); ?></td>
                                        <td><?php echo inr_format((int)$row['free_qty'], 0); ?></td>
                                        <td><?php echo inr_format((int)$row['damage_qty'], 0); ?></td>
                                        <td><?php echo inr_format((int)$row['return_qty'], 0); ?></td>
                                        <td><strong><?php echo inr_format((int)$row['net_qty'], 0); ?></strong></td>
                                        <td><?php echo $row['pieces_per_pack'] !== null ? (int)$row['pieces_per_pack'] : '1 *'; ?></td>
                                        <td><?php echo inr_format((int)$row['total_pieces'], 0); ?></td>
                                        <td><?php echo inr_format((int)$row['return_pieces'], 0); ?></td>
                                        <td><strong><?php echo inr_format((int)$row['net_pieces'], 0); ?></strong></td>
                                        <td>
                                            &#8377;<?php echo inr_format((float)$row['total_value'], 2); ?>
                                            <?php if ((float)$row['unrated_pieces'] > 0): ?>
                                            <div style="font-size:11px;color:#dc2626;"><?php echo inr_format((int)$row['unrated_pieces'], 0); ?> pcs unrated</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>&#8377;<?php echo inr_format((float)$row['return_value'], 2); ?></td>
                                        <td><strong>&#8377;<?php echo inr_format((float)$row['net_value'], 2); ?></strong></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                            </div>
                            <p style="font-size:11.5px;color:#898781;margin-top:10px;">* Pack size not set for this product — pieces shown equal pack quantity. Value uses whichever Femi9 LLP rate was effective on each sale's actual date. Demo/Free/Damage are a breakdown of Pack Qty Sold, not additional to it — Conversion entries aren't included anywhere in this report.</p>
                        </div>
                    </div>

                    <h3 style="font-size:19px;font-weight:700;margin:28px 0 12px;">Diaper</h3>
                    <div class="equation-row">
                        <div class="kpi-card">
                            <div class="kpi-t">Sold</div>
                            <div class="kpi-multi">
                                <div><span>Pack Qty</span><b><?php echo inr_format($grand_diaper_pack_qty, 0); ?></b></div>
                                <div><span>Value</span><b>&#8377;<?php echo inr_format($grand_diaper_value, 2); ?></b></div>
                            </div>
                        </div>
                        <div class="equation-op">&minus;</div>
                        <div class="kpi-card">
                            <div class="kpi-t">Return</div>
                            <div class="kpi-multi">
                                <div><span>Pack Qty</span><b><?php echo inr_format($grand_diaper_return_qty, 0); ?></b></div>
                                <div><span>Value</span><b>&#8377;<?php echo inr_format($grand_diaper_return_value, 2); ?></b></div>
                            </div>
                        </div>
                        <div class="equation-op eq">=</div>
                        <div class="kpi-card">
                            <div class="kpi-t">Overall Turnover</div>
                            <div class="kpi-multi">
                                <div><span>Pack Qty</span><b><?php echo inr_format($grand_diaper_net_qty, 0); ?></b></div>
                                <div><span>Amount</span><b>&#8377;<?php echo inr_format($grand_diaper_value - $grand_diaper_return_value, 2); ?></b></div>
                            </div>
                        </div>
                    </div>

                    <div class="equation-row">
                        <div class="kpi-card"><div class="kpi-t">Output GST (Sales)</div><div class="kpi-v">&#8377;<?php echo inr_format($grand_diaper_output_gst, 2); ?></div></div>
                        <div class="equation-op">&minus;</div>
                        <div class="kpi-card"><div class="kpi-t">Input GST (Purchases)</div><div class="kpi-v">&#8377;<?php echo inr_format($grand_diaper_input_gst, 2); ?></div></div>
                        <div class="equation-op eq">=</div>
                        <div class="kpi-card"><div class="kpi-t">Net GST Payable</div><div class="kpi-v" style="<?php echo $grand_diaper_net_gst < 0 ? 'color:#dc2626;' : ''; ?>">&#8377;<?php echo inr_format($grand_diaper_net_gst, 2); ?></div></div>
                    </div>
                    <p class="text-muted" style="font-size:11.5px;margin-top:-8px;margin-bottom:14px;">GST shown separately — never included in Diaper Gross Profit above. Net of returns.</p>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="kpi-card"><div class="kpi-t">Diaper Gross Profit</div><div class="kpi-v" style="<?php echo $grand_diaper_gross_profit < 0 ? 'color:#dc2626;' : ''; ?>">&#8377;<?php echo inr_format($grand_diaper_gross_profit, 2); ?></div></div>
                        </div>
                    </div>
                    <p class="text-muted" style="font-size:11.5px;margin-top:-8px;margin-bottom:14px;">Diaper is a pack-based product mapped 1:1 to its company SKU, so quantity/value here are packs/rate-per-pack — no piece conversion. Combined with Napkin and Expense at the bottom of this page.</p>

                    <?php if ($grand_diaper_unrated_qty > 0): ?>
                    <div class="alert alert-warning" style="font-size:13px;"><?php echo inr_format($grand_diaper_unrated_qty, 0); ?> packs sold before any rate was set for their product — excluded from Sold Value. <a href="neksomo-llp-piece-sale.php">Add a rate</a> covering that period to include them.</div>
                    <?php endif; ?>
                    <?php if ($grand_diaper_unpriced_qty > 0): ?>
                    <div class="alert alert-warning" style="font-size:13px;"><?php echo inr_format($grand_diaper_unpriced_qty, 0); ?> packs sold before any purchase rate was set for their product — treated as ₹0 cost, so Gross Profit may be overstated. <a href="neksomo-llp-piece-purchase-rate.php">Add a purchase rate</a> covering that period.</div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header"><h5 class="card-title">Product-wise Packs Sold</h5></div>
                        <div class="card-body">
                            <div style="overflow-x:auto;">
                            <table class="mt">
                                <thead><tr><th>Product</th><th>Pack Qty Sold</th><th>Demo</th><th>Free</th><th>Damage</th><th>Return Qty</th><th>Net Qty</th><th>Sold Value &#8377;</th><th>Return Value &#8377;</th><th>Net Value &#8377;</th></tr></thead>
                                <tbody>
                                <?php if (empty($diaper_sold)): ?>
                                    <tr><td colspan="10" style="text-align:center;color:#898781;">No sales in this period.</td></tr>
                                <?php else: foreach ($diaper_sold as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['productName']); ?></td>
                                        <td><?php echo inr_format((int)$row['total_qty'], 0); ?></td>
                                        <td><?php echo inr_format((int)$row['demo_qty'], 0); ?></td>
                                        <td><?php echo inr_format((int)$row['free_qty'], 0); ?></td>
                                        <td><?php echo inr_format((int)$row['damage_qty'], 0); ?></td>
                                        <td><?php echo inr_format((int)$row['return_qty'], 0); ?></td>
                                        <td><strong><?php echo inr_format((int)$row['net_qty'], 0); ?></strong></td>
                                        <td>
                                            &#8377;<?php echo inr_format((float)$row['total_value'], 2); ?>
                                            <?php if ((float)$row['unrated_qty'] > 0): ?>
                                            <div style="font-size:11px;color:#dc2626;"><?php echo inr_format((int)$row['unrated_qty'], 0); ?> packs unrated</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>&#8377;<?php echo inr_format((float)$row['return_value'], 2); ?></td>
                                        <td><strong>&#8377;<?php echo inr_format((float)$row['net_value'], 2); ?></strong></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                            </div>
                            <p style="font-size:11.5px;color:#898781;margin-top:10px;">Demo/Free/Damage are a breakdown of Pack Qty Sold, not additional to it — Conversion entries aren't included anywhere in this report.</p>
                        </div>
                    </div>

                    <h3 style="font-size:19px;font-weight:700;margin:28px 0 4px;">Combined</h3>
                    <div class="equation-row">
                        <div class="kpi-card"><div class="kpi-t">Napkin Gross Profit</div><div class="kpi-v" style="<?php echo $grand_gross_profit < 0 ? 'color:#dc2626;' : ''; ?>">&#8377;<?php echo inr_format($grand_gross_profit, 2); ?></div></div>
                        <div class="equation-op">+</div>
                        <div class="kpi-card"><div class="kpi-t">Diaper Gross Profit</div><div class="kpi-v" style="<?php echo $grand_diaper_gross_profit < 0 ? 'color:#dc2626;' : ''; ?>">&#8377;<?php echo inr_format($grand_diaper_gross_profit, 2); ?></div></div>
                        <div class="equation-op eq">=</div>
                        <div class="kpi-card"><div class="kpi-t">Combined Gross Profit</div><div class="kpi-v" style="<?php echo $grand_combined_gross_profit < 0 ? 'color:#dc2626;' : ''; ?>">&#8377;<?php echo inr_format($grand_combined_gross_profit, 2); ?></div></div>
                    </div>

                    <div class="equation-row">
                        <div class="kpi-card"><div class="kpi-t">Combined Gross Profit</div><div class="kpi-v" style="<?php echo $grand_combined_gross_profit < 0 ? 'color:#dc2626;' : ''; ?>">&#8377;<?php echo inr_format($grand_combined_gross_profit, 2); ?></div></div>
                        <div class="equation-op">&minus;</div>
                        <div class="kpi-card"><div class="kpi-t">Expense</div><div class="kpi-v">&#8377;<?php echo inr_format($grand_combined_expense, 2); ?></div></div>
                        <div class="equation-op eq">=</div>
                        <div class="kpi-card"><div class="kpi-t">Net Profit</div><div class="kpi-v" style="<?php echo $grand_combined_net_profit < 0 ? 'color:#dc2626;' : ''; ?>">&#8377;<?php echo inr_format($grand_combined_net_profit, 2); ?></div></div>
                    </div>
                    <p class="text-muted" style="font-size:11.5px;margin-top:-8px;margin-bottom:14px;">Expense is Neksomo's single shared expense pool (not split between categories).</p>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="kpi-card"><div class="kpi-t">Overall GST Payable</div><div class="kpi-v" style="<?php echo $grand_combined_net_gst < 0 ? 'color:#dc2626;' : ''; ?>">&#8377;<?php echo inr_format($grand_combined_net_gst, 2); ?></div></div>
                        </div>
                    </div>
                    <p class="text-muted" style="font-size:11.5px;margin-top:-8px;margin-bottom:14px;">GST payable is separate from profit — it's collected on behalf of the government, not earnings. Napkin is always 0% GST, so this is effectively Diaper's Net GST.</p>

                    <?php
                    // Fixed partner split of combined Net Profit — Anand 50%, Saravana Shankar 40%, Tamil Selvan 10%.
                    $profit_shares = [
                        'Anand'            => 0.50,
                        'Saravana Shankar' => 0.40,
                        'Tamil Selvan'     => 0.10,
                    ];
                    ?>
                    <div class="row mb-3">
                        <div class="col-12">
                            <p class="text-muted" style="font-size:11.5px;margin-bottom:6px;">Combined Net Profit Share (this period)</p>
                        </div>
                        <?php foreach ($profit_shares as $partner_name => $partner_pct): ?>
                        <div class="col-md-4">
                            <div class="kpi-card">
                                <div class="kpi-t"><?php echo htmlspecialchars($partner_name); ?> (<?php echo (int)round($partner_pct * 100); ?>%)</div>
                                <div class="kpi-v" style="<?php echo $grand_combined_net_profit < 0 ? 'color:#dc2626;' : ''; ?>">&#8377;<?php echo inr_format($grand_combined_net_profit * $partner_pct, 2); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
<?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        :root {
            --surface-1:      #ffffff;
            --page-plane:     #f7f7f6;
            --text-primary:   #0b0b0b;
            --text-secondary: #52514e;
            --text-muted:     #898781;
            --gridline:       #e1e0d9;
            --border:         rgba(11,11,11,0.10);

            --blue:    #2a78d6; --blue-tint:    #eaf2fc;
            --aqua:    #1baf7a; --aqua-tint:    #e7f8f1;
            --green:   #008300; --green-tint:   #e5f0e5;
            --violet:  #4a3aa7; --violet-tint:  #eeecf8;
            --orange:  #eb6834; --orange-tint:  #fdece4;
            --red:     #e34948; --red-tint:     #fcecec;

            --good:     #0ca30c; --good-tint:     #e5f7e5;
            --warning:  #fab219; --warning-tint:  #fff3dc;
            --serious:  #ec835a;
            --critical: #d03b3b; --critical-tint: #fbe6e6;
        }

        body { background: var(--page-plane); }
        .mis-section { margin-bottom: 28px; scroll-margin-top: 90px; }

        /* ── Filter bar ──────────────────────────────────────────────── */
        .mis-filter { background: var(--surface-1); border: 1px solid var(--border); border-radius: 10px; padding: 14px 18px; margin-bottom: 14px; box-shadow: 0 1px 2px rgba(11,11,11,0.03); }
        .preset-btn { padding:4px 13px; border-radius:20px; border:1.5px solid var(--blue); color:var(--blue); background:var(--surface-1); font-size:12px; cursor:pointer; text-decoration:none; display:inline-block; transition:background .12s,color .12s; }
        .preset-btn.active, .preset-btn:hover { background:var(--blue); color:#fff; border-color:var(--blue); }

        /* ── Section navigation (sticky quick-jump) ──────────────────── */
        .section-nav { position: sticky; top: 0; z-index: 20; background: var(--surface-1); border: 1px solid var(--border); border-radius: 10px; padding: 8px 10px; margin-bottom: 22px; display: flex; gap: 4px; overflow-x: auto; box-shadow: 0 1px 2px rgba(11,11,11,0.03); }
        .section-nav a { flex: 0 0 auto; padding: 7px 14px; border-radius: 7px; font-size: 12.5px; font-weight: 600; color: var(--text-secondary); text-decoration: none; white-space: nowrap; transition: background .12s, color .12s; }
        .section-nav a:hover { background: var(--page-plane); color: var(--text-primary); }
        .section-nav a.active { background: var(--blue-tint); color: var(--blue); }

        /* ── KPI stat tiles ───────────────────────────────────────────── */
        .kpi-card { background: var(--surface-1); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px 18px 22px; position: relative; overflow: hidden; height: 100%; box-shadow: 0 1px 2px rgba(11,11,11,0.03); transition: box-shadow .15s ease, transform .15s ease; }
        .kpi-card:hover { box-shadow: 0 6px 16px rgba(11,11,11,0.08); transform: translateY(-1px); }
        .kpi-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background: var(--kpi-accent, var(--blue)); }
        .kpi-card .kpi-ico { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; background: var(--kpi-tint, var(--blue-tint)); color: var(--kpi-accent, var(--blue)); font-size:17px; position:absolute; right:16px; top:16px; }
        .kpi-card .kpi-t  { font-size: 11.5px; text-transform: uppercase; letter-spacing: .6px; font-weight:700; color: var(--text-secondary); padding-right:40px; }
        .kpi-card .kpi-v  { font-size: 28px; font-weight: 800; margin-top: 10px; line-height: 1.15; color: var(--text-primary); letter-spacing: -0.01em; font-variant-numeric: tabular-nums; word-break: break-word; }
        .kpi-card .kpi-s  { font-size: 12.5px; font-weight: 600; margin-top: 9px; color: var(--text-secondary); }
        .kpi-card .kpi-s.good { color: var(--good); }
        .kpi-card .kpi-s.bad  { color: var(--critical); }
        @media (max-width: 576px) {
            .kpi-card .kpi-v { font-size: 23px; }
        }

        /* ── Equation-style consolidated cards (Sales − Returns = Turnover) ── */
        .equation-row { display:flex; align-items:stretch; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
        .equation-row .kpi-card { flex:1 1 220px; }
        .equation-op { display:flex; align-items:center; justify-content:center; font-size:26px; font-weight:300; color: var(--text-muted); flex:0 0 auto; padding:0 2px; }
        .equation-op.eq { color: var(--text-secondary); font-weight:600; }
        .kpi-multi { margin-top:10px; }
        .kpi-multi > div { display:flex; justify-content:space-between; align-items:baseline; padding:5px 0; border-bottom:1px dashed var(--gridline); font-size:13px; color: var(--text-secondary); }
        .kpi-multi > div:last-child { border-bottom:none; padding-top:8px; font-size:15px; }
        .kpi-multi > div:last-child b { font-size:17px; color: var(--text-primary); font-variant-numeric: tabular-nums; }
        .kpi-multi b { font-weight:700; font-variant-numeric: tabular-nums; color: var(--text-primary); }
        @media (max-width: 576px) {
            .equation-op { flex-basis:100%; }
        }

        /* ── Tabs (period breakdown) ──────────────────────────────────── */
        .tab-nav { display:flex; gap:0; border-bottom:1px solid var(--gridline); margin-bottom:14px; }
        .tab-item { padding:7px 18px; cursor:pointer; font-size:13px; font-weight:600; color:var(--text-secondary); border-bottom:2px solid transparent; margin-bottom:-1px; transition:color .12s,border-color .12s; }
        .tab-item.active { color:var(--blue); border-bottom-color:var(--blue); }
        .tab-content { display:none; } .tab-content.active { display:block; }

        /* ── Tables ───────────────────────────────────────────────────── */
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:var(--page-plane); font-weight:600; color:var(--text-secondary); padding:8px 11px; text-align:left; border-bottom:1px solid var(--gridline); white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:7px 11px; border-bottom:1px solid var(--gridline); vertical-align:middle; color:var(--text-primary); }
        .mt td.num, .mt td.text-right { font-variant-numeric: tabular-nums; }
        .mt tr:hover td { background: var(--page-plane); }

        /* ── Progress / meter bars ───────────────────────────────────── */
        .pbar { height:7px; border-radius:4px; background: var(--blue-tint); overflow:hidden; }
        .pbar .pf { height:100%; border-radius:4px; background: var(--blue); }

        /* ── Badges ───────────────────────────────────────────────────── */
        .br { background:var(--green-tint); color:var(--green); padding:2px 7px; border-radius:10px; font-size:12px; font-weight:600; }
        .bq { background:var(--blue-tint); color:var(--blue); padding:2px 7px; border-radius:10px; font-size:12px; font-weight:600; }
        .bp  { background:var(--good-tint); color:var(--good); }
        .bpa { background:var(--warning-tint); color:#9a6b00; }
        .bu  { background:var(--critical-tint); color:var(--critical); }
        .sbadge { padding:2px 9px; border-radius:10px; font-size:12px; font-weight:600; }
        .gp { color:var(--good); font-weight:700; } .gn { color:var(--critical); font-weight:700; }
        .chart-box { position:relative; height:250px; }
        .rank-1 { color:var(--warning); font-weight:700; } .rank-2 { color:var(--text-muted); font-weight:700; } .rank-3 { color:var(--orange); font-weight:700; }
        .tp-tag { font-size:11px; background:var(--blue-tint); color:var(--blue); padding:1px 6px; border-radius:4px; }
        .snote { font-size:12px; color:var(--text-muted); margin-bottom:6px; }

        /* ── Cards (page-wide polish for the existing Bootstrap .card) ─ */
        .card { border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(11,11,11,0.03); }
        .card-header { background: var(--surface-1); border-bottom: 1px solid var(--gridline); }
        .card-title { font-size: 14px; font-weight: 700; color: var(--text-primary); margin: 0; }

        /* ── Status legend row (Order Status) ─────────────────────────── */
        .status-row { display:flex; align-items:center; justify-content:space-between; padding:8px 0; }
        .status-row .status-label { display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text-primary); }
        .status-dot { width:10px; height:10px; border-radius:50%; flex:0 0 auto; }
        .status-row .status-val { font-size:13px; font-weight:600; color:var(--text-primary); font-variant-numeric: tabular-nums; }
        .stackbar { display:flex; height:14px; border-radius:7px; overflow:hidden; background:var(--gridline); margin-bottom:4px; }
        .stackbar > div { height:100%; }
    </style>
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Femi9 Company">
    <meta name="theme-color" content="#f5b400">
    <link rel="apple-touch-icon" href="../../assets/images/pwa-icon-apple-touch.png">
    <script>
    if ("serviceWorker" in navigator) {
        window.addEventListener("load", function () {
            navigator.serviceWorker.register("service-worker.js");
        });
    }
    </script>
</head>
<body>
    <div id="app-preloader" style="position:fixed;inset:0;z-index:99999;background:#ffffff;display:flex;flex-direction:column;align-items:center;justify-content:center;transition:opacity .25s ease;">
        <img src="../../assets/images/pwa-icon-192.png" alt="" style="width:72px;height:72px;border-radius:50%;margin-bottom:18px;">
        <div style="width:34px;height:34px;border:3px solid #f0e2b9;border-top-color:#f5b400;border-radius:50%;animation:app-preloader-spin .8s linear infinite;"></div>
    </div>
    <style>@keyframes app-preloader-spin{to{transform:rotate(360deg)}}</style>
    <script>
    (function(){
        var el = document.getElementById('app-preloader');
        function hide(){
            if (!el) return;
            el.style.opacity = '0';
            setTimeout(function(){ el && el.remove(); }, 300);
        }
        document.addEventListener('DOMContentLoaded', hide);
        window.addEventListener('load', hide);
        setTimeout(hide, 1500);
    })();
    </script>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">

                    <!-- Header -->
                    <div class="row mb-2">
                        <div class="col">
                            <div class="page-description" style="margin-left:-10px;">
                                <h1>
                                    <i class="material-icons-outlined" style="vertical-align:middle;margin-right:6px;">assessment</i>
                                    Dashboard — Sales Overview
                                </h1>
                            </div>
                        </div>
                    </div>

                    <style>
                        /* ── Main / Total Flow toggle ─────────────────── */
                        .tf-toggle { display:inline-flex; gap:4px; background:var(--page-plane); border:1px solid var(--border); border-radius:22px; padding:4px; margin-bottom:14px; }
                        .tf-toggle-btn { border:none; background:transparent; color:var(--text-secondary); font-size:13px; font-weight:600; padding:7px 20px; border-radius:18px; cursor:pointer; transition:background .12s,color .12s; }
                        .tf-toggle-btn.active { background:var(--blue); color:#fff; }
                        /* ── Total Flow panel ─────────────────────────── */
                        .tf-banner { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; background:var(--surface-1); border:1px solid var(--border); border-radius:10px; padding:14px 20px; margin-bottom:14px; box-shadow:0 1px 2px rgba(11,11,11,0.03); }
                        .tf-banner-title { font-size:16px; font-weight:800; letter-spacing:.3px; color:var(--text-primary); }
                        .tf-banner-date { font-size:13px; font-weight:600; color:var(--text-secondary); }
                        .tf-panel-title { font-size:13.5px; font-weight:700; margin:0 0 12px; color:var(--text-primary); }
                        .tf-sidebar { display:flex; flex-direction:column; }
                        .tf-team-list { list-style:none; margin:0 0 16px; padding:0; }
                        .tf-team-list li { padding:8px 0; border-bottom:1px solid var(--gridline); font-size:12.5px; }
                        .tf-team-list li b { display:block; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; color:var(--blue); }
                        .tf-team-overview h4 { font-size:12px; text-transform:uppercase; letter-spacing:.4px; color:var(--text-secondary); margin:0 0 8px; }
                        .tf-team-overview .tf-ov-row { display:flex; justify-content:space-between; font-size:12.5px; padding:4px 0; }
                        .tf-callout { text-align:center; font-weight:700; font-size:13px; margin-top:10px; padding:8px; border-radius:8px; background:var(--critical-tint); color:var(--critical); }
                        .tf-donut-center { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; font-weight:800; font-size:20px; color:var(--text-primary); pointer-events:none; }
                        .tf-legend { display:flex; justify-content:center; gap:16px; margin-top:10px; font-size:12px; }
                        .tf-legend span.dot { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:5px; }
                        .tf-insights { margin:0; padding-left:20px; font-size:13px; line-height:1.9; }
                        .tf-table-total td { font-weight:700; background:var(--page-plane); }
                    </style>

                    <!-- ── MAIN / TOTAL FLOW TOGGLE ─────────────────────────
                         Pure client-side show/hide of #mainPanel vs
                         #totalFlowPanel — never reloads the page. The Total
                         Flow panel's data is fetched lazily (only on first
                         click) from dashboard-total-flow-data.php and cached
                         in JS afterwards; it never runs on normal page load. -->
                    <div class="tf-toggle" id="dashViewToggle">
                        <button type="button" class="tf-toggle-btn active" id="btnDashMain" data-view="main">Main</button>
                        <button type="button" class="tf-toggle-btn" id="btnDashTotalFlow" data-view="totalflow">Total Flow</button>
                    </div>

                    <div id="mainPanel">

                    <!-- ── FILTER ────────────────────────────────────────── -->
                    <div class="mis-filter">
                        <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">MIS Type</label>
                                <select name="mis_type" class="form-control form-control-sm" style="width:140px;" onchange="this.form.submit()">
                                    <option value="sales" selected>Sales</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Scope</label>
                                <select name="scope" id="scopeSelect" class="form-control form-control-sm" style="width:190px;" onchange="this.form.submit()">
                                    <option value="company" <?php echo $scope==='company'?'selected':''; ?>>Income to Company</option>
                                    <option value="tp" <?php echo $scope==='tp'?'selected':''; ?>>Territory Partner</option>
                                    <option value="super_stockiest" <?php echo $scope==='super_stockiest'?'selected':''; ?>>Super Stockist</option>
                                    <option value="stockiest" <?php echo $scope==='stockiest'?'selected':''; ?>>Stockist</option>
                                </select>
                            </div>
                            <div id="tpSubFilter" style="<?php echo $scope!=='tp' ? 'display:none;' : ''; ?>">
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Territory Partner</label>
                                <select name="tp_id" class="form-control form-control-sm" style="width:200px;" onchange="this.form.submit()">
                                    <option value="0" <?php echo $filter_tp==0?'selected':''; ?>>All Territory Partners</option>
                                    <?php foreach ($all_tps as $tp): ?>
                                    <option value="<?php echo $tp['id']; ?>" <?php echo $filter_tp==$tp['id']?'selected':''; ?>>
                                        <?php echo htmlspecialchars($tp['name']); ?> (<?php echo $tp['tp_id']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">From</label>
                                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo $from; ?>" style="width:145px;">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">To</label>
                                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo $to; ?>" style="width:145px;">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            </div>
                            <div style="margin-left:auto;display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap;">
                                <?php $tp_qs = "&scope={$scope}" . ($filter_tp > 0 ? "&tp_id={$filter_tp}" : ""); ?>
                                <a href="?preset=today<?php echo $tp_qs; ?>"  class="preset-btn <?php echo $preset=='today' ?'active':''; ?>">Today</a>
                                <a href="?preset=week<?php echo $tp_qs; ?>"   class="preset-btn <?php echo $preset=='week'  ?'active':''; ?>">This Week</a>
                                <a href="?preset=month<?php echo $tp_qs; ?>"  class="preset-btn <?php echo $preset=='month' ?'active':''; ?>">This Month</a>
                                <a href="?preset=year<?php echo $tp_qs; ?>"   class="preset-btn <?php echo $preset=='year'  ?'active':''; ?>">This Year</a>
                            </div>
                        </form>
                        <?php
                        $scope_labels = ['company'=>'Income to Company','tp'=>'Territory Partner','super_stockiest'=>'Super Stockist','stockiest'=>'Stockist'];
                        ?>
                        <div style="font-size:12px;color:#888;margin-top:7px;">
                            Scope: <b><?php echo $scope_labels[$scope]; ?></b> &nbsp;|&nbsp;
                            <?php if ($scope==='tp' && $filter_tp > 0): ?>
                            Filtered by TP: <b><?php foreach($all_tps as $t) if ($t['id']==$filter_tp) echo htmlspecialchars($t['name']); ?></b> &nbsp;|&nbsp;
                            <?php endif; ?>
                            Period: <b><?php echo date('d M Y', strtotime($from)); ?></b> to <b><?php echo date('d M Y', strtotime($to)); ?></b>
                        </div>
                    </div>
                    <script>
                    document.getElementById('scopeSelect').addEventListener('change', function() {
                        document.getElementById('tpSubFilter').style.display = this.value === 'tp' ? '' : 'none';
                    });
                    </script>

                    <!-- ── SECTION NAVIGATION (quick jump) ─────────────────── -->
                    <nav class="section-nav" id="sectionNav">
                        <a href="#sec-overview">Overview</a>
                        <?php if ($scope === 'company'): ?><a href="#sec-channels">Channels</a><?php endif; ?>
                        <a href="#sec-trend">Trend</a>
                        <a href="#sec-breakdown">Breakdown</a>
                        <?php if ($scope === 'tp'): ?><a href="#sec-tpperf">TP Performance</a><?php endif; ?>
                        <a href="#sec-products">Products</a>
                        <a href="#sec-geo">Geography</a>
                        <a href="#sec-topcustomers">Shops &amp; Distributors</a>
                        <a href="#sec-growth">Growth</a>
                        <a href="#sec-returns">Returns</a>
                    </nav>

                    <!-- ══ KPI CARDS ════════════════════════════════════════ -->
                    <?php
                    // accent => [border/icon color, icon chip tint]
                    $accents = [
                        'blue'     => ['var(--blue)',     'var(--blue-tint)'],
                        'aqua'     => ['var(--aqua)',     'var(--aqua-tint)'],
                        'green'    => ['var(--green)',    'var(--green-tint)'],
                        'violet'   => ['var(--violet)',   'var(--violet-tint)'],
                        'orange'   => ['var(--orange)',   'var(--orange-tint)'],
                        'good'     => ['var(--good)',     'var(--good-tint)'],
                        'warning'  => ['var(--warning)',  'var(--warning-tint)'],
                        'critical' => ['var(--critical)', 'var(--critical-tint)'],
                    ];
                    $active_labels = [
                        'company'          => ['Active Counterparties', 'Direct customers'],
                        'tp'               => ['Active TPs', 'TPs'],
                        'super_stockiest'  => ['Active Super Stockists', 'Super Stockists'],
                        'stockiest'        => ['Active Stockists', 'Stockists'],
                    ];
                    [$active_label, $active_sublabel] = $active_labels[$scope];
                    $active_sub_text = ($scope === 'company')
                        ? inr_format($active_customers, 0).' customers · '.inr_format($active_businesses, 0).' businesses'
                        : $active_sublabel.' with invoices in period';
                    // kpi row: [accent key, icon, label, value, sub-text, sub-text tone ('', 'good', 'bad')]
                    // Sales / Returns / Total Turnover are shown as a consolidated
                    // 3-card equation (Sales − Returns = Total Turnover) below,
                    // each card carrying its own amount + count/quantity stats —
                    // same .equation-row/.kpi-multi pattern already used for the
                    // Neksomo Sold/Return/Overall Turnover cards further down this
                    // page — so they're built separately, not part of $kpis.
                    $kpis = [
                        ['violet','people',$active_label,inr_format($active_tps, 0),
                         $active_sub_text, ''],
                    ];
                    if ($scope === 'tp') {
                        $tgt_accent = $overall_pct_all>=100 ? 'good' : ($overall_pct_all>=50 ? 'warning' : 'critical');
                        $kpis[] = [$tgt_accent,'flag','Overall Napkin Target %',$overall_pct_all.'%',
                         '₹'.inr_format($total_napkin_achieved_all, 0).' / ₹'.inr_format($total_target_all, 0), ''];
                    }
                    // Company scope (non-Neksomo) shows Gross Profit / Expenses / Net
                    // Profit as the dedicated Napkin/Diaper split card further down the
                    // page instead of here, so this generic trio is skipped there to
                    // avoid showing the same figures twice.
                    if (!($scope === 'company' && !$is_neksomo_view)) {
                        $kpis[] = ['orange','trending_up','Gross Profit','₹'.inr_format($gross_profit, 0),
                             'Total Turnover − LLP cost basis', ''];
                        if ($scope === 'company') {
                            $kpis[] = ['critical','receipt','Expenses','₹'.inr_format($total_expenses, 0),
                             'for selected period', ''];
                            $kpis[] = [$net_profit>=0?'good':'critical','account_balance_wallet','Net Profit','₹'.inr_format($net_profit, 0),
                             'Gross Profit − Expenses (₹'.inr_format($total_expenses, 0).')', $net_profit>=0?'good':'bad'];
                        }
                    }
                    ?>
                    <div class="row mis-section" id="sec-overview">
                        <div class="col-12">
                        <div class="equation-row">
                            <div class="kpi-card" style="--kpi-accent:var(--blue);--kpi-tint:var(--blue-tint);">
                                <i class="material-icons-outlined kpi-ico">payments</i>
                                <div class="kpi-t">Sales</div>
                                <div class="kpi-multi">
                                    <div><span>Amount</span><b>₹<?php echo inr_format($gross_revenue, 0); ?></b></div>
                                    <div><span>Invoices</span><b><?php echo inr_format($total_invoices, 0); ?></b></div>
                                    <div><span>Units</span><b><?php echo inr_format($total_units, 0); ?></b></div>
                                </div>
                            </div>
                            <div class="equation-op">&minus;</div>
                            <div class="kpi-card" style="--kpi-accent:var(--critical);--kpi-tint:var(--critical-tint);">
                                <i class="material-icons-outlined kpi-ico">keyboard_return</i>
                                <div class="kpi-t">Returns</div>
                                <div class="kpi-multi">
                                    <div><span>Amount</span><b>₹<?php echo inr_format($total_return_amt, 0); ?></b></div>
                                    <div><span>Returns</span><b><?php echo inr_format($total_returns, 0); ?></b></div>
                                    <div><span>Quantity</span><b><?php echo inr_format($total_return_qty, 0); ?></b></div>
                                </div>
                            </div>
                            <div class="equation-op eq">=</div>
                            <div class="kpi-card" style="--kpi-accent:var(--good);--kpi-tint:var(--good-tint);">
                                <i class="material-icons-outlined kpi-ico">account_balance_wallet</i>
                                <div class="kpi-t">Total Turnover</div>
                                <div class="kpi-multi">
                                    <div><span>Amount</span><b>₹<?php echo inr_format($total_revenue, 0); ?></b></div>
                                    <div><span>Quantity</span><b><?php echo inr_format($net_units, 0); ?></b></div>
                                    <div><span>vs Prev Period</span><b style="color:<?php echo $revenue_growth>=0?'var(--good)':'var(--critical)'; ?>"><?php echo ($revenue_growth>=0?'▲':'▼').' '.abs($revenue_growth).'%'; ?></b></div>
                                </div>
                            </div>
                        </div>
                        </div>
                        <?php foreach ($kpis as $k): [$accent, $tint] = $accents[$k[0]]; ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 col-6 mb-3">
                            <div class="kpi-card" style="--kpi-accent:<?php echo $accent; ?>;--kpi-tint:<?php echo $tint; ?>;">
                                <i class="material-icons-outlined kpi-ico"><?php echo $k[1]; ?></i>
                                <div class="kpi-t"><?php echo $k[2]; ?></div>
                                <div class="kpi-v"><?php echo $k[3]; ?></div>
                                <div class="kpi-s<?php echo $k[5] ? ' '.($k[5]==='bad'?'bad':'good') : ''; ?>"><?php echo $k[4]; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ══ SALES / RETURN / TURNOVER — NAPKIN / DIAPER SPLIT (Income to Company scope only) ═ -->
                    <?php if ($scope === 'company' && !$is_neksomo_view): ?>
                    <div class="row mis-section" id="sec-srt-split">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Sales / Return / Turnover — Napkin / Diaper</h5></div>
                                <div class="card-body">
                                    <p class="snote">Only products mapped to a Neksomo product (Napkin/Diaper Product Mapping) are classified here — an unmapped product has no category and is excluded from this split, though it's still included in the combined Sales/Turnover figures above.</p>

                                    <h3 style="font-size:16px;font-weight:700;margin:0 0 10px;">Napkin</h3>
                                    <div class="equation-row">
                                        <div class="kpi-card">
                                            <div class="kpi-t">Sales</div>
                                            <div class="kpi-multi">
                                                <div><span>Amount</span><b>₹<?php echo inr_format($grand_napkin_sold_amt_llp, 0); ?></b></div>
                                                <div><span>Quantity</span><b><?php echo inr_format($grand_napkin_sold_qty_llp, 0); ?></b></div>
                                            </div>
                                        </div>
                                        <div class="equation-op">&minus;</div>
                                        <div class="kpi-card">
                                            <div class="kpi-t">Returns</div>
                                            <div class="kpi-multi">
                                                <div><span>Amount</span><b>₹<?php echo inr_format($grand_napkin_return_amt_llp, 0); ?></b></div>
                                                <div><span>Quantity</span><b><?php echo inr_format($grand_napkin_return_qty_llp, 0); ?></b></div>
                                            </div>
                                        </div>
                                        <div class="equation-op eq">=</div>
                                        <div class="kpi-card">
                                            <div class="kpi-t">Total Turnover</div>
                                            <div class="kpi-multi">
                                                <div><span>Amount</span><b>₹<?php echo inr_format($grand_napkin_turnover_amt_llp, 0); ?></b></div>
                                                <div><span>Quantity</span><b><?php echo inr_format($grand_napkin_turnover_qty_llp, 0); ?></b></div>
                                            </div>
                                        </div>
                                    </div>

                                    <h3 style="font-size:16px;font-weight:700;margin:20px 0 10px;">Diaper</h3>
                                    <div class="equation-row">
                                        <div class="kpi-card">
                                            <div class="kpi-t">Sales</div>
                                            <div class="kpi-multi">
                                                <div><span>Amount</span><b>₹<?php echo inr_format($grand_diaper_sold_amt_llp, 0); ?></b></div>
                                                <div><span>Quantity</span><b><?php echo inr_format($grand_diaper_sold_qty_llp, 0); ?></b></div>
                                            </div>
                                        </div>
                                        <div class="equation-op">&minus;</div>
                                        <div class="kpi-card">
                                            <div class="kpi-t">Returns</div>
                                            <div class="kpi-multi">
                                                <div><span>Amount</span><b>₹<?php echo inr_format($grand_diaper_return_amt_llp, 0); ?></b></div>
                                                <div><span>Quantity</span><b><?php echo inr_format($grand_diaper_return_qty_llp, 0); ?></b></div>
                                            </div>
                                        </div>
                                        <div class="equation-op eq">=</div>
                                        <div class="kpi-card">
                                            <div class="kpi-t">Total Turnover</div>
                                            <div class="kpi-multi">
                                                <div><span>Amount</span><b>₹<?php echo inr_format($grand_diaper_turnover_amt_llp, 0); ?></b></div>
                                                <div><span>Quantity</span><b><?php echo inr_format($grand_diaper_turnover_qty_llp, 0); ?></b></div>
                                            </div>
                                        </div>
                                    </div>

                                    <h3 style="font-size:16px;font-weight:700;margin:20px 0 10px;">Combined</h3>
                                    <div class="equation-row">
                                        <div class="kpi-card">
                                            <div class="kpi-t">Sales</div>
                                            <div class="kpi-multi">
                                                <div><span>Amount</span><b>₹<?php echo inr_format($grand_combined_sold_amt_llp, 0); ?></b></div>
                                                <div><span>Quantity</span><b><?php echo inr_format($grand_combined_sold_qty_llp, 0); ?></b></div>
                                            </div>
                                        </div>
                                        <div class="equation-op">&minus;</div>
                                        <div class="kpi-card">
                                            <div class="kpi-t">Returns</div>
                                            <div class="kpi-multi">
                                                <div><span>Amount</span><b>₹<?php echo inr_format($grand_combined_return_amt_llp, 0); ?></b></div>
                                                <div><span>Quantity</span><b><?php echo inr_format($grand_combined_return_qty_llp, 0); ?></b></div>
                                            </div>
                                        </div>
                                        <div class="equation-op eq">=</div>
                                        <div class="kpi-card">
                                            <div class="kpi-t">Total Turnover</div>
                                            <div class="kpi-multi">
                                                <div><span>Amount</span><b>₹<?php echo inr_format($grand_combined_turnover_amt_llp, 0); ?></b></div>
                                                <div><span>Quantity</span><b><?php echo inr_format($grand_combined_turnover_qty_llp, 0); ?></b></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ══ GROSS PROFIT — NAPKIN / DIAPER SPLIT (Income to Company scope only) ═ -->
                    <?php if ($scope === 'company' && !$is_neksomo_view): ?>
                    <div class="row mis-section" id="sec-gp-split">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Gross Profit — Napkin / Diaper</h5></div>
                                <div class="card-body">
                                    <p class="snote">Only products mapped to a Neksomo product (Napkin/Diaper Product Mapping) are classified here — an unmapped product has no category and is excluded from this split, though it's still included in the combined Gross Profit KPI card above.</p>

                                    <div class="equation-row">
                                        <div class="kpi-card"><div class="kpi-t">Napkin Gross Profit</div><div class="kpi-v" style="<?php echo $grand_gross_profit_llp < 0 ? 'color:#dc2626;' : ''; ?>">₹<?php echo inr_format($grand_gross_profit_llp, 2); ?></div></div>
                                        <div class="equation-op">+</div>
                                        <div class="kpi-card"><div class="kpi-t">Diaper Gross Profit</div><div class="kpi-v" style="<?php echo $grand_diaper_gross_profit_llp < 0 ? 'color:#dc2626;' : ''; ?>">₹<?php echo inr_format($grand_diaper_gross_profit_llp, 2); ?></div></div>
                                        <div class="equation-op eq">=</div>
                                        <div class="kpi-card"><div class="kpi-t">Combined Gross Profit</div><div class="kpi-v" style="<?php echo $grand_combined_gross_profit_llp < 0 ? 'color:#dc2626;' : ''; ?>">₹<?php echo inr_format($grand_combined_gross_profit_llp, 2); ?></div></div>
                                    </div>

                                    <div class="equation-row">
                                        <div class="kpi-card"><div class="kpi-t">Combined Gross Profit</div><div class="kpi-v" style="<?php echo $grand_combined_gross_profit_llp < 0 ? 'color:#dc2626;' : ''; ?>">₹<?php echo inr_format($grand_combined_gross_profit_llp, 2); ?></div></div>
                                        <div class="equation-op">&minus;</div>
                                        <div class="kpi-card"><div class="kpi-t">Expense</div><div class="kpi-v">₹<?php echo inr_format($grand_combined_expense_llp, 2); ?></div></div>
                                        <div class="equation-op eq">=</div>
                                        <div class="kpi-card"><div class="kpi-t">Net Profit</div><div class="kpi-v" style="<?php echo $grand_combined_net_profit_llp < 0 ? 'color:#dc2626;' : ''; ?>">₹<?php echo inr_format($grand_combined_net_profit_llp, 2); ?></div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ══ CHANNEL BREAKDOWN (Income to Company scope only) ═ -->
                    <?php if ($scope === 'company'): ?>
                    <div class="row mis-section" id="sec-channels">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Channel Breakdown</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <p class="snote">Every channel below is included in the Total Turnover / Gross Profit figures above.</p>
                                    <table class="mt">
                                        <thead><tr><th>Channel</th><th>Invoices</th><th>Revenue</th><th>Share</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($channel_labels as $key => $label):
                                            $row = $channel_breakdown[$key] ?? ['cnt' => 0, 'rev' => 0.0];
                                            $pct = round($row['rev'] / $channel_total_rev * 100, 1);
                                        ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($label); ?></b></td>
                                                <td><?php echo inr_format($row['cnt'], 0); ?></td>
                                                <td><span class="br">₹<?php echo inr_format($row['rev'], 2); ?></span></td>
                                                <td><div style="display:flex;align-items:center;gap:5px">
                                                    <div class="pbar" style="width:70px"><div class="pf" style="width:<?php echo $pct; ?>%"></div></div>
                                                    <span style="font-size:12px"><?php echo $pct; ?>%</span>
                                                </div></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ══ TREND CHART + ORDER STATUS ══════════════════════ -->
                    <div class="row mis-section" id="sec-trend">
                        <div class="col-xl-8">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Daily Sales Trend</h5></div>
                                <div class="card-body"><div class="chart-box"><canvas id="trendChart"></canvas></div></div>
                            </div>
                        </div>
                        <div class="col-xl-4">
                            <div class="card h-100">
                                <div class="card-header"><h5 class="card-title">Order Status</h5></div>
                                <div class="card-body">
                                    <?php
                                    $os_total_a = $os_paid_a + $os_part_a + $os_unpd_a ?: 1;
                                    $os_paid_pct = round($os_paid_a / $os_total_a * 100, 1);
                                    $os_part_pct = round($os_part_a / $os_total_a * 100, 1);
                                    $os_unpd_pct = round($os_unpd_a / $os_total_a * 100, 1);
                                    ?>
                                    <div class="stackbar">
                                        <div style="width:<?php echo $os_paid_pct; ?>%;background:var(--good)"></div>
                                        <div style="width:<?php echo $os_part_pct; ?>%;background:var(--warning)"></div>
                                        <div style="width:<?php echo $os_unpd_pct; ?>%;background:var(--critical)"></div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="status-row">
                                            <span class="status-label"><span class="status-dot" style="background:var(--good)"></span>Fully Paid</span>
                                            <span class="status-val"><?php echo $os_paid; ?> — ₹<?php echo inr_format($os_paid_a, 0); ?> (<?php echo $os_paid_pct; ?>%)</span>
                                        </div>
                                        <div class="status-row">
                                            <span class="status-label"><span class="status-dot" style="background:var(--warning)"></span>Partially Paid</span>
                                            <span class="status-val"><?php echo $os_part; ?> — ₹<?php echo inr_format($os_part_a, 0); ?> (<?php echo $os_part_pct; ?>%)</span>
                                        </div>
                                        <div class="status-row">
                                            <span class="status-label"><span class="status-dot" style="background:var(--critical)"></span>Unpaid</span>
                                            <span class="status-val"><?php echo $os_unpd; ?> — ₹<?php echo inr_format($os_unpd_a, 0); ?> (<?php echo $os_unpd_pct; ?>%)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ PERIOD BREAKDOWN TABS ════════════════════════════ -->
                    <div class="row mis-section" id="sec-breakdown">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Sales Breakdown by Period</h5></div>
                                <div class="card-body">
                                    <div class="tab-nav" id="ptabs">
                                        <div class="tab-item active" data-tab="daily">Daily</div>
                                        <div class="tab-item" data-tab="weekly">Weekly</div>
                                        <div class="tab-item" data-tab="monthly">Monthly</div>
                                        <div class="tab-item" data-tab="yearly">Yearly</div>
                                    </div>
                                    <?php
                                    function cmp_period_table($data, $id) {
                                        $active = $id === 'daily' ? 'active' : '';
                                        echo "<div class='tab-content {$active}' id='tab-{$id}'>";
                                        if (empty($data)) { echo "<p class='text-muted text-center py-3'>No data.</p></div>"; return; }
                                        $gr = array_sum(array_map(fn($r)=>($r['c']??0)+($r['s']??0)+($r['t']??0)+($r['o']??0), $data)) ?: 1;
                                        echo "<div style='overflow-x:auto'><table class='mt'>";
                                        echo "<thead><tr><th>Period</th><th>Customer</th><th>Shop</th><th>TP</th><th>OT</th><th>Total</th><th>Invoices</th><th>Share</th></tr></thead><tbody>";
                                        foreach ($data as $g => $r) {
                                            $rev = ($r['c']??0)+($r['s']??0)+($r['t']??0)+($r['o']??0);
                                            $cnt = ($r['cc']??0)+($r['sc']??0)+($r['tc']??0)+($r['oc']??0);
                                            $pct = round($rev/$gr*100,1);
                                            echo "<tr>
                                                <td><b>".htmlspecialchars($r['lbl']??$g)."</b></td>
                                                <td>₹".inr_format($r['c']??0, 2)." <small>({$r['cc']})</small></td>
                                                <td>₹".inr_format($r['s']??0, 2)." <small>({$r['sc']})</small></td>
                                                <td>₹".inr_format($r['t']??0, 2)." <small>({$r['tc']})</small></td>
                                                <td>₹".inr_format($r['o']??0, 2)." <small>({$r['oc']})</small></td>
                                                <td><b>₹".inr_format($rev, 2)."</b></td>
                                                <td>{$cnt}</td>
                                                <td><div style='display:flex;align-items:center;gap:6px'>
                                                    <div class='pbar' style='width:80px'><div class='pf' style='width:{$pct}%;background:#2a78d6'></div></div>
                                                    <span style='font-size:12px'>{$pct}%</span></div></td>
                                            </tr>";
                                        }
                                        echo "</tbody></table></div></div>";
                                    }
                                    cmp_period_table($daily_p,'daily');
                                    cmp_period_table($weekly_p,'weekly');
                                    cmp_period_table($monthly_p,'monthly');
                                    cmp_period_table($yearly_p,'yearly');
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ TERRITORY PARTNER PERFORMANCE ═══════════════════ -->
                    <?php if ($scope === 'tp'): ?>
                    <div class="row mis-section" id="sec-tpperf">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Territory Partner Performance (Salesperson View)</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-xl-8">
                                            <div class="chart-box" style="height:220px;"><canvas id="tpChart"></canvas></div>
                                        </div>
                                        <div class="col-xl-4">
                                            <div style="background:#f5f6fa;border-radius:8px;padding:14px;text-align:center;margin-bottom:10px;">
                                                <div style="font-size:11px;color:#888;text-transform:uppercase;font-weight:600">Total Revenue (All TPs)</div>
                                                <div style="font-size:22px;font-weight:700;color:#1a237e">₹<?php echo inr_format($total_achieved_all, 0); ?></div>
                                            </div>
                                            <div style="background:#f5f6fa;border-radius:8px;padding:14px;text-align:center;">
                                                <div style="font-size:11px;color:#888;text-transform:uppercase;font-weight:600">Overall Napkin Achievement</div>
                                                <div style="font-size:22px;font-weight:700;color:<?php echo $overall_pct_all>=100?'#0ca30c':($overall_pct_all>=50?'#9a6b00':'#d03b3b'); ?>">
                                                    <?php echo $overall_pct_all; ?>%
                                                </div>
                                                <div style="font-size:12px;color:#888">Napkin Target: ₹<?php echo inr_format($total_target_all, 0); ?></div>
                                                <div style="font-size:12px;color:#888;margin-top:6px;">Diaper: ₹<?php echo inr_format($total_diaper_achieved_all, 0); ?> (<?php echo $overall_diaper_pct_all; ?>% of total sales)</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="overflow-x:auto">
                                    <table class="mt">
                                        <thead><tr><th>Rank</th><th>TP Name</th><th>TP Code</th><th>Invoices</th><th>Units</th><th>Napkin Revenue</th><th>Napkin Target</th><th>Napkin Achievement</th><th>Napkin Gap</th><th>Diaper Revenue</th><th>Diaper Share</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($tp_perf as $i => $tp): ?>
                                            <?php
                                            $pct = $tp['target']>0 ? min(round($tp['napkin_revenue']/$tp['target']*100,1),999) : 0;
                                            $bc  = $pct>=100?'#0ca30c':($pct>=50?'#fab219':'#d03b3b');
                                            $gap = (float)$tp['target'] - (float)$tp['napkin_revenue'];
                                            $rk_class = $i==0?'rank-1':($i==1?'rank-2':($i==2?'rank-3':''));
                                            ?>
                                            <tr>
                                                <td class="<?php echo $rk_class; ?>"><?php echo $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':($i+1))); ?></td>
                                                <td><b><?php echo htmlspecialchars($tp['tp_name']); ?></b></td>
                                                <td><span class="tp-tag"><?php echo htmlspecialchars($tp['tp_code']); ?></span></td>
                                                <td><?php echo inr_format((int)$tp['inv_cnt'], 0); ?></td>
                                                <td><span class="bq"><?php echo inr_format((int)$tp['units'], 0); ?></span></td>
                                                <td>
                                                    <b>₹<?php echo inr_format($tp['napkin_revenue'], 2); ?></b>
                                                    <div class="pbar mt-1"><div class="pf" style="width:<?php echo round($tp['napkin_revenue']/$max_tp_rev*100,1); ?>%;background:#2a78d6"></div></div>
                                                </td>
                                                <td>₹<?php echo inr_format($tp['target'], 0); ?></td>
                                                <td>
                                                    <div style="display:flex;align-items:center;gap:5px">
                                                        <div class="pbar" style="width:80px"><div class="pf" style="width:<?php echo min($pct,100); ?>%;background:<?php echo $bc; ?>"></div></div>
                                                        <span style="font-size:13px;font-weight:700;color:<?php echo $bc; ?>"><?php echo $pct; ?>%</span>
                                                    </div>
                                                </td>
                                                <td style="color:<?php echo $gap>0?'#d03b3b':'#0ca30c'; ?>">
                                                    <?php echo $gap>0?'−':'+'?>₹<?php echo inr_format(abs($gap), 0); ?>
                                                </td>
                                                <td><b>₹<?php echo inr_format($tp['diaper_revenue'], 2); ?></b></td>
                                                <td><span style="font-size:13px;font-weight:700;color:#7c3aed;"><?php echo $tp['diaper_pct']; ?>%</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                    <div style="font-size:11px;color:#888;margin-top:6px;">Diaper has no separate target yet — "Diaper Share" is that revenue as a % of the TP's own total sales, not an achievement figure.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ══ PRODUCT-WISE SALES ════════════════════════════════ -->
                    <div class="row mis-section" id="sec-products">
                        <div class="col-xl-7">
                            <div class="card h-100">
                                <div class="card-header"><h5 class="card-title">Product-wise Sales</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($product_sales)): ?>
                                        <p class="text-muted text-center py-3">No data.</p>
                                    <?php else: ?>
                                    <table class="mt">
                                        <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Revenue</th><th>Returned Qty</th><th>Returned Amount</th><th>Total Qty</th><th>Share</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($product_sales as $i => $p): ?>
                                            <?php
                                            $pct = $grand_qty>0 ? round($p['total_qty']/$grand_qty*100,1) : 0;
                                            $bc  = '#2a78d6';
                                            $ret = $returns_by_pid[(int)$p['pid']] ?? ['qty' => 0, 'amt' => 0];
                                            $net_qty = (int)$p['total_qty'] - (int)$ret['qty'];
                                            ?>
                                            <tr>
                                                <td><?php echo $i+1; ?></td>
                                                <td><b><?php echo htmlspecialchars($p['productName']); ?></b></td>
                                                <td><span class="bq"><?php echo inr_format((int)$p['total_qty'], 0); ?></span></td>
                                                <td><span class="br">₹<?php echo inr_format($p['total_rev'], 2); ?></span></td>
                                                <td><?php echo inr_format((int)$ret['qty'], 0); ?></td>
                                                <td>₹<?php echo inr_format($ret['amt'], 2); ?></td>
                                                <td><b><?php echo inr_format($net_qty, 0); ?></b></td>
                                                <td><div style="display:flex;align-items:center;gap:5px">
                                                    <div class="pbar" style="width:70px"><div class="pf" style="width:<?php echo $pct; ?>%;background:<?php echo $bc; ?>"></div></div>
                                                    <span style="font-size:12px"><?php echo $pct; ?>%</span>
                                                </div></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-5">
                            <div class="card h-100">
                                <div class="card-header"><h5 class="card-title">Product Mix (Top 8)</h5></div>
                                <div class="card-body">
                                    <?php if (empty($product_sales)): ?>
                                        <p class="text-muted text-center py-3">No data.</p>
                                    <?php else: ?>
                                    <div class="chart-box"><canvas id="productChart"></canvas></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ STATE / DISTRICT ══════════════════════════════════ -->
                    <div class="row mis-section" id="sec-geo">
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">State-wise Sales</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <p class="snote">Shop invoices (by location), TP invoices (by territory), and OT sales (by state_id) — direct customer invoices have no geographic data.</p>
                                    <?php if (empty($state_sales)): ?>
                                        <p class="text-muted text-center py-3">No geographic data.</p>
                                    <?php else:
                                        $ts_rev = array_sum(array_column($state_sales,'revenue')) ?: 1;
                                    ?>
                                    <table class="mt">
                                        <thead><tr><th>State</th><th>Invoices</th><th>Revenue</th><th>Share</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($state_sales as $s): ?>
                                            <?php $pct = round($s['revenue']/$ts_rev*100,1); ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($s['state_name']); ?></b></td>
                                                <td><?php echo $s['cnt']; ?></td>
                                                <td><span class="br">₹<?php echo inr_format($s['revenue'], 2); ?></span></td>
                                                <td><div style="display:flex;align-items:center;gap:5px">
                                                    <div class="pbar" style="width:70px"><div class="pf" style="width:<?php echo $pct; ?>%;background:#1baf7a"></div></div>
                                                    <span style="font-size:12px"><?php echo $pct; ?>%</span>
                                                </div></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">District-wise Sales</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($district_sales)): ?>
                                        <p class="text-muted text-center py-3">No district data.</p>
                                    <?php else:
                                        $td_rev = array_sum(array_column($district_sales,'revenue')) ?: 1;
                                    ?>
                                    <table class="mt">
                                        <thead><tr><th>District</th><th>Invoices</th><th>Revenue</th><th>Share</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($district_sales as $d): ?>
                                            <?php $pct = round($d['revenue']/$td_rev*100,1); ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($d['district_name']); ?></b></td>
                                                <td><?php echo $d['cnt']; ?></td>
                                                <td><span class="br">₹<?php echo inr_format($d['revenue'], 2); ?></span></td>
                                                <td><div style="display:flex;align-items:center;gap:5px">
                                                    <div class="pbar" style="width:70px"><div class="pf" style="width:<?php echo $pct; ?>%;background:#4a3aa7"></div></div>
                                                    <span style="font-size:12px"><?php echo $pct; ?>%</span>
                                                </div></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ TOP SHOPS & DISTRIBUTORS ═════════════════════════ -->
                    <div class="row mis-section" id="sec-topcustomers">
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Top 10 Shops by Revenue</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($top_shops)): ?>
                                        <p class="text-muted text-center py-3">No shop data.</p>
                                    <?php else:
                                        $msr = (float)$top_shops[0]['revenue'] ?: 1;
                                    ?>
                                    <table class="mt">
                                        <thead><tr><th>#</th><th>Shop</th><th>Invoices</th><th>Revenue</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($top_shops as $i => $s): ?>
                                            <tr>
                                                <td><?php echo $i+1; ?></td>
                                                <td><b><?php echo htmlspecialchars($s['shop_name']); ?></b></td>
                                                <td><?php echo $s['inv_cnt']; ?></td>
                                                <td>
                                                    <span class="br">₹<?php echo inr_format($s['revenue'], 2); ?></span>
                                                    <div class="pbar mt-1"><div class="pf" style="width:<?php echo round($s['revenue']/$msr*100,1); ?>%;background:#eb6834"></div></div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Top 10 Distributors by Revenue</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($top_distributors)): ?>
                                        <p class="text-muted text-center py-3">No distributor data.</p>
                                    <?php else:
                                        $mcr = (float)$top_distributors[0]['revenue'] ?: 1;
                                    ?>
                                    <table class="mt">
                                        <thead><tr><th>#</th><th>Distributor</th><th>Type</th><th>Invoices</th><th>Revenue</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($top_distributors as $i => $c): ?>
                                            <tr>
                                                <td><?php echo $i+1; ?></td>
                                                <td><b><?php echo htmlspecialchars($c['dist_name']); ?></b></td>
                                                <td><?php echo htmlspecialchars($c['dist_type']); ?></td>
                                                <td><?php echo $c['inv_cnt']; ?></td>
                                                <td>
                                                    <span class="br">₹<?php echo inr_format($c['revenue'], 2); ?></span>
                                                    <div class="pbar mt-1"><div class="pf" style="width:<?php echo round($c['revenue']/$mcr*100,1); ?>%;background:#2a78d6"></div></div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ 6-MONTH GROWTH TREND ══════════════════════════════ -->
                    <div class="row mis-section" id="sec-growth">
                        <div class="col-xl-7">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">6-Month Growth Trend</h5></div>
                                <div class="card-body"><div class="chart-box"><canvas id="growthChart"></canvas></div></div>
                            </div>
                        </div>
                        <div class="col-xl-5">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Month-over-Month</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($six_months)): ?>
                                        <p class="text-muted text-center py-3">No data.</p>
                                    <?php else: ?>
                                    <table class="mt">
                                        <thead><tr><th>Month</th><th>Revenue</th><th>Invoices</th><th>Growth</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($six_months as $m): ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($m['lbl']); ?></b></td>
                                                <td>₹<?php echo inr_format($m['total_rev'], 0); ?></td>
                                                <td><?php echo (int)$m['total_cnt']; ?></td>
                                                <td>
                                                    <?php if ($m['growth']===null): ?>
                                                        <span style="color:#888">—</span>
                                                    <?php elseif ($m['growth']>=0): ?>
                                                        <span class="gp">▲ <?php echo $m['growth']; ?>%</span>
                                                    <?php else: ?>
                                                        <span class="gn">▼ <?php echo abs($m['growth']); ?>%</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ RETURNS & CANCELLATIONS ══════════════════════════ -->
                    <div class="row mis-section" id="sec-returns">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Returns &amp; Credit Notes
                                        <span class="sbadge bu" style="margin-left:8px;">
                                            <?php echo $total_returns; ?> returns — ₹<?php echo inr_format($total_return_amt, 2); ?>
                                        </span>
                                    </h5>
                                </div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($returns_list)): ?>
                                        <p class="text-muted text-center py-3">No returns in this period.</p>
                                    <?php else:
                                        $returns_page_size = 25;
                                    ?>
                                    <table class="mt" id="returnsTable">
                                        <thead><tr><th>Return ID</th><th>Invoice No.</th><th>TP</th><th>From</th><th>Date</th><th>Amount</th><th>Status</th><th>Detail</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($returns_list as $i => $r): ?>
                                            <tr class="returns-row"<?php echo $i >= $returns_page_size ? ' style="display:none;"' : ''; ?>>
                                                <td><small><?php echo htmlspecialchars($r['returnid']); ?></small></td>
                                                <td><?php echo htmlspecialchars($r['inv_number']); ?></td>
                                                <td><?php echo htmlspecialchars($r['tp_name'] ?? '—'); ?></td>
                                                <td><?php echo htmlspecialchars($r['from_label']); ?></td>
                                                <td><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                                                <td><span class="br">₹<?php echo inr_format($r['amount'], 2); ?></span></td>
                                                <td>
                                                    <?php if ($r['status'] === null): ?>
                                                    <span class="sbadge bp">Completed</span>
                                                    <?php elseif ($r['status']==='pending'): ?>
                                                    <span class="sbadge bpa">Pending</span>
                                                    <?php else: ?>
                                                    <span class="sbadge bp"><?php echo ucfirst($r['status']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php if ($r['detail_url']): ?><a href="<?php echo htmlspecialchars($r['detail_url']); ?>">View</a><?php else: ?>—<?php endif; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php if (count($returns_list) > $returns_page_size): ?>
                                    <div style="text-align:center;margin-top:14px;">
                                        <button type="button" id="returnsLoadMoreBtn" class="preset-btn" data-page-size="<?php echo $returns_page_size; ?>" style="padding:7px 20px;">
                                            Load More (<?php echo count($returns_list) - $returns_page_size; ?> more)
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    </div><!-- /#mainPanel -->

                    <!-- ══ TOTAL FLOW PANEL (zone-wise BDM performance) ═════
                         Hidden until the "Total Flow" toggle is clicked.
                         Empty on page load — filled in by JS via a lazy
                         fetch to dashboard-total-flow-data.php the first
                         time this panel is shown. -->
                    <div id="totalFlowPanel" style="display:none;">
                        <div class="tf-banner">
                            <div class="tf-banner-title">FEMI9 LLP – TAMILNADU BDM DASHBOARD</div>
                            <div class="tf-banner-date" id="tfDateLabel">Date : —</div>
                        </div>

                        <div id="tfLoading" style="padding:50px 0;text-align:center;color:var(--text-secondary);">
                            <i class="material-icons-outlined" style="font-size:28px;vertical-align:middle;">hourglass_empty</i>
                            &nbsp;Loading Total Flow dashboard…
                        </div>
                        <div id="tfError" style="display:none;color:var(--critical);padding:20px;"></div>

                        <div id="tfContent" style="display:none;">
                            <!-- KPI row -->
                            <div class="row" id="tfKpiRow"></div>

                            <div class="row">
                                <!-- Sidebar: Business Development Team -->
                                <div class="col-lg-3 mb-3">
                                    <div class="kpi-card tf-sidebar">
                                        <h3 class="tf-panel-title">Business Development Team</h3>
                                        <ul class="tf-team-list" id="tfTeamList"></ul>
                                        <div class="tf-team-overview">
                                            <h4>Team Overview</h4>
                                            <div id="tfTeamOverview"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Two zone-wise summary tables -->
                                <div class="col-lg-9">
                                    <div class="row">
                                        <div class="col-lg-6 mb-3">
                                            <div class="kpi-card">
                                                <h3 class="tf-panel-title">Zone Wise – Territory Partner Summary</h3>
                                                <div style="overflow-x:auto;">
                                                <table class="mt">
                                                    <thead><tr>
                                                        <th>Zone</th><th>Total Firkas</th><th>Filled Firkas</th><th>Active / Inactive Firkas</th><th>Active TPs</th><th>Vacant Firkas</th>
                                                    </tr></thead>
                                                    <tbody id="tfTpTableBody"></tbody>
                                                </table>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6 mb-3">
                                            <div class="kpi-card">
                                                <h3 class="tf-panel-title">Zone Wise – Channel Partner Divisions Summary</h3>
                                                <div style="overflow-x:auto;">
                                                <table class="mt">
                                                    <thead><tr>
                                                        <th>Zone</th><th>Total Districts</th><th>Total Divisions</th><th>Filled Divisions</th><th>Vacant Divisions</th>
                                                    </tr></thead>
                                                    <tbody id="tfCpTableBody"></tbody>
                                                </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Charts -->
                            <div class="row">
                                <div class="col-lg-4 mb-3">
                                    <div class="kpi-card">
                                        <h3 class="tf-panel-title">Vacant Business Opportunity (₹)</h3>
                                        <div style="height:250px;"><canvas id="tfVacantChart"></canvas></div>
                                        <div class="tf-callout" id="tfVacantTotal"></div>
                                    </div>
                                </div>
                                <div class="col-lg-4 mb-3">
                                    <div class="kpi-card">
                                        <h3 class="tf-panel-title">Firkas Status (By Count)</h3>
                                        <div style="height:200px;position:relative;">
                                            <canvas id="tfFirkaDonut"></canvas>
                                            <div class="tf-donut-center" id="tfFirkaDonutCenter"></div>
                                        </div>
                                        <div class="tf-legend" id="tfFirkaLegend"></div>
                                    </div>
                                </div>
                                <div class="col-lg-4 mb-3">
                                    <div class="kpi-card">
                                        <h3 class="tf-panel-title">Channel Partner Divisions Status (By Count)</h3>
                                        <div style="height:200px;position:relative;">
                                            <canvas id="tfDivisionDonut"></canvas>
                                            <div class="tf-donut-center" id="tfDivisionDonutCenter"></div>
                                        </div>
                                        <div class="tf-legend" id="tfDivisionLegend"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Key Insights -->
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <div class="kpi-card">
                                        <h3 class="tf-panel-title">Key Insights</h3>
                                        <ul class="tf-insights" id="tfInsights"></ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- /#totalFlowPanel -->

                </div><!-- container-fluid -->
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = 'Poppins, sans-serif';
Chart.defaults.font.size   = 12;

// Tab switching
document.querySelectorAll('.tab-item').forEach(function(t) {
    t.addEventListener('click', function() {
        document.querySelectorAll('#ptabs .tab-item').forEach(x => x.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(x => x.classList.remove('active'));
        t.classList.add('active');
        var el = document.getElementById('tab-' + t.dataset.tab);
        if (el) el.classList.add('active');
    });
});

// Returns & Credit Notes: "Load More" reveals additional pre-rendered rows
// client-side, page size at a time — no AJAX round-trip since the full list
// is already server-rendered, just display:none beyond the first page.
(function() {
    var btn = document.getElementById('returnsLoadMoreBtn');
    if (!btn) return;
    var pageSize = parseInt(btn.dataset.pageSize, 10) || 25;
    var shown = pageSize;
    var rows = document.querySelectorAll('#returnsTable .returns-row');
    btn.addEventListener('click', function() {
        var next = Math.min(shown + pageSize, rows.length);
        for (var i = shown; i < next; i++) rows[i].style.display = '';
        shown = next;
        var remaining = rows.length - shown;
        if (remaining > 0) {
            btn.textContent = 'Load More (' + remaining + ' more)';
        } else {
            btn.style.display = 'none';
        }
    });
})();

// Section nav: smooth scroll + scrollspy active-state
(function() {
    var navLinks = Array.from(document.querySelectorAll('#sectionNav a'));
    if (!navLinks.length) return;
    var sections = navLinks.map(a => document.querySelector(a.getAttribute('href'))).filter(Boolean);

    navLinks.forEach(function(a) {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.querySelector(a.getAttribute('href'));
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    var onScroll = function() {
        var pos = window.scrollY + 110;
        var current = sections[0];
        sections.forEach(function(s) { if (s.offsetTop <= pos) current = s; });
        navLinks.forEach(function(a) {
            a.classList.toggle('active', current && a.getAttribute('href') === '#' + current.id);
        });
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
})();

// 1. Daily Trend
(function() {
    var ctx = document.getElementById('trendChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo $j_labels; ?>,
            datasets: [
                { label: 'Customer', data: <?php echo $j_cust; ?>,  borderColor:'#2a78d6', backgroundColor:'rgba(42,120,214,.10)', borderWidth:2, tension:.3, fill:true, pointRadius:3, pointBackgroundColor:'#2a78d6' },
                { label: 'Shop',     data: <?php echo $j_shop; ?>,  borderColor:'#1baf7a', backgroundColor:'rgba(27,175,122,.10)', borderWidth:2, tension:.3, fill:true, pointRadius:3, pointBackgroundColor:'#1baf7a' },
                { label: 'Territory Partner', data: <?php echo $j_tp; ?>,  borderColor:'#4a3aa7', backgroundColor:'rgba(74,58,167,.10)', borderWidth:2, tension:.3, fill:true, pointRadius:3, pointBackgroundColor:'#4a3aa7' }<?php if ($scope === 'company'): ?>,
                { label: 'OT Channel', data: <?php echo $j_ot; ?>,  borderColor:'#d68f2a', backgroundColor:'rgba(214,143,42,.10)', borderWidth:2, tension:.3, fill:true, pointRadius:3, pointBackgroundColor:'#d68f2a' }<?php endif; ?>
            ]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'top'}},
            scales:{y:{grid:{color:'#e1e0d9'},ticks:{callback:v=>'₹'+(v/1000).toFixed(0)+'k'}}, x:{grid:{display:false}}} }
    });
})();

// 3. Product Mix — horizontal bar (magnitude, single hue; a donut would need
// re-coloring 8 nominal categories, which the palette reserves for identity).
(function() {
    var ctx = document.getElementById('productChart');
    if (!ctx) return;
    var labels = <?php echo $j_plabels; ?>.slice(0,8).map(l => l.length > 28 ? l.slice(0,26)+'…' : l);
    var data   = <?php echo $j_pqty; ?>.slice(0,8);
    new Chart(ctx, {
        type: 'bar',
        data: { labels: labels, datasets: [{ data: data, backgroundColor: '#2a78d6', borderRadius: 4, maxBarThickness: 20 }] },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: '#e1e0d9' }, ticks: { callback: v => v.toLocaleString('en-IN') } },
                y: { grid: { display: false } }
            }
        }
    });
})();

// 4. TP Revenue vs Target (Grouped Bar)
(function() {
    var ctx = document.getElementById('tpChart');
    if (!ctx) return;
    var labels = <?php echo $j_tplabels; ?>;
    var revs   = <?php echo $j_tprevs; ?>;
    var tgts   = <?php echo $j_tptgts; ?>;
    new Chart(ctx, {
        type:'bar',
        data:{
            labels:labels,
            datasets:[
                { label:'Napkin Revenue',  data:revs, backgroundColor:'#2a78d6', borderRadius:4, maxBarThickness:24 },
                { label:'Napkin Target',   data:tgts, backgroundColor:'#c3c2b7', borderRadius:4, maxBarThickness:24 }
            ]
        },
        options:{responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'top'}},
            scales:{y:{grid:{color:'#e1e0d9'},ticks:{callback:v=>'₹'+(v/1000).toFixed(0)+'k'}}, x:{grid:{display:false}}} }
    });
})();

// 5. 6-Month Growth
(function() {
    var ctx = document.getElementById('growthChart');
    if (!ctx) return;
    new Chart(ctx, {
        type:'bar',
        data:{ labels:<?php echo $j_glabels; ?>, datasets:[{label:'Revenue', data:<?php echo $j_gvals; ?>, backgroundColor:'#2a78d6', borderRadius:6, maxBarThickness:36}] },
        options:{responsive:true, maintainAspectRatio:false,
            plugins:{legend:{display:false}},
            scales:{y:{grid:{color:'#e1e0d9'},ticks:{callback:v=>'₹'+(v/1000).toFixed(0)+'k'}}, x:{grid:{display:false}}} }
    });
})();

// ── Main / Total Flow toggle ────────────────────────────────────────────
// Pure client-side show/hide — no page reload. Total Flow data is fetched
// lazily (only the first time the tab is clicked) and cached in tfCache so
// switching back and forth afterwards never re-fetches.
(function() {
    var btnMain = document.getElementById('btnDashMain');
    var btnFlow = document.getElementById('btnDashTotalFlow');
    var mainPanel = document.getElementById('mainPanel');
    var flowPanel = document.getElementById('totalFlowPanel');
    if (!btnMain || !btnFlow || !mainPanel || !flowPanel) return;

    var tfCache = null;
    var tfCharts = {};

    function fmtInr(n) {
        n = Math.round(n || 0);
        return '₹' + n.toLocaleString('en-IN');
    }

    function tfKpiCard(accent, tint, icon, label, value, sub) {
        return '<div class="col-xl-3 col-lg-4 col-md-6 col-6 mb-3">' +
            '<div class="kpi-card" style="--kpi-accent:' + accent + ';--kpi-tint:' + tint + ';">' +
            '<i class="material-icons-outlined kpi-ico">' + icon + '</i>' +
            '<div class="kpi-t">' + label + '</div>' +
            '<div class="kpi-v">' + value + '</div>' +
            (sub ? '<div class="kpi-s">' + sub + '</div>' : '') +
            '</div></div>';
    }

    function renderTotalFlow(d) {
        document.getElementById('tfDateLabel').textContent = 'Date : ' + d.date_label;

        // KPI cards
        var k = d.kpis;
        var kpiHtml = '';
        kpiHtml += tfKpiCard('var(--blue)','var(--blue-tint)','map','Total Firkas', k.total_firkas.toLocaleString('en-IN'), 'Business Value ' + fmtInr(k.total_firkas_value));
        kpiHtml += tfKpiCard('var(--good)','var(--good-tint)','check_circle','Active Firkas', k.active_firkas.toLocaleString('en-IN'), 'Business Value ' + fmtInr(k.active_firkas_value));
        kpiHtml += tfKpiCard('#eab308','#fef9c3','pause_circle','Inactive Firkas', k.inactive_firkas.toLocaleString('en-IN'), 'Business Value ' + fmtInr(k.inactive_firkas_value));
        kpiHtml += tfKpiCard('var(--good)','var(--good-tint)','check_circle','Filled Firkas', k.filled_firkas.toLocaleString('en-IN') + ' (' + k.filled_firkas_pct + '%)', 'Business Value ' + fmtInr(k.filled_firkas_value));
        kpiHtml += tfKpiCard('var(--critical)','var(--critical-tint)','error_outline','Vacant Firkas', k.vacant_firkas.toLocaleString('en-IN') + ' (' + k.vacant_firkas_pct + '%)', 'Business Value ' + fmtInr(k.vacant_firkas_value));
        kpiHtml += tfKpiCard('var(--violet)','var(--violet-tint)','people','Active Territory Partners', k.active_tps.toLocaleString('en-IN'), '');
        kpiHtml += tfKpiCard('var(--aqua)','var(--aqua-tint)','account_tree','Total CP Divisions', k.total_divisions.toLocaleString('en-IN'), 'Total Districts: ' + k.total_districts);
        kpiHtml += tfKpiCard('var(--good)','var(--good-tint)','check_circle','Filled CP Divisions', k.filled_divisions.toLocaleString('en-IN') + ' (' + k.filled_divisions_pct + '%)', 'Business Value ' + fmtInr(k.filled_divisions_value));
        kpiHtml += tfKpiCard('var(--critical)','var(--critical-tint)','error_outline','Vacant CP Divisions', k.vacant_divisions.toLocaleString('en-IN') + ' (' + k.vacant_divisions_pct + '%)', 'Business Value ' + fmtInr(k.vacant_divisions_value));
        kpiHtml += tfKpiCard('var(--orange)','var(--orange-tint)','groups','Active Channel Partners', k.active_cps.toLocaleString('en-IN'), '');
        document.getElementById('tfKpiRow').innerHTML = kpiHtml;

        // Sidebar: team list
        var teamHtml = '';
        d.team.forEach(function(t) {
            teamHtml += '<li><b>' + t.zone.toUpperCase() + '</b>' + t.bdm_name + '</li>';
        });
        document.getElementById('tfTeamList').innerHTML = teamHtml;

        var ov = d.team_overview;
        var ovRows = [
            ['Zones', ov.zones], ['Team Members', ov.members],
            ['Total Firkas', ov.total_firkas.toLocaleString('en-IN')],
            ['Active TPs', ov.active_tps.toLocaleString('en-IN')],
            ['Total CP Divisions', ov.total_divisions.toLocaleString('en-IN')],
            ['Active CPs', ov.active_cps.toLocaleString('en-IN')],
        ];
        document.getElementById('tfTeamOverview').innerHTML = ovRows.map(function(r) {
            return '<div class="tf-ov-row"><span>' + r[0] + '</span><b>' + r[1] + '</b></div>';
        }).join('');

        // TP summary table
        var tpBody = '';
        d.tp_table.forEach(function(r) {
            tpBody += '<tr>' +
                '<td>' + r.zone + '<br><span style="color:var(--text-secondary);font-size:11px;">' + r.bdm_name + '</span></td>' +
                '<td>' + r.total_firkas + '<br><span style="color:var(--text-secondary);font-size:11px;">' + fmtInr(r.total_firkas_value) + '</span></td>' +
                '<td>' + r.filled_firkas + '<br><span style="color:var(--text-secondary);font-size:11px;">' + fmtInr(r.filled_firkas_value) + '</span></td>' +
                '<td><span style="color:var(--good);">' + r.active_firkas + ' active</span><br><span style="color:var(--critical);font-size:11px;">' + r.inactive_firkas + ' inactive</span></td>' +
                '<td>' + r.active_tps + '</td>' +
                '<td>' + r.vacant_firkas + '<br><span style="color:var(--text-secondary);font-size:11px;">' + fmtInr(r.vacant_firkas_value) + '</span></td>' +
                '</tr>';
        });
        var tt = d.tp_totals;
        tpBody += '<tr class="tf-table-total">' +
            '<td>TOTAL</td>' +
            '<td>' + tt.total_firkas + '<br><span style="font-size:11px;">' + fmtInr(tt.total_firkas_value) + '</span></td>' +
            '<td>' + tt.filled_firkas + '<br><span style="font-size:11px;">' + fmtInr(tt.filled_firkas_value) + '</span></td>' +
            '<td>' + tt.active_firkas + ' active<br>' + tt.inactive_firkas + ' inactive</td>' +
            '<td>' + tt.active_tps + '</td>' +
            '<td>' + tt.vacant_firkas + '<br><span style="font-size:11px;">' + fmtInr(tt.vacant_firkas_value) + '</span></td>' +
            '</tr>';
        document.getElementById('tfTpTableBody').innerHTML = tpBody;

        // CP summary table
        var cpBody = '';
        d.cp_table.forEach(function(r) {
            cpBody += '<tr>' +
                '<td>' + r.zone + '<br><span style="color:var(--text-secondary);font-size:11px;">' + r.bdm_name + '</span></td>' +
                '<td>' + r.total_districts + '</td>' +
                '<td>' + r.total_divisions + '</td>' +
                '<td>' + r.filled_divisions + '<br><span style="color:var(--text-secondary);font-size:11px;">' + fmtInr(r.filled_divisions_value) + '</span></td>' +
                '<td>' + r.vacant_divisions + '<br><span style="color:var(--text-secondary);font-size:11px;">' + fmtInr(r.vacant_divisions_value) + '</span></td>' +
                '</tr>';
        });
        var ct = d.cp_totals;
        cpBody += '<tr class="tf-table-total">' +
            '<td>TOTAL</td>' +
            '<td>' + ct.total_districts + '</td>' +
            '<td>' + ct.total_divisions + '</td>' +
            '<td>' + ct.filled_divisions + '<br><span style="font-size:11px;">' + fmtInr(ct.filled_divisions_value) + '</span></td>' +
            '<td>' + ct.vacant_divisions + '<br><span style="font-size:11px;">' + fmtInr(ct.vacant_divisions_value) + '</span></td>' +
            '</tr>';
        document.getElementById('tfCpTableBody').innerHTML = cpBody;

        // Charts
        var vc = d.chart_vacant_business;
        var vctx = document.getElementById('tfVacantChart');
        if (vctx) {
            tfCharts.vacant = new Chart(vctx, {
                type: 'bar',
                data: { labels: vc.labels, datasets: [{ data: vc.data, backgroundColor: '#d03b3b', borderRadius: 4, maxBarThickness: 22 }] },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: '#e1e0d9' }, ticks: { callback: v => '₹' + (v/1000).toFixed(0) + 'k' } },
                        y: { grid: { display: false } }
                    }
                }
            });
        }
        document.getElementById('tfVacantTotal').textContent = 'TOTAL VACANT BUSINESS POTENTIAL: ' + fmtInr(vc.total);

        var fs = d.chart_firka_status;
        var fctx = document.getElementById('tfFirkaDonut');
        if (fctx) {
            tfCharts.firka = new Chart(fctx, {
                type: 'doughnut',
                data: { labels: ['Filled', 'Vacant'], datasets: [{ data: [fs.filled, fs.vacant], backgroundColor: ['#0ca30c', '#d03b3b'], borderWidth: 0 }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false } } }
            });
        }
        document.getElementById('tfFirkaDonutCenter').textContent = (fs.filled + fs.vacant).toLocaleString('en-IN');
        document.getElementById('tfFirkaLegend').innerHTML =
            '<span><span class="dot" style="background:#0ca30c;"></span>Filled ' + fs.filled + '</span>' +
            '<span><span class="dot" style="background:#d03b3b;"></span>Vacant ' + fs.vacant + '</span>';

        var ds = d.chart_division_status;
        var dctx = document.getElementById('tfDivisionDonut');
        if (dctx) {
            tfCharts.division = new Chart(dctx, {
                type: 'doughnut',
                data: { labels: ['Filled', 'Vacant'], datasets: [{ data: [ds.filled, ds.vacant], backgroundColor: ['#0ca30c', '#d03b3b'], borderWidth: 0 }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false } } }
            });
        }
        document.getElementById('tfDivisionDonutCenter').textContent = (ds.filled + ds.vacant).toLocaleString('en-IN');
        document.getElementById('tfDivisionLegend').innerHTML =
            '<span><span class="dot" style="background:#0ca30c;"></span>Filled ' + ds.filled + '</span>' +
            '<span><span class="dot" style="background:#d03b3b;"></span>Vacant ' + ds.vacant + '</span>';

        // Insights
        document.getElementById('tfInsights').innerHTML = d.insights.map(function(i) { return '<li>' + i + '</li>'; }).join('');
    }

    function loadTotalFlow() {
        var loading = document.getElementById('tfLoading');
        var errorEl = document.getElementById('tfError');
        var content = document.getElementById('tfContent');
        loading.style.display = '';
        errorEl.style.display = 'none';
        content.style.display = 'none';

        fetch('dashboard-total-flow-data.php')
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.error) throw new Error(d.error);
                tfCache = d;
                renderTotalFlow(d);
                loading.style.display = 'none';
                content.style.display = '';
            })
            .catch(function(e) {
                loading.style.display = 'none';
                errorEl.style.display = '';
                errorEl.textContent = 'Could not load Total Flow dashboard. Please try again.';
            });
    }

    function showView(view) {
        if (view === 'totalflow') {
            btnFlow.classList.add('active');
            btnMain.classList.remove('active');
            mainPanel.style.display = 'none';
            flowPanel.style.display = '';
            if (!tfCache) loadTotalFlow();
        } else {
            btnMain.classList.add('active');
            btnFlow.classList.remove('active');
            flowPanel.style.display = 'none';
            mainPanel.style.display = '';
        }
    }

    btnMain.addEventListener('click', function() { showView('main'); });
    btnFlow.addEventListener('click', function() { showView('totalflow'); });
})();
</script>
</body>
</html>
