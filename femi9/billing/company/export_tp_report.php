<?php
ob_start();
include("checksession.php");
include("config.php");
error_reporting(0);

// Mirrors Report-TP-Details.php's own data-fetch logic (same seller = TP,
// same shop/customer bill sources) — kept independent rather than shared,
// same reasoning as that page's own header comment: territory_partners has
// no state/district FK, so this matches on the same raw branch_state text.
$selected_branch_state = trim($_REQUEST['branch_state'] ?? '');
$selected_pincode      = trim($_REQUEST['branch_pincode'] ?? '');
$selected_buyer_type   = trim($_REQUEST['buyer_type'] ?? '');
$from_date             = $_REQUEST['frdate'] ?? '';
$to_date               = $_REQUEST['todate'] ?? '';

if ($selected_branch_state === '' || $from_date === '' || $to_date === '') {
    header("Location: Report-TP-First-Page.php");
    exit;
}

ob_end_clean();

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=TP_Report_".date('Y-m-d').".csv");
header("Pragma: no-cache");
header("Expires: 0");

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, array('B2B Sales Report - ' . $selected_branch_state . ' (Territory Partner)'));
fputcsv($output, array('Date: '.date("d/m/Y",strtotime($from_date)).' to '.date("d/m/Y",strtotime($to_date))));
fputcsv($output, array(''));

// District here comes from the TP's actual assigned territory (firkas), not
// their free-typed billing/delivery address fields — those can be stale or
// wrong (e.g. billing address in one district, TP actually assigned firkas
// in another). Every TP is assigned one or more firka-level nodes in
// partner_location_nodes (see territory-partner/geo_layers.php for the same
// hierarchy: COUNTRY > STATE > DISTRICT > DIVISION > TALUK > FIRKA); walking
// each assigned firka up to its DISTRICT-depth ancestor gives the authoritative
// district. A TP's firkas normally all resolve to the same single district.
$header = array('S.No', 'Territory Partner', 'TP ID', 'Mobile', 'District', 'Invoice Number', 'Buyer Type', 'Buyer Name', 'Buyer Mobile', 'Date', 'Sub Total', 'Courier Charge', 'Total Amount');

$products = array();
$prodRes = mysqli_query($db_conn, "SELECT id, productName FROM products WHERE (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) ORDER BY id ASC");
while ($p = mysqli_fetch_assoc($prodRes)) {
    $header[] = $p['productName'];
    $products[$p['id']] = $p['productName'];
}
fputcsv($output, $header);

// ── Territory partners matching this branch_state (+ optional pincode) ──
$tp_ids = [];
$tp_names = [];
$tpWhere = "branch_state = ?";
$tpTypes = "s";
$tpParams = [$selected_branch_state];
if ($selected_pincode !== '') {
    $tpWhere .= " AND branch_pincode LIKE ?";
    $tpTypes .= "s";
    $tpParams[] = "%{$selected_pincode}%";
}
$tpStmt = $db_conn->prepare("SELECT id, tp_id, name, mobile FROM territory_partners WHERE $tpWhere ORDER BY name ASC");
$tpStmt->bind_param($tpTypes, ...$tpParams);
$tpStmt->execute();
$tpRes = $tpStmt->get_result();
while ($tp = $tpRes->fetch_assoc()) {
    $tp_ids[] = (int)$tp['id'];
    $tp_names[(int)$tp['id']] = $tp;
}
$tpStmt->close();

// ── Resolve each TP's district via their assigned firka(s) ──────────────
$districtDepthRow = mysqli_fetch_assoc(mysqli_query($db_conn, "SELECT depth FROM partner_location_layers WHERE layer_name = 'DISTRICT' LIMIT 1"));
$districtDepth = $districtDepthRow ? (int)$districtDepthRow['depth'] : 3;

$locNodes = [];
$locRes = mysqli_query($db_conn, "SELECT id, parent_id, depth, name FROM partner_location_nodes");
while ($n = mysqli_fetch_assoc($locRes)) {
    $locNodes[(int)$n['id']] = $n;
}

function tp_report_find_district(array $locNodes, int $districtDepth, int $nodeId): ?string {
    $cur = $nodeId;
    while ($cur !== null && isset($locNodes[$cur])) {
        if ((int)$locNodes[$cur]['depth'] === $districtDepth) return $locNodes[$cur]['name'];
        $cur = $locNodes[$cur]['parent_id'] !== null ? (int)$locNodes[$cur]['parent_id'] : null;
    }
    return null;
}

if (!empty($tp_ids)) {
    $tpIdListForLoc = implode(',', $tp_ids);
    $locAssignRes = mysqli_query($db_conn, "SELECT territory_partner_id, location_id FROM territory_partner_locations WHERE territory_partner_id IN ($tpIdListForLoc)");
    $tpFirkasById = [];
    while ($row = mysqli_fetch_assoc($locAssignRes)) {
        $tpFirkasById[(int)$row['territory_partner_id']][] = (int)$row['location_id'];
    }
    foreach ($tp_ids as $tpid) {
        $districts = [];
        foreach ($tpFirkasById[$tpid] ?? [] as $locId) {
            $d = tp_report_find_district($locNodes, $districtDepth, $locId);
            if ($d !== null) $districts[$d] = true;
        }
        $tp_names[$tpid]['district'] = !empty($districts) ? implode(', ', array_keys($districts)) : '-';
    }
}

$rows = [];

if (!empty($tp_ids)) {
    $idList = implode(',', $tp_ids);

    // Shop bills (TP -> Shop)
    if ($selected_buyer_type === '' || $selected_buyer_type === 'shop') {
        $shopRes = mysqli_query($db_conn, "
            SELECT ui.inv_id, ui.inv_number, ui.date, ui.sub_total, ui.courier_charges, ui.total,
                   ui.from_user_id AS tp_id,
                   s.name AS buyer_name, s.mobile_number AS buyer_mobile, 'Shop' AS buyer_type
            FROM user_invoice ui
            JOIN shop s ON s.temp_id = ui.to_user_id
            WHERE ui.from_user_type='territory_partner' AND ui.from_user_id IN ($idList)
              AND ui.to_user_type='shop'
              AND ui.date BETWEEN '" . $db_conn->real_escape_string($from_date) . "' AND '" . $db_conn->real_escape_string($to_date) . "'
            ORDER BY ui.date DESC, ui.id DESC
        ");
        while ($r = mysqli_fetch_assoc($shopRes)) {
            $items = [];
            $itemRes = mysqli_query($db_conn, "SELECT pr_id, qty FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $r['inv_id']) . "'");
            while ($it = mysqli_fetch_assoc($itemRes)) { $items[$it['pr_id']] = ($items[$it['pr_id']] ?? 0) + (int)$it['qty']; }
            $r['items'] = $items;
            $rows[] = $r;
        }
    }

    // Customer bills (TP -> Customer)
    if ($selected_buyer_type === '' || $selected_buyer_type === 'customer') {
        $custRes = mysqli_query($db_conn, "
            SELECT i.inv_id, i.inv_number, i.date, i.sub_total, i.courier_charges, i.total,
                   i.user_id AS tp_id,
                   COALESCE(c.name,'Walking Customer') AS buyer_name, COALESCE(c.mobile,'') AS buyer_mobile, 'Customer' AS buyer_type
            FROM invoice i
            LEFT JOIN customers c ON c.id = i.customer_id
            WHERE i.user_type='territory_partner' AND i.user_id IN ($idList)
              AND i.date BETWEEN '" . $db_conn->real_escape_string($from_date) . "' AND '" . $db_conn->real_escape_string($to_date) . "'
            ORDER BY i.date DESC, i.id DESC
        ");
        while ($r = mysqli_fetch_assoc($custRes)) {
            $items = [];
            $itemRes = mysqli_query($db_conn, "SELECT pr_id, qty FROM invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $r['inv_id']) . "'");
            while ($it = mysqli_fetch_assoc($itemRes)) { $items[$it['pr_id']] = ($items[$it['pr_id']] ?? 0) + (int)$it['qty']; }
            $r['items'] = $items;
            $rows[] = $r;
        }
    }

    usort($rows, fn($a, $b) => strcmp($b['date'] . $b['inv_id'], $a['date'] . $a['inv_id']));
}

$i = 0;
$grand_subtotal = 0;
$grand_courier = 0;
$grand_total = 0;
$product_grand_totals = array_fill_keys(array_keys($products), 0);

foreach ($rows as $r) {
    $tp = $tp_names[$r['tp_id']] ?? ['name' => 'N/A', 'tp_id' => '', 'mobile' => '', 'district' => '-'];

    $grand_subtotal += $r['sub_total'];
    $grand_courier  += $r['courier_charges'];
    $grand_total    += $r['total'];

    $row = array(
        ++$i,
        $tp['name'],
        $tp['tp_id'],
        $tp['mobile'],
        $tp['district'],
        $r['inv_number'],
        $r['buyer_type'],
        $r['buyer_name'],
        $r['buyer_mobile'],
        date("d/M/Y", strtotime($r['date'])),
        inr_format($r['sub_total'], 2),
        inr_format($r['courier_charges'], 2),
        inr_format($r['total'], 2)
    );

    foreach ($products as $pid => $pname) {
        $qty = $r['items'][$pid] ?? 0;
        $product_grand_totals[$pid] += $qty;
        $row[] = $qty ?: "0";
    }

    fputcsv($output, $row);
}

fputcsv($output, array(''));

$total_row = array_fill(0, 10, '');
$total_row[9]  = 'GRAND TOTAL:';
$total_row[10] = inr_format($grand_subtotal, 2);
$total_row[11] = inr_format($grand_courier, 2);
$total_row[12] = inr_format($grand_total, 2);
foreach ($products as $pid => $pname) {
    $total_row[] = $product_grand_totals[$pid];
}
fputcsv($output, $total_row);

fclose($output);
exit;
