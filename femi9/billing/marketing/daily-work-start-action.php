<?php
// Action endpoint for daily-work-start.php — 'ask_company' is now the ONLY
// action: it sends the Firka to ASM/Company (work-start-requests.php +
// work-start-resolve-action.php) for them to explicitly accept — only that
// resolve flips tp_approval_status to 'accepted' and unlocks this DM.
//
// 'tp_approve' (a DM self-accepting a Firka with no one else involved) was
// removed entirely 2026-11-30 per user request ("no tp approve click pannura
// optioney venam, ask ASM/Company nu mattum than irukanum, asm/Company
// yaravathu accept pannanum apo tha login work aganum") — the button is gone
// from daily-work-start.php, and this endpoint now rejects the action too
// (defense in depth, in case of a stale cached page/replayed request).
// Before this it briefly went straight to 'accepted' with no TP-side step
// (2026-11-24 fix) — that history is why tp_approval_status can still be
// 'accepted' without ever having passed through 'requested'.
//
// territory-partner/work-start-confirm.php and work-start-confirm-action.php
// are left in place (nothing else references them) but sit unreachable in
// practice, since nothing is ever left in 'requested'/'denied' for them to
// show — a TP visiting that URL directly still just sees "nothing pending"
// and bounces to their own dashboard.
//
// The row id is re-checked against the caller's own assigned-location scope
// (same rule tp-assign-firka-action.php's save path uses) so a DM can only
// ever act on a Firka inside their own area.
include("checksession.php");
require_once __DIR__ . '/include/AssignedLocations.php';
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
if (!$id || $action !== 'ask_company') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$msId = (int)$Login_user_IDvl;
$scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $msId);
$scopeIds = array_column($scopeNodes, 'id');
if (empty($scopeIds)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No assigned area.']);
    exit;
}
$scopeList = implode(',', array_map('intval', $scopeIds));

$stmtChk = $db_conn->prepare("
    WITH RECURSIVE node_tree AS (
        SELECT id FROM partner_location_nodes WHERE id IN ($scopeList)
        UNION ALL
        SELECT n.id FROM partner_location_nodes n JOIN node_tree nt ON n.parent_id = nt.id
    )
    SELECT tda.id, tda.territory_partner_id, tda.tp_approval_status, tda.tp_denial_reason
    FROM tp_daily_assignments tda
    WHERE tda.id = ? AND tda.assign_date = CURDATE() AND tda.location_id IN (SELECT id FROM node_tree)
");
$stmtChk->bind_param('i', $id);
$stmtChk->execute();
$row = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();

if (!$row) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'This assignment is outside your area or not for today.']);
    exit;
}

// ask_company — only valid from 'none' (never asked the TP, or no TP to
// ask) or 'denied' (TP already said no) -- NOT 'requested' (TP hasn't had a
// chance to respond yet) or 'accepted' (already working, nothing to escalate).
if (!in_array($row['tp_approval_status'], ['none', 'denied'], true)) {
    echo json_encode(['success' => false, 'error' => 'This assignment is not in a state that can be escalated.']);
    exit;
}

if ($row['territory_partner_id']) {
    $note = $row['tp_approval_status'] === 'denied'
        ? 'TP not available — denied: ' . ($row['tp_denial_reason'] ?? '')
        : 'TP not available';
} else {
    $note = 'Vacancy Area';
}

$stmt = $db_conn->prepare("
    UPDATE tp_daily_assignments
    SET company_request_status = 'pending', company_request_note = ?, company_requested_at = NOW()
    WHERE id = ? AND company_request_status != 'pending'
");
$stmt->bind_param('si', $note, $id);
$stmt->execute();
$changed = $stmt->affected_rows > 0;
$stmt->close();

if (!$changed) {
    echo json_encode(['success' => false, 'error' => 'A request is already pending for this Firka.']);
    exit;
}

echo json_encode(['success' => true]);
