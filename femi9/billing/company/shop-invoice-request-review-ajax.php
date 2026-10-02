<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
include("config.php");
require_once __DIR__ . '/../shared/TpShopInvoiceActionRequest.php';
header('Content-Type: application/json');
error_reporting(0);

function respond(bool $ok, string $message = ''): void
{
    echo json_encode(['success' => $ok, 'message' => $message]);
    exit;
}

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    respond(false, 'Session expired — please refresh and try again.');
}

$id = (int)($_POST['id'] ?? 0);
$decision = $_POST['decision'] ?? '';
if ($id <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
    respond(false, 'Invalid request.');
}

tpEnsureShopInvoiceActionRequestTable($db_conn);

$row = $db_conn->query("SELECT status FROM tp_shop_invoice_action_requests WHERE id = $id")->fetch_assoc();
if (!$row) { respond(false, 'Request not found.'); }
if ($row['status'] !== 'pending') { respond(false, 'This request has already been reviewed.'); }

// Company reviewing directly bypasses the assigned Sales BDM entirely (and
// doesn't require that BDM to be marked eligible) — no bdm_id to attach,
// but reviewed_by_name still records that Company acted, distinguished
// from a BDM's own name so the audit trail stays unambiguous.
$reviewerName = trim((string)($_SESSION['LOGIN_USER_NAME'] ?? 'Company')) . ' (Company)';
$ok = tpShopInvoiceActionRequestReview($db_conn, $id, $decision, null, $reviewerName);

respond($ok, $ok ? '' : 'Could not save — please refresh and try again.');
