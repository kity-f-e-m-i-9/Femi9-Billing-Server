<?php
// GSTR-1 Excel export in the GST Offline Tool workbook format. Same data as the JSON export
// (include/Gstr1JsonHelper.php); workbook built by include/Gstr1XlsxWriter.php.
ob_start();
include("checksession.php"); require_once("include/GodownAccess.php");
include("config.php");
require_once __DIR__ . '/include/Gstr1JsonHelper.php';
require_once __DIR__ . '/include/Gstr1XlsxWriter.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$from_month = $_REQUEST['frdate'] ?? '';
$to_month   = $_REQUEST['todate'] ?? '';
$gid = (int)($_REQUEST['godown_id'] ?? 0);
if (!$gid || !is_godown_allowed($db_conn, $gid)) { ob_end_clean(); http_response_code(403); echo "Not allowed"; exit; }
$from_date = date("Y-m-01", strtotime($from_month));
$to_date   = date("Y-m-t", strtotime($to_month));

$data = build_gstr1_json($db_conn, $Login_user_TYPEvl, $gid, $from_date, $to_date);

// Same figures as the on-screen Intra/Inter-state tables on GSTR1.php (these includes only compute, no output).
$get_godown_id = $gid;
error_reporting(0);
include('gst_details.php'); include('gst_details_inter.php'); include('gst_details_credit.php'); include('gst_details_credit_inter.php');

$summary = [
    ['Intra-state', [
        ['Total Sales (SS, ST, DT, SHP, CUS, TP)', $total_intra_register + $total_intra_register2 + $total_reg_TP, $total_intra_unregister + $total_intra_unregister2 + $total_unreg_TP],
        ['Total OT Sales', $total_reg_OTSLS_intra, $total_reg_OTSLSUN_intra],
        ['Total Internal Transfer Sales', $total_reg_INTR, 0],
    ], [
        ['Sales Return (SS, ST, DT, SHP, CUS, TP)', $total_intra_register_credit + $total_reg_TP_credit, $total_intra_unregister_credit + $total_unreg_TP_credit],
        ['OT Sales Return', $total_intra_register_creditOT, $total_intra_unregister_creditOT],
    ]],
    ['Inter-state', [
        ['Total Sales (SS, ST, DT, SHP, CUS, TP)', $total_inter_register + $total_inter_register2 + $total_reg_TP_inter, $total_inter_unregister + $total_inter_unregister2 + $total_unreg_TP_inter],
        ['Total OT Sales', $total_reg_OTSLS_inter, $total_reg_OTSLSUN_inter],
    ], [
        ['Sales Return (SS, ST, DT, SHP, CUS, TP)', $total_inter_register_credit + $total_reg_TP_credit_inter, $total_inter_unregister_credit + $total_unreg_TP_credit_inter],
        ['OT Sales Return', $total_inter_register_creditOT, $total_inter_unregister_creditOT],
    ]],
];
$wb = gstr1_build_workbook($data, $from_date, $to_date, $summary);

$file = ($data['gstin'] ?: 'GSTR1') . '_GSTR1_' . strtoupper(date('M-Y', strtotime($to_date))) . '_' . date('dmY_His') . '.xlsx';
ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $file . '"');
\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($wb, 'Xlsx')->save('php://output');
