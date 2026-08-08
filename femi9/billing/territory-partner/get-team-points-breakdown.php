<?php
/**
 * AJAX: per-member breakdown behind THIS TP's own Team Points total, for the
 * click-to-drill-down popup on reward-points.php. Always scoped to the
 * logged-in TP — no referrer_id parameter, since a TP can only ever see
 * their own team's contribution.
 */
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../company/include/TpRewardPointsData.php';

header('Content-Type: application/json');

function validateDate_gtpb(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}

$daysInMonth = (int) date('t');
$defaultFrom = date('Y-m-01');
$defaultTo   = date("Y-m-{$daysInMonth}");
$from_date   = validateDate_gtpb($_GET['frdate'] ?? null, $defaultFrom);
$to_date     = validateDate_gtpb($_GET['todate'] ?? null, $defaultTo);
if (strtotime($from_date) > strtotime($to_date)) {
    [$from_date, $to_date] = [$to_date, $from_date];
}

try {
    $members = getTpTeamPointsBreakdown((int)$Login_user_IDvl, $from_date, $to_date);
    echo json_encode(['success' => true, 'members' => $members, 'from_date' => $from_date, 'to_date' => $to_date]);
} catch (\Throwable $e) {
    error_log('[get-team-points-breakdown] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load team points breakdown.']);
}
