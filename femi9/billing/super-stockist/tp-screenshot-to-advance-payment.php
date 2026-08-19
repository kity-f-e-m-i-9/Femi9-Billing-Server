<?php
include("checksession.php");
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);

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

$screenshotId = (int)($_POST['screenshot_id'] ?? 0);
$amount       = round((float)($_POST['amount'] ?? 0), 2);
$paymentDate  = trim($_POST['payment_date'] ?? '');
$paymentMode  = trim($_POST['payment_mode'] ?? '');
$referenceNum = trim($_POST['reference_number'] ?? '');
$remarks      = trim($_POST['remarks'] ?? '');
$createdBy    = $_SESSION['LOGIN_USER'] ?? '';

$allowedModes = ['Cash', 'Bank Transfer', 'Cheque', 'UPI', 'NEFT', 'RTGS', 'IMPS', 'Demand Draft', 'Other'];

$errors = [];
if ($screenshotId <= 0)                             $errors[] = 'Invalid screenshot.';
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

// Screenshot must belong to a PO actually routed to this SS — joins through
// tp_purchase_orders rather than trusting the screenshot row's own
// territory_partner_id alone, since that alone doesn't prove approver
// routing or SS ownership.
$stmt = $db_conn->prepare(
    "SELECT s.id, s.territory_partner_id, s.status, s.advance_payment_id, po.product_type AS po_product_type
     FROM tp_purchase_order_screenshots s
     JOIN tp_purchase_orders po ON po.id = s.po_id
     JOIN territory_partners tp ON tp.id = s.territory_partner_id
     WHERE s.id = ? AND tp.onboard_ss_id = ? AND po.approver_type = 'ss' AND po.approver_ss_id = ?"
);
$stmt->bind_param('isi', $screenshotId, $ss_temp_id, $ss_account_id);
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

// Inherits the type from the screenshot's own PO — see the matching comment
// in company/tp-screenshot-to-advance-payment.php. The JOIN above is INNER
// (po_id is never NULL here), so po_product_type is always real.
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
    $approverType = 'ss';

    // No company_id/company_godown here — that's Company's own GST/finance
    // bookkeeping concept, unrelated to SS-routed advance payments.
    $ins = $db_conn->prepare(
        "INSERT INTO tp_advance_payments
            (territory_partner_id, product_type, approver_type, approver_ss_id, amount, payment_date, payment_mode, reference_number, remarks,
             adjusted_amount, balance_amount, status, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $ins->bind_param(
        'issidssssddss',
        $tpId, $productType, $approverType, $ss_account_id, $amount, $paymentDate, $paymentMode, $referenceNum, $remarks,
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
