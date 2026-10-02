<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
require_once __DIR__ . '/../shared/TpShopInvoiceActionRequest.php';
header('Content-Type: application/json');
error_reporting(0);

function respond(bool $ok, string $message = ''): void
{
    echo json_encode(['success' => $ok, 'message' => $message]);
    exit;
}

if (!tpIsBdmEligibleForShopInvoiceRequests($db_conn, (int)$salesBdmID)) {
    respond(false, 'You are not eligible to review these requests.');
}

$id = (int)($_POST['id'] ?? 0);
$decision = $_POST['decision'] ?? '';
if ($id <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
    respond(false, 'Invalid request.');
}

tpEnsureShopInvoiceActionRequestTable($db_conn);

// Only a request belonging to a TP within this BDM's own Zone OR any
// subordinate's Zone (their whole "Our Team" subtree) may be reviewed —
// same subtree-aware scope as shop-invoice-permission-requests.php's own
// listing, so an eligible Chief BDM can act on a subordinate's TP request
// even when that subordinate isn't themselves marked eligible.
$tpIds = getBdmSubtreeAssignedTpIds($db_conn, (int)$salesBdmID);
if (empty($tpIds)) { respond(false, 'You have no assigned territory partners.'); }

$row = $db_conn->query("SELECT territory_partner_id, status FROM tp_shop_invoice_action_requests WHERE id = $id")->fetch_assoc();
if (!$row) { respond(false, 'Request not found.'); }
if (!in_array((int)$row['territory_partner_id'], $tpIds, true)) { respond(false, 'This request is not assigned to you.'); }
if ($row['status'] !== 'pending') { respond(false, 'This request has already been reviewed.'); }

$bdmName = $_SESSION['LOGIN_USER_NAME'] ?? '';
$ok = tpShopInvoiceActionRequestReview($db_conn, $id, $decision, (int)$salesBdmID, $bdmName);

respond($ok, $ok ? '' : 'Could not save — please refresh and try again.');
