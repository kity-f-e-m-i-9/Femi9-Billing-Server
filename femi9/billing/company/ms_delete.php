<?php include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

// Self-migrating soft-delete column — a hard DELETE here used to orphan
// every order/shop/invoice history row that referenced this ms_id. Same
// pattern as territory_partners.deleted_at / shop.deleted_at.
$_col = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'deleted_at'");
if ($_col && $_col->num_rows === 0) {
    $db_conn->query("ALTER TABLE marketing_staff ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
}

$stmt = $db_conn->prepare("UPDATE marketing_staff SET deleted_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $prid);
$stmt->execute();
$stmt->close();

echo "<script>window.location='ms_manage?deletedDone';</script>";
?>
