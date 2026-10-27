<?php include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
error_reporting(0);

// Internal Stock Transfer is a finance-only area — this report is linked
// from there, so keep the same gate.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    header("Location: dashboard.php");
    exit;
}

if (($_REQUEST['frdate'] ?? '') === '' && ($_REQUEST['todate'] ?? '') === '') {
    $se_fromDate = date('Y-m-d', strtotime('-6 days'));
    $se_toDate   = date('Y-m-d');
} else {
    $se_fromDate = $_REQUEST['frdate'];
    $se_toDate   = $_REQUEST['todate'];
}

// Product-wise totals of everything returned through OT Channel Sales
// (ot-sale-return.php), so Finance can see at a glance how much of each
// product has come back, without opening every individual sale.
$stmt = $db_conn->prepare(
    "SELECT p.productName, w.code AS warehouse_code,
            SUM(r.qty) AS total_qty, COUNT(DISTINCT r.tempid) AS invoice_count
     FROM ot_sales_return r
     LEFT JOIN products p ON p.id = r.prid
     LEFT JOIN warehouses w ON w.id = r.warehouse_id
     WHERE r.return_date BETWEEN ? AND ?
     GROUP BY r.prid, r.warehouse_id
     ORDER BY p.productName ASC"
);
$stmt->bind_param('ss', $se_fromDate, $se_toDate);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$grand_total_qty = 0;
foreach ($rows as $r) { $grand_total_qty += (int)$r['total_qty']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OT Channel Return Qty : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
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
                                    <td>OT Channel Return Qty</td>
                                    <td><a href="internal_transfer_manage" title="Back to Manage Internal Stock Transfer">&#8630;</a></td>
                                    </tr>
                                    </table>
                                    </h1>
                                    <br/>

                                    <form method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>">
                                    <div class="overviewcontainar">
                                    <div id="searchleftcont">
                                    <label class="form-label">From Date</label>
                                    <input type="date" required name="frdate" value="<?= htmlspecialchars($se_fromDate); ?>" class="form-control">
                                    </div>
                                    <div id="searchleftcont">
                                    <label class="form-label">To Date</label>
                                    <input type="date" required name="todate" value="<?= htmlspecialchars($se_toDate); ?>" class="form-control">
                                    </div>
                                    <div id="searchbuttoncont">
                                    <button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
                                    </div>
                                    <div id="searchbuttoncont">&nbsp;
                                    <button type="button" onclick="javascript:window.location='ot-channel-return-qty.php';" class="btn btn-danger">Reset</button>
                                    </div>
                                    </div>
                                    </form>

                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <p><strong>Total Returned Qty (all products):</strong> <?= (int)$grand_total_qty; ?></p>
                                        <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Product Name</th>
                                                    <th>Godown (physical)</th>
                                                    <th>Return Qty</th>
                                                    <th>Return Entries</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php if (empty($rows)): ?>
                                                <tr><td colspan="4" class="text-center">No OT Channel returns in this date range.</td></tr>
                                            <?php else: foreach ($rows as $r): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($r['productName'] ?? '—'); ?></td>
                                                    <td><?= htmlspecialchars($r['warehouse_code'] ?? '— Not tracked —'); ?></td>
                                                    <td><?= (int)$r['total_qty']; ?></td>
                                                    <td><?= (int)$r['invoice_count']; ?></td>
                                                </tr>
                                            <?php endforeach; endif; ?>
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
    </div>

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
