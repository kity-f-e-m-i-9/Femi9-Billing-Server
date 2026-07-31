<?php
// Walks the marketing_staff hierarchy (manager_id) down from a given root,
// used to scope "My Team" views to only what a logged-in staff member is
// actually allowed to see — their own downline, nothing else.

function getMsSubtreeIds($db_conn, int $rootId): array {
    $all = [];
    $res = $db_conn->query("SELECT id, manager_id FROM marketing_staff");
    $byManager = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $mgr = $row['manager_id'] ? (int)$row['manager_id'] : 0;
            $byManager[$mgr][] = (int)$row['id'];
        }
    }

    $ids = [$rootId];
    $queue = [$rootId];
    $visited = [$rootId => true];
    while (!empty($queue)) {
        $id = array_shift($queue);
        foreach (($byManager[$id] ?? []) as $childId) {
            if (isset($visited[$childId])) continue;
            $visited[$childId] = true;
            $ids[] = $childId;
            $queue[] = $childId;
        }
    }
    return $ids;
}
