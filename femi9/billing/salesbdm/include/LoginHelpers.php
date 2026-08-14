<?php
// Shared by CheckLogin.php and choose-login.php so the "finalize a Sales BDM
// session" logic (and the salesbdm→company TP-page bridge cookie) only
// exists in one place.
function finalizeSalesBdmSession($db_conn, array $user): void {
    $_SESSION['LOGIN_USER'] = $user['bdm_mobile'];
    $_SESSION['LOGIN_USER_ID'] = $user['id'];
    $_SESSION['LOGIN_USER_NAME'] = $user['bdm_name'];
    $_SESSION['LOGIN_USER_TYPE'] = 'salesbdm';
    $_SESSION['last_activity'] = time();

    $db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_company_bridge (
        token VARCHAR(64) PRIMARY KEY,
        bdm_id INT NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $bridgeToken = bin2hex(random_bytes(32));
    $bridgeStmt = $db_conn->prepare("INSERT INTO salesbdm_company_bridge (token, bdm_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
    $bridgeStmt->bind_param('si', $bridgeToken, $user['id']);
    $bridgeStmt->execute();
    $bridgeStmt->close();
    setcookie('femi9_bdm_bridge', $bridgeToken, ['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);

    $updateStmt = mysqli_prepare($db_conn, "UPDATE sales_bdm_staff SET last_login = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($updateStmt, "i", $user['id']);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);
}

// One-time, short-lived token that hands off to company/switch-login.php —
// same DB-bridge-token pattern as salesbdm_company_bridge, just carrying an
// admin_log id instead of a bdm_id, and single-use (deleted on first read).
function createCompanySwitchToken($db_conn, int $adminId): string {
    $db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_login_switch_bridge (
        token VARCHAR(64) PRIMARY KEY,
        admin_id INT NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $token = bin2hex(random_bytes(32));
    $stmt = $db_conn->prepare("INSERT INTO salesbdm_login_switch_bridge (token, admin_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))");
    $stmt->bind_param('si', $token, $adminId);
    $stmt->execute();
    $stmt->close();
    return $token;
}
