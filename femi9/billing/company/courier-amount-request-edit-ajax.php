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
$amount = (float)($_POST['amount'] ?? -1);
if ($id <= 0 || $amount < 0) {
    respond(false, 'Enter a valid amount.');
}

tpEnsureCourierAmountRequestTable($db_conn);

$row = $db_conn->query("SELECT status FROM tp_courier_amount_requests WHERE id = $id")->fetch_assoc();
if (!$row) { respond(false, 'Request not found.'); }
if ($row['status'] !== 'approved') { respond(false, 'Only an already-approved request\'s amount can be corrected here.'); }

// Company correcting bypasses the assigned Sales BDM entirely — no bdm_id to
// attach, but reviewed_by_name still records that Company acted.
$reviewerName = trim((string)($_SESSION['LOGIN_USER_NAME'] ?? 'Company')) . ' (Company)';
$ok = tpCourierAmountRequestEditAmount($db_conn, $id, $amount, null, $reviewerName);

respond($ok, $ok ? '' : 'Could not save — please refresh and try again.');
