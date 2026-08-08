<?php
/**
 * AJAX: per-member breakdown behind one TP's Team Points total, for the
 * click-to-drill-down popup on reward_points_tp.php.
 */
require_once("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('reward_points');
require_once("config.php");
require_once("include/TpRewardPointsData.php");

header('Content-Type: application/json');

function validateDate_ttpb(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}

$referrer_id = (int)($_GET['referrer_id'] ?? 0);
if ($referrer_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid referrer.']);
    exit;
}

$daysInMonth = (int) date('t');
$defaultFrom = date('Y-m-01');
$defaultTo   = date("Y-m-{$daysInMonth}");
$from_date   = validateDate_ttpb($_GET['frdate'] ?? null, $defaultFrom);
$to_date     = validateDate_ttpb($_GET['todate'] ?? null, $defaultTo);
if (strtotime($from_date) > strtotime($to_date)) {
    [$from_date, $to_date] = [$to_date, $from_date];
}

try {
    $members = getTpTeamPointsBreakdown($referrer_id, $from_date, $to_date);
    echo json_encode(['success' => true, 'members' => $members, 'from_date' => $from_date, 'to_date' => $to_date]);
} catch (\Throwable $e) {
    error_log('[get-tp-team-points-breakdown] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load team points breakdown.']);
}
