<?php
// Per-Sales-BDM target vs achieved, reusing the exact same formula as
// dashboard.php's own "Overall Target %" card (target = SUM of
// partner_location_nodes.target_amount across that BDM's assigned/active
// TPs; achieved = their TPs' Napkin-only downstream sales in the range) —
// just parameterized by an arbitrary bdm_id instead of always "me", so
// my-team.php can show the same figure for every BDM in the reporting chain.

function getBdmRawTargetAchieved($db_conn, int $bdmId, string $fromDate, string $toDate): array {
    require_once __DIR__ . '/BdmTpScope.php';
    $tpIds = getBdmAssignedTpIds($db_conn, $bdmId);
    if (empty($tpIds)) {
        return ['target' => 0.0, 'achieved' => 0.0, 'tp_count' => 0, 'advance_paid' => 0.0, 'napkin_purchase' => 0.0];
    }
    $tpIdList = implode(',', array_map('intval', $tpIds));

    $target = (float)($db_conn->query("
        SELECT COALESCE(SUM(pln.target_amount),0) FROM territory_partner_locations tpl
        JOIN partner_location_nodes pln ON pln.id = tpl.location_id
        WHERE tpl.territory_partner_id IN ($tpIdList)
    ")->fetch_row()[0] ?? 0);

    // Napkin advance payments received in the range — same figure as
    // dashboard.php's own "Overall Target %" card ($overall_achieved there);
    // this is what the % column is actually based on, not downstream sales.
    $stmtAdvance = $db_conn->prepare("
        SELECT COALESCE(SUM(amount),0) FROM tp_advance_payments
        WHERE territory_partner_id IN ($tpIdList) AND product_type='napkin' AND deleted_at IS NULL
          AND payment_date BETWEEN ? AND ?
    ");
    $stmtAdvance->bind_param('ss', $fromDate, $toDate);
    $stmtAdvance->execute();
    $advancePaid = (float)($stmtAdvance->get_result()->fetch_row()[0] ?? 0);
    $stmtAdvance->close();

    $stmtCust = $db_conn->prepare("
        SELECT COALESCE(SUM(ii.total),0) FROM invoice_items ii
        JOIN invoice i ON i.inv_id = ii.inv_id JOIN products p ON p.id = ii.pr_id
        WHERE i.user_type='territory_partner' AND i.sub_total>0 AND i.date BETWEEN ? AND ?
          AND i.user_id IN ($tpIdList) AND COALESCE(p.category,'') != 'diaper'
    ");
    $stmtCust->bind_param('ss', $fromDate, $toDate);
    $stmtCust->execute();
    $custAmt = (float)($stmtCust->get_result()->fetch_row()[0] ?? 0);
    $stmtCust->close();

    $stmtShop = $db_conn->prepare("
        SELECT COALESCE(SUM(uii.total),0) FROM user_invoice_items uii
        JOIN user_invoice ui ON ui.inv_id = uii.inv_id JOIN products p ON p.id = uii.pr_id
        WHERE ui.from_user_type='territory_partner' AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?
          AND ui.from_user_id IN ($tpIdList) AND COALESCE(p.category,'') != 'diaper'
    ");
    $stmtShop->bind_param('ss', $fromDate, $toDate);
    $stmtShop->execute();
    $shopAmt = (float)($stmtShop->get_result()->fetch_row()[0] ?? 0);
    $stmtShop->close();

    // How much these TPs bought FROM the company (tp_invoices) in the range —
    // distinct from 'achieved' above, which is their downstream resale to
    // customers/shops. Same Napkin-only filter, same query shape as
    // dashboard.php's "Purchases from Company" $napkinPurchaseByTp.
    $stmtPurchase = $db_conn->prepare("
        SELECT COALESCE(SUM(tii.amount),0) FROM tp_invoice_items tii
        JOIN tp_invoices ti ON ti.id = tii.tp_invoice_id
        JOIN products p ON p.id = tii.product_id
        WHERE ti.territory_partner_id IN ($tpIdList) AND ti.invoice_date BETWEEN ? AND ?
          AND COALESCE(p.category,'') != 'diaper'
    ");
    $stmtPurchase->bind_param('ss', $fromDate, $toDate);
    $stmtPurchase->execute();
    $napkinPurchase = (float)($stmtPurchase->get_result()->fetch_row()[0] ?? 0);
    $stmtPurchase->close();

    return [
        'target' => $target, 'achieved' => $custAmt + $shopAmt, 'tp_count' => count($tpIds),
        'napkin_purchase' => $napkinPurchase, 'advance_paid' => $advancePaid,
    ];
}

// Per-TP breakdown for one BDM — District/Firka + own target/achieved, same
// shape as dashboard.php's "Purchases from Company — by TP" achievement
// columns, used by the drill-down when a manager clicks into a team member.
function getBdmTpBreakdown($db_conn, int $bdmId, string $fromDate, string $toDate): array {
    require_once __DIR__ . '/BdmTpScope.php';
    $tpIds = getBdmAssignedTpIds($db_conn, $bdmId);
    if (empty($tpIds)) return [];
    $tpIdList = implode(',', array_map('intval', $tpIds));

    $tps = $db_conn->query("SELECT id, name, tp_id, branch_district FROM territory_partners WHERE id IN ($tpIdList) ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

    $targetByTp = [];
    $tgtRes = $db_conn->query("
        SELECT tpl.territory_partner_id tp_id, COALESCE(SUM(pln.target_amount),0) target,
               GROUP_CONCAT(DISTINCT pln.name ORDER BY pln.name SEPARATOR ', ') loc_names
        FROM territory_partner_locations tpl JOIN partner_location_nodes pln ON pln.id = tpl.location_id
        WHERE tpl.territory_partner_id IN ($tpIdList)
        GROUP BY tpl.territory_partner_id
    ");
    while ($r = $tgtRes->fetch_assoc()) { $targetByTp[(int)$r['tp_id']] = $r; }

    $achievedByTp = [];
    $stmtCust = $db_conn->prepare("
        SELECT i.user_id tp_id, COALESCE(SUM(ii.total),0) amt FROM invoice_items ii
        JOIN invoice i ON i.inv_id = ii.inv_id JOIN products p ON p.id = ii.pr_id
        WHERE i.user_type='territory_partner' AND i.sub_total>0 AND i.date BETWEEN ? AND ?
          AND i.user_id IN ($tpIdList) AND COALESCE(p.category,'') != 'diaper'
        GROUP BY i.user_id
    ");
    $stmtCust->bind_param('ss', $fromDate, $toDate);
    $stmtCust->execute();
    $custRes = $stmtCust->get_result();
    while ($r = $custRes->fetch_assoc()) { $achievedByTp[(int)$r['tp_id']] = ($achievedByTp[(int)$r['tp_id']] ?? 0) + (float)$r['amt']; }
    $stmtCust->close();

    $stmtShop = $db_conn->prepare("
        SELECT ui.from_user_id tp_id, COALESCE(SUM(uii.total),0) amt FROM user_invoice_items uii
        JOIN user_invoice ui ON ui.inv_id = uii.inv_id JOIN products p ON p.id = uii.pr_id
        WHERE ui.from_user_type='territory_partner' AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?
          AND ui.from_user_id IN ($tpIdList) AND COALESCE(p.category,'') != 'diaper'
        GROUP BY ui.from_user_id
    ");
    $stmtShop->bind_param('ss', $fromDate, $toDate);
    $stmtShop->execute();
    $shopRes = $stmtShop->get_result();
    while ($r = $shopRes->fetch_assoc()) { $achievedByTp[(int)$r['tp_id']] = ($achievedByTp[(int)$r['tp_id']] ?? 0) + (float)$r['amt']; }
    $stmtShop->close();

    $out = [];
    foreach ($tps as $tp) {
        $tid = (int)$tp['id'];
        $target = (float)($targetByTp[$tid]['target'] ?? 0);
        $achieved = (float)($achievedByTp[$tid] ?? 0);
        $out[] = [
            'id' => $tid,
            'name' => $tp['name'],
            'tp_code' => $tp['tp_id'],
            'district' => $tp['branch_district'],
            'firkas' => $targetByTp[$tid]['loc_names'] ?? '',
            'target' => $target,
            'achieved' => $achieved,
            'pct' => $target > 0 ? min(round($achieved / $target * 100, 1), 999) : 0,
        ];
    }
    return $out;
}
