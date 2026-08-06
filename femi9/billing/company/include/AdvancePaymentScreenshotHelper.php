<?php
/**
 * Company-side helpers for standalone TP advance-payment submissions.
 * A submission (tp_advance_payment_submissions) carries one set of payment
 * details (amount/date/mode/reference/note) and one or more independently
 * uploaded/verified screenshots (tp_advance_payment_screenshots, each with
 * its own auto-detection result) — approval/rejection/conversion act on the
 * submission as a whole, exactly once the TP has finished uploading and
 * explicitly submitted it (status='pending_review').
 *
 * Two-step pattern mirrors the PO-screenshot flow: approve() only confirms/
 * corrects the amount and reference and marks the submission verified; a
 * separate convert() step (mirroring
 * company/tp-screenshot-to-advance-payment.php) actually inserts the
 * tp_advance_payments row once a reviewer picks a company profile and
 * confirms the payment details a second time.
 *
 * Upload-time helpers (compression, initial duplicate check, correction
 * lookups for the vision prompt) live in
 * territory-partner/include/AdvancePaymentScreenshotHelper.php.
 */

// Claude's raw_text is a structured one-line summary written by
// classifyAdvancePaymentVisionResult() in upload-advance-payment-screenshot.php,
// always prefixed "Claude vision: " — anything else stored in ocr_raw_text
// is the Google Vision OCR fallback's verbatim extracted text. Duplicated
// from territory-partner/include/PoScreenshotHelper.php rather than shared,
// since that file lives under the TP-side include path and this one under
// company-side — same convention both directories already follow for their
// own screenshot helpers.
function advancePaymentScreenshotEngineFromRawText(string $rawText): string {
    return strpos($rawText, 'Claude vision:') === 0 ? 'claude_vision' : 'google_vision';
}

// A reference number can only ever fund one accepted submission, across
// every TP — case/whitespace-insensitive. Checked against both this table
// and tp_purchase_order_screenshots so the same UTR can't be used once as a
// PO-excess payment and again as a standalone advance submission.
function advancePaymentSubmissionReferenceIsDuplicate($db_conn, string $referenceNumber, ?int $excludeSubmissionId = null): bool {
    $referenceNumber = trim($referenceNumber);
    if ($referenceNumber === '') return false;

    if ($excludeSubmissionId !== null) {
        $stmt = $db_conn->prepare(
            "SELECT COUNT(*) AS cnt FROM tp_advance_payment_submissions
             WHERE status = 'accepted' AND UPPER(TRIM(reference_number)) = UPPER(TRIM(?)) AND id != ?"
        );
        $stmt->bind_param('si', $referenceNumber, $excludeSubmissionId);
    } else {
        $stmt = $db_conn->prepare(
            "SELECT COUNT(*) AS cnt FROM tp_advance_payment_submissions
             WHERE status = 'accepted' AND UPPER(TRIM(reference_number)) = UPPER(TRIM(?))"
        );
        $stmt->bind_param('s', $referenceNumber);
    }
    $stmt->execute();
    $cnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();
    if ($cnt > 0) return true;

    $stmt2 = $db_conn->prepare(
        "SELECT COUNT(*) AS cnt FROM tp_purchase_order_screenshots
         WHERE status = 'accepted' AND UPPER(TRIM(reference_number)) = UPPER(TRIM(?))"
    );
    $stmt2->bind_param('s', $referenceNumber);
    $stmt2->execute();
    $cnt2 = (int)($stmt2->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt2->close();

    return $cnt2 > 0;
}

/** @return array{success:bool,message:string} */
function rejectAdvancePaymentSubmission($db_conn, int $submissionId, string $reviewedBy, string $reason = ''): array {
    $stmt = $db_conn->prepare("SELECT id FROM tp_advance_payment_submissions WHERE id = ? AND status = 'pending_review'");
    $stmt->bind_param('i', $submissionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'This submission has already been reviewed.'];
    }

    $reason = $reason !== '' ? $reason : 'Rejected by reviewer.';
    $upd = $db_conn->prepare(
        "UPDATE tp_advance_payment_submissions SET status='rejected', rejection_reason=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?"
    );
    $upd->bind_param('ssi', $reason, $reviewedBy, $submissionId);
    $upd->execute();
    $upd->close();

    return ['success' => true, 'message' => 'Submission rejected.'];
}

/**
 * Approve — confirms/corrects the submission's amount and reference
 * against what its screenshots collectively support. Only marks the
 * submission verified; does NOT touch tp_advance_payments. A reviewer must
 * separately run convertAdvancePaymentSubmissionToAdvancePayment() to
 * actually credit the TP's advance balance.
 * @return array{success:bool,message:string}
 */
function approveAdvancePaymentSubmission(
    $db_conn,
    int $submissionId,
    float $confirmedAmount,
    string $confirmedReference,
    string $reviewedBy
): array {
    $confirmedReference = trim($confirmedReference);
    if ($confirmedAmount <= 0 || $confirmedReference === '') {
        return ['success' => false, 'message' => 'Enter both a confirmed amount and reference number before approving.'];
    }

    if (advancePaymentSubmissionReferenceIsDuplicate($db_conn, $confirmedReference, $submissionId)) {
        return ['success' => false, 'message' => 'This payment reference has already been used on another accepted submission.'];
    }

    $stmt = $db_conn->prepare(
        "SELECT id, amount, reference_number FROM tp_advance_payment_submissions WHERE id = ? AND status = 'pending_review'"
    );
    $stmt->bind_param('i', $submissionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'This submission has already been reviewed.'];
    }

    // Feed the correction loop from every screenshot that fed into this
    // submission's decision, not just one — a submission can have several
    // images, each with its own auto-detection result worth learning from.
    $shotsStmt = $db_conn->prepare(
        "SELECT id, detected_amount, reference_number, ocr_raw_text FROM tp_advance_payment_screenshots WHERE submission_id = ?"
    );
    $shotsStmt->bind_param('i', $submissionId);
    $shotsStmt->execute();
    $screenshots = $shotsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $shotsStmt->close();

    foreach ($screenshots as $s) {
        $rawText = $s['ocr_raw_text'] ?? '';
        if ($rawText === '') continue;
        $engine = advancePaymentScreenshotEngineFromRawText($rawText);
        recordAdvancePaymentScreenshotCorrection($db_conn, (int)$s['id'], 'amount', $engine, $s['detected_amount'], (string)$confirmedAmount, $rawText);
        recordAdvancePaymentScreenshotCorrection($db_conn, (int)$s['id'], 'reference', $engine, $s['reference_number'], $confirmedReference, $rawText);
    }

    $upd = $db_conn->prepare(
        "UPDATE tp_advance_payment_submissions
         SET status='accepted', amount=?, reference_number=?, rejection_reason=NULL, reviewed_by=?, reviewed_at=NOW()
         WHERE id=?"
    );
    $upd->bind_param('dssi', $confirmedAmount, $confirmedReference, $reviewedBy, $submissionId);
    $upd->execute();
    $upd->close();

    return ['success' => true, 'message' => 'Submission verified.'];
}

/**
 * Convert — the second, separate confirmation: only an already-accepted
 * submission not yet converted can become a real tp_advance_payments row.
 * Mirrors company/tp-screenshot-to-advance-payment.php's logic for
 * tp_purchase_order_screenshots exactly (same validation, same company_id/
 * godown-access gate), applied to this table instead.
 * @return array{success:bool,message:string,advance_payment_id?:int}
 */
function convertAdvancePaymentSubmissionToAdvancePayment(
    $db_conn,
    int $submissionId,
    float $amount,
    string $paymentDate,
    string $paymentMode,
    string $referenceNumber,
    string $note,
    int $companyId,
    string $createdBy
): array {
    $referenceNumber = trim($referenceNumber);
    if ($amount <= 0 || $amount > 99999999.99) {
        return ['success' => false, 'message' => 'Invalid amount.'];
    }
    if ($referenceNumber === '') {
        return ['success' => false, 'message' => 'Reference number is required.'];
    }
    if ($companyId <= 0) {
        return ['success' => false, 'message' => 'Please select a company profile.'];
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
        "SELECT id, territory_partner_id, status, advance_payment_id FROM tp_advance_payment_submissions WHERE id = ?"
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

    $db_conn->begin_transaction();
    try {
        $adjusted = 0.00;
        $balance = $amount;
        $status = 'active';

        $ins = $db_conn->prepare(
            "INSERT INTO tp_advance_payments
                (company_id, territory_partner_id, amount, payment_date, payment_mode, reference_number, remarks,
                 adjusted_amount, balance_amount, status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        $ins->bind_param(
            'iidssssddss',
            $companyId, $tpId, $amount, $paymentDate, $paymentMode, $referenceNumber, $note,
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

// Records what approval corrected a screenshot's auto-detection to. Read
// side (recentAdvancePaymentScreenshotCorrections /
// lookupKnownAdvancePaymentScreenshotCorrection) lives in
// territory-partner/include/AdvancePaymentScreenshotHelper.php, used by the
// upload endpoint.
function recordAdvancePaymentScreenshotCorrection($db_conn, int $screenshotId, string $field, string $engine, $autoValue, string $correctValue, string $rawText): void {
    $autoValueStr = $autoValue === null ? null : (string)$autoValue;
    if ($autoValueStr !== null && trim($autoValueStr) !== '' && strcasecmp(trim($autoValueStr), trim($correctValue)) === 0) {
        return;
    }
    if ($rawText === '') return;

    $hash = hash('sha256', $rawText);
    $stmt = $db_conn->prepare(
        "INSERT INTO tp_advance_payment_screenshot_ocr_corrections (screenshot_id, field, engine, wrong_value, correct_value, raw_text_hash, ocr_raw_text)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('issssss', $screenshotId, $field, $engine, $autoValueStr, $correctValue, $hash, $rawText);
    $stmt->execute();
    $stmt->close();
}
