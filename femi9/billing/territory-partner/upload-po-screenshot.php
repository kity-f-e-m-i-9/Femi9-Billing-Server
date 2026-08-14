<?php
include("checksession.php");
include("config.php");
error_reporting(0);

require_once __DIR__ . '/include/PoScreenshotHelper.php';
require_once __DIR__ . '/../shared/OcrService.php';
require_once __DIR__ . '/../shared/PaymentScreenshotParser.php';
require_once __DIR__ . '/../shared/ClaudeVisionService.php';

header('Content-Type: application/json');

function respond(array $payload, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode($payload);
    exit;
}

// Without this, a fatal error partway through (Vision API call exceeding
// max_execution_time, an uncaught exception, memory limit) leaves the
// response empty or as raw HTML — the client's `.json()` parse then throws,
// which the frontend reports as a generic "check your connection" failure
// even though the request reached the server fine. Turn that into a real
// JSON response routed to manual review instead of a misleading network error.
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

function acceptedTotalFor($db_conn, int $tp_id): float {
    $stmt = $db_conn->prepare(
        "SELECT COALESCE(SUM(detected_amount), 0) AS total
         FROM tp_purchase_order_screenshots
         WHERE territory_partner_id = ? AND po_id IS NULL AND status = 'accepted'"
    );
    $stmt->bind_param('i', $tp_id);
    $stmt->execute();
    $total = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}

$tp_id = (int)$Login_user_IDvl;

$countStmt = $db_conn->prepare(
    "SELECT COUNT(*) AS cnt FROM tp_purchase_order_screenshots WHERE territory_partner_id = ? AND po_id IS NULL"
);
$countStmt->bind_param('i', $tp_id);
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

// The browser normally converts HEIC to JPEG before it reaches here (see
// add-purchase-order.php's heic2any step) — this only runs if that
// conversion failed client-side. GD/Vision can't read raw HEIC at all, so
// there's no automatic verification possible; store it as-is for a human
// to open and check manually rather than dead-ending the TP.
if ($isUnconvertedHeic) {
    $uploadDir = __DIR__ . '/po_screenshots/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $screenshotName = 'po_' . $tp_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.heic';

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $screenshotName)) {
        respond(['success' => false, 'message' => 'Could not save the uploaded file. Please try again.'], 500);
    }

    $reason = 'HEIC image could not be automatically verified — needs manual review.';
    $status = 'pending_review';
    $stmt = $db_conn->prepare(
        "INSERT INTO tp_purchase_order_screenshots
            (territory_partner_id, file_path, status, rejection_reason)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('isss', $tp_id, $screenshotName, $status, $reason);
    $stmt->execute();
    $screenshotId = $db_conn->insert_id;
    $stmt->close();

    respond([
        'success' => true,
        'screenshot' => [
            'id' => $screenshotId,
            'status' => $status,
            'detected_amount' => null,
            'reference_number' => null,
            'reason' => $reason,
            'file_url' => 'po_screenshots/' . $screenshotName,
        ],
        'accepted_total' => acceptedTotalFor($db_conn, $tp_id),
    ]);
}

// Primary verification path: Claude reads the image directly and reasons
// about amount/reference/recipient in context, few-shot primed with recent
// reviewer corrections. This replaces blind OCR + regex, which mis-happened
// on masked account numbers, transaction IDs, and glued currency symbols.
$classification = null;
try {
    $vision = new ClaudeVisionService();
    $priorCorrections = recentPoScreenshotCorrections($db_conn, 'claude_vision', 6);
    $visionResult = $vision->analyzeScreenshot($file['tmp_name'], $priorCorrections);

    if ($visionResult['success']) {
        $classification = classifyFromVisionResult($visionResult);
    }
} catch (\Throwable $e) {
    // Falls through to the OCR fallback below.
}

// Fallback path: if Claude's API is unavailable (misconfigured key, network
// issue, quota) fall back to Google Vision OCR + regex rather than losing
// automatic verification entirely.
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

        // If this exact OCR text was previously misread and a reviewer
        // already corrected it (e.g. the TP re-uploads the same screenshot
        // after an earlier pending_review), reuse the known-correct values
        // instead of sending it back through manual review again for an
        // identical image.
        if ($classification['status'] !== 'accepted') {
            $known = lookupKnownPoScreenshotCorrection($db_conn, $ocrText);
            if ($known['amount'] !== null && $known['reference'] !== null) {
                $classification['amount'] = $known['amount'];
                $classification['reference'] = $known['reference'];
                $classification['status'] = 'accepted';
                $classification['reason'] = null;
            }
        }
    } else {
        // Neither verification path is available — never penalize the TP
        // for that; route to manual review instead.
        $classification = [
            'status' => 'pending_review',
            'amount' => null,
            'reference' => null,
            'reason' => 'Automatic verification is temporarily unavailable — this screenshot will be reviewed manually.',
            'raw_text' => '',
        ];
    }
}

// Turns a Claude vision read into the same {status,amount,reference,reason,
// raw_text} shape the rest of this endpoint (and PaymentScreenshotParser)
// already works with, applying the same conservative policy: only accept
// outright on a high-confidence read with both fields present and a
// matching recipient — anything else goes to pending_review for a human.
function classifyFromVisionResult(array $v): array {
    $raw = 'Claude vision: amount=' . ($v['amount'] ?? 'null') . ' reference=' . ($v['reference'] ?? 'null')
        . ' recipient_matches=' . ($v['recipient_matches'] ? 'true' : 'false') . ' confidence=' . $v['confidence']
        . ' looks_like_payment_screenshot=' . ($v['looks_like_payment_screenshot'] ? 'true' : 'false')
        . ($v['reasoning'] ? (' — ' . $v['reasoning']) : '');

    // Distinguished from "readable but ambiguous" (which is a legitimate
    // pending_review case) — an image that isn't a payment screenshot at
    // all (an unrelated app screen, an error message, an invoice form) has
    // nothing for a human reviewer to verify either, so it's rejected
    // outright with a message that tells the TP what actually went wrong
    // instead of the generic "could not be read clearly".
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

if ($classification['status'] === 'accepted' && poScreenshotReferenceIsDuplicate($db_conn, $classification['reference'])) {
    $classification['status'] = 'rejected';
    $classification['reason'] = 'This payment reference has already been used on another order.';
}

$uploadDir = __DIR__ . '/po_screenshots/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$screenshotName = 'po_' . $tp_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';

if (!compressPoScreenshot($file['tmp_name'], $uploadDir . $screenshotName)) {
    respond(['success' => false, 'message' => 'File must be a valid image (JPG/PNG/WEBP/GIF).'], 400);
}

$reason = $classification['status'] === 'accepted' ? null : $classification['reason'];

$stmt = $db_conn->prepare(
    "INSERT INTO tp_purchase_order_screenshots
        (territory_partner_id, file_path, detected_amount, reference_number, ocr_raw_text, status, rejection_reason)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param(
    'isdssss',
    $tp_id,
    $screenshotName,
    $classification['amount'],
    $classification['reference'],
    $classification['raw_text'],
    $classification['status'],
    $reason
);
$stmt->execute();
$screenshotId = $db_conn->insert_id;
$stmt->close();

respond([
    'success' => true,
    'screenshot' => [
        'id' => $screenshotId,
        'status' => $classification['status'],
        'detected_amount' => $classification['amount'],
        'reference_number' => $classification['reference'],
        'reason' => $reason,
        'file_url' => 'po_screenshots/' . $screenshotName,
    ],
    'accepted_total' => acceptedTotalFor($db_conn, $tp_id),
]);
