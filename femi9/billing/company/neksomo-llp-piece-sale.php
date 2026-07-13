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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sale to Femi9 LLP : <?php echo $business_name; ?></title>

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
                                        <td>Sale to Femi9 LLP (Per Piece)</td>
                                        <td><a href="neksomo-llp-piece-sale-manage" title="Manage Entries">&#9776;</a></td>
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

                                    <?php if (isset($_REQUEST['addesuccess'])) { ?><div class="alert alert-success">Entry added successfully.</div><?php } ?>
                                    <?php if (isset($_REQUEST['error'])) { ?><div class="alert alert-danger">Something went wrong. Please try again.</div><?php } ?>

<form action="neksomo-llp-piece-sale-action.php" method="post">
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                        <div class="example-container">
                                            <div class="example-content">
                                                <p class="text-muted" style="font-size:13px;">
                                                    This is a rate list, not a per-sale log. Enter the per-piece rate and the
                                                    date it takes effect — that rate applies to every sale from that date
                                                    onward, until a later effective date for the same product supersedes it.
                                                </p>
                                                <label class="form-label">Product</label>
                                                <select required name="product_id" class="form-control">
                                                    <option value="" hidden>Select Product</option>
                                                    <?php foreach ($products as $p): ?>
                                                    <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['productName']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <label class="form-label">Effective Date</label>
                                                <input type="date" required name="effective_date" value="<?php echo date('Y-m-d'); ?>" class="form-control">

                                                <label class="form-label">Rate per Piece (&#8377;)</label>
                                                <input type="number" min="0" step="0.01" required name="rate_per_piece" class="form-control">

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
