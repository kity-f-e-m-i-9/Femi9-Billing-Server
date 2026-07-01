<?php
ob_start();
include("checksession.php");
include("config.php");

$get_t1 = $_REQUEST['t1'] ?? '0';
$get_t2 = $_REQUEST['t2'] ?? '0';
$get_t3 = $_REQUEST['t3'] ?? '0';
$get_t4 = $_REQUEST['t4'] ?? '0';

$file = "GSTR1.csv";

$csv  = "Description,Nil Rated Supplies,Exempted (Other than Nil Rated/non GST Supply),Non GST Supplies\n";
$csv .= '"Intra-state supplies to registered person","'   . $get_t1 . '","0","0"' . "\n";
$csv .= '"Intra-state supplies to unregistered person","' . $get_t2 . '","0","0"' . "\n";
$csv .= '"Inter-state supplies to registered person","'   . $get_t3 . '","0","0"' . "\n";
$csv .= '"Inter-state supplies to unregistered person","' . $get_t4 . '","0","0"' . "\n";

ob_end_clean();
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");
echo $csv;
