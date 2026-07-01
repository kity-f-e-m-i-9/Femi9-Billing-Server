<?php 
ob_start();

include("checksession.php");
include("config.php");

error_reporting(E_ALL);
ini_set('display_errors', 1); 

// Clear all filters if requested
if(isset($_GET['clear_filters']) || isset($_POST['clear_all'])) {
    ob_clean();
    
    unset($_SESSION['report_from_date']);
    unset($_SESSION['report_to_date']);
    unset($_SESSION['report_district_id']);
    unset($_SESSION['report_taluk_id']);
    unset($_SESSION['report_seller_type']);
    unset($_SESSION['report_seller_id']);
    unset($_SESSION['report_buyer_type']);
    unset($_SESSION['report_records_per_page']);
    unset($_SESSION['report_buyer_district_id']);
    unset($_SESSION['report_search']); // <-- clear search too
    unset($_SESSION['report_buyer_id']);
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if(isset($_GET['go_back']) || isset($_POST['go_back'])) {
    ob_clean();
    
    unset($_SESSION['report_from_date']);
    unset($_SESSION['report_to_date']);
    unset($_SESSION['report_district_id']);
    unset($_SESSION['report_taluk_id']);
    unset($_SESSION['report_seller_type']);
    unset($_SESSION['report_seller_id']);
    unset($_SESSION['report_buyer_type']);
    unset($_SESSION['report_records_per_page']);
    unset($_SESSION['report_state_id']);
    unset($_SESSION['report_buyer_district_id']);
    unset($_SESSION['report_search']); // <-- clear search too
    unset($_SESSION['report_buyer_id']);
    
    header("Location: Report-First-Page.php");
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
    
    if(isset($_SESSION['report_state_id']) && $_SESSION['report_state_id'] != $selected_state_id) {
        unset($_SESSION['report_district_id']);
        unset($_SESSION['report_taluk_id']);
        unset($_SESSION['report_seller_type']);
        unset($_SESSION['report_seller_id']);
        unset($_SESSION['report_buyer_type']);
    }
    
    $_SESSION['report_state_id'] = $selected_state_id;
} elseif(isset($_SESSION['report_state_id'])) {
    $selected_state_id = (int)$_SESSION['report_state_id'];
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
    $_SESSION['report_from_date'] = $from_date;
}
if(isset($_POST['todate']) && !empty($_POST['todate'])) {
    $to_date = $_POST['todate'];
    $_SESSION['report_to_date'] = $to_date;
}

// Restore from session if available
if(isset($_SESSION['report_from_date'])) {
    $from_date = $_SESSION['report_from_date'];
}
if(isset($_SESSION['report_to_date'])) {
    $to_date = $_SESSION['report_to_date'];
}

// Capture filter values from POST or SESSION
$selected_district = 0;
$selected_taluk = 0;
$selected_seller_type = '';
$selected_seller_id = '';
$selected_buyer_type = '';

// District filter
if(isset($_POST['district_id'])) {
    $selected_district = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : 0;
    if($selected_district > 0) {
        $_SESSION['report_district_id'] = $selected_district;
    } else {
        unset($_SESSION['report_district_id']);
    }
} elseif(isset($_SESSION['report_district_id'])) {
    $selected_district = (int)$_SESSION['report_district_id'];
}

// Taluk filter
if(isset($_POST['taluk_id'])) {
    $selected_taluk = !empty($_POST['taluk_id']) ? (int)$_POST['taluk_id'] : 0;
    if($selected_taluk > 0) {
        $_SESSION['report_taluk_id'] = $selected_taluk;
    } else {
        unset($_SESSION['report_taluk_id']);
    }
} elseif(isset($_SESSION['report_taluk_id'])) {
    $selected_taluk = (int)$_SESSION['report_taluk_id'];
}

// Seller type filter
if(isset($_POST['seller_type'])) {
    $selected_seller_type = !empty($_POST['seller_type']) ? $_POST['seller_type'] : '';
    if($selected_seller_type) {
        $_SESSION['report_seller_type'] = $selected_seller_type;
    } else {
        unset($_SESSION['report_seller_type']);
    }
} elseif(isset($_SESSION['report_seller_type'])) {
    $selected_seller_type = $_SESSION['report_seller_type'];
}

// Seller ID filter
if(isset($_POST['seller_id'])) {
    $selected_seller_id = !empty($_POST['seller_id']) ? $_POST['seller_id'] : '';
    if($selected_seller_id) {
        $_SESSION['report_seller_id'] = $selected_seller_id;
    } else {
        unset($_SESSION['report_seller_id']);
    }
} elseif(isset($_SESSION['report_seller_id'])) {
    $selected_seller_id = $_SESSION['report_seller_id'];
}

// Buyer type filter
if(isset($_POST['buyer_type'])) {
    $selected_buyer_type = !empty($_POST['buyer_type']) ? $_POST['buyer_type'] : '';
    if($selected_buyer_type) {
        $_SESSION['report_buyer_type'] = $selected_buyer_type;
    } else {
        unset($_SESSION['report_buyer_type']);
    }
} elseif(isset($_SESSION['report_buyer_type'])) {
    $selected_buyer_type = $_SESSION['report_buyer_type'];
}

// Buyer ID filter
if(isset($_POST['buyer_id'])) {
    $selected_buyer_id = !empty($_POST['buyer_id']) ? $_POST['buyer_id'] : '';
    if($selected_buyer_id) {
        $_SESSION['report_buyer_id'] = $selected_buyer_id;
    } else {
        unset($_SESSION['report_buyer_id']);
    }
} elseif(isset($_SESSION['report_buyer_id'])) {
    $selected_buyer_id = $_SESSION['report_buyer_id'];
} else {
    $selected_buyer_id = '';
}

// Buyer district filter
if(isset($_POST['buyer_district_id'])) {
    $selected_buyer_district = !empty($_POST['buyer_district_id']) ? (int)$_POST['buyer_district_id'] : 0;
    if($selected_buyer_district > 0) {
        $_SESSION['report_buyer_district_id'] = $selected_buyer_district;
    } else {
        unset($_SESSION['report_buyer_district_id']);
    }
} elseif(isset($_SESSION['report_buyer_district_id'])) {
    $selected_buyer_district = (int)$_SESSION['report_buyer_district_id'];
} else {
    $selected_buyer_district = 0;
}

// Clear taluk if district changed
if(isset($_POST['district_id']) && isset($_SESSION['report_district_id'])) {
    if($_POST['district_id'] != $_SESSION['report_district_id']) {
        $selected_taluk = 0;
        unset($_SESSION['report_taluk_id']);
    }
}

// Clear seller_id if seller_type changed
if(isset($_POST['seller_type']) && isset($_SESSION['report_seller_type'])) {
    if($_POST['seller_type'] != $_SESSION['report_seller_type']) {
        $selected_seller_id = '';
        unset($_SESSION['report_seller_id']);
    }
}

if(isset($_POST['buyer_type']) && isset($_SESSION['report_buyer_type'])) {
    if($_POST['buyer_type'] != $_SESSION['report_buyer_type']) {
        $selected_buyer_id = '';
        unset($_SESSION['report_buyer_id']);
    }
}

$Report_LABLE = "B2B Sales Report";
if($state_name) {
    $Report_LABLE .= " - " . $state_name;
}

// Pagination settings
$records_per_page = 20;
if(isset($_POST['records_per_page'])) {
    $records_per_page = (int)$_POST['records_per_page'];
    $_SESSION['report_records_per_page'] = $records_per_page;
} elseif(isset($_SESSION['report_records_per_page'])) {
    $records_per_page = (int)$_SESSION['report_records_per_page'];
}

// Validate records per page
$allowed_values = [20, 40, 60];
if(!in_array($records_per_page, $allowed_values)) {
    $records_per_page = 20;
}

// --- Universal search (persist across navigation) ---
$search = '';
if (isset($_GET['q'])) {
    $search = trim($_GET['q']);
    $_SESSION['report_search'] = $search;
} elseif (isset($_POST['q'])) {
    $search = trim($_POST['q']);
    $_SESSION['report_search'] = $search;
} elseif (isset($_SESSION['report_search'])) {
    $search = $_SESSION['report_search'];
}
$search_esc = $db_conn->real_escape_string($search);
$is_search = ($search !== '');

$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$offset = ($page - 1) * $records_per_page;

// When searching, fetch all matches (no pagination), with a safety cap
if ($is_search) {
    $MAX_SEARCH_ROWS = 5000; // adjust if needed
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

        .app-sidebar {
            width: 260px !important;
            min-width: 260px !important;
            max-width: 260px !important;
        }

        .app-sidebar .menu-category,
        .app-sidebar .menu-item {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .app-sidebar .sidebar-menu .menu-item > ul {
            display: none;
        }

        .app-sidebar .sidebar-menu .menu-item.active > ul,
        .app-sidebar .sidebar-menu .menu-item:hover > ul {
            display: block;
        }

        .app-sidebar .menu-item a {
            padding: 8px 15px !important;
            font-size: 14px !important;
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
                                                  <form method="post" action="export_report_xlsx.php" target="_blank" class="d-inline">
                                                    <input type="hidden" name="state_id"    value="<?=$selected_state_id;?>">
                                                    <input type="hidden" name="frdate"      value="<?=$from_date;?>">
                                                    <input type="hidden" name="todate"      value="<?=$to_date;?>">
                                                    <input type="hidden" name="district_id" value="<?=$selected_district;?>">
                                                    <input type="hidden" name="taluk_id"    value="<?=$selected_taluk;?>">
                                                    <input type="hidden" name="seller_type" value="<?=$selected_seller_type;?>">
                                                    <input type="hidden" name="seller_id"   value="<?=htmlspecialchars($selected_seller_id, ENT_QUOTES);?>">
                                                    <input type="hidden" name="buyer_type"  value="<?=$selected_buyer_type;?>">
                                                    <input type="hidden" name="buyer_district_id" value="<?=$selected_buyer_district;?>">
                                                    <input type="hidden" name="q"           value="<?=htmlspecialchars($search, ENT_QUOTES);?>"><!-- preserve search -->
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
                        <?php if($state_name != ""): ?>
                        <?php else: ?>
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
                                            
                                            <!-- Row 2: Seller Filters and Action Buttons -->
                                            <div class="row mb-3">
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">Seller Type (From)</label>
                                                    <select name="seller_type" id="seller_type_filter" class="form-control">
                                                        <option value="">All Seller Types</option>
                                                        <?php
                                                        $seller_types = [
                                                            'company' => 'Company',
                                                            'super_stockiest' => 'Super Stockist',
                                                            'stockiest' => 'Stockist',
                                                            'super_distributor' => 'Super Distributor',
                                                            'distributor' => 'Distributor'
                                                        ];
                                                        
                                                        foreach($seller_types as $value => $label) {
                                                            $selected_attr = ($selected_seller_type == $value) ? 'selected' : '';
                                                            echo '<option value="'.$value.'" '.$selected_attr.'>'.$label.'</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2" id="seller_name_container" style="display:<?= !empty($selected_seller_type) ? 'block' : 'none'; ?>;">
                                                    <label class="form-label">Seller Name</label>
                                                    <select name="seller_id" id="seller_name_filter" class="form-control">
                                                        <option value="">All Sellers</option>
                                                        <?php
                                                            if(!empty($selected_seller_type)) {
                                                                if($selected_seller_type == 'company') {
                                                                    $seller_query = "SELECT id, gname as name FROM company_godown ORDER BY gname ASC LIMIT 1000";
                                                                    $seller_result = mysqli_query($db_conn, $seller_query);
                                                                    
                                                                    if($seller_result) {
                                                                        while($seller = mysqli_fetch_assoc($seller_result)) {
                                                                            $selected_attr = ($selected_seller_id == $seller['id']) ? 'selected' : '';
                                                                            echo '<option value="'.$seller['id'].'" '.$selected_attr.'>'.htmlspecialchars($seller['name']).'</option>';
                                                                        }
                                                                    }
                                                                } else {
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
                                                                    
                                                                    if(isset($table_config[$selected_seller_type])) {
                                                                        $config = $table_config[$selected_seller_type];
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
                                                                        
                                                                        $seller_query = "SELECT {$config['id_field']} as id, {$config['name_field']} as name 
                                                                                        FROM {$config['table']} 
                                                                                        WHERE " . implode(" AND ", $where) . "
                                                                                        ORDER BY {$config['name_field']} ASC 
                                                                                        LIMIT 1000";
                                                                        
                                                                        $seller_result = mysqli_query($db_conn, $seller_query);
                                                                        
                                                                        if($seller_result) {
                                                                            while($seller = mysqli_fetch_assoc($seller_result)) {
                                                                                $selected_attr = ($selected_seller_id == $seller['id']) ? 'selected' : '';
                                                                                echo '<option value="'.$seller['id'].'" '.$selected_attr.'>'.htmlspecialchars($seller['name']).'</option>';
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                            ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">Buyer Type</label>
                                                    <select name="buyer_type" id="buyer_type_filter" class="form-control">
                                                        <option value="">All Buyer Types</option>
                                                        <option value="shop" <?= $selected_buyer_type == 'shop' ? 'selected' : ''; ?>>Shop</option>
                                                        <option value="distributor" <?= $selected_buyer_type == 'distributor' ? 'selected' : ''; ?>>Distributor</option>
                                                        <option value="super_distributor" <?= $selected_buyer_type == 'super_distributor' ? 'selected' : ''; ?>>Super Distributor</option>
                                                        <option value="stockiest" <?= $selected_buyer_type == 'stockiest' ? 'selected' : ''; ?>>Stockist</option>
                                                        <option value="super_stockiest" <?= $selected_buyer_type == 'super_stockiest' ? 'selected' : ''; ?>>Super Stockist</option>
                                                        <option value="candf" <?= $selected_buyer_type == 'candf' ? 'selected' : ''; ?>>C&F</option>
                                                        <option value="outlet" <?= $selected_buyer_type == 'outlet' ? 'selected' : ''; ?>>Outlet</option>
                                                        <option value="customer" <?= $selected_buyer_type == 'customer' ? 'selected' : ''; ?>>Customer</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2" id="buyer_name_container" style="display:<?= !empty($selected_buyer_type) ? 'block' : 'none'; ?>;">
                                                    <label class="form-label">Buyer Name</label>
                                                    <select name="buyer_id" id="buyer_name_filter" class="form-control">
                                                        <option value="">All Buyers</option>
                                                        <?php
                                                        if(!empty($selected_buyer_type) && $selected_buyer_type != 'customer') {
                                                            $buyer_table_config = [
                                                                'shop'             => ['table'=>'shop',             'id_field'=>'temp_id', 'name_field'=>'name'],
                                                                'distributor'      => ['table'=>'distributor',      'id_field'=>'temp_id', 'name_field'=>'name'],
                                                                'super_distributor'=> ['table'=>'super_distributor','id_field'=>'temp_id', 'name_field'=>'name'],
                                                                'stockiest'        => ['table'=>'stockiest',        'id_field'=>'temp_id', 'name_field'=>'name'],
                                                                'super_stockiest'  => ['table'=>'super_stockiest',  'id_field'=>'temp_id', 'name_field'=>'name'],
                                                                'candf'            => ['table'=>'c_and_f',          'id_field'=>'temp_id', 'name_field'=>'name'],
                                                                'outlet'           => ['table'=>'outlet',           'id_field'=>'temp_id', 'name_field'=>'name'],
                                                            ];
                                                            if(isset($buyer_table_config[$selected_buyer_type])) {
                                                                $bcfg = $buyer_table_config[$selected_buyer_type];
                                                                $bwhere = ["1=1"];
                                                                if($selected_state_id > 0) $bwhere[] = "state_id = ".(int)$selected_state_id;
                                                                if($selected_district > 0)  $bwhere[] = "district_id = ".(int)$selected_district;
                                                                $buyer_list_query = "SELECT {$bcfg['id_field']} as id, {$bcfg['name_field']} as name FROM {$bcfg['table']} WHERE ".implode(" AND ",$bwhere)." ORDER BY {$bcfg['name_field']} ASC LIMIT 1000";
                                                                $buyer_list_result = mysqli_query($db_conn, $buyer_list_query);
                                                                if($buyer_list_result) while($b = mysqli_fetch_assoc($buyer_list_result)) {
                                                                    $sel = ($selected_buyer_id == $b['id']) ? 'selected' : '';
                                                                    echo '<option value="'.$b['id'].'" '.$sel.'>'.htmlspecialchars($b['name']).'</option>';
                                                                }
                                                            }
                                                        } elseif($selected_buyer_type == 'customer') {
                                                            $cq = mysqli_query($db_conn, "SELECT id, name FROM customers ORDER BY name ASC LIMIT 1000");
                                                            if($cq) while($c = mysqli_fetch_assoc($cq)) {
                                                                $sel = ($selected_buyer_id == $c['id']) ? 'selected' : '';
                                                                echo '<option value="'.$c['id'].'" '.$sel.'>'.htmlspecialchars($c['name']).'</option>';
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">Buyer District</label>
                                                    <select name="buyer_district_id" id="buyer_district_filter" class="form-control">
                                                        <option value="">All Buyer Districts</option>
                                                        <?php
                                                        if($selected_state_id > 0) {
                                                            $buyer_dist_query = "SELECT DISTINCT id, dist_name FROM district WHERE state_id = ? ORDER BY dist_name ASC";
                                                            $stmt_buyer_dist = $db_conn->prepare($buyer_dist_query);
                                                            $stmt_buyer_dist->bind_param("i", $selected_state_id);
                                                            $stmt_buyer_dist->execute();
                                                            $buyer_dist_result = $stmt_buyer_dist->get_result();
                                                            
                                                            while($bdist = $buyer_dist_result->fetch_assoc()) {
                                                                $selected_attr = ($selected_buyer_district == $bdist['id']) ? 'selected' : '';
                                                                echo '<option value="'.$bdist['id'].'" '.$selected_attr.'>'.htmlspecialchars($bdist['dist_name']).'</option>';
                                                            }
                                                            $stmt_buyer_dist->close();
                                                        }
                                                        ?>
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
                                            if(!empty($selected_seller_id)) {
                                                $active_filters[] = "Specific Seller Selected";
                                            }
                                            if(!empty($selected_buyer_type)) {
                                                $active_filters[] = "Buyer Type: " . ucwords(str_replace('_', ' ', $selected_buyer_type));
                                            }
                                            if($search !== '') {
                                                $active_filters[] = "Search: “" . htmlspecialchars($search) . "”";
                                            }
                                            $buyer_id_filter = "";
                                                if(!empty($selected_buyer_id)) {
                                                    $buyer_id_filter = " AND ui.to_user_id = '" . $db_conn->real_escape_string($selected_buyer_id) . "'";
                                                }
                                                
                                            $buyer_district_filter_query = "";
                                            if(!empty($selected_buyer_district)) {
                                                // Get all buyer IDs in that district across all buyer type tables
                                                $bd = (int)$selected_buyer_district;
                                                $buyer_district_ids_query = "
                                                    SELECT temp_id, 'shop' as utype FROM shop WHERE district_id = $bd
                                                    UNION ALL SELECT temp_id, 'distributor' FROM distributor WHERE district_id = $bd
                                                    UNION ALL SELECT temp_id, 'super_distributor' FROM super_distributor WHERE district_id = $bd
                                                    UNION ALL SELECT temp_id, 'stockiest' FROM stockiest WHERE district_id = $bd
                                                    UNION ALL SELECT temp_id, 'super_stockiest' FROM super_stockiest WHERE district_id = $bd
                                                    UNION ALL SELECT temp_id, 'candf' FROM c_and_f WHERE district_id = $bd
                                                    UNION ALL SELECT temp_id, 'outlet' FROM outlet WHERE district_id = $bd
                                                ";
                                                $buyer_district_filter_query = " AND EXISTS (
                                                    SELECT 1 FROM ($buyer_district_ids_query) bdf
                                                    WHERE bdf.temp_id = ui.to_user_id AND bdf.utype = ui.to_user_type
                                                )";
                                            }    
                                            
                                            if(!empty($selected_buyer_district)) {
                                                $buyer_dist_name_query = "SELECT dist_name FROM district WHERE id = ?";
                                                $stmt_bdn = $db_conn->prepare($buyer_dist_name_query);
                                                $stmt_bdn->bind_param("i", $selected_buyer_district);
                                                $stmt_bdn->execute();
                                                $result_bdn = $stmt_bdn->get_result();
                                                if($row_bdn = $result_bdn->fetch_assoc()) {
                                                    $active_filters[] = "Buyer District: " . $row_bdn['dist_name'];
                                                }
                                                $stmt_bdn->close();
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
                                                <input type="hidden" name="seller_id" value="<?=htmlspecialchars($selected_seller_id, ENT_QUOTES);?>">
                                                <input type="hidden" name="buyer_type" value="<?=$selected_buyer_type;?>">
                                                <input type="hidden" name="page" value="1">
                                                <input type="hidden" name="buyer_district_id" value="<?=$selected_buyer_district;?>">
                                                <input type="hidden" name="q" value="<?=htmlspecialchars($search, ENT_QUOTES);?>"><!-- keep search -->

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
                                                       placeholder="Search invoice, buyer, seller, product..."
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
                                            
                                            // Build seller filter conditions
                                            $seller_type_filter = "";
                                            $seller_id_filter = "";
                                            $buyer_type_filter = "";
                                            $buyer_id_filter = "";
                                            $buyer_district_filter_query="";
                                            
                                            if(!empty($selected_seller_type)) {
                                                $seller_type_filter = " AND ui.from_user_type = '" . $db_conn->real_escape_string($selected_seller_type) . "'";
                                            }
                                            
                                            if(!empty($selected_seller_id)) {
                                                $seller_id_filter = " AND ui.from_user_id = '" . $db_conn->real_escape_string($selected_seller_id) . "'";
                                            }
                                            
                                            if(!empty($selected_buyer_type)) {
                                                $buyer_type_filter = " AND ui.to_user_type = '" . $db_conn->real_escape_string($selected_buyer_type) . "'";
                                            }
                                            
                                            if(!empty($selected_buyer_id)) {
                                                $active_filters[] = "Specific Buyer Selected";
                                            }
                                            
                                            if(!empty($selected_buyer_district)) {
                                                $bd = (int)$selected_buyer_district;
                                                $buyer_district_ids_query = "
                                                    SELECT temp_id, 'shop' as utype FROM shop WHERE district_id = $bd
                                                    UNION ALL SELECT temp_id, 'distributor' FROM distributor WHERE district_id = $bd
                                                    UNION ALL SELECT temp_id, 'super_distributor' FROM super_distributor WHERE district_id = $bd
                                                    UNION ALL SELECT temp_id, 'stockiest' FROM stockiest WHERE district_id = $bd
                                                    UNION ALL SELECT temp_id, 'super_stockiest' FROM super_stockiest WHERE district_id = $bd
                                                    UNION ALL SELECT temp_id, 'candf' FROM c_and_f WHERE district_id = $bd
                                                    UNION ALL SELECT temp_id, 'outlet' FROM outlet WHERE district_id = $bd
                                                ";
                                                $buyer_district_filter_query = " AND EXISTS (
                                                    SELECT 1 FROM ($buyer_district_ids_query) bdf
                                                    WHERE bdf.temp_id = ui.to_user_id AND bdf.utype = ui.to_user_type
                                                )";
                                            }
                                            
                                            // Get all products
                                            $products = [];
                                            $product_query = "SELECT id, productName FROM products ORDER BY id ASC";
                                            $product_result = mysqli_query($db_conn, $product_query);
                                            
                                            if($product_result) {
                                                while($pr = mysqli_fetch_assoc($product_result)) {
                                                    $products[$pr['id']] = $pr['productName'];
                                                }
                                            }
                                            
                                            // BUYER UNION (collation-safe for text)
                                            
                                            $buyer_district_filter = $selected_buyer_district > 0 ? " AND district_id = " . (int)$selected_buyer_district : "";
                                            
                                            $buyer_union_parts = [];
                                            $buyer_union_parts[] = "
                                              SELECT temp_id, state_id, district_id, taluk_id,
                                                     CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci AS name,
                                                     CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci AS mobile_number,
                                                     'shop' as user_type
                                              FROM shop
                                              WHERE 1=1 $buyer_district_filter
                                            ";
                                            $buyer_union_parts[] = "
                                              SELECT temp_id, state_id, district_id, taluk_id,
                                                     CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     'distributor'
                                              FROM distributor
                                              WHERE 1=1 $buyer_district_filter
                                            ";
                                            $buyer_union_parts[] = "
                                              SELECT temp_id, state_id, district_id, taluk_id,
                                                     CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     'super_distributor'
                                              FROM super_distributor
                                              WHERE 1=1 $buyer_district_filter
                                            ";
                                            $buyer_union_parts[] = "
                                              SELECT temp_id, CAST(state_id AS UNSIGNED) as state_id, district_id, CAST(taluk_id AS UNSIGNED) as taluk_id,
                                                     CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     'stockiest'
                                              FROM stockiest
                                              WHERE 1=1 $buyer_district_filter
                                            ";
                                            $buyer_union_parts[] = "
                                              SELECT temp_id, state_id, district_id, 0 as taluk_id,
                                                     CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     'super_stockiest'
                                              FROM super_stockiest
                                              WHERE 1=1 $buyer_district_filter
                                            ";
                                            $buyer_union_parts[] = "
                                              SELECT temp_id, CAST(state_id AS UNSIGNED) as state_id, district_id, 0 as taluk_id,
                                                     CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     'candf'
                                              FROM c_and_f
                                              WHERE 1=1 $buyer_district_filter
                                            ";
                                            $buyer_union_parts[] = "
                                              SELECT temp_id, state_id, district_id, taluk_id,
                                                     CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     'outlet'
                                              FROM outlet
                                              WHERE 1=1 $buyer_district_filter
                                            ";
                                            $buyer_union_query = implode(" UNION ALL ", $buyer_union_parts);
                                            
                                            // SELLER GEO FILTER UNION - Find this section and replace
                                            $district_condition = !empty($selected_district) ? " AND district_id = " . (int)$selected_district : "";
                                            $taluk_condition = !empty($selected_taluk) ? " AND taluk_id = " . (int)$selected_taluk : "";
                                            
                                            $seller_union_parts = [];

                                            // Normalize every temp_id to CHAR with consistent collation
                                            $collate = "COLLATE utf8mb4_general_ci";
                                            
                                            // Include company seller type only when allowed
                                            if (empty($selected_seller_type) || $selected_seller_type == 'company') {
                                                if (empty($selected_district) || $selected_district == 8) {
                                                    $seller_union_parts[] = "
                                                        SELECT CAST(id AS CHAR) $collate AS temp_id, 'company' AS user_type
                                                        FROM company_godown
                                                    ";
                                                }
                                            }
                                            
                                            // Include other seller types
                                            if (empty($selected_seller_type) || $selected_seller_type != 'company') {
                                                $seller_union_parts[] = "
                                                    SELECT CAST(temp_id AS CHAR) $collate AS temp_id, 'shop' AS user_type
                                                    FROM shop WHERE state_id = $selected_state_id $district_condition $taluk_condition
                                                ";
                                                $seller_union_parts[] = "
                                                    SELECT CAST(temp_id AS CHAR) $collate AS temp_id, 'distributor' AS user_type
                                                    FROM distributor WHERE CAST(state_id AS UNSIGNED) = $selected_state_id $district_condition $taluk_condition
                                                ";
                                                $seller_union_parts[] = "
                                                    SELECT CAST(temp_id AS CHAR) $collate AS temp_id, 'super_distributor' AS user_type
                                                    FROM super_distributor WHERE CAST(state_id AS UNSIGNED) = $selected_state_id $district_condition $taluk_condition
                                                ";
                                                $seller_union_parts[] = "
                                                    SELECT CAST(temp_id AS CHAR) $collate AS temp_id, 'stockiest' AS user_type
                                                    FROM stockiest WHERE state_id = $selected_state_id $district_condition
                                                    " . (!empty($selected_taluk) ? " AND CAST(taluk_id AS UNSIGNED) = $selected_taluk" : "") . "
                                                ";
                                                $seller_union_parts[] = "
                                                    SELECT CAST(temp_id AS CHAR) $collate AS temp_id, 'super_stockiest' AS user_type
                                                    FROM super_stockiest WHERE state_id = $selected_state_id $district_condition
                                                ";
                                                $seller_union_parts[] = "
                                                    SELECT CAST(temp_id AS CHAR) $collate AS temp_id, 'candf' AS user_type
                                                    FROM c_and_f WHERE CAST(state_id AS UNSIGNED) = $selected_state_id $district_condition
                                                ";
                                                $seller_union_parts[] = "
                                                    SELECT CAST(temp_id AS CHAR) $collate AS temp_id, 'outlet' AS user_type
                                                    FROM outlet WHERE state_id = $selected_state_id $district_condition $taluk_condition
                                                ";
                                            }

                                            
                                            $seller_geo_join = "";
                                            if(!empty($seller_union_parts)) {
                                                file_put_contents(__DIR__ . '/debug_step.log', "Joining unions...\n", FILE_APPEND);

                                                $seller_union_query = implode(" UNION ALL ", $seller_union_parts);
                                                $seller_geo_join = " INNER JOIN ($seller_union_query) seller_geo ON ui.from_user_id = seller_geo.temp_id AND ui.from_user_type = seller_geo.user_type ";
                                            }
                                            
                                            // B2C seller geo (same logic for company)
                                            $b2c_seller_union_parts = [];
                                            
                                            // Include company for B2C
                                            if(empty($selected_seller_type) || $selected_seller_type == 'company') {
                                                if (empty($selected_district) || $selected_district == 8) {
                                                    $b2c_seller_union_parts[] = "SELECT CAST(id AS CHAR) AS user_id, 'company' AS user_type FROM company_godown WHERE 1=1";
                                                }
                                            }
                                            
                                            // Include other seller types for B2C
                                            if(empty($selected_seller_type) || $selected_seller_type != 'company') {
                                                $b2c_seller_union_parts[] = "SELECT temp_id AS user_id, 'shop' AS user_type FROM shop WHERE state_id = " . (int)$selected_state_id . $district_condition . $taluk_condition;
                                                $b2c_seller_union_parts[] = "SELECT temp_id AS user_id, 'distributor' AS user_type FROM distributor WHERE CAST(state_id AS UNSIGNED) = " . (int)$selected_state_id . $district_condition . $taluk_condition;
                                                $b2c_seller_union_parts[] = "SELECT temp_id AS user_id, 'super_distributor' AS user_type FROM super_distributor WHERE CAST(state_id AS UNSIGNED) = " . (int)$selected_state_id . $district_condition . $taluk_condition;
                                                $b2c_seller_union_parts[] = "SELECT temp_id AS user_id, 'stockiest' AS user_type FROM stockiest WHERE state_id = " . (int)$selected_state_id . $district_condition . (!empty($selected_taluk) ? " AND CAST(taluk_id AS UNSIGNED) = " . (int)$selected_taluk : "");
                                                $b2c_seller_union_parts[] = "SELECT temp_id AS user_id, 'super_stockiest' AS user_type FROM super_stockiest WHERE state_id = " . (int)$selected_state_id . $district_condition;
                                                $b2c_seller_union_parts[] = "SELECT temp_id AS user_id, 'candf' AS user_type FROM c_and_f WHERE CAST(state_id AS UNSIGNED) = " . (int)$selected_state_id . $district_condition;
                                                $b2c_seller_union_parts[] = "SELECT temp_id AS user_id, 'outlet' AS user_type FROM outlet WHERE state_id = " . (int)$selected_state_id . $district_condition . $taluk_condition;
                                            }
                                            
                                            $b2c_seller_geo_join = "";
                                            if(!empty($b2c_seller_union_parts)) {
                                                $b2c_seller_union_query = implode(" UNION ALL ", $b2c_seller_union_parts);
                                                $b2c_seller_geo_join = " INNER JOIN ($b2c_seller_union_query) b2c_seller_geo ON i.user_id = b2c_seller_geo.user_id AND i.user_type = b2c_seller_geo.user_type ";
                                            }
                                            
                                            // SELLER META (for name/mobile search across all seller types)
                                            $seller_meta_parts = [];
                                            $seller_meta_parts[] = "
                                              SELECT temp_id AS id, 'shop' AS user_type,
                                                     CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci AS name,
                                                     CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci AS mobile
                                              FROM shop
                                            ";
                                            $seller_meta_parts[] = "
                                              SELECT temp_id, 'distributor',
                                                     CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
                                              FROM distributor
                                            ";
                                            $seller_meta_parts[] = "
                                              SELECT temp_id, 'super_distributor',
                                                     CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
                                              FROM super_distributor
                                            ";
                                            $seller_meta_parts[] = "
                                              SELECT temp_id, 'stockiest',
                                                     CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
                                              FROM stockiest
                                            ";
                                            $seller_meta_parts[] = "
                                              SELECT temp_id, 'super_stockiest',
                                                     CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
                                              FROM super_stockiest
                                            ";
                                            $seller_meta_parts[] = "
                                              SELECT temp_id, 'candf',
                                                     CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
                                              FROM c_and_f
                                            ";
                                            $seller_meta_parts[] = "
                                              SELECT temp_id, 'outlet',
                                                     CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci,
                                                     CONVERT(mobile_number USING utf8mb4) COLLATE utf8mb4_general_ci
                                              FROM outlet
                                            ";
                                            $seller_meta_parts[] = "
                                              SELECT CAST(id AS CHAR) AS id, 'company' AS user_type,
                                                     CONVERT(gname USING utf8mb4) COLLATE utf8mb4_general_ci AS name,
                                                     CONVERT(contact USING utf8mb4) COLLATE utf8mb4_general_ci AS mobile
                                              FROM company_godown
                                            ";
                                            $seller_meta_union_query = implode(" UNION ALL ", $seller_meta_parts);
                                            $seller_meta_join = " LEFT JOIN ($seller_meta_union_query) seller_meta
                                                                  ON seller_meta.id = ui.from_user_id AND seller_meta.user_type = ui.from_user_type ";
                                            
                                            // --- Search Filters (B2B / B2C) ---
                                            $search_filter_b2b = '';
                                            $search_filter_b2c = '';
                                            
                                            if ($search_esc !== '') {
                                                $date_like = $search_esc; // YYYY-MM-DD or partial
                                                $search_filter_b2b = "
                                                  AND (
                                                      ui.inv_number LIKE '%$search_esc%'
                                                   OR CAST(ui.total AS CHAR) LIKE '%$search_esc%'
                                                   OR ui.date LIKE '%$date_like%'
                                                   OR ui.from_user_type LIKE '%$search_esc%'
                                                   OR ui.to_user_type   LIKE '%$search_esc%'
                                                   OR buyers.name        LIKE '%$search_esc%' COLLATE utf8mb4_general_ci
                                                   OR buyers.mobile_number LIKE '%$search_esc%'
                                                   OR seller_meta.name   LIKE '%$search_esc%' COLLATE utf8mb4_general_ci
                                                   OR seller_meta.mobile LIKE '%$search_esc%'
                                                   OR EXISTS (
                                                        SELECT 1
                                                        FROM user_invoice_items uii
                                                        JOIN products p ON p.id = uii.pr_id
                                                        WHERE uii.inv_id = ui.inv_id
                                                          AND p.productName LIKE '%$search_esc%' COLLATE utf8mb4_general_ci
                                                   )
                                                  )
                                                ";
                                                
                                                $search_filter_b2c = "
                                                  AND (
                                                      i.inv_number LIKE '%$search_esc%'
                                                   OR CAST(i.total AS CHAR) LIKE '%$search_esc%'
                                                   OR i.date LIKE '%$date_like%'
                                                   OR i.user_type LIKE '%$search_esc%'
                                                   OR COALESCE(c.name, 'Walking Customer') LIKE '%$search_esc%' COLLATE utf8mb4_general_ci
                                                   OR COALESCE(c.mobile, '') LIKE '%$search_esc%'
                                                   OR EXISTS (
                                                        SELECT 1
                                                        FROM invoice_items ii
                                                        JOIN products p2 ON p2.id = ii.pr_id
                                                        WHERE ii.inv_id = i.inv_id
                                                          AND p2.productName LIKE '%$search_esc%' COLLATE utf8mb4_general_ci
                                                   )
                                                  )
                                                ";
                                            }
                                            
                                            // COUNT TOTAL RECORDS
                                            $count_b2b_query = "
                                                SELECT COUNT(DISTINCT ui.id) as total
                                                FROM user_invoice ui
                                                $seller_geo_join
                                                $seller_meta_join
                                                INNER JOIN ($buyer_union_query) buyers 
                                                    ON ui.to_user_id = buyers.temp_id AND ui.to_user_type = buyers.user_type
                                                WHERE ui.date BETWEEN '" . $db_conn->real_escape_string($from_date) . "' 
                                                AND '" . $db_conn->real_escape_string($to_date) . "'
                                                AND ui.sub_total > 0
                                                $seller_type_filter
                                                $seller_id_filter
                                                $buyer_type_filter
                                                $buyer_id_filter
                                                $buyer_district_filter_query
                                                $search_filter_b2b
                                            ";
                                            
                                            $result_b2b_count = mysqli_query($db_conn, $count_b2b_query);
                                            $total_b2b = $result_b2b_count ? (int)mysqli_fetch_assoc($result_b2b_count)['total'] : 0;
                                            
                                            $total_b2c = 0;
                                            if(empty($selected_buyer_type) || $selected_buyer_type == 'customer') {
                                                $b2c_seller_type_filter = "";
                                                $b2c_seller_id_filter = "";
                                                
                                                if(!empty($selected_seller_type)) {
                                                    $b2c_seller_type_filter = " AND i.user_type = '" . $db_conn->real_escape_string($selected_seller_type) . "'";
                                                }
                                                
                                                if(!empty($selected_seller_id)) {
                                                    $b2c_seller_id_filter = " AND i.user_id = '" . $db_conn->real_escape_string($selected_seller_id) . "'";
                                                }
                                                
                                                $count_b2c_query = "
                                                    SELECT COUNT(DISTINCT i.id) as total
                                                    FROM invoice i
                                                    $b2c_seller_geo_join
                                                    LEFT JOIN customers c ON i.customer_id = c.id
                                                    WHERE i.date BETWEEN '" . $db_conn->real_escape_string($from_date) . "' 
                                                    AND '" . $db_conn->real_escape_string($to_date) . "'
                                                    AND i.sub_total > 0
                                                    $b2c_seller_type_filter
                                                    $b2c_seller_id_filter
                                                    $search_filter_b2c
                                                ";
                                                
                                                $result_b2c_count = mysqli_query($db_conn, $count_b2c_query);
                                                $total_b2c = $result_b2c_count ? (int)mysqli_fetch_assoc($result_b2c_count)['total'] : 0;
                                            }
                                            
                                            $total_records = $total_b2b + $total_b2c;
                                            $total_pages = max(1, (int)ceil($total_records / max(1, $records_per_page)));
                                            
                                            if ($is_search) {
                                                // single-page view for search
                                                $total_pages = 1;
                                                $page = 1;
                                                $offset = 0;
                                            }
                                            
                                            // GET COMBINED DATA
                                            $query_b2b = "
                                                SELECT 
                                                    ui.id,
                                                    ui.inv_id,
                                                    ui.inv_number,
                                                    ui.date,
                                                    ui.sub_total,       
                                                    ui.courier_charges,
                                                    ui.total,
                                                    ui.to_user_type,
                                                    ui.to_user_id,
                                                    ui.from_user_type,
                                                    ui.from_user_id,
                                                    buyers.name as buyer_name,
                                                    buyers.mobile_number as buyer_mobile,
                                                    'B2B' as source_type
                                                FROM user_invoice ui
                                                $seller_geo_join
                                                $seller_meta_join
                                                INNER JOIN ($buyer_union_query) buyers 
                                                    ON ui.to_user_id = buyers.temp_id AND ui.to_user_type = buyers.user_type
                                                WHERE ui.date BETWEEN '" . $db_conn->real_escape_string($from_date) . "' 
                                                AND '" . $db_conn->real_escape_string($to_date) . "'
                                                AND ui.sub_total > 0
                                                $seller_type_filter
                                                $seller_id_filter
                                                $buyer_type_filter
                                                $buyer_id_filter
                                                $buyer_district_filter_query
                                                $search_filter_b2b
                                            ";
                                            
                                            $query_b2c = "";
                                            if(empty($selected_buyer_type) || $selected_buyer_type == 'customer') {
                                                $b2c_seller_type_filter = "";
                                                $b2c_seller_id_filter = "";
                                                
                                                if(!empty($selected_seller_type)) {
                                                    $b2c_seller_type_filter = " AND i.user_type = '" . $db_conn->real_escape_string($selected_seller_type) . "'";
                                                }
                                                
                                                if(!empty($selected_seller_id)) {
                                                    $b2c_seller_id_filter = " AND i.user_id = '" . $db_conn->real_escape_string($selected_seller_id) . "'";
                                                }
                                                
                                                $query_b2c = "
                                                    SELECT 
                                                        i.id,
                                                        i.inv_id,
                                                        i.inv_number,
                                                        i.date,
                                                        i.sub_total,
                                                        i.courier_charges,
                                                        i.total,
                                                        'customer' as to_user_type,
                                                        i.customer_id as to_user_id,
                                                        i.user_type as from_user_type,
                                                        i.user_id as from_user_id,
                                                        COALESCE(c.name, 'Walking Customer') as buyer_name,
                                                        COALESCE(c.mobile, '') as buyer_mobile,
                                                        'B2C' as source_type
                                                    FROM invoice i
                                                    $b2c_seller_geo_join
                                                    LEFT JOIN customers c ON i.customer_id = c.id
                                                    WHERE i.date BETWEEN '" . $db_conn->real_escape_string($from_date) . "' 
                                                    AND '" . $db_conn->real_escape_string($to_date) . "'
                                                    AND i.sub_total > 0
                                                    $b2c_seller_type_filter
                                                    $b2c_seller_id_filter
                                                    $search_filter_b2c
                                                ";
                                            }
                                            
                                            $final_query = $query_b2b;
                                            if(!empty($query_b2c)) {
                                                $final_query .= " UNION ALL " . $query_b2c;
                                            }
                                            $final_query .= " ORDER BY date DESC, id DESC";
                                            if (!$is_search) {
                                                $final_query .= " LIMIT " . (int)$records_per_page . " OFFSET " . (int)$offset;
                                            }
                                            
                                            $result = mysqli_query($db_conn, $final_query);
                                            
                                            $invoice_ids_b2b = [];
                                            $invoice_ids_b2c = [];
                                            $invoices = [];
                                            
                                            if($result) {
                                                while($row = mysqli_fetch_assoc($result)) {
                                                    $invoices[$row['inv_id']] = $row;
                                                    
                                                    if($row['source_type'] == 'B2B') {
                                                        $invoice_ids_b2b[] = $row['inv_id'];
                                                    } else {
                                                        $invoice_ids_b2c[] = $row['inv_id'];
                                                    }
                                                }
                                            }
                                            
                                            // GET PRODUCT QUANTITIES
                                            $product_quantities = [];
                                            
                                            if(!empty($invoice_ids_b2b)) {
                                                $inv_list = "'" . implode("','", array_map([$db_conn, 'real_escape_string'], $invoice_ids_b2b)) . "'";
                                                $qty_query = "SELECT inv_id, pr_id, qty FROM user_invoice_items WHERE inv_id IN ($inv_list)";
                                                $qty_result = mysqli_query($db_conn, $qty_query);
                                                
                                                if($qty_result) {
                                                    while($row = mysqli_fetch_assoc($qty_result)) {
                                                        $product_quantities[$row['inv_id']][$row['pr_id']] = $row['qty'];
                                                    }
                                                }
                                            }
                                            
                                            if(!empty($invoice_ids_b2c)) {
                                                $inv_list = "'" . implode("','", array_map([$db_conn, 'real_escape_string'], $invoice_ids_b2c)) . "'";
                                                $qty_query = "SELECT inv_id, pr_id, qty FROM invoice_items WHERE inv_id IN ($inv_list)";
                                                $qty_result = mysqli_query($db_conn, $qty_query);
                                                
                                                if($qty_result) {
                                                    while($row = mysqli_fetch_assoc($qty_result)) {
                                                        $product_quantities[$row['inv_id']][$row['pr_id']] = $row['qty'];
                                                    }
                                                }
                                            }
                                            
                                            // ── RETURN INVOICES ─────────────────────────────────────────────
                                            $returns_by_invoice = [];
                                            $return_product_quantities = [];
                                            
                                            $all_inv_ids = array_merge($invoice_ids_b2b, $invoice_ids_b2c);
                                            if (!empty($all_inv_ids)) {
                                                $inv_list = "'".implode("','", array_map([$db_conn,'real_escape_string'], $all_inv_ids))."'";
                                            
                                                $ret_r = mysqli_query($db_conn, "
                                                    SELECT rs.id AS return_id, rs.invnumber AS original_inv_id,
                                                           rs.date AS return_date, rs.total AS return_total,
                                                           rs.from_usertype,
                                                           COALESCE(ss.name,st.name,dt.name,sd.name,sh.name,cf.name,ot.name,c2.name) AS returner_name
                                                    FROM user_return_stock rs
                                                    LEFT JOIN super_stockiest   ss ON rs.from_usertype='super_stockiest'    AND ss.temp_id=rs.from_userid
                                                    LEFT JOIN stockiest         st ON rs.from_usertype='stockiest'          AND st.temp_id=rs.from_userid
                                                    LEFT JOIN distributor       dt ON rs.from_usertype='distributor'        AND dt.temp_id=rs.from_userid
                                                    LEFT JOIN super_distributor sd ON rs.from_usertype='super_distributor'  AND sd.temp_id=rs.from_userid
                                                    LEFT JOIN shop              sh ON rs.from_usertype='shop'               AND sh.temp_id=rs.from_userid
                                                    LEFT JOIN c_and_f           cf ON rs.from_usertype='candf'              AND cf.temp_id=rs.from_userid
                                                    LEFT JOIN outlet            ot ON rs.from_usertype='outlet'             AND ot.temp_id=rs.from_userid
                                                    LEFT JOIN customers         c2 ON rs.from_usertype='customer'           AND c2.id=rs.from_userid
                                                    WHERE rs.invnumber IN ($inv_list)
                                                      AND rs.total > 0
                                                      AND rs.date BETWEEN '$from_date' AND '$to_date'
                                                ");
                                            
                                                $return_ids = [];
                                                if ($ret_r) while ($rrow = mysqli_fetch_assoc($ret_r)) {
                                                    $returns_by_invoice[$rrow['original_inv_id']][] = $rrow;
                                                    $return_ids[] = $rrow['return_id'];
                                                }
                                            
                                                if (!empty($return_ids)) {
                                                    $rid_list = implode(',', array_map('intval', $return_ids));
                                                    $rpq = mysqli_query($db_conn, "SELECT returnid, prid, qty FROM user_return_stock_items WHERE returnid IN ($rid_list)");
                                                    if($rpq) while($rp = mysqli_fetch_assoc($rpq))
                                                        $return_product_quantities[$rp['returnid']][$rp['prid']] = $rp['qty'];
                                                }
                                            }
                                            
                                            // Load any parent invoices that aren't already in $invoices
                                                $missing_inv_ids = array_diff(array_keys($returns_by_invoice), array_keys($invoices));
                                                
                                                if (!empty($missing_inv_ids)) {
                                                    $missing_list = "'".implode("','", array_map([$db_conn,'real_escape_string'], $missing_inv_ids))."'";
                                                    
                                                    // Try user_invoice first
                                                    $mq = mysqli_query($db_conn, "
                                                        SELECT ui.id, ui.inv_id, ui.inv_number, ui.date, ui.sub_total,
                                                               ui.courier_charges, ui.total, ui.to_user_type, ui.to_user_id,
                                                               ui.from_user_type, ui.from_user_id,
                                                               COALESCE(ss.name,st.name,dt.name,sd.name,sh.name,cf.name,ot.name) AS buyer_name,
                                                               COALESCE(ss.mobile_number,st.mobile_number,dt.mobile_number,sd.mobile_number,sh.mobile_number,cf.mobile_number,ot.mobile_number) AS buyer_mobile,
                                                               'B2B' AS source_type
                                                        FROM user_invoice ui
                                                        LEFT JOIN super_stockiest   ss ON ui.to_user_type='super_stockiest'    AND ss.temp_id=ui.to_user_id
                                                        LEFT JOIN stockiest         st ON ui.to_user_type='stockiest'          AND st.temp_id=ui.to_user_id
                                                        LEFT JOIN distributor       dt ON ui.to_user_type='distributor'        AND dt.temp_id=ui.to_user_id
                                                        LEFT JOIN super_distributor sd ON ui.to_user_type='super_distributor'  AND sd.temp_id=ui.to_user_id
                                                        LEFT JOIN shop              sh ON ui.to_user_type='shop'               AND sh.temp_id=ui.to_user_id
                                                        LEFT JOIN c_and_f           cf ON ui.to_user_type='candf'              AND cf.temp_id=ui.to_user_id
                                                        LEFT JOIN outlet            ot ON ui.to_user_type='outlet'             AND ot.temp_id=ui.to_user_id
                                                        WHERE ui.inv_id IN ($missing_list)
                                                    ");
                                                    if ($mq) while ($row = mysqli_fetch_assoc($mq)) {
                                                        $invoices[$row['inv_id']] = $row;
                                                        $invoice_ids_b2b[] = $row['inv_id'];
                                                    }
                                                    
                                                    // Also try invoice table (B2C)
                                                    $mq2 = mysqli_query($db_conn, "
                                                        SELECT i.id, i.inv_id, i.inv_number, i.date, i.sub_total,
                                                               i.courier_charges, i.total, 'customer' AS to_user_type,
                                                               i.customer_id AS to_user_id, i.user_type AS from_user_type,
                                                               i.user_id AS from_user_id,
                                                               COALESCE(c.name,'Walking Customer') AS buyer_name,
                                                               COALESCE(c.mobile,'') AS buyer_mobile,
                                                               'B2C' AS source_type
                                                        FROM invoice i
                                                        LEFT JOIN customers c ON c.id = i.customer_id
                                                        WHERE i.inv_id IN ($missing_list)
                                                    ");
                                                    if ($mq2) while ($row = mysqli_fetch_assoc($mq2)) {
                                                        if (!isset($invoices[$row['inv_id']])) {
                                                            $invoices[$row['inv_id']] = $row;
                                                            $invoice_ids_b2c[] = $row['inv_id'];
                                                        }
                                                    }
                                                }
                                            
                                            // GET SELLER DETAILS
                                            $seller_details = [];
                                            if(!empty($invoices)) {
                                                $seller_types_group = [];
                                                foreach($invoices as $inv) {
                                                    $seller_types_group[$inv['from_user_type']][] = $inv['from_user_id'];
                                                }
                                                
                                                foreach($seller_types_group as $type => $ids) {
                                                    $table_map = [
                                                        'company' => ['table' => 'company_godown', 'id_field' => 'id', 'name_field' => 'gname', 'mobile_field' => 'contact'],
                                                        'super_stockiest' => ['table' => 'super_stockiest', 'id_field' => 'temp_id', 'name_field' => 'name', 'mobile_field' => 'mobile_number'],
                                                        'stockiest' => ['table' => 'stockiest', 'id_field' => 'temp_id', 'name_field' => 'name', 'mobile_field' => 'mobile_number'],
                                                        'distributor' => ['table' => 'distributor', 'id_field' => 'temp_id', 'name_field' => 'name', 'mobile_field' => 'mobile_number'],
                                                        'super_distributor' => ['table' => 'super_distributor', 'id_field' => 'temp_id', 'name_field' => 'name', 'mobile_field' => 'mobile_number'],
                                                        'candf' => ['table' => 'c_and_f', 'id_field' => 'temp_id', 'name_field' => 'name', 'mobile_field' => 'mobile_number'],
                                                        'outlet' => ['table' => 'outlet', 'id_field' => 'temp_id', 'name_field' => 'name', 'mobile_field' => 'mobile_number'],
                                                        'shop' => ['table' => 'shop', 'id_field' => 'temp_id', 'name_field' => 'name', 'mobile_field' => 'mobile_number']
                                                    ];
                                                    
                                                    if(isset($table_map[$type])) {
                                                        $config = $table_map[$type];
                                                        $id_list = "'" . implode("','", array_map([$db_conn, 'real_escape_string'], array_unique($ids))) . "'";
                                                        $seller_query2 = "SELECT {$config['id_field']} as id, {$config['name_field']} as name, {$config['mobile_field']} as mobile 
                                                                         FROM {$config['table']} 
                                                                         WHERE {$config['id_field']} IN ($id_list)";
                                                        $seller_result = mysqli_query($db_conn, $seller_query2);
                                                        
                                                        if($seller_result) {
                                                            while($seller = mysqli_fetch_assoc($seller_result)) {
                                                                $seller_details[$type][$seller['id']] = $seller;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            if(!empty($invoices)) {
                                        ?>
                                    
                                        <div id="overflowon">
                                            <table class="table table-bordered table-hover table-sm">
                                                <thead>
                                                    <tr>
                                                        <th rowspan="2">S.No</th>
                                                        <th rowspan="2">Seller Details</th>
                                                        <th rowspan="2">Invoice Number</th>
                                                        <th rowspan="2">Buyer Type</th>
                                                        <th rowspan="2">Buyer Details</th>
                                                        <th rowspan="2">Date</th>
                                                        <th rowspan="2">Sub Total</th>
                                                        <th rowspan="2">Courier Charge</th>
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
                                                                
                                                                // ADD these two lines alongside the existing grand_total/product_totals inits
                                                                $grand_return_total    = 0;
                                                                $product_return_totals = array_fill_keys(array_keys($products), 0);
                                                                
                                                                foreach($invoices as $inv_id => $invoice):
                                                                    $seller_type = ucwords(str_replace('_', ' ', $invoice['from_user_type']));
                                                                    $seller = $seller_details[$invoice['from_user_type']][$invoice['from_user_id']] ?? ['name' => 'N/A', 'mobile' => ''];
                                                                    
                                                                    $buyer_type = ucwords(str_replace('_', ' ', $invoice['to_user_type']));
                                                                    
                                                                    $grand_total += $invoice['total'];
                                                                    $grand_subtotal += $invoice['sub_total'];
                                                                    $grand_courier += $invoice['courier_charges'];
                                                    ?>
                                                    <tr>
                                                        <td><?=$serial++;?></td>
                                                        <td>
                                                            <strong><?=htmlspecialchars($seller['name']);?></strong><br/>
                                                            <small>(<?=$seller_type;?>)</small><br/>
                                                            <small><?=htmlspecialchars($seller['mobile']);?></small>
                                                        </td>
                                                        <td><?=htmlspecialchars($invoice['inv_number']);?></td>
                                                        <td><?=$buyer_type;?></td>
                                                        <td>
                                                            <?=htmlspecialchars($invoice['buyer_name']);?><br/>
                                                            <small><b>M:</b> <?=htmlspecialchars($invoice['buyer_mobile']);?></small>
                                                        </td>
                                                        <td><?=date("d/M/Y", strtotime($invoice['date']));?></td>
                                                        <td align="right"><strong>₹<?=number_format($invoice['sub_total'], 2);?></strong></td>
                                                        <td align="right"><strong>₹<?=number_format($invoice['courier_charges'], 2);?></strong></td>
                                                        <td align="right"><strong>₹<?=number_format($invoice['total'], 2);?></strong></td>
                                                        
                                                        <?php foreach($products as $pr_id => $pr_name): 
                                                            $qty = $product_quantities[$inv_id][$pr_id] ?? 0;
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
                                                    <?php
                                                        // Return rows — right after the closing </tr> of the invoice row
                                                        $invoice_return_total = 0;
                                                        if(!empty($returns_by_invoice[$inv_id])):
                                                            foreach($returns_by_invoice[$inv_id] as $ret):
                                                                $invoice_return_total += $ret['return_total'];
                                                                $grand_return_total   += $ret['return_total'];
                                                                $ret_id = $ret['return_id'];
                                                        ?>
                                                        <tr style="background:#fff5f5; font-size:12px; color:#c0392b;">
                                                            <td></td>
                                                            <td></td>
                                                            <td colspan="3">
                                                                <b>↩ Return</b> by <?=htmlspecialchars($ret['returner_name'] ?? $ret['from_usertype']);?>
                                                                &nbsp;•&nbsp; <?=date("d/M/Y", strtotime($ret['return_date']));?>
                                                            </td>
                                                            <td></td><td></td><td></td>
                                                            <td align="right">
                                                                <strong>₹<?=number_format($invoice['total'],2);?></strong>
                                                                <?php if(!empty($returns_by_invoice[$inv_id])): 
                                                                    $inv_ret = array_sum(array_column($returns_by_invoice[$inv_id], 'return_total')); ?>
                                                                <br/><small style="color:#c0392b;">-₹<?=number_format($inv_ret,2);?></small>
                                                                <br/><small style="color:#1b5e20;font-weight:600;">Net: ₹<?=number_format($invoice['total']-$inv_ret,2);?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <?php foreach($products as $pr_id => $pr_name):
                                                                $rqty = $return_product_quantities[$ret_id][$pr_id] ?? 0;
                                                                if($rqty > 0) $product_return_totals[$pr_id] += $rqty;
                                                            ?>
                                                            <td align="center" class="product-col">
                                                                <?= $rqty > 0 ? '<span style="color:#c0392b;">-'.$rqty.'</span>' : '<span style="color:#ccc;">-</span>' ?>
                                                            </td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                        <?php endforeach; endif; ?>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot>
                                                    <?php if($grand_return_total > 0): ?>
                                                    <tr style="background:#fff5f5; color:#c0392b; font-weight:bold;">
                                                        <th colspan="8" align="right">Total Returns (–):</th>
                                                        <th align="right">-₹<?=number_format($grand_return_total,2);?></th>
                                                        <?php foreach($product_return_totals as $rt): ?>
                                                        <th align="center" class="product-col"><?= $rt > 0 ? '<span style="color:#c0392b;">-'.$rt.'</span>' : '-' ?></th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                    <?php endif; ?>
                                                
                                                    <tr style="background:#e9ecef; font-weight:bold;">
                                                        <th colspan="6" align="right">Page Total (Sales):</th>
                                                        <th align="right">₹<?=number_format($grand_subtotal,2);?></th>
                                                        <th align="right">₹<?=number_format($grand_courier,2);?></th>
                                                        <th align="right">₹<?=number_format($grand_total,2);?></th>
                                                        <?php foreach($product_totals as $total): ?>
                                                        <th align="center" class="product-col"><?=$total;?></th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                
                                                    <?php if($grand_return_total > 0): ?>
                                                    <tr style="background:#e8f5e9; font-weight:bold; color:#1b5e20;">
                                                        <th colspan="6" align="right">Net Total (Sales – Returns):</th>
                                                        <th align="right">₹<?=number_format($grand_subtotal - $grand_return_total,2);?></th>
                                                        <th align="right">₹<?=number_format($grand_courier,2);?></th>
                                                        <th align="right">₹<?=number_format($grand_total - $grand_return_total,2);?></th>
                                                        <?php foreach($products as $pr_id => $pr_name): ?>
                                                        <th align="center" class="product-col">
                                                            <?= $product_totals[$pr_id] - ($product_return_totals[$pr_id] ?? 0) ?>
                                                        </th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                    <?php endif; ?>
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
                                                    echo '<div class="alert alert-warning">No invoices found for the selected filters and date range.</div>';
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
        // Initialize visibility for seller name container and dynamic filters
        document.addEventListener('DOMContentLoaded', function() {
            initializeSellerNameVisibility();
            
            window.currentFilters = {
                stateId: document.querySelector('input[name="state_id"]') ? document.querySelector('input[name="state_id"]').value : '',
                districtId: document.getElementById('district_filter') ? document.getElementById('district_filter').value : '',
                talukId: document.getElementById('taluk_filter') ? document.getElementById('taluk_filter').value : '',
                sellerType: document.getElementById('seller_type_filter') ? document.getElementById('seller_type_filter').value : ''
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
                    
                    if(window.currentFilters.sellerType) {
                        setTimeout(() => reloadSellersWithFilters(), 300);
                    }
                });
            }
            
            const talukEl = document.getElementById('taluk_filter');
            if (talukEl) {
                talukEl.addEventListener('change', function() {
                    window.currentFilters.talukId = this.value;
                    if(window.currentFilters.sellerType) {
                        reloadSellersWithFilters();
                    }
                });
            }
            
            const sellerTypeEl = document.getElementById('seller_type_filter');
            if (sellerTypeEl) {
                sellerTypeEl.addEventListener('change', function() {
                    const sellerType = this.value;
                    window.currentFilters.sellerType = sellerType;
                    
                    if(sellerType) {
                        document.getElementById('seller_name_container').style.display = 'block';
                        reloadSellersWithFilters();
                    } else {
                        document.getElementById('seller_name_container').style.display = 'none';
                        document.getElementById('seller_name_filter').innerHTML = '<option value="">All Sellers</option>';
                    }
                });
            }
            
            const buyerTypeEl = document.getElementById('buyer_type_filter');
                if(buyerTypeEl) {
                    buyerTypeEl.addEventListener('change', function() {
                        const buyerType = this.value;
                        const container = document.getElementById('buyer_name_container');
                        if(container) container.style.display = buyerType ? 'block' : 'none';
                        if(buyerType) reloadBuyersWithFilters(buyerType);
                        else document.getElementById('buyer_name_filter').innerHTML = '<option value="">All Buyers</option>';
                    });
                }
        });
        
        function initializeSellerNameVisibility() {
            const sellerTypeEl = document.getElementById('seller_type_filter');
            const container = document.getElementById('seller_name_container');
            if(!sellerTypeEl || !container) return;
            if(sellerTypeEl.value) {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }
        
        function reloadSellersWithFilters() {
            const sellerNameSelect = document.getElementById('seller_name_filter');
            if(!sellerNameSelect || !window.currentFilters.sellerType) return;
            
            const previouslySelectedSeller = sellerNameSelect.value;
            sellerNameSelect.innerHTML = '<option value="">Loading sellers...</option>';
            
            let url = `get_filter_data.php?action=get_sellers&seller_type=${encodeURIComponent(window.currentFilters.sellerType)}`;
            if(window.currentFilters.stateId)   url += `&state_id=${encodeURIComponent(window.currentFilters.stateId)}`;
            if(window.currentFilters.districtId)url += `&district_id=${encodeURIComponent(window.currentFilters.districtId)}`;
            if(window.currentFilters.talukId)   url += `&taluk_id=${encodeURIComponent(window.currentFilters.talukId)}`;
            url += `&_=${Date.now()}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    sellerNameSelect.innerHTML = '<option value="">All Sellers</option>';
                    if(data.success && data.data && data.data.length > 0) {
                        data.data.forEach(seller => {
                            const option = document.createElement('option');
                            option.value = seller.id;
                            option.textContent = seller.name;
                            if(String(seller.id) === String(previouslySelectedSeller)) {
                                option.selected = true;
                            }
                            sellerNameSelect.appendChild(option);
                        });
                    } else {
                        sellerNameSelect.innerHTML = '<option value="">No sellers found in this area</option>';
                    }
                })
                .catch(() => {
                    sellerNameSelect.innerHTML = '<option value="">Error loading sellers</option>';
                });
        }
        
        function reloadBuyersWithFilters(buyerType) {
            const sel = document.getElementById('buyer_name_filter');
            if(!sel || !buyerType) return;
            const prev = sel.value;
            sel.innerHTML = '<option value="">Loading buyers...</option>';
            let url = `get_filter_data.php?action=get_buyers&buyer_type=${encodeURIComponent(buyerType)}`;
            if(window.currentFilters.stateId)    url += `&state_id=${encodeURIComponent(window.currentFilters.stateId)}`;
            if(window.currentFilters.districtId) url += `&district_id=${encodeURIComponent(window.currentFilters.districtId)}`;
            url += `&_=${Date.now()}`;
            fetch(url).then(r=>r.json()).then(data => {
                sel.innerHTML = '<option value="">All Buyers</option>';
                if(data.success && data.data?.length > 0) {
                    data.data.forEach(b => {
                        const o = document.createElement('option');
                        o.value = b.id; o.textContent = b.name;
                        if(String(b.id) === String(prev)) o.selected = true;
                        sel.appendChild(o);
                    });
                } else { sel.innerHTML = '<option value="">No buyers found</option>'; }
            }).catch(() => { sel.innerHTML = '<option value="">Error loading buyers</option>'; });
        }
    </script>
</body>
</html>