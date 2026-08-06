<?php
/**
 * TP-side helpers for standalone advance-payment screenshot submissions —
 * image compression for storage and the duplicate-reference-number check,
 * both needed at upload time. Approve/reject/corrections live in
 * company/include/AdvancePaymentScreenshotHelper.php since those are
 * company-side review actions.
 */

// Re-encodes an uploaded payment screenshot as a heavily compressed JPEG.
// Rejects anything getimagesize() can't parse as JPEG/PNG/WEBP/GIF, which
// also blocks non-image files disguised with an image extension. Identical
// to compressPoScreenshot() in PoScreenshotHelper.php.
function compressAdvancePaymentScreenshot(string $tmpPath, string $destPath): bool {
    $info = @getimagesize($tmpPath);
    if ($info === false) return false;

    switch ($info['mime']) {
        case 'image/jpeg': $img = @imagecreatefromjpeg($tmpPath); break;
        case 'image/png':  $img = @imagecreatefrompng($tmpPath);  break;
        case 'image/webp': $img = @imagecreatefromwebp($tmpPath); break;
        case 'image/gif':  $img = @imagecreatefromgif($tmpPath);  break;
        default: return false;
    }
    if (!$img) return false;

    $srcW = imagesx($img);
    $srcH = imagesy($img);
    $maxDim = 1600;
    $ratio = min(1, $maxDim / max($srcW, $srcH));
    $dstW = max(1, (int)round($srcW * $ratio));
    $dstH = max(1, (int)round($srcH * $ratio));

    $canvas = imagecreatetruecolor($dstW, $dstH);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopyresampled($canvas, $img, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    imagedestroy($img);

    $targetBytes = 150 * 1024;
    $quality = 82;
    $data = null;
    while ($quality >= 30) {
        ob_start();
        imagejpeg($canvas, null, $quality);
        $data = ob_get_clean();
        if (strlen($data) <= $targetBytes) break;
        $quality -= 8;
    }

    if ($data !== null && strlen($data) > $targetBytes && max($dstW, $dstH) > 800) {
        $ratio2 = 800 / max($dstW, $dstH);
        $dstW2 = max(1, (int)round($dstW * $ratio2));
        $dstH2 = max(1, (int)round($dstH * $ratio2));
        $canvas2 = imagecreatetruecolor($dstW2, $dstH2);
        imagecopyresampled($canvas2, $canvas, 0, 0, 0, 0, $dstW2, $dstH2, $dstW, $dstH);
        ob_start();
        imagejpeg($canvas2, null, 60);
        $data = ob_get_clean();
        imagedestroy($canvas2);
    }
    imagedestroy($canvas);

    if ($data === null) return false;
    return file_put_contents($destPath, $data) !== false;
}

// Global across TPs and across both screenshot systems — the same UTR
// can't fund both a PO-excess payment and a standalone advance submission.
// Checked at upload time against a screenshot's own detected reference
// (individual-image level — a duplicate here means "this exact payment was
// already used elsewhere", not a submission-level decision) plus already
// company-accepted submissions' confirmed reference.
function advancePaymentScreenshotReferenceIsDuplicateForUpload($db_conn, string $referenceNumber): bool {
    $referenceNumber = trim($referenceNumber);
    if ($referenceNumber === '') return false;

    $stmt = $db_conn->prepare(
        "SELECT COUNT(*) AS cnt FROM tp_advance_payment_submissions
         WHERE status = 'accepted' AND UPPER(TRIM(reference_number)) = UPPER(TRIM(?))"
    );
    $stmt->bind_param('s', $referenceNumber);
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

// Pulls recent reviewer corrections to few-shot the vision model, filtered
// to the engine currently reading the image (see the identical rationale
// in territory-partner/include/PoScreenshotHelper.php). Write side lives in
// company/include/AdvancePaymentScreenshotHelper.php's
// recordAdvancePaymentScreenshotCorrection(), used at approval time.
function recentAdvancePaymentScreenshotCorrections($db_conn, string $engine, int $limit = 6): array {
    $limit = max(1, min(20, $limit));
    $stmt = $db_conn->prepare(
        "SELECT field, wrong_value, correct_value FROM tp_advance_payment_screenshot_ocr_corrections
         WHERE engine = ? ORDER BY created_at DESC LIMIT $limit"
    );
    $stmt->bind_param('s', $engine);
    $stmt->execute();
    $res = $stmt->get_result();
    $corrections = [];
    while ($row = $res->fetch_assoc()) {
        $corrections[] = [
            'field' => $row['field'],
            'wrong' => $row['wrong_value'],
            'correct' => $row['correct_value'],
        ];
    }
    $stmt->close();
    return $corrections;
}

// Looks up whether this exact OCR/vision text was previously corrected
// (e.g. the same screenshot re-uploaded after an earlier pending_review),
// and if so returns the confirmed amount/reference so the upload can skip
// manual review entirely.
function lookupKnownAdvancePaymentScreenshotCorrection($db_conn, string $rawText): array {
    $result = ['amount' => null, 'reference' => null];
    if ($rawText === '') return $result;

    $hash = hash('sha256', $rawText);
    $stmt = $db_conn->prepare(
        "SELECT field, correct_value FROM tp_advance_payment_screenshot_ocr_corrections
         WHERE raw_text_hash = ? ORDER BY created_at DESC"
    );
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if ($row['field'] === 'amount' && $result['amount'] === null) {
            $result['amount'] = (float)$row['correct_value'];
        } elseif ($row['field'] === 'reference' && $result['reference'] === null) {
            $result['reference'] = $row['correct_value'];
        }
    }
    $stmt->close();

    return $result;
}

// Claude's raw_text is a structured one-line summary written by
// classifyFromVisionResult() in upload-advance-payment-screenshot.php,
// always prefixed "Claude vision: " — anything else stored in ocr_raw_text
// is the Google Vision OCR fallback's verbatim extracted text.
function advancePaymentScreenshotEngineFromRawText(string $rawText): string {
    return strpos($rawText, 'Claude vision:') === 0 ? 'claude_vision' : 'google_vision';
}
