<?php
ob_start();
include("checksession.php");
include("config.php");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// All active TPs
$tp_list = $db_conn->query("SELECT id, tp_id, name, mobile FROM territory_partners WHERE is_active = 1 ORDER BY name ASC")
               ->fetch_all(MYSQLI_ASSOC);

// Selected TP from GET
$selected_tp_db_id = isset($_GET['tp_db_id']) ? (int)$_GET['tp_db_id'] : 0;
$selected_tp       = null;
$products          = [];
$current_stock     = [];

if ($selected_tp_db_id > 0) {
    // Load TP details
    $stmt = $db_conn->prepare("SELECT id, tp_id, name, mobile FROM territory_partners WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("i", $selected_tp_db_id);
    $stmt->execute();
    $selected_tp = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($selected_tp) {
        // All products
        $prod_res = $db_conn->query("SELECT id, productName FROM products ORDER BY productName ASC");
        if ($prod_res) while ($r = $prod_res->fetch_assoc()) $products[] = $r;

        // Current stock for this TP
        $stk_res = $db_conn->query("SELECT product_id, input_qty, closing_qty FROM territory_partner_stock WHERE territory_partner_id = " . $selected_tp_db_id);
        if ($stk_res) while ($r = $stk_res->fetch_assoc()) $current_stock[$r['product_id']] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add TP Input Stock : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">

    <style>
        .card { border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.08); border-radius: 12px; }
        .card-header { background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%); border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0; }

        .stock-table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 600; font-size: 13px; padding: 10px 12px;
            border-color: #dee2e6; color: #495057; white-space: nowrap;
        }
        .stock-table td { font-size: 13px; padding: 8px 12px; vertical-align: middle; border-color: #dee2e6; }
        .stock-table tbody tr:hover { background: rgba(0,123,255,.03); }

        .qty-input {
            width: 90px; text-align: center; font-size: 14px; font-weight: 600;
            border: 1px solid #ced4da; border-radius: 6px; padding: 5px 8px;
            transition: border-color .15s, box-shadow .15s;
        }
        .qty-input:focus { border-color: #86b7fe; box-shadow: 0 0 0 .2rem rgba(13,110,253,.2); outline: none; }
        .qty-input.has-value    { border-color: #198754; background: #f0fff4; }
        .qty-input.has-negative { border-color: #dc3545; background: #fff5f5; color: #dc3545; }

        .current-stock-badge {
            display: inline-block; min-width: 44px; padding: 3px 10px;
            border-radius: 20px; font-weight: 600; font-size: 13px; text-align: center;
        }
        .badge-positive { background: #d1fae5; color: #065f46; }
        .badge-zero     { background: #fee2e2; color: #991b1b; }

        .tp-info-bar {
            background: #eff6ff; border-left: 4px solid #3b82f6;
            border-radius: 6px; padding: 10px 16px; font-size: 13px;
            display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
        }

        .select2-container--default .select2-selection--single { border-radius: 6px; border: 1px solid #ced4da; height: auto; padding: 8px 10px; font-size: 0.9rem; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 1.5; padding: 0; color: #374151; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 100%; top: 0; right: 8px; }
        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--single { border-color: #86b7fe; box-shadow: 0 0 0 .2rem rgba(13,110,253,.25); }
        .select2-dropdown { border: 1px solid #ced4da; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,.1); font-size: 0.875rem; }
        .select2-search--dropdown .select2-search__field { border: 1px solid #ced4da; border-radius: 4px; padding: 5px 8px; }
        .select2-container { width: 100% !important; }
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

                    <!-- Page header -->
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble">
                                        <tr>
                                            <td>Add TP Input Stock</td>
                                            <td><a href="tp-stock">&#8592;&nbsp;TP Stock</a></td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Alerts -->
                    <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <strong>Stock updated.</strong> Input quantities have been added successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger">An error occurred while saving. Please try again.</div>
                    <?php endif; ?>

                    <!-- TP Selector Card -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Select Territory Partner</h5>
                                </div>
                                <div class="card-body">
                                    <form method="get" action="<?= $_SERVER['PHP_SELF']; ?>" id="tpSelectForm">
                                        <div class="mb-3">
                                            <select name="tp_db_id" id="tpSelect" class="form-control" required>
                                                <option value="">— Choose a Territory Partner —</option>
                                                <?php foreach ($tp_list as $tp): ?>
                                                <option value="<?= (int)$tp['id']; ?>"
                                                    <?= ($selected_tp_db_id === (int)$tp['id']) ? 'selected' : ''; ?>>
                                                    <?= htmlspecialchars($tp['name']); ?> (<?= htmlspecialchars($tp['tp_id']); ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="material-icons" style="vertical-align:middle;font-size:18px;">inventory_2</i>
                                            View Products
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($selected_tp && !empty($products)): ?>

                    <!-- TP info bar -->
                    <div class="tp-info-bar mb-3">
                        <span><strong><?= htmlspecialchars($selected_tp['name']); ?></strong></span>
                        <span class="text-muted">ID: <?= htmlspecialchars($selected_tp['tp_id']); ?></span>
                        <span class="text-muted">M: <?= htmlspecialchars($selected_tp['mobile']); ?></span>
                    </div>

                    <!-- Products Table -->
                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <h5 class="card-title mb-0">Enter Input Quantities</h5>
                                    <small class="text-muted">Leave blank or 0 to skip a product</small>
                                </div>
                                <div class="card-body p-0">
                                    <form method="post" action="add-tp-input-stock-action.php" id="stockForm">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="tp_db_id" value="<?= $selected_tp_db_id; ?>">
                                        <input type="hidden" name="tp_id_text" value="<?= htmlspecialchars($selected_tp['tp_id']); ?>">

                                        <div style="overflow-x:auto;">
                                            <table class="table table-bordered stock-table mb-0">
                                                <thead>
                                                    <tr>
                                                        <th style="width:40px;">#</th>
                                                        <th>Product</th>
                                                        <th style="width:130px; text-align:center;">Current Stock</th>
                                                        <th style="width:120px; text-align:center;">Qty Change</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($products as $i => $prod):
                                                    $stk        = $current_stock[$prod['id']] ?? null;
                                                    $closing    = $stk ? (int)$stk['closing_qty'] : 0;
                                                ?>
                                                <tr>
                                                    <td class="text-muted"><?= $i + 1; ?></td>
                                                    <td><strong><?= htmlspecialchars($prod['productName']); ?></strong></td>
                                                    <td align="center">
                                                        <span class="current-stock-badge <?= $closing > 0 ? 'badge-positive' : 'badge-zero'; ?>">
                                                            <?= $closing; ?>
                                                        </span>
                                                    </td>
                                                    <td align="center">
                                                        <input type="number"
                                                               name="qty[<?= (int)$prod['id']; ?>]"
                                                               class="qty-input"
                                                               step="1"
                                                               placeholder="0"
                                                               value=""
                                                               autocomplete="off">
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="p-3 border-top d-flex align-items-center gap-3 flex-wrap">
                                            <div class="flex-grow-1">
                                                <input type="text" name="note" class="form-control"
                                                       placeholder="Note / Reference (optional)" maxlength="255">
                                            </div>
                                            <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                                <i class="material-icons" style="vertical-align:middle;font-size:20px;">save</i>
                                                Update Stock
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php elseif ($selected_tp_db_id > 0 && !$selected_tp): ?>
                    <div class="alert alert-danger">Territory partner not found or inactive.</div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script>
$(function () {
    $('#tpSelect').select2({
        placeholder: '— Choose a Territory Partner —',
        allowClear: false,
        width: '100%'
    });

    // Auto-submit TP selector when selection changes
    $('#tpSelect').on('change', function () {
        if ($(this).val()) $('#tpSelectForm').submit();
    });

    // Highlight qty inputs — green for positive, red for negative
    $(document).on('input', '.qty-input', function () {
        var v = parseInt($(this).val(), 10);
        $(this).toggleClass('has-value',    !isNaN(v) && v > 0);
        $(this).toggleClass('has-negative', !isNaN(v) && v < 0);
    });

    // Prevent accidental double-submit
    $('#stockForm').on('submit', function () {
        $('#submitBtn').prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-1"></span> Saving…'
        );
    });
});
</script>
</body>
</html>
