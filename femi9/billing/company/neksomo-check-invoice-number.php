<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");
header('Content-Type: application/json');

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    echo json_encode(['duplicate' => false]);
    exit;
}

$invnumber = trim($_REQUEST['q'] ?? '');

$duplicate = false;
if ($invnumber !== '') {
    $stmt = $db_conn->prepare("SELECT id FROM neksomo_manufacturer_purchases WHERE invoice_number = ?");
    $stmt->bind_param('s', $invnumber);
    $stmt->execute();
    $duplicate = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}

echo json_encode(['duplicate' => $duplicate]);
