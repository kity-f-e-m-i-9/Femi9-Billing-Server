<?php include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
error_reporting(0);

$get_from_date=$_REQUEST['frdate'];
//$get_from_date=date ("Y-m-d", strtotime("-1 day", strtotime($get_from_date1)));
$get_to_date=$_REQUEST['todate'];

if($_REQUEST['godownid']!=NULL)
{
$get_company=$_REQUEST['godownid'];
if (!is_godown_allowed($db_conn, (int)$get_company)) {
    header("Location: overall-stock?unauthorized"); exit;
}
//company details
$select_Godown="select * from company_godown where id='$get_company'";
							   $fetch_Godown=mysqli_query($db_conn,$select_Godown);
							   $result_Godown=mysqli_fetch_array($fetch_Godown);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Datewise Overall Stocks : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">


    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
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
									<td>Datewise Overall stock</td>
									<td><a href="overall-stock">&#8592; Go Back</a></td>
									<td><a href="overstock_datewise_pdf?frdate=<?=$get_from_date;?>&&todate=<?=$get_to_date;?>&&godownid=<?=$get_company;?>" title="Export" target="_blank"><img src="32-pdf.png"></a></td>
									</tr>
									</table>
									</h1>
									<h5><?=date("d-m-Y",strtotime($get_from_date));?> (to) <?=date("d-m-Y",strtotime($get_to_date));?>
									<?php if($result_Godown['gname']!=NULL){?>
									<br/>Company Profile : <b><?=$result_Godown['gname'];?></b>
									<?php }?>
									</h5>
                                </div>
                            </div>
                        </div>
						
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
									<div style="background:#fff;overflow:scroll;width:100%;">
									
									<?php 
$startTime = strtotime($get_from_date);
$endTime = strtotime($get_to_date);

// Loop between timestamps, 24 hours at a time
for ( $i = $startTime; $i <= $endTime; $i = $i + 86400 ) {
 
 $thisDate = date( 'Y-m-d', $i ); // 2010-05-01, 2010-05-02, etc

 ?>
 <h1 align="center"><?=date("d-m-Y",strtotime($thisDate));?></h1>
                                        <table class="table">
                                            <thead>
                                               <tr>
											<th>Product Name</th>
											<th style="text-align:right;">Input Stock Qty</th>
											<th style="text-align:right;">Sales Qty</th>
											<th style="text-align:right;">Return Qty</th>
											<th style="text-align:right;">Sent Qty</th>
											</tr>
                                            </thead>
											
											<tbody>
<?php 
$select_productDetils="select * from products order by id asc";
						$Fetch_productDetils=mysqli_query($db_conn,$select_productDetils);
						while($Result_productDetils=mysqli_fetch_array($Fetch_productDetils))
										{
											$report_date=$thisDate;
											$report_prid=$Result_productDetils['id'];
											
//INPUT QTY
if($_REQUEST['godownid']==NULL)
{
$select_sum_input_qty="select sum(input_qty) from input_stock where input_date='$report_date' and product_id='$report_prid'";
}else{
$select_sum_input_qty="select sum(input_qty) from input_stock where input_date='$report_date' and product_id='$report_prid' and godownid='$get_company'";
}
$fetch_sum_input_qty=mysqli_query($db_conn,$select_sum_input_qty);
$result_sum_input_qty=mysqli_fetch_array($fetch_sum_input_qty);
if($result_sum_input_qty[0]!=NULL){ $Total_input_qty=$result_sum_input_qty[0];}else{ $Total_input_qty="0";}


//SALES QTY
//OT-SALES
if($_REQUEST['godownid']==NULL)
{
$select_sum_OTSLS_qty="select sum(qty) from ot_sales where date='$report_date' and prid='$report_prid'";
}else{
$select_sum_OTSLS_qty="select sum(qty) from ot_sales where date='$report_date' and prid='$report_prid' and godownid='$get_company'";
}

$fetch_sum_OTSLS_qty=mysqli_query($db_conn,$select_sum_OTSLS_qty);
$result_sum_OTSLS_qty=mysqli_fetch_array($fetch_sum_OTSLS_qty);
if($result_sum_OTSLS_qty[0]!=NULL){ $Total_OTSLS_qty=$result_sum_OTSLS_qty[0];}else{ $Total_OTSLS_qty="0";}

//OT-SALES-RETURN
if($_REQUEST['godownid']==NULL)
{
$select_sum_OTSLSrtn_qty="select sum(qty) from ot_sales_return where return_date='$report_date' and prid='$report_prid'";
}else{
$select_sum_OTSLSrtn_qty="select sum(qty) from ot_sales_return where return_date='$report_date' and prid='$report_prid' and godownid='$get_company'";
}

$fetch_sum_OTSLSrtn_qty=mysqli_query($db_conn,$select_sum_OTSLSrtn_qty);
$result_sum_OTSLSrtn_qty=mysqli_fetch_array($fetch_sum_OTSLSrtn_qty);
if($result_sum_OTSLSrtn_qty[0]!=NULL){ $Total_OTSLSrtn_qty=$result_sum_OTSLSrtn_qty[0];}else{ $Total_OTSLSrtn_qty="0";}


//SALES-1
if($_REQUEST['godownid']==NULL)
{
$select_sum_SLS1_qty="select sum(qty) from user_invoice_items where date='$report_date' and pr_id='$report_prid' and from_user_type='$Login_user_TYPEvl'";
}else{
$select_sum_SLS1_qty="select sum(qty) from user_invoice_items where date='$report_date' and pr_id='$report_prid' and from_user_type='$Login_user_TYPEvl' and from_user_id='$get_company'";
}

$fetch_sum_SLS1_qty=mysqli_query($db_conn,$select_sum_SLS1_qty);
$result_sum_SLS1_qty=mysqli_fetch_array($fetch_sum_SLS1_qty);
if($result_sum_SLS1_qty[0]!=NULL){ $Total_SLS1_qty=$result_sum_SLS1_qty[0];}else{ $Total_SLS1_qty="0";}

//SALES-2
if($_REQUEST['godownid']==NULL)
{
$select_sum_SLS2_qty="select sum(qty) from invoice_items where date='$report_date' and pr_id='$report_prid' and user_type='$Login_user_TYPEvl'";
}else{
$select_sum_SLS2_qty="select sum(qty) from invoice_items where date='$report_date' and pr_id='$report_prid' and user_type='$Login_user_TYPEvl' and user_id='$get_company'";
}

$fetch_sum_SLS2_qty=mysqli_query($db_conn,$select_sum_SLS2_qty);
$result_sum_SLS2_qty=mysqli_fetch_array($fetch_sum_SLS2_qty);
if($result_sum_SLS2_qty[0]!=NULL){ $Total_SLS2_qty=$result_sum_SLS2_qty[0];}else{ $Total_SLS2_qty="0";}	

//SALES-RETURN
if($_REQUEST['godownid']==NULL)
{
$select_sum_SLSreturn_qty="select sum(qty) from user_return_stock_items where date='$report_date' and prid='$report_prid' and to_usertype='$Login_user_TYPEvl'";
}else{
$select_sum_SLSreturn_qty="select sum(qty) from user_return_stock_items where date='$report_date' and prid='$report_prid' and to_usertype='$Login_user_TYPEvl' and to_userid='$get_company'";
}

$fetch_sum_SLSreturn_qty=mysqli_query($db_conn,$select_sum_SLSreturn_qty);
$result_sum_SLSreturn_qty=mysqli_fetch_array($fetch_sum_SLSreturn_qty);
if($result_sum_SLSreturn_qty[0]!=NULL){ $Total_SLSreturn_qty=$result_sum_SLSreturn_qty[0];}else{ $Total_SLSreturn_qty="0";}

//AVERAGE SALES
$Average_total_sales=$Total_OTSLS_qty+$Total_SLS1_qty+$Total_SLS2_qty;
$Average_total_salesReturn=$Total_OTSLSrtn_qty+$Total_SLSreturn_qty;

//Total sales qty
//$Total_slsQTY=$Average_total_sales-$Average_total_salesReturn;



//DEMO/FREE/DAMAGE
if($_REQUEST['godownid']==NULL)
{
$select_sum_DFD_qty="select sum(qty) from demofreedamage where date='$report_date' and product_id='$report_prid' and usertype='$Login_user_TYPEvl'";
}else{
$select_sum_DFD_qty="select sum(qty) from demofreedamage where date='$report_date' and product_id='$report_prid' and usertype='$Login_user_TYPEvl' and userid='$get_company'";
}
$fetch_sum_DFD_qty=mysqli_query($db_conn,$select_sum_DFD_qty);
$result_sum_DFD_qty=mysqli_fetch_array($fetch_sum_DFD_qty);
if($result_sum_DFD_qty[0]!=NULL){ $Total_DFD_qty=$result_sum_DFD_qty[0];}else{ $Total_DFD_qty="0";}

//INTERNAL TRANSFER
if($_REQUEST['godownid']==NULL)
{
$select_sum_INTRN_qty="select sum(qty) from internal_transfer where date='$report_date' and product_id='$report_prid'";
}else{
$select_sum_INTRN_qty="select sum(qty) from internal_transfer where date='$report_date' and product_id='$report_prid' and send_from='$get_company'";
}
$fetch_sum_INTRN_qty=mysqli_query($db_conn,$select_sum_INTRN_qty);
$result_sum_INTRN_qty=mysqli_fetch_array($fetch_sum_INTRN_qty);
if($result_sum_INTRN_qty[0]!=NULL){ $Total_INTRN_qty=$result_sum_INTRN_qty[0];}else{ $Total_INTRN_qty="0";}

$Average_sent_qty=$Total_DFD_qty+$Total_INTRN_qty;						
						?>
                        <tr>
                        <td><?php echo $Result_productDetils["productName"];?></td>
						<td align="right"><?php echo $Total_input_qty;?></td>
						<td align="right"><?php echo $Average_total_sales;?></td>
						<td align="right"><?php echo $Average_total_salesReturn;?></td>
						<td align="right"><?php echo $Average_sent_qty;?></td>
                        </tr>
						<?php }?>
										
									    </tbody>
                                        </table>
										
										<?php }?>

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