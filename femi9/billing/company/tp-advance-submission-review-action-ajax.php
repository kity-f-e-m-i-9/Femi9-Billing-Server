<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
include("config.php");
require_once __DIR__ . '/include/AdvancePaymentScreenshotHelper.php';
error_reporting(0);

header('Content-Type: application/json');

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Session expired — please reload the page and try again.']);
    exit;
}

$submissionId = (int)($_POST['submission_id'] ?? 0);
$action       = $_POST['action'] ?? '';
$reviewedBy   = $_SESSION['LOGIN_USER'] ?? '';

if ($submissionId < 1 || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
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
