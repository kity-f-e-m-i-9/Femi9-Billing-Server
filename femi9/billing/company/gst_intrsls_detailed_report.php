<?php 
include("checksession.php");
include("config.php"); 
error_reporting(0);

$from_date     = mysqli_real_escape_string($db_conn, $_REQUEST['frd']); 
$to_date       = mysqli_real_escape_string($db_conn, $_REQUEST['tod']);
$get_godown_id = mysqli_real_escape_string($db_conn, $_REQUEST['gid']);
$gst_type      = mysqli_real_escape_string($db_conn, $_REQUEST['data1']); 
$buyer_gsttype = mysqli_real_escape_string($db_conn, $_REQUEST['data2']); 

$select_Godown_details = "SELECT * FROM company_godown WHERE id='$get_godown_id'";
$fetch_Godown_details  = mysqli_query($db_conn, $select_Godown_details);
$result_Godown_details = mysqli_fetch_array($fetch_Godown_details);

if ($gst_type == "inner" && $buyer_gsttype == "register")
    $lable_header = "Intra-state (Registered person)";
elseif ($gst_type == "inner" && $buyer_gsttype == "unregister")
    $lable_header = "Intra-state (Unregistered person)";
elseif ($gst_type == "outer" && $buyer_gsttype == "register")
    $lable_header = "Inter-state (Registered person)";
else
    $lable_header = "Inter-state (Unregistered person)";

// ✅ Single JOIN query — was 4 queries per row (distinct tempids × 4)
// Joins internal_transfer + internal_transfer_invoice + company_godown (send_to) in one shot
$select_Report = "
    SELECT
        it.tempid,
        MAX(it.date)          AS transfer_date,
        MAX(it.send_to)       AS send_to,
        MAX(iti.inv_number)   AS inv_number,
        MAX(cg.gname)         AS gname,
        MAX(cg.gstin)         AS company_gstin,
        SUM(it.total)         AS total_sls_amount
    FROM internal_transfer it
    LEFT JOIN internal_transfer_invoice iti ON iti.tempid = it.tempid
    LEFT JOIN company_godown cg ON cg.id = (
        SELECT send_to FROM internal_transfer WHERE tempid = it.tempid LIMIT 1
    )
    WHERE it.date BETWEEN '$from_date' AND '$to_date'
      AND it.send_from = '$get_godown_id'
    GROUP BY it.tempid
    ORDER BY MAX(it.date) ASC
";
$fetch_Report = mysqli_query($db_conn, $select_Report);

$rows = [];
$overall_total = 0;
while ($row = mysqli_fetch_assoc($fetch_Report)) {
    $overall_total += $row['total_sls_amount'];
    $rows[] = $row;
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
    <title>GSTR1 : <?php echo htmlspecialchars($business_name); ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style type="text/css">
    #gsttablevl tr th { border: 1px solid #000; padding: 5px; }
    #gsttablevl tr td { border: 1px solid #000; padding: 5px; }
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
                    <div class="container">
                        <div class="row">
                            <div class="col">
                                <div class="page-description" style="margin-left:-25px;">
                                    <table style="width:100%;">
                                        <tr>
                                            <td>
                                                <h1>GSTR1 &gt; Detailed Internal Transfer Report</h1>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <table style="width:100%;" id="gsttablevl">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Company Name</th>
                                        <th>GSTIN</th>
                                        <th>Invoice Number</th>
                                        <th>Invoice Date</th>
                                        <th>Total Sales Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; padding:20px;">No records found.</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php $sn = 0; foreach ($rows as $row): $sn++; ?>
                                    <tr>
                                        <td><?= $sn ?></td>
                                        <td><?= htmlspecialchars($row['gname']) ?></td>
                                        <td><?= htmlspecialchars($row['company_gstin']) ?></td>
                                        <td><?= htmlspecialchars($row['inv_number']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['transfer_date'])) ?></td>
                                        <td align="right"><b><?= number_format($row['total_sls_amount'], 2) ?></b></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" align="right"><b>Grand Total</b></td>
                                        <td align="right"><b><?= number_format($overall_total, 2) ?></b></td>
                                    </tr>
                                </tfoot>
                            </table>
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
    <script src="../../assets/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>
</body>
</html>
