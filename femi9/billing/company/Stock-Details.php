<?php 
ob_start();

include("checksession.php");
include("config.php");

// Clear all filters if requested
if(isset($_GET['clear_filters']) || isset($_POST['clear_all'])) {
    ob_clean();
    
    
    unset($_SESSION['stock_district_id']);
    unset($_SESSION['stock_taluk_id']);
    unset($_SESSION['stock_user_type']);
    unset($_SESSION['stock_user_id']);
    unset($_SESSION['stock_records_per_page']);
    unset($_SESSION['stock_search']);
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if(isset($_GET['go_back']) || isset($_POST['go_back'])) {
    ob_clean();
    
    
    unset($_SESSION['stock_district_id']);
    unset($_SESSION['stock_taluk_id']);
    unset($_SESSION['stock_user_type']);
    unset($_SESSION['stock_user_id']);
    unset($_SESSION['stock_records_per_page']);
    unset($_SESSION['stock_search']);
    
    header("Location: Stock-First-Page.php");
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
    
    if(isset($_SESSION['stock_state_id']) && $_SESSION['stock_state_id'] != $selected_state_id) {
        unset($_SESSION['stock_district_id']);
        unset($_SESSION['stock_taluk_id']);
        unset($_SESSION['stock_user_type']);
        unset($_SESSION['stock_user_id']);
    }
    
    $_SESSION['stock_state_id'] = $selected_state_id;
} elseif(isset($_SESSION['stock_state_id'])) {
    $selected_state_id = (int)$_SESSION['stock_state_id'];
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

// Capture filter values from POST or SESSION
$selected_district = 0;
$selected_taluk = 0;
$selected_user_type = '';
$selected_user_id = '';

// District filter
if(isset($_POST['district_id'])) {
    $selected_district = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : 0;
    if($selected_district > 0) {
        $_SESSION['stock_district_id'] = $selected_district;
    } else {
        unset($_SESSION['stock_district_id']);
    }
} elseif(isset($_SESSION['stock_district_id'])) {
    $selected_district = (int)$_SESSION['stock_district_id'];
}

// Taluk filter
if(isset($_POST['taluk_id'])) {
    $selected_taluk = !empty($_POST['taluk_id']) ? (int)$_POST['taluk_id'] : 0;
    if($selected_taluk > 0) {
        $_SESSION['stock_taluk_id'] = $selected_taluk;
    } else {
        unset($_SESSION['stock_taluk_id']);
    }
} elseif(isset($_SESSION['stock_taluk_id'])) {
    $selected_taluk = (int)$_SESSION['stock_taluk_id'];
}

// User type filter
if(isset($_POST['user_type'])) {
    $selected_user_type = !empty($_POST['user_type']) ? $_POST['user_type'] : '';
    if($selected_user_type) {
        $_SESSION['stock_user_type'] = $selected_user_type;
    } else {
        unset($_SESSION['stock_user_type']);
    }
} elseif(isset($_SESSION['stock_user_type'])) {
    $selected_user_type = $_SESSION['stock_user_type'];
}

// User ID filter
if(isset($_POST['user_id'])) {
    $selected_user_id = !empty($_POST['user_id']) ? $_POST['user_id'] : '';
    if($selected_user_id) {
        $_SESSION['stock_user_id'] = $selected_user_id;
    } else {
        unset($_SESSION['stock_user_id']);
    }
} elseif(isset($_SESSION['stock_user_id'])) {
    $selected_user_id = $_SESSION['stock_user_id'];
}

// Clear taluk if district changed
if(isset($_POST['district_id']) && isset($_SESSION['stock_district_id'])) {
    if($_POST['district_id'] != $_SESSION['stock_district_id']) {
        $selected_taluk = 0;
        unset($_SESSION['stock_taluk_id']);
    }
}

// Clear user_id if user_type changed
if(isset($_POST['user_type']) && isset($_SESSION['stock_user_type'])) {
    if($_POST['user_type'] != $_SESSION['stock_user_type']) {
        $selected_user_id = '';
        unset($_SESSION['stock_user_id']);
    }
}

$Report_LABLE = "Stock Details Report";
if($state_name) {
    $Report_LABLE .= " - " . $state_name;
}

// Pagination settings
$records_per_page = 20;
if(isset($_POST['records_per_page'])) {
    $records_per_page = (int)$_POST['records_per_page'];
    $_SESSION['stock_records_per_page'] = $records_per_page;
} elseif(isset($_SESSION['stock_records_per_page'])) {
    $records_per_page = (int)$_SESSION['stock_records_per_page'];
}

// Validate records per page
$allowed_values = [20, 40, 60, 100];
if(!in_array($records_per_page, $allowed_values)) {
    $records_per_page = 20;
}

// Universal search
$search = '';
if (isset($_GET['q'])) {
    $search = trim($_GET['q']);
    $_SESSION['stock_search'] = $search;
} elseif (isset($_POST['q'])) {
    $search = trim($_POST['q']);
    $_SESSION['stock_search'] = $search;
} elseif (isset($_SESSION['stock_search'])) {
    $search = $_SESSION['stock_search'];
}
$search_esc = $db_conn->real_escape_string($search);
$is_search = ($search !== '');

$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$offset = ($page - 1) * $records_per_page;

// When searching, fetch all matches with safety cap
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

        .stock-positive {
            color: #28a745;
            font-weight: 600;
        }

        .stock-zero {
            color: #dc3545;
            font-weight: 600;
        }

        .stock-low {
            color: #ffc107;
            font-weight: 600;
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

        .badge-stock {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
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
                                                  <form method="post" action="export_stock_xlsx.php" target="_blank" class="d-inline">
                                                    <input type="hidden" name="state_id" value="<?=$selected_state_id;?>">
                                                    <input type="hidden" name="district_id" value="<?=$selected_district;?>">
                                                    <input type="hidden" name="taluk_id" value="<?=$selected_taluk;?>">
                                                    <input type="hidden" name="user_type" value="<?=$selected_user_type;?>">
                                                    <input type="hidden" name="user_id" value="<?=htmlspecialchars($selected_user_id, ENT_QUOTES);?>">
                                                    <input type="hidden" name="q" value="<?=htmlspecialchars($search, ENT_QUOTES);?>">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                      Export to Excel
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
                        <?php if($state_name != ""): ?>
                        <?php else: ?>
                        <div class="row">
                            <div class="col">
                                <div class="alert alert-warning">
                                    <strong>No state selected.</strong> Please <a href="?clear_filters=1">go back</a> and select a state.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Advanced Filters Form -->
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Stock Filters</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="<?=$_SERVER['PHP_SELF'];?>" id="filterForm">
                                            <input type="hidden" name="state_id" value="<?=$selected_state_id;?>"/>
                                            
                                            <!-- Filter Row -->
                                            <div class="row mb-3">
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
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">User Type</label>
                                                    <select name="user_type" id="user_type_filter" class="form-control">
                                                        <option value="">All User Types</option>
                                                        <?php
                                                        $user_types = [
                                                            'distributor' => 'Distributor',
                                                            'super_distributor' => 'Super Distributor',
                                                            'stockiest' => 'Stockist',
                                                            'super_stockiest' => 'Super Stockist'
                                                        ];
                                                        
                                                        foreach($user_types as $value => $label) {
                                                            $selected_attr = ($selected_user_type == $value) ? 'selected' : '';
                                                            echo '<option value="'.$value.'" '.$selected_attr.'>'.$label.'</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2" id="user_name_container" style="display:<?= !empty($selected_user_type) ? 'block' : 'none'; ?>;">
                                                    <label class="form-label">User Name</label>
                                                    <select name="user_id" id="user_name_filter" class="form-control">
                                                        <option value="">All Users</option>
                                                        <?php
                                                        if(!empty($selected_user_type)) {
                                                            $table_config = [
                                                                'super_stockiest' => [
                                                                    'table' => 'super_stockiest',
                                                                    'id_field' => 'temp_id',
                                                                    'name_field' => 'name',
                                                                    'has_taluk' => false
                                                                ],
                                                                'stockiest' => [
                                                                    'table' => 'stockiest',
                                                                    'id_field' => 'temp_id',
                                                                    'name_field' => 'name',
                                                                    'has_taluk' => true
                                                                ],
                                                                'distributor' => [
                                                                    'table' => 'distributor',
                                                                    'id_field' => 'temp_id',
                                                                    'name_field' => 'name',
                                                                    'has_taluk' => true
                                                                ],
                                                                'super_distributor' => [
                                                                    'table' => 'super_distributor',
                                                                    'id_field' => 'temp_id',
                                                                    'name_field' => 'name',
                                                                    'has_taluk' => true
                                                                ]
                                                            ];
                                                            
                                                            if(isset($table_config[$selected_user_type])) {
                                                                $config = $table_config[$selected_user_type];
                                                                $where = ["account_status = 'active'"];
                                                                
                                                                if($selected_state_id > 0) {
                                                                    $where[] = "state_id = " . (int)$selected_state_id;
                                                                }
                                                                
                                                                if($selected_district > 0) {
                                                                    $where[] = "district_id = " . (int)$selected_district;
                                                                }
                                                                
                                                                if($selected_taluk > 0 && $config['has_taluk']) {
                                                                    $where[] = "taluk_id = " . (int)$selected_taluk;
                                                                }
                                                                
                                                                $user_query = "SELECT {$config['id_field']} as id, {$config['name_field']} as name 
                                                                              FROM {$config['table']} 
                                                                              WHERE " . implode(" AND ", $where) . "
                                                                              ORDER BY {$config['name_field']} ASC 
                                                                              LIMIT 1000";
                                                                
                                                                $user_result = mysqli_query($db_conn, $user_query);
                                                                
                                                                if($user_result) {
                                                                    while($user = mysqli_fetch_assoc($user_result)) {
                                                                        $selected_attr = ($selected_user_id == $user['id']) ? 'selected' : '';
                                                                        echo '<option value="'.$user['id'].'" '.$selected_attr.'>'.htmlspecialchars($user['name']).'</option>';
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        <button type="submit" name="apply_filters" class="btn btn-primary">
                                                            <i class="material-icons">filter_list</i> Apply Filters
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
                                            if(!empty($selected_user_type)) {
                                                $active_filters[] = "User Type: " . ucwords(str_replace('_', ' ', $selected_user_type));
                                            }
                                            if(!empty($selected_user_id)) {
                                                $active_filters[] = "Specific User Selected";
                                            }
                                            if($search !== '') {
                                                $active_filters[] = "Search: \"" . htmlspecialchars($search) . "\"";
                                            }
                                            
                                            if(!empty($active_filters)):
                                            ?>
                                            <div class="alert alert-success mt-3 mb-0">
                                                <strong>Active Filters:</strong> <?= implode(' | ', $active_filters); ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Stock Table -->
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <!-- Clean Toolbar -->
                                    <div class="card-header py-3 bg-white border-0">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <!-- LEFT: Show entries -->
                                            <form method="post" action="<?=$_SERVER['PHP_SELF'];?>" class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="state_id" value="<?=$selected_state_id;?>">
                                                <input type="hidden" name="district_id" value="<?=$selected_district;?>">
                                                <input type="hidden" name="taluk_id" value="<?=$selected_taluk;?>">
                                                <input type="hidden" name="user_type" value="<?=$selected_user_type;?>">
                                                <input type="hidden" name="user_id" value="<?=htmlspecialchars($selected_user_id, ENT_QUOTES);?>">
                                                <input type="hidden" name="page" value="1">
                                                <input type="hidden" name="q" value="<?=htmlspecialchars($search, ENT_QUOTES);?>">

                                                <label class="mb-0 text-muted">Show:</label>
                                                <select name="records_per_page" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                                                    <option value="20" <?= $records_per_page==20 ? 'selected' : '' ?>>20</option>
                                                    <option value="40" <?= $records_per_page==40 ? 'selected' : '' ?>>40</option>
                                                    <option value="60" <?= $records_per_page==60 ? 'selected' : '' ?>>60</option>
                                                    <option value="100" <?= $records_per_page==100 ? 'selected' : '' ?>>100</option>
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
                                                       placeholder="Search user, product..."
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
                                            
                                            // Build WHERE conditions for filtering
                                            $where_conditions = [];
                                            $user_type_filter = "";
                                            $user_id_filter = "";
                                            
                                            if(!empty($selected_user_type)) {
                                                $user_type_filter = " AND s.user_type = '" . $db_conn->real_escape_string($selected_user_type) . "'";
                                            }
                                            
                                            if(!empty($selected_user_id)) {
                                                $user_id_filter = " AND s.user_id = '" . $db_conn->real_escape_string($selected_user_id) . "'";
                                            }
                                            
                                            // Build user table UNION for geo filtering
                                            $district_condition = !empty($selected_district) ? " AND district_id = " . (int)$selected_district : "";
                                            $taluk_condition = !empty($selected_taluk) ? " AND taluk_id = " . (int)$selected_taluk : "";
                                            
                                            $user_union_parts = [];
                                            $user_union_parts[] = "SELECT temp_id, 'distributor' AS user_type, 
                                                                   CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci AS name,
                                                                   CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci AS mobile
                                                                   FROM distributor 
                                                                   WHERE account_status = 'active' 
                                                                   AND CAST(state_id AS UNSIGNED) = " . (int)$selected_state_id . $district_condition . $taluk_condition;
                                            
                                            $user_union_parts[] = "SELECT temp_id, 'super_distributor',
                                                                   CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                                   CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
                                                                   FROM super_distributor 
                                                                   WHERE account_status = 'active' 
                                                                   AND CAST(state_id AS UNSIGNED) = " . (int)$selected_state_id . $district_condition . $taluk_condition;
                                            
                                            $user_union_parts[] = "SELECT temp_id, 'stockiest',
                                                                   CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                                   CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
                                                                   FROM stockiest 
                                                                   WHERE account_status = 'active' 
                                                                   AND state_id = " . (int)$selected_state_id . $district_condition . 
                                                                   (!empty($selected_taluk) ? " AND CAST(taluk_id AS UNSIGNED) = " . (int)$selected_taluk : "");
                                            
                                            $user_union_parts[] = "SELECT temp_id, 'super_stockiest',
                                                                   CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                                   CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
                                                                   FROM super_stockiest 
                                                                   WHERE account_status = 'active' 
                                                                   AND state_id = " . (int)$selected_state_id . $district_condition;
                                            
                                            $user_union_query = implode(" UNION ALL ", $user_union_parts);
                                            
                                            // Search filter
                                            $search_filter = '';
                                            if ($search_esc !== '') {
                                                $search_filter = "
                                                  AND (
                                                      users.name LIKE '%$search_esc%' COLLATE utf8mb4_general_ci
                                                   OR users.mobile LIKE '%$search_esc%'
                                                   OR users.user_type LIKE '%$search_esc%'
                                                   OR s.user_id LIKE '%$search_esc%'
                                                   OR EXISTS (
                                                        SELECT 1
                                                        FROM products p
                                                        WHERE p.id = s.product_id
                                                          AND p.productName LIKE '%$search_esc%' COLLATE utf8mb4_general_ci
                                                   )
                                                  )
                                                ";
                                            }
                                            
                                            // COUNT TOTAL RECORDS
                                            $count_query = "
                                                SELECT COUNT(DISTINCT CONCAT(s.user_type, '-', s.user_id)) as total
                                                FROM stock s
                                                INNER JOIN ($user_union_query) users 
                                                    ON s.user_id = users.temp_id AND s.user_type = users.user_type
                                                WHERE 1=1
                                                $user_type_filter
                                                $user_id_filter
                                                $search_filter
                                            ";
                                            
                                            $result_count = mysqli_query($db_conn, $count_query);
                                            $total_records = $result_count ? (int)mysqli_fetch_assoc($result_count)['total'] : 0;
                                            $total_pages = max(1, (int)ceil($total_records / max(1, $records_per_page)));
                                            
                                            if ($is_search) {
                                                $total_pages = 1;
                                                $page = 1;
                                                $offset = 0;
                                            }
                                            
                                            // GET STOCK DATA - Group by user
                                            $stock_query = "
                                                SELECT 
                                                    s.user_type,
                                                    s.user_id,
                                                    users.name as user_name,
                                                    users.mobile as user_mobile
                                                FROM stock s
                                                INNER JOIN ($user_union_query) users 
                                                    ON s.user_id = users.temp_id AND s.user_type = users.user_type
                                                WHERE 1=1
                                                $user_type_filter
                                                $user_id_filter
                                                $search_filter
                                                GROUP BY s.user_type, s.user_id, users.name, users.mobile
                                                ORDER BY users.user_type ASC, users.name ASC
                                            ";
                                            
                                            if (!$is_search) {
                                                $stock_query .= " LIMIT " . (int)$records_per_page . " OFFSET " . (int)$offset;
                                            }
                                            
                                            $result = mysqli_query($db_conn, $stock_query);
                                            
                                            $stock_users = [];
                                            if($result) {
                                                while($row = mysqli_fetch_assoc($result)) {
                                                    $stock_users[] = $row;
                                                }
                                            }
                                            
                                            // GET STOCK QUANTITIES FOR THESE USERS
                                            $stock_data = [];
                                            if(!empty($stock_users)) {
                                                $user_conditions = [];
                                                foreach($stock_users as $user) {
                                                    $user_type_esc = $db_conn->real_escape_string($user['user_type']);
                                                    $user_id_esc = $db_conn->real_escape_string($user['user_id']);
                                                    $user_conditions[] = "(user_type = '$user_type_esc' AND user_id = '$user_id_esc')";
                                                }
                                                
                                                $user_filter = implode(" OR ", $user_conditions);
                                                
                                                $stock_detail_query = "
                                                    SELECT 
                                                        user_type,
                                                        user_id,
                                                        product_id,
                                                        opening_qty,
                                                        input_qty,
                                                        sales_qty,
                                                        sent_qty,
                                                        returnqty,
                                                        closing_qty
                                                    FROM stock
                                                    WHERE ($user_filter)
                                                ";
                                                
                                                $stock_detail_result = mysqli_query($db_conn, $stock_detail_query);
                                                
                                                if($stock_detail_result) {
                                                    while($row = mysqli_fetch_assoc($stock_detail_result)) {
                                                        $key = $row['user_type'] . '-' . $row['user_id'];
                                                        $stock_data[$key][$row['product_id']] = $row;
                                                    }
                                                }
                                            }
                                            
                                            if(!empty($stock_users)) {
                                        ?>
                                    
                                        <div id="overflowon">
                                            <table class="table table-bordered table-hover table-sm">
                                                <thead>
                                                    <tr>
                                                        <th rowspan="2">S.No</th>
                                                        <th rowspan="2">User Type</th>
                                                        <th rowspan="2">User Details</th>
                                                        <th colspan="<?=count($products);?>" style="text-align:center; background:#e3f2fd;">Product Stock (Closing Qty)</th>
                                                        <th rowspan="2">Total Stock</th>
                                                    </tr>
                                                    <tr>
                                                        <?php foreach($products as $pr_id => $pr_name): ?>
                                                        <th class="product-col" title="<?=htmlspecialchars($pr_name);?>">
                                                            <?php 
                                                            $short_name = strlen($pr_name) > 20 ? substr($pr_name, 0, 17) . '...' : $pr_name;
                                                            echo htmlspecialchars($short_name);
                                                            ?>
                                                        </th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $serial = $offset + 1;
                                                    $product_totals = array_fill_keys(array_keys($products), 0);
                                                    $grand_total_stock = 0;
                                                    
                                                    foreach($stock_users as $user):
                                                        $user_type_display = ucwords(str_replace('_', ' ', $user['user_type']));
                                                        $key = $user['user_type'] . '-' . $user['user_id'];
                                                        $user_total = 0;
                                                    ?>
                                                    <tr>
                                                        <td><?=$serial++;?></td>
                                                        <td>
                                                            <span class="badge bg-primary"><?=$user_type_display;?></span>
                                                        </td>
                                                        <td>
                                                            <strong><?=htmlspecialchars($user['user_name']);?></strong><br/>
                                                            <small><b>M:</b> <?=htmlspecialchars($user['user_mobile']);?></small><br/>
                                                            <small class="text-muted">ID: <?=htmlspecialchars($user['user_id']);?></small>
                                                        </td>
                                                        
                                                        <?php foreach($products as $pr_id => $pr_name): 
                                                            $stock_info = $stock_data[$key][$pr_id] ?? null;
                                                            $closing_qty = $stock_info ? (int)$stock_info['closing_qty'] : 0;
                                                            $product_totals[$pr_id] += $closing_qty;
                                                            $user_total += $closing_qty;
                                                            
                                                            // Determine stock status class
                                                            $stock_class = 'stock-zero';
                                                            if($closing_qty > 10) {
                                                                $stock_class = 'stock-positive';
                                                            } elseif($closing_qty > 0) {
                                                                $stock_class = 'stock-low';
                                                            }
                                                        ?>
                                                        <td align="center" class="product-col" 
                                                            title="Opening: <?=$stock_info ? $stock_info['opening_qty'] : 0;?> | Input: <?=$stock_info ? $stock_info['input_qty'] : 0;?> | Sales: <?=$stock_info ? $stock_info['sales_qty'] : 0;?> | Sent: <?=$stock_info ? $stock_info['sent_qty'] : 0;?> | Return: <?=$stock_info ? $stock_info['returnqty'] : 0;?>">
                                                            <?php if($closing_qty > 0): ?>
                                                                <strong class="<?=$stock_class;?>"><?=$closing_qty;?></strong>
                                                            <?php else: ?>
                                                                <span style="color:#ccc;">0</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <?php endforeach; ?>
                                                        
                                                        <td align="center">
                                                            <strong class="<?=$user_total > 0 ? 'stock-positive' : 'stock-zero';?>">
                                                                <?=$user_total;?>
                                                            </strong>
                                                        </td>
                                                    </tr>
                                                    <?php 
                                                        $grand_total_stock += $user_total;
                                                    endforeach; 
                                                    ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr style="background:#e9ecef; font-weight:bold;">
                                                        <th colspan="3" align="right">Page Total:</th>
                                                        <?php foreach($product_totals as $pr_id => $total): ?>
                                                        <th align="center" class="product-col"><?=$total;?></th>
                                                        <?php endforeach; ?>
                                                        <th align="center"><?=$grand_total_stock;?></th>
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
                                                echo '<div class="alert alert-warning">No stock data found for the selected filters.</div>';
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
        // Initialize visibility and dynamic filters
        document.addEventListener('DOMContentLoaded', function() {
            initializeUserNameVisibility();
            
            window.currentFilters = {
                stateId: document.querySelector('input[name="state_id"]') ? document.querySelector('input[name="state_id"]').value : '',
                districtId: document.getElementById('district_filter') ? document.getElementById('district_filter').value : '',
                talukId: document.getElementById('taluk_filter') ? document.getElementById('taluk_filter').value : '',
                userType: document.getElementById('user_type_filter') ? document.getElementById('user_type_filter').value : ''
            };
            
            const districtEl = document.getElementById('district_filter');
            if (districtEl) {
                districtEl.addEventListener('change', function() {
                    const districtId = this.value;
                    window.currentFilters.districtId = districtId;
                    
                    const talukSelect = document.getElementById('taluk_filter');
                    if (!talukSelect) return;
                    talukSelect.innerHTML = '<option value="">All Taluks</option>';
                    window.currentFilters.talukId = '';
                    
                    if(districtId) {
                        talukSelect.innerHTML = '<option value="">Loading taluks...</option>';
                        const url = `get_stock_filter_data.php?action=get_taluks&district_id=${districtId}&_=${Date.now()}`;
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
                    
                    if(window.currentFilters.userType) {
                        setTimeout(() => reloadUsersWithFilters(), 300);
                    }
                });
            }
            
            const talukEl = document.getElementById('taluk_filter');
            if (talukEl) {
                talukEl.addEventListener('change', function() {
                    window.currentFilters.talukId = this.value;
                    if(window.currentFilters.userType) {
                        reloadUsersWithFilters();
                    }
                });
            }
            
            const userTypeEl = document.getElementById('user_type_filter');
            if (userTypeEl) {
                userTypeEl.addEventListener('change', function() {
                    const userType = this.value;
                    window.currentFilters.userType = userType;
                    
                    if(userType) {
                        document.getElementById('user_name_container').style.display = 'block';
                        reloadUsersWithFilters();
                    } else {
                        document.getElementById('user_name_container').style.display = 'none';
                        document.getElementById('user_name_filter').innerHTML = '<option value="">All Users</option>';
                    }
                });
            }
        });
        
        function initializeUserNameVisibility() {
            const userTypeEl = document.getElementById('user_type_filter');
            const container = document.getElementById('user_name_container');
            if(!userTypeEl || !container) return;
            if(userTypeEl.value) {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }
        
        function reloadUsersWithFilters() {
            const userNameSelect = document.getElementById('user_name_filter');
            if(!userNameSelect || !window.currentFilters.userType) return;
            
            const previouslySelectedUser = userNameSelect.value;
            userNameSelect.innerHTML = '<option value="">Loading users...</option>';
            
            let url = `get_stock_filter_data.php?action=get_stock_users&user_type=${encodeURIComponent(window.currentFilters.userType)}`;
            if(window.currentFilters.stateId)   url += `&state_id=${encodeURIComponent(window.currentFilters.stateId)}`;
            if(window.currentFilters.districtId)url += `&district_id=${encodeURIComponent(window.currentFilters.districtId)}`;
            if(window.currentFilters.talukId)   url += `&taluk_id=${encodeURIComponent(window.currentFilters.talukId)}`;
            url += `&_=${Date.now()}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    userNameSelect.innerHTML = '<option value="">All Users</option>';
                    if(data.success && data.data && data.data.length > 0) {
                        data.data.forEach(user => {
                            const option = document.createElement('option');
                            option.value = user.id;
                            option.textContent = user.name;
                            if(String(user.id) === String(previouslySelectedUser)) {
                                option.selected = true;
                            }
                            userNameSelect.appendChild(option);
                        });
                    } else {
                        userNameSelect.innerHTML = '<option value="">No users found in this area</option>';
                    }
                })
                .catch(() => {
                    userNameSelect.innerHTML = '<option value="">Error loading users</option>';
                });
        }
    </script>
</body>
</html>