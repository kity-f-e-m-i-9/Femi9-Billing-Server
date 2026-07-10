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
 * Company owner (usertype='admin') and 'finance' always pass, matching
 * femi_menu.php where those two roles' menu blocks have no per-item checks.
 */

function requirePermission(string $perm): void
{
    global $db_conn;

    static $allowedPerms = [
        'dash', 'report', 'company_profile', 'users_demo', 'reward_points', 'demo_free',
        'manage_return', 'debit_note', 'stock_request', 'products', 'add_input_stock',
        'manage_input_stock', 'add_input_stock_users', 'manage_input_stock_users',
        'ot_channels', 'location', 'ss', 'st', 'dt', 'sdt', 'shop', 'cus', 'ms', 'unassigned',
        'remap', 'users_network', 'payment_entry', 'manage_payment_entry',
        'consolidated_payment_entry', 'bonus_calculator', 'manage_bonus_points',
        'partner_location', 'channel_partner', 'territory_partner', 'stock_transfers',
    ];

    // $perm is always a hardcoded literal at each call site, never request
    // data — this whitelist just guards against a typo interpolating an
    // unintended column name into the query below.
    if (!in_array($perm, $allowedPerms, true)) {
        http_response_code(403);
        die('Access denied: unknown permission.');
    }

    $username = $_SESSION['LOGIN_USER'] ?? '';
    if ($username === '') {
        header('Location: index.php?sessionexpiry');
        exit;
    }

    $stmt = mysqli_prepare($db_conn, "SELECT usertype, `{$perm}` AS perm_value FROM admin_log WHERE username=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: index.php');
        exit;
    }

    if ($row['usertype'] === 'admin' || $row['usertype'] === 'finance') {
        return;
    }

    if ((int)$row['perm_value'] !== 1) {
        denyAccess();
    }
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
