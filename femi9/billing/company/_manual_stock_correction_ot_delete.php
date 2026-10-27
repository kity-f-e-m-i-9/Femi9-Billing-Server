<?php
// TEMPORARY one-time fix — two OT channel sale invoices were deleted via
// ot-sale-delete.php, which calls StockService::otDeductUndo() per line to
// automatically add the stock back. otDeductUndo() silently no-ops
// ('success' => false, 'reason' => 'no_stock_row') instead of throwing when
// it can't find a matching stock row, and ot-sale-delete.php never checks
// that return value — so the deletion succeeded but these 4 line items'
// stock was never actually credited back. Confirmed 2026-10-27.
//
// This re-runs the exact same otDeductUndo() operation that should have
// fired automatically, for FEMI NAYAN LLP (company_id=1), warehouse G1
// (warehouse_id=2):
//   180mm (30 PCS)            [product 4]  qty 7   (invoice 1)
//   330mm XL (DW-9 PCS)       [product 51] qty 12  (invoice 1)
//   280mm L                   [product 3]  qty 8   (invoice 2)
//   290mm L (SW-9 PCS)        [product 11] qty 8   (invoice 2)
//
// Default mode is DRY RUN (shows current + after-correction stock, writes
// nothing). Visit with ?apply=1 to actually commit.
// Delete this file after use.
include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");
require_once("include/StockService.php");
error_reporting(0);

header('Content-Type: text/plain; charset=utf-8');

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['admin', 'finance'], true)) {
    die("Access denied — admin/finance only.\n");
}

$apply = isset($_GET['apply']) && $_GET['apply'] == '1';

$companyId   = 1; // FEMI NAYAN LLP
$warehouseId = 2; // G1
$userType    = 'company';
$createdBy   = $_SESSION['LOGIN_USER'] ?? 'system';

$corrections = [
    ['product_id' => 4,  'qty' => 7,  'label' => '180mm (30 PCS)', 'ref' => 'manual_corr_20261027_1'],
    ['product_id' => 51, 'qty' => 12, 'label' => '330mm XL (DW-9 PCS)', 'ref' => 'manual_corr_20261027_2'],
    ['product_id' => 3,  'qty' => 8,  'label' => '280mm L', 'ref' => 'manual_corr_20261027_3'],
    ['product_id' => 11, 'qty' => 8,  'label' => '290mm L (SW-9 PCS)', 'ref' => 'manual_corr_20261027_4'],
];

echo "=== Manual stock correction (deleted OT invoices never credited back) ===\n";
echo "Mode: " . ($apply ? "APPLY (will commit)" : "DRY RUN (nothing written — add ?apply=1 to commit)") . "\n";
echo "FEMI NAYAN LLP, warehouse G1\n\n";

$stockService = new StockService($db_conn);

function fetchStockRow($db_conn, $productId, $userType, $companyId, $warehouseId) {
    $stmt = $db_conn->prepare("SELECT product_id, sales_qty, closing_qty FROM stock WHERE product_id=? AND user_type=? AND user_id=? AND warehouse_id=?");
    $uid = (string)$companyId;
    $stmt->bind_param('issi', $productId, $userType, $uid, $warehouseId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

foreach ($corrections as $c) {
    $before = fetchStockRow($db_conn, $c['product_id'], $userType, $companyId, $warehouseId);
    if (!$before) {
        echo "SKIP — no stock row for product {$c['product_id']} ({$c['label']})\n\n";
        continue;
    }
    echo "{$c['label']} [product {$c['product_id']}], qty {$c['qty']}\n";
    echo "  Before: sales_qty={$before['sales_qty']}, closing_qty={$before['closing_qty']}\n";
    echo "  After:  sales_qty=" . max(0, $before['sales_qty'] - $c['qty']) . ", closing_qty=" . ($before['closing_qty'] + $c['qty']) . "\n";

    if ($apply) {
        try {
            $result = $stockService->otDeductUndo(
                $c['product_id'], $userType, (string)$companyId,
                $c['qty'], $c['ref'], $createdBy,
                false, $warehouseId
            );
            if ($result['success']) {
                echo "  APPLIED (ledger_id {$result['ledger_id']})\n";
            } else {
                echo "  FAILED: " . ($result['reason'] ?? 'unknown') . "\n";
            }
        } catch (\Throwable $e) {
            echo "  EXCEPTION: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
}

echo $apply ? "Done applying. Verify stock above, then delete this file.\n" : "Dry run only — review the Before/After numbers, then revisit with ?apply=1.\n";
