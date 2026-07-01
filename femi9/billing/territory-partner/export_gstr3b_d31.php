<?php
ob_start();
include("checksession.php");
include("config.php");

$get_t1 = $_REQUEST['t1'] ?? '0';

$file = "GSTR3B(3.1)-Details-of-Outward-Supplies-and-inward-supplies-liable-to-reverse-charge.csv";

$csv  = "Nature of Supplies,Total Taxable value,Integrated Tax,Central Tax,State/UT Tax,CESS\n";
$csv .= '"(a) Outward taxable supplies (other than zero rated, nil rated and exempted)","0","0","0","0","0"' . "\n";
$csv .= '"(b) Outward taxable supplies (zero rated)","0","0","0","0","0"' . "\n";
$csv .= '"(c) Other outward supplies (Nil rated, exempted)","' . $get_t1 . '","0","0","0","0"' . "\n";
$csv .= '"(d) Inward supplies (liable to reverse charge)","0","0","0","0","0"' . "\n";
$csv .= '"(e) Non-GST outward supplies","0","0","0","0","0"' . "\n";

ob_end_clean();
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");
echo $csv;
