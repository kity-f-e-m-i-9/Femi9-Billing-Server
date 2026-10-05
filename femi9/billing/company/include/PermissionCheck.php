<?php
/**
 * Server-side mirror of femi_menu.php's menu-gating logic.
 *
 * The sidebar menu hides links a "users" sub-user isn't permitted to see,
 * but until now nothing stopped that sub-user from loading the page
 * directly by URL. Call requirePermission($perm) right after
 * checksession.php + config.php on every permission-mapped page — it
 * enforces the exact same admin_log boolean column the menu already checks.
 *
 * Company owner (usertype='admin'), 'finance', and 'neksomo' always pass,
 * matching femi_menu.php where those roles' menu blocks have no per-item
 * checks. 'neksomo' is instead scoped at the data level via GodownAccess.php
 * (restricted to the NEKSOMO HYGIENE INDUSTRIES godown only).
 */

/**
 * Every admin_log boolean column requirePermission()/hasPermission() are
 * allowed to check. $perm is always a hardcoded literal at each call site,
 * never request data — this whitelist just guards against a typo
 * interpolating an unintended column name into the query.
 *
 * manage_input_stock_edit/manage_input_stock_delete are the pilot for a
 * granular View/Edit/Delete model under a module's base permission
 * (manage_input_stock = View) — see users_add.php/users_edit.php's "Edit" /
 * "Delete" sub-checkboxes and manage-input.php's use of hasPermission() to
 * show/hide its own Edit/Delete buttons.
 */
function allowedPermissionKeys(): array
{
    return [
        'dash', 'report', 'company_profile', 'users_demo', 'reward_points', 'demo_free',
        'demo_free_edit', 'demo_free_delete',
        'manage_return', 'manage_return_edit', 'manage_return_delete',
        'debit_note', 'debit_note_edit', 'debit_note_delete',
        'stock_request', 'products', 'products_edit', 'products_delete', 'add_input_stock',
        'manage_input_stock', 'manage_input_stock_edit', 'manage_input_stock_delete',
        'add_input_stock_users', 'manage_input_stock_users',
        'manage_input_stock_users_edit', 'manage_input_stock_users_delete',
        'ot_channels', 'location',
        'ss', 'ss_edit', 'ss_delete', 'st', 'st_edit', 'st_delete',
        'dt', 'dt_edit', 'dt_delete', 'sdt', 'sdt_edit', 'sdt_delete',
        'shop', 'shop_edit', 'shop_delete', 'cus', 'cus_edit', 'cus_delete',
        'ms', 'ms_edit', 'ms_delete',
        'salesbdm_manage', 'salesbdm_manage_edit', 'salesbdm_manage_delete',
        'unassigned',
        'remap', 'users_network', 'payment_entry', 'manage_payment_entry',
        'manage_payment_entry_edit', 'manage_payment_entry_delete',
        'consolidated_payment_entry', 'consolidated_payment_entry_edit', 'consolidated_payment_entry_delete',
        'bonus_calculator', 'manage_bonus_points', 'manage_bonus_points_edit', 'manage_bonus_points_delete',
        'partner_location',
        'channel_partner', 'channel_partner_edit', 'channel_partner_delete',
        'territory_partner', 'territory_partner_edit', 'territory_partner_delete',
        'stock_transfers', 'internal_transfer',
    ];
}

/**
 * admin_log columns for the View/Edit/Delete/All rollout to products,
 * manage_return, debit_note, demo_free, manage_input_stock_users, ms,
 * manage_payment_entry, consolidated_payment_entry, manage_bonus_points —
 * added lazily (same SHOW COLUMNS-then-ALTER pattern as
 * TpShopInvoiceActionRequest.php's tpEnsureShopInvoiceEligibilityColumn())
 * instead of a one-off migration script, so a fresh/out-of-sync DB self-heals
 * on first use rather than silently erroring on an unknown column.
 */
function ensureGranularPermissionColumns(mysqli $db): void
{
    static $ensured = false;
    if ($ensured) { return; }
    $ensured = true;

    $newColumns = [
        'products_edit', 'products_delete',
        'manage_return_edit', 'manage_return_delete',
        'debit_note_edit', 'debit_note_delete',
        'demo_free_edit', 'demo_free_delete',
        'manage_input_stock_users_edit', 'manage_input_stock_users_delete',
        'ms_edit', 'ms_delete',
        'manage_payment_entry_edit', 'manage_payment_entry_delete',
        'consolidated_payment_entry_edit', 'consolidated_payment_entry_delete',
        'manage_bonus_points_edit', 'manage_bonus_points_delete',
    ];
    foreach ($newColumns as $col) {
        $res = $db->query("SHOW COLUMNS FROM admin_log LIKE '{$col}'");
        if ($res && $res->num_rows === 0) {
            $db->query("ALTER TABLE admin_log ADD COLUMN `{$col}` TINYINT(1) NOT NULL DEFAULT 0");
        }
    }

    // Sales BDM used to be piggybacked on the 'ms' (Marketing Staff) column —
    // same pages, same checkbox. Splitting it into its own permission must not
    // silently revoke access from anyone who currently has it via 'ms', so the
    // new columns are backfilled from ms/ms_edit/ms_delete at creation time
    // only; after that the two permissions are independent.
    $sbdmCols = ['salesbdm_manage' => 'ms', 'salesbdm_manage_edit' => 'ms_edit', 'salesbdm_manage_delete' => 'ms_delete'];
    foreach ($sbdmCols as $col => $seedFrom) {
        $res = $db->query("SHOW COLUMNS FROM admin_log LIKE '{$col}'");
        if ($res && $res->num_rows === 0) {
            $db->query("ALTER TABLE admin_log ADD COLUMN `{$col}` TINYINT(1) NOT NULL DEFAULT 0");
            $db->query("UPDATE admin_log SET `{$col}` = `{$seedFrom}`");
        }
    }
}

function requirePermission(string $perm): void
{
    global $db_conn, $Login_user_TYPEvl;

    if (!in_array($perm, allowedPermissionKeys(), true)) {
        http_response_code(403);
        die('Access denied: unknown permission.');
    }

    // Sales BDM sessions never appear in admin_log — checksession.php already
    // scoped which pages they can even reach, so a BDM session hitting one of
    // the perms those pages check ('territory_partner', 'channel_partner') is
    // always allowed here. ms_edit/ms_delete are included alongside 'ms' so
    // splitting Marketing Staff into View/Edit/Delete for company "users"
    // sub-accounts doesn't change what a BDM could already reach.
    if (($Login_user_TYPEvl ?? '') === 'salesbdm') {
        if (in_array($perm, ['territory_partner', 'channel_partner', 'ms', 'ms_edit', 'ms_delete'], true)) { return; }
        denyAccess();
    }

    $username = $_SESSION['LOGIN_USER'] ?? '';
    if ($username === '') {
        header('Location: index.php?sessionexpiry');
        exit;
    }

    ensureGranularPermissionColumns($db_conn);

    $stmt = mysqli_prepare($db_conn, "SELECT usertype, `{$perm}` AS perm_value FROM admin_log WHERE username=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: index.php');
        exit;
    }

    if ($row['usertype'] === 'admin' || $row['usertype'] === 'finance' || $row['usertype'] === 'neksomo' || $row['usertype'] === 'stockviewer') {
        return;
    }

    if ((int)$row['perm_value'] !== 1) {
        denyAccess();
    }
}

/**
 * Same admin_log column check as requirePermission(), but returns a bool
 * instead of dying — for deciding whether to render a button/link (e.g. an
 * Edit or Delete icon) rather than gating an entire page.
 */
function hasPermission(string $perm): bool
{
    global $db_conn, $Login_user_TYPEvl;

    if (!in_array($perm, allowedPermissionKeys(), true)) {
        return false;
    }

    if (($Login_user_TYPEvl ?? '') === 'salesbdm') {
        return in_array($perm, ['territory_partner', 'channel_partner', 'ms', 'ms_edit', 'ms_delete'], true);
    }

    $username = $_SESSION['LOGIN_USER'] ?? '';
    if ($username === '') {
        return false;
    }

    ensureGranularPermissionColumns($db_conn);

    $stmt = mysqli_prepare($db_conn, "SELECT usertype, `{$perm}` AS perm_value FROM admin_log WHERE username=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) {
        return false;
    }

    if (in_array($row['usertype'], ['admin', 'finance', 'neksomo', 'stockviewer'], true)) {
        return true;
    }

    return (int) $row['perm_value'] === 1;
}

/**
 * For the handful of pages gated to the company owner only (Change Password,
 * WhatsApp Settings) — these have no admin_log boolean column, they're just
 * usertype==='admin' in the header/menu. Kept separate from requirePermission()
 * since it's not a permission-column check.
 */
function requireAdminOnly(): void
{
    global $db_conn;

    $username = $_SESSION['LOGIN_USER'] ?? '';
    if ($username === '') {
        header('Location: index.php?sessionexpiry');
        exit;
    }

    $stmt = mysqli_prepare($db_conn, "SELECT usertype FROM admin_log WHERE username=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: index.php');
        exit;
    }

    if ($row['usertype'] !== 'admin') {
        denyAccess();
    }
}

function denyAccess(): void
{
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Access Denied</title>
        <link href="../../assets/css/main.min.css" rel="stylesheet">
    </head>
    <body style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;">
        <div style="text-align:center;">
            <h2>Access Denied</h2>
            <p>You don't have permission to view this page. Contact your administrator if you need access.</p>
            <a href="dashboard">Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
