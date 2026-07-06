<?php 
include("checksession.php"); require_once("include/GodownAccess.php");
include("config.php"); 
error_reporting(0);

$from_date=$_REQUEST['frd']; 
$to_date=$_REQUEST['tod'];
$get_godown_id=$_REQUEST['gid'];
if (!empty($get_godown_id) && !is_godown_allowed($db_conn, (int)$get_godown_id)) {
    header("Location: overall-stock?unauthorized"); exit;
}
//
$select_Godown_details="select * from company_godown where id='$get_godown_id'";
$fetch_Godown_details=mysqli_query($db_conn,$select_Godown_details);
$result_Godown_details=mysqli_fetch_array($fetch_Godown_details);
//
$gst_type=$_REQUEST['data1']; 
$buyer_gsttype=$_REQUEST['data2']; 

if($gst_type=="inner" && $buyer_gsttype=="register")
{
	$lable_header="Intra-state (Registered person)";
}
else if($gst_type=="inner" && $buyer_gsttype=="unregister")
{
	$lable_header="Intra-state (Unregistered person)";
}
else if($gst_type=="outer" && $buyer_gsttype=="register")
{
	$lable_header="Inter-state (Registered person)";
}
else{$lable_header="Inter-state (Unregistered person)";}
							   
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Responsive Admin Dashboard Template">
    <meta name="keywords" content="admin,dashboard">
    <meta name="author" content="stacks">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    
    <!-- Title -->
    <title>GSTR1 : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">

    
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
	
	<style type="text/css">
	#gsttablevl tr th{border:1px solid #000;padding:5px;}
	#gsttablevl tr td{border:1px solid #000;padding:5px;}
	</style>
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
                    <div class="container">
                        <div class="row">
                            <div class="col">
                                <div class="page-description" style="margin-left:-25px;">
								<table style="width:100%;">
								<tr>
								<td><h1>GSTR1 > Detailed OT Sales Report > <span style="color:red;">Credit</span> Note</h1>
								<h5><?=$lable_header;?></h5>
								</td>
								</tr>
								</table>
                                </div>
                            </div>
                        </div>
						
						<!--------------------------------------------------------------------->
						<!--------------------------------------------------------------------->
						<div class="row">
                           
						<table style="width:100%;" id="gsttablevl">
						   
						   <tr>
						   <th>#</th>
						   <th>Customer Name</th>
						   <th>Customer Mobile</th>
						    <?php if($buyer_gsttype=="register"){?>
						  <th>GSTIN</th>
						  <?php }?>
						   <th>Invoice Number</th>
						   <th>Invoice Date</th>
						   <th>Return Date</th>
						   <th>Total Return Value(Rs.)</th>
						   </tr>
						   
						  <?php 
						  $select_Report="select distinct tempid from ot_sales_return where buyer_gsttype='$buyer_gsttype' and return_date between '$from_date' and '$to_date' and godownid='$get_godown_id' and gst_type='$gst_type'";
							   $fetch_Report=mysqli_query($db_conn,$select_Report);
							   while($result_Report=mysqli_fetch_array($fetch_Report))
							   {
								   
								   //1
								   $tempid=$result_Report["tempid"];
						$select_return_details="select return_date from ot_sales_return where tempid='$tempid'";
						$fetch_return_details=mysqli_query($db_conn,$select_return_details);
						$result_return_details=mysqli_fetch_array($fetch_return_details);
								   
								   //2
								   $tempid=$result_Report["tempid"];
						$select_productDetils="select * from ot_sales where tempid='$tempid'";
						$Fetch_productDetils=mysqli_query($db_conn,$select_productDetils);
						$Result_productDetils=mysqli_fetch_array($Fetch_productDetils);
						
						//3
						$select_ot_invoice="select inv_number from ot_sales_invoice where tempid='$tempid'";
						$fetch_ot_invoice=mysqli_query($db_conn,$select_ot_invoice);
						$result_ot_invoice=mysqli_fetch_array($fetch_ot_invoice);

//4
$select_sUM_QTY="select sum(total) from ot_sales_return where tempid='$tempid'";
										$fetch_sUM_QTY=mysqli_query($db_conn,$select_sUM_QTY);
										$result_sUM_QTY=mysqli_fetch_array($fetch_sUM_QTY);
										if($result_sUM_QTY[0]!=NULL)
										{
											$slsamount=$result_sUM_QTY[0];
										}
										else{ $slsamount="0";}
										
										
								   $overall_total+=$slsamount;
							   ?>
							   
						  <tr>
						  <td><?=$sn=$sn+1;?></td>
						  <td><?=$Result_productDetils['customer_name'];?></td>
						  <td>
						  <?php 
						  if($Result_productDetils['customer_mobile']!=NULL){
							  echo $Result_productDetils['customer_mobile'];
						  }else{ echo "---";}
						  ?>
						  </td>
						  
						   <?php if($buyer_gsttype=="register"){?>
						  <td><?=$Result_productDetils['gst_number'];?></td>
						  <?php }?>
						  
						  <td><?=$result_ot_invoice['inv_number'];?></td>
						  <td><?=date("d/m/Y",strtotime($Result_productDetils['date']));?></td>
						  <td><?=date("d/m/Y",strtotime($result_return_details['return_date']));?></td>
						  <td align="right"><b><?=inr_format($slsamount, 2);?></b></td>
						  </tr>
						   
							   <?php }?>
							
							   <tfoot>
							   <tr>
							   <td></td>
							    <td></td>
								 <td></td>
								  <?php if($buyer_gsttype=="register"){?><td></td><?php } ?>
								   <td></td>
								   <td></td>
							   <td align="right">Grand Total</td>
							    <td align="right"><b><?=inr_format($overall_total, 2);?></b></td>
							   </tr>
							   </tfoot>
						   
						   </table>
							
                        </div>
						
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>
</body>
</html>