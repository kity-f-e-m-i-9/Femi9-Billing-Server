<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

header("Location: overall-stock.php"); exit;

$advBalance = 0;

// CSRF token bootstrap
if (empty($_SESSION['csrf_token_opstock_tp'])) {
    $_SESSION['csrf_token_opstock_tp'] = bin2hex(random_bytes(32));
}

// Handle opening stock submission
if (isset($_REQUEST['update-opstock'])) {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (
        empty($_SESSION['csrf_token_opstock_tp']) ||
        !hash_equals($_SESSION['csrf_token_opstock_tp'], $submittedToken)
    ) {
        $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
        header("Location: op-stock.php?csrferror");
        exit;
    }
    $_SESSION['csrf_token_opstock_tp'] = bin2hex(random_bytes(32));

    $tp_id   = (int) $Login_user_IDvl;
    $pr_ids  = $_POST['pr_id']  ?? [];
    $op_qtys = $_POST['op_qty'] ?? [];

    if (!is_array($pr_ids) || count($pr_ids) === 0) {
        header("Location: op-stock.php?invalid");
        exit;
    }

    $createdBy = $_SESSION['LOGIN_USER'] ?? 'system';

    $stmtChk = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM territory_partner_stock WHERE territory_partner_id = ? AND product_id = ?"
    );
    $stmtIns = $db_conn->prepare(
        "INSERT INTO territory_partner_stock (territory_partner_id, product_id, opening_qty, input_qty, closing_qty) VALUES (?, ?, ?, ?, ?)"
    );
    $stmtLed = $db_conn->prepare(
        "INSERT INTO territory_partner_stock_ledger
            (territory_partner_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
         VALUES (?, ?, 'opening', ?, 0, ?, 'opening', ?, 'opening stock set', ?)"
    );

    $inserted = 0;
    foreach ($pr_ids as $i => $rawPid) {
        $pid = (int) $rawPid;
        $qty = (int) ($op_qtys[$i] ?? 0);
        if ($pid <= 0 || $qty < 0) continue;

        $stmtChk->bind_param('ii', $tp_id, $pid);
        $stmtChk->execute();
        $chkRes = $stmtChk->get_result();
        $alreadyExists = (int)$chkRes->fetch_assoc()['n'] > 0;
        $chkRes->free();
        if ($alreadyExists) continue;

        $stmtIns->bind_param('iiiii', $tp_id, $pid, $qty, $qty, $qty);
        $stmtIns->execute();

        $refId = (string)$pid;
        $stmtLed->bind_param('iiiiiss', $tp_id, $pid, $qty, $qty, $refId, $createdBy);
        $stmtLed->execute();

        $inserted++;
    }

    $stmtChk->close();
    $stmtIns->close();
    $stmtLed->close();

    if ($inserted > 0) {
        echo "<script>window.location='op-stock.php?StockUpdatedSuccess';</script>";
    } else {
        echo "<script>window.location='op-stock.php?stockalreadyupdated';</script>";
    }
    exit;
}

$tp_id = (int) $Login_user_IDvl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set Opening Stock : <?php echo $business_name; ?></title>
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
                                <h1><table class="headertble"><tr><td>Set Opening Stock</td></tr></table></h1>
                                <?php if (isset($_REQUEST['StockUpdatedSuccess'])) { ?><div class="alert alert-success">Stock Updated Successfully.</div><?php } ?>
                                <?php if (isset($_REQUEST['stockalreadyupdated'])) { ?><div class="alert alert-warning">Stock already set for all products.</div><?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token_opstock_tp']) ?>">
                                        <div class="example-container">
                                            <div class="example-content">
                                                <table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th>Product Name</th>
                                                            <th>Opening Stock Qty</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
<?php
$stmtProds = $db_conn->prepare("SELECT id, productName FROM products ORDER BY id ASC");
$stmtProds->execute();
$products = $stmtProds->get_result();
$stmtProds->close();

while ($row_prod = $products->fetch_assoc()) {
    $pid = (int) $row_prod['id'];
    $stmtStk = $db_conn->prepare(
        "SELECT opening_qty FROM territory_partner_stock WHERE territory_partner_id = ? AND product_id = ?"
    );
    $stmtStk->bind_param('ii', $tp_id, $pid);
    $stmtStk->execute();
    $stockRow = $stmtStk->get_result()->fetch_assoc();
    $stmtStk->close();

    if (!$stockRow) { ?>
                                                        <input type="hidden" name="pr_id[]" value="<?php echo $pid; ?>"/>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($row_prod['productName']); ?></td>
                                                            <td><input type="number" name="op_qty[]" class="form-control" required="" style="border-color:#000 !important;" placeholder="Opening Stock Qty" min="0"/></td>
                                                        </tr>
    <?php } else { ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($row_prod['productName']); ?></td>
                                                            <td><input type="number" value="<?= (int)$stockRow['opening_qty']; ?>" disabled class="form-control"/></td>
                                                        </tr>
    <?php }
}
?>
                                                        <tr>
                                                            <td></td>
                                                            <td>
                                                                <button type="submit" onclick="return confirm('Please make a confirm!');" name="update-opstock" class="btn btn-primary">
                                                                    <i class="material-icons">update</i>Update
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
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
