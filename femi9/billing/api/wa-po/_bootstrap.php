<?php
/**
 * Shared bootstrap for every WhatsApp Purchase Order automation endpoint
 * (femi9-whatsapp-po-api-spec.md §3/§4). Included first by every file in
 * this folder. Responsible for:
 *   1. Bearer API-key check (Wati agent -> us)
 *   2. HMAC-SHA256 X-Signature check on the raw request body
 *   3. Decoding the JSON body into $input
 *   4. A shared DB connection ($db_conn), same credentials/pattern every
 *      other category folder's include/db-connect.php uses
 *   5. wa_po_fail() — standard JSON error responder
 *   6. wa_po_rate_limit_check() — generic MySQL-backed rate limiter
 *   7. wa_po_require_session() — session_token lookup/refresh helper used
 *      by every endpoint downstream of auth
 *
 * This is a genuinely separate, parallel subsystem from the existing
 * territory-partner PO/advance-payment screens — it does not touch those
 * files or tables.
 */

require_once __DIR__ . '/../../config/wa_po_secrets.php';
require_once __DIR__ . '/../../shared/env-loader.php';

header('Content-Type: application/json');

function wa_po_fail($code, $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

// --- DB connection -------------------------------------------------------
$db_conn = mysqli_connect(
    $_ENV['DB_HOST'] ?? 'localhost',
    $_ENV['DB_USERNAME'] ?? null,
    $_ENV['DB_PASSWORD'] ?? null,
    $_ENV['DB_NAME'] ?? null,
    (int)($_ENV['DB_PORT'] ?? 3306)
);
if (!$db_conn) {
    wa_po_fail(500, 'Database connection failed');
}

// --- 1. Bearer token check ------------------------------------------------
$headers = function_exists('getallheaders') ? getallheaders() : [];
// getallheaders() header casing can vary by SAPI; normalize to a
// case-insensitive lookup so "authorization" vs "Authorization" both work.
$normalizedHeaders = [];
foreach ($headers as $k => $v) {
    $normalizedHeaders[strtolower($k)] = $v;
}

$authHeader = $normalizedHeaders['authorization'] ?? '';
if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m) || !hash_equals(WA_PO_API_KEY, $m[1])) {
    wa_po_fail(401, 'Invalid or missing API key');
}

// --- 2. HMAC signature check on raw body ----------------------------------
$rawBody = file_get_contents('php://input');
$expectedSig = hash_hmac('sha256', $rawBody, WA_PO_WEBHOOK_SECRET);
$givenSig = $normalizedHeaders['x-signature'] ?? '';
if ($givenSig === '' || !hash_equals($expectedSig, $givenSig)) {
    wa_po_fail(401, 'Signature mismatch');
}

// --- 3. Decode JSON body ---------------------------------------------------
// GET endpoints (balance.php, payment-verify-status.php) legitimately have
// an empty body — HMAC above still signs that empty string, and $input
// simply ends up as [] so those endpoints read identity from $_GET instead.
$decoded = $rawBody !== '' ? json_decode($rawBody, true) : [];
$input = is_array($decoded) ? $decoded : [];

// --- 6. Generic rate limiter ------------------------------------------------
/**
 * Sliding-window-ish (fixed window, keyed by floor(now/windowSeconds))
 * MySQL-backed rate limit check. Returns true if the call is allowed (and
 * records it), false if the caller is over the limit.
 */
function wa_po_rate_limit_check($db_conn, $key, $maxPerWindow, $windowSeconds) {
    $now = time();
    $windowStartTs = $now - ($now % $windowSeconds);
    $windowStart = date('Y-m-d H:i:s', $windowStartTs);

    $stmt = mysqli_prepare($db_conn,
        "SELECT id, count FROM wa_po_rate_limits WHERE rate_key = ? AND window_start = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $key, $windowStart);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) {
        $ins = mysqli_prepare($db_conn,
            "INSERT INTO wa_po_rate_limits (rate_key, window_start, count) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE count = count + 1"
        );
        mysqli_stmt_bind_param($ins, 'ss', $key, $windowStart);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
        return true;
    }

    if ((int)$row['count'] >= $maxPerWindow) {
        return false;
    }

    $upd = mysqli_prepare($db_conn, "UPDATE wa_po_rate_limits SET count = count + 1 WHERE id = ?");
    mysqli_stmt_bind_param($upd, 'i', $row['id']);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);
    return true;
}

// --- 7. Session resolution ---------------------------------------------------
/**
 * Looks up session_token (from $input, or $_GET for GET-style endpoints),
 * rejects with 401 if missing/expired, refreshes expires_at on valid use
 * (sliding 30-60 min window), and returns the session's bound identity.
 * This identity is authoritative — callers must use its user_category and
 * user_id, never a value passed separately in the request body, so one WA
 * conversation can never act as a different account mid-conversation.
 *
 * @return array{session_id:int, user_category:string, user_id:int, wa_number:string, conversation_id:?string}
 */
function wa_po_require_session($db_conn, array $input) {
    $token = $input['session_token'] ?? ($_GET['session_token'] ?? null);
    if (!$token || !is_string($token)) {
        wa_po_fail(401, 'session_token is required');
    }

    $stmt = mysqli_prepare($db_conn,
        "SELECT id, wa_number, user_category, user_id, conversation_id, expires_at
         FROM wa_po_sessions WHERE session_token = ?"
    );
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $session = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$session) {
        wa_po_fail(401, 'Invalid session_token');
    }
    if (strtotime($session['expires_at']) < time()) {
        wa_po_fail(401, 'Session expired — please verify again');
    }

    // Sliding window: refresh expires_at 45 minutes from now on every valid use.
    $newExpiry = date('Y-m-d H:i:s', time() + 45 * 60);
    $upd = mysqli_prepare($db_conn, "UPDATE wa_po_sessions SET expires_at = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, 'si', $newExpiry, $session['id']);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    return [
        'session_id' => (int)$session['id'],
        'user_category' => $session['user_category'],
        'user_id' => (int)$session['user_id'],
        'wa_number' => $session['wa_number'],
        'conversation_id' => $session['conversation_id'],
    ];
}

/**
 * Generates a cryptographically random session token and inserts a new
 * wa_po_sessions row, returning the token. TTL defaults to 45 minutes
 * (within the spec's 30-60 min suggested range).
 */
function wa_po_create_session($db_conn, $waNumber, $userCategory, $userId, $conversationId = null, $ttlSeconds = 2700) {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

    $stmt = mysqli_prepare($db_conn,
        "INSERT INTO wa_po_sessions (session_token, wa_number, user_category, user_id, conversation_id, expires_at)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'ssssss', $token, $waNumber, $userCategory, $userId, $conversationId, $expiresAt);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $token;
}

/**
 * Category config used across every wa-po endpoint — re-derived from
 * shared/user-config.php's getUserConfig()/getCentralLoginTypes() (the
 * authoritative source), not hardcoded independently. Kept local to this
 * bootstrap (rather than including user-config.php directly) because that
 * file's getUserConfig() also returns 'company'/'salesbdm' entries which
 * are explicitly NOT part of the 8 central-login categories this
 * WhatsApp subsystem serves.
 *
 * id_field intentionally matches each category's own $_SESSION['LOGIN_USER_ID']
 * convention used throughout the rest of the app (verified in each
 * category's checksession.php): distributor/super_distributor/stockiest/
 * super_stockiest/candf key off the string `temp_id`, NOT the numeric `id`
 * PK — channel_partner/territory_partner/marketing key off their numeric
 * `id` PK. wa_po_* tables store user_id as INT UNSIGNED, so for the
 * temp_id-keyed categories we store/compare the numeric `id` column
 * instead (still unique per row) while login_id_field carries the
 * human-facing temp_id/cp_id/tp_id string for display purposes only.
 */
function wa_po_category_configs() {
    return [
        'distributor' => [
            'table' => 'distributor', 'id_field' => 'id', 'mobile_field' => 'mobile_number',
            'status_field' => 'account_status', 'status_active_value' => 'active', 'name_field' => 'name',
            'login_id_field' => 'temp_id',
        ],
        'super_distributor' => [
            'table' => 'super_distributor', 'id_field' => 'id', 'mobile_field' => 'mobile_number',
            'status_field' => 'account_status', 'status_active_value' => 'active', 'name_field' => 'name',
            'login_id_field' => 'temp_id',
        ],
        'stockiest' => [
            'table' => 'stockiest', 'id_field' => 'id', 'mobile_field' => 'mobile_number',
            'status_field' => 'account_status', 'status_active_value' => 'active', 'name_field' => 'name',
            'login_id_field' => 'temp_id',
        ],
        'super_stockiest' => [
            'table' => 'super_stockiest', 'id_field' => 'id', 'mobile_field' => 'mobile_number',
            'status_field' => 'account_status', 'status_active_value' => 'active', 'name_field' => 'name',
            'login_id_field' => 'temp_id',
        ],
        'channel_partner' => [
            'table' => 'channel_partners', 'id_field' => 'id', 'mobile_field' => 'mobile',
            'status_field' => 'is_active', 'status_active_value' => '1', 'name_field' => 'name',
            'login_id_field' => 'cp_id',
        ],
        'candf' => [
            'table' => 'c_and_f', 'id_field' => 'id', 'mobile_field' => 'username',
            'status_field' => 'account_status', 'status_active_value' => 'active', 'name_field' => 'name',
            'login_id_field' => 'temp_id',
        ],
        'marketing' => [
            'table' => 'marketing_staff', 'id_field' => 'id', 'mobile_field' => 'ms_mobile',
            'status_field' => 'account_status', 'status_active_value' => 'active', 'name_field' => 'ms_name',
            'login_id_field' => 'id',
        ],
        'territory_partner' => [
            'table' => 'territory_partners', 'id_field' => 'id', 'mobile_field' => 'mobile',
            'status_field' => 'is_active', 'status_active_value' => '1', 'name_field' => 'name',
            'login_id_field' => 'tp_id',
        ],
    ];
}

/**
 * Normalizes a phone number for comparison: strips everything but digits,
 * then keeps the last 10 — since different tables store numbers with or
 * without +91 / country code / leading zero.
 */
function wa_po_normalize_phone($raw) {
    $digits = preg_replace('/\D+/', '', (string)$raw);
    return substr($digits, -10);
}
