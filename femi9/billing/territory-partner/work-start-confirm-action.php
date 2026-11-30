<?php
// Accept/Deny endpoint for work-start-confirm.php. Scope-checked against the
// logged-in TP's own id (territory_partner_id) and today's date, so a TP can
// never respond to a request that isn't theirs.
include("checksession.php");
require_once __DIR__ . '/../shared/TpWorkStart.php';
header('Content-Type: application/json');
error_reporting(0);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

tpWorkStartEnsureColumns($db_conn);

$id     = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$reason = trim($_POST['reason'] ?? '');

if (!$id || !in_array($action, ['accept', 'deny'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}
if ($action === 'deny' && $reason === '') {
    echo json_encode(['success' => false, 'error' => 'Reason is required.']);
    exit;
}

$tpId = (int)$Login_user_IDvl;
$status = $action === 'accept' ? 'accepted' : 'denied';
$reasonVal = $action === 'deny' ? $reason : null;

$stmt = $db_conn->prepare("
    UPDATE tp_daily_assignments
    SET tp_approval_status = ?, tp_denial_reason = ?, tp_responded_at = NOW()
    WHERE id = ? AND territory_partner_id = ? AND assign_date = CURDATE() AND tp_approval_status = 'requested'
");
$stmt->bind_param('ssii', $status, $reasonVal, $id, $tpId);
$stmt->execute();
$changed = $stmt->affected_rows > 0;
$stmt->close();

if (!$changed) {
    echo json_encode(['success' => false, 'error' => 'This request is no longer available.']);
    exit;
}

echo json_encode(['success' => true]);
