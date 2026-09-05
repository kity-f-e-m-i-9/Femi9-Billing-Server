<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
require_once("include/DistrictNotes.php");
error_reporting(0);

ensureDistrictNotesTable($db_conn);
ensureFixedDistrictColumn($db_conn);

$districts = getBdmAssignedDistrictNames($db_conn, (int)$salesBdmID);
$fixedDistricts = array_values(array_intersect(getFixedNoteDistricts($db_conn, (int)$salesBdmID), $districts));
// If the BDM's own assignment changed since fixing (e.g. a reassignment
// dropped a district), silently prune it rather than keep saving notes
// against a district they no longer cover.

// Clearing the fix ("Change districts") is its own quick GET action — no
// need to submit a whole note just to unlock the picker again.
if (isset($_GET['unfix'])) {
    setFixedNoteDistricts($db_conn, (int)$salesBdmID, []);
    header("Location: add-district-note.php");
    exit;
}

$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fixed districts always win over whatever the (hidden) form fields say —
    // the picker isn't even rendered while fixed, so the posted values should
    // already match, but this keeps a tampered request from sneaking a
    // different district through while "fixed" is on.
    $selectedDistricts = !empty($fixedDistricts) ? $fixedDistricts : array_map('trim', (array)($_POST['district'] ?? []));
    $selectedDistricts = array_values(array_unique(array_filter($selectedDistricts, fn($d) => $d !== '')));
    $issueText = trim($_POST['issue_text'] ?? '');
    $priority  = $_POST['priority'] ?? 'normal';
    if (!in_array($priority, ['high', 'priority', 'normal'], true)) { $priority = 'normal'; }
    $noteType  = $_POST['note_type'] ?? 'tp';
    if (!in_array($noteType, ['software', 'tp'], true)) { $noteType = 'tp'; }
    $wantsFix  = !empty($_POST['fix_district']);

    // Never trust the posted districts on their own — every one must be a
    // district this BDM is actually assigned to, same posture as every other
    // BDM-scoped form.
    $invalidDistrict = false;
    foreach ($selectedDistricts as $d) { if (!in_array($d, $districts, true)) { $invalidDistrict = true; break; } }

    // Same posture for the selected TPs — only ones that actually sit in one
    // of the selected districts can be tagged, never trusting the posted
    // names/ids on their own.
    $selectedTpNames = [];
    $postedTpIds = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['tp_ids'] ?? []), ), fn($v) => $v !== '')));
    if (!empty($postedTpIds) && !$invalidDistrict && !empty($selectedDistricts)) {
        $ph = implode(',', array_fill(0, count($postedTpIds), '?'));
        $dPh = implode(',', array_fill(0, count($selectedDistricts), '?'));
        $normDistricts = array_map(fn($n) => mb_strtolower(trim($n)), $selectedDistricts);
        $tpStmt = $db_conn->prepare(
            "SELECT name FROM territory_partners WHERE tp_id IN ($ph) AND LOWER(TRIM(branch_district)) IN ($dPh)"
        );
        $tpTypes = str_repeat('s', count($postedTpIds)) . str_repeat('s', count($normDistricts));
        $tpStmt->bind_param($tpTypes, ...array_merge($postedTpIds, $normDistricts));
        $tpStmt->execute();
        foreach ($tpStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) { $selectedTpNames[] = $r['name']; }
        $tpStmt->close();
    }
    $tpNamesForRow = !empty($selectedTpNames) ? implode(', ', $selectedTpNames) : null;

    if (empty($selectedDistricts) || $invalidDistrict) {
        $errorMsg = 'Select at least one valid district from your assigned list.';
    } elseif ($issueText === '') {
        $errorMsg = 'Describe the issue before saving.';
    } else {
        $photoPath = null;
        // Photo upload only applies to a Software Issue — a TPs Issue never
        // shows that field at all, so ignore anything posted alongside it.
        if ($noteType === 'software' && !empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];
            if ($file['size'] > 10 * 1024 * 1024) {
                $errorMsg = 'Photo is too large. Maximum allowed size is 10 MB.';
            } elseif (@getimagesize($file['tmp_name']) === false) {
                $errorMsg = 'Photo must be a valid image (JPG/PNG/WEBP).';
            } else {
                $uploadDir = __DIR__ . '/district_note_photos/';
                if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) { $ext = 'jpg'; }
                $photoPath = 'note_' . $salesBdmID . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $uploadDir . $photoPath)) {
                    $errorMsg = 'Could not save the uploaded photo. Please try again.';
                    $photoPath = null;
                }
            }
        }

        if ($errorMsg === '') {
            // One row per selected district — same issue/priority/photo,
            // so Manage Notes' per-district filter still finds it correctly
            // under each district it was logged against.
            $stmt = $db_conn->prepare(
                "INSERT INTO salesbdm_district_notes (bdm_id, district, note_type, issue_text, priority, photo_path, tp_names) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($selectedDistricts as $d) {
                $stmt->bind_param('issssss', $salesBdmID, $d, $noteType, $issueText, $priority, $photoPath, $tpNamesForRow);
                $stmt->execute();
            }
            $stmt->close();

            // Only a fresh pick (the picker was actually shown) can set the
            // fix — while already fixed, the checkbox isn't rendered at all,
            // so this never re-writes the same value pointlessly.
            if (empty($fixedDistricts) && $wantsFix) {
                setFixedNoteDistricts($db_conn, (int)$salesBdmID, $selectedDistricts);
            }

            header("Location: add-district-note.php?saved=1");
            exit;
        }
    }
}

if (isset($_GET['saved'])) { $successMsg = 'Note saved.'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add District Note : <?php echo $business_name; ?></title>
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
        body { font-family: 'Poppins', sans-serif; background:#f4f5fb; }

        .dn-wrap { display:flex; justify-content:center; padding:6px 0 40px; }

        .dn-type-switch { display:flex; gap:8px; }
        .dn-type-btn {
            flex:1; display:flex; align-items:center; justify-content:center; gap:6px;
            padding:11px 12px; border-radius:10px; border:1.5px solid #e5e7eb; background:#f9fafb;
            color:#6b7280; font-size:13px; font-weight:700; cursor:pointer; transition:background .15s,border-color .15s,color .15s;
        }
        .dn-type-btn input { display:none; }
        .dn-type-btn .material-icons-outlined { font-size:18px; }
        .dn-type-btn:hover { background:#f3f4f6; }
        .dn-type-btn.active { background:#eef0ff; border-color:#c7d2fe; color:#3730a3; }

        .dn-fixed-district {
            display:flex; align-items:center; gap:8px; background:#eef0ff; border:1.5px solid #c7d2fe;
            border-radius:10px; padding:10px 14px; font-size:13.5px; font-weight:700; color:#3730a3;
        }
        .dn-fixed-district .material-icons-outlined { font-size:17px; color:#5b4fd6; }
        .dn-fixed-district span { flex:1; }
        .dn-change-link { font-size:12px; font-weight:700; color:#5b4fd6; text-decoration:underline; white-space:nowrap; }
        .dn-fix-check {
            display:flex; align-items:center; gap:6px; margin-top:9px; font-size:12px; font-weight:600;
            color:#6b7280; cursor:pointer; text-transform:none; letter-spacing:0;
        }
        .dn-fix-check .material-icons-outlined { font-size:15px; color:#9ca3af; }
        .dn-fix-check input { width:15px; height:15px; accent-color:#5b4fd6; }

        .dn-tps-panel { margin-top:12px; background:#f9fafb; border:1px solid #eef0f4; border-radius:10px; padding:10px 12px; }
        .dn-tps-title {
            display:flex; align-items:center; gap:6px; font-size:11px; font-weight:700; color:#6b7280;
            text-transform:uppercase; letter-spacing:.4px; margin-bottom:8px;
        }
        .dn-tps-title .material-icons-outlined { font-size:15px; color:#9ca3af; }
        .dn-card {
            background:#fff; border:1px solid rgba(17,17,26,0.06); border-radius:18px;
            max-width:560px; width:100%; overflow:hidden;
            box-shadow: 0 1px 2px rgba(17,17,26,.05), 0 12px 32px rgba(76,81,191,.08);
        }
        .dn-card-head {
            background: linear-gradient(135deg, #667eea 0%, #5b4fd6 100%);
            padding: 26px 28px 22px; color:#fff; position:relative; overflow:hidden;
        }
        .dn-card-head::after {
            content:''; position:absolute; right:-30px; top:-30px; width:140px; height:140px;
            background:rgba(255,255,255,.08); border-radius:50%;
        }
        .dn-card-head::before {
            content:''; position:absolute; right:20px; bottom:-40px; width:90px; height:90px;
            background:rgba(255,255,255,.06); border-radius:50%;
        }
        .dn-icon-badge {
            width:46px; height:46px; border-radius:13px; background:rgba(255,255,255,.18);
            display:flex; align-items:center; justify-content:center; margin-bottom:12px; position:relative;
        }
        .dn-icon-badge .material-icons-outlined { font-size:24px; color:#fff; }
        .dn-card-head h2 { font-size:19px; font-weight:700; margin:0; position:relative; }
        .dn-card-head p { font-size:12.5px; margin:4px 0 0; opacity:.85; position:relative; }

        .dn-body { padding:26px 28px 28px; }
        .dn-card label { font-size:12px; font-weight:700; color:#374151; display:flex; align-items:center; gap:6px; margin-bottom:6px; text-transform:uppercase; letter-spacing:.4px; }
        .dn-card label .material-icons-outlined { font-size:15px; color:#9ca3af; }
        .dn-field { margin-bottom:20px; }
        .dn-card .form-control {
            border-radius:10px; border:1.5px solid #e5e7eb; padding:10px 13px; font-size:13.5px;
            transition: border-color .15s, box-shadow .15s;
        }
        .dn-card .form-control:focus { border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,.14); }
        .dn-card select.form-control { cursor:pointer; }

        /* Searchable, multi-select district picker (select2) restyled to match
           the plain .form-control fields around it instead of select2's
           default look. Multi-mode renders selections as removable chips. */
        .dn-field .select2-container { width:100% !important; }
        .dn-field .select2-selection--multiple {
            min-height:46px !important; border-radius:10px !important; border:1.5px solid #e5e7eb !important;
            padding:8px 10px !important;
        }
        /* Select2 renders every chosen chip as an <li> inside this <ul>, packed
           edge-to-edge by default with only whatever margin each chip sets on
           itself — switching it to a wrapping flex row with its own gap is what
           actually gives every chip breathing room on all sides, neat instead
           of "kasa kasa" (cramped) against its neighbours. */
        .dn-field .select2-selection__rendered {
            display:flex !important; flex-wrap:wrap !important; gap:7px !important; padding:2px !important;
        }
        .dn-field .select2-selection__choice {
            background:#eef0ff !important; border-color:#c7d2fe !important; color:#3730a3 !important;
            border-radius:7px !important; font-size:12.5px !important; font-weight:600 !important; padding:6px 10px !important;
            margin:0 !important; display:inline-flex !important; align-items:center !important; line-height:1 !important;
        }
        .dn-field .select2-selection__choice__remove {
            color:#5b4fd6 !important; margin-right:6px !important; display:inline-flex !important; align-items:center !important;
        }
        .dn-field .select2-search__field { font-size:13.5px !important; margin-top:2px !important; }
        .dn-field .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color:#667eea !important; box-shadow:0 0 0 3px rgba(102,126,234,.14);
        }
        .select2-dropdown { border-radius:10px !important; border-color:#e5e7eb !important; overflow:hidden; }
        .select2-search--dropdown .select2-search__field { border-radius:8px !important; padding:6px 10px !important; }
        .select2-results__option--highlighted { background:#f0f1ff !important; color:#111827 !important; }

        /* Checkbox indicator on the right of each option — a plain box that
           fills in with a check mark once select2 marks that option
           aria-selected, so multi-picking reads like a checkbox list instead
           of a single-select highlight. */
        .select2-results__option { position:relative !important; padding:8px 40px 8px 12px !important; }
        .select2-results__option::after {
            content:''; position:absolute; right:12px; top:50%; transform:translateY(-50%);
            width:17px; height:17px; border:1.5px solid #cbd5e1; border-radius:5px; background:#fff;
        }
        .select2-results__option[aria-selected="true"] {
            background:#f5f5ff !important; color:#3730a3 !important; font-weight:600;
        }
        .select2-results__option[aria-selected="true"]::after {
            background-color:#5b4fd6; border-color:#5b4fd6;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='white' stroke-width='2'%3E%3Cpath d='M3 8l3.5 3.5L13 5'/%3E%3C/svg%3E");
            background-repeat:no-repeat; background-position:center; background-size:11px;
        }

        .dn-priority-row { display:flex; gap:9px; flex-wrap:wrap; }
        .dn-priority-btn {
            flex:1; min-width:112px; display:flex; flex-direction:column; align-items:center; gap:4px;
            text-align:center; padding:12px 10px; border-radius:11px; cursor:pointer;
            font-size:12.5px; font-weight:700; border:1.5px solid #e5e7eb; background:#f9fafb; color:#9ca3af;
            transition: background .15s, border-color .15s, color .15s, transform .1s;
        }
        .dn-priority-btn:hover { transform: translateY(-1px); }
        .dn-priority-btn input { display:none; }
        .dn-priority-btn .material-icons-outlined { font-size:19px; }
        .dn-priority-btn.high.active { background:#fee2e2; border-color:#fca5a5; color:#991b1b; }
        .dn-priority-btn.priority.active { background:#fef3c7; border-color:#fcd34d; color:#92400e; }
        .dn-priority-btn.normal.active { background:#e0e7ff; border-color:#a5b4fc; color:#3730a3; }

        .dn-drop {
            border:1.5px dashed #d1d5db; border-radius:12px; padding:18px 14px; text-align:center;
            cursor:pointer; transition: border-color .15s, background .15s; background:#fafafc;
        }
        .dn-drop:hover { border-color:#667eea; background:#f5f6ff; }
        .dn-drop .material-icons-outlined { font-size:26px; color:#9ca3af; display:block; margin:0 auto 4px; }
        .dn-drop-text { font-size:12px; color:#6b7280; font-weight:600; }
        .dn-drop input[type=file] { display:none; }
        .dn-photo-preview { margin-top:12px; max-width:100%; max-height:220px; border-radius:10px; display:none; border:1px solid #e5e7eb; }

        .dn-save-btn {
            width:100%; border:none; border-radius:11px; padding:13px;
            background: linear-gradient(135deg, #667eea 0%, #5b4fd6 100%); color:#fff;
            font-size:14px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:7px;
            box-shadow: 0 6px 16px rgba(102,126,234,.35); transition: transform .1s, box-shadow .15s;
        }
        .dn-save-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(102,126,234,.42); color:#fff; }
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
                                        <tr><td>Add District Note</td></tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <?php if ($successMsg): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
                    <?php endif; ?>
                    <?php if ($errorMsg): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
                    <?php endif; ?>

                    <?php if (empty($districts)): ?>
                        <div class="alert alert-info">No districts are assigned to you yet.</div>
                    <?php else: ?>

                    <div class="dn-wrap">
                    <div class="dn-card">
                        <div class="dn-card-head">
                            <div class="dn-icon-badge"><i class="material-icons-outlined">sticky_note_2</i></div>
                            <h2>New District Note</h2>
                            <p>Log a field issue with a priority so it doesn't get lost.</p>
                        </div>
                        <div class="dn-body">
                        <form method="post" enctype="multipart/form-data">
                            <div class="dn-field">
                                <label><i class="material-icons-outlined">category</i> Note Type</label>
                                <?php $postedNoteType = ($_POST['note_type'] ?? 'tp') === 'software' ? 'software' : 'tp'; ?>
                                <div class="dn-type-switch" id="noteTypeSwitch">
                                    <label class="dn-type-btn <?php echo $postedNoteType === 'tp' ? 'active' : ''; ?>" data-val="tp">
                                        <input type="radio" name="note_type" value="tp" <?php echo $postedNoteType === 'tp' ? 'checked' : ''; ?>>
                                        <i class="material-icons-outlined">storefront</i> TPs Issue
                                    </label>
                                    <label class="dn-type-btn <?php echo $postedNoteType === 'software' ? 'active' : ''; ?>" data-val="software">
                                        <input type="radio" name="note_type" value="software" <?php echo $postedNoteType === 'software' ? 'checked' : ''; ?>>
                                        <i class="material-icons-outlined">bug_report</i> Software Issue
                                    </label>
                                </div>
                            </div>

                            <div class="dn-field">
                                <label><i class="material-icons-outlined">location_on</i> District<span style="text-transform:none;font-weight:500;color:#9ca3af;"> (select one or more)</span></label>
                                <?php if (!empty($fixedDistricts)): ?>
                                    <div class="dn-fixed-district">
                                        <i class="material-icons-outlined">push_pin</i>
                                        <span><?php echo htmlspecialchars(implode(', ', $fixedDistricts)); ?></span>
                                        <a href="add-district-note.php?unfix=1" class="dn-change-link">Change</a>
                                    </div>
                                    <?php foreach ($fixedDistricts as $fd): ?>
                                        <input type="hidden" name="district[]" value="<?php echo htmlspecialchars($fd, ENT_QUOTES); ?>">
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php $postedDistricts = (array)($_POST['district'] ?? []); ?>
                                    <select name="district[]" id="districtSelect" class="form-control" multiple required>
                                        <?php foreach ($districts as $d): ?>
                                            <option value="<?php echo htmlspecialchars($d, ENT_QUOTES); ?>" <?php echo in_array($d, $postedDistricts, true) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label class="dn-fix-check">
                                        <input type="checkbox" name="fix_district" value="1" <?php echo !empty($_POST['fix_district']) ? 'checked' : ''; ?>>
                                        <i class="material-icons-outlined">push_pin</i> Fix the selected district(s) so they auto-fill next time
                                    </label>
                                <?php endif; ?>
                                <div id="districtTpsPanel" class="dn-tps-panel" style="display:none;">
                                    <div class="dn-tps-title"><i class="material-icons-outlined">groups</i> Tag Territory Partner(s) (optional)</div>
                                    <select id="tpSelect" name="tp_ids[]" multiple class="form-control"></select>
                                </div>
                            </div>

                            <div class="dn-field">
                                <label><i class="material-icons-outlined">edit_note</i> Issue</label>
                                <textarea name="issue_text" class="form-control" rows="4" placeholder="Describe the issue…" required><?php echo htmlspecialchars($_POST['issue_text'] ?? ''); ?></textarea>
                            </div>

                            <div class="dn-field">
                                <label><i class="material-icons-outlined">flag</i> Priority</label>
                                <div class="dn-priority-row" id="priorityRow">
                                    <label class="dn-priority-btn high" data-val="high">
                                        <i class="material-icons-outlined">error</i>
                                        <input type="radio" name="priority" value="high"> High Priority
                                    </label>
                                    <label class="dn-priority-btn priority" data-val="priority">
                                        <i class="material-icons-outlined">priority_high</i>
                                        <input type="radio" name="priority" value="priority"> Medium
                                    </label>
                                    <label class="dn-priority-btn normal active" data-val="normal">
                                        <i class="material-icons-outlined">check_circle</i>
                                        <input type="radio" name="priority" value="normal" checked> Normal
                                    </label>
                                </div>
                            </div>

                            <div class="dn-field" id="photoField" style="<?php echo (($_POST['note_type'] ?? 'tp') === 'software') ? '' : 'display:none;'; ?>">
                                <label><i class="material-icons-outlined">image</i> Photo (optional)</label>
                                <label class="dn-drop" id="dropZone">
                                    <i class="material-icons-outlined">cloud_upload</i>
                                    <span class="dn-drop-text" id="dropZoneText">Tap to add a photo</span>
                                    <input type="file" name="photo" id="photoInput" accept="image/*">
                                </label>
                                <img id="photoPreview" class="dn-photo-preview">
                            </div>

                            <button type="submit" class="dn-save-btn">
                                <i class="material-icons-outlined" style="font-size:18px;">save</i> Save Note
                            </button>
                        </form>
                        </div>
                    </div>
                    </div>

                    <?php endif; ?>
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
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script>
$('#districtSelect').select2({ placeholder: 'Select district(s)…', width: '100%', closeOnSelect: false });

// Ticked TPs survive a district-list refresh (e.g. adding a second district)
// instead of silently un-ticking whatever was already picked.
var checkedTpIds = {};

function loadDistrictTps(districts) {
    var $panel = $('#districtTpsPanel');
    var $select = $('#tpSelect');
    if (!districts || !districts.length) { $panel.hide(); return; }
    $panel.show();
    $.getJSON('get-district-tps.php', { districts: districts }, function (resp) {
        var tps = resp.tps || [];
        if ($select.data('select2')) { $select.select2('destroy'); }
        $select.empty();
        tps.forEach(function (t) {
            if (!t.tp_id) { return; }
            var $opt = $('<option></option>').val(t.tp_id).text(t.name + (t.active ? '' : ' (inactive)'));
            if (checkedTpIds[t.tp_id]) { $opt.prop('selected', true); }
            $select.append($opt);
        });
        $select.select2({
            placeholder: tps.length ? 'Select Territory Partner(s)…' : 'No Territory Partners in this district',
            width: '100%', closeOnSelect: false
        });
        $select.on('change', function () {
            checkedTpIds = {};
            ($(this).val() || []).forEach(function (id) { checkedTpIds[id] = true; });
        });
    }).fail(function () {
        $select.empty().append('<option disabled>Could not load Territory Partners</option>');
    });
}

$('#districtSelect').on('change', function () { loadDistrictTps($(this).val()); });
<?php if (!empty($fixedDistricts)): ?>
loadDistrictTps(<?php echo json_encode($fixedDistricts); ?>);
<?php endif; ?>

document.querySelectorAll('.dn-priority-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.dn-priority-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        btn.querySelector('input').checked = true;
    });
});

// Photo upload only makes sense for a Software Issue — a TPs Issue never
// needs it, so the field itself only shows for that type.
document.querySelectorAll('#noteTypeSwitch .dn-type-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('#noteTypeSwitch .dn-type-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        btn.querySelector('input').checked = true;
        var isSoftware = btn.getAttribute('data-val') === 'software';
        document.getElementById('photoField').style.display = isSoftware ? '' : 'none';
    });
});
document.getElementById('photoInput').addEventListener('change', function () {
    var preview = document.getElementById('photoPreview');
    var dropText = document.getElementById('dropZoneText');
    if (this.files && this.files[0]) {
        preview.src = URL.createObjectURL(this.files[0]);
        preview.style.display = 'block';
        dropText.textContent = this.files[0].name;
    } else {
        preview.style.display = 'none';
        dropText.textContent = 'Tap to add a photo';
    }
});
</script>
</body>
</html>
