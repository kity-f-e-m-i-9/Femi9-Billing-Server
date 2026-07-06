<?php
/**
 * Manage Bonus Points - Modern UI with Advanced Filtering
 * Femi9 Billing Application
 * 
 * Description: View and manage bonus points history with comprehensive filtering
 * Features: Date filter, user type/name/district, execution filter, eligibility status
 * Consolidates rows when "All Months" is selected — one row per user instead of per month.
 * 
 * @author Femi9 Development Team
 * @version 2.0
 * @date 2026-03-06
 */

declare(strict_types=1);

// Security headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

require_once("checksession.php"); 
require_once("config.php"); 

date_default_timezone_set("Asia/Kolkata");

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/bonus-points-errors.log');

if ($db_conn) {
    mysqli_set_charset($db_conn, 'utf8mb4');
}

// ============================================================================
// SESSION & SECURITY
// ============================================================================

$logged_user_id   = $_SESSION['LOGIN_USER_ID']   ?? '';
$logged_user_type = $_SESSION['LOGIN_USER_TYPE'] ?? '';
$logged_user_name = $_SESSION['LOGIN_USER']      ?? '';

if (empty($logged_user_id) || empty($logged_user_type)) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================================
// VALIDATION FUNCTIONS
// ============================================================================

function validateMonthYear(?string $monthYear): ?string {
    if (empty($monthYear)) return null;
    if (!preg_match('/^\d{4}-\d{2}$/', $monthYear)) return null;
    $parts = explode('-', $monthYear);
    $year  = (int)$parts[0];
    $month = (int)$parts[1];
    if ($month < 1 || $month > 12) return null;
    if ($year < 2020 || $year > (int)date('Y')) return null;
    return $monthYear;
}

function validateUserType(?string $type): string {
    $allowedTypes = ['super_stockiest', 'stockiest', ''];
    return in_array($type ?? '', $allowedTypes, true) ? ($type ?? '') : '';
}

function validateEligibility(?string $status): string {
    $allowedStatuses = ['eligible', 'not_eligible', ''];
    return in_array($status ?? '', $allowedStatuses, true) ? ($status ?? '') : '';
}

function validateInt($value): int {
    $filtered = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['default' => 0, 'min_range' => 0]
    ]);
    return $filtered !== false ? $filtered : 0;
}

// ============================================================================
// FILTER PARAMETERS
// Read from GET for initial page render only — DataTable uses live form values.
// No longer default to last month; default is empty = All Months.
// ============================================================================

$filter_month_year   = validateMonthYear($_GET['month_year'] ?? null); // null = All Months
$filter_user_type    = validateUserType($_GET['user_type'] ?? '');
$filter_user_id      = $_GET['user_id'] ?? '';
$filter_district_id  = validateInt($_GET['district_id'] ?? 0);
$filter_eligibility  = validateEligibility($_GET['eligibility'] ?? '');
$filter_execution_id = $_GET['execution_id'] ?? '';

// ============================================================================
// DATABASE FUNCTIONS
// ============================================================================

function getAvailableMonths(mysqli $db_conn): array {
    $months = [];
    $query  = "SELECT DISTINCT month_year 
               FROM bonus_points_history 
               WHERE rolled_back_at IS NULL
               ORDER BY month_year DESC 
               LIMIT 24";
    $result = $db_conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $months[] = $row['month_year'];
        }
    }
    return $months;
}

function getAvailableExecutionsForFilter(mysqli $db_conn): array {
    $executions = [];
    $query = "SELECT DISTINCT 
                execution_id,
                month_year,
                total_bonus_points_awarded,
                executed_at
              FROM bonus_execution_log 
              WHERE is_rolled_back = 0
              ORDER BY executed_at DESC
              LIMIT 50";
    $result = $db_conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $executions[] = $row;
        }
    }
    return $executions;
}

function getAllDistricts(mysqli $db_conn): array {
    $districts = [];
    $query     = "SELECT id, dist_name as name FROM district ORDER BY dist_name ASC";
    $result    = $db_conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $districts[] = $row;
        }
    }
    return $districts;
}

function formatUserType(string $type): string {
    return ucwords(str_replace('_', ' ', $type));
}

$available_months     = getAvailableMonths($db_conn);
$available_executions = getAvailableExecutionsForFilter($db_conn);
$all_districts        = getAllDistricts($db_conn);
$business_name        = $business_name ?? 'Femi9 Billing';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Bonus Points | <?php echo htmlspecialchars($business_name, ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png" />

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --primary-color: #667eea;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --mixed-color: #8b5cf6;
        }

        body {
            background: linear-gradient(180deg, #f3f4f6 0%, #ffffff 100%);
            font-family: 'Poppins', sans-serif;
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

        /* Consolidated mode notice banner */
        #consolidatedNotice {
            display: none;
            background: linear-gradient(135deg, #f59e0b22, #f59e0b11);
            border: 1px solid #f59e0b55;
            border-radius: 10px;
            padding: 10px 18px;
            margin-bottom: 16px;
            color: #92400e;
            font-size: 13px;
            font-weight: 500;
        }
        #consolidatedNotice i {
            vertical-align: middle;
            font-size: 18px;
            margin-right: 6px;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .stats-card.primary { border-left: 4px solid var(--primary-color); }
        .stats-card.success { border-left: 4px solid var(--success-color); }
        .stats-card.danger  { border-left: 4px solid var(--danger-color); }
        .stats-card.warning { border-left: 4px solid var(--warning-color); }

        .stats-card h3 { font-size: 32px; font-weight: 700; margin: 0; }
        .stats-card.primary h3 { color: var(--primary-color); }
        .stats-card.success h3 { color: var(--success-color); }
        .stats-card.danger h3  { color: var(--danger-color); }
        .stats-card.warning h3 { color: var(--warning-color); }

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

        .status-eligible     { background: #d1fae5; color: #065f46; }
        .status-not-eligible { background: #fee2e2; color: #991b1b; }
        .status-mixed        { background: #ede9fe; color: #5b21b6; }
        .week-pass           { background: #d1fae5; color: #065f46; }
        .week-fail           { background: #fee2e2; color: #991b1b; }

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
            border-bottom: 2px solid #e5e7eb;
        }

        .amount-cell { font-weight: 600; color: var(--primary-color); font-size: 15px; }
        .bonus-cell  { font-weight: 700; color: var(--warning-color); font-size: 16px; }

        .btn-action {
            padding: 6px 10px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-action i { font-size: 18px; vertical-align: middle; }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .page-description h1 {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        /* Per-month view: square emoji indicators */
        .week-indicator {
            display: inline-block;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            text-align: center;
            line-height: 28px;
            font-size: 14px;
            margin: 0 2px;
        }

        /* Consolidated view: "W1 3/5" pill */
        .week-count-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f3f4f6;
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 12px;
            font-weight: 600;
            margin: 2px;
        }

        .week-count-pill.all-pass  { background: #d1fae5; color: #065f46; }
        .week-count-pill.none-pass { background: #fee2e2; color: #991b1b; }
        .week-count-pill.some-pass { background: #fef3c7; color: #92400e; }

        /* Month cell chips for consolidated view */
        .month-chip {
            display: inline-block;
            background: #ede9fe;
            color: #5b21b6;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
            margin: 1px;
            white-space: nowrap;
        }
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

                        <!-- Page Header -->
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble" style="width:100%">
                                            <tr>
                                                <td>🎯 Manage Bonus Points</td>
                                                <td style="text-align:right">
                                                    <a href="bonus-points-calculator.php" title="Calculate New Bonus">
                                                        <i class="material-icons">calculate</i>
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="row">
                            <div class="col-lg-3 col-md-6">
                                <div class="stats-card primary">
                                    <h3 id="stat_total_records">0</h3>
                                    <p id="stat_total_label">Total Records</p>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="stats-card success">
                                    <h3 id="stat_eligible_users">0</h3>
                                    <p>Eligible Users</p>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="stats-card danger">
                                    <h3 id="stat_ineligible_users">0</h3>
                                    <p>Not Eligible / Mixed</p>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="stats-card warning">
                                    <h3 id="stat_total_bonus">₹0</h3>
                                    <p>Total Bonus Points</p>
                                </div>
                            </div>
                        </div>

                        <!-- Consolidated mode notice -->
                        <div id="consolidatedNotice">
                            <i class="material-icons">info</i>
                            <strong>Consolidated View:</strong> Showing all months combined — one row per user with summed targets, payments, and bonus points.
                        </div>

                        <!-- Filter Card -->
                        <div class="row">
                            <div class="col-12">
                                <div class="filter-card">
                                    <form id="filterForm" onsubmit="return false;">
                                        <div class="row g-3 align-items-end">

                                            <!-- Month Year -->
                                            <div class="col-lg-2 col-md-4 col-sm-6">
                                                <label class="form-label" for="month_year">Month &amp; Year</label>
                                                <select name="month_year" id="month_year" class="form-select">
                                                    <option value="">All Months (Consolidated)</option>
                                                    <?php foreach ($available_months as $month): ?>
                                                        <option value="<?php echo htmlspecialchars($month, ENT_QUOTES, 'UTF-8'); ?>"
                                                                <?php echo $filter_month_year === $month ? 'selected' : ''; ?>>
                                                            <?php echo date('F Y', strtotime($month . '-01')); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <!-- User Type -->
                                            <div class="col-lg-2 col-md-4 col-sm-6">
                                                <label class="form-label" for="user_type">User Type</label>
                                                <select name="user_type" id="user_type" class="form-select">
                                                    <option value="">All Types</option>
                                                    <option value="super_stockiest" <?php echo $filter_user_type === 'super_stockiest' ? 'selected' : ''; ?>>Super Stockist</option>
                                                    <option value="stockiest"       <?php echo $filter_user_type === 'stockiest'       ? 'selected' : ''; ?>>Stockist</option>
                                                </select>
                                            </div>

                                            <!-- District -->
                                            <div class="col-lg-2 col-md-4 col-sm-6">
                                                <label class="form-label" for="district_id">District</label>
                                                <select name="district_id" id="district_id" class="form-select">
                                                    <option value="">All Districts</option>
                                                    <?php foreach ($all_districts as $district): ?>
                                                        <option value="<?php echo htmlspecialchars($district['id'],   ENT_QUOTES, 'UTF-8'); ?>"
                                                                <?php echo $filter_district_id == $district['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($district['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <!-- User Name -->
                                            <div class="col-lg-2 col-md-4 col-sm-6">
                                                <label class="form-label" for="user_id">User Name</label>
                                                <select name="user_id" id="user_id" class="form-select">
                                                    <option value="">All Users</option>
                                                    <!-- Populated via AJAX -->
                                                </select>
                                            </div>

                                            <!-- Eligibility Status -->
                                            <div class="col-lg-2 col-md-4 col-sm-6">
                                                <label class="form-label" for="eligibility">Eligibility</label>
                                                <select name="eligibility" id="eligibility" class="form-select">
                                                    <option value="">All Status</option>
                                                    <option value="eligible"     <?php echo $filter_eligibility === 'eligible'     ? 'selected' : ''; ?>>Eligible</option>
                                                    <option value="not_eligible" <?php echo $filter_eligibility === 'not_eligible' ? 'selected' : ''; ?>>Not Eligible</option>
                                                </select>
                                            </div>

                                            <!-- Execution ID -->
                                            <div class="col-lg-4 col-md-6 col-sm-6">
                                                <label class="form-label" for="execution_id">Execution</label>
                                                <select name="execution_id" id="execution_id" class="form-select">
                                                    <option value="">All Executions</option>
                                                    <?php foreach ($available_executions as $exec): ?>
                                                        <option value="<?php echo htmlspecialchars($exec['execution_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                <?php echo $filter_execution_id === $exec['execution_id'] ? 'selected' : ''; ?>>
                                                            <?php
                                                            echo htmlspecialchars(
                                                                $exec['month_year'] . ' | ' .
                                                                inr_format((float)$exec['total_bonus_points_awarded'], 2) . ' pts | ' .
                                                                date('d M Y', strtotime($exec['executed_at'])),
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            );
                                                            ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <!-- Buttons -->
                                            <div class="col-lg-8 col-md-6">
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <button type="submit" class="btn btn-filter">
                                                        <i class="material-icons" style="vertical-align:middle;font-size:18px">filter_list</i>
                                                        Apply Filters
                                                    </button>
                                                    <button type="button" id="btnReset" class="btn btn-reset">
                                                        <i class="material-icons" style="vertical-align:middle;font-size:18px">refresh</i>
                                                        Reset
                                                    </button>
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
                                            <table id="bonusPointsTable" class="table table-hover" style="width:100%">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Month</th>
                                                        <th>User Name</th>
                                                        <th>User Type</th>
                                                        <th>Category</th>
                                                        <th>Target</th>
                                                        <th>Paid</th>
                                                        <th>Week Status</th>
                                                        <th>Eligibility</th>
                                                        <th>Bonus Points</th>
                                                        <th>Executed By</th>
                                                        <th>Executed At</th>
                                                        <th>Actions</th>
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
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                    <h5 class="modal-title" id="viewModalLabel">Bonus Points Detailed Breakdown</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
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
    $(document).ready(function () {
        'use strict';

        // ====================================================================
        // UTILITIES
        // ====================================================================

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function formatNumber(num) {
            if (num === null || num === undefined || isNaN(num)) return '0.00';
            return parseFloat(num).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatUserType(type) {
            if (!type) return '';
            return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        /**
         * Convert "2025-01" → "Jan 25"
         */
        function shortMonth(ym) {
            if (!ym) return '';
            const d = new Date(ym + '-01');
            return d.toLocaleDateString('en-IN', { month: 'short', year: '2-digit' });
        }

        // ====================================================================
        // FILTER STATE
        // Read live form values every time the DataTable calls data().
        // This is the key fix — filters were previously captured once at page
        // load as a static object and never updated when dropdowns changed.
        // ====================================================================

        function getFilters() {
            return {
                month_year:   $('#month_year').val()   || '',
                user_type:    $('#user_type').val()    || '',
                district_id:  parseInt($('#district_id').val())  || 0,
                user_id:      $('#user_id').val()      || '',
                eligibility:  $('#eligibility').val()  || '',
                execution_id: $('#execution_id').val() || ''
                // NOTE: "consolidate" is determined server-side from empty month_year
            };
        }

        function isConsolidatedMode() {
            return $('#month_year').val() === '';
        }

        // ====================================================================
        // DATATABLE
        // ====================================================================

        const table = $('#bonusPointsTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: 'get-bonus-points-data.php',
                type: 'POST',
                // data() is called fresh on every reload — reads live form values
                data: function (d) {
                    return $.extend({}, d, getFilters());
                },
                dataSrc: function (json) {
                    if (json.error) {
                        alert('Error: ' + json.error);
                        return [];
                    }

                    // Update stat cards
                    if (json.stats) {
                        $('#stat_total_records').text(json.stats.total_records  || 0);
                        $('#stat_eligible_users').text(json.stats.eligible_users || 0);
                        $('#stat_ineligible_users').text(json.stats.ineligible_users || 0);
                        $('#stat_total_bonus').text('₹' + formatNumber(json.stats.total_bonus_points || 0));
                        $('#stat_total_label').text(
                            json.stats.consolidate ? 'Total Users (All Months)' : 'Total Records'
                        );
                    }

                    // Show/hide consolidated notice banner
                    if (json.stats && json.stats.consolidate) {
                        $('#consolidatedNotice').show();
                    } else {
                        $('#consolidatedNotice').hide();
                    }

                    return json.data || [];
                },
                error: function (xhr, error, thrown) {
                    console.error('DataTable AJAX Error:', error, thrown);
                    alert('Failed to load bonus points data. Please refresh the page.');
                }
            },

            columns: [
                // 0 — ID
                {
                    data: 'id',
                    width: '50px'
                },

                // 1 — Month
                // Per-month: single formatted month
                // Consolidated: chips for each month covered
                {
                    data: 'month_year',
                    width: '130px',
                    render: function (d, t, row) {
                        if (row.all_months && row.months_count > 1) {
                            // Consolidated — show chips
                            const months = row.all_months.split(',');
                            return months.map(m => '<span class="month-chip">' + escapeHtml(shortMonth(m)) + '</span>').join('');
                        }
                        // Single month
                        if (!d) return 'N/A';
                        return shortMonth(d);
                    }
                },

                // 2 — User Name
                { data: 'user_name', render: d => escapeHtml(d) },

                // 3 — User Type
                { data: 'user_type', render: d => escapeHtml(formatUserType(d)) },

                // 4 — Category
                { data: 'category_name', render: d => escapeHtml(d) },

                // 5 — Target (summed in consolidated mode)
                {
                    data: 'monthly_target',
                    className: 'text-end',
                    render: function (d, t, row) {
                        const label = row.months_count > 1
                            ? '<br><small class="text-muted">(' + row.months_count + ' months)</small>'
                            : '';
                        return '₹' + formatNumber(d) + label;
                    }
                },

                // 6 — Paid (summed in consolidated mode)
                {
                    data: 'total_advance_paid',
                    className: 'amount-cell text-end',
                    render: d => '₹' + formatNumber(d)
                },

                // 7 — Week Status
                // Per-month: ✅/❌ per week
                // Consolidated: "W1 3/5" pass-count pills
                {
                    data: null,
                    orderable: false,
                    render: function (d, t, row) {
                        const n = parseInt(row.months_count) || 1;

                        if (n > 1) {
                            // Consolidated — show pass counts
                            return [1, 2, 3, 4].map(function (w) {
                                const passes = parseInt(row['week' + w + '_pass_count']) || 0;
                                let cls = 'some-pass';
                                if (passes === n) cls = 'all-pass';
                                else if (passes === 0) cls = 'none-pass';
                                return '<span class="week-count-pill ' + cls + '" title="Week ' + w + ': ' + passes + ' of ' + n + ' months passed">' +
                                       'W' + w + ' ' + passes + '/' + n +
                                       '</span>';
                            }).join('');
                        }

                        // Per-month — emoji indicators
                        return [1, 2, 3, 4].map(function (w) {
                            const status = row['week' + w + '_status'];
                            const pass   = status === 'pass';
                            return '<span class="week-indicator ' + (pass ? 'week-pass' : 'week-fail') + '" title="Week ' + w + '">' +
                                   (pass ? '✅' : '❌') +
                                   '</span>';
                        }).join('');
                    }
                },

                // 8 — Eligibility
                // Handles: eligible | not_eligible | mixed (consolidated only)
                {
                    data: 'eligibility_status',
                    render: function (d) {
                        if (d === 'eligible') {
                            return '<span class="status-badge status-eligible">✅ Eligible</span>';
                        } else if (d === 'mixed') {
                            return '<span class="status-badge status-mixed">⚡ Mixed</span>';
                        } else {
                            return '<span class="status-badge status-not-eligible">❌ Not Eligible</span>';
                        }
                    }
                },

                // 9 — Bonus Points (summed in consolidated mode)
                {
                    data: 'bonus_points_awarded',
                    className: 'bonus-cell text-end',
                    render: d => formatNumber(d)
                },

                // 10 — Executed By
                {
                    data: 'executed_by_user_name',
                    render: function (d, t, row) {
                        return escapeHtml(d) +
                               '<br><small class="text-muted">' +
                               escapeHtml(formatUserType(row.executed_by_user_type)) +
                               '</small>';
                    }
                },

                // 11 — Executed At (latest in consolidated mode)
                {
                    data: 'executed_at',
                    render: function (d, t, row) {
                        if (!d) return 'N/A';
                        const date = new Date(d);
                        const prefix = (parseInt(row.months_count) || 1) > 1
                            ? '<small class="text-muted d-block" style="font-size:10px">Latest</small>'
                            : '';
                        return prefix +
                               date.toLocaleDateString('en-IN') +
                               '<br><small class="text-muted">' + date.toLocaleTimeString('en-IN') + '</small>';
                    }
                },

                // 12 — Actions
                // In consolidated mode the view-btn uses the MIN(id) from the group,
                // which will open the details for the earliest record of that user.
                {
                    data: null,
                    orderable: false,
                    width: '80px',
                    render: function (d, t, row) {
                        const id           = escapeHtml(row.id);
                        const isConsolidated = (parseInt(row.months_count) || 1) > 1;
                        const title        = isConsolidated ? 'View Earliest Record' : 'View Details';
                        return '<button class="btn btn-sm btn-primary btn-action view-btn" data-id="' + id + '" title="' + title + '">' +
                               '<i class="material-icons">visibility</i></button>';
                    }
                }
            ],

            order: [[11, 'desc']],
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
                emptyTable:     "No bonus points records found",
                loadingRecords: "Loading bonus points data...",
                processing:     "Processing...",
                zeroRecords:    "No matching bonus points records found"
            }
        });

        // ====================================================================
        // FILTER FORM — submit & reset
        // ====================================================================

        $('#filterForm').on('submit', function () {
            table.ajax.reload();
        });

        $('#btnReset').on('click', function () {
            $('#filterForm')[0].reset();
            $('#user_id').html('<option value="">All Users</option>');
            loadUsers(); // reload user list without filters
            table.ajax.reload();
        });

        // ====================================================================
        // DYNAMIC USER DROPDOWN
        // Loads users filtered by type & district via AJAX.
        // ====================================================================

        function loadUsers() {
            const userType   = $('#user_type').val();
            const districtId = $('#district_id').val();
            const userSelect = $('#user_id');

            userSelect.html('<option value="">Loading...</option>');

            let url = 'get-bonus-filter-data.php?action=get_users';
            if (userType)   url += '&user_type='   + encodeURIComponent(userType);
            if (districtId) url += '&district_id=' + encodeURIComponent(districtId);

            $.get(url)
                .done(function (response) {
                    userSelect.html('<option value="">All Users</option>');
                    if (response.success && response.data && response.data.length > 0) {
                        response.data.forEach(function (user) {
                            userSelect.append(
                                '<option value="' + escapeHtml(user.id) + '">' +
                                escapeHtml(user.name) + '</option>'
                            );
                        });
                    } else {
                        userSelect.html('<option value="">No users found</option>');
                    }
                })
                .fail(function () {
                    userSelect.html('<option value="">Error loading users</option>');
                });
        }

        // Initial load
        loadUsers();

        $('#user_type').on('change', function () {
            $('#user_id').val('');
            loadUsers();
        });

        $('#district_id').on('change', function () {
            $('#user_id').val('');
            loadUsers();
        });

        // ====================================================================
        // VIEW DETAILS MODAL
        // ====================================================================

        $('#bonusPointsTable').on('click', '.view-btn', function () {
            const id = $(this).data('id');
            $('#modalContent').html(
                '<div class="text-center py-4">' +
                '<div class="spinner-border text-primary" role="status">' +
                '<span class="visually-hidden">Loading...</span></div></div>'
            );
            $('#viewModal').modal('show');

            $.post('get-bonus-points-details.php', {
                id: id,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            })
            .done(function (response) {
                $('#modalContent').html(response);
            })
            .fail(function () {
                $('#modalContent').html(
                    '<div class="alert alert-danger">Error loading bonus points details. Please try again.</div>'
                );
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