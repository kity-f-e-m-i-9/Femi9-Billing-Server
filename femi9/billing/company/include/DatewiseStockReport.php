<?php
/**
 * Shared query layer for the "Datewise Overall Stock" report — used by both
 * overstock_datewise.php (on-screen, AJAX fragment) and
 * overstock_datewise_pdf.php (print/export), which previously each carried
 * their own copy of this logic.
 */

// Net stock movement for one product over an inclusive date range, read
// entirely from stock_ledger — the one table StockService guarantees is
// warehouse-scoped and complete for every write since it was introduced
// (2026-06-22 onward). $warehouseCond is a pre-built
// "AND (warehouse_id IN (...) OR warehouse_id IS NULL)" fragment (empty
// string when no warehouse filter applies to this particular call).
function computeStockMovement($db_conn, $prid, $fromDate, $toDate, $companyIdsSql, $filterByGodown, $showManufPurchases, $warehouseCond) {
    $prid = (int)$prid;
    $fromDate = mysqli_real_escape_string($db_conn, $fromDate);
    $toDate   = mysqli_real_escape_string($db_conn, $toDate);
    $sum = function($sql) use ($db_conn) {
        return (int)(mysqli_fetch_row(mysqli_query($db_conn, $sql))[0] ?? 0);
    };
    $godownCond = $filterByGodown ? " AND user_id IN ($companyIdsSql)" : '';
    $dateCond   = " AND created_at >= '$fromDate 00:00:00' AND created_at <= '$toDate 23:59:59'";
    $base       = "product_id=$prid AND user_type='company'$godownCond$warehouseCond$dateCond";
    $sumAction  = function($action) use ($sum, $base) {
        return $sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='$action'");
    };

    // Input Stock Qty — every credit into this godown: input_stock
    // submissions, Neksomo conversions, transfer-in from another godown/
    // warehouse, and accepted returns. Net of reverse_credit (a credit later
    // undone) and transfer_in_reverse (a transfer_in later undone).
    $credit       = $sumAction('credit');
    $reverseCr    = $sumAction('reverse_credit');
    $transferIn   = $sumAction('transfer_in');
    $transferInRv = $sumAction('transfer_in_reverse');
    $returnAccept = $sumAction('return_accept');
    $input_qty    = $credit - $reverseCr + $transferIn - $transferInRv + $returnAccept;

    // Sales Qty — every deduction from this godown that represents goods
    // leaving via a sale: ordinary deduct (customer/TP/user invoices),
    // ot_deduct (OT sales), and demofree's transfer_out (demo/free/damage,
    // folded into Sales per this page's existing convention). Net of
    // reverse_deduct and ot_reverse.
    $deduct      = $sumAction('deduct');
    $reverseDed  = $sumAction('reverse_deduct');
    $otDeduct    = $sumAction('ot_deduct');
    $otReverse   = $sumAction('ot_reverse');
    $dfd         = (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out' AND ref_type='demofree'")
                 - (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out_reverse' AND ref_type='demofree'");
    $total_sales = $deduct - $reverseDed + $otDeduct - $otReverse + $dfd;

    // Return Qty — reverse_deduct entries are already netted into Sales
    // above (a return of a prior sale reduces net sales), but this page
    // also shows the gross return amount as its own column, same as before.
    $total_sales_return = $reverseDed + $otReverse;

    // Internal Transfer Qty — outbound godown-to-godown/warehouse-to-
    // warehouse transfer_out, excluding demofree's transfer_out (already
    // counted in Sales above). Net of transfer_out_reverse.
    $transferOut   = (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out' AND ref_type != 'demofree'");
    $transferOutRv = (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out_reverse' AND ref_type != 'demofree'");
    $internal_transfer = $transferOut - $transferOutRv;

    // Movement to CP — Partner-Location <-> Godown transfers
    // (pl-godown-transfer-action.php) write their own ref_type/ref_id
    // ('PLT-xxxxx') through StockService like everything else, so they're
    // already included in transfer_out above; no separate query needed.
    $movement_to_cp = 0;

    // Neksomo "Purchase from Manufacturer" — StockService-based like
    // everything else here (ref_type='adjustment', note references the
    // manufacturer purchase), already included in $credit above when this
    // godown is Neksomo's. Kept as its own reported column via a dedicated
    // ref_id pattern match, same rows already counted in $input_qty (not
    // double-added to net_change).
    $manuf = 0;
    if ($showManufPurchases) {
        $manuf = $sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='credit' AND ref_id LIKE 'manuf_purchase_%'");
    }

    return [
        'input_qty'          => $input_qty,
        'total_sales'        => $total_sales,
        'total_sales_return' => $total_sales_return,
        'internal_transfer'  => $internal_transfer,
        'movement_to_cp'      => $movement_to_cp,
        'manuf_qty'          => $manuf,
        // Credits add to stock; sales (incl. demo/free/damage) and internal
        // transfer remove from it. Return Qty is informational only — its
        // effect is already netted into total_sales via reverse_deduct.
        'net_change'         => $input_qty - $total_sales - $internal_transfer,
    ];
}

// SQL fragment: "AND warehouse_id IN (...)" / "AND warehouse_id IS NULL" /
// combined via OR — applied to stock_ledger/stock queries only when a
// warehouse filter was actually submitted.
function warehouseLedgerCondition(bool $filterByWarehouse, array $selectedWarehouseIds, bool $includeUnassigned): string {
    if (!$filterByWarehouse) return '';
    $parts = [];
    if (!empty($selectedWarehouseIds)) $parts[] = 'warehouse_id IN (' . implode(',', $selectedWarehouseIds) . ')';
    if ($includeUnassigned) $parts[] = 'warehouse_id IS NULL';
    if (empty($parts)) return ' AND 1=0'; // filter submitted but nothing selected — show nothing rather than silently ignore it
    return ' AND (' . implode(' OR ', $parts) . ')';
}

// One warehouseLedgerCondition() fragment PER selected warehouse bucket,
// each scoped to exactly one warehouse (or Unassigned) rather than OR'd
// together — the building block for the per-warehouse breakdown. Returns
// [['label' => ..., 'cond' => ...], ...], "Unassigned" first (matching
// overall-stock.php's card ordering), then real warehouses by id.
// $warehouseNames is [id => label] as built by both callers from the
// `warehouses` table.
function datewiseWarehouseBuckets(array $selectedWarehouseIds, bool $includeUnassigned, array $warehouseNames): array {
    $buckets = [];
    if ($includeUnassigned) {
        $buckets[] = ['label' => 'Unassigned', 'cond' => warehouseLedgerCondition(true, [], true)];
    }
    foreach ($selectedWarehouseIds as $wid) {
        $buckets[] = ['label' => $warehouseNames[$wid] ?? "Warehouse #$wid", 'cond' => warehouseLedgerCondition(true, [$wid], false)];
    }
    return $buckets;
}

// Today's real closing_qty per product for one warehouse condition, summed
// across whichever godowns are in scope — the live `stock` table is always
// accurate, so this is the trusted anchor for the running-closing-balance
// seed (see overstock_datewise.php for the full rationale).
function datewiseTodayClosingByProduct($db_conn, bool $filterByGodown, string $companyIdsSql, string $warehouseCond): array {
    $out = [];
    $sql = "SELECT product_id, SUM(closing_qty) sum_closing FROM stock WHERE user_type='company'"
        . ($filterByGodown ? " AND user_id IN ($companyIdsSql)" : '')
        . $warehouseCond
        . " GROUP BY product_id";
    $res = mysqli_query($db_conn, $sql);
    while ($row = mysqli_fetch_assoc($res)) {
        $out[(int)$row['product_id']] = (int)$row['sum_closing'];
    }
    return $out;
}

// Running closing balance per product, seeded with today's real closing_qty
// minus the net movement from $fromDate through today (inclusive) — i.e.
// the balance as it stood right before $fromDate. Carried forward day by
// day by the caller's loop via computeStockMovement()'s net_change.
function datewiseSeedRunningClosing($db_conn, array $allProducts, string $fromDate, bool $filterByGodown, string $companyIdsSql, bool $showManufPurchases, string $warehouseCond): array {
    $todayClosingByProduct = datewiseTodayClosingByProduct($db_conn, $filterByGodown, $companyIdsSql, $warehouseCond);
    $runningClosing = [];
    $today = date('Y-m-d');
    foreach ($allProducts as $p) {
        $prid = (int)$p['id'];
        $todayClosing = $todayClosingByProduct[$prid] ?? 0;
        if ($fromDate <= $today) {
            $sinceFrom = computeStockMovement($db_conn, $prid, $fromDate, $today, $companyIdsSql, $filterByGodown, $showManufPurchases, $warehouseCond);
            $runningClosing[$prid] = $todayClosing - $sinceFrom['net_change'];
        } else {
            // Report starts in the future — nothing to unwind, start from today's balance.
            $runningClosing[$prid] = $todayClosing;
        }
    }
    return $runningClosing;
}
