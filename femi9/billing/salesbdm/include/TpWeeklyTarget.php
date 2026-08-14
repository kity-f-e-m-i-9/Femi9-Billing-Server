<?php
// Read-only adaptation of the weekly cumulative-target concept already used
// for TP bonus/deactivation decisions (see company/tp-bonus-points-calculator.php
// — getWeekRanges()/getTpAdvancePaymentInRange()/the 25%/50%/75%/100% weekly
// thresholds). This file only reuses that same math to show a TP's current
// on-track/behind status for display — no bonus award or deactivation logic.

function getTpWeekRangesForBdm(string $monthYear): array {
    $year    = (int)substr($monthYear, 0, 4);
    $month   = (int)substr($monthYear, 5, 2);
    $lastDay = (int)date('t', mktime(0, 0, 0, $month, 1, $year));

    return [
        'week1' => [
            'start' => sprintf('%04d-%02d-01', $year, $month),
            'end'   => sprintf('%04d-%02d-07', $year, $month),
            'label' => 'Week 1 (Day 1-7)',
        ],
        'week2' => [
            'start' => sprintf('%04d-%02d-08', $year, $month),
            'end'   => sprintf('%04d-%02d-14', $year, $month),
            'label' => 'Week 2 (Day 8-14)',
        ],
        'week3' => [
            'start' => sprintf('%04d-%02d-15', $year, $month),
            'end'   => sprintf('%04d-%02d-21', $year, $month),
            'label' => 'Week 3 (Day 15-21)',
        ],
        'week4' => [
            'start' => sprintf('%04d-%02d-22', $year, $month),
            'end'   => sprintf('%04d-%02d-%02d', $year, $month, $lastDay),
            'label' => sprintf('Week 4 (Day 22-%d)', $lastDay),
        ],
    ];
}

function getTpAdvancePaymentInRangeForBdm(mysqli $dbConn, string $tpCode, string $startDate, string $endDate): float {
    $stmt = $dbConn->prepare("
        SELECT COALESCE(SUM(tap.amount), 0) AS total
        FROM tp_advance_payments tap
        INNER JOIN territory_partners tp ON tp.id = tap.territory_partner_id
        WHERE tp.tp_id = ?
          AND tap.payment_date >= ?
          AND tap.payment_date <= ?
    ");
    if (!$stmt) return 0.0;
    $stmt->bind_param("sss", $tpCode, $startDate, $endDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($row['total'] ?? 0.0);
}

// Where the TP stands against a given month's weekly cumulative thresholds
// — same 25/50/75/100% ladder used by the bonus calculator. $monthYear
// (Y-m) lets the caller ask about whatever month the dashboard's own date
// filter is currently showing, not just the current calendar month.
// Includes a full week-by-week breakdown (amount/cumulative/required/status
// for week1-4) so the UI can show exactly how each week landed.
//
// A month that hasn't started yet has no payment data to show at all —
// returns ['is_future' => true] instead of a zeroed-out breakdown, so the
// UI can say "hasn't started yet" rather than implying the TP already
// missed a target for a period that doesn't exist yet.
function getTpWeeklyCompletion(mysqli $dbConn, string $tpCode, float $targetAmount, ?string $monthYear = null): array {
    $currentMonthYear = date('Y-m');
    $monthYear = $monthYear ?: $currentMonthYear;
    $monthLabel = date('F Y', strtotime($monthYear . '-01'));

    if ($monthYear > $currentMonthYear) {
        return [
            'is_future'   => true,
            'month_label' => $monthLabel,
        ];
    }

    // A past (already-completed) month has no "today" cutoff — every week
    // is fully elapsed, so cap at that month's last day instead of today's
    // date (which would incorrectly fall outside a past month's range).
    $today = ($monthYear === $currentMonthYear) ? date('Y-m-d') : date('Y-m-t', strtotime($monthYear . '-01'));

    $ranges    = getTpWeekRangesForBdm($monthYear);
    $thresholdPct = ['week1' => 0.25, 'week2' => 0.50, 'week3' => 0.75, 'week4' => 1.00];

    $currentWeekKey = 'week4';
    foreach ($ranges as $key => $r) {
        if ($today >= $r['start'] && $today <= $r['end']) { $currentWeekKey = $key; break; }
    }

    $weeks = [];
    $cumulative = 0.0;
    foreach ($ranges as $key => $r) {
        // Future weeks (relative to today) haven't happened yet — don't sum
        // payment data past today, so a week that hasn't started shows 0/0
        // rather than borrowing from a date range that's still in the future.
        $rangeEnd = $today < $r['end'] ? $today : $r['end'];
        $amount = ($today >= $r['start'])
            ? getTpAdvancePaymentInRangeForBdm($dbConn, $tpCode, $r['start'], $rangeEnd)
            : 0.0;
        $cumulative += $amount;
        $required = $targetAmount * $thresholdPct[$key];
        $weeks[$key] = [
            'label'      => $r['label'],
            'start'      => $r['start'],
            'end'        => $r['end'],
            'amount'     => $amount,
            'cumulative' => $cumulative,
            'required'   => $required,
            'is_current' => $key === $currentWeekKey,
            'has_started'=> $today >= $r['start'],
            'pass'       => $targetAmount <= 0 || $cumulative >= $required,
        ];
    }

    $requiredSoFar = $targetAmount * $thresholdPct[$currentWeekKey];
    $paidSoFar     = $weeks[$currentWeekKey]['cumulative'];
    $pctOfTarget   = $targetAmount > 0 ? round(($paidSoFar / $targetAmount) * 100, 1) : 0;
    $onTrack       = $targetAmount <= 0 || $paidSoFar >= $requiredSoFar;

    return [
        'is_future'       => false,
        'month_label'     => $monthLabel,
        'week_label'      => $ranges[$currentWeekKey]['label'],
        'paid_so_far'     => $paidSoFar,
        'required_so_far' => $requiredSoFar,
        'pct_of_target'   => $pctOfTarget,
        'on_track'        => $onTrack,
        'weeks'           => $weeks,
    ];
}
