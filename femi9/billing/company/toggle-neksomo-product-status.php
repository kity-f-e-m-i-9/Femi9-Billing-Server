<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

$id = (int) base64_decode($_REQUEST['id'] ?? '');
if (!$id) {
    header("Location: neksomo-manage-products.php?error");
    exit;
}

// Only ever touch products this login created — never the shared/admin catalog.
$stmt = $db_conn->prepare(
    "UPDATE products SET deleted_at = IF(deleted_at IS NULL, NOW(), NULL) WHERE id = ? AND temp_id LIKE 'NKS-%'"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

header("Location: neksomo-manage-products.php?statuschanged");
exit;
