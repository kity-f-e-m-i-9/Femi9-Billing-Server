<?php
/**
 * POST /po/validate-cart
 * Spec §1.4 — computes cart total and checks it against the session
 * account's available advance balance (same reservation-aware balance
 * calc as balance.php).
 */
require_once __DIR__ . '/_bootstrap.php';

$session = wa_po_require_session($db_conn, $input);
$category = $session['user_category'];
$userId = $session['user_id'];

$items = $input['items'] ?? null;
if (!is_array($items) || count($items) === 0) {
    wa_po_fail(400, 'items is required and must be a non-empty array');
}

$totalAmount = 0.0;
foreach ($items as $i => $item) {
    if (!isset($item['qty']) || !is_numeric($item['qty']) || (int)$item['qty'] <= 0) {
        wa_po_fail(400, "items[$i].qty is required and must be a positive number");
    }
    if (!isset($item['price']) || !is_numeric($item['price']) || (float)$item['price'] < 0) {
        wa_po_fail(400, "items[$i].price is required and must be a non-negative number");
    }
    $totalAmount += (float)$item['price'] * (int)$item['qty'];
}
$totalAmount = round($totalAmount, 2);

// Available balance (reservation-aware, same calc as balance.php).
$stmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(balance_amount), 0) AS bal
     FROM wa_po_advance_payments
     WHERE user_category = ? AND user_id = ? AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL"
);
mysqli_stmt_bind_param($stmt, 'si', $category, $userId);
mysqli_stmt_execute($stmt);
$balance = (float)(mysqli_stmt_get_result($stmt)->fetch_assoc()['bal'] ?? 0);
mysqli_stmt_close($stmt);

$reservedStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(poi.total - po.excess_amount), 0) AS reserved
     FROM wa_po_purchase_orders po
     JOIN (SELECT po_id, SUM(amount) AS total FROM wa_po_purchase_order_items GROUP BY po_id) poi
       ON poi.po_id = po.id
     WHERE po.user_category = ? AND po.user_id = ? AND po.status = 'waiting'"
);
mysqli_stmt_bind_param($reservedStmt, 'si', $category, $userId);
mysqli_stmt_execute($reservedStmt);
$reserved = (float)(mysqli_stmt_get_result($reservedStmt)->fetch_assoc()['reserved'] ?? 0);
mysqli_stmt_close($reservedStmt);

$availableBalance = max(0, round($balance - $reserved, 2));

$withinBalance = $totalAmount <= $availableBalance;
$balanceAfter = $withinBalance ? round($availableBalance - $totalAmount, 2) : $availableBalance;

wa_po_log_event('cart validated (' . $category . ' user_id ' . $userId . ', total ' . $totalAmount . ', within_balance=' . ($withinBalance ? 'yes' : 'no') . ')');
echo json_encode([
    'total_amount' => $totalAmount,
    'within_balance' => $withinBalance,
    'balance_after' => $balanceAfter,
]);
