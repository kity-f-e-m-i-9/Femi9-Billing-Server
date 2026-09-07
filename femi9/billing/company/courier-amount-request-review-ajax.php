<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
include("config.php");
require_once __DIR__ . '/../shared/TpCourierAmountRequest.php';
header('Content-Type: application/json');
error_reporting(0);

function respond(bool $ok, string $message = ''): void
{
    echo json_encode(['success' => $ok, 'message' => $message]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$decision = $_POST['decision'] ?? '';
if ($id <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
    respond(false, 'Invalid request.');
}

tpEnsureCourierAmountRequestTable($db_conn);

$row = $db_conn->query("SELECT status FROM tp_courier_amount_requests WHERE id = $id")->fetch_assoc();
if (!$row) { respond(false, 'Request not found.'); }
if ($row['status'] !== 'pending') { respond(false, 'This request has already been reviewed.'); }

$approvedAmount = null;
if ($decision === 'approved') {
    $approvedAmount = (float)($_POST['amount'] ?? -1);
    if ($approvedAmount < 0) { respond(false, 'Enter a valid amount.'); }
}

// Company reviewing directly bypasses the assigned Sales BDM entirely — no
// bdm_id to attach, but reviewed_by_name still records that Company acted,
// distinguished from a BDM's own name so the audit trail stays unambiguous.
$reviewerName = trim((string)($_SESSION['LOGIN_USER_NAME'] ?? 'Company')) . ' (Company)';
$ok = tpCourierAmountRequestReview($db_conn, $id, $decision, $approvedAmount, null, $reviewerName);

respond($ok, $ok ? '' : 'Could not save — please refresh and try again.');
