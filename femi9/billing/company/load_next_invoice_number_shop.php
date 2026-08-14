<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/InvoiceNumberSuggest.php';
header('Content-Type: application/json');

// Auto-suggest for company's shop-channel invoices (user_invoice table,
// from_user_type='company') — format SH/FY/LLP{n} (napkin) or SHD/FY/LLP{n}
// (diaper), decided by whichever product type is added FIRST to the invoice
// (see InvoiceNumberSuggest.php). ?pid= is the just-selected product id,
// optional so this endpoint still works before any product is chosen.
$category = 'napkin';
if (isset($_GET['pid']) && ctype_digit((string)$_GET['pid'])) {
    $category = invoiceProductCategory($db_conn, (int)$_GET['pid']);
}

$number = invoiceSuggestNextNumber($db_conn, 'user_invoice', "from_user_type='company'", 'SH', 'SHD', $category);

echo json_encode(['number' => $number]);
