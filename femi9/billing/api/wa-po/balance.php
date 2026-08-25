<?php
/**
 * GET /balance/advance?session_token=...
 * Spec §1.2 — advance payment balance for the session's bound account.
 * Sums wa_po_advance_payments.balance_amount for this user_category+user_id
 * (mirrors tp_advance_payments' balance logic in add-purchase-order.php).
 */
require_once __DIR__ . '/_bootstrap.php';

$session = wa_po_require_session($db_conn, $input);
$category = $session['user_category'];
$userId = $session['user_id'];

$stmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(balance_amount), 0) AS bal
     FROM wa_po_advance_payments
     WHERE user_category = ? AND user_id = ? AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL"
);
mysqli_stmt_bind_param($stmt, 'si', $category, $userId);
mysqli_stmt_execute($stmt);
$balance = (float)(mysqli_stmt_get_result($stmt)->fetch_assoc()['bal'] ?? 0);
mysqli_stmt_close($stmt);

// Subtract amounts already reserved by "waiting" POs not yet fulfilled,
// same reservation logic as add-purchase-order.php's $reservedAmount, so
// the agent doesn't quote a balance that's already spoken for by a pending
// order.
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

$balance = max(0, round($balance - $reserved, 2));

date_default_timezone_set('Asia/Kolkata');

wa_po_log_event('balance queried (' . $category . ' user_id ' . $userId . ', ' . $balance . ')');
echo json_encode([
    'user_id' => $userId,
    'advance_balance' => $balance,
    'currency' => 'INR',
    'as_of' => date('c'),
]);
