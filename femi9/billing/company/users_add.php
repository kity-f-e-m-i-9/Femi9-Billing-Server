<?php 
// Load environment variables FIRST (before anything else)
require_once __DIR__ . '/../shared/env-loader.php';

// Then include session check
include("checksession.php");

// Now load encryption service
require_once __DIR__ . '/../shared/EncryptionService.php';
$encryption = new EncryptionService();
require_once __DIR__ . '/include/PermissionCheckboxWidget.php';
require_once __DIR__ . '/include/PermissionCheck.php';
ensureGranularPermissionColumns($db_conn);

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
    if(isset($_REQUEST['demo_free_edit']) && $_REQUEST['demo_free_edit']==1){$demo_free_edit="1";}else{$demo_free_edit="0";}
    if(isset($_REQUEST['demo_free_delete']) && $_REQUEST['demo_free_delete']==1){$demo_free_delete="1";}else{$demo_free_delete="0";}
    if(isset($_REQUEST['manage_return']) && $_REQUEST['manage_return']==1){$manage_return="1";}else{$manage_return="0";}
    if(isset($_REQUEST['manage_return_edit']) && $_REQUEST['manage_return_edit']==1){$manage_return_edit="1";}else{$manage_return_edit="0";}
    if(isset($_REQUEST['manage_return_delete']) && $_REQUEST['manage_return_delete']==1){$manage_return_delete="1";}else{$manage_return_delete="0";}
    if(isset($_REQUEST['debit_note']) && $_REQUEST['debit_note']==1){$debit_note="1";}else{$debit_note="0";}
    if(isset($_REQUEST['debit_note_edit']) && $_REQUEST['debit_note_edit']==1){$debit_note_edit="1";}else{$debit_note_edit="0";}
    if(isset($_REQUEST['debit_note_delete']) && $_REQUEST['debit_note_delete']==1){$debit_note_delete="1";}else{$debit_note_delete="0";}
    if(isset($_REQUEST['stock_request']) && $_REQUEST['stock_request']==1){$stock_request="1";}else{$stock_request="0";}
    if(isset($_REQUEST['products']) && $_REQUEST['products']==1){$products="1";}else{$products="0";}
    if(isset($_REQUEST['products_edit']) && $_REQUEST['products_edit']==1){$products_edit="1";}else{$products_edit="0";}
    if(isset($_REQUEST['products_delete']) && $_REQUEST['products_delete']==1){$products_delete="1";}else{$products_delete="0";}

    if($_REQUEST['add_input_stock']==1){$add_input_stock=$_REQUEST['add_input_stock'];}else{$add_input_stock="0";}
	if($_REQUEST['manage_input_stock']==1){$manage_input_stock=$_REQUEST['manage_input_stock'];}else{$manage_input_stock="0";}
	if($_REQUEST['manage_input_stock_edit']==1){$manage_input_stock_edit=$_REQUEST['manage_input_stock_edit'];}else{$manage_input_stock_edit="0";}
	if($_REQUEST['manage_input_stock_delete']==1){$manage_input_stock_delete=$_REQUEST['manage_input_stock_delete'];}else{$manage_input_stock_delete="0";}
	if($_REQUEST['add_input_stock_users']==1){$add_input_stock_users=$_REQUEST['add_input_stock_users'];}else{$add_input_stock_users="0";}
	if($_REQUEST['manage_input_stock_users']==1){$manage_input_stock_users=$_REQUEST['manage_input_stock_users'];}else{$manage_input_stock_users="0";}
	if(isset($_REQUEST['manage_input_stock_users_edit']) && $_REQUEST['manage_input_stock_users_edit']==1){$manage_input_stock_users_edit="1";}else{$manage_input_stock_users_edit="0";}
	if(isset($_REQUEST['manage_input_stock_users_delete']) && $_REQUEST['manage_input_stock_users_delete']==1){$manage_input_stock_users_delete="1";}else{$manage_input_stock_users_delete="0";}
	
    if(isset($_REQUEST['ot_channels']) && $_REQUEST['ot_channels']==1){$ot_channels="1";}else{$ot_channels="0";}
    if(isset($_REQUEST['location']) && $_REQUEST['location']==1){$location="1";}else{$location="0";}
    if(isset($_REQUEST['ss']) && $_REQUEST['ss']==1){$ss="1";}else{$ss="0";}
    if(isset($_REQUEST['ss_edit']) && $_REQUEST['ss_edit']==1){$ss_edit="1";}else{$ss_edit="0";}
    if(isset($_REQUEST['ss_delete']) && $_REQUEST['ss_delete']==1){$ss_delete="1";}else{$ss_delete="0";}
    if(isset($_REQUEST['st']) && $_REQUEST['st']==1){$st="1";}else{$st="0";}
    if(isset($_REQUEST['st_edit']) && $_REQUEST['st_edit']==1){$st_edit="1";}else{$st_edit="0";}
    if(isset($_REQUEST['st_delete']) && $_REQUEST['st_delete']==1){$st_delete="1";}else{$st_delete="0";}

    if(isset($_REQUEST['dt']) && $_REQUEST['dt']==1){$dt="1";}else{$dt="0";}
    if(isset($_REQUEST['dt_edit']) && $_REQUEST['dt_edit']==1){$dt_edit="1";}else{$dt_edit="0";}
    if(isset($_REQUEST['dt_delete']) && $_REQUEST['dt_delete']==1){$dt_delete="1";}else{$dt_delete="0";}
    if(isset($_REQUEST['sdt']) && $_REQUEST['sdt']==1){$sdt="1";}else{$sdt="0";}
    if(isset($_REQUEST['sdt_edit']) && $_REQUEST['sdt_edit']==1){$sdt_edit="1";}else{$sdt_edit="0";}
    if(isset($_REQUEST['sdt_delete']) && $_REQUEST['sdt_delete']==1){$sdt_delete="1";}else{$sdt_delete="0";}
    if(isset($_REQUEST['shop']) && $_REQUEST['shop']==1){$shop="1";}else{$shop="0";}
    if(isset($_REQUEST['shop_edit']) && $_REQUEST['shop_edit']==1){$shop_edit="1";}else{$shop_edit="0";}
    if(isset($_REQUEST['shop_delete']) && $_REQUEST['shop_delete']==1){$shop_delete="1";}else{$shop_delete="0";}
    if(isset($_REQUEST['cus']) && $_REQUEST['cus']==1){$cus="1";}else{$cus="0";}
    if(isset($_REQUEST['cus_edit']) && $_REQUEST['cus_edit']==1){$cus_edit="1";}else{$cus_edit="0";}
    if(isset($_REQUEST['cus_delete']) && $_REQUEST['cus_delete']==1){$cus_delete="1";}else{$cus_delete="0";}
    if(isset($_REQUEST['ms']) && $_REQUEST['ms']==1){$ms="1";}else{$ms="0";}
    if(isset($_REQUEST['ms_edit']) && $_REQUEST['ms_edit']==1){$ms_edit="1";}else{$ms_edit="0";}
    if(isset($_REQUEST['ms_delete']) && $_REQUEST['ms_delete']==1){$ms_delete="1";}else{$ms_delete="0";}
    if(isset($_REQUEST['salesbdm_manage']) && $_REQUEST['salesbdm_manage']==1){$salesbdm_manage="1";}else{$salesbdm_manage="0";}
    if(isset($_REQUEST['salesbdm_manage_edit']) && $_REQUEST['salesbdm_manage_edit']==1){$salesbdm_manage_edit="1";}else{$salesbdm_manage_edit="0";}
    if(isset($_REQUEST['salesbdm_manage_delete']) && $_REQUEST['salesbdm_manage_delete']==1){$salesbdm_manage_delete="1";}else{$salesbdm_manage_delete="0";}
    if(isset($_REQUEST['unassigned']) && $_REQUEST['unassigned']==1){$unassigned="1";}else{$unassigned="0";}

    if(isset($_REQUEST['remap']) && $_REQUEST['remap']==1){$remap="1";}else{$remap="0";}
    if(isset($_REQUEST['users_network']) && $_REQUEST['users_network']==1){$users_network="1";}else{$users_network="0";}
    if(isset($_REQUEST['add_payment_entry']) && $_REQUEST['add_payment_entry']==1){$add_payment_entry="1";}else{$add_payment_entry="0";}
    if(isset($_REQUEST['manage_payment_entry']) && $_REQUEST['manage_payment_entry']==1){$manage_payment_entry="1";}else{$manage_payment_entry="0";}
    if(isset($_REQUEST['manage_payment_entry_edit']) && $_REQUEST['manage_payment_entry_edit']==1){$manage_payment_entry_edit="1";}else{$manage_payment_entry_edit="0";}
    if(isset($_REQUEST['manage_payment_entry_delete']) && $_REQUEST['manage_payment_entry_delete']==1){$manage_payment_entry_delete="1";}else{$manage_payment_entry_delete="0";}
    if(isset($_REQUEST['consolidated_payment_entry']) && $_REQUEST['consolidated_payment_entry']==1){$consolidated_payment_entry="1";}else{$consolidated_payment_entry="0";}
    if(isset($_REQUEST['consolidated_payment_entry_edit']) && $_REQUEST['consolidated_payment_entry_edit']==1){$consolidated_payment_entry_edit="1";}else{$consolidated_payment_entry_edit="0";}
    if(isset($_REQUEST['consolidated_payment_entry_delete']) && $_REQUEST['consolidated_payment_entry_delete']==1){$consolidated_payment_entry_delete="1";}else{$consolidated_payment_entry_delete="0";}
    if(isset($_REQUEST['bonus_calculator']) && $_REQUEST['bonus_calculator']==1){$bonus_calculator="1";}else{$bonus_calculator="0";}
    if(isset($_REQUEST['manage_bonus_points']) && $_REQUEST['manage_bonus_points']==1){$manage_bonus_points="1";}else{$manage_bonus_points="0";}
    if(isset($_REQUEST['manage_bonus_points_edit']) && $_REQUEST['manage_bonus_points_edit']==1){$manage_bonus_points_edit="1";}else{$manage_bonus_points_edit="0";}
    if(isset($_REQUEST['manage_bonus_points_delete']) && $_REQUEST['manage_bonus_points_delete']==1){$manage_bonus_points_delete="1";}else{$manage_bonus_points_delete="0";}
    if(isset($_REQUEST['partner_location']) && $_REQUEST['partner_location']==1){$partner_location="1";}else{$partner_location="0";}
    if(isset($_REQUEST['channel_partner']) && $_REQUEST['channel_partner']==1){$channel_partner="1";}else{$channel_partner="0";}
    if(isset($_REQUEST['channel_partner_edit']) && $_REQUEST['channel_partner_edit']==1){$channel_partner_edit="1";}else{$channel_partner_edit="0";}
    if(isset($_REQUEST['channel_partner_delete']) && $_REQUEST['channel_partner_delete']==1){$channel_partner_delete="1";}else{$channel_partner_delete="0";}
    if(isset($_REQUEST['territory_partner']) && $_REQUEST['territory_partner']==1){$territory_partner="1";}else{$territory_partner="0";}
    if(isset($_REQUEST['territory_partner_edit']) && $_REQUEST['territory_partner_edit']==1){$territory_partner_edit="1";}else{$territory_partner_edit="0";}
    if(isset($_REQUEST['territory_partner_delete']) && $_REQUEST['territory_partner_delete']==1){$territory_partner_delete="1";}else{$territory_partner_delete="0";}
    if(isset($_REQUEST['stock_transfers']) && $_REQUEST['stock_transfers']==1){$stock_transfers="1";}else{$stock_transfers="0";}
    if(isset($_REQUEST['internal_transfer']) && $_REQUEST['internal_transfer']==1){$internal_transfer="1";}else{$internal_transfer="0";}


	$select_count_users="select count(*) as numusers from admin_log where username='$username'";
	$fetch_count_users=mysqli_query($db_conn,$select_count_users);
	$result_count_users=mysqli_fetch_array($fetch_count_users);
	if($result_count_users['numusers']==1)
	{
		echo "<script>window.location='users_add?alreadyexists';</script>";
	    exit;
	}

		$insert_users="INSERT INTO admin_log (username,password,usertype,state,dash,report,company_profile,users_demo,reward_points,demo_free,demo_free_edit,demo_free_delete,manage_return,manage_return_edit,manage_return_delete,debit_note,debit_note_edit,debit_note_delete,stock_request,products,products_edit,products_delete,ot_channels,location,ss,ss_edit,ss_delete,st,st_edit,st_delete,dt,dt_edit,dt_delete,sdt,sdt_edit,sdt_delete,shop,shop_edit,shop_delete,cus,cus_edit,cus_delete,ms,ms_edit,ms_delete,salesbdm_manage,salesbdm_manage_edit,salesbdm_manage_delete,unassigned,remap,users_network,payment_entry, manage_payment_entry, manage_payment_entry_edit, manage_payment_entry_delete, consolidated_payment_entry, consolidated_payment_entry_edit, consolidated_payment_entry_delete, bonus_calculator ,manage_bonus_points, manage_bonus_points_edit, manage_bonus_points_delete, add_input_stock,manage_input_stock,manage_input_stock_edit,manage_input_stock_delete ,add_input_stock_users, manage_input_stock_users, manage_input_stock_users_edit, manage_input_stock_users_delete, partner_location, channel_partner, channel_partner_edit, channel_partner_delete, territory_partner, territory_partner_edit, territory_partner_delete, stock_transfers, internal_transfer) values ('$username','$encryptedPassword','$usertype','$state','$dash','$report','$company_profile','$users_demo',
		'$reward_points','$demo_free','$demo_free_edit','$demo_free_delete','$manage_return','$manage_return_edit','$manage_return_delete','$debit_note','$debit_note_edit','$debit_note_delete','$stock_request','$products','$products_edit','$products_delete','$ot_channels','$location','$ss','$ss_edit','$ss_delete','$st','$st_edit','$st_delete','$dt','$dt_edit','$dt_delete','$sdt','$sdt_edit','$sdt_delete','$shop','$shop_edit','$shop_delete','$cus','$cus_edit','$cus_delete','$ms','$ms_edit','$ms_delete','$salesbdm_manage','$salesbdm_manage_edit','$salesbdm_manage_delete','$unassigned','$remap','$users_network','$add_payment_entry','$manage_payment_entry','$manage_payment_entry_edit','$manage_payment_entry_delete','$consolidated_payment_entry','$consolidated_payment_entry_edit','$consolidated_payment_entry_delete','$bonus_calculator','$manage_bonus_points','$manage_bonus_points_edit','$manage_bonus_points_delete','$add_input_stock','$manage_input_stock','$manage_input_stock_edit','$manage_input_stock_delete','$add_input_stock_users','$manage_input_stock_users','$manage_input_stock_users_edit','$manage_input_stock_users_delete','$partner_location','$channel_partner','$channel_partner_edit','$channel_partner_delete','$territory_partner','$territory_partner_edit','$territory_partner_delete','$stock_transfers','$internal_transfer')";
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
				
				<div class="view-only-row">
					<button type="button" id="viewOnlyBtn" class="btn btn-sm view-only-btn" title="Check/uncheck View for every module below">Select All (View Only)</button>
				</div>
				<table id="permGrid" cellpadding="0">
				<tr>
					<th>Module</th>
					<th class="perm-check-head" id="permColView">View</th>
					<th class="perm-check-head" id="permColEdit">Edit</th>
					<th class="perm-check-head" id="permColDelete">Delete</th>
					<th class="perm-check-head" id="permColAll">All</th>
				</tr>

				<?php renderPermSectionHeader('Core'); ?>
				<?php renderPermViewOnlyRow('dash', 'Dashboard'); ?>
				<?php renderPermViewOnlyRow('report', 'Report'); ?>
				<?php renderPermViewOnlyRow('company_profile', 'Company Profile'); ?>
				<?php renderPermViewOnlyRow('users_demo', 'Users Demo'); ?>
				<?php renderPermViewOnlyRow('reward_points', 'Reward Points'); ?>
				<?php renderPermViewOnlyRow('ot_channels', 'OT Channels'); ?>
				<?php renderPermViewOnlyRow('location', 'Location'); ?>
				<?php renderPermViewOnlyRow('unassigned', 'Un-assigned'); ?>
				<?php renderPermViewOnlyRow('remap', 'Re-mapping'); ?>
				<?php renderPermViewOnlyRow('users_network', 'Users network'); ?>
				<?php renderPermViewOnlyRow('stock_request', 'Stock Request'); ?>
				<?php renderPermViewOnlyRow('bonus_calculator', 'Bonus Calculator'); ?>

				<?php renderPermSectionHeader('Stock'); ?>
				<?php renderPermAddOnlyRow('add_input_stock', 'Add Input Stock'); ?>
				<?php renderPermRow('manage_input_stock', 'Manage Input Stock'); ?>
				<?php renderPermAddOnlyRow('add_input_stock_users', 'Add Input Stock Users'); ?>
				<?php renderPermRow('manage_input_stock_users', 'Manage Input Stock Users'); ?>
				<?php renderPermViewOnlyRow('partner_location', 'Partner Location'); ?>
				<?php renderPermViewOnlyRow('stock_transfers', 'Stock Transfers'); ?>
				<?php renderPermViewOnlyRow('internal_transfer', 'Internal Stock Transfer'); ?>

				<?php renderPermSectionHeader('Products & Returns'); ?>
				<?php renderPermRow('products', 'Products'); ?>
				<?php renderPermRow('demo_free', 'Demo/Free/Damage'); ?>
				<?php renderPermRow('manage_return', 'Manage Return'); ?>
				<?php renderPermRow('debit_note', 'Debit Note'); ?>

				<?php renderPermSectionHeader('Partners & Network'); ?>
				<?php renderPermRow('ss', 'Super Stockist'); ?>
				<?php renderPermRow('st', 'Stockist'); ?>
				<?php renderPermRow('dt', 'Distributor'); ?>
				<?php renderPermRow('sdt', 'Super Distributor'); ?>
				<?php renderPermRow('shop', 'Shop'); ?>
				<?php renderPermRow('cus', 'Customer'); ?>
				<?php renderPermRow('ms', 'Marketing Staff'); ?>
				<?php renderPermRow('salesbdm_manage', 'Sales BDM'); ?>
				<?php renderPermRow('channel_partner', 'Channel Partner'); ?>
				<?php renderPermRow('territory_partner', 'Territory Partner'); ?>

				<?php renderPermSectionHeader('Payments & Bonus'); ?>
				<?php renderPermAddOnlyRow('payment_entry', 'Add Payment Entry', null, 'add_payment_entry'); ?>
				<?php renderPermRow('manage_payment_entry', 'Manage Payment Entry'); ?>
				<?php renderPermRow('consolidated_payment_entry', 'Consolidated Payment Entry'); ?>
				<?php renderPermRow('manage_bonus_points', 'Manage Bonus Points'); ?>

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
    <?php renderPermGroupScript(); ?>
</body>

</html>