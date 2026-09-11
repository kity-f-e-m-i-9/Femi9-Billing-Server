<?php
include("checksession.php"); require_once("include/GodownAccess.php");
include("config.php");
error_reporting(0);

$from_date     = mysqli_real_escape_string($db_conn, $_REQUEST['frd']);
$to_date       = mysqli_real_escape_string($db_conn, $_REQUEST['tod']);
$get_godown_id = mysqli_real_escape_string($db_conn, $_REQUEST['gid']);
$gst_type      = mysqli_real_escape_string($db_conn, $_REQUEST['data1']);
$buyer_gsttype = mysqli_real_escape_string($db_conn, $_REQUEST['data2']);

if (!empty($get_godown_id) && !is_godown_allowed($db_conn, (int)$get_godown_id)) {
    header("Location: overall-stock?unauthorized"); exit;
}

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

// ✅ Single JOIN query — was N+1 lookups per return (5 queries per row).
// ot_sales_return has no rate/GST-amount column of its own — osr.total is
// treated as the taxable value directly (no GST portion to strip out), same
// convention used everywhere else on GSTR1 for this table (see GSTR1.php's
// HSN and B2B/B2C tables) — the product's own rate (joined via prid) is used
// only to display GST %, not to recompute the value.
$select_Report = "
    SELECT
        osr.tempid,
        MAX(osr.return_date)     AS return_date,
        MAX(s.customer_name)     AS customer_name,
        MAX(s.customer_mobile)   AS customer_mobile,
        MAX(s.gst_number)        AS gst_number,
        MAX(s.date)              AS invoice_date,
        MAX(i.inv_number)        AS inv_number,
        MAX(p.gst)               AS gst_percentage,
        SUM(osr.total)           AS total_sls_amount
    FROM ot_sales_return osr
    JOIN products p ON p.id = osr.prid
    LEFT JOIN ot_sales s ON s.tempid = osr.tempid
    LEFT JOIN ot_sales_invoice i ON i.tempid = osr.tempid
    WHERE osr.buyer_gsttype = '$buyer_gsttype'
      AND osr.return_date BETWEEN '$from_date' AND '$to_date'
      AND osr.godownid = '$get_godown_id'
      AND osr.gst_type = '$gst_type'
    GROUP BY osr.tempid
    ORDER BY MAX(osr.return_date) ASC
";
$fetch_Report = mysqli_query($db_conn, $select_Report);

$overall_total = 0;
$rows = [];
while ($row = mysqli_fetch_assoc($fetch_Report)) {
    $overall_total += $row['total_sls_amount'];
    $rows[] = $row;
}

// ✅ Excel (CSV) export — same rows/columns as the on-screen table.
if (isset($_REQUEST['export']) && $_REQUEST['export'] == 'csv') {
    ob_start();
    $csv_rows = [];
    $header = ['#', 'Customer Name', 'Customer Mobile'];
    if ($buyer_gsttype == "register") $header[] = 'GSTIN';
    $header = array_merge($header, ['Invoice Number', 'Invoice Date', 'Return Date', 'GST %', 'Taxable Value', 'GST Amount', 'Total Return Value']);
    $csv_rows[] = $header;

    $sn = 0;
    foreach ($rows as $row) {
        $sn++;
        $line = [$sn, $row['customer_name'], $row['customer_mobile'] ?: '---'];
        if ($buyer_gsttype == "register") $line[] = $row['gst_number'];
        $line = array_merge($line, [
            $row['inv_number'],
            date("d/m/Y", strtotime($row['invoice_date'])),
            date("d/m/Y", strtotime($row['return_date'])),
            ($row['gst_percentage'] > 0 ? $row['gst_percentage'].'%' : '0%'),
            number_format($row['total_sls_amount'], 2, '.', ''),
            '0.00',
            number_format($row['total_sls_amount'], 2, '.', ''),
        ]);
        $csv_rows[] = $line;
    }
    $total_row = ['', '', ''];
    if ($buyer_gsttype == "register") $total_row[] = '';
    $total_row = array_merge($total_row, ['', '', '', 'Grand Total',
        number_format($overall_total, 2, '.', ''),
        '0.00',
        number_format($overall_total, 2, '.', ''),
    ]);
    $csv_rows[] = $total_row;

    $csv_content = '';
    foreach ($csv_rows as $csv_row) {
        $csv_content .= implode(',', array_map(function ($v) {
            return '"' . str_replace('"', '""', $v) . '"';
        }, $csv_row)) . "\n";
    }

    ob_end_clean();
    header("Content-type: text/csv");
    header("Content-Disposition: attachment; filename=GST_Credit_OT_Sales_Detailed_Report.csv");
    echo $csv_content;
    exit;
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
    <title>GSTR1 : <?php echo htmlspecialchars($business_name); ?></title>

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
                                                <h1>GSTR1 &gt; Detailed OT Sales Report &gt; <span style="color:red;">Credit</span> Note</h1>
                                                <h5><?= htmlspecialchars($lable_header) ?></h5>
                                            </td>
                                            <td align="right" valign="top">
                                                <a href="?data1=<?= urlencode($gst_type) ?>&amp;data2=<?= urlencode($buyer_gsttype) ?>&amp;frd=<?= urlencode($from_date) ?>&amp;tod=<?= urlencode($to_date) ?>&amp;gid=<?= urlencode($get_godown_id) ?>&amp;export=csv" title="Export to Excel"><img src="../../assets/images/excel-3-32.png"></a>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!--------------------------------------------------------------------->
                        <div class="row">

                            <table style="width:100%;" id="gsttablevl">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Customer Name</th>
                                        <th>Customer Mobile</th>
                                        <?php if ($buyer_gsttype == "register"): ?>
                                        <th>GSTIN</th>
                                        <?php endif; ?>
                                        <th>Invoice Number</th>
                                        <th>Invoice Date</th>
                                        <th>Return Date</th>
                                        <th>GST %</th>
                                        <th>Taxable Value</th>
                                        <th>GST Amount</th>
                                        <th>Total Return Value(Rs.)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                    <tr>
                                        <td colspan="<?= $buyer_gsttype == 'register' ? 10 : 9 ?>" style="text-align:center; padding:20px;">
                                            No records found for the selected criteria.
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php $sn = 0; foreach ($rows as $row): $sn++; ?>
                                    <tr>
                                        <td><?= $sn ?></td>
                                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                        <td><?= $row['customer_mobile'] ? htmlspecialchars($row['customer_mobile']) : '---' ?></td>
                                        <?php if ($buyer_gsttype == "register"): ?>
                                        <td><?= htmlspecialchars($row['gst_number']) ?></td>
                                        <?php endif; ?>
                                        <td><?= htmlspecialchars($row['inv_number']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['invoice_date'])) ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['return_date'])) ?></td>
                                        <td align="center"><?= $row['gst_percentage'] > 0 ? $row['gst_percentage'].'%' : '0%' ?></td>
                                        <td align="right"><?= inr_format($row['total_sls_amount'], 2) ?></td>
                                        <td align="right"><?= inr_format(0, 2) ?></td>
                                        <td align="right"><b><?= inr_format($row['total_sls_amount'], 2) ?></b></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <?php if ($buyer_gsttype == "register"): ?><td></td><?php endif; ?>
                                        <td></td>
                                        <td></td>
                                        <td><b>Grand Total</b></td>
                                        <td></td>
                                        <td align="right"><b><?= inr_format($overall_total, 2) ?></b></td>
                                        <td align="right"><b><?= inr_format(0, 2) ?></b></td>
                                        <td align="right"><b><?= inr_format($overall_total, 2) ?></b></td>
                                    </tr>
                                </tfoot>
                            </table>

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
