<?php 
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");
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
     <title>Wallet : <?php echo $business_name;?></title>

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
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">


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
	   
	   
	   <?php
// Check for error message in session
if (isset($_SESSION['successMessage'])) {
$successMessage = $_SESSION['successMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: '<?php echo $successMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['successMessage']); } ?>
	   
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="card todo-container wh-card">
                                    <div class="row wh-row">
									
									
									
                                        <div class="col-12 wh-page">
                                            <div class="todo-menu wh-menu" style="padding:0;text-align:left;">

                                                <div class="wh-balance-box"><div class="wh-bal"><span class="wh-bal-label">Wallet - Available Amount</span><span class="wh-bal-amt">&#8377;<?=inr_format($Average_available_walletAmount_ST, 2);?></span></div><div class="wh-bal-action">
												
												<?php if($Average_available_walletAmount_ST>0){?>
												<a href="#" id="linkcaption" data-bs-toggle="modal" data-bs-target="#exampleModalLive">
												<button type="button" class="btn btn-primary">Send Withdraw Request</button>
												</a>
												<?php }?></div></div>
												
												<?php 
$select_admin_settings2233="select tds_percentage from admin_settings where id='1'";
$fetch_admin_settings2233=mysqli_query($db_conn,$select_admin_settings2233);
$result_admin_settings2233=mysqli_fetch_array($fetch_admin_settings2233);
$tds_percentage=$result_admin_settings2233['tds_percentage'];
?>
												
												<div class="wh-note"><b>Note:</b> <?=$tds_percentage;?>% TDS will be deducted for all withdrawals by Femi9, and it will be reflected in your PAN card only if it is linked with your aadhar.</div>

												
												<!----------------------------------------------------------------->
												<!--------------------------Popup Open----------------------------->
												<div class="modal fade" id="exampleModalLive<?php echo $result_product_list["id"];?>" tabindex="-1" aria-labelledby="exampleModalLiveLabel" aria-hidden="true">
													
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLiveLabel">Wallet Withdraw Request<br/>
																</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
									<form method="post" onsubmit="return confirm('Please make a confirm!');" enctype="multipart/form-data" action="wallet_request_process">	

<?php function GeraHash($qtd){ $Caracteres = '123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } $randum_number=GeraHash(20);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("His"); 
$tempID="".$randum_number."".$temp_date."".$temp_time."";?>

<input type="hidden" name="req_id" value="<?=$tempID?>">
<input type="hidden" name="req_status" value="pending">

<input type="hidden" name="user_type" value="<?=$Login_user_TYPEvl?>">
<input type="hidden" name="user_id" value="<?=$Login_user_IDvl?>">

                                                            <div class="example-content" style="padding:20px;">												
<?php
$selectcoutprofileDetaiils="select acname,acnumber,bankname,ifsc,upinumber, pannumber from users_profile where user_tempid='$Login_user_IDvl' and usertype='$Login_user_TYPEvl'";
$fetchcountprofileDetails=mysqli_query($db_conn,$selectcoutprofileDetaiils);
$resultcountprofileDetails=mysqli_fetch_array($fetchcountprofileDetails);
if(mysqli_num_rows($fetchcountprofileDetails)==1)
{
$user_acc_Name=$resultcountprofileDetails['acname'];
$user_acc_Number=$resultcountprofileDetails['acnumber'];
$user_acc_BankName=$resultcountprofileDetails['bankname'];
$user_acc_IFSC=$resultcountprofileDetails['ifsc'];
$user_acc_PAN=$resultcountprofileDetails['pannumber'];
}
?>
<div class="form-floating mb-3">
<input type="number" min="100" max="<?=$Average_available_walletAmount_ST;?>" name="request_amount" required placeholder="Amount" class="form-control" id="floatingPassword">
<label for="floatingPassword">Amount</label>
</div>

<div class="form-floating mb-3">
<input type="text" value="<?=$user_acc_Name;?>" name="acname" required placeholder="A/C Name" class="form-control">
<label>A/C Name</label>
</div>

<div class="form-floating mb-3">
<input type="text" value="<?=$user_acc_Number;?>" name="acnumber" required placeholder="A/C Number" class="form-control">
<label>A/C Number</label>
</div>

<div class="form-floating mb-3">
<input type="text" value="<?=$user_acc_BankName;?>" name="bankname" required placeholder="Bank Name" class="form-control">
<label>Bank Name</label>
</div>

<div class="form-floating mb-3">
<input type="text" value="<?=$user_acc_IFSC;?>" name="ifsc" required placeholder="IFS Code" class="form-control">
<label>IFS Code</label>
</div>

<div class="form-floating mb-3">
<input type="text" value="<?=$user_acc_PAN;?>" name="pannumber" required placeholder="PAN Number" class="form-control">
<label>PAN Number</label>
</div>
												
<button type="submit" name="sent_money_request" class="btn btn-primary">
<i class="material-icons">send</i>Sent</button>
												
												
                                            </div>
											</form>
											</form>
                                                        </div>
                                                    </div>
                                                </div>
												
												<!----------------------------------------------------------------->
												<!--------------------------Popup Closed--------------------------->
                                            </div>
                                        </div>
<?php
$wh_to   = (isset($_GET['to_date'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to_date']))   ? $_GET['to_date']   : date('Y-m-d');
$wh_from = (isset($_GET['from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from_date'])) ? $_GET['from_date'] : date('Y-m-d', strtotime('-7 months'));
if ($wh_from > $wh_to) { $t = $wh_from; $wh_from = $wh_to; $wh_to = $t; }
?>
<style>
.wh-page{padding:0}
.wh-balance-box{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;padding:22px 26px;border-radius:12px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff}
.wh-bal{display:flex;flex-direction:column;gap:4px}
.wh-bal-label{font-size:12px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;opacity:.85}
.wh-bal-amt{font-size:32px;font-weight:700;line-height:1.1}
.wh-bal-action .btn{background:#fff;color:#4f46e5;border:0;font-weight:600;padding:10px 22px;border-radius:8px}
.wh-note{margin:14px 0 0;padding:12px 16px;border-radius:10px;background:#fff7e6;border:1px solid #ffe2a8;color:#8a5a00;font-size:13px;line-height:1.5}
.wh-section-title{font-size:13px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#6b7280;margin:26px 0 10px}
.wh-filter{display:flex;flex-wrap:wrap;align-items:flex-end;gap:14px;padding:14px 18px;background:#f6f8fb;border:1px solid #e4e8ef;border-radius:10px}
.wh-filter .wh-f{display:flex;flex-direction:column;gap:4px}
.wh-filter label{font-size:12px;font-weight:600;color:#6b7280;margin:0}
.wh-filter input[type=date]{height:40px;min-width:170px;border-radius:8px}
.wh-filter .wh-actions{display:flex;gap:8px;margin-left:auto}
.wh-filter .btn{height:40px;border-radius:8px;display:inline-flex;align-items:center}
.wh-col{margin-top:22px}
.wh-col .todo-list{height:100%}
.wh-col .todo-menu-title{display:flex;align-items:center;gap:8px;font-size:16px;font-weight:700;padding:0 0 10px;margin:0 0 14px;border-bottom:2px solid #eef0f4}
.wh-col .todo-menu-title:before{content:"";width:10px;height:10px;border-radius:50%;background:#22c55e}
.wh-col.wh-debit .todo-menu-title:before{background:#4f46e5}
.wh-col .todo-list>ul{max-height:620px;overflow-y:auto;padding:0 4px 0 0;margin:0}
.wh-col .todo-item{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;background:#fff;border:1px solid #e9ecf1;border-radius:10px;padding:14px 16px;margin:0 0 10px}
.wh-col .todo-item:hover{box-shadow:0 2px 8px rgba(0,0,0,.07)}
.wh-col .todo-item-content{display:flex;flex-direction:column;gap:4px;flex:1;min-width:0}
.wh-col .todo-item-title{display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-size:17px;font-weight:700}
.wh-col .todo-item-subtitle{display:block;font-size:12px;color:#8a93a2}
.wh-col .todo-item-actions{flex:none}
.wh-empty{display:block;text-align:center;padding:34px 16px;border:1px dashed #d5dae3;border-radius:10px;color:#8a93a2;font-size:14px;list-style:none}
.wh-empty .material-icons-outlined{display:block;font-size:34px;margin-bottom:6px;color:#c3c9d4}
@media(max-width:767px){.wh-bal-amt{font-size:26px}.wh-filter .wh-actions{margin-left:0;width:100%}.wh-filter .wh-f{flex:1 1 140px}}
</style>
<style>
.wh-card{height:auto!important;min-height:0;overflow:visible;padding:24px}
.wh-card>.wh-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 24px;margin:0;height:auto}
.wh-card>.wh-row>div{width:auto;max-width:none;height:auto;padding:0;margin:0}
.wh-card>.wh-row>.wh-page,.wh-card>.wh-row>.wh-filter-wrap{grid-column:1/-1}
.wh-card .wh-menu{margin:0!important;padding:0!important;height:auto!important;overflow:visible;border:0!important}
.wh-card .wh-col .todo-list{margin:0;height:auto;overflow:visible}
.wh-card .wh-col .todo-list>ul{padding:0 6px 0 0}
.wh-card .wh-section-title{margin-top:24px}
.wh-card>.wh-row>.wh-col{margin-top:32px}
@media(max-width:991px){.wh-card{padding:16px}.wh-card>.wh-row{grid-template-columns:minmax(0,1fr)}}
</style>
<div class="col-12 wh-filter-wrap">
<div class="wh-section-title">Filter by date</div>
<form method="get" class="wh-filter">
<div class="wh-f"><label>From</label><input type="date" name="from_date" value="<?=$wh_from;?>" max="<?=date('Y-m-d');?>" class="form-control" required></div>
<div class="wh-f"><label>To</label><input type="date" name="to_date" value="<?=$wh_to;?>" max="<?=date('Y-m-d');?>" class="form-control" required></div>
<div class="wh-actions"><button type="submit" class="btn btn-primary">Apply</button><a href="wallet-history" class="btn btn-outline-secondary">Last 7 months</a></div>
</form>
</div>
										
										
										
                      <div class="col-lg-6 wh-col wh-credit">
                              <div class="todo-list">
							  <h5 class="todo-menu-title">Credit History</h5>
                                    <ul class="list-unstyled">		
<?php 
$select_wallet_History1234="select * from wallet_monthly_sls_report where refer_by_usertype='$Login_user_TYPEvl' and refer_by_userid='$Login_user_IDvl' and DATE(from_date) between '$wh_from' and '$wh_to' order by from_date desc";
$fetch_wallet_History1234=mysqli_query($db_conn,$select_wallet_History1234);
while($result_wallet_History1234=mysqli_fetch_array($fetch_wallet_History1234))
{

//Whom did he refferred?
$whom_user_type=$result_wallet_History1234['user_type'];
$Whom_user_id=$result_wallet_History1234['user_id'];
									
if($whom_user_type=="candf"){$tablename_whom="c_and_f";}
elseif($whom_user_type=="super_stockiest") {$tablename_whom="super_stockiest";}
elseif($whom_user_type=="stockiest") {$tablename_whom="stockiest";}
elseif($whom_user_type=="distributor"){$tablename_whom="distributor";}
else{$tablename_whom="super_distributor";}

$select_onbaord_user_records_whom="select * from ".$tablename_whom." where temp_id='$Whom_user_id'";
$fetch_onbaord_user_records_whom=mysqli_query($db_conn,$select_onbaord_user_records_whom);
$result_onbaord_user_records_whom=mysqli_fetch_array($fetch_onbaord_user_records_whom);

$commissionType=$result_wallet_History1234['commission_type'];

?>
												
                                                    <li class="todo-item">
<div class="todo-item-content">
<span class="todo-item-title">&#8377;
<?=inr_format($result_wallet_History1234['commission_amount'], 2);?>
<span class="badge badge-style-light rounded-pill badge-success">Credit (<?=$commissionType;?>)</span></span>

<!----Whom did he refferred---->
<?php if($commissionType=='Refferral'){?>
<span><b><?php echo ucwords($result_onbaord_user_records_whom['name']);?> </b><span style="font-size:12px !important;">(<?=ucwords($whom_user_type);?>)</span><br/>
<?php echo $result_onbaord_user_records_whom['mobile_number'];?></span>
<?php }?>

<!-------Cashback Remarks------->
<?php if($commissionType=='Cashback'){?>
Cashback : <?php echo $result_wallet_History1234['commission_percentage'];?>%<br/>
<?php echo $result_wallet_History1234['remarks'];?>
<?php }?>
															
	<?php if($commissionType!='Website Order Commission'){?>
	<span class="todo-item-subtitle"><?=$result_wallet_History1234['month'];?>, <?=$result_wallet_History1234['year'];?></span>
	<?php }else{?>
	<span class="todo-item-subtitle">Website Order Commission (OT Sales)<br/><?=$result_wallet_History1234['remarks'];?></span>
	<?php }?>
															
                                                        </div>
                                                        <!-----<div class="todo-item-actions">
                                                            <a href="#" class="todo-item-delete"><i class="material-icons-outlined no-m">close</i></a>
                                                            <a href="#" class="todo-item-done"><i class="material-icons-outlined no-m">done</i></a>
                                                        </div>---->
                                                    </li>
													
<?php }?><?php if(mysqli_num_rows($fetch_wallet_History1234)==0){?><li class="wh-empty"><i class="material-icons-outlined">inbox</i>No credits found from <?=date("d M Y",strtotime($wh_from));?> to <?=date("d M Y",strtotime($wh_to));?>.<br/>Try a different date range.</li><?php }?>
                                                </ul>
                                            </div>
                                        </div>
										
										
										<div class="col-lg-6 wh-col wh-debit">
                                            <div class="todo-list">
											<h5 class="todo-menu-title">Debit History</h5>
                                                <ul class="list-unstyled">
												
<?php 
$select_wallet_History1234="select * from wallet_withdraw where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' and DATE(date) between '$wh_from' and '$wh_to' order by date desc";
$fetch_wallet_History1234=mysqli_query($db_conn,$select_wallet_History1234);
while($result_wallet_History1234=mysqli_fetch_array($fetch_wallet_History1234))
{
?>
												 <li class="todo-item">
                                                        <div class="todo-item-content">
                                                            <span class="todo-item-title">&#8377;<?=inr_format($result_wallet_History1234['amount'], 2);?>

<?php if($result_wallet_History1234['req_status']=='pending'){?>
<span class="badge badge-style-light rounded-pill badge-danger">Pending</span>
<?php } else {?>
<span class="badge badge-style-light rounded-pill badge-primary">Debit</span>
<?php }?>															

															</span>
															
															<span class="todo-item-subtitle"><?=date("d/m/Y",strtotime($result_wallet_History1234['date']));?>, <?=date("g:i A",strtotime($result_wallet_History1234['time']));?></span>
                                                        </div>
                                                        <div class="todo-item-actions">
                                                            <a href="del_request?delid=<?=base64_encode($result_wallet_History1234['id']);?>" onclick="return confirm('You want to delete confirm?');" class="todo-item-delete"><i class="material-icons-outlined no-m">close</i></a>
                                                            <!----<a href="#" class="todo-item-done"><i class="material-icons-outlined no-m">done</i></a>---->
                                                        </div>
                                                    </li>
<?php }?><?php if(mysqli_num_rows($fetch_wallet_History1234)==0){?><li class="wh-empty"><i class="material-icons-outlined">inbox</i>No withdrawals found from <?=date("d M Y",strtotime($wh_from));?> to <?=date("d M Y",strtotime($wh_to));?>.<br/>Try a different date range.</li><?php }?>
												</ul>
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