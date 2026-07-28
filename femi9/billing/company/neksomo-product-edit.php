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

$id = (int)($_GET['id'] ?? 0);
$stmt = $db_conn->prepare("SELECT * FROM products WHERE id = ? AND temp_id LIKE 'NKS-%'");
$stmt->bind_param('i', $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: neksomo-manage-products.php?error");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Product : <?php echo $business_name; ?></title>

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
                                        <td>Edit Product</td>
                                        <td><a href="neksomo-manage-products.php" title="Manage Products">&#9776;</a></td>
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

                                    <?php if (isset($_REQUEST['error'])) { ?><div class="alert alert-danger">Please check that the product name, category, GST, and unit fields are filled in correctly.</div><?php } ?>

<form action="neksomo-product-action.php" method="post">
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
<input type="hidden" name="action" value="update-product">
<input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">

                                        <div class="example-container">
                                            <div class="example-content">
                                                <?php include("validate-scripts.php"); ?>

                                                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                                <input type="text" required name="productName" maxlength="255" class="form-control" autofocus onkeypress="restrictSpecialChars(event)"
                                                       value="<?php echo htmlspecialchars($product['productName']); ?>">

                                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                                <select name="category" class="form-control" required>
                                                    <option value="" disabled <?php echo empty($product['category']) ? 'selected' : ''; ?>>Select category</option>
                                                    <option value="napkin" <?php echo $product['category'] === 'napkin' ? 'selected' : ''; ?>>Napkin</option>
                                                    <option value="diaper" <?php echo $product['category'] === 'diaper' ? 'selected' : ''; ?>>Diaper</option>
                                                </select>

                                                <label class="form-label">Sold As <span class="text-danger">*</span></label>
                                                <select name="unit_type" id="unit_type" class="form-control" required onchange="document.getElementById('pieces_per_pack_wrap').style.display = (this.value === 'pack') ? 'block' : 'none';">
                                                    <option value="pieces" <?php echo $product['unit_type'] !== 'pack' ? 'selected' : ''; ?>>Pieces</option>
                                                    <option value="pack" <?php echo $product['unit_type'] === 'pack' ? 'selected' : ''; ?>>Pack</option>
                                                </select>

                                                <div id="pieces_per_pack_wrap" style="display:<?php echo $product['unit_type'] === 'pack' ? 'block' : 'none'; ?>;">
                                                    <label class="form-label">Pieces per Pack</label>
                                                    <input type="number" min="1" name="pieces_per_pack" class="form-control" onkeypress="restrictnumber(event)" placeholder="e.g. 12"
                                                           value="<?php echo htmlspecialchars((string)($product['pieces_per_pack'] ?? '')); ?>">
                                                </div>

                                                <label class="form-label">GST (%) <span class="text-danger">*</span></label>
                                                <input type="number" min="0" max="99" required name="gst" class="form-control" onkeypress="restrictnumber(event)"
                                                       value="<?php echo htmlspecialchars((string)$product['gst']); ?>">

                                                <label class="form-label">GST Type <span class="text-danger">*</span></label>
                                                <select name="gst_type" class="form-control" required>
                                                    <option value="exclusive" <?php echo $product['gst_type'] !== 'inclusive' ? 'selected' : ''; ?>>Exclusive (GST added on top of price)</option>
                                                    <option value="inclusive" <?php echo $product['gst_type'] === 'inclusive' ? 'selected' : ''; ?>>Inclusive (GST included in price)</option>
                                                </select>

                                                <label class="form-label">HSN <span class="text-muted" style="font-size:12px;">(optional)</span></label>
                                                <input type="text" name="hsn" maxlength="255" class="form-control" onkeypress="restrictHSN(event)"
                                                       value="<?php echo htmlspecialchars($product['hsn'] ?? ''); ?>">

                                                <br/>
                                                <button type="submit" class="btn btn-primary"><i class="material-icons">save</i>Save Changes</button>
                                                <a href="neksomo-manage-products.php" class="btn btn-secondary ms-2">Cancel</a>
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
