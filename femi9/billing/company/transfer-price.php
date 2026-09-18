<?php
include("checksession.php");
require_once("include/AutoTransferDemand.php");
include("config.php");

// Same product scope internal_transfer.php's manual form uses (excludes
// Neksomo-internal temp_id-tagged rows) — this page manages the default
// rate Auto Transfer pre-fills for every one of those products.
$productsRes = $db_conn->query(
    "SELECT id, productName FROM products WHERE (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) ORDER BY productName"
);
$products = $productsRes->fetch_all(MYSQLI_ASSOC);
$defaultRates = get_auto_transfer_default_rates($db_conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transfer Price : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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

                    <?php
                    if (isset($_SESSION['sucMessage'])) {
                        $flashMsg = htmlspecialchars($_SESSION['sucMessage'], ENT_QUOTES, 'UTF-8');
                        unset($_SESSION['sucMessage']);
                        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
                           . '<script>Swal.fire({icon:"success",title:"Success",text:"' . $flashMsg . '",confirmButtonText:"OK"});</script>';
                    }
                    ?>

                    <div class="page-description">
                        <h1>
                            <table class="headertble">
                                <tr>
                                    <td>Transfer Price</td>
                                    <td><a href="internal_transfer_auto" title="Auto Transfer for Orders">&#9776;</a></td>
                                </tr>
                            </table>
                        </h1>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <p class="text-muted">
                                        Default per-product rate used to pre-fill the "Rate to Health
                                        Care" / "Rate to LLP" boxes on the Auto Transfer for Orders page.
                                        Still editable there before every transfer — this is only the
                                        starting value. Leave a box blank for a product with no known
                                        rate yet.
                                    </p>
                                    <form method="post" action="transfer-price-action.php">
                                        <div style="overflow-x:auto;">
                                        <table class="table table-bordered" style="min-width:700px;">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Neksomo &rarr; Health Care Rate (Rs.)</th>
                                                    <th>Health Care &rarr; LLP Rate (Rs.)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($products as $p): $pid = (int) $p['id']; $r = $defaultRates[$pid] ?? null; ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($p['productName'], ENT_QUOTES, 'UTF-8'); ?>
                                                        <input type="hidden" name="product_id[]" value="<?php echo $pid; ?>">
                                                    </td>
                                                    <td>
                                                        <input type="number" min="0" step="0.01" name="rate_healthcare[]" class="form-control"
                                                               value="<?php echo $r ? htmlspecialchars((string) $r['healthcare'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                                    </td>
                                                    <td>
                                                        <input type="number" min="0" step="0.01" name="rate_llp[]" class="form-control"
                                                               value="<?php echo $r ? htmlspecialchars((string) $r['llp'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="material-icons" style="font-size:16px;vertical-align:middle;">save</i> Save Rates
                                        </button>
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
