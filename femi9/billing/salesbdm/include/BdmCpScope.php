<?php
// Channel Partner analog of BdmTpScope.php — resolves which Channel Partners
// fall under a Sales BDM's own personal district assignment(s). channel_partners
// has no assigned_district column (unlike territory_partners), so this matches
// directly against branch_district (case-insensitive, trimmed) — there's no FK
// between the location tree and channel_partners either.
require_once __DIR__ . '/BdmTpScope.php';

function getBdmAssignedCpIds($db_conn, int $bdmId, bool $includeInactive = false): array {
    $districtNames = getBdmAssignedDistrictNames($db_conn, $bdmId);
    if (empty($districtNames)) return [];

    $placeholders = implode(',', array_fill(0, count($districtNames), '?'));
    $types = str_repeat('s', count($districtNames));
    $normalized = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);

    $activeClause = $includeInactive ? '' : 'is_active = 1 AND ';
    $stmt = $db_conn->prepare("
        SELECT id FROM channel_partners
        WHERE {$activeClause}LOWER(TRIM(branch_district)) IN ($placeholders)
    ");
    $stmt->bind_param($types, ...$normalized);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(fn($r) => (int)$r['id'], $rows);
}
?>
