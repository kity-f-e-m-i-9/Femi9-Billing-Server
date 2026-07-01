<?php 
// Load environment variables FIRST
require_once __DIR__ . '/../shared/env-loader.php';

// Then include session check
include("checksession.php");

// Now load encryption service
require_once __DIR__ . '/../shared/EncryptionService.php';
$encryption = new EncryptionService();

error_reporting(0);

$getinvuser = isset($_REQUEST['invuser']) ? mysqli_real_escape_string($db_conn, $_REQUEST['invuser']) : '';

// Map invuser to table name, display title, edit URL, and active/inactive URL label
switch ($getinvuser) {
    case 'super_stockiest':
        $tablename    = 'super_stockiest';
        $displaytitle = "Deactive - Super Stockist's";
        $editpage     = 'super-stockist-edit-ss';
        $userlabel    = 'Super Stockist';
        $usr_param    = 'super_stockiest';
        break;
    case 'distributor':
        $tablename    = 'distributor';
        $displaytitle = "Deactive - Distributor's";
        $editpage     = 'distributor-edit-ss';
        $userlabel    = 'Distributor';
        $usr_param    = 'distributor';
        break;
    case 'super_distributor':
        $tablename    = 'super_distributor';
        $displaytitle = "Deactive - Super Distributor's";
        $editpage     = 'super-distributor-edit-ss';
        $userlabel    = 'Super Distributor';
        $usr_param    = 'super_distributor';
        break;
    case 'stockiest':
    default:
        $tablename    = 'stockiest';
        $displaytitle = "Deactive - Stockist's";
        $editpage     = 'stockist-edit-ss';
        $userlabel    = 'Stockist';
        $usr_param    = 'stockiest';
        break;
}

$xlurl = "ex_overallusers";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?=$displaytitle?> : <?php echo $business_name;?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">

    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link href="../../assets/css/vlstyle.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        #datatable1 { font-size: 14px; }

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

        #datatable1 tbody tr:hover { background-color: #f8f9ff; }

        .password-container {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }

        .password-toggle {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #555;
            user-select: none;
        }

        .password-toggle:hover { color: #667eea; }

        .password-text {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #333;
            font-size: 13px;
        }

        .copy-btn {
            background: #fff;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            padding: 4px 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .copy-btn:hover {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }

        .copy-btn i { font-size: 14px; }

        .badge-style-bordered {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            border: 2px solid;
        }

        .badge-success { background-color: #d4edda; color: #155724; border-color: #28a745; }
        .badge-danger  { background-color: #f8d7da; color: #721c24; border-color: #dc3545; }

        .edit-btn {
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

        .edit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .edit-btn i { font-size: 18px; }

        #linkcaption {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        #linkcaption:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .serial-number { font-weight: 600; color: #888; }
        .user-name     { font-weight: 600; color: #333; }

        .deactive-badge {
            display: inline-block;
            background-color: #f8d7da;
            color: #721c24;
            border: 2px solid #dc3545;
            border-radius: 20px;
            padding: 3px 14px;
            font-size: 13px;
            font-weight: 600;
            margin-left: 10px;
            vertical-align: middle;
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

                                    <?php if(isset($_REQUEST['Samenumbernotaccepted'])){?><div class="alert alert-danger">Same mobile number not accepted.</div><?php }?>
                                    <?php if(isset($_REQUEST['MobileAlreadyExists'])){?><div class="alert alert-danger">You entered mobile number already exists.</div><?php }?>
                                    <?php if(isset($_REQUEST['MobileUpdatedSuccess'])){?><div class="alert alert-success">New mobile number updated success.</div><?php }?>

                                    <h1>
                                        <table class="headertble">
                                            <tr>
                                                <td>
                                                    <?=$displaytitle?>
                                                    <span class="deactive-badge">Deactive Users</span>
                                                </td>
                                                <td><a href="<?=$xlurl;?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <?php
                        if (isset($_SESSION['successMessage'])) {
                            $successMessage = $_SESSION['successMessage'];
                        ?>
                            <script>
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success',
                                    text: '<?php echo $successMessage; ?>',
                                    confirmButtonText: 'OK'
                                });
                            </script>
                        <?php unset($_SESSION['successMessage']); } ?>

                        <?php
                        $num_rec_per_page = 30;
                        $page       = isset($_GET["page"]) ? $_GET["page"] : 1;
                        $start_from = ($page - 1) * $num_rec_per_page;
                        $i          = $start_from;
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
                                                        <th>Name</th>
                                                        <th>Mobile</th>
                                                        <th>District</th>
                                                        <th>Taluk</th>
                                                        <th>Username</th>
                                                        <th>Password</th>
                                                        <th>Status</th>
                                                        <th>Edit</th>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                <?php
                                                // Fetch deactive users from the correct table based on invuser
                                                $select_product_list = "SELECT * FROM " . $tablename . " WHERE account_status = 'deactive' ORDER BY id DESC";
                                                $fetch_product_list  = mysqli_query($db_conn, $select_product_list);

                                                if (mysqli_num_rows($fetch_product_list) == 0) {
                                                ?>
                                                    <tr>
                                                        <td colspan="10" class="text-center" style="padding:40px; color:#888; font-size:15px;">
                                                            <i class="material-icons-outlined" style="font-size:48px; display:block; margin-bottom:10px; color:#ccc;">person_off</i>
                                                            No deactive <?=$userlabel;?>s found.
                                                        </td>
                                                    </tr>
                                                <?php
                                                } else {
                                                    while ($result_product_list = mysqli_fetch_array($fetch_product_list)) {

                                                        // District
                                                        $district_id = $result_product_list['district_id'];
                                                        if (is_numeric($district_id)) {
                                                            $fetch_district  = mysqli_query($db_conn, "SELECT dist_name FROM district WHERE id='$district_id'");
                                                            $result_district = mysqli_fetch_array($fetch_district);
                                                            $district_name   = $result_district['dist_name'];
                                                        } else {
                                                            $district_name = $district_id;
                                                        }

                                                        // Taluk
                                                        $Taluk_id = $result_product_list['taluk_id'];
                                                        if (is_numeric($Taluk_id)) {
                                                            $fetch_taluk  = mysqli_query($db_conn, "SELECT taluk FROM taluk WHERE id='$Taluk_id'");
                                                            $result_taluk = mysqli_fetch_array($fetch_taluk);
                                                            $taluk_name   = $result_taluk['taluk'];
                                                        } else {
                                                            $taluk_name = $Taluk_id;
                                                        }

                                                        $rowid = base64_encode($result_product_list["id"]);
                                                ?>

                                                <tr>
                                                    <td class="text-center serial-number"><?php echo ++$i; ?></td>
                                                    <td class="text-center"><?=$result_product_list["useridtext"];?></td>
                                                    <td><span class="user-name"><?php echo ucwords($result_product_list["name"]);?></span></td>

                                                    <!-- Mobile with update modal -->
                                                    <td class="text-center">
                                                        <a href="#" id="linkcaption"
                                                           data-bs-toggle="modal"
                                                           data-bs-target="#exampleModalLive<?php echo $result_product_list["id"];?>"
                                                           title="Click to Update Mobile Number">
                                                            <?=$result_product_list["country_code"];?>&nbsp;<?=$result_product_list["mobile_number"];?>
                                                        </a>

                                                        <div class="modal fade" id="exampleModalLive<?php echo $result_product_list["id"];?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Update Mobile Number<br/><?=$result_product_list["mobile_number"];?></h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <form method="post" onsubmit="return confirm('Please make a confirm!');" enctype="multipart/form-data" action="update_mobile_action">
                                                                        <input type="hidden" name="old_mobile_number" value="<?=$result_product_list["mobile_number"];?>">
                                                                        <input type="hidden" name="update_usertype" value="<?=$usr_param;?>">
                                                                        <div class="example-content" style="padding:20px;">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="number" name="new_mobile_number" placeholder="New Mobile Number" min="0" class="form-control">
                                                                                <label for="floatingInput">New Mobile Number</label>
                                                                            </div>
                                                                            <button type="submit" name="UpdateAction" class="btn btn-primary">
                                                                                <i class="material-icons">update</i> Update
                                                                            </button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td class="text-center"><?php echo $district_name;?></td>
                                                    <td class="text-center"><?=$taluk_name;?></td>
                                                    <td class="text-center"><?=$result_product_list["username"];?></td>

                                                    <!-- Password -->
                                                    <td class="text-center">
                                                        <?php
                                                        $storedPassword    = $result_product_list['password'];
                                                        $decryptedPassword = '';
                                                        $passwordError     = false;

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
                                                                <span class="password-toggle" onclick="togglePassword(<?php echo $rowIdUnique; ?>)">
                                                                    <i class="material-icons-outlined" id="pwd-icon-<?php echo $rowIdUnique; ?>" style="font-size:18px;">visibility_off</i>
                                                                    <span class="password-text" id="pwd-text-<?php echo $rowIdUnique; ?>">••••••</span>
                                                                </span>
                                                                <button onclick="copyPassword('<?php echo htmlspecialchars($decryptedPassword, ENT_QUOTES); ?>')" class="copy-btn" title="Copy password">
                                                                    <i class="material-icons-outlined">content_copy</i>
                                                                </button>
                                                                <input type="hidden" id="pwd-value-<?php echo $rowIdUnique; ?>" value="<?php echo htmlspecialchars($decryptedPassword, ENT_QUOTES); ?>">
                                                            </div>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Status -->
                                                    <td class="text-center">
                                                        <?php if ($result_product_list['account_status'] == "pending") { ?>
                                                            <span class='badge badge-style-bordered badge-danger'>Pending</span>
                                                        <?php } else if ($result_product_list['account_status'] == "active") { ?>
                                                            <a href="inactive_user?usr=<?=$usr_param;?>&&backurl=overallusers_stockist&&usrid=<?=$rowid;?>&&usrname=<?=base64_encode($result_product_list["name"]);?>&&userlabel=<?=$userlabel;?>"
                                                               onclick="return confirm('You want to confirm Deactivate this (<?php echo strtoupper($result_product_list["name"]);?>) user?');">
                                                                <span class='badge badge-style-bordered badge-success'>Active</span>
                                                            </a>
                                                        <?php } else { ?>
                                                            <a href="active_user?usr=<?=$usr_param;?>&&backurl=overallusers_stockist&&usrid=<?=$rowid;?>&&usrname=<?=base64_encode($result_product_list["name"]);?>&&userlabel=<?=$userlabel;?>"
                                                               onclick="return confirm('You want to confirm Activate this (<?php echo strtoupper($result_product_list["name"]);?>) user?');">
                                                                <span class='badge badge-style-bordered badge-danger'>Deactive</span>
                                                            </a>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Edit — dynamic URL based on user type -->
                                                    <td class="text-center">
                                                        <a href="<?=$editpage;?>?prid=<?php echo $rowid; ?>" class="edit-btn" title="Edit <?=$userlabel;?>">
                                                            <i class="material-icons-outlined">edit</i>
                                                        </a>
                                                    </td>
                                                </tr>

                                                <?php } } // end while + empty check ?>

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
        const icon  = document.getElementById('pwd-icon-' + userId);
        const text  = document.getElementById('pwd-text-' + userId);
        const value = document.getElementById('pwd-value-' + userId).value;
        if (text.textContent === '••••••') {
            text.textContent = value;
            icon.textContent = 'visibility';
        } else {
            text.textContent = '••••••';
            icon.textContent = 'visibility_off';
        }
    }

    function copyPassword(password) {
        const tempInput = document.createElement('input');
        tempInput.value = password;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
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