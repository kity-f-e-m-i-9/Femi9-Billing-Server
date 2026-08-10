<?php
/**
 * POST /auth/send-otp
 * Spec §1.1b — sends a 6-digit OTP via Wati's Send Template API to the
 * number on file for the account (not necessarily the number currently
 * chatting). One of the few outbound-to-Wati calls this subsystem makes,
 * so it needs WATI_API_TOKEN + WATI_TENANT_ID. If those aren't configured
 * yet, fails gracefully with a clear 503 instead of a raw curl error.
 *
 * Guardrails: max 3 sends/hour/account (wa_po_otp_rate_limit), OTP expires
 * in 5 minutes, code stored only as a SHA-256 hash (never plaintext).
 */
require_once __DIR__ . '/_bootstrap.php';

$userId = $input['user_id'] ?? null;
$category = $input['category'] ?? null;

if (!is_numeric($userId)) wa_po_fail(400, 'user_id is required');
$userId = (int)$userId;

$configs = wa_po_category_configs();

// category is optional in the spec's example payload — if omitted, scan
// every category table for this user_id (same approach as select-account).
if ($category && isset($configs[$category])) {
    $candidateCategories = [$category];
} else {
    $candidateCategories = array_keys($configs);
}

$resolved = null;
foreach ($candidateCategories as $cat) {
    $cfg = $configs[$cat];
    $sql = "SELECT `{$cfg['id_field']}` AS user_id, `{$cfg['mobile_field']}` AS mobile, `{$cfg['status_field']}` AS status
            FROM `{$cfg['table']}` WHERE `{$cfg['id_field']}` = ?";
    $stmt = mysqli_prepare($db_conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    if ($row) {
        $resolved = ['category' => $cat, 'cfg' => $cfg, 'row' => $row];
        break;
    }
}

if (!$resolved) {
    wa_po_fail(404, 'Account not found');
}

$cat = $resolved['category'];
$registeredMobile = (string)$resolved['row']['mobile'];

// --- Guardrail: max 3 OTP sends per hour per account ----------------------
$windowStart = date('Y-m-d H:i:s', time() - (time() % 3600));
$rlStmt = mysqli_prepare($db_conn,
    "SELECT id, send_count FROM wa_po_otp_rate_limit WHERE user_category = ? AND user_id = ? AND window_start = ?"
);
mysqli_stmt_bind_param($rlStmt, 'sis', $cat, $userId, $windowStart);
mysqli_stmt_execute($rlStmt);
$rlRow = mysqli_stmt_get_result($rlStmt)->fetch_assoc();
mysqli_stmt_close($rlStmt);

if ($rlRow && (int)$rlRow['send_count'] >= 3) {
    wa_po_fail(429, 'Maximum OTP sends reached for this hour — please try again later or contact support.');
}

// --- Generate + store OTP --------------------------------------------------
$otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$otpId = 'OTP-' . bin2hex(random_bytes(6));
$otpHash = hash('sha256', $otp);
$expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes

$ins = mysqli_prepare($db_conn,
    "INSERT INTO wa_po_otp_attempts (user_category, user_id, otp_id, otp_code_hash, expires_at)
     VALUES (?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param($ins, 'sisss', $cat, $userId, $otpId, $otpHash, $expiresAt);
mysqli_stmt_execute($ins);
mysqli_stmt_close($ins);

if ($rlRow) {
    $upd = mysqli_prepare($db_conn, "UPDATE wa_po_otp_rate_limit SET send_count = send_count + 1 WHERE id = ?");
    mysqli_stmt_bind_param($upd, 'i', $rlRow['id']);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);
} else {
    $insRl = mysqli_prepare($db_conn,
        "INSERT INTO wa_po_otp_rate_limit (user_category, user_id, window_start, send_count) VALUES (?, ?, ?, 1)"
    );
    mysqli_stmt_bind_param($insRl, 'sis', $cat, $userId, $windowStart);
    mysqli_stmt_execute($insRl);
    mysqli_stmt_close($insRl);
}

// --- Send via Wati -----------------------------------------------------------
$watiToken = $_ENV['WATI_API_TOKEN'] ?? '';
$watiTenantId = $_ENV['WATI_TENANT_ID'] ?? '';

if ($watiToken === '' || $watiTenantId === '') {
    wa_po_fail(503, 'WhatsApp OTP delivery not configured — WATI_API_TOKEN/WATI_TENANT_ID are not set yet.');
}

$payload = json_encode([
    'template_name' => 'wa_po_login_otp',
    'broadcast_name' => 'wa_po_otp_' . time(),
    'parameters' => [ ['name' => 'otp', 'value' => $otp] ],
]);

$url = "https://live-mt-server.wati.io/{$watiTenantId}/api/v1/sendTemplateMessage?whatsappNumber=" . urlencode($registeredMobile);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $watiToken, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $httpCode < 200 || $httpCode >= 300) {
    wa_po_fail(502, 'Failed to send OTP via WhatsApp — please try again shortly.');
}

echo json_encode(['otp_id' => $otpId, 'expires_in' => 300]);
