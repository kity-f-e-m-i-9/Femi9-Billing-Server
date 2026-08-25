<?php
/**
 * POST /payment/submit-proof
 * Spec §1.6 — submits a payment screenshot (Wati passes its own CDN URL,
 * which we fetch once rather than re-hosting Wati's copy) plus optional
 * UTR. Runs it straight through Claude Vision (same
 * ClaudeVisionService/pattern as territory-partner's
 * upload-advance-payment-screenshot.php) to auto-classify
 * accepted/pending_review/rejected, creates the submission +
 * screenshot rows in this subsystem's own wa_po_* tables.
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/include/WaPoPaymentScreenshotHelper.php';
require_once __DIR__ . '/../../shared/ClaudeVisionService.php';

$session = wa_po_require_session($db_conn, $input);
$category = $session['user_category'];
$userId = $session['user_id'];

$screenshotUrl = trim((string)($input['screenshot_url'] ?? ''));
$utrReference = trim((string)($input['utr_reference'] ?? ''));
$cartRef = trim((string)($input['cart_ref'] ?? ''));
$amount = $input['amount'] ?? null;
$paymentDate = trim((string)($input['payment_date'] ?? date('Y-m-d')));
$paymentMode = trim((string)($input['payment_mode'] ?? 'UPI'));

if ($screenshotUrl === '') wa_po_fail(400, 'screenshot_url is required');
if (!filter_var($screenshotUrl, FILTER_VALIDATE_URL)) wa_po_fail(400, 'screenshot_url is not a valid URL');

if (!wa_po_rate_limit_check($db_conn, 'payment-submit-proof:' . $category . ':' . $userId, 10, 3600)) {
    wa_po_fail(429, 'Too many submissions — please try again later');
}

// --- Fetch the screenshot from Wati's CDN (one-time; we store our own compressed copy) ---
$ch = curl_init($screenshotUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
]);
$imageData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $httpCode < 200 || $httpCode >= 300 || $imageData === false || $imageData === '') {
    wa_po_fail(502, 'Could not fetch the payment screenshot from the provided URL.');
}
if (strlen($imageData) > 10 * 1024 * 1024) {
    wa_po_fail(400, 'Image is too large. Maximum allowed size is 10 MB.');
}

$tmpPath = tempnam(sys_get_temp_dir(), 'wapo_ss_');
file_put_contents($tmpPath, $imageData);

$isImage = @getimagesize($tmpPath) !== false;
if (!$isImage) {
    @unlink($tmpPath);
    wa_po_fail(400, 'The fetched file is not a valid image (JPG/PNG/WEBP/GIF).');
}

// --- Create the submission row (no 'draft' state — WhatsApp flow submits amount+screenshot together) ---
$amountVal = is_numeric($amount) ? round((float)$amount, 2) : 0.0;

date_default_timezone_set('Asia/Kolkata');

$ins = mysqli_prepare($db_conn,
    "INSERT INTO wa_po_advance_payment_submissions
        (user_category, user_id, amount, payment_date, payment_mode, reference_number, note, status, created_at, submitted_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_review', NOW(), NOW())"
);
$note = $cartRef !== '' ? ('cart_ref: ' . $cartRef) : null;
mysqli_stmt_bind_param($ins, 'sidsssss', $category, $userId, $amountVal, $paymentDate, $paymentMode, $utrReference, $note);
mysqli_stmt_execute($ins);
$submissionId = mysqli_insert_id($db_conn);
mysqli_stmt_close($ins);

// --- Claude Vision classification (same pattern as TP's upload-advance-payment-screenshot.php) ---
$classification = null;
try {
    $vision = new ClaudeVisionService();
    $priorCorrections = waPoRecentPaymentScreenshotCorrections($db_conn, 'claude_vision', 6);
    $visionResult = $vision->analyzeScreenshot($tmpPath, $priorCorrections);
    if ($visionResult['success']) {
        $classification = waPoClassifyVisionResult($visionResult);
    }
} catch (\Throwable $e) {
    // Falls through to manual review below.
}

if ($classification === null) {
    $classification = [
        'status' => 'pending_review',
        'amount' => null,
        'reference' => $utrReference !== '' ? $utrReference : null,
        'reason' => 'Automatic verification is temporarily unavailable — this screenshot will be reviewed manually.',
        'raw_text' => '',
    ];
}

// If the user typed a UTR explicitly and vision didn't find one, prefer the typed value.
if ($classification['reference'] === null && $utrReference !== '') {
    $classification['reference'] = $utrReference;
}

if ($classification['status'] === 'accepted' && waPoPaymentReferenceIsDuplicate($db_conn, (string)$classification['reference'])) {
    $classification['status'] = 'rejected';
    $classification['reason'] = 'This payment reference has already been used on another submission.';
}

// --- Store our own compressed copy ---
$uploadDir = __DIR__ . '/payment_screenshots/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$screenshotName = 'wapo_' . $category . '_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';

if (!waPoCompressPaymentScreenshot($tmpPath, $uploadDir . $screenshotName)) {
    @unlink($tmpPath);
    wa_po_fail(400, 'File must be a valid image (JPG/PNG/WEBP/GIF).');
}
@unlink($tmpPath);

$reason = $classification['status'] === 'accepted' ? null : $classification['reason'];

$insSs = mysqli_prepare($db_conn,
    "INSERT INTO wa_po_advance_payment_screenshots
        (submission_id, file_path, detected_amount, reference_number, ocr_raw_text, status, rejection_reason)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param($insSs, 'isdssss',
    $submissionId, $screenshotName, $classification['amount'], $classification['reference'],
    $classification['raw_text'], $classification['status'], $reason
);
mysqli_stmt_execute($insSs);
mysqli_stmt_close($insSs);

// If vision's status disagrees with what the caller submitted, keep the
// submission row's own status in sync with the screenshot's outcome so
// payment-verify-status.php reflects the true state immediately (rather
// than waiting on a human review pass for a clear-cut accept/reject).
if ($classification['status'] !== 'pending_review') {
    $updSub = mysqli_prepare($db_conn, "UPDATE wa_po_advance_payment_submissions SET status = ?, rejection_reason = ?, reviewed_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($updSub, 'ssi', $classification['status'], $reason, $submissionId);
    mysqli_stmt_execute($updSub);
    mysqli_stmt_close($updSub);
}

// Same public status vocabulary as GET /payment/verify-status (§1.7) —
// pending|approved|rejected, not the submission row's internal
// pending_review/accepted/rejected — so an agent that only reads this
// immediate response (Vision can resolve accepted/rejected instantly,
// without waiting on the §2 outbound webhook or a poll) still gets a value
// it recognizes, rather than a silently-unmatched 'accepted'.
$statusMap = ['pending_review' => 'pending', 'accepted' => 'approved', 'rejected' => 'rejected'];
$publicStatus = $statusMap[$classification['status']] ?? 'pending';

wa_po_log_event('proof submitted (id ' . $submissionId . ', ' . $category . ' user_id ' . $userId . ', status ' . $publicStatus . ')');
echo json_encode([
    'proof_id' => 'PRF-' . $submissionId,
    'status' => $publicStatus,
    'verified_amount' => $publicStatus === 'approved' ? $amountVal : null,
    'remarks' => $publicStatus === 'rejected' ? $reason : null,
]);
