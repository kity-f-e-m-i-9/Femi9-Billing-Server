<?php 


ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

include("checksession.php");
include("config.php");

// Clear all filters if requested
if(isset($_GET['clear_filters']) || isset($_POST['clear_all'])) {
    ob_clean();
    
    unset($_SESSION['retail_report_from_date']);
    unset($_SESSION['retail_report_to_date']);
    unset($_SESSION['retail_report_district_id']);
    unset($_SESSION['retail_report_taluk_id']);
    unset($_SESSION['retail_report_seller_type']);
    unset($_SESSION['retail_report_amount_range']);
    unset($_SESSION['retail_report_stockist_category']);
    unset($_SESSION['retail_report_records_per_page']);
    unset($_SESSION['retail_report_search']);
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if(isset($_GET['go_back']) || isset($_POST['go_back'])) {
    ob_clean();
    
    unset($_SESSION['retail_report_from_date']);
    unset($_SESSION['retail_report_to_date']);
    unset($_SESSION['retail_report_district_id']);
    unset($_SESSION['retail_report_taluk_id']);
    unset($_SESSION['retail_report_seller_type']);
    unset($_SESSION['retail_report_amount_range']);
    unset($_SESSION['retail_report_stockist_category']);
    unset($_SESSION['retail_report_records_per_page']);
    unset($_SESSION['retail_report_state_id']);
    unset($_SESSION['retail_report_search']);
    
    header("Location: Report-Retail-First-Page.php");
    exit;
}

mysqli_set_charset($db_conn, 'utf8mb4');
mysqli_query($db_conn, "SET collation_connection = 'utf8mb4_general_ci'");
mysqli_query($db_conn, "SET collation_server = 'utf8mb4_general_ci'");

// Capture state_id from POST or SESSION
$selected_state_id = 0;
$state_name = "";

if(isset($_POST['state_id']) && !empty($_POST['state_id'])) {
    $selected_state_id = (int)$_POST['state_id'];
    
    if(isset($_SESSION['retail_report_state_id']) && $_SESSION['retail_report_state_id'] != $selected_state_id) {
        unset($_SESSION['retail_report_district_id']);
        unset($_SESSION['retail_report_taluk_id']);
        unset($_SESSION['retail_report_seller_type']);
        unset($_SESSION['retail_report_amount_range']);
        unset($_SESSION['retail_report_stockist_category']);
    }
    
    $_SESSION['retail_report_state_id'] = $selected_state_id;
} elseif(isset($_SESSION['retail_report_state_id'])) {
    $selected_state_id = (int)$_SESSION['retail_report_state_id'];
}

// Get state name
if($selected_state_id > 0) {
    $stmt = $db_conn->prepare("SELECT st_name FROM state WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $selected_state_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if($row = $result->fetch_assoc()) {
        $state_name = $row['st_name'];
    }
    $stmt->close();
}

// Calculate last 7 days date range (default)
$to_date = date('Y-m-d');
$from_date = date('Y-m-d', strtotime('-7 days'));

// Override if custom dates provided via form
if(isset($_POST['frdate']) && !empty($_POST['frdate'])) {
    $from_date = $_POST['frdate'];
    $_SESSION['retail_report_from_date'] = $from_date;
}
if(isset($_POST['todate']) && !empty($_POST['todate'])) {
    $to_date = $_POST['todate'];
    $_SESSION['retail_report_to_date'] = $to_date;
}

// Restore from session if available
if(isset($_SESSION['retail_report_from_date'])) {
    $from_date = $_SESSION['retail_report_from_date'];
}
if(isset($_SESSION['retail_report_to_date'])) {
    $to_date = $_SESSION['retail_report_to_date'];
}

// Capture filter values from POST or SESSION
$selected_district = 0;
$selected_taluk = 0;
$selected_seller_type = '';
$selected_amount_range = '';
$selected_stockist_category = 0;

// District filter
if(isset($_POST['district_id'])) {
    $selected_district = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : 0;
    if($selected_district > 0) {
        $_SESSION['retail_report_district_id'] = $selected_district;
    } else {
        unset($_SESSION['retail_report_district_id']);
    }
} elseif(isset($_SESSION['retail_report_district_id'])) {
    $selected_district = (int)$_SESSION['retail_report_district_id'];
}

// Taluk filter
if(isset($_POST['taluk_id'])) {
    $selected_taluk = !empty($_POST['taluk_id']) ? (int)$_POST['taluk_id'] : 0;
    if($selected_taluk > 0) {
        $_SESSION['retail_report_taluk_id'] = $selected_taluk;
    } else {
        unset($_SESSION['retail_report_taluk_id']);
    }
} elseif(isset($_SESSION['retail_report_taluk_id'])) {
    $selected_taluk = (int)$_SESSION['retail_report_taluk_id'];
}

// Seller type filter (only super_stockiest and stockiest)
if(isset($_POST['seller_type'])) {
    $selected_seller_type = !empty($_POST['seller_type']) ? $_POST['seller_type'] : '';
    if($selected_seller_type) {
        $_SESSION['retail_report_seller_type'] = $selected_seller_type;
        
        // Clear stockist category if seller type changed to non-stockist
        if($selected_seller_type != 'stockiest') {
            unset($_SESSION['retail_report_stockist_category']);
            $selected_stockist_category = 0;
        }
    } else {
        unset($_SESSION['retail_report_seller_type']);
        unset($_SESSION['retail_report_stockist_category']);
        $selected_stockist_category = 0;
    }
} elseif(isset($_SESSION['retail_report_seller_type'])) {
    $selected_seller_type = $_SESSION['retail_report_seller_type'];
}

// Stockist category filter (only applicable when seller_type is 'stockiest')
if(isset($_POST['stockist_category'])) {
    $selected_stockist_category = !empty($_POST['stockist_category']) ? (int)$_POST['stockist_category'] : 0;
    if($selected_stockist_category > 0 && $selected_seller_type == 'stockiest') {
        $_SESSION['retail_report_stockist_category'] = $selected_stockist_category;
    } else {
        unset($_SESSION['retail_report_stockist_category']);
        $selected_stockist_category = 0;
    }
} elseif(isset($_SESSION['retail_report_stockist_category']) && $selected_seller_type == 'stockiest') {
    $selected_stockist_category = (int)$_SESSION['retail_report_stockist_category'];
}

// Amount range filter
if(isset($_POST['amount_range'])) {
    $selected_amount_range = !empty($_POST['amount_range']) ? $_POST['amount_range'] : '';
    if($selected_amount_range) {
        $_SESSION['retail_report_amount_range'] = $selected_amount_range;
    } else {
        unset($_SESSION['retail_report_amount_range']);
    }
} elseif(isset($_SESSION['retail_report_amount_range'])) {
    $selected_amount_range = $_SESSION['retail_report_amount_range'];
}

// Clear taluk if district changed
if(isset($_POST['district_id']) && isset($_SESSION['retail_report_district_id'])) {
    if($_POST['district_id'] != $_SESSION['retail_report_district_id']) {
        $selected_taluk = 0;
        unset($_SESSION['retail_report_taluk_id']);
    }
}

$Report_LABLE = "Retail Sales Report";
if($state_name) {
    $Report_LABLE .= " - " . $state_name;
}

// Pagination settings
$records_per_page = 20;
if(isset($_POST['records_per_page'])) {
    $records_per_page = (int)$_POST['records_per_page'];
    $_SESSION['retail_report_records_per_page'] = $records_per_page;
} elseif(isset($_SESSION['retail_report_records_per_page'])) {
    $records_per_page = (int)$_SESSION['retail_report_records_per_page'];
}

// Validate records per page
$allowed_values = [20, 40, 60];
if(!in_array($records_per_page, $allowed_values)) {
    $records_per_page = 20;
}

// Universal search (persist across navigation)
$search = '';
if (isset($_GET['q'])) {
    $search = trim($_GET['q']);
    $_SESSION['retail_report_search'] = $search;
} elseif (isset($_POST['q'])) {
    $search = trim($_POST['q']);
    $_SESSION['retail_report_search'] = $search;
} elseif (isset($_SESSION['retail_report_search'])) {
    $search = $_SESSION['retail_report_search'];
}
$search_esc = $db_conn->real_escape_string($search);
$is_search = ($search !== '');

$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$offset = ($page - 1) * $records_per_page;

// When searching, fetch all matches (no pagination), with a safety cap
if ($is_search) {
    $MAX_SEARCH_ROWS = 5000;
    $page = 1;
    $offset = 0;
    $records_per_page = $MAX_SEARCH_ROWS;
}
$qparam = urlencode($search ?? '');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?=$Report_LABLE;?> : <?php echo $business_name;?></title>

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
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>
        
        <div class="app-container">
            <?php include("app-header.php");?>
            
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
                                                <td><?=$Report_LABLE;?></td>
                                                <td><a href="?go_back=1">&#8592;&nbsp;Go&nbsp;Back</a></td>
                                                <td>
                                                  <form method="post" action="export_retail_report_xlsx.php" target="_blank" class="d-inline">
                                                    <input type="hidden" name="state_id"    value="<?=$selected_state_id;?>">
                                                    <input type="hidden" name="frdate"      value="<?=$from_date;?>">
                                                    <input type="hidden" name="todate"      value="<?=$to_date;?>">
                                                    <input type="hidden" name="district_id" value="<?=$selected_district;?>">
                                                    <input type="hidden" name="taluk_id"    value="<?=$selected_taluk;?>">
                                                    <input type="hidden" name="seller_type" value="<?=$selected_seller_type;?>">
                                                    <input type="hidden" name="stockist_category" value="<?=$selected_stockist_category;?>">
                                                    <input type="hidden" name="amount_range" value="<?=$selected_amount_range;?>">
                                                    <input type="hidden" name="q"           value="<?=htmlspecialchars($search, ENT_QUOTES);?>">
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

                        <!-- State Info Alert -->
                        <?php if($state_name == ""): ?>
                        <div class="row">
                            <div class="col">
                                <div class="alert alert-warning">
                                    <strong>No state selected.</strong> Please <a href="?go_back=1">go back</a> and select a state.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Advanced Filters Form -->
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Advanced Filters</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="<?=$_SERVER['PHP_SELF'];?>" id="filterForm">
                                            <input type="hidden" name="state_id" value="<?=$selected_state_id;?>"/>
                                            
                                            <!-- Row 1: Date and Location Filters -->
                                            <div class="row mb-3">
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">From Date <span class="text-danger">*</span></label>
                                                    <input type="date" name="frdate" value="<?=$from_date;?>" class="form-control" required>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">To Date <span class="text-danger">*</span></label>
                                                    <input type="date" name="todate" value="<?=$to_date;?>" class="form-control" required>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">District</label>
                                                    <select name="district_id" id="district_filter" class="form-control">
                                                        <option value="">All Districts</option>
                                                        <?php
                                                        if($selected_state_id > 0) {
                                                            $dist_query = "SELECT DISTINCT id, dist_name FROM district WHERE state_id = ? ORDER BY dist_name ASC";
                                                            $stmt_dist = $db_conn->prepare($dist_query);
                                                            $stmt_dist->bind_param("i", $selected_state_id);
                                                            $stmt_dist->execute();
                                                            $dist_result = $stmt_dist->get_result();
                                                            
                                                            while($dist = $dist_result->fetch_assoc()) {
                                                                $selected_attr = ($selected_district == $dist['id']) ? 'selected' : '';
                                                                echo '<option value="'.$dist['id'].'" '.$selected_attr.'>'.htmlspecialchars($dist['dist_name']).'</option>';
                                                            }
                                                            $stmt_dist->close();
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">Taluk</label>
                                                    <select name="taluk_id" id="taluk_filter" class="form-control">
                                                        <option value="">All Taluks</option>
                                                        <?php
                                                        if(!empty($selected_district)) {
                                                            $taluk_query = "SELECT DISTINCT id, taluk FROM taluk WHERE dist_id = ? ORDER BY taluk ASC";
                                                            $stmt_taluk = $db_conn->prepare($taluk_query);
                                                            $stmt_taluk->bind_param("i", $selected_district);
                                                            $stmt_taluk->execute();
                                                            $taluk_result = $stmt_taluk->get_result();
                                                            
                                                            while($taluk = $taluk_result->fetch_assoc()) {
                                                                $selected_attr = ($selected_taluk == $taluk['id']) ? 'selected' : '';
                                                                echo '<option value="'.$taluk['id'].'" '.$selected_attr.'>'.htmlspecialchars($taluk['taluk']).'</option>';
                                                            }
                                                            $stmt_taluk->close();
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <!-- Row 2: Seller Type, Stockist Category, Amount Range and Action Buttons -->
                                            <div class="row mb-3">
                                                <div class="col-md-2 col-sm-6 mb-2">
                                                    <label class="form-label">Seller Type</label>
                                                    <select name="seller_type" id="seller_type_filter" class="form-control">
                                                        <option value="">All Seller Types</option>
                                                        <option value="super_stockiest" <?= $selected_seller_type == 'super_stockiest' ? 'selected' : ''; ?>>Super Stockist</option>
                                                        <option value="stockiest" <?= $selected_seller_type == 'stockiest' ? 'selected' : ''; ?>>Stockist</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2 col-sm-6 mb-2" id="stockist_category_container" style="display:<?= $selected_seller_type == 'stockiest' ? 'block' : 'none'; ?>;">
                                                    <label class="form-label">Stockist Category</label>
                                                    <select name="stockist_category" id="stockist_category_filter" class="form-control">
                                                        <option value="">All Categories</option>
                                                        <?php
                                                        $cat_query = "SELECT id, catname FROM stockist_category ORDER BY catname ASC";
                                                        $cat_result = mysqli_query($db_conn, $cat_query);
                                                        if($cat_result) {
                                                            while($cat = mysqli_fetch_assoc($cat_result)) {
                                                                $selected_attr = ($selected_stockist_category == $cat['id']) ? 'selected' : '';
                                                                echo '<option value="'.$cat['id'].'" '.$selected_attr.'>'.htmlspecialchars($cat['catname']).'</option>';
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">Amount Range</label>
                                                    <select name="amount_range" id="amount_range_filter" class="form-control">
                                                        <option value="">All Amounts</option>
                                                        <option value="50000-99999" <?= $selected_amount_range == '50000-99999' ? 'selected' : ''; ?>>₹50,000 - ₹99,999</option>
                                                        <option value="100000-149999" <?= $selected_amount_range == '100000-149999' ? 'selected' : ''; ?>>₹1,00,000 - ₹1,49,999</option>
                                                        <option value="150000-above" <?= $selected_amount_range == '150000-above' ? 'selected' : ''; ?>>Above ₹1,50,000</option>
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
                                            if(!empty($selected_district)) {
                                                $dist_name_query = "SELECT dist_name FROM district WHERE id = ?";
                                                $stmt_dn = $db_conn->prepare($dist_name_query);
                                                $stmt_dn->bind_param("i", $selected_district);
                                                $stmt_dn->execute();
                                                $result_dn = $stmt_dn->get_result();
                                                if($row_dn = $result_dn->fetch_assoc()) {
                                                    $active_filters[] = "District: " . $row_dn['dist_name'];
                                                }
                                                $stmt_dn->close();
                                            }
                                            if(!empty($selected_taluk)) {
                                                $taluk_name_query = "SELECT taluk FROM taluk WHERE id = ?";
                                                $stmt_tn = $db_conn->prepare($taluk_name_query);
                                                $stmt_tn->bind_param("i", $selected_taluk);
                                                $stmt_tn->execute();
                                                $result_tn = $stmt_tn->get_result();
                                                if($row_tn = $result_tn->fetch_assoc()) {
                                                    $active_filters[] = "Taluk: " . $row_tn['taluk'];
                                                }
                                                $stmt_tn->close();
                                            }
                                            if(!empty($selected_seller_type)) {
                                                $active_filters[] = "Seller Type: " . ucwords(str_replace('_', ' ', $selected_seller_type));
                                            }
                                            if(!empty($selected_stockist_category) && $selected_seller_type == 'stockiest') {
                                                $cat_name_query = "SELECT catname FROM stockist_category WHERE id = ?";
                                                $stmt_cn = $db_conn->prepare($cat_name_query);
                                                $stmt_cn->bind_param("i", $selected_stockist_category);
                                                $stmt_cn->execute();
                                                $result_cn = $stmt_cn->get_result();
                                                if($row_cn = $result_cn->fetch_assoc()) {
                                                    $active_filters[] = "Category: " . $row_cn['catname'];
                                                }
                                                $stmt_cn->close();
                                            }
                                            if(!empty($selected_amount_range)) {
                                                $amount_labels = [
                                                    '50000-99999' => '₹50,000 - ₹99,999',
                                                    '100000-149999' => '₹1,00,000 - ₹1,49,999',
                                                    '150000-above' => 'Above ₹1,50,000'
                                                ];
                                                $active_filters[] = "Amount: " . $amount_labels[$selected_amount_range];
                                            }
                                            if($search !== '') {
                                                $active_filters[] = "Search: \"" . htmlspecialchars($search) . "\"";
                                            }
                                            
                                            if(!empty($active_filters)):
                                            ?>
                                            <div class="alert alert-success mb-0">
                                                <strong>Active Filters:</strong> <?= implode(' | ', $active_filters); ?>
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
                                            <form method="post" action="<?=$_SERVER['PHP_SELF'];?>" class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="state_id" value="<?=$selected_state_id;?>">
                                                <input type="hidden" name="frdate" value="<?=$from_date;?>">
                                                <input type="hidden" name="todate" value="<?=$to_date;?>">
                                                <input type="hidden" name="district_id" value="<?=$selected_district;?>">
                                                <input type="hidden" name="taluk_id" value="<?=$selected_taluk;?>">
                                                <input type="hidden" name="seller_type" value="<?=$selected_seller_type;?>">
                                                <input type="hidden" name="stockist_category" value="<?=$selected_stockist_category;?>">
                                                <input type="hidden" name="amount_range" value="<?=$selected_amount_range;?>">
                                                <input type="hidden" name="page" value="1">
                                                <input type="hidden" name="q" value="<?=htmlspecialchars($search, ENT_QUOTES);?>">

                                                <label class="mb-0 text-muted">Show:</label>
                                                <select name="records_per_page" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                                                    <option value="20" <?= $records_per_page==20 ? 'selected' : '' ?>>20</option>
                                                    <option value="40" <?= $records_per_page==40 ? 'selected' : '' ?>>40</option>
                                                    <option value="60" <?= $records_per_page==60 ? 'selected' : '' ?>>60</option>
                                                </select>
                                                <label class="mb-0 text-muted">entries</label>
                                            </form>

                                            <!-- RIGHT: Universal search + page info -->
                                            <div class="d-flex align-items-center gap-3">
                                              <form method="get" action="<?=$_SERVER['PHP_SELF'];?>" id="searchForm" class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="page" value="1">
                                                <input type="text"
                                                       name="q"
                                                       value="<?=htmlspecialchars($search, ENT_QUOTES);?>"
                                                       class="form-control form-control-sm"
                                                       placeholder="Search seller name, mobile, product..."
                                                       style="min-width:280px">
                                              </form>
                                              <div class="text-muted small">
                                                <?php if ($is_search): ?>
                                                  Showing all matches
                                                <?php else: ?>
                                                  Page <?=$page;?> of <?=$total_pages ?? 1;?>
                                                <?php endif; ?>
                                              </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
<?php
// Data fetching and display logic
if($selected_state_id > 0) {
    
    // Get all products
    $products = [];
    $product_query = "SELECT id, productName FROM products ORDER BY id ASC";
    $product_result = mysqli_query($db_conn, $product_query);
    
    if($product_result) {
        while($pr = mysqli_fetch_assoc($product_result)) {
            $products[$pr['id']] = $pr['productName'];
        }
    }
    
    // Build filters
    $district_condition = !empty($selected_district) ? " AND district_id = " . (int)$selected_district : "";
    $taluk_condition = !empty($selected_taluk) ? " AND CAST(taluk_id AS UNSIGNED) = " . (int)$selected_taluk : "";
    $category_condition = !empty($selected_stockist_category) && $selected_seller_type == 'stockiest' ? " AND sr.st_cat_id = " . (int)$selected_stockist_category : "";
    
    // Get sellers based on type
    $sellers = [];
    
    if(empty($selected_seller_type) || $selected_seller_type == 'super_stockiest') {
        $query = "SELECT temp_id as seller_id, 
                         CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci as seller_name,
                         CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci as seller_mobile,
                         'super_stockiest' as seller_type,
                         NULL as category_name
                  FROM super_stockiest
                  WHERE state_id = " . (int)$selected_state_id . $district_condition;
        $result = mysqli_query($db_conn, $query);
        if($result) {
            while($row = mysqli_fetch_assoc($result)) {
                $sellers[] = $row;
            }
        }
    }
    
    if(empty($selected_seller_type) || $selected_seller_type == 'stockiest') {
        // Join with stockist_referral to get category
        $query = "SELECT s.temp_id as seller_id,
                         CONVERT(s.name USING utf8mb4) COLLATE utf8mb4_general_ci as seller_name,
                         CONVERT(s.mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci as seller_mobile,
                         'stockiest' as seller_type,
                         sc.catname as category_name
                  FROM stockiest s
                  LEFT JOIN stockist_referral sr ON s.temp_id = sr.stockist_id
                  LEFT JOIN stockist_category sc ON sr.st_cat_id = sc.id
                  WHERE s.state_id = " . (int)$selected_state_id . $district_condition . $taluk_condition . $category_condition;
        $result = mysqli_query($db_conn, $query);
        if($result) {
            while($row = mysqli_fetch_assoc($result)) {
                $sellers[] = $row;
            }
        }
    }
    
    // Now calculate totals for each seller using user_invoice
    $sellers_with_data = [];
    foreach($sellers as $seller) {
        // Get total amount for this seller from user_invoice where buyer is 'shop'
        $total_query = "SELECT COALESCE(SUM(sub_total), 0) as sub_total,
                        COALESCE(SUM(courier_charges), 0) as courier_charges,
                        COALESCE(SUM(total), 0) as total_amount
                        FROM user_invoice
                        WHERE from_user_id = '" . $db_conn->real_escape_string($seller['seller_id']) . "'
                        AND from_user_type = '" . $db_conn->real_escape_string($seller['seller_type']) . "'
                        AND to_user_type = 'shop'
                        AND date BETWEEN '" . $db_conn->real_escape_string($from_date) . "' 
                        AND '" . $db_conn->real_escape_string($to_date) . "'
                        AND sub_total > 0";
        
        $total_result = mysqli_query($db_conn, $total_query);
        if($total_result) {
            $total_row = mysqli_fetch_assoc($total_result);
            $seller['total_amount'] = $total_row['total_amount'];
            $seller['sub_total'] = $total_row['sub_total'];
            $seller['courier_charges'] = $total_row['courier_charges'];
            
            // Apply amount range filter
            $include_seller = false;
            if(!empty($selected_amount_range)) {
                switch($selected_amount_range) {
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
            if($include_seller) {
                // Apply search filter if exists
                if($search_esc !== '') {
                    if(stripos($seller['seller_name'], $search_esc) !== false || 
                       stripos($seller['seller_mobile'], $search_esc) !== false ||
                       stripos($seller['seller_type'], $search_esc) !== false) {
                        $sellers_with_data[] = $seller;
                    }
                } else {
                    $sellers_with_data[] = $seller;
                }
            }
        }
    }
    
    // Sort by total amount ASCENDING (requirement #1)
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
    
    // Get product quantities for paginated sellers from user_invoice_items
    $seller_product_quantities = [];
    foreach($sellers_paginated as $seller) {
        $seller_id = $seller['seller_id'];
        $seller_type = $seller['seller_type'];
        
        $product_qty_query = "
            SELECT uii.pr_id, SUM(uii.qty) as total_qty
            FROM user_invoice ui
            INNER JOIN user_invoice_items uii ON ui.inv_id = uii.inv_id
            WHERE ui.from_user_id = '" . $db_conn->real_escape_string($seller_id) . "'
            AND ui.from_user_type = '" . $db_conn->real_escape_string($seller_type) . "'
            AND ui.to_user_type = 'shop'
            AND ui.date BETWEEN '" . $db_conn->real_escape_string($from_date) . "' 
            AND '" . $db_conn->real_escape_string($to_date) . "'
            AND ui.sub_total > 0
            GROUP BY uii.pr_id
        ";
        
        $qty_result = mysqli_query($db_conn, $product_qty_query);
        if($qty_result) {
            while($qty_row = mysqli_fetch_assoc($qty_result)) {
                $seller_product_quantities[$seller_id][$qty_row['pr_id']] = $qty_row['total_qty'];
            }
        }
    }
    
    if(!empty($sellers_paginated)) {
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
                <th rowspan="2">Courier</th>
                <th rowspan="2">Total Amount</th>
                <th colspan="<?=count($products);?>" style="text-align:center; background:#e3f2fd;">Product Quantities</th>
            </tr>
            <tr>
                <?php foreach($products as $pr_id => $pr_name): ?>
                <th class="product-col" title="<?=htmlspecialchars($pr_name);?>">
                    <?php 
                    $short_name = strlen($pr_name) > 30 ? substr($pr_name, 0, 27) . '...' : $pr_name;
                    echo htmlspecialchars($short_name);
                    ?>
                </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php
                $serial = $offset + 1;
                $grand_total = 0;
                $grand_subtotal = 0;
                $grand_courier = 0;
                $product_totals = array_fill_keys(array_keys($products), 0);
                
                foreach($sellers_paginated as $seller):
                    $seller_type_display = ucwords(str_replace('_', ' ', $seller['seller_type']));
                    $grand_total += $seller['total_amount'];
                    $grand_subtotal += $seller['sub_total'];
                    $grand_courier += $seller['courier_charges'];
                    $category_display = !empty($seller['category_name']) ? htmlspecialchars($seller['category_name']) : '-';
            ?>
            <tr>
                <td><?=$serial++;?></td>
                <td><strong><?=htmlspecialchars($seller['seller_name']);?></strong></td>
                <td><?=$seller_type_display;?></td>
                <td><?=$category_display;?></td>
                <td><?=htmlspecialchars($seller['seller_mobile']);?></td>
                <td align="right"><strong>₹<?=inr_format($seller['sub_total'], 2);?></strong></td>
                <td align="right"><strong>₹<?=inr_format($seller['courier_charges'], 2);?></strong></td>
                <td align="right"><strong>₹<?=inr_format($seller['total_amount'], 2);?></strong></td>
                
                <?php foreach($products as $pr_id => $pr_name): 
                    $qty = $seller_product_quantities[$seller['seller_id']][$pr_id] ?? 0;
                    $product_totals[$pr_id] += $qty;
                ?>
                <td align="center" class="product-col">
                    <?php if($qty > 0): ?>
                        <strong><?=$qty;?></strong>
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
                <th align="right">₹<?=inr_format($grand_subtotal, 2);?></th>
                <th align="right">₹<?=inr_format($grand_courier, 2);?></th>
                <th align="right">₹<?=inr_format($grand_total, 2);?></th>
                <?php foreach($product_totals as $pr_id => $total): ?>
                <th align="center" class="product-col"><?=$total;?></th>
                <?php endforeach; ?>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Pagination -->
<?php if(!$is_search && $total_pages > 1): ?>
<nav aria-label="Page navigation" class="mt-3">
    <ul class="pagination justify-content-center">
        <?php if($page > 1): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?=($page-1);?>&q=<?=$qparam;?>">Previous</a>
        </li>
        <?php endif; ?>
        
        <?php 
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        if($start_page > 1) {
            echo '<li class="page-item"><a class="page-link" href="?page=1&q='.$qparam.'">1</a></li>';
            if($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        
        for($i = $start_page; $i <= $end_page; $i++): 
        ?>
        <li class="page-item <?=($i == $page) ? 'active' : '';?>">
            <a class="page-link" href="?page=<?=$i;?>&q=<?=$qparam;?>"><?=$i;?></a>
        </li>
        <?php 
        endfor; 
        
        if($end_page < $total_pages) {
            if($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&q='.$qparam.'">' . $total_pages . '</a></li>';
        }
        ?>
        
        <?php if($page < $total_pages): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?=($page+1);?>&q=<?=$qparam;?>">Next</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<p class="text-center text-muted mt-3">
    <?php if ($is_search): ?>
        Showing all <?=$total_records;?> matching entries
    <?php else: ?>
        Showing <?= $offset + 1; ?> to <?= min($offset + $records_per_page, $total_records); ?> of <?=$total_records;?> entries
    <?php endif; ?>
</p>
<?php
    } else {
        echo '<div class="alert alert-warning">No sellers found for the selected filters and date range.</div>';
    }
} else {
    echo '<div class="alert alert-danger">Invalid state selection. Please go back and select a state.</div>';
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
        // Dynamic district/taluk loading
        document.addEventListener('DOMContentLoaded', function() {
            const districtEl = document.getElementById('district_filter');
            if (districtEl) {
                districtEl.addEventListener('change', function() {
                    const districtId = this.value;
                    const talukSelect = document.getElementById('taluk_filter');
                    if (!talukSelect) return;
                    
                    talukSelect.innerHTML = '<option value="">All Taluks</option>';
                    
                    if(districtId) {
                        talukSelect.innerHTML = '<option value="">Loading taluks...</option>';
                        const url = `get_filter_data.php?action=get_taluks&district_id=${districtId}&_=${Date.now()}`;
                        fetch(url)
                            .then(response => response.json())
                            .then(data => {
                                talukSelect.innerHTML = '<option value="">All Taluks</option>';
                                if(data.success && data.data && data.data.length > 0) {
                                    data.data.forEach(taluk => {
                                        const option = document.createElement('option');
                                        option.value = taluk.id;
                                        option.textContent = taluk.name;
                                        talukSelect.appendChild(option);
                                    });
                                }
                            })
                            .catch(() => {
                                talukSelect.innerHTML = '<option value="">Error loading taluks</option>';
                            });
                    }
                });
            }
            
            // Show/hide stockist category based on seller type
            const sellerTypeEl = document.getElementById('seller_type_filter');
            if (sellerTypeEl) {
                sellerTypeEl.addEventListener('change', function() {
                    const categoryContainer = document.getElementById('stockist_category_container');
                    if (this.value === 'stockiest') {
                        categoryContainer.style.display = 'block';
                    } else {
                        categoryContainer.style.display = 'none';
                        document.getElementById('stockist_category_filter').value = '';
                    }
                });
            }
        });
    </script>
</body>
</html>