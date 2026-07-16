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

$products = $db_conn->query("SELECT id, productName, pieces_per_pack FROM products WHERE deleted_at IS NULL ORDER BY productName ASC")->fetch_all(MYSQLI_ASSOC);
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
    <style>
        .form-section { background:white; border:2px solid #e5e7eb; border-radius:14px; padding:30px 35px; margin-bottom:22px; box-shadow:0 1px 4px rgba(0,0,0,0.06); }
        .section-header { color:#475569; font-size:13px; font-weight:600; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:24px; display:flex; align-items:center; gap:10px; padding-bottom:14px; border-bottom:2px solid #f1f5f9; }
        .section-header i { color:#2563eb; font-size:19px; }
        .field-hint { font-size:12px; color:#94a3b8; margin-top:7px; }
        .product-add-section { background:#f8fafc; border:2px dashed #cbd5e1; border-radius:12px; padding:20px; margin-top:20px; }
        .product-add-grid { display:grid; grid-template-columns:2.5fr 1fr auto; gap:14px; align-items:end; }
        .input-group-modern { display:flex; flex-direction:column; }
        .input-group-modern label { font-size:12px; color:#64748b; font-weight:600; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.4px; }
        .input-group-modern .form-control { border:2px solid #e5e7eb; border-radius:8px; padding:10px 12px; font-size:14px; }
        .btn-add-product { background:#10b981; color:white; border:none; padding:11px 20px; border-radius:8px; font-weight:500; display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-family:'Poppins',sans-serif; font-size:14px; white-space:nowrap; }
        .btn-add-product:hover { background:#059669; }
        .table-modern { background:white; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-top:20px; border:1px solid #f1f5f9; }
        .table-modern table { margin:0; width:100%; }
        .table-modern thead { background:#f8fafc; }
        .table-modern thead th { color:#475569; font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; padding:14px 16px; border-bottom:2px solid #e5e7eb; white-space:nowrap; }
        .table-modern tbody td { padding:13px 16px; vertical-align:middle; border-bottom:1px solid #f1f5f9; color:#1e293b; font-size:14px; }
        .table-modern tbody tr:last-child td { border-bottom:none; }
        .table-modern .empty-row td { text-align:center; padding:40px; color:#94a3b8; }
        .row-num { width:28px; height:28px; background:#f1f5f9; color:#64748b; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:12px; font-weight:600; }
        .badge-remove { background:#fee2e2; color:#991b1b; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:600; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:3px; }
        .badge-remove:hover { background:#fecaca; }
        @media (max-width:768px) { .product-add-grid { grid-template-columns:1fr 1fr; } .product-add-grid .input-group-modern:last-child { grid-column:1/-1; } }
        @keyframes spin { to { transform:rotate(360deg); } }
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
                                        <td>Sale to Femi9 LLP (Per Piece)</td>
                                        <td><a href="neksomo-llp-piece-sale-manage" title="Manage Entries">&#9776;</a></td>
                                        </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($_REQUEST['addesuccess'])): ?>
                            <div class="alert alert-success">
                                <?php echo (int)($_REQUEST['count'] ?? 0); ?> rate(s) added.
                                <?php if (!empty($_REQUEST['skipped'])): ?>
                                    <?php echo (int)$_REQUEST['skipped']; ?> skipped — a rate already existed for that product on this date (edit it instead from Manage Entries).
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($_REQUEST['error'])): ?><div class="alert alert-danger">Something went wrong. Please try again.</div><?php endif; ?>

                        <form action="neksomo-llp-piece-sale-action.php" method="post" id="rateForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="add-record" value="1">
                        <div id="hiddenRateInputs"></div>

                        <div class="form-section">
                            <div class="section-header"><i class="material-icons">edit_document</i>Rate Details</div>
                            <p class="text-muted" style="font-size:13px;">
                                This is a rate list, not a per-sale log. Enter the per-piece rate and the
                                date it takes effect — that rate applies to every sale from that date
                                onward, until a later effective date for the same product supersedes it.
                            </p>
                            <div class="row g-4 align-items-start">
                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">Effective Date <span class="required text-danger">*</span></label>
                                    <input type="date" required name="effective_date" id="effectiveDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                    <div class="field-hint">Applies to every product added below</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-header" style="border:none;padding-bottom:0;margin-bottom:15px;"><i class="material-icons">playlist_add</i>Add Products</div>
                            <div class="product-add-section">
                                <div class="product-add-grid">
                                    <div class="input-group-modern">
                                        <label>Product <span style="color:#ef4444;">*</span></label>
                                        <select id="productSelect" class="form-control">
                                            <option value="">— Select Product —</option>
                                            <?php foreach ($products as $p): ?>
                                            <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['productName']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="input-group-modern">
                                        <label>Rate/Piece (₹) <span style="color:#ef4444;">*</span></label>
                                        <input type="number" id="rateInput" class="form-control" min="0" step="0.01" placeholder="0.00">
                                    </div>
                                    <div class="input-group-modern" style="align-items:flex-end;">
                                        <button type="button" class="btn-add-product" id="addProductBtn" onclick="addProduct()">
                                            <i class="material-icons">add</i> Add
                                        </button>
                                    </div>
                                </div>
                                <div id="addError" style="margin-top:10px;display:none;"></div>
                            </div>

                            <div class="table-modern">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>#</th><th>Product</th><th>Rate/Piece (₹)</th><th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="productBody">
                                        <tr class="empty-row" id="emptyRow">
                                            <td colspan="4">
                                                <i class="material-icons" style="font-size:40px;display:block;margin-bottom:10px;color:#cbd5e1;">inventory_2</i>
                                                No products added yet
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div style="margin-top:20px;">
                                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                    <i class="material-icons">check_circle</i> Save Rates
                                </button>
                            </div>
                        </div>

                        </form>

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
    (function ($) {
        var rateItems = [];

        window.addProduct = function () {
            hideAddError();
            var $opt       = $('#productSelect').find('option:selected');
            var product_id = parseInt($('#productSelect').val());
            var name       = $opt.text().trim();
            var rate       = parseFloat($('#rateInput').val());

            if (!product_id)             { showAddError('Please select a product.'); return; }
            if (isNaN(rate) || rate < 0) { showAddError('Please enter a valid rate per piece.'); return; }
            if (rateItems.find(function (i) { return i.product_id === product_id; })) {
                showAddError('This product is already added.'); return;
            }

            rateItems.push({ product_id: product_id, name: name, rate: rate });
            renderTable();
            resetAddForm();
        };

        window.removeProduct = function (idx) {
            rateItems.splice(idx, 1);
            renderTable();
        };

        function renderTable() {
            var $body = $('#productBody').empty();
            if (!rateItems.length) {
                $body.html('<tr class="empty-row"><td colspan="4"><i class="material-icons" style="font-size:40px;display:block;margin-bottom:10px;color:#cbd5e1;">inventory_2</i>No products added yet</td></tr>');
                updateSubmitState(); return;
            }
            $.each(rateItems, function (i, item) {
                $body.append(
                    '<tr>' +
                    '<td><span class="row-num">' + (i + 1) + '</span></td>' +
                    '<td><strong>' + escHtml(item.name) + '</strong></td>' +
                    '<td>₹' + item.rate.toFixed(2) + '</td>' +
                    '<td><button type="button" class="badge-remove" onclick="removeProduct(' + i + ')"><i class="material-icons" style="font-size:14px;vertical-align:middle;">delete</i> Remove</button></td>' +
                    '</tr>'
                );
            });
            updateSubmitState();
            buildHiddenInputs();
        }

        function updateSubmitState() {
            $('#submitBtn').prop('disabled', rateItems.length === 0);
        }

        function buildHiddenInputs() {
            var html = '';
            $.each(rateItems, function (_, item) {
                html += '<input type="hidden" name="product_id[]"     value="' + item.product_id + '">';
                html += '<input type="hidden" name="rate_per_piece[]" value="' + item.rate + '">';
            });
            $('#hiddenRateInputs').html(html);
        }

        $('#rateForm').on('submit', function (e) {
            if (!rateItems.length) { e.preventDefault(); alert('Please add at least one product.'); return; }
            buildHiddenInputs();
            $('#submitBtn').prop('disabled', true).html('<i class="material-icons" style="animation:spin 1s linear infinite;font-size:18px;">refresh</i> Saving…');
        });

        function resetAddForm() {
            $('#productSelect').val('');
            $('#rateInput').val('');
            hideAddError();
        }

        function showAddError(msg) {
            $('#addError').html('<div class="alert alert-danger mb-0" style="font-size:13px;padding:10px 14px;"><i class="material-icons" style="vertical-align:middle;font-size:16px;">error</i> ' + msg + '</div>').show();
        }
        function hideAddError() { $('#addError').hide().empty(); }
        function escHtml(str) { return $('<div>').text(str).html(); }
    }(jQuery));
    </script>
</body>

</html>
