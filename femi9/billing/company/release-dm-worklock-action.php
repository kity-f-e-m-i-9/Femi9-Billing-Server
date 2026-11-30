<?php
// Company-only emergency override for work-start-requests.php's "DMs Locked
// Out Today" panel -- forces every one of a stuck DM's today's rows to
// resolved, regardless of what the normal TP-accept / Company-resolve chain
// has done so far. For when that chain is running late and the DM genuinely
// can't get into the portal to work. Deliberately NOT exposed on the
// marketing side -- only Company can do this (2026-11-20, user request).
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms_edit');
require_once __DIR__ . '/../shared/TpWorkStart.php';
require_once __DIR__ . '/../marketing/include/AssignedLocations.php';
header('Content-Type: application/json');
error_reporting(0);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

tpWorkStartEnsureColumns($db_conn);

$msId = (int)($_POST['ms_id'] ?? 0);
if (!$msId) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $msId);
$scopeIds = array_column($scopeNodes, 'id');
if (empty($scopeIds)) {
    echo json_encode(['success' => false, 'error' => 'This staff member has no assigned locations.']);
    exit;
}

$resolvedBy = $_SESSION['LOGIN_USER_NAME'] ?? ($_SESSION['LOGIN_USER'] ?? 'company');
$affected = forceReleaseDmWorkLockToday($db_conn, $scopeIds, $resolvedBy);

if ($affected === 0) {
    echo json_encode(['success' => false, 'error' => 'Nothing to release — this DM is not currently locked.']);
    exit;
}

echo json_encode(['success' => true, 'rows_released' => $affected]);
