<?php
// Streams the currently-filtered Bundle Report (either tab) as CSV —
// mirrors export_GSTR1.php's plain-text streaming convention rather than
// pulling in a spreadsheet library for a simple tabular export.
ob_start();

include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/RawMaterialBundles.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

$filterProductId = filter_var($_GET['product_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$filterGodownId  = filter_var($_GET['godown_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$filterStatus    = in_array($_GET['status'] ?? '', ['open', 'closed'], true) ? $_GET['status'] : null;
$filterType      = in_array($_GET['type'] ?? '', ['conversion', 'damage', 'extra'], true) ? $_GET['type'] : null;
$filterDateFrom  = trim((string) ($_GET['date_from'] ?? '')) ?: null;
$filterDateTo    = trim((string) ($_GET['date_to'] ?? '')) ?: null;
if ($filterDateFrom && !\DateTime::createFromFormat('Y-m-d', $filterDateFrom)) $filterDateFrom = null;
if ($filterDateTo && !\DateTime::createFromFormat('Y-m-d', $filterDateTo)) $filterDateTo = null;

$tab = ($_GET['tab'] ?? 'summary') === 'transactions' ? 'transactions' : 'summary';

ob_end_clean();

if ($tab === 'transactions') {
    $rows = get_bundle_report_transactions($db_conn, $filterProductId, $filterGodownId, $filterType, $filterDateFrom, $filterDateTo);
    $filename = 'bundle-transactions-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Type', 'Bundle', 'Product', 'Godown', 'Warehouse', 'Qty', 'Detail', 'Ref ID', 'By']);
    foreach ($rows as $r) {
        fputcsv($out, [
            date('d-M-Y H:i', strtotime($r['created_at'])),
            ucfirst($r['type']),
            'Bundle #' . $r['bundle_id'],
            $r['product_name'],
            $r['gname'],
            $r['warehouse_code'] ?? '',
            $r['qty'],
            $r['detail'],
            $r['ref_id'] ?? '',
            $r['created_by'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$rows = get_bundle_report_summary($db_conn, $filterProductId, $filterGodownId, $filterStatus, $filterDateFrom, $filterDateTo);
$filename = 'bundle-summary-' . date('Ymd-His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, [
    'Bundle', 'Product', 'Godown', 'Warehouse', 'Nominal', 'Remaining', 'Damaged', 'Extra Found',
    'Conversions', 'Packs Made', 'Status', 'Variance', 'Created By', 'Created At', 'Closed By', 'Closed At',
]);
foreach ($rows as $b) {
    fputcsv($out, [
        $b['label'],
        $b['product_name'],
        $b['gname'],
        $b['warehouse_code'] ?? '',
        $b['nominal_pieces'],
        max($b['remaining_pieces'], 0),
        $b['damaged_pieces'],
        $b['added_extra_pieces'],
        $b['conversions_count'],
        $b['packs_made_total'],
        ucfirst($b['status']),
        $b['variance_label'] ?? '',
        $b['created_by'] ?? '',
        $b['created_at'],
        $b['closed_by'] ?? '',
        $b['closed_at'] ?? '',
    ]);
}
fclose($out);
exit;
