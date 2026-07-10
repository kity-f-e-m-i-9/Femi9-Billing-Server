<?php 
// Load environment variables FIRST (before anything else)
require_once __DIR__ . '/../shared/env-loader.php';

// Then include session check
include("checksession.php");

// Now load encryption service
require_once __DIR__ . '/../shared/EncryptionService.php';
$encryption = new EncryptionService();

error_reporting(1);
ini_set('display_errors', 1);

$title="Add User Permission";
$manage_url="users_manage";
$manage_title="Manage Users";
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
									<td><?php echo $title;?></td>
									<td><a href="<?php echo $manage_url;?>" title="<?php echo $manage_title;?>">&#9776;</a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <!----<div class="card-header">
                                        <h5 class="card-title">Basic Input</h5>
                                    </div>--->
                                    <div class="card-body">
									
									
									<?php if(isset($_REQUEST['alreadyexists'])){?><div class="alert alert-danger">
									This user already exists !</div>
									<?php }?>
    
<?php
if(isset($_REQUEST['add-users']))
{
	$username=$_REQUEST['username'];
	$password=$_REQUEST['password'];
	
	// Encrypt password
	$encryptedPassword = $encryption->encrypt($password);
	
	$usertype="users";
	$state="0";
	
	if(isset($_REQUEST['dash']) && $_REQUEST['dash']==1){$dash="1";}else{$dash="0";}
    if(isset($_REQUEST['report']) && $_REQUEST['report']==1){$report="1";}else{$report="0";}
    if(isset($_REQUEST['company_profile']) && $_REQUEST['company_profile']==1){$company_profile="1";}else{$company_profile="0";}
    if(isset($_REQUEST['users_demo']) && $_REQUEST['users_demo']==1){$users_demo="1";}else{$users_demo="0";}
    if(isset($_REQUEST['reward_points']) && $_REQUEST['reward_points']==1){$reward_points="1";}else{$reward_points="0";}
    
    if(isset($_REQUEST['demo_free']) && $_REQUEST['demo_free']==1){$demo_free="1";}else{$demo_free="0";}
    if(isset($_REQUEST['manage_return']) && $_REQUEST['manage_return']==1){$manage_return="1";}else{$manage_return="0";}
    if(isset($_REQUEST['debit_note']) && $_REQUEST['debit_note']==1){$debit_note="1";}else{$debit_note="0";}
    if(isset($_REQUEST['stock_request']) && $_REQUEST['stock_request']==1){$stock_request="1";}else{$stock_request="0";}
    if(isset($_REQUEST['products']) && $_REQUEST['products']==1){$products="1";}else{$products="0";}
    
    if($_REQUEST['add_input_stock']==1){$add_input_stock=$_REQUEST['add_input_stock'];}else{$add_input_stock="0";}
	if($_REQUEST['manage_input_stock']==1){$manage_input_stock=$_REQUEST['manage_input_stock'];}else{$manage_input_stock="0";}
	if($_REQUEST['add_input_stock_users']==1){$add_input_stock_users=$_REQUEST['add_input_stock_users'];}else{$add_input_stock_users="0";}
	if($_REQUEST['manage_input_stock_users']==1){$manage_input_stock_users=$_REQUEST['manage_input_stock_users'];}else{$manage_input_stock_users="0";}
	
    if(isset($_REQUEST['ot_channels']) && $_REQUEST['ot_channels']==1){$ot_channels="1";}else{$ot_channels="0";}
    if(isset($_REQUEST['location']) && $_REQUEST['location']==1){$location="1";}else{$location="0";}
    if(isset($_REQUEST['ss']) && $_REQUEST['ss']==1){$ss="1";}else{$ss="0";}
    if(isset($_REQUEST['st']) && $_REQUEST['st']==1){$st="1";}else{$st="0";}
    
    if(isset($_REQUEST['dt']) && $_REQUEST['dt']==1){$dt="1";}else{$dt="0";}
    if(isset($_REQUEST['sdt']) && $_REQUEST['sdt']==1){$sdt="1";}else{$sdt="0";}
    if(isset($_REQUEST['shop']) && $_REQUEST['shop']==1){$shop="1";}else{$shop="0";}
    if(isset($_REQUEST['cus']) && $_REQUEST['cus']==1){$cus="1";}else{$cus="0";}
    if(isset($_REQUEST['ms']) && $_REQUEST['ms']==1){$ms="1";}else{$ms="0";}
    if(isset($_REQUEST['unassigned']) && $_REQUEST['unassigned']==1){$unassigned="1";}else{$unassigned="0";}
    
    if(isset($_REQUEST['remap']) && $_REQUEST['remap']==1){$remap="1";}else{$remap="0";}
    if(isset($_REQUEST['users_network']) && $_REQUEST['users_network']==1){$users_network="1";}else{$users_network="0";}
    if(isset($_REQUEST['add_payment_entry']) && $_REQUEST['add_payment_entry']==1){$add_payment_entry="1";}else{$add_payment_entry="0";}
    if(isset($_REQUEST['manage_payment_entry']) && $_REQUEST['manage_payment_entry']==1){$manage_payment_entry="1";}else{$manage_payment_entry="0";}
    if(isset($_REQUEST['consolidated_payment_entry']) && $_REQUEST['consolidated_payment_entry']==1){$consolidated_payment_entry="1";}else{$consolidated_payment_entry="0";}
    if(isset($_REQUEST['bonus_calculator']) && $_REQUEST['bonus_calculator']==1){$bonus_calculator="1";}else{$bonus_calculator="0";}
    if(isset($_REQUEST['manage_bonus_points']) && $_REQUEST['manage_bonus_points']==1){$manage_bonus_points="1";}else{$manage_bonus_points="0";}
    if(isset($_REQUEST['partner_location']) && $_REQUEST['partner_location']==1){$partner_location="1";}else{$partner_location="0";}
    if(isset($_REQUEST['channel_partner']) && $_REQUEST['channel_partner']==1){$channel_partner="1";}else{$channel_partner="0";}
    if(isset($_REQUEST['territory_partner']) && $_REQUEST['territory_partner']==1){$territory_partner="1";}else{$territory_partner="0";}
    if(isset($_REQUEST['stock_transfers']) && $_REQUEST['stock_transfers']==1){$stock_transfers="1";}else{$stock_transfers="0";}


	$select_count_users="select count(*) as numusers from admin_log where username='$username'";
	$fetch_count_users=mysqli_query($db_conn,$select_count_users);
	$result_count_users=mysqli_fetch_array($fetch_count_users);
	if($result_count_users['numusers']==1)
	{
		echo "<script>window.location='users_add?alreadyexists';</script>";
	    exit;
	}
	
		$insert_users="INSERT INTO admin_log (username,password,usertype,state,dash,report,company_profile,users_demo,reward_points,demo_free,manage_return,debit_note,stock_request,products,ot_channels,location,ss,st,dt,sdt,shop,cus,ms,unassigned,remap,users_network,payment_entry, manage_payment_entry, consolidated_payment_entry, bonus_calculator ,manage_bonus_points, add_input_stock,manage_input_stock ,add_input_stock_users, manage_input_stock_users, partner_location, channel_partner, territory_partner, stock_transfers) values ('$username','$encryptedPassword','$usertype','$state','$dash','$report','$company_profile','$users_demo',
		'$reward_points','$demo_free','$manage_return','$debit_note','$stock_request','$products','$ot_channels','$location','$ss','$st','$dt','$sdt','$shop','$cus','$ms','$unassigned','$remap','$users_network','$add_payment_entry','$manage_payment_entry','$consolidated_payment_entry','$bonus_calculator','$manage_bonus_points','$add_input_stock','$manage_input_stock','$add_input_stock_users','$manage_input_stock_users','$partner_location','$channel_partner','$territory_partner','$stock_transfers')";
		mysqli_query($db_conn,$insert_users);
		
		
    if(isset($_REQUEST['ot_catID']) && is_array($_REQUEST['ot_catID']) && count($_REQUEST['ot_catID']) > 0) {
        $catid = implode("#", $_REQUEST['ot_catID']); 
        $ex_catid = explode("#", $catid);
        
        foreach ($ex_catid as $key => $value) {   
            $select_count_users12 = "select * from admin_log_ot where username='$username' and ot_cat='$value'";
            $fetch_count_users12 = mysqli_query($db_conn, $select_count_users12);
            if(mysqli_num_rows($fetch_count_users12) == 0) {
                $insert_ot = "insert into admin_log_ot (username,ot_cat) values ('$username','$value')";
                mysqli_query($db_conn, $insert_ot);
            }
        }
    }    
 
  foreach ($ex_catid as $key => $value)
   {   
    
    $select_count_users12="select * from admin_log_ot where username='$username' and ot_cat='$value'";
	$fetch_count_users12=mysqli_query($db_conn,$select_count_users12);
	if(mysqli_num_rows($fetch_count_users12)==0)
	{
		$insert_ot="insert into admin_log_ot (username,ot_cat) values ('$username','$value')";
		mysqli_query($db_conn,$insert_ot);
	} /// End validate duplicate
	
   } /// End foreach
   
		
		echo "<script>window.location='users_manage?addedsuccess';</script>";
		exit;
	
}
?>
	
<?php include("validate-scripts.php");?>
	
<form method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!')">
			
                                        <div class="example-container">
                                            <div class="example-content">
                                   
												
<label class="form-label">Username*</label>
<input type="number" min="0" required="" name="username" class="form-control" onkeypress="restrictusername(event)">
<br/>

<label class="form-label">Password*</label>
<input type="text" required="" name="password" class="form-control" onkeypress="restrictSpecialChars(event)">
<br/>
				
				<table style="width:100%;" cellpadding="10">
				<tr>
				<td><label><input type="checkbox" value="1" name="dash">&nbsp;Dashboard</label></td>
				<td><label><input type="checkbox" value="1" name="report">&nbsp;Report</label></td>
				<td><label><input type="checkbox" value="1" name="company_profile">&nbsp;Company&nbsp;Profile</label></td>
				<td><label><input type="checkbox" value="1" name="users_demo">&nbsp;Users Demo</label></td>
				<td><label><input type="checkbox" value="1" name="reward_points">&nbsp;Reward Points</label></td>
				</tr>
				
				<tr>
				<td><label><input type="checkbox" value="1" name="demo_free">&nbsp;Demo/Free/Damage</label></td>
				<td><label><input type="checkbox" value="1" name="manage_return">&nbsp;Manage Return</label></td>
				<td><label><input type="checkbox" value="1" name="debit_note">&nbsp;Debit Note</label></td>
				<td><label><input type="checkbox" value="1" name="stock_request">&nbsp;Stock Request</label></td>
				<td><label><input type="checkbox" value="1" name="products">&nbsp;Products</label></td>
				</tr>
				
				<tr>
				<td><label><input type="checkbox" value="1" name="add_input_stock">&nbsp;Add Input Stock</label></td>
				<td><label><input type="checkbox" value="1" name="manage_input_stock">&nbsp;Manage Input Stock</label></td>
				<td><label><input type="checkbox" value="1" name="add_input_stock_users">&nbsp;Add Input Stock Users</label></td>
				<td><label><input type="checkbox" value="1" name="manage_input_stock_users">&nbsp;Manage Input Stock Users</label></td>
				<td><label><input type="checkbox" value="1" name="ss">&nbsp;Super Stockist</label></td>
				</tr>
				
				<tr>
				<td><label><input type="checkbox" value="1" name="dt">&nbsp;Distributor</label></td>
				<td><label><input type="checkbox" value="1" name="sdt">&nbsp;Super Distributor</label></td>
				<td><label><input type="checkbox" value="1" name="shop">&nbsp;Shop</label></td>
				<td><label><input type="checkbox" value="1" name="cus">&nbsp;Customer</label></td>
				<td><label><input type="checkbox" value="1" name="ms">&nbsp;Marketing Staff</label></td>
				</tr>
				
				<tr>
				<td><label><input type="checkbox" value="1" name="unassigned">&nbsp;Un-assigned</label></td>    
				<td><label><input type="checkbox" value="1" name="remap">&nbsp;Re-mapping</label></td>
				<td><label><input type="checkbox" value="1" name="users_network">&nbsp;Users Network</label></td>
				<td><label><input type="checkbox" value="1" name="add_payment_entry">&nbsp;Add Payment Entry</label></td>
				<td><label><input type="checkbox" value="1" name="manage_payment_entry">&nbsp;Manage Payment Entry</label></td>
				</tr>
				
				<tr>
				<td><label><input type="checkbox" value="1" name="consolidated_payment_entry">&nbsp;Consolidated Payment Entry</label></td>
				<td><label><input type="checkbox" value="1" name="bonus_calculator">&nbsp;Bonus Calculator</label></td>
				<td><label><input type="checkbox" value="1" name="manage_bonus_points">&nbsp;Manage Bonus Points</label></td>
				<td><label><input type="checkbox" value="1" name="ot_channels">&nbsp;OT Channels</label></td>
				<td><label><input type="checkbox" value="1" name="location">&nbsp;Location</label></td>
				</tr>
				
				<tr>
				    <td><label><input type="checkbox" value="1" name="st">&nbsp;Stockist</label></td>
				    <td><label><input type="checkbox" value="1" name="partner_location">&nbsp;Partner Location</label></td>
				    <td><label><input type="checkbox" value="1" name="channel_partner">&nbsp;Channel Partner</label></td>
				    <td><label><input type="checkbox" value="1" name="territory_partner">&nbsp;Territory Partner</label></td>
				    <td><label><input type="checkbox" value="1" name="stock_transfers">&nbsp;Stock Transfers</label></td>
				</tr>

				</table>
				
				<br/>
				<label class="form-label"><u>OT SALES CATEGORY</u></label><br/>
				<?php $ot_sls_category="select * from ot_cat order by id asc";
				$fetch_sls_category=mysqli_query($db_conn,$ot_sls_category);
				while($result_sls_category=mysqli_fetch_array($fetch_sls_category)){?>
				<label>
				<input type="checkbox" name="ot_catID[]" value="<?=$result_sls_category['id'];?>">&nbsp;<?=$result_sls_category['cat'];?></label> <?php echo "<br/>";?>
				<?php }?>

<br/>
												
				<br/>
												
			<button type="submit" name="add-users" class="btn btn-primary"><i class="material-icons">add</i>Add</button>
												
                                            </div>
                                        </div>
										</form>
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