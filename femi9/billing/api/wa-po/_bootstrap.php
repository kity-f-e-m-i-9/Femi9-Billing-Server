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

// --- Request/response logging ---------------------------------------------
// One line per call, written to logs/wa_po_api.log, covering every endpoint
// automatically (including PHP fatal errors and every wa_po_fail() exit) —
// hooked at shutdown rather than scattered through each of the 13 endpoint
// files individually. Mirrors the existing login_attempts.log line format
// used elsewhere in this app.
$GLOBALS['wa_po_log_endpoint'] = basename($_SERVER['SCRIPT_NAME'] ?? 'unknown');
$GLOBALS['wa_po_log_start'] = microtime(true);
$GLOBALS['wa_po_log_event'] = null;

/**
 * Lets an endpoint attach a short human-readable outcome/event string to the
 * log line (e.g. "multiple_accounts (2)", "PO created", "OTP sent") —
 * analogous to login_attempts.log's "Reason: ..." field. Purely additive;
 * logging still happens even if no endpoint ever calls this.
 */
function wa_po_log_event($event) {
    $GLOBALS['wa_po_log_event'] = (string)$event;
}

function wa_po_write_log($statusCode, $responseBody) {
    $endpoint = $GLOBALS['wa_po_log_endpoint'] ?? 'unknown';
    $durationMs = round((microtime(true) - ($GLOBALS['wa_po_log_start'] ?? microtime(true))) * 1000);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '-';

    // Identify the caller from whatever the request body happens to carry,
    // without assuming a fixed shape (endpoints take different identifiers).
    $input = $GLOBALS['input'] ?? [];
    $identifier = $input['wa_number'] ?? $input['identifier'] ?? $input['session_token'] ?? null;
    if ($identifier === null && isset($input['user_id'])) {
        $identifier = 'user_id:' . $input['user_id'];
    }
    $identifier = $identifier ? (is_string($identifier) && strlen($identifier) > 40 ? substr($identifier, 0, 12) . '...' : $identifier) : '-';

    $event = $GLOBALS['wa_po_log_event'];
    if ($event === null) {
        // Fall back to a short excerpt of the response so failures without
        // an explicit wa_po_log_event() call are still informative.
        $bodyStr = is_string($responseBody) ? $responseBody : json_encode($responseBody);
        $event = $bodyStr !== false ? substr($bodyStr, 0, 200) : '-';
    }

    $line = sprintf(
        "[%s] %s | Status: %d | Identifier: %s | IP: %s | Duration: %dms | Event: %s\n",
        date('Y-m-d H:i:s'),
        $endpoint,
        $statusCode,
        $identifier,
        $ip,
        $durationMs,
        $event
    );

    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logDir . '/wa_po_api.log', $line, FILE_APPEND | LOCK_EX);
}

register_shutdown_function(function () {
    $statusCode = http_response_code();
    $statusCode = $statusCode !== false ? $statusCode : 200;

    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        wa_po_write_log(500, 'PHP fatal: ' . $error['message'] . ' @ ' . $error['file'] . ':' . $error['line']);
        return;
    }

    wa_po_write_log($statusCode, null);
});

function wa_po_fail($code, $msg) {
    http_response_code($code);
    wa_po_log_event('FAIL: ' . $msg);
    echo json_encode(['error' => $msg]);
    exit;
}

/**
 * Writes a one-off diagnostic line to logs/wa_po_auth_debug.log capturing
 * exactly what the caller sent for the two auth checks (headers present,
 * a masked/truncated view of the Authorization value, and the raw body),
 * so an "Invalid or missing API key" / "Signature mismatch" failure in
 * wa_po_api.log can be cross-referenced against what was actually received
 * — without ever writing the real secrets or full token to disk.
 *
 * Kept separate from wa_po_api.log (which stays a compact one-liner) since
 * this is verbose and only needed while chasing an auth failure.
 */
function wa_po_log_auth_debug($reason, array $normalizedHeaders, $rawBody) {
    $mask = function ($v, $keep = 6) {
        if (!is_string($v) || $v === '') return '(empty)';
        $len = strlen($v);
        return $len <= $keep ? str_repeat('*', $len) : substr($v, 0, $keep) . '...(' . $len . ' chars)';
    };

    $authHeader = $normalizedHeaders['authorization'] ?? null;
    $sigHeader = $normalizedHeaders['x-signature'] ?? null;

    $entry = [
        'time' => date('Y-m-d H:i:s'),
        'endpoint' => $GLOBALS['wa_po_log_endpoint'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '-',
        'reason' => $reason,
        'method' => $_SERVER['REQUEST_METHOD'] ?? '-',
        'headers_received' => array_keys($normalizedHeaders),
        'authorization_present' => $authHeader !== null,
        'authorization_masked' => $authHeader !== null ? $mask($authHeader) : null,
        'x_signature_present' => $sigHeader !== null,
        'x_signature_masked' => $sigHeader !== null ? $mask($sigHeader, 8) : null,
        'content_type' => $normalizedHeaders['content-type'] ?? null,
        'body_length' => is_string($rawBody) ? strlen($rawBody) : 0,
        'body_preview' => is_string($rawBody) ? substr($rawBody, 0, 500) : null,
    ];

    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents(
        $logDir . '/wa_po_auth_debug.log',
        json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n",
        FILE_APPEND | LOCK_EX
    );
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

// --- 1. API key check -------------------------------------------------------
$headers = function_exists('getallheaders') ? getallheaders() : [];
// getallheaders() header casing can vary by SAPI; normalize to a
// case-insensitive lookup so "authorization" vs "Authorization" both work.
$normalizedHeaders = [];
foreach ($headers as $k => $v) {
    $normalizedHeaders[strtolower($k)] = $v;
}

// Read the key from X-Api-Key rather than Authorization: Bearer. The
// caller is n8n's HTTP Request tool nodes, and n8n treats "Authorization"
// as a reserved header tied to its own node-level Authentication setting —
// a manually-added "Authorization" Header Parameter gets silently dropped
// from the actual outgoing request (confirmed via wa_po_auth_debug.log:
// X-Signature arrived fine, Authorization never did). X-Api-Key is a
// plain, non-reserved header name n8n sends as configured.
$apiKeyHeader = $normalizedHeaders['x-api-key'] ?? '';
if (!hash_equals(WA_PO_API_KEY, $apiKeyHeader)) {
    wa_po_log_auth_debug('Invalid or missing API key', $normalizedHeaders, file_get_contents('php://input'));
    wa_po_fail(401, 'Invalid or missing API key');
}

// --- 2. HMAC signature check (static, NOT over the raw body) --------------
// Originally this hashed the raw request body, but the caller here is an
// n8n AI Agent's tool nodes — each of the 13 tool nodes is invoked directly
// by the LLM with its own dynamically-decided arguments, so there is no
// single upstream step in the graph that can see (and sign) each call's
// exact outgoing body before it's sent. That makes a body-bound signature
// impractical to produce from n8n's agent-tool architecture.
//
// Instead X-Signature is now a fixed value — HMAC-SHA256(WA_PO_API_KEY,
// WA_PO_WEBHOOK_SECRET) — the same string on every call, computed once and
// pasted as a static header value into each tool node (same way the
// Authorization header is a static pasted value). This keeps two
// independent secrets required to call in (defense-in-depth / key
// rotation isolation) without needing per-call signing. It intentionally
// gives up replay/tamper protection on the body itself, which isn't
// enforceable from this caller anyway.
$rawBody = file_get_contents('php://input');
$expectedSig = hash_hmac('sha256', WA_PO_API_KEY, WA_PO_WEBHOOK_SECRET);
$givenSig = $normalizedHeaders['x-signature'] ?? '';
if ($givenSig === '' || !hash_equals($expectedSig, $givenSig)) {
    wa_po_log_auth_debug('Signature mismatch', $normalizedHeaders, $rawBody);
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
