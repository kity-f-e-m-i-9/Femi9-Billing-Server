<?php
include("checksession.php");
require_once __DIR__ . '/../shared/TpProductType.php';
header('Content-Type: application/json');
error_reporting(0);
tpEnsureAdvanceWalletColumns($db_conn);

if (($Login_user_TYPEvl ?? '') !== 'company') {
    echo json_encode(['balance' => 0.00, 'count' => 0, 'error' => 'unauthorized']); exit;
}

$tp_id     = (int)($_GET['tp_id']     ?? 0);
$godown_id = (int)($_GET['godown_id'] ?? 0);
if (!$tp_id) { echo json_encode(['balance' => 0.00, 'count' => 0]); exit; }

// Scoped to the Company-approved pool only — an SS-assigned TP may also hold
// a separate SS-approved balance (see super-stockist/get-tp-advance-balance.php),
// which tp-invoice-action.php never draws from for a company-side invoice, so
// showing it here would preview a balance the submit-time check would reject.
if ($godown_id > 0) {
    $stmt = $db_conn->prepare("SELECT product_type, COALESCE(SUM(balance_amount), 0) AS balance, COUNT(*) AS cnt FROM tp_advance_payments WHERE territory_partner_id = ? AND company_id = ? AND approver_type = 'company' AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL GROUP BY product_type");
    $stmt->bind_param("ii", $tp_id, $godown_id);
} else {
    $stmt = $db_conn->prepare("SELECT product_type, COALESCE(SUM(balance_amount), 0) AS balance, COUNT(*) AS cnt FROM tp_advance_payments WHERE territory_partner_id = ? AND approver_type = 'company' AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL GROUP BY product_type");
    $stmt->bind_param("i", $tp_id);
}
$stmt->execute();
$byType = ['napkin' => 0.0, 'diaper' => 0.0];
$cntByType = ['napkin' => 0, 'diaper' => 0];
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $t = tpResolveProductType($r['product_type']);
    $byType[$t] = (float)$r['balance'];
    $cntByType[$t] = (int)$r['cnt'];
}
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
    // Kept for older callers not yet updated for the wallet split — sum of
    // both wallets. New callers should use balance_napkin/balance_diaper.
    'balance'        => round($byType['napkin'] + $byType['diaper'], 2),
    'count'          => $cntByType['napkin'] + $cntByType['diaper'],
    'balance_napkin' => round($byType['napkin'], 2),
    'balance_diaper' => round($byType['diaper'], 2),
    'count_napkin'   => $cntByType['napkin'],
    'count_diaper'   => $cntByType['diaper'],
    'target'  => ($tgtRow && (int)$tgtRow['set_count'] > 0) ? round((float)$tgtRow['total_target'], 2) : null,
]);
