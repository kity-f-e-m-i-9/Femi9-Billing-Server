<?php
// Bulk ZIP of signed Agreement documents (PDF) for a batch of TPs/CPs picked
// via checkboxes in the agreement-signers-list.php modal on
// manage-agreements.php.
//
// Deliberately does NOT reimplement the PDF — view-tp-agreement.php /
// view-cp-agreement.php's own ?print=1 branch (dompdf + letterhead +
// Schedule-1 particulars) is 150+ lines of HTML/CSS that's easy to let drift
// out of sync with a second copy. Instead this fetches each partner's PDF
// from that same script via an internal HTTP request carrying this admin's
// own session cookie, so the output is byte-identical to clicking "Print /
// Save as PDF" on that partner's page one at a time, and any future change
// to that template only has to happen in one place.
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

$type = ($_POST['type'] ?? '') === 'tp' ? 'tp' : 'cp';
$ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
if (empty($ids)) {
    header('Location: manage-agreements.php');
    exit;
}
// Each id costs one full dompdf render -- keep a batch from running past a
// reasonable request time.
$ids = array_slice($ids, 0, 100);
set_time_limit(max(300, 6 * count($ids)));

function agreement_zip_safe_name(string $s): string {
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
    $scriptName = 'view-tp-agreement.php';
    $idParam = 'tp_id';
} else {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db_conn->prepare("SELECT id, cp_id, name FROM channel_partners WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $partners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $codeField = 'cp_id';
    $scriptName = 'view-cp-agreement.php';
    $idParam = 'cp_id';
}

// This admin's own current session, re-sent on each internal request so
// view-*-agreement.php's own checksession.php/PermissionCheck gate sees the
// same logged-in admin -- never a second, separate login.
$cookieName = session_name();
$cookieHeader = $cookieName . '=' . session_id();
$baseUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);

// PHP's default session handler holds an exclusive file lock on the session
// for as long as this script keeps it open. Without releasing it here, each
// internal curl call below -- hitting view-*-agreement.php, which opens that
// SAME session file via its own checksession.php -- blocks waiting for a
// lock this very script is still holding, serializing/deadlocking every
// request until it hits CURLOPT_TIMEOUT. session_write_close() lets go of
// the lock (the data already read above -- cookie name/id -- doesn't need
// the session kept open any further).
session_write_close();

$tmpZip = tempnam(sys_get_temp_dir(), 'agreements_');
$zip = new ZipArchive();
$zip->open($tmpZip, ZipArchive::OVERWRITE);

$added = 0;
$failed = [];
foreach ($partners as $p) {
    $url = $baseUrl . '/' . $scriptName . '?' . $idParam . '=' . (int) $p['id'] . '&print=1';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Cookie: ' . $cookieHeader],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false, // internal loopback call, same server
    ]);
    $pdfBytes = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($httpCode !== 200 || !$pdfBytes || strpos((string) $contentType, 'pdf') === false) {
        $failed[] = $p['name'] . ' (' . $p[$codeField] . ')';
        continue;
    }

    $fileName = agreement_zip_safe_name($p[$codeField] . '_' . $p['name']) . '_Agreement.pdf';
    $zip->addFromString($fileName, $pdfBytes);
    $added++;
}

if ($failed) {
    $zip->addFromString('FAILED_TO_GENERATE.txt', "Could not generate a document for these partners -- open their Agreement page directly to see why:\n\n" . implode("\n", $failed) . "\n");
}
$zip->close();

if ($added === 0 && empty($failed)) {
    @unlink($tmpZip);
    header('Location: manage-agreements.php');
    exit;
}

$zipName = ($type === 'tp' ? 'TP' : 'CP') . '_Agreements_' . date('Y-m-d') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tmpZip));
readfile($tmpZip);
@unlink($tmpZip);
