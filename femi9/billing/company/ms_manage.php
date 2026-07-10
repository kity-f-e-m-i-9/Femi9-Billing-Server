<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");

require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/../shared/EncryptionService.php';
$encryption = new EncryptionService();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Manage Marketing Staff : <?php echo $business_name;?></title>

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
        
<style>
.password-container { display:inline-flex; align-items:center; gap:6px; background:#f8f9fa; padding:4px 8px; border-radius:6px; border:1px solid #e0e0e0; }
.password-toggle { cursor:pointer; display:inline-flex; align-items:center; gap:4px; color:#555; user-select:none; }
.password-toggle:hover { color:#667eea; }
.password-text { font-family:'Courier New',monospace; font-weight:600; color:#333; font-size:13px; }
.copy-btn { background:none; border:none; cursor:pointer; color:#667eea; padding:2px; display:inline-flex; align-items:center; }
</style>

    <style>
        .action-link { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;text-decoration:none;padding:0; }
        .action-link:hover { background:#f3f4f6;border-color:#d1d5db; }
        .action-link.delete:hover { background:#fef2f2;border-color:#fecaca; }
        .actions-group { display:inline-flex;align-items:center;gap:5px;white-space:nowrap; }
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
								
								<?php if(isset($_REQUEST['addesuccess'])){?><div class="alert alert-success">Marketing Staff added success.</div><?php }?>
								
								<?php if(isset($_REQUEST['updatedSuccess'])){?><div class="alert alert-info">Changes saved success.</div><?php }?>
								
								<?php if(isset($_REQUEST['deletedDone'])){?><div class="alert alert-warning">Deleted ! one Marketing Staff details deleted success.</div><?php }?>
								
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Manage Marketing Staff</td>
									<td><a href="ms_add.php" title="Add Customer">&#10011;</a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
<?php
//----Continuos Serial Number In Next Page.......................
$num_rec_per_page=30;
if (isset($_GET["page"])) { $page  = $_GET["page"]; } else { $page=1; }; 
 $start_from = ($page-1) * $num_rec_per_page; 
$i= $start_from;
//---------------------------------------------------------------
//echo ++$i; 
?>


                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div style="overflow-x:scroll;">
                                         <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Name</th>
													<th>Mobile</th>
													<th>Password</th>
													<th>Email</th>
													<th>Address</th>
													<th>Head</th>
													
													<th>Actions</th>
                                                </tr>
                                            </thead>
											
											<tbody>
										<?php $select_product_list="select * from marketing_staff order by id desc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
											$product_id=base64_encode($result_product_list["id"]);
											?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo $result_product_list["ms_name"];?></td>
													<td><?php echo $result_product_list["country_code"];?>&nbsp;<?php echo $result_product_list["ms_mobile"];?></td>
													<td>
                                                        <?php
                                                        $storedPassword = $result_product_list['password'];
                                                        $decryptedPassword = '';
                                                        $passwordError = false;
                                                        try {
                                                            $decryptedPassword = $encryption->decrypt($storedPassword);
                                                        } catch (Exception $e) {
                                                            if (strlen($storedPassword) < 50) {
                                                                $decryptedPassword = $storedPassword;
                                                            } else {
                                                                $passwordError = true;
                                                            }
                                                        }
                                                        if ($passwordError) {
                                                            echo '<span class="text-danger">Error</span>';
                                                        } else {
                                                            $rowIdUnique = $result_product_list["id"];
                                                        ?>
                                                        <div class="password-container">
                                                            <span class="password-toggle" id="pwd-toggle-<?=$rowIdUnique?>" onclick="togglePassword(<?=$rowIdUnique?>)">
                                                                <i class="material-icons-outlined" id="pwd-icon-<?=$rowIdUnique?>" style="font-size:18px;">visibility_off</i>
                                                                <span class="password-text" id="pwd-text-<?=$rowIdUnique?>">••••••</span>
                                                            </span>
                                                            <button onclick="copyPassword('<?=htmlspecialchars($decryptedPassword, ENT_QUOTES)?>', <?=$rowIdUnique?>)" class="copy-btn" title="Copy password">
                                                                <i class="material-icons-outlined">content_copy</i>
                                                            </button>
                                                            <input type="hidden" id="pwd-value-<?=$rowIdUnique?>" value="<?=htmlspecialchars($decryptedPassword, ENT_QUOTES)?>">
                                                        </div>
                                                        <?php } ?>
                                                        </td>
													<td><?php echo $result_product_list["ms_email"];?></td>
													<td><?php echo $result_product_list["ms_address"];?></td>
													
													<td>
													<?php if($result_product_list['user_position']==1){?>
													<button type="button" class="btn btn-success btn-style-light m-b-xs m-r-xs">Enable</button>
													<?php }else{?>
													<button type="button" class="btn btn-danger btn-style-light m-b-xs m-r-xs">Disable</button>
													<?php }?>
													</td>
													
																										<td>
													    <div class="actions-group">
													        <a href="ms_edit.php?prid=<?php echo $product_id;?>" class="action-link" title="Edit"><i class="material-icons-outlined" style="font-size:17px;color:#667eea;">edit</i></a>
													        <a href="ms_delete.php?prid=<?php echo $product_id;?>" class="action-link delete" title="Delete" onclick="return confirm('You want to delete confirm?');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
													    </div>
													</td>
                                                </tr>
                                           
										<?php }?>
										
										 </tbody>
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
    <script src="../../assets/plugins/datatables/datatables.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/datatables.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function togglePassword(userId) {
        const icon = document.getElementById('pwd-icon-' + userId);
        const text = document.getElementById('pwd-text-' + userId);
        const value = document.getElementById('pwd-value-' + userId).value;
        if (text.textContent === '••••••') {
            text.textContent = value;
            icon.textContent = 'visibility';
        } else {
            text.textContent = '••••••';
            icon.textContent = 'visibility_off';
        }
    }
    function copyPassword(password, userId) {
        const tempInput = document.createElement('input');
        tempInput.value = password;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        Swal.fire({ icon:'success', title:'Copied!', text:'Password copied to clipboard', timer:1500, showConfirmButton:false, toast:true, position:'top-end' });
    }
    </script>
</body>

</html>