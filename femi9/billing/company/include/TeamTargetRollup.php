<?php
// Rolls up an individual Marketing Staff member's target (district firka
// target_amount for whoever has an assigned district — a leaf DM, in
// practice — prorated to the date range) and what they've actually achieved
// (converted invoice value), batched for a whole list of ms_ids at once —
// reused by marketing/my-team.php (ASM/SM's own team view) and
// company/ms-team-view.php (company's org-chart tree) so both surface the
// exact same numbers computed the exact same way as the single-person card
// already on marketing/manage_order_product.php (lines ~313-336).
//
// Target source: each person's own assigned location(s) -> every Firka's
// target_amount under it (filled AND vacant — same total the District/
// Division Dashboard's "Total Target" card shows), NOT the old
// marketing_staff.monthly_target_amount field. An assignment at DISTRICT or
// DIVISION depth is used as-is (a DM assigned a specific Division only rolls
// up that division's Firkas, not the whole district's) — see
// getMsAssignedTargetScopeNodes()'s own doc comment for the full depth
// handling. A non-leaf (SM/ASM) normally has no location of their own
// assigned, so this naturally comes back 0 for them; the caller
// (msTeamViewSubtreeTargetSum() / my-team.php's own equivalent) still zeroes
// a manager's own raw target whenever they have a team below them and rolls
// up the children's sum instead, same convention as before — only the
// leaf's own number's *source* changed.
require_once __DIR__ . '/../../marketing/include/AssignedLocations.php';
require_once __DIR__ . '/../../marketing/include/MsDistrictScope.php';

function getRawTargetAchievedStats($db_conn, array $ids, string $fromDate, string $toDate): array {
    $stats = [];
    foreach ($ids as $id) { $stats[(int)$id] = ['target' => 0.0, 'achieved' => 0.0]; }
    if (empty($ids)) return $stats;

    $idList = implode(',', array_map('intval', $ids));

    $daysInMonth = (int)date('t', strtotime($fromDate));
    $daysInRange = (int)floor((strtotime($toDate) - strtotime($fromDate)) / 86400) + 1;
    if ($daysInRange < 1) { $daysInRange = 1; }

    // Target — each person's assigned location(s)' full Firka total
    // (filled + vacant), prorated to the range the same way the old
    // monthly-amount figure was.
    foreach ($ids as $id) {
        $id = (int)$id;
        $scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $id);
        if (empty($scopeNodes)) continue;
        $scopeIds = array_column($scopeNodes, 'id');
        $scopeStats = getMsNodeTargetAndFirkaStats($db_conn, $scopeIds);
        $monthly = (float)$scopeStats['district_total_target'];
        $perDay = $daysInMonth > 0 ? $monthly / $daysInMonth : 0.0;
        $stats[$id]['target'] = $perDay * $daysInRange;
    }

    // Achieved — sum of Napkin-category line items (Lumi Baby Diaper doesn't
    // count toward target) for orders this person got converted, in the same
    // date range. Dedups (ms_id, invoiced_inv_id) BEFORE joining to
    // user_invoice_items, since tp_orders has one row per product line —
    // joining straight through would double up which invoices count once
    // per person (matches manage_order_product.php's distinct-invoice-ids
    // approach); summing the invoice's own line items (rather than its
    // header total) is what lets Diaper lines be excluded per-item.
    // No COLLATE cast on the join below (tp_orders.invoiced_inv_id,
    // user_invoice.inv_id and user_invoice_items.inv_id already all share
    // utf8mb4_general_ci — confirmed via SHOW FULL COLUMNS) — a COLLATE
    // clause on a join column, even a no-op one matching the column's own
    // collation, stops MySQL using its index (EXPLAIN showed a full table
    // scan of user_invoice_items — ~180k rows — turning this into a 9s+
    // query; removing it brought the same query under 0.2s).
    $fromEsc = $db_conn->real_escape_string($fromDate);
    $toEsc   = $db_conn->real_escape_string($toDate);
    $resA = $db_conn->query("
        SELECT x.ms_id, COALESCE(SUM(uii.total), 0) AS achieved
        FROM (
            SELECT DISTINCT o.ms_id, t.invoiced_inv_id
            FROM ms_orders o
            JOIN tp_orders t ON t.order_id = o.order_id
            WHERE o.ms_id IN ($idList) AND o.new_order = 'yes'
              AND o.order_date BETWEEN '$fromEsc' AND '$toEsc'
              AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''
        ) x
        JOIN user_invoice_items uii ON uii.inv_id = x.invoiced_inv_id
        JOIN products p ON p.id = uii.pr_id
        WHERE COALESCE(p.category,'') != 'diaper'
        GROUP BY x.ms_id
    ");
    if ($resA) {
        while ($r = $resA->fetch_assoc()) {
            $stats[(int)$r['ms_id']]['achieved'] = (float)$r['achieved'];
        }
    }

    return $stats;
}

// Day-by-day version of getRawTargetAchievedStats()'s "Achieved" (Napkin-
// only, Get-Order-converted sales) — same join, grouped by the invoice's own
// date (ui.date) as well as ms_id, instead of summed into one period total.
// Used to find the exact date a person's cumulative Napkin sales first
// reached their Target Amount ("Target Completed Date" — SM-wise/DM-wise
// requirement #4), which needs the running total day by day, not just the
// period's grand total.
function getRawAchievedDailyByMsId($db_conn, array $ids, string $fromDate, string $toDate): array {
    $out = [];
    foreach ($ids as $id) { $out[(int)$id] = []; }
    if (empty($ids)) return $out;

    $idList = implode(',', array_map('intval', $ids));
    $fromEsc = $db_conn->real_escape_string($fromDate);
    $toEsc   = $db_conn->real_escape_string($toDate);
    $res = $db_conn->query("
        SELECT x.ms_id, ui.date AS inv_date, COALESCE(SUM(uii.total), 0) AS achieved
        FROM (
            SELECT DISTINCT o.ms_id, t.invoiced_inv_id
            FROM ms_orders o
            JOIN tp_orders t ON t.order_id = o.order_id
            WHERE o.ms_id IN ($idList) AND o.new_order = 'yes'
              AND o.order_date BETWEEN '$fromEsc' AND '$toEsc'
              AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''
        ) x
        JOIN user_invoice ui ON ui.inv_id = x.invoiced_inv_id
        JOIN user_invoice_items uii ON uii.inv_id = x.invoiced_inv_id
        JOIN products p ON p.id = uii.pr_id
        WHERE COALESCE(p.category,'') != 'diaper'
        GROUP BY x.ms_id, ui.date
        ORDER BY ui.date ASC
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $id = (int)$r['ms_id'];
            if (!isset($out[$id])) { continue; }
            $out[$id][$r['inv_date']] = (float)$r['achieved'];
        }
    }
    return $out;
}

// First date (within [$fromDate, $toDate]) on which $msId's cumulative
// Napkin-only achieved sales reached $target — null if never reached in
// range. $dailyByMsId is getRawAchievedDailyByMsId()'s own output (passed
// in rather than re-queried, so a caller checking several people reuses one
// batched query).
function getTargetCompletedDate(array $dailyByMsId, int $msId, float $target): ?string {
    if ($target <= 0) { return null; }
    $days = $dailyByMsId[$msId] ?? [];
    ksort($days);
    $running = 0.0;
    foreach ($days as $date => $amt) {
        $running += $amt;
        if ($running >= $target) { return $date; }
    }
    return null;
}

// Same "Achieved" definition as getRawTargetAchievedStats() above (Get
// Order-sourced, converted-to-invoice, Napkin-only sales in the date range),
// just grouped by the fulfilling TP (tp_orders.tp_id) instead of by the
// Marketing Staff member who placed the order — for a per-TP Target/
// Achieved/Balance table. Target isn't computed here; callers already have
// each TP's own target (e.g. dashboard.php's $tpRoster) from elsewhere.
function getTpOrderAchievedStats($db_conn, array $tpIds, string $fromDate, string $toDate): array {
    $stats = [];
    foreach ($tpIds as $id) { $stats[(int)$id] = 0.0; }
    if (empty($tpIds)) return $stats;

    $idList = implode(',', array_map('intval', $tpIds));
    $fromEsc = $db_conn->real_escape_string($fromDate);
    $toEsc   = $db_conn->real_escape_string($toDate);

    $resA = $db_conn->query("
        SELECT x.tp_id, COALESCE(SUM(uii.total), 0) AS achieved
        FROM (
            SELECT DISTINCT t.tp_id, t.invoiced_inv_id
            FROM tp_orders t
            JOIN ms_orders o ON o.order_id = t.order_id
            WHERE t.tp_id IN ($idList) AND o.new_order = 'yes'
              AND o.order_date BETWEEN '$fromEsc' AND '$toEsc'
              AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''
        ) x
        JOIN user_invoice_items uii ON uii.inv_id COLLATE utf8mb4_general_ci = x.invoiced_inv_id COLLATE utf8mb4_general_ci
        JOIN products p ON p.id = uii.pr_id
        WHERE COALESCE(p.category,'') != 'diaper'
        GROUP BY x.tp_id
    ");
    if ($resA) {
        while ($r = $resA->fetch_assoc()) {
            $stats[(int)$r['tp_id']] = (float)$r['achieved'];
        }
    }

    return $stats;
}
?>
