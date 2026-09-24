<?php
// AJAX: suggests the next Internal Stock Transfer invoice number for a given
// "Send From" company profile — one more than the highest number that
// profile has actually used so far (internal_transfer_invoice.inv_number,
// joined via internal_transfer.send_from), same highest-number approach
// already used for TP invoices (territory-partner/include/InvoiceNumberSuggest.php),
// not simply the last-inserted row, so out-of-order numbering can't suggest
// an already-used number. Confirmed 2026-09-23.
include("checksession.php");
include("config.php");
error_reporting(0);
header('Content-Type: application/json');

$sendFrom = (int)($_GET['send_from'] ?? 0);
if ($sendFrom <= 0) {
    echo json_encode(['success' => false, 'inv_number' => '']);
    exit;
}

$stmt = $db_conn->prepare(
    "SELECT iti.inv_number
     FROM internal_transfer_invoice iti
     JOIN internal_transfer it ON it.tempid = iti.tempid
     WHERE it.send_from = ?
     ORDER BY iti.id DESC LIMIT 500"
);
$stmt->bind_param('i', $sendFrom);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$bestPrefix = null;
$bestWidth  = 0;
$bestValue  = -1;
foreach ($rows as $row) {
    $invNum = (string)($row['inv_number'] ?? '');
    if (!preg_match('/^(.*?)(\d+)$/', $invNum, $m)) { continue; }
    $value = (int)$m[2];
    if ($value > $bestValue) {
        $bestValue  = $value;
        $bestPrefix = $m[1];
        $bestWidth  = strlen($m[2]);
    }
}

if ($bestPrefix === null) {
    echo json_encode(['success' => true, 'inv_number' => '']);
    exit;
}

$nextValue = (string)($bestValue + 1);
$suggested = $bestPrefix . str_pad($nextValue, $bestWidth, '0', STR_PAD_LEFT);

echo json_encode(['success' => true, 'inv_number' => $suggested]);
