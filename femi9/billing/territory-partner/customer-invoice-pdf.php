<?php
/**
 * No-login PDF endpoint for customer invoices, opened by the customer from a
 * WhatsApp share link (see the "Share to WhatsApp" button on
 * customer-invoice-print.php). The recipient never has a session cookie
 * here, so this deliberately does NOT include checksession.php — access is
 * instead gated by a signed token (see InvoiceShareLink.php) so only links
 * this app generated itself work; a guessed/incremented invoice id will fail
 * signature verification.
 */
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/../shared/CustomerInvoiceData.php';
require_once __DIR__ . '/../shared/CustomerInvoiceHtml.php';
require_once __DIR__ . '/../shared/InvoiceShareLink.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$Invoice_ID = $_GET['id'] ?? '';
$sig        = $_GET['sig'] ?? '';

if ($Invoice_ID === '' || !invoice_share_verify('customer', $Invoice_ID, $sig)) {
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
// there's no logged-in user on this endpoint. invoice.user_id is the TP
// that raised the invoice, mirroring how customer-invoice-print.php's
// $Login_user_IDvl is always that same TP for any invoice it's allowed to
// show.
$invRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT user_id FROM invoice WHERE inv_id='" . mysqli_real_escape_string($db_conn, $decoded_id) . "' AND user_type='territory_partner' LIMIT 1"));
if (!$invRow) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Invoice not found.';
    exit;
}
$tp_id = (int)$invRow['user_id'];

$invData = load_customer_invoice_data($db_conn, $decoded_id, $tp_id);
if (!$invData) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Invoice not found.';
    exit;
}

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'
    . render_customer_invoice_html($invData, true)
    . '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);   // logo images are fetched by URL
// defaultFont deliberately left at dompdf's own default (Helvetica, its
// built-in Arial-equivalent) rather than forced to DejaVu Sans — the
// invoice CSS itself (CustomerInvoiceHtml.php) already lists
// arial,"DejaVu Sans" as its font-family, so dompdf only drops to DejaVu
// Sans for the one character (₹) missing from Arial/Helvetica, the same
// automatic font-fallback substitution a browser does silently. Forcing
// defaultFont here previously replaced the typeface for ALL text, not
// just the missing glyph, making every column wider than the Print page.

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml($html);
$dompdf->render();

$fileName = 'Invoice_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $invData['inv']['inv_number'] ?? 'invoice') . '.pdf';

// inline (not attachment) so tapping the WhatsApp link opens the PDF
// straight in the phone's browser/PDF viewer instead of forcing a download
// prompt first.
$dompdf->stream($fileName, ['Attachment' => false]);
