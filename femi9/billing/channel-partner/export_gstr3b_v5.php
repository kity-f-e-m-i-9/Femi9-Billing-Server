<?php
ob_start();
include("checksession.php");
include("config.php");

$get_t1 = $_REQUEST['t1'] ?? '0';  // inter-state
$get_t2 = $_REQUEST['t2'] ?? '0';  // intra-state

$file = "GSTR3B(5)-Values-of-exempt-nil-rated-and-non-GST-inward-supplies.csv";

$csv  = "Nature of Supplies,Inter-state supplies,Intra-state supplies\n";
$csv .= '"From a supplier under composition scheme, Exempt and Nil rated supply","' . $get_t1 . '","' . $get_t2 . '"' . "\n";
$csv .= '"Non GST Supply","0","0"' . "\n";

ob_end_clean();
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");
echo $csv;
