<?php
declare(strict_types=1);

include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");

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
    redirectWithMessage('neksomo-product-add.php', 'error');
}

$action      = $_POST['action'] ?? 'insert-product';
$productName = trim(str_replace("'", "", $_POST['productName'] ?? ''));
$hsn         = trim(str_replace("'", "", $_POST['hsn'] ?? ''));

if ($action === 'insert-product') {
    if ($productName === '') {
        redirectWithMessage('neksomo-product-add.php', 'error');
    }

    // Products added here are piece-native — no pack size (pieces_per_pack
    // stays NULL, which every Neksomo report already treats as "1 piece per
    // pack") and none of the reseller pack-tier prices apply, so they're all
    // zeroed rather than shown as fields on the form.
    $temp_id         = 'NKS-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $pieces_per_pack = null;
    $zero            = 0;
    $gst_type        = 'exclusive';

    $stmt = $db_conn->prepare(
        "INSERT INTO products
            (temp_id, productName, pieces_per_pack, mrp, supersstock_price, super_distributor_price,
             stockist_price, distributor_price, outlet_price, gst, gst_type, hsn, rwpoints, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );
    $stmt->bind_param(
        'ssidddddddssd',
        $temp_id, $productName, $pieces_per_pack, $zero, $zero, $zero,
        $zero, $zero, $zero, $zero, $gst_type, $hsn, $zero
    );
    $ok = $stmt->execute();
    $product_id = $ok ? (int)$db_conn->insert_id : 0;
    $stmt->close();

    // Give it a zeroed stock row in Neksomo's own godown right away, so it
    // shows up on Overall Stock immediately instead of only after its first
    // purchase (StockService::credit() would otherwise create this row lazily).
    if ($ok) {
        $neksomoGodownId = (int) ($db_conn->query(
            "SELECT id FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1"
        )->fetch_row()[0] ?? 0);
        if ($neksomoGodownId) {
            $neksomoGodownIdStr = (string) $neksomoGodownId;
            $stockStmt = $db_conn->prepare(
                "INSERT INTO stock
                    (product_id, opening_qty, opening_date, input_qty, sales_qty,
                     sent_qty, returnqty, closing_qty, extra_pieces, user_type, user_id, updated_at)
                 VALUES (?, 0, CURDATE(), 0, 0, 0, 0, 0, 0, 'company', ?, NOW())"
            );
            $stockStmt->bind_param('is', $product_id, $neksomoGodownIdStr);
            $stockStmt->execute();
            $stockStmt->close();
        }
    }

    redirectWithMessage('neksomo-product-add.php', $ok ? 'addesuccess' : 'error');
} elseif ($action === 'update-product') {
    $product_id = (int)($_POST['product_id'] ?? 0);

    // Only ever touch products this login created — never the shared/admin catalog.
    $own = $db_conn->prepare("SELECT id FROM products WHERE id = ? AND temp_id LIKE 'NKS-%'");
    $own->bind_param('i', $product_id);
    $own->execute();
    if (!$product_id || $own->get_result()->num_rows === 0) {
        $own->close();
        redirectWithMessage('neksomo-manage-products.php', 'error');
    }
    $own->close();

    if ($productName === '') {
        redirectWithMessage('neksomo-product-edit.php?id=' . $product_id, 'error');
    }

    $stmt = $db_conn->prepare("UPDATE products SET productName = ?, hsn = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('ssi', $productName, $hsn, $product_id);
    $ok = $stmt->execute();
    $stmt->close();

    redirectWithMessage('neksomo-manage-products.php', $ok ? 'updatedSuccess' : 'error');
} else {
    redirectWithMessage('neksomo-manage-products.php');
}
