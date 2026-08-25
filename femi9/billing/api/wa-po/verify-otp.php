<?php
/**
 * POST /auth/verify-otp
 * Spec §1.1b — verifies the 6-digit code against wa_po_otp_attempts.
 * Max 3 attempts then the OTP is locked (must request a fresh one via
 * /auth/send-otp — spec's 15-min lock is naturally achieved since a new
 * OTP row is a separate otp_id and the old one just stays permanently
 * exhausted/expired). On success, creates a session_token.
 */
require_once __DIR__ . '/_bootstrap.php';

$otpId = trim((string)($input['otp_id'] ?? ''));
$code = trim((string)($input['code'] ?? ''));
$waNumber = trim((string)($input['wa_number'] ?? ''));

if ($otpId === '') wa_po_fail(400, 'otp_id is required');
if ($code === '') wa_po_fail(400, 'code is required');
if ($waNumber === '') wa_po_fail(400, 'wa_number is required');

$stmt = mysqli_prepare($db_conn,
    "SELECT id, user_category, user_id, otp_code_hash, expires_at, attempts_used, max_attempts, verified
     FROM wa_po_otp_attempts WHERE otp_id = ?"
);
mysqli_stmt_bind_param($stmt, 's', $otpId);
mysqli_stmt_execute($stmt);
$row = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$row) {
    wa_po_log_event('invalid_otp_id');
    echo json_encode(['verified' => false, 'reason' => 'invalid_otp_id']);
    exit;
}

if ((int)$row['verified'] === 1) {
    wa_po_log_event('already_used');
    echo json_encode(['verified' => false, 'reason' => 'already_used']);
    exit;
}

if ((int)$row['attempts_used'] >= (int)$row['max_attempts']) {
    wa_po_log_event('locked');
    echo json_encode(['verified' => false, 'reason' => 'locked', 'message' => 'Maximum attempts reached — please contact support.']);
    exit;
}

if (strtotime($row['expires_at']) < time()) {
    wa_po_log_event('expired');
    echo json_encode(['verified' => false, 'reason' => 'expired']);
    exit;
}

$codeHash = hash('sha256', $code);
if (!hash_equals($row['otp_code_hash'], $codeHash)) {
    $upd = mysqli_prepare($db_conn, "UPDATE wa_po_otp_attempts SET attempts_used = attempts_used + 1 WHERE id = ?");
    mysqli_stmt_bind_param($upd, 'i', $row['id']);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    $remaining = (int)$row['max_attempts'] - ((int)$row['attempts_used'] + 1);
    wa_po_log_event('incorrect_code (remaining ' . max(0, $remaining) . ')');
    echo json_encode(['verified' => false, 'reason' => 'incorrect_code', 'attempts_remaining' => max(0, $remaining)]);
    exit;
}

// Correct code.
$upd = mysqli_prepare($db_conn, "UPDATE wa_po_otp_attempts SET verified = 1 WHERE id = ?");
mysqli_stmt_bind_param($upd, 'i', $row['id']);
mysqli_stmt_execute($upd);
mysqli_stmt_close($upd);

$category = $row['user_category'];
$userId = (int)$row['user_id'];

$sessionToken = wa_po_create_session($db_conn, $waNumber, $category, $userId);

wa_po_log_event('verified, session created (' . $category . ' user_id ' . $userId . ')');
echo json_encode([
    'verified' => true,
    'session_token' => $sessionToken,
    'user_id' => $userId,
    'category' => $category,
]);
