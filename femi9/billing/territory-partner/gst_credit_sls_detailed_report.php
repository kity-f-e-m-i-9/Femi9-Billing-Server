<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$from_date     = $_REQUEST['frd']  ?? '';
$to_date       = $_REQUEST['tod']  ?? '';
$gst_type      = $_REQUEST['data1'] ?? '';
$buyer_gsttype = $_REQUEST['data2'] ?? '';

$tp_id = $Login_user_IDvl;

if ($gst_type == "inner" && $buyer_gsttype == "register")
    $lable_header = "Intra-state (Registered person)";
elseif ($gst_type == "inner" && $buyer_gsttype == "unregister")
    $lable_header = "Intra-state (Unregistered person)";
elseif ($gst_type == "outer" && $buyer_gsttype == "register")
    $lable_header = "Inter-state (Registered person)";
else
    $lable_header = "Inter-state (Unregistered person)";

// Same "intra-state" convention used on gstr1.php: any non-'outer' value
// counts as intra, since some legacy invoice rows carry gst_type='0'.
$intraSql = $gst_type == 'outer' ? "gst_type = 'outer'" : "gst_type != 'outer'";

// BLOCK 1: Shop returns — items carry the GST-corrected taxable value
// (total - gstamount_total; see gstr1.php for why), joined back to the
// shop table and the originating user_invoice for invoice number/date.
$select_Report = "
    SELECT
        rsi.date        AS return_date,
        SUM(rsi.total - rsi.gstamount_total) AS total_sls_amount,
        sh.name          AS cust_name,
        sh.mobile_number AS cust_mobile,
        sh.gstin         AS cust_gstin,
        ui.inv_number,
        ui.date AS invoice_date
    FROM user_return_stock_items rsi
    LEFT JOIN shop sh          ON sh.temp_id = rsi.from_userid
    LEFT JOIN user_invoice ui  ON ui.inv_id = rsi.invnumber
    WHERE rsi.to_usertype   = ?
      AND rsi.to_userid     = ?
      AND rsi.buyer_gsttype = ?
      AND ($intraSql)
      AND rsi.date BETWEEN ? AND ?
      AND rsi.total > 0
      AND rsi.from_usertype != 'customer'
    GROUP BY rsi.returnid, rsi.date, sh.name, sh.mobile_number, sh.gstin, ui.inv_number, ui.date
    ORDER BY rsi.date ASC
";
$stmt = $db_conn->prepare($select_Report);
$stmt->bind_param("sisss", $Login_user_TYPEvl, $tp_id, $buyer_gsttype, $from_date, $to_date);
$stmt->execute();
$rows1 = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$total1 = 0;
foreach ($rows1 as $row) { $total1 += (float)$row['total_sls_amount']; }

// BLOCK 2: Customer returns — same taxable-value correction.
$select_Report2 = "
    SELECT
        rsi.date        AS return_date,
        SUM(rsi.total - rsi.gstamount_total) AS total_sls_amount,
        c.name   AS cust_name,
        c.mobile AS cust_mobile,
        c.gstin  AS cust_gstin,
        i.inv_number,
        i.date AS invoice_date
    FROM user_return_stock_items rsi
    LEFT JOIN customers c ON c.id = rsi.from_userid
    LEFT JOIN invoice   i ON i.inv_id = rsi.invnumber
    WHERE rsi.to_usertype   = ?
      AND rsi.to_userid     = ?
      AND rsi.buyer_gsttype = ?
      AND ($intraSql)
      AND rsi.date BETWEEN ? AND ?
      AND rsi.total > 0
      AND rsi.from_usertype = 'customer'
    GROUP BY rsi.returnid, rsi.date, c.name, c.mobile, c.gstin, i.inv_number, i.date
    ORDER BY rsi.date ASC
";
$stmt2 = $db_conn->prepare($select_Report2);
$stmt2->bind_param("sisss", $Login_user_TYPEvl, $tp_id, $buyer_gsttype, $from_date, $to_date);
$stmt2->execute();
$rows2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();
$total2 = 0;
foreach ($rows2 as $row) { $total2 += (float)$row['total_sls_amount']; }

$overall_total = $total1 + $total2;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
                                                <h4>(Shop, Customer)</h4>
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
                                        <th>Return Date</th>
                                        <th>Total Return Value (Rs.)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $sn = 0; ?>

                                    <?php foreach ($rows1 as $row): $sn++; ?>
                                    <tr>
                                        <td><?= $sn ?></td>
                                        <td>Shop</td>
                                        <td><?= htmlspecialchars($row['cust_name']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_mobile']) ?></td>
                                        <td><?= htmlspecialchars($row['cust_gstin']) ?></td>
                                        <td><?= htmlspecialchars($row['inv_number']) ?></td>
                                        <td><?= $row['invoice_date'] ? date("d/m/Y", strtotime($row['invoice_date'])) : '' ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['return_date'])) ?></td>
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
                                        <td><?= $row['invoice_date'] ? date("d/m/Y", strtotime($row['invoice_date'])) : '' ?></td>
                                        <td><?= date("d/m/Y", strtotime($row['return_date'])) ?></td>
                                        <td align="right"><b><?= inr_format($row['total_sls_amount'], 2) ?></b></td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php if ($sn === 0): ?>
                                    <tr>
                                        <td colspan="9" style="text-align:center; padding:20px;">No records found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="8" align="right"><b>Grand Total</b></td>
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
