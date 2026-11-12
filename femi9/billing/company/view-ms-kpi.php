<?php
// Company-side "View KPI" entry — read-only bridge to marketing/kpi.php,
// exact mirror of view-ms-dashboard.php (same company_ms_view_bridge table
// and femi9_company_ms_view cookie; marketing/checksession.php already
// allowlists kpi.php alongside dashboard.php for this bridge).
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

$_fwd = [];
foreach (['embed'] as $_k) {
    if (isset($_GET[$_k]) && $_GET[$_k] !== '') { $_fwd[$_k] = $_GET[$_k]; }
}
header('Location: ../marketing/kpi.php' . ($_fwd ? ('?' . http_build_query($_fwd)) : ''));
exit;
