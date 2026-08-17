<?php
/**
 * TpApproverContext — resolves whether a TP's PO / advance-payment
 * submission is routed to Company or to their assigned Super Stockist.
 *
 * territory_partners.onboard_ss_id stores super_stockiest.temp_id (varchar),
 * same as company/remapping-tp-ss.php's own join — so tpGetAssignedSs()
 * mirrors that join exactly to stay consistent with what "assigned" means
 * everywhere else in the codebase.
 */

/**
 * @return array{id:int,temp_id:string,name:string}|null null when the TP has
 *   no onboard_ss_id, or it doesn't resolve to a live SS row.
 */
function tpGetAssignedSs(mysqli $db, int $tpId): ?array
{
    $stmt = $db->prepare(
        "SELECT ss.id, ss.temp_id, ss.name
           FROM territory_partners tp
           JOIN super_stockiest ss ON ss.temp_id COLLATE utf8mb4_general_ci = tp.onboard_ss_id COLLATE utf8mb4_general_ci
          WHERE tp.id = ? AND tp.onboard_ss_id IS NOT NULL AND tp.onboard_ss_id != ''
          LIMIT 1"
    );
    $stmt->bind_param('i', $tpId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return null;

    return ['id' => (int)$row['id'], 'temp_id' => $row['temp_id'], 'name' => $row['name']];
}

/**
 * Server-side authoritative resolution of who a PO/submission is routed to.
 * Never trust a client-submitted approver type on its own — a TP could POST
 * approver_type=ss without actually having an SS assignment, so this always
 * re-verifies against tpGetAssignedSs() before honoring 'ss'.
 *
 * @return array{type:'company'|'ss',ss_id:?int}
 */
function tpResolveApprover(mysqli $db, int $tpId, ?string $requestedApprover): array
{
    if ($requestedApprover === 'ss') {
        $ss = tpGetAssignedSs($db, $tpId);
        if ($ss !== null) {
            return ['type' => 'ss', 'ss_id' => $ss['id']];
        }
    }

    return ['type' => 'company', 'ss_id' => null];
}
