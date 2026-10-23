<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('salesbdm_manage');
error_reporting(0);

$bdmId = (int)($_GET['bdm_id'] ?? 0);
if (!$bdmId) { header('Location: salesbdm-team-report'); exit; }

$stmt = $db_conn->prepare("SELECT id FROM sales_bdm_staff WHERE id = ? AND account_status = 'active' LIMIT 1");
$stmt->bind_param('i', $bdmId);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$exists) { header('Location: salesbdm-team-report'); exit; }

$db_conn->query("CREATE TABLE IF NOT EXISTS company_bdm_view_bridge (
    token VARCHAR(64) PRIMARY KEY,
    bdm_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$token = bin2hex(random_bytes(32));
$ins = $db_conn->prepare("INSERT INTO company_bdm_view_bridge (token, bdm_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
$ins->bind_param('si', $token, $bdmId);
$ins->execute();
$ins->close();

setcookie('femi9_company_bdm_view', $token, [
    'expires'  => time() + 900,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Forward the district + date-range filter straight through to the real
// dashboard — lets MIS Report's "Sales BDM" tab (district + from/to +
// Search) land on the exact filtered view it asked for instead of the
// BDM's default (whole-zone, this-month) dashboard.
$_fwd = [];
foreach (['district', 'from', 'to', 'preset', 'embed', 'tp_id'] as $_k) {
    if (isset($_GET[$_k]) && $_GET[$_k] !== '') { $_fwd[$_k] = $_GET[$_k]; }
}
header('Location: ../salesbdm/dashboard.php' . ($_fwd ? ('?' . http_build_query($_fwd)) : ''));
exit;
