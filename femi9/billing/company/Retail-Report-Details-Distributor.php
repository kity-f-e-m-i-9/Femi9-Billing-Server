<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

ob_start();

include("checksession.php");
include("config.php");

/**
 * Sanitizes output for HTML context
 */
function h(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Redirects and exits
 */
function redirect(string $location): void {
    ob_clean();
    header("Location: $location", true, 303);
    exit;
}

// Clear all filters if requested
if (isset($_GET['clear_filters']) || isset($_POST['clear_all'])) {
    $session_keys = [
        'dist_report_from_date', 'dist_report_to_date', 'dist_report_seller_type',
        'dist_report_amount_range', 'dist_report_distributor_category',
        'dist_report_super_distributor_category', 'dist_report_records_per_page', 
        'dist_report_search'
    ];
    
    foreach ($session_keys as $key) {
        unset($_SESSION[$key]);
    }
    
    redirect($_SERVER['PHP_SELF']);
}

// Go back handling
if (isset($_GET['go_back']) || isset($_POST['go_back'])) {
    $session_keys = [
        'dist_report_from_date', 'dist_report_to_date', 'dist_report_seller_type',
        'dist_report_amount_range', 'dist_report_distributor_category',
        'dist_report_super_distributor_category', 'dist_report_records_per_page', 
        'dist_report_search'
    ];
    
    foreach ($session_keys as $key) {
        unset($_SESSION[$key]);
    }
    
    redirect('Report-Retail-First-Page.php');
}

// Set charset (do once, not multiple times)
if (!$db_conn->set_charset('utf8mb4')) {
    die("Error loading character set utf8mb4: " . $db_conn->error);
}

// Date range handling
$to_date = date('Y-m-d');
$from_date = date('Y-m-d', strtotime('-7 days'));

// Override with POST values
if (isset($_POST['frdate']) && !empty($_POST['frdate'])) {
    $from_date = $_POST['frdate'];
    $_SESSION['dist_report_from_date'] = $from_date;
}
if (isset($_POST['todate']) && !empty($_POST['todate'])) {
    $to_date = $_POST['todate'];
    $_SESSION['dist_report_to_date'] = $to_date;
}

// Restore from session
if (isset($_SESSION['dist_report_from_date'])) {
    $from_date = $_SESSION['dist_report_from_date'];
}
if (isset($_SESSION['dist_report_to_date'])) {
    $to_date = $_SESSION['dist_report_to_date'];
}

// Validate date format (prevent SQL injection via date fields)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    die('Invalid date format');
}

// Initialize filter variables
$selected_seller_type = '';
$selected_amount_range = '';
$selected_distributor_category = 0;
$selected_super_distributor_category = 0;

// Seller type filter - ONLY distributor and super_distributor
$allowed_seller_types = ['distributor', 'super_distributor'];

if (isset($_POST['seller_type'])) {
    $seller_type_input = $_POST['seller_type'];
    $selected_seller_type = in_array($seller_type_input, $allowed_seller_types) ? $seller_type_input : '';
    
    if ($selected_seller_type) {
        $_SESSION['dist_report_seller_type'] = $selected_seller_type;
        
        // Clear categories when seller type changes
        if ($selected_seller_type !== 'distributor') {
            unset($_SESSION['dist_report_distributor_category']);
            $selected_distributor_category = 0;
        }
        if ($selected_seller_type !== 'super_distributor') {
            unset($_SESSION['dist_report_super_distributor_category']);
            $selected_super_distributor_category = 0;
        }
    } else {
        unset($_SESSION['dist_report_seller_type']);
        unset($_SESSION['dist_report_distributor_category']);
        unset($_SESSION['dist_report_super_distributor_category']);
        $selected_distributor_category = 0;
        $selected_super_distributor_category = 0;
    }
} elseif (isset($_SESSION['dist_report_seller_type'])) {
    $selected_seller_type = $_SESSION['dist_report_seller_type'];
    // Validate from session
    if (!in_array($selected_seller_type, $allowed_seller_types)) {
        $selected_seller_type = '';
        unset($_SESSION['dist_report_seller_type']);
    }
}

// Distributor category filter (only for distributor)
if (isset($_POST['distributor_category'])) {
    $selected_distributor_category = !empty($_POST['distributor_category']) ? (int)$_POST['distributor_category'] : 0;
    if ($selected_distributor_category > 0 && $selected_seller_type === 'distributor') {
        $_SESSION['dist_report_distributor_category'] = $selected_distributor_category;
    } else {
        unset($_SESSION['dist_report_distributor_category']);
        $selected_distributor_category = 0;
    }
} elseif (isset($_SESSION['dist_report_distributor_category']) && $selected_seller_type === 'distributor') {
    $selected_distributor_category = (int)$_SESSION['dist_report_distributor_category'];
}

// Super Distributor category filter (only for super_distributor)
if (isset($_POST['super_distributor_category'])) {
    $selected_super_distributor_category = !empty($_POST['super_distributor_category']) ? (int)$_POST['super_distributor_category'] : 0;
    if ($selected_super_distributor_category > 0 && $selected_seller_type === 'super_distributor') {
        $_SESSION['dist_report_super_distributor_category'] = $selected_super_distributor_category;
    } else {
        unset($_SESSION['dist_report_super_distributor_category']);
        $selected_super_distributor_category = 0;
    }
} elseif (isset($_SESSION['dist_report_super_distributor_category']) && $selected_seller_type === 'super_distributor') {
    $selected_super_distributor_category = (int)$_SESSION['dist_report_super_distributor_category'];
}

// Amount range filter
$allowed_ranges = ['50000-99999', '100000-149999', '150000-above'];

if (isset($_POST['amount_range'])) {
    $range_input = $_POST['amount_range'];
    $selected_amount_range = in_array($range_input, $allowed_ranges) ? $range_input : '';
    
    if ($selected_amount_range) {
        $_SESSION['dist_report_amount_range'] = $selected_amount_range;
    } else {
        unset($_SESSION['dist_report_amount_range']);
    }
} elseif (isset($_SESSION['dist_report_amount_range'])) {
    $selected_amount_range = $_SESSION['dist_report_amount_range'];
    // Validate from session
    if (!in_array($selected_amount_range, $allowed_ranges)) {
        $selected_amount_range = '';
        unset($_SESSION['dist_report_amount_range']);
    }
}

// Build report label
$Report_LABLE = "Distributor & Super Distributor Sales Report";

// Pagination settings
$allowed_per_page = [20, 40, 60];
$records_per_page = 20;

if (isset($_POST['records_per_page'])) {
    $input_per_page = (int)$_POST['records_per_page'];
    $records_per_page = in_array($input_per_page, $allowed_per_page) ? $input_per_page : 20;
    $_SESSION['dist_report_records_per_page'] = $records_per_page;
} elseif (isset($_SESSION['dist_report_records_per_page'])) {
    $session_per_page = (int)$_SESSION['dist_report_records_per_page'];
    $records_per_page = in_array($session_per_page, $allowed_per_page) ? $session_per_page : 20;
}

// Universal search with XSS protection
$search = '';
if (isset($_GET['q'])) {
    $search = trim($_GET['q']);
    $_SESSION['dist_report_search'] = $search;
} elseif (isset($_POST['q'])) {
    $search = trim($_POST['q']);
    $_SESSION['dist_report_search'] = $search;
} elseif (isset($_SESSION['dist_report_search'])) {
    $search = $_SESSION['dist_report_search'];
}

$is_search = ($search !== '');

// Pagination
$page = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
$offset = ($page - 1) * $records_per_page;

// Search mode - fetch all with safety cap
$MAX_SEARCH_ROWS = 5000;
if ($is_search) {
    $page = 1;
    $offset = 0;
    $records_per_page = $MAX_SEARCH_ROWS;
}

$qparam = urlencode($search);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($Report_LABLE) ?> : <?= h($business_name ?? 'Business') ?></title>

    <!-- Styles -->
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
        #overflowon {
            width: 100%; 
            overflow-x: auto;
        }

        .table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 600;
            white-space: nowrap;
            font-size: 14px;
            padding: 12px 8px;
            border-color: #dee2e6;
            color: #495057;
        }

        .table td {
            white-space: nowrap;
            font-size: 13px;
            padding: 10px 8px;
            vertical-align: middle;
            border-color: #dee2e6;
        }

        .product-col {
            background: linear-gradient(135deg, #e3f2fd 0%, #f0f8ff 100%);
            text-align: center;
            min-width: 80px;
            font-weight: 500;
        }

        .table-bordered {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0,123,255,0.05);
            transition: background-color 0.2s ease;
        }

        .card {
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border-radius: 12px;
        }

        .card-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-bottom: 1px solid #e9ecef;
            border-radius: 12px 12px 0 0;
        }

        .pagination .page-link {
            border-radius: 6px;
            margin: 0 2px;
            border: none;
            background: #f8f9fa;
            color: #495057;
            font-weight: 500;
        }

        .pagination .page-item.active .page-link {
            background: #0d6efd;
            color: white;
            box-shadow: 0 2px 4px rgba(13,110,253,0.3);
        }

        .pagination .page-link:hover {
            background: #e9ecef;
            color: #495057;
        }

        .form-select-sm {
            border-radius: 6px;
            border-color: #ced4da;
            font-size: 13px;
        }

        .alert {
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .table th, .table td {
                font-size: 12px;
                padding: 8px 4px;
            }
            
            .card-header .d-flex {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include "logo.php"; ?>
            <?php include "femi_menu.php"; ?>
        </div>
        
        <div class="app-container">
            <?php include "app-header.php"; ?>
            
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
                                                <td><?= h($Report_LABLE) ?></td>
                                                <td><a href="?go_back=1">&#8592;&nbsp;Go&nbsp;Back</a></td>
                                                <td>
                                                  <form method="post" action="export_distributor_report_xlsx.php" target="_blank" class="d-inline">
                                                    <input type="hidden" name="frdate" value="<?= h($from_date) ?>">
                                                    <input type="hidden" name="todate" value="<?= h($to_date) ?>">
                                                    <input type="hidden" name="seller_type" value="<?= h($selected_seller_type) ?>">
                                                    <input type="hidden" name="distributor_category" value="<?= $selected_distributor_category ?>">
                                                    <input type="hidden" name="super_distributor_category" value="<?= $selected_super_distributor_category ?>">
                                                    <input type="hidden" name="amount_range" value="<?= h($selected_amount_range) ?>">
                                                    <input type="hidden" name="q" value="<?= h($search) ?>">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                      Export
                                                    </button>
                                                  </form>
                                                </td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Filters Form -->
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Advanced Filters</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="<?= h($_SERVER['PHP_SELF']) ?>" id="filterForm">
                                            
                                            <!-- Row 1: Date and Seller Type Filters -->
                                            <div class="row mb-3">
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">From Date <span class="text-danger">*</span></label>
                                                    <input type="date" name="frdate" value="<?= h($from_date) ?>" class="form-control" required>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">To Date <span class="text-danger">*</span></label>
                                                    <input type="date" name="todate" value="<?= h($to_date) ?>" class="form-control" required>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">Seller Type</label>
                                                    <select name="seller_type" id="seller_type_filter" class="form-control">
                                                        <option value="">All (Distributor & Super Distributor)</option>
                                                        <option value="distributor" <?= $selected_seller_type === 'distributor' ? 'selected' : '' ?>>Distributor</option>
                                                        <option value="super_distributor" <?= $selected_seller_type === 'super_distributor' ? 'selected' : '' ?>>Super Distributor</option>
                                                    </select>
                                                </div>
                                                
                                                <!-- Distributor Category -->
                                                <div class="col-md-3 col-sm-6 mb-2" id="distributor_category_container" style="display:<?= $selected_seller_type === 'distributor' ? 'block' : 'none' ?>;">
                                                    <label class="form-label">Distributor Category</label>
                                                    <select name="distributor_category" id="distributor_category_filter" class="form-control">
                                                        <option value="">All Categories</option>
                                                        <?php
                                                        $stmt_dcat = $db_conn->prepare("SELECT id, name FROM distributor_category ORDER BY name ASC");
                                                        $stmt_dcat->execute();
                                                        $dcat_result = $stmt_dcat->get_result();
                                                        
                                                        while ($dcat = $dcat_result->fetch_assoc()) {
                                                            $selected_attr = ($selected_distributor_category === (int)$dcat['id']) ? 'selected' : '';
                                                            echo '<option value="' . (int)$dcat['id'] . '" ' . $selected_attr . '>' . h($dcat['name']) . '</option>';
                                                        }
                                                        $stmt_dcat->close();
                                                        ?>
                                                    </select>
                                                </div>
                                                
                                                <!-- Super Distributor Category -->
                                                <div class="col-md-3 col-sm-6 mb-2" id="super_distributor_category_container" style="display:<?= $selected_seller_type === 'super_distributor' ? 'block' : 'none' ?>;">
                                                    <label class="form-label">Super Dist. Category</label>
                                                    <select name="super_distributor_category" id="super_distributor_category_filter" class="form-control">
                                                        <option value="">All Categories</option>
                                                        <?php
                                                        $stmt_sdcat = $db_conn->prepare("SELECT id, name FROM super_distributor_category ORDER BY name ASC");
                                                        $stmt_sdcat->execute();
                                                        $sdcat_result = $stmt_sdcat->get_result();
                                                        
                                                        while ($sdcat = $sdcat_result->fetch_assoc()) {
                                                            $selected_attr = ($selected_super_distributor_category === (int)$sdcat['id']) ? 'selected' : '';
                                                            echo '<option value="' . (int)$sdcat['id'] . '" ' . $selected_attr . '>' . h($sdcat['name']) . '</option>';
                                                        }
                                                        $stmt_sdcat->close();
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <!-- Row 2: Amount Range and Action Buttons -->
                                            <div class="row mb-3">
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">Amount Range</label>
                                                    <select name="amount_range" id="amount_range_filter" class="form-control">
                                                        <option value="">All Amounts</option>
                                                        <option value="50000-99999" <?= $selected_amount_range === '50000-99999' ? 'selected' : '' ?>>₹50,000 - ₹99,999</option>
                                                        <option value="100000-149999" <?= $selected_amount_range === '100000-149999' ? 'selected' : '' ?>>₹1,00,000 - ₹1,49,999</option>
                                                        <option value="150000-above" <?= $selected_amount_range === '150000-above' ? 'selected' : '' ?>>Above ₹1,50,000</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-3 col-sm-12 mb-2">
                                                    <label class="form-label d-none d-md-block">&nbsp;</label>
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        <button type="submit" name="filter_dates" class="btn btn-primary">
                                                            <i class="material-icons">search</i> Apply Filters
                                                        </button>
                                                        <button type="submit" name="clear_all" class="btn btn-secondary">
                                                            <i class="material-icons">refresh</i> Reset All
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Active Filters Display -->
                                            <?php
                                            $active_filters = [];
                                            
                                            if ($selected_seller_type !== '') {
                                                $seller_type_labels = [
                                                    'distributor' => 'Distributor',
                                                    'super_distributor' => 'Super Distributor'
                                                ];
                                                $active_filters[] = "Seller Type: " . ($seller_type_labels[$selected_seller_type] ?? h($selected_seller_type));
                                            }
                                            
                                            if ($selected_distributor_category > 0 && $selected_seller_type === 'distributor') {
                                                $stmt_dcn = $db_conn->prepare("SELECT name FROM distributor_category WHERE id = ?");
                                                $stmt_dcn->bind_param("i", $selected_distributor_category);
                                                $stmt_dcn->execute();
                                                $result_dcn = $stmt_dcn->get_result();
                                                if ($row_dcn = $result_dcn->fetch_assoc()) {
                                                    $active_filters[] = "Distributor Category: " . h($row_dcn['name']);
                                                }
                                                $stmt_dcn->close();
                                            }
                                            
                                            if ($selected_super_distributor_category > 0 && $selected_seller_type === 'super_distributor') {
                                                $stmt_sdcn = $db_conn->prepare("SELECT name FROM super_distributor_category WHERE id = ?");
                                                $stmt_sdcn->bind_param("i", $selected_super_distributor_category);
                                                $stmt_sdcn->execute();
                                                $result_sdcn = $stmt_sdcn->get_result();
                                                if ($row_sdcn = $result_sdcn->fetch_assoc()) {
                                                    $active_filters[] = "Super Dist. Category: " . h($row_sdcn['name']);
                                                }
                                                $stmt_sdcn->close();
                                            }
                                            
                                            if ($selected_amount_range !== '') {
                                                $amount_labels = [
                                                    '50000-99999' => '₹50,000 - ₹99,999',
                                                    '100000-149999' => '₹1,00,000 - ₹1,49,999',
                                                    '150000-above' => 'Above ₹1,50,000'
                                                ];
                                                $active_filters[] = "Amount: " . ($amount_labels[$selected_amount_range] ?? '');
                                            }
                                            
                                            if ($search !== '') {
                                                $active_filters[] = "Search: \"" . h($search) . "\"";
                                            }
                                            
                                            if (!empty($active_filters)):
                                            ?>
                                            <div class="alert alert-success mb-0">
                                                <strong>Active Filters:</strong> <?= implode(' | ', $active_filters) ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Report Table -->
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <!-- Clean Toolbar -->
                                    <div class="card-header py-3 bg-white border-0">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <!-- LEFT: Show entries -->
                                            <form method="post" action="<?= h($_SERVER['PHP_SELF']) ?>" class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="frdate" value="<?= h($from_date) ?>">
                                                <input type="hidden" name="todate" value="<?= h($to_date) ?>">
                                                <input type="hidden" name="seller_type" value="<?= h($selected_seller_type) ?>">
                                                <input type="hidden" name="distributor_category" value="<?= $selected_distributor_category ?>">
                                                <input type="hidden" name="super_distributor_category" value="<?= $selected_super_distributor_category ?>">
                                                <input type="hidden" name="amount_range" value="<?= h($selected_amount_range) ?>">
                                                <input type="hidden" name="page" value="1">
                                                <input type="hidden" name="q" value="<?= h($search) ?>">

                                                <label class="mb-0 text-muted">Show:</label>
                                                <select name="records_per_page" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                                                    <option value="20" <?= $records_per_page === 20 ? 'selected' : '' ?>>20</option>
                                                    <option value="40" <?= $records_per_page === 40 ? 'selected' : '' ?>>40</option>
                                                    <option value="60" <?= $records_per_page === 60 ? 'selected' : '' ?>>60</option>
                                                </select>
                                                <label class="mb-0 text-muted">entries</label>
                                            </form>

                                            <!-- RIGHT: Universal search + page info -->
                                            <div class="d-flex align-items-center gap-3">
                                              <form method="get" action="<?= h($_SERVER['PHP_SELF']) ?>" id="searchForm" class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="page" value="1">
                                                <input type="text"
                                                       name="q"
                                                       value="<?= h($search) ?>"
                                                       class="form-control form-control-sm"
                                                       placeholder="Search seller name, mobile, product..."
                                                       style="min-width:280px">
                                              </form>
                                              <div class="text-muted small">
                                                <?php if ($is_search): ?>
                                                  Showing all matches
                                                <?php else: ?>
                                                  Page <?= $page ?> of <?= $total_pages ?? 1 ?>
                                                <?php endif; ?>
                                              </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
<?php
// ========== DATA FETCHING AND DISPLAY LOGIC ==========

// Get all products efficiently
$products = [];
$stmt_products = $db_conn->prepare("SELECT id, productName FROM products ORDER BY id ASC");
$stmt_products->execute();
$product_result = $stmt_products->get_result();

while ($pr = $product_result->fetch_assoc()) {
    $products[(int)$pr['id']] = $pr['productName'];
}
$stmt_products->close();

// Build filter conditions with prepared statements
$sellers = [];

// Seller type mapping to table names
$seller_table_map = [
    'distributor' => 'distributor',
    'super_distributor' => 'super_distributor'
];

// Determine which seller types to query
$seller_types_to_query = [];
if ($selected_seller_type === '') {
    // Query both types
    $seller_types_to_query = array_keys($seller_table_map);
} else {
    // Query only selected type
    $seller_types_to_query = [$selected_seller_type];
}

// Fetch sellers from each applicable table
foreach ($seller_types_to_query as $type) {
    $table = $seller_table_map[$type];
    
    // Build query based on seller type
    if ($type === 'distributor') {
        // Distributor with category (category_id directly in table)
        $sql = "SELECT d.temp_id as seller_id,
                       d.name as seller_name,
                       d.mobile_number as seller_mobile,
                       ? as seller_type,
                       dc.name as category_name
                FROM {$table} d
                LEFT JOIN distributor_category dc ON d.category_id = dc.id
                WHERE 1=1";
        
        $params = [$type];
        $types = "s";
        
        // Add category filter
        if ($selected_distributor_category > 0) {
            $sql .= " AND d.category_id = ?";
            $params[] = $selected_distributor_category;
            $types .= "i";
        }
        
    } else {
        // Super Distributor with category (category_id directly in table)
        $sql = "SELECT sd.temp_id as seller_id,
                       sd.name as seller_name,
                       sd.mobile_number as seller_mobile,
                       ? as seller_type,
                       sdc.name as category_name
                FROM {$table} sd
                LEFT JOIN super_distributor_category sdc ON sd.category_id = sdc.id
                WHERE 1=1";
        
        $params = [$type];
        $types = "s";
        
        // Add category filter
        if ($selected_super_distributor_category > 0) {
            $sql .= " AND sd.category_id = ?";
            $params[] = $selected_super_distributor_category;
            $types .= "i";
        }
    }
    
    // Execute query
    $stmt_sellers = $db_conn->prepare($sql);
    if (!$stmt_sellers) {
        echo "<div class='alert alert-danger'>Prepare failed for {$type}: " . $db_conn->error . "</div>";
        continue;
    }
    
    $stmt_sellers->bind_param($types, ...$params);
    $stmt_sellers->execute();
    $seller_result = $stmt_sellers->get_result();
    
    while ($row = $seller_result->fetch_assoc()) {
        $sellers[] = $row;
    }
    $stmt_sellers->close();
}

// Calculate totals for each seller with prepared statements
$sellers_with_data = [];

foreach ($sellers as $seller) {
    $seller_id = $seller['seller_id'];
    $seller_type = $seller['seller_type'];
    
    // Get total amount using prepared statement
    $stmt_total = $db_conn->prepare("
        SELECT COALESCE(SUM(total), 0) as total_amount,
        COALESCE(SUM(sub_total), 0) as sub_total,
        COALESCE(SUM(courier_charges), 0) as courier_charges
        FROM user_invoice
        WHERE from_user_id = ?
        AND from_user_type = ?
        AND to_user_type = 'shop'
        AND date BETWEEN ? AND ?
        AND sub_total > 0
    ");
    
    $stmt_total->bind_param("ssss", $seller_id, $seller_type, $from_date, $to_date);
    $stmt_total->execute();
    $total_result = $stmt_total->get_result();
    $total_row = $total_result->fetch_assoc();
    $seller['total_amount'] = (float)$total_row['total_amount'];
    $seller['sub_total'] = (float)$total_row['sub_total'];
    $seller['courier_charges'] = (float)$total_row['courier_charges'];
    $stmt_total->close();
    
    // Apply amount range filter
    $include_seller = false;
    if ($selected_amount_range !== '') {
        switch ($selected_amount_range) {
            case '50000-99999':
                $include_seller = ($seller['total_amount'] >= 50000 && $seller['total_amount'] <= 99999);
                break;
            case '100000-149999':
                $include_seller = ($seller['total_amount'] >= 100000 && $seller['total_amount'] <= 149999);
                break;
            case '150000-above':
                $include_seller = ($seller['total_amount'] >= 150000);
                break;
        }
    } else {
        $include_seller = ($seller['total_amount'] > 0);
    }
    
    // Only include sellers with sales and matching amount filter
    if ($include_seller) {
        // Apply search filter if exists
        if ($search !== '') {
            if (mb_stripos($seller['seller_name'], $search) !== false || 
                mb_stripos($seller['seller_mobile'], $search) !== false ||
                mb_stripos($seller['seller_type'], $search) !== false) {
                $sellers_with_data[] = $seller;
            }
        } else {
            $sellers_with_data[] = $seller;
        }
    }
}

// Sort by total amount DESCENDING
usort($sellers_with_data, function($a, $b) {
    return $b['total_amount'] <=> $a['total_amount'];
});

// Pagination
$total_records = count($sellers_with_data);
$total_pages = max(1, (int)ceil($total_records / max(1, $records_per_page)));

if ($is_search) {
    $total_pages = 1;
    $page = 1;
    $offset = 0;
    $sellers_paginated = $sellers_with_data;
} else {
    $sellers_paginated = array_slice($sellers_with_data, $offset, $records_per_page);
}

// Get product quantities for paginated sellers using prepared statement
$seller_product_quantities = [];

foreach ($sellers_paginated as $seller) {
    $seller_id = $seller['seller_id'];
    $seller_type = $seller['seller_type'];
    
    $stmt_qty = $db_conn->prepare("
        SELECT uii.pr_id, SUM(uii.qty) as total_qty
        FROM user_invoice ui
        INNER JOIN user_invoice_items uii ON ui.inv_id = uii.inv_id
        WHERE ui.from_user_id = ?
        AND ui.from_user_type = ?
        AND ui.to_user_type = 'shop'
        AND ui.date BETWEEN ? AND ?
        AND ui.sub_total > 0
        GROUP BY uii.pr_id
    ");
    
    $stmt_qty->bind_param("ssss", $seller_id, $seller_type, $from_date, $to_date);
    $stmt_qty->execute();
    $qty_result = $stmt_qty->get_result();
    
    while ($qty_row = $qty_result->fetch_assoc()) {
        $seller_product_quantities[$seller_id][(int)$qty_row['pr_id']] = (int)$qty_row['total_qty'];
    }
    $stmt_qty->close();
}

if (!empty($sellers_paginated)) {
?>

<div id="overflowon">
    <table class="table table-bordered table-hover table-sm">
        <thead>
            <tr>
                <th rowspan="2">S.No</th>
                <th rowspan="2">Seller Name</th>
                <th rowspan="2">Seller Type</th>
                <th rowspan="2">Category</th>
                <th rowspan="2">Mobile Number</th>
                <th rowspan="2">Sub Total</th>
                <th rowspan="2">Courier Charges</th>
                <th rowspan="2">Total Amount</th>
                <th colspan="<?= count($products) ?>" style="text-align:center; background:#e3f2fd;">Product Quantities</th>
            </tr>
            <tr>
                <?php foreach ($products as $pr_id => $pr_name): ?>
                <th class="product-col" title="<?= h($pr_name) ?>">
                    <?php 
                    $short_name = mb_strlen($pr_name) > 30 ? mb_substr($pr_name, 0, 27) . '...' : $pr_name;
                    echo h($short_name);
                    ?>
                </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php
                $serial = $offset + 1;
                $grand_total = 0.0;
                $product_totals = array_fill_keys(array_keys($products), 0);
                
                $seller_type_labels = [
                    'distributor' => 'Distributor',
                    'super_distributor' => 'Super Distributor'
                ];
                
                foreach ($sellers_paginated as $seller):
                    $seller_type_display = $seller_type_labels[$seller['seller_type']] ?? ucwords(str_replace('_', ' ', $seller['seller_type']));
                    $grand_total += $seller['total_amount'];
                    $grand_subtotal += $seller['sub_total'];
                    $grand_courier += $seller['courier_charges'];
                    $category_display = !empty($seller['category_name']) ? h($seller['category_name']) : '-';
            ?>
            <tr>
                <td><?= $serial++ ?></td>
                <td><strong><?= h($seller['seller_name']) ?></strong></td>
                <td><?= h($seller_type_display) ?></td>
                <td><?= $category_display ?></td>
                <td><?= h($seller['seller_mobile']) ?></td>
                <td align="right"><strong>₹<?= number_format($seller['sub_total'], 2) ?></strong></td>
                <td align="right"><strong>₹<?= number_format($seller['courier_charges'], 2) ?></strong></td>
                <td align="right"><strong>₹<?= number_format($seller['total_amount'], 2) ?></strong></td>
                
                <?php foreach ($products as $pr_id => $pr_name): 
                    $qty = $seller_product_quantities[$seller['seller_id']][$pr_id] ?? 0;
                    $product_totals[$pr_id] += $qty;
                ?>
                <td align="center" class="product-col">
                    <?php if ($qty > 0): ?>
                        <strong><?= $qty ?></strong>
                    <?php else: ?>
                        <span style="color:#ccc;">-</span>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#e9ecef; font-weight:bold;">
                <th colspan="5" align="right">Page Total:</th>
                <th align="right">₹<?= number_format($grand_subtotal, 2) ?></th>
                <th align="right">₹<?= number_format($grand_courier, 2) ?></th>
                <th align="right">₹<?= number_format($grand_total, 2) ?></th>
                <?php foreach ($product_totals as $pr_id => $total): ?>
                <th align="center" class="product-col"><?= $total ?></th>
                <?php endforeach; ?>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Pagination -->
<?php if (!$is_search && $total_pages > 1): ?>
<nav aria-label="Page navigation" class="mt-3">
    <ul class="pagination justify-content-center">
        <?php if ($page > 1): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?= ($page - 1) ?>&q=<?= $qparam ?>">Previous</a>
        </li>
        <?php endif; ?>
        
        <?php 
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        if ($start_page > 1) {
            echo '<li class="page-item"><a class="page-link" href="?page=1&q=' . $qparam . '">1</a></li>';
            if ($start_page > 2) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        for ($i = $start_page; $i <= $end_page; $i++): 
        ?>
        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>&q=<?= $qparam ?>"><?= $i ?></a>
        </li>
        <?php 
        endfor; 
        
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&q=' . $qparam . '">' . $total_pages . '</a></li>';
        }
        ?>
        
        <?php if ($page < $total_pages): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?= ($page + 1) ?>&q=<?= $qparam ?>">Next</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<p class="text-center text-muted mt-3">
    <?php if ($is_search): ?>
        Showing all <?= $total_records ?> matching entries
    <?php else: ?>
        Showing <?= $offset + 1 ?> to <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> entries
    <?php endif; ?>
</p>
<?php
} else {
    echo '<div class="alert alert-warning">No distributors or super distributors found for the selected filters and date range.</div>';
}
?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show/hide category containers based on seller type
            const sellerTypeEl = document.getElementById('seller_type_filter');
            if (sellerTypeEl) {
                sellerTypeEl.addEventListener('change', function() {
                    const distributorCategoryContainer = document.getElementById('distributor_category_container');
                    const superDistributorCategoryContainer = document.getElementById('super_distributor_category_container');
                    
                    // Hide all category containers
                    distributorCategoryContainer.style.display = 'none';
                    superDistributorCategoryContainer.style.display = 'none';
                    
                    // Clear all category values
                    document.getElementById('distributor_category_filter').value = '';
                    document.getElementById('super_distributor_category_filter').value = '';
                    
                    // Show relevant category container
                    if (this.value === 'distributor') {
                        distributorCategoryContainer.style.display = 'block';
                    } else if (this.value === 'super_distributor') {
                        superDistributorCategoryContainer.style.display = 'block';
                    }
                });
            }
        });
    </script>
</body>
</html>