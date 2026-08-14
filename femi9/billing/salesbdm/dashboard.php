<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
require_once("include/TeamSubtree.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// "View as" mode — a manager drilling into a team member's own dashboard
// from Our Team Report sees this exact page, scoped to that person instead
// of themselves. Only allowed for someone in the viewer's own reporting
// chain (never an arbitrary id typed into the URL).
$effectiveBdmId = (int)$salesBdmID;
$viewingOther = false;
$viewBdmName = '';
$viewBackUrl = 'my-team-report.php';
// Company staff viewing this BDM's own dashboard via the read-only bridge
// (checksession.php) — already resolved to this BDM's own id, so treat it
// the same as a manager's "view as", just with a different back-link.
if (!empty($_companyBridgeView)) {
    $viewingOther = true;
    $viewBdmName = $result_LoGuserDtails['bdm_name'] ?? '';
    $viewBackUrl = '../company/salesbdm-team-report.php';
}
if (!empty($_GET['view_bdm_id'])) {
    $requestedId = (int)$_GET['view_bdm_id'];
    if ($requestedId > 0 && $requestedId !== (int)$salesBdmID) {
        $mySubtree = getBdmSubtreeIds($db_conn, (int)$salesBdmID);
        if (in_array($requestedId, $mySubtree, true)) {
            $effectiveBdmId = $requestedId;
            $viewingOther = true;
            $nameRow = $db_conn->query("SELECT bdm_name FROM sales_bdm_staff WHERE id=" . $requestedId)->fetch_assoc();
            $viewBdmName = $nameRow['bdm_name'] ?? '';
        }
    }
}

// ── DB helpers (copied verbatim from company/mis-report.php) ───────────────
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

// ── This BDM's assigned TPs (district-name matched, see BdmTpScope.php) ────
$tpIds = getBdmAssignedTpIds($db_conn, $effectiveBdmId);
$hasTps = !empty($tpIds);
$tpIdList = $hasTps ? implode(',', array_map('intval', $tpIds)) : '0';

$tpNameMap = [];
if ($hasTps) {
    $tpRows = call_rows($db_conn, "SELECT id, name FROM territory_partners WHERE id IN ($tpIdList) ORDER BY name ASC");
    foreach ($tpRows as $r) { $tpNameMap[(int)$r['id']] = $r['name']; }
}

// A specific TP picked from the filter dropdown narrows every query below to
// just that one TP — 0 (or anything not actually assigned to this BDM) means
// "all of my TPs", same as company MIS report's "All Territory Partners".
$filter_tp_id = (int)($_GET['tp_id'] ?? 0);
if ($filter_tp_id > 0 && in_array($filter_tp_id, $tpIds, true)) {
    $tpIdList = (string)$filter_tp_id;
} else {
    $filter_tp_id = 0;
}

// ── Date range & presets (same UX as company MIS report) ───────────────────
$preset = $_GET['preset'] ?? 'month';
$today  = date('Y-m-d');
switch ($preset) {
    case 'today': $df = $today; $dt = $today; break;
    case 'week':  $df = date('Y-m-d', strtotime('monday this week')); $dt = date('Y-m-d', strtotime('sunday this week')); break;
    case 'year':  $df = date('Y-01-01'); $dt = date('Y-12-31'); break;
    default:      $df = date('Y-m-01'); $dt = date('Y-m-t');
}
$from = isset($_GET['from']) && $_GET['from'] ? date('Y-m-d', strtotime($_GET['from'])) : $df;
$to   = isset($_GET['to'])   && $_GET['to']   ? date('Y-m-d', strtotime($_GET['to']))   : $dt;

$days_diff = (strtotime($to) - strtotime($from)) / 86400;
$prev_from = date('Y-m-d', strtotime($from) - ($days_diff + 1) * 86400);
$prev_to   = date('Y-m-d', strtotime($from) - 86400);

$gross_revenue = 0.0; $total_revenue = 0.0; $total_invoices = 0; $total_units = 0;
$total_returns = 0; $total_return_amt = 0.0; $total_return_qty = 0.0; $net_units = 0;
$revenue_growth = 0; $product_sales = []; $returns_by_pid = [];
$purchaseRows = []; $purchaseReturnByTp = []; $downstreamByTp = []; $downstreamReturnByTp = [];
$overall_target = 0.0; $overall_achieved = 0.0; $overall_target_pct = 0;

if ($hasTps) {
    // ═══ Overview — "TP → downstream sales" (TP selling onward to shops/customers) ═══
    $cust_row = crow($db_conn,
        "SELECT COUNT(*) cnt, COALESCE(SUM(total-courier_charges),0) rev FROM invoice
         WHERE user_type='territory_partner' AND sub_total>0 AND `date` BETWEEN ? AND ? AND user_id IN ($tpIdList)",
        'ss', [$from, $to]);
    $shop_row = crow($db_conn,
        "SELECT COUNT(*) cnt, COALESCE(SUM(total-courier_charges),0) rev FROM user_invoice
         WHERE from_user_type='territory_partner' AND sub_total>0 AND `date` BETWEEN ? AND ? AND from_user_id IN ($tpIdList)",
        'ss', [$from, $to]);
    $total_invoices = (int)$cust_row['cnt'] + (int)$shop_row['cnt'];
    $total_revenue  = (float)$cust_row['rev'] + (float)$shop_row['rev'];

    $cust_units = (int)cval($db_conn,
        "SELECT COALESCE(SUM(ii.qty),0) FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
         WHERE i.user_type='territory_partner' AND i.date BETWEEN ? AND ? AND i.user_id IN ($tpIdList)",
        'ss', [$from, $to]);
    $shop_units = (int)cval($db_conn,
        "SELECT COALESCE(SUM(uii.qty),0) FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
         WHERE ui.from_user_type='territory_partner' AND ui.date BETWEEN ? AND ? AND ui.from_user_id IN ($tpIdList)",
        'ss', [$from, $to]);
    $total_units = $cust_units + $shop_units;

    $returns_row = crow($db_conn,
        "SELECT COUNT(*) cnt, COALESCE(SUM(total),0) amount FROM (
            SELECT returnid, MAX(total) total FROM user_return_stock
            WHERE to_usertype='territory_partner' AND to_userid IN ($tpIdList) AND `date` BETWEEN ? AND ?
            GROUP BY returnid
         ) x",
        'ss', [$from, $to]);
    $total_returns    = (int)$returns_row['cnt'];
    $total_return_amt = (float)$returns_row['amount'];

    $gross_revenue  = $total_revenue;
    $total_revenue -= $total_return_amt;

    $prev_revenue_row = crow($db_conn,
        "SELECT COALESCE(SUM(total-courier_charges),0) rev FROM invoice
         WHERE user_type='territory_partner' AND sub_total>0 AND `date` BETWEEN ? AND ? AND user_id IN ($tpIdList)",
        'ss', [$prev_from, $prev_to]);
    $prev_shop_row = crow($db_conn,
        "SELECT COALESCE(SUM(total-courier_charges),0) rev FROM user_invoice
         WHERE from_user_type='territory_partner' AND sub_total>0 AND `date` BETWEEN ? AND ? AND from_user_id IN ($tpIdList)",
        'ss', [$prev_from, $prev_to]);
    $prev_return_amt = (float)cval($db_conn,
        "SELECT COALESCE(SUM(total),0) FROM (
            SELECT returnid, MAX(total) total FROM user_return_stock
            WHERE to_usertype='territory_partner' AND to_userid IN ($tpIdList) AND `date` BETWEEN ? AND ?
            GROUP BY returnid
         ) x",
        'ss', [$prev_from, $prev_to]);
    $prev_revenue = (float)($prev_revenue_row['rev'] ?? 0) + (float)($prev_shop_row['rev'] ?? 0) - $prev_return_amt;
    $revenue_growth = $prev_revenue > 0 ? round((($total_revenue - $prev_revenue) / $prev_revenue) * 100, 1) : 0;

    // ═══ District Total Target — every Firka's target_amount within this
    // BDM's assigned districts, regardless of whether that Firka currently
    // has a TP, or whether that TP is active/inactive. This is the full
    // district-level potential. Restricted to pll.is_tp_filter_enabled=1
    // + pln.is_active=1 to match exactly what company's Add Territory Partner
    // location picker (get-tp-flat-nodes.php) shows/lets a TP be assigned —
    // otherwise target_amount set at a non-assignable depth (e.g. Division)
    // gets double-counted on top of the Firka figure.
    $district_total_target = 0.0;
    $_districtDepthRow = crow($db_conn, "SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1");
    $_districtDepth = (int)($_districtDepthRow['depth'] ?? 0);
    $_districtNames = getBdmAssignedDistrictNames($db_conn, $effectiveBdmId);
    if ($_districtDepth && !empty($_districtNames)) {
        $_dn = array_map(fn($n) => mb_strtolower(trim($n)), $_districtNames);
        $_ph = implode(',', array_fill(0, count($_dn), '?'));
        $_types = 'i' . str_repeat('s', count($_dn));
        $_params = array_merge([$_districtDepth], $_dn);
        $district_total_target = (float)cval($db_conn,
            "WITH RECURSIVE district_tree AS (
                SELECT id FROM partner_location_nodes WHERE depth = ? AND LOWER(TRIM(name)) IN ($_ph)
                UNION ALL
                SELECT n.id FROM partner_location_nodes n JOIN district_tree dt ON n.parent_id = dt.id
             )
             SELECT COALESCE(SUM(pln.target_amount),0) FROM partner_location_nodes pln
             JOIN partner_location_layers pll ON pll.depth = pln.depth
             WHERE pln.id IN (SELECT id FROM district_tree) AND pll.is_tp_filter_enabled = 1 AND pln.is_active = 1",
            $_types, $_params);
    }

    // ═══ District / Firka coverage counts — how many districts are assigned
    // to this BDM, how many Firkas exist across them in total, and of those,
    // how many have at least one Territory Partner assigned vs none at all.
    $district_count = count($_districtNames);
    $firka_total_count = 0;
    $firka_filled_count = 0;
    if ($_districtDepth && !empty($_districtNames)) {
        $_firkaRow = crow($db_conn,
            "WITH RECURSIVE district_tree AS (
                SELECT id FROM partner_location_nodes WHERE depth = ? AND LOWER(TRIM(name)) IN ($_ph)
                UNION ALL
                SELECT n.id FROM partner_location_nodes n JOIN district_tree dt ON n.parent_id = dt.id
             )
             SELECT
                 COUNT(*) AS total_firkas,
                 COUNT(DISTINCT tpl.location_id) AS filled_firkas
             FROM partner_location_nodes pln
             JOIN partner_location_layers pll ON pll.depth = pln.depth
             LEFT JOIN territory_partner_locations tpl ON tpl.location_id = pln.id
             WHERE pln.id IN (SELECT id FROM district_tree) AND pll.is_tp_filter_enabled = 1 AND pln.is_active = 1",
            $_types, $_params);
        $firka_total_count  = (int)($_firkaRow['total_firkas'] ?? 0);
        $firka_filled_count = (int)($_firkaRow['filled_firkas'] ?? 0);
    }
    $firka_unassigned_count = $firka_total_count - $firka_filled_count;

    // ═══ District Total Target, split by Firka TP status — Active (has an
    // active TP), Inactive (TP assigned but none active), Unassigned (no TP
    // at all). The three always add up to $district_total_target above.
    $target_active_amount = 0.0;
    $target_inactive_amount = 0.0;
    $target_unassigned_amount = 0.0;
    if ($_districtDepth && !empty($_districtNames)) {
        $_targetSplitRows = call_rows($db_conn,
            "WITH RECURSIVE district_tree AS (
                SELECT id FROM partner_location_nodes WHERE depth = ? AND LOWER(TRIM(name)) IN ($_ph)
                UNION ALL
                SELECT n.id FROM partner_location_nodes n JOIN district_tree dt ON n.parent_id = dt.id
             )
             SELECT pln.id, pln.target_amount, MAX(tp.is_active) AS has_active_tp, COUNT(tpl.location_id) AS tp_count
             FROM partner_location_nodes pln
             JOIN partner_location_layers pll ON pll.depth = pln.depth
             LEFT JOIN territory_partner_locations tpl ON tpl.location_id = pln.id
             LEFT JOIN territory_partners tp ON tp.id = tpl.territory_partner_id
             WHERE pln.id IN (SELECT id FROM district_tree) AND pll.is_tp_filter_enabled = 1 AND pln.is_active = 1
             GROUP BY pln.id, pln.target_amount",
            $_types, $_params);
        foreach ($_targetSplitRows as $_r) {
            $_val = (float)($_r['target_amount'] ?? 0);
            if ((int)($_r['tp_count'] ?? 0) === 0) { $target_unassigned_amount += $_val; }
            elseif ((int)($_r['has_active_tp'] ?? 0) === 1) { $target_active_amount += $_val; }
            else { $target_inactive_amount += $_val; }
        }
    }

    // ═══ Overall Target % — target is the SAME "Active" figure as the
    // District Total Target card above ($target_active_amount: every Firka
    // in your districts that currently has an active TP). Kept as one
    // number so the two cards can never show a different target for what is
    // the same underlying scope — a mismatch used to happen here because
    // this used to be computed bottom-up (TP → its assigned locations,
    // matched by TP ownership) while District Total Target is computed
    // top-down (district tree → Firka → is there an active TP), and a TP's
    // free-text branch_district doesn't always agree with which district
    // tree its assigned Firka actually falls under.
    $overall_target = $target_active_amount;
    // Target achievement counts Napkin-category products only — Lumi Baby
    // Diaper sales/purchases don't count toward the Firka target, even though
    // they still show in Sales/Turnover/Purchases figures elsewhere on this page.
    $napkin_achieved_cust = (float)cval($db_conn,
        "SELECT COALESCE(SUM(ii.total),0) FROM invoice_items ii
         JOIN invoice i ON i.inv_id=ii.inv_id JOIN products p ON p.id=ii.pr_id
         WHERE i.user_type='territory_partner' AND i.sub_total>0 AND i.date BETWEEN ? AND ?
           AND i.user_id IN ($tpIdList) AND COALESCE(p.category,'') != 'diaper'",
        'ss', [$from, $to]);
    $napkin_achieved_shop = (float)cval($db_conn,
        "SELECT COALESCE(SUM(uii.total),0) FROM user_invoice_items uii
         JOIN user_invoice ui ON ui.inv_id=uii.inv_id JOIN products p ON p.id=uii.pr_id
         WHERE ui.from_user_type='territory_partner' AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?
           AND ui.from_user_id IN ($tpIdList) AND COALESCE(p.category,'') != 'diaper'",
        'ss', [$from, $to]);
    $overall_achieved = $napkin_achieved_cust + $napkin_achieved_shop;
    $overall_target_pct = $overall_target > 0 ? min(round($overall_achieved / $overall_target * 100, 1), 999) : 0;

    // ═══ Products — downstream sold + returned, across all assigned TPs ═══
    // Split by channel (Customer via `invoice`, Shop via `user_invoice`) so the
    // Products table can show a Customer/Shop breakdown per product.
    $product_sales_cust = call_rows($db_conn,
        "SELECT p.id pid, p.productName, COALESCE(SUM(ii.qty),0) qty, COALESCE(SUM(ii.total),0) rev
         FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id JOIN products p ON p.id=ii.pr_id
         WHERE i.user_type='territory_partner' AND i.date BETWEEN ? AND ? AND i.user_id IN ($tpIdList)
         GROUP BY p.id, p.productName",
        'ss', [$from, $to]);
    $product_sales_shop = call_rows($db_conn,
        "SELECT p.id pid, p.productName, COALESCE(SUM(uii.qty),0) qty, COALESCE(SUM(uii.total),0) rev
         FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id JOIN products p ON p.id=uii.pr_id
         WHERE ui.from_user_type='territory_partner' AND ui.date BETWEEN ? AND ? AND ui.from_user_id IN ($tpIdList)
         GROUP BY p.id, p.productName",
        'ss', [$from, $to]);
    $product_sales_map = [];
    foreach ($product_sales_cust as $r) {
        $pid = (int)$r['pid'];
        $product_sales_map[$pid]['pid'] = $pid;
        $product_sales_map[$pid]['productName'] = $r['productName'];
        $product_sales_map[$pid]['cust_qty'] = (float)$r['qty'];
        $product_sales_map[$pid]['cust_rev'] = (float)$r['rev'];
        $product_sales_map[$pid]['total_qty'] = ($product_sales_map[$pid]['total_qty'] ?? 0) + (float)$r['qty'];
        $product_sales_map[$pid]['total_rev'] = ($product_sales_map[$pid]['total_rev'] ?? 0) + (float)$r['rev'];
    }
    foreach ($product_sales_shop as $r) {
        $pid = (int)$r['pid'];
        $product_sales_map[$pid]['pid'] = $pid;
        $product_sales_map[$pid]['productName'] = $r['productName'];
        $product_sales_map[$pid]['shop_qty'] = (float)$r['qty'];
        $product_sales_map[$pid]['shop_rev'] = (float)$r['rev'];
        $product_sales_map[$pid]['total_qty'] = ($product_sales_map[$pid]['total_qty'] ?? 0) + (float)$r['qty'];
        $product_sales_map[$pid]['total_rev'] = ($product_sales_map[$pid]['total_rev'] ?? 0) + (float)$r['rev'];
    }
    $product_sales_map = array_values($product_sales_map);
    usort($product_sales_map, function ($a, $b) { return ($b['total_qty'] ?? 0) <=> ($a['total_qty'] ?? 0); });
    $product_sales = array_slice($product_sales_map, 0, 25);

    $product_returns_cust = call_rows($db_conn,
        "SELECT ri.prid pid, COALESCE(SUM(ri.qty),0) qty, COALESCE(SUM(ri.total),0) amt
         FROM user_return_stock_items ri
         WHERE ri.to_usertype='territory_partner' AND ri.to_userid IN ($tpIdList)
           AND ri.from_usertype='customer' AND ri.date BETWEEN ? AND ?
         GROUP BY ri.prid",
        'ss', [$from, $to]);
    $product_returns_shop = call_rows($db_conn,
        "SELECT ri.prid pid, COALESCE(SUM(ri.qty),0) qty, COALESCE(SUM(ri.total),0) amt
         FROM user_return_stock_items ri
         WHERE ri.to_usertype='territory_partner' AND ri.to_userid IN ($tpIdList)
           AND ri.from_usertype='shop' AND ri.date BETWEEN ? AND ?
         GROUP BY ri.prid",
        'ss', [$from, $to]);
    foreach ($product_returns_cust as $r) {
        $pid = (int)$r['pid'];
        $returns_by_pid[$pid]['cust_qty'] = (float)$r['qty'];
        $returns_by_pid[$pid]['cust_amt'] = (float)$r['amt'];
        $returns_by_pid[$pid]['qty'] = ($returns_by_pid[$pid]['qty'] ?? 0) + (float)$r['qty'];
        $returns_by_pid[$pid]['amt'] = ($returns_by_pid[$pid]['amt'] ?? 0) + (float)$r['amt'];
    }
    foreach ($product_returns_shop as $r) {
        $pid = (int)$r['pid'];
        $returns_by_pid[$pid]['shop_qty'] = (float)$r['qty'];
        $returns_by_pid[$pid]['shop_amt'] = (float)$r['amt'];
        $returns_by_pid[$pid]['qty'] = ($returns_by_pid[$pid]['qty'] ?? 0) + (float)$r['qty'];
        $returns_by_pid[$pid]['amt'] = ($returns_by_pid[$pid]['amt'] ?? 0) + (float)$r['amt'];
    }
    $total_return_qty = (float)array_sum(array_column($returns_by_pid, 'qty'));
    $net_units = $total_units - $total_return_qty;

    // ═══ "Purchases from Company" — per assigned TP ═══
    // Includes purchases billed by either Company or a Super Stockist —
    // both are "upstream of the TP" from a Sales BDM's point of view.
    $purchaseRows = call_rows($db_conn,
        "SELECT ti.territory_partner_id tp_id,
                COALESCE(SUM(tii.quantity),0) qty,
                COALESCE(SUM(tii.amount),0) amt
         FROM tp_invoice_items tii JOIN tp_invoices ti ON ti.id=tii.tp_invoice_id
         WHERE ti.territory_partner_id IN ($tpIdList) AND ti.invoice_date BETWEEN ? AND ?
         GROUP BY ti.territory_partner_id
         ORDER BY amt DESC",
        'ss', [$from, $to]);

    $purchaseReturnRows = call_rows($db_conn,
        "SELECT ri.from_userid tp_id, COALESCE(SUM(ri.qty),0) qty FROM user_return_stock_items ri
         WHERE ri.from_usertype='territory_partner' AND ri.from_userid IN ($tpIdList)
           AND ri.to_usertype='company' AND ri.date BETWEEN ? AND ?
         GROUP BY ri.from_userid",
        'ss', [$from, $to]);
    foreach ($purchaseReturnRows as $r) { $purchaseReturnByTp[(int)$r['tp_id']] = (float)$r['qty']; }

    // Napkin-only purchased amount per TP — used for the Target/Achievement/
    // Balance columns below (Lumi Baby Diaper purchases don't count toward
    // the Firka target); "Value Purchased" itself still shows the full amount.
    $napkinPurchaseByTp = [];
    $napkinPurchaseRows = call_rows($db_conn,
        "SELECT ti.territory_partner_id tp_id, COALESCE(SUM(tii.amount),0) amt
         FROM tp_invoice_items tii JOIN tp_invoices ti ON ti.id=tii.tp_invoice_id
         JOIN products p ON p.id = tii.product_id
         WHERE ti.territory_partner_id IN ($tpIdList) AND ti.invoice_date BETWEEN ? AND ? AND COALESCE(p.category,'') != 'diaper'
         GROUP BY ti.territory_partner_id",
        'ss', [$from, $to]);
    foreach ($napkinPurchaseRows as $r) { $napkinPurchaseByTp[(int)$r['tp_id']] = (float)$r['amt']; }

    // Per-TP Firka/location name + target amount — same join pattern as the
    // page-wide $overall_target calc, just grouped per TP instead of summed.
    $tpTargetByTp = []; $tpLocByTp = [];
    $tpTargetRows = call_rows($db_conn,
        "SELECT tpl.territory_partner_id tp_id,
                COALESCE(SUM(pln.target_amount),0) target,
                GROUP_CONCAT(DISTINCT pln.name ORDER BY pln.name SEPARATOR ', ') loc_names
         FROM territory_partner_locations tpl
         JOIN partner_location_nodes pln ON pln.id = tpl.location_id
         WHERE tpl.territory_partner_id IN ($tpIdList)
         GROUP BY tpl.territory_partner_id");
    foreach ($tpTargetRows as $r) {
        $tid = (int)$r['tp_id'];
        $tpTargetByTp[$tid] = (float)$r['target'];
        $tpLocByTp[$tid] = $r['loc_names'];
    }

    // ═══ "Your Sales via TP" — downstream sales + returns, per assigned TP ═══
    $downstreamCust = call_rows($db_conn,
        "SELECT i.user_id tp_id, COUNT(*) cnt, COALESCE(SUM(i.total-i.courier_charges),0) rev, COALESCE(SUM(ii.qty),0) qty
         FROM invoice i JOIN invoice_items ii ON ii.inv_id=i.inv_id
         WHERE i.user_type='territory_partner' AND i.sub_total>0 AND i.date BETWEEN ? AND ? AND i.user_id IN ($tpIdList)
         GROUP BY i.user_id",
        'ss', [$from, $to]);
    $downstreamShop = call_rows($db_conn,
        "SELECT ui.from_user_id tp_id, COUNT(*) cnt, COALESCE(SUM(ui.total-ui.courier_charges),0) rev, COALESCE(SUM(uii.qty),0) qty
         FROM user_invoice ui JOIN user_invoice_items uii ON uii.inv_id=ui.inv_id
         WHERE ui.from_user_type='territory_partner' AND ui.sub_total>0 AND ui.date BETWEEN ? AND ? AND ui.from_user_id IN ($tpIdList)
         GROUP BY ui.from_user_id",
        'ss', [$from, $to]);
    foreach ($downstreamCust as $r) {
        $tid = (int)$r['tp_id'];
        $downstreamByTp[$tid]['rev'] = ($downstreamByTp[$tid]['rev'] ?? 0) + (float)$r['rev'];
        $downstreamByTp[$tid]['qty'] = ($downstreamByTp[$tid]['qty'] ?? 0) + (float)$r['qty'];
        $downstreamByTp[$tid]['cust_rev'] = (float)$r['rev'];
        $downstreamByTp[$tid]['cust_qty'] = (float)$r['qty'];
    }
    foreach ($downstreamShop as $r) {
        $tid = (int)$r['tp_id'];
        $downstreamByTp[$tid]['rev'] = ($downstreamByTp[$tid]['rev'] ?? 0) + (float)$r['rev'];
        $downstreamByTp[$tid]['qty'] = ($downstreamByTp[$tid]['qty'] ?? 0) + (float)$r['qty'];
        $downstreamByTp[$tid]['shop_rev'] = (float)$r['rev'];
        $downstreamByTp[$tid]['shop_qty'] = (float)$r['qty'];
    }

    $downstreamReturnRows = call_rows($db_conn,
        "SELECT returnid, to_userid tp_id, MAX(total) total FROM user_return_stock
         WHERE to_usertype='territory_partner' AND to_userid IN ($tpIdList) AND `date` BETWEEN ? AND ?
         GROUP BY returnid, to_userid",
        'ss', [$from, $to]);
    foreach ($downstreamReturnRows as $r) {
        $tid = (int)$r['tp_id'];
        $downstreamReturnByTp[$tid]['amt'] = ($downstreamReturnByTp[$tid]['amt'] ?? 0) + (float)$r['total'];
    }
    $downstreamReturnQtyRows = call_rows($db_conn,
        "SELECT to_userid tp_id, COALESCE(SUM(qty),0) qty FROM user_return_stock_items
         WHERE to_usertype='territory_partner' AND to_userid IN ($tpIdList) AND `date` BETWEEN ? AND ?
         GROUP BY to_userid",
        'ss', [$from, $to]);
    foreach ($downstreamReturnQtyRows as $r) {
        $tid = (int)$r['tp_id'];
        $downstreamReturnByTp[$tid]['qty'] = ($downstreamReturnByTp[$tid]['qty'] ?? 0) + (float)$r['qty'];
    }
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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        :root {
            --surface-1: #ffffff; --page-plane: #f7f7f6; --text-primary: #0b0b0b;
            --text-secondary: #52514e; --text-muted: #898781; --gridline: #e1e0d9; --border: rgba(11,11,11,0.10);
            --blue: #2a78d6; --blue-tint: #eaf2fc; --good: #0ca30c; --good-tint: #e5f7e5;
            --critical: #d03b3b; --critical-tint: #fbe6e6;
        }
        body { background: var(--page-plane); }
        .section-nav { position: sticky; top: 0; z-index: 20; background: var(--surface-1); border: 1px solid var(--border); border-radius: 10px; padding: 8px 10px; margin-bottom: 22px; display: flex; gap: 4px; overflow-x: auto; box-shadow: 0 1px 2px rgba(11,11,11,0.03); }
        .section-nav a { flex: 0 0 auto; padding: 7px 14px; border-radius: 7px; font-size: 12.5px; font-weight: 600; color: var(--text-secondary); text-decoration: none; white-space: nowrap; transition: background .12s, color .12s; }
        .section-nav a:hover { background: var(--page-plane); color: var(--text-primary); }
        .section-nav a.active { background: var(--blue-tint); color: var(--blue); }
        .mis-section { scroll-margin-top: 90px; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:8px 11px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:7px 11px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .mt tr:hover td { background:#f7f7f6; }
        .kpi-card { background: var(--surface-1); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px 18px 22px; position: relative; overflow: hidden; height: 100%; box-shadow: 0 1px 2px rgba(11,11,11,0.03); }
        .kpi-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background: var(--kpi-accent, var(--blue)); }
        .kpi-card .kpi-ico { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; background: var(--kpi-tint, var(--blue-tint)); color: var(--kpi-accent, var(--blue)); font-size:17px; position:absolute; right:16px; top:16px; }
        .kpi-card .kpi-t  { font-size: 11.5px; text-transform: uppercase; letter-spacing: .6px; font-weight:700; color: var(--text-secondary); padding-right:40px; }
        .equation-row { display:flex; align-items:stretch; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
        .equation-row .kpi-card { flex:1 1 220px; }
        .equation-op { display:flex; align-items:center; justify-content:center; font-size:26px; font-weight:300; color: var(--text-muted); flex:0 0 auto; padding:0 2px; }
        .equation-op.eq { color: var(--text-secondary); font-weight:600; }
        .kpi-multi { margin-top:10px; }
        .kpi-multi > div { display:flex; justify-content:space-between; align-items:baseline; padding:5px 0; border-bottom:1px dashed var(--gridline); font-size:13px; color: var(--text-secondary); }
        .kpi-multi > div:last-child { border-bottom:none; padding-top:8px; font-size:15px; }
        .kpi-multi > div:last-child b { font-size:17px; color: var(--text-primary); }
        .kpi-multi b { font-weight:700; color: var(--text-primary); }
        .mis-filter { background: var(--surface-1); border: 1px solid var(--border); border-radius: 10px; padding: 14px 18px; margin-bottom: 14px; }
        .preset-btn { padding:4px 13px; border-radius:20px; border:1.5px solid var(--blue); color:var(--blue); background:var(--surface-1); font-size:12px; cursor:pointer; text-decoration:none; display:inline-block; }
        .preset-btn.active, .preset-btn:hover { background:var(--blue); color:#fff; border-color:var(--blue); }
        .tp-name-cell { cursor: pointer; color: var(--blue); font-weight: 600; text-decoration: underline dotted; }
        .snote { font-size:12px; color:var(--text-muted); margin-bottom:10px; }
        .col-toggle-btn { border:1px solid var(--blue); background:#fff; color:var(--blue); font-size:10.5px; font-weight:700; padding:1px 7px; border-radius:10px; cursor:pointer; margin-top:3px; }
        .col-toggle-btn.active, .col-toggle-btn:hover { background:var(--blue); color:#fff; }
        .pbar { height:7px; border-radius:4px; background: var(--blue-tint); overflow:hidden; }
        .pbar .pf { height:100%; border-radius:4px; background: var(--blue); }

        /* ── Mobile responsiveness ─────────────────────────────────────── */
        @media (max-width: 768px) {
            .mis-filter form { flex-direction: column; align-items: stretch !important; }
            .mis-filter form > div { width: 100% !important; }
            .mis-filter select, .mis-filter input { width: 100% !important; }
            .mis-filter form > div[style*="margin-left:auto"] { margin-left: 0 !important; justify-content: flex-start !important; }
            .kpi-card { margin-bottom: 12px; }
            .section-nav { overflow-x: auto; flex-wrap: nowrap; -webkit-overflow-scrolling: touch; }
            .section-nav a { white-space: nowrap; }
            .equation-row { flex-direction: column; }
            .equation-row .equation-op { display: none; }
            .mt { font-size: 12px; }
        }
        @media (max-width: 480px) {
            .page-description h1 { font-size: 20px; }
            .kpi-multi b { font-size: 15px; }
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
                    <div class="row mb-2">
                        <div class="col">
                            <div class="page-description" style="margin-left:-10px;">
                                <h1>
                                    <i class="material-icons-outlined" style="vertical-align:middle;margin-right:6px;">dashboard</i>
                                    Dashboard — <?php echo $viewingOther ? htmlspecialchars($viewBdmName) . "'s TPs" : 'Your TPs'; ?>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <?php if ($viewingOther): ?>
                        <div class="alert alert-info" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
                            <span><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">visibility</i> Viewing <b><?php echo htmlspecialchars($viewBdmName); ?>'s</b> dashboard (read-only).</span>
                            <a href="<?php echo htmlspecialchars($viewBackUrl); ?>" class="btn btn-sm" style="background:#fff;color:#0b5ed7;font-weight:700;border:none;box-shadow:0 1px 4px rgba(0,0,0,.15);">&larr; Back to Our Team Report</a>
                        </div>
                    <?php endif; ?>

                    <?php if (!$hasTps): ?>
                        <div class="alert alert-info">No Territory Partners are assigned to your districts yet. Contact your admin if this looks wrong.</div>
                    <?php else: ?>

                    <div class="mis-filter">
                        <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Territory Partner</label>
                                <select name="tp_id" class="form-control form-control-sm" style="width:200px;" onchange="this.form.submit()">
                                    <option value="0">All Territory Partners</option>
                                    <?php foreach ($tpNameMap as $tid => $tname): ?>
                                        <option value="<?php echo $tid; ?>" <?php echo $filter_tp_id===$tid?'selected':''; ?>><?php echo htmlspecialchars($tname); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">From</label>
                                <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control form-control-sm" style="width:145px;">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">To</label>
                                <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control form-control-sm" style="width:145px;">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            </div>
                            <div style="margin-left:auto;display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap;">
                                <?php $presetQs = $filter_tp_id > 0 ? "&tp_id={$filter_tp_id}" : ''; ?>
                                <a href="?preset=today<?php echo $presetQs; ?>" class="preset-btn <?php echo $preset==='today'?'active':''; ?>">Today</a>
                                <a href="?preset=week<?php echo $presetQs; ?>" class="preset-btn <?php echo $preset==='week'?'active':''; ?>">This Week</a>
                                <a href="?preset=month<?php echo $presetQs; ?>" class="preset-btn <?php echo $preset==='month'?'active':''; ?>">This Month</a>
                                <a href="?preset=year<?php echo $presetQs; ?>" class="preset-btn <?php echo $preset==='year'?'active':''; ?>">This Year</a>
                            </div>
                        </form>
                        <div style="font-size:12px;color:#888;margin-top:7px;">
                            <?php if ($filter_tp_id>0): ?>
                            Filtered by TP: <b><?php echo htmlspecialchars($tpNameMap[$filter_tp_id] ?? ''); ?></b> &nbsp;|&nbsp;
                            <?php endif; ?>
                            Period: <b><?php echo date('d M Y', strtotime($from)); ?></b> to <b><?php echo date('d M Y', strtotime($to)); ?></b> &nbsp;|&nbsp;
                            <?php echo count($tpIds); ?> TP(s) in your districts
                        </div>
                    </div>

                    <!-- ── SECTION NAVIGATION (quick jump) ─────────────────── -->
                    <nav class="section-nav" id="sectionNav">
                        <a href="#sec-overview">Overview</a>
                        <a href="#sec-products">Products</a>
                        <a href="#sec-purchases">Purchases from Company</a>
                        <a href="#sec-yoursales">Your Sales via TP</a>
                    </nav>

                    <!-- ══ District / Firka coverage — how much of your territory has a TP yet ══ -->
                    <div class="row mb-3">
                        <div class="col-md-3 col-sm-6">
                            <div class="kpi-card" style="--kpi-accent:var(--blue);--kpi-tint:var(--blue-tint);">
                                <i class="material-icons-outlined kpi-ico">public</i>
                                <div class="kpi-t">Districts Assigned</div>
                                <div class="kpi-multi"><div><b><?php echo $district_count; ?></b></div></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="kpi-card" style="--kpi-accent:#6b7280;--kpi-tint:#f3f4f6;">
                                <i class="material-icons-outlined kpi-ico">grid_view</i>
                                <div class="kpi-t">Total Firkas</div>
                                <div class="kpi-multi"><div><b><?php echo $firka_total_count; ?></b></div></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="kpi-card" id="filledFirkasCard" style="--kpi-accent:var(--good);--kpi-tint:var(--good-tint);cursor:pointer;" title="Click to see the Territory Partners in your filled Firkas">
                                <i class="material-icons-outlined kpi-ico">check_circle</i>
                                <div class="kpi-t">Filled Firkas</div>
                                <div class="kpi-multi"><div><b><?php echo $firka_filled_count; ?></b></div></div>
                                <p class="snote" style="margin:6px 0 0;">Has at least one Territory Partner assigned.</p>
                                <button type="button" style="margin-top:8px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border:none;border-radius:6px;padding:6px 14px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:5px;">
                                    <i class="material-icons-outlined" style="font-size:15px;">visibility</i> View TPs
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="kpi-card" style="--kpi-accent:var(--critical);--kpi-tint:var(--critical-tint);">
                                <i class="material-icons-outlined kpi-ico">error_outline</i>
                                <div class="kpi-t">Unassigned Firkas</div>
                                <div class="kpi-multi"><div><b><?php echo $firka_unassigned_count; ?></b></div></div>
                                <p class="snote" style="margin:6px 0 0;">No Territory Partner assigned yet.</p>
                            </div>
                        </div>
                    </div>

                    <!-- ══ Overview — Sales / Returns / Total Turnover (your TPs' downstream sales) ══ -->
                    <div class="mis-section" id="sec-overview">
                    <h3 style="font-size:16px;font-weight:700;margin:6px 0 8px;">Overview — Your TPs' Downstream Sales</h3>
                    <div class="equation-row">
                        <div class="kpi-card" style="--kpi-accent:var(--blue);--kpi-tint:var(--blue-tint);">
                            <i class="material-icons-outlined kpi-ico">payments</i>
                            <div class="kpi-t">Sales</div>
                            <div class="kpi-multi">
                                <div><span>Amount</span><b>&#8377;<?php echo inr_format($gross_revenue, 0); ?></b></div>
                                <div><span>Invoices</span><b><?php echo inr_format($total_invoices, 0); ?></b></div>
                                <div><span>Units</span><b><?php echo inr_format($total_units, 0); ?></b></div>
                            </div>
                        </div>
                        <div class="equation-op">&minus;</div>
                        <div class="kpi-card" style="--kpi-accent:var(--critical);--kpi-tint:var(--critical-tint);">
                            <i class="material-icons-outlined kpi-ico">keyboard_return</i>
                            <div class="kpi-t">Returns</div>
                            <div class="kpi-multi">
                                <div><span>Amount</span><b>&#8377;<?php echo inr_format($total_return_amt, 0); ?></b></div>
                                <div><span>Returns</span><b><?php echo inr_format($total_returns, 0); ?></b></div>
                                <div><span>Quantity</span><b><?php echo inr_format($total_return_qty, 0); ?></b></div>
                            </div>
                        </div>
                        <div class="equation-op eq">=</div>
                        <div class="kpi-card" style="--kpi-accent:var(--good);--kpi-tint:var(--good-tint);">
                            <i class="material-icons-outlined kpi-ico">account_balance_wallet</i>
                            <div class="kpi-t">Total Turnover</div>
                            <div class="kpi-multi">
                                <div><span>Amount</span><b>&#8377;<?php echo inr_format($total_revenue, 0); ?></b></div>
                                <div><span>Quantity</span><b><?php echo inr_format($net_units, 0); ?></b></div>
                                <div><span>vs Prev Period</span><b style="color:<?php echo $revenue_growth>=0?'var(--good)':'var(--critical)'; ?>"><?php echo ($revenue_growth>=0?'&#9650; ':'&#9660; ').abs($revenue_growth).'%'; ?></b></div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ Overall Target % — Firka targets assigned to your TPs vs their achieved sales ══ -->
                    <?php
                        $tgtAccent = $overall_target_pct >= 100 ? 'var(--good)' : ($overall_target_pct >= 50 ? '#eab308' : 'var(--critical)');
                        $tgtTint   = $overall_target_pct >= 100 ? 'var(--good-tint)' : ($overall_target_pct >= 50 ? '#fef9c3' : 'var(--critical-tint)');
                    ?>
                    <div class="row mb-3">
                        <div class="col-md-5 col-sm-12">
                            <div class="kpi-card" style="--kpi-accent:<?php echo $tgtAccent; ?>;--kpi-tint:<?php echo $tgtTint; ?>;">
                                <i class="material-icons-outlined kpi-ico">flag</i>
                                <div class="kpi-t">Overall Target %</div>
                                <div class="kpi-multi">
                                    <div><span>Achieved</span><b>&#8377;<?php echo inr_format($overall_achieved, 0); ?></b></div>
                                    <div><span>Target</span><b>&#8377;<?php echo inr_format($overall_target, 0); ?></b></div>
                                    <div><span>%</span><b style="color:<?php echo $tgtAccent; ?>;"><?php echo $overall_target_pct; ?>%</b></div>
                                </div>
                                <p class="snote" style="margin:6px 0 0;">Achieved counts Napkin products only — Lumi Baby Diaper sales don't count toward the Firka target.</p>
                            </div>
                        </div>
                        <div class="col-md-5 col-sm-12">
                            <div class="kpi-card" style="--kpi-accent:var(--blue);--kpi-tint:var(--blue-tint);">
                                <i class="material-icons-outlined kpi-ico">map</i>
                                <div class="kpi-t">District Total Target</div>
                                <div class="kpi-multi">
                                    <div><span>All Firkas in your districts</span><b>&#8377;<?php echo inr_format($district_total_target, 0); ?></b></div>
                                    <div><span style="color:var(--good);">Active Firkas</span><b style="color:var(--good);">&#8377;<?php echo inr_format($target_active_amount, 0); ?></b></div>
                                    <div><span style="color:#eab308;">Inactive Firkas</span><b style="color:#eab308;">&#8377;<?php echo inr_format($target_inactive_amount, 0); ?></b></div>
                                    <div><span style="color:var(--critical);">Unassigned Firkas</span><b style="color:var(--critical);">&#8377;<?php echo inr_format($target_unassigned_amount, 0); ?></b></div>
                                </div>
                                <p class="snote" style="margin:6px 0 0;">Includes every Firka's target in your assigned districts — even ones with no TP, or an inactive TP.</p>
                            </div>
                        </div>
                    </div>

                    </div><!-- /#sec-overview -->

                    <!-- ══ Products ══ -->
                    <div class="mis-section" id="sec-products">
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title" style="margin:0;font-size:14px;">Products — Downstream Sales &amp; Returns</h5></div>
                                <div class="card-body" style="overflow-x:auto;">
                                    <table class="mt">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th class="col-cust-qty" style="display:none;">Customer Qty</th>
                                                <th class="col-shop-qty" style="display:none;">Shop Qty</th>
                                                <th>Total Qty<br><button type="button" class="col-toggle-btn" data-target="col-cust-qty col-shop-qty">Split</button></th>
                                                <th class="col-cust-svalue" style="display:none;">Customer Value</th>
                                                <th class="col-shop-svalue" style="display:none;">Shop Value</th>
                                                <th>Sales Value<br><button type="button" class="col-toggle-btn" data-target="col-cust-svalue col-shop-svalue">Value</button></th>
                                                <th class="col-cust-rqty" style="display:none;">Customer Ret. Qty</th>
                                                <th class="col-shop-rqty" style="display:none;">Shop Ret. Qty</th>
                                                <th>Total Ret. Qty<br><button type="button" class="col-toggle-btn" data-target="col-cust-rqty col-shop-rqty">Split</button></th>
                                                <th class="col-cust-rvalue" style="display:none;">Customer Ret. Value</th>
                                                <th class="col-shop-rvalue" style="display:none;">Shop Ret. Value</th>
                                                <th>Return Value<br><button type="button" class="col-toggle-btn" data-target="col-cust-rvalue col-shop-rvalue">Value</button></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($product_sales)): ?>
                                            <tr><td colspan="13" class="text-muted">No sales in this period.</td></tr>
                                        <?php else: foreach ($product_sales as $ps):
                                            $pid = (int)$ps['pid'];
                                            $ret = $returns_by_pid[$pid] ?? ['qty' => 0, 'amt' => 0, 'cust_qty' => 0, 'cust_amt' => 0, 'shop_qty' => 0, 'shop_amt' => 0];
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($ps['productName']); ?></td>
                                                <td class="col-cust-qty" style="display:none;"><?php echo inr_format($ps['cust_qty'] ?? 0, 0); ?></td>
                                                <td class="col-shop-qty" style="display:none;"><?php echo inr_format($ps['shop_qty'] ?? 0, 0); ?></td>
                                                <td><b><?php echo inr_format($ps['total_qty'], 0); ?></b></td>
                                                <td class="col-cust-svalue" style="display:none;">&#8377;<?php echo inr_format($ps['cust_rev'] ?? 0, 2); ?></td>
                                                <td class="col-shop-svalue" style="display:none;">&#8377;<?php echo inr_format($ps['shop_rev'] ?? 0, 2); ?></td>
                                                <td><b>&#8377;<?php echo inr_format($ps['total_rev'], 2); ?></b></td>
                                                <td class="col-cust-rqty" style="display:none;"><?php echo inr_format($ret['cust_qty'] ?? 0, 0); ?></td>
                                                <td class="col-shop-rqty" style="display:none;"><?php echo inr_format($ret['shop_qty'] ?? 0, 0); ?></td>
                                                <td><b><?php echo inr_format($ret['qty'] ?? 0, 0); ?></b></td>
                                                <td class="col-cust-rvalue" style="display:none;">&#8377;<?php echo inr_format($ret['cust_amt'] ?? 0, 2); ?></td>
                                                <td class="col-shop-rvalue" style="display:none;">&#8377;<?php echo inr_format($ret['shop_amt'] ?? 0, 2); ?></td>
                                                <td><b>&#8377;<?php echo inr_format($ret['amt'] ?? 0, 2); ?></b></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div><!-- /#sec-products -->

                    <!-- ══ Purchases from Company (per TP, hover for product breakdown) ══ -->
                    <div class="mis-section" id="sec-purchases">
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title" style="margin:0;font-size:14px;">Purchases from Company — by TP</h5></div>
                                <div class="card-body" style="overflow-x:auto;">
                                    <p class="snote">Hover a TP name to see their product-wise purchase &amp; return breakdown. Achievement/Balance count Napkin products only &mdash; Lumi Baby Diaper purchases don't count toward the Firka target.</p>
                                    <table class="mt">
                                        <thead><tr><th>TP</th><th>District (Firka)</th><th>Qty Purchased</th><th>Value Purchased</th><th>Qty Returned to Company</th><th>Target Amount</th><th>Achievement</th><th>Balance</th></tr></thead>
                                        <tbody>
                                        <?php if (empty($purchaseRows)): ?>
                                            <tr><td colspan="8" class="text-muted">No purchases in this period.</td></tr>
                                        <?php else: foreach ($purchaseRows as $pr):
                                            $tid = (int)$pr['tp_id'];
                                            $tname = $tpNameMap[$tid] ?? ('TP #' . $tid);
                                            $tp_target = $tpTargetByTp[$tid] ?? 0;
                                            $tp_napkin_amt = $napkinPurchaseByTp[$tid] ?? 0;
                                            $tp_pct = $tp_target > 0 ? min(round($tp_napkin_amt / $tp_target * 100, 1), 999) : 0;
                                            $tp_bc = $tp_pct >= 100 ? 'var(--good)' : ($tp_pct >= 50 ? '#eab308' : 'var(--critical)');
                                            $tp_balance = $tp_target - $tp_napkin_amt;
                                        ?>
                                            <tr>
                                                <td><span class="tp-name-cell" data-tp-id="<?php echo $tid; ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-html="true" data-type="purchase"><?php echo htmlspecialchars($tname); ?></span></td>
                                                <td style="font-size:12px;color:#666;"><?php echo htmlspecialchars($tpLocByTp[$tid] ?? '—'); ?></td>
                                                <td><?php echo inr_format($pr['qty'], 0); ?></td>
                                                <td>&#8377;<?php echo inr_format($pr['amt'], 2); ?></td>
                                                <td><?php echo inr_format($purchaseReturnByTp[$tid] ?? 0, 0); ?></td>
                                                <td><?php echo $tp_target > 0 ? '&#8377;' . inr_format($tp_target, 0) : '—'; ?></td>
                                                <td>
                                                    <?php if ($tp_target > 0): ?>
                                                    <div style="display:flex;align-items:center;gap:5px;">
                                                        <div class="pbar" style="width:70px;"><div class="pf" style="width:<?php echo min($tp_pct, 100); ?>%;background:<?php echo $tp_bc; ?>;"></div></div>
                                                        <span style="font-size:12.5px;font-weight:700;color:<?php echo $tp_bc; ?>;"><?php echo $tp_pct; ?>%</span>
                                                    </div>
                                                    <?php else: ?>—<?php endif; ?>
                                                </td>
                                                <td style="<?php echo $tp_target > 0 ? 'color:' . ($tp_balance > 0 ? 'var(--critical)' : 'var(--good)') . ';' : ''; ?>">
                                                    <?php echo $tp_target > 0 ? ($tp_balance > 0 ? '&minus;' : '+') . '&#8377;' . inr_format(abs($tp_balance), 0) : '—'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div><!-- /#sec-purchases -->

                    <div class="mis-section" id="sec-yoursales">
                    <!-- ══ Sales Channel Breakdown — TP → direct Customer vs TP → Shop/business ══ -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title" style="margin:0;font-size:14px;">Downstream Sales — Customer vs Shop</h5></div>
                                <div class="card-body" style="overflow-x:auto;">
                                    <table class="mt">
                                        <thead><tr><th>Channel</th><th>Amount</th><th>Invoices</th><th>Units</th></tr></thead>
                                        <tbody>
                                            <tr>
                                                <td>Customer</td>
                                                <td>&#8377;<?php echo inr_format((float)($cust_row['rev'] ?? 0), 2); ?></td>
                                                <td><?php echo inr_format((int)($cust_row['cnt'] ?? 0), 0); ?></td>
                                                <td><?php echo inr_format($cust_units, 0); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Shop</td>
                                                <td>&#8377;<?php echo inr_format((float)($shop_row['rev'] ?? 0), 2); ?></td>
                                                <td><?php echo inr_format((int)($shop_row['cnt'] ?? 0), 0); ?></td>
                                                <td><?php echo inr_format($shop_units, 0); ?></td>
                                            </tr>
                                            <tr style="font-weight:700;border-top:2px solid var(--gridline);">
                                                <td>Total</td>
                                                <td>&#8377;<?php echo inr_format($gross_revenue, 2); ?></td>
                                                <td><?php echo inr_format($total_invoices, 0); ?></td>
                                                <td><?php echo inr_format($total_units, 0); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ Your Sales via TP (downstream, per TP) ══ -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title" style="margin:0;font-size:14px;">Your Sales via TP — Downstream</h5></div>
                                <div class="card-body" style="overflow-x:auto;">
                                    <p class="snote">Hover a TP name to see their product-wise downstream sales &amp; return breakdown.</p>
                                    <table class="mt">
                                        <thead>
                                            <tr>
                                                <th>TP</th>
                                                <th>Customer Qty<br><button type="button" class="col-toggle-btn" data-target="col-cust-value">Value</button></th>
                                                <th class="col-cust-value" style="display:none;">Customer Value</th>
                                                <th>Shop Qty<br><button type="button" class="col-toggle-btn" data-target="col-shop-value">Value</button></th>
                                                <th class="col-shop-value" style="display:none;">Shop Value</th>
                                                <th>Total Qty</th>
                                                <th>Total Value</th>
                                                <th>Return Qty</th>
                                                <th>Return Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($downstreamByTp)): ?>
                                            <tr><td colspan="9" class="text-muted">No downstream sales in this period.</td></tr>
                                        <?php else: foreach ($downstreamByTp as $tid => $d):
                                            $tname = $tpNameMap[$tid] ?? ('TP #' . $tid);
                                            $dret = $downstreamReturnByTp[$tid] ?? ['qty' => 0, 'amt' => 0];
                                        ?>
                                            <tr>
                                                <td><span class="tp-name-cell" data-tp-id="<?php echo $tid; ?>" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-html="true" data-type="downstream"><?php echo htmlspecialchars($tname); ?></span></td>
                                                <td><?php echo inr_format($d['cust_qty'] ?? 0, 0); ?></td>
                                                <td class="col-cust-value" style="display:none;">&#8377;<?php echo inr_format($d['cust_rev'] ?? 0, 2); ?></td>
                                                <td><?php echo inr_format($d['shop_qty'] ?? 0, 0); ?></td>
                                                <td class="col-shop-value" style="display:none;">&#8377;<?php echo inr_format($d['shop_rev'] ?? 0, 2); ?></td>
                                                <td><b><?php echo inr_format($d['qty'], 0); ?></b></td>
                                                <td><b>&#8377;<?php echo inr_format($d['rev'], 2); ?></b></td>
                                                <td><?php echo inr_format($dret['qty'] ?? 0, 0); ?></td>
                                                <td>&#8377;<?php echo inr_format($dret['amt'] ?? 0, 2); ?></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div><!-- /#sec-yoursales -->

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filled Firkas — Territory Partners Modal -->
<div class="modal fade" id="filledFirkasModal" tabindex="-1" aria-labelledby="filledFirkasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                <h6 class="modal-title" id="filledFirkasModalLabel" style="font-weight:600;color:#1f2937;">
                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#16a34a;">check_circle</i>
                    Territory Partners in your Filled Firkas
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:14px 20px;">
                <ul class="nav nav-tabs" id="ffTabs" role="tablist" style="margin-bottom:12px;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ffActiveTabBtn" type="button" data-tab="active">Active TPs</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="ffInactiveTabBtn" type="button" data-tab="inactive">Inactive TPs</button>
                    </li>
                </ul>
                <div id="ffListBody">
                    <div style="color:#9ca3af;font-size:13px;padding:20px 0;text-align:center;">Loading&hellip;</div>
                </div>
                <div id="ffPagination" style="display:flex;justify-content:center;align-items:center;gap:10px;margin-top:12px;"></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
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
</script>
<script>
// Value columns start hidden — click "Value" next to Customer/Shop Qty to reveal that one column.
document.querySelectorAll('.col-toggle-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var targets = btn.getAttribute('data-target').split(' ');
        var show = btn.classList.toggle('active');
        targets.forEach(function (target) {
            document.querySelectorAll('.' + target).forEach(function (el) {
                el.style.display = show ? '' : 'none';
            });
        });
    });
});
</script>
<script>
(function ($) {
    var cache = {};
    var fromDate = <?php echo json_encode($from); ?>;
    var toDate   = <?php echo json_encode($to); ?>;

    function buildPopoverHtml(data) {
        if (!data || !data.rows || !data.rows.length) {
            return '<div style="font-size:12px;color:#999;">No data</div>';
        }
        var html = '<table style="font-size:11.5px;border-collapse:collapse;min-width:220px;">';
        html += '<tr><th style="text-align:left;padding:2px 6px;">Product</th><th style="text-align:right;padding:2px 6px;">Qty</th><th style="text-align:right;padding:2px 6px;">Value</th><th style="text-align:right;padding:2px 6px;">Returned</th></tr>';
        $.each(data.rows, function (_, r) {
            html += '<tr><td style="padding:2px 6px;">' + $('<div/>').text(r.name).html() + '</td>'
                  + '<td style="text-align:right;padding:2px 6px;">' + r.qty + '</td>'
                  + '<td style="text-align:right;padding:2px 6px;">₹' + r.value + '</td>'
                  + '<td style="text-align:right;padding:2px 6px;">' + r.ret_qty + '</td></tr>';
        });
        html += '</table>';
        return html;
    }

    $('.tp-name-cell').each(function () {
        var $el = $(this);
        var tpId = $el.data('tp-id');
        var type = $el.data('type');
        $el.popover({
            content: function () {
                var key = type + '-' + tpId;
                if (cache[key]) return buildPopoverHtml(cache[key]);
                $.getJSON('get-tp-product-breakdown.php', { tp_id: tpId, type: type, from: fromDate, to: toDate }, function (data) {
                    cache[key] = data;
                    $el.attr('data-bs-content', buildPopoverHtml(data));
                    var inst = bootstrap.Popover.getInstance($el[0]);
                    if (inst) { inst.setContent({ '.popover-body': buildPopoverHtml(data) }); }
                });
                return '<div style="font-size:12px;color:#999;">Loading&hellip;</div>';
            }
        });
    });
})(jQuery);
</script>
<script>
(function ($) {
    var viewBdmId = <?php echo json_encode($_GET['view_bdm_id'] ?? ''); ?>;
    // Weekly-target month follows the dashboard's own date filter ("From"),
    // so switching the filter to a previous month shows that month's own
    // Active/Inactive weekly-target breakdown, not always the current month.
    var ffMonth = <?php echo json_encode(date('Y-m', strtotime($from))); ?>;
    var ffCache = {};
    var ffCurrentTab = 'active';
    var ffCurrentPage = 1;

    function ffEsc(s) { return $('<div/>').text(s == null ? '' : s).html(); }
    function ffMoney(n) { return '&#8377;' + Number(n || 0).toLocaleString('en-IN', {minimumFractionDigits: 0, maximumFractionDigits: 0}); }

    function ffRenderRows(data) {
        if (!data || !data.rows || !data.rows.length) {
            return '<div style="color:#9ca3af;font-size:13px;padding:20px 0;text-align:center;">No Territory Partners found.</div>';
        }
        var serialStart = ((data.page || 1) - 1) * (data.per_page || 15);
        var html = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:13px;">' +
            '<thead><tr style="border-bottom:2px solid #e9ecef;text-align:left;color:#6b7280;">' +
            '<th style="padding:6px 8px;">S.No</th><th style="padding:6px 8px;">TP ID</th>' +
            '<th style="padding:6px 8px;">Name</th><th style="padding:6px 8px;">Phone</th>' +
            '<th style="padding:6px 8px;">District</th><th style="padding:6px 8px;">Firka(s)</th>' +
            '<th style="padding:6px 8px;text-align:right;">Target (Napkin)</th>' +
            '<th style="padding:6px 8px;">Weekly Status</th></tr></thead><tbody>';
        $.each(data.rows, function (i, r) {
            var w = r.weekly || {};
            var rowId = 'ffwk-' + i;
            var weeklyCell;
            if (w.is_future) {
                weeklyCell = '<span style="background:#f3f4f6;color:#6b7280;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">Upcoming</span>' +
                    '<div style="font-size:10.5px;color:#9ca3af;margin-top:2px;">' + ffEsc(w.month_label) + ' hasn\'t started yet.</div>';
            } else {
                var badge = w.on_track
                    ? '<span style="background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">On Track</span>'
                    : '<span style="background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">Behind</span>';
                weeklyCell = '<span class="ff-weekly-trigger" style="cursor:pointer;" data-target="' + rowId + '">' + badge + ' <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">expand_more</i></span>' +
                    '<div style="font-size:10.5px;color:#9ca3af;margin-top:2px;">' + ffEsc(w.week_label) + ' &middot; ' + ffMoney(w.paid_so_far) + ' paid (' + (w.pct_of_target || 0) + '% of target)</div>';
            }
            html += '<tr style="border-bottom:1px solid #f3f4f6;">' +
                '<td style="padding:7px 8px;color:#9ca3af;">' + (serialStart + i + 1) + '</td>' +
                '<td style="padding:7px 8px;color:#6b7280;">' + ffEsc(r.tp_id) + '</td>' +
                '<td style="padding:7px 8px;font-weight:600;color:#1f2937;">' + ffEsc(r.name) + '</td>' +
                '<td style="padding:7px 8px;">' + ffEsc(r.mobile) + '</td>' +
                '<td style="padding:7px 8px;">' + ffEsc(r.district) + '</td>' +
                '<td style="padding:7px 8px;color:#6b7280;">' + ffEsc(r.firkas) + '</td>' +
                '<td style="padding:7px 8px;text-align:right;font-weight:600;">' + ffMoney(r.target_amount) + '</td>' +
                '<td style="padding:7px 8px;white-space:nowrap;">' + weeklyCell + '</td>' +
                '</tr>';
            if (!w.is_future) {
                html += '<tr id="' + rowId + '" class="ff-week-detail-row" style="display:none;">' +
                    '<td colspan="8" style="padding:8px 10px 14px 30px;background:#f9fafb;">' + ffBuildWeeklyDetailHtml(w) + '</td>' +
                    '</tr>';
            }
        });
        html += '</tbody></table></div>';
        return html;
    }

    function ffBuildWeeklyDetailHtml(w) {
        if (!w || !w.weeks) return 'No data';
        var html = '<div style="font-weight:600;margin-bottom:6px;color:#374151;">' + ffEsc(w.month_label) + ' &mdash; Weekly Purchases</div>' +
            '<table style="font-size:12px;border-collapse:collapse;width:100%;max-width:520px;">' +
            '<tr style="color:#6b7280;"><th style="text-align:left;padding:3px 8px;">Week</th><th style="text-align:right;padding:3px 8px;">Purchased this week</th><th style="text-align:right;padding:3px 8px;">Total so far</th><th style="text-align:right;padding:3px 8px;">Required so far</th><th style="text-align:center;padding:3px 8px;">Status</th></tr>';
        $.each(['week1', 'week2', 'week3', 'week4'], function (_, key) {
            var wk = w.weeks[key];
            if (!wk) return;
            var status = !wk.has_started ? '<span style="color:#9ca3af;">&mdash;</span>'
                : (wk.pass ? '<span style="color:#15803d;font-weight:600;">Pass</span>' : '<span style="color:#b91c1c;font-weight:600;">Fail</span>');
            var rowStyle = wk.is_current ? ' style="background:#fffbeb;"' : '';
            html += '<tr' + rowStyle + '><td style="padding:4px 8px;">' + ffEsc(wk.label) + '</td>' +
                '<td style="text-align:right;padding:4px 8px;font-weight:600;">' + ffMoney(wk.amount) + '</td>' +
                '<td style="text-align:right;padding:4px 8px;">' + ffMoney(wk.cumulative) + '</td>' +
                '<td style="text-align:right;padding:4px 8px;color:#6b7280;">' + ffMoney(wk.required) + '</td>' +
                '<td style="text-align:center;padding:4px 8px;">' + status + '</td></tr>';
        });
        html += '</table>';
        return html;
    }

    function ffRenderPagination(data) {
        var totalPages = Math.max(1, Math.ceil((data.total || 0) / (data.per_page || 15)));
        if (totalPages <= 1) return '';
        var html = '<button type="button" class="btn btn-sm btn-outline-secondary ff-page-btn" data-page="' + (data.page - 1) + '"' + (data.page <= 1 ? ' disabled' : '') + '>Prev</button>' +
            '<span style="font-size:12.5px;color:#6b7280;">Page ' + data.page + ' of ' + totalPages + ' &nbsp;(' + data.total + ' total)</span>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary ff-page-btn" data-page="' + (data.page + 1) + '"' + (data.page >= totalPages ? ' disabled' : '') + '>Next</button>';
        return html;
    }

    function ffLoad(tab, page) {
        var key = tab + '-' + page;
        $('#ffListBody').html('<div style="color:#9ca3af;font-size:13px;padding:20px 0;text-align:center;">Loading&hellip;</div>');
        $('#ffPagination').html('');
        if (ffCache[key]) {
            $('#ffListBody').html(ffRenderRows(ffCache[key]));
            $('#ffPagination').html(ffRenderPagination(ffCache[key]));
            return;
        }
        var params = { tab: tab, page: page, month: ffMonth };
        if (viewBdmId) { params.view_bdm_id = viewBdmId; }
        $.getJSON('get-filled-firka-tps.php', params, function (data) {
            ffCache[key] = data;
            $('#ffListBody').html(ffRenderRows(data));
            $('#ffPagination').html(ffRenderPagination(data));
        }).fail(function () {
            $('#ffListBody').html('<div style="color:#b91c1c;font-size:13px;padding:20px 0;text-align:center;">Could not load data.</div>');
        });
    }

    $(document).on('click', '.ff-weekly-trigger', function () {
        $('#' + $(this).data('target')).toggle();
    });

    $('#filledFirkasCard').on('click', function () {
        ffCurrentTab = 'active';
        ffCurrentPage = 1;
        $('#ffActiveTabBtn').addClass('active');
        $('#ffInactiveTabBtn').removeClass('active');
        $('#filledFirkasModal').modal('show');
        ffLoad(ffCurrentTab, ffCurrentPage);
    });

    $('#ffTabs button').on('click', function () {
        $('#ffTabs button').removeClass('active');
        $(this).addClass('active');
        ffCurrentTab = $(this).data('tab');
        ffCurrentPage = 1;
        ffLoad(ffCurrentTab, ffCurrentPage);
    });

    $(document).on('click', '.ff-page-btn', function () {
        if ($(this).is(':disabled')) return;
        ffCurrentPage = parseInt($(this).data('page'), 10);
        ffLoad(ffCurrentTab, ffCurrentPage);
    });
})(jQuery);
</script>
</body>
</html>
