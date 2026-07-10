<?php include("checksession.php");
include("config.php");
error_reporting(0);

include("geo_layers.php");

$file = "shop-import-template.csv";
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=$file");

$output = fopen("php://output", "w");
fputcsv($output, ['Name', 'Category', 'State', 'District', 'Taluk', 'Pincode', 'Country Code', 'Mobile Number', 'Landline', 'Email ID', 'Address', 'GSTIN']);

$exampleCategory = 'GROCERY STORES / GENERAL STORES';
$catRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT catlable FROM shop_category ORDER BY id ASC LIMIT 1"));
if ($catRow) {
    $exampleCategory = $catRow['catlable'];
}

$exampleState = '';
$exampleDistrict = '';
$exampleTaluk = '';
foreach ($geoNodes as $node) {
    if ($node['depth'] === 2 && $exampleState === '') {
        $exampleState = $node['name'];
        $stateId = $node['id'];
    }
}
foreach ($geoNodes as $node) {
    if ($node['depth'] === 3 && isset($stateId) && $node['parent_id'] === $stateId) {
        $exampleDistrict = $node['name'];
        $districtId = $node['id'];
        break;
    }
}
foreach ($geoNodes as $node) {
    if ($node['depth'] === 4 && isset($districtId) && $node['parent_id'] === $districtId) {
        $exampleTaluk = $node['name'];
        break;
    }
}

fputcsv($output, [
    'Example Shop', $exampleCategory,
    $exampleState !== '' ? $exampleState : 'STATE NAME',
    $exampleDistrict !== '' ? $exampleDistrict : 'DISTRICT NAME',
    $exampleTaluk,
    '600001', '+91', '9876543210', '', 'shop@example.com', 'Full address here', '',
]);

fclose($output);
?>
