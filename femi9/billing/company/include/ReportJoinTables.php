<?php
/**
 * Indexed stand-ins for the big "UNION ALL of every shop / distributor /
 * stockist ..." derived tables that the B2B state report joins to.
 *
 * Joined as derived tables those unions have no usable index, so every
 * invoice row was compared against tens of thousands of party rows (one
 * 8-day report ran 19+ minutes and exhausted the PHP pool, 2026-11-27).
 * Loading the same rows into a TEMPORARY table with a key on the join
 * columns keeps the result identical and makes each lookup indexed.
 *
 * Returns what to put after JOIN / FROM: the temporary table's name, or —
 * if it could not be built — the original "(<union>)" so the report still
 * works, only slowly. TEMPORARY tables are private to this connection and
 * creating them does not commit anything.
 *
 * $columns must be listed in the same order as the SELECT's columns;
 * $keyColumns are the join columns.
 */
function report_join_table(mysqli $db_conn, string $table, array $columns, array $keyColumns, string $selectSql): string
{
    try {
        $defs = [];
        foreach ($columns as $column) {
            $type   = in_array($column, $keyColumns, true) ? 'VARCHAR(255)' : 'TEXT';
            $defs[] = "`$column` $type NULL";
        }
        $defs[] = 'KEY k_join (`' . implode('`, `', $keyColumns) . '`)';

        $ok = $db_conn->query("DROP TEMPORARY TABLE IF EXISTS `$table`")
            && $db_conn->query(
                "CREATE TEMPORARY TABLE `$table` (" . implode(', ', $defs) . ")
                 ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            )
            // IGNORE: some party rows hold text such as 'Tamilnadu' in state_id /
            // district_id. A plain SELECT only warns when comparing those with a
            // number, but under strict mode INSERT ... SELECT aborts. IGNORE keeps
            // it a warning, so the rows picked are the same as the original query.
            // (No unique key here, so no row is ever dropped as a duplicate.)
            && $db_conn->query("INSERT IGNORE INTO `$table` $selectSql");

        if ($ok) {
            return "`$table`";
        }
        error_log("report_join_table($table) failed: " . $db_conn->error);
    } catch (\Throwable $e) {
        error_log("report_join_table($table) failed: " . $e->getMessage());
    }

    return "($selectSql)";
}
