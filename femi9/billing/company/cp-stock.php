<?php
ob_start();
include("checksession.php");
include("config.php");

// Clear filters
if (isset($_GET['clear_filters']) || isset($_POST['clear_all'])) {
    ob_clean();
    unset($_SESSION['cpstock_cp_id']);
    unset($_SESSION['cpstock_records_per_page']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

mysqli_set_charset($db_conn, 'utf8mb4');

// CP filter (optional — empty = show all)
$selected_cp_id   = '';
$selected_cp_name = '';
if (isset($_POST['cp_id'])) {
    $selected_cp_id = trim($_POST['cp_id']);
    if ($selected_cp_id) $_SESSION['cpstock_cp_id'] = $selected_cp_id;
    else unset($_SESSION['cpstock_cp_id']);
} elseif (isset($_SESSION['cpstock_cp_id'])) {
    $selected_cp_id = $_SESSION['cpstock_cp_id'];
}
if ($selected_cp_id !== '') {
    $esc = $db_conn->real_escape_string($selected_cp_id);
    $cn  = $db_conn->query("SELECT name FROM channel_partners WHERE cp_id = '$esc' LIMIT 1");
    if ($cn && $row = $cn->fetch_assoc()) $selected_cp_name = $row['name'];
}

// Pagination
$records_per_page = 20;
if (isset($_POST['records_per_page'])) {
    $records_per_page = (int)$_POST['records_per_page'];
    $_SESSION['cpstock_records_per_page'] = $records_per_page;
} elseif (isset($_SESSION['cpstock_records_per_page'])) {
    $records_per_page = (int)$_SESSION['cpstock_records_per_page'];
}
if (!in_array($records_per_page, [20, 40, 60, 100])) $records_per_page = 20;

$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// Build WHERE
$where_parts = ["cp.is_active = 1"];
if ($selected_cp_id !== '') {
    $esc_cp = $db_conn->real_escape_string($selected_cp_id);
    $where_parts[] = "cp.cp_id = '$esc_cp'";
}
$where_sql = implode(' AND ', $where_parts);

// Products
$products = [];
$prod_res = mysqli_query($db_conn, "SELECT id, productName FROM products ORDER BY id ASC");
if ($prod_res) while ($pr = mysqli_fetch_assoc($prod_res)) $products[$pr['id']] = $pr['productName'];

// Count distinct CPs that have stock
$count_res = mysqli_query($db_conn, "
    SELECT COUNT(DISTINCT cps.channel_partner_id) AS total
    FROM channel_partner_stock cps
    JOIN channel_partners cp ON cp.id = cps.channel_partner_id
    WHERE $where_sql
");
$total_records = $count_res ? (int)mysqli_fetch_assoc($count_res)['total'] : 0;
$total_pages   = max(1, (int)ceil($total_records / $records_per_page));

// Paginated CP list
$cp_rows = [];
$list_res = mysqli_query($db_conn, "
    SELECT cp.id AS cp_db_id, cp.cp_id, cp.name AS cp_name, cp.mobile
    FROM channel_partners cp
    WHERE $where_sql
      AND EXISTS (SELECT 1 FROM channel_partner_stock cps WHERE cps.channel_partner_id = cp.id)
    ORDER BY cp.name ASC
    LIMIT " . (int)$records_per_page . " OFFSET " . (int)$offset
);
if ($list_res) while ($row = mysqli_fetch_assoc($list_res)) $cp_rows[] = $row;

// Stock quantities for these CPs
$stock_data = [];
if (!empty($cp_rows)) {
    $cp_db_ids = implode(',', array_map(fn($r) => (int)$r['cp_db_id'], $cp_rows));
    $stk_res   = mysqli_query($db_conn, "
        SELECT channel_partner_id, product_id, input_qty, closing_qty
        FROM channel_partner_stock
        WHERE channel_partner_id IN ($cp_db_ids)
    ");
    if ($stk_res) while ($row = mysqli_fetch_assoc($stk_res))
        $stock_data[$row['channel_partner_id']][$row['product_id']] = $row;
}

// CP list for dropdown
$cp_list = [];
$cp_list_res = mysqli_query($db_conn, "SELECT cp_id, name FROM channel_partners WHERE is_active = 1 ORDER BY name ASC LIMIT 1000");
if ($cp_list_res) while ($r = mysqli_fetch_assoc($cp_list_res)) $cp_list[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CP Stock : <?php echo $business_name; ?></title>

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
        #overflowon { width: 100%; overflow-x: auto; }

        .table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 600; white-space: nowrap; font-size: 14px;
            padding: 12px 8px; border-color: #dee2e6; color: #495057;
        }
        .table td {
            white-space: nowrap; font-size: 13px; padding: 10px 8px;
            vertical-align: middle; border-color: #dee2e6;
        }
        .product-col {
            background: linear-gradient(135deg, #e3f2fd 0%, #f0f8ff 100%);
            text-align: center; min-width: 80px; font-weight: 500;
        }
        .stock-positive { color: #28a745; font-weight: 600; }
        .stock-zero     { color: #dc3545; font-weight: 600; }
        .stock-low      { color: #ffc107; font-weight: 600; }
        .table-bordered { border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; }
        .table-hover tbody tr:hover { background-color: rgba(0,123,255,0.05); }

        .card { border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.08); border-radius: 12px; }
        .card-header { background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%); border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0; }

        .pagination .page-link { border-radius: 6px; margin: 0 2px; border: none; background: #f8f9fa; color: #495057; font-weight: 500; }
        .pagination .page-item.active .page-link { background: #0d6efd; color: #fff; box-shadow: 0 2px 4px rgba(13,110,253,.3); }
        .pagination .page-link:hover { background: #e9ecef; }

        .active-filter-bar {
            background: #eff6ff; border-left: 4px solid #3b82f6;
            border-radius: 6px; padding: 8px 14px;
            font-size: 13px; display: flex; align-items: center; gap: 10px;
        }

        .select2-container--default .select2-selection--single {
            border-radius: 4px; border: 1px solid #ced4da;
            height: auto; padding: 6px 10px; font-size: 0.875rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 1.5; padding: 0; color: #495057; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 100%; top: 0; right: 6px; }
        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--single { border-color: #86b7fe; box-shadow: 0 0 0 .2rem rgba(13,110,253,.25); }
        .select2-dropdown { border: 1px solid #ced4da; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,.1); font-size: .875rem; }
        .select2-search--dropdown .select2-search__field { border: 1px solid #ced4da; border-radius: 4px; padding: 5px 8px; }
        .select2-container { width: 100% !important; }

        .filter-hint { font-size: 12px; color: #6c757d; margin-top: 4px; }
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

                    <!-- Header -->
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble">
                                        <tr>
                                            <td>CP Stock</td>
                                            <td><a href="manage-channel-partner">&#8592;&nbsp;Channel&nbsp;Partners</a></td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Card -->
                    <div class="row mb-3">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <form method="post" action="<?= $_SERVER['PHP_SELF']; ?>" id="filterForm">

                                        <div class="row align-items-end g-3">
                                            <div class="col-md-4">
                                                <label class="form-label mb-1">Filter by Channel Partner</label>
                                                <select name="cp_id" id="cp_filter" class="form-control">
                                                    <option value="">— All Channel Partners —</option>
                                                    <?php foreach ($cp_list as $cp): ?>
                                                    <option value="<?= htmlspecialchars($cp['cp_id']); ?>"
                                                        <?= ($selected_cp_id === $cp['cp_id']) ? 'selected' : ''; ?>>
                                                        <?= htmlspecialchars($cp['name']); ?> (<?= htmlspecialchars($cp['cp_id']); ?>)
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="filter-hint">Select a CP to filter, or leave blank to see all.</div>
                                            </div>
                                            <div class="col-md-auto">
                                                <button type="submit" name="clear_all" class="btn btn-sm btn-outline-secondary">
                                                    <i class="material-icons" style="font-size:15px;vertical-align:middle;">refresh</i>
                                                    Reset
                                                </button>
                                            </div>
                                        </div>

                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Results -->
                    <div class="row">
                        <div class="col">
                            <div class="card">

                                <?php if (!empty($cp_rows)): ?>

                                <!-- Toolbar -->
                                <div class="card-header py-3">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

                                        <form method="post" action="<?= $_SERVER['PHP_SELF']; ?>"
                                              class="d-flex align-items-center gap-2">
                                            <input type="hidden" name="cp_id" value="<?= htmlspecialchars($selected_cp_id, ENT_QUOTES); ?>">
                                            <label class="mb-0 text-muted">Show:</label>
                                            <select name="records_per_page" class="form-select form-select-sm"
                                                    style="width:auto" onchange="this.form.submit()">
                                                <option value="20"  <?= $records_per_page == 20  ? 'selected' : ''; ?>>20</option>
                                                <option value="40"  <?= $records_per_page == 40  ? 'selected' : ''; ?>>40</option>
                                                <option value="60"  <?= $records_per_page == 60  ? 'selected' : ''; ?>>60</option>
                                                <option value="100" <?= $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                                            </select>
                                            <label class="mb-0 text-muted">entries</label>
                                        </form>

                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <?php if ($selected_cp_name): ?>
                                            <div class="active-filter-bar">
                                                <i class="material-icons" style="font-size:16px;color:#3b82f6;">person</i>
                                                <strong><?= htmlspecialchars($selected_cp_name); ?></strong>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted small">All Channel Partners &mdash; <?= $total_records; ?> with stock</span>
                                            <?php endif; ?>
                                            <div class="text-muted small">Page <?= $page; ?> of <?= $total_pages; ?></div>
                                        </div>

                                    </div>
                                </div>

                                <div class="card-body px-3 pt-0 pb-2">
                                    <div id="overflowon">
                                        <table class="table table-bordered table-hover table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th rowspan="2">#</th>
                                                    <th rowspan="2">Channel Partner</th>
                                                    <th colspan="<?= count($products); ?>"
                                                        style="text-align:center; background:#e3f2fd;">
                                                        Product Stock (Closing Qty)
                                                    </th>
                                                    <th rowspan="2">Total</th>
                                                </tr>
                                                <tr>
                                                    <?php foreach ($products as $pr_id => $pr_name): ?>
                                                    <th class="product-col" title="<?= htmlspecialchars($pr_name); ?>">
                                                        <?= htmlspecialchars(strlen($pr_name) > 18 ? substr($pr_name, 0, 15) . '...' : $pr_name); ?>
                                                    </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $serial         = $offset + 1;
                                            $product_totals = array_fill_keys(array_keys($products), 0);
                                            $grand_total    = 0;
                                            foreach ($cp_rows as $cp):
                                                $cp_db_id  = $cp['cp_db_id'];
                                                $cp_stock  = $stock_data[$cp_db_id] ?? [];
                                                $row_total = 0;
                                            ?>
                                            <tr>
                                                <td class="text-muted"><?= $serial++; ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($cp['cp_name']); ?></strong><br>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($cp['cp_id']); ?>
                                                        &nbsp;&middot;&nbsp;
                                                        <?= htmlspecialchars($cp['mobile']); ?>
                                                    </small>
                                                </td>

                                                <?php foreach ($products as $pr_id => $pr_name):
                                                    $stk     = $cp_stock[$pr_id] ?? null;
                                                    $closing = $stk ? (int)$stk['closing_qty'] : 0;
                                                    $product_totals[$pr_id] += $closing;
                                                    $row_total += $closing;
                                                    $cls = 'stock-zero';
                                                    if ($closing > 10)    $cls = 'stock-positive';
                                                    elseif ($closing > 0) $cls = 'stock-low';
                                                ?>
                                                <td align="center" class="product-col"
                                                    title="Input: <?= $stk ? $stk['input_qty'] : 0; ?>">
                                                    <?php if ($closing > 0): ?>
                                                        <strong class="<?= $cls; ?>"><?= $closing; ?></strong>
                                                    <?php else: ?>
                                                        <span style="color:#ccc;">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endforeach; ?>

                                                <td align="center">
                                                    <strong class="<?= $row_total > 0 ? 'stock-positive' : 'stock-zero'; ?>">
                                                        <?= $row_total; ?>
                                                    </strong>
                                                </td>
                                            </tr>
                                            <?php
                                                $grand_total += $row_total;
                                            endforeach;
                                            ?>
                                            </tbody>
                                            <tfoot>
                                                <tr style="background:#e9ecef; font-weight:bold;">
                                                    <th colspan="2" align="right">Total:</th>
                                                    <?php foreach ($product_totals as $total): ?>
                                                    <th align="center" class="product-col"><?= $total; ?></th>
                                                    <?php endforeach; ?>
                                                    <th align="center"><?= $grand_total; ?></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <?php if ($total_pages > 1): ?>
                                    <nav class="mt-3 px-3">
                                        <ul class="pagination justify-content-center">
                                            <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page - 1; ?>">Previous</a>
                                            </li>
                                            <?php endif; ?>
                                            <?php
                                            $start_page = max(1, $page - 2);
                                            $end_page   = min($total_pages, $page + 2);
                                            if ($start_page > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                                if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                            }
                                            for ($i = $start_page; $i <= $end_page; $i++):
                                            ?>
                                            <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?= $i; ?>"><?= $i; ?></a>
                                            </li>
                                            <?php endfor;
                                            if ($end_page < $total_pages) {
                                                if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                                            }
                                            ?>
                                            <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page + 1; ?>">Next</a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                        <p class="text-center text-muted small pb-2">
                                            Showing <?= $offset + 1; ?> – <?= min($offset + $records_per_page, $total_records); ?>
                                            of <?= $total_records; ?> entries
                                        </p>
                                    </nav>
                                    <?php endif; ?>

                                </div>

                                <?php else: ?>
                                <div class="card-body">
                                    <div class="text-center py-4 text-muted">
                                        <i class="material-icons" style="font-size:40px;color:#ced4da;display:block;margin-bottom:8px;">inventory_2</i>
                                        <?= $selected_cp_id ? 'No stock found for the selected channel partner.' : 'No CP stock data found.'; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

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
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script>
$(function () {
    $('#cp_filter').select2({
        placeholder: '— All Channel Partners —',
        allowClear: true,
        width: '100%'
    }).on('change', function () {
        $('#filterForm').submit();
    });
});
</script>
</body>
</html>
