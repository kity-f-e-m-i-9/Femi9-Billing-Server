<?php
// Resolve endpoint for marketing/work-start-requests.php (ASM side) — same
// as company/work-start-resolve-action.php, but the row id is re-checked
// against the caller's own subtree districts first, same rule tp-assign-
// firka-action.php's save path uses.
include("checksession.php");
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/include/TeamSubtree.php';
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

$msId = (int)$Login_user_IDvl;
$subtreeIds = getMsSubtreeIds($db_conn, $msId);

// getMsSubtreeIds() always includes the caller themselves, so without this
// check a plain DM (no subordinates) could escalate their own Firka via
// daily-work-start-action.php's ask_company, then resolve it themselves
// right here -- self-approving the exact step that's supposed to require
// ASM/Company authority, and bypassing the TP's own Accept/Deny consent
// entirely. Only someone with at least one subordinate (same $hasTeamMN
// convention femi_menu.php gates this page's own link behind) may resolve.
if (count($subtreeIds) <= 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only ASM/Company can resolve this.']);
    exit;
}

$allowedDistrictIds = [];
foreach ($subtreeIds as $sid) {
    foreach (getMsAssignedDistricts($db_conn, $sid) as $d) {
        $allowedDistrictIds[(int)$d['id']] = true;
    }
}
if (empty($allowedDistrictIds)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No assigned area.']);
    exit;
}
$distList = implode(',', array_map('intval', array_keys($allowedDistrictIds)));

$stmtChk = $db_conn->prepare("
    SELECT d.id
    FROM tp_daily_assignments tda
    JOIN partner_location_nodes f  ON f.id  = tda.location_id
    JOIN partner_location_nodes t  ON t.id  = f.parent_id
    JOIN partner_location_nodes dv ON dv.id = t.parent_id
    JOIN partner_location_nodes d  ON d.id  = dv.parent_id
    WHERE tda.id = ? AND d.id IN ($distList)
");
$stmtChk->bind_param('i', $id);
$stmtChk->execute();
$found = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();

if (!$found) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'This request is outside your team\'s territory.']);
    exit;
}

$resolvedBy = $_SESSION['LOGIN_USER_NAME'] ?? '';
$status = 'accepted';
$stmt = $db_conn->prepare("
    UPDATE tp_daily_assignments
    SET territory_partner_id = ?,
        tp_approval_status = ?, tp_denial_reason = NULL, tp_responded_at = NOW(),
        company_request_status = 'resolved', company_resolved_by = ?, company_resolved_at = NOW()
    WHERE id = ? AND company_request_status = 'pending'
");
$stmt->bind_param('issi', $tpId, $status, $resolvedBy, $id);
$stmt->execute();
$changed = $stmt->affected_rows > 0;
$stmt->close();

if (!$changed) {
    echo json_encode(['success' => false, 'error' => 'This request was already resolved.']);
    exit;
}

echo json_encode(['success' => true]);
