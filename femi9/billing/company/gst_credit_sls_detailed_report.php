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

// Friendly labels for raw DB enum values shown in the Customer Type column
// (the page-level GST Type/state is already shown once in $lable_header
// above, so it isn't repeated as a per-row column here) — same convention
// as gst_sls_detailed_report.php.
$customer_type_labels = [
    'super_stockiest' => 'Super Stockist',
    'stockiest'        => 'Stockist',
    'distributor'      => 'Distributor',
    'shop'             => 'Shop',
    'customer'         => 'Customer',
];

// ✅ BLOCK 1: Non-customer returns (ss/st/dt/shop) — aggregated from items
// (not the parent return) so embedded GST on an 'inclusive'-priced product
// can be stripped via gstamount_total; see gst_details_credit.php.
$select_Report = "
    SELECT
        rsi.date        AS return_date,
        SUM(rsi.total - rsi.gstamount_total) AS total_sls_amount,
        SUM(rsi.gstamount_total) AS gst_amount,
        rsi.from_usertype AS customer_usertype,
        COALESCE(ss.name,   st.name,   dt.name,   sh.name)                        AS cust_name,
        COALESCE(ss.mobile_number, st.mobile_number, dt.mobile_number, sh.mobile_number) AS cust_mobile,
        COALESCE(ss.gstin,  st.gstin,  dt.gstin,  sh.gstin)                       AS cust_gstin,
        ui.inv_number,
        ui.date AS invoice_date
    FROM user_return_stock_items rsi
    LEFT JOIN super_stockiest ss ON rsi.from_usertype='super_stockiest' AND ss.temp_id = rsi.from_userid
    LEFT JOIN stockiest        st ON rsi.from_usertype='stockiest'       AND st.temp_id = rsi.from_userid
    LEFT JOIN distributor      dt ON rsi.from_usertype='distributor'     AND dt.temp_id = rsi.from_userid
    LEFT JOIN shop             sh ON rsi.from_usertype='shop'            AND sh.temp_id = rsi.from_userid
    LEFT JOIN user_invoice     ui ON ui.inv_id = rsi.invnumber
    WHERE rsi.to_usertype   = '$Login_user_TYPEvl'
      AND rsi.to_userid     = '$get_godown_id'
      AND rsi.buyer_gsttype = '$buyer_gsttype'
      AND rsi.gst_type      = '$gst_type'
      AND rsi.date BETWEEN '$from_date' AND '$to_date'
      AND rsi.total > 0
      AND rsi.from_usertype != 'customer'
    GROUP BY rsi.returnid, rsi.date, rsi.from_usertype,
             ss.name, st.name, dt.name, sh.name,
             ss.mobile_number, st.mobile_number, dt.mobile_number, sh.mobile_number,
             ss.gstin, st.gstin, dt.gstin, sh.gstin,
             ui.inv_number, ui.date
    ORDER BY rsi.date ASC
";
$fetch_Report = mysqli_query($db_conn, $select_Report);
$rows1 = [];
$total1 = 0; $total_gst1 = 0;
while ($row = mysqli_fetch_assoc($fetch_Report)) {
    $total1 += $row['total_sls_amount'];
    $total_gst1 += $row['gst_amount'];
    $rows1[] = $row;
}

// ✅ BLOCK 2: Customer returns — same taxable-value fix.
$select_Report2 = "
    SELECT
        rsi.date        AS return_date,
        SUM(rsi.total - rsi.gstamount_total) AS total_sls_amount,
        SUM(rsi.gstamount_total) AS gst_amount,
        'customer'     AS customer_usertype,
        c.name         AS cust_name,
        c.mobile       AS cust_mobile,
        c.gstin        AS cust_gstin,
        i.inv_number,
        i.date         AS invoice_date
    FROM user_return_stock_items rsi
    LEFT JOIN customers c ON c.id = rsi.from_userid
    LEFT JOIN invoice   i ON i.inv_id = rsi.invnumber
    WHERE rsi.to_usertype   = '$Login_user_TYPEvl'
      AND rsi.to_userid     = '$get_godown_id'
      AND rsi.buyer_gsttype = '$buyer_gsttype'
      AND rsi.gst_type      = '$gst_type'
      AND rsi.date BETWEEN '$from_date' AND '$to_date'
      AND rsi.total > 0
      AND rsi.from_usertype = 'customer'
    GROUP BY rsi.returnid, rsi.date, c.name, c.mobile, c.gstin, i.inv_number, i.date
    ORDER BY rsi.date ASC
";
$fetch_Report2 = mysqli_query($db_conn, $select_Report2);
$rows2 = [];
$total2 = 0; $total_gst2 = 0;
while ($row = mysqli_fetch_assoc($fetch_Report2)) {
    $total2 += $row['total_sls_amount'];
    $total_gst2 += $row['gst_amount'];
    $rows2[] = $row;
}

// ✅ BLOCK 3: TP sales returns (credit notes), godown-sourced only.
require_once "include/TpGstHelper.php";
$tp_lines = tp_credit_gst_lines($db_conn, $from_date, $to_date, "tpi.source_godown_id = '$get_godown_id'");
$want_intra = ($gst_type == 'inner');
$want_register = ($buyer_gsttype == 'register');
$tp_lines = array_filter($tp_lines, function ($l) use ($want_intra, $want_register) {
    return $l['is_intra'] === $want_intra && $l['is_registered'] === $want_register;
});
$rows3 = tp_group_lines($tp_lines, 'returnid');
$total3 = 0; $total_gst3 = 0;
foreach ($rows3 as $row) { $total3 += $row['taxable_value']; $total_gst3 += $row['gst_amount']; }

$overall_total = $total1 + $total2 + $total3;
$overall_gst   = $total_gst1 + $total_gst2 + $total_gst3;

// Effective GST% for a row = gst_amount / taxable_value * 100 — same helper
// convention as gst_sls_detailed_report.php.
$gst_slabs = [0, 5, 12, 18, 28];
function gst_percentage_label($taxable_value, $gst_amount, $gst_slabs) {
    if ((float)$taxable_value == 0.0) return $gst_amount == 0 ? '0%' : 'Mixed';
    $rate = round(($gst_amount / $taxable_value) * 100, 1);
    foreach ($gst_slabs as $slab) {
        if (abs($rate - $slab) <= 0.3) return $slab . '%';
    }
    return $rate . '% (Mixed)';
}

// ✅ Excel (CSV) export — same three row sets as the on-screen table, same
// columns, so the download always matches what's currently displayed.
if (isset($_REQUEST['export']) && $_REQUEST['export'] == 'csv') {
    ob_start();
    $sn = 0;
    $csv_rows = [];
    $csv_rows[] = ['#', 'Customer Type', 'Customer Name', 'Customer Mobile', 'GSTIN', 'Invoice Number', 'Invoice Date', 'Return Date', 'GST %', 'Taxable Value', 'GST Amount', 'Total Return Value'];

    foreach ($rows1 as $row) {
        $sn++;
        $csv_rows[] = [
            $sn,
            $customer_type_labels[$row['customer_usertype']] ?? ucfirst(str_replace('_', ' ', $row['customer_usertype'])),
            $row['cust_name'], $row['cust_mobile'], $row['cust_gstin'], $row['inv_number'],
            date("d/m/Y", strtotime($row['invoice_date'])),
            date("d/m/Y", strtotime($row['return_date'])),
            gst_percentage_label($row['total_sls_amount'], $row['gst_amount'], $gst_slabs),
            number_format($row['total_sls_amount'], 2, '.', ''),
            number_format($row['gst_amount'], 2, '.', ''),
            number_format($row['total_sls_amount'] + $row['gst_amount'], 2, '.', ''),
        ];
    }
    foreach ($rows2 as $row) {
        $sn++;
        $csv_rows[] = [
            $sn, 'Customer', $row['cust_name'], $row['cust_mobile'], $row['cust_gstin'], $row['inv_number'],
            date("d/m/Y", strtotime($row['invoice_date'])),
            date("d/m/Y", strtotime($row['return_date'])),
            gst_percentage_label($row['total_sls_amount'], $row['gst_amount'], $gst_slabs),
            number_format($row['total_sls_amount'], 2, '.', ''),
            number_format($row['gst_amount'], 2, '.', ''),
            number_format($row['total_sls_amount'] + $row['gst_amount'], 2, '.', ''),
        ];
    }
    foreach ($rows3 as $row) {
        $sn++;
        $csv_rows[] = [
            $sn, 'Territory Partner', $row['tp_name'], $row['tp_mobile'], $row['tp_gstin'], $row['invoice_number'],
            date("d/m/Y", strtotime($row['invoice_date'])),
            date("d/m/Y", strtotime($row['return_date'])),
            gst_percentage_label($row['taxable_value'], $row['gst_amount'], $gst_slabs),
            number_format($row['taxable_value'], 2, '.', ''),
            number_format($row['gst_amount'], 2, '.', ''),
            number_format($row['taxable_value'] + $row['gst_amount'], 2, '.', ''),
        ];
    }
    $csv_rows[] = ['', '', '', '', '', '', '', '', 'Grand Total',
        number_format($overall_total, 2, '.', ''),
        number_format($overall_gst, 2, '.', ''),
        number_format($overall_total + $overall_gst, 2, '.', ''),
    ];

    $csv_content = '';
    foreach ($csv_rows as $csv_row) {
        $csv_content .= implode(',', array_map(function ($v) {
            return '"' . str_replace('"', '""', $v) . '"';
        }, $csv_row)) . "\n";
    }

    ob_end_clean();
    header("Content-type: text/csv");
    header("Content-Disposition: attachment; filename=GST_Credit_Sales_Detailed_Report.csv");
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
                                                <h1>GSTR1 &gt; Detailed Sales Report &gt; <span style="color:red;">Credit</span> Note</h1>
                                                <h4>(SS, ST, DT, SHP, CUS, TP)</h4>
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

                        <div class="row">
                            <table style="width:100%;" id="gsttablevl">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Customer Type</th>
                                        <th>Customer Name</th>
                                        <th>Customer Mobile</th>
                                        <th>GSTIN</th>
                                        <th>Invoice Number</th>
                                        <th>Invoice Date</th>
                                        <th>Return Date</th>
                                        <th>GST %</th>
                                        <th>Taxable Value</th>
                                        <th>GST Amount</th>
                                        <th>Total Return Value (Rs.)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $sn = 0; ?>

                                    <?php foreach ($rows1 as $row): $sn++; ?>
                                    <tr>
                                        <td><?= $sn ?></td>
                                        <td><?= htmlspecialchars($customer_type_labels[$row['customer_usertype']] ?? ucfirst(str_replace('_', ' ', $row['customer_usertype']))) ?></td>
                                        <td><?= htmlspecialchars($row['cust_name']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_mobile']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_gstin']) ?></td>
                                        <td><?= htmlspecialchars($row['inv_number']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['invoice_date'])) ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['return_date'])) ?></td>
                                        <td align="center"><?= gst_percentage_label($row['total_sls_amount'], $row['gst_amount'], $gst_slabs) ?></td>
                                        <td align="right"><?= inr_format($row['total_sls_amount'], 2) ?></td>
                                        <td align="right"><?= inr_format($row['gst_amount'], 2) ?></td>
                                        <td align="right"><b><?= inr_format($row['total_sls_amount'] + $row['gst_amount'], 2) ?></b></td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php foreach ($rows2 as $row): $sn++; ?>
                                    <tr>
                                        <td><?= $sn ?></td>
                                        <td>Customer</td>
                                        <td><?= htmlspecialchars($row['cust_name']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_mobile']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_gstin']) ?></td>
                                        <td><?= htmlspecialchars($row['inv_number']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['invoice_date'])) ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['return_date'])) ?></td>
                                        <td align="center"><?= gst_percentage_label($row['total_sls_amount'], $row['gst_amount'], $gst_slabs) ?></td>
                                        <td align="right"><?= inr_format($row['total_sls_amount'], 2) ?></td>
                                        <td align="right"><?= inr_format($row['gst_amount'], 2) ?></td>
                                        <td align="right"><b><?= inr_format($row['total_sls_amount'] + $row['gst_amount'], 2) ?></b></td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php foreach ($rows3 as $row): $sn++; ?>
                                    <tr>
                                        <td><?= $sn ?></td>
                                        <td>Territory Partner</td>
                                        <td><?= htmlspecialchars($row['tp_name']) ?></td>
                                        <td><?= htmlspecialchars($row['tp_mobile']) ?></td>
                                        <td><?= htmlspecialchars($row['tp_gstin']) ?></td>
                                        <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['invoice_date'])) ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['return_date'])) ?></td>
                                        <td align="center"><?= gst_percentage_label($row['taxable_value'], $row['gst_amount'], $gst_slabs) ?></td>
                                        <td align="right"><?= inr_format($row['taxable_value'], 2) ?></td>
                                        <td align="right"><?= inr_format($row['gst_amount'], 2) ?></td>
                                        <td align="right"><b><?= inr_format($row['taxable_value'] + $row['gst_amount'], 2) ?></b></td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php if ($sn === 0): ?>
                                    <tr>
                                        <td colspan="12" style="text-align:center; padding:20px;">No records found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="9" align="right"><b>Grand Total</b></td>
                                        <td align="right"><b><?= inr_format($overall_total, 2) ?></b></td>
                                        <td align="right"><b><?= inr_format($overall_gst, 2) ?></b></td>
                                        <td align="right"><b><?= inr_format($overall_total + $overall_gst, 2) ?></b></td>
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
