<?php
// CSV export of GSTR1.php's HSN-wise B2B / B2C table (same numbers: both use
// include/HsnB2bB2cHelper.php).
ob_start();
include("checksession.php"); require_once("include/GodownAccess.php");
include("config.php");
require_once __DIR__ . '/include/TpGstHelper.php';
require_once __DIR__ . '/include/HsnB2bB2cHelper.php';

$from_month = $_REQUEST['frdate'] ?? '';
$to_month   = $_REQUEST['todate'] ?? '';
$get_godown_id = (int)($_REQUEST['godown_id'] ?? 0);
if (!$get_godown_id || !is_godown_allowed($db_conn, $get_godown_id)) {
    ob_end_clean(); http_response_code(403); echo "Not allowed"; exit;
}
$from_date = date("Y-m-01", strtotime($from_month));
$to_date   = date("Y-m-t", strtotime($to_month));

$tp_sls_lines = tp_sales_gst_lines($db_conn, $from_date, $to_date, "tpi.source_godown_id = '$get_godown_id'");
$rows = compute_hsn_b2b_b2c($db_conn, $Login_user_TYPEvl, $get_godown_id, $from_date, $to_date, $tp_sls_lines);

$out = fopen('php://temp', 'w+');
fputcsv($out, ['HSN', 'GST Rate', 'UQC',
    'B2B Qty', 'B2B Taxable Value', 'B2B CGST', 'B2B SGST', 'B2B IGST',
    'B2C Qty', 'B2C Taxable Value', 'B2C CGST', 'B2C SGST', 'B2C IGST']);
$tot = ['b2b' => array_fill_keys(['qty','taxable','cgst','sgst','igst'], 0), 'b2c' => array_fill_keys(['qty','taxable','cgst','sgst','igst'], 0)];
foreach ($rows as $r) {
    $line = [$r['hsn'], $r['rate'] > 0 ? $r['rate'] . '%' : 'Nil', $r['uqc']];
    foreach (['b2b', 'b2c'] as $b) {
        foreach (['qty','taxable','cgst','sgst','igst'] as $f) {
            $tot[$b][$f] += $r[$b][$f];
            $line[] = $f === 'qty' ? $r[$b][$f] : number_format($r[$b][$f], 2, '.', '');
        }
    }
    fputcsv($out, $line);
}
$line = ['Total', '', ''];
foreach (['b2b', 'b2c'] as $b) {
    foreach (['qty','taxable','cgst','sgst','igst'] as $f) {
        $line[] = $f === 'qty' ? $tot[$b][$f] : number_format($tot[$b][$f], 2, '.', '');
    }
}
fputcsv($out, $line);
rewind($out);
$csv = stream_get_contents($out);
fclose($out);

ob_end_clean();
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=GSTR1_HSN_B2B_B2C_" . date('Ym', strtotime($from_date)) . "_" . date('Ym', strtotime($to_date)) . ".csv");
echo $csv;
