<?php 
// Load environment variables FIRST
require_once __DIR__ . '/../shared/env-loader.php';

// Then include session check
include("checksession.php");

// Now load encryption service
require_once __DIR__ . '/../shared/EncryptionService.php';
$encryption = new EncryptionService();

$get_id=base64_decode($_REQUEST['prid']);
$select_product_list="select * from super_stockiest where id='$get_id'";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
$result_product_list=mysqli_fetch_array($fetch_product_list);
				
//state details
$state_id=$result_product_list['state_id'];
$select_state_dtails="select * from state where id='$state_id'";
$fetch_state_dtails=mysqli_query($db_conn,$select_state_dtails);
$result_state_dtails=mysqli_fetch_array($fetch_state_dtails);
$state_name=$result_state_dtails['st_name'];

//district details
$district_id=$result_product_list['district_id'];
$select_distict="select * from district where id='$district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?php echo ucwords($result_product_list['name']);?> : Super Stockist</title>

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
    <style type="text/css">
        #usernamebox{background:#c6ff54;font-weight:bold;padding:5px;border-radius:5px;letter-spacing:1px;}
    </style>
</head>

<body>
    
    <div style="padding:20px;">
        <h1>Details : Super Stockist</h1>
        <hr/>
    </div>

    <table class="table">
        <?php if($result_product_list["user_icon"]!="Nil"){ ?>
        <tr>
            <th colspan="2">
                <?php if($result_product_list["onboard_userTYPE"]=="candf"){ ?>
                <img src="../c-and-f/<?php echo $result_product_list["user_icon"];?>" style="width:150px;border-radius:10px;">
                <?php }else{?>
                <img src="<?php echo $result_product_list["user_icon"];?>" style="width:150px;border-radius:10px;">
                <?php }?>
            </th>
        </tr>
        <?php } ?>
        
        <tr>
            <th scope="col">Account Status</th>
            <td>
                <?php 
                if($result_product_list['account_status']=="pending")
                {
                    echo "<span class='badge badge-style-bordered badge-danger'>Pending</span>";
                }
                else if($result_product_list['account_status']=="active")
                {
                    echo "<span class='badge badge-style-bordered badge-success'>Active</span>";
                }else{
                    echo "<span class='badge badge-style-bordered badge-danger'>Deactive</span>";
                }
                ?>
            </td>
        </tr>
        
        <tr>
            <th scope="col">Name</th>
            <td><?php echo $result_product_list['name'];?></td>
        </tr>
        
        <tr>
            <th scope="col">State</th>
            <td><?php echo $state_name;?></td>
        </tr>
        
        <tr>
            <th scope="col">District</th>
            <td><?php echo $district_name;?></td>
        </tr>
        
        <tr>
            <th scope="col">Email ID</th>
            <td><?php echo $result_product_list['email'];?></td>
        </tr>
        
        <tr>
            <th scope="col">Address</th>
            <td><?php echo $result_product_list['address'];?></td>
        </tr>
        
        <tr>
            <th scope="col">Mobile Number</th>
            <td><?php echo $result_product_list['country_code'];?>&nbsp;<?php echo $result_product_list['mobile_number'];?></td>
        </tr>
        
        <tr>
            <th scope="col">Username</th>
            <td><span id="usernamebox"><?php echo $result_product_list['username'];?></span></td>
        </tr>
        
        <tr>
            <th scope="col">Password</th>
            <td>
                <?php 
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
                
                if ($passwordError) {
                    echo '<span class="text-danger">Decryption Error</span>';
                } else {
                    ?>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <span id="pwd-hidden" style="cursor:pointer;font-size:18px;display:flex;align-items:center;gap:5px;" 
                              onclick="document.getElementById('pwd-hidden').style.display='none'; 
                                       document.getElementById('pwd-shown').style.display='flex';"
                              title="Click to show password">
                            <i class="material-icons" style="vertical-align:middle;">visibility_off</i> 
                            <strong>••••••••••</strong>
                        </span>
                        
                        <span id="pwd-shown" style="display:none;cursor:pointer;font-size:18px;align-items:center;gap:5px;" 
                              onclick="document.getElementById('pwd-shown').style.display='none'; 
                                       document.getElementById('pwd-hidden').style.display='flex';"
                              title="Click to hide password">
                            <i class="material-icons" style="vertical-align:middle;">visibility</i> 
                            <strong style="background:#ffeb3b;padding:5px 10px;border-radius:5px;letter-spacing:1px;">
                                <?php echo htmlspecialchars($decryptedPassword); ?>
                            </strong>
                        </span>
                        
                        <button onclick="copyPasswordDetails('<?php echo htmlspecialchars($decryptedPassword, ENT_QUOTES); ?>')" 
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
        </tr>
        
        <tr>
            <th scope="col">GST Number</th>
            <td><?php echo $result_product_list['gstin'];?></td>
        </tr>
        
        <?php if($result_product_list['account_status']=="pending"){?>
        <tr>
            <td colspan="2">
                <form method="post" enctype="mutipart/form-data" action="active-users" onSubmit="return confirm('Please make a confirm!');">
                    <input type="hidden" name="usertype" value="super_stockiest">
                    <input type="hidden" name="userrowid" value="<?=$get_id;?>">
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" type="submit">Click to Activate</button>
                    </div>
                </form>
                <br/><br/>
            </td>
        </tr>
        <?php }?>
        
    </table>

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
    function copyPasswordDetails(password) {
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