<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: tp-wallet-transfer.php?error=unauthorized"); exit;
}
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: tp-wallet-transfer.php"); exit;
}

tpEnsureAdvanceWalletColumns($db_conn);

$tpId = (int)($_POST['tp_id'] ?? 0);
$amount = round((float)($_POST['amount'] ?? 0), 2);
$createdBy = $_SESSION['LOGIN_USER'] ?? '';

$fromApproverType = ($_POST['from_approver_type'] ?? '') === 'ss' ? 'ss' : 'company';
$fromApproverSsId = ($_POST['from_approver_ss_id'] ?? '') !== '' ? (int)$_POST['from_approver_ss_id'] : null;
$fromProductType  = tpResolveProductType($_POST['from_product_type'] ?? null);

$toApproverType = ($_POST['to_approver_type'] ?? '') === 'ss' ? 'ss' : 'company';
$toApproverSsId = ($_POST['to_approver_ss_id'] ?? '') !== '' ? (int)$_POST['to_approver_ss_id'] : null;
$toProductType  = tpResolveProductType($_POST['to_product_type'] ?? null);

$redirectBack = 'tp-wallet-transfer.php?tp_id=' . $tpId;

if ($tpId < 1 || $amount <= 0) {
    header("Location: $redirectBack&error=invalid"); exit;
}
if ($fromApproverType === $toApproverType && $fromApproverSsId === $toApproverSsId && $fromProductType === $toProductType) {
    header("Location: $redirectBack&error=same_type"); exit;
}

$db_conn->begin_transaction();
try {
    // FIFO-deduct the transfer amount from the source pool's own balance —
    // same ordering convention as tpAdvanceDeduct(), reducing both amount
    // and balance_amount on each touched row so conservation
    // (amount = balance + adjusted) holds. adjusted_amount is never touched
    // — already-spent money stays exactly where its real invoice history
    // (tp_invoice_advance_log) says it is.
    $stmt = $db_conn->prepare(
        "SELECT id, amount, balance_amount FROM tp_advance_payments
          WHERE territory_partner_id = ? AND approver_type = ? AND approver_ss_id <=> ? AND product_type = ?
            AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL
          ORDER BY payment_date ASC, id ASC FOR UPDATE"
    );
    $stmt->bind_param('isis', $tpId, $fromApproverType, $fromApproverSsId, $fromProductType);
    $stmt->execute();
    $sourceRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $remaining = $amount;
    foreach ($sourceRows as $row) {
        if ($remaining <= 0.005) break;
        $take = min($remaining, (float)$row['balance_amount']);
        $newAmount = round((float)$row['amount'] - $take, 2);
        $newBalance = round((float)$row['balance_amount'] - $take, 2);
        $u = $db_conn->prepare("UPDATE tp_advance_payments SET amount = ?, balance_amount = ? WHERE id = ?");
        $u->bind_param('ddi', $newAmount, $newBalance, $row['id']);
        $u->execute();
        $u->close();
        $remaining = round($remaining - $take, 2);
    }

    if ($remaining > 0.005) {
        throw new \Exception('insufficient');
    }

    $reference = 'Wallet transfer #' . date('YmdHis');
    $remarks = "Transferred from " . tpProductTypeLabel($fromProductType) . " to " . tpProductTypeLabel($toProductType) . " by $createdBy";
    $ins = $db_conn->prepare(
        "INSERT INTO tp_advance_payments
            (territory_partner_id, product_type, product_type_reviewed, approver_type, approver_ss_id,
             amount, payment_date, payment_mode, reference_number, remarks,
             adjusted_amount, balance_amount, status, created_by)
         VALUES (?, ?, 1, ?, ?, ?, CURDATE(), 'Wallet Transfer', ?, ?, 0.00, ?, 'active', ?)"
    );
    $ins->bind_param(
        'issidssds',
        $tpId, $toProductType, $toApproverType, $toApproverSsId,
        $amount, $reference, $remarks, $amount, $createdBy
    );
    if (!$ins->execute()) throw new \Exception('insert_failed');
    $ins->close();

    $db_conn->commit();
    header("Location: $redirectBack&done=1");
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    $errCode = $e->getMessage() === 'insufficient' ? 'insufficient' : 'failed';
    header("Location: $redirectBack&error=$errCode");
    exit;
}
