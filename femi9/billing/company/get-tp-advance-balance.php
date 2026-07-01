<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'company') {
    echo json_encode(['balance' => 0.00, 'count' => 0, 'error' => 'unauthorized']); exit;
}

$tp_id     = (int)($_GET['tp_id']     ?? 0);
$godown_id = (int)($_GET['godown_id'] ?? 0);
if (!$tp_id) { echo json_encode(['balance' => 0.00, 'count' => 0]); exit; }

if ($godown_id > 0) {
    $stmt = $db_conn->prepare("SELECT COALESCE(SUM(balance_amount), 0) AS balance, COUNT(*) AS cnt FROM tp_advance_payments WHERE territory_partner_id = ? AND company_id = ? AND balance_amount > 0 AND status != 'fully_adjusted'");
    $stmt->bind_param("ii", $tp_id, $godown_id);
} else {
    $stmt = $db_conn->prepare("SELECT COALESCE(SUM(balance_amount), 0) AS balance, COUNT(*) AS cnt FROM tp_advance_payments WHERE territory_partner_id = ? AND balance_amount > 0 AND status != 'fully_adjusted'");
    $stmt->bind_param("i", $tp_id);
}
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'balance' => round((float)$row['balance'], 2),
    'count'   => (int)$row['cnt'],
]);
