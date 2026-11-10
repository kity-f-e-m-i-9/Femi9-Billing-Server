<?php
// Product list + previously-entered actual qty for one TP + category — backs
// the second step of the "Actual Stock" modal on my-assigned-tps.php.
require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
include("config.php");
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/include/MsDistrictScope.php';
header('Content-Type: application/json');
error_reporting(0);

$msId     = (int)$Login_user_IDvl;
$tpId     = (int)($_GET['tp_id'] ?? 0);
$category = $_GET['category'] ?? '';
if ($tpId <= 0 || !in_array($category, ['napkin', 'diaper'], true)) { echo json_encode([]); exit; }

$scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $msId);
$allowedTpIds = [];
foreach ($scopeNodes as $node) {
    $allowedTpIds = array_merge($allowedTpIds, getMsNodeTpIds($db_conn, [$node['id']], true));
}
if (!in_array($tpId, $allowedTpIds, true)) {
    echo json_encode([]);
    exit;
}

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

$stmt = $db_conn->prepare("
    SELECT p.id AS product_id, p.productName, e.qty
    FROM products p
    LEFT JOIN tp_actual_stock_entries e ON e.product_id = p.id AND e.territory_partner_id = ?
    WHERE p.category = ? AND p.deleted_at IS NULL
    ORDER BY p.productName ASC
");
$stmt->bind_param('is', $tpId, $category);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($rows as &$r) {
    $r['product_id'] = (int)$r['product_id'];
    $r['qty'] = $r['qty'] === null ? null : (int)$r['qty'];
}

echo json_encode($rows);
