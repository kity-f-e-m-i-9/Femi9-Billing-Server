<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/NeksomoProductMapping.php");
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

// Company catalog only — never another Neksomo piece-product.
$companyProducts = $db_conn->query(
    "SELECT id, productName, pieces_per_pack, hsn FROM products
     WHERE temp_id NOT LIKE 'NKS-%' AND deleted_at IS NULL
     ORDER BY productName ASC"
)->fetch_all(MYSQLI_ASSOC);

$mappedIds = get_neksomo_product_mapping($db_conn, $id);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Map Product : <?php echo $business_name; ?></title>

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
    <style>
        .map-list { max-height: 480px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 6px; }
        .map-row { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid #f1f5f9; }
        .map-row:last-child { border-bottom: none; }
        .map-row:hover { background: #f9fafb; }
        .map-row input[type="checkbox"] { width: 18px; height: 18px; flex-shrink: 0; }
        .map-row .pname { flex: 1; }
        .map-row .ppack { color: #64748b; font-size: 12px; white-space: nowrap; }
        .mapped-count { font-size: 13px; color: #64748b; margin-bottom: 8px; }
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
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble">
                                        <tr>
                                        <td>Map Product</td>
                                        <td><a href="neksomo-manage-products.php" title="Manage Products">&#9776;</a></td>
                                        </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-body">

                                    <?php if (isset($_REQUEST['updatedSuccess'])) { ?><div class="alert alert-success">Mapping saved.</div><?php } ?>
                                    <?php if (isset($_REQUEST['error'])) { ?><div class="alert alert-danger">Something went wrong. Please try again.</div><?php } ?>

                                    <p class="text-muted" style="font-size:13px;">
                                        Linking <b><?php echo htmlspecialchars($product['productName']); ?></b> (piece) to the company pack-variant(s)
                                        it corresponds to. Select every pack size that's built from this piece — e.g. the 9pc, 6pc and 3pc packs
                                        of the same size. This is only a manual link for future stock-maintenance use; nothing else changes.
                                    </p>

                                    <form action="neksomo-product-map-action.php" method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="neksomo_product_id" value="<?php echo (int)$product['id']; ?>">

                                        <input type="text" id="productFilter" class="form-control" placeholder="Search company products..." style="margin-bottom:10px;">

                                        <div class="mapped-count"><span id="selectedCount"><?php echo count($mappedIds); ?></span> selected</div>

                                        <div class="map-list" id="mapList">
                                            <?php foreach ($companyProducts as $cp): ?>
                                            <label class="map-row" data-name="<?php echo htmlspecialchars(strtolower($cp['productName'])); ?>">
                                                <input type="checkbox" name="company_product_ids[]" value="<?php echo (int)$cp['id']; ?>"
                                                       <?php echo in_array((int)$cp['id'], $mappedIds, true) ? 'checked' : ''; ?>>
                                                <span class="pname"><?php echo htmlspecialchars($cp['productName']); ?></span>
                                                <?php if (!empty($cp['pieces_per_pack'])): ?>
                                                    <span class="ppack"><?php echo (int)$cp['pieces_per_pack']; ?> pcs/pack</span>
                                                <?php endif; ?>
                                            </label>
                                            <?php endforeach; ?>
                                            <?php if (empty($companyProducts)): ?>
                                                <div class="map-row">No company products found.</div>
                                            <?php endif; ?>
                                        </div>

                                        <br/>
                                        <button type="submit" class="btn btn-primary"><i class="material-icons">save</i>Save Mapping</button>
                                        <a href="neksomo-manage-products.php" class="btn btn-secondary ms-2">Cancel</a>
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
    <script>
        document.getElementById('productFilter').addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            document.querySelectorAll('#mapList .map-row[data-name]').forEach(function (row) {
                row.style.display = row.getAttribute('data-name').indexOf(q) !== -1 ? '' : 'none';
            });
        });
        function updateSelectedCount() {
            document.getElementById('selectedCount').textContent =
                document.querySelectorAll('#mapList input[type="checkbox"]:checked').length;
        }
        document.querySelectorAll('#mapList input[type="checkbox"]').forEach(function (cb) {
            cb.addEventListener('change', updateSelectedCount);
        });
    </script>
</body>

</html>
