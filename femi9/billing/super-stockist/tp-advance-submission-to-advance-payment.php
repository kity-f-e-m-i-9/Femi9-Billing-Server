<?php
include("checksession.php");
error_reporting(0);
require_once __DIR__ . '/include/AdvancePaymentScreenshotHelper.php';

header('Content-Type: application/json');

function respond(array $payload, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode($payload);
    exit;
}

if (($Login_user_TYPEvl ?? '') !== 'super_stockiest') {
    respond(['success' => false, 'message' => 'Unauthorized.'], 403);
}
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    respond(['success' => false, 'message' => 'Session expired — please reload the page and try again.'], 400);
}

$ss_temp_id    = $Login_user_IDvl;
$ss_account_id = (int)($result_LoGuserDtails['id'] ?? 0);

$submissionId = (int)($_POST['submission_id'] ?? 0);
$amount       = round((float)($_POST['amount'] ?? 0), 2);
$paymentDate  = trim($_POST['payment_date'] ?? '');
$paymentMode  = trim($_POST['payment_mode'] ?? '');
$referenceNum = trim($_POST['reference_number'] ?? '');
$note         = trim($_POST['note'] ?? '');
$createdBy    = $_SESSION['LOGIN_USER'] ?? '';

if ($submissionId < 1) {
    respond(['success' => false, 'message' => 'Invalid submission.'], 400);
}

// Ownership check — this submission must be routed to this SS, for one of this SS's own TPs.
$own = $db_conn->prepare(
    "SELECT sub.id
     FROM tp_advance_payment_submissions sub
     JOIN territory_partners tp ON tp.id = sub.territory_partner_id
     WHERE sub.id = ? AND tp.onboard_ss_id = ? AND sub.approver_type = 'ss' AND sub.approver_ss_id = ?"
);
$own->bind_param('isi', $submissionId, $ss_temp_id, $ss_account_id);
$own->execute();
$owned = $own->get_result()->fetch_assoc();
$own->close();

if (!$owned) {
    respond(['success' => false, 'message' => 'Submission not found.'], 404);
}

$productTypeOverride = isset($_POST['product_type']) ? (string)$_POST['product_type'] : null;

$result = convertAdvancePaymentSubmissionToSsAdvancePayment(
    $db_conn, $submissionId, $amount, $paymentDate, $paymentMode, $referenceNum, $note, $ss_account_id, $createdBy, $productTypeOverride
);

echo json_encode($result);
