<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once("include/GodownAccess.php");
include("config.php");
require_once __DIR__ . '/include/AdvancePaymentScreenshotHelper.php';
error_reporting(0);

header('Content-Type: application/json');

function respond(array $payload, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode($payload);
    exit;
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    respond(['success' => false, 'message' => 'Session expired — please reload the page and try again.'], 400);
}

$submissionId = (int)($_POST['submission_id'] ?? 0);
$companyId    = (int)($_POST['company_id'] ?? 0);
$amount       = round((float)($_POST['amount'] ?? 0), 2);
$paymentDate  = trim($_POST['payment_date'] ?? '');
$paymentMode  = trim($_POST['payment_mode'] ?? '');
$referenceNum = trim($_POST['reference_number'] ?? '');
$note         = trim($_POST['note'] ?? '');
$createdBy    = $_SESSION['LOGIN_USER'] ?? '';

if ($submissionId < 1) {
    respond(['success' => false, 'message' => 'Invalid submission.'], 400);
}

if ($companyId > 0) {
    $chkCompany = $db_conn->prepare("SELECT id FROM company_godown WHERE id = ? LIMIT 1");
    $chkCompany->bind_param('i', $companyId);
    $chkCompany->execute();
    if (!$chkCompany->get_result()->fetch_assoc()) {
        respond(['success' => false, 'message' => 'Company profile not found.'], 400);
    }
    $chkCompany->close();

    if (!is_godown_allowed($db_conn, $companyId)) {
        respond(['success' => false, 'message' => 'You are not authorized to record payments for this company profile.'], 403);
    }
}

$productTypeOverride = isset($_POST['product_type']) ? (string)$_POST['product_type'] : null;

$result = convertAdvancePaymentSubmissionToAdvancePayment(
    $db_conn, $submissionId, $amount, $paymentDate, $paymentMode, $referenceNum, $note, $companyId, $createdBy, $productTypeOverride
);

echo json_encode($result);
