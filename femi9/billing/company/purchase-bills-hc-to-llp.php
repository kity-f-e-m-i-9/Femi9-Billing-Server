<?php include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
error_reporting(0);

// This page names Femi Health Care directly, so it's restricted to the
// finance and admin usertypes only, same spirit as the rest of the Femi
// Health Care / Neksomo access-control sweep (other usertypes stay blocked).
if (!is_finance_login($db_conn) && get_login_usertype($db_conn) !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$from_godown = mysqli_fetch_array(mysqli_query($db_conn, "SELECT id, gname FROM company_godown WHERE gname='FEMI HEALTH CARE' LIMIT 1"));
$to_godown   = mysqli_fetch_array(mysqli_query($db_conn, "SELECT id, gname FROM company_godown WHERE gname='FEMI NAYAN LLP' LIMIT 1"));
$from_id = (int)($from_godown['id'] ?? 0);
$to_id   = (int)($to_godown['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Bills - Health Care to LLP : <?php echo $business_name;?></title>

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

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
</head>

<body>
    <div class="app align-content-stretch d-flex flex-wrap">

        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>

        <div class="app-container">

          <?php include("app-header.php");?>

            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">

                                    <h1>
                                    <table class="headertble">
                                    <tr>
                                    <td>Purchase Bills — <?php echo htmlspecialchars($from_godown['gname'] ?? 'Health Care'); ?> to <?php echo htmlspecialchars($to_godown['gname'] ?? 'LLP'); ?></td>
                                    </tr>
                                    </table>
                                    </h1>
                                    <br/>

                                    <?php
                                    if($_REQUEST['frdate']==NULL && $_REQUEST['todate']==NULL)
                                    {
                                        $se_fromDate=date("Y-m-01");
                                        $se_toDate=date("Y-m-d");
                                    }else{
                                        $se_fromDate=$_REQUEST['frdate'];
                                        $se_toDate=$_REQUEST['todate'];
                                    }
                                    ?>

<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">
<div class="overviewcontainar">
<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=$se_fromDate;?>" class="form-control">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=$se_toDate;?>" class="form-control">
</div>

<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>

<div id="searchbuttoncont">&nbsp;
<button type="button" onclick="javascript:window.location='purchase-bills-hc-to-llp';" name="sedatas" class="btn btn-danger">Reset</button>
</div>
                                </div>
                                <div style="clear:both;"></div>
                                <br/>
                                </form>

                                </div>
                            </div>
                        </div>

<?php
$num_rec_per_page=30;
if (isset($_GET["page"])) { $page  = $_GET["page"]; } else { $page=1; };
$start_from = ($page-1) * $num_rec_per_page;
$i= $start_from;
?>

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div style="overflow-x:scroll;">
                                         <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Invoice Date</th>
                                                    <th>Invoice Number</th>
                    <?php $select_prdetails_header="select * from `products` order by `id` asc";
                    $fetch_prdetails_header=mysqli_query($db_conn,$select_prdetails_header);
                    while($result_prdetails_header=mysqli_fetch_array($fetch_prdetails_header)){?>
                    <th><?=$result_prdetails_header['productName'];?></th>
                    <?php }?>
                    <th>Total</th>
                    <th>Entered by</th>
                                                    <th>Details</th>
                                                    <th>Print</th>

                                                </tr>
                                            </thead>

                                        <tbody>
                                    <?php
                                $select_product_list12="select distinct `tempid` from `internal_transfer` where date between '$se_fromDate' and '$se_toDate' and send_from='$from_id' and send_to='$to_id'";
                                    $fetch_product_list12=mysqli_query($db_conn,$select_product_list12);
                                    while($ResultRecords12=mysqli_fetch_array($fetch_product_list12))
                                    {

                                        $tempid=$ResultRecords12["tempid"];

                                    $select_product_list="select * from internal_transfer where tempid='$tempid'";
                                    $fetch_product_list=mysqli_query($db_conn,$select_product_list);
                                    $ResultRecords=mysqli_fetch_array($fetch_product_list);

                                    $select_INVOICE="select * from internal_transfer_invoice where tempid='$tempid'";
                                    $fetch_INVOICE=mysqli_query($db_conn,$select_INVOICE);
                                    $result_INVOICE=mysqli_fetch_array($fetch_INVOICE);

                                    $select_SUM_TOTAL="select sum(total) as grand_total from internal_transfer where tempid='$tempid'";
                                    $fetch_SUM_TOTAL=mysqli_query($db_conn,$select_SUM_TOTAL);
                                    $result_SUM_TOTAL=mysqli_fetch_array($fetch_SUM_TOTAL);
                                        ?>

                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                    <td><?php echo date("d/M/Y",strtotime($ResultRecords["date"]));?></td>
                    <td><?php echo $result_INVOICE["inv_number"];?></td>

                    <?php $select_prdetails_header="select * from `products` order by `id` asc";
                    $fetch_prdetails_header=mysqli_query($db_conn,$select_prdetails_header);
                    while($result_prdetails_header=mysqli_fetch_array($fetch_prdetails_header)){

                        $prid_header=$result_prdetails_header['id'];

                        $select_SUM_QTY="select qty from internal_transfer where tempid='$tempid' and product_id='$prid_header'";
                        $fetch_SUM_QTY=mysqli_query($db_conn,$select_SUM_QTY);
                        $result_SUM_QTY=mysqli_fetch_array($fetch_SUM_QTY);
                        if($result_SUM_QTY['qty']!=NULL){ $slsqty=$result_SUM_QTY['qty'];} else{ $slsqty="0";}

                        $net_sls_qty=$slsqty;

                    ?>
                    <th><?=$net_sls_qty;?></th>
                    <?php }?>

                    <td><?=inr_format($result_SUM_TOTAL['grand_total'], 2);?></td>
                    <td><?=$ResultRecords["username"];?><br/><?=ucwords($ResultRecords["usertype"]);?></td>

                                                    <td>
<a href="internal_transfer_details?tempid=<?=$tempid;?>"><img src="../../assets/images/details-32.png"/></a>
                                                    </td>

                                                    <td>
<a href="internal_transfer_print?tempid=<?=$tempid;?>" title="Print">
<img src="../../assets/images/print32.png"/></a>
                                                    </td>

                                                </tr>

                                    <?php }?>

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

    <!-- Javascripts -->
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
</body>

</html>
