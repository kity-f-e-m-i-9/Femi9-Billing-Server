<?php
ob_start();
include("checksession.php");
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'super_stockiest') {
    header("Location: manage-tp-advance-payments?error=unauthorized"); exit;
}
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: manage-tp-advance-payments"); exit;
}
if (!isset($_POST['add_tp_advance_payment'])) {
    header("Location: add-tp-advance-payment"); exit;
}

tpEnsureAdvanceWalletColumns($db_conn);

$ss_id        = $Login_user_IDvl;
$tp_id        = (int)($_POST['territory_partner_id'] ?? 0);
$productType  = tpResolveProductType($_POST['product_type'] ?? null);
$amount       = round((float)($_POST['amount'] ?? 0), 2);
$payment_date = trim($_POST['payment_date'] ?? '');
$payment_mode = trim($_POST['payment_mode'] ?? '');
$reference_num = trim($_POST['reference_number'] ?? '');
$bank_name    = trim($_POST['bank_name'] ?? '');
$remarks      = trim($_POST['remarks'] ?? '');
$created_by   = $_SESSION['LOGIN_USER'] ?? '';

$allowed_modes = ['Cash','Bank Transfer','Cheque','UPI','NEFT','RTGS','IMPS','Demand Draft','Other'];

$errors = [];
if ($tp_id <= 0)                                              $errors[] = 'Please select a territory partner.';
if ($amount <= 0)                                             $errors[] = 'Invalid amount. Must be greater than 0.';
if ($amount > 99999999.99)                                    $errors[] = 'Amount exceeds maximum limit.';
if (empty($payment_date))                                     $errors[] = 'Payment date is required.';
if (!in_array($payment_mode, $allowed_modes, true))           $errors[] = 'Invalid payment mode.';

if (!empty($payment_date)) {
    $d = DateTime::createFromFormat('Y-m-d', $payment_date);
    if (!$d || $d->format('Y-m-d') !== $payment_date)        $errors[] = 'Invalid payment date format.';
    elseif ($d > new DateTime())                              $errors[] = 'Payment date cannot be in the future.';
}

if (!empty($errors)) {
    header("Location: add-tp-advance-payment?error=" . urlencode(implode(' ', $errors))); exit;
}

$db_conn->begin_transaction();
try {
    // Verify TP belongs to this SS
    $chk = $db_conn->prepare("SELECT id FROM territory_partners WHERE id=? AND is_active=1 AND onboard_ss_id=? LIMIT 1");
    $chk->bind_param("is", $tp_id, $ss_id); $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) throw new \Exception("Territory partner not found under your account.");
    $chk->close();

    // This SS's own numeric id — approver_ss_id is always stored as this,
    // never the varchar temp_id. A payment recorded directly by an SS is
    // always their own pool (unlike invoices/POs, tp_advance_payments has
    // no product/line-item link, so no GST override applies here).
    $ss_account_id = (int)($result_LoGuserDtails['id'] ?? 0);
    $approver_type = 'ss';

    $balance  = $amount;
    $adjusted = 0.00;
    $status   = 'active';

    $s = $db_conn->prepare("INSERT INTO tp_advance_payments
        (territory_partner_id, product_type, approver_type, approver_ss_id, amount, payment_date, payment_mode, reference_number, bank_name, remarks,
         adjusted_amount, balance_amount, status, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param("issidssssssdss",
        $tp_id, $productType, $approver_type, $ss_account_id, $amount, $payment_date, $payment_mode, $reference_num, $bank_name, $remarks,
        $adjusted, $balance, $status, $created_by);
    if (!$s->execute()) throw new \Exception("Insert failed: " . $s->error);
    $s->close();

    $db_conn->commit();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header("Location: add-tp-advance-payment?success=1"); exit;

} catch (\Throwable $e) {
    $db_conn->rollback();
    header("Location: add-tp-advance-payment?error=" . urlencode("Failed to record payment. " . $e->getMessage())); exit;
}
