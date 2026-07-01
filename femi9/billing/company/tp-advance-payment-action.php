<?php
ob_start();
include("checksession.php");
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: manage-tp-advance-payments?error=unauthorized"); exit;
}
if (empty($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: manage-tp-advance-payments"); exit;
}

if (!isset($_POST['add_tp_advance_payment'])) {
    header("Location: add-tp-advance-payment"); exit;
}

// Ensure company_id column exists
$_col = $db_conn->query("SHOW COLUMNS FROM tp_advance_payments LIKE 'company_id'");
if ($_col && $_col->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_advance_payments ADD COLUMN company_id INT UNSIGNED DEFAULT NULL AFTER territory_partner_id, ADD INDEX idx_tpap_company (company_id)");
}

$company_id     = (int)($_POST['company_id'] ?? 0);
$tp_id          = (int)($_POST['territory_partner_id'] ?? 0);
$amount         = round((float)($_POST['amount'] ?? 0), 2);
$payment_date   = trim($_POST['payment_date'] ?? '');
$payment_mode   = trim($_POST['payment_mode'] ?? '');
$reference_num  = trim($_POST['reference_number'] ?? '');
$bank_name      = trim($_POST['bank_name'] ?? '');
$remarks        = trim($_POST['remarks'] ?? '');
$created_by     = $_SESSION['LOGIN_USER'] ?? '';

$allowed_modes = ['Cash','Bank Transfer','Cheque','UPI','NEFT','RTGS','IMPS','Demand Draft','Other'];

$errors = [];
if ($company_id <= 0)                    $errors[] = 'Please select a company profile.';
if ($tp_id <= 0)                         $errors[] = 'Please select a territory partner.';
if ($amount <= 0)                        $errors[] = 'Invalid amount. Must be greater than 0.';
if ($amount > 99999999.99)               $errors[] = 'Amount exceeds maximum limit.';
if (empty($payment_date))                $errors[] = 'Payment date is required.';
if (!in_array($payment_mode, $allowed_modes, true)) $errors[] = 'Invalid payment mode.';

if (!empty($payment_date)) {
    $d = DateTime::createFromFormat('Y-m-d', $payment_date);
    if (!$d || $d->format('Y-m-d') !== $payment_date) {
        $errors[] = 'Invalid payment date format.';
    } elseif ($d > new DateTime()) {
        $errors[] = 'Payment date cannot be in the future.';
    }
}

if (!empty($errors)) {
    header("Location: add-tp-advance-payment?error=" . urlencode(implode(' ', $errors))); exit;
}

$db_conn->begin_transaction();
try {
    // Verify TP exists
    $chk = $db_conn->prepare("SELECT id, name, tp_id FROM territory_partners WHERE id=? AND is_active=1 LIMIT 1");
    $chk->bind_param("i", $tp_id); $chk->execute();
    $tp_row = $chk->get_result()->fetch_assoc(); $chk->close();
    if (!$tp_row) throw new \Exception("Territory partner not found or inactive.");

    // Verify company profile exists
    $chk_cp = $db_conn->prepare("SELECT id FROM company_godown WHERE id=? LIMIT 1");
    $chk_cp->bind_param("i", $company_id); $chk_cp->execute();
    if (!$chk_cp->get_result()->fetch_assoc()) throw new \Exception("Company profile not found.");
    $chk_cp->close();

    $balance = $amount; // starts equal to amount, no adjustment yet
    $adjusted = 0.00;
    $status = 'active';

    $s = $db_conn->prepare("INSERT INTO tp_advance_payments
        (company_id, territory_partner_id, amount, payment_date, payment_mode, reference_number, bank_name, remarks,
         adjusted_amount, balance_amount, status, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param("iidssssssdss",
        $company_id, $tp_id, $amount, $payment_date, $payment_mode, $reference_num, $bank_name, $remarks,
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
