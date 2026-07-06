<?php
include("checksession.php");
error_reporting(0);
require_once __DIR__ . '/../shared/EncryptionService.php';
$_enc = new EncryptionService();
function decryptPassword($enc, $hash) {
    if (!$hash) return '';
    if (password_get_info($hash)['algo']) return null; // bcrypt — cannot reverse
    try { return $enc->decrypt($hash); } catch (Exception $e) { return $hash; }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ensure target_amount column exists before querying it
$_col = $db_conn->query("SHOW COLUMNS FROM partner_location_nodes LIKE 'target_amount'");
if ($_col && $_col->num_rows === 0) {
    $db_conn->query("ALTER TABLE partner_location_nodes ADD COLUMN target_amount DECIMAL(12,2) DEFAULT NULL AFTER deposit_amount");
}

$result = $db_conn->query("
    SELECT tp.*,
           COUNT(tpl.id) AS location_count,
           GROUP_CONCAT(n.name ORDER BY n.name SEPARATOR ', ') AS location_names,
           COALESCE(SUM(n.target_amount), 0) AS total_target,
           GROUP_CONCAT(CONCAT(COALESCE(pll.layer_name,''), '||', n.name, '||', COALESCE(pn.name,''), '||', COALESCE(ppn.name,'')) ORDER BY pll.depth, n.name SEPARATOR '~~') AS location_details
    FROM territory_partners tp
    LEFT JOIN territory_partner_locations tpl ON tpl.territory_partner_id = tp.id
    LEFT JOIN partner_location_nodes n ON n.id = tpl.location_id
    LEFT JOIN partner_location_nodes pn ON pn.id = n.parent_id
    LEFT JOIN partner_location_nodes ppn ON ppn.id = pn.parent_id
    LEFT JOIN partner_location_layers pll ON pll.depth = n.depth
    GROUP BY tp.id
    ORDER BY tp.name
");
$partners = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Derive parent and grandparent column headers from actual layer data
$col_parent_hdr      = 'Level 1';
$col_grandparent_hdr = 'Level 2';
$_hdr = $db_conn->query("
    SELECT pll1.layer_name AS parent_layer, pll2.layer_name AS grandparent_layer
    FROM territory_partner_locations tpl
    JOIN partner_location_nodes n   ON n.id   = tpl.location_id
    LEFT JOIN partner_location_nodes pn  ON pn.id  = n.parent_id
    LEFT JOIN partner_location_nodes ppn ON ppn.id = pn.parent_id
    LEFT JOIN partner_location_layers pll1 ON pll1.depth = pn.depth
    LEFT JOIN partner_location_layers pll2 ON pll2.depth = ppn.depth
    WHERE pll1.layer_name IS NOT NULL OR pll2.layer_name IS NOT NULL
    LIMIT 1
");
if ($_hdr && $_row = $_hdr->fetch_assoc()) {
    if (!empty($_row['parent_layer']))      $col_parent_hdr      = $_row['parent_layer'];
    if (!empty($_row['grandparent_layer'])) $col_grandparent_hdr = $_row['grandparent_layer'];
}

$total       = count($partners);
$active      = count(array_filter($partners, fn($r) => $r['is_active']));
$inactive    = $total - $active;
$total_locs  = array_sum(array_column($partners, 'location_count'));
$grand_target = array_sum(array_column($partners, 'total_target'));
$i = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Territory Partners : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        body { font-family: 'Poppins', sans-serif; }

        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 18px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            border-left: 4px solid;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .stat-card.purple { border-color: #667eea; }
        .stat-card.green  { border-color: #10b981; }
        .stat-card.red    { border-color: #ef4444; }
        .stat-card.blue   { border-color: #3b82f6; }
        .stat-card.orange { border-color: #f59e0b; }
        .stat-card h3 { font-size: 26px; font-weight: 700; margin: 0 0 2px 0; color: #1f2937; }
        .stat-card p  { margin: 0; font-size: 11.5px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; }
        .stat-icon { font-size: 36px; opacity: .12; }
        .stat-card.purple .stat-icon { color: #667eea; }
        .stat-card.green  .stat-icon { color: #10b981; }
        .stat-card.red    .stat-icon { color: #ef4444; }
        .stat-card.blue   .stat-icon { color: #3b82f6; }
        .stat-card.orange .stat-icon { color: #f59e0b; }

        .card { border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); border: none; margin-bottom: 20px; }
        .card-header {
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
            border-radius: 10px 10px 0 0 !important;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-header-title { font-size: 14px; font-weight: 600; color: #2c3e50; margin: 0; display: flex; align-items: center; gap: 8px; }
        .card-header-title i { font-size: 18px; color: #667eea; }

        .alert { border-radius: 8px; border: none; font-size: 13.5px; padding: 12px 16px; }
        .alert-success { background: #f0fdf4; color: #166534; border-left: 4px solid #22c55e; }
        .alert-danger  { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-warning { background: #fffbeb; color: #92400e; border-left: 4px solid #f59e0b; }
        .alert-info    { background: #eff6ff; color: #1e40af; border-left: 4px solid #3b82f6; }

        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            transition: all .2s;
            text-decoration: none;
        }
        .btn-add:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,.4); color: #fff; }

        .avatar-circle {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }

        .badge-active   { background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-inactive { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-loc { background: #ede9fe; color: #5b21b6; padding: 3px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }

        .action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            background: #fff;
            cursor: pointer;
            transition: all .15s;
            text-decoration: none;
            padding: 0;
        }
        .action-link:hover { background: #f3f4f6; border-color: #d1d5db; }
        .action-link.delete:hover { background: #fef2f2; border-color: #fecaca; }
        .actions-group { display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }

        table#datatable1 thead th { font-size: 11.5px !important; font-weight: 600 !important; color: #6b7280 !important; text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
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

                    <!-- Page Header -->
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble"><tr>
                                        <td>Territory Partners</td>
                                        <td><a href="add-territory-partner" title="Add Territory Partner">&#10011;</a></td>
                                    </tr></table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Alerts -->
                    <?php if (isset($_REQUEST['addesuccess'])): ?>
                        <div class="alert alert-success mb-3"><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">check_circle</i> Territory partner added successfully.</div>
                    <?php endif; ?>
                    <?php if (isset($_REQUEST['updatedSuccess'])): ?>
                        <div class="alert alert-info mb-3"><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">info</i> Changes saved successfully.</div>
                    <?php endif; ?>
                    <?php if (isset($_REQUEST['deletedDone'])): ?>
                        <div class="alert alert-warning mb-3"><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">warning_amber</i> Territory partner deleted.</div>
                    <?php endif; ?>
                    <?php if (isset($_REQUEST['error'])): ?>
                        <div class="alert alert-danger mb-3"><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">error_outline</i> An error occurred. Please try again.</div>
                    <?php endif; ?>

                    <!-- Stats -->
                    <div class="row">
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card purple">
                                <div>
                                    <h3><?php echo $total; ?></h3>
                                    <p>Total Partners</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">groups</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card green">
                                <div>
                                    <h3><?php echo $active; ?></h3>
                                    <p>Active</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">check_circle</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card red">
                                <div>
                                    <h3><?php echo $inactive; ?></h3>
                                    <p>Inactive</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">cancel</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card blue">
                                <div>
                                    <h3><?php echo $total_locs; ?></h3>
                                    <p>Locations Assigned</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">location_on</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card orange">
                                <div>
                                    <h3 style="font-size:20px;">₹<?php echo inr_format($grand_target, 0); ?></h3>
                                    <p>Total Target</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">track_changes</i>
                            </div>
                        </div>
                    </div>

                    <!-- Table Card -->
                    <div class="card">
                        <div class="card-header">
                            <span class="card-header-title">
                                <i class="material-icons-outlined">people</i>
                                All Territory Partners
                            </span>
                            <a href="add-territory-partner" class="btn-add">
                                <i class="material-icons" style="font-size:16px;">add</i>
                                Add Partner
                            </a>
                        </div>
                        <div class="card-body">
                            <div style="overflow-x:auto;">
                                <table id="datatable1" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Photo</th>
                                            <th>TP ID</th>
                                            <th>Name</th>
                                            <th>Mobile</th>
                                            <th>Locations</th>
                                            <th><?php echo htmlspecialchars($col_grandparent_hdr); ?></th>
                                            <th><?php echo htmlspecialchars($col_parent_hdr); ?></th>
                                            <th>Target Amount</th>
                                            <th>Created By</th>
                                            <th>Updated By</th>
                                            <th>Password</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($partners as $tp):
                                        $enc_id = base64_encode($tp['id']);
                                        $plain_pw = decryptPassword($_enc, $tp['password']);
                                        $initials = strtoupper(substr($tp['name'], 0, 1));
                                        $loc_data = [];
                                        if (!empty($tp['location_details'])) {
                                            foreach (explode('~~', $tp['location_details']) as $_d) {
                                                $_parts = explode('||', $_d, 4);
                                                $loc_data[] = ['layer' => $_parts[0] ?: '—', 'name' => $_parts[1] ?? $_d, 'parent' => $_parts[2] ?? '', 'grandparent' => $_parts[3] ?? ''];
                                            }
                                        }
                                        $loc_json = htmlspecialchars(json_encode($loc_data, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                                    ?>
                                        <tr>
                                            <td style="color:#9ca3af;font-size:13px;"><?php echo ++$i; ?></td>
                                            <td>
                                                <?php if ($tp['photo']): ?>
                                                    <img src="tp_photo/<?php echo htmlspecialchars($tp['photo']); ?>"
                                                         style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;">
                                                <?php else: ?>
                                                    <span class="avatar-circle"><?php echo $initials; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><code style="font-size:12px;background:#f3f4f6;padding:2px 7px;border-radius:4px;"><?php echo htmlspecialchars($tp['tp_id']); ?></code></td>
                                            <td>
                                                <span style="font-weight:600;font-size:13.5px;color:#1f2937;"><?php echo htmlspecialchars($tp['name']); ?></span>
                                                <?php if ($tp['email']): ?>
                                                    <br><small style="color:#9ca3af;font-size:11.5px;"><?php echo htmlspecialchars($tp['email']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:13.5px;"><?php echo htmlspecialchars($tp['mobile']); ?></td>
                                            <td>
                                                <?php if ($tp['location_count'] > 0): ?>
                                                    <button type="button" class="loc-view-trigger"
                                                            style="border:none;cursor:pointer;background:#667eea;color:#fff;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;white-space:nowrap;"
                                                            data-partner="<?php echo htmlspecialchars($tp['name'], ENT_QUOTES); ?>"
                                                            data-locs="<?php echo $loc_json; ?>">
                                                        <?php echo (int)$tp['location_count']; ?> location<?php echo $tp['location_count'] > 1 ? 's' : ''; ?>
                                                    </button>
                                                <?php else: ?>
                                                    <span style="color:#d1d5db;font-size:12px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $gp_vals = array_values(array_unique(array_filter(array_column($loc_data, 'grandparent'))));
                                                if ($gp_vals):
                                                    foreach ($gp_vals as $_v):
                                                ?>
                                                    <span style="display:inline-block;background:#ede9fe;color:#5b21b6;font-size:11px;font-weight:500;padding:2px 9px;border-radius:20px;margin:2px 2px 2px 0;white-space:nowrap;"><?php echo htmlspecialchars($_v); ?></span>
                                                <?php endforeach; else: ?>
                                                    <span style="color:#d1d5db;font-size:12px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $p_vals = array_values(array_unique(array_filter(array_column($loc_data, 'parent'))));
                                                if ($p_vals):
                                                    foreach ($p_vals as $_v):
                                                ?>
                                                    <span style="display:inline-block;background:#e0f2fe;color:#0369a1;font-size:11px;font-weight:500;padding:2px 9px;border-radius:20px;margin:2px 2px 2px 0;white-space:nowrap;"><?php echo htmlspecialchars($_v); ?></span>
                                                <?php endforeach; else: ?>
                                                    <span style="color:#d1d5db;font-size:12px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $target = (float)$tp['total_target'];
                                                if ($target > 0):
                                                ?>
                                                    <span style="font-weight:600;color:#b45309;">
                                                        ₹<?php echo inr_format($target, 2); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color:#d1d5db;font-size:12px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="white-space:nowrap;font-size:12px;color:#374151;">
                                                <div><?php echo htmlspecialchars($tp['created_by'] ?: '—'); ?></div>
                                                <div style="color:#9ca3af;font-size:11px;"><?php echo $tp['created_at'] ? date('d M Y, h:i A', strtotime($tp['created_at'])) : '—'; ?></div>
                                            </td>
                                            <td style="white-space:nowrap;font-size:12px;color:#374151;">
                                                <?php if (!empty($tp['updated_by'])): ?>
                                                    <div><?php echo htmlspecialchars($tp['updated_by']); ?></div>
                                                    <div style="color:#9ca3af;font-size:11px;"><?php echo date('d M Y, h:i A', strtotime($tp['updated_at'])); ?></div>
                                                <?php else: ?>
                                                    <span style="color:#d1d5db;font-size:12px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="white-space:nowrap;">
                                                <?php if ($plain_pw === null): ?>
                                                    <span style="font-size:11px;color:#9ca3af;font-style:italic;">Hashed — ask user to login once</span>
                                                <?php else: ?>
                                                    <span class="pw-text" style="font-family:monospace;font-size:12px;letter-spacing:1px;">••••••••</span>
                                                    <span class="pw-plain" style="display:none;font-family:monospace;font-size:12px;"><?php echo htmlspecialchars($plain_pw, ENT_QUOTES); ?></span>
                                                    <button type="button" onclick="togglePw(this)" style="border:none;background:none;padding:0 4px;cursor:pointer;vertical-align:middle;" title="Show/Hide">
                                                        <i class="material-icons-outlined" style="font-size:15px;color:#9ca3af;vertical-align:middle;">visibility</i>
                                                    </button>
                                                    <button type="button" onclick="copyPw(this)" data-pw="<?php echo htmlspecialchars($plain_pw, ENT_QUOTES); ?>" style="border:none;background:none;padding:0 4px;cursor:pointer;vertical-align:middle;" title="Copy password">
                                                        <i class="material-icons-outlined" style="font-size:15px;color:#9ca3af;vertical-align:middle;">content_copy</i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($tp['is_active']): ?>
                                                    <span class="badge-active">Active</span>
                                                <?php else: ?>
                                                    <span class="badge-inactive">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="actions-group">
                                                    <a href="edit-territory-partner?tpid=<?php echo $enc_id; ?>" class="action-link" title="Edit">
                                                        <i class="material-icons-outlined" style="font-size:17px;color:#667eea;">edit</i>
                                                    </a>
                                                    <button type="button" class="action-link toggle-status-btn"
                                                            title="<?php echo $tp['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                                            data-id="<?php echo $enc_id; ?>"
                                                            data-type="tp"
                                                            data-status="<?php echo (int)$tp['is_active']; ?>"
                                                            data-name="<?php echo htmlspecialchars($tp['name'], ENT_QUOTES); ?>">
                                                        <i class="material-icons-outlined" style="font-size:17px;color:<?php echo $tp['is_active'] ? '#10b981' : '#9ca3af'; ?>;">power_settings_new</i>
                                                    </button>
                                                    <a href="delete-territory-partner?tpid=<?php echo $enc_id; ?>"
                                                       class="action-link delete" title="Delete"
                                                       onclick="return confirm('Delete \'<?php echo addslashes(htmlspecialchars($tp['name'])); ?>\'? Location assignments will also be removed.');">
                                                        <i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i>
                                                    </a>
                                                </div>
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

<!-- Location View Modal -->
<div class="modal fade" id="locViewModal" tabindex="-1" aria-labelledby="locViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-md">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                <h6 class="modal-title" id="locViewModalLabel" style="font-weight:600;color:#1f2937;">
                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#667eea;">location_on</i>
                    Assigned Locations
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="locViewModalBody" style="padding:16px 20px;">
            </div>
            <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
var CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';

$(document).on('click', '.toggle-status-btn', function () {
    var $btn       = $(this);
    var id         = $btn.data('id');
    var name       = $btn.data('name');
    var curStatus  = parseInt($btn.data('status'));
    var newStatus  = curStatus === 1 ? 0 : 1;
    var action     = newStatus === 1 ? 'Activate' : 'Deactivate';

    if (!confirm(action + ' "' + name + '"?')) return;

    $.post('toggle-partner-status.php', {
        csrf_token: CSRF_TOKEN, type: 'tp', id: id, status: newStatus
    }, function (res) {
        if (!res.success) { alert('Failed. Please try again.'); return; }
        $btn.data('status', res.new_status);
        var $icon = $btn.find('i');
        if (res.new_status === 1) {
            $icon.css('color', '#10b981');
            $btn.attr('title', 'Deactivate');
        } else {
            $icon.css('color', '#9ca3af');
            $btn.attr('title', 'Activate');
        }
        var $badge = $btn.closest('tr').find('.badge-active, .badge-inactive');
        if (res.new_status === 1) {
            $badge.removeClass('badge-inactive').addClass('badge-active').text('Active');
        } else {
            $badge.removeClass('badge-active').addClass('badge-inactive').text('Inactive');
        }
    }, 'json').fail(function () { alert('Request failed. Please try again.'); });
});

function togglePw(btn) {
    var $td = $(btn).closest('td');
    var $mask = $td.find('.pw-text');
    var $plain = $td.find('.pw-plain');
    var $icon = $(btn).find('i');
    if ($plain.is(':hidden')) {
        $mask.hide(); $plain.show();
        $icon.text('visibility_off');
    } else {
        $plain.hide(); $mask.show();
        $icon.text('visibility');
    }
}

function copyPw(btn) {
    var password = $(btn).data('pw');
    function toast() {
        Swal.fire({ icon: 'success', title: 'Copied!', text: 'Password copied to clipboard', timer: 1500, showConfirmButton: false, toast: true, position: 'top-end' });
    }
    if (navigator.clipboard) {
        navigator.clipboard.writeText(password).then(toast, function () {
            fallbackCopy(password); toast();
        });
    } else {
        fallbackCopy(password); toast();
    }
}

function fallbackCopy(text) {
    var $tmp = $('<input>').val(text).appendTo('body').select();
    document.execCommand('copy');
    $tmp.remove();
}

$(document).on('click', '.loc-view-trigger', function () {
    var partner = $(this).data('partner');
    var locs    = $(this).data('locs');
    $('#locViewModalLabel').html(
        '<i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#667eea;">location_on</i>' +
        $('<span>').text(partner).html()
    );
    var grouped = {};
    var order   = [];
    $.each(locs, function (_, loc) {
        var layer = loc.layer || '—';
        if (!grouped[layer]) { grouped[layer] = []; order.push(layer); }
        grouped[layer].push({ name: loc.name, parent: loc.parent || '', grandparent: loc.grandparent || '' });
    });
    var html = '';
    $.each(order, function (_, layer) {
        html += '<div style="margin-bottom:14px;">';
        html += '<div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.8px;border-bottom:1px solid #f3f4f6;padding-bottom:5px;margin-bottom:7px;">' + $('<div>').text(layer).html() + '</div>';
        $.each(grouped[layer], function (_, item) {
            html += '<div style="padding:5px 0 5px 8px;font-size:13.5px;color:#1f2937;border-bottom:1px dotted #f3f4f6;display:flex;align-items:center;gap:7px;">' +
                    '<i class="material-icons-outlined" style="font-size:15px;color:#a5b4fc;">location_on</i>' +
                    '<div><span>' + $('<div>').text(item.name).html() + '</span>' +
                    (item.grandparent || item.parent
                        ? '<span style="font-size:11px;color:#9ca3af;margin-left:6px;">' +
                          (item.grandparent ? $('<div>').text(item.grandparent).html() + ' › ' : '') +
                          (item.parent ? $('<div>').text(item.parent).html() : '') +
                          '</span>'
                        : '') +
                    '</div></div>';
        });
        html += '</div>';
    });
    $('#locViewModalBody').html(html);
    $('#locViewModal').modal('show');
});
</script>
</body>
</html>
