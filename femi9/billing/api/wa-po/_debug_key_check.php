<?php
/**
 * TEMPORARY diagnostic — delete after use. Prints only the last 6 hex
 * chars of the currently-loaded WA_PO_API_KEY/WA_PO_WEBHOOK_SECRET (never
 * the full value) plus the source file's mtime, to determine whether the
 * server is serving a stale cached copy after deploy vs. genuinely holding
 * an old value.
 */
require_once __DIR__ . '/../../config/wa_po_secrets.php';

header('Content-Type: application/json');

$path = __DIR__ . '/../../config/wa_po_secrets.php';

echo json_encode([
    'api_key_last6' => substr(WA_PO_API_KEY, -6),
    'webhook_secret_last6' => substr(WA_PO_WEBHOOK_SECRET, -6),
    'file_mtime' => date('Y-m-d H:i:s', filemtime($path)),
    'server_time' => date('Y-m-d H:i:s'),
]);
