<?php
/**
 * No-login PDF endpoint for TP invoices raised by a super-stockist, opened
 * from a WhatsApp share link (see the "PDF / Share" button on
 * tp-invoice-print.php). The recipient never has a session cookie here, so
 * this deliberately does NOT include checksession.php — access is instead
 * gated by a signed token (see InvoiceShareLink.php).
 */
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/SuperStockistTpInvoiceData.php';
require_once __DIR__ . '/../shared/SuperStockistTpInvoiceHtml.php';
require_once __DIR__ . '/../shared/InvoiceShareLink.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$enc_id = $_GET['id'] ?? '';
$sig    = $_GET['sig'] ?? '';

if ($enc_id === '' || !invoice_share_verify('sstp', $enc_id, $sig)) {
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

// The seller (SS) is derived from the invoice's own TP, not a session —
// there's no logged-in user on this endpoint. territory_partners.onboard_ss_id
// is the super-stockist that onboarded this TP, same scoping
// tp-invoice-print.php uses.
$ssRow = mysqli_fetch_array(mysqli_query($db_conn, "
    SELECT tp.onboard_ss_id
    FROM tp_invoices tpi
    JOIN territory_partners tp ON tp.id = tpi.territory_partner_id
    WHERE tpi.id = " . (int)$inv_id . "
    LIMIT 1
"));
if (!$ssRow || $ssRow['onboard_ss_id'] === null) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Invoice not found.';
    exit;
}
$ss_id = $ssRow['onboard_ss_id'];

$invData = load_superstockist_tp_invoice_data($db_conn, $inv_id, $ss_id);
if (!$invData) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Invoice not found.';
    exit;
}

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
    . render_superstockist_tp_invoice_html($invData, true, $__scale, $__marginMm)
    . '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);

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
