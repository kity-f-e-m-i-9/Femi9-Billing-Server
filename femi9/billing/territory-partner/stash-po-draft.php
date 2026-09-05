<?php
include("checksession.php");
include("config.php");
error_reporting(0);

header('Content-Type: application/json');

// Saves the in-progress purchase order (cart lines + delivery address) into
// the session so it survives the redirect to add-advance-payment.php and
// back. Keyed per-TP so it can't leak across accounts sharing a browser.
// Cleared once the PO is actually submitted (purchase-order-action.php) or
// explicitly discarded, whichever comes first — otherwise a stale draft
// would keep reappearing on unrelated future visits to the PO page.

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || empty($data['lines']) || !is_array($data['lines'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nothing to save.']);
    exit;
}

$lines = [];
foreach ($data['lines'] as $l) {
    if (!isset($l['pr_id'], $l['qty'])) continue;
    $lines[] = [
        'pr_id'    => (string)$l['pr_id'],
        'name'     => (string)($l['name'] ?? ''),
        'qty'      => (int)$l['qty'],
        'price'    => (float)($l['price'] ?? 0),
        'discPct'  => (float)($l['discPct'] ?? 0),
        'discAmt'  => (float)($l['discAmt'] ?? 0),
        // 'pickup' (TP collects this line in person, no courier fee) or
        // 'courier' (default) — set via the "Pick Up Order" modal on
        // add-purchase-order.php, never left client-controlled beyond this
        // draft: every downstream courier-fee calc re-reads it from here,
        // not from anything the TP submits directly at payment time.
        'method'   => ($l['method'] ?? 'courier') === 'pickup' ? 'pickup' : 'courier',
    ];
}

if (empty($lines)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nothing to save.']);
    exit;
}

// A courier-amount-change request approved for the draft's exact line items
// (see request-courier-amount-change.php) stays usable ONLY while those
// exact items keep getting re-stashed unchanged — e.g. the TP goes back and
// forth between this page and pay-courier-payment.php without touching the
// cart. The instant a line/qty/method changes, this is a materially
// different order, so the flag is dropped and the TP falls back to the
// normal box/cover calculation (or can raise a fresh request for the new
// cart) — never silently inherits an approval meant for the old cart.
$draftKey = 'po_draft_' . (int)$Login_user_IDvl;
$previousLines = $_SESSION[$draftKey]['lines'] ?? null;
$carryCourierRequestId = ($previousLines !== null && $previousLines === $lines)
    ? ($_SESSION[$draftKey]['courier_request_id'] ?? null)
    : null;

$_SESSION[$draftKey] = [
    'lines'                          => $lines,
    'use_default_delivery_address'   => !empty($data['use_default_delivery_address']),
    'custom_delivery_line1'          => (string)($data['custom_delivery_line1']    ?? ''),
    'custom_delivery_line2'          => (string)($data['custom_delivery_line2']    ?? ''),
    'custom_delivery_city'           => (string)($data['custom_delivery_city']     ?? ''),
    'custom_delivery_district'       => (string)($data['custom_delivery_district'] ?? ''),
    'custom_delivery_state'          => (string)($data['custom_delivery_state']    ?? ''),
    'custom_delivery_country'        => (string)($data['custom_delivery_country']  ?? ''),
    'custom_delivery_pincode'        => (string)($data['custom_delivery_pincode']  ?? ''),
    'saved_at'                       => time(),
    'courier_request_id'             => $carryCourierRequestId,
];

echo json_encode(['success' => true]);
