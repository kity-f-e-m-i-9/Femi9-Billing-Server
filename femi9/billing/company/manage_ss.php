<?php 
include("checksession.php"); 
error_reporting(0);
include("config.php");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Title -->
    <title>Manage Super Stockist : <?php echo $business_name;?></title>

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

    <style>
        /* Custom table styling */
        #datatable1 {
            font-size: 14px;
        }
        
        #datatable1 thead th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
            padding: 12px 8px;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
            border-bottom: 2px solid #dee2e6;
        }
        
        #datatable1 tbody td {
            padding: 10px 8px;
            vertical-align: middle;
            border-bottom: 1px solid #e8e8e8;
        }
        
        #datatable1 tbody tr {
            transition: background-color 0.2s ease;
        }
        
        #datatable1 tbody tr:hover {
            background-color: #f8f9ff;
        }
        
        /* Badge styling */
        .badge-style-bordered {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            border: 2px solid;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .badge-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        
        .badge-success:hover {
            background-color: #c3e6cb;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }
        
        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }
        
        .badge-danger:hover {
            background-color: #f5c6cb;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        }
        
        .badge-pending {
            background-color: #fff3cd;
            color: #856404;
            border-color: #ffc107;
            cursor: default;
        }
        
        /* Status link styling */
        .status-link {
            text-decoration: none;
        }
        
        .status-link:hover {
            text-decoration: none;
        }
        
        /* Action buttons */
        .action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .action-btn i {
            font-size: 18px;
        }
        
        .action-btn-delete {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .action-btn-delete:hover {
            box-shadow: 0 4px 12px rgba(245, 87, 108, 0.4);
        }
        
        .action-btn-view {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .action-btn-view:hover {
            box-shadow: 0 4px 12px rgba(79, 172, 254, 0.4);
        }
        
        /* Serial number styling */
        .serial-number {
            font-weight: 600;
            color: #888;
        }
        
        /* User name styling */
        .user-name {
            font-weight: 600;
            color: #333;
        }
        
        /* Info text styling */
        .info-text {
            font-size: 13px;
            color: #666;
            line-height: 1.6;
        }
        
        /* Mobile number link styling */
        .mobile-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .mobile-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
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
                                
                                <?php if(isset($_REQUEST['addedsuccess'])){?><div class="alert alert-success">Super Stockist added success.</div><?php }?>
                                
                                <?php if(isset($_REQUEST['updatedSuccess'])){?><div class="alert alert-info">Changes saved success.</div><?php }?>
                                
                                <?php if(isset($_REQUEST['deletedDone'])){?><div class="alert alert-warning">Deleted ! One Super Stockist Details Deleted Success.</div><?php }?>
                                
                                <?php if(isset($_REQUEST['Samenumbernotaccepted'])){?><div class="alert alert-danger">Same mobile number not accepted.</div><?php }?>
                                
                                <?php if(isset($_REQUEST['MobileAlreadyExists'])){?><div class="alert alert-danger">You entered mobile number already exists.</div><?php }?>
                                
                                <?php if(isset($_REQUEST['MobileUpdatedSuccess'])){?><div class="alert alert-success">New mobile number updated success.</div><?php }?>
                                
                                <?php
                                // Check for success message in session
                                if (isset($_SESSION['successMessage'])) {
                                    $successMessage = $_SESSION['successMessage'];
                                    echo "<div class='alert alert-success'>" . htmlspecialchars($successMessage) . "</div>";
                                    unset($_SESSION['successMessage']);
                                }
                                ?>
                                
                                    <h1>
                                    <table class="headertble">
                                    <tr>
                                    <td>Manage Super Stockist</td>
                                    <td><a href="add_ss" title="Add Super Stockist">&#10011;</a></td>
                                    <td><a href="export_ss" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
                                    </tr>
                                    </table>
                                    </h1>
                                </div>
                            </div>
                        </div>
                        
                        <?php
                        //----Continuous Serial Number In Next Page
                        $num_rec_per_page=30;
                        if (isset($_GET["page"])) { $page  = $_GET["page"]; } else { $page=1; }; 
                        $start_from = ($page-1) * $num_rec_per_page; 
                        $i= $start_from;
                        ?>

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div style="overflow-x:auto;">
                                            <table id="datatable1" class="table" style="width:100%;">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>ID</th>
                                                        <th>Details</th>
                                                        <th>Mobile</th>
                                                        <th>Status</th>
                                                        <th>View</th>
                                                        <th>Edit</th>
                                                        <th>Delete</th>
                                                    </tr>
                                                </thead>
                                                
                                                <tbody>
                                                <?php 
                                                $select_product_list="select * from super_stockiest order by id desc";
                                                $fetch_product_list=mysqli_query($db_conn,$select_product_list);
                                                while($result_product_list=mysqli_fetch_array($fetch_product_list))
                                                {
                                                    // Get district name
                                                    $district_id=$result_product_list['district_id'];
                                                    $select_distict="select * from district where id='$district_id'";
                                                    $fetch_district=mysqli_query($db_conn,$select_distict);
                                                    $result_district=mysqli_fetch_array($fetch_district);
                                                    $district_name=$result_district['dist_name'];
                                                    
                                                    // Get taluk name (if available)
                                                    $taluk_name = '';
                                                    if(isset($result_product_list['taluk_id']) && !empty($result_product_list['taluk_id'])) {
                                                        $taluk_id=$result_product_list['taluk_id'];
                                                        $select_taluk="select * from taluk where id='$taluk_id'";
                                                        $fetch_taluk=mysqli_query($db_conn,$select_taluk);
                                                        $result_taluk=mysqli_fetch_array($fetch_taluk);
                                                        $taluk_name=$result_taluk['taluk'];
                                                    }
                                                    
                                                    // Get pincode (if available)
                                                    $pincode_id = isset($result_product_list['pincode_id']) ? $result_product_list['pincode_id'] : '';
                                                    
                                                    $rowid=base64_encode($result_product_list["id"]);
                                                    $encoded_name=base64_encode($result_product_list["name"]);
                                                ?>
                                                
                                                <tr>
                                                    <td class="text-center serial-number"><?php echo ++$i; ?></td>
                                                    <td class="text-center"><?=$result_product_list["useridtext"];?></td>
                                                    <td>
                                                        <div class="user-name"><?php echo ucwords($result_product_list["name"]);?></div>
                                                        <div class="info-text">
                                                            <strong>D:</strong> <?=ucwords($district_name);?>
                                                            <?php if(!empty($taluk_name)) { ?>
                                                                <br/><strong>T:</strong> <?=ucwords($taluk_name);?>
                                                            <?php } ?>
                                                            <?php if(!empty($pincode_id)) { ?>
                                                                <br/><strong>P:</strong> <?=$pincode_id;?>
                                                            <?php } ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="#" 
                                                           class="mobile-link" 
                                                           data-bs-toggle="modal" 
                                                           data-bs-target="#exampleModalLive<?php echo $result_product_list["id"];?>" 
                                                           title="Click to Update Mobile Number">
                                                            <?=$result_product_list["country_code"];?>&nbsp;<?=$result_product_list["mobile_number"];?>
                                                        </a>
                                                        
                                                        <!-- Mobile Update Modal -->
                                                        <div class="modal fade" id="exampleModalLive<?php echo $result_product_list["id"];?>" tabindex="-1" aria-labelledby="exampleModalLiveLabel" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="exampleModalLiveLabel">
                                                                            Update Mobile Number<br/>
                                                                            <?=$result_product_list["mobile_number"];?>
                                                                        </h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <form method="post" onsubmit="return confirm('Please make a confirm!');" enctype="multipart/form-data" action="update_mobile_action">	
                                                                        <input type="hidden" name="old_mobile_number" value="<?=$result_product_list["mobile_number"];?>">
                                                                        <input type="hidden" name="update_usertype" value="super_stockiest">
                                                                        
                                                                        <div class="modal-body" style="padding:20px;">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="number" name="new_mobile_number" placeholder="New Mobile Number" min="0" class="form-control" required>
                                                                                <label for="floatingInput">New Mobile Number</label>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                            <button type="submit" name="UpdateAction" class="btn btn-primary">
                                                                                <i class="material-icons">update</i> Update
                                                                            </button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    
                                                    <td class="text-center">
                                                        <?php 
                                                        if($result_product_list['account_status']=="pending")
                                                        {
                                                            echo "<span class='badge badge-style-bordered badge-pending'>Pending</span>";
                                                        }
                                                        else if($result_product_list['account_status']=="active")
                                                        {
                                                        ?>
                                                            <a href="inactive_user?usr=super_stockiest&backurl=manage_ss&usrid=<?=$rowid;?>&usrname=<?=$encoded_name;?>&userlabel=Super Stockist" 
                                                               class="status-link" 
                                                               onclick="return confirm('You want to confirm Deactivate this (<?php echo strtoupper($result_product_list["name"]);?>) user?');" 
                                                               data-bs-toggle="tooltip" 
                                                               data-bs-placement="top" 
                                                               title="Click to Deactivate user">
                                                                <span class='badge badge-style-bordered badge-success'>Active</span>
                                                            </a>
                                                        <?php
                                                        }
                                                        else
                                                        {
                                                        ?>
                                                            <a href="active_user?usr=super_stockiest&backurl=manage_ss&usrid=<?=$rowid;?>&usrname=<?=$encoded_name;?>&userlabel=Super Stockist" 
                                                               class="status-link" 
                                                               onclick="return confirm('You want to confirm Activate this (<?php echo strtoupper($result_product_list["name"]);?>) user?');" 
                                                               data-bs-toggle="tooltip" 
                                                               data-bs-placement="top" 
                                                               title="Click to Activate user">
                                                                <span class='badge badge-style-bordered badge-danger'>Deactive</span>
                                                            </a>
                                                        <?php
                                                        }
                                                        ?>
                                                    </td>

                                                    <td class="text-center">
                                                        <a href="JavaScript:newPopup('details-ss.php?prid=<?php echo $rowid;?>');" 
                                                           class="action-btn action-btn-view" 
                                                           title="View Details">
                                                            <i class="material-icons-outlined">visibility</i>
                                                        </a>
                                                    </td>
                                                    
                                                    <td class="text-center">
                                                        <a href="edit-ss.php?prid=<?php echo $rowid;?>" 
                                                           class="action-btn" 
                                                           title="Edit Details">
                                                            <i class="material-icons-outlined">edit</i>
                                                        </a>
                                                    </td>
                                                    
                                                    <td class="text-center">
                                                        <a href="delete-ss.php?prid=<?php echo $rowid;?>" 
                                                           onclick="return confirm('You want to delete confirm?');" 
                                                           class="action-btn action-btn-delete" 
                                                           title="Delete Details">
                                                            <i class="material-icons-outlined">delete</i>
                                                        </a>
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

    <script type="text/javascript">
    function newPopup(url) {
        popupWindow = window.open(
            url,'popUpWindow','height=450,width=750,left=350,top=200,resizable=yes,scrollbars=yes,toolbar=yes,menubar=no,location=no,directories=no,status=yes')
    }
    </script>

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
</body>

</html>