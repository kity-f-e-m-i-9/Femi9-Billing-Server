<?php 
include("checksession.php");
include("config.php"); 
error_reporting(0);
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
    <title>GSTR3B : <?php echo $business_name;?></title>

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
	
	<style type="text/css">
	#dashanch{color:#000 !important;}
	#dashanch:hover{color:#1a06a6 !important;}
	#reportdash th{font-size:13px;font-weight:600;}
	#reportdash td{font-weight:700;font-size:14px;}
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
                    <div class="container">
                        <div class="row">
                            <div class="col">
                                <div class="page-description" style="margin-left:-25px;">
                                   <table style="width:100%;">
								<tr>
								<td><h1>GST Reports > GSTR3B</h1></td>
								</tr>
								</table>
                                </div>
                            </div>
                        </div>
						
						<!--------------------------------------------------------------------->
						<!--------------------------------------------------------------------->
						<div class="row">
                            <div class="col-xl-3" style="height:100px;">
                                <div class="card widget widget-stats">
                                    <div class="card-body" style="height:200px;">
                                        <div class="widget-stats-container d-flex">
						<a href="GSTR3B_D31" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <p>3.1 Details of Outward Supplies and inward supplies liable to reverse charge (other than those covered by Table 3.1.1</p>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body" style="height:200px;">
                                        <div class="widget-stats-container d-flex">
										<a href="GSTR3B_V5" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <p>5. Values of exempt, nil-rated and non-GST inward supplies</p>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							
							
							
							
						
						
			
                        </div>
						
						
							<!--------------------end***--------------------------------------->
						




						
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