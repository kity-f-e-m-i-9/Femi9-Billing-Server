<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$tempid = $_REQUEST['tempid'] ?? '';

$select_rec = "SELECT * FROM demofreedamage WHERE tempid=? LIMIT 1";
$stmt_rec = $db_conn->prepare($select_rec);
$stmt_rec->bind_param('s', $tempid);
$stmt_rec->execute();
$result_rec = $stmt_rec->get_result()->fetch_assoc();
$stmt_rec->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demo/Free/Damage Details : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
                                <h1><table class="headertble"><tr>
                                    <td>Demo/Free/Damage Details</td>
                                    <td><a href="demofree-manage.php" title="Manage">&#9776;</a></td>
                                </tr></table></h1>
                            </div>
                        </div>
                    </div>

<?php if (isset($_SESSION['sucMessage'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>Swal.fire({ icon:'success', title:'Success', text:'<?php echo $_SESSION['sucMessage']; ?>', confirmButtonText:'OK' });</script>
<?php unset($_SESSION['sucMessage']); endif; ?>

                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <h4>Product Details</h4>
                                    <div style="overflow-x:scroll;">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Product Name</th>
                                                    <th>Qty</th>
                                                    <th>Delete</th>
                                                </tr>
                                            </thead>
                                            <tbody>
<?php
$i = 0;
$stmt_list = $db_conn->prepare("SELECT d.id, d.product_id, d.qty, p.productName FROM demofreedamage d LEFT JOIN products p ON p.id=d.product_id WHERE d.tempid=? ORDER BY d.id ASC");
$stmt_list->bind_param('s', $tempid);
$stmt_list->execute();
$res_list = $stmt_list->get_result();
if ($res_list->num_rows > 0):
    while ($row = $res_list->fetch_assoc()):
        $rowid = base64_encode($row['id']);
?>
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo htmlspecialchars($row['productName'] ?? ''); ?></td>
                                                    <td><?php echo inr_format($row['qty'], 1); ?></td>
                                                    <td>
                                                        <a href="demofree_delete.php?Roowid=<?php echo urlencode($rowid); ?>&tempid=<?php echo urlencode($tempid); ?>"
                                                           onclick="return confirm('Delete this product entry?');">
                                                            <img src="../../assets/images/delete-32.png"/>
                                                        </a>
                                                    </td>
                                                </tr>
<?php
    endwhile;
else:
?>
                                                <tr><td colspan="4" style="color:red;">No Records Found..!</td></tr>
<?php endif; $stmt_list->close(); ?>
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
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
