<?php
include("checksession.php"); require_once("include/GodownAccess.php");
include("config.php");
error_reporting(0);

$from_date     = mysqli_real_escape_string($db_conn, $_REQUEST['frd']);
$to_date       = mysqli_real_escape_string($db_conn, $_REQUEST['tod']);
$get_godown_id = mysqli_real_escape_string($db_conn, $_REQUEST['gid']);

if (!empty($get_godown_id) && !is_godown_allowed($db_conn, (int)$get_godown_id)) {
    header("Location: overall-stock?unauthorized"); exit;
}

$select_Godown_details = "SELECT * FROM company_godown WHERE id='$get_godown_id'";
$fetch_Godown_details  = mysqli_query($db_conn, $select_Godown_details);
$result_Godown_details = mysqli_fetch_array($fetch_Godown_details);

require_once "include/TpGstHelper.php";
require_once "include/B2bBuyerHelper.php";
$tp_sls_lines = tp_sales_gst_lines($db_conn, $from_date, $to_date, "tpi.source_godown_id = '$get_godown_id'");
$b2b_buyers = compute_b2b_buyers($db_conn, $Login_user_TYPEvl, $get_godown_id, $from_date, $to_date, $tp_sls_lines);

$grand_taxable = 0; $grand_cgst = 0; $grand_sgst = 0; $grand_igst = 0;
foreach ($b2b_buyers as $b) {
    $grand_taxable += $b['taxable']; $grand_cgst += $b['cgst']; $grand_sgst += $b['sgst']; $grand_igst += $b['igst'];
}
$grand_gst = $grand_cgst + $grand_sgst + $grand_igst;

// ✅ Excel (CSV) export — same rows/columns as the on-screen table, same
// pattern as gst_sls_detailed_report.php's export.
if (isset($_REQUEST['export']) && $_REQUEST['export'] == 'csv') {
    ob_start();
    $csv_rows = [];
    $csv_rows[] = ['#', 'Buyer', 'Type', 'GSTIN', 'Invoices', 'Taxable Value', 'CGST', 'SGST', 'IGST', 'Total'];
    $sn = 0;
    foreach ($b2b_buyers as $b) {
        $sn++;
        $csv_rows[] = [
            $sn, $b['name'] ?: '—', $b['type'], $b['gstin'] ?: '—', count($b['invoices']),
            number_format($b['taxable'], 2, '.', ''),
            number_format($b['cgst'], 2, '.', ''),
            number_format($b['sgst'], 2, '.', ''),
            number_format($b['igst'], 2, '.', ''),
            number_format($b['taxable'] + $b['cgst'] + $b['sgst'] + $b['igst'], 2, '.', ''),
        ];
    }
    $csv_rows[] = ['', '', '', '', 'Grand Total',
        number_format($grand_taxable, 2, '.', ''),
        number_format($grand_cgst, 2, '.', ''),
        number_format($grand_sgst, 2, '.', ''),
        number_format($grand_igst, 2, '.', ''),
        number_format($grand_taxable + $grand_gst, 2, '.', ''),
    ];

    $csv_content = '';
    foreach ($csv_rows as $csv_row) {
        $csv_content .= implode(',', array_map(function ($v) {
            return '"' . str_replace('"', '""', $v) . '"';
        }, $csv_row)) . "\n";
    }

    ob_end_clean();
    header("Content-type: text/csv");
    header("Content-Disposition: attachment; filename=GST_B2B_Buyer_Report.csv");
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
                                                <h1>GSTR1 &gt; Table 4 &mdash; B2B Buyer-Wise Detail</h1>
                                                <h5><?= htmlspecialchars($result_Godown_details['gname'] ?? '') ?> &middot; <?= date("d/m/Y", strtotime($from_date)) ?> to <?= date("d/m/Y", strtotime($to_date)) ?></h5>
                                            </td>
                                            <td align="right" valign="top">
                                                <a href="?frd=<?= urlencode($from_date) ?>&amp;tod=<?= urlencode($to_date) ?>&amp;gid=<?= urlencode($get_godown_id) ?>&amp;export=csv" title="Export to Excel"><img src="../../assets/images/excel-3-32.png"></a>
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
                                        <th>Buyer</th>
                                        <th>Type</th>
                                        <th>GSTIN</th>
                                        <th>Invoices</th>
                                        <th>Taxable Value</th>
                                        <th>CGST</th>
                                        <th>SGST</th>
                                        <th>IGST</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($b2b_buyers)) { ?>
                                    <tr><td colspan="10" style="text-align:center; padding:20px;">No B2B buyers in this period.</td></tr>
                                    <?php } else { $sn = 0; foreach ($b2b_buyers as $b): $sn++; ?>
                                    <tr>
                                        <td><?= $sn ?></td>
                                        <td><?= htmlspecialchars($b['name'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($b['type']) ?></td>
                                        <td><?= htmlspecialchars($b['gstin'] ?: '—') ?></td>
                                        <td><?= count($b['invoices']) ?></td>
                                        <td align="right"><?= inr_format($b['taxable'], 2) ?></td>
                                        <td align="right"><?= inr_format($b['cgst'], 2) ?></td>
                                        <td align="right"><?= inr_format($b['sgst'], 2) ?></td>
                                        <td align="right"><?= inr_format($b['igst'], 2) ?></td>
                                        <td align="right"><b><?= inr_format($b['taxable'] + $b['cgst'] + $b['sgst'] + $b['igst'], 2) ?></b></td>
                                    </tr>
                                    <?php endforeach; } ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" align="right"><b>Grand Total</b></td>
                                        <td align="right"><b><?= inr_format($grand_taxable, 2) ?></b></td>
                                        <td align="right"><b><?= inr_format($grand_cgst, 2) ?></b></td>
                                        <td align="right"><b><?= inr_format($grand_sgst, 2) ?></b></td>
                                        <td align="right"><b><?= inr_format($grand_igst, 2) ?></b></td>
                                        <td align="right"><b><?= inr_format($grand_taxable + $grand_gst, 2) ?></b></td>
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
