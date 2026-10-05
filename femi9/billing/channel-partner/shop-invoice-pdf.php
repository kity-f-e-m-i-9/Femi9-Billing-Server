<?php
/**
 * No-login PDF endpoint for shop invoices raised from the channel-partner
 * login, opened by the shop from a WhatsApp share link (see the
 * "PDF / Share" button on shop-invoice-print.php). The recipient never has
 * a session cookie here, so this deliberately does NOT include
 * checksession.php — access is instead gated by a signed token (see
 * InvoiceShareLink.php) so only links this app generated itself work; a
 * guessed/incremented invoice id will fail signature verification.
 *
 * Reuses the exact same data loader / HTML renderer / 'shop' share-link
 * kind as territory-partner/shop-invoice-pdf.php — $Login_user_IDvl in the
 * channel-partner login also resolves to a territory_partners.id, so the
 * shared loader works unchanged and a link opened via either endpoint
 * verifies and renders identically.
 */
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/../shared/ShopInvoiceData.php';
require_once __DIR__ . '/../shared/ShopInvoiceHtml.php';
require_once __DIR__ . '/../shared/InvoiceShareLink.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$Invoice_ID = $_GET['id'] ?? '';
$sig        = $_GET['sig'] ?? '';

if ($Invoice_ID === '' || !invoice_share_verify('shop', $Invoice_ID, $sig)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Invalid or expired invoice link.';
    exit;
}

$decoded_id = base64_decode($Invoice_ID, true);
if ($decoded_id === false) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Invalid invoice link.';
    exit;
}

// The seller (TP) is looked up from the invoice itself, not a session —
// there's no logged-in user on this endpoint. user_invoice.from_user_id is
// the TP that raised the invoice.
$invRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT from_user_id FROM user_invoice WHERE inv_id='" . mysqli_real_escape_string($db_conn, $decoded_id) . "' AND from_user_type='territory_partner' LIMIT 1"));
if (!$invRow) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Invoice not found.';
    exit;
}
$tp_id = (int)$invRow['from_user_id'];

$invData = load_shop_invoice_data($db_conn, $decoded_id, $tp_id);
if (!$invData) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Invoice not found.';
    exit;
}

// Paper size / margin / scale, driven by the "PDF options" dialog on
// shop-invoice-print.php (same pattern as territory-partner's). Clamped
// server-side since these arrive as plain query params.
$__paperWidthsMm = ['a4' => 210, 'a5' => 148, 'letter' => 215.9];
$__paper = strtolower($_GET['paper'] ?? 'a4');
if (!isset($__paperWidthsMm[$__paper])) {
    $__paper = 'a4';
}
$__paperWidthMm = $__paperWidthsMm[$__paper];

$__marginMm = (float)($_GET['margin'] ?? 6);
if ($__marginMm < 0) { $__marginMm = 0; }
if ($__marginMm > 30) { $__marginMm = 30; }

$__scalePct = (int)($_GET['scale'] ?? 100);
if ($__scalePct < 50) { $__scalePct = 50; }
if ($__scalePct > 150) { $__scalePct = 150; }
$__scale = $__scalePct / 100;

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'
    . render_shop_invoice_html($invData, true, $__scale, $__marginMm)
    . '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);   // logo images are fetched by URL
// defaultFont deliberately left at dompdf's own default (Helvetica, its
// built-in Arial-equivalent) — see shop-invoice-pdf.php's own comment for
// the full rationale (identical here, same renderer).

// Dynamic page height so the invoice always renders as ONE continuous page
// regardless of item count — same approach as territory-partner's
// shop-invoice-pdf.php. See that file's own comment for the full rationale.
$__itemCount = count($invData['invoice_items'] ?? []);
$__hsnCount  = count($invData['hsn_totals'] ?? []);
$__baseMm      = 210 * $__scale * (210 / $__paperWidthMm);
$__perItemMm   = 7 * $__scale * (210 / $__paperWidthMm);
$__perHsnRowMm = 5 * $__scale;
$__estimatedMm = $__baseMm + ($__itemCount * $__perItemMm) + (max(0, $__hsnCount - 1) * $__perHsnRowMm);
$__minPageHeightMm = $__paperWidthMm * (297 / 210);
$__pageHeightMm = max($__minPageHeightMm, $__estimatedMm);
$__mmToPt = 72 / 25.4;
$__pageWidthPt  = $__paperWidthMm * $__mmToPt;
$__pageHeightPt = $__pageHeightMm * $__mmToPt;

$dompdf = new Dompdf($options);
$dompdf->setPaper([0, 0, $__pageWidthPt, $__pageHeightPt]);
$dompdf->loadHtml($html);
$dompdf->render();

$fileName = 'Invoice_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $invData['inv']['inv_number'] ?? 'invoice') . '.pdf';

// dompdf's stream() sends no cache-control headers of its own, so without
// this a browser (and any CDN in front of the site) is free to cache this
// exact URL indefinitely under default heuristics.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Attachment so Download PDF / Share to WhatsApp always gets a real
// downloaded file, never a view-only link.
$dompdf->stream($fileName, ['Attachment' => true]);
