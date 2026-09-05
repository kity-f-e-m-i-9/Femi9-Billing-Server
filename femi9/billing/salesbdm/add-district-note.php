<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
require_once("include/DistrictNotes.php");
error_reporting(0);

ensureDistrictNotesTable($db_conn);

$districts = getBdmAssignedDistrictNames($db_conn, (int)$salesBdmID);

$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $district  = trim($_POST['district'] ?? '');
    $issueText = trim($_POST['issue_text'] ?? '');
    $priority  = $_POST['priority'] ?? 'normal';
    if (!in_array($priority, ['high', 'priority', 'normal'], true)) { $priority = 'normal'; }

    // Never trust the posted district on its own — it must be one this BDM
    // is actually assigned to, same posture as every other BDM-scoped form.
    if (!in_array($district, $districts, true)) {
        $errorMsg = 'Select a valid district from your assigned list.';
    } elseif ($issueText === '') {
        $errorMsg = 'Describe the issue before saving.';
    } else {
        $photoPath = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
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
            $stmt = $db_conn->prepare(
                "INSERT INTO salesbdm_district_notes (bdm_id, district, issue_text, priority, photo_path) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('issss', $salesBdmID, $district, $issueText, $priority, $photoPath);
            $stmt->execute();
            $stmt->close();
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

        /* Searchable district select (select2) restyled to match the plain
           .form-control fields around it instead of select2's default look. */
        .dn-field .select2-container { width:100% !important; }
        .dn-field .select2-selection--single {
            height:auto !important; border-radius:10px !important; border:1.5px solid #e5e7eb !important;
            padding:9px 13px !important;
        }
        .dn-field .select2-selection__rendered { padding:0 !important; font-size:13.5px; line-height:1.4 !important; color:#111827; }
        .dn-field .select2-selection__arrow { height:38px !important; top:1px !important; }
        .dn-field .select2-container--default.select2-container--focus .select2-selection--single,
        .dn-field .select2-container--default .select2-selection--single:focus {
            border-color:#667eea !important; box-shadow:0 0 0 3px rgba(102,126,234,.14);
        }
        .select2-dropdown { border-radius:10px !important; border-color:#e5e7eb !important; overflow:hidden; }
        .select2-search--dropdown .select2-search__field { border-radius:8px !important; padding:6px 10px !important; }
        .select2-results__option--highlighted { background:#667eea !important; }

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
                                <label><i class="material-icons-outlined">location_on</i> District</label>
                                <select name="district" id="districtSelect" class="form-control" required>
                                    <option value="">Select district…</option>
                                    <?php foreach ($districts as $d): ?>
                                        <option value="<?php echo htmlspecialchars($d, ENT_QUOTES); ?>" <?php echo (($_POST['district'] ?? '') === $d) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                                        <input type="radio" name="priority" value="priority"> Priority
                                    </label>
                                    <label class="dn-priority-btn normal active" data-val="normal">
                                        <i class="material-icons-outlined">check_circle</i>
                                        <input type="radio" name="priority" value="normal" checked> Normal
                                    </label>
                                </div>
                            </div>

                            <div class="dn-field">
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
$('#districtSelect').select2({ placeholder: 'Select district…', width: '100%' });

document.querySelectorAll('.dn-priority-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.dn-priority-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        btn.querySelector('input').checked = true;
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
