<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$filter_district = isset($_GET['f_district']) ? (int)$_GET['f_district'] : 0;
$filter_division = isset($_GET['f_division']) ? (int)$_GET['f_division'] : 0;
$filter_firka    = isset($_GET['f_firka']) ? (int)$_GET['f_firka'] : 0;

$file = "Shop-list.csv";

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=$file");

$output = fopen("php://output", "w");
fputcsv($output, ['Category', 'ID', 'Name', 'State', 'District', 'Division', 'Firka', 'Pincode', 'Country Code', 'Mobile Number', 'Landline', 'Email ID', 'Address', 'GSTIN']);

$select_shops = "SELECT s.*, sc.catlable FROM shop s LEFT JOIN shop_category sc ON s.shop_cat = sc.id WHERE s.onboard_userID=? AND s.onboard_userTYPE=? AND s.deleted_at IS NULL";
$filter_types = "is";
$filter_params = [$Login_user_IDvl, $Login_user_TYPEvl];
if ($filter_district > 0) {
    $select_shops .= " AND s.district_id=?";
    $filter_types .= "i";
    $filter_params[] = $filter_district;
}
if ($filter_division > 0) {
    $select_shops .= " AND s.taluk_id=?";
    $filter_types .= "i";
    $filter_params[] = $filter_division;
}
if ($filter_firka > 0) {
    $select_shops .= " AND s.firka_id=?";
    $filter_types .= "i";
    $filter_params[] = $filter_firka;
}
$select_shops .= " ORDER BY s.id DESC";

$stmt_shops = mysqli_prepare($db_conn, $select_shops);
mysqli_stmt_bind_param($stmt_shops, $filter_types, ...$filter_params);
mysqli_stmt_execute($stmt_shops);
$fetch_shops = mysqli_stmt_get_result($stmt_shops);

// Preload all active geo nodes once, keyed by id, so each row's
// state/district/division/firka names can be resolved without a query per row.
$geo_node_names = [];
$geo_res = mysqli_query($db_conn, "SELECT id, name FROM partner_location_nodes WHERE is_active=1");
while ($geo_row = mysqli_fetch_assoc($geo_res)) {
    $geo_node_names[(int)$geo_row['id']] = $geo_row['name'];
}

function geo_name($geo_node_names, $id) {
    $id = (int)$id;
    return ($id > 0 && isset($geo_node_names[$id])) ? $geo_node_names[$id] : 'Na';
}

while ($result_shop = mysqli_fetch_array($fetch_shops)) {
    fputcsv($output, [
        $result_shop['catlable'],
        $result_shop['useridtext'],
        $result_shop['name'],
        geo_name($geo_node_names, $result_shop['state_id']),
        geo_name($geo_node_names, $result_shop['district_id']),
        geo_name($geo_node_names, $result_shop['taluk_id']),
        geo_name($geo_node_names, $result_shop['firka_id']),
        $result_shop['pincode_id'],
        $result_shop['country_code'],
        $result_shop['mobile_number'],
        $result_shop['landline'],
        $result_shop['email'],
        $result_shop['address'],
        $result_shop['gstin'],
    ]);
}

fclose($output);
?>
