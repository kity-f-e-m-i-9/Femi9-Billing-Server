<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/InvoiceNumberSuggest.php';
header('Content-Type: application/json');

// Auto-suggest for super distributor's user_invoice invoices (scoped to this
// logged-in account) — covers both downstream targets this account's
// user-invoice-add.php supports: invuser=distributor (network) and
// invuser=shop (default/else branch, see user-invoice-add.php:12-25).
// Napkin vs diaper decided by whichever product type is added FIRST to the
// invoice (see InvoiceNumberSuggest.php). ?pid= is the just-selected product
// id, optional so this endpoint still works before any product is chosen.
$invuser = $_GET['invuser'] ?? 'shop';
$prefixMap = array(
    'distributor' => array('DT', 'DTD'),
    'shop'        => array('SDSH', 'SDSHD'),
);
if (!isset($prefixMap[$invuser])) {
    $invuser = 'shop';
}
list($napkinPrefix, $diaperPrefix) = $prefixMap[$invuser];

$category = 'napkin';
if (isset($_GET['pid']) && ctype_digit((string)$_GET['pid'])) {
    $category = invoiceProductCategory($db_conn, (int)$_GET['pid']);
}

$whereSql = "from_user_type='" . $db_conn->real_escape_string($Login_user_TYPEvl) . "' and from_user_id='" . $db_conn->real_escape_string($Login_user_IDvl) . "'";
$number = invoiceSuggestNextNumber($db_conn, 'user_invoice', $whereSql, $napkinPrefix, $diaperPrefix, $category);

echo json_encode(['number' => $number]);
