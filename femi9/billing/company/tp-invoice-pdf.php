<?php
/**
 * No-login PDF endpoint for TP invoices (company-issued, billed to a
 * Territory Partner), opened from a WhatsApp share link (see the "Share to
 * WhatsApp" button on tp-invoice-print.php). The recipient never has a
 * session cookie here, so this deliberately does NOT include
 * checksession.php — access is instead gated by a signed token (see
 * InvoiceShareLink.php) so only links this app generated itself work; a
 * guessed/incremented invoice id will fail signature verification.
 *
 * Packs/Carton and Cartons columns are dropped on this PDF (unlike the
 * regular Print page, which shows them whenever the invoice has carton
 * data) — same behavior the old iframe/html2pdf WhatsApp flow had via its
 * `?whatsapp=1` flag on tp-invoice-print.php.
 */
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/include/GodownAccess.php';
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

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'
    . render_tp_invoice_html($invData, false, true)
    . '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);   // logo images are fetched by URL
// defaultFont deliberately left at dompdf's own default (Helvetica, its
// built-in Arial-equivalent) rather than forced to DejaVu Sans — the
// invoice CSS itself (TpInvoiceHtml.php) already lists
// arial,"DejaVu Sans" as its font-family, so dompdf only drops to DejaVu
// Sans for the one character (₹) missing from Arial/Helvetica, the same
// automatic font-fallback substitution a browser does silently. Forcing
// defaultFont here previously replaced the typeface for ALL text, not
// just the missing glyph, making every column wider than the Print page.

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml($html);
$dompdf->render();

$fileName = 'TPInvoice_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $invData['result_Invoice_Details']['invoice_number'] ?? 'invoice') . '.pdf';

// inline (not attachment) so tapping the WhatsApp link opens the PDF
// straight in the phone's browser/PDF viewer instead of forcing a download
// prompt first.
$dompdf->stream($fileName, ['Attachment' => false]);
