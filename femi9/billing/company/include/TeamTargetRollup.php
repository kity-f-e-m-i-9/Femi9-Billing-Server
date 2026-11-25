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
// Target source: each person's own assigned location(s) -> every Firka's
// target_amount under it (filled AND vacant — same total the District/
// Division Dashboard's "Total Target" card shows), NOT the old
// marketing_staff.monthly_target_amount field. An assignment at DISTRICT or
// DIVISION depth is used as-is (a DM assigned a specific Division only rolls
// up that division's Firkas, not the whole district's) — see
// getMsAssignedTargetScopeNodes()'s own doc comment for the full depth
// handling. A non-leaf (SM/ASM) normally has no location of their own
// assigned, so this naturally comes back 0 for them; the caller
// (msTeamViewSubtreeTargetSum() / my-team.php's own equivalent) still zeroes
// a manager's own raw target whenever they have a team below them and rolls
// up the children's sum instead, same convention as before — only the
// leaf's own number's *source* changed.
require_once __DIR__ . '/../../marketing/include/AssignedLocations.php';
require_once __DIR__ . '/../../marketing/include/MsDistrictScope.php';
require_once __DIR__ . '/../../shared/CpShopWalletCommission.php'; // ensureShopWalletCommissionSchema() — fulfilled_by_cp_id

// Self-migrating performance fix, 2026-11-13: every join below used to force
// `COLLATE utf8mb4_general_ci` onto BOTH sides of an inv_id comparison, to
// survive a DB reimport recreating a table under a different default
// collation than the rest of the schema (see the old comment this replaced).
// Wrapping an indexed column in COLLATE() makes it non-sargable — MySQL can
// no longer use the index, and has to full-scan tables this app now has at
// six figures of rows (user_invoice_items: 185k+). Measured live: one of
// these queries alone was taking 74s with the cast and 0.04s without it. The
// cast is only ever actually needed when the columns' collations have
// genuinely drifted apart, so check that once per request (cheap,
// information_schema-only) and only pay the COLLATE cost when it's real.
function _teamTargetCollationCast(mysqli $db_conn): string {
    static $cast = null;
    if ($cast !== null) { return $cast; }
    $row = $db_conn->query("
        SELECT COUNT(DISTINCT COLLATION_NAME) AS n
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND ((TABLE_NAME='user_invoice_items' AND COLUMN_NAME='inv_id')
            OR (TABLE_NAME='tp_orders' AND COLUMN_NAME='invoiced_inv_id')
            OR (TABLE_NAME='user_invoice' AND COLUMN_NAME='inv_id'))
    ")->fetch_assoc();
    $cast = ((int)($row['n'] ?? 2)) > 1 ? ' COLLATE utf8mb4_general_ci' : '';
    return $cast;
}

// CP-fulfilled and Company-direct DM Get Orders (company/ms-order-invoice-
// add.php, 2026-11-12/13) never get bridged into tp_orders at all — that
// bridge only ever fires when the order has a TP (marketing/order_action_
// get.php's $tp_id>0 guard) — so every "Achieved" query below that joins
// through tp_orders was silently excluding them: a DM's Get Order that a CP
// or Company fulfilled directly counted 0 toward Achieved even though real
// money moved. Fixed by adding this second, strictly-additive join path
// (keyed on user_invoice.source_ms_order_id instead of tp_orders), 2026-11-13.
// Never double-counts against the tp_orders path since an order can only
// ever go through one or the other.
function _teamTargetAchievedCpCompanySql(string $idList, string $fromEsc, string $toEsc, string $cast): string {
    return "
        SELECT x.ms_id, COALESCE(SUM(uii.total), 0) AS achieved
        FROM (
            SELECT DISTINCT o.ms_id, ui.inv_id
            FROM ms_orders o
            JOIN user_invoice ui ON ui.source_ms_order_id = o.order_id
            WHERE o.ms_id IN ($idList) AND o.new_order = 'yes'
              AND o.order_date BETWEEN '$fromEsc' AND '$toEsc'
        ) x
        JOIN user_invoice_items uii ON uii.inv_id$cast = x.inv_id$cast
        JOIN products p ON p.id = uii.pr_id
        WHERE COALESCE(p.category,'') != 'diaper'
        GROUP BY x.ms_id
    ";
}

function getRawTargetAchievedStats($db_conn, array $ids, string $fromDate, string $toDate): array {
    $stats = [];
    foreach ($ids as $id) { $stats[(int)$id] = ['target' => 0.0, 'achieved' => 0.0]; }
    if (empty($ids)) return $stats;

    $idList = implode(',', array_map('intval', $ids));

    $daysInMonth = (int)date('t', strtotime($fromDate));
    $daysInRange = (int)floor((strtotime($toDate) - strtotime($fromDate)) / 86400) + 1;
    if ($daysInRange < 1) { $daysInRange = 1; }

    // Target — each person's assigned location(s)' full Firka total
    // (filled + vacant), prorated to the range the same way the old
    // monthly-amount figure was.
    foreach ($ids as $id) {
        $id = (int)$id;
        $scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $id);
        if (empty($scopeNodes)) continue;
        $scopeIds = array_column($scopeNodes, 'id');
        $scopeStats = getMsNodeTargetAndFirkaStats($db_conn, $scopeIds);
        $monthly = (float)$scopeStats['district_total_target'];
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
    // See _teamTargetCollationCast() — the join below only pays for a
    // COLLATE cast when the schema genuinely needs one.
    $fromEsc = $db_conn->real_escape_string($fromDate);
    $toEsc   = $db_conn->real_escape_string($toDate);
    $cast    = _teamTargetCollationCast($db_conn);
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
        JOIN user_invoice_items uii ON uii.inv_id$cast = x.invoiced_inv_id$cast
        JOIN products p ON p.id = uii.pr_id
        WHERE COALESCE(p.category,'') != 'diaper'
        GROUP BY x.ms_id
    ");
    if ($resA) {
        while ($r = $resA->fetch_assoc()) {
            $stats[(int)$r['ms_id']]['achieved'] = (float)$r['achieved'];
        }
    }

    // Additive: CP-fulfilled and Company-direct Get Orders (see comment above).
    $resB = $db_conn->query(_teamTargetAchievedCpCompanySql($idList, $fromEsc, $toEsc, $cast));
    if ($resB) {
        while ($r = $resB->fetch_assoc()) {
            $stats[(int)$r['ms_id']]['achieved'] += (float)$r['achieved'];
        }
    }

    return $stats;
}

// Day-by-day version of getRawTargetAchievedStats()'s "Achieved" (Napkin-
// only, Get-Order-converted sales) — same join, grouped by the invoice's own
// date (ui.date) as well as ms_id, instead of summed into one period total.
// Used to find the exact date a person's cumulative Napkin sales first
// reached their Target Amount ("Target Completed Date" — SM-wise/DM-wise
// requirement #4), which needs the running total day by day, not just the
// period's grand total.
function getRawAchievedDailyByMsId($db_conn, array $ids, string $fromDate, string $toDate): array {
    $out = [];
    foreach ($ids as $id) { $out[(int)$id] = []; }
    if (empty($ids)) return $out;

    $idList = implode(',', array_map('intval', $ids));
    $fromEsc = $db_conn->real_escape_string($fromDate);
    $toEsc   = $db_conn->real_escape_string($toDate);
    $cast    = _teamTargetCollationCast($db_conn);
    $res = $db_conn->query("
        SELECT x.ms_id, ui.date AS inv_date, COALESCE(SUM(uii.total), 0) AS achieved
        FROM (
            SELECT DISTINCT o.ms_id, t.invoiced_inv_id
            FROM ms_orders o
            JOIN tp_orders t ON t.order_id = o.order_id
            WHERE o.ms_id IN ($idList) AND o.new_order = 'yes'
              AND o.order_date BETWEEN '$fromEsc' AND '$toEsc'
              AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''
        ) x
        JOIN user_invoice ui ON ui.inv_id$cast = x.invoiced_inv_id$cast
        JOIN user_invoice_items uii ON uii.inv_id$cast = x.invoiced_inv_id$cast
        JOIN products p ON p.id = uii.pr_id
        WHERE COALESCE(p.category,'') != 'diaper'
        GROUP BY x.ms_id, ui.date
        ORDER BY ui.date ASC
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $id = (int)$r['ms_id'];
            if (!isset($out[$id])) { continue; }
            $out[$id][$r['inv_date']] = (float)$r['achieved'];
        }
    }

    // Additive: CP-fulfilled and Company-direct Get Orders (see
    // _teamTargetAchievedCpCompanySql()'s comment above) — += since a
    // CP/Company invoice can land on the same date an existing TP invoice
    // already has an entry for.
    $resCpCo = $db_conn->query("
        SELECT x.ms_id, ui.date AS inv_date, COALESCE(SUM(uii.total), 0) AS achieved
        FROM (
            SELECT DISTINCT o.ms_id, ui.inv_id
            FROM ms_orders o
            JOIN user_invoice ui ON ui.source_ms_order_id = o.order_id
            WHERE o.ms_id IN ($idList) AND o.new_order = 'yes'
              AND o.order_date BETWEEN '$fromEsc' AND '$toEsc'
        ) x
        JOIN user_invoice ui ON ui.inv_id$cast = x.inv_id$cast
        JOIN user_invoice_items uii ON uii.inv_id$cast = x.inv_id$cast
        JOIN products p ON p.id = uii.pr_id
        WHERE COALESCE(p.category,'') != 'diaper'
        GROUP BY x.ms_id, ui.date
    ");
    if ($resCpCo) {
        while ($r = $resCpCo->fetch_assoc()) {
            $id = (int)$r['ms_id'];
            if (!isset($out[$id])) { continue; }
            $out[$id][$r['inv_date']] = ($out[$id][$r['inv_date']] ?? 0.0) + (float)$r['achieved'];
        }
    }

    return $out;
}

// The invoice ids each person's own Get Orders actually converted into, in
// the date range — same (ms_id, invoiced_inv_id) pairing the "Achieved"
// queries above are built on, just returned as ids instead of summed to an
// amount. Used to look up Return % against specifically the invoices that
// came from a DM's own Get Orders (not every invoice their TPs ever raised
// — that broader figure is dashboard.php's own separate Return % card).
function getGetOrderInvoiceIdsByMsId($db_conn, array $ids, string $fromDate, string $toDate): array {
    $out = [];
    foreach ($ids as $id) { $out[(int)$id] = []; }
    if (empty($ids)) return $out;

    $idList = implode(',', array_map('intval', $ids));
    $fromEsc = $db_conn->real_escape_string($fromDate);
    $toEsc   = $db_conn->real_escape_string($toDate);
    $res = $db_conn->query("
        SELECT DISTINCT o.ms_id, t.invoiced_inv_id
        FROM ms_orders o
        JOIN tp_orders t ON t.order_id = o.order_id
        WHERE o.ms_id IN ($idList) AND o.new_order = 'yes'
          AND o.order_date BETWEEN '$fromEsc' AND '$toEsc'
          AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $id = (int)$r['ms_id'];
            if (!isset($out[$id])) { continue; }
            $out[$id][] = $r['invoiced_inv_id'];
        }
    }

    // CP-fulfilled and Company-direct Get Orders (see
    // _teamTargetAchievedCpCompanySql()'s comment above) — same "this DM's
    // own Get Orders" scope, so Return % stays consistent with Achieved.
    $resCpCo = $db_conn->query("
        SELECT DISTINCT o.ms_id, ui.inv_id
        FROM ms_orders o
        JOIN user_invoice ui ON ui.source_ms_order_id = o.order_id
        WHERE o.ms_id IN ($idList) AND o.new_order = 'yes'
          AND o.order_date BETWEEN '$fromEsc' AND '$toEsc'
    ");
    if ($resCpCo) {
        while ($r = $resCpCo->fetch_assoc()) {
            $id = (int)$r['ms_id'];
            if (!isset($out[$id])) { continue; }
            $out[$id][] = $r['inv_id'];
        }
    }

    return $out;
}

// Date $msId's cumulative Napkin-only achieved sales first reached $target
// — null if never reached in range. $dailyByMsId is
// getRawAchievedDailyByMsId()'s own output (passed in rather than
// re-queried, so a caller checking several people reuses one batched
// query).
//
// Returns the day AFTER the raw crossing day, not the crossing day itself —
// 2026-12-01, user-requested ("innaikku morning na 12 clock eluntha na
// enakku ennoda login la balance 0 nu varanum antha day than achieved
// date" — e.g. the target was actually crossed mid-day on the 27th, but the
// 27th itself never opened with Balance already at 0, only the 28th does,
// so the 28th is the real "completed" date). Same concept
// marketing/include/KpiDataPrep.php's Early Month Target Achievement KPI
// applies to its own copy of this same crossing-date logic
// (kpiCompletedDate() + its own +1-day shift) — kept as two separate
// functions since they read from differently-shaped daily maps, but both
// must apply the identical +1-day rule so "Target Completed Date" shown
// here and the KPI's own scoring date never disagree.
function getTargetCompletedDate(array $dailyByMsId, int $msId, float $target): ?string {
    if ($target <= 0) { return null; }
    $days = $dailyByMsId[$msId] ?? [];
    ksort($days);
    $running = 0.0;
    foreach ($days as $date => $amt) {
        $running += $amt;
        if ($running >= $target) { return date('Y-m-d', strtotime($date . ' +1 day')); }
    }
    return null;
}

// Same "Achieved" definition as getRawTargetAchievedStats() above (Get
// Order-sourced, converted-to-invoice, Napkin-only sales in the date range),
// just grouped by the fulfilling TP (tp_orders.tp_id) instead of by the
// Marketing Staff member who placed the order — for a per-TP Target/
// Achieved/Balance table. Target isn't computed here; callers already have
// each TP's own target (e.g. dashboard.php's $tpRoster) from elsewhere.
function getTpOrderAchievedStats($db_conn, array $tpIds, string $fromDate, string $toDate): array {
    $stats = [];
    foreach ($tpIds as $id) { $stats[(int)$id] = 0.0; }
    if (empty($tpIds)) return $stats;

    $idList = implode(',', array_map('intval', $tpIds));
    $fromEsc = $db_conn->real_escape_string($fromDate);
    $toEsc   = $db_conn->real_escape_string($toDate);
    $cast    = _teamTargetCollationCast($db_conn);

    $resA = $db_conn->query("
        SELECT x.tp_id, COALESCE(SUM(uii.total), 0) AS achieved
        FROM (
            SELECT DISTINCT t.tp_id, t.invoiced_inv_id
            FROM tp_orders t
            JOIN ms_orders o ON o.order_id = t.order_id
            WHERE t.tp_id IN ($idList) AND o.new_order = 'yes'
              AND o.order_date BETWEEN '$fromEsc' AND '$toEsc'
              AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''
        ) x
        JOIN user_invoice_items uii ON uii.inv_id$cast = x.invoiced_inv_id$cast
        JOIN products p ON p.id = uii.pr_id
        WHERE COALESCE(p.category,'') != 'diaper'
        GROUP BY x.tp_id
    ");
    if ($resA) {
        while ($r = $resA->fetch_assoc()) {
            $stats[(int)$r['tp_id']] = (float)$r['achieved'];
        }
    }

    return $stats;
}

// Channel breakdown of the same "Achieved" figure getRawTargetAchievedStats()
// returns (TP / CP / Company-direct), for a hover tooltip on dashboards that
// want to show where the number came from instead of just the total —
// company/ms-team-shop-view.php's Achieved column, 2026-11-13.
function getAchievedChannelBreakdown($db_conn, array $ids, string $fromDate, string $toDate): array {
    ensureShopWalletCommissionSchema($db_conn); // fulfilled_by_cp_id
    $stats = [];
    foreach ($ids as $id) { $stats[(int)$id] = ['tp' => 0.0, 'cp' => 0.0, 'company' => 0.0]; }
    if (empty($ids)) return $stats;

    $idList = implode(',', array_map('intval', $ids));
    $fromEsc = $db_conn->real_escape_string($fromDate);
    $toEsc   = $db_conn->real_escape_string($toDate);
    $cast    = _teamTargetCollationCast($db_conn);

    $resTp = $db_conn->query("
        SELECT x.ms_id, COALESCE(SUM(uii.total), 0) AS achieved
        FROM (
            SELECT DISTINCT o.ms_id, t.invoiced_inv_id AS inv_id
            FROM ms_orders o
            JOIN tp_orders t ON t.order_id = o.order_id
            WHERE o.ms_id IN ($idList) AND o.new_order = 'yes'
              AND o.order_date BETWEEN '$fromEsc' AND '$toEsc'
              AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''
        ) x
        JOIN user_invoice_items uii ON uii.inv_id$cast = x.inv_id$cast
        JOIN products p ON p.id = uii.pr_id
        WHERE COALESCE(p.category,'') != 'diaper'
        GROUP BY x.ms_id
    ");
    if ($resTp) {
        while ($r = $resTp->fetch_assoc()) {
            $id = (int)$r['ms_id'];
            if (!isset($stats[$id])) { continue; }
            $stats[$id]['tp'] = (float)$r['achieved'];
        }
    }

    $resCpCo = $db_conn->query("
        SELECT x.ms_id, x.channel, COALESCE(SUM(uii.total), 0) AS achieved
        FROM (
            SELECT DISTINCT o.ms_id, ui.inv_id,
                   CASE WHEN ui.fulfilled_by_cp_id IS NOT NULL THEN 'cp' ELSE 'company' END AS channel
            FROM ms_orders o
            JOIN user_invoice ui ON ui.source_ms_order_id = o.order_id
            WHERE o.ms_id IN ($idList) AND o.new_order = 'yes'
              AND o.order_date BETWEEN '$fromEsc' AND '$toEsc'
        ) x
        JOIN user_invoice_items uii ON uii.inv_id$cast = x.inv_id$cast
        JOIN products p ON p.id = uii.pr_id
        WHERE COALESCE(p.category,'') != 'diaper'
        GROUP BY x.ms_id, x.channel
    ");
    if ($resCpCo) {
        while ($r = $resCpCo->fetch_assoc()) {
            $id = (int)$r['ms_id'];
            if (!isset($stats[$id])) { continue; }
            $stats[$id][$r['channel']] = (float)$r['achieved'];
        }
    }

    return $stats;
}
?>
