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
    $stmt = $db_conn->prepare("SELECT COALESCE(SUM(balance_amount), 0) AS balance, COUNT(*) AS cnt FROM tp_advance_payments WHERE territory_partner_id = ? AND company_id = ? AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL");
    $stmt->bind_param("ii", $tp_id, $godown_id);
} else {
    $stmt = $db_conn->prepare("SELECT COALESCE(SUM(balance_amount), 0) AS balance, COUNT(*) AS cnt FROM tp_advance_payments WHERE territory_partner_id = ? AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL");
    $stmt->bind_param("i", $tp_id);
}
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// TP's monthly target = sum of target_amount across the Firka/location(s) assigned to them
$stmtTgt = $db_conn->prepare("
    SELECT SUM(pln.target_amount) AS total_target, COUNT(pln.target_amount) AS set_count
    FROM territory_partner_locations tpl
    JOIN partner_location_nodes pln ON pln.id = tpl.location_id
    WHERE tpl.territory_partner_id = ?
");
$stmtTgt->bind_param("i", $tp_id);
$stmtTgt->execute();
$tgtRow = $stmtTgt->get_result()->fetch_assoc();
$stmtTgt->close();

echo json_encode([
    'balance' => round((float)$row['balance'], 2),
    'count'   => (int)$row['cnt'],
    'target'  => ($tgtRow && (int)$tgtRow['set_count'] > 0) ? round((float)$tgtRow['total_target'], 2) : null,
]);
