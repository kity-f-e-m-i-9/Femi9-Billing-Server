<?php
// GSTR-1 Excel export (GST offline tool workbook + Summary sheet) for the logged-in Territory Partner.
ob_start();
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../company/include/Gstr1JsonHelper.php';
require_once __DIR__ . '/../company/include/Gstr1XlsxWriter.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$from_date = date("Y-m-01", strtotime($_REQUEST['frdate'] ?? ''));
$to_date   = date("Y-m-t",  strtotime($_REQUEST['todate'] ?? ''));
$tp_id     = (int)$Login_user_IDvl;   // always the logged-in TP

$data = build_gstr1_json($db_conn, $Login_user_TYPEvl, $tp_id, $from_date, $to_date, true);

// Same figures as the on-screen tables on gstr1.php (these includes only compute, no output).
error_reporting(0);
include('gst_details.php'); include('gst_details_inter.php'); include('gst_details_credit.php'); include('gst_details_credit_inter.php');
$summary = [
    ['Intra-state', [['Total Sales (Shop, Customer)', $total_intra_register + $total_intra_register2, $total_intra_unregister + $total_intra_unregister2]],
                    [['Sales Return', $total_intra_register_credit, $total_intra_unregister_credit]]],
    ['Inter-state', [['Total Sales (Shop, Customer)', $total_inter_register + $total_inter_register2, $total_inter_unregister + $total_inter_unregister2]],
                    [['Sales Return', $total_inter_register_credit, $total_inter_unregister_credit]]],
];
$wb = gstr1_build_workbook($data, $from_date, $to_date, $summary);

$file = ($data['gstin'] ?: 'GSTR1') . '_GSTR1_' . strtoupper(date('M-Y', strtotime($to_date))) . '_' . date('dmY_His') . '.xlsx';
ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $file . '"');
\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($wb, 'Xlsx')->save('php://output');
