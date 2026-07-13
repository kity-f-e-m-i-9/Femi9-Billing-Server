<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

$id = (int) base64_decode($_REQUEST['id'] ?? '');

$stmt = $db_conn->prepare("DELETE FROM neksomo_llp_piece_rates WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

header("Location: neksomo-llp-piece-sale-manage.php?deletedDone");
exit;
