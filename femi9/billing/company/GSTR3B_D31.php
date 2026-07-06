<?php 
include("checksession.php"); require_once("include/GodownAccess.php");
include("config.php"); 
error_reporting(0);

$from_month=$_REQUEST['frdate']; 
$to_month=$_REQUEST['todate'];

$to_month_days=date("t",strtotime($to_month));
$from_date=date("Y-m-01",strtotime($from_month));
$to_date=date("Y-m-".$to_month_days."",strtotime($to_month));

$get_godown_id=$_REQUEST['godown_id'];
if (!empty($get_godown_id) && !is_godown_allowed($db_conn, (int)$get_godown_id)) {
    header("Location: overall-stock?unauthorized"); exit;
}
//
$select_Godown_details="select * from company_godown where id='$get_godown_id'";
$fetch_Godown_details=mysqli_query($db_conn,$select_Godown_details);
$result_Godown_details=mysqli_fetch_array($fetch_Godown_details);	   
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
    <title>GSTR3B : <?php echo $business_name;?></title>

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
	#dashanch{color:#000 !important;}
	#dashanch:hover{color:#1a06a6 !important;}
	#reportdash th{font-size:13px;font-weight:600;}
	#reportdash td{font-weight:700;font-size:14px;}
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
								<td><h1>GSTR3B</h1>
								<p>3.1 Details of Outward Supplies and inward supplies liable to reverse charge (other than those covered by Table 3.1.1</p>
								</td>
								
								<?php 
								
								if($from_month!=NULL){
							   
 include('gst_details.php'); 		//intra state (Tamilnadu)
							    include('gst_details_inter.php');	
								
								
								//
								$Total_sls_register_intra=$total_intra_register+$total_intra_register2;
						   $Total_sls_unregister_intra=$total_intra_unregister+$total_intra_unregister2;
						   
						   
						   //
						   $Total_intra_register_sales=$total_intra_register+$total_intra_register2+$total_reg_OTSLS_intra+$total_reg_INTR;
						   
						   $Total_intra_unregister_sales=$total_intra_unregister+$total_intra_unregister2+$total_reg_OTSLSUN_intra;
						   
						   
						   include('gst_details_credit.php');
						   
						   
						   //
						   // intra-state (register) credit note
							$total_intra_register_credit_note=$total_intra_register_credit+$total_intra_register_creditOT;
							// intra-state (unregister) credit note
							$total_intra_unregister_credit_note=$total_intra_unregister_credit+$total_intra_unregister_creditOT;
							
							//
							 $Total_sls_register_inter=$total_inter_register+$total_inter_register2;
						   $Total_sls_unregister_inter=$total_inter_unregister+$total_inter_unregister2;
						   
						   
						   //
						  $Total_inter_register_sales=$Total_sls_register_inter+$total_reg_OTSLS_inter;
						   $Total_inter_unregister_sales=$Total_sls_unregister_inter+$total_reg_OTSLSUN_inter;
						   
						   
						   include('gst_details_credit_inter.php');
						   
						   
						   // inter-state (register) credit note
							$total_inter_register_credit_note=$total_inter_register_credit+$total_inter_register_creditOT;
							// inter-state (unregister) credit note
							$total_inter_unregister_credit_note=$total_inter_unregister_credit+$total_inter_unregister_creditOT;
							
							
							
							//Intra
			$intra_reg_supplies_grand_total=$Total_intra_register_sales-$total_intra_register_credit_note;
			$intra_unreg_supplies_grand_total=$Total_intra_unregister_sales-$total_intra_unregister_credit_note;
			
			//Inter
			$inter_reg_supplies_grand_total=$Total_inter_register_sales-$total_inter_register_credit_note;
			$inter_unreg_supplies_grand_total=$Total_inter_unregister_sales-$total_inter_unregister_credit_note;
			
			
			$Nil_rated_total=$intra_reg_supplies_grand_total+$intra_unreg_supplies_grand_total+$inter_reg_supplies_grand_total+$inter_unreg_supplies_grand_total;

								
						   ?>
								
								<td><a href="export_GSTR3B_D31?t1=<?=$Nil_rated_total;?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
								
								<?php }?>
								
								</tr>
								</table>
								
                                </div>
                            </div>
							
							<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">

							<div class="overviewcontainar">
							<div id="searchleftcont">
<label class="form-label">From Month</label>
<input type="month" required="" name="frdate" value="<?=$from_month;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Month</label>
<input type="month" required="" name="todate" value="<?=$to_month;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>

<div id="searchleftcont">
<label for="exampleInputEmail1" class="form-label">Company</label>
                               <select required name="godown_id" class="form-control">
							   <?php if($get_godown_id==NULL){?>
							   <option value="" hidden="">Select</option>
							   <?php }else{?>
							   <option value="<?=$get_godown_id;?>" hidden=""><?=$result_Godown_details['gname'];?></option>
							   <?php }?>
							   <?php $select_Godown="select * from company_godown where " . godown_finance_filter_sql($db_conn) . " order by id asc";
							   $fetch_Godown=mysqli_query($db_conn,$select_Godown);
							   while($result_Godown=mysqli_fetch_array($fetch_Godown))
							   {?>
						  <option value="<?=$result_Godown['id'];?>"><?=$result_Godown['gname'];?></option>
							   <?php }?>
							   </select>
							   </div>
							   
							   
<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>



							</div>
							<div style="clear:both;"></div>
							<br/>
							</form>	
							
                        </div>
						
						<!--------------------------------------------------------------------->
						<!--------------------------------------------------------------------->
						<div class="row">
                           
						   <?php if($from_month!=NULL){?>
						   
						   <table class="table">
						   <tr>
						   <th>Nature of Supplies</th>
						   <th>Total Taxable value</th>
						   <th>Integrated Tax</th>
						   <th>Central Tax</th>
						    <th>State/UT Tax</th>
							 <th>CESS</th>
						   </tr>
						   <tr>
						   <td>(a) Outward taxable supplies (other than zero rated, nil rated and exempted)</td>
						   <td>0.00</td>
						   <td>0.00</td>
						   <td>0.00</td>
						    <td>0.00</td>
							 <td>0.00</td>
						   </tr>
						   <tr>
						   <td>(b) Outward taxable supplies (zero rated)</td>
						   <td>0.00</td>
						   <td>0.00</td>
						   <td>0.00</td>
						    <td>0.00</td>
							 <td>0.00</td>
						   </tr>
						   <tr>
						   <td>(c) Other outward supplies (Nil rated, exempted)</td>
						   <td><?=inr_format($Nil_rated_total, 2);?></td>
						   <td>0.00</td>
						   <td>0.00</td>
						    <td>0.00</td>
							 <td>0.00</td>
						   </tr>
						   <tr>
						   <td>(d) Inward supplies (liable to reverse charge)</td>
						   <td>0.00</td>
						   <td>0.00</td>
						   <td>0.00</td>
						    <td>0.00</td>
							 <td>0.00</td>
						   </tr>
						   <tr>
						   <td>(e) Non-GST outward supplies</td>
						   <td>0.00</td>
						   <td>0.00</td>
						   <td>0.00</td>
						    <td>0.00</td>
							 <td>0.00</td>
						   </tr>
						  
						  
						   </table>
							
						   <?php }?>
							
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