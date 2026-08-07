<?php
// Rolls up an individual Marketing Staff member's own monthly target
// (prorated to a date range) and what they've actually achieved (converted
// invoice value), batched for a whole list of ms_ids at once — reused by
// marketing/my-team.php (ASM/SM's own team view) and company/ms-team-view.php
// (company's org-chart tree) so both surface the exact same numbers computed
// the exact same way as the single-person card already on
// marketing/manage_order_product.php (lines ~313-336).

function getRawTargetAchievedStats($db_conn, array $ids, string $fromDate, string $toDate): array {
    $stats = [];
    foreach ($ids as $id) { $stats[(int)$id] = ['target' => 0.0, 'achieved' => 0.0]; }
    if (empty($ids)) return $stats;

    $idList = implode(',', array_map('intval', $ids));

    $daysInMonth = (int)date('t', strtotime($fromDate));
    $daysInRange = (int)floor((strtotime($toDate) - strtotime($fromDate)) / 86400) + 1;
    if ($daysInRange < 1) { $daysInRange = 1; }

    // Target — each person's own monthly_target_amount, prorated to the range.
    $resT = $db_conn->query("SELECT id, monthly_target_amount FROM marketing_staff WHERE id IN ($idList)");
    if ($resT) {
        while ($r = $resT->fetch_assoc()) {
            $monthly = (float)($r['monthly_target_amount'] ?? 0);
            $perDay = $daysInMonth > 0 ? $monthly / $daysInMonth : 0.0;
            $stats[(int)$r['id']]['target'] = $perDay * $daysInRange;
        }
    }

    // Achieved — sum of invoice totals for orders this person got converted,
    // in the same date range. Dedups (ms_id, invoiced_inv_id) BEFORE summing
    // user_invoice.total, since tp_orders has one row per product line —
    // joining straight through would add the same invoice's total once per
    // line item instead of once per invoice (matches
    // manage_order_product.php's distinct-invoice-ids-then-sum approach).
    $fromEsc = $db_conn->real_escape_string($fromDate);
    $toEsc   = $db_conn->real_escape_string($toDate);
    $resA = $db_conn->query("
        SELECT x.ms_id, COALESCE(SUM(ui.total), 0) AS achieved
        FROM (
            SELECT DISTINCT o.ms_id, t.invoiced_inv_id
            FROM ms_orders o
            JOIN tp_orders t ON t.order_id = o.order_id
            WHERE o.ms_id IN ($idList) AND o.new_order = 'yes'
              AND o.order_date BETWEEN '$fromEsc' AND '$toEsc'
              AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''
        ) x
        JOIN user_invoice ui ON ui.inv_id = x.invoiced_inv_id
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
