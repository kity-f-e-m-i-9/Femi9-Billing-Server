<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

$vendors = $db_conn->query(
    "SELECT id, vendor_name, address, gstin, phone, email, is_active
     FROM neksomo_vendors
     ORDER BY vendor_name ASC"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Vendors : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">

    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <style>
        .action-link { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;text-decoration:none;padding:0; }
        .action-link:hover { background:#f3f4f6;border-color:#d1d5db; }
        .action-link.delete:hover { background:#fef2f2;border-color:#fecaca; }
        .actions-group { display:inline-flex;align-items:center;gap:5px;white-space:nowrap; }
        .status-badge { display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600; }
        .status-badge.active { background:#d1fae5;color:#065f46; }
        .status-badge.inactive { background:#f1f5f9;color:#64748b; }
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
                                    <?php if (isset($_REQUEST['addsuccess'])) { ?><div class="alert alert-success">Vendor added.</div><?php } ?>
                                    <?php if (isset($_REQUEST['updatesuccess'])) { ?><div class="alert alert-success">Vendor updated.</div><?php } ?>
                                    <?php if (isset($_REQUEST['statuschanged'])) { ?><div class="alert alert-success">Vendor status updated.</div><?php } ?>
                                    <?php if (isset($_REQUEST['error'])) { ?><div class="alert alert-danger">Something went wrong. Please try again.</div><?php } ?>

                                    <h1>
                                        <table class="headertble">
                                        <tr>
                                        <td>Vendors</td>
                                        <td><a href="neksomo-vendor-add" title="Add Vendor">&#10011;</a></td>
                                        </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div style="overflow-x:scroll;">
                                        <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Vendor Name</th>
                                                    <th>Address</th>
                                                    <th>GSTIN</th>
                                                    <th>Phone</th>
                                                    <th>Email</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($vendors as $v): $vid = base64_encode((string)$v['id']); ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($v['vendor_name']); ?></td>
                                                    <td><?php echo nl2br(htmlspecialchars($v['address'] ?? '')); ?></td>
                                                    <td><?php echo htmlspecialchars($v['gstin'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($v['phone'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($v['email'] ?? ''); ?></td>
                                                    <td>
                                                        <?php if ((int)$v['is_active'] === 1): ?>
                                                            <span class="status-badge active">Active</span>
                                                        <?php else: ?>
                                                            <span class="status-badge inactive">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="actions-group">
                                                            <a href="neksomo-vendor-edit.php?id=<?php echo (int)$v['id']; ?>" class="action-link" title="Edit"><i class="material-icons-outlined" style="font-size:17px;color:#2563eb;">edit</i></a>
                                                            <?php if ((int)$v['is_active'] === 1): ?>
                                                                <a href="toggle-neksomo-vendor-status.php?id=<?php echo $vid; ?>" class="action-link delete" title="Deactivate" onclick="return confirm('Deactivate this vendor? It will no longer appear in the purchase form.');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">block</i></a>
                                                            <?php else: ?>
                                                                <a href="toggle-neksomo-vendor-status.php?id=<?php echo $vid; ?>" class="action-link" title="Reactivate" onclick="return confirm('Reactivate this vendor?');"><i class="material-icons-outlined" style="font-size:17px;color:#10b981;">check_circle</i></a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($vendors)): ?>
                                                <tr><td colspan="7" style="text-align:center;color:#898781;">No vendors added yet.</td></tr>
                                            <?php endif; ?>
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

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/datatables/datatables.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/datatables.js"></script>
</body>

</html>
