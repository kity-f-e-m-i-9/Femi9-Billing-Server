<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$advBalance = 0;
$title = "Import Shops (CSV)";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?> : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
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
                                <h1><table class="headertble"><tr>
                                    <td><?php echo $title; ?></td>
                                    <td><a href="shop-manage.php" title="Manage Shop">&#9776;</a></td>
                                </tr></table></h1>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">

                                    <div class="alert alert-info">
                                        <strong>How it works:</strong>
                                        <ol class="mb-2">
                                            <li>Download the template below and fill one row per shop, or reuse a shop-list CSV exported elsewhere (Name, State, District, Taluk, Pincode, Mobile Number, Email ID, Address) — extra columns like Category are optional.</li>
                                            <li><strong>Name</strong>, <strong>State</strong>, <strong>District</strong>, <strong>Mobile Number</strong> and <strong>Address</strong> are required. <strong>Taluk</strong>, <strong>Pincode</strong>, Email, GSTIN, Landline and Country Code are optional.</li>
                                            <li><strong>State</strong> and <strong>District</strong> names must exactly match the location names available to you in "Add Shop" (case-insensitive).</li>
                                            <li><strong>Category</strong> is optional — if provided it must match one of the existing shop categories (case-insensitive); if omitted, a default general category is used.</li>
                                            <li>Rows with missing required fields or unmatched locations/categories will be skipped and reported after upload — valid rows will still be imported.</li>
                                        </ol>
                                        <a href="shop-import-template.php" class="btn btn-sm btn-outline-primary">
                                            <i class="material-icons-outlined" style="vertical-align:middle;font-size:16px">download</i>
                                            Download CSV Template
                                        </a>
                                    </div>

                                    <form action="shop-import-action.php" method="post" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label class="form-label">Select CSV File*</label>
                                            <input type="file" name="csv_file" accept=".csv" required class="form-control">
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="material-icons-outlined" style="vertical-align:middle;font-size:18px">upload</i>
                                            Upload &amp; Import
                                        </button>
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
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
