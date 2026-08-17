<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/InvoiceNumberSuggest.php';
header('Content-Type: application/json');

// Auto-suggest for company's network channel invoices (user_invoice table,
// from_user_type='company') — one prefix per downstream channel (?invuser=),
// napkin vs diaper decided by whichever product type is added FIRST to the
// invoice (see InvoiceNumberSuggest.php). ?pid= is the just-selected product
// id, optional so this endpoint still works before any product is chosen.
$invuser = $_GET['invuser'] ?? '';
$prefixMap = array(
    'super_stockiest'    => array('SS', 'SSD'),
    'stockiest'          => array('ST', 'STD'),
    'super_distributor'  => array('SD', 'SDD'),
    'distributor'        => array('DT', 'DTD'),
    'candf'              => array('CF', 'CFD'),
    // 'outlet' is reachable through this same file (user-invoice-add?invuser=outlet,
    // see femi_menu.php) — 'shop' is a different file (shop-user-invoice-add.php,
    // see load_next_invoice_number_shop.php) and deliberately not listed here.
    'outlet'             => array('OT', 'OTD'),
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

$number = invoiceSuggestNextNumber($db_conn, 'user_invoice', "from_user_type='company'", $napkinPrefix, $diaperPrefix, $category);

echo json_encode(['number' => $number]);
