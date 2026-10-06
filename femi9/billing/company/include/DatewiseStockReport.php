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

    // Sales Qty — every deduction from this godown that represents an
    // actual sale: ordinary deduct (customer/TP/user invoices) and
    // ot_deduct (OT sales). Demo/Free/Damage is tracked and reported
    // separately below (dfd_qty), not folded into Sales.
    //
    // Deliberately gross, NOT netted against reverse_deduct/ot_reverse — a
    // return doesn't erase the fact that a sale happened, so Sales Qty
    // should keep showing what was actually sold regardless of later
    // returns. Return Qty (below) already reports returns as their own
    // column; a return should only ever reduce Closing Stock (via
    // $net_sales_for_closing below), never the displayed Sales figure
    // itself. Per explicit user correction — Sales was previously net of
    // returns here, which could show a negative/understated Sales Qty on
    // a day with more returns than fresh sales.
    $deduct      = $sumAction('deduct');
    $reverseDed  = $sumAction('reverse_deduct');
    $otDeduct    = $sumAction('ot_deduct');
    $otReverse   = $sumAction('ot_reverse');
    $total_sales = $deduct + $otDeduct;
    // Stock impact only — returns still reduce the sale's effect on
    // Closing Stock, exactly as before; this value isn't shown anywhere,
    // only fed into net_change below.
    $net_sales_for_closing = $deduct - $reverseDed + $otDeduct - $otReverse;

    // Demo/Free/Damage Qty — goods that left as demo, free giveaway, or
    // damage (demofree's transfer_out), net of transfer_out_reverse. Still
    // removes stock the same as a sale, but reported in its own column
    // rather than folded into Sales.
    $dfd_qty     = (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out' AND ref_type='demofree'")
                 - (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out_reverse' AND ref_type='demofree'");

    // Return Qty — reverse_deduct/ot_reverse entries shown as their own
    // column. No longer netted into the displayed Sales Qty (see
    // $total_sales above) — only still subtracted from Closing Stock via
    // $net_sales_for_closing.
    $total_sales_return = $reverseDed + $otReverse;

    // Movement to CP — the godown -> Channel Partner leg of Partner-
    // Location transfers (pl-godown-transfer-action.php, transfer_type=
    // 'godown_to_location'). Its outbound leg writes into stock_ledger like
    // any other transfer_out (ref_id = pl_godown_transfers.ref_number), so
    // it's identified by joining to that table rather than a ref_id string
    // pattern — a user-supplied ref_number isn't guaranteed to start with
    // "PLT-" (see pl-godown-transfer-action.php's $ref_input override).
    $baseSl = "sl.product_id=$prid AND sl.user_type='company'"
        . ($filterByGodown ? " AND sl.user_id IN ($companyIdsSql)" : '')
        . str_replace('warehouse_id', 'sl.warehouse_id', $warehouseCond)
        . " AND sl.created_at >= '$fromDate 00:00:00' AND sl.created_at <= '$toDate 23:59:59'";
    $sumCpAction = function($action) use ($sum, $baseSl) {
        return $sum("SELECT COALESCE(SUM(sl.qty),0) FROM stock_ledger sl
            INNER JOIN pl_godown_transfers t ON t.ref_number COLLATE utf8mb4_general_ci = sl.ref_id AND t.transfer_type = 'godown_to_location'
            WHERE $baseSl AND sl.action='$action'");
    };
    $movement_to_cp = $sumCpAction('transfer_out') - $sumCpAction('transfer_out_reverse');

    // Internal Transfer Qty — outbound godown-to-godown/warehouse-to-
    // warehouse transfer_out, excluding demofree's transfer_out (already
    // counted in Sales above) and excluding Movement to CP (reported in its
    // own column instead of double-counted here). Net of transfer_out_reverse.
    $transferOut   = (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out' AND ref_type != 'demofree'");
    $transferOutRv = (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out_reverse' AND ref_type != 'demofree'");
    $internal_transfer = ($transferOut - $transferOutRv) - $movement_to_cp;

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
        'dfd_qty'            => $dfd_qty,
        'internal_transfer'  => $internal_transfer,
        'movement_to_cp'      => $movement_to_cp,
        'manuf_qty'          => $manuf,
        // Credits add to stock; sales, Demo/Free/Damage, internal transfer,
        // and Movement to CP remove from it (movement_to_cp is reported in
        // its own column but is a real stock deduction, not folded into
        // internal_transfer any more — see above). Uses
        // $net_sales_for_closing (sales net of returns), NOT the displayed
        // $total_sales — Closing Stock still correctly reflects returns
        // even though the Sales Qty column no longer does.
        'net_change'         => $input_qty - $net_sales_for_closing - $dfd_qty - $internal_transfer - $movement_to_cp,
    ];
}

// Structured "from (company profile, warehouse) -> to (company profile,
// warehouse): qty" breakdown of the Internal Transfer Qty figure, for the
// click-to-view detail popup. Every transfer_out row counted in that number
// (see computeStockMovement()'s $internal_transfer — godown-to-godown
// internal transfers only — Movement to CP is excluded here and reported
// separately via datewiseCpMovementBreakdownRows()/$movement_to_cp) is
// included, resolved via a matching transfer_in row in stock_ledger sharing
// the same ref_id, to get the destination godown (i.user_id) and warehouse
// (i.warehouse_id). Rows belonging to a Partner-Location/CP transfer are
// excluded (NOT EXISTS against pl_godown_transfers) — those are one-legged
// in stock_ledger (godown_to_location only writes transfer_out here; the
// inbound leg goes to channel_partner_stock_ledger instead) and are covered
// by the dedicated CP breakdown function instead.
// NULL warehouse_id is labeled "Unassigned" to match the rest of the UI's
// convention. $godownCond/$warehouseCond/$dateCond mirror
// computeStockMovement()'s own scoping so the breakdown always matches the
// number it explains. Returns a list of
// ['from_godown','from_warehouse','to_godown','to_warehouse','qty'].
function datewiseTransferBreakdownRows($db_conn, int $prid, string $fromDate, string $toDate, string $companyIdsSql, bool $filterByGodown, string $warehouseCond, array $warehouseNames): array {
    $fromDate = mysqli_real_escape_string($db_conn, $fromDate);
    $toDate   = mysqli_real_escape_string($db_conn, $toDate);
    $godownCond = $filterByGodown ? " AND o.user_id IN ($companyIdsSql)" : '';
    $dateCond   = " AND o.created_at >= '$fromDate 00:00:00' AND o.created_at <= '$toDate 23:59:59'";
    // $warehouseCond is built for a bare "warehouse_id" column (see
    // warehouseLedgerCondition()) — applied to the outbound leg (o), which
    // is the leg every other Internal Transfer query in this file scopes by.
    $outWarehouseCond = str_replace('warehouse_id', 'o.warehouse_id', $warehouseCond);
    // Both transfer_out (+qty) and transfer_out_reverse (-qty, a transfer
    // later undone) are summed per (from, to, ref_id) so a reversed transfer
    // nets to 0 here too — otherwise this breakdown's total could exceed the
    // Internal Transfer Qty cell it's explaining, which already nets
    // transfer_out_reverse out (see computeStockMovement()'s
    // $internal_transfer above).
    $sql = "SELECT o.user_id AS from_godown_id, o.warehouse_id AS from_wh,
                   i.user_id AS to_godown_id, i.warehouse_id AS to_wh,
                   o.ref_id AS ref_id,
                   SUM(CASE WHEN o.action = 'transfer_out' THEN o.qty ELSE -o.qty END) AS qty
            FROM stock_ledger o
            LEFT JOIN stock_ledger i
                ON i.ref_id = o.ref_id AND i.product_id = o.product_id
               AND i.user_type = o.user_type AND i.action = 'transfer_in'
            WHERE o.product_id = $prid AND o.user_type = 'company'
              AND o.action IN ('transfer_out', 'transfer_out_reverse')
              AND o.ref_type != 'demofree'$godownCond$outWarehouseCond$dateCond
              AND NOT EXISTS (SELECT 1 FROM pl_godown_transfers t WHERE t.ref_number COLLATE utf8mb4_general_ci = o.ref_id AND t.transfer_type = 'godown_to_location')
            GROUP BY o.user_id, o.warehouse_id, i.user_id, i.warehouse_id, o.ref_id
            HAVING qty != 0
            ORDER BY qty DESC";
    $res = mysqli_query($db_conn, $sql);

    // Godown names for every id this query could reference — every godown
    // the product's stock_ledger rows touch, not just the ones passed in
    // $companyIdsSql (the destination side is often a different godown than
    // the one being viewed).
    $godownNames = [];
    $gres = mysqli_query($db_conn, "SELECT id, gname FROM company_godown");
    while ($grow = mysqli_fetch_assoc($gres)) { $godownNames[(int)$grow['id']] = $grow['gname']; }

    // Group in PHP by the full (from godown+warehouse, to godown+warehouse)
    // tuple so multiple ref_ids landing on the same pair combine into one
    // row instead of one per transfer.
    $totals = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $fromGodown    = $godownNames[(int)$row['from_godown_id']] ?? ('Company Profile #' . $row['from_godown_id']);
        $fromWarehouse = $row['from_wh'] !== null ? ($warehouseNames[(int)$row['from_wh']] ?? 'Warehouse #' . $row['from_wh']) : 'Unassigned';
        if ($row['to_godown_id'] !== null) {
            // Matching stock_ledger transfer_in row found — real godown-to-
            // godown internal transfer, destination is a known godown/warehouse.
            $toGodown    = $godownNames[(int)$row['to_godown_id']] ?? ('Company Profile #' . $row['to_godown_id']);
            $toWarehouse = $row['to_wh'] !== null ? ($warehouseNames[(int)$row['to_wh']] ?? 'Warehouse #' . $row['to_wh']) : 'Unassigned';
        } else {
            // No matching transfer_in leg — genuinely landed Unassigned at
            // the destination, same godown as the source (internal
            // transfers are always within-company; CP transfers are
            // already excluded above).
            $toGodown    = $fromGodown;
            $toWarehouse = 'Unassigned';
        }
        $key = "$fromGodown\u{0000}$fromWarehouse\u{0000}$toGodown\u{0000}$toWarehouse";
        $totals[$key] = ($totals[$key] ?? 0) + (int)$row['qty'];
    }
    arsort($totals);
    $out = [];
    foreach ($totals as $key => $qty) {
        [$fromGodown, $fromWarehouse, $toGodown, $toWarehouse] = explode("\u{0000}", $key);
        $out[] = ['from_godown' => $fromGodown, 'from_warehouse' => $fromWarehouse, 'to_godown' => $toGodown, 'to_warehouse' => $toWarehouse, 'qty' => $qty];
    }
    return $out;
}

// Return Qty for one product, scoped to a single warehouse condition (a
// bare "warehouse_id" fragment — see warehouseLedgerCondition()) — the same
// gross-return total that feeds computeStockMovement()'s
// $total_sales_return (reverse_deduct + ot_reverse: a regular invoice
// return credited back, or an OT-channel sale returned/deleted), but
// callable standalone for a card that isn't going through the day-by-day
// movement loop (e.g. the Current Stock tab's all-time cumulative view).
// This is what surfaces OT channel returns on that tab, which previously
// had no Return Qty column at all — only Sales Qty (net of returns).
function datewiseReturnTotal($db_conn, int $prid, string $fromDate, string $toDate, string $companyIdsSql, bool $filterByGodown, string $warehouseCond): int {
    $fromDate = mysqli_real_escape_string($db_conn, $fromDate);
    $toDate   = mysqli_real_escape_string($db_conn, $toDate);
    $godownCond = $filterByGodown ? " AND user_id IN ($companyIdsSql)" : '';
    $dateCond   = " AND created_at >= '$fromDate 00:00:00' AND created_at <= '$toDate 23:59:59'";
    $base       = "product_id=$prid AND user_type='company'$godownCond$warehouseCond$dateCond";
    $sum = function($sql) use ($db_conn) {
        return (int)(mysqli_fetch_row(mysqli_query($db_conn, $sql))[0] ?? 0);
    };
    $reverseDed = $sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='reverse_deduct'");
    $otReverse  = $sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='ot_reverse'");
    return $reverseDed + $otReverse;
}

// Gross Sales Qty for one product, scoped to a single warehouse condition —
// deduct + ot_deduct, NOT netted against reverse_deduct/ot_reverse. Lets the
// Current Stock tab show the same "Sales isn't reduced by a later Return"
// figure as the Datewise tab's $total_sales (see computeStockMovement()'s
// own note on this), computed fresh from stock_ledger here instead of
// trusting the stored stock.sales_qty column — StockService.php's
// reverseDeduct()/otReverse() both floor-decrement that stored column on
// every return (see reverseDeduct()'s own "Floors sales_qty at 0"
// docblock), so it carries the same net-of-returns problem the Datewise fix
// corrected. Fixing
// it at the display layer here, same as that fix, rather than touching
// StockService's core write path — stock.sales_qty likely backs other
// reports beyond this one tab, and changing its stored meaning system-wide
// is a materially bigger, riskier change than recomputing it for display on
// this one page.
function datewiseGrossSalesTotal($db_conn, int $prid, string $fromDate, string $toDate, string $companyIdsSql, bool $filterByGodown, string $warehouseCond): int {
    $fromDate = mysqli_real_escape_string($db_conn, $fromDate);
    $toDate   = mysqli_real_escape_string($db_conn, $toDate);
    $godownCond = $filterByGodown ? " AND user_id IN ($companyIdsSql)" : '';
    $dateCond   = " AND created_at >= '$fromDate 00:00:00' AND created_at <= '$toDate 23:59:59'";
    $base       = "product_id=$prid AND user_type='company'$godownCond$warehouseCond$dateCond";
    $sum = function($sql) use ($db_conn) {
        return (int)(mysqli_fetch_row(mysqli_query($db_conn, $sql))[0] ?? 0);
    };
    $deduct   = $sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='deduct'");
    $otDeduct = $sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='ot_deduct'");
    return $deduct + $otDeduct;
}

// Internal Transfer Qty for one product, scoped to a single warehouse
// condition (a bare "warehouse_id" fragment — see warehouseLedgerCondition())
// — the same stock_ledger transfer_out total that feeds
// computeStockMovement()'s $internal_transfer, but callable standalone for
// a card that isn't going through the day-by-day movement loop (e.g. the
// Current Stock tab's all-time cumulative view). Net of transfer_out_reverse,
// excludes demofree (reported separately as DFD) and Movement to CP
// (reported separately via datewiseCpMovementTotal()).
function datewiseTransferOutTotal($db_conn, int $prid, string $fromDate, string $toDate, string $companyIdsSql, bool $filterByGodown, string $warehouseCond): int {
    $fromDate = mysqli_real_escape_string($db_conn, $fromDate);
    $toDate   = mysqli_real_escape_string($db_conn, $toDate);
    $godownCond = $filterByGodown ? " AND user_id IN ($companyIdsSql)" : '';
    $dateCond   = " AND created_at >= '$fromDate 00:00:00' AND created_at <= '$toDate 23:59:59'";
    $base       = "product_id=$prid AND user_type='company'$godownCond$warehouseCond$dateCond";
    $sum = function($sql) use ($db_conn) {
        return (int)(mysqli_fetch_row(mysqli_query($db_conn, $sql))[0] ?? 0);
    };
    $out = $sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out' AND ref_type != 'demofree'");
    $outRv = $sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out_reverse' AND ref_type != 'demofree'");
    return ($out - $outRv) - datewiseCpMovementTotal($db_conn, $prid, $fromDate, $toDate, $companyIdsSql, $filterByGodown, $warehouseCond);
}

// Movement to CP total for one product — the godown -> Channel Partner leg
// of Partner-Location transfers (transfer_type='godown_to_location'),
// identified by joining stock_ledger.ref_id to
// pl_godown_transfers.ref_number (see computeStockMovement()'s
// $movement_to_cp for the full rationale). Callable standalone for a card
// that isn't going through the day-by-day movement loop.
function datewiseCpMovementTotal($db_conn, int $prid, string $fromDate, string $toDate, string $companyIdsSql, bool $filterByGodown, string $warehouseCond): int {
    $fromDate = mysqli_real_escape_string($db_conn, $fromDate);
    $toDate   = mysqli_real_escape_string($db_conn, $toDate);
    $baseSl = "sl.product_id=$prid AND sl.user_type='company'"
        . ($filterByGodown ? " AND sl.user_id IN ($companyIdsSql)" : '')
        . str_replace('warehouse_id', 'sl.warehouse_id', $warehouseCond)
        . " AND sl.created_at >= '$fromDate 00:00:00' AND sl.created_at <= '$toDate 23:59:59'";
    $sum = function($sql) use ($db_conn) {
        return (int)(mysqli_fetch_row(mysqli_query($db_conn, $sql))[0] ?? 0);
    };
    $sumCpAction = function($action) use ($sum, $baseSl) {
        return $sum("SELECT COALESCE(SUM(sl.qty),0) FROM stock_ledger sl
            INNER JOIN pl_godown_transfers t ON t.ref_number COLLATE utf8mb4_general_ci = sl.ref_id AND t.transfer_type = 'godown_to_location'
            WHERE $baseSl AND sl.action='$action'");
    };
    return $sumCpAction('transfer_out') - $sumCpAction('transfer_out_reverse');
}

// Structured "from (company profile, warehouse) -> Channel Partner: qty"
// breakdown of the Movement to CP figure, for the click-to-view detail
// popup — same idea as datewiseTransferBreakdownRows() but for the CP leg.
// Returns a list of ['from_godown','from_warehouse','cp_name','qty'].
function datewiseCpMovementBreakdownRows($db_conn, int $prid, string $fromDate, string $toDate, string $companyIdsSql, bool $filterByGodown, string $warehouseCond, array $warehouseNames): array {
    $fromDate = mysqli_real_escape_string($db_conn, $fromDate);
    $toDate   = mysqli_real_escape_string($db_conn, $toDate);
    $baseSl = "sl.product_id=$prid AND sl.user_type='company'"
        . ($filterByGodown ? " AND sl.user_id IN ($companyIdsSql)" : '')
        . str_replace('warehouse_id', 'sl.warehouse_id', $warehouseCond)
        . " AND sl.created_at >= '$fromDate 00:00:00' AND sl.created_at <= '$toDate 23:59:59'";
    // Both transfer_out (+qty) and transfer_out_reverse (-qty, a CP
    // movement later undone) are summed so a reversed movement nets to 0
    // here too — see datewiseTransferBreakdownRows() for why this matters
    // (this popup's total must match datewiseCpMovementTotal()'s own
    // transfer_out - transfer_out_reverse netting).
    $sql = "SELECT sl.user_id AS from_godown_id, sl.warehouse_id AS from_wh, cp.name AS cp_name,
                   SUM(CASE WHEN sl.action = 'transfer_out' THEN sl.qty ELSE -sl.qty END) AS qty
            FROM stock_ledger sl
            INNER JOIN pl_godown_transfers t ON t.ref_number COLLATE utf8mb4_general_ci = sl.ref_id AND t.transfer_type = 'godown_to_location'
            LEFT JOIN channel_partners cp ON cp.id = t.cp_id
            WHERE $baseSl AND sl.action IN ('transfer_out', 'transfer_out_reverse')
            GROUP BY sl.user_id, sl.warehouse_id, cp.name
            HAVING qty != 0
            ORDER BY qty DESC";
    $res = mysqli_query($db_conn, $sql);

    $godownNames = [];
    $gres = mysqli_query($db_conn, "SELECT id, gname FROM company_godown");
    while ($grow = mysqli_fetch_assoc($gres)) { $godownNames[(int)$grow['id']] = $grow['gname']; }

    $out = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $fromGodown    = $godownNames[(int)$row['from_godown_id']] ?? ('Company Profile #' . $row['from_godown_id']);
        $fromWarehouse = $row['from_wh'] !== null ? ($warehouseNames[(int)$row['from_wh']] ?? 'Warehouse #' . $row['from_wh']) : 'Unassigned';
        $cpName        = $row['cp_name'] !== null ? $row['cp_name'] : 'Unknown Channel Partner';
        $out[] = ['from_godown' => $fromGodown, 'from_warehouse' => $fromWarehouse, 'cp_name' => $cpName, 'qty' => (int)$row['qty']];
    }
    return $out;
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
        $buckets[] = ['label' => 'Unassigned', 'cond' => warehouseLedgerCondition(true, [], true), 'whParam' => 'unassigned'];
    }
    foreach ($selectedWarehouseIds as $wid) {
        $buckets[] = ['label' => $warehouseNames[$wid] ?? "Warehouse #$wid", 'cond' => warehouseLedgerCondition(true, [$wid], false), 'whParam' => (string)$wid];
    }
    return $buckets;
}

// One bucket PER selected Company Profile (godown), each scoped to exactly
// one godown id rather than OR'd together via "user_id IN (...)" — the
// building block for the per-godown breakdown, same idea as
// datewiseWarehouseBuckets() above. Returns [['label' => ..., 'ids_sql' =>
// '<id>'], ...] in selection order. $godownNames is [id => gname].
function datewiseGodownBuckets(array $selectedGodownIds, array $godownNames): array {
    $buckets = [];
    foreach ($selectedGodownIds as $gid) {
        $buckets[] = ['label' => $godownNames[$gid] ?? "Company Profile #$gid", 'ids_sql' => (string)$gid];
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
