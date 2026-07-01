<?php 
// Load environment variables FIRST (before anything else)
require_once __DIR__ . '/../shared/env-loader.php';

// Then include session check
include("checksession.php");

// Now load encryption service
require_once __DIR__ . '/../shared/EncryptionService.php';
$encryption = new EncryptionService();

$title="Manage Users";
$add_url="users_add";
$add_title="Add User";
$message_title="User";
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">


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
								
								<?php if(isset($_REQUEST['addedsuccess'])){?><div class="alert alert-success">
									One new user added successfully.</div>
									<?php }?>
								
								<?php if(isset($_REQUEST['updatedSuccess'])){?><div class="alert alert-info">Changes saved success.</div><?php }?>
								
								<?php if(isset($_REQUEST['deletedDone'])){?><div class="alert alert-warning">Deleted ! one user deleted success.</div><?php }?>
								
                                    <h1>
									<table class="headertble">
									<tr>
									<td><?php echo $title;?></td>
									<td><a href="<?php echo $add_url;?>" title="<?php echo $add_title;?>">&#10011;</a></td>
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
                                                    <th>S.No</th>
													<th>Username</th>
                                                    <th>Password</th>
													
													<th>Actions</th>
                                                </tr>
                                            </thead>
											 <tbody>
					<?php $select_product_list="select * from admin_log where usertype='users' order by id desc";
					$fetch_product_list=mysqli_query($db_conn,$select_product_list);
					while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											$row_id=base64_encode($result_product_list["id"]);
											
											// Decrypt password
											$storedPassword = $result_product_list['password'];
											$decryptedPassword = '';
											$passwordError = false;
											
											try {
												$decryptedPassword = $encryption->decrypt($storedPassword);
											} catch (Exception $e) {
												// If decryption fails, it might be plain text
												if (strlen($storedPassword) < 50) {
													$decryptedPassword = $storedPassword; // Plain text
												} else {
													$passwordError = true;
												}
											}
											?>
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
													<td><?php echo $result_product_list['username'];?></td>
                                                    <td>
													<?php 
													if ($passwordError) {
														echo '<span class="text-danger">Decryption Error</span>';
													} else {
													?>
														<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
															<span id="pwd-hidden-<?php echo $result_product_list["id"];?>" 
																  style="cursor:pointer;font-size:16px;display:flex;align-items:center;gap:5px;" 
																  onclick="document.getElementById('pwd-hidden-<?php echo $result_product_list["id"];?>').style.display='none'; 
																		   document.getElementById('pwd-shown-<?php echo $result_product_list["id"];?>').style.display='flex';"
																  title="Click to show password">
																<i class="material-icons" style="vertical-align:middle;font-size:18px;">visibility_off</i> 
																<strong>••••••••••</strong>
															</span>
															
															<span id="pwd-shown-<?php echo $result_product_list["id"];?>" 
																  style="display:none;cursor:pointer;font-size:16px;align-items:center;gap:5px;" 
																  onclick="document.getElementById('pwd-shown-<?php echo $result_product_list["id"];?>').style.display='none'; 
																		   document.getElementById('pwd-hidden-<?php echo $result_product_list["id"];?>').style.display='flex';"
																  title="Click to hide password">
																<i class="material-icons" style="vertical-align:middle;font-size:18px;">visibility</i> 
																<strong style="background:#ffeb3b;padding:5px 10px;border-radius:5px;letter-spacing:1px;">
																	<?php echo htmlspecialchars($decryptedPassword); ?>
																</strong>
															</span>
															
															<button onclick="copyPassword('<?php echo htmlspecialchars($decryptedPassword, ENT_QUOTES); ?>')" 
																	class="btn btn-sm btn-primary" 
																	style="padding:5px 15px;"
																	title="Copy password to clipboard">
																<i class="material-icons" style="font-size:16px;vertical-align:middle;">content_copy</i> Copy
															</button>
														</div>
													<?php
													}
													?>
													</td>
																										<td>
													    <div class="actions-group">
													        <a href="users_edit?prid=<?php echo $row_id;?>" class="action-link" title="Edit"><i class="material-icons-outlined" style="font-size:17px;color:#667eea;">edit</i></a>
													        <a href="users_delete?prid=<?php echo $row_id;?>&&delusername=<?=base64_encode($result_product_list['username']);?>" class="action-link delete" title="Delete" onclick="return confirm('You want to delete confirm?');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
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
    function copyPassword(password) {
        // Create temporary input
        const tempInput = document.createElement('input');
        tempInput.value = password;
        document.body.appendChild(tempInput);
        tempInput.select();
        
        // Try modern clipboard API first
        if (navigator.clipboard) {
            navigator.clipboard.writeText(password).then(function() {
                showCopySuccess();
            }).catch(function() {
                // Fallback to execCommand
                document.execCommand('copy');
                showCopySuccess();
            });
        } else {
            // Fallback for older browsers
            document.execCommand('copy');
            showCopySuccess();
        }
        
        document.body.removeChild(tempInput);
    }
    
    function showCopySuccess() {
        Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'Password copied to clipboard',
            timer: 1500,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
    </script>
</body>

</html>