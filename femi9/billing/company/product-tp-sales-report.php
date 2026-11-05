<?php
require_once("checksession.php");
require_once("config.php");

$defaultFrom = date('Y-m-01', strtotime('-2 months'));
$defaultTo   = date('Y-m-t');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product-wise TP Sales Report | <?php echo $safe_business_name ?? 'Femi9'; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png" />
    <style>
        .ptr-card { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); border:none; margin-bottom:20px; }
    </style>
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
                                    <table class="headertble" style="width:100%">
                                        <tr><td>Product-wise TP Sales Report</td></tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <div class="card ptr-card">
                        <div class="card-body">
                            <p style="color:#6b7280;font-size:13px;">
                                Type any part of a product name (e.g. "245mm") to see which Territory Partner
                                billed how much quantity, to which Shop and which Customer, in the date range
                                picked below. The downloaded Excel has two sheets — "By Shop" and "By Customer"
                                — each with a filter dropdown on every column header so you can search/narrow
                                results right inside Excel.
                            </p>
                            <form action="export-product-tp-sales-xlsx.php" method="get" target="_blank">
                                <div class="row g-2 align-items-end">
                                    <div class="col-auto">
                                        <label class="form-label mb-1">Product Name</label>
                                        <input type="text" name="product" class="form-control form-control-sm" placeholder="e.g. 245mm" required style="min-width:200px;">
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-label mb-1">From</label>
                                        <input type="date" name="frdate" class="form-control form-control-sm" value="<?php echo htmlspecialchars($defaultFrom); ?>">
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-label mb-1">To</label>
                                        <input type="date" name="todate" class="form-control form-control-sm" value="<?php echo htmlspecialchars($defaultTo); ?>">
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="material-icons" style="vertical-align:middle;font-size:16px">file_download</i> Download Excel
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
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
</body>
</html>
