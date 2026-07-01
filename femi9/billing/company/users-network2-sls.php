<?php include("checksession.php");
error_reporting(0);

$title="Users Network";
$get_state_id=$_REQUEST['state_id'];
//
$select_stateList="select * from `state` where id='$get_state_id'";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   $result_stateList=mysqli_fetch_array($fetch_staeList);
							   $state_name=$result_stateList['st_name'];
							   
							   $get_from_date=$_REQUEST['fromdate'];
							   $get_to_date=$_REQUEST['todate'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?php echo $title;?> : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">


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
		#linkbackvl{text-decoration:none;}
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
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
								<a href="users-network-sls.php" id="linkbackvl">&#8630;&nbsp;Go Back</a>
                                    <h1>
									<table class="headertble">
									<tr>
									<td><?php echo $title;?> Report<br/><?=$state_name;?><br/>
									<span style="font-size:18px;"><?=date("d/m/Y",strtotime($get_from_date));?>(to)<?=date("d/m/Y",strtotime($get_to_date));?>
									</span>
									</td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
									
									<style type="text/css">
									.bottom-arrow {
      border-bottom: 4px solid #6A0136;
}
.bottom-arrow:after {
    content:'';
    position: absolute;
    left: 0;
    right: 0;
    margin: 0 auto;
    width: 0;
    height: 0;
    border-top: 25px solid #6A0136;
    border-left: 35px solid transparent;
    border-right: 35px solid transparent;
}
.users-networks{width:100%;border-collapse:collapse;}

									</style>
									

<table class="users-networks">
<thead>
<th>Super Stockist Name</th>
<th>District</th>
</thead>

<?php 
$select_product_list="select * from super_stockiest where state_id='$get_state_id'";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
								$usertype_invoice="super_stockiest";
								$userid_invoice=$result_product_list['temp_id'];
											
									$district_id=$result_product_list['district_id'];
//
$select_distict="select * from district where id='$district_id'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];
?>

<tbody>
<tr>
<td><?php echo strtoupper($result_product_list["name"]);?></td>
<td><?php echo strtoupper($district_name);?></td>
</tr>


<!--------------------COUNT AREA START------------------------->
<tr>
<td colspan="2">
<?php 
//total sales invoice Count
$select_totalsls_invcount="select count(*) as numslscount from user_invoice where from_user_type='$usertype_invoice' and from_user_id='$userid_invoice' and date between '$get_from_date' and '$get_to_date'";
$fetch_totalsls_invcount=mysqli_query($db_conn,$select_totalsls_invcount);
$result_totalsls_invcount=mysqli_fetch_array($fetch_totalsls_invcount);
$Total_invoice_count=$result_totalsls_invcount['numslscount'];

//total sales product qty
$select_totalsls_invcount234="select * from user_invoice where from_user_type='$usertype_invoice' and from_user_id='$userid_invoice' and date between '$get_from_date' and '$get_to_date'";
$fetch_totalsls_invcount234=mysqli_query($db_conn,$select_totalsls_invcount234);
while($result_totalsls_invcount234=mysqli_fetch_array($fetch_totalsls_invcount234))
{
	$get_inv_id=$result_totalsls_invcount234['inv_id'];
	$get_inv_date=$result_totalsls_invcount234['date'];
	
$select_totalsls_invcount_qty="select * from user_invoice_items where from_user_type='$usertype_invoice' and from_user_id='$userid_invoice' and inv_id='$get_inv_id'";
$fetch_totalsls_invcount_qty=mysqli_query($db_conn,$select_totalsls_invcount_qty);
while($result_totalsls_invcount_qty=mysqli_fetch_array($fetch_totalsls_invcount_qty))
{

	$invoice_product_id=$result_totalsls_invcount_qty['pr_id'];
	$invoice_qty=$result_totalsls_invcount_qty['qty'];
	
	$selectcountDUP="select count(*) as numDUP from temp_report where usertype='$usertype_invoice' and userid='$userid_invoice' and inv_id='$get_inv_id' and pr_id='$invoice_product_id'";
	$fetchcountDUP=mysqli_query($db_conn,$selectcountDUP);
	$resultcountDUP=mysqli_fetch_array($fetchcountDUP);
	if($resultcountDUP['numDUP']==0)
	{
		
		$insertRecords="insert into temp_report (inv_id,date,pr_id,qty,usertype,userid) values ('$get_inv_id','$get_inv_date','$invoice_product_id','$invoice_qty','$usertype_invoice','$userid_invoice')";
		mysqli_query($db_conn,$insertRecords);
	}
	
}

}
?>
<a href="JavaScript:newPopup('prdetails?userid=<?=$userid_invoice;?>&&usertype=<?=$usertype_invoice?>&&frd=<?=$get_from_date;?>&&tod=<?=$get_to_date;?>');" style="text-decoration:none;">
<table>
<tr>
<th>Total Sales Count</th>
<td>&nbsp;:&nbsp;<?=$Total_invoice_count;?></td>
</tr>

<?php
$select_totalsls_invcount_qty123="select sum(qty) from temp_report where usertype='$usertype_invoice' and userid='$userid_invoice' and date between '$get_from_date' and '$get_to_date'";
$fetch_totalsls_invcount_qty123=mysqli_query($db_conn,$select_totalsls_invcount_qty123);
$result_totalsls_invcount_qty123=mysqli_fetch_array($fetch_totalsls_invcount_qty123);
if($result_totalsls_invcount_qty123[0]!=NULL){
$Total_invoice_count_qty123=$result_totalsls_invcount_qty123[0];
}else{$Total_invoice_count_qty123="0";}
?>
<tr>
<th>Total Sales Qty</th>
<td>&nbsp;:&nbsp;<?=$Total_invoice_count_qty123;?></td>
</tr>
</table>
</a>




<script type="text/javascript">
function newPopup(url) {
	popupWindow = window.open(
		url,'popUpWindow','height=450,width=750,left=350,top=200,resizable=yes,scrollbars=yes,toolbar=yes,menubar=no,location=no,directories=no,status=yes')
}
</script>


</td>
</tr>
<!--------------------COUNT AREA END--****---------------------->


<tr><td colspan="2"><a href="stockist-network-sls?ssid=<?=$result_product_list['temp_id'];?>&&stataeid=<?=$get_state_id;?>&&frd=<?=$get_from_date;?>&&tod=<?=$get_to_date?>" title="<?php echo strtoupper($result_product_list["name"]);?>"><div class="bottom-arrow"></div></a><br/></td></tr>
</tbody>



<?php }?>		

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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>

</html>