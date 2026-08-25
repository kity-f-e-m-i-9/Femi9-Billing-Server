<?php
/**
 * POST /auth/lookup-by-alt
 * Spec §1.1b — fallback verification when a WhatsApp number doesn't match
 * any registered account. Caller supplies a login ID / registered mobile
 * number / GSTIN; we resolve the account without confirming/denying
 * whether the *original* wa_number is registered (that's the point of
 * this fallback path).
 */
require_once __DIR__ . '/_bootstrap.php';

$identifier = trim((string)($input['identifier'] ?? ''));
if ($identifier === '') {
    wa_po_fail(400, 'identifier is required');
}

if (!wa_po_rate_limit_check($db_conn, 'lookup-by-alt:' . $identifier, 20, 3600)) {
    wa_po_fail(429, 'Too many requests — please try again later');
}

$configs = wa_po_category_configs();
$normalizedIdentifier = wa_po_normalize_phone($identifier);
// One identifier (especially a mobile number) can legitimately be
// registered under more than one category — e.g. a stockiest and a
// territory partner sharing the same phone number. Collect every match
// instead of stopping at the first, so we don't silently resolve to the
// wrong account (or a lower-priority one) while masking the rest.
$matches = [];

foreach ($configs as $category => $cfg) {
    $hasDeletedAt = in_array($category, ['distributor', 'super_distributor', 'stockiest', 'super_stockiest', 'candf'], true);
    $deletedClause = $hasDeletedAt ? "AND deleted_at IS NULL" : "";

    // Try login_id_field (TP-style login ID / cp_id / temp_id), GSTIN
    // (where the table has one), and mobile field (exact + normalized).
    $hasGstin = in_array($category, ['distributor', 'super_distributor', 'stockiest', 'super_stockiest', 'candf', 'channel_partner', 'territory_partner'], true);
    $gstinClause = $hasGstin ? "OR `gstin` = ?" : "";

    $sql = "SELECT `{$cfg['id_field']}` AS user_id, `{$cfg['login_id_field']}` AS login_id, `{$cfg['name_field']}` AS name,
                   `{$cfg['status_field']}` AS status, `{$cfg['mobile_field']}` AS mobile
            FROM `{$cfg['table']}`
            WHERE (`{$cfg['login_id_field']}` = ? OR `{$cfg['mobile_field']}` = ? $gstinClause) $deletedClause
            LIMIT 1";
    $stmt = mysqli_prepare($db_conn, $sql);
    if ($hasGstin) {
        mysqli_stmt_bind_param($stmt, 'sss', $identifier, $identifier, $identifier);
    } else {
        mysqli_stmt_bind_param($stmt, 'ss', $identifier, $identifier);
    }
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if ($row) {
        $matches[] = ['category' => $category, 'cfg' => $cfg, 'row' => $row];
        continue;
    }

    // Normalized mobile fallback if identifier looks like a phone number.
    if (strlen($normalizedIdentifier) === 10) {
        $sqlAll = "SELECT `{$cfg['id_field']}` AS user_id, `{$cfg['login_id_field']}` AS login_id, `{$cfg['name_field']}` AS name,
                          `{$cfg['status_field']}` AS status, `{$cfg['mobile_field']}` AS mobile
                   FROM `{$cfg['table']}` WHERE `{$cfg['mobile_field']}` IS NOT NULL AND `{$cfg['mobile_field']}` != '' $deletedClause";
        $resAll = mysqli_query($db_conn, $sqlAll);
        while ($r = $resAll->fetch_assoc()) {
            if (wa_po_normalize_phone($r['mobile']) === $normalizedIdentifier) {
                $matches[] = ['category' => $category, 'cfg' => $cfg, 'row' => $r];
                break;
            }
        }
    }
}

if (empty($matches)) {
    wa_po_log_event('not_found');
    echo json_encode(['found' => false]);
    exit;
}

function wa_po_mask_mobile($mobile) {
    $digits = preg_replace('/\D+/', '', (string)$mobile);
    $last2 = substr($digits, -2);
    return '+9198XXXX' . $last2;
}

if (count($matches) > 1) {
    $accounts = array_map(function ($m) {
        $row = $m['row'];
        $isActive = ((string)$row['status'] === (string)$m['cfg']['status_active_value']);
        return [
            'user_id' => (int)$row['user_id'],
            'tp_login_id' => (string)$row['login_id'],
            'name' => (string)$row['name'],
            'registered_number_masked' => wa_po_mask_mobile($row['mobile']),
            'category' => $m['category'],
            'status' => $isActive ? 'active' : 'inactive',
        ];
    }, $matches);

    wa_po_log_event('multiple_accounts (' . count($accounts) . ')');
    echo json_encode([
        'found' => true,
        'match_type' => 'multiple_accounts',
        'accounts' => $accounts,
    ]);
    exit;
}

$found = $matches[0];
$row = $found['row'];
$isActive = ((string)$row['status'] === (string)$found['cfg']['status_active_value']);

wa_po_log_event('exact (' . $found['category'] . ')');
echo json_encode([
    'found' => true,
    'match_type' => 'exact',
    'user_id' => (int)$row['user_id'],
    'tp_login_id' => (string)$row['login_id'],
    'name' => (string)$row['name'],
    'registered_number_masked' => wa_po_mask_mobile($row['mobile']),
    'category' => $found['category'],
    'status' => $isActive ? 'active' : 'inactive',
]);
