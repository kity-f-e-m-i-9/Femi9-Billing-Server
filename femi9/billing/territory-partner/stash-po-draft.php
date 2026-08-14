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
    ];
}

if (empty($lines)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nothing to save.']);
    exit;
}

$_SESSION['po_draft_' . (int)$Login_user_IDvl] = [
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
];

echo json_encode(['success' => true]);
