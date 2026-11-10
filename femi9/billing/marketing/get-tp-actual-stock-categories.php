<?php
// Category list (Napkin / Diaper) for the "Actual Stock" modal on
// my-assigned-tps.php — product count per category plus when/by-whom it was
// last physically counted for this TP. tp_id is never trusted from the
// client without checking it's one of this person's own TPs, same pattern
// get-tp-stock-breakdown.php already uses.
require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
include("config.php");
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/include/MsDistrictScope.php';
header('Content-Type: application/json');
error_reporting(0);

$msId = (int)$Login_user_IDvl;
$tpId = (int)($_GET['tp_id'] ?? 0);
if ($tpId <= 0) { echo json_encode([]); exit; }

$scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $msId);
$allowedTpIds = [];
foreach ($scopeNodes as $node) {
    $allowedTpIds = array_merge($allowedTpIds, getMsNodeTpIds($db_conn, [$node['id']], true));
}
if (!in_array($tpId, $allowedTpIds, true)) {
    echo json_encode([]);
    exit;
}

// One-time table, self-created here too (not just via the .sql file) so a
// forgotten manual migration on any environment can never surface as a
// "Failed to save" error the way tp_daily_assignments once did.
$db_conn->query("
    CREATE TABLE IF NOT EXISTS `tp_actual_stock_entries` (
      `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `territory_partner_id`  INT UNSIGNED NOT NULL,
      `product_id`            INT UNSIGNED NOT NULL,
      `qty`                   INT NOT NULL DEFAULT 0,
      `updated_by`            VARCHAR(100) NULL DEFAULT NULL,
      `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_tase_tp_product` (`territory_partner_id`, `product_id`),
      KEY `idx_tase_tp` (`territory_partner_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$labels = ['napkin' => 'Napkin', 'diaper' => 'Diaper'];

$counts = [];
$cres = $db_conn->query("SELECT category, COUNT(*) AS n FROM products WHERE category IN ('napkin','diaper') AND deleted_at IS NULL GROUP BY category");
while ($row = $cres->fetch_assoc()) { $counts[$row['category']] = (int)$row['n']; }

$last = [];
$stmt = $db_conn->prepare("
    SELECT p.category, MAX(e.updated_at) AS last_updated,
           SUBSTRING_INDEX(GROUP_CONCAT(e.updated_by ORDER BY e.updated_at DESC SEPARATOR '||'), '||', 1) AS last_updated_by
    FROM tp_actual_stock_entries e
    JOIN products p ON p.id = e.product_id
    WHERE e.territory_partner_id = ?
    GROUP BY p.category
");
$stmt->bind_param('i', $tpId);
$stmt->execute();
$lres = $stmt->get_result();
while ($row = $lres->fetch_assoc()) { $last[$row['category']] = $row; }
$stmt->close();

$out = [];
foreach ($labels as $cat => $label) {
    $out[] = [
        'category'         => $cat,
        'label'            => $label,
        'product_count'    => $counts[$cat] ?? 0,
        'last_updated'     => $last[$cat]['last_updated'] ?? null,
        'last_updated_by'  => $last[$cat]['last_updated_by'] ?? null,
    ];
}

echo json_encode($out);
