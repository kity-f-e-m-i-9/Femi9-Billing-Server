<?php
/**
 * WhatsApp PO subsystem's own payment-screenshot helpers — image
 * compression + Claude Vision result classification + the few-shot
 * "prior corrections" lookup, mirroring
 * territory-partner/include/AdvancePaymentScreenshotHelper.php's shape
 * exactly, but scoped entirely to the wa_po_* tables. Deliberately a
 * separate copy (not a shared include) per the task's explicit instruction
 * to keep this subsystem's tables/corrections independent of the TP ones.
 */

// Identical resize/compress approach to compressAdvancePaymentScreenshot()
// in territory-partner/include/AdvancePaymentScreenshotHelper.php.
function waPoCompressPaymentScreenshot(string $tmpPath, string $destPath): bool {
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

// Same classification thresholds as classifyAdvancePaymentVisionResult()
// in upload-advance-payment-screenshot.php.
function waPoClassifyVisionResult(array $v): array {
    $raw = 'Claude vision: amount=' . ($v['amount'] ?? 'null') . ' reference=' . ($v['reference'] ?? 'null')
        . ' recipient_matches=' . ($v['recipient_matches'] ? 'true' : 'false') . ' confidence=' . $v['confidence']
        . ' looks_like_payment_screenshot=' . ($v['looks_like_payment_screenshot'] ? 'true' : 'false')
        . ($v['reasoning'] ? (' — ' . $v['reasoning']) : '');

    if (!$v['looks_like_payment_screenshot']) {
        return [
            'status' => 'rejected',
            'amount' => null,
            'reference' => null,
            'reason' => "This doesn't look like a payment screenshot — please share a screenshot of the actual payment confirmation (UPI success screen, bank transfer receipt, etc.).",
            'raw_text' => $raw,
        ];
    }

    if (!$v['recipient_matches']) {
        return [
            'status' => 'rejected',
            'amount' => $v['amount'],
            'reference' => $v['reference'],
            'reason' => "This payment does not appear to have been made to Femi9 — please share a screenshot showing the payment made to Femi9 / Femi Nayan LLP / Anand Praveen.",
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

// Global duplicate-reference guard across this subsystem's own submissions
// table — mirrors advancePaymentScreenshotReferenceIsDuplicateForUpload()
// but scoped to wa_po_advance_payment_submissions only (this subsystem has
// no separate PO-screenshot table the way the TP system does).
function waPoPaymentReferenceIsDuplicate($db_conn, string $referenceNumber): bool {
    $referenceNumber = trim($referenceNumber);
    if ($referenceNumber === '') return false;

    $stmt = $db_conn->prepare(
        "SELECT COUNT(*) AS cnt FROM wa_po_advance_payment_submissions
         WHERE status = 'accepted' AND UPPER(TRIM(reference_number)) = UPPER(TRIM(?))"
    );
    $stmt->bind_param('s', $referenceNumber);
    $stmt->execute();
    $cnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();
    return $cnt > 0;
}

// Few-shot prior-corrections lookup, identical shape to
// recentAdvancePaymentScreenshotCorrections() but reading from this
// subsystem's own wa_po_payment_screenshot_ocr_corrections table.
function waPoRecentPaymentScreenshotCorrections($db_conn, string $engine, int $limit = 6): array {
    $limit = max(1, min(20, $limit));
    $stmt = $db_conn->prepare(
        "SELECT field, wrong_value, correct_value FROM wa_po_payment_screenshot_ocr_corrections
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
