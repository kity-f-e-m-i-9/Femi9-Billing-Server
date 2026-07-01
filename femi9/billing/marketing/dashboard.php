<?php 
include("checksession.php");
include("config.php"); 

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// ========================================
// CHECK IF USER MUST CHANGE PASSWORD
// ========================================
$userMobile = $result_LoGuserDtails['ms_mobile'];
$userType = 'marketing'; // Change based on user type

// Check if user has a pending password reset
$checkResetStmt = mysqli_prepare($db_conn, 
    "SELECT id, reset_at FROM forgotpassword 
     WHERE usertype = ? AND mobilenumber = ? AND must_change_password = 1 
     ORDER BY reset_at DESC LIMIT 1"
);
mysqli_stmt_bind_param($checkResetStmt, "ss", $userType, $userMobile);
mysqli_stmt_execute($checkResetStmt);
$resetResult = mysqli_stmt_get_result($checkResetStmt);
$resetData = mysqli_fetch_assoc($resetResult);
mysqli_stmt_close($checkResetStmt);

// If user has pending password reset, force them to change password
if ($resetData) {
    echo "<script>
        alert('For security reasons, you must change your password before continuing.');
        window.location='change-password.php?forced=1';
    </script>";
    exit;
}
// ========================================


//INSERT ATTENDANCE
date_default_timezone_set("Asia/Kolkata");
$ATTND_date=date("Y-m-d");
$ATTND_time=date("H:i:s");

$select_AttendanceCount="select * from ms_attendance where ms_id='$markeingSTFID' and date='$ATTND_date'";
$fetch_AttendanceCount=mysqli_query($db_conn,$select_AttendanceCount);
$result_AttendanceCount=mysqli_num_rows($fetch_AttendanceCount);
if($result_AttendanceCount==0)
	{
		$insertATTND="insert into ms_attendance (ms_id,date,time) values ('$markeingSTFID','$ATTND_date','$ATTND_time')";
		mysqli_query($db_conn,$insertATTND);
		
	}
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
    <title>Dashboard : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">

    
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
                    <div class="container">
                        
						
						<div class="row">
                            <div class="col-xl-12">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
							<h1>Dashboard</h1>	
						<h2>Welcome to Femi9 - Happy day Everyday</h2>



<!----						
						<h2>Today Order's</h2>
						<table class="table" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Plan Amount</th>
													<th>Valid Months</th>
													<th>Coupon Number</th>
													<th>Copy</th>
													<th>Available</th>
													<th>Coupon Date</th>
                                                </tr>
                                            </thead>
											
											<tbody>
                                            
                                                <tr>
                                                    <td>1</td>
                                                    <td>Nil</td>
													  <td>Nil</td>
													  <td>Nil</td>
													    <td>Nil</td>
														  <td>Nil</td>
														    <td>Nil</td>													
                                                </tr>
												 <tr>
                                                    <td>1</td>
                                                    <td>Nil</td>
													  <td>Nil</td>
													  <td>Nil</td>
													    <td>Nil</td>
														  <td>Nil</td>
														    <td>Nil</td>													
                                                </tr>
												 <tr>
                                                    <td>1</td>
                                                    <td>Nil</td>
													  <td>Nil</td>
													  <td>Nil</td>
													    <td>Nil</td>
														  <td>Nil</td>
														    <td>Nil</td>													
                                                </tr>
                                           
										
										 </tbody>
                                        </table>
										
										
										
										<br/>
										<h2>Yesterday Order's</h2>
										
										<br/>
										<h2>This Month Order's</h2>--->
										
										
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
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>
</body>
</html>