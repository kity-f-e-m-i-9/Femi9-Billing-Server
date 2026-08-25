<?php
/**
 * POST /auth/verify-user
 * Spec §1.1 — looks up a WhatsApp number against every one of the 8
 * central-login category tables (plus wa_po_linked_numbers for numbers
 * explicitly linked via the OTP fallback flow) and returns exact /
 * multiple_accounts / not_found. Also surfaces last_used_account_id
 * (§1.1c) when multiple accounts exist.
 */
require_once __DIR__ . '/_bootstrap.php';

$waNumber = $input['wa_number'] ?? null;
if (!$waNumber || !is_string($waNumber) || trim($waNumber) === '') {
    wa_po_fail(400, 'wa_number is required');
}

if (!wa_po_rate_limit_check($db_conn, 'verify-user:' . wa_po_normalize_phone($waNumber), 30, 3600)) {
    wa_po_fail(429, 'Too many requests — please try again later');
}

$normalized = wa_po_normalize_phone($waNumber);
if (strlen($normalized) !== 10) {
    wa_po_fail(400, 'wa_number is not a valid phone number');
}

$configs = wa_po_category_configs();
$accounts = [];

foreach ($configs as $category => $cfg) {
    $table = $cfg['table'];
    $idField = $cfg['id_field'];
    $mobileField = $cfg['mobile_field'];
    $statusField = $cfg['status_field'];
    $nameField = $cfg['name_field'];
    $loginIdField = $cfg['login_id_field'];

    // deleted_at is present on most category tables (verified via SHOW
    // COLUMNS) but not all (channel_partners/territory_partners/
    // marketing_staff have no deleted_at column) — check dynamically per
    // table rather than assuming.
    $hasDeletedAt = in_array($category, ['distributor', 'super_distributor', 'stockiest', 'super_stockiest', 'candf'], true);
    $deletedClause = $hasDeletedAt ? "AND deleted_at IS NULL" : "";

    $sql = "SELECT `$idField` AS user_id, `$loginIdField` AS login_id, `$nameField` AS name, `$statusField` AS status, `$mobileField` AS mobile
            FROM `$table` WHERE `$mobileField` = ? $deletedClause";
    // Exact match on the field as stored is tried first (fast, uses any
    // index on mobile_field); if that yields nothing we fall back to a
    // normalized last-10-digit scan since some tables store +91/leading
    // zeros inconsistently.
    $stmt = mysqli_prepare($db_conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $waNumber);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    mysqli_stmt_close($stmt);

    if (empty($rows)) {
        // Fallback: normalized scan (strip non-digits, compare last 10).
        $sqlAll = "SELECT `$idField` AS user_id, `$loginIdField` AS login_id, `$nameField` AS name, `$statusField` AS status, `$mobileField` AS mobile
                   FROM `$table` WHERE `$mobileField` IS NOT NULL AND `$mobileField` != '' $deletedClause";
        $resAll = mysqli_query($db_conn, $sqlAll);
        while ($row = $resAll->fetch_assoc()) {
            if (wa_po_normalize_phone($row['mobile']) === $normalized) {
                $rows[] = $row;
            }
        }
    }

    foreach ($rows as $row) {
        $isActive = ((string)$row['status'] === (string)$cfg['status_active_value']);
        $accounts[] = [
            'user_id' => (int)$row['user_id'],
            'tp_login_id' => (string)$row['login_id'],
            'name' => (string)$row['name'],
            'category' => $category,
            'status' => $isActive ? 'active' : 'inactive',
        ];
    }
}

// Also consider numbers explicitly linked via /auth/link-number (spec
// §1.1b) — these resolve the same way a directly-registered number does.
$linkStmt = mysqli_prepare($db_conn, "SELECT user_category, user_id FROM wa_po_linked_numbers WHERE wa_number = ?");
mysqli_stmt_bind_param($linkStmt, 's', $waNumber);
mysqli_stmt_execute($linkStmt);
$linkRes = mysqli_stmt_get_result($linkStmt);
$linked = [];
while ($row = $linkRes->fetch_assoc()) $linked[] = $row;
mysqli_stmt_close($linkStmt);

foreach ($linked as $link) {
    $cfg = $configs[$link['user_category']] ?? null;
    if (!$cfg) continue;
    // Skip if already present from the direct-mobile-match scan above.
    $already = false;
    foreach ($accounts as $a) {
        if ($a['category'] === $link['user_category'] && $a['user_id'] === (int)$link['user_id']) { $already = true; break; }
    }
    if ($already) continue;

    $sql = "SELECT `{$cfg['id_field']}` AS user_id, `{$cfg['login_id_field']}` AS login_id, `{$cfg['name_field']}` AS name, `{$cfg['status_field']}` AS status
            FROM `{$cfg['table']}` WHERE `{$cfg['id_field']}` = ?";
    $stmt = mysqli_prepare($db_conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $link['user_id']);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    if (!$row) continue;

    $isActive = ((string)$row['status'] === (string)$cfg['status_active_value']);
    $accounts[] = [
        'user_id' => (int)$row['user_id'],
        'tp_login_id' => (string)$row['login_id'],
        'name' => (string)$row['name'],
        'category' => $link['user_category'],
        'status' => $isActive ? 'active' : 'inactive',
    ];
}

if (empty($accounts)) {
    wa_po_log_event('not_found');
    echo json_encode(['match_type' => 'not_found', 'accounts' => []]);
    exit;
}

if (count($accounts) === 1) {
    wa_po_log_event('exact (' . $accounts[0]['category'] . ')');
    echo json_encode(['match_type' => 'exact', 'accounts' => $accounts]);
    exit;
}

// multiple_accounts — also surface last_used_account_id (spec §1.1c).
$lastUsed = null;
$lastStmt = mysqli_prepare($db_conn, "SELECT user_id FROM wa_number_last_account WHERE wa_number = ?");
mysqli_stmt_bind_param($lastStmt, 's', $waNumber);
mysqli_stmt_execute($lastStmt);
$lastRow = mysqli_stmt_get_result($lastStmt)->fetch_assoc();
mysqli_stmt_close($lastStmt);
if ($lastRow) {
    $lastUsed = (int)$lastRow['user_id'];
}

wa_po_log_event('multiple_accounts (' . count($accounts) . ')');
echo json_encode([
    'match_type' => 'multiple_accounts',
    'accounts' => $accounts,
    'last_used_account_id' => $lastUsed,
]);
