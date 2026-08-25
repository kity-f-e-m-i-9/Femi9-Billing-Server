<?php
/**
 * POST /auth/select-account
 * Spec §1.1a — issues a session_token for a chosen account, and upserts
 * wa_number_last_account so /auth/verify-user's last_used_account_id
 * (§1.1c) can offer the quick-confirm shortcut next time.
 */
require_once __DIR__ . '/_bootstrap.php';

$waNumber = $input['wa_number'] ?? null;
$userId = $input['user_id'] ?? null;
$category = $input['category'] ?? null;
$conversationId = $input['conversation_id'] ?? null;

if (!$waNumber || !is_string($waNumber)) wa_po_fail(400, 'wa_number is required');
if (!is_numeric($userId)) wa_po_fail(400, 'user_id is required');
$userId = (int)$userId;

$configs = wa_po_category_configs();

// category is optional in the request per spec example, but we need to
// know which table to check the account against — if omitted, scan every
// category's config for a matching user_id that also matches this
// wa_number (via the same verify-user matching logic), then use whichever
// category resolves. This mirrors verify-user.php's normalized lookup.
if ($category && isset($configs[$category])) {
    $candidateCategories = [$category];
} else {
    $candidateCategories = array_keys($configs);
}

$normalized = wa_po_normalize_phone($waNumber);
$resolved = null;

foreach ($candidateCategories as $cat) {
    $cfg = $configs[$cat];
    $hasDeletedAt = in_array($cat, ['distributor', 'super_distributor', 'stockiest', 'super_stockiest', 'candf'], true);
    $deletedClause = $hasDeletedAt ? "AND deleted_at IS NULL" : "";

    $sql = "SELECT `{$cfg['id_field']}` AS user_id, `{$cfg['login_id_field']}` AS login_id, `{$cfg['name_field']}` AS name,
                   `{$cfg['status_field']}` AS status, `{$cfg['mobile_field']}` AS mobile
            FROM `{$cfg['table']}` WHERE `{$cfg['id_field']}` = ? $deletedClause";
    $stmt = mysqli_prepare($db_conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) continue;

    // Confirm this wa_number is legitimately tied to this account — either
    // directly on the category table, or via an explicit wa_po_linked_numbers
    // link. Prevents selecting an arbitrary user_id unrelated to the caller.
    $mobileMatches = wa_po_normalize_phone($row['mobile']) === $normalized;
    $linkMatches = false;
    if (!$mobileMatches) {
        $linkStmt = mysqli_prepare($db_conn,
            "SELECT 1 FROM wa_po_linked_numbers WHERE user_category = ? AND user_id = ? AND wa_number = ?"
        );
        mysqli_stmt_bind_param($linkStmt, 'sis', $cat, $userId, $waNumber);
        mysqli_stmt_execute($linkStmt);
        $linkMatches = (bool)mysqli_stmt_get_result($linkStmt)->fetch_assoc();
        mysqli_stmt_close($linkStmt);
    }

    if ($mobileMatches || $linkMatches) {
        $resolved = ['category' => $cat, 'cfg' => $cfg, 'row' => $row];
        break;
    }
}

if (!$resolved) {
    wa_po_fail(404, 'No matching account for this wa_number/user_id combination');
}

$cat = $resolved['category'];
$row = $resolved['row'];

$sessionToken = wa_po_create_session($db_conn, $waNumber, $cat, $userId, $conversationId);

// Upsert wa_number_last_account (spec §1.1a/§1.1c).
$upsert = mysqli_prepare($db_conn,
    "INSERT INTO wa_number_last_account (wa_number, user_category, user_id, updated_at)
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE user_category = VALUES(user_category), user_id = VALUES(user_id), updated_at = NOW()"
);
mysqli_stmt_bind_param($upsert, 'ssi', $waNumber, $cat, $userId);
mysqli_stmt_execute($upsert);
mysqli_stmt_close($upsert);

wa_po_log_event('session created (' . $cat . ', user_id ' . $userId . ')');
echo json_encode([
    'session_token' => $sessionToken,
    'user_id' => $userId,
    'tp_login_id' => (string)$row['login_id'],
    'category' => $cat,
]);
