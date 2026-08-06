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
$reason       = trim($_POST['reason'] ?? '');
$cancelledBy  = $_SESSION['LOGIN_USER'] ?? '';

if ($submissionId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid submission.']);
    exit;
}

$result = cancelAdvancePaymentSubmission($db_conn, $submissionId, $cancelledBy, $reason);

echo json_encode($result);
