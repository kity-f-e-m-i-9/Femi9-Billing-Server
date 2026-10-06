<?php
/**
 * No-login PDF endpoint for super-stockist-raised invoices, opened by the
 * buyer (stockiest/super_distributor/distributor) from a WhatsApp share
 * link (see the "PDF / Share" button on user-invoice-print.php). The
 * recipient never has a session cookie here, so this deliberately does NOT
 * include checksession.php — access is instead gated by a signed token
 * (see InvoiceShareLink.php) so only links this app generated itself work.
 */
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/../shared/SuperStockistUserInvoiceData.php';
require_once __DIR__ . '/../shared/SuperStockistUserInvoiceHtml.php';
require_once __DIR__ . '/../shared/InvoiceShareLink.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$Invoice_ID = $_GET['id'] ?? '';
$sig        = $_GET['sig'] ?? '';

if ($Invoice_ID === '' || !invoice_share_verify('ssuser', $Invoice_ID, $sig)) {
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

// The seller (super-stockist) is looked up from the invoice itself, not a
// session — there's no logged-in user on this endpoint.
$invRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT from_user_id FROM user_invoice WHERE inv_id='" . mysqli_real_escape_string($db_conn, $decoded_id) . "' AND from_user_type='super_stockiest' LIMIT 1"));
if (!$invRow) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Invoice not found.';
    exit;
}
$ss_id = (int)$invRow['from_user_id'];

$invData = load_superstockist_user_invoice_data($db_conn, $decoded_id, $ss_id);
if (!$invData) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Invoice not found.';
    exit;
}

// Paper size / margin / scale, driven by the "PDF options" dialog on
// user-invoice-print.php (same pattern as shop-invoice-pdf.php).
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
    . render_superstockist_user_invoice_html($invData, true, $__scale, $__marginMm)
    . '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);

// Dynamic page height so the invoice always renders as ONE continuous page
// regardless of item count — same approach as shop-invoice-pdf.php.
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

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$dompdf->stream($fileName, ['Attachment' => true]);
