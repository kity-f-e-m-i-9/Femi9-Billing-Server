<?php
/**
 * ============================================================================
 * BACKFILL TOOL: Missing Daily Login Rewards
 * Femi9 Billing Application — Admin Utility
 * ============================================================================
 *
 * Source table  : `invoice`  (columns: user_type, user_id, date, inv_id, inv_number)
 * Reward table  : `daily_login_rewards`
 * Audit table   : `daily_reward_audit_log`
 *
 * Point Rules (per user per billing day):
 *   super_stockiest  → 2 pts  |  stockiest         → 2 pts
 *   distributor      → 1 pt   |  super_distributor → 1 pt
 *
 * Logic:
 *   1. UNION invoice + user_invoice to cover all billing tables
 *   2. Group by (user_type, user_id, billing_date) — 1 reward per user per day
 *   3. Skip if a row already exists in daily_login_rewards for that (user, date)
 *   4. No status filter — all invoice rows count
 *   5. Results displayed grouped by billing date, with all users under each date
 *
 * Rollback safety: every inserted row tagged notes='backfill_jan2025'
 * ============================================================================
 */

declare(strict_types=1);

include("checksession.php");
include("config.php");

// ============================================================================
// CONFIG
// ============================================================================
const BACKFILL_START  = '2026-01-01';
const BACKFILL_SOURCE = 'backfill_jan2025';
const BACKFILL_ADMIN  = 'backfill_script';

$BACKFILL_END = date('Y-m-d');

$POINT_MAP = [
    'super_stockiest'   => 2,
    'stockiest'         => 2,
    'distributor'       => 1,
    'super_distributor' => 1,
];

$USER_LABELS = [
    'super_stockiest'   => 'Super Stockist',
    'stockiest'         => 'Stockist',
    'distributor'       => 'Distributor',
    'super_distributor' => 'Super Distributor',
];

$BADGE_CLASS = [
    'super_stockiest'   => 'b-ss',
    'stockiest'         => 'b-st',
    'distributor'       => 'b-d',
    'super_distributor' => 'b-sd',
];

// ============================================================================
// ACTION
// ============================================================================
$action  = $_POST['action'] ?? 'home';
$results = [];

// ============================================================================
// HELPER: Fetch all missing (user_type, user_id, billing_date) combos
//
// Uses UNION of `invoice` (user_type/user_id) and `user_invoice` (from_user_type/from_user_id)
// to cover all invoice sources. Groups by (user_type, user_id, date) — 1 row per user per day.
// Excludes any (user_type, user_id, date) that already has a daily_login_rewards entry.
// ============================================================================
function fetchMissing($db, string $end): array {
    $start = BACKFILL_START;
    $types = "'super_stockiest','stockiest','distributor','super_distributor'";

    $sql = "
        SELECT
            src.user_type,
            src.user_id,
            DATE(src.bill_date)      AS billing_date,
            MIN(src.inv_id)          AS first_invoice_id,
            MIN(src.inv_number)      AS first_invoice_number,
            COUNT(*)                 AS invoice_count
        FROM (
            -- Source 1: invoice table
            SELECT
                user_type,
                user_id,
                `date`      AS bill_date,
                inv_id,
                inv_number
            FROM `invoice`
            WHERE `date` >= '$start'
              AND `date` <= '$end'
              AND user_type IN ($types)
              AND deleted_at IS NULL

            UNION ALL

            -- Source 2: user_invoice table
            SELECT
                from_user_type  AS user_type,
                from_user_id    AS user_id,
                `date`          AS bill_date,
                inv_id,
                inv_number
            FROM `user_invoice`
            WHERE `date` >= '$start'
              AND `date` <= '$end'
              AND from_user_type IN ($types)
              AND deleted_at IS NULL
        ) AS src
        WHERE NOT EXISTS (
            SELECT 1
            FROM daily_login_rewards dlr
            WHERE dlr.user_type   = src.user_type
              AND dlr.user_id     = src.user_id
              AND dlr.reward_date = DATE(src.bill_date)
        )
        GROUP BY src.user_type, src.user_id, DATE(src.bill_date)
        ORDER BY DATE(src.bill_date) ASC, src.user_type, src.user_id
    ";

    $res  = mysqli_query($db, $sql);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
    } else {
        error_log("fetchMissing SQL error: " . mysqli_error($db));
    }
    return $rows;
}

// ============================================================================
// HELPER: Group flat rows by billing_date → [ date => [ rows ] ]
// ============================================================================
function groupByDate(array $rows): array {
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[$row['billing_date']][] = $row;
    }
    return $grouped;
}

// ============================================================================
// HELPER: Fetch backfill-tagged records for rollback
// ============================================================================
function fetchBackfillRecords($db): array {
    $tag = BACKFILL_SOURCE;
    $res = mysqli_query($db,
        "SELECT * FROM daily_login_rewards
         WHERE notes LIKE '%$tag%'
         ORDER BY reward_date DESC, user_type, user_id"
    );
    $rows = [];
    if ($res) while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
    return $rows;
}

// ============================================================================
// PROCESS: PREVIEW
// ============================================================================
if ($action === 'preview') {
    $missing  = fetchMissing($db_conn, $BACKFILL_END);
    $byType   = [];
    $totalPts = 0;
    foreach ($missing as $r) {
        $pts = $POINT_MAP[$r['user_type']] ?? 1;
        $totalPts += $pts;
        $byType[$r['user_type']] = ($byType[$r['user_type']] ?? 0) + 1;
    }
    $results = [
        'grouped'  => groupByDate($missing),
        'flat'     => $missing,
        'total'    => count($missing),
        'points'   => $totalPts,
        'by_type'  => $byType,
    ];
}

// ============================================================================
// PROCESS: EXECUTE
// ============================================================================
if ($action === 'execute') {
    $missing   = fetchMissing($db_conn, $BACKFILL_END);
    $inserted  = 0;
    $failed    = 0;
    $points    = 0;
    $log       = [];  // flat log with status per row
    $ip        = mysqli_real_escape_string($db_conn, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $tag       = BACKFILL_SOURCE;
    $admin     = BACKFILL_ADMIN;

    foreach ($missing as $row) {
        $userType   = mysqli_real_escape_string($db_conn, $row['user_type']);
        $userId     = mysqli_real_escape_string($db_conn, $row['user_id']);
        $date       = $row['billing_date'];
        $invoiceId  = mysqli_real_escape_string($db_conn, $row['first_invoice_id']);
        $invoiceNum = mysqli_real_escape_string($db_conn, $row['first_invoice_number']);
        $pts        = $POINT_MAP[$row['user_type']] ?? 1;

        mysqli_begin_transaction($db_conn);
        try {
            // Insert reward record
            $ins = "INSERT INTO daily_login_rewards
                        (user_type, user_id, reward_date, points_awarded,
                         invoice_id, invoice_number, created_at, notes)
                    VALUES
                        ('$userType','$userId','$date',$pts,
                         '$invoiceId','$invoiceNum', NOW(),'$tag')";
            if (!mysqli_query($db_conn, $ins)) {
                throw new Exception(mysqli_error($db_conn));
            }
            $rewardId = mysqli_insert_id($db_conn);

            // Audit log
            mysqli_query($db_conn,
                "INSERT INTO daily_reward_audit_log
                     (action_type, user_type, user_id, reward_date, points_amount,
                      invoice_id, invoice_number, admin_user, notes, ip_address, created_at)
                 VALUES
                     ('backfill_run','$userType','$userId','$date',$pts,
                      '$invoiceId','$invoiceNum','$admin','$tag','$ip', NOW())"
            );

            mysqli_commit($db_conn);
            $inserted++;
            $points += $pts;
            $log[] = ['status' => 'ok', 'row' => $row, 'pts' => $pts, 'reward_id' => $rewardId];
        } catch (Exception $e) {
            mysqli_rollback($db_conn);
            $failed++;
            $log[] = ['status' => 'fail', 'row' => $row, 'pts' => $pts, 'error' => $e->getMessage()];
        }
    }

    // Group log by date for display
    $grouped_log = [];
    foreach ($log as $entry) {
        $grouped_log[$entry['row']['billing_date']][] = $entry;
    }
    ksort($grouped_log);

    $results = [
        'grouped_log' => $grouped_log,
        'inserted'    => $inserted,
        'failed'      => $failed,
        'points'      => $points,
        'total'       => count($missing),
    ];
}

// ============================================================================
// PROCESS: ROLLBACK
// ============================================================================
if ($action === 'rollback') {
    $records = fetchBackfillRecords($db_conn);
    $deleted  = 0;
    $failed   = 0;
    $points   = 0;
    $log      = [];
    $ip       = mysqli_real_escape_string($db_conn, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $admin    = BACKFILL_ADMIN;
    $tag      = BACKFILL_SOURCE;

    foreach ($records as $row) {
        mysqli_begin_transaction($db_conn);
        try {
            $id = (int)$row['id'];
            if (!mysqli_query($db_conn, "DELETE FROM daily_login_rewards WHERE id = $id")
                || mysqli_affected_rows($db_conn) === 0) {
                throw new Exception("Delete failed for ID $id");
            }
            $ut   = mysqli_real_escape_string($db_conn, $row['user_type']);
            $uid  = mysqli_real_escape_string($db_conn, $row['user_id']);
            $rd   = $row['reward_date'];
            $pts  = (int)$row['points_awarded'];
            $inv  = mysqli_real_escape_string($db_conn, $row['invoice_id']);
            $invn = mysqli_real_escape_string($db_conn, $row['invoice_number']);
            mysqli_query($db_conn,
                "INSERT INTO daily_reward_audit_log
                     (action_type, user_type, user_id, reward_date, points_amount,
                      invoice_id, invoice_number, admin_user, notes, ip_address, created_at)
                 VALUES
                     ('backfill_rollback','$ut','$uid','$rd',$pts,
                      '$inv','$invn','$admin','Rollback of $tag (reward #$id)','$ip', NOW())"
            );
            mysqli_commit($db_conn);
            $deleted++;
            $points += $pts;
            $log[] = ['status' => 'ok', 'row' => $row];
        } catch (Exception $e) {
            mysqli_rollback($db_conn);
            $failed++;
            $log[] = ['status' => 'fail', 'row' => $row, 'error' => $e->getMessage()];
        }
    }

    $grouped_log = [];
    foreach ($log as $entry) {
        $grouped_log[$entry['row']['reward_date']][] = $entry;
    }
    krsort($grouped_log); // newest first for rollback

    $results = [
        'grouped_log' => $grouped_log,
        'deleted'     => $deleted,
        'failed'      => $failed,
        'points'      => $points,
        'total'       => count($records),
    ];
}

// ============================================================================
// HOME STATS
// ============================================================================
if ($action === 'home') {
    $allMissing     = fetchMissing($db_conn, $BACKFILL_END);
    $missing_count  = count($allMissing);
    $backfill_count = count(fetchBackfillRecords($db_conn));
    $stat = mysqli_fetch_assoc(mysqli_query($db_conn,
        "SELECT COUNT(*) as cnt, COALESCE(SUM(points_awarded),0) as pts
         FROM daily_login_rewards
         WHERE reward_date >= '".BACKFILL_START."'"));
    $missing_breakdown = [];
    $missing_pts_total = 0;
    foreach ($allMissing as $r) {
        $missing_breakdown[$r['user_type']] = ($missing_breakdown[$r['user_type']] ?? 0) + 1;
        $missing_pts_total += $POINT_MAP[$r['user_type']] ?? 1;
    }
    // Date range of missing
    $dates = array_column($allMissing, 'billing_date');
    $missing_date_min = $dates ? min($dates) : null;
    $missing_date_max = $dates ? max($dates) : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reward Backfill — Femi9 Admin</title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#0c0e14;--panel:#12151e;--card:#161924;--border:#1e2336;
    --text:#c8cfe8;--muted:#5a6180;
    --blue:#4f8ef7;--green:#3ecf7a;--yellow:#f5c842;
    --red:#f05c5c;--orange:#f0884a;--purple:#9b6ff7;
    --r:10px;--mono:'JetBrains Mono',monospace;--sans:'Sora',sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--sans);background:var(--bg);color:var(--text);min-height:100vh;padding-bottom:60px}

/* HEADER */
.hdr{background:var(--panel);border-bottom:1px solid var(--border);padding:16px 32px;display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:50}
.hdr-logo{width:36px;height:36px;background:linear-gradient(135deg,#4f8ef7,#9b6ff7);border-radius:9px;display:grid;place-items:center;font-size:17px;flex-shrink:0}
.hdr-title{font-size:16px;font-weight:700;color:#fff}
.hdr-sub{font-size:11px;color:var(--muted);margin-top:1px}
.hdr-period{margin-left:auto;font-family:var(--mono);font-size:11px;background:#1a2040;border:1px solid var(--border);padding:4px 12px;border-radius:20px;color:var(--muted)}

.wrap{max-width:1100px;margin:0 auto;padding:26px 22px 0}

/* STAT GRID */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:22px}
.sc{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:16px 18px;position:relative;overflow:hidden}
.sc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px}
.sc.c-blue::before{background:var(--blue)}.sc.c-green::before{background:var(--green)}
.sc.c-yellow::before{background:var(--yellow)}.sc.c-red::before{background:var(--red)}
.sc-lbl{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:6px}
.sc-val{font-family:var(--mono);font-size:24px;font-weight:700;color:#fff}
.sc-sub{font-size:11px;color:var(--muted);margin-top:4px;line-height:1.4}

/* ACTION CARDS */
.action-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-bottom:24px}
.ac{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:18px 20px;cursor:pointer;transition:all .18s;text-align:left;width:100%;font-family:inherit;color:var(--text);display:flex;align-items:center;gap:14px}
.ac:hover{transform:translateY(-2px)}
.ac.a-yellow:hover{border-color:var(--yellow);box-shadow:0 8px 24px rgba(245,200,66,.12)}
.ac.a-green:hover{border-color:var(--green);box-shadow:0 8px 24px rgba(62,207,122,.12)}
.ac.a-red:hover{border-color:var(--red);box-shadow:0 8px 24px rgba(240,92,92,.12)}
.ac-icon{width:40px;height:40px;border-radius:10px;display:grid;place-items:center;font-size:19px;flex-shrink:0}
.ac-icon.i-yellow{background:rgba(245,200,66,.15)}.ac-icon.i-green{background:rgba(62,207,122,.15)}.ac-icon.i-red{background:rgba(240,92,92,.15)}
.ac-lbl{font-size:14px;font-weight:700;color:#fff;display:block}
.ac-sub{font-size:11px;color:var(--muted);display:block;margin-top:2px}

/* CARD */
.rc{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:22px 24px;margin-bottom:18px}
.sec-hdr{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;padding-bottom:13px;border-bottom:1px solid var(--border)}
.sec-hdr h3{font-size:15px;font-weight:700;color:#fff}

/* PILLS */
.pill{font-family:var(--mono);font-size:11px;padding:2px 9px;border-radius:20px;font-weight:600}
.p-blue{background:rgba(79,142,247,.18);color:var(--blue)}
.p-green{background:rgba(62,207,122,.18);color:var(--green)}
.p-red{background:rgba(240,92,92,.18);color:var(--red)}
.p-yellow{background:rgba(245,200,66,.18);color:var(--yellow)}
.p-purple{background:rgba(155,111,247,.18);color:var(--purple)}
.p-orange{background:rgba(240,136,74,.18);color:var(--orange)}

/* SUMMARY BAR */
.sbar{display:flex;flex-wrap:wrap;gap:8px;background:var(--panel);border:1px solid var(--border);border-radius:var(--r);padding:12px 16px;margin-bottom:16px}
.sbi{display:flex;align-items:center;gap:7px}
.sbi-lbl{font-size:11px;color:var(--muted)}
.sbi-val{font-family:var(--mono);font-size:13px;font-weight:700;color:#fff}
.vsep{width:1px;background:var(--border);height:16px;margin:0 3px}

/* BREAKDOWN */
.breakdown{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px}
.bi{background:var(--panel);border:1px solid var(--border);border-radius:8px;padding:12px 14px;text-align:center}
.bi-type{font-size:11px;color:var(--muted);margin-bottom:4px}
.bi-cnt{font-family:var(--mono);font-size:22px;font-weight:700;color:#fff}
.bi-pts{font-size:11px;color:var(--yellow);margin-top:3px}

/* NOTICE */
.notice{border-radius:8px;padding:10px 14px;font-size:12px;display:flex;align-items:flex-start;gap:9px;margin-bottom:14px;line-height:1.6}
.n-blue{background:rgba(79,142,247,.1);border:1px solid rgba(79,142,247,.25);color:#8ab8ff}
.n-green{background:rgba(62,207,122,.1);border:1px solid rgba(62,207,122,.25);color:#7adfa4}
.n-red{background:rgba(240,92,92,.1);border:1px solid rgba(240,92,92,.25);color:#f09090}
.n-yellow{background:rgba(245,200,66,.1);border:1px solid rgba(245,200,66,.25);color:#f5d060}

/* ── DATE GROUP ── */
.date-group{margin-bottom:22px}
.date-hdr{
    display:flex;align-items:center;gap:12px;
    background:#1a1f2e;border:1px solid var(--border);
    border-radius:8px 8px 0 0;
    padding:10px 16px;
    border-bottom:none;
}
.date-hdr .dh-date{
    font-family:var(--mono);font-size:13px;font-weight:700;color:#fff;
    display:flex;align-items:center;gap:8px;
}
.date-hdr .dh-day{font-size:11px;color:var(--muted);font-family:var(--sans);font-weight:400}
.date-hdr .dh-pills{margin-left:auto;display:flex;gap:6px}
.date-body{border:1px solid var(--border);border-radius:0 0 8px 8px;overflow:hidden}

/* TABLE */
table{width:100%;border-collapse:collapse;font-size:12px}
thead th{background:#1a1f30;padding:8px 13px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);text-align:left;white-space:nowrap;border-bottom:1px solid var(--border)}
tbody td{padding:8px 13px;border-bottom:1px solid var(--border);vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover td{background:rgba(255,255,255,.02)}
.mono{font-family:var(--mono);font-size:12px}

/* BADGES */
.badge{display:inline-block;font-family:var(--mono);font-size:10px;padding:2px 7px;border-radius:4px;font-weight:600;white-space:nowrap}
.b-ss{background:rgba(155,111,247,.18);color:var(--purple)}
.b-st{background:rgba(79,142,247,.18);color:var(--blue)}
.b-d{background:rgba(62,207,122,.18);color:var(--green)}
.b-sd{background:rgba(240,136,74,.18);color:var(--orange)}
.b-2pt{background:rgba(245,200,66,.18);color:var(--yellow)}
.b-1pt{background:rgba(62,207,122,.18);color:var(--green)}
.s-ok{color:var(--green);font-weight:600;font-size:11px}
.s-fail{color:var(--red);font-size:11px;font-weight:600}
.s-del{color:var(--red);font-weight:600;font-size:11px}

/* BACK BTN */
.back{display:inline-flex;align-items:center;gap:7px;background:var(--panel);border:1px solid var(--border);padding:7px 14px;border-radius:8px;font-size:12px;color:var(--muted);text-decoration:none;margin-bottom:20px;cursor:pointer;font-family:inherit;transition:color .15s}
.back:hover{color:var(--text)}

/* CONFIRM OVERLAY */
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(5px);z-index:200;place-items:center}
.overlay.active{display:grid}
.cbox{background:var(--panel);border:1px solid var(--border);border-radius:14px;padding:30px 32px;max-width:400px;width:90%;text-align:center}
.cbox-icon{font-size:44px;margin-bottom:12px}
.cbox h3{font-size:17px;font-weight:700;color:#fff;margin-bottom:7px}
.cbox p{font-size:12px;color:var(--muted);line-height:1.7;margin-bottom:22px}
.cbox-btns{display:flex;gap:10px;justify-content:center}
.cbtn{padding:9px 24px;border-radius:8px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;border:none;transition:opacity .15s}
.cbtn:hover{opacity:.85}
.cbtn-cancel{background:var(--card);color:var(--muted);border:1px solid var(--border)}
.cbtn-ok{background:linear-gradient(135deg,#4f8ef7,#9b6ff7);color:#fff}
.cbtn-danger{background:linear-gradient(135deg,#f05c5c,#f0884a);color:#fff}

/* EMPTY */
.empty{text-align:center;padding:52px 20px;color:var(--muted)}
.empty-icon{font-size:44px;margin-bottom:12px}
.empty-title{font-size:16px;font-weight:600;color:var(--text);margin-bottom:6px}
.empty-sub{font-size:12px;line-height:1.7}

code{font-family:var(--mono);background:rgba(255,255,255,.06);padding:1px 5px;border-radius:3px;font-size:11px}
</style>
</head>
<body>

<div class="hdr">
    <div class="hdr-logo">🎯</div>
    <div>
        <div class="hdr-title">Reward Backfill Tool</div>
        <div class="hdr-sub">Daily Login Rewards · Femi9 Billing Admin</div>
    </div>
    <div class="hdr-period">📅 <?= BACKFILL_START ?> → <?= $BACKFILL_END ?></div>
</div>

<!-- CONFIRM OVERLAY -->
<div class="overlay" id="overlay">
    <div class="cbox">
        <div class="cbox-icon" id="cIcon">⚡</div>
        <h3 id="cTitle">Confirm</h3>
        <p id="cDesc">Are you sure?</p>
        <div class="cbox-btns">
            <button class="cbtn cbtn-cancel" onclick="closeOverlay()">Cancel</button>
            <button class="cbtn cbtn-ok" id="cConfirm" onclick="doSubmit()">Confirm</button>
        </div>
    </div>
</div>
<form id="actionForm" method="POST" style="display:none">
    <input type="hidden" name="action" id="hiddenAction">
</form>

<div class="wrap">

<?php
// ────────────────────────────────────────────────────── HOME
if ($action === 'home'):
?>

<div class="stat-grid">
    <div class="sc c-blue">
        <div class="sc-lbl">Missing Rewards</div>
        <div class="sc-val"><?= inr_format($missing_count, 0) ?></div>
        <div class="sc-sub">User-days with invoices but no reward entry</div>
    </div>
    <div class="sc c-yellow">
        <div class="sc-lbl">Points to Award</div>
        <div class="sc-val"><?= inr_format($missing_pts_total, 0) ?></div>
        <div class="sc-sub">
            <?php if ($missing_date_min): ?>
            <?= $missing_date_min ?> → <?= $missing_date_max ?>
            <?php else: ?>All caught up<?php endif; ?>
        </div>
    </div>
    <div class="sc c-green">
        <div class="sc-lbl">Already Rewarded</div>
        <div class="sc-val"><?= inr_format((int)($stat['cnt'] ?? 0), 0) ?></div>
        <div class="sc-sub"><?= inr_format((int)($stat['pts'] ?? 0), 0) ?> pts in daily_login_rewards since Jan 2025</div>
    </div>
    <div class="sc c-red">
        <div class="sc-lbl">Backfill Records</div>
        <div class="sc-val"><?= inr_format($backfill_count, 0) ?></div>
        <div class="sc-sub">Rows inserted by this tool (rollback-safe)</div>
    </div>
</div>

<?php if ($missing_count > 0): ?>
<div class="rc">
    <div class="sec-hdr"><h3>Missing by User Type</h3></div>
    <div class="breakdown">
        <?php foreach ($USER_LABELS as $type => $label):
            $cnt = $missing_breakdown[$type] ?? 0;
            $pts = $POINT_MAP[$type];
        ?>
        <div class="bi">
            <div class="bi-type"><?= $label ?></div>
            <div class="bi-cnt"><?= $cnt ?></div>
            <div class="bi-pts"><?= $cnt * $pts ?> pts to award</div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="action-grid">
    <button class="ac a-yellow" onclick="confirm_('preview','🔍','Preview Dry Run','Scans <strong>both</strong> <code>invoice</code> and <code>user_invoice</code> tables and shows every missing reward grouped by date. Zero DB writes.','cbtn-ok')">
        <div class="ac-icon i-yellow">🔍</div>
        <div><span class="ac-lbl">Preview</span><span class="ac-sub">Dry run — see all missing rewards</span></div>
    </button>
    <button class="ac a-green" onclick="confirm_('execute','✅','Execute Backfill','Inserts all missing reward records into <code>daily_login_rewards</code> and logs to audit table. Tagged <code>backfill_jan2025</code> for safe rollback.','cbtn-ok')">
        <div class="ac-icon i-green">✅</div>
        <div><span class="ac-lbl">Execute</span><span class="ac-sub">Insert all missing reward records</span></div>
    </button>
    <button class="ac a-red" onclick="confirm_('rollback','↩️','Rollback Backfill','<strong>Permanently deletes</strong> all rows tagged <code>backfill_jan2025</code>. Organically earned rewards are untouched.','cbtn-danger')">
        <div class="ac-icon i-red">↩️</div>
        <div><span class="ac-lbl">Rollback</span><span class="ac-sub">Remove all backfill-tagged records</span></div>
    </button>
</div>

<div class="rc">
    <div class="sec-hdr"><h3>Point Rules & Scope</h3></div>
    <div class="breakdown">
        <div class="bi"><div class="bi-type">Super Stockist</div><div class="bi-cnt">2</div><div class="bi-pts">pts / billing day</div></div>
        <div class="bi"><div class="bi-type">Stockist</div><div class="bi-cnt">2</div><div class="bi-pts">pts / billing day</div></div>
        <div class="bi"><div class="bi-type">Distributor</div><div class="bi-cnt">1</div><div class="bi-pts">pt / billing day</div></div>
        <div class="bi"><div class="bi-type">Super Distributor</div><div class="bi-cnt">1</div><div class="bi-pts">pt / billing day</div></div>
    </div>
    <div class="notice n-blue">
        <span>ℹ️</span>
        <span>Checks both <code>invoice</code> (columns: <code>user_type</code>, <code>user_id</code>) and <code>user_invoice</code> (columns: <code>from_user_type</code>, <code>from_user_id</code>). One reward per user per calendar date — earliest invoice of that day is referenced.</span>
    </div>
    <div class="notice n-yellow">
        <span>🔒</span>
        <span>No status filter applied — all invoice rows qualify regardless of draft/submitted state. Login-today check is skipped for retroactive backfill.</span>
    </div>
</div>

<?php
// ────────────────────────────────────────────────────── PREVIEW
elseif ($action === 'preview'):
?>

<button class="back" onclick="history.back()">← Back</button>

<div class="rc">
    <div class="sec-hdr">
        <h3>🔍 Preview — Missing Rewards</h3>
        <span class="pill p-yellow"><?= $results['total'] ?> records</span>
        <span class="pill p-blue"><?= $results['points'] ?> pts total</span>
    </div>

    <?php if ($results['total'] === 0): ?>
    <div class="empty">
        <div class="empty-icon">✅</div>
        <div class="empty-title">All caught up!</div>
        <div class="empty-sub">No missing reward records in <code>invoice</code> or <code>user_invoice</code> for <?= BACKFILL_START ?> → <?= $BACKFILL_END ?></div>
    </div>
    <?php else: ?>

    <div class="sbar">
        <div class="sbi"><span class="sbi-lbl">Period</span><span class="sbi-val"><?= BACKFILL_START ?> → <?= $BACKFILL_END ?></span></div>
        <div class="vsep"></div>
        <div class="sbi"><span class="sbi-lbl">Records</span><span class="sbi-val"><?= $results['total'] ?></span></div>
        <div class="vsep"></div>
        <div class="sbi"><span class="sbi-lbl">Points</span><span class="sbi-val" style="color:var(--yellow)"><?= $results['points'] ?> pts</span></div>
        <div class="vsep"></div>
        <div class="sbi"><span class="sbi-lbl">Dates</span><span class="sbi-val"><?= count($results['grouped']) ?></span></div>
    </div>

    <!-- By-type summary -->
    <div class="breakdown" style="margin-bottom:20px">
        <?php foreach ($USER_LABELS as $type => $label):
            $cnt = $results['by_type'][$type] ?? 0; $pts = $POINT_MAP[$type]; ?>
        <div class="bi">
            <div class="bi-type"><?= $label ?></div>
            <div class="bi-cnt"><?= $cnt ?></div>
            <div class="bi-pts"><?= $cnt * $pts ?> pts</div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="notice n-blue">
        <span>ℹ️</span>
        <span>Dry run only — no data written. Go back and click <strong>Execute</strong> to apply.</span>
    </div>

    <!-- DATE GROUPED RESULTS -->
    <?php foreach ($results['grouped'] as $date => $rows):
        $dayPts   = array_sum(array_map(fn($r) => $POINT_MAP[$r['user_type']] ?? 1, $rows));
        $dayLabel = date('D, d M Y', strtotime($date));
    ?>
    <div class="date-group">
        <div class="date-hdr">
            <div class="dh-date">
                📅 <?= $date ?>
                <span class="dh-day"><?= $dayLabel ?></span>
            </div>
            <div class="dh-pills">
                <span class="pill p-yellow"><?= count($rows) ?> users</span>
                <span class="pill p-blue"><?= $dayPts ?> pts</span>
            </div>
        </div>
        <div class="date-body">
        <table>
        <thead>
            <tr>
                <th>#</th><th>User Type</th><th>User ID</th>
                <th>Points</th><th>First Invoice #</th><th>Bills That Day</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $row):
            $type = $row['user_type'];
            $pts  = $POINT_MAP[$type] ?? 1;
            $bc   = $BADGE_CLASS[$type] ?? 'b-st';
            $pc   = $pts === 2 ? 'b-2pt' : 'b-1pt';
        ?>
        <tr>
            <td class="mono" style="color:var(--muted)"><?= $i+1 ?></td>
            <td><span class="badge <?= $bc ?>"><?= $USER_LABELS[$type] ?? $type ?></span></td>
            <td class="mono"><?= htmlspecialchars($row['user_id']) ?></td>
            <td><span class="badge <?= $pc ?>"><?= $pts ?> pts</span></td>
            <td class="mono"><?= htmlspecialchars($row['first_invoice_number'] ?? '-') ?></td>
            <td class="mono" style="color:var(--muted)"><?= (int)$row['invoice_count'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
        </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
</div>

<?php
// ────────────────────────────────────────────────────── EXECUTE
elseif ($action === 'execute'):
?>

<button class="back" onclick="history.back()">← Back</button>

<div class="rc">
    <div class="sec-hdr">
        <h3>✅ Execute Results</h3>
        <span class="pill <?= $results['failed'] > 0 ? 'p-red' : 'p-green' ?>"><?= $results['inserted'] ?>/<?= $results['total'] ?> inserted</span>
        <?php if ($results['failed'] > 0): ?><span class="pill p-red"><?= $results['failed'] ?> failed</span><?php endif; ?>
        <span class="pill p-yellow"><?= $results['points'] ?> pts awarded</span>
    </div>

    <?php if ($results['total'] === 0): ?>
    <div class="empty">
        <div class="empty-icon">✅</div>
        <div class="empty-title">Nothing to backfill</div>
        <div class="empty-sub">All reward records are already present.</div>
    </div>
    <?php else: ?>

    <div class="sbar">
        <div class="sbi"><span class="sbi-lbl">Processed</span><span class="sbi-val"><?= $results['total'] ?></span></div>
        <div class="vsep"></div>
        <div class="sbi"><span class="sbi-lbl">Inserted</span><span class="sbi-val" style="color:var(--green)"><?= $results['inserted'] ?></span></div>
        <div class="vsep"></div>
        <div class="sbi"><span class="sbi-lbl">Failed</span><span class="sbi-val" style="color:<?= $results['failed']>0?'var(--red)':'var(--muted)' ?>"><?= $results['failed'] ?></span></div>
        <div class="vsep"></div>
        <div class="sbi"><span class="sbi-lbl">Points</span><span class="sbi-val" style="color:var(--yellow)"><?= $results['points'] ?> pts</span></div>
    </div>

    <?php if ($results['inserted'] > 0): ?>
    <div class="notice n-green">
        <span>✅</span>
        <span><?= $results['inserted'] ?> records inserted, tagged <code>backfill_jan2025</code>. Audit entries created. Use <strong>Rollback</strong> to undo.</span>
    </div>
    <?php endif; ?>
    <?php if ($results['failed'] > 0): ?>
    <div class="notice n-red"><span>⚠️</span><span><?= $results['failed'] ?> failed — check errors below.</span></div>
    <?php endif; ?>

    <?php foreach ($results['grouped_log'] as $date => $entries):
        $dayInserted = count(array_filter($entries, fn($e) => $e['status'] === 'ok'));
        $dayPts      = array_sum(array_map(fn($e) => $e['status']==='ok' ? $e['pts'] : 0, $entries));
        $dayLabel    = date('D, d M Y', strtotime($date));
    ?>
    <div class="date-group">
        <div class="date-hdr">
            <div class="dh-date">📅 <?= $date ?> <span class="dh-day"><?= $dayLabel ?></span></div>
            <div class="dh-pills">
                <span class="pill p-green"><?= $dayInserted ?> inserted</span>
                <span class="pill p-yellow"><?= $dayPts ?> pts</span>
            </div>
        </div>
        <div class="date-body">
        <table>
        <thead>
            <tr><th>#</th><th>Status</th><th>User Type</th><th>User ID</th><th>Points</th><th>Invoice #</th><th>Reward ID</th></tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $i => $entry):
            $row  = $entry['row'];
            $type = $row['user_type'];
            $bc   = $BADGE_CLASS[$type] ?? 'b-st';
            $pc   = ($entry['pts']??1)===2 ? 'b-2pt' : 'b-1pt';
        ?>
        <tr>
            <td class="mono" style="color:var(--muted)"><?= $i+1 ?></td>
            <td><?= $entry['status']==='ok'
                ? '<span class="s-ok">✅ Inserted</span>'
                : '<span class="s-fail">❌ '.htmlspecialchars($entry['error']??'Error').'</span>' ?></td>
            <td><span class="badge <?= $bc ?>"><?= $USER_LABELS[$type]??$type ?></span></td>
            <td class="mono"><?= htmlspecialchars($row['user_id']) ?></td>
            <td><span class="badge <?= $pc ?>"><?= $entry['pts'] ?> pts</span></td>
            <td class="mono"><?= htmlspecialchars($row['first_invoice_number']??'-') ?></td>
            <td class="mono" style="color:var(--muted)"><?= isset($entry['reward_id']) ? '#'.$entry['reward_id'] : '-' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
        </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
</div>

<?php
// ────────────────────────────────────────────────────── ROLLBACK
elseif ($action === 'rollback'):
?>

<button class="back" onclick="history.back()">← Back</button>

<div class="rc">
    <div class="sec-hdr">
        <h3>↩️ Rollback Results</h3>
        <span class="pill p-red"><?= $results['deleted'] ?> deleted</span>
        <?php if ($results['failed'] > 0): ?><span class="pill p-red"><?= $results['failed'] ?> failed</span><?php endif; ?>
        <span class="pill p-orange"><?= $results['points'] ?> pts reversed</span>
    </div>

    <?php if ($results['total'] === 0): ?>
    <div class="empty">
        <div class="empty-icon">🔍</div>
        <div class="empty-title">Nothing to rollback</div>
        <div class="empty-sub">No rows found with notes tag <code>backfill_jan2025</code>. Execute hasn't been run, or rollback was already completed.</div>
    </div>
    <?php else: ?>

    <div class="sbar">
        <div class="sbi"><span class="sbi-lbl">Found</span><span class="sbi-val"><?= $results['total'] ?></span></div>
        <div class="vsep"></div>
        <div class="sbi"><span class="sbi-lbl">Deleted</span><span class="sbi-val" style="color:var(--red)"><?= $results['deleted'] ?></span></div>
        <div class="vsep"></div>
        <div class="sbi"><span class="sbi-lbl">Points reversed</span><span class="sbi-val" style="color:var(--orange)"><?= $results['points'] ?> pts</span></div>
    </div>

    <div class="notice n-red">
        <span>↩️</span>
        <span><?= $results['deleted'] ?> backfill records deleted. Audit entries logged. Organically earned rewards were <strong>not touched</strong>.</span>
    </div>

    <?php foreach ($results['grouped_log'] as $date => $entries):
        $dayDel   = count(array_filter($entries, fn($e) => $e['status'] === 'ok'));
        $dayPts   = array_sum(array_map(fn($e) => $e['status']==='ok' ? (int)$e['row']['points_awarded'] : 0, $entries));
        $dayLabel = date('D, d M Y', strtotime($date));
    ?>
    <div class="date-group">
        <div class="date-hdr">
            <div class="dh-date">📅 <?= $date ?> <span class="dh-day"><?= $dayLabel ?></span></div>
            <div class="dh-pills">
                <span class="pill p-red"><?= $dayDel ?> deleted</span>
                <span class="pill p-orange"><?= $dayPts ?> pts</span>
            </div>
        </div>
        <div class="date-body">
        <table>
        <thead>
            <tr><th>#</th><th>Status</th><th>Reward ID</th><th>User Type</th><th>User ID</th><th>Points</th><th>Invoice #</th></tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $i => $entry):
            $row  = $entry['row'];
            $type = $row['user_type'];
            $bc   = $BADGE_CLASS[$type] ?? 'b-st';
            $pts  = (int)$row['points_awarded'];
            $pc   = $pts===2 ? 'b-2pt' : 'b-1pt';
        ?>
        <tr>
            <td class="mono" style="color:var(--muted)"><?= $i+1 ?></td>
            <td><?= $entry['status']==='ok'
                ? '<span class="s-del">🗑️ Deleted</span>'
                : '<span class="s-fail">❌ '.htmlspecialchars($entry['error']??'Error').'</span>' ?></td>
            <td class="mono" style="color:var(--muted)">#<?= (int)$row['id'] ?></td>
            <td><span class="badge <?= $bc ?>"><?= $USER_LABELS[$type]??$type ?></span></td>
            <td class="mono"><?= htmlspecialchars($row['user_id']) ?></td>
            <td><span class="badge <?= $pc ?>"><?= $pts ?> pts</span></td>
            <td class="mono"><?= htmlspecialchars($row['invoice_number']??'-') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
        </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
</div>

<?php endif; ?>

</div><!-- .wrap -->

<script>
let pendingAction = '';
function confirm_(action, icon, title, desc, btnCls) {
    pendingAction = action;
    document.getElementById('cIcon').textContent = icon;
    document.getElementById('cTitle').textContent = title;
    document.getElementById('cDesc').innerHTML = desc;
    const btn = document.getElementById('cConfirm');
    btn.className = 'cbtn ' + btnCls;
    btn.textContent = action === 'rollback' ? 'Yes, Rollback' : 'Confirm';
    document.getElementById('overlay').classList.add('active');
}
function closeOverlay() {
    document.getElementById('overlay').classList.remove('active');
    pendingAction = '';
}
function doSubmit() {
    if (!pendingAction) return;
    document.getElementById('hiddenAction').value = pendingAction;
    document.getElementById('actionForm').submit();
}
document.getElementById('overlay').addEventListener('click', e => {
    if (e.target === document.getElementById('overlay')) closeOverlay();
});
</script>
</body>
</html>
