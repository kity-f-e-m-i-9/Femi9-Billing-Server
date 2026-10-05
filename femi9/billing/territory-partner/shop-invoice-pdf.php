<?php
/**
 * No-login PDF endpoint for shop invoices, opened by the shop from a WhatsApp
 * share link (see the "Share to WhatsApp" button on shop-invoice-print.php).
 * The recipient never has a session cookie here, so this deliberately does
 * NOT include checksession.php — access is instead gated by a signed token
 * (see InvoiceShareLink.php) so only links this app generated itself work;
 * a guessed/incremented invoice id will fail signature verification.
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
// the TP that raised the invoice, mirroring how shop-invoice-print.php's
// $Login_user_IDvl is always that same TP for any invoice it's allowed to
// show.
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
// shop-invoice-print.php (mirrors the browser's own Print "More settings"
// panel) so the TP can actually control how the downloaded/shared PDF looks
// instead of always getting one fixed layout. Every value is clamped
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
// built-in Arial-equivalent) rather than forced to DejaVu Sans — the
// invoice CSS itself (ShopInvoiceHtml.php) already lists
// arial,"DejaVu Sans" as its font-family, so dompdf only drops to DejaVu
// Sans for the one character (₹) missing from Arial/Helvetica, the same
// automatic font-fallback substitution a browser does silently. Forcing
// defaultFont here previously replaced the typeface for ALL text, not
// just the missing glyph, making every column wider than the Print page.

// Dynamic page height so the invoice always renders as ONE continuous page
// regardless of how many products it has, instead of a fixed A4 height that
// a long product list would spill past into a second page. dompdf has no
// browser-style "shrink to fit" — the standard way to get a one-page PDF
// for variable-length content is to size the page itself to the content
// (like a receipt-printer roll), not to fight a fixed page size. Width
// stays A4's own width so the invoice still reads like a normal document,
// only the height is custom.
//
// Estimated in mm at the PDF's own tightened font/padding sizes
// (see ShopInvoiceHtml.php's $forPdf styles): a generous per-item allowance
// covers a product name that wraps to 2 lines. $baseMm covers every part of
// the layout that DOESN'T grow with item count (seller/buyer/meta blocks,
// the item table's own header + subtotal/GST/discount/total rows, amount-
// in-words, HSN summary header+total, declaration+bank details, seal/
// signature, footer line, @page margins). Never goes below the chosen
// paper's own A-series height so a short invoice still looks like a normal
// page, not a stub. Scaled by $__scale (the Scale option) and widened/
// narrowed by $__paperWidthMm (the Paper size option) — a smaller paper
// width wraps the item table onto more lines, a larger Scale enlarges the
// fonts, and both make the content taller, so the estimate accounts for
// both instead of just the item count.
$__itemCount = count($invData['invoice_items'] ?? []);
$__hsnCount  = count($invData['hsn_totals'] ?? []);
$__baseMm      = 210 * $__scale * (210 / $__paperWidthMm);
$__perItemMm   = 7 * $__scale * (210 / $__paperWidthMm);
$__perHsnRowMm = 5 * $__scale; // beyond the first HSN row, which $__baseMm already covers
$__estimatedMm = $__baseMm + ($__itemCount * $__perItemMm) + (max(0, $__hsnCount - 1) * $__perHsnRowMm);
$__minPageHeightMm = $__paperWidthMm * (297 / 210); // same aspect ratio as A4 for the chosen width
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
// exact URL indefinitely under default heuristics — the URL never changes
// for a given invoice, so a stale cached copy from before a rendering fix
// or a later invoice edit could keep being served instead of a fresh one.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Attachment so tapping the WhatsApp link downloads the PDF straight to the
// device instead of just opening a view-only tab — the TP asked for a real
// downloaded file, not a link the recipient has to view-then-save themselves.
$dompdf->stream($fileName, ['Attachment' => true]);
