<?php
// Re-encodes an uploaded KYC document image (PAN card etc.) as a compressed
// JPEG before storing it — quality only, original pixel dimensions are kept
// as-is (no resizing) so the document stays zoomable at full resolution.
// PDFs are left untouched by the caller — this only handles
// JPEG/PNG/WEBP/GIF.
function compressKycImage(string $tmpPath, string $destPath): bool {
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

    $w = imagesx($img);
    $h = imagesy($img);

    // Flatten onto white — a PAN card photo has no alpha, and JPEG has none.
    $canvas = imagecreatetruecolor($w, $h);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopy($canvas, $img, 0, 0, 0, 0, $w, $h);
    imagedestroy($img);

    $targetBytes = 400 * 1024;
    $quality = 88;
    $data = null;
    while ($quality >= 40) {
        ob_start();
        imagejpeg($canvas, null, $quality);
        $data = ob_get_clean();
        if (strlen($data) <= $targetBytes) break;
        $quality -= 8;
    }
    imagedestroy($canvas);

    if ($data === null) return false;
    return file_put_contents($destPath, $data) !== false;
}
