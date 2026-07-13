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

// ✅ BLOCK 1: SS/ST/DT/SHOP invoices — single JOIN across all user-type tables using UNION
// Each user type has its own table, so we UNION them all into one query
$select_Report = "
    SELECT
        ui.inv_number,
        ui.date,
        ui.total AS total_sls_amount,
        ui.to_user_type AS customer_usertype,
        COALESCE(ss.name,   st.name,   dt.name,   sh.name)            AS cust_name,
        COALESCE(ss.mobile_number, st.mobile_number, dt.mobile_number, sh.mobile_number) AS cust_mobile,
        COALESCE(ss.gstin,  st.gstin,  dt.gstin,  sh.gstin)           AS cust_gstin
    FROM user_invoice ui
    LEFT JOIN super_stockiest ss ON ui.to_user_type='super_stockiest' AND ss.temp_id = ui.to_user_id
    LEFT JOIN stockiest        st ON ui.to_user_type='stockiest'       AND st.temp_id = ui.to_user_id
    LEFT JOIN distributor      dt ON ui.to_user_type='distributor'     AND dt.temp_id = ui.to_user_id
    LEFT JOIN shop             sh ON ui.to_user_type='shop'            AND sh.temp_id = ui.to_user_id
    WHERE ui.from_user_type  = '$Login_user_TYPEvl'
      AND ui.from_user_id    = '$get_godown_id'
      AND ui.buyer_gsttype   = '$buyer_gsttype'
      AND ui.gst_type        = '$gst_type'
      AND ui.date BETWEEN '$from_date' AND '$to_date'
    ORDER BY ui.date ASC
";
$fetch_Report = mysqli_query($db_conn, $select_Report);
$rows1 = [];
$total1 = 0;
while ($row = mysqli_fetch_assoc($fetch_Report)) {
    $total1 += $row['total_sls_amount'];
    $rows1[] = $row;
}

// ✅ BLOCK 2: Customer invoices — single JOIN with customers table
$select_Report2 = "
    SELECT
        i.inv_number,
        i.date,
        i.total AS total_sls_amount,
        'customer' AS customer_usertype,
        c.name   AS cust_name,
        c.mobile AS cust_mobile,
        c.gstin  AS cust_gstin
    FROM invoice i
    LEFT JOIN customers c ON c.id = i.customer_id
    WHERE i.user_type = '$Login_user_TYPEvl'
      AND i.user_id   = '$get_godown_id'
      AND i.buyer_gsttype = '$buyer_gsttype'
      AND i.gst_type      = '$gst_type'
      AND i.date BETWEEN '$from_date' AND '$to_date'
    ORDER BY i.date ASC
";
$fetch_Report2 = mysqli_query($db_conn, $select_Report2);
$rows2 = [];
$total2 = 0;
while ($row = mysqli_fetch_assoc($fetch_Report2)) {
    $total2 += $row['total_sls_amount'];
    $rows2[] = $row;
}

// ✅ BLOCK 3: TP invoices (company -> territory partner transfers), godown-sourced only.
// Matched to this row's gst_type (intra/inter) and buyer_gsttype (register/unregister)
// the same way the summary totals on GSTR1 are computed — see TpGstHelper.php.
require_once "include/TpGstHelper.php";
$tp_lines = tp_sales_gst_lines($db_conn, $from_date, $to_date, "tpi.source_godown_id = '$get_godown_id'");
$want_intra = ($gst_type == 'inner');
$want_register = ($buyer_gsttype == 'register');
$tp_lines = array_filter($tp_lines, function ($l) use ($want_intra, $want_register) {
    return $l['is_intra'] === $want_intra && $l['is_registered'] === $want_register;
});
$rows3 = tp_group_lines($tp_lines, 'tp_invoice_id');
$total3 = 0;
foreach ($rows3 as $row) { $total3 += $row['taxable_value']; }

$overall_total = $total1 + $total2 + $total3;
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
                                                <h1>GSTR1 &gt; Detailed Sales Report</h1>
                                                <h4>(SS, ST, DT, SHP, CUS, TP)</h4>
                                                <h5><?= htmlspecialchars($lable_header) ?></h5>
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
                                        <th>Total Sales Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $sn = 0; ?>

                                    <?php foreach ($rows1 as $row): $sn++; ?>
                                    <tr>
                                        <td><?= $sn ?></td>
                                        <td><?= htmlspecialchars($row['customer_usertype']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_name']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_mobile']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_gstin']) ?></td>
                                        <td><?= htmlspecialchars($row['inv_number']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['date'])) ?></td>
                                        <td align="right"><b><?= inr_format($row['total_sls_amount'], 2) ?></b></td>
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
                                        <td><?= date("d/m/Y", strtotime($row['date'])) ?></td>
                                        <td align="right"><b><?= inr_format($row['total_sls_amount'], 2) ?></b></td>
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
                                        <td align="right"><b><?= inr_format($row['taxable_value'], 2) ?></b></td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php if ($sn === 0): ?>
                                    <tr>
                                        <td colspan="8" style="text-align:center; padding:20px;">No records found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="7" align="right"><b>Grand Total</b></td>
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
