<?php
include("checksession.php");
include("config.php");
error_reporting(0);

require_once __DIR__ . '/include/PoScreenshotHelper.php';
require_once __DIR__ . '/../shared/OcrService.php';
require_once __DIR__ . '/../shared/PaymentScreenshotParser.php';

header('Content-Type: application/json');

function respond(array $payload, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode($payload);
    exit;
}

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

// Run OCR on the original upload — before compression, for the best text fidelity.
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
} else {
    // OCR itself is unavailable (misconfigured key, network issue, quota) —
    // never penalize the TP for that; route to manual review instead.
    $classification = [
        'status' => 'pending_review',
        'amount' => null,
        'reference' => null,
        'reason' => 'Automatic verification is temporarily unavailable — this screenshot will be reviewed manually.',
        'raw_text' => '',
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
