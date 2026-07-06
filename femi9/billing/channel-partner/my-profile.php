<?php
include("checksession.php");
include("config.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$cp_id = (int) $Login_user_IDvl;

// Fetch CP details
$stmtCp = $db_conn->prepare("SELECT * FROM channel_partners WHERE id = ?");
$stmtCp->bind_param('i', $cp_id);
$stmtCp->execute();
$cp = $stmtCp->get_result()->fetch_assoc();
$stmtCp->close();

// Fetch assigned locations
$stmtLoc = $db_conn->prepare("
    SELECT
        n.id,
        n.name            AS location_name,
        n.depth,
        n.deposit_amount,
        COALESCE(n.target_amount, 0) AS target_amount,
        pll.layer_name,
        pn.name           AS parent_name,
        ppn.name          AS grandparent_name
    FROM channel_partner_locations cpl
    JOIN partner_location_nodes n    ON n.id   = cpl.location_id
    LEFT JOIN partner_location_nodes pn  ON pn.id  = n.parent_id
    LEFT JOIN partner_location_nodes ppn ON ppn.id = pn.parent_id
    LEFT JOIN partner_location_layers pll ON pll.depth = n.depth
    WHERE cpl.channel_partner_id = ?
    ORDER BY n.depth ASC, n.name ASC
");
$stmtLoc->bind_param('i', $cp_id);
$stmtLoc->execute();
$assigned_locations = $stmtLoc->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtLoc->close();

$total_deposit = array_sum(array_column($assigned_locations, 'deposit_amount'));
$total_target  = array_sum(array_column($assigned_locations, 'target_amount'));

// Handle profile update
if (isset($_REQUEST['updateprofile'])) {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: my-profile.php?error=1"); exit;
    }

    $name              = trim($_POST['cp_name'] ?? '');
    $email             = trim($_POST['cp_email'] ?? '') ?: null;
    $branch_line1      = trim($_POST['cp_branch_line1'] ?? '');
    $branch_line2      = trim($_POST['cp_branch_line2'] ?? '') ?: null;
    $branch_city       = trim($_POST['cp_branch_city'] ?? '') ?: null;
    $branch_district   = trim($_POST['cp_branch_district'] ?? '') ?: null;
    $branch_state      = trim($_POST['cp_branch_state'] ?? '') ?: null;
    $branch_country    = trim($_POST['cp_branch_country'] ?? '') ?: null;
    $branch_pincode    = trim($_POST['cp_branch_pincode'] ?? '') ?: null;
    $delivery_line1    = trim($_POST['cp_delivery_line1'] ?? '');
    $delivery_line2    = trim($_POST['cp_delivery_line2'] ?? '') ?: null;
    $delivery_city     = trim($_POST['cp_delivery_city'] ?? '') ?: null;
    $delivery_district = trim($_POST['cp_delivery_district'] ?? '') ?: null;
    $delivery_state    = trim($_POST['cp_delivery_state'] ?? '') ?: null;
    $delivery_country  = trim($_POST['cp_delivery_country'] ?? '') ?: null;
    $delivery_pincode  = trim($_POST['cp_delivery_pincode'] ?? '') ?: null;

    if (!$name || !$branch_line1 || !$delivery_line1) {
        header("Location: my-profile.php?error=1"); exit;
    }

    // Photo upload (stored alongside company-managed CP photos so admin view stays in sync)
    $photo_update = '';
    if (!empty($_FILES['cp_photo']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['cp_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed) && $_FILES['cp_photo']['size'] <= 2097152) {
            $filename = 'cp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest     = __DIR__ . '/../company/cp_photo/' . $filename;
            if (move_uploaded_file($_FILES['cp_photo']['tmp_name'], $dest)) {
                $photo_update = $filename;
            }
        }
    }

    if ($photo_update) {
        $stmtUpd = $db_conn->prepare("
            UPDATE channel_partners
            SET name=?, email=?,
                branch_line1=?, branch_line2=?, branch_city=?, branch_district=?, branch_state=?, branch_country=?, branch_pincode=?,
                delivery_line1=?, delivery_line2=?, delivery_city=?, delivery_district=?, delivery_state=?, delivery_country=?, delivery_pincode=?,
                photo=?
            WHERE id=?
        ");
        $stmtUpd->bind_param('sssssssssssssssssi',
            $name, $email,
            $branch_line1, $branch_line2, $branch_city, $branch_district, $branch_state, $branch_country, $branch_pincode,
            $delivery_line1, $delivery_line2, $delivery_city, $delivery_district, $delivery_state, $delivery_country, $delivery_pincode,
            $photo_update, $cp_id
        );
    } else {
        $stmtUpd = $db_conn->prepare("
            UPDATE channel_partners
            SET name=?, email=?,
                branch_line1=?, branch_line2=?, branch_city=?, branch_district=?, branch_state=?, branch_country=?, branch_pincode=?,
                delivery_line1=?, delivery_line2=?, delivery_city=?, delivery_district=?, delivery_state=?, delivery_country=?, delivery_pincode=?
            WHERE id=?
        ");
        $stmtUpd->bind_param('ssssssssssssssssi',
            $name, $email,
            $branch_line1, $branch_line2, $branch_city, $branch_district, $branch_state, $branch_country, $branch_pincode,
            $delivery_line1, $delivery_line2, $delivery_city, $delivery_district, $delivery_state, $delivery_country, $delivery_pincode,
            $cp_id
        );
    }
    $stmtUpd->execute();
    $stmtUpd->close();

    if ($photo_update && !empty($cp['photo'])) {
        $old_path = __DIR__ . '/../company/cp_photo/' . $cp['photo'];
        if (file_exists($old_path)) @unlink($old_path);
    }

    header("Location: my-profile.php?Updatedsuccess=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile : <?php echo $business_name; ?></title>
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
        /* ── Assigned Locations Card ────────────────────── */
        .loc-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,.07);
            border: none;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .loc-card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .loc-header-left { display: flex; align-items: center; gap: 12px; }
        .loc-icon-box {
            width: 40px; height: 40px; border-radius: 10px;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .loc-icon-box i { font-size: 20px; color: #fff; }
        .loc-header-title { font-size: 14px; font-weight: 700; color: #1e293b; margin: 0; line-height: 1.2; }
        .loc-header-sub   { font-size: 12px; color: #94a3b8; margin: 0; }
        .loc-count-pill {
            background: #d1fae5; color: #065f46;
            font-size: 12px; font-weight: 700;
            padding: 4px 12px; border-radius: 20px;
        }

        /* summary strip */
        .loc-summary {
            display: flex; gap: 0;
            border-bottom: 1px solid #f1f5f9;
            background: #f8fafc;
        }
        .loc-summary-item {
            flex: 1; padding: 14px 20px;
            border-right: 1px solid #f1f5f9;
            text-align: center;
        }
        .loc-summary-item:last-child { border-right: none; }
        .loc-summary-item .val { font-size: 18px; font-weight: 700; color: #1e293b; line-height: 1; }
        .loc-summary-item .lbl { font-size: 10.5px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .6px; margin-top: 4px; }

        /* location rows */
        .loc-list { padding: 8px 0; }
        .loc-item {
            display: flex; align-items: flex-start; gap: 16px;
            padding: 16px 24px;
            border-bottom: 1px solid #f8fafc;
            transition: background .12s;
        }
        .loc-item:last-child { border-bottom: none; }
        .loc-item:hover { background: #fafbff; }

        .loc-pin-box {
            width: 38px; height: 38px; border-radius: 10px;
            background: #ecfdf5;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .loc-pin-box i { font-size: 18px; color: #10b981; }

        .loc-body { flex: 1; min-width: 0; }
        .loc-layer-tag {
            display: inline-block;
            font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
            color: #0369a1; background: #e0f2fe;
            padding: 2px 8px; border-radius: 4px; margin-bottom: 4px;
        }
        .loc-name { font-size: 14px; font-weight: 600; color: #1e293b; margin-bottom: 3px; }
        .loc-breadcrumb { font-size: 12px; color: #94a3b8; display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
        .loc-breadcrumb i { font-size: 13px; }

        .loc-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
        .loc-meta-item { font-size: 12px; color: #64748b; display: flex; align-items: center; gap: 4px; white-space: nowrap; }
        .loc-meta-item i { font-size: 14px; color: #cbd5e1; }
        .loc-meta-item strong { color: #1e293b; }

        .loc-empty { text-align: center; padding: 40px 20px; }
        .loc-empty i { font-size: 40px; color: #e2e8f0; display: block; margin-bottom: 10px; }
        .loc-empty p { font-size: 13px; color: #94a3b8; margin: 0; }
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
                                <h1><table class="headertble"><tr><td>My Profile</td></tr></table></h1>
                            </div>
                        </div>
                    </div>
                    <?php if (isset($_REQUEST['Updatedsuccess'])): ?><div class="alert alert-success">Profile updated successfully.</div><?php endif; ?>
                    <?php if (isset($_REQUEST['error'])): ?><div class="alert alert-danger">Please fill all required fields.</div><?php endif; ?>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <p class="text-muted mb-3" style="font-size:13px;">
                                        <i class="material-icons-two-tone" style="vertical-align:middle;font-size:16px;">handshake</i>
                                        ID: <strong><?php echo htmlspecialchars($cp['cp_id'] ?? ''); ?></strong>
                                    </p>

                                    <form method="post" enctype="multipart/form-data" onsubmit="return confirm('Please confirm update.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <div class="example-container">
                                            <div class="example-content">

<div class="mb-3">
    <label class="form-label">Name <span class="text-danger">*</span></label>
    <input type="text" name="cp_name" class="form-control" maxlength="100" required
           value="<?php echo htmlspecialchars($cp['name'] ?? ''); ?>">
</div>

<div class="mb-3">
    <label class="form-label">Mobile</label>
    <input type="text" value="<?php echo htmlspecialchars($cp['mobile'] ?? ''); ?>" disabled class="form-control">
    <small class="text-muted">Mobile number cannot be changed (used for login).</small>
</div>

<div class="mb-3">
    <label class="form-label">Email <span class="text-muted" style="font-size:12px;">(optional)</span></label>
    <input type="email" name="cp_email" class="form-control" maxlength="100"
           value="<?php echo htmlspecialchars($cp['email'] ?? ''); ?>">
</div>

<!-- Branch Address -->
<div class="mb-1">
    <label class="form-label fw-semibold">Branch Address <span class="text-danger">*</span></label>
</div>
<div class="row g-2 mb-3">
    <div class="col-12">
        <input type="text" name="cp_branch_line1" class="form-control" maxlength="255" placeholder="Line 1 (Building / Street) *" required
               value="<?php echo htmlspecialchars($cp['branch_line1'] ?? ''); ?>">
    </div>
    <div class="col-12">
        <input type="text" name="cp_branch_line2" class="form-control" maxlength="255" placeholder="Line 2 (Area / Landmark)"
               value="<?php echo htmlspecialchars($cp['branch_line2'] ?? ''); ?>">
    </div>
    <div class="col-6">
        <input type="text" name="cp_branch_city" class="form-control" maxlength="100" placeholder="City"
               value="<?php echo htmlspecialchars($cp['branch_city'] ?? ''); ?>">
    </div>
    <div class="col-6">
        <input type="text" name="cp_branch_district" class="form-control" maxlength="100" placeholder="District"
               value="<?php echo htmlspecialchars($cp['branch_district'] ?? ''); ?>">
    </div>
    <div class="col-6">
        <input type="text" name="cp_branch_state" class="form-control" maxlength="100" placeholder="State"
               value="<?php echo htmlspecialchars($cp['branch_state'] ?? ''); ?>">
    </div>
    <div class="col-6">
        <input type="text" name="cp_branch_country" class="form-control" maxlength="100" placeholder="Country"
               value="<?php echo htmlspecialchars($cp['branch_country'] ?? ''); ?>">
    </div>
    <div class="col-6">
        <input type="text" name="cp_branch_pincode" id="cp_branch_pincode" class="form-control" maxlength="20" placeholder="Pincode"
               value="<?php echo htmlspecialchars($cp['branch_pincode'] ?? ''); ?>">
    </div>
</div>

<!-- Delivery Address -->
<div class="mb-1 d-flex align-items-center gap-2">
    <label class="form-label fw-semibold mb-0">Delivery Address <span class="text-danger">*</span></label>
    <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="copyBranchToDelivery" style="font-size:11px;padding:2px 8px;">
        <i class="material-icons" style="font-size:13px;vertical-align:middle;">content_copy</i> Same as Branch
    </button>
</div>
<div class="row g-2 mb-3">
    <div class="col-12">
        <input type="text" name="cp_delivery_line1" id="cp_delivery_line1" class="form-control" maxlength="255" placeholder="Line 1 (Building / Street) *" required
               value="<?php echo htmlspecialchars($cp['delivery_line1'] ?? ''); ?>">
    </div>
    <div class="col-12">
        <input type="text" name="cp_delivery_line2" id="cp_delivery_line2" class="form-control" maxlength="255" placeholder="Line 2 (Area / Landmark)"
               value="<?php echo htmlspecialchars($cp['delivery_line2'] ?? ''); ?>">
    </div>
    <div class="col-6">
        <input type="text" name="cp_delivery_city" id="cp_delivery_city" class="form-control" maxlength="100" placeholder="City"
               value="<?php echo htmlspecialchars($cp['delivery_city'] ?? ''); ?>">
    </div>
    <div class="col-6">
        <input type="text" name="cp_delivery_district" id="cp_delivery_district" class="form-control" maxlength="100" placeholder="District"
               value="<?php echo htmlspecialchars($cp['delivery_district'] ?? ''); ?>">
    </div>
    <div class="col-6">
        <input type="text" name="cp_delivery_state" id="cp_delivery_state" class="form-control" maxlength="100" placeholder="State"
               value="<?php echo htmlspecialchars($cp['delivery_state'] ?? ''); ?>">
    </div>
    <div class="col-6">
        <input type="text" name="cp_delivery_country" id="cp_delivery_country" class="form-control" maxlength="100" placeholder="Country"
               value="<?php echo htmlspecialchars($cp['delivery_country'] ?? ''); ?>">
    </div>
    <div class="col-6">
        <input type="text" name="cp_delivery_pincode" id="cp_delivery_pincode" class="form-control" maxlength="20" placeholder="Pincode"
               value="<?php echo htmlspecialchars($cp['delivery_pincode'] ?? ''); ?>">
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Photo <span class="text-muted" style="font-size:12px;">(leave blank to keep current, max 2MB)</span></label>
    <?php if (!empty($cp['photo'])): ?>
        <div class="mb-2">
            <img src="../company/cp_photo/<?php echo htmlspecialchars($cp['photo']); ?>"
                 style="width:52px;height:52px;border-radius:6px;object-fit:cover;border:1px solid #e0e0e0;">
        </div>
    <?php endif; ?>
    <input type="file" name="cp_photo" class="form-control" accept="image/*">
</div>

<button type="submit" name="updateprofile" class="btn btn-primary"><i class="material-icons">update</i>Update</button>

                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Assigned Locations -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="loc-card">

                                <div class="loc-card-header">
                                    <div class="loc-header-left">
                                        <div class="loc-icon-box">
                                            <i class="material-icons-outlined">location_on</i>
                                        </div>
                                        <div>
                                            <p class="loc-header-title">Assigned Locations</p>
                                            <p class="loc-header-sub">Territories managed by you</p>
                                        </div>
                                    </div>
                                    <span class="loc-count-pill"><?php echo count($assigned_locations); ?> Location<?php echo count($assigned_locations) !== 1 ? 's' : ''; ?></span>
                                </div>

                                <?php if (!empty($assigned_locations)): ?>
                                <div class="loc-summary">
                                    <div class="loc-summary-item">
                                        <div class="val"><?php echo count($assigned_locations); ?></div>
                                        <div class="lbl">Locations</div>
                                    </div>
                                    <div class="loc-summary-item">
                                        <div class="val">&#8377;<?php echo inr_format($total_deposit, 0); ?></div>
                                        <div class="lbl">Total Deposit</div>
                                    </div>
                                    <div class="loc-summary-item">
                                        <div class="val">&#8377;<?php echo inr_format($total_target, 0); ?></div>
                                        <div class="lbl">Total Target</div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="loc-list">
                                    <?php if (empty($assigned_locations)): ?>
                                    <div class="loc-empty">
                                        <i class="material-icons-outlined">location_off</i>
                                        <p>No locations assigned yet.<br>Contact admin to get your territories assigned.</p>
                                    </div>
                                    <?php else: ?>
                                        <?php foreach ($assigned_locations as $loc): ?>
                                        <div class="loc-item">

                                            <div class="loc-pin-box">
                                                <i class="material-icons-outlined">place</i>
                                            </div>

                                            <div class="loc-body">
                                                <?php if (!empty($loc['layer_name'])): ?>
                                                <span class="loc-layer-tag"><?php echo htmlspecialchars($loc['layer_name']); ?></span>
                                                <?php endif; ?>
                                                <div class="loc-name"><?php echo htmlspecialchars($loc['location_name']); ?></div>
                                                <?php if (!empty($loc['parent_name']) || !empty($loc['grandparent_name'])): ?>
                                                <div class="loc-breadcrumb">
                                                    <i class="material-icons-outlined" style="font-size:13px;color:#cbd5e1;">arrow_right</i>
                                                    <?php if (!empty($loc['grandparent_name'])): ?>
                                                        <span><?php echo htmlspecialchars($loc['grandparent_name']); ?></span>
                                                        <i class="material-icons-outlined" style="font-size:13px;">chevron_right</i>
                                                    <?php endif; ?>
                                                    <?php if (!empty($loc['parent_name'])): ?>
                                                        <span><?php echo htmlspecialchars($loc['parent_name']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="loc-meta">
                                                <?php if (!empty($loc['deposit_amount'])): ?>
                                                <div class="loc-meta-item">
                                                    <i class="material-icons-outlined">account_balance_wallet</i>
                                                    Deposit: <strong>&#8377;<?php echo inr_format((float)$loc['deposit_amount'], 0); ?></strong>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (!empty($loc['target_amount'])): ?>
                                                <div class="loc-meta-item">
                                                    <i class="material-icons-outlined">flag</i>
                                                    Target: <strong>&#8377;<?php echo inr_format((float)$loc['target_amount'], 0); ?></strong>
                                                </div>
                                                <?php endif; ?>
                                            </div>

                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                    </div>
                    <!-- /Assigned Locations -->

                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
document.getElementById('copyBranchToDelivery').addEventListener('click', function () {
    var map = [
        ['cp_branch_line1',    'cp_delivery_line1'],
        ['cp_branch_line2',    'cp_delivery_line2'],
        ['cp_branch_city',     'cp_delivery_city'],
        ['cp_branch_district', 'cp_delivery_district'],
        ['cp_branch_state',    'cp_delivery_state'],
        ['cp_branch_country',  'cp_delivery_country'],
        ['cp_branch_pincode',  'cp_delivery_pincode'],
    ];
    map.forEach(function (pair) {
        var src = document.querySelector('[name="' + pair[0] + '"]');
        var dst = document.getElementById(pair[1]);
        if (src && dst) dst.value = src.value;
    });
});
</script>
</body>
</html>
