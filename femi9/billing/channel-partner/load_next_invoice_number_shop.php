<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/InvoiceNumberSuggest.php';
header('Content-Type: application/json');

// Auto-suggest for channel partner's shop-channel invoices (user_invoice
// table, scoped to this logged-in account) — format CPSH/FY/LLP{n} (napkin)
// or CPSHD/FY/LLP{n} (diaper), decided by whichever product type is added
// FIRST to the invoice (see InvoiceNumberSuggest.php). ?pid= is the
// just-selected product id, optional so this endpoint still works before any
// product is chosen.
$category = 'napkin';
if (isset($_GET['pid']) && ctype_digit((string)$_GET['pid'])) {
    $category = invoiceProductCategory($db_conn, (int)$_GET['pid']);
}

$whereSql = "from_user_type='" . $db_conn->real_escape_string($Login_user_TYPEvl) . "' and from_user_id='" . $db_conn->real_escape_string($Login_user_IDvl) . "'";
$number = invoiceSuggestNextNumber($db_conn, 'user_invoice', $whereSql, 'CPSH', 'CPSHD', $category);

echo json_encode(['number' => $number]);
