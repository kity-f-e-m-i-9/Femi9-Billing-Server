<?php
declare(strict_types=1);

/**
 * Godown-to-godown stock move — a direct move of a product's stock from
 * one company profile to another (optionally tagging a physical
 * warehouse on each side), with NO invoice created. Deliberately
 * separate from Internal Transfer (internal_transfer/internal_transfer_
 * invoice) and Auto Transfer — this concept never touches either of
 * those tables or their code paths. Stock still moves through
 * StockService::transferOut()/transferIn() so stock_ledger and
 * stock_lots (FIFO costing) stay correct; only the invoice-table writes
 * are skipped.
 */

// Self-migrating — same convention as every other table added in this
// project. Its own audit trail (product, from/to company profile,
// from/to physical warehouse, qty, who, when) since there's no invoice
// record to look back at otherwise.
function ensure_company_godown_stock_moves_table(mysqli $db_conn): void
{
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS company_godown_stock_moves (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ref_id VARCHAR(64) NOT NULL,
            product_id INT NOT NULL,
            from_company_godown_id INT NOT NULL,
            to_company_godown_id INT NOT NULL,
            from_warehouse_id INT NULL,
            to_warehouse_id INT NULL,
            qty INT NOT NULL,
            note VARCHAR(255) NULL,
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_gsm_product (product_id),
            KEY idx_gsm_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

/**
 * Generates a unique reference id for one move — pairs the transferOut/
 * transferIn ledger entries the same way Auto Transfer's "AUTO..."
 * tempid does, but this is never shown to the user as an invoice/order
 * number since no invoice exists for this concept.
 */
function generate_godown_stock_move_ref_id(): string
{
    return 'GDNMOVE' . date('YmdHis') . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
}

/**
 * Records one completed move for the history page. Call AFTER the
 * StockService transferOut/transferIn calls have succeeded.
 */
function record_godown_stock_move(
    mysqli $db_conn,
    string $refId,
    int $productId,
    int $fromCompanyGodownId,
    int $toCompanyGodownId,
    ?int $fromWarehouseId,
    ?int $toWarehouseId,
    int $qty,
    ?string $note,
    ?string $createdBy
): void {
    ensure_company_godown_stock_moves_table($db_conn);
    $stmt = $db_conn->prepare(
        "INSERT INTO company_godown_stock_moves
            (ref_id, product_id, from_company_godown_id, to_company_godown_id,
             from_warehouse_id, to_warehouse_id, qty, note, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'siiiiiiss',
        $refId, $productId, $fromCompanyGodownId, $toCompanyGodownId,
        $fromWarehouseId, $toWarehouseId, $qty, $note, $createdBy
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * All recorded moves, most recent first, joined to product/company
 * profile/warehouse names for display — powers godown-stock-move-manage.php.
 *
 * @return array<int, array{
 *   id:int, ref_id:string, product_id:int, product_name:string,
 *   from_gname:string, to_gname:string,
 *   from_warehouse_code:?string, to_warehouse_code:?string,
 *   qty:int, note:?string, created_by:?string, created_at:string
 * }>
 */
function get_godown_stock_moves(mysqli $db_conn): array
{
    ensure_company_godown_stock_moves_table($db_conn);
    $stmt = $db_conn->prepare(
        "SELECT m.id, m.ref_id, m.product_id, p.productName,
                cgFrom.gname AS from_gname, cgTo.gname AS to_gname,
                whFrom.code AS from_warehouse_code, whTo.code AS to_warehouse_code,
                m.qty, m.note, m.created_by, m.created_at
         FROM company_godown_stock_moves m
         INNER JOIN products p ON p.id = m.product_id
         INNER JOIN company_godown cgFrom ON cgFrom.id = m.from_company_godown_id
         INNER JOIN company_godown cgTo ON cgTo.id = m.to_company_godown_id
         LEFT JOIN warehouses whFrom ON whFrom.id = m.from_warehouse_id
         LEFT JOIN warehouses whTo ON whTo.id = m.to_warehouse_id
         ORDER BY m.created_at DESC, m.id DESC"
    );
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(function ($row) {
        return [
            'id'                  => (int) $row['id'],
            'ref_id'              => $row['ref_id'],
            'product_id'          => (int) $row['product_id'],
            'product_name'        => $row['productName'],
            'from_gname'          => $row['from_gname'],
            'to_gname'            => $row['to_gname'],
            'from_warehouse_code' => $row['from_warehouse_code'],
            'to_warehouse_code'   => $row['to_warehouse_code'],
            'qty'                 => (int) $row['qty'],
            'note'                => $row['note'],
            'created_by'          => $row['created_by'],
            'created_at'          => $row['created_at'],
        ];
    }, $rows);
}
