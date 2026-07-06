<?php 
include("checksession.php");
include("config.php"); 
error_reporting(0);

$from_date=$_REQUEST['frdate'];
$to_date=$_REQUEST['todate'];

if($from_date!=NULL)
{
 //instra-state registered person (tamilnadu)
							   $select_sum_total_intra_register="select sum(total) from user_invoice where from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and buyer_gsttype='register' and gst_type='inner' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_intra_register=mysqli_query($db_conn,$select_sum_total_intra_register);
							   $result_sum_total_intra_register=mysqli_fetch_array($fetch_sum_total_intra_register);
							   
							   if($result_sum_total_intra_register[0]!=NULL)
							   {$total_intra_register=$result_sum_total_intra_register[0];
							   }else{$total_intra_register="0";}
							   
							   $select_sum_total_intra_register2="select sum(total) from invoice where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' and buyer_gsttype='register' and gst_type='inner' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_intra_register2=mysqli_query($db_conn,$select_sum_total_intra_register2);
							   $result_sum_total_intra_register2=mysqli_fetch_array($fetch_sum_total_intra_register2);
							   
							   if($result_sum_total_intra_register2[0]!=NULL)
							   {$total_intra_register2=$result_sum_total_intra_register2[0];
							   }else{$total_intra_register2="0";}
							   
							   $show_total_intra_register=$total_intra_register+$total_intra_register2;
							   
							   //instra-state unregistered person (tamilnadu)
							   $select_sum_total_intra_unregister="select sum(total) from user_invoice where from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and buyer_gsttype='unregister' and gst_type='inner' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_intra_unregister=mysqli_query($db_conn,$select_sum_total_intra_unregister);
							   $result_sum_total_intra_unregister=mysqli_fetch_array($fetch_sum_total_intra_unregister);
							   
							   if($result_sum_total_intra_unregister[0]!=NULL)
							   {$total_intra_unregister=$result_sum_total_intra_unregister[0];
							   }else{$total_intra_unregister="0";}
							   
							   $select_sum_total_intra_unregister2="select sum(total) from invoice where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' and buyer_gsttype='unregister' and gst_type='inner' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_intra_unregister2=mysqli_query($db_conn,$select_sum_total_intra_unregister2);
							   $result_sum_total_intra_unregister2=mysqli_fetch_array($fetch_sum_total_intra_unregister2);
							   
							   if($result_sum_total_intra_unregister2[0]!=NULL)
							   {$total_intra_unregister2=$result_sum_total_intra_unregister2[0];
							   }else{$total_intra_unregister2="0";}
							   
							   $show_total_intra_unregister=$total_intra_unregister+$total_intra_unregister2;
							   
							   
							   
							   //inter-state registered person (other state)
							   $select_sum_total_inter_register="select sum(total) from user_invoice where from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and buyer_gsttype='register' and gst_type='outer' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_inter_register=mysqli_query($db_conn,$select_sum_total_inter_register);
							   $result_sum_total_inter_register=mysqli_fetch_array($fetch_sum_total_inter_register);
							   
							   if($result_sum_total_inter_register[0]!=NULL)
							   {$total_inter_register=$result_sum_total_inter_register[0];
							   }else{$total_inter_register="0";}
							   
							   $select_sum_total_inter_register2="select sum(total) from invoice where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' and buyer_gsttype='register' and gst_type='outer' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_inter_register2=mysqli_query($db_conn,$select_sum_total_inter_register2);
							   $result_sum_total_inter_register2=mysqli_fetch_array($fetch_sum_total_inter_register2);
							   
							   if($result_sum_total_inter_register2[0]!=NULL)
							   {$total_inter_register2=$result_sum_total_inter_register2[0];
							   }else{$total_inter_register2="0";}
							   
							   $show_total_inter_register=$total_inter_register+$total_inter_register2;
							   
							   //instra-state unregistered person (other state)
							   $select_sum_total_inter_unregister="select sum(total) from user_invoice where from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and buyer_gsttype='unregister' and gst_type='outer' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_inter_unregister=mysqli_query($db_conn,$select_sum_total_inter_unregister);
							   $result_sum_total_inter_unregister=mysqli_fetch_array($fetch_sum_total_inter_unregister);
							   
							   if($result_sum_total_inter_unregister[0]!=NULL)
							   {$total_inter_unregister=$result_sum_total_inter_unregister[0];
							   }else{$total_inter_unregister="0";}
							   
							   $select_sum_total_inter_unregister2="select sum(total) from invoice where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' and buyer_gsttype='unregister' and gst_type='outer' and date between '$from_date' and '$to_date'";
							   $fetch_sum_total_inter_unregister2=mysqli_query($db_conn,$select_sum_total_inter_unregister2);
							   $result_sum_total_inter_unregister2=mysqli_fetch_array($fetch_sum_total_inter_unregister2);
							   
							   if($result_sum_total_inter_unregister2[0]!=NULL)
							   {$total_inter_unregister2=$result_sum_total_inter_unregister2[0];
							   }else{$total_inter_unregister2="0";}
							   
							   $show_total_inter_unregister=$total_inter_unregister+$total_inter_unregister2;
							   
}
							   
							   
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
								<td><h1>GST Reports > GSTR1</h1></td>
								<td><a href="export_GSTR1?t1=<?=$show_total_intra_register;?>&&t2=<?=$show_total_intra_unregister;?>&&t3=<?=$show_total_inter_register;?>&&t4=<?=$show_total_inter_unregister;?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
								</tr>
								</table>
                                </div>
                            </div>
							
							
							<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">

							<div class="overviewcontainar">
							<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=$from_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=$to_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
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
                           
						   <?php if($from_date!=NULL){?>
						   
						   <table class="table">
						   <tr>
						   <th>Description</th>
						   <th>Nil Rated Supplies</th>
						   <th>Exempted (Other than Nil Rated/non GST Supply)</th>
						   <th>Non GST Supplies</th>
						   </tr>
						   <tr>
						   <td>Intra-state supplies to registered person</td>
						   <td><?=inr_format($show_total_intra_register, 2);?></td>
						   <td>0.00</td>
						   <td>0.00</td>
						   </tr>
						   <tr>
						   <td>Intra-state supplies to unregistered person</td>
						   <td><?=inr_format($show_total_intra_unregister, 2);?></td>
						   <td>0.00</td>
						   <td>0.00</td>
						   </tr>
						   <tr>
						   <td>Inter-state supplies to registered person</td>
						   <td><?=inr_format($show_total_inter_register, 2);?></td>
						   <td>0.00</td>
						   <td>0.00</td>
						   </tr>
						   <tr>
						   <td>Inter-state supplies to unregistered person</td>
						   <td><?=inr_format($show_total_inter_unregister, 2);?></td>
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