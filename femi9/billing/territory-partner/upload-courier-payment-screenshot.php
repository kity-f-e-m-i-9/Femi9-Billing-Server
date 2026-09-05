<?php
include("checksession.php");
include("config.php");
error_reporting(0);

require_once __DIR__ . '/include/PoScreenshotHelper.php';
require_once __DIR__ . '/../shared/OcrService.php';
require_once __DIR__ . '/../shared/PaymentScreenshotParser.php';
require_once __DIR__ . '/../shared/ClaudeVisionService.php';
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/TpCourierPayment.php';

header('Content-Type: application/json');

// Phrases a FAILED/incomplete UPI transaction screen shows — checked before
// anything else, since a failed payment is never valid proof no matter what
// amount/UPI-ID text also happens to appear on the same error screen (e.g.
// "You've exceeded the bank limit for this payment" still shows the intended
// amount and payee, but the money never actually moved). Declared up here,
// not down near where it's used — a top-level `const` (unlike a `function`)
// is NOT hoisted in PHP, it's bound in file execution order; the OCR
// fallback path (courierClassifyFromOcr(), reached whenever Claude Vision is
// unavailable) called this before the const's original position further
// down had actually executed, throwing "Undefined constant" — a real fatal
// error on every single upload while Claude Vision was down. Confirmed
// 2026-09-04.
const TP_COURIER_FAILURE_PHRASES = [
    'not been debited', 'transaction failed', 'payment failed', 'payment unsuccessful',
    'could not be completed', "couldn't be completed", 'exceeded the bank limit',
    'please try again', 'retry with a smaller amount',
];

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

$tp_id = (int)$Login_user_IDvl;
$productType = tpResolveProductType($_POST['product_type'] ?? null);
$totalBoxes = (int)($_POST['total_boxes'] ?? 0);
$totalCovers = (int)($_POST['total_covers'] ?? 0);
$requiredAmount = round((float)($_POST['required_amount'] ?? 0), 2);
$po_id = (int)($_POST['po_id'] ?? 0);

tpEnsureCourierPaymentTables($db_conn);

// Re-derive the required amount server-side, never trusting the client-
// submitted total_boxes/required_amount (a courtesy echo of what the page
// already showed) — same never-trust-the-client posture as
// purchase-order-action.php. Two sources depending on which mode this
// upload is for (see pay-courier-payment.php's own po_id branch): an
// already-submitted PO's real saved line items, or the pre-submission
// cart's session draft.
if ($po_id > 0) {
    tpEnsureCourierOverrideColumn($db_conn);
    $poOwnStmt = $db_conn->prepare("SELECT product_type, status, courier_amount_override FROM tp_purchase_orders WHERE id = ? AND territory_partner_id = ?");
    $poOwnStmt->bind_param('ii', $po_id, $tp_id);
    $poOwnStmt->execute();
    $poOwnRow = $poOwnStmt->get_result()->fetch_assoc();
    $poOwnStmt->close();

    if (!$poOwnRow || $poOwnRow['status'] !== 'waiting') {
        respond(['success' => false, 'message' => 'This purchase order is not available for a courier payment retry.'], 400);
    }
    $productType = $poOwnRow['product_type'];

    tpEnsurePickupColumn($db_conn);
    $poItemsStmt = $db_conn->prepare("SELECT product_id, qty, delivery_method FROM tp_purchase_order_items WHERE po_id = ?");
    $poItemsStmt->bind_param('i', $po_id);
    $poItemsStmt->execute();
    $items = [];
    foreach ($poItemsStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $it) {
        if (($it['delivery_method'] ?? 'courier') === 'pickup') continue;
        $items[] = ['pid' => (int)$it['product_id'], 'qty' => (int)$it['qty']];
    }
    $poItemsStmt->close();

    $shipment = tpCourierComputeShipmentForItems($db_conn, $items);
    $totalBoxes = $shipment['boxes'];
    $totalCovers = $shipment['covers'];
    $requiredAmount = tpCourierComputeAmount($db_conn, $productType, $totalBoxes, $totalCovers);
    if ($poOwnRow['courier_amount_override'] !== null) {
        $requiredAmount = (float)$poOwnRow['courier_amount_override'];
    }
} else {
    $draft = $_SESSION['po_draft_' . $tp_id] ?? null;
    if ($draft && !empty($draft['lines'])) {
        $items = [];
        foreach ($draft['lines'] as $l) {
            if (($l['method'] ?? 'courier') === 'pickup') continue;
            $items[] = ['pid' => (int)$l['pr_id'], 'qty' => (int)$l['qty']];
        }
        $shipment = tpCourierComputeShipmentForItems($db_conn, $items);
        $totalBoxes = $shipment['boxes'];
        $totalCovers = $shipment['covers'];
        $requiredAmount = tpCourierComputeAmount($db_conn, $productType, $totalBoxes, $totalCovers);
    }
}

// Whatever UPI ID is currently configured on the settings page — the
// recipient check below verifies the screenshot's payee against THIS exact
// ID (never a hardcoded name), since it can change any time the company
// updates it.
$expectedUpi = tpCourierGetUpiDetails($db_conn)['upi_id'];

if ($po_id > 0) {
    $countStmt = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM tp_courier_payments WHERE po_id = ?");
    $countStmt->bind_param('i', $po_id);
} else {
    $countStmt = $db_conn->prepare(
        "SELECT COUNT(*) AS cnt FROM tp_courier_payments WHERE territory_partner_id = ? AND product_type = ? AND po_id IS NULL"
    );
    $countStmt->bind_param('is', $tp_id, $productType);
}
$countStmt->execute();
$existingCount = (int)($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$countStmt->close();

if ($existingCount >= 5) {
    respond(['success' => false, 'message' => 'You can upload a maximum of 5 screenshots.'], 400);
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

// Same exact image bytes reused by anyone — the same TP re-uploading, or a
// different TP submitting someone else's proof — can't fund a second
// courier payment. Hashed from the raw uploaded bytes (before compression),
// so a re-compressed copy of the identical screenshot still matches.
$imageHash = hash_file('sha256', $file['tmp_name']);
if ($imageHash && tpCourierImageIsDuplicate($db_conn, $imageHash)) {
    respond(['success' => false, 'message' => 'This exact screenshot has already been uploaded (it is either pending review or already accepted) — it cannot be reused for another payment.'], 400);
}

$uploadDir = __DIR__ . '/courier_payment_screenshots/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// A retry upload (po_id > 0, an already-submitted order whose earlier
// courier payment was rejected) links straight to that PO on insert, rather
// than joining the pre-submission po_id-IS-NULL pool — it's already known
// which order this screenshot is for.
$poIdForInsert = $po_id > 0 ? $po_id : null;

if ($isUnconvertedHeic) {
    $screenshotName = 'cpay_' . $tp_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.heic';
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $screenshotName)) {
        respond(['success' => false, 'message' => 'Could not save the uploaded file. Please try again.'], 500);
    }
    $reason = 'HEIC image could not be automatically verified — needs manual review.';
    $stmt = $db_conn->prepare(
        "INSERT INTO tp_courier_payments (territory_partner_id, product_type, total_boxes, total_covers, required_amount, file_path, image_hash, po_id, status, rejection_reason)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_review', ?)"
    );
    $stmt->bind_param('isiidssis', $tp_id, $productType, $totalBoxes, $totalCovers, $requiredAmount, $screenshotName, $imageHash, $poIdForInsert, $reason);
    $stmt->execute();
    $id = $db_conn->insert_id;
    $stmt->close();
    respond(['success' => true, 'screenshot' => ['id' => $id, 'status' => 'pending_review', 'reason' => $reason]]);
}

// Claude-vision-first, OCR-fallback — same two engines as the advance-
// payment/PO-excess flow, but the recipient check here verifies against
// whatever UPI ID is CURRENTLY configured on the settings page ($expectedUpi),
// not a hardcoded "Femi9" name list — the courier-collection account is
// usually a personal name (e.g. "Jayadeepa R"), so the generic name check
// used to wrongly reject genuinely correct payments. Confirmed 2026-09-04.
$classification = null;
try {
    $vision = new ClaudeVisionService();
    $visionResult = $vision->analyzeScreenshot($file['tmp_name'], [], $expectedUpi);
    if ($visionResult['success']) {
        $classification = classifyCourierVisionResult($visionResult, $requiredAmount, $expectedUpi);
    }
} catch (\Throwable $e) {
    // Falls through to OCR fallback.
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
        $classification = courierClassifyFromOcr(PaymentScreenshotParser::classify($ocrText), $requiredAmount, $expectedUpi);
    } else {
        $classification = [
            'status' => 'pending_review',
            'amount' => null,
            'reference' => null,
            'payment_date' => null,
            'reason' => 'Automatic verification is temporarily unavailable — this screenshot will be reviewed manually.',
            'raw_text' => '',
        ];
    }
}

// PaymentScreenshotParser::classify() is shared with the advance-payment/PO
// flow and always applies its own hardcoded "Femi9 name" recipient gate —
// courier payment re-judges on amount AND on whether $expectedUpi's exact
// UPI ID text appears anywhere in the raw OCR text instead (OCR has no
// structured recipient field to compare directly, unlike Claude vision). A
// genuinely signal-free image (no amount, no UTR, no payment keyword
// anywhere) is still rejected outright; a readable payment whose UPI ID text
// can't be confirmed goes to pending_review for a human, never an outright
// reject — the ID might just be rendered in a way plain OCR can't isolate.
// OCR has no structured date field (unlike Claude vision), so payment_date
// is always null here — the same-day check only actually enforces on the
// Claude vision path, which is the primary engine; this fallback only runs
// when Claude's API is unavailable.
function courierTextLooksLikeFailure(string $text): bool {
    $lower = strtolower($text);
    foreach (TP_COURIER_FAILURE_PHRASES as $phrase) {
        if (strpos($lower, $phrase) !== false) return true;
    }
    return false;
}

function courierClassifyFromOcr(array $ocrResult, float $requiredAmount, ?string $expectedUpi): array {
    if ($ocrResult['status'] === 'rejected'
        && strpos($ocrResult['reason'] ?? '', "doesn't look like a payment screenshot") !== false) {
        return $ocrResult + ['payment_date' => null];
    }
    if (courierTextLooksLikeFailure($ocrResult['raw_text'] ?? '')) {
        return ['status' => 'rejected', 'amount' => $ocrResult['amount'], 'reference' => $ocrResult['reference'], 'payment_date' => null,
            'reason' => 'This screenshot shows a failed or incomplete payment (the money was not actually debited) — please upload a screenshot of a successful, completed payment.',
            'raw_text' => $ocrResult['raw_text']];
    }
    if ($ocrResult['amount'] === null) {
        return ['status' => 'pending_review', 'amount' => null, 'reference' => $ocrResult['reference'], 'payment_date' => null,
            'reason' => 'Could not clearly read the paid amount from this screenshot.', 'raw_text' => $ocrResult['raw_text']];
    }
    if (abs((float)$ocrResult['amount'] - $requiredAmount) > 1.0) {
        return ['status' => 'rejected', 'amount' => $ocrResult['amount'], 'reference' => $ocrResult['reference'], 'payment_date' => null,
            'reason' => 'The amount in this screenshot (₹' . number_format((float)$ocrResult['amount'], 2) . ') does not match the required courier amount (₹' . number_format($requiredAmount, 2) . ').',
            'raw_text' => $ocrResult['raw_text']];
    }
    if ($expectedUpi !== null && stripos($ocrResult['raw_text'], $expectedUpi) === false) {
        return ['status' => 'pending_review', 'amount' => $ocrResult['amount'], 'reference' => $ocrResult['reference'], 'payment_date' => null,
            'reason' => 'Could not confirm the payment was made to ' . $expectedUpi . ' — needs manual review.',
            'raw_text' => $ocrResult['raw_text']];
    }
    return ['status' => 'accepted', 'amount' => $requiredAmount, 'reference' => $ocrResult['reference'], 'payment_date' => null, 'reason' => null, 'raw_text' => $ocrResult['raw_text']];
}

// Requires BOTH the amount to match AND (when a UPI ID is configured) the
// recipient to match that exact ID — a mismatch on either downgrades to
// pending_review rather than an outright reject, since a wrong auto-reject
// blocks a TP from submitting their order over a misread, while a wrong
// auto-accept would let an unpaid/misdirected order through; pending_review
// is the safe middle ground either way, reviewed on the company's Courier
// Payment column (tp-today-orders.php), same Approve/Reject pattern as the
// advance-payment submission queue.
function classifyCourierVisionResult(array $v, float $requiredAmount, ?string $expectedUpi): array {
    $raw = 'Claude vision: amount=' . ($v['amount'] ?? 'null') . ' reference=' . ($v['reference'] ?? 'null')
        . ' payment_date=' . ($v['payment_date'] ?? 'null') . ' confidence=' . $v['confidence']
        . ' looks_like_payment_screenshot=' . ($v['looks_like_payment_screenshot'] ? 'true' : 'false')
        . ' payment_succeeded=' . (($v['payment_succeeded'] ?? true) ? 'true' : 'false')
        . ($v['reasoning'] ? (' — ' . $v['reasoning']) : '');
    $paymentDate = $v['payment_date'] ?? null;

    if (!$v['looks_like_payment_screenshot']) {
        return ['status' => 'rejected', 'amount' => null, 'reference' => null, 'payment_date' => null,
            'reason' => "This doesn't look like a payment screenshot — please upload a screenshot of the actual payment confirmation (UPI success screen, bank transfer receipt, etc.).",
            'raw_text' => $raw];
    }
    // A FAILED/incomplete transaction screen is never valid proof, no matter
    // what amount/recipient text also appears on it (e.g. a bank-limit error
    // still shows the intended amount and payee — the money never moved).
    // Checked before amount/recipient so a failure screen can't slip through
    // just because its numbers happen to look right.
    if (!($v['payment_succeeded'] ?? true)) {
        return ['status' => 'rejected', 'amount' => $v['amount'], 'reference' => $v['reference'], 'payment_date' => $paymentDate,
            'reason' => 'This screenshot shows a failed or incomplete payment (the money was not actually debited) — please upload a screenshot of a successful, completed payment.',
            'raw_text' => $raw];
    }
    if ($v['amount'] === null) {
        return ['status' => 'pending_review', 'amount' => null, 'reference' => $v['reference'], 'payment_date' => $paymentDate,
            'reason' => 'Could not clearly read the paid amount from this screenshot.', 'raw_text' => $raw];
    }
    // A rupee of rounding slack for OCR/vision reads a paisa off — anything
    // beyond that is a genuine mismatch, not noise.
    if (abs((float)$v['amount'] - $requiredAmount) > 1.0) {
        return ['status' => 'rejected', 'amount' => $v['amount'], 'reference' => $v['reference'], 'payment_date' => $paymentDate,
            'reason' => 'The amount in this screenshot (₹' . number_format((float)$v['amount'], 2) . ') does not match the required courier amount (₹' . number_format($requiredAmount, 2) . ').',
            'raw_text' => $raw];
    }
    // The payment must have been made TODAY (the same day this order is
    // being placed) — an old screenshot reused from a previous day's payment
    // is not proof this order's fee was paid. A date Claude couldn't read at
    // all is NOT treated as a mismatch (many UPI screens show no absolute
    // date at all) — that just falls through to the checks below.
    $today = date('Y-m-d');
    if ($paymentDate !== null && $paymentDate !== $today) {
        return ['status' => 'rejected', 'amount' => $v['amount'], 'reference' => $v['reference'], 'payment_date' => $paymentDate,
            'reason' => 'This screenshot shows a payment made on ' . $paymentDate . ', not today (' . $today . ') — please upload today\'s payment screenshot.',
            'raw_text' => $raw];
    }
    if ($expectedUpi !== null && !$v['recipient_matches']) {
        return ['status' => 'pending_review', 'amount' => $v['amount'], 'reference' => $v['reference'], 'payment_date' => $paymentDate,
            'reason' => 'Could not confirm the payment was made to ' . $expectedUpi . ' — needs manual review.',
            'raw_text' => $raw];
    }
    return ['status' => 'accepted', 'amount' => $requiredAmount, 'reference' => $v['reference'], 'payment_date' => $paymentDate, 'reason' => null, 'raw_text' => $raw];
}

// A reference already used to fund an accepted advance-payment or PO-excess
// screenshot can't also fund a courier payment — same cross-purpose dedup
// posture as advancePaymentSubmissionReferenceIsDuplicate().
if ($classification['status'] === 'accepted' && $classification['reference']
    && (poScreenshotReferenceIsDuplicate($db_conn, $classification['reference'])
        || courierPaymentReferenceIsDuplicate($db_conn, $classification['reference']))) {
    $classification['status'] = 'rejected';
    $classification['reason'] = 'This payment reference has already been used on another payment.';
}

function courierPaymentReferenceIsDuplicate($db_conn, string $referenceNumber): bool {
    $referenceNumber = trim($referenceNumber);
    if ($referenceNumber === '') return false;
    $stmt = $db_conn->prepare(
        "SELECT COUNT(*) AS cnt FROM tp_courier_payments WHERE status = 'accepted' AND UPPER(TRIM(reference_number)) = UPPER(TRIM(?))"
    );
    $stmt->bind_param('s', $referenceNumber);
    $stmt->execute();
    $cnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();
    return $cnt > 0;
}

$screenshotName = 'cpay_' . $tp_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
if (!compressPoScreenshot($file['tmp_name'], $uploadDir . $screenshotName)) {
    respond(['success' => false, 'message' => 'File must be a valid image (JPG/PNG/WEBP/GIF).'], 400);
}

$reason = $classification['status'] === 'accepted' ? null : $classification['reason'];

$stmt = $db_conn->prepare(
    "INSERT INTO tp_courier_payments
        (territory_partner_id, product_type, total_boxes, total_covers, required_amount, detected_amount, reference_number, ocr_raw_text, image_hash, payment_date, file_path, po_id, status, rejection_reason)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$detectedAmount = $classification['amount'];
$paymentDateToStore = $classification['payment_date'] ?? null;
$stmt->bind_param(
    'isiiddsssssiss',
    $tp_id, $productType, $totalBoxes, $totalCovers, $requiredAmount,
    $detectedAmount, $classification['reference'], $classification['raw_text'],
    $imageHash, $paymentDateToStore, $screenshotName, $poIdForInsert, $classification['status'], $reason
);
$stmt->execute();
$id = $db_conn->insert_id;
$stmt->close();

respond([
    'success' => true,
    'screenshot' => [
        'id' => $id,
        'status' => $classification['status'],
        'detected_amount' => $classification['amount'],
        'reason' => $reason,
    ],
]);
