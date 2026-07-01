<?php 
include("checksession.php");
include("config.php");

$title = "Stock Details Report";

// Clear session filters if requested
if(isset($_GET['clear_all'])) {
    unset($_SESSION['stock_state_id']);
    unset($_SESSION['stock_district_id']);
    unset($_SESSION['stock_taluk_id']);
    unset($_SESSION['stock_user_type']);
    unset($_SESSION['stock_user_id']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

mysqli_set_charset($db_conn, 'utf8mb4');
mysqli_query($db_conn, "SET collation_connection = 'utf8mb4_general_ci'");
mysqli_query($db_conn, "SET collation_server = 'utf8mb4_general_ci'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
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
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    
    <style>
        .card {
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border-radius: 12px;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 6px;
            border-color: #ced4da;
            padding: 0.6rem 0.75rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.25);
        }
        
        .btn {
            border-radius: 6px;
            padding: 0.6rem 1.5rem;
            font-weight: 500;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #0a58ca 0%, #084298 100%);
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
                        
                        <!-- Header -->
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble">
                                            <tr>
                                                <td><?php echo $title;?></td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filter Form -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    
                                    <div class="card-body">
									
                                        <form action="Stock-Details.php" method="post" enctype="multipart/form-data">

                                        <div class="example-container">
                                            <div class="example-content">
											
											<label for="state_select" class="form-label">Select State <span style="color:red;">*</span></label>
                               <select required name="state_id" id="state_select" class="form-control">
							   <option value="" hidden>-- Select State --</option>
							   <?php 
							   $select_stateList="SELECT * FROM `state` ORDER BY `st_name` ASC";
							   $fetch_stateList=mysqli_query($db_conn,$select_stateList);
							   while($result_stateList=mysqli_fetch_array($fetch_stateList))
							   {?>
						   <option value="<?php echo $result_stateList['id'];?>"><?php echo htmlspecialchars($result_stateList['st_name']);?></option>
							   <?php }?>
							   </select>
							   <br/>
												
							<button type="submit" name="view_stock" class="btn btn-primary">
							    <i class="material-icons">search</i> View Stock Report
							</button>
												
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