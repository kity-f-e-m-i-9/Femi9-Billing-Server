<?php
/**
 * No-login PDF endpoint for TP purchase bills, opened by whoever the TP
 * forwards it to from a WhatsApp share link (see the "Share to WhatsApp"
 * button on purchased-bill-print.php). The recipient never has a session
 * cookie here, so this deliberately does NOT include checksession.php —
 * access is instead gated by a signed token (see InvoiceShareLink.php) so
 * only links this app generated itself work; a guessed/incremented invoice
 * id will fail signature verification.
 */
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/../shared/PurchasedBillData.php';
require_once __DIR__ . '/../shared/PurchasedBillHtml.php';
require_once __DIR__ . '/../shared/InvoiceShareLink.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$Invoice_ID = $_GET['id'] ?? '';
$sig        = $_GET['sig'] ?? '';

if ($Invoice_ID === '' || !invoice_share_verify('purchase', $Invoice_ID, $sig)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Invalid or expired invoice link.';
    exit;
}

$inv_id = (int)base64_decode($Invoice_ID, true);
if (!$inv_id) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Invalid invoice link.';
    exit;
}

// The buyer (TP) is looked up from the bill itself, not a session — there's
// no logged-in user on this endpoint. tp_invoices.territory_partner_id is
// the TP that raised this purchase order, mirroring how
// purchased-bill-print.php's $Login_user_IDvl is always that same TP for
// any bill it's allowed to show.
$billRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT territory_partner_id FROM tp_invoices WHERE id='" . (int)$inv_id . "' LIMIT 1"));
if (!$billRow) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Invoice not found.';
    exit;
}
$tp_id = (int)$billRow['territory_partner_id'];

$billData = load_purchased_bill_data($db_conn, $inv_id, $tp_id);
if (!$billData) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Invoice not found.';
    exit;
}

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'
    . render_purchased_bill_html($billData, true)
    . '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);   // logo images are fetched by URL
// DejaVu Sans (bundled with dompdf) instead of Arial — Arial has no glyph
// for the Rupee sign (₹, the &#8377; entity used throughout the invoice),
// which rendered as a "?" tofu box under the Arial fallback.
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml($html);
$dompdf->render();

$fileName = 'Purchase_Bill_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $billData['result_Invoice_Details']['invoice_number'] ?? 'bill') . '.pdf';

// inline (not attachment) so tapping the WhatsApp link opens the PDF
// straight in the phone's browser/PDF viewer instead of forcing a download
// prompt first.
$dompdf->stream($fileName, ['Attachment' => false]);
