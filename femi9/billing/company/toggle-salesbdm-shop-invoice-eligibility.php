<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
require_once __DIR__ . '/../shared/TpShopInvoiceActionRequest.php';
header('Content-Type: application/json');
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'csrf']); exit;
}

$id       = (int)($_POST['id'] ?? 0);
$eligible = array_key_exists('eligible', $_POST) ? (int)$_POST['eligible'] : -1;

if ($id < 1 || !in_array($eligible, [0, 1], true)) {
    echo json_encode(['success' => false]); exit;
}

tpEnsureShopInvoiceEligibilityColumn($db_conn);
$stmt = $db_conn->prepare("UPDATE sales_bdm_staff SET shop_invoice_request_eligible = ? WHERE id = ?");
$stmt->bind_param("ii", $eligible, $id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok, 'new_eligible' => $eligible]);
