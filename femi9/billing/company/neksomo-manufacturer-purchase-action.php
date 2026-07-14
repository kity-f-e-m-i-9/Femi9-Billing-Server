<?php
declare(strict_types=1);

include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

function redirectWithMessage(string $location, string $message = ''): void {
    $url = $location . ($message ? '?' . $message : '');
    header("Location: $url");
    exit();
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    redirectWithMessage('neksomo-manufacturer-purchase.php', 'error');
}

if (!isset($_POST['add-record'])) {
    redirectWithMessage('neksomo-manufacturer-purchase.php');
}

$product_id        = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
$manufacturer_name = trim($_POST['manufacturer_name'] ?? '');
$purchase_date     = $_POST['purchase_date'] ?? '';
$quantity_packs    = filter_var($_POST['quantity_packs'] ?? 0, FILTER_VALIDATE_INT);
$cost_per_piece    = filter_var($_POST['cost_per_piece'] ?? 0, FILTER_VALIDATE_FLOAT);

if (
    !$product_id || $manufacturer_name === '' ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchase_date) ||
    !$quantity_packs || $quantity_packs <= 0 ||
    $cost_per_piece === false || $cost_per_piece < 0
) {
    redirectWithMessage('neksomo-manufacturer-purchase.php', 'error');
}

$manufacturer_name = htmlspecialchars($manufacturer_name, ENT_QUOTES, 'UTF-8');

$pieces_per_pack = (int) $db_conn->query(
    "SELECT pieces_per_pack FROM products WHERE id = " . (int)$product_id
)->fetch_row()[0] ?? 0;
$total_cost = round($quantity_packs * ($pieces_per_pack ?: 1) * $cost_per_piece, 2);
$created_by = $_SESSION['LOGIN_USER'] ?? 'system';

// Neksomo's own godown id, looked up by name rather than hardcoded — this
// page is only ever reachable by neksomo/admin, and the stock it credits is
// always Neksomo Hygiene Industries' own on-hand stock.
$neksomoGodownId = (int) ($db_conn->query(
    "SELECT id FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1"
)->fetch_row()[0] ?? 0);

if (!$neksomoGodownId) {
    redirectWithMessage('neksomo-manufacturer-purchase.php', 'error');
}

$db_conn->begin_transaction();
try {
    $stockService = new StockService($db_conn);
    // stock_ledger.ref_type is a closed ENUM (invoice/user_invoice/return/
    // transfer/ot_sale/adjustment/demofree/tp_invoice) — 'adjustment' is
    // what Add Input Stock also uses; the distinguishing detail goes in ref_id.
    $refId = 'manuf_purchase_' . uniqid();
    $result = $stockService->credit(
        $product_id,
        'company',
        (string) $neksomoGodownId,
        $quantity_packs,
        'adjustment',
        $refId,
        $created_by,
        true // caller owns the transaction
    );

    $stmt = $db_conn->prepare(
        "INSERT INTO neksomo_manufacturer_purchases
            (product_id, manufacturer_name, purchase_date, quantity_packs, cost_per_piece, total_cost, stock_ledger_id, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $ledgerId = $result['ledger_id'];
    $stmt->bind_param('issiddis', $product_id, $manufacturer_name, $purchase_date, $quantity_packs, $cost_per_piece, $total_cost, $ledgerId, $created_by);
    $stmt->execute();
    $stmt->close();

    $db_conn->commit();
    redirectWithMessage('neksomo-manufacturer-purchase.php', 'addesuccess');
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log('[neksomo-manufacturer-purchase] ' . $e->getMessage());
    redirectWithMessage('neksomo-manufacturer-purchase.php', 'error');
}
