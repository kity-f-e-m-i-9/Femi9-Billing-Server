<?php
// Resolve endpoint for work-start-requests.php — company confirms (or
// changes) which TP covers today's Firka, which immediately unlocks that
// TP's own portal for today (tp_approval_status='accepted' bypasses the
// TP's own confirmation, since company has already vouched for them).
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms_edit');
require_once __DIR__ . '/../shared/TpWorkStart.php';
header('Content-Type: application/json');
error_reporting(0);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

tpWorkStartEnsureColumns($db_conn);

$id   = (int)($_POST['id'] ?? 0);
$tpId = isset($_POST['tp_id']) && $_POST['tp_id'] !== '' ? (int)$_POST['tp_id'] : null;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$resolvedBy = $_SESSION['LOGIN_USER_NAME'] ?? '';
$stmt = $db_conn->prepare("
    UPDATE tp_daily_assignments
    SET territory_partner_id = ?,
        tp_approval_status = ?, tp_denial_reason = NULL, tp_responded_at = NOW(),
        company_request_status = 'resolved', company_resolved_by = ?, company_resolved_at = NOW()
    WHERE id = ? AND company_request_status = 'pending'
");
$status = 'accepted'; // a Vacant Area resolution (tp_id null) has no TP to gate — status is harmless either way
$stmt->bind_param('issi', $tpId, $status, $resolvedBy, $id);
$stmt->execute();
$changed = $stmt->affected_rows > 0;
$stmt->close();

if (!$changed) {
    echo json_encode(['success' => false, 'error' => 'This request was already resolved.']);
    exit;
}

echo json_encode(['success' => true]);
