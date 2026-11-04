<?php require_once __DIR__ . '/../shared/db-helpers.php'; 
include("checksession.php");
include("config.php"); 

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// ========================================
// CHECK IF USER MUST CHANGE PASSWORD
// ========================================
$userMobile = $result_LoGuserDtails['ms_mobile'];
$userType = 'marketing'; // Change based on user type

// Check if user has a pending password reset
$checkResetStmt = mysqli_prepare($db_conn, 
    "SELECT id, reset_at FROM forgotpassword 
     WHERE usertype = ? AND mobilenumber = ? AND must_change_password = 1 
     ORDER BY reset_at DESC LIMIT 1"
);
mysqli_stmt_bind_param($checkResetStmt, "ss", $userType, $userMobile);
mysqli_stmt_execute($checkResetStmt);
$resetResult = mysqli_stmt_get_result($checkResetStmt);
$resetData = mysqli_fetch_assoc($resetResult);
mysqli_stmt_close($checkResetStmt);

// If user has pending password reset, force them to change password
if ($resetData) {
    echo "<script>
        alert('For security reasons, you must change your password before continuing.');
        window.location='change-password.php?forced=1';
    </script>";
    exit;
}
// ========================================


//INSERT ATTENDANCE
date_default_timezone_set("Asia/Kolkata");
$ATTND_date=date("Y-m-d");
$ATTND_time=date("H:i:s");

$select_AttendanceCount="select * from ms_attendance where ms_id=? and date=?";
$fetch_AttendanceCount=db_q($db_conn,$select_AttendanceCount,[$markeingSTFID,$ATTND_date]);
$result_AttendanceCount=mysqli_num_rows($fetch_AttendanceCount);
if($result_AttendanceCount==0)
	{
		$insertATTND="insert into ms_attendance (ms_id,date,time) values (?,?,?)";
		db_q($db_conn,$insertATTND,[$markeingSTFID,$ATTND_date,$ATTND_time]);

	}

// ========================================
// DISTRICT DASHBOARD DATA
// ========================================
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/include/MsDistrictScope.php';

$msId          = (int)$Login_user_IDvl;

// Areas the company assigned to this person TODAY (company/ms_edit.php and
// ms_action.php both re-insert every current row into marketing_staff_locations
// on every save, so this fires again if the company just re-saves an
// unchanged assignment the same day — acceptable here since the point is
// "an admin touched my area today", not a strict diff). Surfaced as a modal
// that pops up the moment this page loads, so a DM/ASM sees it the instant
// they log in instead of having to notice it buried in a card somewhere.
$todayAssignedAreas = [];
$_resTodayLoc = $db_conn->query("
    SELECT n.name, n.depth
    FROM marketing_staff_locations msl
    JOIN partner_location_nodes n ON n.id = msl.location_id
    WHERE msl.ms_id = $msId AND DATE(msl.created_at) = CURDATE()
    ORDER BY n.name
");
if ($_resTodayLoc) {
    while ($_r = $_resTodayLoc->fetch_assoc()) {
        $todayAssignedAreas[] = $_r['name'];
    }
}

// Target-scope nodes: District OR Division depth, used as-is (not collapsed
// up to the enclosing district) — see getMsAssignedTargetScopeNodes()'s own
// doc comment. $districtNames stays the generic "assigned location name(s)"
// list the rest of this page (team roster / cross-district TP split below)
// already keys off of; those stay district-oriented (out of scope for the
// division feature — see the plan), so a division assignment simply won't
// contribute to that particular downstream widget, same as before.
$scopeNodes    = getMsAssignedTargetScopeNodes($db_conn, $msId);
$districts     = $scopeNodes;
$districtNames = array_column($scopeNodes, 'name');
$districtLabel = !empty($districtNames) ? implode(', ', $districtNames) : 'No District Assigned';
// true only when EVERY assigned scope node is Division depth (4) — drives
// the "District"/"Division" wording on the dashboard's own cards below.
$isDivisionScope = !empty($scopeNodes) && count(array_filter($scopeNodes, fn($n) => (int)$n['depth'] === 4)) === count($scopeNodes);
$scopeWord = $isDivisionScope ? 'Division' : 'District';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to   = date('Y-m-d'); }

// Embedded in company's MIS Report "Marketing Staff" tab iframe (see
// company/view-ms-dashboard.php + mis-report.php) — same ?embed=1 pattern
// salesbdm/dashboard.php already uses: hides this page's own sidebar/header
// chrome so only the actual dashboard content shows inside the iframe.
$embed = !empty($_GET['embed']);

$scopeIds       = array_column($scopeNodes, 'id');

// ── District Managers under this person — clickable list, each showing their
// own assigned district(s) + target/achieved/balance for the same period.
// "DM" = whichever marketing_team_levels row has "District" in its name, same
// convention ms-team-view.php uses for the "District" label under a branch.
// Computed BEFORE the top summary cards below so their scope can be widened
// to the whole team (see $teamScopeIds) — an ASM/SM is often assigned no
// district of their own, or a narrower one than their DMs' combined
// districts, which used to make this page's own "District Total Target" /
// "Your TPs' Invoice Amount" cards read LOWER than the sum of the DM rows
// directly underneath them (reported 2026-10-31: ASM showed 15,65,000 total
// target while their 5 DMs' own rows summed to 27,70,000) — confusing since
// a roll-up total should never be smaller than its own parts.
require_once __DIR__ . '/../company/include/TeamTargetRollup.php';
$myDms = [];
$teamScopeIds = [];
$dmTpIdsById = [];
$_staffRows = $db_conn->query("
    SELECT ms.id, ms.ms_name, ms.manager_id, tl.level_name
    FROM marketing_staff ms
    LEFT JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
    WHERE ms.deleted_at IS NULL
")->fetch_all(MYSQLI_ASSOC);
$_byManager = [];
foreach ($_staffRows as $row) {
    $_byManager[$row['manager_id'] ? (int)$row['manager_id'] : 0][] = $row;
}
// BFS from the logged-in person down through every level (not just direct
// reports) to collect every DM anywhere below them in the chain.
$_queue = [$msId];
$_dmCandidates = [];
while (!empty($_queue)) {
    $_cur = array_shift($_queue);
    foreach ($_byManager[$_cur] ?? [] as $_child) {
        if (stripos($_child['level_name'] ?? '', 'District') !== false) {
            $_dmCandidates[] = $_child;
        }
        $_queue[] = (int)$_child['id'];
    }
}
if (!empty($_dmCandidates)) {
    $_dmIds = array_column($_dmCandidates, 'id');
    // "Achieved" stays period-bound (Napkin-category converted sales in the
    // selected from/to range) — that part of getRawTargetAchievedStats is
    // genuinely a performance-for-this-period number. "Target" is NOT taken
    // from there, though: that figure is prorated to the date range (see
    // TeamTargetRollup.php), which silently disagreed with the plain,
    // un-prorated district total this same DM sees as their own "District
    // Total Target" card when they log in directly — same number the top
    // cards above use. Pulled fresh here via getMsDistrictTargetAndFirkaStats
    // instead, so a DM's chip always matches what that DM actually sees.
    $_dmTargetStats = getRawTargetAchievedStats($db_conn, $_dmIds, $from, $to);
    foreach ($_dmCandidates as $_dm) {
        $_dmId = (int)$_dm['id'];
        $_dmScopeNodes = getMsAssignedTargetScopeNodes($db_conn, $_dmId);
        $_dmDistrictNames = array_column($_dmScopeNodes, 'name');
        $_dmDistrictLabel = !empty($_dmDistrictNames) ? implode(', ', $_dmDistrictNames) : 'No District Assigned';
        $_dmScopeIds = array_column($_dmScopeNodes, 'id');
        $teamScopeIds = array_merge($teamScopeIds, $_dmScopeIds);
        $_t = !empty($_dmScopeIds) ? getMsNodeTargetAndFirkaStats($db_conn, $_dmScopeIds)['district_total_target'] : 0.0;
        $_a = $_dmTargetStats[$_dmId]['achieved'] ?? 0.0;

        // "Your TPs' Invoice Amount" for just this DM's own district — same
        // figure/query as the top-level $invoicedAmount card above, just
        // scoped to this one DM's TPs instead of every TP in the whole team.
        // Broader than "Achieved" above (which only counts Get-Order-sourced,
        // Napkin-only sales): this is every shop invoice any of this DM's TPs
        // raised in the period, same as what the DM sees on their own
        // dashboard's "Your TPs' Invoice Amount" card.
        $_dmTpIds = !empty($_dmScopeIds) ? getMsNodeTpIds($db_conn, $_dmScopeIds, true) : [];
        $dmTpIdsById[$_dmId] = $_dmTpIds;
        $_dmInvoiced = 0.0;
        if (!empty($_dmTpIds)) {
            $_dmIdList = implode(',', $_dmTpIds);
            $_stmtDmInv = $db_conn->prepare(
                "SELECT COALESCE(SUM(total),0) AS amt FROM user_invoice
                 WHERE from_user_type='territory_partner' AND from_user_id IN ($_dmIdList)
                   AND `date` BETWEEN ? AND ?"
            );
            $_stmtDmInv->bind_param('ss', $from, $to);
            $_stmtDmInv->execute();
            $_dmInvoiced = (float)($_stmtDmInv->get_result()->fetch_assoc()['amt'] ?? 0);
            $_stmtDmInv->close();
        }

        // Achieve Total = Achieved (this DM's own Get Order-converted sales)
        // + TP Invoice Amount (every shop invoice this DM's TPs raised) —
        // Balance is Target minus that combined total.
        $_achieveTotal = $_a + $_dmInvoiced;

        $myDms[] = [
            'id'                => $_dmId,
            'name'              => $_dm['ms_name'],
            'district'          => $_dmDistrictLabel,
            'target'            => $_t,
            'achieved'          => $_a,
            'balance'           => max($_t - $_a, 0),
            'tp_invoice_amount' => $_dmInvoiced,
            'achieve_total'     => $_achieveTotal,
            'total_balance'     => max($_t - $_achieveTotal, 0),
        ];
    }
    usort($myDms, fn($a, $b) => strcmp($a['name'], $b['name']));
}

// Scope used for this page's own top summary cards: the person's own direct
// assignment PLUS every DM-below-them's own assignment, deduped — so a
// manager's "District Total Target" / "Your TPs' Invoice Amount" / "Balance"
// always cover at least as much as the DM rows shown below them. Falls back
// to the own-only scope for an actual DM (no one below them, $myDms empty),
// unchanged from before.
$statsScopeIds = !empty($myDms) ? array_values(array_unique(array_merge($scopeIds, $teamScopeIds))) : $scopeIds;

// ── TP Invoice Amount for districts with NO DM assigned — this ASM/SM's own
// assigned district(s)/division(s) ($scopeIds) that none of their DMs below
// ($teamScopeIds) actually cover. Shown alongside the DM table so the TP
// Invoice Amount for shops in an unstaffed district is visible by name, not
// silently folded into the top summary cards only.
$noDmDistrictRows = [];
if (!empty($myDms)) {
    $noDmNodeIds = array_values(array_diff($scopeIds, $teamScopeIds));
    $scopeNodeNameById = array_column($scopeNodes, 'name', 'id');
    foreach ($noDmNodeIds as $_nodeId) {
        $_nodeTpIds = getMsNodeTpIds($db_conn, [$_nodeId], true);
        $_nodeInvoiced = 0.0;
        if (!empty($_nodeTpIds)) {
            $_nodeIdList = implode(',', $_nodeTpIds);
            $_stmtNodeInv = $db_conn->prepare(
                "SELECT COALESCE(SUM(total),0) AS amt FROM user_invoice
                 WHERE from_user_type='territory_partner' AND from_user_id IN ($_nodeIdList)
                   AND `date` BETWEEN ? AND ?"
            );
            $_stmtNodeInv->bind_param('ss', $from, $to);
            $_stmtNodeInv->execute();
            $_nodeInvoiced = (float)($_stmtNodeInv->get_result()->fetch_assoc()['amt'] ?? 0);
            $_stmtNodeInv->close();
        }
        $noDmDistrictRows[] = [
            'district'    => $scopeNodeNameById[$_nodeId] ?? ('#' . $_nodeId),
            'tp_invoice'  => $_nodeInvoiced,
        ];
    }
    usort($noDmDistrictRows, fn($a, $b) => strcmp($a['district'], $b['district']));
}

// ── Zone-wise Target Rollup — ASM/SM view only (any login with DMs below
// them). "Zone" is the company-wide District grouping from Manage Zones
// (partner_zones / partner_zone_districts — same table salesbdm's Zone
// field and marketing's own "Fill from Zone" picker already read). For
// every zone that contains at least one of this team's own districts, the
// zone's Target rolls up EVERY district that belongs to that zone — not
// just the ones a DM happens to be covering right now — so a zone still
// shows a real target even if part of it currently has no DM assigned
// (target is a property of the zone's districts, not of staffing).
// Achieved / TP Invoice Amount, by contrast, stay scoped to this team's own
// $myDms rows (same "Achieved"/"TP Invoice Amount" figures already shown
// per-DM above) — whichever of this team's DMs' own districts fall inside
// that zone.
require_once __DIR__ . '/../company/include/PartnerZones.php';
$zoneRollup = [];
$calendarWeeks = [];
$_daysInMonth = 0;
if (!empty($myDms)) {
    // Calendar-accurate Mon–Sun weeks for the month $from falls in: the
    // first week runs from the 1st through its first Sunday (whatever
    // weekday the 1st lands on), middle weeks are full Mon–Sun, and the
    // last week is clipped to the month's last day instead of being padded
    // out to a full 7 days (e.g. a month ending on a Thursday gives a
    // Mon–Thu final week, not Mon–Sun). Shared by both the Zone weekly
    // breakdown below and each DM's own weekly breakdown in the Target vs
    // Achieved table.
    $_monthFirst = new DateTime(date('Y-m-01', strtotime($from)));
    $_monthLast  = (clone $_monthFirst)->modify('last day of this month');
    $_daysInMonth = (int)$_monthLast->format('d');
    $_cursor = clone $_monthFirst;
    while ($_cursor <= $_monthLast) {
        $_weekStart = clone $_cursor;
        $_dow = (int)$_weekStart->format('N'); // 1=Mon .. 7=Sun
        $_weekEnd = (clone $_weekStart)->modify('+' . (7 - $_dow) . ' days');
        if ($_weekEnd > $_monthLast) { $_weekEnd = clone $_monthLast; }
        $calendarWeeks[] = ['start' => $_weekStart->format('Y-m-d'), 'end' => $_weekEnd->format('Y-m-d')];
        $_cursor = (clone $_weekEnd)->modify('+1 day');
    }
}
if (!empty($myDms) && !empty($teamScopeIds)) {
    ensurePartnerZonesTables($db_conn);
    $teamScopeIdList = implode(',', array_map('intval', array_unique($teamScopeIds)));
    $resZ = $db_conn->query("
        SELECT DISTINCT z.id, z.name
        FROM partner_zone_districts zd
        JOIN partner_zones z ON z.id = zd.zone_id
        WHERE zd.district_node_id IN ($teamScopeIdList)
        ORDER BY z.name ASC
    ");
    $teamZones = $resZ ? $resZ->fetch_all(MYSQLI_ASSOC) : [];

    foreach ($teamZones as $z) {
        $zoneId = (int)$z['id'];
        $zoneDistrictIds = getZoneDistrictNodeIds($db_conn, $zoneId);
        $zoneStats = !empty($zoneDistrictIds) ? getMsNodeTargetAndFirkaStats($db_conn, $zoneDistrictIds) : ['district_total_target' => 0.0];
        $zoneTarget = (float)$zoneStats['district_total_target'];
        $zoneMonthlyPerDay = $_daysInMonth > 0 ? $zoneTarget / $_daysInMonth : 0.0;

        $zoneDistrictNames = array_map(fn($n) => mb_strtolower(trim($n)), getZoneDistrictNames($db_conn, $zoneId));
        $zoneAchieved = 0.0;
        $zoneTpInvoiced = 0.0;
        foreach ($myDms as $_dm) {
            foreach (explode(', ', $_dm['district']) as $_dn) {
                if (in_array(mb_strtolower(trim($_dn)), $zoneDistrictNames, true)) {
                    $zoneAchieved   += $_dm['achieved'];
                    $zoneTpInvoiced += $_dm['tp_invoice_amount'];
                    break;
                }
            }
        }
        $zoneAchieveTotal = $zoneAchieved + $zoneTpInvoiced;
        $zoneOverageAmt = $zoneAchieveTotal - $zoneTarget;
        $zoneOveragePct = $zoneTarget > 0 ? ($zoneAchieveTotal / $zoneTarget) * 100 : 0.0;

        $zoneWeeks = [];
        foreach ($calendarWeeks as $_wk) {
            $_wkDays = (int)floor((strtotime($_wk['end']) - strtotime($_wk['start'])) / 86400) + 1;
            $zoneWeeks[] = [
                'start'  => $_wk['start'],
                'end'    => $_wk['end'],
                'target' => $zoneMonthlyPerDay * $_wkDays,
            ];
        }

        $zoneRollup[] = [
            'zone_id'       => $zoneId,
            'name'          => $z['name'],
            'target'        => $zoneTarget,
            'achieved'      => $zoneAchieved,
            'tp_invoice'    => $zoneTpInvoiced,
            'achieve_total' => $zoneAchieveTotal,
            'overage_amt'   => $zoneOverageAmt,
            'overage_pct'   => $zoneOveragePct,
            'weeks'         => $zoneWeeks,
        ];
    }
}

// ── Daily Visit Compliance (35 shop visits/day, 20 of them Get Order) ──────
// For an ASM/SM, each DM's own day-by-day numbers are rolled up into a
// compliance summary (days meeting each target out of the days in range,
// plus the daily average) and attached onto that DM's own $myDms row — read
// in the "My District Managers" detail chip below. For an actual DM (no one
// below them), the full day-by-day table itself is shown instead.
require_once __DIR__ . '/../shared/MsDailyVisitReport.php';
$myOwnVisitDays = [];
if (!empty($myDms)) {
    foreach ($myDms as &$_dmRef) {
        $_dayStats = getMsDailyVisitDaySummary($db_conn, $from, $to, (int)$_dmRef['id']);
        $_totalDays = count($_dayStats);
        $_visitMetDays = count(array_filter($_dayStats, fn($d) => $d['visit_target_met']));
        $_getOrderMetDays = count(array_filter($_dayStats, fn($d) => $d['get_order_target_met']));
        $_dmRef['visit_total_days']      = $_totalDays;
        $_dmRef['visit_met_days']        = $_visitMetDays;
        $_dmRef['get_order_met_days']    = $_getOrderMetDays;
        $_dmRef['avg_visits_per_day']    = $_totalDays > 0 ? array_sum(array_column($_dayStats, 'total_visits')) / $_totalDays : 0.0;
        $_dmRef['avg_get_order_per_day'] = $_totalDays > 0 ? array_sum(array_column($_dayStats, 'get_order_visits')) / $_totalDays : 0.0;
        $_dmRef['new_shop_visits']       = array_sum(array_column($_dayStats, 'new_visits'));
        $_dmRef['existing_shop_visits']  = array_sum(array_column($_dayStats, 'existing_visits'));
        $_dmRef['visit_days']            = $_dayStats;
    }
    unset($_dmRef);
} else {
    $myOwnVisitDays = getMsDailyVisitDaySummary($db_conn, $from, $to, $msId);
}

$districtStats  = getMsNodeTargetAndFirkaStats($db_conn, $statsScopeIds);

// ── Target Completed Date — the date Napkin-only (no Diaper) cumulative
// achieved sales first reached the Target Amount for the selected period.
// One batched day-by-day query for every ms_id on this page (the team's own
// DMs, or just this person if they ARE the DM), then each person's own
// completion date is found by walking their own running total against
// their own target.
$_targetCompletionMsIds = !empty($myDms) ? array_column($myDms, 'id') : [$msId];
$dailyAchievedByMsId = getRawAchievedDailyByMsId($db_conn, $_targetCompletionMsIds, $from, $to);
if (!empty($myDms)) {
    foreach ($myDms as &$_dmRef2) {
        $_dmRef2['target_completed_date'] = getTargetCompletedDate($dailyAchievedByMsId, (int)$_dmRef2['id'], (float)$_dmRef2['target']);
    }
    unset($_dmRef2);
    $myOwnTargetCompletedDate = null;
} else {
    $myOwnTargetCompletedDate = getTargetCompletedDate($dailyAchievedByMsId, $msId, (float)$districtStats['district_total_target']);
}

// ── Per-DM Weekly Breakdown (Target, Achieved, TP Invoice, Total) — each
// week's Target is this DM's own Target Amount prorated by days in that
// week; Achieved comes from $dailyAchievedByMsId (already fetched above,
// same Napkin-only Get-Order-converted definition as the "Achieved" column)
// summed into whichever week each invoice date falls in; TP Invoice Amount
// is a fresh day-by-day query per DM (reusing $dmTpIdsById from the DM-
// build loop above) summed the same way. Shown via the "Weekly" button/
// modal on each DM's row, and rolled up into $teamWeeklyBreakdown for the
// "ASM Weekly Target Amount" button covering the whole team.
$teamWeeklyBreakdown = [];
if (!empty($myDms)) {
    foreach ($calendarWeeks as $_wk) {
        $teamWeeklyBreakdown[] = ['start' => $_wk['start'], 'end' => $_wk['end'], 'target' => 0.0, 'achieved' => 0.0, 'tp_invoice' => 0.0, 'total' => 0.0];
    }
    foreach ($myDms as &$_dmWeekRef) {
        $_dmPerDay = $_daysInMonth > 0 ? $_dmWeekRef['target'] / $_daysInMonth : 0.0;
        $_dmAchievedByDate = $dailyAchievedByMsId[(int)$_dmWeekRef['id']] ?? [];

        $_dmTpInvByDate = [];
        $_dmTpIdsForWeekly = $dmTpIdsById[(int)$_dmWeekRef['id']] ?? [];
        if (!empty($_dmTpIdsForWeekly)) {
            $_dmTpIdList = implode(',', $_dmTpIdsForWeekly);
            $_resDmInvDaily = $db_conn->query("
                SELECT `date`, COALESCE(SUM(total),0) AS amt FROM user_invoice
                WHERE from_user_type='territory_partner' AND from_user_id IN ($_dmTpIdList)
                  AND `date` BETWEEN '" . $db_conn->real_escape_string($from) . "' AND '" . $db_conn->real_escape_string($to) . "'
                GROUP BY `date`
            ");
            while ($_r = $_resDmInvDaily->fetch_assoc()) { $_dmTpInvByDate[$_r['date']] = (float)$_r['amt']; }
        }

        $_dmWeeks = [];
        foreach ($calendarWeeks as $_wkIdx => $_wk) {
            $_wkDays = (int)floor((strtotime($_wk['end']) - strtotime($_wk['start'])) / 86400) + 1;
            $_wkTarget = $_dmPerDay * $_wkDays;

            $_wkAchieved = 0.0;
            foreach ($_dmAchievedByDate as $_d => $_amt) {
                if ($_d >= $_wk['start'] && $_d <= $_wk['end']) { $_wkAchieved += $_amt; }
            }
            $_wkInvoiced = 0.0;
            foreach ($_dmTpInvByDate as $_d => $_amt) {
                if ($_d >= $_wk['start'] && $_d <= $_wk['end']) { $_wkInvoiced += $_amt; }
            }
            $_wkTotal = $_wkAchieved + $_wkInvoiced;

            $_dmWeeks[] = ['start' => $_wk['start'], 'end' => $_wk['end'], 'target' => $_wkTarget, 'achieved' => $_wkAchieved, 'tp_invoice' => $_wkInvoiced, 'total' => $_wkTotal];

            $teamWeeklyBreakdown[$_wkIdx]['target']     += $_wkTarget;
            $teamWeeklyBreakdown[$_wkIdx]['achieved']   += $_wkAchieved;
            $teamWeeklyBreakdown[$_wkIdx]['tp_invoice'] += $_wkInvoiced;
            $teamWeeklyBreakdown[$_wkIdx]['total']      += $_wkTotal;
        }
        $_dmWeekRef['weeks'] = $_dmWeeks;
    }
    unset($_dmWeekRef);

    // Fold in TP Invoice Amount for districts with NO DM assigned
    // ($noDmNodeIds, computed above) — the ASM Weekly Target Amount modal's
    // TP Invoice Amount otherwise only covered DM-assigned districts, same
    // gap the "TP Invoice Amount — No DM Assigned" tab exists to surface
    // separately; this folds that same money into the team's own weekly
    // total too, so the weekly Total isn't silently short.
    if (!empty($noDmNodeIds)) {
        $_noDmTpIds = getMsNodeTpIds($db_conn, $noDmNodeIds, true);
        if (!empty($_noDmTpIds)) {
            $_noDmTpIdList = implode(',', $_noDmTpIds);
            $_resNoDmInvDaily = $db_conn->query("
                SELECT `date`, COALESCE(SUM(total),0) AS amt FROM user_invoice
                WHERE from_user_type='territory_partner' AND from_user_id IN ($_noDmTpIdList)
                  AND `date` BETWEEN '" . $db_conn->real_escape_string($from) . "' AND '" . $db_conn->real_escape_string($to) . "'
                GROUP BY `date`
            ");
            $_noDmInvByDate = [];
            while ($_r = $_resNoDmInvDaily->fetch_assoc()) { $_noDmInvByDate[$_r['date']] = (float)$_r['amt']; }

            foreach ($calendarWeeks as $_wkIdx => $_wk) {
                $_wkNoDmInvoiced = 0.0;
                foreach ($_noDmInvByDate as $_d => $_amt) {
                    if ($_d >= $_wk['start'] && $_d <= $_wk['end']) { $_wkNoDmInvoiced += $_amt; }
                }
                $teamWeeklyBreakdown[$_wkIdx]['tp_invoice'] += $_wkNoDmInvoiced;
                $teamWeeklyBreakdown[$_wkIdx]['total']      += $_wkNoDmInvoiced;
            }
        }
    }

    // ── Get Order Return Amount — how much of what each DM's own Get Orders
    // converted into (invoice-wise, not just the Napkin portion) came back
    // as a return. Same user_return_stock.invnumber-actually-holds-inv_id
    // convention as the Return % card. One batched query across every DM's
    // Get-Order invoice ids instead of a query per DM.
    $_getOrderInvIdsByMs = getGetOrderInvoiceIdsByMsId($db_conn, array_column($myDms, 'id'), $from, $to);
    $_allGetOrderInvIds = [];
    foreach ($_getOrderInvIdsByMs as $_ids) { $_allGetOrderInvIds = array_merge($_allGetOrderInvIds, $_ids); }
    $_allGetOrderInvIds = array_values(array_unique($_allGetOrderInvIds));
    $_getOrderReturnByInv = [];
    if (!empty($_allGetOrderInvIds)) {
        $_invIdList = "'" . implode("','", array_map(fn($v) => $db_conn->real_escape_string($v), $_allGetOrderInvIds)) . "'";
        $_resGoReturn = $db_conn->query("
            SELECT invnumber, returnid, MAX(total) AS total
            FROM user_return_stock
            WHERE invnumber IN ($_invIdList) AND deleted_at IS NULL
            GROUP BY invnumber, returnid
        ");
        while ($_r = $_resGoReturn->fetch_assoc()) {
            $_getOrderReturnByInv[$_r['invnumber']] = ($_getOrderReturnByInv[$_r['invnumber']] ?? 0) + (float)$_r['total'];
        }
    }
    foreach ($myDms as &$_dmReturnRef) {
        $_dmInvIds = $_getOrderInvIdsByMs[(int)$_dmReturnRef['id']] ?? [];
        $_dmReturnAmt = 0.0;
        foreach ($_dmInvIds as $_invId) { $_dmReturnAmt += $_getOrderReturnByInv[$_invId] ?? 0.0; }
        $_dmReturnRef['get_order_return_amt'] = $_dmReturnAmt;
    }
    unset($_dmReturnRef);

    // Fold each DM's own Get Order Return amount (just computed above) into
    // the zone it belongs to — same district-name matching $zoneAchieved /
    // $zoneTpInvoiced already used when $zoneRollup was first built, done as
    // a second pass here since 'get_order_return_amt' doesn't exist on
    // $myDms until this point.
    if (!empty($zoneRollup)) {
        foreach ($zoneRollup as &$_zoneReturnRef) {
            $_zoneDistrictNamesForReturn = array_map(fn($n) => mb_strtolower(trim($n)), getZoneDistrictNames($db_conn, (int)$_zoneReturnRef['zone_id']));
            $_zoneReturnAmt = 0.0;
            foreach ($myDms as $_dm) {
                foreach (explode(', ', $_dm['district']) as $_dn) {
                    if (in_array(mb_strtolower(trim($_dn)), $_zoneDistrictNamesForReturn, true)) {
                        $_zoneReturnAmt += $_dm['get_order_return_amt'] ?? 0.0;
                        break;
                    }
                }
            }
            $_zoneReturnRef['return_amt'] = $_zoneReturnAmt;
        }
        unset($_zoneReturnRef);
    }
}

// ── New Shop Onboarding (Target: 50 new shops per district) ────────────────
// Two separate onboarding sources, merged: shops this team's DMs added
// themselves (ms_shop.created_at), and shops this team's own TPs added
// directly (shop.created_at — a column this feature added; existing rows
// from before that migration have no created_at and are NOT counted here,
// since there's no way to know when they were actually added). A `shop` row
// with source_ms_shop_id set is that SAME ms_shop lead promoted into a real
// billing record, not a second new shop, so it's excluded to avoid double-
// counting the one DM-onboarded shop twice.
$teamMsIdsForShops = !empty($myDms) ? array_merge([$msId], array_column($myDms, 'id')) : [$msId];
$newShopByDistrict = [];
if (!empty($teamMsIdsForShops)) {
    $_msIdList = implode(',', array_map('intval', $teamMsIdsForShops));
    $_fromEsc = $db_conn->real_escape_string($from);
    $_toEsc = $db_conn->real_escape_string($to);
    $_newShopCounts = [];

    $resNewShopDm = $db_conn->query("
        SELECT COALESCE(dn.name, s.district_name, 'Unassigned') AS district, COUNT(*) AS c
        FROM ms_shop s
        LEFT JOIN partner_location_nodes dn ON dn.id = s.district_node_id
        WHERE s.ms_id IN ($_msIdList) AND DATE(s.created_at) BETWEEN '$_fromEsc' AND '$_toEsc'
        GROUP BY district
    ");
    while ($_nr = $resNewShopDm->fetch_assoc()) {
        $_newShopCounts[$_nr['district']] = ($_newShopCounts[$_nr['district']] ?? 0) + (int)$_nr['c'];
    }

    if (!empty($statsScopeIds)) {
        // Self-migrating — same guard as shop-action.php's own (that one
        // only runs when a TP actually adds a shop, so a fresh deploy with
        // no new TP shop yet would otherwise hit this SELECT before the
        // column exists).
        $_shopCreatedAtCol = $db_conn->query("SHOW COLUMNS FROM shop LIKE 'created_at'");
        if ($_shopCreatedAtCol && $_shopCreatedAtCol->num_rows === 0) {
            $db_conn->query("ALTER TABLE shop ADD COLUMN created_at TIMESTAMP NULL DEFAULT NULL");
        }
        $_scopeIdList = implode(',', array_map('intval', $statsScopeIds));
        $resNewShopTp = $db_conn->query("
            SELECT COALESCE(dn.name, 'Unassigned') AS district, COUNT(*) AS c
            FROM shop s
            LEFT JOIN partner_location_nodes dn ON dn.id = s.district_id
            WHERE s.district_id IN ($_scopeIdList) AND s.source_ms_shop_id IS NULL
              AND s.deleted_at IS NULL AND s.created_at IS NOT NULL
              AND DATE(s.created_at) BETWEEN '$_fromEsc' AND '$_toEsc'
            GROUP BY district
        ");
        while ($_nr = $resNewShopTp->fetch_assoc()) {
            $_newShopCounts[$_nr['district']] = ($_newShopCounts[$_nr['district']] ?? 0) + (int)$_nr['c'];
        }
    }

    ksort($_newShopCounts);
    foreach ($_newShopCounts as $_district => $_count) {
        $newShopByDistrict[] = ['district' => $_district, 'count' => $_count, 'met' => $_count >= 50];
    }
}

// ── Shop Category Split (supermarket, beauty parlour, etc.) ────────────────
// Shops this team added (ms_shop.created_at) within the selected period,
// grouped by shop_category — shop_cat already stores the category id from
// add_ss.php's own "Category" dropdown, no new field needed. Same period
// scoping as New Shop Onboarding above, so both cards agree on "this
// period"'s shops.
$shopCategorySplit = [];
if (!empty($teamMsIdsForShops)) {
    $_msIdList2 = implode(',', array_map('intval', $teamMsIdsForShops));
    $resShopCat = $db_conn->query("
        SELECT COALESCE(sc.catlable, 'Uncategorized') AS category, COUNT(*) AS c
        FROM ms_shop s
        LEFT JOIN shop_category sc ON sc.id = s.shop_cat
        WHERE s.ms_id IN ($_msIdList2) AND DATE(s.created_at) BETWEEN '" . $db_conn->real_escape_string($from) . "' AND '" . $db_conn->real_escape_string($to) . "'
        GROUP BY category
        ORDER BY c DESC
    ");
    while ($_scr = $resShopCat->fetch_assoc()) {
        $shopCategorySplit[] = ['category' => $_scr['category'], 'count' => (int)$_scr['c']];
    }
}

// ── Napkin vs Diaper — Orders & Shops ───────────────────────────────────────
// Every visit (Get Order or No Order) this team logged in the period,
// classified Napkin vs Diaper. A Get Order's category comes from its own
// product line(s) — products.category (Napkin if anything other than
// 'diaper', same convention used throughout this app) — "Mixed" if a
// single order has both. A No Order visit has no product line at all, so
// it uses order_category, the category the DM explicitly picks on the No
// Order form now (added for this requirement) — older rows from before
// that pick existed fall back to "Unspecified".
$napkinDiaperSplit = null;
if (!empty($teamMsIdsForShops)) {
    // Self-migrating — same guard as order_action.php's own (that one only
    // runs when a No Order is actually submitted, so a fresh deploy with no
    // No-Order submissions yet would otherwise hit this SELECT before the
    // column exists).
    $_catCol2 = $db_conn->query("SHOW COLUMNS FROM ms_orders LIKE 'order_category'");
    if ($_catCol2 && $_catCol2->num_rows === 0) {
        $db_conn->query("ALTER TABLE ms_orders ADD COLUMN order_category ENUM('napkin','diaper') NULL DEFAULT NULL AFTER new_order");
    }
    $_msIdList3 = implode(',', array_map('intval', $teamMsIdsForShops));
    $resCat = $db_conn->query("
        SELECT o.order_id, o.shop_id, o.new_order, o.order_category,
               GROUP_CONCAT(DISTINCT CASE WHEN p.category = 'diaper' THEN 'diaper' ELSE 'napkin' END) AS line_cats
        FROM ms_orders o
        LEFT JOIN products p ON p.id = o.pr_id
        WHERE o.ms_id IN ($_msIdList3) AND o.order_date BETWEEN '" . $db_conn->real_escape_string($from) . "' AND '" . $db_conn->real_escape_string($to) . "'
        GROUP BY o.order_id, o.shop_id, o.new_order, o.order_category
    ");
    $_napkinOrderIds = []; $_diaperOrderIds = []; $_mixedOrderIds = []; $_unspecOrderIds = [];
    $_napkinShopIds  = []; $_diaperShopIds  = [];
    while ($r = $resCat->fetch_assoc()) {
        if ($r['new_order'] === 'yes') {
            $_cats = $r['line_cats'] ? explode(',', $r['line_cats']) : [];
            if (count($_cats) === 1 && $_cats[0] === 'diaper')      { $_cat = 'diaper'; }
            elseif (count($_cats) === 1 && $_cats[0] === 'napkin')  { $_cat = 'napkin'; }
            elseif (count($_cats) > 1)                              { $_cat = 'mixed'; }
            else                                                     { $_cat = 'unspecified'; }
        } else {
            $_cat = $r['order_category'] ?: 'unspecified';
        }
        if ($_cat === 'napkin') { $_napkinOrderIds[$r['order_id']] = true; $_napkinShopIds[$r['shop_id']] = true; }
        elseif ($_cat === 'diaper') { $_diaperOrderIds[$r['order_id']] = true; $_diaperShopIds[$r['shop_id']] = true; }
        elseif ($_cat === 'mixed') { $_mixedOrderIds[$r['order_id']] = true; $_napkinShopIds[$r['shop_id']] = true; $_diaperShopIds[$r['shop_id']] = true; }
        else { $_unspecOrderIds[$r['order_id']] = true; }
    }
    $napkinDiaperSplit = [
        'napkin_orders'      => count($_napkinOrderIds),
        'napkin_shops'       => count($_napkinShopIds),
        'diaper_orders'      => count($_diaperOrderIds),
        'diaper_shops'       => count($_diaperShopIds),
        'mixed_orders'       => count($_mixedOrderIds),
        'unspecified_orders' => count($_unspecOrderIds),
    ];
}

$vacantFirkas   = getMsNodeVacantFirkas($db_conn, $statsScopeIds, $districtLabel);
$allTpIds       = getMsNodeTpIds($db_conn, $statsScopeIds, true);

$invoicedAmount = 0.0;
$invoiceRows    = [];
if (!empty($allTpIds)) {
    $idList = implode(',', $allTpIds);

    $stmtInv = $db_conn->prepare(
        "SELECT COALESCE(SUM(total),0) AS amt FROM user_invoice
         WHERE from_user_type='territory_partner' AND from_user_id IN ($idList)
           AND `date` BETWEEN ? AND ?"
    );
    $stmtInv->bind_param('ss', $from, $to);
    $stmtInv->execute();
    $invoicedAmount = (float)($stmtInv->get_result()->fetch_assoc()['amt'] ?? 0);
    $stmtInv->close();

    // Per-TP target = sum of target_amount of every Firka that TP is assigned
    // to — computed as its own GROUP BY query (one row per TP) instead of a
    // correlated subquery inside the main SELECT below: a correlated
    // subquery re-runs once per output ROW (every invoice, not every TP),
    // which turned this into a full scan of territory_partner_locations for
    // each of several hundred invoices and took 5-7s; the grouped query
    // below runs once regardless of how many invoices there are.
    $tpTargetMap = [];
    $resTpTarget = $db_conn->query(
        "SELECT tpl.territory_partner_id AS tp_id, SUM(pln.target_amount) AS tgt
         FROM territory_partner_locations tpl
         JOIN partner_location_nodes pln ON pln.id = tpl.location_id
         WHERE tpl.territory_partner_id IN ($idList)
         GROUP BY tpl.territory_partner_id"
    );
    while ($_tr = $resTpTarget->fetch_assoc()) { $tpTargetMap[(int)$_tr['tp_id']] = (float)$_tr['tgt']; }

    $stmtRows = $db_conn->prepare(
        "SELECT ui.inv_id, ui.inv_number, ui.date, ui.total, ui.to_user_id,
                tp.id AS tp_pk, tp.name AS tp_name, tp.tp_id AS tp_code, tp.mobile AS tp_mobile,
                COALESCE(NULLIF(tp.assigned_district,''), tp.branch_district) AS tp_district,
                s.name AS shop_name
         FROM user_invoice ui
         JOIN territory_partners tp ON tp.id = ui.from_user_id
         LEFT JOIN shop s ON s.temp_id = ui.to_user_id
         WHERE ui.from_user_type='territory_partner' AND ui.from_user_id IN ($idList)
           AND ui.`date` BETWEEN ? AND ?
         ORDER BY ui.date DESC, ui.inv_number DESC"
    );
    $stmtRows->bind_param('ss', $from, $to);
    $stmtRows->execute();
    $invoiceRows = $stmtRows->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtRows->close();
    foreach ($invoiceRows as &$_invRow) {
        $_invRow['tp_target'] = $tpTargetMap[(int)$_invRow['tp_pk']] ?? 0.0;
    }
    unset($_invRow);
}

// Get Order Amount (Napkin-only, Get-Order-converted sales) for this page's
// own scope — same "Achieved" definition as $myDms rows / TeamTargetRollup.
// For an ASM/SM, summed straight from the team's own DM achieved figures
// already computed above; for an actual DM (no one below them) computed
// fresh for their own ms_id.
if (!empty($myDms)) {
    $ownGetOrderAchieved = array_sum(array_column($myDms, 'achieved'));
} else {
    $_ownAchievedStats = getRawTargetAchievedStats($db_conn, [$msId], $from, $to);
    $ownGetOrderAchieved = (float)($_ownAchievedStats[$msId]['achieved'] ?? 0);
}

// Per-district Get Order Amount — same breakdown as $districtTargetTotals
// below, so the top cards' district filter can add TP Invoice Amount + Get
// Order Amount consistently whichever district is picked.
$districtAchievedTotals = [];
if (!empty($myDms)) {
    foreach ($myDms as $_dm) {
        foreach (explode(', ', $_dm['district']) as $_dn) {
            $_dn = trim($_dn);
            if ($_dn === '' || $_dn === 'No District Assigned') { continue; }
            $districtAchievedTotals[$_dn] = ($districtAchievedTotals[$_dn] ?? 0) + $_dm['achieved'];
        }
    }
} else {
    foreach ($districtNames as $_dn) {
        $districtAchievedTotals[$_dn] = $ownGetOrderAchieved;
    }
}

// "Your TPs' Invoice Amount" card now shows TP Invoice Amount + Get Order
// Amount combined (the Total the DM/ASM/SM actually achieved), with
// Balance computed against that combined total instead of invoice-only.
$totalAchieveAmount = $invoicedAmount + $ownGetOrderAchieved;
$balanceTarget = max($districtStats['district_total_target'] - $totalAchieveAmount, 0);

// TP roster for the tabs — All / Active / Inactive. Scoped to this person's
// OWN assigned district(s) for a DM, but for an ASM/SM (who may have no
// district of their own, or a narrower direct assignment than their actual
// team) widened to every district any of their District Managers covers —
// same union $myDms above was already built from — so the roster, and the
// "Team Districts" breakdown below, reflect the whole team, not just
// whatever happens to be assigned straight to the ASM/SM themselves.
$teamDistrictNames = $districtNames;
foreach ($myDms as $_dm) {
    foreach (explode(', ', $_dm['district']) as $_dn) {
        $_dn = trim($_dn);
        if ($_dn !== '' && $_dn !== 'No District Assigned') { $teamDistrictNames[] = $_dn; }
    }
}
$teamDistrictNames = array_values(array_unique($teamDistrictNames));
sort($teamDistrictNames);

$teamTpIds = !empty($teamDistrictNames) ? getMsDistrictTpIds($db_conn, $teamDistrictNames, true) : $allTpIds;

$tpRoster = [];
if (!empty($teamTpIds)) {
    $idList = implode(',', $teamTpIds);
    $resTp = $db_conn->query(
        "SELECT id, tp_id AS tp_code, name, mobile, is_active,
                COALESCE(NULLIF(assigned_district,''), branch_district) AS district
         FROM territory_partners
         WHERE id IN ($idList) ORDER BY is_active DESC, name ASC"
    );
    $tpRoster = $resTp ? $resTp->fetch_all(MYSQLI_ASSOC) : [];

    // Target/Firka columns are scoped to just this team's own district(s) —
    // a cross-district TP (e.g. one holding Firkas in both Coimbatore and
    // Tiruppur) must only contribute the Tiruppur portion to a Tiruppur DM's
    // roster, matching the district's own "Filled Firkas" total; the rest is
    // surfaced as a split note naming the other district and its DM, instead
    // of silently inflating this DM's numbers with another DM's target.
    $tpSplit = getTpFirkaDistrictSplit($db_conn, $teamTpIds);
    $teamDistrictNamesLower = array_map(fn($n) => mb_strtolower(trim($n)), $teamDistrictNames);

    $otherDistrictsSeen = [];
    foreach ($tpSplit as $_byDistrict) {
        foreach ($_byDistrict as $_dn => $_info) {
            if (!in_array(mb_strtolower(trim($_dn)), $teamDistrictNamesLower, true)) {
                $otherDistrictsSeen[$_dn] = true;
            }
        }
    }
    $otherDistrictDms = getDistrictManagerNames($db_conn, array_keys($otherDistrictsSeen));

    foreach ($tpRoster as &$_tpRow) {
        $_tpId       = (int)$_tpRow['id'];
        $_byDistrict = $tpSplit[$_tpId] ?? [];
        $_inTarget   = 0.0;
        $_inFirkas   = [];
        $_otherParts = [];
        foreach ($_byDistrict as $_dn => $_info) {
            if (in_array(mb_strtolower(trim($_dn)), $teamDistrictNamesLower, true)) {
                $_inTarget = $_inTarget + $_info['target'];
                $_inFirkas = array_merge($_inFirkas, $_info['firkas']);
            } else {
                $_dmNames = !empty($otherDistrictDms[$_dn]) ? implode('/', $otherDistrictDms[$_dn]) : 'unassigned DM';
                $_otherParts[] = $_dn . ': ' . inr_format($_info['target'], 0) . ' (under ' . $_dmNames . ')';
            }
        }
        sort($_inFirkas);
        $_tpRow['firka']  = implode(', ', $_inFirkas);
        $_tpRow['target'] = $_inTarget;
        $_tpRow['other_district_split'] = implode('; ', $_otherParts);
    }
    unset($_tpRow);
}
$activeTpRoster   = array_values(array_filter($tpRoster, fn($r) => $r['is_active']));
$inactiveTpRoster = array_values(array_filter($tpRoster, fn($r) => !$r['is_active']));

// ── Return % — per invoice, how much of its amount came back as a return,
// with invoice date, TP name, District + Firka (SM-wise/DM-wise requirement
// #11). user_return_stock.invnumber actually holds user_invoice.inv_id (not
// inv_number, despite the column name — confirmed against live data, same
// convention company/asm-dm-order-conversion-export.php and mis-report.php
// already rely on). Repeats the whole return's total on every line item, so
// dedupe by returnid before summing.
$tpFirkaById = array_column($tpRoster, 'firka', 'id');
$returnByInv = [];
if (!empty($invoiceRows)) {
    $_invIdsForReturn = array_column($invoiceRows, 'inv_id');
    $_idList3 = "'" . implode("','", array_map(fn($v) => $db_conn->real_escape_string($v), $_invIdsForReturn)) . "'";
    $resReturn = $db_conn->query("
        SELECT invnumber, returnid, MAX(total) AS total
        FROM user_return_stock
        WHERE invnumber IN ($_idList3) AND deleted_at IS NULL
        GROUP BY invnumber, returnid
    ");
    while ($rr = $resReturn->fetch_assoc()) {
        $returnByInv[$rr['invnumber']] = ($returnByInv[$rr['invnumber']] ?? 0) + (float)$rr['total'];
    }
}

$returnPctRows = [];
$returnPctTotalInv = 0.0;
$returnPctTotalReturned = 0.0;
foreach ($invoiceRows as $_ir) {
    $returnPctTotalInv += (float)$_ir['total'];
    $_returned = $returnByInv[$_ir['inv_id']] ?? 0.0;
    if ($_returned <= 0) { continue; }
    $returnPctTotalReturned += $_returned;
    $_pct = $_ir['total'] > 0 ? ($_returned / $_ir['total']) * 100 : 0.0;
    $returnPctRows[] = [
        'date'          => $_ir['date'],
        'inv_number'    => $_ir['inv_number'],
        'tp_name'       => $_ir['tp_name'],
        'district'      => $_ir['tp_district'],
        'firka'         => $tpFirkaById[$_ir['tp_pk']] ?? '',
        'invoice_total' => (float)$_ir['total'],
        'returned'      => $_returned,
        'pct'           => $_pct,
    ];
}
$returnPctOverall = $returnPctTotalInv > 0 ? ($returnPctTotalReturned / $returnPctTotalInv) * 100 : 0.0;

// ── TP Return (per DM) — return amount against EVERY shop invoice this DM's
// own TPs raised (the broader "TP Invoice Amount" column), not just the
// narrower "Get Order Return" above (invoices that specifically came from
// this DM's own Get Orders). Built from $invoiceRows/$returnByInv already
// fetched for the Return % card above — no extra query needed, just
// attributed per DM via $dmTpIdsById (tp_pk -> which DM owns that TP).
if (!empty($myDms)) {
    $_tpIdToDmId = [];
    foreach ($dmTpIdsById as $_dmIdForTp => $_tpIdsForDm) {
        foreach ($_tpIdsForDm as $_tpId) { $_tpIdToDmId[(int)$_tpId] = (int)$_dmIdForTp; }
    }
    $_tpReturnByDm = [];
    foreach ($invoiceRows as $_ir) {
        $_dmIdForInv = $_tpIdToDmId[(int)$_ir['tp_pk']] ?? null;
        if ($_dmIdForInv === null) { continue; }
        $_returnedForInv = $returnByInv[$_ir['inv_id']] ?? 0.0;
        if ($_returnedForInv <= 0) { continue; }
        $_tpReturnByDm[$_dmIdForInv] = ($_tpReturnByDm[$_dmIdForInv] ?? 0) + $_returnedForInv;
    }
    foreach ($myDms as &$_dmTpReturnRef) {
        $_dmTpReturnRef['tp_return_amt'] = $_tpReturnByDm[(int)$_dmTpReturnRef['id']] ?? 0.0;
    }
    unset($_dmTpReturnRef);
}

// Per-district target total (sum of each TP's own target, once per TP) —
// feeds the top stat cards when the Shop Invoices district filter narrows
// to a single district, so Target/Invoiced/Balance all agree with the table.
$districtTargetTotals = [];
foreach ($tpRoster as $_tp) {
    $_d = $_tp['district'] ?: 'Unassigned';
    $districtTargetTotals[$_d] = ($districtTargetTotals[$_d] ?? 0) + (float)$_tp['target'];
}

// District-wise Active/Inactive TP counts, for the clickable "Team Districts"
// chips — only shown when there's more than one district to break down
// (a single-district DM already sees everything in the roster below).
$districtTpCounts = [];
foreach ($tpRoster as $_r) {
    $_dn = $_r['district'] ?: 'Unassigned';
    if (!isset($districtTpCounts[$_dn])) { $districtTpCounts[$_dn] = ['active' => 0, 'inactive' => 0]; }
    $districtTpCounts[$_dn][$_r['is_active'] ? 'active' : 'inactive']++;
}
ksort($districtTpCounts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Responsive Admin Dashboard Template">
    <meta name="keywords" content="admin,dashboard">
    <meta name="author" content="stacks">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    
    <!-- Title -->
    <title>Dashboard : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">

    
    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style>
        body { font-family: 'Poppins', sans-serif; }

        .stat-card {
            background: #fff; border-radius: 14px; padding: 22px 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06); border-left: 5px solid;
            margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between;
            transition: transform .15s, box-shadow .15s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.10); }
        .stat-card.purple { border-color: #667eea; }
        .stat-card.green  { border-color: #10b981; }
        .stat-card.blue   { border-color: #3b82f6; }
        .stat-card.orange { border-color: #f59e0b; }
        .stat-card.red    { border-color: #ef4444; }
        .stat-card.red h3 { color: #ef4444; }
        .stat-card h3 { font-size: 24px; font-weight: 700; margin: 0 0 3px 0; color: #1f2937; line-height: 1; }
        .stat-card p  { margin: 0; font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: .7px; }
        .stat-icon-wrap { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .stat-icon-wrap i { font-size: 26px; }
        .stat-card.purple .stat-icon-wrap { background: #ede9fe; color: #667eea; }
        .stat-card.green  .stat-icon-wrap { background: #d1fae5; color: #10b981; }
        .stat-card.blue   .stat-icon-wrap { background: #dbeafe; color: #3b82f6; }
        .stat-card.orange .stat-icon-wrap { background: #fef3c7; color: #f59e0b; }
        .stat-card.red    .stat-icon-wrap { background: #fee2e2; color: #ef4444; }

        .firka-tile { background: #fff; border-radius: 14px; padding: 16px 18px; box-shadow: 0 2px 12px rgba(0,0,0,.06); margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; }
        .firka-tile .ft-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .4px; }
        .firka-tile .ft-count { font-size: 22px; font-weight: 700; color: #1f2937; }
        .firka-tile .ft-value { font-size: 13px; font-weight: 600; color: #64748b; }
        .firka-tile-clickable { cursor: pointer; border: 1.5px solid transparent; transition: border-color .15s, background .15s; }
        .firka-tile-clickable:hover, .firka-tile-clickable:focus { border-color: #fecaca; background: #fef7f7; outline: none; }
        .ft-tap-hint { font-size: 10.5px; color: #ef4444; font-weight: 600; margin-top: 2px; }

        .main-card { background: #fff; border-radius: 16px; box-shadow: 0 2px 16px rgba(0,0,0,.07); border: none; overflow: hidden; margin-bottom: 24px; }
        .main-card-header { padding: 20px 28px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; background: #fff; }
        .main-card-header-left { display: flex; align-items: center; gap: 12px; }
        .header-icon-box { width: 42px; height: 42px; border-radius: 11px; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; }
        .header-icon-box i { font-size: 20px; color: #fff; }
        .header-title { font-size: 15px; font-weight: 700; color: #1f2937; margin: 0; line-height: 1.2; }
        .header-sub   { font-size: 12px; color: #9ca3af; margin: 0; }
        .tp-count-pill { background: #ede9fe; color: #6d28d9; font-size: 12px; font-weight: 700; padding: 5px 14px; border-radius: 20px; }

        .tp-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .tp-table thead tr { background: #f8fafc; }
        .tp-table thead th { padding: 14px 20px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #94a3b8; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
        .tp-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .12s; }
        .tp-table tbody tr:last-child { border-bottom: none; }
        .tp-table tbody tr:hover { background: #fafbff; }
        .tp-table tbody td { padding: 14px 20px; vertical-align: middle; }
        .tp-table tfoot td { padding: 14px 20px; vertical-align: middle; }

        .tp-avatar { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #fff; flex-shrink: 0; text-transform: uppercase; }
        .tp-info { display: flex; align-items: center; gap: 12px; }
        .tp-info-text { display: flex; flex-direction: column; gap: 3px; }
        .tp-name { font-size: 13.5px; font-weight: 600; color: #1e293b; line-height: 1.2; }
        .tp-code-pill { display: inline-block; font-size: 10.5px; font-weight: 700; color: #7c3aed; background: #ede9fe; padding: 2px 8px; border-radius: 5px; letter-spacing: .3px; }

        .mobile-cell { display: flex; align-items: center; gap: 7px; }
        .mobile-cell i { font-size: 15px; color: #cbd5e1; }
        .mobile-text { font-size: 13px; color: #475569; font-weight: 500; }

        .sales-amount { font-size: 13.5px; font-weight: 700; color: #1e293b; }
        .sales-currency { font-size: 11px; color: #94a3b8; font-weight: 600; margin-right: 1px; }

        .status-dot { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 5px 12px; border-radius: 20px; }
        .status-dot::before { content: ''; width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .status-active   { background: #ecfdf5; color: #059669; }
        .status-active::before   { background: #10b981; }
        .status-inactive { background: #fef2f2; color: #dc2626; }
        .status-inactive::before { background: #ef4444; }

        .serial-num { font-size: 12px; color: #cbd5e1; font-weight: 600; }

        .empty-state { text-align: center; padding: 56px 20px; color: #9ca3af; }
        .empty-icon-wrap { width: 64px; height: 64px; border-radius: 18px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; }
        .empty-icon-wrap i { font-size: 32px; color: #cbd5e1; }
        .empty-state h6 { font-size: 14px; font-weight: 600; color: #64748b; margin-bottom: 6px; }
        .empty-state p  { font-size: 13px; color: #9ca3af; margin: 0; }

        #tpSearch, .dash-search {
            border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 7px 14px 7px 36px; font-size: 13px; color: #374151;
            outline: none; width: 220px;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%239ca3af' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zm-5.242 1.656a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z'/%3E%3C/svg%3E") no-repeat 12px center;
            transition: border-color .15s;
        }
        #tpSearch:focus, .dash-search:focus { border-color: #667eea; background-color: #fff; }

        .date-filter-form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .date-filter-form input[type=date] { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 6px 10px; font-size: 13px; }
        .nav-tabs .nav-link.active { font-weight: 700; color: #6d28d9; border-color: #e2e8f0 #e2e8f0 #fff; }
        .inv-number-link { color: #2563eb; font-weight: 700; text-decoration: none; }
        .inv-number-link:hover { text-decoration: underline; }

        .status-filter-tabs { display: flex; gap: 4px; padding: 0 20px; border-bottom: 1px solid #f1f5f9; }
        .status-filter-btn { border: none; background: none; padding: 14px 10px; font-size: 13.5px; font-weight: 600; color: #6b7280; cursor: pointer; border-bottom: 2px solid transparent; }
        .status-filter-btn.active { color: #6d28d9; border-bottom-color: #6d28d9; font-weight: 700; }

        .mis-pagination { display:flex; align-items:center; justify-content:flex-end; gap:6px; padding:14px 20px; flex-wrap:wrap; }
        .mis-pagination button { padding:4px 11px; border-radius:6px; border:1px solid #e5e7eb; background:#fff; color:#374151; font-size:12.5px; font-weight:600; cursor:pointer; }
        .mis-pagination button.active { background:#667eea; border-color:#667eea; color:#fff; }
        .mis-pagination button:disabled { opacity:.4; cursor:default; }
        .mis-pagination .mis-pg-info { font-size:12px; color:#6b7280; margin-right:6px; }

        /* ── Mobile responsiveness ───────────────────────── */
        @media (max-width: 767px) {
            .stat-card { padding: 16px 18px; margin-bottom: 14px; }
            .stat-card h3 { font-size: 19px; }
            .stat-icon-wrap { width: 42px; height: 42px; border-radius: 11px; }
            .stat-icon-wrap i { font-size: 20px; }

            .main-card-header { padding: 14px 16px; }
            .header-title { font-size: 14px; }
            .header-icon-box { width: 36px; height: 36px; }

            .date-filter-form { width: 100%; }
            .date-filter-form input[type=date] { flex: 1 1 auto; min-width: 0; }
            .date-filter-form .btn { flex: 0 0 auto; }

            .dash-search, #tpSearch { width: 100%; max-width: none; }
            .main-card-header-left { width: 100%; }
            .main-card-header > div:not(.main-card-header-left) { width: 100%; }

            #districtDashboardLabel { font-size: 12px; word-break: break-word; }
            .district-chip, .dm-name-chip { font-size: 12px; padding: 6px 10px; }

            .status-filter-tabs { padding: 0 10px; overflow-x: auto; white-space: nowrap; flex-wrap: nowrap; }
            .status-filter-btn { padding: 12px 8px; font-size: 12.5px; flex-shrink: 0; }

            .tp-table thead th, .tp-table tbody td { padding: 10px 12px; font-size: 12px; }
            .tp-name { font-size: 12.5px; }
            .tp-avatar { width: 32px; height: 32px; font-size: 11px; }
            .mobile-text, .sales-amount { font-size: 12px; }

            .firka-tile { padding: 13px 14px; }
            .firka-tile .ft-count { font-size: 18px; }

            .mis-pagination { justify-content: center; padding: 12px 10px; }
            .mis-pagination .mis-pg-info { width: 100%; text-align: center; margin: 0 0 6px; }

            .modal-dialog { margin: 10px; }
        }
    </style>
    <?php if ($embed): ?>
    <style>
        .app-sidebar { display: none !important; }
        .app-container { margin-left: 0 !important; width: 100% !important; }
        .app-content { margin-top: 0 !important; }
    </style>
    <?php endif; ?>
</head>
<body<?php echo $embed ? ' class="ms-embed"' : ''; ?>>
    <?php if (!empty($todayAssignedAreas)): ?>
    <div class="modal fade" id="newAreaAssignedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background:#eef2ff;">
                    <h5 class="modal-title" style="color:#3730a3;">New Area Assigned</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p style="margin:0 0 10px;font-size:13.5px;color:#374151;">You have been assigned to the following area<?php echo count($todayAssignedAreas) > 1 ? 's' : ''; ?> today:</p>
                    <ul style="margin:0;padding-left:20px;">
                        <?php foreach ($todayAssignedAreas as $_area): ?>
                        <li style="font-size:14px;font-weight:600;color:#1f2937;"><?php echo htmlspecialchars($_area); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var _naModalEl = document.getElementById('newAreaAssignedModal');
            if (_naModalEl && window.bootstrap) {
                new bootstrap.Modal(_naModalEl).show();
            }
        });
    </script>
    <?php endif; ?>
    <div class="app align-content-stretch d-flex flex-wrap">
        <?php if (!$embed): ?>
        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>
        <?php endif; ?>
        <div class="app-container">

           <?php if (!$embed) include("app-header.php");?>

            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">

                        <?php if (!empty($_companyBridgeView) && !$embed): ?>
                        <div class="alert alert-info" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
                            <span><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">visibility</i> Viewing <b><?php echo htmlspecialchars($result_LoGuserDtails['ms_name'] ?? ''); ?>'s</b> dashboard (read-only).</span>
                        </div>
                        <?php endif; ?>

                        <div class="row mb-2">
                            <div class="col">
                                <h4 style="font-weight:700;color:#1f2937;margin-bottom:4px;">
                                    <i class="material-icons-two-tone" style="vertical-align:middle;font-size:22px;">dashboard</i>
                                    <?php echo htmlspecialchars($scopeWord); ?> Dashboard
                                </h4>
                                <p id="districtDashboardLabel" style="color:#6b7280;font-size:13px;margin:0;">
                                    <?php echo htmlspecialchars($districtLabel); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Top Stat Cards -->
                        <div class="row">
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="stat-card purple">
                                    <div>
                                        <h3 id="statDistrictTarget">&#8377;<?php echo inr_format($districtStats['district_total_target'], 0); ?></h3>
                                        <p><?php echo htmlspecialchars($scopeWord); ?> Total Target</p>
                                    </div>
                                    <div class="stat-icon-wrap"><i class="material-icons-outlined">flag</i></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="stat-card green">
                                    <div>
                                        <h3 id="statInvoiceAmount">&#8377;<?php echo inr_format($totalAchieveAmount, 0); ?></h3>
                                        <p title="TP Invoice Amount + Get Order Amount">Your TPs' Invoice + Get Order Amount</p>
                                    </div>
                                    <div class="stat-icon-wrap"><i class="material-icons-outlined">receipt_long</i></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="stat-card red">
                                    <div>
                                        <h3 id="statBalanceTarget">&#8377;<?php echo inr_format($balanceTarget, 0); ?></h3>
                                        <p>Balance Target Amount</p>
                                    </div>
                                    <div class="stat-icon-wrap"><i class="material-icons-outlined">trending_up</i></div>
                                </div>
                            </div>
                            <?php if (empty($myDms)): ?>
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="stat-card purple">
                                    <div>
                                        <h3><?php echo !empty($myOwnTargetCompletedDate) ? date('d M Y', strtotime($myOwnTargetCompletedDate)) : 'Not yet'; ?></h3>
                                        <p title="Date your cumulative Napkin-only (no Diaper) sales first reached your Target Amount">Target Completed Date</p>
                                    </div>
                                    <div class="stat-icon-wrap"><i class="material-icons-outlined">event_available</i></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($myOwnVisitDays)): ?>
                        <?php
                        $_vdTotalDays = count($myOwnVisitDays);
                        $_vdVisitMetDays = count(array_filter($myOwnVisitDays, fn($d) => $d['visit_target_met']));
                        $_vdGetOrderMetDays = count(array_filter($myOwnVisitDays, fn($d) => $d['get_order_target_met']));
                        $_vdAvgVisits = $_vdTotalDays > 0 ? array_sum(array_column($myOwnVisitDays, 'total_visits')) / $_vdTotalDays : 0.0;
                        $_vdAvgGetOrder = $_vdTotalDays > 0 ? array_sum(array_column($myOwnVisitDays, 'get_order_visits')) / $_vdTotalDays : 0.0;
                        ?>
                        <!-- Daily Visit Compliance (35 shop / 20 Get Order) — solo DM only -->
                        <div class="row">
                            <div class="col-12">
                                <div class="main-card">
                                    <div class="main-card-header">
                                        <div class="main-card-header-left">
                                            <div class="header-icon-box"><i class="material-icons-outlined">fact_check</i></div>
                                            <div>
                                                <p class="header-title">Daily Visit Compliance</p>
                                                <p class="header-sub">Target: 35 shop visits/day, 20 of them Get Order</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="padding:14px 20px;">
                                        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
                                            <span style="background:<?php echo $_vdVisitMetDays >= $_vdTotalDays && $_vdTotalDays > 0 ? '#d1fae5' : '#fee2e2'; ?>;color:<?php echo $_vdVisitMetDays >= $_vdTotalDays && $_vdTotalDays > 0 ? '#065f46' : '#991b1b'; ?>;padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;">35-Visit Days Met: <?php echo $_vdVisitMetDays; ?>/<?php echo $_vdTotalDays; ?> (avg <?php echo number_format($_vdAvgVisits, 1); ?>/day)</span>
                                            <span style="background:<?php echo $_vdGetOrderMetDays >= $_vdTotalDays && $_vdTotalDays > 0 ? '#d1fae5' : '#fee2e2'; ?>;color:<?php echo $_vdGetOrderMetDays >= $_vdTotalDays && $_vdTotalDays > 0 ? '#065f46' : '#991b1b'; ?>;padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;">20-Get Order Days Met: <?php echo $_vdGetOrderMetDays; ?>/<?php echo $_vdTotalDays; ?> (avg <?php echo number_format($_vdAvgGetOrder, 1); ?>/day)</span>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="var t=document.getElementById('visitComplianceDetail'); var open=t.style.display!=='none'; t.style.display=open?'none':'block'; this.textContent=open?'View Day-wise Details':'Hide Day-wise Details';">View Day-wise Details</button>
                                        </div>
                                        <div id="visitComplianceDetail" style="display:none;">
                                        <div style="overflow-x:auto;">
                                        <table class="table table-sm table-hover" style="min-width:640px;">
                                            <thead>
                                                <tr style="color:#6b7280;font-size:12px;text-transform:uppercase;">
                                                    <th>Date</th>
                                                    <th class="text-end">Total Visits</th>
                                                    <th class="text-end">New</th>
                                                    <th class="text-end">Existing</th>
                                                    <th class="text-end">Get Order Visits</th>
                                                    <th class="text-end">Napkin Shops</th>
                                                    <th class="text-end">Diaper Shops</th>
                                                    <th class="text-end">Diaper Value</th>
                                                    <th class="text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="visitComplianceTbody">
                                                <?php foreach ($myOwnVisitDays as $_vd): ?>
                                                <tr>
                                                    <td><?php echo date('d M Y', strtotime($_vd['date'])); ?></td>
                                                    <td class="text-end"><?php echo (int)$_vd['total_visits']; ?> / 35</td>
                                                    <td class="text-end"><?php echo (int)$_vd['new_visits']; ?></td>
                                                    <td class="text-end"><?php echo (int)$_vd['existing_visits']; ?></td>
                                                    <td class="text-end"><?php echo (int)$_vd['get_order_visits']; ?> / 20</td>
                                                    <td class="text-end"><?php echo (int)$_vd['napkin_shops']; ?></td>
                                                    <td class="text-end"><?php echo (int)$_vd['diaper_shops']; ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_vd['diaper_value'], 0); ?></td>
                                                    <td class="text-center">
                                                        <?php if ($_vd['visit_target_met'] && $_vd['get_order_target_met']): ?>
                                                        <span style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Met</span>
                                                        <?php else: ?>
                                                        <span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Not Met</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <?php
                                                $_vdTot = ['total_visits' => 0, 'new_visits' => 0, 'existing_visits' => 0, 'get_order_visits' => 0, 'napkin_shops' => 0, 'diaper_shops' => 0, 'diaper_value' => 0.0];
                                                foreach ($myOwnVisitDays as $_vd) {
                                                    $_vdTot['total_visits']     += $_vd['total_visits'];
                                                    $_vdTot['new_visits']       += $_vd['new_visits'];
                                                    $_vdTot['existing_visits']  += $_vd['existing_visits'];
                                                    $_vdTot['get_order_visits'] += $_vd['get_order_visits'];
                                                    $_vdTot['napkin_shops']     += $_vd['napkin_shops'];
                                                    $_vdTot['diaper_shops']     += $_vd['diaper_shops'];
                                                    $_vdTot['diaper_value']     += $_vd['diaper_value'];
                                                }
                                                ?>
                                                <tr style="font-weight:600;border-top:2px solid #e5e7eb;">
                                                    <td>Total</td>
                                                    <td class="text-end"><?php echo (int)$_vdTot['total_visits']; ?></td>
                                                    <td class="text-end"><?php echo (int)$_vdTot['new_visits']; ?></td>
                                                    <td class="text-end"><?php echo (int)$_vdTot['existing_visits']; ?></td>
                                                    <td class="text-end"><?php echo (int)$_vdTot['get_order_visits']; ?></td>
                                                    <td class="text-end"><?php echo (int)$_vdTot['napkin_shops']; ?></td>
                                                    <td class="text-end"><?php echo (int)$_vdTot['diaper_shops']; ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_vdTot['diaper_value'], 0); ?></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                        </div>
                                        <div id="visitCompliancePager" class="mis-pagination" style="margin-top:10px;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                        if (typeof femi9PaginateVisitTable !== 'function') {
                            // 7-rows-per-page client-side pagination for a Daily Visit Compliance
                            // table (31 day rows otherwise all render at once) — rows are already
                            // in the page's HTML (no AJAX), this just hides all but the current
                            // page's <tr>s and draws Prev/page-number/Next controls. Defined here
                            // too (guarded) since a solo DM never reaches the "My District
                            // Managers" block below where this same function is otherwise defined.
                            function femi9PaginateVisitTable(tbodyId, pagerId, pageSize) {
                                var tbody = document.getElementById(tbodyId);
                                var pager = document.getElementById(pagerId);
                                if (!tbody || !pager) return;
                                var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                                if (!rows.length) return;
                                var totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
                                var currentPage = 1;

                                function render() {
                                    var start = (currentPage - 1) * pageSize;
                                    rows.forEach(function (row, i) {
                                        row.style.display = (i >= start && i < start + pageSize) ? '' : 'none';
                                    });
                                    var html = '<span class="mis-pg-info">' + (start + 1) + '–' + Math.min(start + pageSize, rows.length) + ' of ' + rows.length + '</span>';
                                    html += '<button type="button" data-pg="prev"' + (currentPage === 1 ? ' disabled' : '') + '>Prev</button>';
                                    var winSize = 5;
                                    var winStart = Math.max(1, currentPage - Math.floor(winSize / 2));
                                    var winEnd = Math.min(totalPages, winStart + winSize - 1);
                                    winStart = Math.max(1, winEnd - winSize + 1);
                                    for (var p = winStart; p <= winEnd; p++) {
                                        html += '<button type="button" data-pg="' + p + '" class="' + (p === currentPage ? 'active' : '') + '">' + p + '</button>';
                                    }
                                    html += '<button type="button" data-pg="next"' + (currentPage === totalPages ? ' disabled' : '') + '>Next</button>';
                                    pager.innerHTML = html;
                                }
                                pager.addEventListener('click', function (e) {
                                    var btn = e.target.closest('button[data-pg]');
                                    if (!btn) return;
                                    var pg = btn.getAttribute('data-pg');
                                    if (pg === 'prev') currentPage = Math.max(1, currentPage - 1);
                                    else if (pg === 'next') currentPage = Math.min(totalPages, currentPage + 1);
                                    else currentPage = parseInt(pg, 10);
                                    render();
                                });
                                render();
                            }
                        }
                        femi9PaginateVisitTable('visitComplianceTbody', 'visitCompliancePager', 7);
                        </script>
                        <?php endif; ?>

                        <?php if (!empty($newShopByDistrict)): ?>
                        <!-- New Shop Onboarding (Target: 50/district) -->
                        <div class="row">
                            <div class="col-12">
                                <div class="main-card">
                                    <div class="main-card-header">
                                        <div class="main-card-header-left">
                                            <div class="header-icon-box"><i class="material-icons-outlined">storefront</i></div>
                                            <div>
                                                <p class="header-title">New Shop Onboarding</p>
                                                <p class="header-sub">Target: 50 new shops per district, this period</p>
                                            </div>
                                        </div>
                                        <?php if (!empty($shopCategorySplit)): ?>
                                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#shopCategorySplitModal">Shop Category Split</button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#shopCategoryWiseModal">Shop Category Wise</button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="padding:14px 20px;">
                                        <div style="display:inline-flex;align-items:center;gap:10px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;padding:10px 18px;margin-bottom:14px;">
                                            <span style="font-size:22px;font-weight:700;color:#3730a3;"><?php echo (int)array_sum(array_column($newShopByDistrict, 'count')); ?></span>
                                            <span style="font-size:12.5px;color:#4338ca;font-weight:600;">Total New Shops Onboarded<br>(all districts, this period)</span>
                                        </div>
                                        <div style="overflow-x:auto;">
                                        <table class="table table-sm table-hover" style="min-width:400px;">
                                            <thead>
                                                <tr style="color:#6b7280;font-size:12px;text-transform:uppercase;">
                                                    <th>District</th>
                                                    <th class="text-end">New Shops</th>
                                                    <th class="text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $_newShopGrandTotal = 0; foreach ($newShopByDistrict as $_ns): $_newShopGrandTotal += $_ns['count']; ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($_ns['district']); ?></td>
                                                    <td class="text-end"><?php echo (int)$_ns['count']; ?> / 50</td>
                                                    <td class="text-center">
                                                        <?php if ($_ns['met']): ?>
                                                        <span style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Met</span>
                                                        <?php else: ?>
                                                        <span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Not Met</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr style="font-weight:700;border-top:2px solid #e5e7eb;">
                                                    <td>Total (all districts)</td>
                                                    <td class="text-end"><?php echo (int)$_newShopGrandTotal; ?></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($shopCategorySplit)): ?>
                        <!-- Shop Category Split — pills summary, in a modal off New Shop Onboarding's header -->
                        <div class="modal fade" id="shopCategorySplitModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-scrollable modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Shop Category Split</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p style="margin:0 0 10px;font-size:12.5px;color:#6b7280;">Shops onboarded this period, by category — click a category to see the shop list</p>
                                        <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                            <?php foreach ($shopCategorySplit as $_sc): ?>
                                            <button type="button" class="scs-pill" data-category="<?php echo htmlspecialchars($_sc['category']); ?>" style="background:#eef2ff;color:#3730a3;padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;border:none;cursor:pointer;"><?php echo htmlspecialchars($_sc['category']); ?>: <?php echo (int)$_sc['count']; ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                        <div id="scsResultWrap" style="display:none;margin-top:16px;border-top:1px solid #e5e7eb;padding-top:14px;">
                                            <p id="scsResultTitle" style="margin:0 0 8px;font-size:13px;font-weight:600;color:#374151;"></p>
                                            <div style="overflow-x:auto;">
                                            <table class="table table-sm table-hover">
                                                <thead>
                                                    <tr style="color:#6b7280;font-size:12px;text-transform:uppercase;">
                                                        <th>Shop Name</th>
                                                        <th>Mobile</th>
                                                        <th>District</th>
                                                        <th>Taluk</th>
                                                        <th>Onboarded</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="scsResultTbody"></tbody>
                                            </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                        (function () {
                            var scsWrap = document.getElementById('scsResultWrap');
                            var scsTitle = document.getElementById('scsResultTitle');
                            var scsTbody = document.getElementById('scsResultTbody');
                            if (!scsWrap) return;
                            var scsEsc = document.createElement('div');
                            function scsH(s) { scsEsc.textContent = (s == null ? '' : s); return scsEsc.innerHTML; }
                            var scsFrom = <?php echo json_encode($from); ?>;
                            var scsTo = <?php echo json_encode($to); ?>;
                            document.querySelectorAll('.scs-pill').forEach(function (pill) {
                                pill.addEventListener('click', function () {
                                    var category = pill.getAttribute('data-category');
                                    scsWrap.style.display = 'block';
                                    scsTitle.textContent = category + ' — shops';
                                    scsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Loading…</td></tr>';
                                    var qs = 'category=' + encodeURIComponent(category) + '&from=' + encodeURIComponent(scsFrom) + '&to=' + encodeURIComponent(scsTo);
                                    fetch('get-shop-category-list.php?' + qs)
                                        .then(function (r) { return r.json(); })
                                        .then(function (rows) {
                                            if (!rows || !rows.length) {
                                                scsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No shops found.</td></tr>';
                                                return;
                                            }
                                            var html = '';
                                            rows.forEach(function (r) {
                                                html += '<tr>' +
                                                    '<td>' + scsH(r.name) + '</td>' +
                                                    '<td>' + scsH(r.mobile) + '</td>' +
                                                    '<td>' + scsH(r.district) + '</td>' +
                                                    '<td>' + scsH(r.taluk) + '</td>' +
                                                    '<td>' + scsH(r.date) + '</td>' +
                                                    '</tr>';
                                            });
                                            scsTbody.innerHTML = html;
                                        })
                                        .catch(function () {
                                            scsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Could not load — try again.</td></tr>';
                                        });
                                });
                            });
                        })();
                        </script>

                        <!-- Shop Category Wise — pick a category + date range, list the actual shops -->
                        <div class="modal fade" id="shopCategoryWiseModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-scrollable modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Shop Category Wise</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:14px;">
                                            <div>
                                                <label class="form-label" style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Category</label>
                                                <select id="scwCategory" class="form-control form-control-sm" style="width:220px;">
                                                    <?php foreach ($shopCategorySplit as $_sc): ?>
                                                    <option value="<?php echo htmlspecialchars($_sc['category']); ?>"><?php echo htmlspecialchars($_sc['category']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label" style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">From</label>
                                                <input type="date" id="scwFrom" class="form-control form-control-sm" value="<?php echo htmlspecialchars($from); ?>">
                                            </div>
                                            <div>
                                                <label class="form-label" style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">To</label>
                                                <input type="date" id="scwTo" class="form-control form-control-sm" value="<?php echo htmlspecialchars($to); ?>">
                                            </div>
                                            <div>
                                                <button type="button" id="scwFilterBtn" class="btn btn-sm btn-primary">Filter</button>
                                            </div>
                                        </div>
                                        <div style="overflow-x:auto;">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr style="color:#6b7280;font-size:12px;text-transform:uppercase;">
                                                    <th>Shop Name</th>
                                                    <th>Mobile</th>
                                                    <th>District</th>
                                                    <th>Taluk</th>
                                                    <th>Onboarded</th>
                                                </tr>
                                            </thead>
                                            <tbody id="scwTbody">
                                                <tr><td colspan="5" class="text-center text-muted">Pick a category and click Filter.</td></tr>
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                        (function () {
                            var scwModal = document.getElementById('shopCategoryWiseModal');
                            if (!scwModal) return;
                            var scwTbody = document.getElementById('scwTbody');
                            var scwCategory = document.getElementById('scwCategory');
                            var scwFrom = document.getElementById('scwFrom');
                            var scwTo = document.getElementById('scwTo');
                            var scwEsc = document.createElement('div');
                            function scwH(s) { scwEsc.textContent = (s == null ? '' : s); return scwEsc.innerHTML; }
                            function scwLoad() {
                                scwTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Loading…</td></tr>';
                                var qs = 'category=' + encodeURIComponent(scwCategory.value) + '&from=' + encodeURIComponent(scwFrom.value) + '&to=' + encodeURIComponent(scwTo.value);
                                fetch('get-shop-category-list.php?' + qs)
                                    .then(function (r) { return r.json(); })
                                    .then(function (rows) {
                                        if (!rows || !rows.length) {
                                            scwTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No shops in this category/date range.</td></tr>';
                                            return;
                                        }
                                        var html = '';
                                        rows.forEach(function (r) {
                                            html += '<tr>' +
                                                '<td>' + scwH(r.name) + '</td>' +
                                                '<td>' + scwH(r.mobile) + '</td>' +
                                                '<td>' + scwH(r.district) + '</td>' +
                                                '<td>' + scwH(r.taluk) + '</td>' +
                                                '<td>' + scwH(r.date) + '</td>' +
                                                '</tr>';
                                        });
                                        scwTbody.innerHTML = html;
                                    })
                                    .catch(function () {
                                        scwTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Could not load — try again.</td></tr>';
                                    });
                            }
                            document.getElementById('scwFilterBtn').addEventListener('click', scwLoad);
                            scwModal.addEventListener('shown.bs.modal', scwLoad);
                        })();
                        </script>
                        <?php endif; ?>

                        <?php if (!empty($napkinDiaperSplit)): ?>
                        <!-- Napkin vs Diaper — Orders & Shops -->
                        <div class="row">
                            <div class="col-12">
                                <div class="main-card">
                                    <div class="main-card-header">
                                        <div class="main-card-header-left">
                                            <div class="header-icon-box"><i class="material-icons-outlined">inventory_2</i></div>
                                            <div>
                                                <p class="header-title">Napkin vs Diaper — Orders &amp; Shops</p>
                                                <p class="header-sub">Get Order + No Order visits, this period</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="padding:14px 20px;">
                                        <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                            <span style="background:#dcfce7;color:#15803d;padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;">Napkin Orders: <?php echo (int)$napkinDiaperSplit['napkin_orders']; ?></span>
                                            <span style="background:#dcfce7;color:#15803d;padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;">Napkin Shops: <?php echo (int)$napkinDiaperSplit['napkin_shops']; ?></span>
                                            <span style="background:#fef3c7;color:#92400e;padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;">Diaper Orders: <?php echo (int)$napkinDiaperSplit['diaper_orders']; ?></span>
                                            <span style="background:#fef3c7;color:#92400e;padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;">Diaper Shops: <?php echo (int)$napkinDiaperSplit['diaper_shops']; ?></span>
                                            <?php if ($napkinDiaperSplit['mixed_orders'] > 0): ?>
                                            <span style="background:#e5e7eb;color:#374151;padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;" title="One Get Order with both Napkin and Diaper products in it">Mixed Orders: <?php echo (int)$napkinDiaperSplit['mixed_orders']; ?></span>
                                            <?php endif; ?>
                                            <?php if ($napkinDiaperSplit['unspecified_orders'] > 0): ?>
                                            <span style="background:#e5e7eb;color:#374151;padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;" title="No Order visits logged before the Napkin/Diaper choice was added">Unspecified: <?php echo (int)$napkinDiaperSplit['unspecified_orders']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($returnPctRows)): ?>
                        <!-- Return % — invoices with a return in this period -->
                        <div class="row">
                            <div class="col-12">
                                <div class="main-card">
                                    <div class="main-card-header">
                                        <div class="main-card-header-left">
                                            <div class="header-icon-box"><i class="material-icons-outlined">assignment_return</i></div>
                                            <div>
                                                <p class="header-title">Return %</p>
                                                <p class="header-sub">Overall: &#8377;<?php echo inr_format($returnPctTotalReturned, 0); ?> returned of &#8377;<?php echo inr_format($returnPctTotalInv, 0); ?> invoiced (<?php echo number_format($returnPctOverall, 1); ?>%)</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="padding:14px 20px;">
                                        <div style="overflow-x:auto;">
                                        <table class="table table-sm table-hover" style="min-width:760px;">
                                            <thead>
                                                <tr style="color:#6b7280;font-size:12px;text-transform:uppercase;">
                                                    <th>Invoice Date</th>
                                                    <th>Invoice #</th>
                                                    <th>TP Name</th>
                                                    <th>District</th>
                                                    <th>Firka</th>
                                                    <th class="text-end">Invoice Amount</th>
                                                    <th class="text-end">Returned Amount</th>
                                                    <th class="text-end">Return %</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($returnPctRows as $_rp): ?>
                                                <tr>
                                                    <td><?php echo date('d M Y', strtotime($_rp['date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($_rp['inv_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($_rp['tp_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($_rp['district']); ?></td>
                                                    <td><?php echo htmlspecialchars($_rp['firka']); ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_rp['invoice_total'], 0); ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_rp['returned'], 0); ?></td>
                                                    <td class="text-end" style="font-weight:600;color:<?php echo $_rp['pct'] >= 25 ? '#dc2626' : '#b45309'; ?>;"><?php echo number_format($_rp['pct'], 1); ?>%</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($myDms)): ?>
                        <!-- District Managers under this person -->
                        <div class="row">
                            <div class="col-12">
                                <div class="main-card">
                                    <div class="main-card-header">
                                        <div class="main-card-header-left">
                                            <div class="header-icon-box"><i class="material-icons-outlined">groups</i></div>
                                            <div>
                                                <p class="header-title">My District Managers</p>
                                                <p class="header-sub">Click a name to see their district + target/achieved/balance</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="padding:14px 18px;">
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                            <?php foreach ($myDms as $_i => $_dm): ?>
                                            <span class="dm-name-chip" data-dm-idx="<?php echo $_i; ?>"
                                                  style="cursor:pointer;padding:6px 14px;border-radius:20px;background:#eef2ff;color:#3730a3;font-size:13px;font-weight:600;">
                                                <?php echo htmlspecialchars($_dm['name']); ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php foreach ($myDms as $_i => $_dm): ?>
                                        <div class="dm-detail-card" data-dm-idx="<?php echo $_i; ?>" style="display:none;margin-top:14px;padding:14px 16px;border:1px solid #e5e7eb;border-radius:10px;background:#fafafa;">
                                            <p style="margin:0 0 10px;font-weight:700;color:#1f2937;"><?php echo htmlspecialchars($_dm['name']); ?></p>
                                            <p style="margin:0 0 10px;font-size:12.5px;color:#6b7280;">District: <b style="color:#374151;"><?php echo htmlspecialchars($_dm['district']); ?></b></p>
                                            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                                <span class="chain-pill" style="background:#eee;color:#333;padding:5px 12px;border-radius:14px;font-size:12.5px;">Target: &#8377;<?php echo inr_format($_dm['target'], 0); ?></span>
                                                <span class="chain-pill" style="background:#eee;color:#333;padding:5px 12px;border-radius:14px;font-size:12.5px;" title="Get Order-sourced, Napkin-only sales converted by this DM">Achieved: &#8377;<?php echo inr_format($_dm['achieved'], 0); ?></span>
                                                <span class="chain-pill" style="background:#d1fae5;color:#065f46;padding:5px 12px;border-radius:14px;font-size:12.5px;font-weight:700;" title="Every shop invoice raised by this DM's TPs in the period — same as this DM's own dashboard card">TP Invoice Amount: &#8377;<?php echo inr_format($_dm['tp_invoice_amount'], 0); ?></span>
                                                <span class="chain-pill" style="background:#eee;color:#333;padding:5px 12px;border-radius:14px;font-size:12.5px;">Balance: &#8377;<?php echo inr_format($_dm['balance'], 0); ?></span>
                                            </div>
                                            <div style="margin-top:10px;">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="var t=document.getElementById('dmVisitDetail<?php echo $_i; ?>'); var open=t.style.display!=='none'; t.style.display=open?'none':'block'; this.textContent=open?'View Daily Visit Compliance':'Hide Daily Visit Compliance';">View Daily Visit Compliance</button>
                                                <div id="dmVisitDetail<?php echo $_i; ?>" style="display:none;margin-top:10px;">
                                                    <p style="margin:0 0 8px;font-size:12px;color:#6b7280;">Target: 35 shop visits/day, 20 of them Get Order</p>
                                                    <div style="overflow-x:auto;">
                                                    <table class="table table-sm table-hover" style="min-width:640px;">
                                                        <thead>
                                                            <tr style="color:#6b7280;font-size:12px;text-transform:uppercase;">
                                                                <th>Date</th>
                                                                <th class="text-end">Total Visits</th>
                                                                <th class="text-end">New</th>
                                                                <th class="text-end">Existing</th>
                                                                <th class="text-end">Get Order Visits</th>
                                                                <th class="text-end">Napkin Shops</th>
                                                                <th class="text-end">Diaper Shops</th>
                                                                <th class="text-end">Diaper Value</th>
                                                                <th class="text-center">Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="dmVisitTbody<?php echo $_i; ?>">
                                                            <?php
                                                            $_dmVdTot = ['total_visits' => 0, 'new_visits' => 0, 'existing_visits' => 0, 'get_order_visits' => 0, 'napkin_shops' => 0, 'diaper_shops' => 0, 'diaper_value' => 0.0];
                                                            foreach (($_dm['visit_days'] ?? []) as $_vd):
                                                                $_dmVdTot['total_visits']     += $_vd['total_visits'];
                                                                $_dmVdTot['new_visits']       += $_vd['new_visits'];
                                                                $_dmVdTot['existing_visits']  += $_vd['existing_visits'];
                                                                $_dmVdTot['get_order_visits'] += $_vd['get_order_visits'];
                                                                $_dmVdTot['napkin_shops']     += $_vd['napkin_shops'];
                                                                $_dmVdTot['diaper_shops']     += $_vd['diaper_shops'];
                                                                $_dmVdTot['diaper_value']     += $_vd['diaper_value'];
                                                            ?>
                                                            <tr>
                                                                <td><?php echo date('d M Y', strtotime($_vd['date'])); ?></td>
                                                                <td class="text-end"><?php echo (int)$_vd['total_visits']; ?> / 35</td>
                                                                <td class="text-end"><?php echo (int)$_vd['new_visits']; ?></td>
                                                                <td class="text-end"><?php echo (int)$_vd['existing_visits']; ?></td>
                                                                <td class="text-end"><?php echo (int)$_vd['get_order_visits']; ?> / 20</td>
                                                                <td class="text-end"><?php echo (int)$_vd['napkin_shops']; ?></td>
                                                                <td class="text-end"><?php echo (int)$_vd['diaper_shops']; ?></td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_vd['diaper_value'], 0); ?></td>
                                                                <td class="text-center">
                                                                    <?php if ($_vd['visit_target_met'] && $_vd['get_order_target_met']): ?>
                                                                    <span style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Met</span>
                                                                    <?php else: ?>
                                                                    <span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Not Met</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                        <tfoot>
                                                            <tr style="font-weight:600;border-top:2px solid #e5e7eb;">
                                                                <td>Total</td>
                                                                <td class="text-end"><?php echo (int)$_dmVdTot['total_visits']; ?></td>
                                                                <td class="text-end"><?php echo (int)$_dmVdTot['new_visits']; ?></td>
                                                                <td class="text-end"><?php echo (int)$_dmVdTot['existing_visits']; ?></td>
                                                                <td class="text-end"><?php echo (int)$_dmVdTot['get_order_visits']; ?></td>
                                                                <td class="text-end"><?php echo (int)$_dmVdTot['napkin_shops']; ?></td>
                                                                <td class="text-end"><?php echo (int)$_dmVdTot['diaper_shops']; ?></td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_dmVdTot['diaper_value'], 0); ?></td>
                                                                <td></td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                    </div>
                                                    <div id="dmVisitPager<?php echo $_i; ?>" class="mis-pagination" style="margin-top:10px;"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                        document.querySelectorAll('.dm-name-chip').forEach(function(chip) {
                            chip.addEventListener('click', function() {
                                var idx = this.getAttribute('data-dm-idx');
                                document.querySelectorAll('.dm-detail-card').forEach(function(card) {
                                    card.style.display = (card.getAttribute('data-dm-idx') === idx && card.style.display === 'none') ? 'block' : 'none';
                                });
                            });
                        });

                        // 7-rows-per-page client-side pagination for a Daily Visit Compliance
                        // table (31 day rows otherwise all render at once) — rows are already
                        // in the page's HTML (no AJAX), this just hides all but the current
                        // page's <tr>s and draws Prev/page-number/Next controls.
                        function femi9PaginateVisitTable(tbodyId, pagerId, pageSize) {
                            var tbody = document.getElementById(tbodyId);
                            var pager = document.getElementById(pagerId);
                            if (!tbody || !pager) return;
                            var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                            if (!rows.length) return;
                            var totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
                            var currentPage = 1;

                            function render() {
                                var start = (currentPage - 1) * pageSize;
                                rows.forEach(function (row, i) {
                                    row.style.display = (i >= start && i < start + pageSize) ? '' : 'none';
                                });
                                var html = '<span class="mis-pg-info">' + (start + 1) + '–' + Math.min(start + pageSize, rows.length) + ' of ' + rows.length + '</span>';
                                html += '<button type="button" data-pg="prev"' + (currentPage === 1 ? ' disabled' : '') + '>Prev</button>';
                                var winSize = 5;
                                var winStart = Math.max(1, currentPage - Math.floor(winSize / 2));
                                var winEnd = Math.min(totalPages, winStart + winSize - 1);
                                winStart = Math.max(1, winEnd - winSize + 1);
                                for (var p = winStart; p <= winEnd; p++) {
                                    html += '<button type="button" data-pg="' + p + '" class="' + (p === currentPage ? 'active' : '') + '">' + p + '</button>';
                                }
                                html += '<button type="button" data-pg="next"' + (currentPage === totalPages ? ' disabled' : '') + '>Next</button>';
                                pager.innerHTML = html;
                            }
                            pager.addEventListener('click', function (e) {
                                var btn = e.target.closest('button[data-pg]');
                                if (!btn) return;
                                var pg = btn.getAttribute('data-pg');
                                if (pg === 'prev') currentPage = Math.max(1, currentPage - 1);
                                else if (pg === 'next') currentPage = Math.min(totalPages, currentPage + 1);
                                else currentPage = parseInt(pg, 10);
                                render();
                            });
                            render();
                        }
                        <?php foreach ($myDms as $_i => $_dm): ?>
                        femi9PaginateVisitTable('dmVisitTbody<?php echo $_i; ?>', 'dmVisitPager<?php echo $_i; ?>', 7);
                        <?php endforeach; ?>
                        </script>
                        <?php endif; ?>

                        <?php if (!empty($myDms)): ?>
                        <!-- Target vs Achieved — DM level, with a tab for No-DM districts -->
                        <div class="row">
                            <div class="col-12">
                                <div class="main-card">
                                    <div class="main-card-header">
                                        <div class="main-card-header-left">
                                            <div class="header-icon-box"><i class="material-icons-outlined">insights</i></div>
                                            <div>
                                                <p class="header-title">Target vs Achieved</p>
                                                <p class="header-sub">District Manager level</p>
                                            </div>
                                        </div>
                                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                            <?php if (!empty($noDmDistrictRows)): ?>
                                            <button type="button" class="dash-tab-btn active" data-tab-target="dmTargetTab" style="padding:7px 14px;border-radius:8px;border:1px solid #c7d2fe;background:#eef2ff;color:#3730a3;font-size:13px;font-weight:600;cursor:pointer;">Target vs Achieved</button>
                                            <button type="button" class="dash-tab-btn" data-tab-target="noDmTab" style="padding:7px 14px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;color:#374151;font-size:13px;font-weight:600;cursor:pointer;">TP Invoice Amount — No DM Assigned</button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#teamWeeklyModal">ASM Weekly Target Amount</button>
                                        </div>
                                    </div>
                                    <div id="dmTargetTab" style="padding:14px 20px;">
                                        <div style="overflow-x:auto;">
                                        <table class="table table-sm table-hover" style="min-width:760px;">
                                            <thead>
                                                <tr style="color:#6b7280;font-size:12px;text-transform:uppercase;">
                                                    <th>DM</th>
                                                    <th class="text-end">Target Amount</th>
                                                    <th class="text-end" title="This DM's own Get Order-converted, Napkin-only sales">Achieved</th>
                                                    <th class="text-end" title="Every shop invoice this DM's TPs raised in the period">TP Invoice Amount</th>
                                                    <th class="text-end" title="Achieved + TP Invoice Amount">Achieve Total</th>
                                                    <th class="text-end" title="Return amount against invoices this DM's own Get Orders converted into">Get Order Return</th>
                                                    <th class="text-end" title="Return amount against every shop invoice this DM's own TPs raised (broader than Get Order Return)">TP Return</th>
                                                    <th class="text-end" title="Target Amount − Achieve Total">Balance</th>
                                                    <th class="text-center" title="Date this DM's cumulative Napkin-only (no Diaper) sales first reached their Target Amount">Target Completed Date</th>
                                                    <th class="text-center">Weekly</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $_dmTotal = ['target' => 0, 'achieved' => 0, 'tp_invoice_amount' => 0, 'achieve_total' => 0, 'total_balance' => 0, 'get_order_return_amt' => 0, 'tp_return_amt' => 0];
                                                foreach ($myDms as $_dmIdx => $_dmRow):
                                                    $_dmTotal['target'] += $_dmRow['target'];
                                                    $_dmTotal['achieved'] += $_dmRow['achieved'];
                                                    $_dmTotal['tp_invoice_amount'] += $_dmRow['tp_invoice_amount'];
                                                    $_dmTotal['achieve_total'] += $_dmRow['achieve_total'];
                                                    $_dmTotal['total_balance'] += $_dmRow['total_balance'];
                                                    $_dmTotal['get_order_return_amt'] += $_dmRow['get_order_return_amt'] ?? 0;
                                                    $_dmTotal['tp_return_amt'] += $_dmRow['tp_return_amt'] ?? 0;
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($_dmRow['name']); ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_dmRow['target'], 0); ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_dmRow['achieved'], 0); ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_dmRow['tp_invoice_amount'], 0); ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_dmRow['achieve_total'], 0); ?></td>
                                                    <td class="text-end" style="color:<?php echo ($_dmRow['get_order_return_amt'] ?? 0) > 0 ? '#dc2626' : '#9ca3af'; ?>;">&#8377;<?php echo inr_format($_dmRow['get_order_return_amt'] ?? 0, 0); ?></td>
                                                    <td class="text-end" style="color:<?php echo ($_dmRow['tp_return_amt'] ?? 0) > 0 ? '#dc2626' : '#9ca3af'; ?>;">&#8377;<?php echo inr_format($_dmRow['tp_return_amt'] ?? 0, 0); ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_dmRow['total_balance'], 0); ?></td>
                                                    <td class="text-center">
                                                        <?php if (!empty($_dmRow['target_completed_date'])): ?>
                                                        <span style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;"><?php echo date('d M Y', strtotime($_dmRow['target_completed_date'])); ?></span>
                                                        <?php else: ?>
                                                        <span style="color:#9ca3af;font-size:12.5px;">Not yet</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#dmWeeklyModal<?php echo $_dmIdx; ?>">Weekly</button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr style="font-weight:600;border-top:2px solid #e5e7eb;">
                                                    <td>Total</td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_dmTotal['target'], 0); ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_dmTotal['achieved'], 0); ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_dmTotal['tp_invoice_amount'], 0); ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_dmTotal['achieve_total'], 0); ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_dmTotal['get_order_return_amt'], 0); ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_dmTotal['tp_return_amt'], 0); ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_dmTotal['total_balance'], 0); ?></td>
                                                    <td></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                        </div>
                                    </div>

                                    <div class="modal fade" id="teamWeeklyModal" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-scrollable modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">ASM Weekly Target Amount — Whole Team</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div style="overflow-x:auto;">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr style="color:#6b7280;font-size:12px;text-transform:uppercase;">
                                                                <th>Week</th>
                                                                <th class="text-end">Target Amount</th>
                                                                <th class="text-end" title="Sum of every DM's own Get Order-converted, Napkin-only sales">Achieved</th>
                                                                <th class="text-end" title="Sum of every shop invoice this team's TPs raised">TP Invoice Amount</th>
                                                                <th class="text-end" title="Achieved + TP Invoice Amount">Total</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $_teamWkTotal = ['target' => 0, 'achieved' => 0, 'tp_invoice' => 0, 'total' => 0];
                                                            foreach ($teamWeeklyBreakdown as $_wk):
                                                                $_teamWkTotal['target']     += $_wk['target'];
                                                                $_teamWkTotal['achieved']   += $_wk['achieved'];
                                                                $_teamWkTotal['tp_invoice'] += $_wk['tp_invoice'];
                                                                $_teamWkTotal['total']      += $_wk['total'];
                                                            ?>
                                                            <tr>
                                                                <td><?php echo date('d M', strtotime($_wk['start'])); ?> &ndash; <?php echo date('d M', strtotime($_wk['end'])); ?></td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_wk['target'], 0); ?></td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_wk['achieved'], 0); ?></td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_wk['tp_invoice'], 0); ?></td>
                                                                <td class="text-end" style="font-weight:600;">&#8377;<?php echo inr_format($_wk['total'], 0); ?></td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                        <tfoot>
                                                            <tr style="font-weight:700;border-top:2px solid #e5e7eb;">
                                                                <td>Total</td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_teamWkTotal['target'], 0); ?></td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_teamWkTotal['achieved'], 0); ?></td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_teamWkTotal['tp_invoice'], 0); ?></td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_teamWkTotal['total'], 0); ?></td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php foreach ($myDms as $_dmIdx => $_dmRow): ?>
                                    <div class="modal fade" id="dmWeeklyModal<?php echo $_dmIdx; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-scrollable modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><?php echo htmlspecialchars($_dmRow['name']); ?> — Weekly Target</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div style="overflow-x:auto;">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr style="color:#6b7280;font-size:12px;text-transform:uppercase;">
                                                                <th>Week</th>
                                                                <th class="text-end">Target Amount</th>
                                                                <th class="text-end" title="This DM's own Get Order-converted, Napkin-only sales">Achieved</th>
                                                                <th class="text-end" title="Every shop invoice this DM's TPs raised">TP Invoice Amount</th>
                                                                <th class="text-end" title="Achieved + TP Invoice Amount">Total</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $_dmWkTotal = ['target' => 0, 'achieved' => 0, 'tp_invoice' => 0, 'total' => 0];
                                                            foreach (($_dmRow['weeks'] ?? []) as $_wk):
                                                                $_dmWkTotal['target']     += $_wk['target'];
                                                                $_dmWkTotal['achieved']   += $_wk['achieved'];
                                                                $_dmWkTotal['tp_invoice'] += $_wk['tp_invoice'];
                                                                $_dmWkTotal['total']      += $_wk['total'];
                                                            ?>
                                                            <tr>
                                                                <td><?php echo date('d M', strtotime($_wk['start'])); ?> &ndash; <?php echo date('d M', strtotime($_wk['end'])); ?></td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_wk['target'], 0); ?></td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_wk['achieved'], 0); ?></td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_wk['tp_invoice'], 0); ?></td>
                                                                <td class="text-end" style="font-weight:600;">&#8377;<?php echo inr_format($_wk['total'], 0); ?></td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                        <tfoot>
                                                            <tr style="font-weight:700;border-top:2px solid #e5e7eb;">
                                                                <td>Total</td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_dmWkTotal['target'], 0); ?></td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_dmWkTotal['achieved'], 0); ?></td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_dmWkTotal['tp_invoice'], 0); ?></td>
                                                                <td class="text-end">&#8377;<?php echo inr_format($_dmWkTotal['total'], 0); ?></td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (!empty($noDmDistrictRows)): ?>
                                    <div id="noDmTab" style="padding:14px 20px;display:none;">
                                        <p style="margin:0 0 10px;font-size:12.5px;color:#6b7280;">Your own assigned district(s) that none of your DMs currently cover</p>
                                        <div style="overflow-x:auto;">
                                        <table class="table table-sm table-hover" style="min-width:400px;">
                                            <thead>
                                                <tr style="color:#6b7280;font-size:12px;text-transform:uppercase;">
                                                    <th>District</th>
                                                    <th class="text-end">TP Invoice Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $_noDmTotal = 0; foreach ($noDmDistrictRows as $_row): $_noDmTotal += $_row['tp_invoice']; ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($_row['district']); ?></td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_row['tp_invoice'], 0); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr style="font-weight:600;border-top:2px solid #e5e7eb;">
                                                    <td>Total</td>
                                                    <td class="text-end">&#8377;<?php echo inr_format($_noDmTotal, 0); ?></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                        </div>
                                    </div>
                                    <script>
                                    document.querySelectorAll('.dash-tab-btn').forEach(function(btn) {
                                        btn.addEventListener('click', function() {
                                            document.querySelectorAll('.dash-tab-btn').forEach(function(b) {
                                                b.classList.remove('active');
                                                b.style.background = '#fff';
                                                b.style.color = '#374151';
                                                b.style.borderColor = '#e5e7eb';
                                            });
                                            this.classList.add('active');
                                            this.style.background = '#eef2ff';
                                            this.style.color = '#3730a3';
                                            this.style.borderColor = '#c7d2fe';
                                            document.getElementById('dmTargetTab').style.display = (this.getAttribute('data-tab-target') === 'dmTargetTab') ? 'block' : 'none';
                                            document.getElementById('noDmTab').style.display = (this.getAttribute('data-tab-target') === 'noDmTab') ? 'block' : 'none';
                                        });
                                    });
                                    </script>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($zoneRollup)): ?>
                        <!-- Zone-wise Target Rollup — ASM/SM view -->
                        <div class="row">
                            <div class="col-12">
                                <div class="main-card">
                                    <div class="main-card-header">
                                        <div class="main-card-header-left">
                                            <div class="header-icon-box"><i class="material-icons-outlined">map</i></div>
                                            <div>
                                                <p class="header-title">Zone-wise Target</p>
                                                <p class="header-sub">Every zone covering this team's districts — target covers the whole zone, even districts with no DM assigned</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="padding:14px 20px;">
                                        <?php foreach ($zoneRollup as $_zoneIdx => $_zone): ?>
                                        <div style="margin-bottom:18px;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                                            <div style="padding:10px 14px;background:#f9fafb;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                                                <strong><?php echo htmlspecialchars($_zone['name']); ?></strong>
                                                <span style="font-size:13px;color:#374151;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                                    Target: &#8377;<?php echo inr_format($_zone['target'], 0); ?>
                                                    &nbsp;|&nbsp; Achieve Total: &#8377;<?php echo inr_format($_zone['achieve_total'], 0); ?>
                                                    &nbsp;|&nbsp; <?php echo $_zone['overage_amt'] >= 0 ? 'Over' : 'Under'; ?> Target:
                                                    <span style="font-weight:600;color:<?php echo $_zone['overage_amt'] >= 0 ? '#16a34a' : '#dc2626'; ?>;">
                                                        &#8377;<?php echo inr_format(abs($_zone['overage_amt']), 0); ?>
                                                        (<?php echo number_format($_zone['overage_pct'], 1); ?>%)
                                                    </span>
                                                    &nbsp;|&nbsp; Return: <span style="font-weight:600;color:<?php echo ($_zone['return_amt'] ?? 0) > 0 ? '#dc2626' : '#9ca3af'; ?>;">&#8377;<?php echo inr_format($_zone['return_amt'] ?? 0, 0); ?></span>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#zoneWeeklyModal<?php echo $_zoneIdx; ?>">Weekly</button>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="modal fade" id="zoneWeeklyModal<?php echo $_zoneIdx; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-scrollable">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><?php echo htmlspecialchars($_zone['name']); ?> — Weekly Target</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <table class="table table-sm">
                                                            <thead>
                                                                <tr style="color:#6b7280;font-size:12px;text-transform:uppercase;">
                                                                    <th>Week</th>
                                                                    <th class="text-end">Target Amount</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($_zone['weeks'] as $_wk): ?>
                                                                <tr>
                                                                    <td><?php echo date('d M', strtotime($_wk['start'])); ?> &ndash; <?php echo date('d M', strtotime($_wk['end'])); ?></td>
                                                                    <td class="text-end">&#8377;<?php echo inr_format($_wk['target'], 0); ?></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Shop Invoices (full width) -->
                        <div class="row">
                            <div class="col-12">
                                <div class="main-card">
                                    <div class="main-card-header">
                                        <div class="main-card-header-left">
                                            <div class="header-icon-box"><i class="material-icons-outlined">receipt</i></div>
                                            <div>
                                                <p class="header-title">Shop Invoices</p>
                                                <p class="header-sub">TP sales to shops in your district</p>
                                            </div>
                                        </div>
                                        <form class="date-filter-form" method="get">
                                            <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
                                            <span style="color:#9ca3af;">to</span>
                                            <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                                            <a class="btn btn-sm btn-success" id="shopInvExportLink" href="export-shop-invoices-xlsx.php?from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>">
                                                <i class="material-icons-outlined" style="font-size:15px;vertical-align:text-bottom;">file_download</i> Export
                                            </a>
                                        </form>
                                    </div>

                                    <div style="padding:14px 20px 0;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;">
                                        <?php if (count($districtNames) > 1): ?>
                                        <select id="shopInvDistrictFilter" class="dash-search" style="max-width:220px;">
                                            <option value="all">All Districts</option>
                                            <?php foreach ($districtNames as $dn): ?>
                                            <option value="<?php echo htmlspecialchars($dn); ?>"><?php echo htmlspecialchars($dn); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php endif; ?>
                                        <input type="text" id="shopInvSearch" class="dash-search" placeholder="Search invoices…">
                                    </div>

                                    <div class="table-responsive" style="max-height:480px;overflow-y:auto;">
                                        <table class="tp-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:48px;text-align:center;">#</th>
                                                    <th>TP Name</th>
                                                    <th>TP ID</th>
                                                    <th>Phone</th>
                                                    <th style="text-align:right;">Target Amount</th>
                                                    <th>Shop Name</th>
                                                    <th>Invoice Number</th>
                                                    <th style="text-align:right;">Invoice Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody id="shopInvTbody">
                                                <tr><td colspan="8" class="text-muted" style="text-align:center;padding:30px;">Loading…</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div id="shopInvPagination" class="mis-pagination"></div>
                                </div>
                            </div>
                        </div>

                        <script>
                        (function () {
                            var allRows = <?php echo json_encode(array_map(function ($r) {
                                return [
                                    'tp_name'   => ucwords(strtolower($r['tp_name'])),
                                    'tp_code'   => $r['tp_code'],
                                    'tp_mobile' => $r['tp_mobile'],
                                    'tp_district'=> $r['tp_district'],
                                    'tp_target' => (float)$r['tp_target'],
                                    'shop_name' => $r['shop_name'],
                                    'inv_number'=> $r['inv_number'],
                                    'inv_id'    => base64_encode($r['inv_id']),
                                    'total'     => (float)$r['total'],
                                ];
                            }, $invoiceRows)); ?>;
                            var pageSize = 10;
                            var currentPage = 1;
                            var searchTerm = '';
                            var currentDistrict = 'all';

                            var districtTargetTotals = <?php echo json_encode($districtTargetTotals); ?>;
                            var overallTarget = <?php echo (float)$districtStats['district_total_target']; ?>;
                            var districtAchievedTotals = <?php echo json_encode($districtAchievedTotals); ?>;
                            var overallAchieved = <?php echo (float)$ownGetOrderAchieved; ?>;

                            var escDiv = document.createElement('div');
                            function esc(s) { escDiv.textContent = (s == null ? '' : s); return escDiv.innerHTML; }
                            function money(n) { return '&#8377;' + Number(n || 0).toLocaleString('en-IN', {minimumFractionDigits: 0, maximumFractionDigits: 0}); }

                            // District-only filter (ignores the search box) — the amount in
                            // the top stat cards should track the district picked, not whatever
                            // is currently typed into the invoice search field.
                            function districtOnlyRows(district) {
                                return allRows.filter(function (r) {
                                    return district === 'all' || (r.tp_district || '').toLowerCase() === district.toLowerCase();
                                });
                            }

                            function updateStatCards() {
                                var targetEl = document.getElementById('statDistrictTarget');
                                var invoiceEl = document.getElementById('statInvoiceAmount');
                                var balanceEl = document.getElementById('statBalanceTarget');
                                if (!targetEl || !invoiceEl || !balanceEl) return;
                                var target = currentDistrict === 'all' ? overallTarget : (districtTargetTotals[currentDistrict] || 0);
                                var invoiced = districtOnlyRows(currentDistrict).reduce(function (sum, r) { return sum + (r.total || 0); }, 0);
                                var getOrderAmt = currentDistrict === 'all' ? overallAchieved : (districtAchievedTotals[currentDistrict] || 0);
                                var totalAmt = invoiced + getOrderAmt;
                                var balance = Math.max(0, target - totalAmt);
                                targetEl.innerHTML = money(target);
                                invoiceEl.innerHTML = money(totalAmt);
                                balanceEl.innerHTML = money(balance);
                            }

                            function filteredRows() {
                                return allRows.filter(function (r) {
                                    if (currentDistrict !== 'all' && (r.tp_district || '').toLowerCase() !== currentDistrict.toLowerCase()) return false;
                                    if (searchTerm && (r.tp_name + ' ' + r.tp_code + ' ' + r.tp_mobile + ' ' + r.inv_number + ' ' + (r.shop_name || '')).toLowerCase().indexOf(searchTerm) === -1) return false;
                                    return true;
                                });
                            }

                            function render() {
                                var rows = filteredRows();
                                var tbody = document.getElementById('shopInvTbody');
                                var pager = document.getElementById('shopInvPagination');
                                if (!rows.length) {
                                    tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state"><div class="empty-icon-wrap"><i class="material-icons-outlined">inbox</i></div><h6>No Invoices Found</h6><p>No shop invoices match this filter.</p></div></td></tr>';
                                    pager.innerHTML = '';
                                    updateStatCards();
                                    return;
                                }
                                var totalPages = Math.ceil(rows.length / pageSize);
                                if (currentPage > totalPages) currentPage = totalPages;
                                var start = (currentPage - 1) * pageSize;
                                var pageRows = rows.slice(start, start + pageSize);
                                var html = '';
                                pageRows.forEach(function (r, i) {
                                    html += '<tr>' +
                                        '<td style="text-align:center;"><span class="serial-num">' + (start + i + 1) + '</span></td>' +
                                        '<td><span class="tp-name">' + esc(r.tp_name) + '</span></td>' +
                                        '<td><span class="tp-code-pill">' + esc(r.tp_code) + '</span></td>' +
                                        '<td><div class="mobile-cell"><i class="material-icons-outlined">phone_iphone</i><span class="mobile-text">' + esc(r.tp_mobile) + '</span></div></td>' +
                                        '<td style="text-align:right;"><span class="sales-amount">' + money(r.tp_target) + '</span></td>' +
                                        '<td>' + esc(r.shop_name || '—') + '</td>' +
                                        '<td><a class="inv-number-link" target="_blank" href="view-shop-invoice.php?invoiceid=' + encodeURIComponent(r.inv_id) + '">' + esc(r.inv_number) + '</a></td>' +
                                        '<td style="text-align:right;"><span class="sales-amount">' + money(r.total) + '</span></td>' +
                                        '</tr>';
                                });
                                tbody.innerHTML = html;

                                var pHtml = '<span class="mis-pg-info">' + (start + 1) + '–' + Math.min(start + pageSize, rows.length) + ' of ' + rows.length + '</span>';
                                pHtml += '<button type="button" data-pg="prev"' + (currentPage === 1 ? ' disabled' : '') + '>Prev</button>';
                                var winSize = 5;
                                var winStart = Math.max(1, currentPage - Math.floor(winSize / 2));
                                var winEnd = Math.min(totalPages, winStart + winSize - 1);
                                winStart = Math.max(1, winEnd - winSize + 1);
                                for (var p = winStart; p <= winEnd; p++) {
                                    pHtml += '<button type="button" data-pg="' + p + '" class="' + (p === currentPage ? 'active' : '') + '">' + p + '</button>';
                                }
                                pHtml += '<button type="button" data-pg="next"' + (currentPage === totalPages ? ' disabled' : '') + '>Next</button>';
                                pager.innerHTML = pHtml;
                                updateStatCards();
                            }

                            document.getElementById('shopInvPagination').addEventListener('click', function (e) {
                                var btn = e.target.closest('button[data-pg]');
                                if (!btn) return;
                                var pg = btn.getAttribute('data-pg');
                                var totalPages = Math.ceil(filteredRows().length / pageSize);
                                if (pg === 'prev') currentPage = Math.max(1, currentPage - 1);
                                else if (pg === 'next') currentPage = Math.min(totalPages, currentPage + 1);
                                else currentPage = parseInt(pg, 10);
                                render();
                            });

                            document.getElementById('shopInvSearch').addEventListener('input', function () {
                                searchTerm = this.value.toLowerCase().trim();
                                currentPage = 1;
                                render();
                            });

                            // Keeps the Export link's district in sync with the on-screen
                            // dropdown — otherwise the export always totalled every
                            // assigned district regardless of what's selected here,
                            // mismatching the visible "Your TPs' Invoice Amount" card.
                            var shopInvExportLink = document.getElementById('shopInvExportLink');
                            var shopInvExportBaseHref = shopInvExportLink ? shopInvExportLink.getAttribute('href') : null;
                            function updateShopInvExportHref() {
                                if (!shopInvExportLink || !shopInvExportBaseHref) return;
                                var href = shopInvExportBaseHref;
                                if (currentDistrict && currentDistrict !== 'all') {
                                    href += '&district=' + encodeURIComponent(currentDistrict);
                                }
                                shopInvExportLink.setAttribute('href', href);
                            }

                            var shopInvDistrictFilter = document.getElementById('shopInvDistrictFilter');
                            if (shopInvDistrictFilter) {
                                shopInvDistrictFilter.addEventListener('change', function () {
                                    currentDistrict = this.value;
                                    currentPage = 1;
                                    updateShopInvExportHref();
                                    render();
                                });
                            }
                            updateShopInvExportHref();

                            render();
                        })();
                        </script>

                        <!-- Territory Partners (left) + Firka Coverage (right) -->
                        <div class="row">
                            <div class="col-12 col-xl-8">
                                <div class="main-card">
                                    <div class="main-card-header">
                                        <div class="main-card-header-left">
                                            <div class="header-icon-box"><i class="material-icons-outlined">people_alt</i></div>
                                            <div>
                                                <p class="header-title">Territory Partners</p>
                                                <p class="header-sub">In your assigned district(s)</p>
                                            </div>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                            <?php if (count($districtTpCounts) > 1): ?>
                                            <select id="tpRosterDistrictFilter" class="dash-search" style="max-width:200px;">
                                                <option value="all">All Districts</option>
                                                <?php foreach (array_keys($districtTpCounts) as $_dn): ?>
                                                <option value="<?php echo htmlspecialchars($_dn, ENT_QUOTES); ?>"><?php echo htmlspecialchars($_dn); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php endif; ?>
                                            <input type="text" id="tpSearch" class="dash-search" placeholder="Search TPs…">
                                        </div>
                                    </div>

                                    <div class="status-filter-tabs" id="tpRosterTabs">
                                        <button type="button" class="status-filter-btn active" data-status="all">Total TPs <span class="tp-count-pill"><?php echo count($tpRoster); ?></span></button>
                                        <button type="button" class="status-filter-btn" data-status="active">Active TPs <span class="tp-count-pill"><?php echo count($activeTpRoster); ?></span></button>
                                        <button type="button" class="status-filter-btn" data-status="inactive">Inactive TPs <span class="tp-count-pill"><?php echo count($inactiveTpRoster); ?></span></button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="tp-table tp-roster-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:48px;text-align:center;">#</th>
                                                    <th>Territory Partner</th>
                                                    <th>Mobile</th>
                                                    <th>District</th>
                                                    <th>Firka</th>
                                                    <th style="text-align:right;">Target Amount</th>
                                                    <th style="text-align:center;">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tpRosterTbody">
                                                <tr><td colspan="7" class="text-muted" style="text-align:center;padding:30px;">Loading…</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div id="tpRosterPagination" class="mis-pagination"></div>
                                </div>
                            </div>

                            <!-- Right: Firka coverage -->
                            <div class="col-12 col-xl-4">
                                <div class="main-card" style="padding:4px 0;">
                                    <div class="main-card-header">
                                        <div class="main-card-header-left">
                                            <div class="header-icon-box"><i class="material-icons-outlined">map</i></div>
                                            <div>
                                                <p class="header-title">Firka Coverage</p>
                                                <p class="header-sub">Your assigned district(s)</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="padding:18px 20px 4px;">
                                        <div class="firka-tile">
                                            <div>
                                                <div class="ft-label">Total Firkas</div>
                                                <div class="ft-count"><?php echo htmlspecialchars((string)($districtStats['firka_total_count']), ENT_QUOTES, 'ISO-8859-1', false); ?></div>
                                            </div>
                                            <div class="ft-value">&#8377;<?php echo inr_format($districtStats['district_total_target'], 0); ?></div>
                                        </div>
                                        <div class="firka-tile">
                                            <div>
                                                <div class="ft-label">Filled Firkas</div>
                                                <div class="ft-count" style="color:#10b981;"><?php echo htmlspecialchars((string)($districtStats['firka_filled_count']), ENT_QUOTES, 'ISO-8859-1', false); ?></div>
                                            </div>
                                            <div class="ft-value">&#8377;<?php echo inr_format($districtStats['target_active_amount'] + $districtStats['target_inactive_amount'], 0); ?></div>
                                        </div>
                                        <div class="firka-tile firka-tile-clickable" id="vacantFirkaTile" data-bs-toggle="modal" data-bs-target="#vacantFirkaModal" role="button" tabindex="0">
                                            <div>
                                                <div class="ft-label">Vacant Firkas</div>
                                                <div class="ft-count" style="color:#ef4444;"><?php echo htmlspecialchars((string)($districtStats['firka_vacant_count']), ENT_QUOTES, 'ISO-8859-1', false); ?></div>
                                            </div>
                                            <div style="text-align:right;">
                                                <div class="ft-value">&#8377;<?php echo inr_format($districtStats['target_unassigned_amount'], 0); ?></div>
                                                <?php if (!empty($vacantFirkas)): ?>
                                                <div class="ft-tap-hint"><i class="material-icons-outlined" style="font-size:13px;vertical-align:text-bottom;">touch_app</i> Tap for list</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if (count($districtTpCounts) > 1):
                                    $_totalActive = 0; $_totalInactive = 0;
                                    foreach ($districtTpCounts as $_cnt) { $_totalActive += $_cnt['active']; $_totalInactive += $_cnt['inactive']; }
                                ?>
                                <!-- Team Districts — every district this person's team (or they themselves)
                                     covers, with Active/Inactive TP counts; clicking one filters the
                                     Territory Partners roster above to just that district. -->
                                <div class="main-card" style="margin-top:20px;">
                                    <div class="main-card-header">
                                        <div class="main-card-header-left">
                                            <div class="header-icon-box"><i class="material-icons-outlined">map</i></div>
                                            <div>
                                                <p class="header-title">Team Districts</p>
                                                <p class="header-sub">Click a district to filter Territory Partners above</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="padding:4px 18px 0;">
                                        <table class="tp-table" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>District</th>
                                                    <th style="text-align:right;">Active</th>
                                                    <th style="text-align:right;">Inactive</th>
                                                </tr>
                                            </thead>
                                            <tbody id="teamDistrictRows">
                                                <?php foreach ($districtTpCounts as $_dn => $_cnt): ?>
                                                <tr class="district-chip" data-district="<?php echo htmlspecialchars($_dn, ENT_QUOTES); ?>" style="cursor:pointer;">
                                                    <td><b style="color:#1f2937;"><?php echo htmlspecialchars($_dn); ?></b></td>
                                                    <td style="text-align:right;color:#059669;font-weight:600;"><?php echo htmlspecialchars((string)($_cnt['active']), ENT_QUOTES, 'ISO-8859-1', false); ?></td>
                                                    <td style="text-align:right;color:#dc2626;font-weight:600;"><?php echo htmlspecialchars((string)($_cnt['inactive']), ENT_QUOTES, 'ISO-8859-1', false); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr style="border-top:2px solid #e2e8f0;">
                                                    <td><b style="color:#1f2937;">Total</b></td>
                                                    <td style="text-align:right;color:#059669;font-weight:700;"><?php echo $_totalActive; ?></td>
                                                    <td style="text-align:right;color:#dc2626;font-weight:700;"><?php echo $_totalInactive; ?></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <div id="teamDistrictPagination" class="mis-pagination" style="padding:8px 18px 16px;"></div>
                                </div>
                                <script>
                                (function () {
                                    var PAGE_SIZE = 6;
                                    var rows = Array.prototype.slice.call(document.querySelectorAll('#teamDistrictRows .district-chip'));
                                    var pager = document.getElementById('teamDistrictPagination');
                                    var totalPages = Math.ceil(rows.length / PAGE_SIZE);
                                    var currentPage = 1;

                                    function render() {
                                        rows.forEach(function (row, idx) {
                                            var page = Math.floor(idx / PAGE_SIZE) + 1;
                                            row.style.display = (page === currentPage) ? '' : 'none';
                                        });
                                        if (totalPages <= 1) { pager.innerHTML = ''; return; }
                                        var html = '';
                                        html += '<button type="button" class="btn btn-sm btn-outline-secondary" id="tdPrev"' + (currentPage === 1 ? ' disabled' : '') + '>Prev</button> ';
                                        for (var p = 1; p <= totalPages; p++) {
                                            html += '<button type="button" class="btn btn-sm ' + (p === currentPage ? 'btn-primary' : 'btn-outline-secondary') + ' td-page-btn" data-page="' + p + '">' + p + '</button> ';
                                        }
                                        html += '<button type="button" class="btn btn-sm btn-outline-secondary" id="tdNext"' + (currentPage === totalPages ? ' disabled' : '') + '>Next</button>';
                                        pager.innerHTML = html;

                                        var prevBtn = document.getElementById('tdPrev');
                                        if (prevBtn) prevBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage--; render(); } });
                                        var nextBtn = document.getElementById('tdNext');
                                        if (nextBtn) nextBtn.addEventListener('click', function () { if (currentPage < totalPages) { currentPage++; render(); } });
                                        pager.querySelectorAll('.td-page-btn').forEach(function (btn) {
                                            btn.addEventListener('click', function () { currentPage = parseInt(this.getAttribute('data-page'), 10); render(); });
                                        });
                                    }
                                    render();
                                })();
                                </script>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Vacant Firkas drill-down modal -->
                        <div class="modal fade" id="vacantFirkaModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h6 class="modal-title" style="font-weight:700;">
                                            Vacant Firkas —
                                            <?php if (count($districtNames) > 2): ?>
                                            <span id="vfDistrictShort"><?php echo htmlspecialchars(implode(', ', array_slice($districtNames, 0, 2))); ?>, <a href="#" id="vfReadMoreBtn" style="font-weight:600;">+<?php echo count($districtNames) - 2; ?> more</a></span>
                                            <span id="vfDistrictFull" style="display:none;"><?php echo htmlspecialchars($districtLabel); ?> <a href="#" id="vfShowLessBtn" style="font-weight:600;">Show less</a></span>
                                            <?php else: ?>
                                            <?php echo htmlspecialchars($districtLabel); ?>
                                            <?php endif; ?>
                                        </h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" style="padding:0;">
                                        <?php if (empty($vacantFirkas)): ?>
                                        <div class="empty-state">
                                            <div class="empty-icon-wrap"><i class="material-icons-outlined">check_circle</i></div>
                                            <h6>No Vacant Firkas</h6>
                                            <p>Every Firka in your district already has a TP.</p>
                                        </div>
                                        <?php else: ?>
                                        <?php if (count($districtNames) > 1): ?>
                                        <div style="padding:14px 20px 0;">
                                            <select id="vacantFirkaDistrictFilter" class="dash-search" style="max-width:220px;">
                                                <option value="all">All Districts</option>
                                                <?php foreach ($districtNames as $dn): ?>
                                                <option value="<?php echo htmlspecialchars($dn); ?>"><?php echo htmlspecialchars($dn); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php endif; ?>
                                        <table class="tp-table">
                                            <thead>
                                                <tr>
                                                    <th>District</th>
                                                    <th>Firka</th>
                                                    <th style="text-align:right;">Target Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody id="vacantFirkaTbody">
                                            <?php foreach ($vacantFirkas as $vf): ?>
                                                <tr data-district="<?php echo htmlspecialchars($vf['district_name']); ?>" data-amount="<?php echo (float)$vf['target_amount']; ?>">
                                                    <td><span class="mobile-text"><?php echo htmlspecialchars($vf['district_name']); ?></span></td>
                                                    <td><span class="tp-name"><?php echo htmlspecialchars($vf['firka_name']); ?></span></td>
                                                    <td style="text-align:right;">
                                                        <span class="sales-amount"><span class="sales-currency">&#8377;</span><?php echo inr_format($vf['target_amount'], 0); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($vacantFirkas)): ?>
                                    <div class="modal-footer" style="justify-content:space-between;">
                                        <span id="vacantFirkaCount" style="font-size:12.5px;color:#6b7280;"><?php echo count($vacantFirkas); ?> vacant Firka(s)</span>
                                        <span id="vacantFirkaTotal" style="font-weight:700;color:#ef4444;">&#8377;<?php echo inr_format($districtStats['target_unassigned_amount'], 0); ?> total</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <script>
                        (function () {
                            var vfReadMoreBtn = document.getElementById('vfReadMoreBtn');
                            var vfShowLessBtn = document.getElementById('vfShowLessBtn');
                            var vfDistrictShort = document.getElementById('vfDistrictShort');
                            var vfDistrictFull = document.getElementById('vfDistrictFull');
                            if (vfReadMoreBtn) {
                                vfReadMoreBtn.addEventListener('click', function (e) {
                                    e.preventDefault();
                                    vfDistrictShort.style.display = 'none';
                                    vfDistrictFull.style.display = '';
                                });
                            }
                            if (vfShowLessBtn) {
                                vfShowLessBtn.addEventListener('click', function (e) {
                                    e.preventDefault();
                                    vfDistrictFull.style.display = 'none';
                                    vfDistrictShort.style.display = '';
                                });
                            }

                            var vfFilter = document.getElementById('vacantFirkaDistrictFilter');
                            if (!vfFilter) return;
                            var vfRows = Array.prototype.slice.call(document.querySelectorAll('#vacantFirkaTbody tr'));
                            var vfCountEl = document.getElementById('vacantFirkaCount');
                            var vfTotalEl = document.getElementById('vacantFirkaTotal');
                            function moneyVf(n) { return '&#8377;' + Number(n || 0).toLocaleString('en-IN', {minimumFractionDigits: 0, maximumFractionDigits: 0}); }
                            vfFilter.addEventListener('change', function () {
                                var val = this.value;
                                var count = 0, total = 0;
                                vfRows.forEach(function (tr) {
                                    var match = (val === 'all' || tr.getAttribute('data-district') === val);
                                    tr.style.display = match ? '' : 'none';
                                    if (match) { count++; total += parseFloat(tr.getAttribute('data-amount')) || 0; }
                                });
                                if (vfCountEl) vfCountEl.textContent = count + ' vacant Firka(s)';
                                if (vfTotalEl) vfTotalEl.innerHTML = moneyVf(total) + ' total';
                            });
                        })();
                        </script>

                        <script>
                        (function () {
                            var allRows = <?php echo json_encode(array_map(function ($tp) {
                                return [
                                    'tp_code'   => $tp['tp_code'],
                                    'name'      => ucwords(strtolower($tp['name'])),
                                    'mobile'    => $tp['mobile'],
                                    'district'  => $tp['district'],
                                    'firka'     => $tp['firka'],
                                    'target'    => (float)$tp['target'],
                                    'other_split' => $tp['other_district_split'] ?? '',
                                    'is_active' => (int)$tp['is_active'],
                                ];
                            }, $tpRoster)); ?>;
                            var pageSize = 10;
                            var currentPage = 1;
                            var currentStatus = 'all';
                            var currentDistrict = 'all';
                            var searchTerm = '';

                            // "+N more" so a TP covering a dozen Firkas doesn't blow out the row.
                            function shortList(str) {
                                if (!str) return '—';
                                var parts = str.split(', ');
                                if (parts.length <= 2) return esc(str);
                                return esc(parts.slice(0, 2).join(', ')) + ' <span style="color:#9ca3af;">+' + (parts.length - 2) + ' more</span>';
                            }
                            function money(n) { return '&#8377;' + Number(n || 0).toLocaleString('en-IN', {minimumFractionDigits: 0, maximumFractionDigits: 0}); }

                            var escDiv = document.createElement('div');
                            function esc(s) { escDiv.textContent = (s == null ? '' : s); return escDiv.innerHTML; }
                            function initialsOf(name) {
                                var words = name.trim().split(/\s+/);
                                if (words.length > 1) return (words[0][0] + words[words.length - 1][0]).toUpperCase();
                                return name.trim().substring(0, 1).toUpperCase();
                            }

                            function filteredRows() {
                                return allRows.filter(function (r) {
                                    if (currentStatus === 'active' && !r.is_active) return false;
                                    if (currentStatus === 'inactive' && r.is_active) return false;
                                    if (currentDistrict !== 'all' && (r.district || '').toLowerCase() !== currentDistrict.toLowerCase()) return false;
                                    if (searchTerm && (r.name + ' ' + r.tp_code + ' ' + r.mobile + ' ' + (r.district || '') + ' ' + (r.firka || '')).toLowerCase().indexOf(searchTerm) === -1) return false;
                                    return true;
                                });
                            }

                            function render() {
                                var rows = filteredRows();
                                var tbody = document.getElementById('tpRosterTbody');
                                var pager = document.getElementById('tpRosterPagination');
                                if (!rows.length) {
                                    tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><div class="empty-icon-wrap"><i class="material-icons-outlined">person_search</i></div><h6>No Territory Partners</h6><p>None found for this filter.</p></div></td></tr>';
                                    pager.innerHTML = '';
                                    return;
                                }
                                var totalPages = Math.ceil(rows.length / pageSize);
                                if (currentPage > totalPages) currentPage = totalPages;
                                var start = (currentPage - 1) * pageSize;
                                var pageRows = rows.slice(start, start + pageSize);
                                var html = '';
                                pageRows.forEach(function (r, i) {
                                    var statusHtml = r.is_active
                                        ? '<span class="status-dot status-active">Active</span>'
                                        : '<span class="status-dot status-inactive">Inactive</span>';
                                    html += '<tr>' +
                                        '<td style="text-align:center;"><span class="serial-num">' + (start + i + 1) + '</span></td>' +
                                        '<td><div class="tp-info"><div class="tp-avatar">' + esc(initialsOf(r.name)) + '</div><div class="tp-info-text"><span class="tp-name">' + esc(r.name) + '</span><span class="tp-code-pill">' + esc(r.tp_code) + '</span></div></div></td>' +
                                        '<td><div class="mobile-cell"><i class="material-icons-outlined">phone_iphone</i><span class="mobile-text">' + esc(r.mobile) + '</span></div></td>' +
                                        '<td><span class="mobile-text">' + esc(r.district || '—') + '</span></td>' +
                                        '<td><span class="mobile-text">' + shortList(r.firka) + '</span></td>' +
                                        '<td style="text-align:right;"><span class="sales-amount">' + money(r.target) + '</span>' +
                                            (r.other_split ? '<div style="font-size:10px;color:#9ca3af;margin-top:2px;">+ ' + esc(r.other_split) + '</div>' : '') +
                                        '</td>' +
                                        '<td style="text-align:center;">' + statusHtml + '</td>' +
                                        '</tr>';
                                });
                                tbody.innerHTML = html;

                                var pHtml = '<span class="mis-pg-info">' + (start + 1) + '–' + Math.min(start + pageSize, rows.length) + ' of ' + rows.length + '</span>';
                                pHtml += '<button type="button" data-pg="prev"' + (currentPage === 1 ? ' disabled' : '') + '>Prev</button>';
                                var winSize = 5;
                                var winStart = Math.max(1, currentPage - Math.floor(winSize / 2));
                                var winEnd = Math.min(totalPages, winStart + winSize - 1);
                                winStart = Math.max(1, winEnd - winSize + 1);
                                for (var p = winStart; p <= winEnd; p++) {
                                    pHtml += '<button type="button" data-pg="' + p + '" class="' + (p === currentPage ? 'active' : '') + '">' + p + '</button>';
                                }
                                pHtml += '<button type="button" data-pg="next"' + (currentPage === totalPages ? ' disabled' : '') + '>Next</button>';
                                pager.innerHTML = pHtml;
                            }

                            document.getElementById('tpRosterPagination').addEventListener('click', function (e) {
                                var btn = e.target.closest('button[data-pg]');
                                if (!btn) return;
                                var pg = btn.getAttribute('data-pg');
                                var totalPages = Math.ceil(filteredRows().length / pageSize);
                                if (pg === 'prev') currentPage = Math.max(1, currentPage - 1);
                                else if (pg === 'next') currentPage = Math.min(totalPages, currentPage + 1);
                                else currentPage = parseInt(pg, 10);
                                render();
                            });

                            document.getElementById('tpRosterTabs').addEventListener('click', function (e) {
                                var btn = e.target.closest('.status-filter-btn');
                                if (!btn) return;
                                document.querySelectorAll('#tpRosterTabs .status-filter-btn').forEach(function (b) { b.classList.remove('active'); });
                                btn.classList.add('active');
                                currentStatus = btn.getAttribute('data-status');
                                currentPage = 1;
                                render();
                            });

                            document.getElementById('tpSearch').addEventListener('input', function () {
                                searchTerm = this.value.toLowerCase().trim();
                                currentPage = 1;
                                render();
                            });

                            var tpRosterDistrictFilter = document.getElementById('tpRosterDistrictFilter');
                            if (tpRosterDistrictFilter) {
                                tpRosterDistrictFilter.addEventListener('change', function () {
                                    currentDistrict = this.value;
                                    currentPage = 1;
                                    render();
                                });
                            }

                            // "Team Districts" chips (above) drive this same table — clicking one
                            // sets the dropdown, filters the roster, and scrolls it into view.
                            document.querySelectorAll('.district-chip').forEach(function (chip) {
                                chip.addEventListener('click', function () {
                                    var district = this.getAttribute('data-district');
                                    currentDistrict = district;
                                    currentPage = 1;
                                    if (tpRosterDistrictFilter) { tpRosterDistrictFilter.value = district; }
                                    render();
                                    document.getElementById('tpRosterTbody').scrollIntoView({ behavior: 'smooth', block: 'center' });
                                });
                            });

                            render();
                        })();
                        </script>

                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>