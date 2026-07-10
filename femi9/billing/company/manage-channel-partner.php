<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');
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

// ── Fetch all channel partners with location count, names and total deposit ───
$result = $db_conn->query("
    SELECT cp.*,
           COUNT(cpl.id) AS location_count,
           GROUP_CONCAT(n.name ORDER BY n.name SEPARATOR ', ') AS location_names,
           COALESCE(SUM(n.deposit_amount), 0) AS total_deposit,
           GROUP_CONCAT(CONCAT(COALESCE(pll.layer_name,''), '||', n.name, '||', COALESCE(pn.name,'')) ORDER BY pll.depth, n.name SEPARATOR '~~') AS location_details
    FROM channel_partners cp
    LEFT JOIN channel_partner_locations cpl ON cpl.channel_partner_id = cp.id
    LEFT JOIN partner_location_nodes n ON n.id = cpl.location_id
    LEFT JOIN partner_location_nodes pn ON pn.id = n.parent_id
    LEFT JOIN partner_location_layers pll ON pll.depth = n.depth
    GROUP BY cp.id
    ORDER BY cp.name
");
$partners = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$i = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Channel Partners : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <style>
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
        #datatable1 thead th { font-size: 11.5px !important; font-weight: 600 !important; color: #6b7280 !important; text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
    </style>
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
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

                    <!-- Alerts -->
                    <div class="row">
                        <div class="col">
                            <?php if (isset($_REQUEST['addesuccess'])): ?>
                                <div class="alert alert-success">Channel partner added successfully.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['updatedSuccess'])): ?>
                                <div class="alert alert-info">Changes saved successfully.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['deletedDone'])): ?>
                                <div class="alert alert-warning">Channel partner deleted successfully.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['error'])): ?>
                                <div class="alert alert-danger">An error occurred. Please try again.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Page header -->
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble">
                                        <tr>
                                            <td>Channel Partners</td>
                                            <td>
                                                <a href="add-channel-partner" title="Add Channel Partner">&#10011;</a>
                                            </td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div style="overflow-x:scroll;">
                                        <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Photo</th>
                                                    <th>CP ID</th>
                                                    <th>Name</th>
                                                    <th>Mobile</th>
                                                    <th>Locations</th>
                                                    <th>Total Deposit</th>
                                                    <th>Created By</th>
                                                    <th>Updated By</th>
                                                    <th>Password</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($partners as $cp):
                                                $enc_id = base64_encode($cp['id']);
                                                $plain_pw = decryptPassword($_enc, $cp['password']);
                                                $loc_data = [];
                                                if (!empty($cp['location_details'])) {
                                                    foreach (explode('~~', $cp['location_details']) as $_d) {
                                                        $_parts = explode('||', $_d, 3);
                                                        $loc_data[] = ['layer' => $_parts[0] ?: '—', 'name' => $_parts[1] ?? $_d, 'parent' => $_parts[2] ?? ''];
                                                    }
                                                }
                                                $loc_json = htmlspecialchars(json_encode($loc_data, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                                            ?>
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td>
                                                        <?php if ($cp['photo']): ?>
                                                            <img src="cp_photo/<?php echo htmlspecialchars($cp['photo']); ?>"
                                                                 style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:1px solid #e0e0e0;">
                                                        <?php else: ?>
                                                            <span style="display:inline-flex;align-items:center;justify-content:center;
                                                                         width:34px;height:34px;border-radius:50%;background:#e8eaf6;
                                                                         font-size:13px;font-weight:700;color:#5c6bc0;">
                                                                <?php echo strtoupper(substr($cp['name'], 0, 1)); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><code style="font-size:12px;"><?php echo htmlspecialchars($cp['cp_id']); ?></code></td>
                                                    <td><strong><?php echo htmlspecialchars($cp['name']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($cp['mobile']); ?></td>
                                                    <td>
                                                        <?php if ($cp['location_count'] > 0): ?>
                                                            <button type="button" class="badge badge-primary loc-view-trigger"
                                                                    style="border:none;cursor:pointer;"
                                                                    data-partner="<?php echo htmlspecialchars($cp['name'], ENT_QUOTES); ?>"
                                                                    data-locs="<?php echo $loc_json; ?>">
                                                                <?php echo (int)$cp['location_count']; ?> location<?php echo $cp['location_count'] > 1 ? 's' : ''; ?>
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted" style="font-size:12px;">None assigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $deposit = (float)$cp['total_deposit'];
                                                        if ($deposit > 0):
                                                        ?>
                                                            <span style="font-weight:600;color:#1565c0;">
                                                                ₹<?php echo inr_format($deposit, 2); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted" style="font-size:12px;">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="white-space:nowrap;font-size:12px;color:#374151;">
                                                        <div><?php echo htmlspecialchars($cp['created_by'] ?: '—'); ?></div>
                                                        <div style="color:#9ca3af;font-size:11px;"><?php echo $cp['created_at'] ? date('d M Y, h:i A', strtotime($cp['created_at'])) : '—'; ?></div>
                                                    </td>
                                                    <td style="white-space:nowrap;font-size:12px;color:#374151;">
                                                        <?php if (!empty($cp['updated_by'])): ?>
                                                            <div><?php echo htmlspecialchars($cp['updated_by']); ?></div>
                                                            <div style="color:#9ca3af;font-size:11px;"><?php echo date('d M Y, h:i A', strtotime($cp['updated_at'])); ?></div>
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
                                                        <?php if ($cp['is_active']): ?>
                                                            <span class="badge badge-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="actions-group">
                                                            <a href="edit-channel-partner?cpid=<?php echo $enc_id; ?>" class="action-link" title="Edit">
                                                                <i class="material-icons-outlined" style="font-size:17px;color:#5c6bc0;">edit</i>
                                                            </a>
                                                            <button type="button" class="action-link toggle-status-btn"
                                                                    title="<?php echo $cp['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                                                    data-id="<?php echo $enc_id; ?>"
                                                                    data-type="cp"
                                                                    data-status="<?php echo (int)$cp['is_active']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($cp['name'], ENT_QUOTES); ?>">
                                                                <i class="material-icons-outlined" style="font-size:17px;color:<?php echo $cp['is_active'] ? '#10b981' : '#9ca3af'; ?>;">power_settings_new</i>
                                                            </button>
                                                            <a href="delete-channel-partner?cpid=<?php echo $enc_id; ?>"
                                                               class="action-link delete" title="Delete"
                                                               onclick="return confirm('Delete \'<?php echo addslashes(htmlspecialchars($cp['name'])); ?>\'? Their location assignments will also be removed.');">
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
    </div>
</div>

<!-- Location View Modal -->
<div class="modal fade" id="locViewModal" tabindex="-1" aria-labelledby="locViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-md">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                <h6 class="modal-title" id="locViewModalLabel" style="font-weight:600;color:#1f2937;">
                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#5c6bc0;">location_on</i>
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
<script src="../../assets/plugins/highlight/highlight.pack.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
var CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';

$(document).on('click', '.toggle-status-btn', function () {
    var $btn      = $(this);
    var id        = $btn.data('id');
    var name      = $btn.data('name');
    var curStatus = parseInt($btn.data('status'));
    var newStatus = curStatus === 1 ? 0 : 1;
    var action    = newStatus === 1 ? 'Activate' : 'Deactivate';

    if (!confirm(action + ' "' + name + '"?')) return;

    $.post('toggle-partner-status.php', {
        csrf_token: CSRF_TOKEN, type: 'cp', id: id, status: newStatus
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
        var $row = $btn.closest('tr');
        var $badge = $row.find('.badge-success, .badge-danger');
        if (res.new_status === 1) {
            $badge.removeClass('badge-danger').addClass('badge-success').text('Active');
        } else {
            $badge.removeClass('badge-success').addClass('badge-danger').text('Inactive');
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
        '<i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#5c6bc0;">location_on</i>' +
        $('<span>').text(partner).html()
    );
    var grouped = {};
    var order   = [];
    $.each(locs, function (_, loc) {
        var layer = loc.layer || '—';
        if (!grouped[layer]) { grouped[layer] = []; order.push(layer); }
        grouped[layer].push({ name: loc.name, parent: loc.parent || '' });
    });
    var html = '';
    $.each(order, function (_, layer) {
        html += '<div style="margin-bottom:14px;">';
        html += '<div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.8px;border-bottom:1px solid #f3f4f6;padding-bottom:5px;margin-bottom:7px;">' + $('<div>').text(layer).html() + '</div>';
        $.each(grouped[layer], function (_, item) {
            html += '<div style="padding:5px 0 5px 8px;font-size:13.5px;color:#1f2937;border-bottom:1px dotted #f3f4f6;display:flex;align-items:center;gap:7px;">' +
                    '<i class="material-icons-outlined" style="font-size:15px;color:#9fa8da;">location_on</i>' +
                    '<div><span>' + $('<div>').text(item.name).html() + '</span>' +
                    (item.parent ? '<span style="font-size:11px;color:#9ca3af;margin-left:6px;">(' + $('<div>').text(item.parent).html() + ')</span>' : '') +
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
