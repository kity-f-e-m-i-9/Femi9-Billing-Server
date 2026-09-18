<?php
/**
 * One-time backfill: draws down every existing Neksomo product's pool into
 * its mapped company product(s)' real stock, using the same eager-conversion
 * logic added to neksomo-manufacturer-purchase-action.php for future
 * purchases. Needed because that fix only applies going forward — stock
 * from purchases already recorded before the fix stays stuck in the pool
 * until something explicitly draws it down.
 *
 * CLI only. Run once: php neksomo-pool-backfill.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

chdir(__DIR__);
require_once("include/db-connect.php");
require_once("include/StockService.php");
require_once("include/NeksomoStockBridge.php");

$neksomoGodownId = get_neksomo_godown_id($db_conn);
if (!$neksomoGodownId) {
    exit("Could not resolve NEKSOMO HYGIENE INDUSTRIES godown id.\n");
}

$neksomoProductIds = array_map('intval', array_column(
    $db_conn->query("SELECT DISTINCT neksomo_product_id FROM neksomo_product_mapping")->fetch_all(MYSQLI_ASSOC),
    'neksomo_product_id'
));

$stockService = new StockService($db_conn);
$createdBy    = 'neksomo-pool-backfill';
$totalDrawn   = 0;

// Only unit_type='pack' Neksomo products (e.g. diapers) are eligible for
// automatic pool-to-pack conversion — see neksomo-manufacturer-purchase-
// action.php for why pieces-type products (e.g. napkins) are excluded.
$unitTypeByNeksomoProduct = array_column(
    $db_conn->query("SELECT id, unit_type FROM products WHERE id IN (" . implode(',', $neksomoProductIds ?: [0]) . ")")->fetch_all(MYSQLI_ASSOC),
    'unit_type', 'id'
);

foreach ($neksomoProductIds as $neksomoProductId) {
    if (($unitTypeByNeksomoProduct[$neksomoProductId] ?? null) !== 'pack') continue;

    $mappedCompanyProductIds = get_neksomo_product_mapping($db_conn, $neksomoProductId);
    sort($mappedCompanyProductIds);

    foreach ($mappedCompanyProductIds as $companyProductId) {
        $availablePacks = get_neksomo_pool_available_packs($db_conn, $companyProductId);
        if ($availablePacks <= 0) continue;

        $db_conn->begin_transaction();
        try {
            $stockService->credit(
                $companyProductId, 'company', (string) $neksomoGodownId, $availablePacks,
                'adjustment', 'neksomo_backfill_' . uniqid(), $createdBy, true
            );
            record_neksomo_stock_conversion($db_conn, $companyProductId, $availablePacks, $createdBy);
            $db_conn->commit();
        } catch (\Throwable $e) {
            $db_conn->rollback();
            echo "FAILED product=$companyProductId (neksomo=$neksomoProductId): {$e->getMessage()}\n";
            continue;
        }

        $totalDrawn++;
        echo "Converted $availablePacks pack(s) into company product $companyProductId (from neksomo product $neksomoProductId)\n";
    }
}

echo "Done. $totalDrawn stock row(s) topped up from pool.\n";
