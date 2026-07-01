<?php
include("checksession.php");
include("config.php");
date_default_timezone_set("Asia/Kolkata");

// Handle remap POST
if (isset($_POST['doRemap'])) {
    $tp_id_int  = (int)($_POST['tp_id']  ?? 0);
    $new_ss_tid = trim($_POST['ss_tempid'] ?? '');

    if ($tp_id_int > 0) {
        if ($new_ss_tid === '__company__') {
            // Remap directly to company — clear SS assignment
            mysqli_query($db_conn, "UPDATE territory_partners SET onboard_ss_id=NULL WHERE id=$tp_id_int");
            $remap_msg = 'success';
        } elseif ($new_ss_tid !== '') {
            $new_ss_tid_esc = mysqli_real_escape_string($db_conn, $new_ss_tid);
            $chkRes = mysqli_query($db_conn, "SELECT temp_id FROM super_stockiest WHERE temp_id='$new_ss_tid_esc' LIMIT 1");
            if ($chkRes && mysqli_num_rows($chkRes) > 0) {
                mysqli_query($db_conn, "UPDATE territory_partners SET onboard_ss_id='$new_ss_tid_esc' WHERE id=$tp_id_int");
                $remap_msg = 'success';
            } else {
                $remap_msg = 'invalid_ss';
            }
        } else {
            $remap_msg = 'invalid_input';
        }
    } else {
        $remap_msg = 'invalid_input';
    }
}

// Load all TPs with their current SS
$tpRows = [];
$_r1 = mysqli_query($db_conn, "
    SELECT tp.id, tp.tp_id AS tp_code, tp.name AS tp_name, tp.mobile, tp.is_active,
           tp.onboard_ss_id,
           ss.name AS ss_name, ss.mobile_number AS ss_mobile
    FROM territory_partners tp
    LEFT JOIN super_stockiest ss ON ss.temp_id COLLATE utf8mb4_general_ci = tp.onboard_ss_id COLLATE utf8mb4_general_ci
    ORDER BY tp.is_active DESC, tp.name ASC
");
if ($_r1) { while ($row = mysqli_fetch_assoc($_r1)) { $tpRows[] = $row; } }

// Load all Super Stockists
$ssList = [];
$_r2 = mysqli_query($db_conn, "SELECT temp_id, name, mobile_number FROM super_stockiest ORDER BY name ASC");
if ($_r2) { while ($row = mysqli_fetch_assoc($_r2)) { $ssList[] = $row; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Remap Territory Partner : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        .remap-card { border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.07); border: none; }
        .remap-card .card-header { background: linear-gradient(135deg,#0d9488,#0f766e); color: #fff; border-radius: 12px 12px 0 0; padding: 16px 22px; }
        .remap-card .card-header h5 { margin: 0; font-size: 15px; font-weight: 600; }
        .tp-table th { font-size: 12px; text-transform: uppercase; color: #64748b; letter-spacing: .04em; }
        .tp-table td { font-size: 13.5px; vertical-align: middle; }
        .badge-active   { background: #dcfce7; color: #16a34a; font-size: 11px; padding: 3px 9px; border-radius: 20px; font-weight: 600; }
        .badge-inactive { background: #fee2e2; color: #dc2626; font-size: 11px; padding: 3px 9px; border-radius: 20px; font-weight: 600; }
        .no-ss { color: #94a3b8; font-style: italic; font-size: 12px; }
        .btn-remap { background: #0d9488; color: #fff; border: none; padding: 4px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .btn-remap:hover { background: #0f766e; color: #fff; }
        #remapModal .modal-header { background: linear-gradient(135deg,#0d9488,#0f766e); color: #fff; border-radius: 8px 8px 0 0; }
        #remapModal .modal-title { font-size: 15px; font-weight: 600; }
        #srchBox { border-radius: 8px; border: 1px solid #e2e8f0; padding: 8px 14px; font-size: 13px; width: 100%; max-width: 320px; margin-bottom: 14px; }
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

                    <div class="row mb-3">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble"><tr>
                                        <td>Re-mapping : Territory Partner → Company / Super Stockist</td>
                                    </tr></table>
                                </h1>
                            </div>

                            <?php if (isset($remap_msg)): ?>
                                <?php if ($remap_msg === 'success'): ?>
                                    <div class="alert alert-success">Territory Partner re-mapped successfully.</div>
                                <?php elseif ($remap_msg === 'invalid_ss'): ?>
                                    <div class="alert alert-danger">Invalid Super Stockist selected. Please try again.</div>
                                <?php else: ?>
                                    <div class="alert alert-warning">Invalid input. Please select both TP and Super Stockist.</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card remap-card">
                                <div class="card-header">
                                    <h5><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">swap_horiz</i>
                                        All Territory Partners &amp; Assigned Super Stockist
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <input type="text" id="srchBox" placeholder="Search TP name, code or mobile...">
                                    <div class="table-responsive">
                                        <table class="table tp-table" id="tpTable">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>TP Code</th>
                                                    <th>Name</th>
                                                    <th>Mobile</th>
                                                    <th>Status</th>
                                                    <th>Current Super Stockist</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($tpRows as $i => $tp): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><b><?= htmlspecialchars($tp['tp_code']) ?></b></td>
                                                <td><?= htmlspecialchars($tp['tp_name']) ?></td>
                                                <td><?= htmlspecialchars($tp['mobile']) ?></td>
                                                <td>
                                                    <?php if ($tp['is_active']): ?>
                                                        <span class="badge-active">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge-inactive">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($tp['ss_name']): ?>
                                                        <b><?= htmlspecialchars($tp['ss_name']) ?></b>
                                                        <br><small class="text-muted"><?= htmlspecialchars($tp['ss_mobile']) ?></small>
                                                    <?php else: ?>
                                                        <span style="background:#dbeafe;color:#1d4ed8;font-size:11px;padding:3px 9px;border-radius:20px;font-weight:600;">Company (Direct)</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn-remap"
                                                        onclick="openRemap(<?= $tp['id'] ?>, '<?= htmlspecialchars(addslashes($tp['tp_name'])) ?>', '<?= htmlspecialchars($tp['tp_code']) ?>', '<?= htmlspecialchars(addslashes($tp['ss_name'] ?? 'Company (Direct)')) ?>')">
                                                        Remap
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Remap Modal -->
<div class="modal fade" id="remapModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius:10px;border:none;">
            <div class="modal-header">
                <h5 class="modal-title"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">swap_horiz</i>Remap Territory Partner</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.8;font-size:22px;">&times;</button>
            </div>
            <form method="post">
                <div class="modal-body" style="padding:24px;">
                    <input type="hidden" name="doRemap" value="1">
                    <input type="hidden" name="tp_id" id="modal_tp_id">

                    <div style="background:#f8fafc;border-radius:8px;padding:14px 16px;margin-bottom:18px;">
                        <div style="font-size:12px;color:#64748b;margin-bottom:2px;">Territory Partner</div>
                        <div id="modal_tp_label" style="font-size:15px;font-weight:700;color:#0d9488;"></div>
                        <div style="font-size:12px;color:#64748b;margin-top:4px;">Current SS: <span id="modal_ss_label" style="color:#334155;font-weight:600;"></span></div>
                    </div>

                    <label style="font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;display:block;">Select New Assignment*</label>
                    <select name="ss_tempid" class="form-control" required style="border-radius:8px;font-size:13.5px;">
                        <option value="" hidden>-- Select --</option>
                        <option value="__company__" style="font-weight:700;color:#1d4ed8;">&#127968; Company (Direct)</option>
                        <optgroup label="── Super Stockists ──">
                        <?php foreach ($ssList as $ss): ?>
                            <option value="<?= htmlspecialchars($ss['temp_id']) ?>">
                                <?= htmlspecialchars($ss['name']) ?> (<?= htmlspecialchars($ss['mobile_number']) ?>)
                            </option>
                        <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <div class="modal-footer" style="border-top:1px solid #f1f5f9;padding:14px 24px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background:#0d9488;border-color:#0d9488;">Confirm Remap</button>
                </div>
            </form>
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
function openRemap(tpId, tpName, tpCode, ssName) {
    document.getElementById('modal_tp_id').value    = tpId;
    document.getElementById('modal_tp_label').textContent = tpName + ' (' + tpCode + ')';
    document.getElementById('modal_ss_label').textContent = ssName;
    $('#remapModal').modal('show');
}

// Live search
document.getElementById('srchBox').addEventListener('input', function () {
    var q = this.value.toLowerCase();
    document.querySelectorAll('#tpTable tbody tr').forEach(function (tr) {
        tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
</body>
</html>
