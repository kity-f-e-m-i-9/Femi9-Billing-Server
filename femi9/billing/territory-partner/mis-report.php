<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(E_ALL);          // TEMP DEBUG — set back to 0 once the 500 is diagnosed
ini_set('display_errors', '1');  // TEMP DEBUG
date_default_timezone_set("Asia/Kolkata");

// ── Date range filter ──────────────────────────────────────────────────────
$preset = $_GET['preset'] ?? 'month';
$today  = date('Y-m-d');

switch ($preset) {
    case 'today':
        $default_from = $today; $default_to = $today; break;
    case 'week':
        $default_from = date('Y-m-d', strtotime('monday this week'));
        $default_to   = date('Y-m-d', strtotime('sunday this week')); break;
    case 'year':
        $default_from = date('Y-01-01'); $default_to = date('Y-12-31'); break;
    default:
        $default_from = date('Y-m-01'); $default_to = date('Y-m-t');
}

$from = isset($_GET['from']) && $_GET['from'] ? $_GET['from'] : $default_from;
$to   = isset($_GET['to'])   && $_GET['to']   ? $_GET['to']   : $default_to;
$from = date('Y-m-d', strtotime($from));
$to   = date('Y-m-d', strtotime($to));

$uid   = (int)$Login_user_IDvl;
$utype = 'territory_partner';

// Previous period (same duration shifted back)
$days_diff    = (strtotime($to) - strtotime($from)) / 86400;
$prev_from    = date('Y-m-d', strtotime($from) - ($days_diff + 1) * 86400);
$prev_to      = date('Y-m-d', strtotime($from) - 86400);

// Helper: run a prepared statement with 'si' or 'ssi' etc.
function mis_q($db, $sql, $types, $params) {
    $s = $db->prepare($sql);
    if (!$s) return null;
    $s->bind_param($types, ...$params);
    $s->execute();
    $r = $s->get_result();
    $s->close();
    return $r;
}
function mis_val($db, $sql, $types, $params) {
    $r = mis_q($db, $sql, $types, $params);
    return $r ? ($r->fetch_row()[0] ?? 0) : 0;
}
function mis_row($db, $sql, $types, $params) {
    $r = mis_q($db, $sql, $types, $params);
    return $r ? ($r->fetch_assoc() ?? []) : [];
}
function mis_all($db, $sql, $types, $params) {
    $r = mis_q($db, $sql, $types, $params);
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

// ═══════════════════════════════════════════════════════════════════════════
// 1. KPI SUMMARY — current period
// ═══════════════════════════════════════════════════════════════════════════

// Customer invoices
$cust_row = mis_row($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total),0) revenue
     FROM invoice WHERE user_id=? AND user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?",
    'isss', [$uid, $utype, $from, $to]);

// Shop invoices
$shop_row = mis_row($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total),0) revenue
     FROM user_invoice WHERE from_user_id=? AND from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?",
    'isss', [$uid, $utype, $from, $to]);

$total_invoices = (int)$cust_row['cnt'] + (int)$shop_row['cnt'];
$total_revenue  = (float)$cust_row['revenue'] + (float)$shop_row['revenue'];

// Units sold
$cust_units = (int)mis_val($db_conn,
    "SELECT COALESCE(SUM(ii.qty),0) FROM invoice_items ii
     JOIN invoice i ON i.inv_id = ii.inv_id
     WHERE i.user_id=? AND i.user_type=? AND i.date BETWEEN ? AND ?",
    'isss', [$uid, $utype, $from, $to]);
$shop_units = (int)mis_val($db_conn,
    "SELECT COALESCE(SUM(qty),0) FROM user_invoice_items
     WHERE from_user_id=? AND from_user_type=? AND `date` BETWEEN ? AND ?",
    'isss', [$uid, $utype, $from, $to]);
$total_units = $cust_units + $shop_units;

// Returns
$returns_row = mis_row($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total),0) amount
     FROM user_return_stock WHERE to_usertype=? AND to_userid=? AND `date` BETWEEN ? AND ?",
    'siss', [$utype, $uid, $from, $to]);
$total_returns     = (int)$returns_row['cnt'];
$total_return_amt  = (float)$returns_row['amount'];

// Advance balance
$adv_balance = (float)mis_val($db_conn,
    "SELECT COALESCE(SUM(balance_amount),0) FROM tp_advance_payments
     WHERE territory_partner_id=? AND status='active' AND deleted_at IS NULL",
    'i', [$uid]);

// ── Purchases (tp_invoices — TP buying stock from company/super-stockist)
$purch_row = mis_row($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total_amount),0) amount, COALESCE(SUM(courier_charges),0) courier
     FROM tp_invoices WHERE territory_partner_id=? AND invoice_date BETWEEN ? AND ?",
    'iss', [$uid, $from, $to]);
$total_purch_invoices = (int)$purch_row['cnt'];
$total_purch_amount   = (float)$purch_row['amount'];
$total_purch_net      = $total_purch_amount - (float)$purch_row['courier'];

$purch_units = (int)mis_val($db_conn,
    "SELECT COALESCE(SUM(ii.quantity),0) FROM tp_invoice_items ii
     JOIN tp_invoices i ON i.id = ii.tp_invoice_id
     WHERE i.territory_partner_id=? AND i.invoice_date BETWEEN ? AND ?",
    'iss', [$uid, $from, $to]);

$prev_purch_amount = (float)mis_val($db_conn,
    "SELECT COALESCE(SUM(total_amount),0) FROM tp_invoices
     WHERE territory_partner_id=? AND invoice_date BETWEEN ? AND ?",
    'iss', [$uid, $prev_from, $prev_to]);
$purch_growth = $prev_purch_amount > 0
    ? round((($total_purch_amount - $prev_purch_amount) / $prev_purch_amount) * 100, 1) : 0;

$net_position = $total_revenue - $total_purch_amount;

// ═══════════════════════════════════════════════════════════════════════════
// 1b. GROSS PROFIT — same formula as the company/LLP login's MIS report
// (see company/mis-report.php §1b), adapted to TP's own real purchase
// records instead of a synthetic LLP cost-rate table:
//
//   net_qty   = qty_sold − qty_returned                      (per product)
//   sold_rate = total sale revenue / qty_sold                (per product,
//               effective average price actually realized this period)
//   cost_rate = total purchase cost / qty_purchased           (per product,
//               effective average price TP actually paid the company/SS
//               this period, from tp_invoices/tp_invoice_items — TP's own
//               genuine purchase order, unlike LLP which has no literal
//               purchase invoice and needs a separate rate table)
//   Gross Profit = Σ (sold_rate − cost_rate) × net_qty         (across products)
//
// Both rates are GST-inclusive at the source (invoice_items.total,
// user_invoice_items.total, tp_invoice_items.amount all store tax-inclusive
// line totals — see customer-invoice-action.php / user-invoice-action.php),
// so both are backed out to a pre-tax basis via products.gst before
// subtracting, same convention as the company login — otherwise GST on the
// sold side would inflate the margin against an already pre-tax-adjusted
// cost, or vice versa if cost stayed inclusive.
//
// A product with no purchase this period (no cost_rate to compare against)
// is excluded from Gross Profit entirely — same "unrated == excluded" rule
// as the company login, since a product TP never bought (e.g. opening stock
// carried over, or a free sample) can't be priced here.
// ═══════════════════════════════════════════════════════════════════════════
$gp_sold_rate_gst_divisor = "(1 + COALESCE(p.gst,0)/100)";
$gross_profit = (float)mis_val($db_conn,
    "SELECT COALESCE(SUM((sold.sold_rate / {$gp_sold_rate_gst_divisor} - cost.cost_rate) * (sold.qty_sold - COALESCE(ret.qty_returned,0))), 0)
     FROM (
         SELECT s.pr_id, SUM(s.qty) qty_sold, SUM(s.line_total)/NULLIF(SUM(s.qty),0) sold_rate
         FROM (
             SELECT ii.pr_id, ii.qty, ii.total AS line_total
             FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
             WHERE i.user_id=? AND i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?
             UNION ALL
             SELECT uii.pr_id, uii.qty, uii.total AS line_total
             FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
             WHERE ui.from_user_id=? AND ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?
         ) s
         GROUP BY s.pr_id
     ) sold
     JOIN products p ON p.id = sold.pr_id
     JOIN (
         SELECT tpii.product_id pr_id,
                SUM(tpii.amount) / (1 + COALESCE(pc.gst,0)/100) / NULLIF(SUM(tpii.quantity),0) cost_rate
         FROM tp_invoice_items tpii
         JOIN tp_invoices tpi ON tpi.id = tpii.tp_invoice_id
         JOIN products pc ON pc.id = tpii.product_id
         WHERE tpi.territory_partner_id=? AND tpi.invoice_date BETWEEN ? AND ?
         GROUP BY tpii.product_id
     ) cost ON cost.pr_id = sold.pr_id
     LEFT JOIN (
         SELECT ri.prid pr_id, SUM(ri.qty) qty_returned
         FROM user_return_stock_items ri
         WHERE ri.to_usertype=? AND ri.to_userid=? AND ri.date BETWEEN ? AND ?
         GROUP BY ri.prid
     ) ret ON ret.pr_id = sold.pr_id",
    'isssisssisssiss',
    [$uid, $utype, $from, $to, $uid, $utype, $from, $to, $uid, $from, $to, $utype, $uid, $from, $to]);

// ── Gross Profit split by paid / unpaid sales invoices ─────────────────────
// Same per-product margin (sold_rate − cost_rate, both pre-tax) as above,
// but attributed to the specific sales invoice each line belongs to, then
// bucketed by that invoice's payment status (same receipt.received-vs-total
// comparison the Order Status section below already uses). An invoice with
// partial payment splits its margin proportionally between the two buckets
// by amount received, rather than being force-bucketed to one side — a
// half-paid ₹1L invoice contributes half its margin to "paid" and half to
// "unpaid", matching how much of that revenue is actually realized in hand.
$gp_cost_rate_map = mis_all($db_conn,
    "SELECT tpii.product_id pr_id,
            SUM(tpii.amount) / (1 + COALESCE(pc.gst,0)/100) / NULLIF(SUM(tpii.quantity),0) cost_rate
     FROM tp_invoice_items tpii
     JOIN tp_invoices tpi ON tpi.id = tpii.tp_invoice_id
     JOIN products pc ON pc.id = tpii.product_id
     WHERE tpi.territory_partner_id=? AND tpi.invoice_date BETWEEN ? AND ?
     GROUP BY tpii.product_id",
    'iss', [$uid, $from, $to]);
$gp_cost_rate_by_pr = [];
foreach ($gp_cost_rate_map as $r) $gp_cost_rate_by_pr[(int)$r['pr_id']] = (float)$r['cost_rate'];

// Per-invoice lines (customer + shop), joined to product GST so sold_rate
// can be put pre-tax per line — margin is computed per LINE here (not
// per-product-average like the headline KPI above) since each line already
// carries its own invoice_id for the paid/unpaid split; small rounding
// differences against the headline figure are expected and immaterial.
$gp_lines = mis_all($db_conn,
    "SELECT ii.inv_id, ii.pr_id, ii.qty, ii.total AS line_total, COALESCE(p.gst,0) gst
     FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id JOIN products p ON p.id=ii.pr_id
     WHERE i.user_id=? AND i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?
     UNION ALL
     SELECT uii.inv_id, uii.pr_id, uii.qty, uii.total AS line_total, COALESCE(p.gst,0) gst
     FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id JOIN products p ON p.id=uii.pr_id
     WHERE ui.from_user_id=? AND ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?",
    'isssisss', [$uid, $utype, $from, $to, $uid, $utype, $from, $to]);

// Invoice header totals + amount received, customer and shop invoices alike
// (mirrors the Order Status section's own $order_cust/$order_shop queries).
$gp_inv_totals = [];
foreach (mis_all($db_conn,
    "SELECT i.inv_id, i.total, COALESCE(r.received,0) paid
     FROM invoice i
     LEFT JOIN (SELECT inv_id, SUM(received) received FROM receipt GROUP BY inv_id) r ON r.inv_id = i.inv_id
     WHERE i.user_id=? AND i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?
     UNION ALL
     SELECT ui.inv_id, ui.total, COALESCE(r.received,0) paid
     FROM user_invoice ui
     LEFT JOIN (SELECT inv_id, SUM(received) received FROM receipt GROUP BY inv_id) r ON r.inv_id = ui.inv_id
     WHERE ui.from_user_id=? AND ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?",
    'isssisss', [$uid, $utype, $from, $to, $uid, $utype, $from, $to]) as $r) {
    $t = (float)$r['total']; $p = (float)$r['paid'];
    // Fraction of this invoice's value actually realized in hand — clamped
    // to [0,1] since an overpayment (advance credit etc.) shouldn't push a
    // line's margin past 100% into the paid bucket.
    $gp_inv_totals[$r['inv_id']] = $t > 0 ? max(0, min(1, $p / $t)) : 0;
}

$gross_profit_paid = 0.0;
$gross_profit_unpaid = 0.0;
foreach ($gp_lines as $ln) {
    $pr_id = (int)$ln['pr_id'];
    if (!isset($gp_cost_rate_by_pr[$pr_id])) continue; // never purchased this period — unrated, excluded (same rule as headline KPI)
    $cost_rate = $gp_cost_rate_by_pr[$pr_id];
    $qty = (float)$ln['qty'];
    if ($qty <= 0) continue;
    $line_sold_rate = (float)$ln['line_total'] / $qty / (1 + (float)$ln['gst'] / 100);
    // Returns aren't captured per-invoice-line in user_return_stock_items,
    // only per-product for the period, so they can't be netted per line
    // here — margin is computed gross per line, and the aggregate
    // reconciliation below (against the headline $gross_profit, which IS
    // return-netted) spreads the return adjustment across both buckets.
    $line_margin = ($line_sold_rate - $cost_rate) * $qty;
    $paid_frac = $gp_inv_totals[$ln['inv_id']] ?? 0;
    $gross_profit_paid   += $line_margin * $paid_frac;
    $gross_profit_unpaid += $line_margin * (1 - $paid_frac);
}
// Net out returns at the aggregate level (by product), same net_qty logic
// as the headline KPI, applied proportionally across the paid/unpaid split
// so the two buckets still sum to (approximately) $gross_profit above.
$gp_paid_unpaid_sum = $gross_profit_paid + $gross_profit_unpaid;
if (abs($gp_paid_unpaid_sum) > 0.01) {
    $gp_return_adjustment = $gross_profit - $gp_paid_unpaid_sum;
    $gross_profit_paid   += $gp_return_adjustment * ($gross_profit_paid / $gp_paid_unpaid_sum);
    $gross_profit_unpaid += $gp_return_adjustment * ($gross_profit_unpaid / $gp_paid_unpaid_sum);
}

// Previous period KPI (for growth %)
$prev_cust = mis_row($db_conn,
    "SELECT COALESCE(SUM(total),0) revenue FROM invoice
     WHERE user_id=? AND user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?",
    'isss', [$uid, $utype, $prev_from, $prev_to]);
$prev_shop = mis_row($db_conn,
    "SELECT COALESCE(SUM(total),0) revenue FROM user_invoice
     WHERE from_user_id=? AND from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?",
    'isss', [$uid, $utype, $prev_from, $prev_to]);
$prev_revenue = (float)$prev_cust['revenue'] + (float)$prev_shop['revenue'];
$revenue_growth = $prev_revenue > 0
    ? round((($total_revenue - $prev_revenue) / $prev_revenue) * 100, 1) : 0;

// ═══════════════════════════════════════════════════════════════════════════
// 2. DAILY TREND (for chart)
// ═══════════════════════════════════════════════════════════════════════════
$daily_cust = mis_all($db_conn,
    "SELECT `date` d, COALESCE(SUM(total),0) rev FROM invoice
     WHERE user_id=? AND user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?
     GROUP BY `date` ORDER BY `date` ASC",
    'isss', [$uid, $utype, $from, $to]);
$daily_shop = mis_all($db_conn,
    "SELECT `date` d, COALESCE(SUM(total),0) rev FROM user_invoice
     WHERE from_user_id=? AND from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?
     GROUP BY `date` ORDER BY `date` ASC",
    'isss', [$uid, $utype, $from, $to]);

$daily_map = [];
foreach ($daily_cust as $r) $daily_map[$r['d']]['cust'] = (float)$r['rev'];
foreach ($daily_shop as $r) $daily_map[$r['d']]['shop'] = (float)$r['rev'];

$daily_purch = mis_all($db_conn,
    "SELECT invoice_date d, COALESCE(SUM(total_amount),0) amt FROM tp_invoices
     WHERE territory_partner_id=? AND invoice_date BETWEEN ? AND ?
     GROUP BY invoice_date ORDER BY invoice_date ASC",
    'iss', [$uid, $from, $to]);
$daily_purch_map = [];
foreach ($daily_purch as $r) $daily_purch_map[$r['d']] = (float)$r['amt'];

// Fill every date in range
$ptr = strtotime($from);
$end = strtotime($to);
$chart_labels = $chart_cust = $chart_shop = $chart_purch = [];
while ($ptr <= $end) {
    $d = date('Y-m-d', $ptr);
    $chart_labels[] = date('d M', $ptr);
    $chart_cust[]   = $daily_map[$d]['cust'] ?? 0;
    $chart_shop[]   = $daily_map[$d]['shop'] ?? 0;
    $chart_purch[]  = $daily_purch_map[$d] ?? 0;
    $ptr = strtotime('+1 day', $ptr);
}

// ═══════════════════════════════════════════════════════════════════════════
// 3. PERIOD SUMMARY (Daily / Weekly / Monthly / Yearly)
// ═══════════════════════════════════════════════════════════════════════════
function period_sales($db, $uid, $utype, $from, $to, $group_fmt, $label_fmt) {
    $cust = mis_all($db,
        "SELECT DATE_FORMAT(`date`, '$group_fmt') g, DATE_FORMAT(MIN(`date`), '$label_fmt') lbl,
                COUNT(*) cnt, COALESCE(SUM(total),0) rev
         FROM invoice WHERE user_id=? AND user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?
         GROUP BY g ORDER BY g ASC",
        'isss', [$uid, $utype, $from, $to]);
    $shop = mis_all($db,
        "SELECT DATE_FORMAT(`date`, '$group_fmt') g, DATE_FORMAT(MIN(`date`), '$label_fmt') lbl,
                COUNT(*) cnt, COALESCE(SUM(total),0) rev
         FROM user_invoice WHERE from_user_id=? AND from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?
         GROUP BY g ORDER BY g ASC",
        'isss', [$uid, $utype, $from, $to]);
    $map = [];
    foreach ($cust as $r) { $map[$r['g']]['lbl'] = $r['lbl']; $map[$r['g']]['cust'] = (float)$r['rev']; $map[$r['g']]['cust_cnt'] = (int)$r['cnt']; }
    foreach ($shop as $r) { $map[$r['g']]['lbl'] = $map[$r['g']]['lbl'] ?? $r['lbl']; $map[$r['g']]['shop'] = (float)$r['rev']; $map[$r['g']]['shop_cnt'] = (int)$r['cnt']; }
    ksort($map);
    return $map;
}
$daily_periods   = period_sales($db_conn, $uid, $utype, $from, $to, '%Y-%m-%d', '%d %b');
$weekly_periods  = period_sales($db_conn, $uid, $utype, $from, $to, '%Y-%u', 'W%u %Y');
$monthly_periods = period_sales($db_conn, $uid, $utype, $from, $to, '%Y-%m', '%b %Y');
$yearly_periods  = period_sales($db_conn, $uid, $utype, $from, $to, '%Y', '%Y');

// ═══════════════════════════════════════════════════════════════════════════
// 4. PRODUCT-WISE SALES
// ═══════════════════════════════════════════════════════════════════════════
$product_sales = mis_all($db_conn,
    "SELECT p.productName,
            COALESCE(SUM(d.qty),0) total_qty,
            COALESCE(SUM(d.subtotal),0) subtotal_rev,
            COALESCE(SUM(d.total),0) total_rev
     FROM (
         SELECT pr_id, qty, subtotal, total FROM invoice_items
         WHERE user_id=? AND user_type=? AND `date` BETWEEN ? AND ?
         UNION ALL
         SELECT pr_id, qty, subtotal, total FROM user_invoice_items
         WHERE from_user_id=? AND from_user_type=? AND `date` BETWEEN ? AND ?
     ) d
     JOIN products p ON p.id = d.pr_id
     GROUP BY p.id, p.productName ORDER BY total_qty DESC LIMIT 25",
    'isssisss', [$uid, $utype, $from, $to, $uid, $utype, $from, $to]);
$grand_qty = array_sum(array_column($product_sales, 'total_qty')) ?: 1;
$grand_rev = array_sum(array_column($product_sales, 'total_rev')) ?: 1;

// ═══════════════════════════════════════════════════════════════════════════
// 4b. PURCHASES — period breakdown, product-wise, invoice list w/ payment status
// ═══════════════════════════════════════════════════════════════════════════
function period_purchases($db, $uid, $from, $to, $group_fmt, $label_fmt) {
    $rows = mis_all($db,
        "SELECT DATE_FORMAT(invoice_date, '$group_fmt') g, DATE_FORMAT(MIN(invoice_date), '$label_fmt') lbl,
                COUNT(*) cnt, COALESCE(SUM(total_amount),0) amt
         FROM tp_invoices WHERE territory_partner_id=? AND invoice_date BETWEEN ? AND ?
         GROUP BY g ORDER BY g ASC",
        'iss', [$uid, $from, $to]);
    $map = [];
    foreach ($rows as $r) $map[$r['g']] = ['lbl' => $r['lbl'], 'cnt' => (int)$r['cnt'], 'amt' => (float)$r['amt']];
    return $map;
}
$daily_purch_periods   = period_purchases($db_conn, $uid, $from, $to, '%Y-%m-%d', '%d %b');
$weekly_purch_periods  = period_purchases($db_conn, $uid, $from, $to, '%Y-%u', 'W%u %Y');
$monthly_purch_periods = period_purchases($db_conn, $uid, $from, $to, '%Y-%m', '%b %Y');
$yearly_purch_periods  = period_purchases($db_conn, $uid, $from, $to, '%Y', '%Y');

$product_purchases = mis_all($db_conn,
    "SELECT p.productName,
            COALESCE(SUM(ii.quantity),0) total_qty,
            COALESCE(SUM(ii.amount),0) total_amt
     FROM tp_invoice_items ii
     JOIN tp_invoices i ON i.id = ii.tp_invoice_id
     JOIN products p ON p.id = ii.product_id
     WHERE i.territory_partner_id=? AND i.invoice_date BETWEEN ? AND ?
     GROUP BY p.id, p.productName ORDER BY total_qty DESC LIMIT 25",
    'iss', [$uid, $from, $to]);
$grand_purch_qty = array_sum(array_column($product_purchases, 'total_qty')) ?: 1;

// Purchase invoice list with payment status (mirrors tpBillInfo() logic in manage-purchase-orders.php)
$purchase_invoices = mis_all($db_conn,
    "SELECT id, invoice_number, invoice_date, total_amount, courier_charges, product_type
     FROM tp_invoices WHERE territory_partner_id=? AND invoice_date BETWEEN ? AND ?
     ORDER BY invoice_date DESC, id DESC LIMIT 50",
    'iss', [$uid, $from, $to]);
foreach ($purchase_invoices as &$pi) {
    $netAmount = round((float)$pi['total_amount'] - (float)$pi['courier_charges'], 2);
    $paid = (float)mis_val($db_conn,
        "SELECT COALESCE(SUM(deducted_amount),0) FROM tp_invoice_advance_log WHERE tp_invoice_id=?",
        'i', [$pi['id']]);
    if ($paid <= 0) {
        $pi['payment_status'] = 'not_paid';
    } elseif ($netAmount > 0 && ($paid + 0.01) >= $netAmount) {
        $pi['payment_status'] = 'fully_paid';
    } else {
        $pi['payment_status'] = 'partially_paid';
    }
    $pi['net_amount'] = $netAmount;
    $pi['paid_amount'] = $paid;
}
unset($pi);

// ═══════════════════════════════════════════════════════════════════════════
// 5. STATE / DISTRICT-WISE SALES (shop invoices only, via partner_location_nodes)
// ═══════════════════════════════════════════════════════════════════════════
// shop.state_id is a `state` table id, NOT a partner_location_nodes id — the
// two id spaces overlap numerically (state.id=7 is Tamilnadu, but
// partner_location_nodes.id=7 is Kanchipuram), so joining state_id against
// partner_location_nodes mislabels every row here. district_id IS a
// partner_location_nodes id, so that join below is correct as-is.
$state_sales = mis_all($db_conn,
    "SELECT st.st_name state_name, COUNT(*) cnt, COALESCE(SUM(ui.total),0) revenue
     FROM user_invoice ui
     JOIN shop s ON s.temp_id = ui.to_user_id
     JOIN state st ON st.id = s.state_id
     WHERE ui.from_user_id=? AND ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?
     GROUP BY st.id, st.st_name ORDER BY revenue DESC",
    'isss', [$uid, $utype, $from, $to]);

$district_sales = mis_all($db_conn,
    "SELECT pln.name district_name, COUNT(*) cnt, COALESCE(SUM(ui.total),0) revenue
     FROM user_invoice ui
     JOIN shop s ON s.temp_id = ui.to_user_id
     JOIN partner_location_nodes pln ON pln.id = s.district_id
     WHERE ui.from_user_id=? AND ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?
     GROUP BY pln.id, pln.name ORDER BY revenue DESC",
    'isss', [$uid, $utype, $from, $to]);

// ═══════════════════════════════════════════════════════════════════════════
// 6. TOP SHOPS & CUSTOMERS (Salesperson Performance)
// ═══════════════════════════════════════════════════════════════════════════
$top_shops = mis_all($db_conn,
    "SELECT s.name shop_name, COUNT(*) inv_cnt,
            COALESCE(SUM(ui.total),0) revenue,
            COALESCE(SUM(uii.qty),0) units
     FROM user_invoice ui
     JOIN shop s ON s.temp_id = ui.to_user_id
     LEFT JOIN user_invoice_items uii ON uii.inv_id = ui.inv_id AND uii.from_user_id=? AND uii.from_user_type=?
     WHERE ui.from_user_id=? AND ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?
     GROUP BY s.temp_id, s.name ORDER BY revenue DESC LIMIT 10",
    'isisss', [$uid, $utype, $uid, $utype, $from, $to]);

$top_customers = mis_all($db_conn,
    "SELECT COALESCE(c.name,'Walking Customer') cust_name, COUNT(*) inv_cnt,
            COALESCE(SUM(i.total),0) revenue,
            COALESCE(SUM(ii.qty),0) units
     FROM invoice i
     LEFT JOIN customers c ON c.id = i.customer_id
     LEFT JOIN invoice_items ii ON ii.inv_id = i.inv_id AND ii.user_id=? AND ii.user_type=?
     WHERE i.user_id=? AND i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?
     GROUP BY i.customer_id, c.name ORDER BY revenue DESC LIMIT 10",
    'isisss', [$uid, $utype, $uid, $utype, $from, $to]);

// ═══════════════════════════════════════════════════════════════════════════
// 7. TARGET VS ACHIEVEMENT
// ═══════════════════════════════════════════════════════════════════════════
$target_rows = mis_all($db_conn,
    "SELECT pln.id loc_id, pln.name loc_name, pln.depth, COALESCE(pln.target_amount,0) target
     FROM territory_partner_locations tpl
     JOIN partner_location_nodes pln ON pln.id = tpl.location_id
     WHERE tpl.territory_partner_id=?
     ORDER BY pln.depth ASC, pln.name ASC",
    'i', [$uid]);

// target_amount is Napkin-only (no separate Diaper target exists), so
// 'achieved' here (combined Napkin+Diaper) is not directly comparable to
// target on its own — every row also gets a Napkin-only 'napkin_achieved'
// figure (product category split via products.category, same canonical
// rule used across the Napkin/Diaper wallet split elsewhere:
// COALESCE(category,'') != 'diaper'). The page renders both and lets the
// viewer switch between "Napkin only" (accurate vs target) and "With
// Diaper" (combined) via a toggle, rather than silently picking one.
$total_target = 0;
foreach ($target_rows as &$tr) {
    $total_target += (float)$tr['target'];
    $loc_id = (int)$tr['loc_id'];

    if ($loc_id > 0) {
        $achieved = (float)mis_val($db_conn,
            "SELECT COALESCE(SUM(ui.total),0) FROM user_invoice ui
             JOIN shop s ON s.temp_id = ui.to_user_id
             WHERE ui.from_user_id=? AND ui.from_user_type=?
               AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?
               AND (s.state_id=? OR s.district_id=?)",
            'isssii', [$uid, $utype, $from, $to, $loc_id, $loc_id]);
        $napkinAchieved = (float)mis_val($db_conn,
            "SELECT COALESCE(SUM(uii.total),0) FROM user_invoice ui
             JOIN shop s ON s.temp_id = ui.to_user_id
             JOIN user_invoice_items uii ON uii.inv_id = ui.inv_id AND uii.from_user_id = ui.from_user_id AND uii.from_user_type = ui.from_user_type
             JOIN products p ON p.id = uii.pr_id
             WHERE ui.from_user_id=? AND ui.from_user_type=?
               AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?
               AND (s.state_id=? OR s.district_id=?)
               AND COALESCE(p.category,'') != 'diaper'",
            'isssii', [$uid, $utype, $from, $to, $loc_id, $loc_id]);
    } else {
        $achieved = 0;
        $napkinAchieved = 0;
    }
    $tr['achieved'] = $achieved;
    // Clamp defensively — item-level sums can differ slightly from the
    // header-level total (gst/discount rounding), never let Napkin exceed combined.
    $tr['napkin_achieved'] = min($napkinAchieved, $achieved);
    $tr['diaper_achieved'] = max(0, $achieved - $tr['napkin_achieved']);
    $tr['pct'] = $tr['target'] > 0 ? min(round($achieved / $tr['target'] * 100, 1), 999) : 0;
    $tr['napkin_pct'] = $tr['target'] > 0 ? min(round($tr['napkin_achieved'] / $tr['target'] * 100, 1), 999) : 0;
}
unset($tr);

// Customer-invoice revenue has no state/district on the `customers` table, so it can
// never match a specific location row above (those are shop-sale-only, via shop.state_id/
// district_id). It still counts toward the TP's overall target, so it's added to the
// grand total here rather than silently dropped.
$located_achieved   = array_sum(array_column($target_rows, 'achieved'));
$located_napkin_achieved = array_sum(array_column($target_rows, 'napkin_achieved'));
$unlocated_achieved = (float)($cust_row['revenue'] ?? 0);
$unlocated_napkin_row = mis_row($db_conn,
    "SELECT COALESCE(SUM(ii.total),0) rev FROM invoice i
     JOIN invoice_items ii ON ii.inv_id = i.inv_id AND ii.user_id = i.user_id AND ii.user_type = i.user_type
     JOIN products p ON p.id = ii.pr_id
     WHERE i.user_id=? AND i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?
       AND COALESCE(p.category,'') != 'diaper'",
    'isss', [$uid, $utype, $from, $to]);
$unlocated_napkin_achieved = min((float)($unlocated_napkin_row['rev'] ?? 0), $unlocated_achieved);
$total_achieved        = $located_achieved + $unlocated_achieved;
$total_napkin_achieved = $located_napkin_achieved + $unlocated_napkin_achieved;
$total_diaper_achieved = max(0, $total_achieved - $total_napkin_achieved);
$overall_pct        = $total_target > 0 ? min(round($total_achieved / $total_target * 100, 1), 999) : 0;
$overall_pct_napkin = $total_target > 0 ? min(round($total_napkin_achieved / $total_target * 100, 1), 999) : 0;

// ═══════════════════════════════════════════════════════════════════════════
// 8. ORDER STATUS
// ═══════════════════════════════════════════════════════════════════════════
$order_cust = mis_all($db_conn,
    "SELECT i.inv_id, i.total, COALESCE(r.received,0) AS paid
     FROM invoice i
     LEFT JOIN (SELECT inv_id, SUM(received) received FROM receipt GROUP BY inv_id) r ON r.inv_id = i.inv_id
     WHERE i.user_id=? AND i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?",
    'isss', [$uid, $utype, $from, $to]);

$order_shop = mis_all($db_conn,
    "SELECT ui.inv_id, ui.total, COALESCE(r.received,0) AS paid
     FROM user_invoice ui
     LEFT JOIN (SELECT inv_id, SUM(received) received FROM receipt GROUP BY inv_id) r ON r.inv_id = ui.inv_id
     WHERE ui.from_user_id=? AND ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?",
    'isss', [$uid, $utype, $from, $to]);

$all_orders  = array_merge($order_cust, $order_shop);
$os_paid = $os_partial = $os_unpaid = 0;
$os_paid_amt = $os_partial_amt = $os_unpaid_amt = 0;
foreach ($all_orders as $o) {
    $t = (float)$o['total']; $p = (float)$o['paid'];
    if ($p >= $t && $t > 0) { $os_paid++; $os_paid_amt += $t; }
    elseif ($p > 0 && $p < $t) { $os_partial++; $os_partial_amt += $t; }
    else { $os_unpaid++; $os_unpaid_amt += $t; }
}

// ═══════════════════════════════════════════════════════════════════════════
// 9. 6-MONTH GROWTH TREND
// ═══════════════════════════════════════════════════════════════════════════
$six_months = mis_all($db_conn,
    "SELECT DATE_FORMAT(d, '%Y-%m') mon, DATE_FORMAT(MIN(d), '%b %Y') lbl,
            SUM(rev) total_rev, SUM(cnt) total_cnt
     FROM (
         SELECT `date` d, SUM(total) rev, COUNT(*) cnt FROM invoice
         WHERE user_id=? AND user_type=? AND sub_total>0 AND `date` >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY `date`
         UNION ALL
         SELECT `date` d, SUM(total) rev, COUNT(*) cnt FROM user_invoice
         WHERE from_user_id=? AND from_user_type=? AND sub_total>0 AND `date` >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY `date`
     ) combined
     GROUP BY DATE_FORMAT(d, '%Y-%m')
     ORDER BY mon ASC",
    'isis', [$uid, $utype, $uid, $utype]);

// Growth % month-over-month
$prev_m_rev = null;
foreach ($six_months as &$m) {
    $m['growth'] = ($prev_m_rev !== null && $prev_m_rev > 0)
        ? round((($m['total_rev'] - $prev_m_rev) / $prev_m_rev) * 100, 1)
        : null;
    $prev_m_rev = (float)$m['total_rev'];
}
unset($m);

// Chart for 6-month
$growth_labels = array_column($six_months, 'lbl');
$growth_values = array_column($six_months, 'total_rev');

// ═══════════════════════════════════════════════════════════════════════════
// 10. RETURNS & CANCELLATIONS
// ═══════════════════════════════════════════════════════════════════════════
$returns_list = mis_all($db_conn,
    "SELECT urs.*, inv_num.inv_number
     FROM user_return_stock urs
     LEFT JOIN (
         SELECT inv_id, inv_number FROM invoice
         UNION ALL SELECT inv_id, inv_number FROM user_invoice
     ) inv_num ON inv_num.inv_id = urs.invnumber
     WHERE urs.to_usertype=? AND urs.to_userid=? AND urs.date BETWEEN ? AND ?
     ORDER BY urs.date DESC LIMIT 20",
    'siss', [$utype, $uid, $from, $to]);

$return_by_month = mis_all($db_conn,
    "SELECT DATE_FORMAT(`date`, '%b %Y') lbl, COUNT(*) cnt, COALESCE(SUM(total),0) amount
     FROM user_return_stock WHERE to_usertype=? AND to_userid=? AND `date` BETWEEN ? AND ?
     GROUP BY DATE_FORMAT(`date`, '%b %Y') ORDER BY MIN(`date`) ASC",
    'siss', [$utype, $uid, $from, $to]);

// ═══════════════════════════════════════════════════════════════════════════
// JSON encode chart data
// ═══════════════════════════════════════════════════════════════════════════
$j_labels  = json_encode($chart_labels);
$j_cust    = json_encode($chart_cust);
$j_shop    = json_encode($chart_shop);
$j_purch   = json_encode($chart_purch);
$j_glabels = json_encode($growth_labels);
$j_gvals   = json_encode(array_map('floatval', $growth_values));

$j_plabels = json_encode(array_column($product_sales, 'productName'));
$j_pqty    = json_encode(array_map('intval', array_column($product_sales, 'total_qty')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MIS Report : <?php echo $business_name; ?></title>
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
            --ink: #1a1d29; --ink-soft: #5c6072; --ink-faint: #9598a6;
            --line: #eceef4; --surface: #ffffff; --canvas: #f4f5fa;
            --indigo: #4338ca; --indigo-soft: #eef0fd;
            --teal: #0d9488; --teal-soft: #e6f7f5;
            --amber: #d97706; --amber-soft: #fef3e2;
            --rose: #dc2626; --rose-soft: #fdeaea;
            --violet: #7c3aed; --violet-soft: #f3ecfe;
            --green: #16a34a; --green-soft: #e8f8ed;
            --shadow-sm: 0 1px 2px rgba(20,20,43,.04), 0 1px 1px rgba(20,20,43,.03);
            --shadow-md: 0 4px 16px rgba(24,24,60,.06), 0 1px 3px rgba(24,24,60,.04);
            --shadow-hover: 0 10px 28px rgba(24,24,60,.10), 0 2px 6px rgba(24,24,60,.05);
        }
        body { background: var(--canvas); }
        .container-fluid { max-width: 1440px; }

        /* ── Page header ─────────────────────────────────────────── */
        .mis-page-title {
            font-size: 22px; font-weight: 800; color: var(--ink); letter-spacing: -.3px;
            display: flex; align-items: center; gap: 10px; margin-bottom: 2px;
        }
        .mis-page-title .icon-chip {
            width: 38px; height: 38px; border-radius: 11px; display: inline-flex;
            align-items: center; justify-content: center; background: var(--indigo-soft); color: var(--indigo);
            flex-shrink: 0;
        }
        .mis-page-sub { font-size: 13px; color: var(--ink-faint); margin: 0 0 20px 48px; }

        /* ── Filter bar ──────────────────────────────────────────── */
        .mis-filter-bar {
            background: var(--surface); border: 1px solid var(--line); border-radius: 14px;
            padding: 16px 20px; margin-bottom: 28px; box-shadow: var(--shadow-sm);
        }
        .mis-filter-bar .preset-btns { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .mis-filter-bar label { font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .4px; color: var(--ink-faint); display: block; margin-bottom: 4px; }
        .mis-filter-bar .form-control-sm {
            border-radius: 8px; border: 1px solid #dfe1eb; font-size: 13px;
        }
        .mis-filter-bar .form-control-sm:focus { border-color: var(--indigo); box-shadow: 0 0 0 3px var(--indigo-soft); }
        .mis-filter-bar .btn-primary {
            background: var(--indigo); border-color: var(--indigo); border-radius: 8px;
            font-size: 13px; font-weight: 600; padding: 6px 16px;
        }
        .mis-filter-bar .btn-primary:hover { background: #372ea8; border-color: #372ea8; }
        .preset-btn {
            padding: 6px 15px; border-radius: 20px; border: 1px solid #e2e4ee;
            color: var(--ink-soft); background: var(--surface); font-size: 12.5px; font-weight: 600;
            cursor: pointer; text-decoration: none; transition: all .15s ease;
        }
        .preset-btn:hover { border-color: var(--indigo); color: var(--indigo); text-decoration: none; }
        .preset-btn.active { background: var(--indigo); color: #fff; border-color: var(--indigo); }
        .mis-range-pill {
            font-size: 12px; color: var(--ink-soft); margin-top: 12px; padding-top: 12px;
            border-top: 1px dashed var(--line); display: flex; align-items: center; gap: 6px;
        }
        .mis-range-pill .material-icons-outlined { font-size: 15px; color: var(--ink-faint); }

        /* ── Section grouping ────────────────────────────────────── */
        .mis-section { margin-bottom: 30px; }
        .mis-subhead {
            font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px;
            color: var(--ink-soft); margin: 2px 2px 12px; display: flex; align-items: center; gap: 8px;
        }
        .mis-subhead::after { content: ''; flex: 1; height: 1px; background: var(--line); }
        .mis-subhead .dot { width: 7px; height: 7px; border-radius: 50%; }
        .mis-subhead .dot.sales { background: var(--indigo); }
        .mis-subhead .dot.purch { background: var(--violet); }

        /* ── KPI tiles ───────────────────────────────────────────── */
        .kpi-card {
            border-radius: 14px; padding: 16px 18px; background: var(--surface);
            border: 1px solid var(--line); box-shadow: var(--shadow-sm);
            position: relative; overflow: hidden; height: 100%;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); }
        .kpi-card .kpi-icon-chip {
            width: 34px; height: 34px; border-radius: 9px; display: inline-flex;
            align-items: center; justify-content: center; margin-bottom: 10px;
        }
        .kpi-card .kpi-icon-chip .material-icons-outlined { font-size: 18px; }
        .kpi-card .kpi-title { font-size: 11.5px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .4px; color: var(--ink-faint); }
        .kpi-card .kpi-value { font-size: 24px; font-weight: 800; margin-top: 3px; line-height: 1.2; color: var(--ink); }
        .kpi-card .kpi-sub { font-size: 12px; margin-top: 7px; color: var(--ink-faint); font-weight: 500; }
        .kpi-card .kpi-sub .up { color: var(--green); font-weight: 700; }
        .kpi-card .kpi-sub .down { color: var(--rose); font-weight: 700; }
        .chip-indigo { background: var(--indigo-soft); color: var(--indigo); }
        .chip-teal   { background: var(--teal-soft); color: var(--teal); }
        .chip-amber  { background: var(--amber-soft); color: var(--amber); }
        .chip-rose   { background: var(--rose-soft); color: var(--rose); }
        .chip-violet { background: var(--violet-soft); color: var(--violet); }
        .chip-green  { background: var(--green-soft); color: var(--green); }
        .kpi-accent { position: absolute; left: 0; top: 0; bottom: 0; width: 4px; }
        .kpi-card.negative .kpi-value { color: var(--rose); }
        .kpi-card.positive .kpi-value { color: var(--green); }

        /* ── Cards / panels ──────────────────────────────────────── */
        .card { border-radius: 14px !important; border: 1px solid var(--line) !important;
            box-shadow: var(--shadow-sm) !important; }
        .card-header {
            background: transparent !important; border-bottom: 1px solid var(--line) !important;
            padding: 16px 20px !important; display: flex; align-items: center; gap: 10px;
        }
        .card-header .card-title { font-size: 14.5px !important; font-weight: 700 !important; color: var(--ink); margin: 0; }
        .card-header .hdr-icon {
            width: 28px; height: 28px; border-radius: 8px; display: inline-flex;
            align-items: center; justify-content: center; flex-shrink: 0;
        }
        .card-header .hdr-icon .material-icons-outlined { font-size: 16px; }
        .card-body { padding: 18px 20px !important; }

        /* ── Tabs ────────────────────────────────────────────────── */
        .tab-nav { display: flex; gap: 2px; border-bottom: 1px solid var(--line); margin-bottom: 16px; }
        .tab-nav .tab-item { padding: 8px 18px; cursor: pointer; font-size: 13px; font-weight: 600;
                             color: var(--ink-faint); border-bottom: 2px solid transparent; margin-bottom: -1px;
                             border-radius: 8px 8px 0 0; transition: all .12s ease; }
        .tab-nav .tab-item:hover { color: var(--indigo); background: var(--indigo-soft); }
        .tab-nav .tab-item.active { color: var(--indigo); border-bottom-color: var(--indigo); background: transparent; }
        .tab-content { display: none; } .tab-content.active { display: block; animation: fadein .2s ease; }
        @keyframes fadein { from { opacity: 0; } to { opacity: 1; } }

        /* ── Tables ──────────────────────────────────────────────── */
        .mis-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .mis-table th {
            background: var(--canvas); font-weight: 700; font-size: 11.5px; text-transform: uppercase;
            letter-spacing: .3px; color: var(--ink-soft); padding: 10px 14px; text-align: left;
            position: sticky; top: 0; z-index: 1;
        }
        .mis-table th:first-child { border-radius: 8px 0 0 8px; }
        .mis-table th:last-child { border-radius: 0 8px 8px 0; }
        .mis-table td { padding: 10px 14px; border-bottom: 1px solid var(--line); vertical-align: middle; color: var(--ink); }
        .mis-table tbody tr:last-child td { border-bottom: none; }
        .mis-table tbody tr:hover td { background: var(--indigo-soft); }
        .mis-table tbody tr:nth-child(even) td { background: #fbfbfe; }
        .mis-table tbody tr:nth-child(even):hover td { background: var(--indigo-soft); }

        /* ── Misc chips / badges ─────────────────────────────────── */
        .progress-bar-mis { height: 7px; border-radius: 6px; background: var(--canvas); overflow: hidden; min-width: 70px; }
        .progress-fill { height: 100%; border-radius: 6px; transition: width .4s ease; }
        .badge-rev { background: var(--green-soft); color: var(--green); padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .badge-qty { background: var(--indigo-soft); color: var(--indigo); padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .badge-paid     { background: var(--green-soft); color: var(--green); }
        .badge-partial  { background: var(--amber-soft); color: var(--amber); }
        .badge-unpaid   { background: var(--rose-soft); color: var(--rose); }
        .status-badge   { padding: 4px 11px; border-radius: 20px; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
        .growth-pos { color: var(--green); font-weight: 700; }
        .growth-neg { color: var(--rose); font-weight: 700; }
        .rank-badge {
            display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px;
            border-radius: 7px; background: var(--canvas); color: var(--ink-soft); font-size: 11.5px; font-weight: 800;
        }
        .rank-badge.top1 { background: #fff4d6; color: #b45309; }
        .rank-badge.top2 { background: #eef0f4; color: #52525b; }
        .rank-badge.top3 { background: #fbe4d5; color: #9a3412; }
        .chart-container { position: relative; height: 260px; }
        .section-note { font-size: 12px; color: var(--ink-faint); margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .empty-state { text-align: center; padding: 36px 20px; color: var(--ink-faint); }
        .empty-state .material-icons-outlined { font-size: 32px; opacity: .4; display: block; margin: 0 auto 8px; }
        .stat-tile { background: var(--canvas); padding: 16px; border-radius: 12px; text-align: center; }
        .stat-tile .stat-label { font-size: 11px; color: var(--ink-faint); text-transform: uppercase; font-weight: 700; letter-spacing: .4px; }
        .stat-tile .stat-value { font-size: 22px; font-weight: 800; color: var(--ink); margin-top: 4px; }

        @media(max-width: 768px) {
            .kpi-card .kpi-value { font-size: 19px; }
            .mis-page-title { font-size: 18px; }
        }
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

                    <!-- Page header -->
                    <div class="mis-page-title">
                        <span class="icon-chip"><i class="material-icons-outlined">assessment</i></span>
                        MIS Report — Sales &amp; Purchases
                    </div>
                    <div class="mis-page-sub">A complete view of your sales, purchases, targets and outstanding dues.</div>

                    <!-- ── FILTER BAR ──────────────────────────────────────── -->
                    <div class="mis-filter-bar">
                        <form method="get" id="filterForm" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                            <div>
                                <label>From</label>
                                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo $from; ?>" style="width:155px;">
                            </div>
                            <div>
                                <label>To</label>
                                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo $to; ?>" style="width:155px;">
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="material-icons-outlined" style="font-size:14px;vertical-align:-2px;">search</i> Apply
                                </button>
                            </div>
                            <div class="preset-btns" style="margin-left:auto;align-self:flex-end;">
                                <a href="?preset=today"  class="preset-btn <?php echo $preset==='today'  ? 'active':'' ?>">Today</a>
                                <a href="?preset=week"   class="preset-btn <?php echo $preset==='week'   ? 'active':'' ?>">This Week</a>
                                <a href="?preset=month"  class="preset-btn <?php echo $preset==='month'  ? 'active':'' ?>">This Month</a>
                                <a href="?preset=year"   class="preset-btn <?php echo $preset==='year'   ? 'active':'' ?>">This Year</a>
                            </div>
                        </form>
                        <div class="mis-range-pill">
                            <i class="material-icons-outlined">event</i>
                            Showing <b><?php echo date('d M Y', strtotime($from)); ?></b> to <b><?php echo date('d M Y', strtotime($to)); ?></b>
                            <span style="color:var(--ink-faint);">(<?php echo $days_diff + 1; ?> days)</span>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════
                         KPI CARDS
                    ═══════════════════════════════════════════════════════ -->
                    <div class="mis-subhead"><span class="dot sales"></span>Sales</div>
                    <div class="row mis-section">
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-indigo"><i class="material-icons-outlined">payments</i></span>
                                <div class="kpi-title">Total Revenue</div>
                                <div class="kpi-value">&#x20B9;<?php echo inr_format($total_revenue, 0); ?></div>
                                <div class="kpi-sub">
                                    <?php if ($revenue_growth != 0): ?>
                                    <span class="<?php echo $revenue_growth >= 0 ? 'up' : 'down'; ?>">
                                        <?php echo $revenue_growth >= 0 ? '▲' : '▼'; ?> <?php echo abs($revenue_growth); ?>%
                                    </span> vs prev period
                                    <?php else: ?>vs previous period<?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-teal"><i class="material-icons-outlined">receipt_long</i></span>
                                <div class="kpi-title">Sales Invoices</div>
                                <div class="kpi-value"><?php echo inr_format($total_invoices, 0); ?></div>
                                <div class="kpi-sub">Cust: <b><?php echo $cust_row['cnt'] ?? 0; ?></b> &nbsp;·&nbsp; Shop: <b><?php echo $shop_row['cnt'] ?? 0; ?></b></div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-amber"><i class="material-icons-outlined">inventory_2</i></span>
                                <div class="kpi-title">Units Sold</div>
                                <div class="kpi-value"><?php echo inr_format($total_units, 0); ?></div>
                                <div class="kpi-sub">Cust: <b><?php echo inr_format($cust_units, 0); ?></b> &nbsp;·&nbsp; Shop: <b><?php echo inr_format($shop_units, 0); ?></b></div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-rose"><i class="material-icons-outlined">keyboard_return</i></span>
                                <div class="kpi-title">Returns</div>
                                <div class="kpi-value"><?php echo inr_format($total_returns, 0); ?></div>
                                <div class="kpi-sub">&#x20B9;<?php echo inr_format($total_return_amt, 0); ?> returned</div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-violet"><i class="material-icons-outlined">account_balance_wallet</i></span>
                                <div class="kpi-title">Advance Balance</div>
                                <div class="kpi-value">&#x20B9;<?php echo inr_format($adv_balance, 0); ?></div>
                                <div class="kpi-sub">Available to use</div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-green"><i class="material-icons-outlined">flag</i></span>
                                <div class="kpi-title">Target Achieved <small style="font-weight:400;color:var(--ink-faint);">(Napkin)</small></div>
                                <div class="kpi-value target-pct-display"
                                     data-napkin-pct="<?php echo $overall_pct_napkin; ?>%"
                                     data-combined-pct="<?php echo $overall_pct; ?>%"><?php echo $overall_pct_napkin; ?>%</div>
                                <div class="kpi-sub target-pct-display"
                                     data-napkin-sub="&#x20B9;<?php echo inr_format($total_napkin_achieved, 0); ?> of &#x20B9;<?php echo inr_format($total_target, 0); ?>"
                                     data-combined-sub="&#x20B9;<?php echo inr_format($total_achieved, 0); ?> of &#x20B9;<?php echo inr_format($total_target, 0); ?>">&#x20B9;<?php echo inr_format($total_napkin_achieved, 0); ?> of &#x20B9;<?php echo inr_format($total_target, 0); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="mis-subhead"><span class="dot purch"></span>Purchases</div>
                    <div class="row mis-section">
                        <div class="col-xl-3 col-md-6 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-violet"><i class="material-icons-outlined">shopping_cart</i></span>
                                <div class="kpi-title">Total Purchases</div>
                                <div class="kpi-value">&#x20B9;<?php echo inr_format($total_purch_amount, 0); ?></div>
                                <div class="kpi-sub">
                                    <?php if ($purch_growth != 0): ?>
                                    <span class="<?php echo $purch_growth >= 0 ? 'down' : 'up'; ?>">
                                        <?php echo $purch_growth >= 0 ? '▲' : '▼'; ?> <?php echo abs($purch_growth); ?>%
                                    </span> vs prev period
                                    <?php else: ?>vs previous period<?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-teal"><i class="material-icons-outlined">receipt</i></span>
                                <div class="kpi-title">Purchase Invoices</div>
                                <div class="kpi-value"><?php echo inr_format($total_purch_invoices, 0); ?></div>
                                <div class="kpi-sub"><?php echo inr_format((int)$purch_units, 0); ?> units purchased</div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip" style="background:#eceff1;color:#455a64;"><i class="material-icons-outlined">local_shipping</i></span>
                                <div class="kpi-title">Net Purchase</div>
                                <div class="kpi-value">&#x20B9;<?php echo inr_format($total_purch_net, 0); ?></div>
                                <div class="kpi-sub">Excl. courier &#x20B9;<?php echo inr_format($purch_row['courier'] ?? 0, 0); ?></div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 col-6 mb-3">
                            <div class="kpi-card <?php echo $net_position >= 0 ? 'positive' : 'negative'; ?>">
                                <span class="kpi-icon-chip <?php echo $net_position >= 0 ? 'chip-green' : 'chip-rose'; ?>">
                                    <i class="material-icons-outlined"><?php echo $net_position >= 0 ? 'trending_up' : 'trending_down'; ?></i>
                                </span>
                                <div class="kpi-title">Net Position</div>
                                <div class="kpi-value"><?php echo $net_position >= 0 ? '+' : '−'; ?>&#x20B9;<?php echo inr_format(abs($net_position), 0); ?></div>
                                <div class="kpi-sub">Sales − Purchases</div>
                            </div>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════
                         GROSS PROFIT
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-12 mb-4">
                            <div class="card">
                                <div class="card-header"><span class="hdr-icon chip-green"><i class="material-icons-outlined">trending_up</i></span><h5 class="card-title">Gross Profit</h5></div>
                                <div class="card-body">
                                    <p class="snote" style="font-size:12px;color:var(--ink-faint);margin-top:-6px;margin-bottom:16px;">
                                        Sold rate is your actual sale price to customers/shops this period; purchase rate is what you actually paid the company/super-stockist on your purchase invoices (<?php echo inr_format($total_purch_invoices, 0); ?> invoices, <?php echo inr_format((int)$purch_units, 0); ?> units) — both put on a pre-tax basis before comparing, same calculation used on the company/LLP login's MIS report. Products purchased once and sold from carried-over stock (never re-purchased this period) are excluded, since there's no purchase rate to compare against.
                                    </p>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <div class="kpi-card <?php echo $gross_profit >= 0 ? 'positive' : 'negative'; ?>">
                                                <span class="kpi-icon-chip <?php echo $gross_profit >= 0 ? 'chip-green' : 'chip-rose'; ?>"><i class="material-icons-outlined">account_balance</i></span>
                                                <div class="kpi-title">Total Gross Profit</div>
                                                <div class="kpi-value">&#x20B9;<?php echo inr_format($gross_profit, 2); ?></div>
                                                <div class="kpi-sub">Sold rate − Purchase rate, net of returns</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="kpi-card positive">
                                                <span class="kpi-icon-chip chip-green"><i class="material-icons-outlined">check_circle</i></span>
                                                <div class="kpi-title">From Paid Invoices</div>
                                                <div class="kpi-value">&#x20B9;<?php echo inr_format($gross_profit_paid, 2); ?></div>
                                                <div class="kpi-sub">Realized — payment received</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="kpi-card">
                                                <span class="kpi-icon-chip chip-amber"><i class="material-icons-outlined">hourglass_empty</i></span>
                                                <div class="kpi-title">From Unpaid / Partial Invoices</div>
                                                <div class="kpi-value">&#x20B9;<?php echo inr_format($gross_profit_unpaid, 2); ?></div>
                                                <div class="kpi-sub">Booked — payment pending</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="progress-bar-mis" style="width:100%;height:10px;">
                                        <?php $gp_paid_pct = ($gross_profit_paid + $gross_profit_unpaid) != 0 ? max(0, min(100, round($gross_profit_paid / ($gross_profit_paid + $gross_profit_unpaid) * 100, 1))) : 0; ?>
                                        <div class="progress-fill" style="width:<?php echo $gp_paid_pct; ?>%;background:var(--green);"></div>
                                    </div>
                                    <div class="section-note" style="margin-top:8px;">
                                        <b><?php echo $gp_paid_pct; ?>%</b>&nbsp;of gross profit is realized (payment received) · <b><?php echo 100 - $gp_paid_pct; ?>%</b> is still booked on outstanding invoices
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════
                         SALES TREND CHART
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-8 mb-4">
                            <div class="card">
                                <div class="card-header"><span class="hdr-icon chip-indigo"><i class="material-icons-outlined">show_chart</i></span><h5 class="card-title">Daily Sales &amp; Purchase Trend</h5></div>
                                <div class="card-body">
                                    <div class="chart-container"><canvas id="trendChart"></canvas></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 mb-4">
                            <div class="card">
                                <div class="card-header"><span class="hdr-icon chip-amber"><i class="material-icons-outlined">pie_chart</i></span><h5 class="card-title">Order Status</h5></div>
                                <div class="card-body">
                                    <div class="chart-container"><canvas id="statusChart"></canvas></div>
                                    <div class="mt-3" style="display:flex;flex-direction:column;gap:8px;">
                                        <div style="display:flex;justify-content:space-between;align-items:center;background:var(--green-soft);border-radius:10px;padding:8px 12px;">
                                            <span class="status-badge badge-paid">Fully Paid</span>
                                            <span style="font-size:12.5px;font-weight:600;color:var(--ink);"><?php echo $os_paid; ?> inv · &#x20B9;<?php echo inr_format($os_paid_amt, 0); ?></span>
                                        </div>
                                        <div style="display:flex;justify-content:space-between;align-items:center;background:var(--amber-soft);border-radius:10px;padding:8px 12px;">
                                            <span class="status-badge badge-partial">Partially Paid</span>
                                            <span style="font-size:12.5px;font-weight:600;color:var(--ink);"><?php echo $os_partial; ?> inv · &#x20B9;<?php echo inr_format($os_partial_amt, 0); ?></span>
                                        </div>
                                        <div style="display:flex;justify-content:space-between;align-items:center;background:var(--rose-soft);border-radius:10px;padding:8px 12px;">
                                            <span class="status-badge badge-unpaid">Unpaid</span>
                                            <span style="font-size:12.5px;font-weight:600;color:var(--ink);"><?php echo $os_unpaid; ?> inv · &#x20B9;<?php echo inr_format($os_unpaid_amt, 0); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════
                         PERIOD BREAKDOWN (tabs)
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <span class="hdr-icon chip-indigo"><i class="material-icons-outlined">calendar_view_week</i></span>
                                    <h5 class="card-title">Sales Breakdown by Period</h5>
                                </div>
                                <div class="card-body">
                                    <div class="tab-nav" id="periodTabs">
                                        <div class="tab-item active" data-tab="daily">Daily</div>
                                        <div class="tab-item" data-tab="weekly">Weekly</div>
                                        <div class="tab-item" data-tab="monthly">Monthly</div>
                                        <div class="tab-item" data-tab="yearly">Yearly</div>
                                    </div>

                                    <?php
                                    function render_period_table($data, $tab_id) {
                                        $active = $tab_id === 'daily' ? 'active' : '';
                                        echo "<div class='tab-content $active' id='tab-$tab_id'>";
                                        if (empty($data)) {
                                            echo "<div class='empty-state'><i class='material-icons-outlined'>event_busy</i>No data for this period.</div>";
                                            echo "</div>"; return;
                                        }
                                        $grand_rev = array_sum(array_map(fn($r) => ($r['cust'] ?? 0) + ($r['shop'] ?? 0), $data));
                                        echo "<div style='overflow-x:auto'><table class='mis-table'>";
                                        echo "<thead><tr><th>Period</th><th>Customer Sales</th><th>Shop Sales</th><th>Total Revenue</th><th>Total Invoices</th><th>Share</th></tr></thead><tbody>";
                                        foreach ($data as $g => $r) {
                                            $rev  = ($r['cust'] ?? 0) + ($r['shop'] ?? 0);
                                            $cnt  = ($r['cust_cnt'] ?? 0) + ($r['shop_cnt'] ?? 0);
                                            $pct  = $grand_rev > 0 ? round($rev / $grand_rev * 100, 1) : 0;
                                            $lbl  = $r['lbl'] ?? $g;
                                            $cust_cnt = $r['cust_cnt'] ?? 0;
                                            $shop_cnt = $r['shop_cnt'] ?? 0;
                                            echo "<tr>
                                                <td><b>$lbl</b></td>
                                                <td>&#x20B9;" . inr_format($r['cust'] ?? 0, 2) . " <small>($cust_cnt)</small></td>
                                                <td>&#x20B9;" . inr_format($r['shop'] ?? 0, 2) . " <small>($shop_cnt)</small></td>
                                                <td><b>&#x20B9;" . inr_format($rev, 2) . "</b></td>
                                                <td>$cnt</td>
                                                <td><div class='d-flex align-items-center gap-2'>
                                                    <div class='progress-bar-mis' style='width:80px'><div class='progress-fill' style='width:{$pct}%;background:#3f51b5'></div></div>
                                                    <span style='font-size:12px'>$pct%</span>
                                                </div></td>
                                            </tr>";
                                        }
                                        echo "</tbody></table></div></div>";
                                    }
                                    render_period_table($daily_periods, 'daily');
                                    render_period_table($weekly_periods, 'weekly');
                                    render_period_table($monthly_periods, 'monthly');
                                    render_period_table($yearly_periods, 'yearly');
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════
                         PRODUCT-WISE SALES
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-7 mb-4">
                            <div class="card h-100">
                                <div class="card-header"><span class="hdr-icon chip-teal"><i class="material-icons-outlined">inventory_2</i></span><h5 class="card-title">Product-wise Sales</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($product_sales)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">inventory_2</i>No product sales in this period.</div>
                                    <?php else: ?>
                                    <table class="mis-table">
                                        <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Revenue (incl. GST)</th><th>% Qty</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($product_sales as $i => $p): ?>
                                            <?php
                                            $pct_qty = $grand_qty > 0 ? round($p['total_qty'] / $grand_qty * 100, 1) : 0;
                                            $bar_color = $i === 0 ? '#f44336' : ($i === 1 ? '#ff9800' : ($i === 2 ? '#4caf50' : '#3f51b5'));
                                            ?>
                                            <tr>
                                                <td><span class="rank-badge <?php echo $i===0?'top1':($i===1?'top2':($i===2?'top3':'')); ?>"><?php echo $i+1; ?></span></td>
                                                <td><b><?php echo htmlspecialchars($p['productName']); ?></b></td>
                                                <td><span class="badge-qty"><?php echo inr_format((int)$p['total_qty'], 0); ?> u</span></td>
                                                <td><span class="badge-rev">&#x20B9;<?php echo inr_format($p['total_rev'], 2); ?></span></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress-bar-mis" style="width:70px"><div class="progress-fill" style="width:<?php echo $pct_qty; ?>%;background:<?php echo $bar_color; ?>"></div></div>
                                                        <span style="font-size:12px"><?php echo $pct_qty; ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-5 mb-4">
                            <div class="card h-100">
                                <div class="card-header"><span class="hdr-icon chip-teal"><i class="material-icons-outlined">donut_small</i></span><h5 class="card-title">Product Mix (Top 8)</h5></div>
                                <div class="card-body">
                                    <div class="chart-container"><canvas id="productChart"></canvas></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════
                         PURCHASES — Period Breakdown
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <span class="hdr-icon chip-violet"><i class="material-icons-outlined">calendar_view_week</i></span>
                                    <h5 class="card-title">Purchases Breakdown by Period</h5>
                                </div>
                                <div class="card-body">
                                    <div class="tab-nav" id="purchPeriodTabs">
                                        <div class="tab-item active" data-tab="pdaily">Daily</div>
                                        <div class="tab-item" data-tab="pweekly">Weekly</div>
                                        <div class="tab-item" data-tab="pmonthly">Monthly</div>
                                        <div class="tab-item" data-tab="pyearly">Yearly</div>
                                    </div>

                                    <?php
                                    function render_purch_period_table($data, $tab_id) {
                                        $active = $tab_id === 'pdaily' ? 'active' : '';
                                        echo "<div class='tab-content $active' id='tab-$tab_id'>";
                                        if (empty($data)) {
                                            echo "<div class='empty-state'><i class='material-icons-outlined'>event_busy</i>No purchases for this period.</div>";
                                            echo "</div>"; return;
                                        }
                                        $grand = array_sum(array_column($data, 'amt')) ?: 1;
                                        echo "<div style='overflow-x:auto'><table class='mis-table'>";
                                        echo "<thead><tr><th>Period</th><th>Purchase Invoices</th><th>Amount</th><th>Share</th></tr></thead><tbody>";
                                        foreach ($data as $g => $r) {
                                            $pct = $grand > 0 ? round($r['amt'] / $grand * 100, 1) : 0;
                                            echo "<tr>
                                                <td><b>{$r['lbl']}</b></td>
                                                <td>{$r['cnt']}</td>
                                                <td><b>&#x20B9;" . inr_format($r['amt'], 2) . "</b></td>
                                                <td><div class='d-flex align-items-center gap-2'>
                                                    <div class='progress-bar-mis' style='width:80px'><div class='progress-fill' style='width:{$pct}%;background:#7e57c2'></div></div>
                                                    <span style='font-size:12px'>$pct%</span>
                                                </div></td>
                                            </tr>";
                                        }
                                        echo "</tbody></table></div></div>";
                                    }
                                    render_purch_period_table($daily_purch_periods, 'pdaily');
                                    render_purch_period_table($weekly_purch_periods, 'pweekly');
                                    render_purch_period_table($monthly_purch_periods, 'pmonthly');
                                    render_purch_period_table($yearly_purch_periods, 'pyearly');
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════
                         PURCHASES — Product-wise & Invoice List
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header"><span class="hdr-icon chip-violet"><i class="material-icons-outlined">shopping_cart</i></span><h5 class="card-title">Product-wise Purchases</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($product_purchases)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">shopping_cart</i>No product purchases in this period.</div>
                                    <?php else: ?>
                                    <table class="mis-table">
                                        <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Amount</th><th>% Qty</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($product_purchases as $i => $p): ?>
                                            <?php $pct_qty = $grand_purch_qty > 0 ? round($p['total_qty'] / $grand_purch_qty * 100, 1) : 0; ?>
                                            <tr>
                                                <td><span class="rank-badge <?php echo $i===0?'top1':($i===1?'top2':($i===2?'top3':'')); ?>"><?php echo $i+1; ?></span></td>
                                                <td><b><?php echo htmlspecialchars($p['productName']); ?></b></td>
                                                <td><span class="badge-qty"><?php echo inr_format((int)$p['total_qty'], 0); ?> u</span></td>
                                                <td><span class="badge-rev">&#x20B9;<?php echo inr_format($p['total_amt'], 2); ?></span></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress-bar-mis" style="width:70px"><div class="progress-fill" style="width:<?php echo $pct_qty; ?>%;background:#7e57c2"></div></div>
                                                        <span style="font-size:12px"><?php echo $pct_qty; ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header"><span class="hdr-icon chip-violet"><i class="material-icons-outlined">receipt_long</i></span><h5 class="card-title">Purchase Invoices <small class="text-muted">(latest 50)</small></h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($purchase_invoices)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">receipt_long</i>No purchase invoices in this period.</div>
                                    <?php else: ?>
                                    <table class="mis-table">
                                        <thead><tr><th>Invoice #</th><th>Type</th><th>Date</th><th>Amount</th><th>Paid</th><th>Status</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($purchase_invoices as $pi): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($pi['invoice_number']); ?></td>
                                                <td>
                                                    <?php $_invType = tpResolveProductType($pi['product_type'] ?? null); [$_tBg, $_tFg] = tpProductTypeBadgeColors($_invType); ?>
                                                    <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:9px;background:<?php echo $_tBg; ?>;color:<?php echo $_tFg; ?>;"><?php echo htmlspecialchars(tpProductTypeLabel($_invType)); ?></span>
                                                </td>
                                                <td><?php echo date('d M Y', strtotime($pi['invoice_date'])); ?></td>
                                                <td>&#x20B9;<?php echo inr_format($pi['total_amount'], 2); ?></td>
                                                <td>&#x20B9;<?php echo inr_format($pi['paid_amount'], 2); ?></td>
                                                <td>
                                                    <?php if ($pi['payment_status'] === 'fully_paid'): ?>
                                                        <span class="status-badge badge-paid">Fully Paid</span>
                                                    <?php elseif ($pi['payment_status'] === 'partially_paid'): ?>
                                                        <span class="status-badge badge-partial">Partial</span>
                                                    <?php else: ?>
                                                        <span class="status-badge badge-unpaid">Not Paid</span>
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

                    <!-- ══════════════════════════════════════════════════════
                         STATE / DISTRICT-WISE SALES
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-6 mb-4">
                            <div class="card">
                                <div class="card-header"><span class="hdr-icon chip-teal"><i class="material-icons-outlined">public</i></span><h5 class="card-title">State-wise Sales</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <p class="section-note">Based on shop invoices only (customer invoices have no geographic data).</p>
                                    <?php if (empty($state_sales)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">public_off</i>No geographic data available.</div>
                                    <?php else:
                                        $max_state = max(array_column($state_sales, 'revenue')) ?: 1;
                                    ?>
                                    <table class="mis-table">
                                        <thead><tr><th>State</th><th>Invoices</th><th>Revenue</th><th>Share</th></tr></thead>
                                        <tbody>
                                        <?php $total_state_rev = array_sum(array_column($state_sales, 'revenue')) ?: 1; ?>
                                        <?php foreach ($state_sales as $s): ?>
                                            <?php $pct = round($s['revenue'] / $total_state_rev * 100, 1); ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($s['state_name']); ?></b></td>
                                                <td><?php echo $s['cnt']; ?></td>
                                                <td><span class="badge-rev">&#x20B9;<?php echo inr_format($s['revenue'], 2); ?></span></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress-bar-mis" style="width:70px"><div class="progress-fill" style="width:<?php echo $pct; ?>%;background:#00897b"></div></div>
                                                        <span style="font-size:12px"><?php echo $pct; ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6 mb-4">
                            <div class="card">
                                <div class="card-header"><span class="hdr-icon chip-violet"><i class="material-icons-outlined">location_on</i></span><h5 class="card-title">District-wise Sales</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($district_sales)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">location_off</i>No district data available.</div>
                                    <?php else:
                                        $total_dist_rev = array_sum(array_column($district_sales, 'revenue')) ?: 1;
                                    ?>
                                    <table class="mis-table">
                                        <thead><tr><th>District</th><th>Invoices</th><th>Revenue</th><th>Share</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($district_sales as $d): ?>
                                            <?php $pct = round($d['revenue'] / $total_dist_rev * 100, 1); ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($d['district_name']); ?></b></td>
                                                <td><?php echo $d['cnt']; ?></td>
                                                <td><span class="badge-rev">&#x20B9;<?php echo inr_format($d['revenue'], 2); ?></span></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress-bar-mis" style="width:70px"><div class="progress-fill" style="width:<?php echo $pct; ?>%;background:#7b1fa2"></div></div>
                                                        <span style="font-size:12px"><?php echo $pct; ?>%</span>
                                                    </div>
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

                    <!-- ══════════════════════════════════════════════════════
                         TOP SHOPS & CUSTOMERS (Salesperson Performance)
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-6 mb-4">
                            <div class="card">
                                <div class="card-header"><span class="hdr-icon chip-amber"><i class="material-icons-outlined">storefront</i></span><h5 class="card-title">Top 10 Shops by Revenue</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($top_shops)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">storefront</i>No shop sales in this period.</div>
                                    <?php else:
                                        $max_shop_rev = (float)$top_shops[0]['revenue'] ?: 1;
                                    ?>
                                    <table class="mis-table">
                                        <thead><tr><th>#</th><th>Shop Name</th><th>Invoices</th><th>Units</th><th>Revenue</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($top_shops as $i => $s): ?>
                                            <tr>
                                                <td><span class="rank-badge <?php echo $i===0?'top1':($i===1?'top2':($i===2?'top3':'')); ?>"><?php echo $i+1; ?></span></td>
                                                <td><b><?php echo htmlspecialchars($s['shop_name']); ?></b></td>
                                                <td><?php echo $s['inv_cnt']; ?></td>
                                                <td><span class="badge-qty"><?php echo inr_format((int)$s['units'], 0); ?></span></td>
                                                <td>
                                                    <span class="badge-rev">&#x20B9;<?php echo inr_format($s['revenue'], 2); ?></span>
                                                    <div class="progress-bar-mis mt-1"><div class="progress-fill" style="width:<?php echo round($s['revenue']/$max_shop_rev*100,1); ?>%;background:#ef6c00"></div></div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6 mb-4">
                            <div class="card">
                                <div class="card-header"><span class="hdr-icon chip-indigo"><i class="material-icons-outlined">person</i></span><h5 class="card-title">Top 10 Customers by Revenue</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($top_customers)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">person_off</i>No customer sales in this period.</div>
                                    <?php else:
                                        $max_cust_rev = (float)$top_customers[0]['revenue'] ?: 1;
                                    ?>
                                    <table class="mis-table">
                                        <thead><tr><th>#</th><th>Customer</th><th>Invoices</th><th>Units</th><th>Revenue</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($top_customers as $i => $c): ?>
                                            <tr>
                                                <td><span class="rank-badge <?php echo $i===0?'top1':($i===1?'top2':($i===2?'top3':'')); ?>"><?php echo $i+1; ?></span></td>
                                                <td><b><?php echo htmlspecialchars($c['cust_name']); ?></b></td>
                                                <td><?php echo $c['inv_cnt']; ?></td>
                                                <td><span class="badge-qty"><?php echo inr_format((int)$c['units'], 0); ?></span></td>
                                                <td>
                                                    <span class="badge-rev">&#x20B9;<?php echo inr_format($c['revenue'], 2); ?></span>
                                                    <div class="progress-bar-mis mt-1"><div class="progress-fill" style="width:<?php echo round($c['revenue']/$max_cust_rev*100,1); ?>%;background:#3f51b5"></div></div>
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

                    <!-- ══════════════════════════════════════════════════════
                         TARGET VS ACHIEVEMENT
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-12 mb-4">
                            <div class="card">
                                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                                    <div>
                                        <span class="hdr-icon chip-green"><i class="material-icons-outlined">flag</i></span>
                                        <h5 class="card-title" style="display:inline;">Target vs Achievement — by Location</h5>
                                    </div>
                                    <label style="display:flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;color:var(--ink-faint);cursor:pointer;user-select:none;">
                                        <input type="checkbox" id="targetDiaperToggle" style="width:16px;height:16px;">
                                        Include Diaper sales (target itself stays Napkin-only)
                                    </label>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($target_rows)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">flag</i>No assigned locations with targets found.</div>
                                    <?php else: ?>
                                    <p class="section-note">
                                        <i class="material-icons-outlined">info</i>
                                        Your target is Napkin-only, so figures below default to Napkin sales. Flip the switch above to see Napkin+Diaper combined instead — useful to check total sales, but not a real reading of target progress.
                                    </p>
                                    <?php if ($unlocated_achieved > 0): ?>
                                    <p class="section-note">
                                        <i class="material-icons-outlined">info</i>
                                        Includes &#x20B9;<?php echo inr_format($unlocated_achieved, 0); ?> (combined) / &#x20B9;<?php echo inr_format($unlocated_napkin_achieved, 0); ?> (Napkin) from customer invoices, which have no state/district on file and so can't be split by location below — it's added to the overall total only.
                                    </p>
                                    <?php endif; ?>
                                    <div class="row mb-3">
                                        <div class="col-md-4 mb-2">
                                            <div class="stat-tile">
                                                <div class="stat-label">Total Target <small>(Napkin)</small></div>
                                                <div class="stat-value" style="color:var(--indigo);">&#x20B9;<?php echo inr_format($total_target, 0); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <div class="stat-tile">
                                                <div class="stat-label">Total Achieved</div>
                                                <div class="stat-value target-pct-display" style="color:var(--green);"
                                                     data-napkin-sub="&#x20B9;<?php echo inr_format($total_napkin_achieved, 0); ?>"
                                                     data-combined-sub="&#x20B9;<?php echo inr_format($total_achieved, 0); ?>">&#x20B9;<?php echo inr_format($total_napkin_achieved, 0); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <div class="stat-tile">
                                                <div class="stat-label">Achievement %</div>
                                                <div class="stat-value target-pct-display"
                                                     data-napkin-pct="<?php echo $overall_pct_napkin; ?>%"
                                                     data-combined-pct="<?php echo $overall_pct; ?>%"
                                                     data-napkin-color="<?php echo $overall_pct_napkin >= 100 ? 'var(--green)' : ($overall_pct_napkin >= 50 ? 'var(--amber)' : 'var(--rose)'); ?>"
                                                     data-combined-color="<?php echo $overall_pct >= 100 ? 'var(--green)' : ($overall_pct >= 50 ? 'var(--amber)' : 'var(--rose)'); ?>"
                                                     style="color:<?php echo $overall_pct_napkin >= 100 ? 'var(--green)' : ($overall_pct_napkin >= 50 ? 'var(--amber)' : 'var(--rose)'); ?>;">
                                                    <?php echo $overall_pct_napkin; ?>%
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="overflow-x:auto">
                                    <table class="mis-table">
                                        <thead><tr><th>Location</th><th>Depth</th><th>Target <small>(Napkin)</small></th><th>Achieved</th><th>Gap</th><th>Achievement %</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($target_rows as $tr): ?>
                                            <?php
                                            $gapNapkin   = (float)$tr['target'] - (float)$tr['napkin_achieved'];
                                            $gapCombined = (float)$tr['target'] - (float)$tr['achieved'];
                                            $pctNapkin   = (float)$tr['napkin_pct'];
                                            $pctCombined = (float)$tr['pct'];
                                            $barNapkin   = $pctNapkin >= 100 ? '#2e7d32' : ($pctNapkin >= 50 ? '#f57c00' : '#c62828');
                                            $barCombined = $pctCombined >= 100 ? '#2e7d32' : ($pctCombined >= 50 ? '#f57c00' : '#c62828');
                                            ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($tr['loc_name']); ?></b></td>
                                                <td><span style="font-size:11px;background:#e8eaf6;padding:2px 6px;border-radius:4px;">L<?php echo $tr['depth']; ?></span></td>
                                                <td>&#x20B9;<?php echo inr_format($tr['target'], 2); ?></td>
                                                <td class="target-pct-display" data-napkin-sub="&#x20B9;<?php echo inr_format($tr['napkin_achieved'], 2); ?>" data-combined-sub="&#x20B9;<?php echo inr_format($tr['achieved'], 2); ?>">&#x20B9;<?php echo inr_format($tr['napkin_achieved'], 2); ?></td>
                                                <td class="target-pct-display"
                                                    data-napkin-sub="<?php echo $gapNapkin > 0 ? '−' : '+'; ?>&#x20B9;<?php echo inr_format(abs($gapNapkin), 2); ?>"
                                                    data-combined-sub="<?php echo $gapCombined > 0 ? '−' : '+'; ?>&#x20B9;<?php echo inr_format(abs($gapCombined), 2); ?>"
                                                    data-napkin-color="<?php echo $gapNapkin > 0 ? '#c62828' : '#2e7d32'; ?>"
                                                    data-combined-color="<?php echo $gapCombined > 0 ? '#c62828' : '#2e7d32'; ?>"
                                                    style="color:<?php echo $gapNapkin > 0 ? '#c62828' : '#2e7d32'; ?>">
                                                    <?php echo $gapNapkin > 0 ? '−' : '+'; ?>&#x20B9;<?php echo inr_format(abs($gapNapkin), 2); ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress-bar-mis" style="width:100px"><div class="progress-fill target-pct-bar" style="width:<?php echo min($pctNapkin,100); ?>%;background:<?php echo $barNapkin; ?>" data-napkin-width="<?php echo min($pctNapkin,100); ?>" data-combined-width="<?php echo min($pctCombined,100); ?>" data-napkin-color="<?php echo $barNapkin; ?>" data-combined-color="<?php echo $barCombined; ?>"></div></div>
                                                        <span class="target-pct-display" style="font-size:13px;font-weight:700;color:<?php echo $barNapkin; ?>" data-napkin-sub="<?php echo $pctNapkin; ?>%" data-combined-sub="<?php echo $pctCombined; ?>%" data-napkin-color="<?php echo $barNapkin; ?>" data-combined-color="<?php echo $barCombined; ?>"><?php echo $pctNapkin; ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if ($unlocated_achieved > 0): ?>
                                        <tr>
                                            <td><b>Customer Sales</b> <span style="font-size:11px;color:var(--ink-faint);">(unassigned)</span></td>
                                            <td><span style="font-size:11px;background:#f1f0fb;color:var(--ink-faint);padding:2px 6px;border-radius:4px;">—</span></td>
                                            <td style="color:var(--ink-faint);">—</td>
                                            <td class="target-pct-display" data-napkin-sub="&#x20B9;<?php echo inr_format($unlocated_napkin_achieved, 2); ?>" data-combined-sub="&#x20B9;<?php echo inr_format($unlocated_achieved, 2); ?>">&#x20B9;<?php echo inr_format($unlocated_napkin_achieved, 2); ?></td>
                                            <td style="color:var(--ink-faint);">—</td>
                                            <td style="color:var(--ink-faint);">Counted in overall total only</td>
                                        </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                    (function () {
                        var toggle = document.getElementById('targetDiaperToggle');
                        if (!toggle) return;
                        toggle.addEventListener('change', function () {
                            var includeDiaper = this.checked;
                            document.querySelectorAll('.target-pct-display').forEach(function (el) {
                                var text = includeDiaper
                                    ? (el.dataset.combinedPct !== undefined ? el.dataset.combinedPct : el.dataset.combinedSub)
                                    : (el.dataset.napkinPct !== undefined ? el.dataset.napkinPct : el.dataset.napkinSub);
                                if (text !== undefined) el.textContent = text;
                                var color = includeDiaper ? el.dataset.combinedColor : el.dataset.napkinColor;
                                if (color) el.style.color = color;
                            });
                            document.querySelectorAll('.target-pct-bar').forEach(function (el) {
                                var width = includeDiaper ? el.dataset.combinedWidth : el.dataset.napkinWidth;
                                var color = includeDiaper ? el.dataset.combinedColor : el.dataset.napkinColor;
                                if (width !== undefined) el.style.width = width + '%';
                                if (color) el.style.background = color;
                            });
                        });
                    })();
                    </script>

                    <!-- ══════════════════════════════════════════════════════
                         6-MONTH GROWTH TREND
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-7 mb-4">
                            <div class="card">
                                <div class="card-header"><span class="hdr-icon chip-indigo"><i class="material-icons-outlined">trending_up</i></span><h5 class="card-title">6-Month Growth Trend</h5></div>
                                <div class="card-body">
                                    <div class="chart-container"><canvas id="growthChart"></canvas></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-5 mb-4">
                            <div class="card">
                                <div class="card-header"><span class="hdr-icon chip-teal"><i class="material-icons-outlined">table_chart</i></span><h5 class="card-title">Month-over-Month Summary</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($six_months)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">bar_chart</i>No data available.</div>
                                    <?php else: ?>
                                    <table class="mis-table">
                                        <thead><tr><th>Month</th><th>Revenue</th><th>Invoices</th><th>Growth</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($six_months as $m): ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($m['lbl']); ?></b></td>
                                                <td>&#x20B9;<?php echo inr_format($m['total_rev'], 0); ?></td>
                                                <td><?php echo (int)$m['total_cnt']; ?></td>
                                                <td>
                                                    <?php if ($m['growth'] === null): ?>
                                                        <span style="color:#888">—</span>
                                                    <?php elseif ($m['growth'] >= 0): ?>
                                                        <span class="growth-pos">▲ <?php echo $m['growth']; ?>%</span>
                                                    <?php else: ?>
                                                        <span class="growth-neg">▼ <?php echo abs($m['growth']); ?>%</span>
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

                    <!-- ══════════════════════════════════════════════════════
                         RETURNS & CANCELLATIONS
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <span class="hdr-icon chip-rose"><i class="material-icons-outlined">keyboard_return</i></span>
                                    <h5 class="card-title">Returns &amp; Credit Notes
                                        <span class="status-badge badge-unpaid" style="margin-left:8px;">
                                            <?php echo $total_returns; ?> returns — &#x20B9;<?php echo inr_format($total_return_amt, 2); ?>
                                        </span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($returns_list)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">check_circle</i>No returns in this period.</div>
                                    <?php else: ?>
                                    <div style="overflow-x:auto">
                                    <table class="mis-table">
                                        <thead><tr><th>Return ID</th><th>Invoice No.</th><th>From</th><th>Date</th><th>Amount</th><th>Status</th><th>Detail</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($returns_list as $r): ?>
                                            <tr>
                                                <td><small><?php echo htmlspecialchars($r['returnid']); ?></small></td>
                                                <td><?php echo htmlspecialchars($r['inv_number'] ?? $r['invnumber']); ?></td>
                                                <td><?php echo ucfirst(str_replace('_',' ',$r['from_usertype'])); ?></td>
                                                <td><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                                                <td><span class="badge-rev">&#x20B9;<?php echo inr_format($r['total'], 2); ?></span></td>
                                                <td>
                                                    <?php if ($r['status'] === 'pending'): ?>
                                                    <span class="status-badge badge-partial">Pending</span>
                                                    <?php else: ?>
                                                    <span class="status-badge badge-paid"><?php echo ucfirst($r['status']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><a href="cnote_details.php?returnid=<?php echo base64_encode($r['returnid']); ?>">View</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

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
// ── Tab switching (scoped per tab-nav group so Sales and Purchase tabs don't clash)
document.querySelectorAll('.tab-nav').forEach(function(nav) {
    nav.querySelectorAll('.tab-item').forEach(function(t) {
        t.addEventListener('click', function() {
            nav.querySelectorAll('.tab-item').forEach(function(x) { x.classList.remove('active'); });
            t.classList.add('active');
            var tab = document.getElementById('tab-' + t.dataset.tab);
            if (tab) {
                tab.parentElement.querySelectorAll(':scope > .tab-content').forEach(function(x) { x.classList.remove('active'); });
                tab.classList.add('active');
            }
        });
    });
});

// ── Chart defaults
Chart.defaults.font.family = 'Poppins, sans-serif';
Chart.defaults.font.size   = 12;

// ── 1. Daily Trend Chart
(function() {
    var ctx = document.getElementById('trendChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo $j_labels; ?>,
            datasets: [
                {
                    label: 'Customer Sales',
                    data: <?php echo $j_cust; ?>,
                    borderColor: '#3f51b5', backgroundColor: 'rgba(63,81,181,0.08)',
                    tension: 0.3, fill: true, pointRadius: 3
                },
                {
                    label: 'Shop Sales',
                    data: <?php echo $j_shop; ?>,
                    borderColor: '#ef6c00', backgroundColor: 'rgba(239,108,0,0.08)',
                    tension: 0.3, fill: true, pointRadius: 3
                },
                {
                    label: 'Purchases',
                    data: <?php echo $j_purch; ?>,
                    borderColor: '#7e57c2', backgroundColor: 'rgba(126,87,194,0.08)',
                    borderDash: [5,3],
                    tension: 0.3, fill: true, pointRadius: 3
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { ticks: { callback: function(v) { return '₹' + (v/1000).toFixed(0) + 'k'; } } }
            }
        }
    });
})();

// ── 2. Order Status Doughnut
(function() {
    var ctx = document.getElementById('statusChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Fully Paid', 'Partial', 'Unpaid'],
            datasets: [{
                data: [<?php echo $os_paid; ?>, <?php echo $os_partial; ?>, <?php echo $os_unpaid; ?>],
                backgroundColor: ['#4caf50','#ff9800','#f44336'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '65%',
            plugins: { legend: { position: 'bottom' } }
        }
    });
})();

// ── 3. Product Mix Doughnut
(function() {
    var ctx = document.getElementById('productChart');
    if (!ctx) return;
    var labels = <?php echo $j_plabels; ?>.slice(0,8);
    var data   = <?php echo $j_pqty; ?>.slice(0,8);
    var colors = ['#3f51b5','#e53935','#ef6c00','#2e7d32','#7b1fa2','#00838f','#c62828','#f57f17'];
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{ data: data, backgroundColor: colors, borderWidth: 0 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '60%',
            plugins: { legend: { position: 'right', labels: { font: { size: 11 } } } }
        }
    });
})();

// ── 4. 6-Month Growth Bar
(function() {
    var ctx = document.getElementById('growthChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo $j_glabels; ?>,
            datasets: [{
                label: 'Monthly Revenue',
                data: <?php echo $j_gvals; ?>,
                backgroundColor: 'rgba(63,81,181,0.75)',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { ticks: { callback: function(v) { return '₹' + (v/1000).toFixed(0) + 'k'; } } }
            }
        }
    });
})();
</script>
</body>
</html>
