<?php
// Data for tp-multi-district-invoice-report.php (company-only) — 2026-11-30,
// user-requested follow-up to the "Your TPs' Invoice Amount" undercount fix
// (marketing/include/TpInvoiceAmount.php's new single-district fallback).
// A TP assigned to 2+ districts can't safely get that same fallback (which
// district would a broken/unresolvable invoice belong to?), so those TPs'
// invoices can still silently fall through the cracks on every DM dashboard.
// This report surfaces exactly that for company: every multi-district TP,
// split by which of their CURRENT districts each napkin invoice resolved to
// (same COALESCE(invoice_district_id, shop.district_id) rule, validated
// against a real District-depth node, that TpInvoiceAmount.php itself uses),
// plus a "Mismatch Amount" bucket for whatever didn't resolve to one of
// their own current districts at all (broken shop data, or sold under a
// district they've since been reassigned away from).
require_once __DIR__ . '/../../marketing/include/TpInvoiceAmount.php';

// TPs currently assigned to 2+ distinct districts, with their district list.
function getMultiDistrictTps($db_conn): array {
    $res = $db_conn->query("
        SELECT tpl.territory_partner_id AS tp_id, d.id AS district_id, d.name AS district_name
        FROM territory_partner_locations tpl
        INNER JOIN partner_location_nodes f  ON f.id  = tpl.location_id
        INNER JOIN partner_location_nodes t  ON t.id  = f.parent_id
        INNER JOIN partner_location_nodes dv ON dv.id = t.parent_id
        INNER JOIN partner_location_nodes d  ON d.id  = dv.parent_id
        GROUP BY tpl.territory_partner_id, d.id
    ");
    $byTp = [];
    while ($r = $res->fetch_assoc()) {
        $byTp[(int)$r['tp_id']][(int)$r['district_id']] = $r['district_name'];
    }
    // Keep only TPs with 2+ distinct districts.
    return array_filter($byTp, fn($districts) => count($districts) >= 2);
}

// Batch-resolves "which DM owns this district" (level_rank=3), same
// preference rule as company/include/DailyAssignReportData.php.
function getDmNamesByDistrictId($db_conn, array $districtIds): array {
    if (empty($districtIds)) { return []; }
    $idList = implode(',', array_map('intval', $districtIds));
    $out = [];
    $res = $db_conn->query("
        SELECT msl.location_id, ms.ms_name
        FROM marketing_staff_locations msl
        JOIN marketing_staff ms ON ms.id = msl.ms_id
        JOIN marketing_team_levels tl ON tl.id = ms.team_level_id AND tl.level_rank = 3
        WHERE msl.location_id IN ($idList) AND ms.account_status = 'active' AND ms.deleted_at IS NULL
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out[(int)$r['location_id']][] = $r['ms_name'];
        }
    }
    return $out;
}

// Returns one row per multi-district TP: tp_id, tp_name, tp_code, mobile,
// districts => [[district_id, district_name, dm_names, amount], ...],
// mismatch_amount, total_amount.
function getMultiDistrictTpInvoiceBreakdown($db_conn, string $from, string $to): array {
    $tpDistricts = getMultiDistrictTps($db_conn);
    if (empty($tpDistricts)) { return []; }
    $tpIds = array_keys($tpDistricts);

    ensureTpInvoiceAmountIndexes($db_conn);
    $tpList = implode(',', array_map('intval', $tpIds));
    $res = $db_conn->query("
        SELECT ui.from_user_id AS tp_id, rd.id AS district_id, COALESCE(SUM(uii.total),0) AS amt
        FROM user_invoice ui
        STRAIGHT_JOIN shop s ON s.temp_id = ui.to_user_id
        STRAIGHT_JOIN user_invoice_items uii ON uii.inv_id = ui.inv_id
        STRAIGHT_JOIN products p ON p.id = uii.pr_id
        LEFT JOIN partner_location_nodes rd ON rd.id = COALESCE(ui.invoice_district_id, s.district_id) AND rd.depth = 3
        WHERE ui.from_user_type='territory_partner' AND ui.to_user_type='shop'
          AND ui.from_user_id IN ($tpList)
          AND ui.`date` BETWEEN '" . $db_conn->real_escape_string($from) . "' AND '" . $db_conn->real_escape_string($to) . "'
          AND p.category = 'napkin'
        GROUP BY ui.from_user_id, rd.id
    ");
    $amtByTpDistrict = [];
    while ($r = $res->fetch_assoc()) {
        $tpId = (int)$r['tp_id'];
        $distId = $r['district_id'] !== null ? (int)$r['district_id'] : null;
        $amtByTpDistrict[$tpId][$distId] = (float)$r['amt'];
    }

    $allDistrictIds = [];
    foreach ($tpDistricts as $districts) { $allDistrictIds = array_merge($allDistrictIds, array_keys($districts)); }
    $dmNamesByDistrict = getDmNamesByDistrictId($db_conn, array_unique($allDistrictIds));

    $tpDetails = [];
    $res = $db_conn->query("SELECT id, tp_id, name, mobile FROM territory_partners WHERE id IN ($tpList)");
    while ($r = $res->fetch_assoc()) { $tpDetails[(int)$r['id']] = $r; }

    $rows = [];
    foreach ($tpDistricts as $tpId => $districts) {
        $tp = $tpDetails[$tpId] ?? null;
        if (!$tp) { continue; }
        $amounts = $amtByTpDistrict[$tpId] ?? [];
        $districtRows = [];
        $assignedTotal = 0.0;
        foreach ($districts as $distId => $distName) {
            $amt = $amounts[$distId] ?? 0.0;
            $assignedTotal += $amt;
            $districtRows[] = [
                'district_id'   => $distId,
                'district_name' => $distName,
                'dm_names'      => $dmNamesByDistrict[$distId] ?? [],
                'amount'        => $amt,
            ];
        }
        $totalAmount = array_sum($amounts);
        $mismatchAmount = round($totalAmount - $assignedTotal, 2);
        if ($mismatchAmount < 0) { $mismatchAmount = 0.0; } // floating-point guard only

        $rows[] = [
            'tp_id'           => $tpId,
            'tp_code'         => $tp['tp_id'],
            'tp_name'         => $tp['name'],
            'mobile'          => $tp['mobile'],
            'districts'       => $districtRows,
            'mismatch_amount' => $mismatchAmount,
            'total_amount'    => $totalAmount,
        ];
    }

    usort($rows, fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);
    return $rows;
}
