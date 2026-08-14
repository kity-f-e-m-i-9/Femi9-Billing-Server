<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/InvoiceNumberSuggest.php';
header('Content-Type: application/json');

// Auto-suggest for Femi Nayan LLP customer invoices only — format: C/FY/LLP{n}
// (napkin) or CD/FY/LLP{n} (diaper), decided by whichever product type is
// added FIRST to the invoice (this page has no cart to inspect up front —
// see InvoiceNumberSuggest.php for why). ?pid= is the just-selected product id,
// optional so this endpoint still works before any product is chosen.
$category = 'napkin';
if (isset($_GET['pid']) && ctype_digit((string)$_GET['pid'])) {
    $category = invoiceProductCategory($db_conn, (int)$_GET['pid']);
}

$number = invoiceSuggestNextNumber($db_conn, 'invoice', "user_type='company'", 'C', 'CD', $category);

echo json_encode(['number' => $number]);
