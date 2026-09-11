<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
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

// Same district-scoping every other salesbdm courier-amount action uses —
// a request id guessed/tampered from outside this BDM's own TP list is
// rejected rather than trusted from the client.
$tpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID);
if (empty($tpIds)) { respond(false, 'You have no assigned territory partners.'); }

$row = $db_conn->query("SELECT territory_partner_id, status FROM tp_courier_amount_requests WHERE id = $id")->fetch_assoc();
if (!$row) { respond(false, 'Request not found.'); }
if (!in_array((int)$row['territory_partner_id'], $tpIds, true)) { respond(false, 'This request is not assigned to you.'); }
if ($row['status'] !== 'approved') { respond(false, 'Only an already-approved request\'s amount can be corrected here.'); }

$bdmName = $_SESSION['LOGIN_USER_NAME'] ?? '';
$ok = tpCourierAmountRequestEditAmount($db_conn, $id, $amount, (int)$salesBdmID, $bdmName);

respond($ok, $ok ? '' : 'Could not save — please refresh and try again.');
