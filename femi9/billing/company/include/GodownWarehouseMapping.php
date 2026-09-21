<?php
declare(strict_types=1);

/**
 * Many-to-many mapping between company profiles (company_godown rows —
 * LLP / Healthcare / Neksomo / etc.) and physical warehouses. A warehouse
 * can hold stock for several company profiles; a company profile can have
 * stock spread across several warehouses. Purely advisory — used to
 * filter warehouse picker dropdowns across the app, never to block a
 * write. An unmapped pair is simply absent from a filtered list, not an
 * error; callers fall back to the full warehouse list when a company
 * profile has no linked warehouses at all.
 */

// Self-migrating — same convention as every other table added in this
// project (e.g. ensure_auto_transfer_skip_table()). A checked-in
// migration file also exists for documentation/manual-apply convenience
// (db_migrations/2026_09_21_company_godown_warehouses.sql), but nothing
// depends on it having run.
function ensure_company_godown_warehouses_table(mysqli $db_conn): void
{
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS company_godown_warehouses (
            company_godown_id INT NOT NULL,
            warehouse_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (company_godown_id, warehouse_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

/**
 * Warehouses linked to one company profile, ordered by code. Empty array
 * if nothing is linked yet — callers apply their own empty-fallback
 * policy (typically: show the full active-warehouse list instead).
 *
 * @return array<int, array{id:int, code:string, name:?string}>
 */
function get_warehouses_for_godown(mysqli $db_conn, int $companyGodownId): array
{
    ensure_company_godown_warehouses_table($db_conn);
    $stmt = $db_conn->prepare(
        "SELECT w.id, w.code, w.name
         FROM company_godown_warehouses cgw
         INNER JOIN warehouses w ON w.id = cgw.warehouse_id
         WHERE cgw.company_godown_id = ? AND w.is_active = 1
         ORDER BY w.code ASC"
    );
    $stmt->bind_param('i', $companyGodownId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(fn($r) => [
        'id'   => (int) $r['id'],
        'code' => $r['code'],
        'name' => $r['name'],
    ], $rows);
}

/**
 * Company profiles linked to one warehouse, ordered by name — powers the
 * admin editor's per-warehouse checkbox list.
 *
 * @return array<int, array{id:int, gname:string}>
 */
function get_godowns_for_warehouse(mysqli $db_conn, int $warehouseId): array
{
    ensure_company_godown_warehouses_table($db_conn);
    $stmt = $db_conn->prepare(
        "SELECT cg.id, cg.gname
         FROM company_godown_warehouses cgw
         INNER JOIN company_godown cg ON cg.id = cgw.company_godown_id
         WHERE cgw.warehouse_id = ?
         ORDER BY cg.gname ASC"
    );
    $stmt->bind_param('i', $warehouseId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(fn($r) => [
        'id'    => (int) $r['id'],
        'gname' => $r['gname'],
    ], $rows);
}

/**
 * Replaces the full set of company profiles linked to one warehouse
 * (delete + re-insert) — simplest correct approach for a small admin
 * form where the whole checkbox set is resubmitted every save.
 *
 * @param int[] $companyGodownIds
 */
function set_godowns_for_warehouse(mysqli $db_conn, int $warehouseId, array $companyGodownIds): void
{
    ensure_company_godown_warehouses_table($db_conn);

    $del = $db_conn->prepare("DELETE FROM company_godown_warehouses WHERE warehouse_id = ?");
    $del->bind_param('i', $warehouseId);
    $del->execute();
    $del->close();

    if (empty($companyGodownIds)) {
        return;
    }

    $ins = $db_conn->prepare("INSERT INTO company_godown_warehouses (company_godown_id, warehouse_id) VALUES (?, ?)");
    foreach (array_unique(array_map('intval', $companyGodownIds)) as $cgId) {
        $ins->bind_param('ii', $cgId, $warehouseId);
        $ins->execute();
    }
    $ins->close();
}
