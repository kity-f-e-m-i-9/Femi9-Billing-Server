<?php
// Shared data-prep + per-row KPI computation for marketing/kpi.php and
// marketing/export-kpi-xlsx.php — extracted 2026-11-21 so the Excel export
// can reuse the EXACT same numbers the page shows instead of a second,
// independently-maintained copy of this logic. Requires checksession.php
// and config.php to already be included by the calling page (both set
// $markeingSTFID / $db_conn this file depends on).
require_once __DIR__ . '/TeamLevelColors.php';
require_once __DIR__ . '/TeamSubtree.php';
require_once __DIR__ . '/../../company/include/TeamTargetRollup.php';
require_once __DIR__ . '/TpInvoiceAmount.php';
error_reporting(0);

// Same ?embed=1 pattern as dashboard.php (company/view-ms-kpi.php's own
// read-only "view as" bridge) — hides sidebar/header when embedded in an
// iframe; $_companyBridgeView (set by checksession.php's bridge) drives the
// read-only banner below when opened standalone instead.
$embed = !empty($_GET['embed']);

$rootId = (int)$markeingSTFID;
$subtreeIds = getMsSubtreeIds($db_conn, $rootId);
$idList = implode(',', $subtreeIds);

$levelColorMap = getTeamLevelColorMap($db_conn);

$staffRows = $db_conn->query("
    SELECT ms.id, ms.ms_name, ms.manager_id, ms.team_level_id, tl.level_name, tl.level_rank
    FROM marketing_staff ms
    LEFT JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
    WHERE ms.id IN ($idList)
    ORDER BY tl.level_rank ASC, ms.ms_name ASC
")->fetch_all(MYSQLI_ASSOC);

$byManager = [];
$selfRow = null;
foreach ($staffRows as $row) {
    if ((int)$row['id'] === $rootId) { $selfRow = $row; }
    $mgrKey = $row['manager_id'] ? (int)$row['manager_id'] : 0;
    $byManager[$mgrKey][] = $row;
}

// ── Date range to show — a plain From/To range the user picks, not tied to
// a calendar month or week anymore. Defaults to "this month so far" (1st of
// this month through today). Every KPI below (Target/Achieved, New Shop
// Onboarding, Daily Market Work, Early Month Target Achieved) is computed
// for this exact range.
function validateKpiDate(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}
$fromDate = validateKpiDate($_GET['from_date'] ?? null, date('Y-m-01'));
$toDate   = validateKpiDate($_GET['to_date'] ?? null, date('Y-m-d'));
if ($fromDate > $toDate) { [$fromDate, $toDate] = [$toDate, $fromDate]; }
$daysInRange = (int)floor((strtotime($toDate) - strtotime($fromDate)) / 86400) + 1;

$targetStats = getRawTargetAchievedStats($db_conn, $subtreeIds, $fromDate, $toDate);
$dailyByMsId = getRawAchievedDailyByMsId($db_conn, $subtreeIds, $fromDate, $toDate);

// "Achieved" above (from getRawTargetAchievedStats()/getRawAchievedDailyByMsId())
// is Get Order-converted sales ONLY (same definition the dashboard's own
// "Achieved" card uses, deliberately separate there from "TP Invoice
// Amount") — but KPI #1/#2/#3 (Napkin Sales Target Achievement, District
// Sales Overachievement, Weekly Sales Target Achievement) are scored
// against "your assigned monthly district napkin sales target" using ONLY
// the napkin-only TP Invoice Amount (the dashboard's own "Your TPs' Invoice
// Amount" card) — Get Order amount is explicitly NOT part of this KPI's
// Achieved at all (confirmed with the user 2026-11-23, after an earlier
// attempt that ADDED TP Invoice Amount on top of Get Order achieved was
// explicitly rejected: "dont combine get order amount antha thappa
// pannatha only tp invoice amount athuvum napkin odathu mattum than
// podanum"). REPLACES (not adds to) both the period total ($targetStats)
// and the day-by-day breakdown ($dailyByMsId, which #3/#4 also depend on)
// per leaf, BEFORE the subtree rollup below, so kpiSubtreeSumAndIds()'s
// existing addition-based rollup still sums correctly for an ASM/SM's
// whole team.
//
// Also overwrites 'target' with the FIXED full-month district target
// (getMsAssignedTargetScopeNodes()'s own district_total_target, before any
// proration) instead of getRawTargetAchievedStats()'s own prorated-to-range
// value — same "Plan" should never shrink just because a shorter date range
// was picked" rule already applied to New Shop/Category Onboarding's flat
// monthly eligibility figures, extended here to Plan on request. Only
// overridden inside this KPI-specific copy of $targetStats, never in
// getRawTargetAchievedStats() itself — other pages reusing that function
// (my-team.php, ms-team-view.php) still want the date-range-prorated target.
// 2026-11-23, user-reported (Plan dropped from ₹6,60,000 to ₹5,06,000 just
// from picking a 23-day range instead of the full month).
foreach ($subtreeIds as $_kpiTpAmtId) {
    $_kpiTpAmtId = (int)$_kpiTpAmtId;
    $_scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $_kpiTpAmtId);
    if (empty($_scopeNodes)) { continue; }
    $_scopeIds = array_column($_scopeNodes, 'id');
    $_scopeStats = getMsNodeTargetAndFirkaStats($db_conn, $_scopeIds);
    $targetStats[$_kpiTpAmtId]['target'] = (float)$_scopeStats['district_total_target'];
    $_tpIds = getMsNodeTpIds($db_conn, $_scopeIds, true);
    if (empty($_tpIds)) { continue; }
    $_districtIds = districtIdsFromScopeNodeIds($db_conn, $_scopeIds);
    if (empty($_districtIds)) { continue; }
    $targetStats[$_kpiTpAmtId]['achieved'] = getTpInvoiceAmountForDistricts($db_conn, $_tpIds, $_districtIds, $fromDate, $toDate);
    $dailyByMsId[$_kpiTpAmtId] = getTpInvoiceAmountByDateForDistricts($db_conn, $_tpIds, $_districtIds, $fromDate, $toDate);
}

// ── 5-KPI scorecard (v1) — the other KPIs in the company's manual "DM ASM
// KPI Report" tracker (District Sales, New Category Outlets, Sales Return
// Control, Shop Merchandising, Promotional Activities, Professional
// Discipline, Reporting Accuracy, Innovation) have no DB signal at all (even
// in the original tracker they're typed in by hand) or need more design
// work to attribute correctly — left out of v1 by explicit agreement with
// the company, not an oversight. These 5 are the ones with a real,
// unambiguous DB source:
//   1. Femi9 Sales KPI (15%)              — reuses $targetStats above
//   2. Weekly Sales KPI (5%)               — reuses $dailyByMsId above
//   3. Early Month Target Achieved KPI (5%) — how early WITHIN THE SELECTED
//      RANGE the target got completed, split into quarters of the range;
//      quarter 1 = full marks (4/4), quarter 4 = 1/4, not completed in the
//      range = 0/4. Confirmed with the company 2026-11-13 that this is what
//      "PLAN=4" in the tracker means (originally week-of-month; generalised
//      to quarter-of-range once the page moved to a free date range).
//   4. New Shop Onboarding KPI (5%)        — ms_shop rows this person's
//      subtree added in the range, against a company-wide plan prorated to
//      the range length (no per-DM target exists for this yet, by explicit
//      choice for v1).
//   5. Daily Market Work & Get Order KPI (10%) — how many distinct calendar
//      days in the range had at least one Get Order (ms_orders.new_order=
//      'yes') from anyone in this person's subtree, against the range's
//      working days (every day except Sunday).
if (!defined('KPI_NEW_SHOP_PLAN_PER_MONTH')) { define('KPI_NEW_SHOP_PLAN_PER_MONTH', 20); }

$idListForKpi = implode(',', array_map('intval', $subtreeIds));
$fromDateEsc = $db_conn->real_escape_string($fromDate);
$toDateEsc   = $db_conn->real_escape_string($toDate);

// Same name+mobile dedupe-key idea as marketing/dashboard.php's own
// shopOnboardDedupeKey() (New Shop Onboarding card) — kept as a separate
// copy here rather than a shared include, since dashboard.php's version is
// a plain top-level function, not one built for reuse.
function kpiShopDedupeKey($name, $mobile): ?string {
    $digits = preg_replace('/\D+/', '', (string)$mobile);
    $nm = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string)$name)));
    if (strlen($digits) === 10 && $nm !== '') { return $nm . '|' . $digits; }
    return null;
}

// Total New Shops Onboarded per ms_id = TP-added shops in that person's own
// district scope (shop.created_at, excluding ms_shop-promoted rows) + the
// DM's own ms_shop additions that do NOT match an existing TP shop by
// name+mobile (de-duping a DM re-typing a shop their TP already onboarded).
// Exact same two-source + dedupe logic as marketing/dashboard.php's "New
// Shop Onboarding" card, just computed per leaf id instead of once for a
// whole team — so kpiSubtreeSumAndIds()-style plain addition still rolls up
// correctly for an ASM/SM. 2026-11-23, user-reported: KPI #6 showed
// Achieved=5 (ms_shop/"DM Added" count only) while the dashboard's own
// "Total New Shops Onboarded" for the same DM/period showed 12 (8 TP Added +
// 4 DM New-unique) — the KPI was silently missing every TP-onboarded shop.
function kpiTotalNewShopsCountByMsId(mysqli $db_conn, array $msIds, string $fromDate, string $toDate): array {
    $out = [];
    $fromEsc = $db_conn->real_escape_string($fromDate);
    $toEsc   = $db_conn->real_escape_string($toDate);
    foreach ($msIds as $msId) {
        $msId = (int)$msId;
        $scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $msId);
        if (empty($scopeNodes)) { $out[$msId] = 0; continue; }
        $scopeIdList = implode(',', array_map('intval', array_column($scopeNodes, 'id')));

        $tpCount = (int)($db_conn->query("
            SELECT COUNT(*) AS c FROM shop
            WHERE district_id IN ($scopeIdList) AND source_ms_shop_id IS NULL
              AND deleted_at IS NULL AND created_at IS NOT NULL
              AND DATE(created_at) BETWEEN '$fromEsc' AND '$toEsc'
        ")->fetch_assoc()['c'] ?? 0);

        $tpShopKeys = [];
        $resAllTp = $db_conn->query("
            SELECT name, mobile_number AS mobile FROM shop
            WHERE district_id IN ($scopeIdList) AND source_ms_shop_id IS NULL AND deleted_at IS NULL
        ");
        while ($r = $resAllTp->fetch_assoc()) {
            $k = kpiShopDedupeKey($r['name'], $r['mobile']);
            if ($k !== null) { $tpShopKeys[$k] = true; }
        }

        $dmUniqueCount = 0;
        $resDm = $db_conn->query("
            SELECT name, mobile_number AS mobile FROM ms_shop
            WHERE ms_id = $msId AND DATE(created_at) BETWEEN '$fromEsc' AND '$toEsc'
        ");
        while ($r = $resDm->fetch_assoc()) {
            $k = kpiShopDedupeKey($r['name'], $r['mobile']);
            if ($k === null || !isset($tpShopKeys[$k])) { $dmUniqueCount++; }
        }

        $out[$msId] = $tpCount + $dmUniqueCount;
    }
    return $out;
}

$newShopCountByMsId = kpiTotalNewShopsCountByMsId($db_conn, $subtreeIds, $fromDate, $toDate);

// Per ms_id: which shop_category ids (shop_cat) this person's own new shops
// (same two sources as kpiTotalNewShopsCountByMsId above — TP-added shop
// rows in scope + DM-unique ms_shop rows) fell into this range, plus a
// per-category shop count for the hover breakdown. 2026-11-30, user-reported
// (screenshot: KPI #7 "New Category Outlets" showing Ach=445, same number as
// #6 "New Shop Onboarding") — #7 was defined 2026-11-17 as "same count as #6,
// every category counts", which is literally correct per that decision, but
// the user looked at the real ~25-row shop_category list and expected #7 to
// mean the number of DISTINCT categories touched (max ~25), not a shop
// count that can run into the hundreds. Confirmed via AskUserQuestion:
// #7 should be the count of distinct categories with at least one new shop
// in range, for this person's own subtree.
function kpiNewCategoryStatsByMsId(mysqli $db_conn, array $msIds, string $fromDate, string $toDate): array {
    $out = [];
    $fromEsc = $db_conn->real_escape_string($fromDate);
    $toEsc   = $db_conn->real_escape_string($toDate);
    foreach ($msIds as $msId) {
        $msId = (int)$msId;
        $scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $msId);
        if (empty($scopeNodes)) { $out[$msId] = ['set' => [], 'breakdown' => []]; continue; }
        $scopeIdList = implode(',', array_map('intval', array_column($scopeNodes, 'id')));

        $breakdown = [];
        $resTp = $db_conn->query("
            SELECT shop_cat, COUNT(*) AS c FROM shop
            WHERE district_id IN ($scopeIdList) AND source_ms_shop_id IS NULL
              AND deleted_at IS NULL AND created_at IS NOT NULL
              AND DATE(created_at) BETWEEN '$fromEsc' AND '$toEsc'
            GROUP BY shop_cat
        ");
        if ($resTp) { while ($r = $resTp->fetch_assoc()) { $breakdown[(int)$r['shop_cat']] = ($breakdown[(int)$r['shop_cat']] ?? 0) + (int)$r['c']; } }

        $tpShopKeys = [];
        $resAllTp = $db_conn->query("
            SELECT name, mobile_number AS mobile FROM shop
            WHERE district_id IN ($scopeIdList) AND source_ms_shop_id IS NULL AND deleted_at IS NULL
        ");
        while ($r = $resAllTp->fetch_assoc()) {
            $k = kpiShopDedupeKey($r['name'], $r['mobile']);
            if ($k !== null) { $tpShopKeys[$k] = true; }
        }

        $resDm = $db_conn->query("
            SELECT name, mobile_number AS mobile, shop_cat FROM ms_shop
            WHERE ms_id = $msId AND DATE(created_at) BETWEEN '$fromEsc' AND '$toEsc'
        ");
        while ($r = $resDm->fetch_assoc()) {
            $k = kpiShopDedupeKey($r['name'], $r['mobile']);
            if ($k === null || !isset($tpShopKeys[$k])) {
                $breakdown[(int)$r['shop_cat']] = ($breakdown[(int)$r['shop_cat']] ?? 0) + 1;
            }
        }

        $out[$msId] = ['set' => array_fill_keys(array_keys($breakdown), true), 'breakdown' => $breakdown];
    }
    return $out;
}

$newCategoryStatsByMsId = kpiNewCategoryStatsByMsId($db_conn, $subtreeIds, $fromDate, $toDate);
$newShopCategorySetByMsId = [];
foreach ($newCategoryStatsByMsId as $mid => $stats) { $newShopCategorySetByMsId[$mid] = array_keys($stats['set']); }

// Distinct active day set per person (not a count yet) — a manager's
// rollup is the UNION of days their team covered, not the sum of each
// member's day-count (that would double-count a day two team members both
// worked), computed in kpiMarketWorkUnionDays() below.
$marketWorkDatesByMsId = [];
$mwRes = $db_conn->query("
    SELECT ms_id, DATE(order_date) AS d
    FROM ms_orders
    WHERE ms_id IN ($idListForKpi) AND new_order = 'yes'
          AND order_date BETWEEN '$fromDateEsc' AND '$toDateEsc'
    GROUP BY ms_id, DATE(order_date)
");
if ($mwRes) { while ($r = $mwRes->fetch_assoc()) { $marketWorkDatesByMsId[(int)$r['ms_id']][$r['d']] = true; } }

// Working-days plan for the range — every day except Sunday.
$workingDaysPlan = 0;
$cursorDt = new DateTime($fromDate);
$toDateDt = new DateTime($toDate);
while ($cursorDt <= $toDateDt) {
    if ((int)$cursorDt->format('w') !== 0) { $workingDaysPlan++; }
    $cursorDt->modify('+1 day');
}

// New Shop Onboarding plan, prorated to the range length against the
// company's 20-per-30-days default.
$newShopPlan = max(1, (int)round(KPI_NEW_SHOP_PLAN_PER_MONTH * $daysInRange / 30));

$rawStats = [];
foreach ($subtreeIds as $id) {
    $rawStats[$id] = [
        'target'   => (float)($targetStats[$id]['target'] ?? 0.0),
        'achieved' => (float)($targetStats[$id]['achieved'] ?? 0.0),
    ];
}

// ── Layer-1 KPI extensions (2026-11-17) — bringing the scorecard above up
// to the official "KPI Structure - District Manager" document's Layer 1
// (Business Impact, 65 marks). Three of these are ranking-based: the DM
// with the best number/percentage among ALL active company DMs (not just
// this person's own subtree) gets full marks, everyone else gets a
// proportional share — so each needs one extra company-wide query,
// independent of whose page is open. Monthly Promotional Activities (5%)
// still has no DB signal anywhere in the system and stays unscored (shown
// as N/A) — everything else in Layer 1 is now covered.
if (!defined('KPI_NEW_SHOP_ELIGIBILITY_PER_MONTH')) { define('KPI_NEW_SHOP_ELIGIBILITY_PER_MONTH', 50); }
if (!defined('KPI_NEW_CATEGORY_ELIGIBILITY_PER_MONTH')) { define('KPI_NEW_CATEGORY_ELIGIBILITY_PER_MONTH', 20); }

$companyDmIds = [];
$dmRes = $db_conn->query("SELECT id FROM marketing_staff WHERE team_level_id = 3 AND account_status = 'active' AND deleted_at IS NULL");
if ($dmRes) { while ($r = $dmRes->fetch_assoc()) { $companyDmIds[] = (int)$r['id']; } }
$companyDmIdList = implode(',', $companyDmIds ?: [0]);

// District Sales Target Overachievement (10%) — same Napkin-only
// Target/Achieved as the Monthly Napkin Sales KPI, but company-wide: only
// DMs over 100% are eligible, and the highest achiever among them gets
// full marks. Must use the SAME napkin-only TP Invoice Amount override
// every DM's own KPI #1 Achieved uses (the per-subtree-leaf loop at the top
// of this file), not the old Get-Order-only getRawTargetAchievedStats() —
// otherwise a DM who legitimately crosses 100% mainly via direct TP
// invoices (little/no Get Order) never counts toward this company-wide
// ranking, so $companyMaxOverachPct can stay 0 even when real DMs are over
// 100%, showing "Not eligible" for everyone including the DM who IS over
// 100% on their own card. 2026-12-01, user-reported (own Achieved 113%,
// card still said "Not eligible") — this was the exact gap flagged
// 2026-11-23 as "could understate a high-performing DM's ranking if they
// sell mostly via direct TP invoice", now confirmed actually happening.
// Costs ~5 extra queries per active company DM (same tradeoff already
// accepted for KPI #6/#7's own company-wide ranking loop below).
$companyMaxOverachPct = 0.0;
foreach ($companyDmIds as $_cdmId) {
    $_cdmScopeNodes = getMsAssignedTargetScopeNodes($db_conn, $_cdmId);
    if (empty($_cdmScopeNodes)) { continue; }
    $_cdmScopeIds = array_column($_cdmScopeNodes, 'id');
    $_cdmTarget = (float)(getMsNodeTargetAndFirkaStats($db_conn, $_cdmScopeIds)['district_total_target'] ?? 0);
    if ($_cdmTarget <= 0) { continue; }
    $_cdmTpIds = getMsNodeTpIds($db_conn, $_cdmScopeIds, true);
    if (empty($_cdmTpIds)) { continue; }
    $_cdmDistrictIds = districtIdsFromScopeNodeIds($db_conn, $_cdmScopeIds);
    if (empty($_cdmDistrictIds)) { continue; }
    $_cdmAchieved = getTpInvoiceAmountForDistricts($db_conn, $_cdmTpIds, $_cdmDistrictIds, $fromDate, $toDate);
    $_cdmPct = $_cdmAchieved / $_cdmTarget;
    if ($_cdmPct > 1 && $_cdmPct > $companyMaxOverachPct) { $companyMaxOverachPct = $_cdmPct; }
}

// New Shop Onboarding (5%) / New Category Outlets Onboarding (5%) —
// PDF states these as flat monthly minimums ("minimum of 50 new retail
// shops per month" / "minimum of 20 new Outlet category") — NOT prorated to
// whatever From/To range happens to be selected. Kept fixed regardless of
// $daysInRange, corrected 2026-11-20 after re-checking against the PDF
// (previously scaled down for shorter ranges, which silently lowered the
// bar below the document's stated minimum).
$newShopEligibility = KPI_NEW_SHOP_ELIGIBILITY_PER_MONTH;
$newCategoryEligibility = KPI_NEW_CATEGORY_ELIGIBILITY_PER_MONTH;

// #6's company-wide ranking denominator — raw new-shop count per company DM.
$companyNewShopCountByMsId = kpiTotalNewShopsCountByMsId($db_conn, $companyDmIds, $fromDate, $toDate);
$companyMaxNewShopEligible = 0;
foreach ($companyNewShopCountByMsId as $cnt) {
    if ($cnt >= $newShopEligibility && $cnt > $companyMaxNewShopEligible) { $companyMaxNewShopEligible = $cnt; }
}

// #7's company-wide ranking denominator — distinct-category count per
// company DM (NOT the same as #6's shop count — see kpiNewCategoryStatsByMsId
// doc comment above for why these were split 2026-11-30).
$companyCategoryStatsByMsId = kpiNewCategoryStatsByMsId($db_conn, $companyDmIds, $fromDate, $toDate);
$companyMaxCategoryEligible = 0;
foreach ($companyCategoryStatsByMsId as $stats) {
    $cnt = count($stats['set']);
    if ($cnt >= $newCategoryEligibility && $cnt > $companyMaxCategoryEligible) { $companyMaxCategoryEligible = $cnt; }
}

$shopCatLabelMap = [];
$scRes = $db_conn->query("SELECT id, catlable FROM shop_category");
if ($scRes) { while ($r = $scRes->fetch_assoc()) { $shopCatLabelMap[(int)$r['id']] = $r['catlable']; } }

// Per-category breakdown of this subtree's own new shops, for the New
// Category Outlets Onboarding hover ("exactly which categories") — now from
// the same combined TP+DM source as the distinct-category count itself
// (previously ms_shop-only, which undercounted — e.g. summed to 5 instead
// of a real 12 for a DM whose TPs did most of the onboarding).
$newShopCategoryBreakdownByMsId = [];
foreach ($newCategoryStatsByMsId as $mid => $stats) { $newShopCategoryBreakdownByMsId[$mid] = $stats['breakdown']; }

// Retail Shop Merchandising Compliance (5%) — proxy agreed with the company
// 2026-11-17/20: a shop this person's subtree onboarded (ms_shop) that was
// later converted into a real billable shop (shop.source_ms_shop_id) is
// checked PER PRODUCT CATEGORY it has ever actually bought (Napkin, Diaper
// — a shop that only ever buys Napkin is never judged on Diaper at all).
// Within each category it has bought at least once, that category must
// have a fresh invoice (within the last 3 calendar months of the range's
// end date) or it counts as lapsed. A shop is "compliant" only if NONE of
// its own categories have lapsed — whichever category is worst drags the
// whole shop down, same rule as Sales Return Control's "either brand over
// 5%" check. A shop with no invoice history at all in any category is
// non-compliant (nothing to be continuous about). Score = % compliant
// among this person's own linked shops — a straight percentage, not
// ranked against other DMs (same shape as the Napkin Sales KPI).
function kpiMerchandisingStats($db_conn, array $ids, string $toDate): array {
    $stats = [];
    foreach ($ids as $id) { $stats[(int)$id] = ['total' => 0, 'compliant' => 0]; }
    if (empty($ids)) return $stats;
    $idList = implode(',', array_map('intval', $ids));
    $toEsc = $db_conn->real_escape_string($toDate);
    $cast = _teamTargetCollationCast($db_conn);

    $shopMsId = [];
    $res = $db_conn->query("
        SELECT ms.ms_id AS ms_id, s.id AS shop_id
        FROM ms_shop ms
        JOIN shop s ON s.source_ms_shop_id = ms.id
        WHERE ms.ms_id IN ($idList) AND s.deleted_at IS NULL
    ");
    if ($res) { while ($r = $res->fetch_assoc()) { $shopMsId[(int)$r['shop_id']] = (int)$r['ms_id']; } }
    if (empty($shopMsId)) return $stats;

    $shopIdList = implode(',', array_keys($shopMsId));
    $catsByShop = [];
    $catRes = $db_conn->query("
        SELECT ui.to_user_id AS shop_id, COALESCE(p.category, 'napkin') AS category, MAX(ui.date) AS last_date
        FROM user_invoice ui
        JOIN user_invoice_items uii ON uii.inv_id$cast = ui.inv_id$cast
        JOIN products p ON p.id = uii.pr_id
        WHERE ui.to_user_type = 'shop' AND ui.to_user_id IN ($shopIdList)
          AND ui.status <> 'cancelled' AND ui.deleted_at IS NULL AND ui.voided_at IS NULL
          AND ui.date <= '$toEsc'
        GROUP BY ui.to_user_id, COALESCE(p.category, 'napkin')
    ");
    if ($catRes) { while ($r = $catRes->fetch_assoc()) { $catsByShop[(int)$r['shop_id']][$r['category']] = $r['last_date']; } }

    foreach ($shopMsId as $shopId => $msId) {
        if (!isset($stats[$msId])) { continue; }
        $stats[$msId]['total']++;
        $cats = $catsByShop[$shopId] ?? [];
        if (empty($cats)) { continue; } // never ordered anything -> non-compliant
        $compliant = true;
        foreach ($cats as $lastDate) {
            $lapsed = ($lastDate === null) || ((strtotime($toDate) - strtotime($lastDate)) > 90 * 86400);
            if ($lapsed) { $compliant = false; break; }
        }
        if ($compliant) { $stats[$msId]['compliant']++; }
    }
    return $stats;
}
$merchandisingStatsByMsId = kpiMerchandisingStats($db_conn, $subtreeIds, $toDate);

// Sales Return Control (deduct 10) — looks at BOTH brands (Femi9 Napkin +
// Lumi9 Diaper). turnover = TP Invoice Amount for that brand, district-
// scoped, SAME definition KPI #1 uses for Napkin Achieved (not the much
// narrower Get-Order-sourced-only figure this used before) — so "5% of
// turnover" is 5% of the exact number shown as Achieved elsewhere on this
// page, not a different, smaller slice of it. returns = user_return_stock_items
// scoped to that same TP/district/date population. -10 is applied if EITHER
// brand's return value exceeds 5% of that brand's own turnover. 2026-11-24,
// user-reported ("ipo 514423 la 5% athu tha avnaga return la vaara vendiya
// amount" — the 5% threshold must be against the Achieved figure, not a
// separate, narrower turnover definition).
function kpiReturnControlStats($db_conn, array $ids, string $fromDate, string $toDate): array {
    $stats = [];
    foreach ($ids as $id) { $stats[(int)$id] = ['napkin_turnover' => 0.0, 'napkin_return' => 0.0, 'diaper_turnover' => 0.0, 'diaper_return' => 0.0]; }
    foreach ($ids as $id) {
        $id = (int)$id;
        $scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $id);
        if (empty($scopeNodes)) { continue; }
        $scopeIds = array_column($scopeNodes, 'id');
        $tpIds = getMsNodeTpIds($db_conn, $scopeIds, true);
        if (empty($tpIds)) { continue; }
        $districtIds = districtIdsFromScopeNodeIds($db_conn, $scopeIds);
        if (empty($districtIds)) { continue; }
        $stats[$id]['napkin_turnover'] = getTpInvoiceAmountForDistricts($db_conn, $tpIds, $districtIds, $fromDate, $toDate);
        $stats[$id]['diaper_turnover'] = getTpDiaperInvoiceAmountForDistricts($db_conn, $tpIds, $districtIds, $fromDate, $toDate);
        $stats[$id]['napkin_return']   = getTpReturnAmountForDistrictsByCategory($db_conn, $tpIds, $districtIds, $fromDate, $toDate, 'napkin');
        $stats[$id]['diaper_return']   = getTpReturnAmountForDistrictsByCategory($db_conn, $tpIds, $districtIds, $fromDate, $toDate, 'diaper');
    }
    return $stats;
}
$returnControlStatsByMsId = kpiReturnControlStats($db_conn, $subtreeIds, $fromDate, $toDate);

// Generic subtree sum for the plain-additive maps above (returns/turnover,
// merchandising total/compliant) — unlike kpiSubtreeSumAndIds() below these
// never zero out a manager's own number, since a manager's shops/invoices
// are real regardless of whether they have a team.
function kpiSubtreeSumGeneric(int $id, array $byManager, array $statsMap, array $fields): array {
    $sum = $statsMap[$id] ?? array_fill_keys($fields, 0);
    $children = $byManager[$id] ?? [];
    foreach ($children as $child) {
        $childSum = kpiSubtreeSumGeneric((int)$child['id'], $byManager, $statsMap, $fields);
        foreach ($fields as $f) { $sum[$f] += $childSum[$f]; }
    }
    return $sum;
}

$kx = [
    'companyMaxOverachPct' => $companyMaxOverachPct,
    'newShopEligibility' => $newShopEligibility,
    'companyMaxNewShopEligible' => $companyMaxNewShopEligible,
    'newCategoryEligibility' => $newCategoryEligibility,
    'companyMaxCategoryEligible' => $companyMaxCategoryEligible,
    'newShopCategoryBreakdownByMsId' => $newShopCategoryBreakdownByMsId,
    'newShopCategorySetByMsId' => $newShopCategorySetByMsId,
    'shopCatLabelMap' => $shopCatLabelMap,
    'merchandisingStatsByMsId' => $merchandisingStatsByMsId,
    'returnControlStatsByMsId' => $returnControlStatsByMsId,
];

// ── Weekly view (toggled with the "Weekly" button below) — breaks the SAME
// selected From/To range (not a whole calendar month) into Sun-Sat weeks,
// each week's Target Amount prorated by how many of its days fall inside
// the selected range (so a week split across the range boundary only
// counts its in-range days), Achieved = TP-converted invoice value in that
// week, Balance = Target - Achieved. Reuses the exact same $targetStats/
// $dailyByMsId as the 5-KPI scorecard above, so the weekly totals always
// add up to the same Plan/Achieved shown there — previously this used the
// whole calendar month regardless of the selected range, which made the
// weekly total look inflated whenever a shorter range was picked.
$wkDaysInRange = $daysInRange;

$monthlyRawStats = $rawStats;
$monthlyDailyByMsId = $dailyByMsId;

// Sun-Sat weeks of the selected range, clipped at the range boundary (a
// range that doesn't start on Sunday has a short first week; same idea for
// the last).
$kpiWeeks = [];
$wkCursor = new DateTime($fromDate);
$wkRangeEndDt = new DateTime($toDate);
$wkNum = 1;
while ($wkCursor <= $wkRangeEndDt) {
    $dow = (int)$wkCursor->format('w');
    $weekStartDt = (clone $wkCursor)->modify('-' . $dow . ' days');
    $weekEndDt = (clone $weekStartDt)->modify('+6 days');
    $clippedStart = $weekStartDt < (new DateTime($fromDate)) ? new DateTime($fromDate) : $weekStartDt;
    $clippedEnd = $weekEndDt > $wkRangeEndDt ? $wkRangeEndDt : $weekEndDt;
    $days = (int)floor(($clippedEnd->getTimestamp() - $clippedStart->getTimestamp()) / 86400) + 1;
    $kpiWeeks[] = [
        'label' => 'Week ' . $wkNum,
        'start' => $clippedStart->format('Y-m-d'),
        'end'   => $clippedEnd->format('Y-m-d'),
        'days'  => $days,
    ];
    $wkNum++;
    $wkCursor = (clone $clippedEnd)->modify('+1 day');
}
// Threaded through $kx (already passed to every computeKpiRowValues() call
// site) so KPI #3 below can count weeks met without touching every
// function's own parameter list. 2026-11-24, user-requested.
$kx['kpiWeeks'] = $kpiWeeks;

// Roll a person's own stats + everyone below them up, same convention as
// my-team.php's subtreeSumAndIds(): once someone has a team, their own raw
// target is dropped in favour of the children's summed total (Achieved is
// always a real performance number, so it's summed either way).
function kpiSubtreeSumAndIds(int $id, array $byManager, array $rawStats): array {
    $sum = $rawStats[$id] ?? ['target' => 0.0, 'achieved' => 0.0];
    $children = $byManager[$id] ?? [];
    if (!empty($children)) { $sum['target'] = 0.0; }
    $ids = [$id];
    foreach ($children as $child) {
        [$childSum, $childIds] = kpiSubtreeSumAndIds((int)$child['id'], $byManager, $rawStats);
        $sum['target']   += $childSum['target'];
        $sum['achieved'] += $childSum['achieved'];
        $ids = array_merge($ids, $childIds);
    }
    return [$sum, $ids];
}

function kpiCombinedDaily(array $ids, array $dailyByMsId): array {
    $combined = [];
    foreach ($ids as $id) {
        foreach (($dailyByMsId[$id] ?? []) as $date => $amt) {
            $combined[$date] = ($combined[$date] ?? 0.0) + $amt;
        }
    }
    return $combined;
}

// Sum of a (date => amount) daily map restricted to [start, end] inclusive.
function kpiSumInRange(array $daily, string $start, string $end): float {
    $sum = 0.0;
    foreach ($daily as $date => $amt) {
        if ($date >= $start && $date <= $end) { $sum += $amt; }
    }
    return $sum;
}

function kpiCompletedDate(array $daily, float $target): ?string {
    if ($target <= 0) { return null; }
    ksort($daily);
    $running = 0.0;
    foreach ($daily as $date => $amt) {
        $running += $amt;
        if ($running >= $target) { return $date; }
    }
    return null;
}

// Union of active-day sets across a subtree (not a sum) — a manager's
// coverage is "did the team work the market this many distinct days",
// which double-sum would overstate whenever two team members both worked
// the same day.
function kpiMarketWorkUnionDays(array $ids, array $datesByMsId): int {
    $union = [];
    foreach ($ids as $id) {
        foreach (($datesByMsId[$id] ?? []) as $d => $_) { $union[$d] = true; }
    }
    return count($union);
}

// Weekly-view row — Target/Achieved/Balance per calendar week of the month,
// same hierarchy rollup as renderKpiRow() but scoped to $monthlyRawStats/
// $monthlyDailyByMsId (the whole month) instead of the From/To range.
function renderWeeklyKpiRow(array $row, array $byManager, array $monthlyRawStats, array $monthlyDailyByMsId, array $levelColorMap, int $depth, array $weeks, int $daysInMonth, int $parentId = 0): string {
    $id = (int)$row['id'];
    $color = $levelColorMap[(int)$row['team_level_id']] ?? '#999999';
    [$sum, $ids] = kpiSubtreeSumAndIds($id, $byManager, $monthlyRawStats);
    $monthlyTarget = $sum['target'];
    $combinedDaily = kpiCombinedDaily($ids, $monthlyDailyByMsId);
    $perDayTarget = $daysInMonth > 0 ? ($monthlyTarget / $daysInMonth) : 0.0;

    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
    $isRoot = $depth === 0;

    $html = '<tr data-id="wk' . $id . '" data-parent="wk' . $parentId . '"' . ($isRoot ? ' style="background:#f8fafc;"' : '') . '>';
    $html .= '<td class="kpi-name">' . $indent . ($depth > 0 ? '&#8618;&nbsp;' : '') . ($isRoot ? '<b>' : '') . htmlspecialchars($row['ms_name']) . ($isRoot ? '</b>' : '') . '</td>';
    $html .= '<td><span class="tp-tag" style="color:' . $color . ';background:' . $color . '1a;">' . htmlspecialchars($row['level_name'] ?: '-') . '</span></td>';

    foreach ($weeks as $week) {
        $weekTarget = $perDayTarget * $week['days'];
        $weekAchieved = kpiSumInRange($combinedDaily, $week['start'], $week['end']);
        $weekBalance = max(0, $weekTarget - $weekAchieved);
        $html .= '<td>&#8377;' . inr_format($weekTarget, 0) . '</td>';
        $html .= '<td>&#8377;' . inr_format($weekAchieved, 0) . '</td>';
        if ($weekTarget > 0 && $weekAchieved >= $weekTarget) {
            $html .= '<td><span style="color:#166534;font-weight:600;">&#8377;0</span></td>';
        } else {
            $html .= '<td>&#8377;' . inr_format($weekBalance, 0) . '</td>';
        }
    }
    $html .= '</tr>';

    $children = $byManager[$id] ?? [];
    foreach ($children as $child) {
        $html .= renderWeeklyKpiRow($child, $byManager, $monthlyRawStats, $monthlyDailyByMsId, $levelColorMap, $depth + 1, $weeks, $daysInMonth, $id);
    }
    return $html;
}

function kpiScoreCell(float $achPct, float $weight): string {
    $score = $achPct * $weight;
    $color = $achPct >= 1 ? '#166534' : ($achPct >= 0.5 ? '#b45309' : '#b91c1c');
    return '<td><span style="color:' . $color . ';font-weight:600;">' . number_format($score, 1) . '</span></td>';
}

// Computes every KPI number for one person (rolled up with their subtree,
// same as renderKpiRow() always did) and returns it as a plain array instead
// of HTML — the single source of truth renderKpiRow() (the table view),
// renderKpiCards() (the solo-view card layout), and export-kpi-xlsx.php (the
// Excel export) all build their own output from, so the three can never
// silently drift apart. 2026-11-21, built alongside the card view + Excel
// export (user request).
function computeKpiRowValues(
    array $row, array $byManager, array $rawStats, array $dailyByMsId, array $levelColorMap,
    int $depth, string $fromDate, int $daysInRange,
    array $newShopCountByMsId, array $marketWorkDatesByMsId, int $workingDaysPlan,
    array $kx, int $parentId = 0
): array {
    $id = (int)$row['id'];
    $color = $levelColorMap[(int)$row['team_level_id']] ?? '#999999';
    [$sum, $ids] = kpiSubtreeSumAndIds($id, $byManager, $rawStats);
    $target = $sum['target'];
    $achieved = $sum['achieved'];

    $combinedDaily = kpiCombinedDaily($ids, $dailyByMsId);
    $completedDate = $target > 0 ? kpiCompletedDate($combinedDaily, $target) : null;

    // 1. Monthly Napkin Sales Target Achievement (15%) — capped at 100%
    // since overachievement beyond that is scored separately by KPI #2
    // below.
    $salesAchPct = $target > 0 ? ($achieved / $target) : 0.0;
    $salesAchPctCapped = min(1, $salesAchPct);

    // 3. Weekly Sales Target Achievement (5%) — a real week-by-week count
    // now (how many of the selected range's own calendar weeks hit THAT
    // week's own prorated target), not a repeat of KPI #1's overall %.
    // PLAN/ACHIEVED show as "weeks met / total weeks" (e.g. 2/5), and how
    // many weeks that denominator even IS depends entirely on the selected
    // range/month — same Sun-Sat weeks ($kx['kpiWeeks']) already shown in
    // this page's own "Weekly" breakdown button/modal, so the two always
    // agree. 2026-11-24, user-requested ("5 week la 2 week than achieve
    // panni irukaru apo 2/5 nu vvaranum... antha month la ethana week
    // calender la katttutho athu ttha varanum").
    $kpiWeeksForScoring = $kx['kpiWeeks'] ?? [];
    $weeklyPerDayTarget = $daysInRange > 0 ? ($target / $daysInRange) : 0.0;
    $weeklyWeeksTotal = count($kpiWeeksForScoring);
    $weeklyWeeksMet = 0;
    foreach ($kpiWeeksForScoring as $_wk) {
        $_wkTarget = $weeklyPerDayTarget * $_wk['days'];
        $_wkAchieved = kpiSumInRange($combinedDaily, $_wk['start'], $_wk['end']);
        if ($_wkTarget > 0 && $_wkAchieved >= $_wkTarget) { $weeklyWeeksMet++; }
    }
    $weeklyAchPct = $weeklyWeeksTotal > 0 ? ($weeklyWeeksMet / $weeklyWeeksTotal) : 0.0;

    // 2. District Sales Target Overachievement (10%) — company-wide
    // ranking: only eligible (>100%) here, scored proportionally against
    // the best achiever among ALL active company DMs for this same range.
    $overachPct = ($target > 0 && $salesAchPct > 1 && $kx['companyMaxOverachPct'] > 0)
        ? min(1, $salesAchPct / $kx['companyMaxOverachPct'])
        : 0.0;

    // 4. Early Month Target Achievement (5%) — 2026-12-01, user-redefined
    // away from "quarter of the selected range" to days-before-the-REAL-
    // calendar-month-end (not the range's own end): completed ON month-end
    // day = 0, 1 day before = 1, 2 before = 2, 3 before = 3, 4 before = 4,
    // 5+ days before = 5 (the capped "4 + 1 bonus point" the user described)
    // — so Plan is 5, not 4.
    //
    // $completedDate (kpiCompletedDate(), above) is the raw day the running
    // total first reaches target — but a target crossed partway through a
    // day doesn't mean that SAME day opened with Balance already 0, only
    // the NEXT day does. User explicitly wants credit based on the first
    // day that opens already fully done ("innaikku morning 12 clock eluntha
    // na balance 0 nu varanum" — e.g. crossed on the 27th -> credited date
    // is the 28th, not the 27th), so +1 day is applied before measuring
    // against month-end.
    $earlyMonthAch = 0;
    if ($completedDate !== null) {
        $_earlyMonthCreditDate = date('Y-m-d', strtotime($completedDate . ' +1 day'));
        // $toDate isn't itself a param here — reconstructed from $fromDate +
        // $daysInRange (both already passed in), same range this function's
        // caller already built, so this stays in sync without adding a
        // redundant third date param.
        $_earlyMonthRangeEnd = date('Y-m-d', strtotime($fromDate . ' +' . ($daysInRange - 1) . ' days'));
        $_earlyMonthMonthEnd = date('Y-m-t', strtotime($_earlyMonthRangeEnd));
        $_earlyMonthDaysBefore = (int)floor((strtotime($_earlyMonthMonthEnd) - strtotime($_earlyMonthCreditDate)) / 86400);
        $earlyMonthAch = max(0, min(5, $_earlyMonthDaysBefore));
    }
    $earlyMonthAchPct = $earlyMonthAch / 5;

    // 6. Monthly New Shop Onboarding (5%) — total new shops, summed across
    // the subtree (shops don't overlap between different DMs' areas).
    $newShopAch = 0;
    foreach ($ids as $mid) { $newShopAch += $newShopCountByMsId[$mid] ?? 0; }
    $newShopEligible = $newShopAch >= $kx['newShopEligibility'];
    $newShopAchPct = ($newShopEligible && $kx['companyMaxNewShopEligible'] > 0) ? min(1, $newShopAch / $kx['companyMaxNewShopEligible']) : 0.0;

    // 7. New Category Outlets Onboarding (5%) — DISTINCT shop_category count
    // among this subtree's new shops (e.g. "Super Markets", "Medicals" —
    // ~25 categories exist in total), NOT a shop count. Previously this was
    // literally $newShopAch (the #6 shop count) per a 2026-11-17 decision,
    // which could run into the hundreds and made no sense labeled
    // "Category" — corrected 2026-11-30 after the user compared it against
    // the real shop_category list. Union across the subtree (not summed),
    // same as market-work days above — two different DMs onboarding a shop
    // in the same category should count as one category covered, not two.
    $categorySet = [];
    foreach ($ids as $mid) {
        foreach (($kx['newShopCategorySetByMsId'][$mid] ?? []) as $catId) { $categorySet[$catId] = true; }
    }
    $categoryAch = count($categorySet);
    $categoryEligible = $categoryAch >= $kx['newCategoryEligibility'];
    $categoryAchPct = ($categoryEligible && $kx['companyMaxCategoryEligible'] > 0) ? min(1, $categoryAch / $kx['companyMaxCategoryEligible']) : 0.0;
    $catBreakdown = [];
    foreach ($ids as $mid) {
        foreach (($kx['newShopCategoryBreakdownByMsId'][$mid] ?? []) as $catId => $cnt) {
            $label = $kx['shopCatLabelMap'][$catId] ?? ('Category #' . $catId);
            $catBreakdown[$label] = ($catBreakdown[$label] ?? 0) + $cnt;
        }
    }
    arsort($catBreakdown);
    $catTip = '';
    foreach ($catBreakdown as $label => $cnt) { $catTip .= htmlspecialchars($label) . ': ' . $cnt . '<br>'; }
    if ($catTip === '') { $catTip = 'No shops onboarded in this range'; }

    // 5. Daily Market Work & Get Order KPI (10%) — union of active days
    // across the subtree, against the range's working-day count.
    $marketWorkAch = kpiMarketWorkUnionDays($ids, $marketWorkDatesByMsId);
    $marketWorkAchPct = $workingDaysPlan > 0 ? ($marketWorkAch / $workingDaysPlan) : 0.0;

    // 8. Retail Shop Merchandising Compliance (5%) — % of this subtree's
    // onboarded-and-billable shops that haven't gone 3+ months without an
    // order (see kpiMerchandisingStats() doc comment above).
    $merch = kpiSubtreeSumGeneric($id, $byManager, $kx['merchandisingStatsByMsId'], ['total', 'compliant']);
    $merchPct = $merch['total'] > 0 ? ($merch['compliant'] / $merch['total']) : 0.0;

    // 9. Sales Return Control (deduct 10) — both brands checked
    // independently; either one over 5% triggers the full deduction.
    $ret = kpiSubtreeSumGeneric($id, $byManager, $kx['returnControlStatsByMsId'], ['napkin_turnover', 'napkin_return', 'diaper_turnover', 'diaper_return']);
    $napkinRetPct = $ret['napkin_turnover'] > 0 ? ($ret['napkin_return'] / $ret['napkin_turnover']) : 0.0;
    $diaperRetPct = $ret['diaper_turnover'] > 0 ? ($ret['diaper_return'] / $ret['diaper_turnover']) : 0.0;
    $returnBreach = ($ret['napkin_turnover'] > 0 && $napkinRetPct > 0.05) || ($ret['diaper_turnover'] > 0 && $diaperRetPct > 0.05);
    $returnPenalty = $returnBreach ? -10 : 0;
    $returnTip = 'Femi9 Napkin: ' . number_format($napkinRetPct * 100, 1) . '% (₹' . inr_format($ret['napkin_return'], 0) . ' / ₹' . inr_format($ret['napkin_turnover'], 0) . ')'
        . '<br>Lumi9 Diaper: ' . number_format($diaperRetPct * 100, 1) . '% (₹' . inr_format($ret['diaper_return'], 0) . ' / ₹' . inr_format($ret['diaper_turnover'], 0) . ')';

    $kpiScoreTotal = ($salesAchPctCapped * 15) + ($overachPct * 10) + ($weeklyAchPct * 5) + ($earlyMonthAchPct * 5)
        + ($marketWorkAchPct * 10) + ($newShopAchPct * 5) + ($categoryAchPct * 5) + ($merchPct * 5) + $returnPenalty;

    return [
        'id' => $id, 'name' => $row['ms_name'], 'level_name' => $row['level_name'] ?: '-', 'color' => $color,
        'depth' => $depth, 'parent_id' => $parentId, 'has_children' => !empty($byManager[$id] ?? []),
        'target' => $target, 'achieved' => $achieved, 'sales_ach_pct' => $salesAchPct, 'sales_ach_pct_capped' => $salesAchPctCapped,
        'overach_pct' => $overachPct,
        'weekly_weeks_total' => $weeklyWeeksTotal, 'weekly_weeks_met' => $weeklyWeeksMet, 'weekly_ach_pct' => $weeklyAchPct,
        'early_month_ach' => $earlyMonthAch, 'early_month_ach_pct' => $earlyMonthAchPct,
        'working_days_plan' => $workingDaysPlan, 'market_work_ach' => $marketWorkAch, 'market_work_ach_pct' => $marketWorkAchPct,
        'new_shop_eligibility' => $kx['newShopEligibility'], 'new_shop_ach' => $newShopAch, 'new_shop_eligible' => $newShopEligible, 'new_shop_ach_pct' => $newShopAchPct,
        'new_category_eligibility' => $kx['newCategoryEligibility'], 'category_ach' => $categoryAch, 'category_eligible' => $categoryEligible, 'category_ach_pct' => $categoryAchPct, 'cat_tip' => $catTip,
        'merch_total' => $merch['total'], 'merch_compliant' => $merch['compliant'], 'merch_pct' => $merchPct,
        'napkin_ret_pct' => $napkinRetPct, 'diaper_ret_pct' => $diaperRetPct, 'return_breach' => $returnBreach, 'return_penalty' => $returnPenalty, 'return_tip' => $returnTip,
        'kpi_score_total' => $kpiScoreTotal,
    ];
}

// Thin HTML-row wrapper around computeKpiRowValues() — recurses over the
// subtree exactly as before, now just formatting whatever that function
// already computed instead of also computing it inline.
function renderKpiRow(
    array $row, array $byManager, array $rawStats, array $dailyByMsId, array $levelColorMap,
    int $depth, string $fromDate, int $daysInRange,
    array $newShopCountByMsId, array $marketWorkDatesByMsId, int $workingDaysPlan, int $newShopPlan,
    array $kx, int $parentId = 0
): string {
    $v = computeKpiRowValues($row, $byManager, $rawStats, $dailyByMsId, $levelColorMap, $depth, $fromDate, $daysInRange, $newShopCountByMsId, $marketWorkDatesByMsId, $workingDaysPlan, $kx, $parentId);
    $id = $v['id'];
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
    $isRoot = $depth === 0;

    $html = '<tr data-id="' . $id . '" data-parent="' . $parentId . '"' . ($isRoot ? ' style="background:#f8fafc;"' : '') . '>';
    $html .= '<td class="kpi-name">' . $indent . ($depth > 0 ? '&#8618;&nbsp;' : '') . ($isRoot ? '<b>' : '') . htmlspecialchars($v['name']) . ($isRoot ? '</b>' : '') . '</td>';
    $html .= '<td><span class="tp-tag" style="color:' . $v['color'] . ';background:' . $v['color'] . '1a;">' . htmlspecialchars($v['level_name']) . '</span></td>';

    // 1. Monthly Napkin Sales Target Achievement (15%)
    $html .= '<td>&#8377;' . inr_format($v['target'], 0) . '</td>';
    $html .= '<td>&#8377;' . inr_format($v['achieved'], 0) . '</td>';
    $html .= '<td>' . number_format($v['sales_ach_pct'] * 100, 0) . '%</td>';
    $html .= kpiScoreCell($v['sales_ach_pct_capped'], 15);

    // 2. District Sales Target Overachievement (10%)
    $html .= '<td>&gt;100%</td>';
    $html .= '<td>' . number_format($v['sales_ach_pct'] * 100, 0) . '%</td>';
    $html .= '<td>' . ($v['overach_pct'] > 0 ? number_format($v['overach_pct'] * 100, 0) . '%' : '-') . '</td>';
    $html .= kpiScoreCell($v['overach_pct'], 10);

    // 3. Weekly Sales Target Achievement (5%) — weeks met out of the
    // selected range's own calendar weeks, not a repeat of KPI #1.
    $html .= '<td>' . $v['weekly_weeks_total'] . '</td>';
    $html .= '<td>' . $v['weekly_weeks_met'] . '</td>';
    $html .= '<td>' . number_format($v['weekly_ach_pct'] * 100, 0) . '%</td>';
    $html .= kpiScoreCell($v['weekly_ach_pct'], 5);

    // 4. Early Month Target Achievement (5%)
    $html .= '<td>5</td>';
    $html .= '<td>' . $v['early_month_ach'] . '</td>';
    $html .= '<td>' . number_format($v['early_month_ach_pct'] * 100, 0) . '%</td>';
    $html .= kpiScoreCell($v['early_month_ach_pct'], 5);

    // 5. Daily Market Visit & Order Achievement (10%)
    $html .= '<td>' . $v['working_days_plan'] . '</td>';
    $html .= '<td>' . $v['market_work_ach'] . '</td>';
    $html .= '<td>' . number_format($v['market_work_ach_pct'] * 100, 0) . '%</td>';
    $html .= kpiScoreCell($v['market_work_ach_pct'], 10);

    // 6. Monthly New Shop Onboarding (5%)
    $html .= '<td>' . $v['new_shop_eligibility'] . '</td>';
    $html .= '<td>' . $v['new_shop_ach'] . '</td>';
    $html .= '<td>' . ($v['new_shop_eligible'] ? number_format($v['new_shop_ach_pct'] * 100, 0) . '%' : 'Not eligible') . '</td>';
    $html .= kpiScoreCell($v['new_shop_ach_pct'], 5);

    // 7. New Category Outlets Onboarding (5%)
    $html .= '<td>' . $v['new_category_eligibility'] . '</td>';
    $html .= '<td class="kpi-hover-cell" data-kpi-tip="' . htmlspecialchars($v['cat_tip'], ENT_QUOTES) . '">' . $v['category_ach'] . '</td>';
    $html .= '<td>' . ($v['category_eligible'] ? number_format($v['category_ach_pct'] * 100, 0) . '%' : 'Not eligible') . '</td>';
    $html .= kpiScoreCell($v['category_ach_pct'], 5);

    // 8. Retail Shop Merchandising Compliance (5%)
    $html .= '<td>' . $v['merch_total'] . '</td>';
    $html .= '<td>' . $v['merch_compliant'] . '</td>';
    $html .= '<td>' . ($v['merch_total'] > 0 ? number_format($v['merch_pct'] * 100, 0) . '%' : '-') . '</td>';
    $html .= kpiScoreCell($v['merch_pct'], 5);

    // 9. Sales Return Control (deduct up to 10)
    $html .= '<td class="kpi-hover-cell" data-kpi-tip="' . htmlspecialchars($v['return_tip'], ENT_QUOTES) . '">'
        . number_format(max($v['napkin_ret_pct'], $v['diaper_ret_pct']) * 100, 1) . '%</td>';
    $html .= '<td>' . ($v['return_breach'] ? '<span style="color:#b91c1c;font-weight:600;">Exceeds 5%</span>' : '<span style="color:#166534;">Within 5%</span>') . '</td>';
    $html .= '<td style="text-align:center;">' . ($v['return_penalty'] !== 0 ? '<b style="color:#b91c1c;">' . $v['return_penalty'] . '</b>' : '0') . '</td>';

    $html .= '<td><b style="font-size:14px;">' . number_format($v['kpi_score_total'], 1) . ' / 60</b></td>';
    $html .= '</tr>';

    $children = $byManager[$id] ?? [];
    foreach ($children as $child) {
        $html .= renderKpiRow($child, $byManager, $rawStats, $dailyByMsId, $levelColorMap, $depth + 1, $fromDate, $daysInRange, $newShopCountByMsId, $marketWorkDatesByMsId, $workingDaysPlan, $newShopPlan, $kx, $id);
    }
    return $html;
}

// Solo view — a DM with no subordinates (the common case: most DMs have no
// team) sees their own 10 KPI components as individual cards instead of one
// row crammed into a 40+-column table that needs horizontal scrolling to
// read on a phone. Each card carries the same Plan/Achieved/Ach%/Score this
// person would see as a column group in the table view, plus the plain-
// language explanation of what that KPI measures. User request, 2026-11-21.
function renderKpiCards(array $v): string {
    $cards = [];

    $cards[] = ['icon' => 'storefront', 'title' => '1. Napkin Sales Target Achievement', 'weight' => 15,
        'desc' => 'Achieve 100% of your assigned monthly district napkin sales target.',
        'plan' => '&#8377;' . inr_format($v['target'], 0), 'ach' => '&#8377;' . inr_format($v['achieved'], 0),
        'pct' => number_format($v['sales_ach_pct'] * 100, 0) . '%', 'score' => $v['sales_ach_pct_capped'] * 15, 'max' => 15];

    $cards[] = ['icon' => 'trending_up', 'title' => '2. District Sales Overachievement', 'weight' => 10,
        'desc' => 'Only counts if you cross 100% of target. Scored against the best over-achiever among ALL company DMs this period.',
        'plan' => '&gt;100%', 'ach' => number_format($v['sales_ach_pct'] * 100, 0) . '%',
        'pct' => $v['overach_pct'] > 0 ? (number_format($v['overach_pct'] * 100, 0) . '% of top achiever') : 'Not eligible', 'score' => $v['overach_pct'] * 10, 'max' => 10];

    $cards[] = ['icon' => 'calendar_view_week', 'title' => '3. Weekly Sales Target Achievement', 'weight' => 5,
        'desc' => 'How many of this range\'s own calendar weeks hit that week\'s own target. Use the "Weekly" button below for the week-by-week breakdown.',
        'plan' => $v['weekly_weeks_total'] . ' weeks', 'ach' => $v['weekly_weeks_met'] . ' weeks',
        'pct' => number_format($v['weekly_ach_pct'] * 100, 0) . '%', 'score' => $v['weekly_ach_pct'] * 5, 'max' => 5];

    $cards[] = ['icon' => 'bolt', 'title' => '4. Early Month Target Achievement', 'weight' => 5,
        'desc' => 'Scored by how many days before the actual calendar month-end your target was fully done (credited from the day AFTER it crossed, since that\'s the first day Balance truly opens at 0) — 5+ days early = full marks, day-of-month-end = 0.',
        'plan' => '5', 'ach' => (string)$v['early_month_ach'],
        'pct' => number_format($v['early_month_ach_pct'] * 100, 0) . '%', 'score' => $v['early_month_ach_pct'] * 5, 'max' => 5];

    $cards[] = ['icon' => 'storefront', 'title' => '5. Daily Market Visit & Order', 'weight' => 10,
        'desc' => 'Visit shops and raise a Get Order on as many working days (Mon-Sat) of this period as possible.',
        'plan' => (string)$v['working_days_plan'], 'ach' => (string)$v['market_work_ach'],
        'pct' => number_format($v['market_work_ach_pct'] * 100, 0) . '%', 'score' => $v['market_work_ach_pct'] * 10, 'max' => 10];

    $cards[] = ['icon' => 'add_business', 'title' => '6. New Shop Onboarding', 'weight' => 5,
        'desc' => 'Onboard at least ' . $v['new_shop_eligibility'] . ' new shops this period to be eligible — scored against the top onboarder among ALL company DMs.',
        'plan' => (string)$v['new_shop_eligibility'], 'ach' => (string)$v['new_shop_ach'],
        'pct' => $v['new_shop_eligible'] ? (number_format($v['new_shop_ach_pct'] * 100, 0) . '%') : 'Not eligible', 'score' => $v['new_shop_ach_pct'] * 5, 'max' => 5];

    $cards[] = ['icon' => 'category', 'title' => '7. New Category Outlets Onboarding', 'weight' => 5,
        'desc' => 'Distinct shop categories (Super Markets, Medicals, etc.) with at least 1 new shop this period — eligible at ' . $v['new_category_eligibility'] . '+.',
        'plan' => (string)$v['new_category_eligibility'], 'ach' => (string)$v['category_ach'],
        'pct' => $v['category_eligible'] ? (number_format($v['category_ach_pct'] * 100, 0) . '%') : 'Not eligible', 'score' => $v['category_ach_pct'] * 5, 'max' => 5,
        'tip' => $v['cat_tip']];

    $cards[] = ['icon' => 'checklist', 'title' => '8. Retail Shop Merchandising Compliance', 'weight' => 5,
        'desc' => '% of your onboarded-and-billable shops that have ordered each category (Napkin/Diaper) they ever bought within the last 3 months.',
        'plan' => (string)$v['merch_total'], 'ach' => (string)$v['merch_compliant'],
        'pct' => $v['merch_total'] > 0 ? (number_format($v['merch_pct'] * 100, 0) . '%') : '-', 'score' => $v['merch_pct'] * 5, 'max' => 5];

    // 9. Monthly Promotional Activities (5%) deliberately omitted — no DB
    // signal anywhere in this system, scored manually outside this page
    // entirely, so it never counted toward kpi_score_total (which is why
    // the total below is out of 60, not the full 65). 2026-11-23,
    // user-requested ("namma kitta data illai so atha remove pannitu 60 ku
    // mattum clarify panna pothum" — we don't have the data, remove it and
    // just show the score out of 60).

    $cards[] = ['icon' => 'warning', 'title' => '9. Sales Return Control', 'weight' => -10,
        'desc' => 'Keep Napkin AND Diaper returns each under 5% of their own turnover — either one over 5% deducts the full 10.',
        'plan' => 'Under 5%', 'ach' => number_format(max($v['napkin_ret_pct'], $v['diaper_ret_pct']) * 100, 1) . '%',
        'pct' => $v['return_breach'] ? 'Exceeds 5%' : 'Within 5%', 'score' => $v['return_penalty'], 'max' => 0,
        'tip' => $v['return_tip']];

    $html = '<div class="kpi-card-grid">';
    foreach ($cards as $c) {
        $scoreColor = $c['score'] === null ? '#9ca3af' : ($c['score'] >= 0 ? ($c['score'] >= $c['max'] * 0.7 ? '#166534' : ($c['score'] > 0 ? '#b45309' : '#9ca3af')) : '#b91c1c');
        $scoreText = $c['score'] === null ? 'N/A' : (number_format($c['score'], 1) . ' / ' . ($c['weight'] < 0 ? '-10' : $c['weight']));
        $tipAttr = !empty($c['tip']) ? ' class="kpi-hover-cell" data-kpi-tip="' . htmlspecialchars($c['tip'], ENT_QUOTES) . '"' : '';
        $html .= '<div class="kpi-card">';
        $html .= '<div class="kpi-card-head"><i class="material-icons-outlined">' . $c['icon'] . '</i><span>' . htmlspecialchars($c['title']) . '</span></div>';
        $html .= '<p class="kpi-card-desc">' . htmlspecialchars($c['desc']) . '</p>';
        $html .= '<div class="kpi-card-stats">';
        $html .= '<div><span class="kpi-card-label">Plan</span><span class="kpi-card-val">' . $c['plan'] . '</span></div>';
        $html .= '<div' . $tipAttr . '><span class="kpi-card-label">Achieved</span><span class="kpi-card-val">' . $c['ach'] . '</span></div>';
        $html .= '<div><span class="kpi-card-label">Status</span><span class="kpi-card-val">' . htmlspecialchars((string)$c['pct']) . '</span></div>';
        $html .= '</div>';
        $html .= '<div class="kpi-card-score" style="color:' . $scoreColor . ';">' . $scoreText . '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '<div class="kpi-card-total">Total KPI Score: <b>' . number_format($v['kpi_score_total'], 1) . ' / 60</b></div>';
    return $html;
}
