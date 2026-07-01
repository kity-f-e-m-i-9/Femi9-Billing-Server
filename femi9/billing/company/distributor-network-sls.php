<?php include("checksession.php");
$title="Users Network";
$superstockistid=$_REQUEST['superid'];
$stockistid=$_REQUEST['stockistid'];

$getstateid=$_REQUEST['stataeid'];
//
$select_stateList="select * from `state` where id='$getstateid'";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   $result_stateList=mysqli_fetch_array($fetch_staeList);
							   $state_name=$result_stateList['st_name'];
							   
							   $get_from_date=$_REQUEST['frd'];
							   $get_to_date=$_REQUEST['tod'];
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
.users-networks-head{width:100%;border-collapse:collapse;}
.users-networks-head th{color:#777;font-weight:500;}
.users-networks-head td{font-size:1.5em;border:1px solid #000;padding:4px;}
#linkbackvl{text-decoration:none;}
.card-body{overflow:scroll !important;}

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
								<a href="stockist-network-sls?ssid=<?=$superstockistid;?>&&stataeid=<?=$getstateid;?>&&frd=<?=$get_from_date;?>&&tod=<?=$get_to_date;?>" id="linkbackvl">&#8630;&nbsp;Go Back</a>
                                    <h1>
									<table class="headertble">
									<tr>
									<td><?php echo $title;?> - <?=$state_name;?></td>
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
									
									
									<table class="users-networks-head">
<thead>
<th>Super Stockist Name</th>
<th>District</th>
</thead>
<?php 
$select_product_list12="select * from super_stockiest where temp_id='$superstockistid'";
$fetch_product_list12=mysqli_query($db_conn,$select_product_list12);
while($result_product_list12=mysqli_fetch_array($fetch_product_list12))
{
//
$district_id12=$result_product_list12['district_id'];
$select_distict12="select * from district where id='$district_id12'";
$fetch_district12=mysqli_query($db_conn,$select_distict12);
$result_district12=mysqli_fetch_array($fetch_district12);
?>
<tbody>
<tr>
<td><?php echo strtoupper($result_product_list12["name"]);?></td>
<td><?php echo strtoupper($result_district12['dist_name']);?></td>
</tr>
</tbody>
<?php }?>		
</table>
<br/>	


<table class="users-networks-head">
<thead>
<th>Stockist Name</th>
<th>District</th>
<th>Taluk</th>
</thead>
<?php 
$select_product_list1212="select * from stockiest where temp_id='$stockistid'";
$fetch_product_list1212=mysqli_query($db_conn,$select_product_list1212);
while($result_product_list1212=mysqli_fetch_array($fetch_product_list1212))
{
//
$district_id1212=$result_product_list1212['district_id'];
$select_distict1212="select * from district where id='$district_id1212'";
$fetch_district1212=mysqli_query($db_conn,$select_distict1212);
$result_district1212=mysqli_fetch_array($fetch_district1212);
//
$taluk_id12=$result_product_list1212['taluk_id'];
$select_Taluks1212="select * from taluk where id='$taluk_id12'";
	$fetch_Taluks1212=mysqli_query($db_conn,$select_Taluks1212);
	$result_Taluks1212=mysqli_fetch_array($fetch_Taluks1212);
$taluk_name12=$result_Taluks1212['taluk'];
?>
<tbody>
<tr>
<td><?php echo strtoupper($result_product_list1212["name"]);?></td>
<td><?php echo strtoupper($result_district1212['dist_name']);?></td>
<td><?php echo strtoupper($taluk_name12);?></td>
</tr>
</tbody>
<?php }?>		
</table>
<br/>													

<table class="users-networks">
<thead>
<th>Distributor Name</th>
<th>District</th>
<th>Taluk</th>
<th>Pincode</th>
</thead>

<?php 
$select_product_list="select * from distributor where stockiest_id='$stockistid'";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{	
											
								$usertype_invoice="distributor";
								$userid_invoice=$result_product_list['temp_id'];		
									
//
$district_id=$result_product_list['district_id'];
$select_distict="select * from district where id='$district_id'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];
//
$taluk_id=$result_product_list['taluk_id'];
$select_Taluks12="select * from taluk where id='$taluk_id'";
	$fetch_Taluks12=mysqli_query($db_conn,$select_Taluks12);
	$result_Taluks12=mysqli_fetch_array($fetch_Taluks12);
$taluk_name=$result_Taluks12['taluk'];
//
$pincode_id=$result_product_list['pincode_id'];
$select_pincodelist="select * from pincode where id='$pincode_id'";
	$fetch_pincodelist=mysqli_query($db_conn,$select_pincodelist);
	$result_pincodelist=mysqli_fetch_array($fetch_pincodelist);
$pincodeshow=$result_pincodelist['pincode'];
?>

<tbody>
<tr>
<td><?php echo strtoupper($result_product_list["name"]);?></td>
<td><?php echo strtoupper($district_name);?></td>
<td><?php echo strtoupper($taluk_name);?></td>
<td><?php echo strtoupper($pincodeshow);?></td>
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



<tr><td colspan="4"><a href="shop-network-sls?superid=<?=$superstockistid;?>&&stockistid=<?=$stockistid;?>&&stataeid=<?=$getstateid;?>&&distributorid=<?=$result_product_list['temp_id'];?>&&frd=<?=$get_from_date;?>&&tod=<?=$get_to_date;?>" title="<?php echo strtoupper($result_product_list["name"]);?>"><div class="bottom-arrow"></div></a><br/></td></tr>
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