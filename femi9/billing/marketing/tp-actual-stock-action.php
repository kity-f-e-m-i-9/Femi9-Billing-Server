<?php
// Save endpoint for the "Actual Stock" modal on my-assigned-tps.php — the DM/
// ASM physically counts what's at the TP's place and records it here, one
// row per (tp, product), upserted on every save so re-opening the same
// category and submitting again is how an existing entry gets edited.
// tp_id and every product_id posted are re-checked server-side (own TP scope,
// and product must actually belong to the posted category) rather than
// trusted from the client, same spirit as tp-assign-firka-action.php.
require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/include/MsDistrictScope.php';
header('Content-Type: application/json');
error_reporting(0);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$msId     = (int)$Login_user_IDvl;
$tpId     = (int)($_POST['tp_id'] ?? 0);
$category = $_POST['category'] ?? '';
$qtyMap   = $_POST['qty'] ?? [];
$updatedBy = $_SESSION['LOGIN_USER_NAME'] ?? '';

if ($tpId <= 0 || !in_array($category, ['napkin', 'diaper'], true) || !is_array($qtyMap)) {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

// ── Scope check: tp_id must be one of this person's own assigned TPs ──────
$scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $msId);
$allowedTpIds = [];
foreach ($scopeNodes as $node) {
    $allowedTpIds = array_merge($allowedTpIds, getMsNodeTpIds($db_conn, [$node['id']], true));
}
if (!in_array($tpId, $allowedTpIds, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'This Territory Partner is outside your assigned area.']);
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

// Only product ids that genuinely belong to the posted category are
// accepted — a tampered field name/value for a product in the other
// category (or a non-existent id) is silently dropped.
$validProductIds = [];
$pres = $db_conn->prepare("SELECT id FROM products WHERE category = ? AND deleted_at IS NULL");
$pres->bind_param('s', $category);
$pres->execute();
$pr = $pres->get_result();
while ($row = $pr->fetch_assoc()) { $validProductIds[(int)$row['id']] = true; }
$pres->close();

$entries = [];
foreach ($qtyMap as $productId => $qty) {
    $productId = (int)$productId;
    if ($qty === '' || $qty === null || !isset($validProductIds[$productId])) { continue; }
    $qty = (int)$qty;
    if ($qty < 0) { continue; }
    $entries[$productId] = $qty;
}

if (empty($entries)) {
    echo json_encode(['success' => true, 'saved' => 0]);
    exit;
}

$db_conn->begin_transaction();
try {
    $ins = $db_conn->prepare("
        INSERT INTO tp_actual_stock_entries (territory_partner_id, product_id, qty, updated_by)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE qty = VALUES(qty), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP
    ");
    foreach ($entries as $productId => $qty) {
        $ins->bind_param('iiis', $tpId, $productId, $qty, $updatedBy);
        $ins->execute();
    }
    $ins->close();
    $db_conn->commit();
    echo json_encode(['success' => true, 'saved' => count($entries)]);
} catch (Exception $e) {
    $db_conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Failed to save.']);
}
