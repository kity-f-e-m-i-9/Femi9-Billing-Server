<?php
/**
 * POST /auth/link-number
 * Spec §1.1b — appends a WhatsApp number to wa_po_linked_numbers after
 * explicit user consent post-OTP-verification, so future /auth/verify-user
 * calls from this number resolve directly (Case A/B) without repeating
 * OTP. Requires a valid session_token bound to the same user_id being
 * linked (never link an arbitrary account from an unauthenticated call).
 */
require_once __DIR__ . '/_bootstrap.php';

$session = wa_po_require_session($db_conn, $input);

$userId = $input['user_id'] ?? null;
$waNumber = trim((string)($input['wa_number'] ?? ''));

if (!is_numeric($userId)) wa_po_fail(400, 'user_id is required');
$userId = (int)$userId;
if ($waNumber === '') wa_po_fail(400, 'wa_number is required');

if ($userId !== $session['user_id']) {
    wa_po_fail(403, 'user_id does not match the authenticated session');
}

$category = $session['user_category'];

$ins = mysqli_prepare($db_conn,
    "INSERT INTO wa_po_linked_numbers (user_category, user_id, wa_number, linked_at)
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE linked_at = NOW()"
);
mysqli_stmt_bind_param($ins, 'sis', $category, $userId, $waNumber);
mysqli_stmt_execute($ins);
mysqli_stmt_close($ins);

wa_po_log_event('number linked (' . $category . ' user_id ' . $userId . ')');
echo json_encode(['linked' => true, 'user_id' => $userId, 'wa_number' => $waNumber, 'category' => $category]);
