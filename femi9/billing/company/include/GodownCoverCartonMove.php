<?php
declare(strict_types=1);

require_once __DIR__ . '/ProductCovers.php';
require_once __DIR__ . '/CartonBoxes.php';

/**
 * Godown-to-godown move for covers and carton boxes — companion to the
 * product stock move in godown-stock-move-action.php. Covers/cartons are
 * standalone balance tables (not StockService stock), so a move is the
 * existing blocking "use" on the source row plus a "receive" on the
 * destination row, both tagged with the move's ref_id and a note so the
 * adjustment history shows where it went / came from. The source side
 * throws StockException when its balance is short, so the caller's
 * transaction rolls the whole batch back.
 */
function move_covers(
    mysqli $db_conn, int $companyProductId, int $fromGodownId, ?int $fromWarehouseId,
    int $toGodownId, ?int $toWarehouseId, int $qty, ?string $userNote, string $refId, ?string $by
): void {
    $today = date('Y-m-d');
    consume_covers($db_conn, $companyProductId, $fromGodownId, $fromWarehouseId, $qty, $today, $refId, $by);
    receive_covers($db_conn, $companyProductId, $toGodownId, $toWarehouseId, $qty,
        trim('Godown move ' . ($userNote ?? '')), $today, $refId, $by);
}

function move_cartons(
    mysqli $db_conn, int $cartonTypeId, int $fromGodownId, ?int $fromWarehouseId,
    int $toGodownId, ?int $toWarehouseId, int $qty, ?string $userNote, string $refId, ?string $by
): void {
    $today = date('Y-m-d');
    $note = mb_substr(trim('Godown move ' . ($userNote ?? '')), 0, 255);
    record_used_cartons($db_conn, $cartonTypeId, $fromGodownId, $fromWarehouseId, $qty, $note . ' (out)', $today, $by);
    receive_cartons($db_conn, $cartonTypeId, $toGodownId, $toWarehouseId, $qty, $note . ' (in)', $today, $refId, $by);
}
