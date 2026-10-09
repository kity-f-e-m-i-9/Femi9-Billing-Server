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
// Target source: each person's own assigned district(s) -> every Firka's
// target_amount in that district (filled AND vacant — same total the
// District Dashboard's "District Total Target" card shows), NOT the old
// marketing_staff.monthly_target_amount field. A non-leaf (SM/ASM) normally
// has no district of their own assigned, so this naturally comes back 0 for
// them; the caller (msTeamViewSubtreeTargetSum() / my-team.php's own
// equivalent) still zeroes a manager's own raw target whenever they have a
// team below them and rolls up the children's sum instead, same convention
// as before — only the leaf's own number's *source* changed.
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

    // Target — each person's assigned district(s)' full Firka total
    // (filled + vacant), prorated to the range the same way the old
    // monthly-amount figure was.
    foreach ($ids as $id) {
        $id = (int)$id;
        $districts = getMsAssignedDistricts($db_conn, $id);
        if (empty($districts)) continue;
        $districtNames = array_column($districts, 'name');
        $districtStats = getMsDistrictTargetAndFirkaStats($db_conn, $districtNames);
        $monthly = (float)$districtStats['district_total_target'];
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
        JOIN user_invoice_items uii ON uii.inv_id COLLATE utf8mb4_general_ci = x.invoiced_inv_id COLLATE utf8mb4_general_ci
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
?>
