<?php
// Shared cross-portal login handoff. Every portal except company/ has its
// own session cookie (see each portal's .htaccess — femi9_<portal>_sess),
// so a login resolved on one URL (central login/, or salesbdm/track's own
// standalone CheckLogin.php) can't write directly into $_SESSION and expect
// the target portal to see it. This bridge carries the resolved account
// (and, for the "Switch Account" dropdown, the full linked-accounts list)
// across that cookie boundary via a single-use, short-lived DB token —
// same shape as the existing company_tp_login_bridge /
// salesbdm_login_switch_bridge tables, generalized into one reusable table
// since this now has many callers instead of one admin feature.

function ensurePortalLoginBridgeTable(mysqli $db_conn): void
{
    $db_conn->query("CREATE TABLE IF NOT EXISTS portal_login_bridge (
        token VARCHAR(64) PRIMARY KEY,
        payload TEXT NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

function mintBridgeToken(mysqli $db_conn, array $account, array $linkedAccounts): string
{
    ensurePortalLoginBridgeTable($db_conn);

    $payload = json_encode([
        'type'            => $account['type'],
        'id'              => $account['id'],
        'name'            => $account['name'],
        'mobile'          => $account['mobile'],
        'linked_accounts' => $linkedAccounts,
    ]);

    $token = bin2hex(random_bytes(32));
    $ins = $db_conn->prepare(
        "INSERT INTO portal_login_bridge (token, payload, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))"
    );
    $ins->bind_param('ss', $token, $payload);
    $ins->execute();
    $ins->close();

    return $token;
}

function consumeBridgeToken(mysqli $db_conn, string $token): ?array
{
    ensurePortalLoginBridgeTable($db_conn);

    $stmt = $db_conn->prepare(
        "SELECT payload FROM portal_login_bridge WHERE token = ? AND expires_at > NOW() LIMIT 1"
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Single-use — delete on read regardless of outcome, so the same link
    // can never be replayed even if it was still within its 2-minute window.
    $del = $db_conn->prepare("DELETE FROM portal_login_bridge WHERE token = ?");
    $del->bind_param('s', $token);
    $del->execute();
    $del->close();

    if (!$row) {
        return null;
    }

    $payload = json_decode($row['payload'], true);
    return is_array($payload) ? $payload : null;
}
?>
