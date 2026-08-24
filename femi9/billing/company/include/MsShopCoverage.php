<?php
// "New shop coverage" — for shops a marketing staff member added within a
// date range, how many of those SAME shops got at least one Get Order
// (ms_orders.new_order='yes') also within that range. Rolled up the
// marketing_staff hierarchy (DM -> ASM -> SM via manager_id) so a manager's
// number is their own shops plus everyone below them, same rollup pattern
// as marketing/my-team.php's subtreeSumAndIds().

function getMsShopCoverageReport($db_conn, string $fromDate, string $toDate): array {
    $staffRes = $db_conn->query("
        SELECT ms.id, ms.ms_name, ms.manager_id, ms.team_level_id, tl.level_name, tl.level_rank
        FROM marketing_staff ms
        LEFT JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
        ORDER BY tl.level_rank ASC, ms.ms_name ASC
    ");
    $byId = [];
    $byManager = [];
    while ($row = $staffRes->fetch_assoc()) {
        $id = (int)$row['id'];
        $byId[$id] = $row;
        $mgrKey = $row['manager_id'] ? (int)$row['manager_id'] : 0;
        $byManager[$mgrKey][] = $row;
    }

    $blankStat = ['shops' => 0, 'ordered' => 0, 'invoiced_value' => 0.0, 'new_shop_orders' => 0, 'new_shop_order_value' => 0.0];
    $rawStats = [];
    foreach ($byId as $id => $row) { $rawStats[$id] = $blankStat; }

    $from = $db_conn->real_escape_string($fromDate);
    $to   = $db_conn->real_escape_string($toDate);

    $shopRes = $db_conn->query("SELECT ms_id, COUNT(*) AS cnt FROM ms_shop WHERE DATE(created_at) BETWEEN '$from' AND '$to' GROUP BY ms_id");
    if ($shopRes) {
        while ($r = $shopRes->fetch_assoc()) {
            $id = (int)$r['ms_id'];
            if (!isset($rawStats[$id])) { $rawStats[$id] = $blankStat; }
            $rawStats[$id]['shops'] = (int)$r['cnt'];
        }
    }

    // A shop counts as "activated" for this range if its FIRST-EVER Get
    // Order (new_order='yes') fell inside it — regardless of when the shop
    // itself was added. A shop added this month whose first order is also
    // this month satisfies this automatically; an old shop (added any
    // earlier month) that finally got its first order this month counts
    // too, same as the user asked for.
    $orderRes = $db_conn->query(
        "SELECT s.ms_id, COUNT(DISTINCT s.id) AS cnt
         FROM ms_shop s
         JOIN (
             SELECT shop_id, MIN(order_date) AS first_order_date
             FROM ms_orders
             WHERE new_order = 'yes'
             GROUP BY shop_id
         ) fo ON fo.shop_id = s.id
         WHERE fo.first_order_date BETWEEN '$from' AND '$to'
         GROUP BY s.ms_id"
    );
    if ($orderRes) {
        while ($r = $orderRes->fetch_assoc()) {
            $id = (int)$r['ms_id'];
            if (!isset($rawStats[$id])) { $rawStats[$id] = $blankStat; }
            $rawStats[$id]['ordered'] = (int)$r['cnt'];
        }
    }

    // Get Order count/value specifically FROM NEW SHOPS — a shop added in
    // this exact range, that itself got a Get Order (new_order='yes') also
    // in this exact range. Deliberately narrower than the "first order ever"
    // metric above: this one is scoped to just-added shops only, counts
    // EVERY order on them (not just their first), and reports raw ordered
    // value (qty * outlet_price * discount), not the invoiced amount.
    $newShopOrderRes = $db_conn->query(
        "SELECT s.ms_id,
                COUNT(DISTINCT o.order_id) AS order_cnt,
                SUM(o.qty * p.outlet_price * (1 - o.discount_percentage/100)) AS order_value
         FROM ms_shop s
         JOIN ms_orders o ON o.shop_id = s.id AND o.new_order = 'yes' AND o.order_date BETWEEN '$from' AND '$to'
         LEFT JOIN products p ON p.id = o.pr_id
         WHERE DATE(s.created_at) BETWEEN '$from' AND '$to'
         GROUP BY s.ms_id"
    );
    if ($newShopOrderRes) {
        while ($r = $newShopOrderRes->fetch_assoc()) {
            $id = (int)$r['ms_id'];
            if (!isset($rawStats[$id])) { $rawStats[$id] = $blankStat; }
            $rawStats[$id]['new_shop_orders']      = (int)$r['order_cnt'];
            $rawStats[$id]['new_shop_order_value'] = (float)($r['order_value'] ?? 0);
        }
    }

    $invoicedMap = getMsInvoicedValueMap($db_conn, $fromDate, $toDate);
    foreach ($invoicedMap as $id => $inv) {
        if (!isset($rawStats[$id])) { $rawStats[$id] = $blankStat; }
        $rawStats[$id]['invoiced_value'] = $inv['value'];
    }

    return ['byId' => $byId, 'byManager' => $byManager, 'rawStats' => $rawStats];
}

function msCoverageSubtreeSum(int $id, array $byManager, array $rawStats, array &$memo): array {
    if (isset($memo[$id])) { return $memo[$id]; }
    $sum = $rawStats[$id] ?? ['shops' => 0, 'ordered' => 0, 'invoiced_value' => 0.0, 'new_shop_orders' => 0, 'new_shop_order_value' => 0.0];
    foreach (($byManager[$id] ?? []) as $child) {
        $childSum = msCoverageSubtreeSum((int)$child['id'], $byManager, $rawStats, $memo);
        $sum['shops']               += $childSum['shops'];
        $sum['ordered']             += $childSum['ordered'];
        $sum['invoiced_value']      += $childSum['invoiced_value'];
        $sum['new_shop_orders']      += $childSum['new_shop_orders'];
        $sum['new_shop_order_value'] += $childSum['new_shop_order_value'];
    }
    $memo[$id] = $sum;
    return $sum;
}

function msCoveragePercent(array $sum): float {
    return $sum['shops'] > 0 ? round(($sum['ordered'] / $sum['shops']) * 100, 1) : 0.0;
}

// Invoiced value — of the Get Orders placed in this range, how much has
// actually been TURNED INTO a real TP invoice (ms_orders -> tp_orders ->
// its invoiced_inv_id -> user_invoice.total), not just the raw ordered
// amount. Same join path as ms_prorders.php's own invoice-value KPI.
// Attributed per ms_id (the staff who placed the order) — [ms_id => ['count'=>N,'value'=>X]].
function getMsInvoicedValueMap($db_conn, string $fromDate, string $toDate): array {
    $from = $db_conn->real_escape_string($fromDate);
    $to   = $db_conn->real_escape_string($toDate);

    $pairRes = $db_conn->query(
        "SELECT DISTINCT o.ms_id, t.invoiced_inv_id
         FROM ms_orders o
         JOIN tp_orders t ON t.order_id = o.order_id
         WHERE o.new_order = 'yes' AND o.order_date BETWEEN '$from' AND '$to'
               AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''"
    );
    $invIdsByMs = [];
    $allInvIds = [];
    if ($pairRes) {
        while ($r = $pairRes->fetch_assoc()) {
            $invIdsByMs[(int)$r['ms_id']][] = $r['invoiced_inv_id'];
            $allInvIds[$r['invoiced_inv_id']] = true;
        }
    }

    $invTotals = [];
    if (!empty($allInvIds)) {
        $idList = "'" . implode("','", array_map(fn($v) => $db_conn->real_escape_string($v), array_keys($allInvIds))) . "'";
        $valRes = $db_conn->query("SELECT inv_id, total FROM user_invoice WHERE inv_id IN ($idList)");
        if ($valRes) {
            while ($r = $valRes->fetch_assoc()) { $invTotals[$r['inv_id']] = (float)$r['total']; }
        }
    }

    $result = [];
    foreach ($invIdsByMs as $msId => $invIds) {
        $value = 0.0;
        foreach ($invIds as $iid) { $value += $invTotals[$iid] ?? 0.0; }
        $result[$msId] = ['count' => count($invIds), 'value' => $value];
    }
    return $result;
}

// District(s) each marketing staff member is assigned to, resolved from
// marketing_staff_locations (which can point at a STATE/DISTRICT/TALUK/FIRKA
// node) up to its owning DISTRICT — same resolution rule as
// marketing/include/AssignedLocations.php's getMsAssignedDistricts() and
// marketing/my-team.php's own copy of this logic.
function getMsDistrictMap($db_conn): array {
    $allLocNodes = [];
    $locRes = $db_conn->query("SELECT id, parent_id, depth, name FROM partner_location_nodes WHERE is_active=1");
    while ($row = $locRes->fetch_assoc()) {
        $allLocNodes[(int)$row['id']] = [
            'parent_id' => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
            'depth'     => (int)$row['depth'],
            'name'      => $row['name'],
        ];
    }

    $districtForNode = function (int $locId) use ($allLocNodes): ?string {
        if (!isset($allLocNodes[$locId])) { return null; }
        $cur = $allLocNodes[$locId];
        if ($cur['depth'] === 2) { return null; } // STATE-level — no single district
        while ($cur !== null && $cur['depth'] !== 3) {
            $cur = $cur['parent_id'] !== null ? ($allLocNodes[$cur['parent_id']] ?? null) : null;
        }
        return $cur['name'] ?? null;
    };

    $districtsByMs = [];
    $assignRes = $db_conn->query("SELECT ms_id, location_id FROM marketing_staff_locations");
    if ($assignRes) {
        while ($row = $assignRes->fetch_assoc()) {
            $distName = $districtForNode((int)$row['location_id']);
            if ($distName !== null) { $districtsByMs[(int)$row['ms_id']][$distName] = true; }
        }
    }
    foreach ($districtsByMs as $msId => $names) { $districtsByMs[$msId] = implode(', ', array_keys($names)); }
    return $districtsByMs;
}
