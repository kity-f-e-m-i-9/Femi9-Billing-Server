<?php
include("checksession.php");
include("config.php");
error_reporting(0);

require_once __DIR__ . '/include/AdvancePaymentScreenshotHelper.php';
require_once __DIR__ . '/../shared/OcrService.php';
require_once __DIR__ . '/../shared/PaymentScreenshotParser.php';
require_once __DIR__ . '/../shared/ClaudeVisionService.php';
require_once __DIR__ . '/../shared/TpApproverContext.php';
require_once __DIR__ . '/../shared/TpProductType.php';

header('Content-Type: application/json');

function respond(array $payload, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode($payload);
    exit;
}

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(200);
        }
        echo json_encode([
            'success' => false,
            'message' => 'Upload could not be processed automatically. Please try again, or contact support if this keeps happening.',
        ]);
    }
});

date_default_timezone_set("Asia/Kolkata");

$tp_id = (int)$Login_user_IDvl;

// Submission details are collected once on the form and carried on every
// individual screenshot upload call — the first upload for a given set of
// details creates the draft submission; later uploads (matching form
// values unchanged) attach to that same draft.
$amount = round((float)($_POST['amount'] ?? 0), 2);
$paymentDate = trim($_POST['payment_date'] ?? '');
$paymentMode = trim($_POST['payment_mode'] ?? '');
$referenceNumber = trim($_POST['reference_number'] ?? '');
$note = trim($_POST['note'] ?? '');
$submissionId = (int)($_POST['submission_id'] ?? 0);
// 'po' when this upload happened on a visit redirected here from an
// over-balance purchase order (add-purchase-order.php); 'direct' otherwise.
// Used only to decide, on a later direct visit, whether a resumed draft's
// amount/reference are still meaningful — a PO's excess has nothing to do
// with an unrelated future visit, so those two fields must never resume
// from a po-sourced draft (see add-advance-payment.php's $hasResumableDraft
// / $isPoSourcedDraft split). Mode/note/screenshots still resume regardless.
$source = ($_POST['source'] ?? '') === 'po' ? 'po' : 'direct';

// Never trust the client-submitted approver type on its own — re-verify the
// TP actually has that SS assignment before honoring 'ss'. Only applied when
// this call is actually creating a new draft submission below; an existing
// draft keeps whatever approver it was created with (a draft is never
// re-routed mid-upload).
$approver = tpResolveApprover($db_conn, $tp_id, $_POST['approver_type'] ?? null);

// Same "never re-routed mid-upload" rule as $approver above — only used when
// this call is creating a brand-new draft; an existing draft keeps whatever
// type it was created with.
tpEnsureAdvanceWalletColumns($db_conn);
$productType = tpResolveProductType($_POST['product_type'] ?? null);

$allowedModes = ['Cash', 'Bank Transfer', 'Cheque', 'UPI', 'NEFT', 'RTGS', 'IMPS', 'Demand Draft', 'Other'];

// The TP can now upload a screenshot before typing amount/UTR — those get
// auto-filled from whatever OCR/vision detects on the image, then the TP
// reviews/edits them. So amount and reference are only required once this
// draft already has at least one screenshot attached — i.e. this genuinely
// isn't the first upload, so auto-fill already had its chance to run and
// this catches a later screenshot being sent with those fields cleared back
// out. Payment mode and date can't be detected from an image, so those
// stay required from the start.
//
// Deliberately checked by "has a screenshot yet", not merely "submission_id
// was passed" — a TP who abandoned an earlier draft before ever uploading
// anything (page reload, browser closed, etc.) has that draft's id resumed
// on their next visit, which would otherwise make every upload look like a
// "second" one and silently reintroduce the old must-fill-first requirement.
$existingScreenshotCountForGate = 0;
if ($submissionId > 0) {
    $gateStmt = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM tp_advance_payment_screenshots WHERE submission_id = ?");
    $gateStmt->bind_param('i', $submissionId);
    $gateStmt->execute();
    $existingScreenshotCountForGate = (int)($gateStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $gateStmt->close();
}

$errors = [];
if (!in_array($paymentMode, $allowedModes, true)) $errors[] = 'Select a valid payment mode.';
if ($existingScreenshotCountForGate > 0) {
    if ($amount <= 0 || $amount > 99999999.99) $errors[] = 'Enter a valid amount.';
    if ($referenceNumber === '') $errors[] = 'Reference number / UTR is required.';
} elseif ($amount > 99999999.99) {
    $errors[] = 'Enter a valid amount.';
}

$today = new DateTime('today');
$earliestAllowed = (clone $today)->modify('-2 days');
if ($paymentDate === '') {
    $errors[] = 'Payment date is required.';
} else {
    // createFromFormat('Y-m-d', ...) only fills the date fields — any
    // fields not in the format string (hour/minute/second) are inherited
    // from the current wall-clock time instead of defaulting to midnight.
    // So $d for today's date ends up as "today at right-now", which
    // compares greater than $today (today at 00:00:00) purely because of
    // the leftover time-of-day — rejecting today's own date as "in the
    // future". setTime(0,0,0) normalizes both sides to midnight so the
    // comparison is date-only, as intended.
    $d = DateTime::createFromFormat('Y-m-d', $paymentDate);
    if ($d) $d->setTime(0, 0, 0);
    if (!$d || $d->format('Y-m-d') !== $paymentDate) {
        $errors[] = 'Invalid payment date format.';
    } elseif ($d > $today || $d < $earliestAllowed) {
        $errors[] = 'Payment date must be within the last 2 days.';
    }
}

if (!empty($errors)) {
    respond(['success' => false, 'message' => implode(' ', $errors)], 400);
}

// Self-migrating: tracks whether a draft originated from a PO redirect vs a
// direct visit — see the $source comment above for why this matters.
$_sourceCol = $db_conn->query("SHOW COLUMNS FROM tp_advance_payment_submissions LIKE 'source'");
if ($_sourceCol && $_sourceCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_advance_payment_submissions ADD COLUMN source VARCHAR(10) NOT NULL DEFAULT 'direct' AFTER note");
}

// Resolve (or create) the draft submission this screenshot belongs to.
if ($submissionId > 0) {
    $stmt = $db_conn->prepare(
        "SELECT id FROM tp_advance_payment_submissions WHERE id = ? AND territory_partner_id = ? AND status = 'draft'"
    );
    $stmt->bind_param('ii', $submissionId, $tp_id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$found) {
        respond(['success' => false, 'message' => 'This submission is no longer editable. Please reload the page.'], 400);
    }
    // Keep the submission's details in sync with whatever the TP currently
    // has typed — they can freely edit amount/mode/etc. before hitting
    // "Submit Payment", same as editing a PO's line items pre-submit.
    $upd = $db_conn->prepare(
        "UPDATE tp_advance_payment_submissions
         SET amount=?, payment_date=?, payment_mode=?, reference_number=?, note=?
         WHERE id=? AND status='draft'"
    );
    $upd->bind_param('sssssi', $amount, $paymentDate, $paymentMode, $referenceNumber, $note, $submissionId);
    $upd->execute();
    $upd->close();
} else {
    $draftCountStmt = $db_conn->prepare(
        "SELECT COUNT(*) AS cnt FROM tp_advance_payment_submissions WHERE territory_partner_id = ? AND status = 'draft'"
    );
    $draftCountStmt->bind_param('i', $tp_id);
    $draftCountStmt->execute();
    $draftCount = (int)($draftCountStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $draftCountStmt->close();
    if ($draftCount >= 3) {
        respond(['success' => false, 'message' => 'You have too many unsubmitted drafts. Please submit or clear an existing one first.'], 400);
    }

    $ins = $db_conn->prepare(
        "INSERT INTO tp_advance_payment_submissions
            (territory_partner_id, product_type, approver_type, approver_ss_id, amount, payment_date, payment_mode, reference_number, note, source, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')"
    );
    $ins->bind_param('isisdsssss', $tp_id, $productType, $approver['type'], $approver['ss_id'], $amount, $paymentDate, $paymentMode, $referenceNumber, $note, $source);
    $ins->execute();
    $submissionId = $db_conn->insert_id;
    $ins->close();
}

$screenshotCountStmt = $db_conn->prepare(
    "SELECT COUNT(*) AS cnt FROM tp_advance_payment_screenshots WHERE submission_id = ?"
);
$screenshotCountStmt->bind_param('i', $submissionId);
$screenshotCountStmt->execute();
$existingCount = (int)($screenshotCountStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$screenshotCountStmt->close();

if ($existingCount >= 5) {
    respond(['success' => false, 'message' => 'You can upload a maximum of 5 screenshots per submission.'], 400);
}

$file = $_FILES['screenshot'] ?? null;
if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
    respond(['success' => false, 'message' => 'No file received.'], 400);
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    respond(['success' => false, 'message' => 'Upload failed. Please try again.'], 400);
}
if ($file['size'] > 10 * 1024 * 1024) {
    respond(['success' => false, 'message' => 'Image is too large. Maximum allowed size is 10 MB.'], 400);
}

$isImage = @getimagesize($file['tmp_name']) !== false;
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$isUnconvertedHeic = !$isImage && in_array($ext, ['heic', 'heif'], true);

if (!$isImage && !$isUnconvertedHeic) {
    respond(['success' => false, 'message' => 'File must be a valid image (JPG/PNG/WEBP/GIF/HEIC).'], 400);
}

function insertAdvancePaymentScreenshotRow(
    $db_conn, int $submissionId, string $screenshotName,
    ?float $detectedAmount, ?string $detectedReference, ?string $rawText, string $status, ?string $reason
): int {
    $stmt = $db_conn->prepare(
        "INSERT INTO tp_advance_payment_screenshots
            (submission_id, file_path, detected_amount, reference_number, ocr_raw_text, status, rejection_reason)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'isdssss',
        $submissionId, $screenshotName, $detectedAmount, $detectedReference, $rawText, $status, $reason
    );
    $stmt->execute();
    $id = $db_conn->insert_id;
    $stmt->close();
    return $id;
}

if ($isUnconvertedHeic) {
    $uploadDir = __DIR__ . '/advance_payment_screenshots/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $screenshotName = 'adv_' . $tp_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.heic';

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $screenshotName)) {
        respond(['success' => false, 'message' => 'Could not save the uploaded file. Please try again.'], 500);
    }

    $reason = 'HEIC image could not be automatically verified — needs manual review.';
    $screenshotId = insertAdvancePaymentScreenshotRow($db_conn, $submissionId, $screenshotName, null, null, null, 'pending_review', $reason);

    respond([
        'success' => true,
        'submission_id' => $submissionId,
        'screenshot' => [
            'id' => $screenshotId,
            'status' => 'pending_review',
            'reason' => $reason,
            'file_url' => 'advance_payment_screenshots/' . $screenshotName,
        ],
    ]);
}

$classification = null;
try {
    $vision = new ClaudeVisionService();
    $priorCorrections = recentAdvancePaymentScreenshotCorrections($db_conn, 'claude_vision', 6);
    $visionResult = $vision->analyzeScreenshot($file['tmp_name'], $priorCorrections);

    if ($visionResult['success']) {
        $classification = classifyAdvancePaymentVisionResult($visionResult);
    }
} catch (\Throwable $e) {
    // Falls through to the OCR fallback below.
}

if ($classification === null) {
    $ocrText = '';
    $ocrAvailable = true;
    try {
        $ocr = new OcrService();
        $ocrResult = $ocr->extractText($file['tmp_name']);
        $ocrText = $ocrResult['success'] ? $ocrResult['text'] : '';
        $ocrAvailable = $ocrResult['success'];
    } catch (\Throwable $e) {
        $ocrAvailable = false;
    }

    if ($ocrAvailable) {
        $classification = PaymentScreenshotParser::classify($ocrText);

        if ($classification['status'] !== 'accepted') {
            $known = lookupKnownAdvancePaymentScreenshotCorrection($db_conn, $ocrText);
            if ($known['amount'] !== null && $known['reference'] !== null) {
                $classification['amount'] = $known['amount'];
                $classification['reference'] = $known['reference'];
                $classification['status'] = 'accepted';
                $classification['reason'] = null;
            }
        }
    } else {
        $classification = [
            'status' => 'pending_review',
            'amount' => null,
            'reference' => null,
            'reason' => 'Automatic verification is temporarily unavailable — this screenshot will be reviewed manually.',
            'raw_text' => '',
        ];
    }
}

function classifyAdvancePaymentVisionResult(array $v): array {
    $raw = 'Claude vision: amount=' . ($v['amount'] ?? 'null') . ' reference=' . ($v['reference'] ?? 'null')
        . ' recipient_matches=' . ($v['recipient_matches'] ? 'true' : 'false') . ' confidence=' . $v['confidence']
        . ' looks_like_payment_screenshot=' . ($v['looks_like_payment_screenshot'] ? 'true' : 'false')
        . ($v['reasoning'] ? (' — ' . $v['reasoning']) : '');

    if (!$v['looks_like_payment_screenshot']) {
        return [
            'status' => 'rejected',
            'amount' => null,
            'reference' => null,
            'reason' => "This doesn't look like a payment screenshot — please upload a screenshot of the actual payment confirmation (UPI success screen, bank transfer receipt, etc.).",
            'raw_text' => $raw,
        ];
    }

    if (!$v['recipient_matches']) {
        return [
            'status' => 'rejected',
            'amount' => $v['amount'],
            'reference' => $v['reference'],
            'reason' => "This payment does not appear to have been made to Femi9 — please upload a screenshot showing the payment made to Femi9 / Femi Nayan LLP / Anand Praveen.",
            'raw_text' => $raw,
        ];
    }

    if ($v['confidence'] === 'high' && $v['amount'] !== null && $v['reference'] !== null) {
        return [
            'status' => 'accepted',
            'amount' => $v['amount'],
            'reference' => $v['reference'],
            'reason' => null,
            'raw_text' => $raw,
        ];
    }

    $reason = $v['amount'] === null && $v['reference'] === null
        ? 'Looks like a payment screenshot, but the amount and reference number could not be read clearly.'
        : ($v['amount'] === null
            ? 'Could not clearly read the paid amount from this screenshot.'
            : ($v['reference'] === null
                ? 'Could not clearly read a UTR/transaction reference number from this screenshot.'
                : 'The automatic reading of this screenshot needs manual verification.'));

    return [
        'status' => 'pending_review',
        'amount' => $v['amount'],
        'reference' => $v['reference'],
        'reason' => $reason,
        'raw_text' => $raw,
    ];
}

if ($classification['status'] === 'accepted' && advancePaymentScreenshotReferenceIsDuplicateForUpload($db_conn, $classification['reference'])) {
    $classification['status'] = 'rejected';
    $classification['reason'] = 'This payment reference has already been used on another order or submission.';
}

$uploadDir = __DIR__ . '/advance_payment_screenshots/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$screenshotName = 'adv_' . $tp_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';

if (!compressAdvancePaymentScreenshot($file['tmp_name'], $uploadDir . $screenshotName)) {
    respond(['success' => false, 'message' => 'File must be a valid image (JPG/PNG/WEBP/GIF).'], 400);
}

$reason = $classification['status'] === 'accepted' ? null : $classification['reason'];

$screenshotId = insertAdvancePaymentScreenshotRow(
    $db_conn, $submissionId, $screenshotName,
    $classification['amount'], $classification['reference'], $classification['raw_text'], $classification['status'], $reason
);

respond([
    'success' => true,
    'submission_id' => $submissionId,
    'screenshot' => [
        'id' => $screenshotId,
        'status' => $classification['status'],
        'detected_amount' => $classification['amount'],
        'reference_number' => $classification['reference'],
        'reason' => $reason,
        'file_url' => 'advance_payment_screenshots/' . $screenshotName,
    ],
]);
