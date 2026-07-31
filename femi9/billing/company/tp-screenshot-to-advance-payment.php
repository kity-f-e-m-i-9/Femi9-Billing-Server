<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once("include/GodownAccess.php");
include("config.php");
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

$screenshotId = (int)($_POST['screenshot_id'] ?? 0);
$companyId    = (int)($_POST['company_id'] ?? 0);
$amount       = round((float)($_POST['amount'] ?? 0), 2);
$paymentDate  = trim($_POST['payment_date'] ?? '');
$paymentMode  = trim($_POST['payment_mode'] ?? '');
$referenceNum = trim($_POST['reference_number'] ?? '');
$bankName     = trim($_POST['bank_name'] ?? '');
$remarks      = trim($_POST['remarks'] ?? '');
$createdBy    = $_SESSION['LOGIN_USER'] ?? '';

$allowedModes = ['Cash', 'Bank Transfer', 'Cheque', 'UPI', 'NEFT', 'RTGS', 'IMPS', 'Demand Draft', 'Other'];

$errors = [];
if ($screenshotId <= 0)                             $errors[] = 'Invalid screenshot.';
if ($companyId <= 0)                                $errors[] = 'Please select a company profile.';
if ($amount <= 0 || $amount > 99999999.99)          $errors[] = 'Invalid amount.';
if ($referenceNum === '')                           $errors[] = 'Reference number is required.';
if (!in_array($paymentMode, $allowedModes, true))   $errors[] = 'Invalid payment mode.';
if (empty($paymentDate)) {
    $errors[] = 'Payment date is required.';
} else {
    $d = DateTime::createFromFormat('Y-m-d', $paymentDate);
    if (!$d || $d->format('Y-m-d') !== $paymentDate) {
        $errors[] = 'Invalid payment date format.';
    } elseif ($d > new DateTime()) {
        $errors[] = 'Payment date cannot be in the future.';
    }
}

if (!empty($errors)) {
    respond(['success' => false, 'message' => implode(' ', $errors)], 400);
}

$stmt = $db_conn->prepare(
    "SELECT id, territory_partner_id, status, advance_payment_id FROM tp_purchase_order_screenshots WHERE id = ?"
);
$stmt->bind_param('i', $screenshotId);
$stmt->execute();
$screenshot = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$screenshot) {
    respond(['success' => false, 'message' => 'Screenshot not found.'], 404);
}
if ($screenshot['status'] !== 'accepted') {
    respond(['success' => false, 'message' => 'Only verified (accepted) screenshots can be added as a payment entry.'], 400);
}
if ($screenshot['advance_payment_id'] !== null) {
    respond(['success' => false, 'message' => 'This screenshot has already been added as a payment entry.'], 400);
}

$tpId = (int)$screenshot['territory_partner_id'];

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

$db_conn->begin_transaction();
try {
    $adjusted = 0.00;
    $balance = $amount;
    $status = 'active';

    $ins = $db_conn->prepare(
        "INSERT INTO tp_advance_payments
            (company_id, territory_partner_id, amount, payment_date, payment_mode, reference_number, bank_name, remarks,
             adjusted_amount, balance_amount, status, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $ins->bind_param(
        'iidssssssdss',
        $companyId, $tpId, $amount, $paymentDate, $paymentMode, $referenceNum, $bankName, $remarks,
        $adjusted, $balance, $status, $createdBy
    );
    if (!$ins->execute()) throw new \Exception('Insert failed: ' . $ins->error);
    $advancePaymentId = $db_conn->insert_id;
    $ins->close();

    $link = $db_conn->prepare(
        "UPDATE tp_purchase_order_screenshots SET advance_payment_id = ? WHERE id = ? AND advance_payment_id IS NULL"
    );
    $link->bind_param('ii', $advancePaymentId, $screenshotId);
    $link->execute();
    if ($link->affected_rows !== 1) throw new \Exception('This screenshot has already been added as a payment entry.');
    $link->close();

    $db_conn->commit();
    respond(['success' => true, 'advance_payment_id' => $advancePaymentId, 'message' => 'Added to TP advance payments.']);
} catch (\Throwable $e) {
    $db_conn->rollback();
    respond(['success' => false, 'message' => 'Failed to record payment. ' . $e->getMessage()], 500);
}
