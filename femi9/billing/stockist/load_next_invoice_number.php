<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/InvoiceNumberSuggest.php';
header('Content-Type: application/json');

// Auto-suggest for stockist's network channel invoices (user_invoice table,
// scoped to this logged-in account) — one prefix per downstream channel
// (?invuser=), napkin vs diaper decided by whichever product type is added
// FIRST to the invoice (see InvoiceNumberSuggest.php). ?pid= is the
// just-selected product id, optional so this endpoint still works before any
// product is chosen.
$invuser = $_GET['invuser'] ?? '';
$prefixMap = array(
    'super_distributor'  => array('SD', 'SDD'),
    'distributor'        => array('DT', 'DTD'),
);
if (!isset($prefixMap[$invuser])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid invuser']);
    exit;
}
list($napkinPrefix, $diaperPrefix) = $prefixMap[$invuser];

$category = 'napkin';
if (isset($_GET['pid']) && ctype_digit((string)$_GET['pid'])) {
    $category = invoiceProductCategory($db_conn, (int)$_GET['pid']);
}

$whereSql = "from_user_type='" . $db_conn->real_escape_string($Login_user_TYPEvl) . "' and from_user_id='" . $db_conn->real_escape_string($Login_user_IDvl) . "'";
$number = invoiceSuggestNextNumber($db_conn, 'user_invoice', $whereSql, $napkinPrefix, $diaperPrefix, $category);

echo json_encode(['number' => $number]);
