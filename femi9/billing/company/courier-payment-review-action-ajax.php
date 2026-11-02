<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
include("config.php");
error_reporting(0);

header('Content-Type: application/json');

$id       = (int)($_POST['courier_payment_id'] ?? 0);
$action   = $_POST['action'] ?? '';
$reviewedBy = $_SESSION['LOGIN_USER'] ?? '';

if ($id < 1 || !in_array($action, ['approve', 'reject', 'delete'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Delete is for a wrongly-accepted screenshot (e.g. the TP uploaded someone
// else's payment proof under their own login) -- reached from the Accepted
// tab, not the pending_review approve/reject flow below, so it's checked
// and handled separately before the pending_review lookup.
if ($action === 'delete') {
    $delStmt = $db_conn->prepare("SELECT id, file_path FROM tp_courier_payments WHERE id = ? AND status = 'accepted'");
    $delStmt->bind_param('i', $id);
    $delStmt->execute();
    $delRow = $delStmt->get_result()->fetch_assoc();
    $delStmt->close();

    if (!$delRow) {
        echo json_encode(['success' => false, 'message' => 'This screenshot is not in Accepted status (it may already have been removed).']);
        exit;
    }

    $del = $db_conn->prepare("DELETE FROM tp_courier_payments WHERE id = ? AND status = 'accepted'");
    $del->bind_param('i', $id);
    $del->execute();
    $deleted = $del->affected_rows === 1;
    $del->close();

    if ($deleted && $delRow['file_path']) {
        $filePath = __DIR__ . '/../territory-partner/courier_payment_screenshots/' . $delRow['file_path'];
        if (is_file($filePath)) @unlink($filePath);
    }

    echo json_encode($deleted
        ? ['success' => true, 'message' => 'Screenshot removed.']
        : ['success' => false, 'message' => 'Could not remove this screenshot. Please try again.']);
    exit;
}

$stmt = $db_conn->prepare("SELECT id, status FROM tp_courier_payments WHERE id = ? AND status = 'pending_review'");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'This screenshot has already been reviewed.']);
    exit;
}

if ($action === 'reject') {
    $upd = $db_conn->prepare("UPDATE tp_courier_payments SET status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE id=?");
    $upd->bind_param('si', $reviewedBy, $id);
    $upd->execute();
    $upd->close();
    echo json_encode(['success' => true, 'message' => 'Screenshot rejected.']);
    exit;
}

// The pool that unlocks a PO submission sums detected_amount, so a
// pending_review row with no auto-detected amount (OCR/vision couldn't read
// it) must get a reviewer-confirmed amount here — approving without one
// would count as ₹0 and silently never unlock anything.
$confirmedAmountRaw = $_POST['confirmed_amount'] ?? null;
if ($confirmedAmountRaw !== null && $confirmedAmountRaw !== '') {
    $confirmedAmount = round((float)$confirmedAmountRaw, 2);
    $upd = $db_conn->prepare("UPDATE tp_courier_payments SET status='accepted', detected_amount=?, rejection_reason=NULL, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
    $upd->bind_param('dsi', $confirmedAmount, $reviewedBy, $id);
} else {
    $upd = $db_conn->prepare("UPDATE tp_courier_payments SET status='accepted', rejection_reason=NULL, reviewed_by=?, reviewed_at=NOW() WHERE id=? AND detected_amount IS NOT NULL");
    $upd->bind_param('si', $reviewedBy, $id);
}
$upd->execute();
$ok = $upd->affected_rows === 1;
$upd->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'This screenshot has no readable amount — enter the confirmed amount to approve it.']);
    exit;
}
echo json_encode(['success' => true, 'message' => 'Screenshot approved.']);
