<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$advBalance = 0;

$from_date = $_REQUEST['frdate'] ?? null;
$to_date   = $_REQUEST['todate'] ?? null;

if ($from_date != NULL) {
    // Intra-state registered
    $q = "SELECT SUM(total) FROM user_invoice WHERE from_user_type='$Login_user_TYPEvl' AND from_user_id='$Login_user_IDvl' AND buyer_gsttype='register' AND gst_type='inner' AND date BETWEEN '$from_date' AND '$to_date'";
    $total_intra_register = mysqli_fetch_array(mysqli_query($db_conn, $q))[0] ?? 0;
    $q2 = "SELECT SUM(total) FROM invoice WHERE user_type='$Login_user_TYPEvl' AND user_id='$Login_user_IDvl' AND buyer_gsttype='register' AND gst_type='inner' AND date BETWEEN '$from_date' AND '$to_date'";
    $total_intra_register2 = mysqli_fetch_array(mysqli_query($db_conn, $q2))[0] ?? 0;
    $show_total_intra_register = $total_intra_register + $total_intra_register2;

    // Intra-state unregistered
    $q = "SELECT SUM(total) FROM user_invoice WHERE from_user_type='$Login_user_TYPEvl' AND from_user_id='$Login_user_IDvl' AND buyer_gsttype='unregister' AND gst_type='inner' AND date BETWEEN '$from_date' AND '$to_date'";
    $total_intra_unregister = mysqli_fetch_array(mysqli_query($db_conn, $q))[0] ?? 0;
    $q2 = "SELECT SUM(total) FROM invoice WHERE user_type='$Login_user_TYPEvl' AND user_id='$Login_user_IDvl' AND buyer_gsttype='unregister' AND gst_type='inner' AND date BETWEEN '$from_date' AND '$to_date'";
    $total_intra_unregister2 = mysqli_fetch_array(mysqli_query($db_conn, $q2))[0] ?? 0;
    $show_total_intra_unregister = $total_intra_unregister + $total_intra_unregister2;

    // Inter-state registered
    $q = "SELECT SUM(total) FROM user_invoice WHERE from_user_type='$Login_user_TYPEvl' AND from_user_id='$Login_user_IDvl' AND buyer_gsttype='register' AND gst_type='outer' AND date BETWEEN '$from_date' AND '$to_date'";
    $total_inter_register = mysqli_fetch_array(mysqli_query($db_conn, $q))[0] ?? 0;
    $q2 = "SELECT SUM(total) FROM invoice WHERE user_type='$Login_user_TYPEvl' AND user_id='$Login_user_IDvl' AND buyer_gsttype='register' AND gst_type='outer' AND date BETWEEN '$from_date' AND '$to_date'";
    $total_inter_register2 = mysqli_fetch_array(mysqli_query($db_conn, $q2))[0] ?? 0;
    $show_total_inter_register = $total_inter_register + $total_inter_register2;

    // Inter-state unregistered
    $q = "SELECT SUM(total) FROM user_invoice WHERE from_user_type='$Login_user_TYPEvl' AND from_user_id='$Login_user_IDvl' AND buyer_gsttype='unregister' AND gst_type='outer' AND date BETWEEN '$from_date' AND '$to_date'";
    $total_inter_unregister = mysqli_fetch_array(mysqli_query($db_conn, $q))[0] ?? 0;
    $q2 = "SELECT SUM(total) FROM invoice WHERE user_type='$Login_user_TYPEvl' AND user_id='$Login_user_IDvl' AND buyer_gsttype='unregister' AND gst_type='outer' AND date BETWEEN '$from_date' AND '$to_date'";
    $total_inter_unregister2 = mysqli_fetch_array(mysqli_query($db_conn, $q2))[0] ?? 0;
    $show_total_inter_unregister = $total_inter_unregister + $total_inter_unregister2;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GSTR1 : <?php echo $business_name; ?></title>
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
    #dashanch { color:#000 !important; }
    #dashanch:hover { color:#1a06a6 !important; }
    .overviewcontainar { display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap; margin-bottom:20px; }
    #searchleftcont { flex:1; min-width:200px; }
    #searchbuttoncont { flex:0 0 auto; }
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
                <div class="container">
                    <div class="row">
                        <div class="col">
                            <div class="page-description" style="margin-left:-25px;">
                                <table style="width:100%;"><tr>
                                    <td><h1>GST Reports &gt; GSTR1</h1></td>
                                    <td style="text-align:right;">
                                        <?php if ($from_date != NULL): ?>
                                        <a href="export_gstr1.php?t1=<?php echo $show_total_intra_register; ?>&t2=<?php echo $show_total_intra_unregister; ?>&t3=<?php echo $show_total_inter_register; ?>&t4=<?php echo $show_total_inter_unregister; ?>" title="Export to Excel">
                                            <img src="../../assets/images/excel-3-32.png">
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr></table>
                            </div>
                        </div>
                    </div>
                    <form method="post" enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                        <div class="overviewcontainar">
                            <div id="searchleftcont">
                                <label class="form-label">From Date</label>
                                <input type="date" required name="frdate" value="<?php echo $from_date; ?>" class="form-control">
                            </div>
                            <div id="searchleftcont">
                                <label class="form-label">To Date</label>
                                <input type="date" required name="todate" value="<?php echo $to_date; ?>" class="form-control">
                            </div>
                            <div id="searchbuttoncont">
                                <button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
                            </div>
                        </div>
                        <div style="clear:both;"></div><br/>
                    </form>
                    <?php if ($from_date != NULL): ?>
                    <div class="row">
                        <div class="col-12">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th>Nil Rated Supplies</th>
                                        <th>Exempted</th>
                                        <th>Non GST Supplies</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Intra-state supplies to registered person</td>
                                        <td><?php echo inr_format($show_total_intra_register, 2); ?></td>
                                        <td>0.00</td><td>0.00</td>
                                    </tr>
                                    <tr>
                                        <td>Intra-state supplies to unregistered person</td>
                                        <td><?php echo inr_format($show_total_intra_unregister, 2); ?></td>
                                        <td>0.00</td><td>0.00</td>
                                    </tr>
                                    <tr>
                                        <td>Inter-state supplies to registered person</td>
                                        <td><?php echo inr_format($show_total_inter_register, 2); ?></td>
                                        <td>0.00</td><td>0.00</td>
                                    </tr>
                                    <tr>
                                        <td>Inter-state supplies to unregistered person</td>
                                        <td><?php echo inr_format($show_total_inter_unregister, 2); ?></td>
                                        <td>0.00</td><td>0.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
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
