<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: review-mixed-advance-payments?error=unauthorized"); exit;
}
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: review-mixed-advance-payments"); exit;
}

tpEnsureAdvanceWalletColumns($db_conn);

$advanceId = (int)($_POST['advance_id'] ?? 0);
$mode = ($_POST['mode'] ?? 'simple') === 'split' ? 'split' : 'simple';
$createdBy = $_SESSION['LOGIN_USER'] ?? '';

if ($advanceId < 1) {
    header("Location: review-mixed-advance-payments?error=invalid"); exit;
}

$row = $db_conn->prepare("SELECT * FROM tp_advance_payments WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$row->bind_param('i', $advanceId);
$row->execute();
$advance = $row->get_result()->fetch_assoc();
$row->close();

if (!$advance) {
    header("Location: review-mixed-advance-payments?error=notfound"); exit;
}

function tpAdvanceStatusFor(float $adjusted, float $balance): string {
    if ($balance <= 0.005) return 'fully_adjusted';
    if ($adjusted > 0.005) return 'partially_adjusted';
    return 'active';
}

if ($mode === 'simple') {
    // Whole-row override, ignoring real funding history — only relabels the
    // row's type tag, never touches amount/balance_amount/adjusted_amount.
    $productType = tpResolveProductType($_POST['product_type'] ?? null);
    $stmt = $db_conn->prepare(
        "UPDATE tp_advance_payments SET product_type = ?, product_type_reviewed = 1 WHERE id = ? AND deleted_at IS NULL"
    );
    $stmt->bind_param('si', $productType, $advanceId);
    $stmt->execute();
    $stmt->close();
    header("Location: review-mixed-advance-payments?saved=1");
    exit;
}

// ── Split mode — by actual usage ────────────────────────────────────────────
// The already-spent portion is never a judgment call: every
// tp_invoice_advance_log entry for this row already points at a real
// invoice, and every invoice already carries its own definitive
// product_type (Phase 2, backfilled). This only asks the reviewer to
// decide the still-UNSPENT balance_amount — everything already spent keeps
// its real type automatically.
$napkinBalanceSplit = round((float)($_POST['split_napkin_amount'] ?? 0), 2);
$diaperBalanceSplit = round((float)($_POST['split_diaper_amount'] ?? 0), 2);
$currentBalance = round((float)$advance['balance_amount'], 2);

if ($napkinBalanceSplit < 0 || $diaperBalanceSplit < 0
    || abs(($napkinBalanceSplit + $diaperBalanceSplit) - $currentBalance) > 0.01) {
    header("Location: review-mixed-advance-payments?error=split_mismatch"); exit;
}

$logStmt = $db_conn->prepare("
    SELECT l.id AS log_id, l.deducted_amount, ti.product_type
    FROM tp_invoice_advance_log l
    JOIN tp_invoices ti ON ti.id = l.tp_invoice_id
    WHERE l.tp_advance_id = ?
");
$logStmt->bind_param('i', $advanceId);
$logStmt->execute();
$logEntries = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$logStmt->close();

$adjustedNapkin = 0.0; $adjustedDiaper = 0.0; $diaperLogIds = [];
foreach ($logEntries as $le) {
    if (($le['product_type'] ?? 'napkin') === 'diaper') {
        $adjustedDiaper += (float)$le['deducted_amount'];
        $diaperLogIds[] = (int)$le['log_id'];
    } else {
        $adjustedNapkin += (float)$le['deducted_amount'];
    }
}
$adjustedNapkin = round($adjustedNapkin, 2);
$adjustedDiaper = round($adjustedDiaper, 2);

$db_conn->begin_transaction();
try {
    // A reviewer allocating everything to Diaper (0 adjusted + 0 balance on
    // the Napkin side) leaves nothing for the original row to keep — updating
    // it in place would strand a hollow ₹0/₹0 "Napkin" row that clutters
    // every advance-payment list forever. Soft-delete it instead; the Diaper
    // side below (guaranteed non-zero, since split_napkin + split_diaper =
    // the original balance > 0) carries the real money forward as the new row.
    $hasNapkinPortion = $adjustedNapkin > 0.005 || $napkinBalanceSplit > 0.005;
    if ($hasNapkinPortion) {
        $newNapkinAmount = round($adjustedNapkin + $napkinBalanceSplit, 2);
        $napkinStatus = tpAdvanceStatusFor($adjustedNapkin, $napkinBalanceSplit);
        $stmt = $db_conn->prepare(
            "UPDATE tp_advance_payments
                SET product_type = 'napkin', product_type_reviewed = 1,
                    amount = ?, adjusted_amount = ?, balance_amount = ?, status = ?
              WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->bind_param('dddsi', $newNapkinAmount, $adjustedNapkin, $napkinBalanceSplit, $napkinStatus, $advanceId);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $db_conn->prepare("UPDATE tp_advance_payments SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param('i', $advanceId);
        $stmt->execute();
        $stmt->close();
    }

    if ($adjustedDiaper > 0 || $diaperBalanceSplit > 0) {
        $newDiaperAmount = round($adjustedDiaper + $diaperBalanceSplit, 2);
        $diaperStatus = tpAdvanceStatusFor($adjustedDiaper, $diaperBalanceSplit);
        $splitRemarks = trim(($advance['remarks'] ?? '') . " (split from advance payment #{$advanceId})");

        $ins = $db_conn->prepare(
            "INSERT INTO tp_advance_payments
                (territory_partner_id, product_type, product_type_reviewed, approver_type, approver_ss_id, company_id,
                 amount, payment_date, payment_mode, reference_number, bank_name, remarks,
                 adjusted_amount, balance_amount, status, created_by)
             VALUES (?, 'diaper', 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->bind_param(
            'isiidsssssddss',
            $advance['territory_partner_id'], $advance['approver_type'], $advance['approver_ss_id'], $advance['company_id'],
            $newDiaperAmount, $advance['payment_date'], $advance['payment_mode'], $advance['reference_number'], $advance['bank_name'],
            $splitRemarks, $adjustedDiaper, $diaperBalanceSplit, $diaperStatus, $createdBy
        );
        if (!$ins->execute()) throw new \Exception('Split row insert failed: ' . $ins->error);
        $newDiaperId = $db_conn->insert_id;
        $ins->close();

        // Re-point every Diaper-invoice log entry at the new row — this is
        // what makes future restore/delete/credit-note math correct, since
        // those all walk tp_invoice_advance_log by tp_advance_id.
        if (!empty($diaperLogIds)) {
            $placeholders = implode(',', array_fill(0, count($diaperLogIds), '?'));
            $types = 'i' . str_repeat('i', count($diaperLogIds));
            $params = array_merge([$newDiaperId], $diaperLogIds);
            $repoint = $db_conn->prepare("UPDATE tp_invoice_advance_log SET tp_advance_id = ? WHERE id IN ($placeholders)");
            $repoint->bind_param($types, ...$params);
            $repoint->execute();
            $repoint->close();
        }
    }

    $db_conn->commit();
    header("Location: review-mixed-advance-payments?saved=1");
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    header("Location: review-mixed-advance-payments?error=split_failed");
    exit;
}
