<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
include("config.php");
require_once __DIR__ . '/../territory-partner/include/PoScreenshotHelper.php';
error_reporting(0);

$screenshotId = (int)($_POST['screenshot_id'] ?? 0);
$action       = $_POST['action'] ?? '';
$reviewedBy   = $_SESSION['LOGIN_USER'] ?? '';

if ($screenshotId < 1 || !in_array($action, ['approve', 'reject'], true)) {
    $_SESSION['errorMessage'] = 'Invalid request.';
    header("Location: tp-po-screenshot-review.php");
    exit;
}

if ($action === 'reject') {
    $result = rejectPoScreenshot($db_conn, $screenshotId, $reviewedBy);
} else {
    $confirmedAmount    = round((float)($_POST['confirmed_amount'] ?? 0), 2);
    $confirmedReference = trim($_POST['confirmed_reference'] ?? '');
    $result = approvePoScreenshot($db_conn, $screenshotId, $confirmedAmount, $confirmedReference, $reviewedBy);
}

if ($result['success']) {
    $_SESSION['successMessage'] = $result['message'];
} else {
    $_SESSION['errorMessage'] = $result['message'];
}
header("Location: tp-po-screenshot-review.php");
exit;
