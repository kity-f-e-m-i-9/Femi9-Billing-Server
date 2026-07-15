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
    header("Location: neksomo-vendor-manage.php?error");
    exit;
}

$stmt = $db_conn->prepare("UPDATE neksomo_vendors SET is_active = 1 - is_active WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

header("Location: neksomo-vendor-manage.php?statuschanged");
exit;
