<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('report');
$title="Report - B2B (Territory Partner)";

// Territory partners don't have a state_id foreign key like every other
// channel (shop/distributor/...) — branch_state is free-typed text ("Tamil
// Nadu", "Tamilnadu", "Tamill nadu", even a pincode typed by mistake). So
// this dropdown lists whatever values actually exist in the data instead of
// the clean `state` table, and Report-TP-Details.php matches on that exact
// text — independent of Report-First-Page.php / Report-Details.php.
$state_list = [];
$select_stateList = "SELECT DISTINCT branch_state FROM territory_partners WHERE branch_state IS NOT NULL AND branch_state <> '' ORDER BY branch_state ASC";
$fetch_stateList = mysqli_query($db_conn, $select_stateList);
while ($row = mysqli_fetch_assoc($fetch_stateList)) { $state_list[] = $row['branch_state']; }
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

    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
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
										</tr>
										</table>
										</h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">

                                    <div class="card-body">

<form action="Report-TP-Details.php" method="post" enctype="multipart/form-data">

                                        <div class="example-container">
                                            <div class="example-content">

												<label for="branch_state_select" class="form-label">Select State (Territory Partner) <span style="color:red;">*</span></label>
                               <select required name="branch_state" id="branch_state_select" class="form-control">
								   <option value="" hidden>-- Select State --</option>
								   <?php foreach ($state_list as $bs): ?>
							   <option value="<?php echo htmlspecialchars($bs);?>"><?php echo htmlspecialchars($bs);?></option>
								   <?php endforeach;?>
								   </select>
								   <br/>

								<button type="submit" name="search-state" class="btn btn-primary">
								    <i class="material-icons">search</i> View Report
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
