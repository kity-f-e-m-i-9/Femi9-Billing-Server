<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$advBalance = 0;
$user_id_Loginvl = (int) $Login_user_IDvl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Overall Stock : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link href="../../assets/css/vlstyle.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
<?php
if (isset($_SESSION['successMessage'])) {
    $successMessage = $_SESSION['successMessage'];
?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        Swal.fire({ icon: 'success', title: 'Success', text: '<?php echo $successMessage; ?>', confirmButtonText: 'OK' });
    </script>
<?php unset($_SESSION['successMessage']); } ?>

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
                                <h1><table class="headertble"><tr><td>Overall Stock</td></tr></table></h1>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div style="background:#fff;overflow:scroll;width:100%;">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Product Name</th>
                                                    <th style="display:none;">Opening Qty</th>
                                                    <th style="display:none;">Input Qty</th>
                                                    <th style="display:none;">Deduct Qty</th>
                                                    <th>Closing Qty</th>
                                                </tr>
                                            </thead>
                                            <tbody>
<?php
$ClosingStock12 = 0;
$stmt = $db_conn->prepare(
    "SELECT tps.opening_qty, tps.input_qty, tps.deduct_qty, tps.closing_qty, p.productName
     FROM territory_partner_stock tps
     LEFT JOIN products p ON tps.product_id = p.id
     WHERE tps.territory_partner_id = ?"
);
$stmt->bind_param('i', $user_id_Loginvl);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if ($row['productName'] != NULL) {
        $ClosingStock12 += $row['closing_qty'];
?>
                                                <tr>
                                                    <td><a href="#" class="popup-trigger"><?php echo htmlspecialchars($row['productName']); ?></a></td>
                                                    <td style="display:none;"><?php echo $row['opening_qty']; ?></td>
                                                    <td style="display:none;"><?php echo $row['input_qty']; ?></td>
                                                    <td style="display:none;"><?php echo $row['deduct_qty']; ?></td>
                                                    <td align="right"><b><?php echo $row['closing_qty']; ?></b></td>
                                                </tr>
<?php }
}
$stmt->close();
?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td align="left">Total Stock Qty</td>
                                                    <td align="right"><b><?php echo $ClosingStock12; ?></b></td>
                                                </tr>
                                            </tfoot>
                                        </table>

                                        <div id="popup" class="popup">
                                            <h2>Overall Stock Details</h2>
                                            <div id="popup-content"></div>
                                            <a href="#" id="close-popup"><img src="../../assets/images/close 32.png"></a>
                                        </div>

                                        <script src="../../assets/js/jquery-3.6.0.min.js"></script>
                                        <script>
                                        $(document).ready(function(){
                                            $('.popup-trigger').click(function(){
                                                var tds = $(this).closest('tr').find('td');
                                                $('#popup-content').html(
                                                    "<p>Product Name : <b>"  + tds.eq(0).text() + "</b></p>" +
                                                    "<p>Opening Qty : <b>"   + tds.eq(1).text() + "</b></p>" +
                                                    "<p>Input Qty : <b>"     + tds.eq(2).text() + "</b></p>" +
                                                    "<p>Deduct Qty : <b>"    + tds.eq(3).text() + "</b></p>" +
                                                    "<p>Closing Qty : <b>"   + tds.eq(4).text() + "</b></p>"
                                                );
                                                $('#popup').fadeIn();
                                            });
                                            $('#close-popup').click(function(){ $('#popup').fadeOut(); });
                                        });
                                        </script>
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
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
