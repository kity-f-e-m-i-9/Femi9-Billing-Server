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
$decision = $_POST['decision'] ?? '';
if ($id <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
    respond(false, 'Invalid request.');
}

tpEnsureCourierAmountRequestTable($db_conn);

// Only a request belonging to a TP this BDM is actually assigned to (same
// district-scoping every other salesbdm page uses) may be reviewed — a
// request id guessed/tampered from outside this BDM's own TP list is
// rejected here rather than trusted from the client.
$tpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID);
if (empty($tpIds)) { respond(false, 'You have no assigned territory partners.'); }
$tpIdList = implode(',', array_map('intval', $tpIds));

$row = $db_conn->query("SELECT territory_partner_id, status FROM tp_courier_amount_requests WHERE id = $id")->fetch_assoc();
if (!$row) { respond(false, 'Request not found.'); }
if (!in_array((int)$row['territory_partner_id'], $tpIds, true)) { respond(false, 'This request is not assigned to you.'); }
if ($row['status'] !== 'pending') { respond(false, 'This request has already been reviewed.'); }

$approvedAmount = null;
if ($decision === 'approved') {
    $approvedAmount = (float)($_POST['amount'] ?? -1);
    if ($approvedAmount < 0) { respond(false, 'Enter a valid amount.'); }
}

$bdmName = $_SESSION['LOGIN_USER_NAME'] ?? '';
$ok = tpCourierAmountRequestReview($db_conn, $id, $decision, $approvedAmount, (int)$salesBdmID, $bdmName);

respond($ok, $ok ? '' : 'Could not save — please refresh and try again.');
