<?php
/**
 * Super-Stockist-side helpers for standalone TP advance-payment submissions
 * routed to this SS. Mirrors company/include/AdvancePaymentScreenshotHelper.php
 * exactly for approve/reject/cancel (those don't touch company_id/godown at
 * all), but convert() here is SS-specific: no company profile picker, credits
 * approver_type='ss' + approver_ss_id=<this SS> instead.
 */

require_once __DIR__ . '/../../company/include/AdvancePaymentScreenshotHelper.php';

/**
 * Convert — SS equivalent of convertAdvancePaymentSubmissionToAdvancePayment().
 * No company_id/godown concept for SS; always credits this SS's own
 * approver-scoped balance pool.
 * @return array{success:bool,message:string,advance_payment_id?:int}
 */
function convertAdvancePaymentSubmissionToSsAdvancePayment(
    $db_conn,
    int $submissionId,
    float $amount,
    string $paymentDate,
    string $paymentMode,
    string $referenceNumber,
    string $note,
    int $ssAccountId,
    string $createdBy,
    ?string $productTypeOverride = null
): array {
    $referenceNumber = trim($referenceNumber);
    if ($amount <= 0 || $amount > 99999999.99) {
        return ['success' => false, 'message' => 'Invalid amount.'];
    }
    if ($referenceNumber === '') {
        return ['success' => false, 'message' => 'Reference number is required.'];
    }

    $allowedModes = ['Cash', 'Bank Transfer', 'Cheque', 'UPI', 'NEFT', 'RTGS', 'IMPS', 'Demand Draft', 'Other'];
    if (!in_array($paymentMode, $allowedModes, true)) {
        return ['success' => false, 'message' => 'Invalid payment mode.'];
    }

    $d = DateTime::createFromFormat('Y-m-d', $paymentDate);
    if (!$d || $d->format('Y-m-d') !== $paymentDate) {
        return ['success' => false, 'message' => 'Invalid payment date.'];
    }
    if ($d > new DateTime()) {
        return ['success' => false, 'message' => 'Payment date cannot be in the future.'];
    }

    $stmt = $db_conn->prepare(
        "SELECT id, territory_partner_id, status, advance_payment_id, product_type FROM tp_advance_payment_submissions WHERE id = ?"
    );
    $stmt->bind_param('i', $submissionId);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$submission) {
        return ['success' => false, 'message' => 'Submission not found.'];
    }
    if ($submission['status'] !== 'accepted') {
        return ['success' => false, 'message' => 'Only verified (approved) submissions can be added as a payment entry.'];
    }
    if ($submission['advance_payment_id'] !== null) {
        return ['success' => false, 'message' => 'This submission has already been added as a payment entry.'];
    }

    if (advancePaymentSubmissionReferenceIsDuplicate($db_conn, $referenceNumber, $submissionId)) {
        return ['success' => false, 'message' => 'This payment reference has already been used on another accepted submission.'];
    }

    $tpId = (int)$submission['territory_partner_id'];

    tpEnsureAdvanceWalletColumns($db_conn);
    $productType = $productTypeOverride !== null
        ? tpResolveProductType($productTypeOverride)
        : tpResolveProductType($submission['product_type'] ?? null);

    $db_conn->begin_transaction();
    try {
        $adjusted = 0.00;
        $balance = $amount;
        $status = 'active';
        $approverType = 'ss';

        $ins = $db_conn->prepare(
            "INSERT INTO tp_advance_payments
                (territory_partner_id, product_type, approver_type, approver_ss_id, amount, payment_date, payment_mode, reference_number, remarks,
                 adjusted_amount, balance_amount, status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $ins->bind_param(
            'issidssssddss',
            $tpId, $productType, $approverType, $ssAccountId, $amount, $paymentDate, $paymentMode, $referenceNumber, $note,
            $adjusted, $balance, $status, $createdBy
        );
        if (!$ins->execute()) throw new \Exception('Insert failed: ' . $ins->error);
        $advancePaymentId = $db_conn->insert_id;
        $ins->close();

        $link = $db_conn->prepare(
            "UPDATE tp_advance_payment_submissions SET advance_payment_id = ? WHERE id = ? AND advance_payment_id IS NULL"
        );
        $link->bind_param('ii', $advancePaymentId, $submissionId);
        $link->execute();
        if ($link->affected_rows !== 1) throw new \Exception('This submission has already been added as a payment entry.');
        $link->close();

        $db_conn->commit();
        return ['success' => true, 'advance_payment_id' => $advancePaymentId, 'message' => 'Added to TP advance payments.'];
    } catch (\Throwable $e) {
        $db_conn->rollback();
        return ['success' => false, 'message' => 'Failed to record payment. ' . $e->getMessage()];
    }
}
