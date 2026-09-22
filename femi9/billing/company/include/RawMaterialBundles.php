<?php
declare(strict_types=1);

require_once __DIR__ . '/StockService.php'; // for StockException

/**
 * Raw material bundle tracking — see docs/superpowers/specs/
 * 2026-09-22-raw-material-bundle-tracking-design.md.
 *
 * A bundle is one physical unit of raw Neksomo material, nominally
 * holding a fixed piece count that's never actually known until the
 * bundle is fully drawn down by Convert Pieces<->Packs conversions.
 * remaining_pieces starts at nominal_pieces.
 *
 * A mapped product's Pieces -> Pack conversion is limited SOLELY by the
 * selected bundle's own remaining_pieces — there is no separate pooled
 * stock.closing_qty check involved. convert_from_raw_material_bundle()
 * caps at whole packs the bundle can actually support and NEVER lets
 * remaining_pieces go negative from a conversion — once it hits 0, no
 * more packs can be made from that bundle until the operator explicitly
 * intervenes (see below).
 *
 * Two manual, operator-initiated adjustments exist on top of conversion:
 *  - record_damaged_pieces(): the operator found N pieces physically
 *    ruined (can never become a pack). Deducts N from remaining_pieces
 *    immediately and separately tallies it in damaged_pieces, so a
 *    later shortage-on-close can be attributed to damage rather than
 *    the bundle simply having fewer pieces than nominal.
 *  - add_extra_pieces_to_bundle(): conversion hard-stopped at 0 but the
 *    operator knows the bundle physically still has material (it held
 *    more than nominal) — explicitly adds N pieces back so conversion
 *    can continue. Tallied in added_extra_pieces so a bundle closed
 *    with 0 remaining but a non-zero added_extra_pieces is recognizably
 *    an EXCESS bundle (see get_raw_material_bundles()'s variance logic),
 *    not just an exact match.
 *
 * Closing a bundle (close_raw_material_bundle()) freezes its final
 * remaining_pieces as the shortage signal (positive = short) — a
 * bundle only ever shows as "excess" via added_extra_pieces having been
 * used at least once, since remaining_pieces itself can no longer go
 * negative under this model.
 */

// Self-migrating — same convention as every other table in this project.
function ensure_raw_material_bundles_table(mysqli $db_conn): void
{
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS raw_material_bundles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            raw_product_id INT NOT NULL,
            company_godown_id INT NOT NULL,
            warehouse_id INT NULL,
            nominal_pieces INT NOT NULL,
            remaining_pieces INT NOT NULL,
            damaged_pieces INT NOT NULL DEFAULT 0,
            added_extra_pieces INT NOT NULL DEFAULT 0,
            status ENUM('open','closed') NOT NULL DEFAULT 'open',
            input_ref_id VARCHAR(64) NULL,
            closed_by VARCHAR(100) NULL,
            closed_at TIMESTAMP NULL,
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_rmb_raw_product (raw_product_id, company_godown_id, warehouse_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    // Column guard for environments where the table already existed
    // before damaged_pieces/added_extra_pieces were added — same
    // convention as every other self-migrating ALTER in this project.
    $col = $db_conn->query("SHOW COLUMNS FROM raw_material_bundles LIKE 'damaged_pieces'");
    if ($col && $col->num_rows === 0) {
        $db_conn->query("ALTER TABLE raw_material_bundles ADD COLUMN damaged_pieces INT NOT NULL DEFAULT 0 AFTER remaining_pieces");
    }
    $col2 = $db_conn->query("SHOW COLUMNS FROM raw_material_bundles LIKE 'added_extra_pieces'");
    if ($col2 && $col2->num_rows === 0) {
        $db_conn->query("ALTER TABLE raw_material_bundles ADD COLUMN added_extra_pieces INT NOT NULL DEFAULT 0 AFTER damaged_pieces");
    }
}

/**
 * Creates $bundleCount new open bundles, all sharing one $inputRefId
 * (links back to the Input Stock submission that created them, audit
 * trail only). Does NOT touch the `stock` table — the caller is
 * responsible for crediting stock.closing_qty separately (same
 * mechanism Add Purchase already uses), since bundle tracking is an
 * additive layer, not a stock-writing mechanism itself.
 *
 * @return int[] the new bundle ids
 */
function create_raw_material_bundles(
    mysqli $db_conn,
    int $rawProductId,
    int $companyGodownId,
    ?int $warehouseId,
    int $nominalPieces,
    int $bundleCount,
    string $inputRefId,
    ?string $createdBy
): array {
    ensure_raw_material_bundles_table($db_conn);
    $stmt = $db_conn->prepare(
        "INSERT INTO raw_material_bundles
            (raw_product_id, company_godown_id, warehouse_id, nominal_pieces, remaining_pieces, input_ref_id, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $ids = [];
    for ($i = 0; $i < $bundleCount; $i++) {
        $stmt->bind_param(
            'iiiiiss',
            $rawProductId, $companyGodownId, $warehouseId, $nominalPieces, $nominalPieces, $inputRefId, $createdBy
        );
        $stmt->execute();
        $ids[] = (int) $db_conn->insert_id;
    }
    $stmt->close();
    return $ids;
}

/**
 * Every OPEN bundle for one (raw product, company profile, warehouse)
 * combination, oldest first — powers Convert Pieces<->Packs' bundle
 * picker. warehouse_id uses NULL-safe equality (<=>) since "unassigned"
 * warehouse is itself a distinct, matchable identity, same convention
 * used throughout this app's warehouse-aware queries.
 *
 * @return array<int, array{id:int, label:string, remaining_pieces:int, nominal_pieces:int}>
 */
function get_open_raw_material_bundles(mysqli $db_conn, int $rawProductId, int $companyGodownId, ?int $warehouseId): array
{
    ensure_raw_material_bundles_table($db_conn);
    $stmt = $db_conn->prepare(
        "SELECT id, remaining_pieces, nominal_pieces, created_at FROM raw_material_bundles
         WHERE raw_product_id = ? AND company_godown_id = ? AND warehouse_id <=> ? AND status = 'open'
         ORDER BY created_at ASC, id ASC"
    );
    $stmt->bind_param('iii', $rawProductId, $companyGodownId, $warehouseId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(function ($row) {
        $date = date('d-M-Y', strtotime($row['created_at']));
        return [
            'id'               => (int) $row['id'],
            'label'            => "Bundle #{$row['id']} ($date)",
            'remaining_pieces' => (int) $row['remaining_pieces'],
            'nominal_pieces'   => (int) $row['nominal_pieces'],
        ];
    }, $rows);
}

/**
 * Decrements one bundle's remaining_pieces by $qtyPieces — allowed to
 * go negative (see file header). Must run inside the caller's
 * transaction, alongside the real StockService::deduct() call that
 * actually moves stock; this is bookkeeping only, never a gate.
 * No-op (returns false) if the bundle doesn't exist or is already
 * closed — callers should treat that as a validation failure upstream
 * (the picker should never offer a closed bundle, but this guards
 * against a stale page/double-submit).
 */
function draw_from_raw_material_bundle(mysqli $db_conn, int $bundleId, int $qtyPieces): bool
{
    ensure_raw_material_bundles_table($db_conn);
    $stmt = $db_conn->prepare(
        "UPDATE raw_material_bundles SET remaining_pieces = remaining_pieces - ?
         WHERE id = ? AND status = 'open'"
    );
    $stmt->bind_param('ii', $qtyPieces, $bundleId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected > 0;
}

/**
 * Draws whole packs' worth of pieces from one bundle, capped at what the
 * bundle actually has remaining — the bundle IS the hard limit for a
 * mapped product's Pieces -> Pack conversion (no pooled stock.closing_qty
 * check involved at all; see docs/superpowers/specs/2026-09-22-raw-
 * material-bundle-tracking-design.md). If the bundle can't fully cover
 * the requested pack count, converts only as many FULL packs as fit and
 * reports the shortfall — never partial-packs, never goes negative (that
 * would defeat the point of this being the hard limit).
 *
 * Locks the bundle row (FOR UPDATE) before computing the cap, so two
 * concurrent conversions against the same bundle can't both read a
 * stale remaining_pieces and over-draw it.
 *
 * @throws StockException if the bundle doesn't exist, isn't open, or
 *   already has 0 (or negative) pieces remaining — nothing to convert.
 * @return array{packs_made:int, pieces_used:int, remaining_after:int, requested_packs:int}
 */
function convert_from_raw_material_bundle(mysqli $db_conn, int $bundleId, int $piecesPerPack, int $requestedPacks): array
{
    ensure_raw_material_bundles_table($db_conn);

    $lockStmt = $db_conn->prepare(
        "SELECT remaining_pieces, status FROM raw_material_bundles WHERE id = ? FOR UPDATE"
    );
    $lockStmt->bind_param('i', $bundleId);
    $lockStmt->execute();
    $bundleRow = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$bundleRow || $bundleRow['status'] !== 'open') {
        throw new StockException("Selected raw material bundle is no longer available.");
    }

    $remaining = (int) $bundleRow['remaining_pieces'];
    if ($remaining <= 0) {
        throw new StockException("Selected bundle has no pieces remaining — close it and pick another bundle.");
    }

    $maxPacksFromBundle = intdiv($remaining, $piecesPerPack);
    $packsMade = min($requestedPacks, $maxPacksFromBundle);
    if ($packsMade <= 0) {
        throw new StockException("Selected bundle doesn't have enough pieces remaining for even one full pack.");
    }

    $piecesUsed = $packsMade * $piecesPerPack;
    $updStmt = $db_conn->prepare(
        "UPDATE raw_material_bundles SET remaining_pieces = remaining_pieces - ? WHERE id = ?"
    );
    $updStmt->bind_param('ii', $piecesUsed, $bundleId);
    $updStmt->execute();
    $updStmt->close();

    return [
        'packs_made'       => $packsMade,
        'pieces_used'      => $piecesUsed,
        'remaining_after'  => $remaining - $piecesUsed,
        'requested_packs'  => $requestedPacks,
    ];
}

/**
 * Records N pieces of this OPEN bundle as physically damaged/unusable —
 * deducts from remaining_pieces immediately (so future conversions
 * correctly see less available) and separately tallies the damaged
 * total, so a later shortage can be attributed to damage rather than
 * the bundle simply having fewer pieces than nominal. Allowed to bring
 * remaining_pieces below 0 (damage found after the bundle was already
 * fully converted is still worth recording for the audit trail — it's
 * not a conversion draw, so the "never negative" rule doesn't apply
 * here the same way).
 *
 * @throws StockException if the bundle doesn't exist or is already closed.
 * @return array{remaining_after:int, damaged_total:int}
 */
function record_damaged_pieces(mysqli $db_conn, int $bundleId, int $qtyPieces, ?string $note, ?string $recordedBy): array
{
    ensure_raw_material_bundles_table($db_conn);
    if ($qtyPieces <= 0) {
        throw new StockException("Damaged quantity must be greater than zero.");
    }

    $lockStmt = $db_conn->prepare(
        "SELECT remaining_pieces, damaged_pieces, status FROM raw_material_bundles WHERE id = ? FOR UPDATE"
    );
    $lockStmt->bind_param('i', $bundleId);
    $lockStmt->execute();
    $bundleRow = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$bundleRow || $bundleRow['status'] !== 'open') {
        throw new StockException("Selected bundle is not open — cannot record damage against it.");
    }

    $updStmt = $db_conn->prepare(
        "UPDATE raw_material_bundles SET remaining_pieces = remaining_pieces - ?, damaged_pieces = damaged_pieces + ? WHERE id = ?"
    );
    $updStmt->bind_param('iii', $qtyPieces, $qtyPieces, $bundleId);
    $updStmt->execute();
    $updStmt->close();

    return [
        'remaining_after' => (int) $bundleRow['remaining_pieces'] - $qtyPieces,
        'damaged_total'   => (int) $bundleRow['damaged_pieces'] + $qtyPieces,
    ];
}

/**
 * Adds N pieces back onto an OPEN bundle whose remaining_pieces has hit
 * (or is near) 0 but the operator knows real material is still
 * physically there — the bundle held more than its nominal count. This
 * is the ONLY way a bundle's excess gets discovered under the "bundle
 * is the hard conversion limit, never goes negative" model: the total
 * ever added this way (added_extra_pieces) is what marks a closed
 * bundle as excess (see get_raw_material_bundles()).
 *
 * @throws StockException if the bundle doesn't exist or is already closed.
 * @return array{remaining_after:int, added_extra_total:int}
 */
function add_extra_pieces_to_bundle(mysqli $db_conn, int $bundleId, int $qtyPieces, ?string $recordedBy): array
{
    ensure_raw_material_bundles_table($db_conn);
    if ($qtyPieces <= 0) {
        throw new StockException("Extra piece quantity must be greater than zero.");
    }

    $lockStmt = $db_conn->prepare(
        "SELECT remaining_pieces, added_extra_pieces, status FROM raw_material_bundles WHERE id = ? FOR UPDATE"
    );
    $lockStmt->bind_param('i', $bundleId);
    $lockStmt->execute();
    $bundleRow = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$bundleRow || $bundleRow['status'] !== 'open') {
        throw new StockException("Selected bundle is not open — cannot add pieces to it.");
    }

    $updStmt = $db_conn->prepare(
        "UPDATE raw_material_bundles SET remaining_pieces = remaining_pieces + ?, added_extra_pieces = added_extra_pieces + ? WHERE id = ?"
    );
    $updStmt->bind_param('iii', $qtyPieces, $qtyPieces, $bundleId);
    $updStmt->execute();
    $updStmt->close();

    return [
        'remaining_after'    => (int) $bundleRow['remaining_pieces'] + $qtyPieces,
        'added_extra_total'  => (int) $bundleRow['added_extra_pieces'] + $qtyPieces,
    ];
}

/**
 * Marks one bundle closed — its remaining_pieces at this moment becomes
 * the permanent shortage signal (positive = short by that amount);
 * excess is instead signaled by a non-zero added_extra_pieces total
 * (see add_extra_pieces_to_bundle()). No further draws or adjustments
 * can target this bundle afterward (get_open_raw_material_bundles()
 * filters status='open').
 */
function close_raw_material_bundle(mysqli $db_conn, int $bundleId, ?string $closedBy): bool
{
    ensure_raw_material_bundles_table($db_conn);
    $stmt = $db_conn->prepare(
        "UPDATE raw_material_bundles SET status = 'closed', closed_by = ?, closed_at = NOW()
         WHERE id = ? AND status = 'open'"
    );
    $stmt->bind_param('si', $closedBy, $bundleId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected > 0;
}

/**
 * Every bundle, most recent first, joined to product name for display —
 * powers raw-material-bundles-manage.php. Optionally filtered by raw
 * product id and/or status.
 *
 * @return array<int, array{
 *   id:int, label:string, raw_product_id:int, product_name:string,
 *   company_godown_id:int, gname:string, warehouse_code:?string,
 *   nominal_pieces:int, remaining_pieces:int, status:string,
 *   variance_label:?string, created_by:?string, created_at:string,
 *   closed_by:?string, closed_at:?string
 * }>
 */
function get_raw_material_bundles(mysqli $db_conn, ?int $rawProductId = null, ?string $status = null): array
{
    ensure_raw_material_bundles_table($db_conn);
    $where = [];
    $types = '';
    $params = [];
    if ($rawProductId !== null) {
        $where[] = 'b.raw_product_id = ?';
        $types .= 'i';
        $params[] = $rawProductId;
    }
    if ($status !== null) {
        $where[] = 'b.status = ?';
        $types .= 's';
        $params[] = $status;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $db_conn->prepare(
        "SELECT b.id, b.raw_product_id, p.productName, b.company_godown_id, cg.gname,
                w.code AS warehouse_code, b.nominal_pieces, b.remaining_pieces,
                b.damaged_pieces, b.added_extra_pieces, b.status,
                b.created_by, b.created_at, b.closed_by, b.closed_at
         FROM raw_material_bundles b
         INNER JOIN products p ON p.id = b.raw_product_id
         INNER JOIN company_godown cg ON cg.id = b.company_godown_id
         LEFT JOIN warehouses w ON w.id = b.warehouse_id
         $whereSql
         ORDER BY b.created_at DESC, b.id DESC"
    );
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(function ($row) {
        $date = date('d-M-Y', strtotime($row['created_at']));
        $remaining = (int) $row['remaining_pieces'];
        $damaged = (int) $row['damaged_pieces'];
        $addedExtra = (int) $row['added_extra_pieces'];

        // Excess is signaled by having ever added extra pieces (the only
        // way a bundle exceeding nominal gets discovered under the
        // "bundle is the hard conversion limit, never goes negative"
        // model) — remaining_pieces itself can no longer go negative, so
        // it's never used as the excess signal anymore. Shortage is
        // still remaining_pieces > 0 at close time. Both can theoretically
        // apply to the same bundle (added extra, then still ran short
        // afterward) — shown together when that happens.
        $varianceLabel = null;
        if ($row['status'] === 'closed') {
            $parts = [];
            if ($remaining > 0) $parts[] = "Short by $remaining";
            if ($addedExtra > 0) $parts[] = "Excess $addedExtra";
            $varianceLabel = $parts ? implode(', ', $parts) : 'Exact';
        }

        return [
            'id'                 => (int) $row['id'],
            'label'              => "Bundle #{$row['id']} ($date)",
            'raw_product_id'     => (int) $row['raw_product_id'],
            'product_name'       => $row['productName'],
            'company_godown_id'  => (int) $row['company_godown_id'],
            'gname'              => $row['gname'],
            'warehouse_code'     => $row['warehouse_code'],
            'nominal_pieces'     => (int) $row['nominal_pieces'],
            'remaining_pieces'   => $remaining,
            'damaged_pieces'     => $damaged,
            'added_extra_pieces' => $addedExtra,
            'status'             => $row['status'],
            'variance_label'     => $varianceLabel,
            'created_by'         => $row['created_by'],
            'created_at'         => $row['created_at'],
            'closed_by'          => $row['closed_by'],
            'closed_at'          => $row['closed_at'],
        ];
    }, $rows);
}
