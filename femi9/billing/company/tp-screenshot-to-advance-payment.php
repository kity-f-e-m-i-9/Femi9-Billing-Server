<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once("include/GodownAccess.php");
include("config.php");
require_once __DIR__ . '/../shared/TpProductType.php';
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
    "SELECT s.id, s.territory_partner_id, s.status, s.advance_payment_id, po.product_type AS po_product_type
     FROM tp_purchase_order_screenshots s
     LEFT JOIN tp_purchase_orders po ON po.id = s.po_id
     WHERE s.id = ?"
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

// Inherits the type from the screenshot's own PO (the most reliable source
// — this money was raised specifically to cover that order), unless a
// reviewer explicitly overrides it or the screenshot was never linked to a
// PO (uploaded then abandoned before submit), in which case it falls back
// to napkin like any other untyped legacy row.
tpEnsureAdvanceWalletColumns($db_conn);
$productTypeOverride = isset($_POST['product_type']) ? (string)$_POST['product_type'] : null;
$productType = $productTypeOverride !== null
    ? tpResolveProductType($productTypeOverride)
    : tpResolveProductType($screenshot['po_product_type'] ?? null);

$db_conn->begin_transaction();
try {
    $adjusted = 0.00;
    $balance = $amount;
    $status = 'active';

    // Company-side conversion — the screenshot's parent PO is guaranteed
    // company-routed (company/tp-today-orders.php only shows those), so this
    // always credits the company-approved pool.
    $approverType = 'company';
    $approverSsId = null;
    $ins = $db_conn->prepare(
        "INSERT INTO tp_advance_payments
            (company_id, territory_partner_id, product_type, approver_type, approver_ss_id, amount, payment_date, payment_mode, reference_number, bank_name, remarks,
             adjusted_amount, balance_amount, status, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $ins->bind_param(
        'iissidsssssddss',
        $companyId, $tpId, $productType, $approverType, $approverSsId, $amount, $paymentDate, $paymentMode, $referenceNum, $bankName, $remarks,
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
