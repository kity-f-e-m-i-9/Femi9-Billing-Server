<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = (int) base64_decode($_REQUEST['id'] ?? '');
$stmt = $db_conn->prepare("SELECT * FROM neksomo_llp_piece_rates WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$entry) {
    header("Location: neksomo-llp-piece-sale-manage.php");
    exit;
}

$products = $db_conn->query("SELECT id, productName FROM products ORDER BY productName ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Rate : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">

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
                                        <td>Edit Femi9 LLP Rate</td>
                                        <td><a href="neksomo-llp-piece-sale-manage.php" title="Manage Rates">&#9776;</a></td>
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

<form action="neksomo-llp-piece-sale-action.php" method="post">
<input type="hidden" name="update_id" value="<?php echo (int)$entry['id']; ?>">
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                        <div class="example-container">
                                            <div class="example-content">
                                                <label class="form-label">Product</label>
                                                <select required name="product_id" class="form-control">
                                                    <?php foreach ($products as $p): ?>
                                                    <option value="<?php echo (int)$p['id']; ?>" <?php echo ((int)$p['id'] === (int)$entry['product_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['productName']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <label class="form-label">Effective Date</label>
                                                <input type="date" required name="effective_date" value="<?php echo htmlspecialchars($entry['effective_date']); ?>" class="form-control">

                                                <label class="form-label">Rate per Piece (&#8377;)</label>
                                                <input type="number" min="0" step="0.01" required name="rate_per_piece" value="<?php echo htmlspecialchars((string)$entry['rate_per_piece']); ?>" class="form-control">

                                                <br/>
                                                <button type="submit" name="update-record" class="btn btn-primary">Update</button>
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
