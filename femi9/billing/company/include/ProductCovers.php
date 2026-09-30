<?php
declare(strict_types=1);

require_once __DIR__ . '/StockService.php'; // for StockException

/**
 * Packaging-cover stock tracking — the outer cover/wrapper a finished pack
 * ships in (e.g. a 330mm 9pc pack needs 9 raw pieces AND 1 cover). Unlike
 * raw_material_bundles, a cover is never broken down into pieces — it's
 * consumed as one whole unit per pack made, so this only ever needs a
 * running balance per (company_product_id, company_godown_id, warehouse_id),
 * not a bundle-style open/close lifecycle.
 *
 * received_qty / used_qty / damaged_qty are running totals; balance is
 * always received_qty - used_qty - damaged_qty, computed on read rather
 * than stored, so there's nothing to drift out of sync.
 *
 * Deliberately does NOT touch the `stock` table — covers are packaging
 * material, not a sellable/purchasable product in the existing stock
 * sense, so this is a wholly separate ledger (same reasoning as why
 * raw_material_bundles is additive bookkeeping on top of, not instead of,
 * StockService for the raw product itself — except covers have no
 * underlying stock row at all).
 */
function ensure_product_covers_table(mysqli $db_conn): void
{
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS product_covers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_product_id INT NOT NULL,
            company_godown_id INT NOT NULL,
            warehouse_id INT NULL,
            received_qty INT NOT NULL DEFAULT 0,
            used_qty INT NOT NULL DEFAULT 0,
            damaged_qty INT NOT NULL DEFAULT 0,
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_pc_key (company_product_id, company_godown_id, warehouse_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

/**
 * One row per operator-recorded receive/damage entry against a cover
 * balance — same reasoning as raw_material_bundle_adjustments: the
 * balance row only holds running totals, this preserves individual
 * qty + reason + note per entry for the audit trail.
 */
function ensure_product_cover_adjustments_table(mysqli $db_conn): void
{
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS product_cover_adjustments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cover_id INT NOT NULL,
            type ENUM('receive','damage','use') NOT NULL,
            reason_id INT NULL,
            qty INT NOT NULL,
            note VARCHAR(255) NULL,
            ref_id VARCHAR(64) NULL,
            entry_date DATE NOT NULL,
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_pca_cover (cover_id),
            KEY idx_pca_type (type),
            KEY idx_pca_date (entry_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    // Column guard for environments where the table already existed
    // before entry_date was added — same convention as every other
    // self-migrating ALTER in this project. Backfills existing rows to
    // their created_at date so history isn't left with an invalid date.
    $col = $db_conn->query("SHOW COLUMNS FROM product_cover_adjustments LIKE 'entry_date'");
    if ($col && $col->num_rows === 0) {
        $db_conn->query("ALTER TABLE product_cover_adjustments ADD COLUMN entry_date DATE NULL AFTER ref_id");
        $db_conn->query("UPDATE product_cover_adjustments SET entry_date = DATE(created_at) WHERE entry_date IS NULL");
        $db_conn->query("ALTER TABLE product_cover_adjustments MODIFY COLUMN entry_date DATE NOT NULL, ADD KEY idx_pca_date (entry_date)");
    }

    // Widen the type enum for environments where 'use' didn't exist yet
    // (see consume_covers(), which now logs an audit row too instead of
    // only touching the running total).
    $typeCol = $db_conn->query("SHOW COLUMNS FROM product_cover_adjustments LIKE 'type'")->fetch_assoc();
    if ($typeCol && strpos($typeCol['Type'], "'use'") === false) {
        $db_conn->query("ALTER TABLE product_cover_adjustments MODIFY COLUMN type ENUM('receive','damage','use') NOT NULL");
    }
}

/**
 * Finds (or creates, at zero balance) the cover balance row for one
 * (company_product_id, company_godown_id, warehouse_id). Locks it FOR
 * UPDATE so callers that go on to mutate it can't race with a concurrent
 * receive/consume/damage against the same row.
 */
function get_or_create_cover_row(mysqli $db_conn, int $companyProductId, int $companyGodownId, ?int $warehouseId): array
{
    ensure_product_covers_table($db_conn);

    $stmt = $db_conn->prepare(
        "SELECT * FROM product_covers
         WHERE company_product_id = ? AND company_godown_id = ? AND warehouse_id <=> ? FOR UPDATE"
    );
    $stmt->bind_param('iii', $companyProductId, $companyGodownId, $warehouseId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        return $row;
    }

    $insStmt = $db_conn->prepare(
        "INSERT INTO product_covers (company_product_id, company_godown_id, warehouse_id)
         VALUES (?, ?, ?)"
    );
    $insStmt->bind_param('iii', $companyProductId, $companyGodownId, $warehouseId);
    $insStmt->execute();
    $newId = (int) $db_conn->insert_id;
    $insStmt->close();

    return [
        'id' => $newId, 'company_product_id' => $companyProductId,
        'company_godown_id' => $companyGodownId, 'warehouse_id' => $warehouseId,
        'received_qty' => 0, 'used_qty' => 0, 'damaged_qty' => 0,
    ];
}

/**
 * Stock-in event — the operator has physically received N covers (e.g. a
 * box of covers for a given pack SKU). Purely additive bookkeeping, same
 * as create_raw_material_bundles(): does not touch the `stock` table.
 *
 * @return array{balance_after:int}
 */
function receive_covers(mysqli $db_conn, int $companyProductId, int $companyGodownId, ?int $warehouseId, int $qty, ?string $note, string $entryDate, ?string $refId, ?string $createdBy): array
{
    ensure_product_cover_adjustments_table($db_conn);
    if ($qty <= 0) {
        throw new StockException("Received quantity must be greater than zero.");
    }

    $cover = get_or_create_cover_row($db_conn, $companyProductId, $companyGodownId, $warehouseId);

    $updStmt = $db_conn->prepare("UPDATE product_covers SET received_qty = received_qty + ? WHERE id = ?");
    $updStmt->bind_param('ii', $qty, $cover['id']);
    $updStmt->execute();
    $updStmt->close();

    $logStmt = $db_conn->prepare(
        "INSERT INTO product_cover_adjustments (cover_id, type, qty, note, ref_id, entry_date, created_by)
         VALUES (?, 'receive', ?, ?, ?, ?, ?)"
    );
    $logStmt->bind_param('iissss', $cover['id'], $qty, $note, $refId, $entryDate, $createdBy);
    $logStmt->execute();
    $logStmt->close();

    $balanceAfter = (int) $cover['received_qty'] + $qty - (int) $cover['used_qty'] - (int) $cover['damaged_qty'];
    return ['balance_after' => $balanceAfter];
}

/**
 * Records N covers physically damaged/unusable — deducted from the
 * available balance and separately tallied in damaged_qty, same
 * reasoning as record_damaged_pieces() for raw material bundles.
 *
 * @throws StockException if no cover row exists yet for this key.
 * @return array{balance_after:int, damaged_total:int}
 */
function record_damaged_covers(mysqli $db_conn, int $companyProductId, int $companyGodownId, ?int $warehouseId, int $qty, ?int $reasonId, ?string $note, string $entryDate, ?string $recordedBy): array
{
    ensure_product_cover_adjustments_table($db_conn);
    if ($qty <= 0) {
        throw new StockException("Damaged quantity must be greater than zero.");
    }

    $cover = get_or_create_cover_row($db_conn, $companyProductId, $companyGodownId, $warehouseId);

    $updStmt = $db_conn->prepare("UPDATE product_covers SET damaged_qty = damaged_qty + ? WHERE id = ?");
    $updStmt->bind_param('ii', $qty, $cover['id']);
    $updStmt->execute();
    $updStmt->close();

    $logStmt = $db_conn->prepare(
        "INSERT INTO product_cover_adjustments (cover_id, type, reason_id, qty, note, entry_date, created_by)
         VALUES (?, 'damage', ?, ?, ?, ?, ?)"
    );
    $logStmt->bind_param('iiisss', $cover['id'], $reasonId, $qty, $note, $entryDate, $recordedBy);
    $logStmt->execute();
    $logStmt->close();

    $balanceAfter = (int) $cover['received_qty'] - (int) $cover['used_qty'] - (int) $cover['damaged_qty'] - $qty;
    return ['balance_after' => $balanceAfter, 'damaged_total' => (int) $cover['damaged_qty'] + $qty];
}

/**
 * Consumes N covers against a Pieces->Pack conversion — called with the
 * conversion's ACTUAL packs_made (never the requested count), since a
 * raw material bundle running short already caps packs_made below what
 * was requested, and covers must track that same real number.
 *
 * Hard-blocks the whole conversion if the cover balance can't cover it
 * (per the confirmed design decision — covers are a second independent
 * constraint, not silently capped like the bundle's own piece count),
 * so the caller's transaction rolls back the same way a StockException
 * from convert_from_raw_material_bundle() already does.
 *
 * @throws StockException if the available balance is less than $qty.
 * @return array{balance_after:int}
 */
function consume_covers(mysqli $db_conn, int $companyProductId, int $companyGodownId, ?int $warehouseId, int $qty, string $entryDate, string $refId, ?string $createdBy): array
{
    ensure_product_cover_adjustments_table($db_conn);
    if ($qty <= 0) {
        return ['balance_after' => get_cover_balance($db_conn, $companyProductId, $companyGodownId, $warehouseId)];
    }

    $cover = get_or_create_cover_row($db_conn, $companyProductId, $companyGodownId, $warehouseId);
    $balance = (int) $cover['received_qty'] - (int) $cover['used_qty'] - (int) $cover['damaged_qty'];

    if ($balance < $qty) {
        throw new StockException("Not enough covers in stock for this conversion — need $qty, only $balance available. Record cover stock via Input Stock → Covers first.");
    }

    $updStmt = $db_conn->prepare("UPDATE product_covers SET used_qty = used_qty + ? WHERE id = ?");
    $updStmt->bind_param('ii', $qty, $cover['id']);
    $updStmt->execute();
    $updStmt->close();

    // Logged so the "Used" column of a date-filtered balance view can
    // show activity within a range — consume_covers() previously only
    // touched the running total, leaving no per-date audit trail.
    $logStmt = $db_conn->prepare(
        "INSERT INTO product_cover_adjustments (cover_id, type, qty, ref_id, entry_date, created_by)
         VALUES (?, 'use', ?, ?, ?, ?)"
    );
    $logStmt->bind_param('iisss', $cover['id'], $qty, $refId, $entryDate, $createdBy);
    $logStmt->execute();
    $logStmt->close();

    return ['balance_after' => $balance - $qty];
}

/**
 * Current available balance (received - used - damaged) for one cover
 * key, 0 if no row exists yet. Read-only — does not lock or create a row.
 */
function get_cover_balance(mysqli $db_conn, int $companyProductId, int $companyGodownId, ?int $warehouseId): int
{
    ensure_product_covers_table($db_conn);
    $stmt = $db_conn->prepare(
        "SELECT received_qty, used_qty, damaged_qty FROM product_covers
         WHERE company_product_id = ? AND company_godown_id = ? AND warehouse_id <=> ?"
    );
    $stmt->bind_param('iii', $companyProductId, $companyGodownId, $warehouseId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return 0;
    }
    return (int) $row['received_qty'] - (int) $row['used_qty'] - (int) $row['damaged_qty'];
}

/**
 * Every cover balance row, for the Manage Covers listing — one row per
 * (company_product_id, company_godown_id, warehouse_id) with its product
 * name joined in, newest first.
 *
 * When $fromDate/$toDate are given, received_qty/used_qty/damaged_qty are
 * recomputed from product_cover_adjustments WITHIN that date range
 * instead of the row's live running totals — balance is always the
 * live current total regardless (see manage-covers.php's date filter:
 * "activity in range, balance = current on hand", not a reconstructed
 * as-of-date balance).
 *
 * @return array<int, array{id:int, productName:string, company_godown_id:int, warehouse_id:?int, received_qty:int, used_qty:int, damaged_qty:int, balance:int}>
 */
function get_all_product_covers(mysqli $db_conn, ?string $fromDate = null, ?string $toDate = null): array
{
    ensure_product_covers_table($db_conn);
    $rows = $db_conn->query(
        "SELECT c.*, p.productName
         FROM product_covers c
         INNER JOIN products p ON p.id = c.company_product_id
         ORDER BY c.created_at DESC, c.id DESC"
    )->fetch_all(MYSQLI_ASSOC);

    $activityByCoverId = ($fromDate || $toDate)
        ? get_cover_activity_totals($db_conn, array_column($rows, 'id'), $fromDate, $toDate)
        : null;

    return array_map(function ($row) use ($activityByCoverId) {
        $coverId = (int) $row['id'];
        $received = (int) $row['received_qty'];
        $used     = (int) $row['used_qty'];
        $damaged  = (int) $row['damaged_qty'];

        if ($activityByCoverId !== null) {
            $activity = $activityByCoverId[$coverId] ?? ['receive' => 0, 'use' => 0, 'damage' => 0];
            $received = $activity['receive'];
            $used     = $activity['use'];
            $damaged  = $activity['damage'];
        }

        return [
            'id'                 => $coverId,
            'productName'        => $row['productName'],
            'company_godown_id'  => (int) $row['company_godown_id'],
            'warehouse_id'       => $row['warehouse_id'] !== null ? (int) $row['warehouse_id'] : null,
            'received_qty'       => $received,
            'used_qty'           => $used,
            'damaged_qty'        => $damaged,
            'balance'            => (int) $row['received_qty'] - (int) $row['used_qty'] - (int) $row['damaged_qty'],
        ];
    }, $rows);
}

/**
 * Sums receive/use/damage qty per cover_id within [$fromDate, $toDate]
 * (either bound optional), keyed by cover_id — powers get_all_product_
 * covers()'s date filter. Returns an empty array if $coverIds is empty
 * rather than running a malformed "IN ()" query.
 *
 * @param int[] $coverIds
 * @return array<int, array{receive:int, use:int, damage:int}>
 */
function get_cover_activity_totals(mysqli $db_conn, array $coverIds, ?string $fromDate, ?string $toDate): array
{
    if (empty($coverIds)) {
        return [];
    }
    ensure_product_cover_adjustments_table($db_conn);

    $placeholders = implode(',', array_fill(0, count($coverIds), '?'));
    $sql = "SELECT cover_id, type, SUM(qty) AS total_qty FROM product_cover_adjustments
            WHERE cover_id IN ($placeholders)";
    $types = str_repeat('i', count($coverIds));
    $params = $coverIds;

    if ($fromDate) { $sql .= " AND entry_date >= ?"; $types .= 's'; $params[] = $fromDate; }
    if ($toDate)   { $sql .= " AND entry_date <= ?"; $types .= 's'; $params[] = $toDate; }
    $sql .= " GROUP BY cover_id, type";

    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $result = [];
    foreach ($rows as $row) {
        $coverId = (int) $row['cover_id'];
        if (!isset($result[$coverId])) {
            $result[$coverId] = ['receive' => 0, 'use' => 0, 'damage' => 0];
        }
        $result[$coverId][$row['type']] = (int) $row['total_qty'];
    }
    return $result;
}
