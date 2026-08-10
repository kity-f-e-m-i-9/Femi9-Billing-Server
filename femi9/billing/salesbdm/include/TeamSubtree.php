<?php
// Resolves a Sales BDM's own reporting hierarchy (sales_bdm_staff.manager_id
// chain) — mirrors marketing/include/TeamSubtree.php's pattern for the
// marketing_staff hierarchy, just for sales_bdm_staff instead.

function getBdmDirectReports($db_conn, int $bdmId): array {
    $stmt = $db_conn->prepare("
        SELECT s.id, s.bdm_name, s.team_level_id, s.manager_id, tl.level_name, tl.level_rank
        FROM sales_bdm_staff s
        LEFT JOIN salesbdm_team_levels tl ON tl.id = s.team_level_id
        WHERE s.manager_id = ?
        ORDER BY s.bdm_name ASC
    ");
    $stmt->bind_param('i', $bdmId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Every BDM at or below $rootId in the reporting chain, root included.
function getBdmSubtreeIds($db_conn, int $rootId): array {
    $ids = [$rootId];
    $queue = [$rootId];
    while (!empty($queue)) {
        $parent = array_shift($queue);
        foreach (getBdmDirectReports($db_conn, $parent) as $child) {
            $cid = (int)$child['id'];
            if (!in_array($cid, $ids, true)) {
                $ids[] = $cid;
                $queue[] = $cid;
            }
        }
    }
    return $ids;
}
