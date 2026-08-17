<?php
/**
 * POST /po/create
 * Spec §1.5 — creates a PO directly when the cart is within balance.
 * idempotency_key is mandatory (Wati/Wati agent retries must not create
 * duplicate POs) and enforced via a UNIQUE column — a retried call with
 * the same key returns the original PO rather than erroring or duplicating.
 */
require_once __DIR__ . '/_bootstrap.php';

$session = wa_po_require_session($db_conn, $input);
$category = $session['user_category'];
$userId = $session['user_id'];

$items = $input['items'] ?? null;
$totalAmount = $input['total_amount'] ?? null;
$idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
$source = trim((string)($input['source'] ?? 'whatsapp'));

if (!is_array($items) || count($items) === 0) wa_po_fail(400, 'items is required and must be a non-empty array');
if (!is_numeric($totalAmount)) wa_po_fail(400, 'total_amount is required');
if ($idempotencyKey === '') wa_po_fail(400, 'idempotency_key is required');

if (!wa_po_rate_limit_check($db_conn, 'po-create:' . $category . ':' . $userId, 20, 3600)) {
    wa_po_fail(429, 'Too many PO creation attempts — please try again later');
}

// Idempotency: if this key was already used, return the existing PO
// instead of creating a duplicate (spec §1.5's core requirement).
$existingStmt = mysqli_prepare($db_conn, "SELECT id, created_at FROM wa_po_purchase_orders WHERE idempotency_key = ?");
mysqli_stmt_bind_param($existingStmt, 's', $idempotencyKey);
mysqli_stmt_execute($existingStmt);
$existing = mysqli_stmt_get_result($existingStmt)->fetch_assoc();
mysqli_stmt_close($existingStmt);

if ($existing) {
    echo json_encode([
        'po_number' => wa_po_format_po_number($existing['id'], $existing['created_at']),
        'status' => 'created',
        'created_at' => date('c', strtotime($existing['created_at'])),
    ]);
    exit;
}

// Resolve each cart line's product by SKU (temp_id) and validate qty/price.
$resolvedItems = [];
$computedTotal = 0.0;
foreach ($items as $i => $item) {
    $sku = trim((string)($item['sku'] ?? ''));
    $qty = $item['qty'] ?? null;
    $price = $item['price'] ?? null;
    if ($sku === '') wa_po_fail(400, "items[$i].sku is required");
    if (!is_numeric($qty) || (int)$qty <= 0) wa_po_fail(400, "items[$i].qty must be a positive number");
    if (!is_numeric($price) || (float)$price < 0) wa_po_fail(400, "items[$i].price must be a non-negative number");

    $prodStmt = mysqli_prepare($db_conn, "SELECT id FROM products WHERE temp_id = ? AND deleted_at IS NULL LIMIT 1");
    mysqli_stmt_bind_param($prodStmt, 's', $sku);
    mysqli_stmt_execute($prodStmt);
    $prod = mysqli_stmt_get_result($prodStmt)->fetch_assoc();
    mysqli_stmt_close($prodStmt);
    if (!$prod) wa_po_fail(400, "items[$i].sku \"$sku\" not found");

    $qty = (int)$qty;
    $price = (float)$price;
    $amount = round($qty * $price, 2);
    $computedTotal += $amount;

    $resolvedItems[] = ['product_id' => (int)$prod['id'], 'qty' => $qty, 'price' => $price, 'amount' => $amount];
}
$computedTotal = round($computedTotal, 2);

if (abs($computedTotal - round((float)$totalAmount, 2)) > 0.01) {
    wa_po_fail(400, 'total_amount does not match the sum of items (expected ' . $computedTotal . ')');
}

// Re-check balance server-side (never trust the agent's earlier validate-cart call alone).
$balStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(balance_amount), 0) AS bal FROM wa_po_advance_payments
     WHERE user_category = ? AND user_id = ? AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL"
);
mysqli_stmt_bind_param($balStmt, 'si', $category, $userId);
mysqli_stmt_execute($balStmt);
$balance = (float)(mysqli_stmt_get_result($balStmt)->fetch_assoc()['bal'] ?? 0);
mysqli_stmt_close($balStmt);

$reservedStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(poi.total - po.excess_amount), 0) AS reserved
     FROM wa_po_purchase_orders po
     JOIN (SELECT po_id, SUM(amount) AS total FROM wa_po_purchase_order_items GROUP BY po_id) poi ON poi.po_id = po.id
     WHERE po.user_category = ? AND po.user_id = ? AND po.status = 'waiting'"
);
mysqli_stmt_bind_param($reservedStmt, 'si', $category, $userId);
mysqli_stmt_execute($reservedStmt);
$reserved = (float)(mysqli_stmt_get_result($reservedStmt)->fetch_assoc()['reserved'] ?? 0);
mysqli_stmt_close($reservedStmt);

$availableBalance = max(0, round($balance - $reserved, 2));
if ($computedTotal > $availableBalance) {
    wa_po_fail(409, 'Balance insufficient for this order — use /payment/submit-proof instead.');
}

date_default_timezone_set('Asia/Kolkata');
$orderDate = date('Y-m-d');

mysqli_begin_transaction($db_conn);
try {
    $insPo = mysqli_prepare($db_conn,
        "INSERT INTO wa_po_purchase_orders (user_category, user_id, order_date, status, excess_amount, idempotency_key, source)
         VALUES (?, ?, ?, 'waiting', 0, ?, ?)"
    );
    mysqli_stmt_bind_param($insPo, 'sisss', $category, $userId, $orderDate, $idempotencyKey, $source);
    mysqli_stmt_execute($insPo);
    $poId = mysqli_insert_id($db_conn);
    mysqli_stmt_close($insPo);

    $insItem = mysqli_prepare($db_conn,
        "INSERT INTO wa_po_purchase_order_items (po_id, product_id, qty, price, discount_percentage, discount_amount, amount)
         VALUES (?, ?, ?, ?, 0, 0, ?)"
    );
    foreach ($resolvedItems as $ri) {
        mysqli_stmt_bind_param($insItem, 'iiidd', $poId, $ri['product_id'], $ri['qty'], $ri['price'], $ri['amount']);
        mysqli_stmt_execute($insItem);
    }
    mysqli_stmt_close($insItem);

    mysqli_commit($db_conn);
} catch (\Throwable $e) {
    mysqli_rollback($db_conn);
    wa_po_fail(500, 'Could not create purchase order — please try again.');
}

$createdAt = date('Y-m-d H:i:s');

echo json_encode([
    'po_number' => wa_po_format_po_number($poId, $createdAt),
    'status' => 'created',
    'created_at' => date('c'),
]);

/**
 * PO number display format "PO-{year}-{month}-{4-digit-padded-id}" — this
 * codebase has no pre-existing PO numbering scheme (tp_purchase_orders is
 * only ever referenced by its raw id internally), so this format simply
 * matches the spec's own example (PO-2026-08-0142) using the row's
 * creation year/month and auto-increment id.
 */
function wa_po_format_po_number($id, $createdAt) {
    $ts = is_numeric($createdAt) ? (int)$createdAt : strtotime($createdAt);
    return 'PO-' . date('Y', $ts) . '-' . date('m', $ts) . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}
