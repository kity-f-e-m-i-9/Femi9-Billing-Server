<?php
// Read-only adaptation of the weekly cumulative-target concept already used
// for TP bonus/deactivation decisions (see company/tp-bonus-points-calculator.php
// — getWeekRanges()/the 25%/50%/75%/100% weekly thresholds). This file only
// reuses that same math to show a TP's current on-track/behind status for
// display — no bonus award or deactivation logic.
//
// Unlike the bonus calculator (which compares the target against ALL cash
// the TP paid via tp_advance_payments, regardless of what it was for), this
// deliberately compares against the TP's actual NAPKIN-category downstream
// sales only — target_amount itself is a Napkin-only figure (Lumi Baby
// Diaper has its own separate diaper_target_amount, unused here), so mixing
// in cash that was really paid for Diaper stock would overstate progress.

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

// Napkin-only downstream sales for one TP in a date range — same formula
// (and same COALESCE(p.category,'') != 'diaper' match, since historic
// Napkin products carry NULL category rather than the literal 'napkin')
// as dashboard.php's own $overall_achieved, just scoped to a single TP and
// date range instead of all of a BDM's TPs over the filter period.
function getTpNapkinAchievedInRangeForBdm(mysqli $dbConn, int $tpDbId, string $startDate, string $endDate): float {
    $custStmt = $dbConn->prepare("
        SELECT COALESCE(SUM(ii.total), 0) AS total
        FROM invoice_items ii
        JOIN invoice i ON i.inv_id = ii.inv_id
        JOIN products p ON p.id = ii.pr_id
        WHERE i.user_type = 'territory_partner' AND i.sub_total > 0
          AND i.date >= ? AND i.date <= ? AND i.user_id = ?
          AND COALESCE(p.category, '') != 'diaper'
    ");
    $custTotal = 0.0;
    if ($custStmt) {
        $custStmt->bind_param('ssi', $startDate, $endDate, $tpDbId);
        $custStmt->execute();
        $custTotal = (float)($custStmt->get_result()->fetch_assoc()['total'] ?? 0.0);
        $custStmt->close();
    }

    $shopStmt = $dbConn->prepare("
        SELECT COALESCE(SUM(uii.total), 0) AS total
        FROM user_invoice_items uii
        JOIN user_invoice ui ON ui.inv_id = uii.inv_id
        JOIN products p ON p.id = uii.pr_id
        WHERE ui.from_user_type = 'territory_partner' AND ui.sub_total > 0
          AND ui.date >= ? AND ui.date <= ? AND ui.from_user_id = ?
          AND COALESCE(p.category, '') != 'diaper'
    ");
    $shopTotal = 0.0;
    if ($shopStmt) {
        $shopStmt->bind_param('ssi', $startDate, $endDate, $tpDbId);
        $shopStmt->execute();
        $shopTotal = (float)($shopStmt->get_result()->fetch_assoc()['total'] ?? 0.0);
        $shopStmt->close();
    }

    return $custTotal + $shopTotal;
}

// Earliest date within a range that a TP made a Napkin-category sale — used
// to rank TPs by how promptly they sold/paid within the current week (paid
// right at week start = best rank, 2-3 days in = medium, no sale yet this
// week = unranked/last). Same category filter and channels (customer +
// shop) as getTpNapkinAchievedInRangeForBdm, just MIN(date) instead of SUM.
function getTpFirstNapkinSaleDateInRange(mysqli $dbConn, int $tpDbId, string $startDate, string $endDate): ?string {
    $custStmt = $dbConn->prepare("
        SELECT MIN(i.date) AS first_date
        FROM invoice_items ii
        JOIN invoice i ON i.inv_id = ii.inv_id
        JOIN products p ON p.id = ii.pr_id
        WHERE i.user_type = 'territory_partner' AND i.sub_total > 0
          AND i.date >= ? AND i.date <= ? AND i.user_id = ?
          AND COALESCE(p.category, '') != 'diaper'
    ");
    $custDate = null;
    if ($custStmt) {
        $custStmt->bind_param('ssi', $startDate, $endDate, $tpDbId);
        $custStmt->execute();
        $custDate = $custStmt->get_result()->fetch_assoc()['first_date'] ?? null;
        $custStmt->close();
    }

    $shopStmt = $dbConn->prepare("
        SELECT MIN(ui.date) AS first_date
        FROM user_invoice_items uii
        JOIN user_invoice ui ON ui.inv_id = uii.inv_id
        JOIN products p ON p.id = uii.pr_id
        WHERE ui.from_user_type = 'territory_partner' AND ui.sub_total > 0
          AND ui.date >= ? AND ui.date <= ? AND ui.from_user_id = ?
          AND COALESCE(p.category, '') != 'diaper'
    ");
    $shopDate = null;
    if ($shopStmt) {
        $shopStmt->bind_param('ssi', $startDate, $endDate, $tpDbId);
        $shopStmt->execute();
        $shopDate = $shopStmt->get_result()->fetch_assoc()['first_date'] ?? null;
        $shopStmt->close();
    }

    $dates = array_filter([$custDate, $shopDate]);
    if (empty($dates)) return null;
    sort($dates);
    return $dates[0];
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
function getTpWeeklyCompletion(mysqli $dbConn, int $tpDbId, float $targetAmount, ?string $monthYear = null): array {
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
            ? getTpNapkinAchievedInRangeForBdm($dbConn, $tpDbId, $r['start'], $rangeEnd)
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

    // Ranking input — how many days into the CURRENT week this TP made
    // their first Napkin sale (0 = the very first day of the week). Null
    // means no Napkin sale yet this week — ranked last, below every TP who
    // has sold something, regardless of amount.
    $currentWeekRange = $ranges[$currentWeekKey];
    $firstSaleDate = getTpFirstNapkinSaleDateInRange($dbConn, $tpDbId, $currentWeekRange['start'], $today);
    $rankDayOffset = $firstSaleDate !== null
        ? (int)((strtotime($firstSaleDate) - strtotime($currentWeekRange['start'])) / 86400)
        : null;
    $rankTier = $rankDayOffset === null ? 'none' : ($rankDayOffset <= 1 ? 'top' : ($rankDayOffset <= 3 ? 'medium' : 'late'));

    return [
        'is_future'        => false,
        'month_label'      => $monthLabel,
        'week_label'       => $ranges[$currentWeekKey]['label'],
        'paid_so_far'      => $paidSoFar,
        'required_so_far'  => $requiredSoFar,
        'pct_of_target'    => $pctOfTarget,
        'on_track'         => $onTrack,
        'weeks'            => $weeks,
        'first_sale_date'  => $firstSaleDate,
        'rank_day_offset'  => $rankDayOffset,
        'rank_tier'        => $rankTier,
    ];
}
