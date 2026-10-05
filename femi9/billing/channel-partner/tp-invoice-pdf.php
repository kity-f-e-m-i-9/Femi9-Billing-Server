<?php
/**
 * No-login PDF endpoint for TP invoices viewed from the channel-partner
 * login, opened from a WhatsApp share link (see the "PDF / Share" button on
 * tp-invoice-print.php). The recipient never has a session cookie here, so
 * this deliberately does NOT include checksession.php — access is instead
 * gated by a signed token (see InvoiceShareLink.php), the same security
 * model company/tp-invoice-pdf.php uses. No source_cp_id restriction here
 * (unlike the logged-in Print page) since a valid signed link is itself the
 * authorization — it can only have been minted by a CP who already passed
 * that check when generating it.
 *
 * Packs/Carton and Cartons columns are dropped on this PDF (unlike the
 * regular Print page, which shows them whenever the invoice has carton
 * data) — same behavior company/tp-invoice-pdf.php has.
 */
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/TpInvoiceData.php';
require_once __DIR__ . '/../shared/TpInvoiceHtml.php';
require_once __DIR__ . '/../shared/InvoiceShareLink.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$enc_id = $_GET['id'] ?? '';
$sig    = $_GET['sig'] ?? '';

if ($enc_id === '' || !invoice_share_verify('tp', $enc_id, $sig)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Invalid or expired invoice link.';
    exit;
}

$inv_id = (int)base64_decode($enc_id, true);
if (!$inv_id) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Invalid invoice link.';
    exit;
}

$invData = load_tp_invoice_data($db_conn, $inv_id);
if (!$invData) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Invoice not found.';
    exit;
}

// Paper size / margin / scale, driven by the "PDF options" dialog on
// tp-invoice-print.php (same pattern as company's).
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
    . render_tp_invoice_html($invData, false, true, $__scale, $__marginMm)
    . '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);

// Dynamic page height so the invoice always renders as ONE continuous page
// regardless of item count — same approach as company/tp-invoice-pdf.php.
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

$__tpName = preg_replace('/[^a-zA-Z0-9]+/', '', trim($invData['result_Invoice_Details']['tp_name'] ?? 'TP'));
$__invNo  = str_replace(['/', '\\'], '-', trim($invData['result_Invoice_Details']['invoice_number'] ?? 'invoice'));
$fileName = "{$__tpName}_Invoice_{$__invNo}.pdf";

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$dompdf->stream($fileName, ['Attachment' => true]);
