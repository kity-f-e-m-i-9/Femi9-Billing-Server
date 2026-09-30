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
 * Closing a bundle (close_raw_material_bundle()) with leftover
 * remaining_pieces does NOT record it as a lost shortage — the leftover
 * automatically CARRIES FORWARD into another bundle for the same
 * (raw_product_id, company_godown_id, warehouse_id): merged onto an
 * existing open bundle if one exists, otherwise a brand-new bundle is
 * created (nominal_pieces=0, since it's carried-over material, not a
 * fresh nominal intake) to hold it. The closed bundle's own record then
 * shows "Carried forward: N" (carried_to_bundle_id points at where it
 * went) instead of "Short by N" — a genuine, permanent shortage only
 * ever happens via record_damaged_pieces() explicitly writing it off
 * before close. Excess (added_extra_pieces having been used at least
 * once) still shows independently, since that's a real physical-count
 * discovery, not a leftover-routing concern.
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
            carried_to_bundle_id INT NULL,
            carried_from_bundle_id INT NULL,
            closed_by VARCHAR(100) NULL,
            closed_at TIMESTAMP NULL,
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_rmb_raw_product (raw_product_id, company_godown_id, warehouse_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    // Column guards for environments where the table already existed
    // before these columns were added — same convention as every other
    // self-migrating ALTER in this project.
    foreach ([
        'damaged_pieces'         => "INT NOT NULL DEFAULT 0 AFTER remaining_pieces",
        'added_extra_pieces'     => "INT NOT NULL DEFAULT 0 AFTER damaged_pieces",
        'carried_to_bundle_id'   => "INT NULL AFTER input_ref_id",
        'carried_from_bundle_id' => "INT NULL AFTER carried_to_bundle_id",
    ] as $columnName => $definition) {
        $col = $db_conn->query("SHOW COLUMNS FROM raw_material_bundles LIKE '$columnName'");
        if ($col && $col->num_rows === 0) {
            $db_conn->query("ALTER TABLE raw_material_bundles ADD COLUMN $columnName $definition");
        }
    }
}

/**
 * One row per bundle-sourced Pieces->Pack conversion draw — the only link
 * from a stock_ledger 'conversion' entry (identified by ref_id) back to
 * which raw_material_bundles row it drew pieces from. Needed so a
 * conversion can be found and undone later (see
 * manage-piece-pack-conversions.php): convert_from_raw_material_bundle()
 * itself only mutates the bundle row in place and returns a result array,
 * it never used to persist which bundle a given conversion came from.
 */
function ensure_raw_material_bundle_draws_table(mysqli $db_conn): void
{
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS raw_material_bundle_draws (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bundle_id INT NOT NULL,
            ref_id VARCHAR(64) NOT NULL,
            product_id INT NOT NULL,
            pieces_used INT NOT NULL,
            packs_made INT NOT NULL,
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_rmbd_bundle (bundle_id),
            KEY idx_rmbd_ref (ref_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

// Global, manageable reason lists for bundle adjustments — same pattern
// as machine_code_master (see include/MachineCodes.php). Separate tables
// per type (rather than one shared list) since damage and extra-found
// reasons are conceptually distinct and shouldn't cross-pollute each
// other's dropdown.
function ensure_bundle_reason_tables(mysqli $db_conn): void
{
    foreach (['damage_reason_master', 'extra_reason_master'] as $table) {
        $db_conn->query(
            "CREATE TABLE IF NOT EXISTS $table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                label VARCHAR(150) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_reason_label (label)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }
}

function get_active_bundle_reasons(mysqli $db_conn, string $type): array
{
    ensure_bundle_reason_tables($db_conn);
    $table = $type === 'damage' ? 'damage_reason_master' : 'extra_reason_master';
    return $db_conn->query(
        "SELECT id, label FROM $table WHERE is_active = 1 ORDER BY label ASC"
    )->fetch_all(MYSQLI_ASSOC);
}

/**
 * One row per operator-recorded damage/extra adjustment against a
 * bundle — preserves the individual qty + reason + note that
 * record_damaged_pieces()/add_extra_pieces_to_bundle() would otherwise
 * only fold into the bundle's running totals, losing the per-entry
 * detail (see those functions below, which both insert here).
 */
function ensure_raw_material_bundle_adjustments_table(mysqli $db_conn): void
{
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS raw_material_bundle_adjustments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bundle_id INT NOT NULL,
            type ENUM('damage','extra') NOT NULL,
            reason_id INT NULL,
            qty INT NOT NULL,
            note VARCHAR(255) NULL,
            ref_id VARCHAR(64) NULL,
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_rmba_bundle (bundle_id),
            KEY idx_rmba_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
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
        "SELECT b.id, b.remaining_pieces, b.nominal_pieces, b.created_at, p.productName
         FROM raw_material_bundles b
         INNER JOIN products p ON p.id = b.raw_product_id
         WHERE b.raw_product_id = ? AND b.company_godown_id = ? AND b.warehouse_id <=> ? AND b.status = 'open'
         ORDER BY b.created_at ASC, b.id ASC"
    );
    $stmt->bind_param('iii', $rawProductId, $companyGodownId, $warehouseId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(function ($row) {
        $date = date('d-M-Y', strtotime($row['created_at']));
        return [
            'id'               => (int) $row['id'],
            'label'            => "Bundle #{$row['id']} — {$row['productName']} ($date)",
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
function convert_from_raw_material_bundle(mysqli $db_conn, int $bundleId, int $productId, int $piecesPerPack, int $requestedPacks, string $refId, ?string $createdBy = null): array
{
    ensure_raw_material_bundles_table($db_conn);
    ensure_raw_material_bundle_draws_table($db_conn);

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

    // Record the draw so this conversion can be found and undone later by
    // ref_id (see manage-piece-pack-conversions.php) — the bundle row
    // itself only ever holds current totals, not per-conversion history.
    $drawStmt = $db_conn->prepare(
        "INSERT INTO raw_material_bundle_draws (bundle_id, ref_id, product_id, pieces_used, packs_made, created_by)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $drawStmt->bind_param('isiiis', $bundleId, $refId, $productId, $piecesUsed, $packsMade, $createdBy);
    $drawStmt->execute();
    $drawStmt->close();

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
 * Every call is also logged as its own row in
 * raw_material_bundle_adjustments (type='damage') with its reason and
 * note, so multiple damage entries against the same bundle — each for a
 * different reason — stay individually attributable instead of
 * collapsing into one opaque running total.
 *
 * @throws StockException if the bundle doesn't exist or is already closed.
 * @return array{remaining_after:int, damaged_total:int}
 */
function record_damaged_pieces(mysqli $db_conn, int $bundleId, int $qtyPieces, ?string $note, ?string $recordedBy, ?int $reasonId = null, ?string $refId = null): array
{
    ensure_raw_material_bundles_table($db_conn);
    ensure_raw_material_bundle_adjustments_table($db_conn);
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

    $logStmt = $db_conn->prepare(
        "INSERT INTO raw_material_bundle_adjustments (bundle_id, type, reason_id, qty, note, ref_id, created_by)
         VALUES (?, 'damage', ?, ?, ?, ?, ?)"
    );
    $logStmt->bind_param('iiisss', $bundleId, $reasonId, $qtyPieces, $note, $refId, $recordedBy);
    $logStmt->execute();
    $logStmt->close();

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
 * Every call is also logged as its own row in
 * raw_material_bundle_adjustments (type='extra') with its reason, so
 * multiple extra-found entries against the same bundle stay
 * individually attributable instead of collapsing into one opaque
 * running total.
 *
 * @throws StockException if the bundle doesn't exist or is already closed.
 * @return array{remaining_after:int, added_extra_total:int}
 */
function add_extra_pieces_to_bundle(mysqli $db_conn, int $bundleId, int $qtyPieces, ?string $recordedBy, ?int $reasonId = null, ?string $refId = null): array
{
    ensure_raw_material_bundles_table($db_conn);
    ensure_raw_material_bundle_adjustments_table($db_conn);
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

    $logStmt = $db_conn->prepare(
        "INSERT INTO raw_material_bundle_adjustments (bundle_id, type, reason_id, qty, ref_id, created_by)
         VALUES (?, 'extra', ?, ?, ?, ?)"
    );
    $logStmt->bind_param('iiiss', $bundleId, $reasonId, $qtyPieces, $refId, $recordedBy);
    $logStmt->execute();
    $logStmt->close();

    return [
        'remaining_after'    => (int) $bundleRow['remaining_pieces'] + $qtyPieces,
        'added_extra_total'  => (int) $bundleRow['added_extra_pieces'] + $qtyPieces,
    ];
}

/**
 * Deletes one damage/extra adjustment entry and reverses exactly what it
 * did to the bundle — the precise inverse of record_damaged_pieces() /
 * add_extra_pieces_to_bundle():
 *   damage entry: remaining_pieces += qty, damaged_pieces -= qty
 *   extra entry:  remaining_pieces -= qty, added_extra_pieces -= qty
 * Works on a closed bundle too (its remaining_pieces/damaged_pieces/
 * added_extra_pieces totals still get corrected even though status stays
 * closed) since correcting a mis-entered adjustment is a valid reason to
 * touch a closed bundle's books without reopening it.
 *
 * @throws StockException if no such adjustment entry exists, or (for an
 *   extra entry) reversing it would take remaining_pieces below 0 —
 *   meaning packs were already made using those extra pieces, so the
 *   entry can no longer be cleanly un-added.
 * @return array{bundle_id:int, type:string, qty:int}
 */
function delete_bundle_adjustment(mysqli $db_conn, int $adjustmentId): array
{
    ensure_raw_material_bundle_adjustments_table($db_conn);
    ensure_raw_material_bundles_table($db_conn);

    $lockStmt = $db_conn->prepare(
        "SELECT id, bundle_id, type, qty FROM raw_material_bundle_adjustments WHERE id = ? FOR UPDATE"
    );
    $lockStmt->bind_param('i', $adjustmentId);
    $lockStmt->execute();
    $adj = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$adj) {
        throw new StockException("Adjustment entry not found — it may already have been removed.");
    }

    $bundleId = (int) $adj['bundle_id'];
    $qty      = (int) $adj['qty'];
    $isDamage = $adj['type'] === 'damage';

    $bundleLockStmt = $db_conn->prepare(
        "SELECT remaining_pieces FROM raw_material_bundles WHERE id = ? FOR UPDATE"
    );
    $bundleLockStmt->bind_param('i', $bundleId);
    $bundleLockStmt->execute();
    $bundleRow = $bundleLockStmt->get_result()->fetch_assoc();
    $bundleLockStmt->close();

    if (!$bundleRow) {
        throw new StockException("The bundle this adjustment belongs to no longer exists.");
    }

    if ($isDamage) {
        $updStmt = $db_conn->prepare(
            "UPDATE raw_material_bundles SET remaining_pieces = remaining_pieces + ?, damaged_pieces = damaged_pieces - ? WHERE id = ?"
        );
        $updStmt->bind_param('iii', $qty, $qty, $bundleId);
        $updStmt->execute();
        $updStmt->close();
    } else {
        if ((int) $bundleRow['remaining_pieces'] < $qty) {
            throw new StockException("Cannot remove this extra-pieces entry — some of those pieces have already been converted into packs.");
        }
        $updStmt = $db_conn->prepare(
            "UPDATE raw_material_bundles SET remaining_pieces = remaining_pieces - ?, added_extra_pieces = added_extra_pieces - ? WHERE id = ?"
        );
        $updStmt->bind_param('iii', $qty, $qty, $bundleId);
        $updStmt->execute();
        $updStmt->close();
    }

    $delStmt = $db_conn->prepare("DELETE FROM raw_material_bundle_adjustments WHERE id = ?");
    $delStmt->bind_param('i', $adjustmentId);
    $delStmt->execute();
    $delStmt->close();

    return ['bundle_id' => $bundleId, 'type' => $adj['type'], 'qty' => $qty];
}

/**
 * Marks one bundle closed. If it still has remaining_pieces > 0, that
 * leftover is NEVER recorded as a lost shortage — it automatically
 * carries forward into another bundle for the same (raw_product_id,
 * company_godown_id, warehouse_id): merged onto the oldest other open
 * bundle if one exists, otherwise a brand-new bundle is created
 * (nominal_pieces=0, since it's carried-over material rather than a
 * fresh nominal intake) to hold it. carried_to_bundle_id/
 * carried_from_bundle_id link the two records for traceability. A
 * genuine permanent shortage only ever happens via
 * record_damaged_pieces() writing it off explicitly before close — see
 * file header and get_raw_material_bundles()'s variance logic, which
 * reads carried_to_bundle_id to show "Carried forward: N" instead of
 * "Short by N" whenever this path was taken.
 *
 * Runs the leftover-routing + close as one transaction — either both
 * happen or neither does, so a bundle is never left half-closed with
 * its leftover unaccounted for.
 *
 * @throws StockException if the bundle doesn't exist or is already closed.
 */
function close_raw_material_bundle(mysqli $db_conn, int $bundleId, ?string $closedBy): bool
{
    ensure_raw_material_bundles_table($db_conn);

    $lockStmt = $db_conn->prepare(
        "SELECT raw_product_id, company_godown_id, warehouse_id, remaining_pieces, status
         FROM raw_material_bundles WHERE id = ? FOR UPDATE"
    );
    $lockStmt->bind_param('i', $bundleId);
    $lockStmt->execute();
    $bundle = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$bundle || $bundle['status'] !== 'open') {
        return false;
    }

    $leftover = (int) $bundle['remaining_pieces'];
    $carriedToBundleId = null;

    if ($leftover > 0) {
        $rawProductId    = (int) $bundle['raw_product_id'];
        $companyGodownId = (int) $bundle['company_godown_id'];
        $warehouseId     = $bundle['warehouse_id'] !== null ? (int) $bundle['warehouse_id'] : null;

        // Oldest other OPEN bundle for the same (raw product, company
        // profile, warehouse) — locked so a concurrent close/convert
        // against it can't race with this merge.
        $targetStmt = $db_conn->prepare(
            "SELECT id FROM raw_material_bundles
             WHERE raw_product_id = ? AND company_godown_id = ? AND warehouse_id <=> ?
               AND status = 'open' AND id != ?
             ORDER BY created_at ASC, id ASC LIMIT 1 FOR UPDATE"
        );
        $targetStmt->bind_param('iiii', $rawProductId, $companyGodownId, $warehouseId, $bundleId);
        $targetStmt->execute();
        $targetRow = $targetStmt->get_result()->fetch_assoc();
        $targetStmt->close();

        if ($targetRow) {
            $carriedToBundleId = (int) $targetRow['id'];
            $mergeStmt = $db_conn->prepare(
                "UPDATE raw_material_bundles SET remaining_pieces = remaining_pieces + ?, carried_from_bundle_id = ?
                 WHERE id = ?"
            );
            $mergeStmt->bind_param('iii', $leftover, $bundleId, $carriedToBundleId);
            $mergeStmt->execute();
            $mergeStmt->close();
        } else {
            // No other open bundle to merge into — create a fresh one
            // to hold the carried-forward leftover. nominal_pieces = 0
            // since this isn't a new nominal intake, just relocated
            // material; its own remaining_pieces starts at $leftover.
            $createStmt = $db_conn->prepare(
                "INSERT INTO raw_material_bundles
                    (raw_product_id, company_godown_id, warehouse_id, nominal_pieces, remaining_pieces, carried_from_bundle_id, created_by)
                 VALUES (?, ?, ?, 0, ?, ?, ?)"
            );
            $createStmt->bind_param('iiiiis', $rawProductId, $companyGodownId, $warehouseId, $leftover, $bundleId, $closedBy);
            $createStmt->execute();
            $carriedToBundleId = (int) $db_conn->insert_id;
            $createStmt->close();
        }
    }

    $stmt = $db_conn->prepare(
        "UPDATE raw_material_bundles SET status = 'closed', closed_by = ?, closed_at = NOW(), carried_to_bundle_id = ?
         WHERE id = ? AND status = 'open'"
    );
    $stmt->bind_param('sii', $closedBy, $carriedToBundleId, $bundleId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected > 0;
}

/**
 * Reopens a closed bundle, symmetric inverse of close_raw_material_bundle():
 * if closing it carried a leftover forward, that's reversed first —
 *  - merged into an existing bundle: pulls the leftover back out of that
 *    target's remaining_pieces (throws if the target no longer has enough
 *    to give back, e.g. it was drawn down further since the merge) and
 *    clears the target's carried_from_bundle_id.
 *  - carried into a fresh bundle created solely to hold it: deletes that
 *    bundle outright (throws if it's been touched since — drawn from,
 *    adjusted, or itself closed/carried — since deleting it would then
 *    lose real history; reconcile manually instead).
 * Then flips this bundle back to 'open' and clears its own close/carry
 * fields, restoring it to its pre-close state.
 *
 * @throws StockException if the bundle doesn't exist, isn't closed, or its
 *   carry-forward target can't be cleanly reversed.
 */
function reopen_raw_material_bundle(mysqli $db_conn, int $bundleId): bool
{
    ensure_raw_material_bundles_table($db_conn);
    ensure_raw_material_bundle_draws_table($db_conn);
    ensure_raw_material_bundle_adjustments_table($db_conn);

    $lockStmt = $db_conn->prepare(
        "SELECT status, carried_to_bundle_id, remaining_pieces FROM raw_material_bundles WHERE id = ? FOR UPDATE"
    );
    $lockStmt->bind_param('i', $bundleId);
    $lockStmt->execute();
    $bundle = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$bundle) {
        throw new StockException("Bundle not found.");
    }
    if ($bundle['status'] !== 'closed') {
        throw new StockException("Only a closed bundle can be reopened.");
    }

    $carriedToBundleId = $bundle['carried_to_bundle_id'] !== null ? (int) $bundle['carried_to_bundle_id'] : null;
    // remaining_pieces is never cleared at close — it still holds exactly
    // the leftover amount that close_raw_material_bundle() carried
    // forward, so it's the reclaim amount for either carry-forward case.
    $leftoverToReclaim = (int) $bundle['remaining_pieces'];

    if ($carriedToBundleId !== null) {
        $targetLockStmt = $db_conn->prepare(
            "SELECT nominal_pieces, remaining_pieces, damaged_pieces, added_extra_pieces,
                    status, carried_from_bundle_id
             FROM raw_material_bundles WHERE id = ? FOR UPDATE"
        );
        $targetLockStmt->bind_param('i', $carriedToBundleId);
        $targetLockStmt->execute();
        $target = $targetLockStmt->get_result()->fetch_assoc();
        $targetLockStmt->close();

        if (!$target) {
            throw new StockException("Cannot reopen — the bundle this leftover was carried into no longer exists. Please reconcile manually.");
        }

        // A carry either merged into an existing bundle (nominal_pieces > 0,
        // was already something before the merge) or was a brand-new
        // carry-only bundle created solely to hold this leftover
        // (nominal_pieces = 0). Only the latter is safe to delete outright.
        if ((int) $target['nominal_pieces'] === 0 && (int) $target['carried_from_bundle_id'] === $bundleId) {
            $hasDraws = $db_conn->prepare("SELECT 1 FROM raw_material_bundle_draws WHERE bundle_id = ? LIMIT 1");
            $hasDraws->bind_param('i', $carriedToBundleId);
            $hasDraws->execute();
            $drawExists = $hasDraws->get_result()->fetch_assoc();
            $hasDraws->close();

            $hasAdj = $db_conn->prepare("SELECT 1 FROM raw_material_bundle_adjustments WHERE bundle_id = ? LIMIT 1");
            $hasAdj->bind_param('i', $carriedToBundleId);
            $hasAdj->execute();
            $adjExists = $hasAdj->get_result()->fetch_assoc();
            $hasAdj->close();

            if ($drawExists || $adjExists || $target['status'] !== 'open') {
                throw new StockException("Cannot reopen — the carry-forward bundle has since been used (converted from, adjusted, or closed). Please reconcile manually.");
            }

            $delStmt = $db_conn->prepare("DELETE FROM raw_material_bundles WHERE id = ?");
            $delStmt->bind_param('i', $carriedToBundleId);
            $delStmt->execute();
            $delStmt->close();
        } else {
            // Merged into a pre-existing open bundle — pull the leftover
            // back out. Needs the merged bundle to still be open and still
            // hold at least that much remaining (it may have been drawn
            // from since the merge).
            if ($target['status'] !== 'open') {
                throw new StockException("Cannot reopen — the bundle this leftover was carried into has since been closed. Please reconcile manually.");
            }
            if ((int) $target['remaining_pieces'] < $leftoverToReclaim) {
                throw new StockException("Cannot reopen — the bundle this leftover was carried into has already drawn from it. Please reconcile manually.");
            }

            $reclaimStmt = $db_conn->prepare(
                "UPDATE raw_material_bundles SET remaining_pieces = remaining_pieces - ?, carried_from_bundle_id = NULL WHERE id = ?"
            );
            $reclaimStmt->bind_param('ii', $leftoverToReclaim, $carriedToBundleId);
            $reclaimStmt->execute();
            $reclaimStmt->close();
        }
    }

    $stmt = $db_conn->prepare(
        "UPDATE raw_material_bundles
         SET status = 'open', closed_by = NULL, closed_at = NULL, carried_to_bundle_id = NULL
         WHERE id = ? AND status = 'closed'"
    );
    $stmt->bind_param('i', $bundleId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected > 0;
}

/**
 * Undo a single bundle-sourced Pieces->Pack conversion, identified by the
 * stock_ledger ref_id it was recorded under. Restores remaining_pieces on
 * the bundle it drew from and deletes the draw record — does NOT touch
 * `stock` or `stock_ledger` itself, the caller is responsible for
 * reversing the StockService credit() that accompanied this draw (see
 * manage-piece-pack-conversions.php, which calls both inside one
 * transaction).
 *
 * Works regardless of the bundle's current status — a closed bundle's
 * remaining_pieces is still incremented, so a deliberate delete/undo of
 * a conversion always fully reverses the draw. Status itself is never
 * changed here; use reopen_raw_material_bundle() separately if the
 * bundle should also go back to open.
 *
 * @throws StockException if no draw exists for this ref_id, or its bundle no longer exists.
 * @return array{bundle_id:int, product_id:int, pieces_used:int, packs_made:int}
 */
function restore_bundle_draw(mysqli $db_conn, string $refId): array
{
    ensure_raw_material_bundles_table($db_conn);
    ensure_raw_material_bundle_draws_table($db_conn);

    $stmt = $db_conn->prepare(
        "SELECT id, bundle_id, product_id, pieces_used, packs_made FROM raw_material_bundle_draws WHERE ref_id = ? LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('s', $refId);
    $stmt->execute();
    $draw = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$draw) {
        throw new StockException("No raw material bundle draw found for this conversion — it may not be bundle-sourced.");
    }

    $bundleId   = (int) $draw['bundle_id'];
    $piecesUsed = (int) $draw['pieces_used'];

    $lockStmt = $db_conn->prepare(
        "SELECT status FROM raw_material_bundles WHERE id = ? FOR UPDATE"
    );
    $lockStmt->bind_param('i', $bundleId);
    $lockStmt->execute();
    $bundleRow = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    if (!$bundleRow) {
        throw new StockException("The bundle this conversion drew from no longer exists.");
    }
    // No longer refuses on a closed bundle — deleting a conversion is a
    // deliberate correction that must be able to fully reverse the draw
    // regardless of what's happened to the bundle since (see
    // manage-piece-pack-conversions.php's delete action). The pieces go
    // back onto remaining_pieces even if that resurrects a closed bundle's
    // balance; the bundle's own status is left untouched here — closed
    // stays closed unless the operator separately reopens it.

    $updStmt = $db_conn->prepare(
        "UPDATE raw_material_bundles SET remaining_pieces = remaining_pieces + ? WHERE id = ?"
    );
    $updStmt->bind_param('ii', $piecesUsed, $bundleId);
    $updStmt->execute();
    $updStmt->close();

    $delStmt = $db_conn->prepare("DELETE FROM raw_material_bundle_draws WHERE id = ?");
    $delStmt->bind_param('i', $draw['id']);
    $delStmt->execute();
    $delStmt->close();

    return [
        'bundle_id'   => $bundleId,
        'product_id'  => (int) $draw['product_id'],
        'pieces_used' => $piecesUsed,
        'packs_made'  => (int) $draw['packs_made'],
    ];
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
                b.carried_to_bundle_id, b.carried_from_bundle_id,
                b.created_by, b.created_at, b.closed_by, b.closed_at
         FROM raw_material_bundles b
         INNER JOIN products p ON p.id = b.raw_product_id
         INNER JOIN company_godown cg ON cg.id = b.company_godown_id
         LEFT JOIN warehouses w ON w.id = b.warehouse_id
         $whereSql
         ORDER BY b.created_at ASC, b.id ASC"
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
        $carriedToBundleId = $row['carried_to_bundle_id'] !== null ? (int) $row['carried_to_bundle_id'] : null;

        // A closed bundle with leftover ALWAYS carried it forward (see
        // close_raw_material_bundle()) — remaining_pieces > 0 at close
        // is never a real loss under this model, only damaged_pieces
        // (explicitly written off before close) represents actual lost
        // material. Excess is independently signaled by added_extra_pieces
        // having been used at least once. Both can apply to the same
        // bundle — shown together when that happens.
        $varianceLabel = null;
        if ($row['status'] === 'closed') {
            $parts = [];
            if ($remaining > 0 && $carriedToBundleId !== null) $parts[] = "Carried forward: $remaining";
            elseif ($remaining > 0) $parts[] = "Short by $remaining"; // defensive fallback — shouldn't happen under current close logic
            if ($addedExtra > 0) $parts[] = "Excess $addedExtra";
            $varianceLabel = $parts ? implode(', ', $parts) : 'Exact';
        }

        return [
            'id'                     => (int) $row['id'],
            'label'                  => "Bundle #{$row['id']} ($date)",
            'raw_product_id'         => (int) $row['raw_product_id'],
            'product_name'           => $row['productName'],
            'company_godown_id'      => (int) $row['company_godown_id'],
            'gname'                  => $row['gname'],
            'warehouse_code'         => $row['warehouse_code'],
            'nominal_pieces'         => (int) $row['nominal_pieces'],
            'remaining_pieces'       => $remaining,
            'damaged_pieces'         => $damaged,
            'added_extra_pieces'     => $addedExtra,
            'status'                 => $row['status'],
            'variance_label'         => $varianceLabel,
            'carried_to_bundle_id'   => $carriedToBundleId,
            'carried_from_bundle_id' => $row['carried_from_bundle_id'] !== null ? (int) $row['carried_from_bundle_id'] : null,
            'created_by'             => $row['created_by'],
            'created_at'             => $row['created_at'],
            'closed_by'              => $row['closed_by'],
            'closed_at'              => $row['closed_at'],
        ];
    }, $rows);
}

/**
 * Every bundle-sourced Pieces->Pack conversion, most recent first, joined
 * to its stock_ledger entry (qty/warehouse/who/when) and the bundle it
 * drew from — powers manage-piece-pack-conversions.php. Pooled (non-
 * bundle) conversions never appear here, since only convert_from_raw_
 * material_bundle() writes a raw_material_bundle_draws row.
 *
 * @return array<int, array{
 *   draw_id:int, ref_id:string, bundle_id:int, bundle_label:string,
 *   bundle_status:string, product_id:int, product_name:string,
 *   company_godown_id:int, gname:string, warehouse_code:?string,
 *   pieces_used:int, packs_made:int, created_by:?string, created_at:string
 * }>
 */
function get_bundle_conversions(mysqli $db_conn): array
{
    ensure_raw_material_bundle_draws_table($db_conn);

    $rows = $db_conn->query(
        "SELECT d.id AS draw_id, d.ref_id, d.bundle_id, d.pieces_used, d.packs_made,
                d.created_by, d.created_at,
                b.status AS bundle_status, b.company_godown_id,
                cg.gname, w.code AS warehouse_code,
                p.id AS product_id, p.productName
         FROM raw_material_bundle_draws d
         INNER JOIN raw_material_bundles b ON b.id = d.bundle_id
         INNER JOIN company_godown cg ON cg.id = b.company_godown_id
         LEFT JOIN warehouses w ON w.id = b.warehouse_id
         INNER JOIN products p ON p.id = d.product_id
         ORDER BY d.created_at DESC, d.id DESC"
    )->fetch_all(MYSQLI_ASSOC);

    return array_map(function ($row) {
        $bundleDate = date('d-M-Y', strtotime($row['created_at']));
        return [
            'draw_id'            => (int) $row['draw_id'],
            'ref_id'             => $row['ref_id'],
            'bundle_id'          => (int) $row['bundle_id'],
            'bundle_label'       => "Bundle #{$row['bundle_id']}",
            'bundle_status'      => $row['bundle_status'],
            'product_id'         => (int) $row['product_id'],
            'product_name'       => $row['productName'],
            'company_godown_id'  => (int) $row['company_godown_id'],
            'gname'              => $row['gname'],
            'warehouse_code'     => $row['warehouse_code'],
            'pieces_used'        => (int) $row['pieces_used'],
            'packs_made'         => (int) $row['packs_made'],
            'created_by'         => $row['created_by'],
            'created_at'         => $row['created_at'],
        ];
    }, $rows);
}

/**
 * One bundle's complete chronological history, merged from every table
 * that records something happening to it — powers the transaction-detail
 * popup on raw-material-bundles-manage.php. Each entry is normalized to
 * a common shape so the caller can render one unified timeline without
 * caring which table it came from:
 *
 *   type: 'created'|'conversion'|'damage'|'extra'|'closed'|'carried_in'
 *   created_at, created_by, qty (signed: +adds to remaining, -draws from it),
 *   detail (human-readable line), ref_id (nullable), adjustment_id (only
 *   set on damage/extra entries — lets the UI offer a delete button
 *   wired to delete_bundle_adjustment())
 *
 * @return array<int, array{type:string, created_at:string, created_by:?string, qty:?int, detail:string, ref_id:?string, adjustment_id?:int}>
 */
function get_bundle_transaction_history(mysqli $db_conn, int $bundleId): array
{
    ensure_raw_material_bundles_table($db_conn);
    ensure_raw_material_bundle_draws_table($db_conn);
    ensure_raw_material_bundle_adjustments_table($db_conn);
    ensure_bundle_reason_tables($db_conn);

    $stmt = $db_conn->prepare(
        "SELECT id, nominal_pieces, input_ref_id, created_by, created_at,
                status, closed_by, closed_at, carried_from_bundle_id, carried_to_bundle_id
         FROM raw_material_bundles WHERE id = ?"
    );
    $stmt->bind_param('i', $bundleId);
    $stmt->execute();
    $bundle = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$bundle) {
        return [];
    }

    $events = [];

    if ($bundle['carried_from_bundle_id'] !== null) {
        $events[] = [
            'type'       => 'carried_in',
            'created_at' => $bundle['created_at'],
            'created_by' => $bundle['created_by'],
            'qty'        => (int) $bundle['nominal_pieces'],
            'detail'     => "Carried forward from Bundle #{$bundle['carried_from_bundle_id']}",
            'ref_id'     => null,
        ];
    } else {
        $events[] = [
            'type'       => 'created',
            'created_at' => $bundle['created_at'],
            'created_by' => $bundle['created_by'],
            'qty'        => (int) $bundle['nominal_pieces'],
            'detail'     => "Bundle created — {$bundle['nominal_pieces']} nominal piece(s)" . ($bundle['input_ref_id'] ? " (ref {$bundle['input_ref_id']})" : ''),
            'ref_id'     => $bundle['input_ref_id'],
        ];
    }

    $drawRows = $db_conn->prepare(
        "SELECT d.ref_id, d.pieces_used, d.packs_made, d.created_by, d.created_at, p.productName
         FROM raw_material_bundle_draws d
         INNER JOIN products p ON p.id = d.product_id
         WHERE d.bundle_id = ?"
    );
    $drawRows->bind_param('i', $bundleId);
    $drawRows->execute();
    foreach ($drawRows->get_result()->fetch_all(MYSQLI_ASSOC) as $d) {
        $events[] = [
            'type'       => 'conversion',
            'created_at' => $d['created_at'],
            'created_by' => $d['created_by'],
            'qty'        => -1 * (int) $d['pieces_used'],
            'detail'     => "Converted into {$d['packs_made']} pack(s) of {$d['productName']} — {$d['pieces_used']} piece(s) drawn",
            'ref_id'     => $d['ref_id'],
        ];
    }
    $drawRows->close();

    $adjRows = $db_conn->prepare(
        "SELECT a.id, a.type, a.qty, a.note, a.ref_id, a.created_by, a.created_at,
                COALESCE(dr.label, er.label) AS reason_label
         FROM raw_material_bundle_adjustments a
         LEFT JOIN damage_reason_master dr ON dr.id = a.reason_id AND a.type = 'damage'
         LEFT JOIN extra_reason_master er ON er.id = a.reason_id AND a.type = 'extra'
         WHERE a.bundle_id = ?"
    );
    $adjRows->bind_param('i', $bundleId);
    $adjRows->execute();
    foreach ($adjRows->get_result()->fetch_all(MYSQLI_ASSOC) as $a) {
        $isDamage = $a['type'] === 'damage';
        $label = $a['reason_label'] ?: ($isDamage ? 'Damaged' : 'Extra found');
        $detail = $isDamage
            ? "{$a['qty']} piece(s) recorded damaged — $label"
            : "{$a['qty']} extra piece(s) added — $label";
        if (!empty($a['note'])) {
            $detail .= " ({$a['note']})";
        }
        $events[] = [
            'type'           => $isDamage ? 'damage' : 'extra',
            'created_at'     => $a['created_at'],
            'created_by'     => $a['created_by'],
            'qty'            => $isDamage ? -1 * (int) $a['qty'] : (int) $a['qty'],
            'detail'         => $detail,
            'ref_id'         => $a['ref_id'],
            'adjustment_id'  => (int) $a['id'],
        ];
    }
    $adjRows->close();

    if ($bundle['status'] === 'closed' && $bundle['closed_at']) {
        $detail = 'Bundle closed';
        if ($bundle['carried_to_bundle_id'] !== null) {
            $detail .= " — leftover carried forward into Bundle #{$bundle['carried_to_bundle_id']}";
        }
        $events[] = [
            'type'       => 'closed',
            'created_at' => $bundle['closed_at'],
            'created_by' => $bundle['closed_by'],
            'qty'        => null,
            'detail'     => $detail,
            'ref_id'     => null,
        ];
    }

    usort($events, function ($a, $b) {
        return strtotime($a['created_at']) <=> strtotime($b['created_at']);
    });

    return $events;
}

/**
 * One row per bundle with conversion/damage/extra activity rolled up —
 * powers the "By Bundle" tab of raw-material-bundles-report.php. Same
 * base shape as get_raw_material_bundles() plus aggregated counts, and
 * filterable by date range (against the bundle's created_at) alongside
 * the existing product/status filters.
 *
 * @return array<int, array{
 *   id:int, label:string, raw_product_id:int, product_name:string,
 *   company_godown_id:int, gname:string, warehouse_code:?string,
 *   nominal_pieces:int, remaining_pieces:int, damaged_pieces:int,
 *   added_extra_pieces:int, status:string, variance_label:?string,
 *   conversions_count:int, packs_made_total:int, pieces_drawn_total:int,
 *   created_by:?string, created_at:string, closed_by:?string, closed_at:?string,
 *   carried_to_bundle_id:?int, carried_from_bundle_id:?int
 * }>
 */
function get_bundle_report_summary(
    mysqli $db_conn,
    ?int $rawProductId = null,
    ?int $companyGodownId = null,
    ?string $status = null,
    ?string $dateFrom = null,
    ?string $dateTo = null
): array {
    ensure_raw_material_bundles_table($db_conn);
    ensure_raw_material_bundle_draws_table($db_conn);

    $where = [];
    $types = '';
    $params = [];
    if ($rawProductId !== null) {
        $where[] = 'b.raw_product_id = ?';
        $types .= 'i';
        $params[] = $rawProductId;
    }
    if ($companyGodownId !== null) {
        $where[] = 'b.company_godown_id = ?';
        $types .= 'i';
        $params[] = $companyGodownId;
    }
    if ($status !== null) {
        $where[] = 'b.status = ?';
        $types .= 's';
        $params[] = $status;
    }
    if ($dateFrom !== null) {
        $where[] = 'DATE(b.created_at) >= ?';
        $types .= 's';
        $params[] = $dateFrom;
    }
    if ($dateTo !== null) {
        $where[] = 'DATE(b.created_at) <= ?';
        $types .= 's';
        $params[] = $dateTo;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $db_conn->prepare(
        "SELECT b.id, b.raw_product_id, p.productName, b.company_godown_id, cg.gname,
                w.code AS warehouse_code, b.nominal_pieces, b.remaining_pieces,
                b.damaged_pieces, b.added_extra_pieces, b.status,
                b.carried_to_bundle_id, b.carried_from_bundle_id,
                b.created_by, b.created_at, b.closed_by, b.closed_at,
                COUNT(d.id) AS conversions_count,
                COALESCE(SUM(d.packs_made), 0) AS packs_made_total,
                COALESCE(SUM(d.pieces_used), 0) AS pieces_drawn_total
         FROM raw_material_bundles b
         INNER JOIN products p ON p.id = b.raw_product_id
         INNER JOIN company_godown cg ON cg.id = b.company_godown_id
         LEFT JOIN warehouses w ON w.id = b.warehouse_id
         LEFT JOIN raw_material_bundle_draws d ON d.bundle_id = b.id
         $whereSql
         GROUP BY b.id
         ORDER BY b.created_at ASC, b.id ASC"
    );
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(function ($row) {
        $remaining = (int) $row['remaining_pieces'];
        $damaged = (int) $row['damaged_pieces'];
        $addedExtra = (int) $row['added_extra_pieces'];
        $carriedToBundleId = $row['carried_to_bundle_id'] !== null ? (int) $row['carried_to_bundle_id'] : null;

        $varianceLabel = null;
        if ($row['status'] === 'closed') {
            $parts = [];
            if ($remaining > 0 && $carriedToBundleId !== null) $parts[] = "Carried forward: $remaining";
            elseif ($remaining > 0) $parts[] = "Short by $remaining";
            if ($addedExtra > 0) $parts[] = "Excess $addedExtra";
            $varianceLabel = $parts ? implode(', ', $parts) : 'Exact';
        }

        return [
            'id'                     => (int) $row['id'],
            'label'                  => "Bundle #{$row['id']}",
            'raw_product_id'         => (int) $row['raw_product_id'],
            'product_name'           => $row['productName'],
            'company_godown_id'      => (int) $row['company_godown_id'],
            'gname'                  => $row['gname'],
            'warehouse_code'         => $row['warehouse_code'],
            'nominal_pieces'         => (int) $row['nominal_pieces'],
            'remaining_pieces'       => $remaining,
            'damaged_pieces'         => $damaged,
            'added_extra_pieces'     => $addedExtra,
            'status'                 => $row['status'],
            'variance_label'         => $varianceLabel,
            'conversions_count'      => (int) $row['conversions_count'],
            'packs_made_total'       => (int) $row['packs_made_total'],
            'pieces_drawn_total'     => (int) $row['pieces_drawn_total'],
            'created_by'             => $row['created_by'],
            'created_at'             => $row['created_at'],
            'closed_by'              => $row['closed_by'],
            'closed_at'              => $row['closed_at'],
            'carried_to_bundle_id'   => $carriedToBundleId,
            'carried_from_bundle_id' => $row['carried_from_bundle_id'] !== null ? (int) $row['carried_from_bundle_id'] : null,
        ];
    }, $rows);
}

/**
 * Flat, global transaction ledger across ALL bundles — every conversion
 * draw and every damage/extra adjustment as its own row, newest first,
 * filterable by date range/product/godown/type. Powers the "By
 * Transaction" tab of raw-material-bundles-report.php. Bundle
 * creation/carry-in/close events are intentionally excluded here (they
 * live in the per-bundle history popup) — this is the activity ledger,
 * not the full lifecycle timeline.
 *
 * @return array<int, array{
 *   type:string, bundle_id:int, product_name:string, gname:string,
 *   warehouse_code:?string, qty:int, detail:string, ref_id:?string,
 *   created_by:?string, created_at:string
 * }>
 */
function get_bundle_report_transactions(
    mysqli $db_conn,
    ?int $rawProductId = null,
    ?int $companyGodownId = null,
    ?string $type = null,
    ?string $dateFrom = null,
    ?string $dateTo = null
): array {
    ensure_raw_material_bundle_draws_table($db_conn);
    ensure_raw_material_bundle_adjustments_table($db_conn);
    ensure_bundle_reason_tables($db_conn);

    $rows = [];

    if ($type === null || $type === 'conversion') {
        $where = ['1=1'];
        $types = '';
        $params = [];
        if ($rawProductId !== null) { $where[] = 'd.product_id = ?'; $types .= 'i'; $params[] = $rawProductId; }
        if ($companyGodownId !== null) { $where[] = 'b.company_godown_id = ?'; $types .= 'i'; $params[] = $companyGodownId; }
        if ($dateFrom !== null) { $where[] = 'DATE(d.created_at) >= ?'; $types .= 's'; $params[] = $dateFrom; }
        if ($dateTo !== null) { $where[] = 'DATE(d.created_at) <= ?'; $types .= 's'; $params[] = $dateTo; }

        $stmt = $db_conn->prepare(
            "SELECT d.bundle_id, d.pieces_used, d.packs_made, d.ref_id, d.created_by, d.created_at,
                    p.productName, cg.gname, w.code AS warehouse_code
             FROM raw_material_bundle_draws d
             INNER JOIN raw_material_bundles b ON b.id = d.bundle_id
             INNER JOIN company_godown cg ON cg.id = b.company_godown_id
             LEFT JOIN warehouses w ON w.id = b.warehouse_id
             INNER JOIN products p ON p.id = d.product_id
             WHERE " . implode(' AND ', $where)
        );
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $rows[] = [
                'type'            => 'conversion',
                'bundle_id'       => (int) $r['bundle_id'],
                'product_name'    => $r['productName'],
                'gname'           => $r['gname'],
                'warehouse_code'  => $r['warehouse_code'],
                'qty'             => -1 * (int) $r['pieces_used'],
                'detail'          => "Converted into {$r['packs_made']} pack(s) — {$r['pieces_used']} piece(s) drawn",
                'ref_id'          => $r['ref_id'],
                'created_by'      => $r['created_by'],
                'created_at'      => $r['created_at'],
            ];
        }
        $stmt->close();
    }

    if ($type === null || $type === 'damage' || $type === 'extra') {
        $where = ['1=1'];
        $types = '';
        $params = [];
        if ($type !== null) { $where[] = 'a.type = ?'; $types .= 's'; $params[] = $type; }
        if ($rawProductId !== null) { $where[] = 'b.raw_product_id = ?'; $types .= 'i'; $params[] = $rawProductId; }
        if ($companyGodownId !== null) { $where[] = 'b.company_godown_id = ?'; $types .= 'i'; $params[] = $companyGodownId; }
        if ($dateFrom !== null) { $where[] = 'DATE(a.created_at) >= ?'; $types .= 's'; $params[] = $dateFrom; }
        if ($dateTo !== null) { $where[] = 'DATE(a.created_at) <= ?'; $types .= 's'; $params[] = $dateTo; }

        $stmt = $db_conn->prepare(
            "SELECT a.type, a.bundle_id, a.qty, a.note, a.ref_id, a.created_by, a.created_at,
                    COALESCE(dr.label, er.label) AS reason_label,
                    p.productName, cg.gname, w.code AS warehouse_code
             FROM raw_material_bundle_adjustments a
             INNER JOIN raw_material_bundles b ON b.id = a.bundle_id
             INNER JOIN company_godown cg ON cg.id = b.company_godown_id
             LEFT JOIN warehouses w ON w.id = b.warehouse_id
             INNER JOIN products p ON p.id = b.raw_product_id
             LEFT JOIN damage_reason_master dr ON dr.id = a.reason_id AND a.type = 'damage'
             LEFT JOIN extra_reason_master er ON er.id = a.reason_id AND a.type = 'extra'
             WHERE " . implode(' AND ', $where)
        );
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $isDamage = $r['type'] === 'damage';
            $label = $r['reason_label'] ?: ($isDamage ? 'Damaged' : 'Extra found');
            $detail = ($isDamage ? "Recorded damaged — $label" : "Extra piece(s) added — $label");
            if (!empty($r['note'])) $detail .= " ({$r['note']})";
            $rows[] = [
                'type'            => $r['type'],
                'bundle_id'       => (int) $r['bundle_id'],
                'product_name'    => $r['productName'],
                'gname'           => $r['gname'],
                'warehouse_code'  => $r['warehouse_code'],
                'qty'             => $isDamage ? -1 * (int) $r['qty'] : (int) $r['qty'],
                'detail'          => $detail,
                'ref_id'          => $r['ref_id'],
                'created_by'      => $r['created_by'],
                'created_at'      => $r['created_at'],
            ];
        }
        $stmt->close();
    }

    usort($rows, function ($a, $b) {
        return strtotime($b['created_at']) <=> strtotime($a['created_at']);
    });

    return $rows;
}
