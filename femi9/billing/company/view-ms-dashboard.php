<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
error_reporting(0);

$msId = (int)($_GET['ms_id'] ?? 0);
if (!$msId) { header('Location: dashboard'); exit; }

$stmt = $db_conn->prepare("SELECT id FROM marketing_staff WHERE id = ? AND account_status = 'active' LIMIT 1");
$stmt->bind_param('i', $msId);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$exists) { header('Location: dashboard'); exit; }

$db_conn->query("CREATE TABLE IF NOT EXISTS company_ms_view_bridge (
    token VARCHAR(64) PRIMARY KEY,
    ms_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$token = bin2hex(random_bytes(32));
$ins = $db_conn->prepare("INSERT INTO company_ms_view_bridge (token, ms_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
$ins->bind_param('si', $token, $msId);
$ins->execute();
$ins->close();

setcookie('femi9_company_ms_view', $token, [
    'expires'  => time() + 900,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Forward the date-range filter straight through to the real dashboard —
// same pattern as company/view-bdm-dashboard.php.
$_fwd = [];
foreach (['from', 'to', 'embed'] as $_k) {
    if (isset($_GET[$_k]) && $_GET[$_k] !== '') { $_fwd[$_k] = $_GET[$_k]; }
}
header('Location: ../marketing/dashboard.php' . ($_fwd ? ('?' . http_build_query($_fwd)) : ''));
exit;
