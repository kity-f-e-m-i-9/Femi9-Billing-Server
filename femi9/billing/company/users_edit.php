<?php 
// Load environment variables FIRST (before anything else)
require_once __DIR__ . '/../shared/env-loader.php';

// Then include session check
include("checksession.php");

// Now load encryption service
require_once __DIR__ . '/../shared/EncryptionService.php';
$encryption = new EncryptionService();

error_reporting(1);

$title="Edit User Permission";
$manage_url="users_manage";
$manage_title="Manage Users";

$id=base64_decode($_REQUEST['prid']);
//
$select_count_users="select * from admin_log where id='$id'";
	$fetch_count_users=mysqli_query($db_conn,$select_count_users);
	$result_count_users=mysqli_fetch_array($fetch_count_users);
	
// Decrypt password for display
$displayPassword = '';
try {
    $displayPassword = $encryption->decrypt($result_count_users['password']);
} catch (Exception $e) {
    // If decryption fails, it might be plain text
    if (strlen($result_count_users['password']) < 50) {
        $displayPassword = $result_count_users['password']; // Plain text
    } else {
        $displayPassword = ''; // Error case
    }
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
    
<?php
if(isset($_REQUEST['add-users']))
{
	$username=$_REQUEST['username'];
	$password=$_REQUEST['password'];
	$update_id=$_REQUEST['update_id'];
	
	// Encrypt password
	$encryptedPassword = $encryption->encrypt($password);
	
	//Truncate
	$select_count_users12_truncate="delete from admin_log_ot where username='$username'";
	mysqli_query($db_conn,$select_count_users12_truncate);
	
	if($_REQUEST['dash']==1){$dash=$_REQUEST['dash'];}else{$dash="0";}
	if($_REQUEST['report']==1){$report=$_REQUEST['report'];}else{$report="0";}
	if($_REQUEST['company_profile']==1){$company_profile=$_REQUEST['company_profile'];}else{$company_profile="0";}
	if($_REQUEST['users_demo']==1){$users_demo=$_REQUEST['users_demo'];}else{$users_demo="0";}
	if($_REQUEST['reward_points']==1){$reward_points=$_REQUEST['reward_points'];}else{$reward_points="0";}
	
	if($_REQUEST['demo_free']==1){$demo_free=$_REQUEST['demo_free'];}else{$demo_free="0";}
	if($_REQUEST['manage_return']==1){$manage_return=$_REQUEST['manage_return'];}else{$manage_return="0";}
	if($_REQUEST['debit_note']==1){$debit_note=$_REQUEST['debit_note'];}else{$debit_note="0";}
	if($_REQUEST['stock_request']==1){$stock_request=$_REQUEST['stock_request'];}else{$stock_request="0";}
	if($_REQUEST['products']==1){$products=$_REQUEST['products'];}else{$products="0";}
	
	if($_REQUEST['add_input_stock']==1){$add_input_stock=$_REQUEST['add_input_stock'];}else{$add_input_stock="0";}
	if($_REQUEST['manage_input_stock']==1){$manage_input_stock=$_REQUEST['manage_input_stock'];}else{$manage_input_stock="0";}
	if($_REQUEST['add_input_stock_users']==1){$add_input_stock_users=$_REQUEST['add_input_stock_users'];}else{$add_input_stock_users="0";}
	if($_REQUEST['manage_input_stock_users']==1){$manage_input_stock_users=$_REQUEST['manage_input_stock_users'];}else{$manage_input_stock_users="0";}
	
	if($_REQUEST['ot_channels']==1){$ot_channels=$_REQUEST['ot_channels'];}else{$ot_channels="0";}
	if($_REQUEST['location']==1){$location=$_REQUEST['location'];}else{$location="0";}
	if($_REQUEST['ss']==1){$ss=$_REQUEST['ss'];}else{$ss="0";}
	if($_REQUEST['st']==1){$st=$_REQUEST['st'];}else{$st="0";}
	
	if($_REQUEST['dt']==1){$dt=$_REQUEST['dt'];}else{$dt="0";}
	if($_REQUEST['sdt']==1){$sdt=$_REQUEST['sdt'];}else{$sdt="0";}
	if($_REQUEST['shop']==1){$shop=$_REQUEST['shop'];}else{$shop="0";}
	if($_REQUEST['cus']==1){$cus=$_REQUEST['cus'];}else{$cus="0";}
	if($_REQUEST['ms']==1){$ms=$_REQUEST['ms'];}else{$ms="0";}
	if($_REQUEST['unassigned']==1){$unassigned=$_REQUEST['unassigned'];}else{$unassigned="0";}
	
	if($_REQUEST['remap']==1){$remap=$_REQUEST['remap'];}else{$remap="0";}
	if($_REQUEST['users_network']==1){$users_network=$_REQUEST['users_network'];}else{$users_network="0";}
	if(isset($_REQUEST['add_payment_entry']) && $_REQUEST['add_payment_entry']==1){$add_payment_entry="1";}else{$add_payment_entry="0";}
    if(isset($_REQUEST['manage_payment_entry']) && $_REQUEST['manage_payment_entry']==1){$manage_payment_entry="1";}else{$manage_payment_entry="0";}
    if(isset($_REQUEST['consolidated_payment_entry']) && $_REQUEST['consolidated_payment_entry']==1){$consolidated_payment_entry="1";}else{$consolidated_payment_entry="0";}
    if(isset($_REQUEST['bonus_calculator']) && $_REQUEST['bonus_calculator']==1){$bonus_calculator="1";}else{$bonus_calculator="0";}
    if(isset($_REQUEST['manage_bonus_points']) && $_REQUEST['manage_bonus_points']==1){$manage_bonus_points="1";}else{$manage_bonus_points="0";}
    if(isset($_REQUEST['partner_location']) && $_REQUEST['partner_location']==1){$partner_location="1";}else{$partner_location="0";}
    if(isset($_REQUEST['channel_partner']) && $_REQUEST['channel_partner']==1){$channel_partner="1";}else{$channel_partner="0";}
    if(isset($_REQUEST['territory_partner']) && $_REQUEST['territory_partner']==1){$territory_partner="1";}else{$territory_partner="0";}
    if(isset($_REQUEST['stock_transfers']) && $_REQUEST['stock_transfers']==1){$stock_transfers="1";}else{$stock_transfers="0";}


		$insert_users="update admin_log set password='$encryptedPassword',dash='$dash',report='$report',
        company_profile='$company_profile',users_demo='$users_demo',reward_points='$reward_points',
        demo_free='$demo_free',manage_return='$manage_return',debit_note='$debit_note',stock_request='$stock_request',
        products='$products',ot_channels='$ot_channels',location='$location',
        ss='$ss',st='$st',dt='$dt',sdt='$sdt',shop='$shop',cus='$cus',ms='$ms',unassigned='$unassigned',remap='$remap',
        users_network='$users_network',payment_entry='$add_payment_entry',manage_payment_entry='$manage_payment_entry',consolidated_payment_entry='$consolidated_payment_entry',bonus_calculator='$bonus_calculator',manage_bonus_points='$manage_bonus_points',
        add_input_stock='$add_input_stock',manage_input_stock='$manage_input_stock',add_input_stock_users='$add_input_stock_users',manage_input_stock_users='$manage_input_stock_users',
        partner_location='$partner_location',channel_partner='$channel_partner',territory_partner='$territory_partner',stock_transfers='$stock_transfers' where id='$update_id'";
		mysqli_query($db_conn,$insert_users);
		
		
		//Insert Ot sales category permission 
$catid = implode("#", $_REQUEST['ot_catID'] ?? []);
$ex_catid = array_filter(explode("#", $catid));
 
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
		
		
		echo "<script>window.location='users_manage?updatedSuccess';</script>";
	
	
	
}
?>
	
<?php include("validate-scripts.php");?>
	
<form method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!')">

<input type="hidden" name="update_id" value="<?=$id;?>">
<input type="hidden" name="username" value="<?=$result_count_users['username'];?>">
			
                                        <div class="example-container">
                                            <div class="example-content">
                                   
												
<label class="form-label">Username*</label>
<input type="text" required="" disabled value="<?=$result_count_users['username'];?>" class="form-control" onkeypress="restrictSpecialChars(event)">
<br/>

<label class="form-label">Password*</label>
<input type="text" required="" name="password" value="<?=$displayPassword;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
<br/>
				
				<table style="width:100%;" cellpadding="10">
				<tr>
				
				<td>
				<label>
				<?php if($result_count_users['dash']==1){?>
				<input type="checkbox" value="1" checked name="dash">
				<?php }else{?>
				<input type="checkbox" value="1" name="dash">
				<?php }?>
				Dashboard</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['report']==1){?>
				<input type="checkbox" value="1" checked name="report">
				<?php }else{?>
				<input type="checkbox" value="1" name="report">
				<?php }?>
				Report</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['company_profile']==1){?>
				<input type="checkbox" value="1" checked name="company_profile">
				<?php }else{?>
				<input type="checkbox" value="1" name="company_profile">
				<?php }?>
				Company Profile</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['users_demo']==1){?>
				<input type="checkbox" value="1" checked name="users_demo">
				<?php }else{?>
				<input type="checkbox" value="1" name="users_demo">
				<?php }?>
				Users Demo</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['reward_points']==1){?>
				<input type="checkbox" value="1" checked name="reward_points">
				<?php }else{?>
				<input type="checkbox" value="1" name="reward_points">
				<?php }?>
				Reward Points</label>
				</td>
				
				</tr>
				
				
				<tr>
				
				<td>
				<label>
				<?php if($result_count_users['demo_free']==1){?>
				<input type="checkbox" value="1" checked name="demo_free">
				<?php }else{?>
				<input type="checkbox" value="1" name="demo_free">
				<?php }?>
				Demo/Free/Damage</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['manage_return']==1){?>
				<input type="checkbox" value="1" checked name="manage_return">
				<?php }else{?>
				<input type="checkbox" value="1" name="manage_return">
				<?php }?>
				Manage Return</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['debit_note']==1){?>
				<input type="checkbox" value="1" checked name="debit_note">
				<?php }else{?>
				<input type="checkbox" value="1" name="debit_note">
				<?php }?>
				Debit Note</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['stock_request']==1){?>
				<input type="checkbox" value="1" checked name="stock_request">
				<?php }else{?>
				<input type="checkbox" value="1" name="stock_request">
				<?php }?>
				Stock Request</label>
				</td>
				
				
				<td>
				<label>
				<?php if($result_count_users['products']==1){?>
				<input type="checkbox" value="1" checked name="products">
				<?php }else{?>
				<input type="checkbox" value="1" name="products">
				<?php }?>
				Products</label>
				</td>
				
				</tr>
				
				
				<tr>
				
				<td>
				<label>
				<?php if($result_count_users['add_input_stock']==1){?>
				<input type="checkbox" value="1" checked name="add_input_stock">
				<?php }else{?>
				<input type="checkbox" value="1" name="add_input_stock">
				<?php }?>
				Add Input Stock</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['manage_input_stock']==1){?>
				<input type="checkbox" value="1" checked name="manage_input_stock">
				<?php }else{?>
				<input type="checkbox" value="1" name="manage_input_stock">
				<?php }?>
				Manage Input Stock</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['add_input_stock_users']==1){?>
				<input type="checkbox" value="1" checked name="add_input_stock_users">
				<?php }else{?>
				<input type="checkbox" value="1" name="add_input_stock_users">
				<?php }?>
				Add Input Stock Users</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['manage_input_stock_users']==1){?>
				<input type="checkbox" value="1" checked name="manage_input_stock_users">
				<?php }else{?>
				<input type="checkbox" value="1" name="manage_input_stock_users">
				<?php }?>
				Manage Input Stock Users</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['ss']==1){?>
				<input type="checkbox" value="1" checked name="ss">
				<?php }else{?>
				<input type="checkbox" value="1" name="ss">
				<?php }?>
				Super stockist</label>
				</td>
			
				</tr>
				
				
				
				<tr>
				
				<td>
				<label>
				<?php if($result_count_users['dt']==1){?>
				<input type="checkbox" value="1" checked name="dt">
				<?php }else{?>
				<input type="checkbox" value="1" name="dt">
				<?php }?>
				Distributor</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['sdt']==1){?>
				<input type="checkbox" value="1" checked name="sdt">
				<?php }else{?>
				<input type="checkbox" value="1" name="sdt">
				<?php }?>
				Super Distributor</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['shop']==1){?>
				<input type="checkbox" value="1" checked name="shop">
				<?php }else{?>
				<input type="checkbox" value="1" name="shop">
				<?php }?>
				Shop</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['cus']==1){?>
				<input type="checkbox" value="1" checked name="cus">
				<?php }else{?>
				<input type="checkbox" value="1" name="cus">
				<?php }?>
				Customer</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['ms']==1){?>
				<input type="checkbox" value="1" checked name="ms">
				<?php }else{?>
				<input type="checkbox" value="1" name="ms">
				<?php }?>
				Marketing Staff</label>
				</td>
				
				</tr>
				
				
				<tr>
				    
				<td>
				<label>
				<?php if($result_count_users['unassigned']==1){?>
				<input type="checkbox" value="1" checked name="unassigned">
				<?php }else{?>
				<input type="checkbox" value="1" name="unassigned">
				<?php }?>
				Un-assigned</label>
				</td>    
				
				<td>
				<label>
				<?php if($result_count_users['remap']==1){?>
				<input type="checkbox" value="1" checked name="remap">
				<?php }else{?>
				<input type="checkbox" value="1" name="remap">
				<?php }?>
				Re-mapping</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['users_network']==1){?>
				<input type="checkbox" value="1" checked name="users_network">
				<?php }else{?>
				<input type="checkbox" value="1" name="users_network">
				<?php }?>
				Users network</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['payment_entry']==1){?>
				<input type="checkbox" value="1" checked name="add_payment_entry">
				<?php }else{?>
				<input type="checkbox" value="1" name="add_payment_entry">
				<?php }?>
				Add Payment Entry</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['manage_payment_entry']==1){?>
				<input type="checkbox" value="1" checked name="manage_payment_entry">
				<?php }else{?>
				<input type="checkbox" value="1" name="manage_payment_entry">
				<?php }?>
				Manage Payment Entry</label>
				</td>
				
			
				</tr>
				<tr>
				    <td>
        				<label>
        				<?php if($result_count_users['consolidated_payment_entry']==1){?>
        				<input type="checkbox" value="1" checked name="consolidated_payment_entry">
        				<?php }else{?>
        				<input type="checkbox" value="1" name="consolidated_payment_entry">
        				<?php }?>
        				Consolidated Payment Entry</label>
    				</td>
    				<td>
        				<label>
        				<?php if($result_count_users['bonus_calculator']==1){?>
        				<input type="checkbox" value="1" checked name="bonus_calculator">
        				<?php }else{?>
        				<input type="checkbox" value="1" name="bonus_calculator">
        				<?php }?>
        				Bonus Calculator</label>
    				</td>
    				<td>
        				<label>
        				<?php if($result_count_users['manage_bonus_points']==1){?>
        				<input type="checkbox" value="1" checked name="manage_bonus_points">
        				<?php }else{?>
        				<input type="checkbox" value="1" name="manage_bonus_points">
        				<?php }?>
        				Manage Bonus Points</label>
    				</td>
    				
    				<td>
				<label>
				<?php if($result_count_users['ot_channels']==1){?>
				<input type="checkbox" value="1" checked name="ot_channels">
				<?php }else{?>
				<input type="checkbox" value="1" name="ot_channels">
				<?php }?>
				OT Channels</label>
				</td>
				
				<td>
				<label>
				<?php if($result_count_users['location']==1){?>
				<input type="checkbox" value="1" checked name="location">
				<?php }else{?>
				<input type="checkbox" value="1" name="location">
				<?php }?>
				Location</label>
				</td>
				</tr>
				<tr>
				    <td>
        				<label>
        				<?php if($result_count_users['st']==1){?>
        				<input type="checkbox" value="1" checked name="st">
        				<?php }else{?>
        				<input type="checkbox" value="1" name="st">
        				<?php }?>
        				stockist</label>
				    </td>
				    <td>
        				<label>
        				<?php if($result_count_users['partner_location']==1){?>
        				<input type="checkbox" value="1" checked name="partner_location">
        				<?php }else{?>
        				<input type="checkbox" value="1" name="partner_location">
        				<?php }?>
        				Partner Location</label>
				    </td>
				    <td>
        				<label>
        				<?php if($result_count_users['channel_partner']==1){?>
        				<input type="checkbox" value="1" checked name="channel_partner">
        				<?php }else{?>
        				<input type="checkbox" value="1" name="channel_partner">
        				<?php }?>
        				Channel Partner</label>
				    </td>
				    <td>
        				<label>
        				<?php if($result_count_users['territory_partner']==1){?>
        				<input type="checkbox" value="1" checked name="territory_partner">
        				<?php }else{?>
        				<input type="checkbox" value="1" name="territory_partner">
        				<?php }?>
        				Territory Partner</label>
				    </td>
				    <td>
        				<label>
        				<?php if($result_count_users['stock_transfers']==1){?>
        				<input type="checkbox" value="1" checked name="stock_transfers">
        				<?php }else{?>
        				<input type="checkbox" value="1" name="stock_transfers">
        				<?php }?>
        				Stock Transfers</label>
				    </td>
				</tr>

				</table>
				
				
				
				<br/>
				<label class="form-label"><u>OT SALES CATEGORY</u></label><br/>
				<?php $ot_sls_category="select * from ot_cat order by id asc";
				$fetch_sls_category=mysqli_query($db_conn,$ot_sls_category);
				while($result_sls_category=mysqli_fetch_array($fetch_sls_category)){
					
					$otcatid=$result_sls_category['id'];
					$otcatname=$result_sls_category['cat'];
					
					$select_count_ot="select id from admin_log_ot where username='".$result_count_users['username']."' and ot_cat='$otcatid'";
					$fetch_count_ot=mysqli_query($db_conn,$select_count_ot);
					?>
					
				<?php if(mysqli_num_rows($fetch_count_ot)==1){?>	
				<label><input type="checkbox" checked name="ot_catID[]" value="<?=$otcatid;?>">&nbsp;<?=$otcatname;?></label>
				<?php } else{?>
				<label><input type="checkbox" name="ot_catID[]" value="<?=$otcatid;?>">&nbsp;<?=$otcatname;?></label>
				<?php }?>

				<?php echo "<br/>";?>
				<?php }?>
												
				<br/>
												
			<button type="submit" name="add-users" class="btn btn-primary"><i class="material-icons">update</i>Update</button>
												
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