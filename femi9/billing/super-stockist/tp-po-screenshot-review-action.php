<?php
include("checksession.php");
error_reporting(0);
require_once __DIR__ . '/../territory-partner/include/PoScreenshotHelper.php';

if (($Login_user_TYPEvl ?? '') !== 'super_stockiest') {
    header("Location: dashboard.php?error=unauthorized"); exit;
}

$ss_temp_id    = $Login_user_IDvl;
$ss_account_id = (int)($result_LoGuserDtails['id'] ?? 0);

$screenshotId = (int)($_POST['screenshot_id'] ?? 0);
$action       = $_POST['action'] ?? '';
$reviewedBy   = $_SESSION['LOGIN_USER'] ?? '';

if ($screenshotId < 1 || !in_array($action, ['approve', 'reject'], true)) {
    $_SESSION['errorMessage'] = 'Invalid request.';
    header("Location: tp-po-screenshot-review.php");
    exit;
}

// Ownership check — this screenshot's PO must actually be routed to this SS.
$own = $db_conn->prepare(
    "SELECT s.id
     FROM tp_purchase_order_screenshots s
     JOIN tp_purchase_orders po ON po.id = s.po_id
     JOIN territory_partners tp ON tp.id = s.territory_partner_id
     WHERE s.id = ? AND tp.onboard_ss_id = ? AND po.approver_type = 'ss' AND po.approver_ss_id = ?"
);
$own->bind_param('isi', $screenshotId, $ss_temp_id, $ss_account_id);
$own->execute();
$owned = $own->get_result()->fetch_assoc();
$own->close();

if (!$owned) {
    $_SESSION['errorMessage'] = 'Screenshot not found.';
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
