<?php
// Read-only adaptation of the weekly cumulative-target concept already used
// for TP bonus/deactivation decisions (see company/tp-bonus-points-calculator.php
// — getWeekRanges()/the 25%/50%/75%/100% weekly thresholds) — Pass/Fail for
// each started week is exactly that cumulative ladder: Week 1 passes once
// the TP's running total this month reaches >=25% of target, Week 2 at
// >=50%, Week 3 at >=75%, Week 4 at >=100%. A payment made in an earlier
// week counts toward every later week's cumulative total too, so paying
// the full month's target in Week 1 alone passes every remaining week
// automatically — there's no separate per-week "own slice" requirement, no
// bonus award or deactivation logic lives here, this file is display-only.
//
// On Track/Behind, the weekly Pass/Fail breakdown, and the promptness rank
// are all driven by how much the TP actually PAID toward their Napkin
// advance wallet each week — not by downstream sales. "Napkin sold this
// week" is still shown alongside for context, but it's informational only
// and never feeds the pass/fail math (a TP could sell from stock they
// already hold without paying anything new that week, which shouldn't read
// as "on track").
//
// Weeks 1-3 are always exactly 7-day blocks — Week 1 (1-7), Week 2 (8-14),
// Week 3 (15-21). Week 4 runs from day 22 all the way to the month's actual
// last day (28/29/30/31, whatever that month has) — every day of the month
// belongs to some week, so there's no "spillover" into the next month.
//
// Everything here is batched across a whole set of TPs at once (a handful
// of queries total, not per TP) — the Filled Firkas modal calls this for an
// entire BDM's team in one go, and a per-TP query fan-out there was the
// actual cause of the modal loading slowly for BDMs with a large team.

function getTpWeekRangesForBdm(string $monthYear): array {
    $year  = (int)substr($monthYear, 0, 4);
    $month = (int)substr($monthYear, 5, 2);
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
            'label' => 'Week 4 (Day 22-' . $lastDay . ')',
        ],
    ];
}

// Where a whole set of TPs stand against a given month's weekly cumulative
// thresholds — same 25/50/75/100% ladder used by the bonus calculator.
// $monthYear (Y-m) lets the caller ask about whatever month the dashboard's
// own date filter is currently showing, not just the current calendar month.
//
// @param int[] $tpDbIds
// @param array<int,float> $targetAmountsByTp territory_partners.id => Napkin target
// @return array<int,array> keyed by territory_partners.id, each shaped like:
//   is_future=true (month hasn't started) — only 'is_future','month_label'
//   no_target=true (target<=0 — no Firka assigned, or no target set) — only
//     'is_future','no_target','month_label'
//   otherwise the full breakdown: paid_so_far/required_so_far/pct_of_target/
//     on_track/weeks (week1-4 each with label/start/end/amount/sold/
//     cumulative/required/is_current/has_started/pass)/first_payment_date/
//     rank_day_offset/rank_tier
function getTpWeeklyCompletionBatch(mysqli $dbConn, array $tpDbIds, array $targetAmountsByTp, ?string $monthYear = null): array {
    $tpDbIds = array_values(array_unique(array_map('intval', $tpDbIds)));
    $result = [];
    if (empty($tpDbIds)) return $result;

    $currentMonthYear = date('Y-m');
    $monthYear = $monthYear ?: $currentMonthYear;
    $monthLabel = date('F Y', strtotime($monthYear . '-01'));

    if ($monthYear > $currentMonthYear) {
        foreach ($tpDbIds as $id) {
            $result[$id] = ['is_future' => true, 'month_label' => $monthLabel];
        }
        return $result;
    }

    // Week 4 now runs through the month's actual last day, so "today" is
    // capped there instead of at a fixed day 28 — every day of the month
    // belongs to some week, nothing spills into the next month anymore.
    $ranges = getTpWeekRangesForBdm($monthYear);
    $monthEndForWeeks = $ranges['week4']['end'];
    $today = ($monthYear === $currentMonthYear) ? date('Y-m-d') : $monthEndForWeeks;
    if ($today > $monthEndForWeeks) $today = $monthEndForWeeks;

    $thresholdPct = ['week1' => 0.25, 'week2' => 0.50, 'week3' => 0.75, 'week4' => 1.00];

    $currentWeekKey = 'week4';
    foreach ($ranges as $key => $r) {
        if ($today >= $r['start'] && $today <= $r['end']) { $currentWeekKey = $key; break; }
    }

    $idList = implode(',', $tpDbIds);
    $overallStart = $ranges['week1']['start'];
    $overallEnd   = $today;

    // Batch 1 — every Napkin advance payment this month (day 1..today,
    // capped at 28) for every TP in one query, bucketed into weeks in PHP
    // below instead of one query per week per TP.
    $paidByTp = [];
    if ($overallEnd >= $overallStart) {
        $stmt = $dbConn->prepare("
            SELECT territory_partner_id, payment_date, amount
            FROM tp_advance_payments
            WHERE territory_partner_id IN ($idList) AND product_type = 'napkin' AND deleted_at IS NULL
              AND payment_date >= ? AND payment_date <= ?
        ");
        $stmt->bind_param('ss', $overallStart, $overallEnd);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $paidByTp[(int)$row['territory_partner_id']][] = ['date' => $row['payment_date'], 'amount' => (float)$row['amount']];
        }
        $stmt->close();
    }

    // Batch 2 — Napkin sold (customer + shop channels), grouped by TP+date
    // so weekly bucketing happens in PHP, same as the paid-amount batch.
    // Informational only ("Napkin sold this week"), never feeds pass/fail.
    $soldByTp = [];
    if ($overallEnd >= $overallStart) {
        $custStmt = $dbConn->prepare("
            SELECT i.user_id AS tp_id, i.date, COALESCE(SUM(ii.total), 0) AS total
            FROM invoice_items ii
            JOIN invoice i ON i.inv_id = ii.inv_id
            JOIN products p ON p.id = ii.pr_id
            WHERE i.user_type = 'territory_partner' AND i.sub_total > 0
              AND i.user_id IN ($idList) AND i.date >= ? AND i.date <= ?
              AND COALESCE(p.category, '') != 'diaper'
            GROUP BY i.user_id, i.date
        ");
        $custStmt->bind_param('ss', $overallStart, $overallEnd);
        $custStmt->execute();
        $custRes = $custStmt->get_result();
        while ($row = $custRes->fetch_assoc()) {
            $soldByTp[(int)$row['tp_id']][] = ['date' => $row['date'], 'amount' => (float)$row['total']];
        }
        $custStmt->close();

        $shopStmt = $dbConn->prepare("
            SELECT ui.from_user_id AS tp_id, ui.date, COALESCE(SUM(uii.total), 0) AS total
            FROM user_invoice_items uii
            JOIN user_invoice ui ON ui.inv_id = uii.inv_id
            JOIN products p ON p.id = uii.pr_id
            WHERE ui.from_user_type = 'territory_partner' AND ui.sub_total > 0
              AND ui.from_user_id IN ($idList) AND ui.date >= ? AND ui.date <= ?
              AND COALESCE(p.category, '') != 'diaper'
            GROUP BY ui.from_user_id, ui.date
        ");
        $shopStmt->bind_param('ss', $overallStart, $overallEnd);
        $shopStmt->execute();
        $shopRes = $shopStmt->get_result();
        while ($row = $shopRes->fetch_assoc()) {
            $soldByTp[(int)$row['tp_id']][] = ['date' => $row['date'], 'amount' => (float)$row['total']];
        }
        $shopStmt->close();
    }

    // ── Assemble each TP's result purely from the batched data above ──────
    foreach ($tpDbIds as $tpId) {
        $targetAmount = (float)($targetAmountsByTp[$tpId] ?? 0);

        // No Firka assigned (or an assigned Firka with no target_amount set)
        // — there's nothing to be "on track" against, so this must never
        // render as a green On Track badge. Distinct from is_future/on_track
        // so the UI can show a neutral "No Target" state instead of a false
        // positive.
        if ($targetAmount <= 0) {
            $result[$tpId] = ['is_future' => false, 'no_target' => true, 'month_label' => $monthLabel];
            continue;
        }

        $tpPaidRows = $paidByTp[$tpId] ?? [];
        $tpSoldRows = $soldByTp[$tpId] ?? [];

        // Pass/fail is the cumulative 25/50/75/100% ladder — see the
        // file-level comment above. $weeklySlice (target/4) is kept only as
        // a display figure now (how much this specific week added), not
        // part of the pass decision.
        $weeklySlice = $targetAmount / 4;

        $weeks = [];
        $cumulative = 0.0;
        foreach ($ranges as $key => $r) {
            $hasStarted = $today >= $r['start'];
            $rangeEnd = $today < $r['end'] ? $today : $r['end'];

            $paid = 0.0;
            $sold = 0.0;
            if ($hasStarted) {
                foreach ($tpPaidRows as $row) {
                    if ($row['date'] >= $r['start'] && $row['date'] <= $rangeEnd) $paid += $row['amount'];
                }
                foreach ($tpSoldRows as $row) {
                    if ($row['date'] >= $r['start'] && $row['date'] <= $rangeEnd) $sold += $row['amount'];
                }
            }

            $cumulative += $paid;
            $required = $targetAmount * $thresholdPct[$key];
            $weeks[$key] = [
                'label'      => $r['label'],
                'start'      => $r['start'],
                'end'        => $r['end'],
                'amount'     => $paid,
                'sold'       => $sold,
                'cumulative' => $cumulative,
                'required'   => $required,
                'weekly_slice' => $weeklySlice,
                'is_current' => $key === $currentWeekKey,
                'has_started'=> $hasStarted,
                'pass'       => $cumulative >= $required,
            ];
        }

        $requiredSoFar = $targetAmount * $thresholdPct[$currentWeekKey];
        $paidSoFar     = $weeks[$currentWeekKey]['cumulative'];
        $pctOfTarget   = round(($paidSoFar / $targetAmount) * 100, 1);
        // On Track only if every week that has already started meets its
        // own cumulative threshold above — since the cumulative total only
        // grows, a week that once failed only becomes On Track again once a
        // later payment pushes the running total past that week's own mark.
        $onTrack = true;
        foreach ($ranges as $key => $r) {
            if (!$weeks[$key]['has_started']) break;
            if (!$weeks[$key]['pass']) { $onTrack = false; }
            if ($key === $currentWeekKey) break;
        }

        // Ranking input — how many days into the CURRENT week this TP made
        // their first Napkin advance payment (0 = the very first day of the
        // week). Null means no payment yet this week — ranked last, below
        // every TP who has paid something, regardless of amount.
        $currentWeekRange = $ranges[$currentWeekKey];
        $firstPaymentDate = null;
        foreach ($tpPaidRows as $row) {
            if ($row['date'] >= $currentWeekRange['start'] && $row['date'] <= $today) {
                if ($firstPaymentDate === null || $row['date'] < $firstPaymentDate) $firstPaymentDate = $row['date'];
            }
        }
        $rankDayOffset = $firstPaymentDate !== null
            ? (int)((strtotime($firstPaymentDate) - strtotime($currentWeekRange['start'])) / 86400)
            : null;
        $rankTier = $rankDayOffset === null ? 'none' : ($rankDayOffset <= 1 ? 'top' : ($rankDayOffset <= 3 ? 'medium' : 'late'));

        $result[$tpId] = [
            'is_future'          => false,
            'no_target'          => false,
            'month_label'        => $monthLabel,
            'week_label'         => $ranges[$currentWeekKey]['label'],
            'paid_so_far'        => $paidSoFar,
            'required_so_far'    => $requiredSoFar,
            'pct_of_target'      => $pctOfTarget,
            'on_track'           => $onTrack,
            'weeks'              => $weeks,
            'first_payment_date' => $firstPaymentDate,
            'rank_day_offset'    => $rankDayOffset,
            'rank_tier'          => $rankTier,
        ];
    }

    return $result;
}

// Single-TP convenience wrapper around getTpWeeklyCompletionBatch() — same
// batched query cost as calling the batch function directly (a handful of
// queries, not ~14), just for callers that only need one TP's result.
function getTpWeeklyCompletion(mysqli $dbConn, int $tpDbId, float $targetAmount, ?string $monthYear = null): array {
    $batch = getTpWeeklyCompletionBatch($dbConn, [$tpDbId], [$tpDbId => $targetAmount], $monthYear);
    return $batch[$tpDbId] ?? ['is_future' => false, 'no_target' => true, 'month_label' => date('F Y')];
}
