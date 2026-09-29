<?php
// Territory Partner Retail Sales Report -- standalone (not folded into
// Retail-Report-Details.php's super_stockiest/stockiest flow, since TP
// uses a completely different location hierarchy: partner_location_nodes
// District(3) -> Division(4) -> Taluk(5) -> Firka(6), not the district/
// taluk tables that page's District/Taluk dropdowns read from. No Taluk
// filter, no Excel export -- per request.
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

include("checksession.php");
include("config.php");

if(isset($_GET['clear_filters']) || isset($_POST['clear_all'])) {
    ob_clean();
    unset($_SESSION['tp_retail_report_from_date']);
    unset($_SESSION['tp_retail_report_to_date']);
    unset($_SESSION['tp_retail_report_district_ids']);
    unset($_SESSION['tp_retail_report_firka_ids']);
    unset($_SESSION['tp_retail_report_tp_ids']);
    unset($_SESSION['tp_retail_report_amount_range']);
    unset($_SESSION['tp_retail_report_records_per_page']);
    unset($_SESSION['tp_retail_report_search']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

mysqli_set_charset($db_conn, 'utf8mb4');
mysqli_query($db_conn, "SET collation_connection = 'utf8mb4_general_ci'");
mysqli_query($db_conn, "SET collation_server = 'utf8mb4_general_ci'");

// Calculate last 7 days date range (default)
$to_date = date('Y-m-d');
$from_date = date('Y-m-d', strtotime('-7 days'));

if(isset($_POST['frdate']) && !empty($_POST['frdate'])) {
    $from_date = $_POST['frdate'];
    $_SESSION['tp_retail_report_from_date'] = $from_date;
}
if(isset($_POST['todate']) && !empty($_POST['todate'])) {
    $to_date = $_POST['todate'];
    $_SESSION['tp_retail_report_to_date'] = $to_date;
}
if(isset($_SESSION['tp_retail_report_from_date'])) {
    $from_date = $_SESSION['tp_retail_report_from_date'];
}
if(isset($_SESSION['tp_retail_report_to_date'])) {
    $to_date = $_SESSION['tp_retail_report_to_date'];
}

// District filter (partner_location_nodes depth=3) -- multi-select
$prevDistrictIds = isset($_SESSION['tp_retail_report_district_ids']) ? (array)$_SESSION['tp_retail_report_district_ids'] : [];

$selected_district_ids = [];
if(isset($_POST['district_id'])) {
    $selected_district_ids = array_values(array_unique(array_map('intval', (array)$_POST['district_id'])));
    $selected_district_ids = array_values(array_filter($selected_district_ids, fn($id) => $id > 0));
    if(!empty($selected_district_ids)) {
        $_SESSION['tp_retail_report_district_ids'] = $selected_district_ids;
    } else {
        unset($_SESSION['tp_retail_report_district_ids']);
    }
} elseif(!empty($prevDistrictIds)) {
    $selected_district_ids = $prevDistrictIds;
}

// Firka filter (partner_location_nodes depth=6) -- multi-select
$selected_firka_ids = [];
if(isset($_POST['firka_id'])) {
    $selected_firka_ids = array_values(array_unique(array_map('intval', (array)$_POST['firka_id'])));
    $selected_firka_ids = array_values(array_filter($selected_firka_ids, fn($id) => $id > 0));
    if(!empty($selected_firka_ids)) {
        $_SESSION['tp_retail_report_firka_ids'] = $selected_firka_ids;
    } else {
        unset($_SESSION['tp_retail_report_firka_ids']);
    }
} elseif(isset($_SESSION['tp_retail_report_firka_ids'])) {
    $selected_firka_ids = (array)$_SESSION['tp_retail_report_firka_ids'];
}

// NOTE: no server-side "clear firka if district changed" here (unlike the
// old single-select version) -- the client already clears/repopulates the
// Firka select the moment District changes (see the change handler below),
// so whatever firka_id[] arrives in $_POST is already consistent with the
// submitted district_id[]. Auto-clearing here as well wiped out a firka
// selection submitted together with a first-ever district pick (no prior
// session value to compare against), which is a real, not a defensive, case.

// TP Name filter (partner_id, i.e. territory_partners.id) -- multi-select,
// same "trust whatever arrives together" reasoning as Firka above: the
// client repopulates this list the moment District/Firka changes.
$selected_tp_ids = [];
if(isset($_POST['tp_id'])) {
    $selected_tp_ids = array_values(array_unique(array_map('intval', (array)$_POST['tp_id'])));
    $selected_tp_ids = array_values(array_filter($selected_tp_ids, fn($id) => $id > 0));
    if(!empty($selected_tp_ids)) {
        $_SESSION['tp_retail_report_tp_ids'] = $selected_tp_ids;
    } else {
        unset($_SESSION['tp_retail_report_tp_ids']);
    }
} elseif(isset($_SESSION['tp_retail_report_tp_ids'])) {
    $selected_tp_ids = (array)$_SESSION['tp_retail_report_tp_ids'];
}

// Amount range filter
$selected_amount_range = '';
if(isset($_POST['amount_range'])) {
    $selected_amount_range = !empty($_POST['amount_range']) ? $_POST['amount_range'] : '';
    if($selected_amount_range) {
        $_SESSION['tp_retail_report_amount_range'] = $selected_amount_range;
    } else {
        unset($_SESSION['tp_retail_report_amount_range']);
    }
} elseif(isset($_SESSION['tp_retail_report_amount_range'])) {
    $selected_amount_range = $_SESSION['tp_retail_report_amount_range'];
}

$Report_LABLE = "Retail Sales Report - Territory Partner";

// Pagination settings
$records_per_page = 20;
if(isset($_POST['records_per_page'])) {
    $records_per_page = (int)$_POST['records_per_page'];
    $_SESSION['tp_retail_report_records_per_page'] = $records_per_page;
} elseif(isset($_SESSION['tp_retail_report_records_per_page'])) {
    $records_per_page = (int)$_SESSION['tp_retail_report_records_per_page'];
}
$allowed_values = [20, 40, 60];
if(!in_array($records_per_page, $allowed_values)) {
    $records_per_page = 20;
}

// Universal search
$search = '';
if (isset($_GET['q'])) {
    $search = trim($_GET['q']);
    $_SESSION['tp_retail_report_search'] = $search;
} elseif (isset($_POST['q'])) {
    $search = trim($_POST['q']);
    $_SESSION['tp_retail_report_search'] = $search;
} elseif (isset($_SESSION['tp_retail_report_search'])) {
    $search = $_SESSION['tp_retail_report_search'];
}
$search_esc = $db_conn->real_escape_string($search);
$is_search = ($search !== '');

$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$offset = ($page - 1) * $records_per_page;
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

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

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
        .table-bordered { border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; }
        .table-hover tbody tr:hover { background-color: rgba(0,123,255,0.05); transition: background-color 0.2s ease; }
        .card { border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.08); border-radius: 12px; }
        .card-header { background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0; }
        .pagination .page-link { border-radius: 6px; margin: 0 2px; border: none; background: #f8f9fa; color: #495057; font-weight: 500; }
        .pagination .page-item.active .page-link { background: #0d6efd; color: white; box-shadow: 0 2px 4px rgba(13,110,253,0.3); }
        .pagination .page-link:hover { background: #e9ecef; color: #495057; }
        .form-select-sm { border-radius: 6px; border-color: #ced4da; font-size: 13px; }
        .alert { border-radius: 8px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        @media (max-width: 768px) {
            .table th, .table td { font-size: 12px; padding: 8px 4px; }
            .card-header .d-flex { flex-direction: column; gap: 10px; }
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

                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble">
                                            <tr>
                                                <td><?=$Report_LABLE;?></td>
                                                <td>
                                                  <form method="post" action="export_retail_report_tp_xlsx.php" target="_blank" class="d-inline">
                                                    <input type="hidden" name="frdate" value="<?=$from_date;?>">
                                                    <input type="hidden" name="todate" value="<?=$to_date;?>">
                                                    <?php foreach($selected_district_ids as $did): ?>
                                                    <input type="hidden" name="district_id[]" value="<?=(int)$did;?>">
                                                    <?php endforeach; ?>
                                                    <?php foreach($selected_firka_ids as $fid): ?>
                                                    <input type="hidden" name="firka_id[]" value="<?=(int)$fid;?>">
                                                    <?php endforeach; ?>
                                                    <?php foreach($selected_tp_ids as $tid): ?>
                                                    <input type="hidden" name="tp_id[]" value="<?=(int)$tid;?>">
                                                    <?php endforeach; ?>
                                                    <input type="hidden" name="amount_range" value="<?=$selected_amount_range;?>">
                                                    <input type="hidden" name="q" value="<?=htmlspecialchars($search, ENT_QUOTES);?>">
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

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Advanced Filters</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="<?=$_SERVER['PHP_SELF'];?>" id="filterForm">
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
                                                    <select name="district_id[]" id="district_filter" class="form-control" multiple="multiple" style="width:100%;">
                                                        <?php
                                                        $dist_query = "SELECT id, name FROM partner_location_nodes
                                                                       WHERE depth = 3
                                                                         AND parent_id = (SELECT id FROM partner_location_nodes WHERE depth = 2 AND name = 'Tamilnadu' LIMIT 1)
                                                                       ORDER BY name ASC";
                                                        $dist_result = mysqli_query($db_conn, $dist_query);
                                                        if($dist_result) {
                                                            while($dist = mysqli_fetch_assoc($dist_result)) {
                                                                $selected_attr = in_array((int)$dist['id'], $selected_district_ids, true) ? 'selected' : '';
                                                                echo '<option value="'.$dist['id'].'" '.$selected_attr.'>'.htmlspecialchars($dist['name']).'</option>';
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">Firka</label>
                                                    <select name="firka_id[]" id="firka_filter" class="form-control" multiple="multiple" style="width:100%;">
                                                        <?php
                                                        if(!empty($selected_district_ids)) {
                                                            $placeholders = implode(',', array_fill(0, count($selected_district_ids), '?'));
                                                            $types = str_repeat('i', count($selected_district_ids));
                                                            $firka_query = "SELECT DISTINCT f.id, f.name
                                                                             FROM partner_location_nodes dv
                                                                             INNER JOIN partner_location_nodes t ON t.parent_id = dv.id AND t.depth = 5
                                                                             INNER JOIN partner_location_nodes f ON f.parent_id = t.id AND f.depth = 6
                                                                             WHERE dv.parent_id IN ($placeholders) AND dv.depth = 4
                                                                             ORDER BY f.name ASC";
                                                            $stmt_firka = $db_conn->prepare($firka_query);
                                                            $stmt_firka->bind_param($types, ...$selected_district_ids);
                                                            $stmt_firka->execute();
                                                            $firka_result = $stmt_firka->get_result();
                                                            while($firka = $firka_result->fetch_assoc()) {
                                                                $selected_attr = in_array((int)$firka['id'], $selected_firka_ids, true) ? 'selected' : '';
                                                                echo '<option value="'.$firka['id'].'" '.$selected_attr.'>'.htmlspecialchars($firka['name']).'</option>';
                                                            }
                                                            $stmt_firka->close();
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">TP Name</label>
                                                    <select name="tp_id[]" id="tp_filter" class="form-control" multiple="multiple" style="width:100%;">
                                                        <?php
                                                        if(!empty($selected_district_ids) || !empty($selected_firka_ids)) {
                                                            $tpNameCondition = "";
                                                            if(!empty($selected_firka_ids)) {
                                                                $fidList = implode(',', array_map('intval', $selected_firka_ids));
                                                                $tpNameCondition = " AND EXISTS (
                                                                    SELECT 1 FROM territory_partner_locations tpl
                                                                    WHERE tpl.territory_partner_id = tp.id AND tpl.location_id IN ($fidList)
                                                                )";
                                                            } elseif(!empty($selected_district_ids)) {
                                                                $didList = implode(',', array_map('intval', $selected_district_ids));
                                                                $tpNameCondition = " AND EXISTS (
                                                                    SELECT 1 FROM territory_partner_locations tpl
                                                                    INNER JOIN partner_location_nodes f  ON f.id = tpl.location_id
                                                                    INNER JOIN partner_location_nodes t  ON t.id = f.parent_id
                                                                    INNER JOIN partner_location_nodes dv ON dv.id = t.parent_id
                                                                    WHERE tpl.territory_partner_id = tp.id AND dv.parent_id IN ($didList)
                                                                )";
                                                            }
                                                            $tpNameQuery = "SELECT tp.id, CONVERT(tp.name USING utf8mb4) COLLATE utf8mb4_general_ci as name
                                                                             FROM territory_partners tp
                                                                             WHERE tp.deleted_at IS NULL" . $tpNameCondition . "
                                                                             ORDER BY name ASC";
                                                            $tpNameResult = mysqli_query($db_conn, $tpNameQuery);
                                                            if($tpNameResult) {
                                                                while($tpRow = mysqli_fetch_assoc($tpNameResult)) {
                                                                    $selected_attr = in_array((int)$tpRow['id'], $selected_tp_ids, true) ? 'selected' : '';
                                                                    echo '<option value="'.$tpRow['id'].'" '.$selected_attr.'>'.htmlspecialchars($tpRow['name']).'</option>';
                                                                }
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">Amount Range</label>
                                                    <select name="amount_range" id="amount_range_filter" class="form-control">
                                                        <option value="">All Amounts</option>
                                                        <option value="10000-49999" <?= $selected_amount_range == '10000-49999' ? 'selected' : ''; ?>>₹10,000 - ₹49,999</option>
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

                                            <?php
                                            $active_filters = [];
                                            if(!empty($selected_district_ids)) {
                                                $placeholders = implode(',', array_fill(0, count($selected_district_ids), '?'));
                                                $types = str_repeat('i', count($selected_district_ids));
                                                $dn_stmt = $db_conn->prepare("SELECT name FROM partner_location_nodes WHERE id IN ($placeholders) ORDER BY name ASC");
                                                $dn_stmt->bind_param($types, ...$selected_district_ids);
                                                $dn_stmt->execute();
                                                $dn_names = [];
                                                $dn_res = $dn_stmt->get_result();
                                                while($row_dn = $dn_res->fetch_assoc()) { $dn_names[] = $row_dn['name']; }
                                                if(!empty($dn_names)) {
                                                    $active_filters[] = "District: " . implode(', ', $dn_names);
                                                }
                                                $dn_stmt->close();
                                            }
                                            if(!empty($selected_firka_ids)) {
                                                $fplaceholders = implode(',', array_fill(0, count($selected_firka_ids), '?'));
                                                $ftypes = str_repeat('i', count($selected_firka_ids));
                                                $fn_stmt = $db_conn->prepare("SELECT name FROM partner_location_nodes WHERE id IN ($fplaceholders) ORDER BY name ASC");
                                                $fn_stmt->bind_param($ftypes, ...$selected_firka_ids);
                                                $fn_stmt->execute();
                                                $fn_names = [];
                                                $fn_res = $fn_stmt->get_result();
                                                while($row_fn = $fn_res->fetch_assoc()) { $fn_names[] = $row_fn['name']; }
                                                if(!empty($fn_names)) {
                                                    $active_filters[] = "Firka: " . implode(', ', $fn_names);
                                                }
                                                $fn_stmt->close();
                                            }
                                            if(!empty($selected_tp_ids)) {
                                                $tplaceholders = implode(',', array_fill(0, count($selected_tp_ids), '?'));
                                                $ttypes = str_repeat('i', count($selected_tp_ids));
                                                $tn_stmt = $db_conn->prepare("SELECT CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci as name FROM territory_partners WHERE id IN ($tplaceholders) ORDER BY name ASC");
                                                $tn_stmt->bind_param($ttypes, ...$selected_tp_ids);
                                                $tn_stmt->execute();
                                                $tn_names = [];
                                                $tn_res = $tn_stmt->get_result();
                                                while($row_tn = $tn_res->fetch_assoc()) { $tn_names[] = $row_tn['name']; }
                                                if(!empty($tn_names)) {
                                                    $active_filters[] = "TP: " . implode(', ', $tn_names);
                                                }
                                                $tn_stmt->close();
                                            }
                                            if(!empty($selected_amount_range)) {
                                                $amount_labels = [
                                                    '10000-49999' => '₹10,000 - ₹49,999',
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

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-header py-3 bg-white border-0">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <form method="post" action="<?=$_SERVER['PHP_SELF'];?>" class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="frdate" value="<?=$from_date;?>">
                                                <input type="hidden" name="todate" value="<?=$to_date;?>">
                                                <?php foreach($selected_district_ids as $did): ?>
                                                <input type="hidden" name="district_id[]" value="<?=(int)$did;?>">
                                                <?php endforeach; ?>
                                                <?php foreach($selected_firka_ids as $fid): ?>
                                                <input type="hidden" name="firka_id[]" value="<?=(int)$fid;?>">
                                                <?php endforeach; ?>
                                                <?php foreach($selected_tp_ids as $tid): ?>
                                                <input type="hidden" name="tp_id[]" value="<?=(int)$tid;?>">
                                                <?php endforeach; ?>
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

                                            <div class="d-flex align-items-center gap-3">
                                              <form method="get" action="<?=$_SERVER['PHP_SELF'];?>" id="searchForm" class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="page" value="1">
                                                <input type="text" name="q" value="<?=htmlspecialchars($search, ENT_QUOTES);?>"
                                                       class="form-control form-control-sm" placeholder="Search name, mobile, product..." style="min-width:280px">
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
$products = [];
$product_query = "SELECT id, productName FROM products WHERE (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) ORDER BY id ASC";
$product_result = mysqli_query($db_conn, $product_query);
if($product_result) {
    while($pr = mysqli_fetch_assoc($product_result)) {
        $products[$pr['id']] = $pr['productName'];
    }
}

// TP location filter -- Firka is most specific, takes priority if both set.
$tp_location_condition = "";
if(!empty($selected_firka_ids)) {
    $firkaIdList = implode(',', array_map('intval', $selected_firka_ids));
    $tp_location_condition = " AND EXISTS (
        SELECT 1 FROM territory_partner_locations tpl
        WHERE tpl.territory_partner_id = tp.id AND tpl.location_id IN ($firkaIdList)
    )";
} elseif(!empty($selected_district_ids)) {
    $districtIdList = implode(',', array_map('intval', $selected_district_ids));
    $tp_location_condition = " AND EXISTS (
        SELECT 1 FROM territory_partner_locations tpl
        INNER JOIN partner_location_nodes f  ON f.id = tpl.location_id
        INNER JOIN partner_location_nodes t  ON t.id = f.parent_id
        INNER JOIN partner_location_nodes dv ON dv.id = t.parent_id
        WHERE tpl.territory_partner_id = tp.id AND dv.parent_id IN ($districtIdList)
    )";
}

// Explicit TP Name selection -- narrows further on top of any
// District/Firka filter (a TP chosen by name is trusted as-is, same as
// every other filter here; the client only ever offers names that
// already match the current District/Firka selection).
$tp_id_condition = "";
if(!empty($selected_tp_ids)) {
    $tpIdList = implode(',', array_map('intval', $selected_tp_ids));
    $tp_id_condition = " AND tp.id IN ($tpIdList)";
}

$sellers = [];
$query = "SELECT tp.id as seller_id,
                 CONVERT(tp.name USING utf8mb4) COLLATE utf8mb4_general_ci as seller_name,
                 CONVERT(tp.mobile USING utf8mb4) COLLATE utf8mb4_general_ci as seller_mobile
          FROM territory_partners tp
          WHERE tp.deleted_at IS NULL" . $tp_location_condition . $tp_id_condition;
$result = mysqli_query($db_conn, $query);
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $sellers[] = $row;
    }
}

// Sales via user_invoice (shop channel) -- same table/convention
// Retail-Report-Details.php uses for every other seller type.
$sellers_with_data = [];
foreach($sellers as $seller) {
    $total_query = "SELECT COALESCE(SUM(sub_total), 0) as sub_total,
                    COALESCE(SUM(courier_charges), 0) as courier_charges,
                    COALESCE(SUM(total), 0) as total_amount
                    FROM user_invoice
                    WHERE from_user_id = '" . $db_conn->real_escape_string($seller['seller_id']) . "'
                    AND from_user_type = 'territory_partner'
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

        $include_seller = false;
        if(!empty($selected_amount_range)) {
            switch($selected_amount_range) {
                case '10000-49999':
                    $include_seller = ($seller['total_amount'] >= 10000 && $seller['total_amount'] <= 49999);
                    break;
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

        if($include_seller) {
            if($search_esc !== '') {
                if(stripos($seller['seller_name'], $search_esc) !== false ||
                   stripos($seller['seller_mobile'], $search_esc) !== false) {
                    $sellers_with_data[] = $seller;
                }
            } else {
                $sellers_with_data[] = $seller;
            }
        }
    }
}

usort($sellers_with_data, function($a, $b) {
    return $b['total_amount'] <=> $a['total_amount'];
});

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

$seller_product_quantities = [];
foreach($sellers_paginated as $seller) {
    $seller_id = $seller['seller_id'];
    $product_qty_query = "
        SELECT uii.pr_id, SUM(uii.qty) as total_qty
        FROM user_invoice ui
        INNER JOIN user_invoice_items uii ON ui.inv_id = uii.inv_id
        WHERE ui.from_user_id = '" . $db_conn->real_escape_string($seller_id) . "'
        AND ui.from_user_type = 'territory_partner'
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
                <th rowspan="2">TP Name</th>
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
                    $grand_total += $seller['total_amount'];
                    $grand_subtotal += $seller['sub_total'];
                    $grand_courier += $seller['courier_charges'];
            ?>
            <tr>
                <td><?=$serial++;?></td>
                <td><strong><?=htmlspecialchars($seller['seller_name']);?></strong></td>
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
                <th colspan="3" align="right">Page Total:</th>
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

<?php if(!$is_search && $total_pages > 1): ?>
<nav aria-label="Page navigation" class="mt-3">
    <ul class="pagination justify-content-center">
        <?php if($page > 1): ?>
        <li class="page-item"><a class="page-link" href="?page=<?=($page-1);?>&q=<?=$qparam;?>">Previous</a></li>
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
        <li class="page-item"><a class="page-link" href="?page=<?=($page+1);?>&q=<?=$qparam;?>">Next</a></li>
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
    echo '<div class="alert alert-warning">No Territory Partners found for the selected filters and date range.</div>';
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

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/plugins/select2/js/select2.full.min.js"></script>

    <script>
        $(function () {
            $('#district_filter').select2({
                placeholder: 'All Districts',
                allowClear: true,
                closeOnSelect: false,
                width: '100%'
            });
            $('#firka_filter').select2({
                placeholder: 'All Firkas',
                allowClear: true,
                closeOnSelect: false,
                width: '100%'
            });
            $('#amount_range_filter').select2({
                placeholder: 'All Amounts',
                allowClear: true,
                width: '100%'
            });
            $('#tp_filter').select2({
                placeholder: 'All TPs',
                allowClear: true,
                closeOnSelect: false,
                width: '100%'
            });
        });

        // Refills the TP Name dropdown from whatever District/Firka is
        // currently selected -- Firka wins when both are set, same
        // priority the server's own query uses.
        function refreshTpNames() {
            const districtIds = $('#district_filter').val() || [];
            const firkaIds = $('#firka_filter').val() || [];
            const $tpSelect = $('#tp_filter');
            if (!$tpSelect.length) return;

            if (districtIds.length === 0 && firkaIds.length === 0) {
                $tpSelect.empty().trigger('change');
                return;
            }

            const params = firkaIds.length > 0
                ? `firka_id=${firkaIds.join(',')}`
                : `district_id=${districtIds.join(',')}`;
            const url = `get_filter_data.php?action=get_tps&${params}&_=${Date.now()}`;
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    $tpSelect.empty();
                    if (data.success && data.data && data.data.length > 0) {
                        data.data.forEach(tp => {
                            $tpSelect.append(new Option(tp.name, tp.id, false, false));
                        });
                    }
                    $tpSelect.trigger('change');
                })
                .catch(() => {
                    $tpSelect.empty().trigger('change');
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const districtEl = document.getElementById('district_filter');
            if (districtEl) {
                $(districtEl).on('change', function() {
                    const districtIds = $(this).val() || [];
                    const $firkaSelect = $('#firka_filter');
                    if (!$firkaSelect.length) return;

                    $firkaSelect.empty().trigger('change');

                    if(districtIds.length > 0) {
                        const url = `get_filter_data.php?action=get_firkas&district_id=${districtIds.join(',')}&_=${Date.now()}`;
                        fetch(url)
                            .then(response => response.json())
                            .then(data => {
                                $firkaSelect.empty();
                                if(data.success && data.data && data.data.length > 0) {
                                    data.data.forEach(firka => {
                                        $firkaSelect.append(new Option(firka.name, firka.id, false, false));
                                    });
                                }
                                $firkaSelect.trigger('change');
                            })
                            .catch(() => {
                                $firkaSelect.empty().trigger('change');
                            });
                    }
                });
            }

            $('#firka_filter').on('change', refreshTpNames);
        });
    </script>
</body>
</html>
