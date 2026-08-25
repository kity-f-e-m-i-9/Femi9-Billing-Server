<?php
/**
 * POST /po/finalize
 * Spec §1.8 — creates the PO once a payment proof has been approved
 * (the balance-insufficient path's final step). Requires proof_id to be
 * an 'accepted' submission belonging to this session's account, and
 * idempotency_key for the same retry-safety reason as /po/create.
 */
require_once __DIR__ . '/_bootstrap.php';

$session = wa_po_require_session($db_conn, $input);
$category = $session['user_category'];
$userId = $session['user_id'];

$proofId = trim((string)($input['proof_id'] ?? ''));
$items = $input['items'] ?? null;
$totalAmount = $input['total_amount'] ?? null;
$idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));

if ($proofId === '') wa_po_fail(400, 'proof_id is required');
if (!is_array($items) || count($items) === 0) wa_po_fail(400, 'items is required and must be a non-empty array');
if (!is_numeric($totalAmount)) wa_po_fail(400, 'total_amount is required');
if ($idempotencyKey === '') wa_po_fail(400, 'idempotency_key is required');

if (!preg_match('/^PRF-(\d+)$/', $proofId, $m)) {
    wa_po_fail(400, 'proof_id is not a valid format');
}
$submissionId = (int)$m[1];

// Idempotency check first (mirrors po-create.php).
$existingStmt = mysqli_prepare($db_conn, "SELECT id, created_at FROM wa_po_purchase_orders WHERE idempotency_key = ?");
mysqli_stmt_bind_param($existingStmt, 's', $idempotencyKey);
mysqli_stmt_execute($existingStmt);
$existing = mysqli_stmt_get_result($existingStmt)->fetch_assoc();
mysqli_stmt_close($existingStmt);

if ($existing) {
    wa_po_log_event('idempotent replay (PO ' . $existing['id'] . ')');
    echo json_encode([
        'po_number' => wa_po_format_po_number($existing['id'], $existing['created_at']),
        'status' => 'confirmed',
    ]);
    exit;
}

// Verify the submission belongs to this account and is accepted.
$subStmt = mysqli_prepare($db_conn,
    "SELECT id, user_category, user_id, amount, status, used_for_po_id
     FROM wa_po_advance_payment_submissions WHERE id = ?"
);
mysqli_stmt_bind_param($subStmt, 'i', $submissionId);
mysqli_stmt_execute($subStmt);
$submission = mysqli_stmt_get_result($subStmt)->fetch_assoc();
mysqli_stmt_close($subStmt);

if (!$submission) wa_po_fail(404, 'proof_id not found');
if ($submission['user_category'] !== $category || (int)$submission['user_id'] !== $userId) {
    wa_po_fail(403, 'This proof_id does not belong to the authenticated session');
}
if ($submission['status'] !== 'accepted') {
    wa_po_fail(409, 'Payment proof has not been approved yet — current status is ' . $submission['status']);
}
if ($submission['used_for_po_id'] !== null) {
    wa_po_fail(409, 'This payment proof has already been used for another order.');
}

// Resolve + total-check items (same logic as po-create.php).
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

$excessAmount = max(0, round($computedTotal - (float)$submission['amount'], 2));

date_default_timezone_set('Asia/Kolkata');
$orderDate = date('Y-m-d');

mysqli_begin_transaction($db_conn);
try {
    $insPo = mysqli_prepare($db_conn,
        "INSERT INTO wa_po_purchase_orders (user_category, user_id, order_date, status, excess_amount, idempotency_key, source)
         VALUES (?, ?, ?, 'waiting', ?, ?, 'whatsapp')"
    );
    mysqli_stmt_bind_param($insPo, 'sisds', $category, $userId, $orderDate, $excessAmount, $idempotencyKey);
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

    $updSub = mysqli_prepare($db_conn, "UPDATE wa_po_advance_payment_submissions SET used_for_po_id = ? WHERE id = ?");
    mysqli_stmt_bind_param($updSub, 'ii', $poId, $submissionId);
    mysqli_stmt_execute($updSub);
    mysqli_stmt_close($updSub);

    mysqli_commit($db_conn);
} catch (\Throwable $e) {
    mysqli_rollback($db_conn);
    wa_po_fail(500, 'Could not finalize purchase order — please try again.');
}

wa_po_log_event('PO finalized (id ' . $poId . ', ' . $category . ' user_id ' . $userId . ', proof ' . $proofId . ', amount ' . $computedTotal . ')');
echo json_encode([
    'po_number' => wa_po_format_po_number($poId, date('Y-m-d H:i:s')),
    'status' => 'confirmed',
]);

function wa_po_format_po_number($id, $createdAt) {
    $ts = strtotime($createdAt);
    return 'PO-' . date('Y', $ts) . '-' . date('m', $ts) . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}
