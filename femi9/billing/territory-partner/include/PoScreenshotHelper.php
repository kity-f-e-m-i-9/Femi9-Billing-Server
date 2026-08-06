<?php
/**
 * Shared helpers for TP purchase-order payment screenshots — compression for
 * storage, the global duplicate-reference-number check used both at
 * auto-accept time and at company-side manual review approval, and the
 * approve/reject actions themselves (shared by the standalone review-queue
 * page and the inline modal on tp-today-orders.php).
 */

// Re-encodes an uploaded payment screenshot as a heavily compressed JPEG.
// Rejects anything getimagesize() can't parse as JPEG/PNG/WEBP/GIF, which
// also blocks non-image files disguised with an image extension.
function compressPoScreenshot(string $tmpPath, string $destPath): bool {
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

    // Flatten onto white — screenshots don't need alpha, and JPEG has none.
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

    // Still too big even at floor quality — downscale further and retry once.
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

// A reference number can only ever be attached to one accepted screenshot,
// across every TP and every purchase order — case/whitespace-insensitive.
function poScreenshotReferenceIsDuplicate($db_conn, string $referenceNumber, ?int $excludeScreenshotId = null): bool {
    $referenceNumber = trim($referenceNumber);
    if ($referenceNumber === '') return false;

    if ($excludeScreenshotId !== null) {
        $stmt = $db_conn->prepare(
            "SELECT COUNT(*) AS cnt FROM tp_purchase_order_screenshots
             WHERE status = 'accepted' AND UPPER(TRIM(reference_number)) = UPPER(TRIM(?)) AND id != ?"
        );
        $stmt->bind_param('si', $referenceNumber, $excludeScreenshotId);
    } else {
        $stmt = $db_conn->prepare(
            "SELECT COUNT(*) AS cnt FROM tp_purchase_order_screenshots
             WHERE status = 'accepted' AND UPPER(TRIM(reference_number)) = UPPER(TRIM(?))"
        );
        $stmt->bind_param('s', $referenceNumber);
    }
    $stmt->execute();
    $cnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();

    return $cnt > 0;
}

/** @return array{success:bool,message:string} */
function rejectPoScreenshot($db_conn, int $screenshotId, string $reviewedBy): array {
    $stmt = $db_conn->prepare("SELECT id FROM tp_purchase_order_screenshots WHERE id = ? AND status = 'pending_review'");
    $stmt->bind_param('i', $screenshotId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'This screenshot has already been reviewed.'];
    }

    $upd = $db_conn->prepare(
        "UPDATE tp_purchase_order_screenshots SET status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE id=?"
    );
    $upd->bind_param('si', $reviewedBy, $screenshotId);
    $upd->execute();
    $upd->close();

    return ['success' => true, 'message' => 'Screenshot rejected.'];
}

/**
 * Approve — admin confirms/corrects the amount and reference number that
 * OCR couldn't read confidently, then this goes through the same global
 * duplicate check as an automatic accept.
 * @return array{success:bool,message:string}
 */
function approvePoScreenshot($db_conn, int $screenshotId, float $confirmedAmount, string $confirmedReference, string $reviewedBy): array {
    $stmt = $db_conn->prepare("SELECT id, detected_amount, reference_number, ocr_raw_text FROM tp_purchase_order_screenshots WHERE id = ? AND status = 'pending_review'");
    $stmt->bind_param('i', $screenshotId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'This screenshot has already been reviewed.'];
    }

    $confirmedReference = trim($confirmedReference);
    if ($confirmedAmount <= 0 || $confirmedReference === '') {
        return ['success' => false, 'message' => 'Enter both a confirmed amount and reference number before approving.'];
    }

    if (poScreenshotReferenceIsDuplicate($db_conn, $confirmedReference, $screenshotId)) {
        return ['success' => false, 'message' => 'This payment reference has already been used on another accepted screenshot.'];
    }

    $rawText = $row['ocr_raw_text'] ?? '';
    $engine = poScreenshotEngineFromRawText($rawText);
    recordPoScreenshotCorrection($db_conn, $screenshotId, 'amount', $engine, $row['detected_amount'], (string)$confirmedAmount, $rawText);
    recordPoScreenshotCorrection($db_conn, $screenshotId, 'reference', $engine, $row['reference_number'], $confirmedReference, $rawText);

    $upd = $db_conn->prepare(
        "UPDATE tp_purchase_order_screenshots
         SET status='accepted', detected_amount=?, reference_number=?, rejection_reason=NULL, reviewed_by=?, reviewed_at=NOW()
         WHERE id=?"
    );
    $upd->bind_param('dssi', $confirmedAmount, $confirmedReference, $reviewedBy, $screenshotId);
    $upd->execute();
    $upd->close();

    return ['success' => true, 'message' => 'Screenshot approved.'];
}

// Pulls recent reviewer corrections to few-shot the vision model with real
// past mistakes (e.g. "a masked account number was mistaken for the
// amount"), so it can recognize similar traps on a screenshot it has never
// seen before — not just an identical re-upload.
//
// Filtered to the same engine doing the reading: Claude (reasoning over the
// image directly) and the Google Vision + regex fallback (blind OCR text
// matching) fail in unrelated ways — a glyph-misread trap from OCR ("₹5
// misread as 35") isn't a mistake a vision-reasoning model is prone to
// repeating, so cross-engine examples would be noise rather than signal in
// the prompt.
function recentPoScreenshotCorrections($db_conn, string $engine, int $limit = 6): array {
    $limit = max(1, min(20, $limit));
    $stmt = $db_conn->prepare(
        "SELECT field, wrong_value, correct_value FROM tp_po_screenshot_ocr_corrections
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

// Looks up whether this exact OCR text has previously been corrected by a
// reviewer (e.g. the same screenshot re-uploaded after an earlier
// pending_review), and if so returns the confirmed amount/reference so the
// upload can skip manual review entirely.
function lookupKnownPoScreenshotCorrection($db_conn, string $rawText): array {
    $result = ['amount' => null, 'reference' => null];
    if ($rawText === '') return $result;

    $hash = hash('sha256', $rawText);
    $stmt = $db_conn->prepare(
        "SELECT field, correct_value FROM tp_po_screenshot_ocr_corrections
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
// classifyFromVisionResult() in upload-po-screenshot.php, always prefixed
// "Claude vision: " — anything else stored in ocr_raw_text is the Google
// Vision OCR fallback's verbatim extracted text.
function poScreenshotEngineFromRawText(string $rawText): string {
    return strpos($rawText, 'Claude vision:') === 0 ? 'claude_vision' : 'google_vision';
}

// Only worth recording when the auto-detected value actually differs from
// what the reviewer confirmed (or was missing entirely) — an exact match
// means auto-detection got it right and there's nothing to learn here.
function recordPoScreenshotCorrection($db_conn, int $screenshotId, string $field, string $engine, $autoValue, string $correctValue, string $rawText): void {
    $autoValueStr = $autoValue === null ? null : (string)$autoValue;
    if ($autoValueStr !== null && trim($autoValueStr) !== '' && strcasecmp(trim($autoValueStr), trim($correctValue)) === 0) {
        return;
    }
    if ($rawText === '') return;

    $hash = hash('sha256', $rawText);
    $stmt = $db_conn->prepare(
        "INSERT INTO tp_po_screenshot_ocr_corrections (screenshot_id, field, engine, wrong_value, correct_value, raw_text_hash, ocr_raw_text)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('issssss', $screenshotId, $field, $engine, $autoValueStr, $correctValue, $hash, $rawText);
    $stmt->execute();
    $stmt->close();
}
