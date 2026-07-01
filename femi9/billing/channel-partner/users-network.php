<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$advBalance = 0;
$title = "Users Network";
$tp_id = (int) $Login_user_IDvl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?> : <?php echo $business_name; ?></title>
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
    .users-networks { width:100%; border-collapse:collapse; }
    .users-networks th { color:#777; font-weight:500; padding:8px; border-bottom:2px solid #ddd; }
    .users-networks td { font-size:13px; font-weight:bold; border:1px solid #ddd; padding:6px; }
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
                                <h1><table class="headertble"><tr><td><?php echo $title; ?></td></tr></table></h1>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5>Assigned Locations</h5>

<?php
// Try territory_partner_locations table
$stmtLoc = $db_conn->prepare(
    "SELECT tpl.*, pln.node_name, pln.node_code, pln.target_amount
     FROM territory_partner_locations tpl
     LEFT JOIN partner_location_nodes pln ON tpl.node_id = pln.id
     WHERE tpl.territory_partner_id = ?
     ORDER BY pln.node_name ASC"
);
$hasLocTable = false;
if ($stmtLoc) {
    $stmtLoc->bind_param('i', $tp_id);
    $stmtLoc->execute();
    $locResult = $stmtLoc->get_result();
    $hasLocTable = true;
}
?>

<?php if ($hasLocTable && $locResult->num_rows > 0): ?>
                                    <table class="users-networks">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Location Name</th>
                                                <th>Code</th>
                                                <th>Target Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
<?php
$i = 0;
while ($rowLoc = $locResult->fetch_assoc()) {
    $i++;
?>
                                            <tr>
                                                <td><?php echo $i; ?></td>
                                                <td><?php echo htmlspecialchars($rowLoc['node_name'] ?? '---'); ?></td>
                                                <td><?php echo htmlspecialchars($rowLoc['node_code'] ?? '---'); ?></td>
                                                <td><?php echo !empty($rowLoc['target_amount']) ? '&#8377;' . number_format($rowLoc['target_amount'], 2) : '---'; ?></td>
                                            </tr>
<?php }
$stmtLoc->close();
?>
                                        </tbody>
                                    </table>
<?php elseif ($hasLocTable): ?>
                                    <div class="alert alert-info">No assigned locations found for your territory.</div>
<?php else: ?>
                                    <div class="alert alert-warning">Location data is not available. Please contact your account manager.</div>
<?php endif; ?>

                                    <hr/>
                                    <h5 style="margin-top:20px;">Shops Under Your Territory</h5>
<?php
// Show shops onboarded under this TP
$stmtShops = $db_conn->prepare(
    "SELECT s.name, s.mobile_number, s.country_code, s.pincode_id, s.useridtext
     FROM shop s
     WHERE s.onboard_userID = ? AND s.onboard_userTYPE = 'territory_partner'
     ORDER BY s.name ASC"
);
if ($stmtShops) {
    $stmtShops->bind_param('i', $tp_id);
    $stmtShops->execute();
    $shopResult = $stmtShops->get_result();
    if ($shopResult->num_rows > 0) {
?>
                                    <table class="users-networks">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Shop ID</th>
                                                <th>Shop Name</th>
                                                <th>Mobile</th>
                                                <th>Pincode</th>
                                            </tr>
                                        </thead>
                                        <tbody>
<?php
        $si = 0;
        while ($rowShop = $shopResult->fetch_assoc()) {
            $si++;
?>
                                            <tr>
                                                <td><?php echo $si; ?></td>
                                                <td><?php echo htmlspecialchars($rowShop['useridtext']); ?></td>
                                                <td><?php echo htmlspecialchars(ucwords($rowShop['name'])); ?></td>
                                                <td><?php echo htmlspecialchars($rowShop['country_code'] . ' ' . $rowShop['mobile_number']); ?></td>
                                                <td><?php echo htmlspecialchars($rowShop['pincode_id']); ?></td>
                                            </tr>
<?php }
        $stmtShops->close();
?>
                                        </tbody>
                                    </table>
<?php } else {
        echo '<div class="alert alert-info">No shops assigned to your territory yet.</div>';
        $stmtShops->close();
    }
}
?>
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
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
