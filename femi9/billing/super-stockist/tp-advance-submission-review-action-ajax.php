<?php
include("checksession.php");
error_reporting(0);
require_once __DIR__ . '/../company/include/AdvancePaymentScreenshotHelper.php';

header('Content-Type: application/json');

if (($Login_user_TYPEvl ?? '') !== 'super_stockiest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Session expired — please reload the page and try again.']);
    exit;
}

$ss_temp_id    = $Login_user_IDvl;
$ss_account_id = (int)($result_LoGuserDtails['id'] ?? 0);

$submissionId = (int)($_POST['submission_id'] ?? 0);
$action       = $_POST['action'] ?? '';
$reviewedBy   = $_SESSION['LOGIN_USER'] ?? '';

if ($submissionId < 1 || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
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
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Submission not found.']);
    exit;
}

if ($action === 'reject') {
    $reason = trim($_POST['rejection_reason'] ?? '');
    $result = rejectAdvancePaymentSubmission($db_conn, $submissionId, $reviewedBy, $reason);
    echo json_encode($result);
    exit;
}

$confirmedAmount    = round((float)($_POST['confirmed_amount'] ?? 0), 2);
$confirmedReference = trim($_POST['confirmed_reference'] ?? '');

$result = approveAdvancePaymentSubmission($db_conn, $submissionId, $confirmedAmount, $confirmedReference, $reviewedBy);

echo json_encode($result);
