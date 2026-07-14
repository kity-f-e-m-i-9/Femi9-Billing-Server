<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");

// Dedicated to the neksomo login (admin retained for oversight/support).
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$products = $db_conn->query("SELECT id, productName, pieces_per_pack FROM products ORDER BY productName ASC")->fetch_all(MYSQLI_ASSOC);
$manufacturers = $db_conn->query("SELECT DISTINCT manufacturer_name FROM neksomo_manufacturer_purchases ORDER BY manufacturer_name ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase from Manufacturer : <?php echo $business_name; ?></title>

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
                                        <td>Purchase from Manufacturer</td>
                                        <td><a href="neksomo-manufacturer-purchase-manage" title="Manage Entries">&#9776;</a></td>
                                        </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">

                                    <?php if (isset($_REQUEST['addesuccess'])) { ?><div class="alert alert-success">Purchase recorded and stock updated.</div><?php } ?>
                                    <?php if (isset($_REQUEST['error'])) { ?><div class="alert alert-danger">Something went wrong. Please try again.</div><?php } ?>

<form action="neksomo-manufacturer-purchase-action.php" method="post">
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                        <div class="example-container">
                                            <div class="example-content">
                                                <p class="text-muted" style="font-size:13px;">
                                                    This records a real purchase and adds directly to Neksomo's on-hand stock
                                                    (same effect as Add Input Stock) — quantity is in packs.
                                                </p>
                                                <label class="form-label">Product</label>
                                                <select required name="product_id" class="form-control">
                                                    <option value="" hidden>Select Product</option>
                                                    <?php foreach ($products as $p): ?>
                                                    <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['productName']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <label class="form-label">Manufacturer</label>
                                                <input type="text" required name="manufacturer_name" class="form-control" list="manufacturer_list" placeholder="e.g. Femi Health Care">
                                                <datalist id="manufacturer_list">
                                                    <?php foreach ($manufacturers as $m): ?>
                                                    <option value="<?php echo htmlspecialchars($m['manufacturer_name']); ?>">
                                                    <?php endforeach; ?>
                                                </datalist>

                                                <label class="form-label">Purchase Date</label>
                                                <input type="date" required name="purchase_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" class="form-control">

                                                <label class="form-label">Quantity (Packs)</label>
                                                <input type="number" min="1" required name="quantity_packs" class="form-control">

                                                <label class="form-label">Cost per Piece (&#8377;)</label>
                                                <input type="number" min="0" step="0.01" required name="cost_per_piece" class="form-control">

                                                <br/>
                                                <button type="submit" name="add-record" class="btn btn-primary"><i class="material-icons">add</i>Add</button>
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
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>

</html>
