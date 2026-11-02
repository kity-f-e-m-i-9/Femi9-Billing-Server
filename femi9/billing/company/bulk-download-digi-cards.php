<?php
// Bulk ZIP of Digi Cards for a batch of TPs/CPs picked via checkboxes in the
// agreement-signers-list.php modal on manage-agreements.php — one PNG per
// partner, reusing the exact same render_tp_digi_card()/render_cp_digi_card()
// shared/DigiCardGenerator.php already uses for the single-partner download
// on view-tp-agreement.php / view-cp-agreement.php, so the image is
// byte-identical either way.
include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");
error_reporting(0);

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['admin', 'finance'], true)) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage-agreements.php');
    exit;
}

require_once __DIR__ . '/../shared/DigiCardGenerator.php';

$type = ($_POST['type'] ?? '') === 'tp' ? 'tp' : 'cp';
$ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
if (empty($ids)) {
    header('Location: manage-agreements.php');
    exit;
}
// Sane upper bound -- this generates a GD image per id in-process before
// anything is written out, so an unbounded list could exhaust memory/time.
$ids = array_slice($ids, 0, 500);

// Sanitizes a partner's name/code into a safe filename fragment -- names are
// free-typed admin data, not guaranteed to be filesystem/zip-safe.
function digi_card_zip_safe_name(string $s): string {
    $s = preg_replace('/[^A-Za-z0-9 _.\-]+/', '', $s);
    $s = trim(preg_replace('/\s+/', '_', $s));
    return $s !== '' ? $s : 'Partner';
}

if ($type === 'tp') {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db_conn->prepare("SELECT id, tp_id, name FROM territory_partners WHERE id IN ($placeholders) AND deleted_at IS NULL");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $partners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $codeField = 'tp_id';
} else {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db_conn->prepare("SELECT id, cp_id, name FROM channel_partners WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $partners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $codeField = 'cp_id';
}

$tmpZip = tempnam(sys_get_temp_dir(), 'digicards_');
$zip = new ZipArchive();
$zip->open($tmpZip, ZipArchive::OVERWRITE);

$added = 0;
$skipped = [];
foreach ($partners as $p) {
    $img = $type === 'tp' ? render_tp_digi_card($db_conn, (int) $p['id']) : render_cp_digi_card($db_conn, (int) $p['id']);
    if (!$img) {
        // Not signed yet -- no card exists for them (see
        // render_tp_digi_card()/render_cp_digi_card()'s own is_locked check).
        $skipped[] = $p['name'] . ' (' . $p[$codeField] . ')';
        continue;
    }
    ob_start();
    imagepng($img);
    $pngBytes = ob_get_clean();
    imagedestroy($img);

    $fileName = digi_card_zip_safe_name($p[$codeField] . '_' . $p['name']) . '.png';
    $zip->addFromString($fileName, $pngBytes);
    $added++;
}

if ($skipped) {
    $zip->addFromString('NOT_SIGNED_YET.txt', "These partners have not signed their agreement yet, so no Digi Card exists for them:\n\n" . implode("\n", $skipped) . "\n");
}
$zip->close();

if ($added === 0 && empty($skipped)) {
    @unlink($tmpZip);
    header('Location: manage-agreements.php');
    exit;
}

$zipName = ($type === 'tp' ? 'TP' : 'CP') . '_Digi_Cards_' . date('Y-m-d') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tmpZip));
readfile($tmpZip);
@unlink($tmpZip);
