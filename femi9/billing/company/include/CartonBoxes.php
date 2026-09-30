<?php
declare(strict_types=1);

require_once __DIR__ . '/StockService.php'; // for StockException

/**
 * Carton box stock tracking — fully standalone packaging stock (the
 * outer shipping carton, not the per-pack cover). Deliberately NOT
 * linked to Convert Pieces<->Packs or any other conversion flow: unlike
 * product_covers, nothing auto-consumes a carton. The operator records
 * receipts and damage here only; there is no consume_cartons() at all.
 *
 * Carton types are a small operator-managed master list (carton_box_types)
 * rather than tied to a specific pack product, since one carton type can
 * box up several different products and the two aren't meant to be
 * conflated — see product_covers (include/ProductCovers.php) for the
 * per-product packaging concept this is explicitly NOT.
 *
 * Balance is received_qty - used_qty - damaged_qty, computed on read.
 * used_qty is only ever touched by record_used_cartons() — a manual,
 * operator-initiated log entry (qty + date + optional remarks, no
 * automatic trigger from any order/conversion/dispatch flow, and no
 * reason dropdown since "used" isn't an anomaly the way damage is).
 */
function ensure_carton_box_types_table(mysqli $db_conn): void
{
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS carton_box_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_carton_type_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

// Separate from damage_reason_master by design (see file header) — kept
// as its own table rather than a shared type-parameterized one so this
// list can diverge from the bundle/cover damage reasons over time.
function ensure_carton_box_damage_reason_table(mysqli $db_conn): void
{
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS carton_box_damage_reason_master (
            id INT AUTO_INCREMENT PRIMARY KEY,
            label VARCHAR(150) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_carton_reason_label (label)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function ensure_carton_boxes_table(mysqli $db_conn): void
{
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS carton_boxes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            carton_type_id INT NOT NULL,
            company_godown_id INT NOT NULL,
            warehouse_id INT NULL,
            received_qty INT NOT NULL DEFAULT 0,
            used_qty INT NOT NULL DEFAULT 0,
            damaged_qty INT NOT NULL DEFAULT 0,
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_cb_key (carton_type_id, company_godown_id, warehouse_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function ensure_carton_box_adjustments_table(mysqli $db_conn): void
{
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS carton_box_adjustments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            carton_id INT NOT NULL,
            type ENUM('receive','damage','use') NOT NULL,
            reason_id INT NULL,
            qty INT NOT NULL,
            note VARCHAR(255) NULL,
            ref_id VARCHAR(64) NULL,
            entry_date DATE NOT NULL,
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_cba_carton (carton_id),
            KEY idx_cba_type (type),
            KEY idx_cba_date (entry_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    // Column guard for environments where the table already existed
    // before entry_date was added — same convention as every other
    // self-migrating ALTER in this project. Backfills existing rows to
    // their created_at date so history isn't left with an invalid date.
    $col = $db_conn->query("SHOW COLUMNS FROM carton_box_adjustments LIKE 'entry_date'");
    if ($col && $col->num_rows === 0) {
        $db_conn->query("ALTER TABLE carton_box_adjustments ADD COLUMN entry_date DATE NULL AFTER ref_id");
        $db_conn->query("UPDATE carton_box_adjustments SET entry_date = DATE(created_at) WHERE entry_date IS NULL");
        $db_conn->query("ALTER TABLE carton_box_adjustments MODIFY COLUMN entry_date DATE NOT NULL, ADD KEY idx_cba_date (entry_date)");
    }

    // Widen the type enum for environments where 'use' didn't exist yet
    // (see record_used_cartons()).
    $typeCol = $db_conn->query("SHOW COLUMNS FROM carton_box_adjustments LIKE 'type'")->fetch_assoc();
    if ($typeCol && strpos($typeCol['Type'], "'use'") === false) {
        $db_conn->query("ALTER TABLE carton_box_adjustments MODIFY COLUMN type ENUM('receive','damage','use') NOT NULL");
    }
}

function get_active_carton_types(mysqli $db_conn): array
{
    ensure_carton_box_types_table($db_conn);
    return $db_conn->query(
        "SELECT id, name FROM carton_box_types WHERE is_active = 1 ORDER BY name ASC"
    )->fetch_all(MYSQLI_ASSOC);
}

function get_active_carton_damage_reasons(mysqli $db_conn): array
{
    ensure_carton_box_damage_reason_table($db_conn);
    return $db_conn->query(
        "SELECT id, label FROM carton_box_damage_reason_master WHERE is_active = 1 ORDER BY label ASC"
    )->fetch_all(MYSQLI_ASSOC);
}

/**
 * Finds (or creates, at zero balance) the carton balance row for one
 * (carton_type_id, company_godown_id, warehouse_id). Locks it FOR UPDATE
 * so callers that go on to mutate it can't race with a concurrent
 * receive/damage against the same row.
 */
function get_or_create_carton_row(mysqli $db_conn, int $cartonTypeId, int $companyGodownId, ?int $warehouseId): array
{
    ensure_carton_boxes_table($db_conn);

    $stmt = $db_conn->prepare(
        "SELECT * FROM carton_boxes
         WHERE carton_type_id = ? AND company_godown_id = ? AND warehouse_id <=> ? FOR UPDATE"
    );
    $stmt->bind_param('iii', $cartonTypeId, $companyGodownId, $warehouseId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        return $row;
    }

    $insStmt = $db_conn->prepare(
        "INSERT INTO carton_boxes (carton_type_id, company_godown_id, warehouse_id)
         VALUES (?, ?, ?)"
    );
    $insStmt->bind_param('iii', $cartonTypeId, $companyGodownId, $warehouseId);
    $insStmt->execute();
    $newId = (int) $db_conn->insert_id;
    $insStmt->close();

    return [
        'id' => $newId, 'carton_type_id' => $cartonTypeId,
        'company_godown_id' => $companyGodownId, 'warehouse_id' => $warehouseId,
        'received_qty' => 0, 'used_qty' => 0, 'damaged_qty' => 0,
    ];
}

/**
 * Stock-in event — the operator has physically received N cartons of one
 * type. Purely additive bookkeeping; no `stock` table involvement, same
 * reasoning as receive_covers().
 *
 * @return array{balance_after:int}
 */
function receive_cartons(mysqli $db_conn, int $cartonTypeId, int $companyGodownId, ?int $warehouseId, int $qty, ?string $note, string $entryDate, ?string $refId, ?string $createdBy): array
{
    ensure_carton_box_adjustments_table($db_conn);
    if ($qty <= 0) {
        throw new StockException("Received quantity must be greater than zero.");
    }

    $carton = get_or_create_carton_row($db_conn, $cartonTypeId, $companyGodownId, $warehouseId);

    $updStmt = $db_conn->prepare("UPDATE carton_boxes SET received_qty = received_qty + ? WHERE id = ?");
    $updStmt->bind_param('ii', $qty, $carton['id']);
    $updStmt->execute();
    $updStmt->close();

    $logStmt = $db_conn->prepare(
        "INSERT INTO carton_box_adjustments (carton_id, type, qty, note, ref_id, entry_date, created_by)
         VALUES (?, 'receive', ?, ?, ?, ?, ?)"
    );
    $logStmt->bind_param('iissss', $carton['id'], $qty, $note, $refId, $entryDate, $createdBy);
    $logStmt->execute();
    $logStmt->close();

    $balanceAfter = (int) $carton['received_qty'] + $qty - (int) $carton['used_qty'] - (int) $carton['damaged_qty'];
    return ['balance_after' => $balanceAfter];
}

/**
 * Records N cartons physically damaged/unusable — deducted from the
 * available balance and separately tallied in damaged_qty, same
 * reasoning as record_damaged_covers().
 *
 * @return array{balance_after:int, damaged_total:int}
 */
function record_damaged_cartons(mysqli $db_conn, int $cartonTypeId, int $companyGodownId, ?int $warehouseId, int $qty, ?int $reasonId, ?string $note, string $entryDate, ?string $recordedBy): array
{
    ensure_carton_box_adjustments_table($db_conn);
    if ($qty <= 0) {
        throw new StockException("Damaged quantity must be greater than zero.");
    }

    $carton = get_or_create_carton_row($db_conn, $cartonTypeId, $companyGodownId, $warehouseId);

    $updStmt = $db_conn->prepare("UPDATE carton_boxes SET damaged_qty = damaged_qty + ? WHERE id = ?");
    $updStmt->bind_param('ii', $qty, $carton['id']);
    $updStmt->execute();
    $updStmt->close();

    $logStmt = $db_conn->prepare(
        "INSERT INTO carton_box_adjustments (carton_id, type, reason_id, qty, note, entry_date, created_by)
         VALUES (?, 'damage', ?, ?, ?, ?, ?)"
    );
    $logStmt->bind_param('iiisss', $carton['id'], $reasonId, $qty, $note, $entryDate, $recordedBy);
    $logStmt->execute();
    $logStmt->close();

    $balanceAfter = (int) $carton['received_qty'] - (int) $carton['used_qty'] - (int) $carton['damaged_qty'] - $qty;
    return ['balance_after' => $balanceAfter, 'damaged_total' => (int) $carton['damaged_qty'] + $qty];
}

/**
 * Records N cartons manually used — a plain operator-initiated log
 * entry (qty + date + optional remarks), not tied to any order,
 * conversion, or dispatch flow (see file header: cartons stay fully
 * standalone). Unlike record_damaged_cartons() — a discovered physical
 * fact the operator can't help finding out about after it happened —
 * "used" is consumption the operator directly controls, so it's blocked
 * from taking the balance negative rather than allowed through.
 *
 * @throws StockException if the available balance is less than $qty.
 * @return array{balance_after:int, used_total:int}
 */
function record_used_cartons(mysqli $db_conn, int $cartonTypeId, int $companyGodownId, ?int $warehouseId, int $qty, ?string $note, string $entryDate, ?string $recordedBy): array
{
    ensure_carton_box_adjustments_table($db_conn);
    if ($qty <= 0) {
        throw new StockException("Used quantity must be greater than zero.");
    }

    $carton = get_or_create_carton_row($db_conn, $cartonTypeId, $companyGodownId, $warehouseId);
    $balance = (int) $carton['received_qty'] - (int) $carton['used_qty'] - (int) $carton['damaged_qty'];

    if ($balance < $qty) {
        throw new StockException("Not enough cartons in stock — need $qty, only $balance available.");
    }

    $updStmt = $db_conn->prepare("UPDATE carton_boxes SET used_qty = used_qty + ? WHERE id = ?");
    $updStmt->bind_param('ii', $qty, $carton['id']);
    $updStmt->execute();
    $updStmt->close();

    $logStmt = $db_conn->prepare(
        "INSERT INTO carton_box_adjustments (carton_id, type, qty, note, entry_date, created_by)
         VALUES (?, 'use', ?, ?, ?, ?)"
    );
    $logStmt->bind_param('iisss', $carton['id'], $qty, $note, $entryDate, $recordedBy);
    $logStmt->execute();
    $logStmt->close();

    return ['balance_after' => $balance - $qty, 'used_total' => (int) $carton['used_qty'] + $qty];
}

/**
 * Every carton balance row, for the Manage Cartons listing — one row
 * per (carton_type_id, company_godown_id, warehouse_id) with its type
 * name joined in, newest first.
 *
 * When $fromDate/$toDate are given, received_qty/used_qty/damaged_qty
 * are recomputed from carton_box_adjustments WITHIN that date range
 * instead of the row's live running totals — balance is always the live
 * current total regardless (same "activity in range, balance = current
 * on hand" behavior as get_all_product_covers()).
 *
 * @return array<int, array{id:int, cartonTypeName:string, carton_type_id:int, company_godown_id:int, warehouse_id:?int, received_qty:int, used_qty:int, damaged_qty:int, balance:int}>
 */
function get_all_carton_boxes(mysqli $db_conn, ?string $fromDate = null, ?string $toDate = null): array
{
    ensure_carton_boxes_table($db_conn);
    ensure_carton_box_types_table($db_conn);
    $rows = $db_conn->query(
        "SELECT c.*, t.name AS carton_type_name
         FROM carton_boxes c
         INNER JOIN carton_box_types t ON t.id = c.carton_type_id
         ORDER BY c.created_at DESC, c.id DESC"
    )->fetch_all(MYSQLI_ASSOC);

    $activityByCartonId = ($fromDate || $toDate)
        ? get_carton_activity_totals($db_conn, array_column($rows, 'id'), $fromDate, $toDate)
        : null;

    return array_map(function ($row) use ($activityByCartonId) {
        $cartonId = (int) $row['id'];
        $received = (int) $row['received_qty'];
        $used     = (int) $row['used_qty'];
        $damaged  = (int) $row['damaged_qty'];

        if ($activityByCartonId !== null) {
            $activity = $activityByCartonId[$cartonId] ?? ['receive' => 0, 'use' => 0, 'damage' => 0];
            $received = $activity['receive'];
            $used     = $activity['use'];
            $damaged  = $activity['damage'];
        }

        return [
            'id'                 => $cartonId,
            'cartonTypeName'     => $row['carton_type_name'],
            'carton_type_id'     => (int) $row['carton_type_id'],
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
 * Sums receive/use/damage qty per carton_id within [$fromDate, $toDate]
 * (either bound optional), keyed by carton_id — powers get_all_carton_
 * boxes()'s date filter. Returns an empty array if $cartonIds is empty
 * rather than running a malformed "IN ()" query.
 *
 * @param int[] $cartonIds
 * @return array<int, array{receive:int, use:int, damage:int}>
 */
function get_carton_activity_totals(mysqli $db_conn, array $cartonIds, ?string $fromDate, ?string $toDate): array
{
    if (empty($cartonIds)) {
        return [];
    }
    ensure_carton_box_adjustments_table($db_conn);

    $placeholders = implode(',', array_fill(0, count($cartonIds), '?'));
    $sql = "SELECT carton_id, type, SUM(qty) AS total_qty FROM carton_box_adjustments
            WHERE carton_id IN ($placeholders)";
    $types = str_repeat('i', count($cartonIds));
    $params = $cartonIds;

    if ($fromDate) { $sql .= " AND entry_date >= ?"; $types .= 's'; $params[] = $fromDate; }
    if ($toDate)   { $sql .= " AND entry_date <= ?"; $types .= 's'; $params[] = $toDate; }
    $sql .= " GROUP BY carton_id, type";

    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $result = [];
    foreach ($rows as $row) {
        $cartonId = (int) $row['carton_id'];
        if (!isset($result[$cartonId])) {
            $result[$cartonId] = ['receive' => 0, 'use' => 0, 'damage' => 0];
        }
        $result[$cartonId][$row['type']] = (int) $row['total_qty'];
    }
    return $result;
}
