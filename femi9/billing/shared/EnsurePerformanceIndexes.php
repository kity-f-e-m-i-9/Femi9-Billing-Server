<?php
// A DB reimport (phpMyAdmin/cPanel export+import) silently drops every
// custom index that wasn't part of the original schema dump — this has
// caused company login to hang for minutes, more than once, because
// invoice/user_invoice/ms_orders/ot_sales lost the indexes their queries
// depend on. Rather than remembering to re-run a .sql file by hand after
// every reimport, this checks on its own and re-creates whatever's missing.
//
// Cost is kept near-zero: one metadata query against information_schema,
// gated behind a 10-minute file marker so it only actually runs once every
// 10 minutes across the whole app, not on every single request.

function ensurePerformanceIndexes(mysqli $db_conn): void {
    static $checkedThisRequest = false;
    if ($checkedThisRequest) { return; }
    $checkedThisRequest = true;

    $markerFile = sys_get_temp_dir() . '/femi9_perf_index_check.flag';
    if (is_file($markerFile) && (time() - filemtime($markerFile)) < 600) {
        return;
    }
    @touch($markerFile);

    $indexes = [
        'invoice' => [
            'idx_invoice_inv_id'         => 'ADD INDEX idx_invoice_inv_id (inv_id)',
            'idx_invoice_user_type_date' => 'ADD INDEX idx_invoice_user_type_date (user_type, date, sub_total)',
            'idx_invoice_user_id'        => 'ADD INDEX idx_invoice_user_id (user_type, user_id, date)',
        ],
        'user_invoice' => [
            'idx_ui_to_user_id' => "ADD INDEX idx_ui_to_user_id (to_user_type(50), to_user_id(50), from_user_type(50), date)",
        ],
        'ms_orders' => [
            'idx_shop_date'  => 'ADD INDEX idx_shop_date (shop_id, order_date)',
            'idx_ms_id'      => 'ADD INDEX idx_ms_id (ms_id)',
            'idx_tp_id'      => 'ADD INDEX idx_tp_id (tp_id)',
            'idx_order_date' => 'ADD INDEX idx_order_date (order_date)',
        ],
        'ms_shop' => [
            'idx_district_node_id' => 'ADD INDEX idx_district_node_id (district_node_id)',
            'idx_taluk_node_id'    => 'ADD INDEX idx_taluk_node_id (taluk_node_id)',
        ],
        'ot_sales' => [
            'idx_ot_sales_tempid'      => 'ADD INDEX idx_ot_sales_tempid (tempid)',
            'idx_ot_sales_date'        => 'ADD INDEX idx_ot_sales_date (date)',
            'idx_ot_sales_tempid_prid' => 'ADD INDEX idx_ot_sales_tempid_prid (tempid, prid)',
        ],
        'ot_sales_invoice' => [
            'idx_ot_sales_invoice_tempid' => 'ADD INDEX idx_ot_sales_invoice_tempid (tempid)',
        ],
    ];

    $existing = [];
    $res = @$db_conn->query("SELECT DISTINCT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $existing[$row['TABLE_NAME']][$row['INDEX_NAME']] = true;
        }
        $res->free();
    } else {
        return; // couldn't read metadata this round; try again next marker window
    }

    foreach ($indexes as $table => $idxList) {
        foreach ($idxList as $idxName => $ddl) {
            if (empty($existing[$table][$idxName])) {
                // @-suppressed: if the table itself doesn't exist on some
                // install, this just fails quietly rather than breaking login.
                @$db_conn->query("ALTER TABLE `$table` $ddl");
            }
        }
    }
}
