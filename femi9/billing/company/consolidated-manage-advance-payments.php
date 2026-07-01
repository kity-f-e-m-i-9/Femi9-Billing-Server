<?php
/**
 * Manage Advance Payments - Enhanced Version with Soft Delete
 * Femi9 Billing Application
 * 
 * Description: List all advance payment entries with comprehensive filtering and soft delete
 * Features: Date filter, payer type/name/district, receiver type/district/name, status filter, soft delete
 * 
 * @author Femi9 Development Team
 * @version 2.2
 * @date 2025-01-22
 */

declare(strict_types=1);

// Security headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com https://cdn.datatables.net https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdn.datatables.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.datatables.net;");

require_once("checksession.php"); 
require_once("config.php"); 

date_default_timezone_set("Asia/Kolkata");

// Production error handling
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/advance-payments-errors.log');

if ($db_conn) {
    mysqli_set_charset($db_conn, 'utf8mb4');
}

// ============================================================================
// SESSION & SECURITY
// ============================================================================

$logged_user_id = $_SESSION['LOGIN_USER_ID'] ?? '';
$logged_user_type = $_SESSION['LOGIN_USER_TYPE'] ?? '';
$logged_user_name = $_SESSION['LOGIN_USER'] ?? '';

if (empty($logged_user_id) || empty($logged_user_type)) {
    header("Location: login.php");
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================================
// VALIDATION FUNCTIONS
// ============================================================================

/**
 * Validate date input
 * 
 * @param string|null $date Date string to validate
 * @param bool $allowFuture Whether to allow future dates
 * @return string|null Validated date or null
 */
function validateDate(?string $date, bool $allowFuture = false): ?string {
    if (empty($date)) return null;
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
    
    $parts = explode('-', $date);
    if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) return null;
    
    if (!$allowFuture && strtotime($date) > strtotime(date('Y-m-d'))) {
        return date('Y-m-d');
    }
    
    return $date;
}

/**
 * Validate user type against allowed types
 * 
 * @param string|null $type User type to validate
 * @param array $allowedTypes Array of allowed user types
 * @return string Validated type or empty string
 */
function validateUserType(?string $type, array $allowedTypes): string {
    return in_array($type ?? '', $allowedTypes, true) ? $type : '';
}

/**
 * Validate status against allowed statuses
 * 
 * @param string|null $status Status to validate
 * @return string Validated status or empty string
 */
function validateStatus(?string $status): string {
    $allowedStatuses = ['active', 'partially_adjusted', 'fully_adjusted', ''];
    return in_array($status ?? '', $allowedStatuses, true) ? $status : '';
}

/**
 * Validate and sanitize integer input
 * 
 * @param mixed $value Value to validate
 * @return int Validated integer (0 if invalid)
 */
function validateInt($value): int {
    $filtered = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['default' => 0, 'min_range' => 0]
    ]);
    return $filtered !== false ? $filtered : 0;
}

// ============================================================================
// FILTER PARAMETERS WITH VALIDATION
// ============================================================================

$filter_from_date = validateDate($_GET['from_date'] ?? null) ?? date('Y-m-01');
$filter_to_date = validateDate($_GET['to_date'] ?? null) ?? date('Y-m-d');

// Ensure from_date is not after to_date
if (strtotime($filter_from_date) > strtotime($filter_to_date)) {
    [$filter_from_date, $filter_to_date] = [$filter_to_date, $filter_from_date];
}

$allowed_payer_types = ['super_stockiest', 'stockiest', 'distributor', 'super_distributor', 'c_and_f', ''];
$filter_payer_type = validateUserType($_GET['payer_type'] ?? '', $allowed_payer_types);

$allowed_receiver_types = ['company', 'c_and_f', 'super_stockiest', 'stockiest', 'distributor', 'super_distributor', ''];

// Check if receiver_type was explicitly passed in URL
$filter_receiver_type = isset($_GET['receiver_type']) 
    ? validateUserType($_GET['receiver_type'], $allowed_receiver_types)
    : 'company'; // Default to company

$filter_payer_id = validateInt($_GET['payer_id'] ?? 0);
$filter_payer_district_id = validateInt($_GET['payer_district_id'] ?? 0);
$filter_receiver_id = $_GET['receiver_id'] ?? '';
$filter_receiver_district_id = validateInt($_GET['receiver_district_id'] ?? 0);
$filter_status = validateStatus($_GET['status'] ?? '');

// ============================================================================
// DATABASE FUNCTIONS
// ============================================================================

/**
 * Get payers for filter dropdown
 * 
 * @param mysqli $db_conn Database connection
 * @param string $logged_user_type Logged user type
 * @param string $logged_user_id Logged user ID
 * @return array Array of payers
 */
function getPayersForFilter(mysqli $db_conn, string $logged_user_type, $logged_user_id): array {
    $payers = [];
    $query = "SELECT DISTINCT from_user_id, from_user_name, from_user_type 
              FROM advance_payments 
              WHERE deleted_at IS NULL";
    
    $params = [];
    $types = "";
    
    if ($logged_user_type !== 'company') {
        $query .= " AND (to_user_id = ? OR from_user_id = ?)";
        $params = [$logged_user_id, $logged_user_id];
        $types = "ss";
    }
    
    $query .= " ORDER BY from_user_name ASC";
    
    $stmt = $db_conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed in getPayersForFilter: " . $db_conn->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $payers[] = $row;
        }
    }
    $stmt->close();
    
    return $payers;
}

/**
 * Get receivers for filter dropdown
 * 
 * @param mysqli $db_conn Database connection
 * @param string $logged_user_type Logged user type
 * @param string $logged_user_id Logged user ID
 * @return array Array of receivers
 */
function getReceiversForFilter(mysqli $db_conn, string $logged_user_type, $logged_user_id): array {
    $receivers = [];
    $query = "SELECT DISTINCT to_user_id, to_user_name, to_user_type 
              FROM advance_payments 
              WHERE deleted_at IS NULL";
    
    $params = [];
    $types = "";
    
    if ($logged_user_type !== 'company') {
        $query .= " AND (to_user_id = ? OR from_user_id = ?)";
        $params = [$logged_user_id, $logged_user_id];
        $types = "ss";
    }
    
    $query .= " ORDER BY to_user_name ASC";
    
    $stmt = $db_conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed in getReceiversForFilter: " . $db_conn->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $receivers[] = $row;
        }
    }
    $stmt->close();
    
    return $receivers;
}

/**
 * Format user type for display
 * 
 * @param string $type User type
 * @return string Formatted user type
 */
function formatUserType(string $type): string {
    return ucwords(str_replace('_', ' ', $type));
}

// Get filter data
$payers_for_filter = getPayersForFilter($db_conn, $logged_user_type, $logged_user_id);
$receivers_for_filter = getReceiversForFilter($db_conn, $logged_user_type, $logged_user_id);

// Get business name safely
$business_name = $business_name ?? 'Femi9 Billing';

$selectusertypeGET="select * from admin_log where username='".$_SESSION['LOGIN_USER']."'";
$fetchusertypeGET=mysqli_query($db_conn,$selectusertypeGET);
$resultusertypeGET=mysqli_fetch_array($fetchusertypeGET);
$LoginusertypeGET=$resultusertypeGET['usertype'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consolidated Payment Entry | <?php echo htmlspecialchars($business_name, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    
    <!-- CSS -->
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png" />

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --primary-color: #667eea;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
        }
        
        .card { 
            border-radius: 12px; 
            box-shadow: 0 2px 12px rgba(0,0,0,0.08); 
            border: none; 
        }
        
        .filter-card { 
            background: var(--primary-gradient); 
            color: white; 
            margin-bottom: 24px; 
            border-radius: 12px; 
            padding: 24px; 
        }
        
        .filter-card .form-label { 
            color: white; 
            font-weight: 500; 
            margin-bottom: 8px; 
        }
        
        .filter-card .form-control, 
        .filter-card .form-select { 
            background: rgba(255,255,255,0.95); 
            border: none; 
            border-radius: 8px; 
            padding: 10px 14px; 
        }
        
        .stats-card { 
            background: white; 
            border-radius: 12px; 
            padding: 24px; 
            margin-bottom: 24px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); 
            border-left: 4px solid var(--primary-color); 
        }
        
        .stats-card h3 { 
            font-size: 32px; 
            font-weight: 700; 
            margin: 0; 
            color: var(--primary-color); 
        }
        
        .stats-card p { 
            margin: 8px 0 0 0; 
            color: #6b7280; 
            font-size: 14px; 
            font-weight: 500; 
        }
        
        .status-badge { 
            padding: 6px 14px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: 600; 
        }
        
        .status-active { background: #d1fae5; color: #065f46; }
        .status-partially { background: #fef3c7; color: #92400e; }
        .status-fully { background: #dbeafe; color: #1e40af; }
        
        .btn-filter { 
            background: white; 
            color: var(--primary-color); 
            border: 2px solid white; 
            font-weight: 600; 
            border-radius: 8px; 
            padding: 10px 20px; 
        }
        
        .btn-filter:hover {
            background: rgba(255,255,255,0.9);
            color: var(--primary-color);
        }
        
        .btn-reset { 
            background: rgba(255,255,255,0.15); 
            color: white; 
            border: 2px solid white; 
            font-weight: 600; 
            border-radius: 8px; 
            padding: 10px 20px; 
        }
        
        .btn-reset:hover {
            background: rgba(255,255,255,0.25);
            color: white;
        }
        
        table.dataTable thead th { 
            background: #f9fafb; 
            font-weight: 600; 
            color: #374151; 
            padding: 16px 12px; 
        }
        
        .amount-cell { 
            font-weight: 600; 
            color: var(--primary-color); 
            font-size: 15px; 
        }
        
        .balance-cell { 
            font-weight: 600; 
            color: var(--success-color); 
            font-size: 15px; 
        }
        
        .btn-action {
            padding: 6px 10px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-action i {
            font-size: 18px;
            vertical-align: middle;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .btn-delete {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <!-- Sidebar -->
        <div class="app-sidebar">
            <?php include("logo.php"); ?>
            <?php include("femi_menu.php"); ?>
        </div>
        
        <!-- Main Content -->
        <div class="app-container">
            <?php include("app-header.php"); ?>
            
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        
                        <!-- Page Header -->
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble" style="width:100%">
                                            <tr>
                                                <td>Consolidated Payment Entry</td>
                                                <?php if($resultusertypeGET['payment_entry']==1){?>
                                                <td style="text-align:right">
                                                    <a href="add-advance-payment.php" title="Add New Payment">
                                                        <i class="material-icons">add_circle</i>
                                                    </a>
                                                </td>
                                                <?php }?>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="row">
                            <div class="col-lg-3 col-md-6">
                                <div class="stats-card">
                                    <h3 id="stat_total_payments">0</h3>
                                    <p>Total Payers</p> <!-- Changed from Total Payments -->
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="stats-card">
                                    <h3 id="stat_total_amount">₹0</h3>
                                    <p>Total Amount</p>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="stats-card">
                                    <h3 id="stat_total_balance">₹0</h3>
                                    <p>Total Balance</p>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="stats-card">
                                    <h3 id="stat_adjusted_amount">₹0</h3>
                                    <p>Adjusted Amount</p>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Card -->
                        <div class="row">
                            <div class="col-12">
                                <div class="filter-card">
                                    <form method="GET" action="" id="filterForm">
                                        <div class="row g-3 align-items-end">
                                            
                                            <!-- Date From -->
                                            <div class="col-lg-2 col-md-4 col-sm-6">
                                                <label class="form-label" for="from_date">From Date</label>
                                                <input type="date" name="from_date" id="from_date" class="form-control" 
                                                       value="<?php echo htmlspecialchars($filter_from_date, ENT_QUOTES, 'UTF-8'); ?>"
                                                       max="<?php echo date('Y-m-d'); ?>">
                                            </div>

                                            <!-- Date To -->
                                            <div class="col-lg-2 col-md-4 col-sm-6">
                                                <label class="form-label" for="to_date">To Date</label>
                                                <input type="date" name="to_date" id="to_date" class="form-control" 
                                                       value="<?php echo htmlspecialchars($filter_to_date, ENT_QUOTES, 'UTF-8'); ?>" 
                                                       max="<?php echo date('Y-m-d'); ?>">
                                            </div>

                                            <!-- Payer Type -->
                                            <div class="col-lg-2 col-md-4 col-sm-6">
                                                <label class="form-label" for="payer_type">Payer Type</label>
                                                <select name="payer_type" id="payer_type" class="form-select">
                                                    <option value="">All Types</option>
                                                    <option value="super_stockiest" <?php echo $filter_payer_type === 'super_stockiest' ? 'selected' : ''; ?>>Super Stockist</option>
                                                    <option value="stockiest" <?php echo $filter_payer_type === 'stockiest' ? 'selected' : ''; ?>>Stockist</option>
                                                    <option value="distributor" <?php echo $filter_payer_type === 'distributor' ? 'selected' : ''; ?>>Distributor</option>
                                                    <option value="super_distributor" <?php echo $filter_payer_type === 'super_distributor' ? 'selected' : ''; ?>>Super Distributor</option>
                                                    <option value="c_and_f" <?php echo $filter_payer_type === 'c_and_f' ? 'selected' : ''; ?>>C&F Agent</option>
                                                </select>
                                            </div>

                                            <!-- Payer District -->
                                            <div class="col-lg-2 col-md-4 col-sm-6">
                                                <label class="form-label" for="payer_district_id">Payer District</label>
                                                <select name="payer_district_id" id="payer_district_id" class="form-select">
                                                    <option value="">All Districts</option>
                                                    <!-- Populated dynamically via AJAX -->
                                                </select>
                                            </div>

                                            <!-- Payer Name -->
                                            <div class="col-lg-2 col-md-4 col-sm-6">
                                                <label class="form-label" for="payer_id">Payer Name</label>
                                                <select name="payer_id" id="payer_id" class="form-select">
                                                    <option value="">All Payers</option>
                                                    <!-- Populated dynamically via AJAX -->
                                                </select>
                                            </div>

                                            <!-- Receiver Type -->
                                            <div class="col-lg-2 col-md-4 col-sm-6">
                                                <label class="form-label" for="receiver_type">Receiver Type</label>
                                                <select name="receiver_type" id="receiver_type" class="form-select">
                                                    <option value="">All Types</option>
                                                    <option value="company" <?php echo $filter_receiver_type === 'company' ? 'selected' : ''; ?>>Company</option>
                                                    <option value="c_and_f" <?php echo $filter_receiver_type === 'c_and_f' ? 'selected' : ''; ?>>C&F Agent</option>
                                                    <option value="super_stockiest" <?php echo $filter_receiver_type === 'super_stockiest' ? 'selected' : ''; ?>>Super Stockist</option>
                                                    <option value="stockiest" <?php echo $filter_receiver_type === 'stockiest' ? 'selected' : ''; ?>>Stockist</option>
                                                    <option value="distributor" <?php echo $filter_receiver_type === 'distributor' ? 'selected' : ''; ?>>Distributor</option>
                                                    <option value="super_distributor" <?php echo $filter_receiver_type === 'super_distributor' ? 'selected' : ''; ?>>Super Distributor</option>
                                                </select>
                                            </div>

                                            <!-- Receiver District -->
                                            <div class="col-lg-2 col-md-4 col-sm-6">
                                                <label class="form-label" for="receiver_district_id">Receiver District</label>
                                                <select name="receiver_district_id" id="receiver_district_id" class="form-select">
                                                    <option value="">All Districts</option>
                                                    <!-- Populated dynamically via AJAX -->
                                                </select>
                                            </div>

                                            <!-- Receiver Name -->
                                            <div class="col-lg-2 col-md-4 col-sm-6">
                                                <label class="form-label" for="receiver_id">Receiver Name</label>
                                                <select name="receiver_id" id="receiver_id" class="form-select">
                                                    <option value="">All Receivers</option>
                                                    <!-- Populated dynamically via AJAX -->
                                                </select>
                                            </div>

                                            <!-- Status -->
                                            <div class="col-lg-2 col-md-4 col-sm-6">
                                                <label class="form-label" for="status">Status</label>
                                                <select name="status" id="status" class="form-select">
                                                    <option value="">All Status</option>
                                                    <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="partially_adjusted" <?php echo $filter_status === 'partially_adjusted' ? 'selected' : ''; ?>>Partially Adjusted</option>
                                                    <option value="fully_adjusted" <?php echo $filter_status === 'fully_adjusted' ? 'selected' : ''; ?>>Fully Adjusted</option>
                                                </select>
                                            </div>

                                            <!-- Buttons -->
                                            <div class="col-12">
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <button type="submit" class="btn btn-filter">
                                                        <i class="material-icons" style="vertical-align:middle;font-size:18px">filter_list</i>
                                                        Apply Filters
                                                    </button>
                                                    <a href="consolidated-manage-advance-payments" class="btn btn-reset">
                                                        <i class="material-icons" style="vertical-align:middle;font-size:18px">refresh</i>
                                                        Reset
                                                    </a>
                                                </div>
                                            </div>

                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Data Table -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table id="advancePaymentsTable" class="table table-hover" style="width:100%">
                                                <thead>
                                                    <tr>
                                                        <th>Payer Name</th>
                                                        <th>Payer Type</th>
                                                        <th>District</th>
                                                        <th>Target Amount</th>
                                                        <th># Payments</th>
                                                        <th>Last Payment</th>
                                                        <th>Receivers</th>
                                                        <th>Total Amount</th>
                                                        <th>Total Balance</th>
                                                        <th>Total Adjusted</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalLabel">Advance Payment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalContent">
                    <div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Payment Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Advance Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editPaymentForm" method="POST" action="edit-advance-payment-action.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="modal-body" id="editModalContent">
                        <div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_advance_payment">
                            <i class="material-icons" style="vertical-align:middle">save</i> Update Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>

    <script>
    $(document).ready(function() {
        'use strict';
        
        // ====================================================================
        // UTILITY FUNCTIONS
        // ====================================================================
        
        /**
         * Escape HTML to prevent XSS
         */
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
        
        /**
         * Format number for Indian currency
         */
        function formatNumber(num) {
            if (isNaN(num) || num === null) return '0.00';
            return parseFloat(num).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        /**
         * Format user type for display
         */
        function formatUserType(type) {
            if (!type) return '';
            return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }
        
        // ====================================================================
        // FILTER PARAMETERS
        // ====================================================================
        
        const urlParams = new URLSearchParams(window.location.search);
        
        let receiverTypeValue;
        if (urlParams.has('receiver_type')) {
            receiverTypeValue = urlParams.get('receiver_type') || '';
        } else {
            receiverTypeValue = 'company';
        }
        
        const filters = {
            from_date: urlParams.get('from_date') || '<?php echo $filter_from_date; ?>',
            to_date: urlParams.get('to_date') || '<?php echo $filter_to_date; ?>',
            payer_type: urlParams.get('payer_type') || '',
            payer_district_id: parseInt(urlParams.get('payer_district_id')) || 0,
            payer_id: urlParams.get('payer_id') || '',
            receiver_type: receiverTypeValue,
            receiver_district_id: parseInt(urlParams.get('receiver_district_id')) || 0,
            receiver_id: urlParams.get('receiver_id') || '',
            status: urlParams.get('status') || ''
        };

        // ====================================================================
        // DATATABLE INITIALIZATION
        // ====================================================================
        
        const table = $('#advancePaymentsTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: 'get-consolidated-advance-payments-data.php',
                type: 'POST',
                data: filters,
                dataSrc: function(json) {
                    if (json.error) { 
                        alert('Error: ' + json.error); 
                        return []; 
                    }
                    
                    // Update statistics
                    if (json.stats) {
                        $('#stat_total_payments').text(json.stats.total_payers || 0);
                        $('#stat_total_amount').text('₹' + formatNumber(json.stats.total_amount || 0));
                        $('#stat_total_balance').text('₹' + formatNumber(json.stats.total_balance || 0));
                        $('#stat_adjusted_amount').text('₹' + formatNumber(json.stats.adjusted_amount || 0));
                    }
                    
                    return json.data || [];
                },
                error: function(xhr, error, thrown) {
                    console.error('DataTable Error:', error, thrown);
                    alert('Failed to load payment data. Please refresh the page.');
                }
            },
            columns: [
                { data: 'from_user_name', render: escapeHtml },
                { data: 'from_user_type', render: d => escapeHtml(formatUserType(d)) },
                { 
                    data: 'payer_district_name',
                    render: d => escapeHtml(d || 'N/A')
                },
                { 
                    data: 'payer_target_amount', 
                    className: 'text-end',
                    render: function(d) {
                        if (!d || d == 0) return '<span class="text-muted">N/A</span>';
                        return '₹' + formatNumber(d);
                    }
                },
                { 
                    data: 'payment_count', 
                    className: 'text-center',
                    render: d => '<span class="badge bg-info">' + d + '</span>'
                },
                { 
                    data: 'last_payment_date', 
                    render: d => new Date(d).toLocaleDateString('en-IN') 
                },
                { 
                    data: 'receiver_names', 
                    render: d => escapeHtml(d || 'N/A')
                },
                { 
                    data: 'total_amount', 
                    className: 'amount-cell text-end', 
                    render: d => '₹' + formatNumber(d) 
                },
                { 
                    data: 'total_balance', 
                    className: 'balance-cell text-end', 
                    render: d => '₹' + formatNumber(d) 
                },
                { 
                    data: 'total_adjusted', 
                    className: 'text-end', 
                    render: d => '₹' + formatNumber(d) 
                },
                { 
                    data: 'overall_status',
                    render: function(d) {
                        let cls = 'status-active', txt = 'Active';
                        if (d === 'partially_adjusted') { 
                            cls = 'status-partially'; 
                            txt = 'Partially Adjusted'; 
                        } else if (d === 'fully_adjusted') { 
                            cls = 'status-fully'; 
                            txt = 'Fully Adjusted'; 
                        }
                        return '<span class="status-badge ' + cls + '">' + escapeHtml(txt) + '</span>';
                    }
                }
            ],
            order: [[5, 'desc']], 
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: '<"row"<"col-sm-6"l><"col-sm-6"f>><"row"<"col-sm-12"B>><"row"<"col-sm-12"tr>><"row"<"col-sm-5"i><"col-sm-7"p>>',
            buttons: [
                {
                    extend: 'excel', 
                    text: '<i class="material-icons" style="vertical-align:middle">download</i> Excel', 
                    className: 'btn btn-success'
                },
                {
                    extend: 'print', 
                    text: '<i class="material-icons" style="vertical-align:middle">print</i> Print', 
                    className: 'btn btn-info'
                }
            ],
            language: {
                emptyTable: "No payment entries found",
                loadingRecords: "Loading payment data...",
                processing: "Processing...",
                zeroRecords: "No matching payment entries found"
            }
        });

        // ====================================================================
        // DYNAMIC FILTER LOADING
        // ====================================================================
        
        /**
         * Load payer districts
         */
        function loadPayerDistricts() {
            $.get('get-payers-districts.php?action=get_payer_districts')
                .done(function(response) {
                    if (response.success && response.data) {
                        const districtSelect = $('#payer_district_id');
                        districtSelect.html('<option value="">All Districts</option>');
                        
                        response.data.forEach(function(district) {
                            const selected = '<?php echo $filter_payer_district_id; ?>' == district.id ? 'selected' : '';
                            districtSelect.append(
                                '<option value="' + escapeHtml(district.id) + '" ' + selected + '>' + 
                                escapeHtml(district.name) + '</option>'
                            );
                        });
                        
                        if ('<?php echo $filter_payer_district_id; ?>') {
                            loadPayerNames();
                        }
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Failed to load payer districts:', error);
                });
        }
        
        /**
         * Load payer names based on type and district
         */
        function loadPayerNames() {
            const payerType = $('#payer_type').val();
            const districtId = $('#payer_district_id').val();
            const payerSelect = $('#payer_id');
            const selectedPayerId = urlParams.get('payer_id') || '';
            
            payerSelect.html('<option value="">Loading...</option>');
            
            let url = 'get-payers-districts.php?action=get_payers';
            if (payerType) url += '&payer_type=' + encodeURIComponent(payerType);
            if (districtId) {
                url = 'get-payers-districts.php?action=get_payers_by_district&district_id=' + districtId;
                if (payerType) url += '&payer_type=' + encodeURIComponent(payerType);
            }
            
            $.get(url)
                .done(function(response) {
                    payerSelect.html('<option value="">All Payers</option>');
                    
                    if (response.success && response.data && response.data.length > 0) {
                        response.data.forEach(function(payer) {
                            const selected = selectedPayerId == payer.id ? 'selected' : '';
                            payerSelect.append(
                                '<option value="' + escapeHtml(payer.id) + '" ' + selected + '>' + 
                                escapeHtml(payer.name) + '</option>'
                            );
                        });
                    } else {
                        payerSelect.html('<option value="">No payers found</option>');
                    }
                })
                .fail(function() {
                    payerSelect.html('<option value="">Error loading payers</option>');
                });
        }
        
        /**
         * Load receiver districts
         */
        function loadReceiverDistricts() {
            $.get('get-receivers-districts.php?action=get_receiver_districts')
                .done(function(response) {
                    if (response.success && response.data) {
                        const districtSelect = $('#receiver_district_id');
                        districtSelect.html('<option value="">All Districts</option>');
                        
                        response.data.forEach(function(district) {
                            const selected = '<?php echo $filter_receiver_district_id; ?>' == district.id ? 'selected' : '';
                            districtSelect.append(
                                '<option value="' + escapeHtml(district.id) + '" ' + selected + '>' + 
                                escapeHtml(district.name) + '</option>'
                            );
                        });
                        
                        if ('<?php echo $filter_receiver_district_id; ?>') {
                            loadReceiverNames();
                        }
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Failed to load receiver districts:', error);
                });
        }
        
        /**
         * Load receiver names based on type and district
         */
        function loadReceiverNames() {
            const receiverType = $('#receiver_type').val();
            const districtId = $('#receiver_district_id').val();
            const receiverSelect = $('#receiver_id');
            const selectedReceiverId = urlParams.get('receiver_id') || '';
            
            receiverSelect.html('<option value="">Loading...</option>');
            
            // Special handling for company type
            if (receiverType === 'company') {
                let url = 'get-receivers-districts.php?action=get_receivers&receiver_type=company';
                
                $.get(url)
                    .done(function(response) {
                        receiverSelect.html('<option value="">All Receivers</option>');
                        
                        if (response.success && response.data && response.data.length > 0) {
                            response.data.forEach(function(receiver) {
                                const selected = selectedReceiverId == receiver.id ? 'selected' : '';
                                receiverSelect.append(
                                    '<option value="' + escapeHtml(receiver.id) + '" ' + selected + '>' + 
                                    escapeHtml(receiver.name) + '</option>'
                                );
                            });
                        } else {
                            receiverSelect.html('<option value="">No company receivers found</option>');
                        }
                    })
                    .fail(function() {
                        receiverSelect.html('<option value="">Error loading receivers</option>');
                    });
                
                return;
            }
            
            let url = 'get-receivers-districts.php?action=get_receivers';
            if (receiverType) url += '&receiver_type=' + encodeURIComponent(receiverType);
            if (districtId) {
                url = 'get-receivers-districts.php?action=get_receivers_by_district&district_id=' + districtId;
                if (receiverType) url += '&receiver_type=' + encodeURIComponent(receiverType);
            }
            
            $.get(url)
                .done(function(response) {
                    receiverSelect.html('<option value="">All Receivers</option>');
                    
                    if (response.success && response.data && response.data.length > 0) {
                        response.data.forEach(function(receiver) {
                            const selected = selectedReceiverId == receiver.id ? 'selected' : '';
                            receiverSelect.append(
                                '<option value="' + escapeHtml(receiver.id) + '" ' + selected + '>' + 
                                escapeHtml(receiver.name) + '</option>'
                            );
                        });
                    } else {
                        receiverSelect.html('<option value="">No receivers found</option>');
                    }
                })
                .fail(function() {
                    receiverSelect.html('<option value="">Error loading receivers</option>');
                });
        }
        
        // Initialize filters on page load
        loadPayerDistricts();
        loadPayerNames();
        loadReceiverDistricts();
        loadReceiverNames();
        
        // Check if company is selected and disable receiver district filter
        if ($('#receiver_type').val() === 'company') {
            $('#receiver_district_id').val('').prop('disabled', true);
        }
        
        // ====================================================================
        // FILTER EVENT HANDLERS
        // ====================================================================
        
        $('#payer_type').on('change', function() {
            $('#payer_id').val('');
            loadPayerNames();
        });
        
        $('#payer_district_id').on('change', function() {
            loadPayerNames();
        });
        
        $('#receiver_type').on('change', function() {
            const receiverType = $(this).val();
            
            if (receiverType === 'company') {
                $('#receiver_district_id').val('').prop('disabled', true);
            } else {
                $('#receiver_district_id').prop('disabled', false);
            }
            
            $('#receiver_id').val('');
            loadReceiverNames();
        });
        
        $('#receiver_district_id').on('change', function() {
            loadReceiverNames();
        });

        // ====================================================================
        // MODAL HANDLERS
        // ====================================================================
        
        /**
         * View payment details
         */
        $('#advancePaymentsTable').on('click', '.view-btn', function() {
            const id = $(this).data('id');
            $('#modalContent').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
            $('#viewModal').modal('show');
            
            $.post('get-advance-payment-details.php', {
                id: id, 
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            })
            .done(function(response) {
                $('#modalContent').html(response);
            })
            .fail(function() {
                $('#modalContent').html('<div class="alert alert-danger">Error loading payment details. Please try again.</div>');
            });
        });

        /**
         * Edit payment
         */
        $('#advancePaymentsTable').on('click', '.edit-btn', function() {
            const id = $(this).data('id');
            $('#editModalContent').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
            $('#editModal').modal('show');
            
            $.post('get-advance-payment-edit-form.php', {
                id: id, 
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            })
            .done(function(response) {
                $('#editModalContent').html(response);
            })
            .fail(function() {
                $('#editModalContent').html('<div class="alert alert-danger">Error loading edit form. Please try again.</div>');
            });
        });

        /**
         * Submit edit form
         */
        $('#editPaymentForm').on('submit', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to update this payment entry?')) {
                return;
            }
            
            const btn = $(this).find('[type="submit"]');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Updating...');
            
            $.post('edit-advance-payment-action.php', $(this).serialize())
                .done(function(response) {
                    btn.prop('disabled', false).html('<i class="material-icons" style="vertical-align:middle">save</i> Update Payment');
                    
                    if (response.success) { 
                        $('#editModal').modal('hide'); 
                        alert(response.message); 
                        table.ajax.reload(null, false); 
                    } else {
                        alert('Error: ' + (response.message || 'Failed to update payment'));
                    }
                })
                .fail(function(xhr, status, error) {
                    btn.prop('disabled', false).html('<i class="material-icons" style="vertical-align:middle">save</i> Update Payment');
                    alert('Error updating payment. Please try again.');
                    console.error('Edit Error:', error);
                });
        });

        /**
         * Delete payment (soft delete)
         */
        $('#advancePaymentsTable').on('click', '.delete-btn', function() {
            const id = $(this).data('id');
            const btn = $(this);
            
            if (!confirm('Are you sure you want to delete this payment entry? This action can be undone later.')) {
                return;
            }
            
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
            
            $.post('delete-advance-payment-action.php', {
                id: id,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            })
            .done(function(response) {
                if (response.success) {
                    alert(response.message || 'Payment deleted successfully');
                    table.ajax.reload(null, false);
                } else {
                    alert('Error: ' + (response.message || 'Failed to delete payment'));
                    btn.prop('disabled', false).html('<i class="material-icons">delete</i>');
                }
            })
            .fail(function(xhr, status, error) {
                alert('Error deleting payment. Please try again.');
                console.error('Delete Error:', error);
                btn.prop('disabled', false).html('<i class="material-icons">delete</i>');
            });
        });

    });
    </script>

</body>
</html>
<?php
if (isset($db_conn) && $db_conn) {
    $db_conn->close();
}
?>