<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
require_once __DIR__ . '/../salesbdm/include/DistrictNotes.php';
error_reporting(0);

ensureDistrictNotesTable($db_conn);
ensureNoteStatusColumn($db_conn);
ensureResolutionNoteColumn($db_conn);

// TP notes live in their own tp_district_notes table (territory-partner/include/DistrictNotes.php)
// — its helper functions share the exact same names as the ones above (both
// tables started from the same design), so that file can't be require_once'd
// here too without a fatal redeclaration. These are the same self-migrating
// guards, inlined, scoped to tp_district_notes instead.
$db_conn->query("CREATE TABLE IF NOT EXISTS tp_district_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tp_id INT NOT NULL,
    district VARCHAR(150) NOT NULL,
    note_type ENUM('software','tp') NOT NULL DEFAULT 'tp',
    issue_text TEXT NOT NULL,
    priority ENUM('high','priority','normal') NOT NULL DEFAULT 'normal',
    photo_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tp_created (tp_id, created_at),
    KEY idx_district (district),
    KEY idx_priority (priority),
    KEY idx_note_type (note_type)
)");
$tpCol = $db_conn->query("SHOW COLUMNS FROM tp_district_notes LIKE 'status'");
if ($tpCol && $tpCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_district_notes ADD COLUMN status ENUM('open','in_progress','completed') NOT NULL DEFAULT 'open' AFTER priority");
    $db_conn->query("ALTER TABLE tp_district_notes ADD KEY idx_status (status)");
}
$tpCol2 = $db_conn->query("SHOW COLUMNS FROM tp_district_notes LIKE 'resolution_note'");
if ($tpCol2 && $tpCol2->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_district_notes ADD COLUMN resolution_note VARCHAR(500) NULL AFTER status");
}

// Company sees every BDM's AND every TP's notes — no district/bdm/tp
// scoping like each role's own version of this page, since this is the
// escalation/oversight view.
$bdms = $db_conn->query("SELECT id, bdm_name FROM sales_bdm_staff ORDER BY bdm_name ASC")->fetch_all(MYSQLI_ASSOC);
$tps  = $db_conn->query("
    SELECT DISTINCT tp.id, tp.name, tp.tp_id AS tp_code
    FROM tp_district_notes n
    JOIN territory_partners tp ON tp.id = n.tp_id
    ORDER BY tp.name ASC
")->fetch_all(MYSQLI_ASSOC);

$filter_from     = $_GET['from_date'] ?? date('Y-m-01');
$filter_to       = $_GET['to_date']   ?? date('Y-m-d');
$filter_bdm      = (int)($_GET['bdm_id'] ?? 0);
$filter_tp       = (int)($_GET['tp_id']  ?? 0);
$filter_district = trim($_GET['district'] ?? '');
$filter_priority = $_GET['priority'] ?? '';
if (!in_array($filter_priority, ['high', 'priority', 'normal', ''], true)) { $filter_priority = ''; }
$filter_status = $_GET['status'] ?? '';
if (!in_array($filter_status, ['open', 'in_progress', 'completed', ''], true)) { $filter_status = ''; }
$filter_type = $_GET['note_type'] ?? '';
if (!in_array($filter_type, ['software', 'tp', ''], true)) { $filter_type = ''; }

// ── Build the two source queries, then combine ──────────────────────────────
// Selecting a specific BDM or a specific TP narrows to just that source's
// notes (a note can never be both); with neither picked, both sources show.
$showBdm = ($filter_tp === 0);
$showTp  = ($filter_bdm === 0);

$bdmWhere  = ["DATE(n.created_at) BETWEEN ? AND ?"];
$bdmParams = [$filter_from, $filter_to];
$bdmTypes  = "ss";
if ($filter_bdm > 0) { $bdmWhere[] = "n.bdm_id = ?"; $bdmParams[] = $filter_bdm; $bdmTypes .= "i"; }
if ($filter_district !== '') { $bdmWhere[] = "n.district = ?"; $bdmParams[] = $filter_district; $bdmTypes .= "s"; }
if ($filter_priority !== '') { $bdmWhere[] = "n.priority = ?"; $bdmParams[] = $filter_priority; $bdmTypes .= "s"; }
if ($filter_status !== '') { $bdmWhere[] = "n.status = ?"; $bdmParams[] = $filter_status; $bdmTypes .= "s"; }
if ($filter_type !== '') { $bdmWhere[] = "n.note_type = ?"; $bdmParams[] = $filter_type; $bdmTypes .= "s"; }

$tpWhere  = ["DATE(n.created_at) BETWEEN ? AND ?"];
$tpParams = [$filter_from, $filter_to];
$tpTypes  = "ss";
if ($filter_tp > 0) { $tpWhere[] = "n.tp_id = ?"; $tpParams[] = $filter_tp; $tpTypes .= "i"; }
if ($filter_district !== '') { $tpWhere[] = "n.district = ?"; $tpParams[] = $filter_district; $tpTypes .= "s"; }
if ($filter_priority !== '') { $tpWhere[] = "n.priority = ?"; $tpParams[] = $filter_priority; $tpTypes .= "s"; }
if ($filter_status !== '') { $tpWhere[] = "n.status = ?"; $tpParams[] = $filter_status; $tpTypes .= "s"; }
if ($filter_type !== '') { $tpWhere[] = "n.note_type = ?"; $tpParams[] = $filter_type; $tpTypes .= "s"; }

$notes = [];
if ($showBdm) {
    $sql = "SELECT n.id, 'bdm' AS source, s.bdm_name AS submitter_name, NULL AS submitter_code,
                   n.district, n.note_type, n.issue_text, n.tp_names, n.priority, n.photo_path,
                   n.status, n.resolution_note, n.created_at
            FROM salesbdm_district_notes n
            LEFT JOIN sales_bdm_staff s ON s.id = n.bdm_id
            WHERE " . implode(" AND ", $bdmWhere);
    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param($bdmTypes, ...$bdmParams);
    $stmt->execute();
    $notes = array_merge($notes, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
}
if ($showTp) {
    $sql = "SELECT n.id, 'tp' AS source, tp.name AS submitter_name, tp.tp_id AS submitter_code,
                   n.district, n.note_type, n.issue_text, NULL AS tp_names, n.priority, n.photo_path,
                   n.status, n.resolution_note, n.created_at
            FROM tp_district_notes n
            LEFT JOIN territory_partners tp ON tp.id = n.tp_id
            WHERE " . implode(" AND ", $tpWhere);
    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param($tpTypes, ...$tpParams);
    $stmt->execute();
    $notes = array_merge($notes, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
}

// Sort combined result (high priority first, then newest) and paginate
// in-memory — both source queries are already date-ranged, so the combined
// set stays small enough for this to be cheap.
usort($notes, function ($a, $b) {
    $prioRank = ['high' => 0, 'priority' => 1, 'normal' => 2];
    $pa = $prioRank[$a['priority']] ?? 2;
    $pb = $prioRank[$b['priority']] ?? 2;
    if ($pa !== $pb) return $pa - $pb;
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$totalNotes = count($notes);
$perPage    = 10;
$totalPages = max(1, (int)ceil($totalNotes / $perPage));
$page       = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset     = ($page - 1) * $perPage;
$pagedNotes = array_slice($notes, $offset, $perPage);

// Districts for the filter dropdown — every district that has ever had a
// note from either source, so switching filters doesn't shrink the
// dropdown's own options out from under the user.
$allDistricts = $db_conn->query("
    SELECT DISTINCT district FROM (
        SELECT district FROM salesbdm_district_notes
        UNION
        SELECT district FROM tp_district_notes
    ) d ORDER BY district ASC
")->fetch_all(MYSQLI_ASSOC);

$highOpenCount = 0;
foreach ($notes as $n) { if ($n['priority'] === 'high') $highOpenCount++; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>District Notes : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .mis-filter { background:#fff; border:1px solid rgba(11,11,11,0.10); border-radius:10px; padding:14px 18px; margin-bottom:20px; }
        .stats-card { background:#fff; border-radius:10px; padding:18px 20px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-left:4px solid #667eea; }
        .stats-card.danger { border-left-color:#dc2626; }
        .stats-card h3 { font-size:26px; font-weight:700; margin:0; color:#667eea; }
        .stats-card.danger h3 { color:#dc2626; }
        .stats-card p { margin:4px 0 0 0; color:#6b7280; font-size:13px; font-weight:500; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:8px 11px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:8px 11px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .dn-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap; }
        .dn-source-bdm { background:#eef2ff; color:#3730a3; }
        .dn-source-tp  { background:#ecfdf5; color:#065f46; }
        .dn-thumb { width:44px; height:44px; object-fit:cover; border-radius:6px; border:1px solid #e5e7eb; cursor:pointer; }
        .dn-issue { max-width:340px; white-space:normal; }
        .dn-row-high { background:#fff8f8; }

        .dn-status-cell { display:flex; gap:6px; align-items:center; }
        .dn-status-btn {
            width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center;
            cursor:pointer; transition:background .15s,border-color .15s,color .15s,transform .1s;
            padding:0; border:1.5px solid transparent;
        }
        .dn-status-btn .material-icons-outlined { font-size:17px; }
        .dn-status-btn:hover { transform:scale(1.08); }
        .dn-status-btn:disabled { opacity:.55; cursor:wait; transform:none; }

        .dn-status-btn.dn-status-start { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
        .dn-status-btn.dn-status-start:hover { background:#dbeafe; }
        .dn-status-btn.dn-status-start.active { background:#f59e0b; border-color:#f59e0b; color:#fff; cursor:default; }
        .dn-status-btn.dn-status-start.active:hover { transform:none; }

        .dn-status-btn.dn-status-complete { background:#fff7ed; border-color:#fdba74; color:#c2410c; }
        .dn-status-btn.dn-status-complete:hover { background:#ffedd5; border-color:#fb923c; color:#9a3412; }
        .dn-spin { animation: dn-spin-anim 1.8s linear infinite; }
        @keyframes dn-spin-anim { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        .dn-pagination { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 4px 4px; flex-wrap:wrap; }
        .dn-pagination-info { font-size:12px; color:#6b7280; }
        .dn-pagination-links { display:flex; gap:4px; flex-wrap:wrap; }
        .dn-page-link {
            min-width:30px; height:30px; padding:0 8px; border-radius:7px; border:1px solid #e5e7eb; background:#fff;
            color:#374151; font-size:12.5px; font-weight:600; display:flex; align-items:center; justify-content:center;
            text-decoration:none;
        }
        .dn-page-link:hover { background:#f3f4f6; color:#374151; }
        .dn-page-link.active { background:#667eea; border-color:#667eea; color:#fff; }
        .dn-page-link.disabled { opacity:.4; pointer-events:none; }
    </style>
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble">
                                        <tr><td>District Notes</td></tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card"><h3><?php echo $totalNotes; ?></h3><p>Notes (this filter)</p></div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card danger"><h3><?php echo $highOpenCount; ?></h3><p>High Priority</p></div>
                        </div>
                    </div>

                    <div class="mis-filter">
                        <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">From</label>
                                <input type="date" name="from_date" value="<?php echo htmlspecialchars($filter_from); ?>" class="form-control form-control-sm">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">To</label>
                                <input type="date" name="to_date" value="<?php echo htmlspecialchars($filter_to); ?>" class="form-control form-control-sm">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Sales BDM</label>
                                <select name="bdm_id" id="bdmSelect" class="form-control form-control-sm" style="width:200px;">
                                    <option value="0">All BDMs</option>
                                    <?php foreach ($bdms as $b): ?>
                                        <option value="<?php echo (int)$b['id']; ?>" <?php echo $filter_bdm === (int)$b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['bdm_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Territory Partner</label>
                                <select name="tp_id" id="tpSelect" class="form-control form-control-sm" style="width:200px;">
                                    <option value="0">All TPs</option>
                                    <?php foreach ($tps as $t): ?>
                                        <option value="<?php echo (int)$t['id']; ?>" <?php echo $filter_tp === (int)$t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']) . ' (' . htmlspecialchars($t['tp_code']) . ')'; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">District</label>
                                <select name="district" id="districtSelect" class="form-control form-control-sm" style="width:180px;">
                                    <option value="">All Districts</option>
                                    <?php foreach ($allDistricts as $d): ?>
                                        <option value="<?php echo htmlspecialchars($d['district'], ENT_QUOTES); ?>" <?php echo $filter_district === $d['district'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['district']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Priority</label>
                                <select name="priority" class="form-control form-control-sm">
                                    <option value="">All</option>
                                    <option value="high" <?php echo $filter_priority === 'high' ? 'selected' : ''; ?>>High Priority</option>
                                    <option value="priority" <?php echo $filter_priority === 'priority' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="normal" <?php echo $filter_priority === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Type</label>
                                <select name="note_type" class="form-control form-control-sm">
                                    <option value="">All</option>
                                    <option value="tp" <?php echo $filter_type === 'tp' ? 'selected' : ''; ?>>Field Issue</option>
                                    <option value="software" <?php echo $filter_type === 'software' ? 'selected' : ''; ?>>Software Issue</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Status</label>
                                <select name="status" class="form-control form-control-sm">
                                    <option value="">All</option>
                                    <option value="open" <?php echo $filter_status === 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <div><button type="submit" class="btn btn-primary btn-sm">Apply</button></div>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-body" style="overflow-x:auto;">
                            <table class="mt">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Source</th>
                                        <th>Sales BDM</th>
                                        <th>Territory Partner</th>
                                        <th>District</th>
                                        <th>Type</th>
                                        <th>Issue</th>
                                        <th>Territory Partners Tagged</th>
                                        <th>Priority</th>
                                        <th>Photo</th>
                                        <th>Status</th>
                                        <th>Note</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($pagedNotes)): ?>
                                    <tr><td colspan="13" class="text-center text-muted" style="padding:24px;">No notes found for this filter.</td></tr>
                                <?php else: foreach ($pagedNotes as $n):
                                    [$bg, $fg] = districtNotePriorityColors($n['priority']);
                                    $status = $n['status'] ?? 'open';
                                    $isTp = $n['source'] === 'tp';
                                    $photoBase = $isTp ? '../territory-partner/district_note_photos/' : '../salesbdm/district_note_photos/';
                                ?>
                                    <tr class="<?php echo $n['priority'] === 'high' ? 'dn-row-high' : ''; ?>">
                                        <td><?php echo date('d M Y', strtotime($n['created_at'])); ?><br><span class="text-muted" style="font-size:11px;"><?php echo date('h:i A', strtotime($n['created_at'])); ?></span></td>
                                        <td>
                                            <span class="dn-badge <?php echo $isTp ? 'dn-source-tp' : 'dn-source-bdm'; ?>"><?php echo $isTp ? 'TP' : 'Sales BDM'; ?></span>
                                        </td>
                                        <td><?php echo !$isTp ? htmlspecialchars($n['submitter_name'] ?? '—') : '<span class="text-muted">&mdash;</span>'; ?></td>
                                        <td><?php echo $isTp ? htmlspecialchars($n['submitter_name'] . ' (' . $n['submitter_code'] . ')') : '<span class="text-muted">&mdash;</span>'; ?></td>
                                        <td><?php echo htmlspecialchars($n['district']); ?></td>
                                        <td>
                                            <?php $noteType = $n['note_type'] ?? 'tp'; ?>
                                            <span class="dn-badge" style="<?php echo $noteType === 'software' ? 'background:#ede9fe;color:#5b21b6;' : 'background:#e0f2fe;color:#075985;'; ?>">
                                                <i class="material-icons-outlined" style="font-size:12px;vertical-align:-2px;"><?php echo $noteType === 'software' ? 'bug_report' : 'storefront'; ?></i>
                                                <?php echo htmlspecialchars(districtNoteTypeLabel($noteType)); ?>
                                            </span>
                                        </td>
                                        <td class="dn-issue"><?php echo nl2br(htmlspecialchars($n['issue_text'])); ?></td>
                                        <td class="dn-issue"><?php echo !empty($n['tp_names']) ? htmlspecialchars($n['tp_names']) : '<span class="text-muted" style="font-size:11px;">&mdash;</span>'; ?></td>
                                        <td><span class="dn-badge" style="background:<?php echo $bg; ?>;color:<?php echo $fg; ?>;"><?php echo htmlspecialchars(districtNotePriorityLabel($n['priority'])); ?></span></td>
                                        <td>
                                            <?php if (!empty($n['photo_path'])): ?>
                                                <a href="<?php echo $photoBase . htmlspecialchars($n['photo_path'], ENT_QUOTES); ?>" target="_blank" rel="noopener">
                                                    <img class="dn-thumb" src="<?php echo $photoBase . htmlspecialchars($n['photo_path'], ENT_QUOTES); ?>" alt="Photo">
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:11px;">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="dn-status-cell" data-id="<?php echo (int)$n['id']; ?>" data-source="<?php echo $n['source']; ?>" data-status="<?php echo htmlspecialchars($status, ENT_QUOTES); ?>">
                                            <?php if ($status === 'completed'): ?>
                                                <span class="dn-badge" style="background:#d1fae5;color:#065f46;"><i class="material-icons-outlined" style="font-size:13px;vertical-align:-2px;">check_circle</i> Completed</span>
                                            <?php elseif ($status === 'in_progress'): ?>
                                                <button type="button" class="dn-status-btn dn-status-complete" data-set="completed" title="Mark Completed">
                                                    <i class="material-icons-outlined dn-spin">autorenew</i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="dn-status-btn dn-status-start" data-set="in_progress" title="Start">
                                                    <i class="material-icons-outlined">play_arrow</i>
                                                </button>
                                            <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="dn-resolution-note-cell" style="max-width:220px;white-space:normal;font-size:12px;color:#4b5563;">
                                            <?php echo $n['resolution_note'] ? htmlspecialchars($n['resolution_note']) : '<span class="text-muted">&mdash;</span>'; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openDnEdit(<?php echo (int)$n['id']; ?>, <?php echo htmlspecialchars(json_encode($n['source']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($n['issue_text']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($n['priority']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($n['note_type'] ?? 'tp'), ENT_QUOTES); ?>)">
                                                <i class="material-icons-outlined" style="font-size:14px;vertical-align:-2px;">edit</i> Edit
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>

                            <?php if ($totalNotes > 0): ?>
                            <?php
                            function dnPageUrl(int $p): string {
                                $q = $_GET;
                                $q['page'] = $p;
                                return 'manage-district-notes.php?' . http_build_query($q);
                            }
                            $rangeStart = $offset + 1;
                            $rangeEnd = min($offset + $perPage, $totalNotes);
                            ?>
                            <div class="dn-pagination">
                                <div class="dn-pagination-info">Showing <?php echo $rangeStart; ?>&ndash;<?php echo $rangeEnd; ?> of <?php echo $totalNotes; ?></div>
                                <div class="dn-pagination-links">
                                    <a href="<?php echo htmlspecialchars(dnPageUrl(max(1, $page - 1))); ?>" class="dn-page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>">&lsaquo;</a>
                                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                        <a href="<?php echo htmlspecialchars(dnPageUrl($p)); ?>" class="dn-page-link <?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
                                    <?php endfor; ?>
                                    <a href="<?php echo htmlspecialchars(dnPageUrl(min($totalPages, $page + 1))); ?>" class="dn-page-link <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">&rsaquo;</a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Completion note modal — "Mark Completed" never fires directly, it always
     stops here first so there's a permanent record of what was actually done. -->
<div class="modal fade" id="dnCompleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" style="font-weight:700;"><i class="material-icons-outlined" style="vertical-align:middle;font-size:18px;color:#059669;">check_circle</i> Mark Completed</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label style="font-size:12.5px;font-weight:600;display:block;margin-bottom:6px;">What was done to resolve this?</label>
                <textarea id="dnCompleteNote" class="form-control" rows="3" placeholder="e.g. Visited the shop and corrected the stock count"></textarea>
                <div id="dnCompleteError" style="color:#991b1b;font-size:12px;margin-top:6px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm" id="dnCompleteSubmit">Mark Completed</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dnEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" style="font-weight:700;"><i class="material-icons-outlined" style="vertical-align:middle;font-size:18px;color:#667eea;">edit</i> Edit Note</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label style="font-size:12.5px;font-weight:600;display:block;margin-bottom:6px;">Issue</label>
                <textarea id="dnEditIssue" class="form-control" rows="3" style="margin-bottom:12px;"></textarea>
                <label style="font-size:12.5px;font-weight:600;display:block;margin-bottom:6px;">Type</label>
                <select id="dnEditType" class="form-control" style="margin-bottom:12px;">
                    <option value="tp">Field / TPs Issue</option>
                    <option value="software">Software Issue</option>
                </select>
                <label style="font-size:12.5px;font-weight:600;display:block;margin-bottom:6px;">Priority</label>
                <select id="dnEditPriority" class="form-control">
                    <option value="high">High Priority</option>
                    <option value="priority">Medium</option>
                    <option value="normal">Normal</option>
                </select>
                <div id="dnEditError" style="color:#991b1b;font-size:12px;margin-top:8px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="dnEditSubmit">Save</button>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script>
$('#bdmSelect').select2({ width: '200px', placeholder: 'All BDMs' });
$('#tpSelect').select2({ width: '200px', placeholder: 'All TPs' });
$('#districtSelect').select2({ width: '180px', placeholder: 'All Districts' });

var dnCompleteCell = null;

$(document).on('click', '.dn-status-btn', function () {
    var $btn = $(this);
    if ($btn.prop('disabled')) { return; }
    var newStatus = $btn.data('set');

    if (newStatus === 'completed') {
        dnCompleteCell = $btn.closest('.dn-status-cell');
        $('#dnCompleteNote').val('');
        $('#dnCompleteError').text('');
        $('#dnCompleteModal').modal('show');
        return;
    }

    var $cell = $btn.closest('.dn-status-cell');
    $cell.find('.dn-status-btn').prop('disabled', true);
    $.post('update-district-note-status.php', { id: $cell.data('id'), source: $cell.data('source'), status: newStatus }, function (resp) {
        if (!resp.success) {
            alert(resp.message || 'Could not update status. Please try again.');
            $cell.find('.dn-status-btn').prop('disabled', false);
            return;
        }
        $cell.data('status', 'in_progress');
        $cell.html('<button type="button" class="dn-status-btn dn-status-complete" data-set="completed" title="Mark Completed"><i class="material-icons-outlined dn-spin">autorenew</i></button>');
    }, 'json').fail(function () {
        alert('Could not update status. Please try again.');
        $cell.find('.dn-status-btn').prop('disabled', false);
    });
});

$('#dnCompleteSubmit').on('click', function () {
    var note = $.trim($('#dnCompleteNote').val());
    if (!note) {
        $('#dnCompleteError').text('Please describe what was done before marking this completed.');
        return;
    }
    var $cell = dnCompleteCell;
    var $submitBtn = $(this);
    $submitBtn.prop('disabled', true);
    $.post('update-district-note-status.php', { id: $cell.data('id'), source: $cell.data('source'), status: 'completed', resolution_note: note }, function (resp) {
        $submitBtn.prop('disabled', false);
        if (!resp.success) {
            $('#dnCompleteError').text(resp.message || 'Could not update status. Please try again.');
            return;
        }
        $('#dnCompleteModal').modal('hide');
        $cell.html('<span class="dn-badge" style="background:#d1fae5;color:#065f46;"><i class="material-icons-outlined" style="font-size:13px;vertical-align:-2px;">check_circle</i> Completed</span>');
        $cell.closest('tr').find('.dn-resolution-note-cell').text(note);
    }, 'json').fail(function () {
        $submitBtn.prop('disabled', false);
        $('#dnCompleteError').text('Could not reach the server. Please try again.');
    });
});

var dnEditId = null;
var dnEditSource = 'bdm';
function openDnEdit(id, source, issueText, priority, noteType) {
    dnEditId = id;
    dnEditSource = source;
    $('#dnEditIssue').val(issueText);
    $('#dnEditPriority').val(priority);
    $('#dnEditType').val(noteType);
    $('#dnEditError').text('');
    $('#dnEditModal').modal('show');
}
$('#dnEditSubmit').on('click', function () {
    var issueText = $.trim($('#dnEditIssue').val());
    if (!issueText) {
        $('#dnEditError').text('Describe the issue before saving.');
        return;
    }
    var $submitBtn = $(this);
    $submitBtn.prop('disabled', true);
    $.post('edit-district-note-ajax.php', {
        id: dnEditId, source: dnEditSource, issue_text: issueText, priority: $('#dnEditPriority').val(), note_type: $('#dnEditType').val()
    }, function (resp) {
        $submitBtn.prop('disabled', false);
        if (!resp.success) {
            $('#dnEditError').text(resp.message || 'Could not save. Please try again.');
            return;
        }
        $('#dnEditModal').modal('hide');
        window.location.reload();
    }, 'json').fail(function () {
        $submitBtn.prop('disabled', false);
        $('#dnEditError').text('Could not reach the server. Please try again.');
    });
});
</script>
</body>
</html>
