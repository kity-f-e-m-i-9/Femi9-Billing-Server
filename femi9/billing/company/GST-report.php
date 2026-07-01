<?php include("checksession.php");
include("config.php");
$title = "GST Report";
date_default_timezone_set("Asia/Kolkata");
$current_date = date("Y-m-d");
error_reporting(0);

if ($_REQUEST['fromdate'] != NULL) {
    $get_from_date = mysqli_real_escape_string($db_conn, $_REQUEST['fromdate']);
    $get_to_date   = mysqli_real_escape_string($db_conn, $_REQUEST['todate']);
} else {
    $get_from_date = date("Y-m-d");
    $get_to_date   = date("Y-m-d");
}

// ✅ Single JOIN query — was 2 queries per invoice row (sum total + sum gst)
// Also removes the dead $reqid stock_request_items query that was doing nothing
$select_picnode_list = "
    SELECT
        ui.inv_number,
        ui.date,
        ui.gst_type,
        SUM(ii.total)         AS total_bill_amount,
        SUM(ii.gstamount_total) AS total_gst_amount
    FROM user_invoice ui
    LEFT JOIN user_invoice_items ii ON ii.inv_id = ui.inv_id
    WHERE ui.date BETWEEN '$get_from_date' AND '$get_to_date'
    GROUP BY ui.inv_id, ui.inv_number, ui.date, ui.gst_type
    ORDER BY ui.id ASC
";
$fetch_picnode_list = mysqli_query($db_conn, $select_picnode_list);

$rows = [];
while ($row = mysqli_fetch_assoc($fetch_picnode_list)) {
    $gst_amount   = $row['total_gst_amount'];
    $bill_amount  = $row['total_bill_amount'];
    $row['taxable_amount'] = $bill_amount - $gst_amount;
    $row['cgst']  = $gst_amount / 2;
    $row['sgst']  = $gst_amount / 2;
    $row['igst']  = $gst_amount;
    $rows[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title); ?> : <?php echo htmlspecialchars($business_name); ?></title>
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
                                            <tr><td><?php echo htmlspecialchars($title); ?></td></tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">

                                        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
                                            <div class="example-container">
                                                <div class="example-content">
                                                    <label class="form-label">From Date</label>
                                                    <input type="date" name="fromdate" value="<?= $get_from_date ?>" required class="form-control">
                                                    <label class="form-label">To Date</label>
                                                    <input type="date" name="todate" required value="<?= $get_to_date ?>" class="form-control">
                                                    <br/>
                                                    <button type="submit" name="search-network" class="btn btn-primary">
                                                        <i class="material-icons">search</i>Search
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                        <br/>

                                        <table width="100%" class="ReportTablevl">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Date</th>
                                                    <th>Invoice Number</th>
                                                    <th style="text-align:right;">Total Amount</th>
                                                    <th style="text-align:right;">CGST</th>
                                                    <th style="text-align:right;">SGST</th>
                                                    <th style="text-align:right;">IGST</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($rows)): ?>
                                                <tr>
                                                    <td colspan="7" style="text-align:center; padding:20px;">No records found.</td>
                                                </tr>
                                                <?php else: ?>
                                                <?php $i = 0; foreach ($rows as $row): $i++; ?>
                                                <tr>
                                                    <td><?= $i ?></td>
                                                    <td><?= date("d/m/Y", strtotime($row['date'])) ?></td>
                                                    <td><?= htmlspecialchars($row['inv_number']) ?></td>
                                                    <td style="text-align:right;"><?= number_format($row['taxable_amount'], 2, '.', '') ?></td>
                                                    <td style="text-align:right;">
                                                        <?php if ($row['gst_type'] == "inner") echo number_format($row['cgst'], 2, '.', ''); ?>
                                                    </td>
                                                    <td style="text-align:right;">
                                                        <?php if ($row['gst_type'] == "inner") echo number_format($row['sgst'], 2, '.', ''); ?>
                                                    </td>
                                                    <td style="text-align:right;">
                                                        <?php if ($row['gst_type'] != "inner") echo number_format($row['igst'], 2, '.', ''); ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
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
