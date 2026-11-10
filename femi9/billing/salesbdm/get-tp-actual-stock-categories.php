<?php
// Read-only twin of marketing/get-tp-actual-stock-categories.php, scoped to
// this Sales BDM's own assigned districts instead of a DM/ASM's location
// tree — Sales BDM only VIEWS what a DM/ASM has physically counted, never
// enters it themselves, so this never writes to tp_actual_stock_entries.
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
header('Content-Type: application/json');
error_reporting(0);

$tpId = (int)($_GET['tp_id'] ?? 0);
if ($tpId <= 0) { echo json_encode([]); exit; }

$myTpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID, true);
if (!in_array($tpId, $myTpIds, true)) {
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
