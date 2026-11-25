<?php
// Month-end TP feedback — on the last calendar day of every month, each TP
// is asked (via a popup on their own dashboard) to rate their DM and their
// SalesBDM separately, 5 star-questions each. Stars are still stored 1-5
// (q1..q5, the raw rating the TP picked), but each question is worth at most
// 1 mark, not 5 — a star maps to 0.2/0.4/0.6/0.8/1.0, so 5 questions sum to
// a submission score out of 5 (see getCumulativeTpFeedbackScore()'s
// `(q1+..+q5) * 0.2`). 2026-11-30, user-corrected (was out of 25 — 1 point
// per star — until "apdi venam... oru question ku oru mark than": each
// question should max out at 1 mark via the 0.2-per-star scale, not 5).
// One submission per TP per target per month (re-submitting the same month
// overwrites, via ON DUPLICATE KEY). The DM's own `marketing/kpi.php` "Your
// Total Score" card shows the cumulative average across whichever months
// fall in the page's own date range (see getCumulativeTpFeedbackScore()'s
// own comment), and a per-TP breakdown is shown only when the page is being
// viewed via the Company "login as this DM" bridge, never on the DM's own
// direct login (kept anonymous there, by design). 2026-11-24, user-requested
// ("month last la tp login panna udaney... share your feedback... 1st star
// ku kila one point... dm login la direct ahh partha antha tp yaaru yaaru
// evvalvau score pottu irukaganu katta kudathu... company login la irunthu
// avnga login ku pona kattanum").

function ensureTpStaffFeedbackTable($db_conn): void {
    static $checked = false;
    if ($checked) { return; }
    $checked = true;
    $db_conn->query("CREATE TABLE IF NOT EXISTS tp_staff_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tp_id INT NOT NULL,
        target_type ENUM('dm','salesbdm') NOT NULL,
        target_id INT NOT NULL,
        period_month CHAR(7) NOT NULL,
        q1 TINYINT NOT NULL, q2 TINYINT NOT NULL, q3 TINYINT NOT NULL, q4 TINYINT NOT NULL, q5 TINYINT NOT NULL,
        submitted_at DATETIME NOT NULL,
        UNIQUE KEY uk_tp_target_month (tp_id, target_type, target_id, period_month),
        KEY idx_target (target_type, target_id)
    )");
}

// The 5 questions asked for each target — same wording shown on the TP's
// popup and (for reference) nowhere else. Kept here, not duplicated, so
// both the popup and any future export/report use identical text.
function tpStaffFeedbackQuestions(string $targetType): array {
    if ($targetType === 'salesbdm') {
        return [
            "How would you rate your SalesBDM's support in resolving your issues?",
            "How satisfied are you with your SalesBDM's guidance on sales/targets?",
            "How would you rate your SalesBDM's communication and responsiveness?",
            "How fair and transparent is your SalesBDM in handling your concerns?",
            "Overall, how satisfied are you with your SalesBDM's support this month?",
        ];
    }
    return [
        "How would you rate your DM's support in resolving your issues?",
        "How satisfied are you with the stock/product availability support from your DM?",
        "How would you rate your DM's communication and guidance?",
        "How fair and transparent is your DM in assigning Firkas/territories?",
        "Overall, how satisfied are you with your DM's support this month?",
    ];
}

// A TP's own DM(s) — district-depth marketing_staff assignment covering the
// TP's own assigned district(s) (territory-partner/include/TpDistrictScope.php's
// getTpAssignedDistricts(), name-matched against marketing_staff_locations,
// same convention marketing/include/MsDistrictScope.php's
// getDistrictManagerNames() already uses, just returning ids too).
function getTpAssignedDmRows($db_conn, int $tpId): array {
    require_once __DIR__ . '/../territory-partner/include/TpDistrictScope.php';
    $names = getTpAssignedDistrictNames($db_conn, $tpId);
    if (empty($names)) { return []; }
    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $types = str_repeat('s', count($names));
    $normalized = array_map(fn($n) => mb_strtolower(trim($n)), $names);
    $stmt = $db_conn->prepare(
        "SELECT DISTINCT ms.id, ms.ms_name
         FROM marketing_staff_locations msl
         JOIN partner_location_nodes d ON d.id = msl.location_id AND d.depth = 3
         JOIN marketing_staff ms ON ms.id = msl.ms_id AND ms.deleted_at IS NULL
         JOIN marketing_team_levels tl ON tl.id = ms.team_level_id AND tl.level_name LIKE '%District%'
         WHERE LOWER(TRIM(d.name)) IN ($placeholders)"
    );
    $stmt->bind_param($types, ...$normalized);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// A TP's own SalesBDM(s) — same district-name match as
// salesbdm/include/BdmTpScope.php's getBdmAssignedDistrictNames(), checked
// in reverse (every active BDM's own personal district list against this
// TP's district names) since there's no direct FK either way.
function getTpAssignedSalesBdmRows($db_conn, int $tpId): array {
    require_once __DIR__ . '/../territory-partner/include/TpDistrictScope.php';
    require_once __DIR__ . '/../salesbdm/include/BdmTpScope.php';
    $tpDistricts = array_map('mb_strtolower', array_map('trim', getTpAssignedDistrictNames($db_conn, $tpId)));
    if (empty($tpDistricts)) { return []; }

    $out = [];
    $res = $db_conn->query("SELECT id, bdm_name FROM sales_bdm_staff WHERE account_status='active'");
    while ($bdm = $res->fetch_assoc()) {
        $bdmDistricts = array_map('mb_strtolower', array_map('trim', getBdmAssignedDistrictNames($db_conn, (int)$bdm['id'])));
        if (array_intersect($tpDistricts, $bdmDistricts)) {
            $out[] = ['id' => (int)$bdm['id'], 'ms_name' => $bdm['bdm_name']];
        }
    }
    return $out;
}

function hasSubmittedTpFeedbackThisMonth($db_conn, int $tpId, string $targetType, int $targetId, string $yearMonth): bool {
    ensureTpStaffFeedbackTable($db_conn);
    $stmt = $db_conn->prepare("SELECT id FROM tp_staff_feedback WHERE tp_id=? AND target_type=? AND target_id=? AND period_month=? LIMIT 1");
    $stmt->bind_param('isis', $tpId, $targetType, $targetId, $yearMonth);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$found;
}

function saveTpStaffFeedback($db_conn, int $tpId, string $targetType, int $targetId, string $yearMonth, array $scores): void {
    ensureTpStaffFeedbackTable($db_conn);
    $q = array_map(fn($v) => max(1, min(5, (int)$v)), array_pad($scores, 5, 1));
    $stmt = $db_conn->prepare(
        "INSERT INTO tp_staff_feedback (tp_id, target_type, target_id, period_month, q1, q2, q3, q4, q5, submitted_at)
         VALUES (?,?,?,?,?,?,?,?,?,NOW())
         ON DUPLICATE KEY UPDATE q1=VALUES(q1), q2=VALUES(q2), q3=VALUES(q3), q4=VALUES(q4), q5=VALUES(q5), submitted_at=NOW()"
    );
    $stmt->bind_param('isisiiiii', $tpId, $targetType, $targetId, $yearMonth, $q[0], $q[1], $q[2], $q[3], $q[4]);
    $stmt->execute();
    $stmt->close();
}

// Cumulative score for one DM/SalesBDM, scoped to whichever months fall
// inside [$fromDate, $toDate] (same KPI-page date range already used for
// every other Layer 1 figure on that page) — e.g. selecting a past month's
// range shows THAT month's own mark, not every month ever. $fromDate/$toDate
// are plain Y-m-d dates; omit both for the true all-time figure (used by
// nothing on kpi.php right now, kept for completeness). A rating belongs to
// the month it was FOR (period_month), not necessarily its submitted_at
// date. 2026-11-24, user-requested ("pona month filter pottalaum athu pona
// month potta mark than kattanum" — a past-month filter must show that past
// month's own mark, not an all-time cumulative).
//
// $rows is the per-TP breakdown, only meant to be shown to Company (never
// the DM/BDM's own direct login — see this file's own top comment).
function getCumulativeTpFeedbackScore($db_conn, string $targetType, int $targetId, ?string $fromDate = null, ?string $toDate = null): array {
    ensureTpStaffFeedbackTable($db_conn);
    $sql = "SELECT f.tp_id, tp.name AS tp_name, tp.tp_id AS tp_code, f.period_month,
                   (f.q1+f.q2+f.q3+f.q4+f.q5) * 0.2 AS score_5, f.submitted_at
            FROM tp_staff_feedback f
            LEFT JOIN territory_partners tp ON tp.id = f.tp_id
            WHERE f.target_type=? AND f.target_id=?";
    $types = 'si';
    $params = [$targetType, $targetId];
    if ($fromDate !== null && $toDate !== null) {
        $sql .= " AND f.period_month BETWEEN ? AND ?";
        $types .= 'ss';
        $params[] = date('Y-m', strtotime($fromDate));
        $params[] = date('Y-m', strtotime($toDate));
    }
    $sql .= " ORDER BY f.period_month DESC, tp.name ASC";
    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $count = count($rows);
    $avg5 = $count > 0 ? array_sum(array_column($rows, 'score_5')) / $count : 0.0;
    return ['count' => $count, 'avg_score_5' => $avg5, 'rows' => $rows];
}
