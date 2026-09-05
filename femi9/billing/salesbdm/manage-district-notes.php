<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
require_once("include/DistrictNotes.php");
error_reporting(0);

ensureDistrictNotesTable($db_conn);
ensureNoteStatusColumn($db_conn);

$districts = getBdmAssignedDistrictNames($db_conn, (int)$salesBdmID);

$filter_from     = $_GET['from_date'] ?? date('Y-m-01');
$filter_to       = $_GET['to_date']   ?? date('Y-m-d');
$filter_district = $_GET['district']  ?? '';
if ($filter_district !== '' && !in_array($filter_district, $districts, true)) { $filter_district = ''; }
$filter_priority = $_GET['priority']  ?? '';
if (!in_array($filter_priority, ['high', 'priority', 'normal', ''], true)) { $filter_priority = ''; }
$filter_status = $_GET['status'] ?? '';
if (!in_array($filter_status, ['open', 'in_progress', 'completed', ''], true)) { $filter_status = ''; }
$filter_type = $_GET['note_type'] ?? '';
if (!in_array($filter_type, ['software', 'tp', ''], true)) { $filter_type = ''; }

$where  = ["bdm_id = ?", "DATE(created_at) BETWEEN ? AND ?"];
$params = [$salesBdmID, $filter_from, $filter_to];
$types  = "iss";
if ($filter_district !== '') {
    $where[]  = "district = ?";
    $params[] = $filter_district;
    $types   .= "s";
}
if ($filter_priority !== '') {
    $where[]  = "priority = ?";
    $params[] = $filter_priority;
    $types   .= "s";
}
if ($filter_status !== '') {
    $where[]  = "status = ?";
    $params[] = $filter_status;
    $types   .= "s";
}
if ($filter_type !== '') {
    $where[]  = "note_type = ?";
    $params[] = $filter_type;
    $types   .= "s";
}

// Total count first (same WHERE, no LIMIT) so pagination links reflect the
// full filtered result, not just what's on the current page.
$countSql = "SELECT COUNT(*) AS cnt FROM salesbdm_district_notes WHERE " . implode(" AND ", $where);
$countStmt = $db_conn->prepare($countSql);
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalNotes = (int)($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$countStmt->close();

$perPage    = 10;
$totalPages = max(1, (int)ceil($totalNotes / $perPage));
$page       = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset     = ($page - 1) * $perPage;

$sql = "SELECT * FROM salesbdm_district_notes WHERE " . implode(" AND ", $where) . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $db_conn->prepare($sql);
$stmt->bind_param($types . 'ii', ...array_merge($params, [$perPage, $offset]));
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage District Notes : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .mis-filter { background:#fff; border:1px solid rgba(11,11,11,0.10); border-radius:10px; padding:14px 18px; margin-bottom:20px; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:8px 11px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:8px 11px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .dn-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap; }
        .dn-thumb { width:44px; height:44px; object-fit:cover; border-radius:6px; border:1px solid #e5e7eb; cursor:pointer; }
        .dn-issue { max-width:340px; white-space:normal; }

        .dn-status-cell { display:flex; gap:6px; align-items:center; }
        .dn-status-btn {
            width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center;
            cursor:pointer; transition:background .15s,border-color .15s,color .15s,transform .1s;
            padding:0; border:1.5px solid transparent;
        }
        .dn-status-btn .material-icons-outlined { font-size:17px; }
        .dn-status-btn:hover { transform:scale(1.08); }
        .dn-status-btn:disabled { opacity:.55; cursor:wait; transform:none; }

        /* Start: not yet begun — outlined blue, hints "tap to begin". */
        .dn-status-btn.dn-status-start { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
        .dn-status-btn.dn-status-start:hover { background:#dbeafe; }
        /* Once started, it's a fixed "in progress" indicator, not a button
           anymore — solid amber so it reads unmistakably different from the
           blue "not started yet" state. */
        .dn-status-btn.dn-status-start.active { background:#f59e0b; border-color:#f59e0b; color:#fff; cursor:default; }
        .dn-status-btn.dn-status-start.active:hover { transform:none; }

        /* Complete: outlined green, hints "tap to finish". */
        .dn-status-btn.dn-status-complete { background:#ecfdf5; border-color:#a7f3d0; color:#059669; }
        .dn-status-btn.dn-status-complete:hover { background:#d1fae5; border-color:#6ee7b7; color:#065f46; }

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
                                        <tr>
                                            <td>Manage District Notes</td>
                                            <td><a href="add-district-note.php" class="btn btn-primary btn-sm"><i class="material-icons-outlined" style="font-size:16px;vertical-align:-3px;">add</i> Add Note</a></td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
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
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">District</label>
                                <select name="district" class="form-control form-control-sm">
                                    <option value="">All Districts</option>
                                    <?php foreach ($districts as $d): ?>
                                        <option value="<?php echo htmlspecialchars($d, ENT_QUOTES); ?>" <?php echo $filter_district === $d ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
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
                                    <option value="tp" <?php echo $filter_type === 'tp' ? 'selected' : ''; ?>>TPs Issue</option>
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
                                        <th>District</th>
                                        <th>Type</th>
                                        <th>Issue</th>
                                        <th>Territory Partners</th>
                                        <th>Priority</th>
                                        <th>Photo</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($notes)): ?>
                                    <tr><td colspan="8" class="text-center text-muted" style="padding:24px;">No notes found for this filter.</td></tr>
                                <?php else: foreach ($notes as $n):
                                    [$bg, $fg] = districtNotePriorityColors($n['priority']);
                                    $status = $n['status'] ?? 'open';
                                ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($n['created_at'])); ?><br><span class="text-muted" style="font-size:11px;"><?php echo date('h:i A', strtotime($n['created_at'])); ?></span></td>
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
                                                <a href="district_note_photos/<?php echo htmlspecialchars($n['photo_path'], ENT_QUOTES); ?>" target="_blank" rel="noopener">
                                                    <img class="dn-thumb" src="district_note_photos/<?php echo htmlspecialchars($n['photo_path'], ENT_QUOTES); ?>" alt="Photo">
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:11px;">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="dn-status-cell" data-id="<?php echo (int)$n['id']; ?>" data-status="<?php echo htmlspecialchars($status, ENT_QUOTES); ?>">
                                            <?php if ($status === 'completed'): ?>
                                                <span class="dn-badge" style="background:#d1fae5;color:#065f46;"><i class="material-icons-outlined" style="font-size:13px;vertical-align:-2px;">check_circle</i> Completed</span>
                                            <?php else: ?>
                                                <button type="button" class="dn-status-btn dn-status-start <?php echo $status === 'in_progress' ? 'active' : ''; ?>" data-set="in_progress" title="<?php echo $status === 'in_progress' ? 'In Progress' : 'Start'; ?>">
                                                    <i class="material-icons-outlined"><?php echo $status === 'in_progress' ? 'autorenew' : 'play_arrow'; ?></i>
                                                </button>
                                                <button type="button" class="dn-status-btn dn-status-complete" data-set="completed" title="Mark Completed">
                                                    <i class="material-icons-outlined">check</i>
                                                </button>
                                            <?php endif; ?>
                                            </div>
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
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
$(document).on('click', '.dn-status-btn', function () {
    var $btn = $(this);
    if ($btn.hasClass('active') || $btn.prop('disabled')) { return; }
    var $cell = $btn.closest('.dn-status-cell');
    var newStatus = $btn.data('set');
    $cell.find('.dn-status-btn').prop('disabled', true);
    $.post('update-note-status.php', { id: $cell.data('id'), status: newStatus }, function (resp) {
        if (!resp.success) {
            alert('Could not update status. Please try again.');
            $cell.find('.dn-status-btn').prop('disabled', false);
            return;
        }
        if (newStatus === 'completed') {
            $cell.html('<span class="dn-badge" style="background:#d1fae5;color:#065f46;"><i class="material-icons-outlined" style="font-size:13px;vertical-align:-2px;">check_circle</i> Completed</span>');
        } else {
            $cell.data('status', 'in_progress');
            $cell.find('.dn-status-start').addClass('active').attr('title', 'In Progress').html('<i class="material-icons-outlined">autorenew</i>').prop('disabled', true);
            $cell.find('.dn-status-complete').prop('disabled', false);
        }
    }, 'json').fail(function () {
        alert('Could not update status. Please try again.');
        $cell.find('.dn-status-btn').prop('disabled', false);
    });
});
</script>
</body>
</html>
