<?php 
include("checksession.php");
include("config.php");

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$title = "Add Category (Super Stockist)";
$manage_url = "cat-view-ss";
$manage_title = "Manage Category (Super Stockist)";
$message_title = "Category (Super Stockist)";

// Fetch districts that don't have categories yet using prepared statement
$query = "SELECT d.id, d.dist_name, d.state_id, s.st_name 
          FROM district d 
          LEFT JOIN state s ON d.state_id = s.id
          LEFT JOIN super_stockiest_category ssc ON d.dist_name = ssc.name 
          WHERE ssc.id IS NULL 
          ORDER BY s.st_name, d.dist_name";

$stmt = $db_conn->prepare($query);
$stmt->execute();
$available_districts = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars($business_name, ENT_QUOTES, 'UTF-8'); ?></title>

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
            <?php include("logo.php"); ?>
            <?php include("femi_menu.php"); ?>
        </div>
        <div class="app-container">
           
            <?php include("app-header.php"); ?>
			
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble">
                                            <tr>
                                                <td><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><a href="<?php echo htmlspecialchars($manage_url, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($manage_title, ENT_QUOTES, 'UTF-8'); ?>">&#9776;</a></td>
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
									
                                        <?php if (isset($_GET['alreadyexists'])) { ?>
                                            <div class="alert alert-danger"><?php echo htmlspecialchars($message_title, ENT_QUOTES, 'UTF-8'); ?> already exists!</div>
                                        <?php } ?>
                                        
                                        <?php if (isset($_GET['district_not_found'])) { ?>
                                            <div class="alert alert-danger">Selected district not found!</div>
                                        <?php } ?>
                                        
                                        <?php if (isset($_GET['csrf_error'])) { ?>
                                            <div class="alert alert-danger">Security token validation failed. Please try again.</div>
                                        <?php } ?>
                                        
                                        <?php if (isset($_GET['invalidparameters'])) { ?>
                                            <div class="alert alert-danger">Invalid parameters provided!</div>
                                        <?php } ?>
    
                                        <?php include("validate-scripts.php"); ?>
	
                                        <form action="product-action" method="post" onSubmit="return confirm('Please make a confirm!')">
                                            <!-- CSRF Token -->
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
			
                                            <div class="example-container">
                                                <div class="example-content">
											
                                                    <label for="district_id" class="form-label">Select District (Category Name)<span class="text-danger">*</span></label>
                                                    <select name="district_id" id="district_id" class="form-control" required>
                                                        <option value="">-- Select District --</option>
                                                        <?php 
                                                        if ($available_districts->num_rows > 0) {
                                                            $current_state = '';
                                                            while ($row = $available_districts->fetch_assoc()) {
                                                                // Add state name as optgroup for better organization
                                                                if ($current_state != $row['st_name']) {
                                                                    if ($current_state != '') {
                                                                        echo '</optgroup>';
                                                                    }
                                                                    $current_state = $row['st_name'];
                                                                    echo '<optgroup label="' . htmlspecialchars($row['st_name'], ENT_QUOTES, 'UTF-8') . '">';
                                                                }
                                                                echo '<option value="' . htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') . '">' 
                                                                     . htmlspecialchars($row['dist_name'], ENT_QUOTES, 'UTF-8') . '</option>';
                                                            }
                                                            if ($current_state != '') {
                                                                echo '</optgroup>';
                                                            }
                                                        } else {
                                                            echo '<option value="" disabled>No districts available (all districts have categories)</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                    <small class="form-text text-muted">The district name will be used as the category name. Districts with existing categories are hidden.</small>
                                                    <br/>
												
                                                    <label for="target_amount" class="form-label">Target Sales Value (Rs.)<span class="text-danger">*</span></label>
                                                    <input type="number" 
                                                           min="0" 
                                                           onkeypress="restrictnumber(event)" 
                                                           class="form-control" 
                                                           name="target_amount" 
                                                           id="target_amount"
                                                           required 
                                                           placeholder="Target Amount (Rs.)"/>
                                                    <br/>
												
                                                    <label for="ref_commission_percentage" class="form-label">Referral (%)<span class="text-danger">*</span></label>
                                                    <input type="number" 
                                                           onkeypress="restrictnumber(event)" 
                                                           class="form-control" 
                                                           name="ref_commission_percentage" 
                                                           id="ref_commission_percentage"
                                                           min="0" 
                                                           max="100" 
                                                           step="0.01"
                                                           required 
                                                           placeholder="Referral (%)"/>
                                                    <br/>
												
                                                    <label for="cash_back_percentage" class="form-label">Cashback (%)<span class="text-danger">*</span></label>
                                                    <input type="number" 
                                                           onkeypress="restrictnumber(event)" 
                                                           class="form-control" 
                                                           name="cash_back_percentage" 
                                                           id="cash_back_percentage"
                                                           min="0" 
                                                           max="100" 
                                                           step="0.01"
                                                           required 
                                                           placeholder="Cashback (%)"/>
                                                    <br/>
												
                                                    <button type="submit" name="InsertSuperStockistCategory" class="btn btn-primary">
                                                        <i class="material-icons">add</i>Add
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