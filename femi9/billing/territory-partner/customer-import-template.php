<?php include("checksession.php");
include("config.php");
error_reporting(0);

$file = "customer-import-template.csv";
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=$file");

$output = fopen("php://output", "w");
fputcsv($output, ['Name', 'Mobile', 'Email', 'GSTIN', 'Address', 'Country Code']);
fputcsv($output, ['Example Customer', '9876543210', 'customer@example.com', '', 'Full address here', '+91']);

fclose($output);
?>
