<?php 
/**
 * OT Channel Sales Report - Detail Page
 * 
 * Displays channel-wise sales with product breakdown
 * Shows NET figures with (Gross - Returns) breakdown on hover
 * 
 * Security: Prepared statements, XSS protection, input validation
 * Performance: Optimized queries with proper indexing
 * 
 * @version 2.0
 * @author Femi9 Development Team
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

ob_start();

// Session and config
require_once("checksession.php");
require_once("config.php");

// Set UTF-8 charset for proper character handling
if (!mysqli_set_charset($db_conn, 'utf8mb4')) {
    die("Error loading character set utf8mb4: " . mysqli_error($db_conn));
}
mysqli_query($db_conn, "SET collation_connection = 'utf8mb4_general_ci'");
mysqli_query($db_conn, "SET collation_server = 'utf8mb4_general_ci'");

// Clear filters if requested
if (isset($_GET['clear_filters']) || isset($_POST['clear_all'])) {
    ob_clean();
    unset($_SESSION['ot_report_from_date'], $_SESSION['ot_report_to_date']);
    header("Location: " . $_SERVER['PHP_SELF'], true, 303);
    exit;
}

// Calculate default date range (last 7 days)
$to_date = date('Y-m-d');
$from_date = date('Y-m-d', strtotime('-7 days'));

// Override with POST data (with validation)
if (isset($_POST['frdate']) && !empty($_POST['frdate'])) {
    $input_from = filter_input(INPUT_POST, 'frdate', FILTER_SANITIZE_STRING);
    if (DateTime::createFromFormat('Y-m-d', $input_from) !== false) {
        $from_date = $input_from;
        $_SESSION['ot_report_from_date'] = $from_date;
    }
}

if (isset($_POST['todate']) && !empty($_POST['todate'])) {
    $input_to = filter_input(INPUT_POST, 'todate', FILTER_SANITIZE_STRING);
    if (DateTime::createFromFormat('Y-m-d', $input_to) !== false) {
        $to_date = $input_to;
        $_SESSION['ot_report_to_date'] = $to_date;
    }
}

// Restore from session
if (isset($_SESSION['ot_report_from_date'])) {
    $from_date = $_SESSION['ot_report_from_date'];
}
if (isset($_SESSION['ot_report_to_date'])) {
    $to_date = $_SESSION['ot_report_to_date'];
}

// Validate dates and ensure from_date <= to_date
$from_date_obj = DateTime::createFromFormat('Y-m-d', $from_date);
$to_date_obj = DateTime::createFromFormat('Y-m-d', $to_date);

if (!$from_date_obj || !$to_date_obj || $from_date > $to_date) {
    $to_date = date('Y-m-d');
    $from_date = date('Y-m-d', strtotime('-7 days'));
}

$Report_LABEL = "OT Channel Sales Report";
$page_title = htmlspecialchars($Report_LABEL . ' : ' . ($business_name ?? 'Business'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <title><?php echo $title;?> : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">

    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    
    <style>
        /* ============================================
           ROOT VARIABLES - Blue Theme Colors
           ============================================ */
        :root {
            --primary-blue: #0d6efd;
            --primary-blue-dark: #0b5ed7;
            --primary-blue-light: #e3f2fd;
            --primary-blue-lighter: #f0f8ff;
            
            --secondary-gray: #6c757d;
            --secondary-gray-light: #e9ecef;
            --secondary-gray-lighter: #f8f9fa;
            
            --border-color: #dee2e6;
            --text-primary: #212529;
            --text-secondary: #495057;
            --text-muted: #6c757d;
            
            --success-green: #198754;
            --warning-yellow: #ffc107;
            --danger-red: #dc3545;
            --info-blue: #0dcaf0;
            
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
            
            --border-radius-sm: 6px;
            --border-radius-md: 8px;
            --border-radius-lg: 12px;
            
            --spacing-xs: 8px;
            --spacing-sm: 12px;
            --spacing-md: 16px;
            --spacing-lg: 24px;
            --spacing-xl: 32px;
        }

        /* ============================================
           GLOBAL STYLES
           ============================================ */
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-primary);
            background-color: #f5f7fa;
        }

        /* ============================================
           CARD COMPONENTS
           ============================================ */
        .card {
            border: none;
            box-shadow: var(--shadow-md);
            border-radius: var(--border-radius-lg);
            margin-bottom: var(--spacing-lg);
            transition: box-shadow 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            background: linear-gradient(135deg, #ffffff 0%, var(--secondary-gray-lighter) 100%);
            border-bottom: 2px solid var(--border-color);
            border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
            padding: var(--spacing-md) var(--spacing-lg);
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--text-primary);
        }

        .card-body {
            padding: var(--spacing-lg);
        }

        /* ============================================
           PAGE HEADER
           ============================================ */
        .page-description {
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-md) 0;
        }

        .page-description h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .headertble {
            width: 100%;
            border: none;
        }

        .headertble td {
            padding: var(--spacing-xs);
            vertical-align: middle;
        }

        .headertble td:first-child {
            text-align: left;
        }

        .headertble td:last-child {
            text-align: right;
        }

        /* ============================================
           FORM ELEMENTS
           ============================================ */
        .form-label {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: var(--spacing-xs);
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: 0.625rem 0.875rem;
            font-size: 0.9375rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }

        /* ============================================
           BUTTONS
           ============================================ */
        .btn {
            padding: 0.625rem 1.25rem;
            font-size: 0.9375rem;
            font-weight: 500;
            border-radius: var(--border-radius-sm);
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn i.material-icons {
            font-size: 1.125rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-dark) 100%);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-blue-dark) 0%, #0a58ca 100%);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: var(--secondary-gray);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-1px);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-green) 0%, #157347 100%);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #157347 0%, #0f5132 100%);
            transform: translateY(-1px);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* ============================================
           ALERTS
           ============================================ */
        .alert {
            border-radius: var(--border-radius-md);
            border: none;
            box-shadow: var(--shadow-sm);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }

        .alert-info {
            background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--primary-blue-lighter) 100%);
            color: #055160;
            border-left: 4px solid var(--primary-blue);
        }

        .alert-success {
            background: linear-gradient(135deg, #d1e7dd 0%, #e8f5e9 100%);
            color: #0a3622;
            border-left: 4px solid var(--success-green);
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #fffbeb 100%);
            color: #664d03;
            border-left: 4px solid var(--warning-yellow);
        }

        /* ============================================
           TABLE STYLES
           ============================================ */
        #overflowon {
            width: 100%; 
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: var(--spacing-md) 0;
        }

        .table-container {
            background: white;
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-sm);
            padding: var(--spacing-md);
        }

        .table {
            margin-bottom: 0;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-bordered {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-md);
            overflow: hidden;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--secondary-gray-lighter) 0%, var(--secondary-gray-light) 100%);
            font-weight: 600;
            white-space: nowrap;
            font-size: 0.875rem;
            padding: var(--spacing-md) var(--spacing-sm);
            border-color: var(--border-color);
            color: var(--text-secondary);
            position: sticky;
            top: 0;
            z-index: 10;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            vertical-align: middle;
        }

        .table tbody td {
            white-space: nowrap;
            font-size: 0.875rem;
            padding: var(--spacing-sm) var(--spacing-sm);
            vertical-align: middle;
            border-color: var(--border-color);
            background-color: white;
        }

        .table tfoot th {
            background: linear-gradient(135deg, var(--secondary-gray-light) 0%, var(--secondary-gray-lighter) 100%);
            font-weight: 700;
            font-size: 0.875rem;
            padding: var(--spacing-md) var(--spacing-sm);
            border-top: 2px solid var(--primary-blue);
            color: var(--text-primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-hover tbody tr {
            transition: background-color 0.2s ease;
        }

        .table-hover tbody tr:hover {
            background-color: var(--primary-blue-light) !important;
        }

        .table tbody tr:nth-child(even) td {
            background-color: #fafbfc;
        }

        .table tbody tr:hover td {
            background-color: var(--primary-blue-light) !important;
        }

        /* Product column specific styles */
        .product-col {
            background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--primary-blue-lighter) 100%) !important;
            text-align: center;
            min-width: 100px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .table-hover tbody tr:hover .product-col {
            background: linear-gradient(135deg, #bbdefb 0%, var(--primary-blue-light) 100%) !important;
        }

        /* ============================================
           BREAKDOWN TOOLTIP STYLES
           ============================================ */
        .breakdown-value {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: help;
            color: var(--primary-blue);
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }

        .breakdown-value:hover {
            background-color: rgba(13, 110, 253, 0.08);
        }

        .breakdown-tooltip {
            visibility: hidden;
            background-color: #2c3e50;
            color: #fff;
            text-align: center;
            border-radius: var(--border-radius-sm);
            padding: var(--spacing-xs) var(--spacing-sm);
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            font-size: 0.75rem;
            font-weight: normal;
            box-shadow: var(--shadow-md);
            pointer-events: none;
        }

        .breakdown-tooltip::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -6px;
            border-width: 6px;
            border-style: solid;
            border-color: #2c3e50 transparent transparent transparent;
        }

        .breakdown-value:hover .breakdown-tooltip {
            visibility: visible;
            opacity: 1;
        }

        .info-icon {
            font-size: 1rem;
            color: var(--text-muted);
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }

        .breakdown-value:hover .info-icon {
            opacity: 1;
        }

        /* ============================================
           TOTAL ROW STYLING
           ============================================ */
        .total-row {
            background: linear-gradient(135deg, var(--secondary-gray-light) 0%, var(--secondary-gray-lighter) 100%);
            font-weight: 700;
            border-top: 3px solid var(--primary-blue);
        }

        /* ============================================
           HEADER INFO SECTION
           ============================================ */
        .header-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--spacing-md);
            flex-wrap: wrap;
        }

        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background-color: var(--primary-blue-light);
            color: var(--text-primary);
            border-radius: var(--border-radius-sm);
            font-size: 0.8125rem;
        }

        .info-badge i {
            font-size: 1rem;
        }

        /* ============================================
           FILTER SECTION
           ============================================ */
        .filter-section {
            background: white;
            padding: var(--spacing-lg);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--spacing-lg);
        }

        .d-flex.gap-2 {
            gap: 0.5rem;
        }

        /* ============================================
           RESPONSIVE DESIGN
           ============================================ */
        @media (max-width: 768px) {
            .card-body {
                padding: var(--spacing-md);
            }

            .table thead th,
            .table tbody td,
            .table tfoot th {
                font-size: 0.75rem;
                padding: var(--spacing-xs) 6px;
            }
            
            .breakdown-tooltip {
                font-size: 0.625rem;
                padding: 6px 8px;
            }

            .btn {
                padding: 0.5rem 0.875rem;
                font-size: 0.875rem;
            }

            .page-description h1 {
                font-size: 1.375rem;
            }

            .headertble td:last-child {
                text-align: left;
                margin-top: var(--spacing-sm);
            }

            /* Mobile: Click to show breakdown */
            .breakdown-mobile {
                display: none;
                font-size: 0.6875rem;
                color: var(--text-muted);
                margin-top: 4px;
                font-weight: normal;
            }
            
            .breakdown-value.active .breakdown-mobile {
                display: block;
            }

            .alert {
                padding: var(--spacing-sm);
                font-size: 0.875rem;
            }
        }

        @media (max-width: 576px) {
            :root {
                --spacing-md: 12px;
                --spacing-lg: 16px;
            }

            .col-md-3,
            .col-md-4 {
                margin-bottom: var(--spacing-sm);
            }
        }

        /* ============================================
           UTILITY CLASSES
           ============================================ */
        .text-muted {
            color: var(--text-muted) !important;
        }

        .text-primary {
            color: var(--primary-blue) !important;
        }

        .text-danger {
            color: var(--danger-red) !important;
        }

        .fw-bold {
            font-weight: 600 !important;
        }

        .small {
            font-size: 0.875rem;
        }

        /* ============================================
           SCROLLBAR STYLING
           ============================================ */
        #overflowon::-webkit-scrollbar {
            height: 8px;
        }

        #overflowon::-webkit-scrollbar-track {
            background: var(--secondary-gray-lighter);
            border-radius: 4px;
        }

        #overflowon::-webkit-scrollbar-thumb {
            background: var(--secondary-gray);
            border-radius: 4px;
        }

        #overflowon::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }

        /* ============================================
           PRINT STYLES
           ============================================ */
        @media print {
            .btn,
            .card-header .d-flex,
            .filter-section {
                display: none !important;
            }

            .card {
                box-shadow: none;
                border: 1px solid var(--border-color);
            }

            .breakdown-tooltip {
                display: none !important;
            }
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
                                        <table class="headertble">
                                            <tr>
                                                <td><?= htmlspecialchars($Report_LABEL, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td>
                                                    <form method="post" action="export_ot_channel_sales_xlsx.php" target="_blank" class="d-inline">
                                                        <input type="hidden" name="frdate" value="<?= htmlspecialchars($from_date, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="todate" value="<?= htmlspecialchars($to_date, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="material-icons">download</i> Export to Excel
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <!-- Date Filter Form -->
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">
                                            <i class="material-icons-outlined" style="vertical-align: middle; font-size: 1.25rem;">date_range</i>
                                            Filter by Date Range
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" id="filterForm">
                                            <div class="row align-items-end">
                                                <div class="col-md-3 col-sm-6 mb-3">
                                                    <label class="form-label">From Date <span class="text-danger">*</span></label>
                                                    <input type="date" name="frdate" value="<?= htmlspecialchars($from_date, ENT_QUOTES, 'UTF-8'); ?>" class="form-control" required max="<?= date('Y-m-d'); ?>">
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-3">
                                                    <label class="form-label">To Date <span class="text-danger">*</span></label>
                                                    <input type="date" name="todate" value="<?= htmlspecialchars($to_date, ENT_QUOTES, 'UTF-8'); ?>" class="form-control" required max="<?= date('Y-m-d'); ?>">
                                                </div>
                                                <div class="col-md-6 col-sm-12 mb-3">
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        <button type="submit" name="filter_dates" class="btn btn-primary">
                                                            <i class="material-icons">search</i> Apply Filter
                                                        </button>
                                                        <button type="submit" name="clear_all" class="btn btn-secondary">
                                                            <i class="material-icons">refresh</i> Reset
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="alert alert-info mb-0">
                                                <div class="d-flex align-items-center">
                                                    <i class="material-icons me-2">info</i>
                                                    <div>
                                                        <strong>Selected Period:</strong> 
                                                        <?= date('d M Y', strtotime($from_date)); ?> to <?= date('d M Y', strtotime($to_date)); ?>
                                                        (<?= (int)((strtotime($to_date) - strtotime($from_date)) / 86400) + 1; ?> days)
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Report Table -->
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-header">
                                        <div class="header-info">
                                            <h5 class="card-title mb-0">
                                                <i class="material-icons-outlined" style="vertical-align: middle; font-size: 1.25rem;">assessment</i>
                                                Channel-wise Sales Report
                                            </h5>
                                            <span class="info-badge">
                                                <i class="material-icons">info</i>
                                                Hover over values to see breakdown
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body">
<?php
// Fetch all products
$products = [];
$stmt_products = $db_conn->prepare("SELECT id, productName FROM products ORDER BY id ASC");
if (!$stmt_products) {
    die("Product query preparation failed: " . htmlspecialchars($db_conn->error, ENT_QUOTES, 'UTF-8'));
}
$stmt_products->execute();
$result_products = $stmt_products->get_result();
while ($pr = $result_products->fetch_assoc()) {
    $products[(int)$pr['id']] = $pr['productName'];
}
$stmt_products->close();

if (empty($products)) {
    echo '<div class="alert alert-warning">
            <i class="material-icons" style="vertical-align: middle;">warning</i>
            No products found in the system.
          </div>';
} else {
    // Fetch all unique channels from ot_cat
    $channels = [];
    $stmt_channels = $db_conn->prepare("SELECT id, cat FROM ot_cat ORDER BY cat ASC");
    if (!$stmt_channels) {
        die("Channel query preparation failed: " . htmlspecialchars($db_conn->error, ENT_QUOTES, 'UTF-8'));
    }
    $stmt_channels->execute();
    $result_channels = $stmt_channels->get_result();
    while ($ch = $result_channels->fetch_assoc()) {
        $channels[$ch['cat']] = [
            'id' => (int)$ch['id'],
            'name' => $ch['cat'],
            'sales_qty' => 0,
            'return_qty' => 0,
            'sales_amount' => 0.0,
            'return_amount' => 0.0,
            'products' => []
        ];
    }
    $stmt_channels->close();

    // If no channels in ot_cat, fetch from ot_sales
    if (empty($channels)) {
        $stmt_sales_channels = $db_conn->prepare("SELECT DISTINCT cat FROM ot_sales WHERE cat IS NOT NULL AND cat != '' ORDER BY cat ASC");
        $stmt_sales_channels->execute();
        $result_sales_channels = $stmt_sales_channels->get_result();
        while ($ch = $result_sales_channels->fetch_assoc()) {
            $channels[$ch['cat']] = [
                'id' => 0,
                'name' => $ch['cat'],
                'sales_qty' => 0,
                'return_qty' => 0,
                'sales_amount' => 0.0,
                'return_amount' => 0.0,
                'products' => []
            ];
        }
        $stmt_sales_channels->close();
    }

    // Initialize product arrays for each channel
    foreach ($channels as $cat => &$channel_data) {
        foreach ($products as $pr_id => $pr_name) {
            $channel_data['products'][$pr_id] = [
                'sales_qty' => 0,
                'return_qty' => 0
            ];
        }
    }
    unset($channel_data);

    // Fetch sales data grouped by channel and product
    $stmt_sales = $db_conn->prepare("
        SELECT 
            cat,
            prid,
            SUM(qty) as total_qty,
            SUM(CAST(total AS DECIMAL(10,2))) as total_amount
        FROM ot_sales
        WHERE date BETWEEN ? AND ?
            AND cat IS NOT NULL 
            AND cat != ''
        GROUP BY cat, prid
    ");
    
    if (!$stmt_sales) {
        die("Sales query preparation failed: " . htmlspecialchars($db_conn->error, ENT_QUOTES, 'UTF-8'));
    }
    
    $stmt_sales->bind_param("ss", $from_date, $to_date);
    $stmt_sales->execute();
    $result_sales = $stmt_sales->get_result();
    
    while ($sale = $result_sales->fetch_assoc()) {
        $cat = $sale['cat'];
        $prid = (int)$sale['prid'];
        
        // Initialize channel if not exists
        if (!isset($channels[$cat])) {
            $channels[$cat] = [
                'id' => 0,
                'name' => $cat,
                'sales_qty' => 0,
                'return_qty' => 0,
                'sales_amount' => 0.0,
                'return_amount' => 0.0,
                'products' => []
            ];
            foreach ($products as $pr_id => $pr_name) {
                $channels[$cat]['products'][$pr_id] = [
                    'sales_qty' => 0,
                    'return_qty' => 0
                ];
            }
        }
        
        if (!isset($channels[$cat]['products'][$prid])) {
            $channels[$cat]['products'][$prid] = [
                'sales_qty' => 0,
                'return_qty' => 0
            ];
        }
        
        $channels[$cat]['sales_qty'] += (int)$sale['total_qty'];
        $channels[$cat]['sales_amount'] += (float)$sale['total_amount'];
        $channels[$cat]['products'][$prid]['sales_qty'] += (int)$sale['total_qty'];
    }
    $stmt_sales->close();

    // Fetch returns data grouped by channel and product
    $stmt_returns = $db_conn->prepare("
        SELECT 
            os.cat,
            osr.prid,
            SUM(osr.qty) as total_return_qty,
            SUM(CAST(osr.total AS DECIMAL(10,2))) as total_return_amount
        FROM ot_sales_return osr
        INNER JOIN ot_sales os ON osr.tempid = os.tempid AND osr.prid = os.prid
        WHERE osr.return_date BETWEEN ? AND ?
            AND os.cat IS NOT NULL 
            AND os.cat != ''
        GROUP BY os.cat, osr.prid
    ");
    
    if (!$stmt_returns) {
        die("Returns query preparation failed: " . htmlspecialchars($db_conn->error, ENT_QUOTES, 'UTF-8'));
    }
    
    $stmt_returns->bind_param("ss", $from_date, $to_date);
    $stmt_returns->execute();
    $result_returns = $stmt_returns->get_result();
    
    while ($ret = $result_returns->fetch_assoc()) {
        $cat = $ret['cat'];
        $prid = (int)$ret['prid'];
        
        if (isset($channels[$cat]) && isset($channels[$cat]['products'][$prid])) {
            $channels[$cat]['return_qty'] += (int)$ret['total_return_qty'];
            $channels[$cat]['return_amount'] += (float)$ret['total_return_amount'];
            $channels[$cat]['products'][$prid]['return_qty'] += (int)$ret['total_return_qty'];
        }
    }
    $stmt_returns->close();

    // Sort channels alphabetically
    ksort($channels);

    // Calculate grand totals
    $grand_sales_qty = 0;
    $grand_return_qty = 0;
    $grand_sales_amount = 0.0;
    $grand_return_amount = 0.0;
    $grand_product_sales = [];
    $grand_product_returns = [];

    foreach ($products as $pr_id => $pr_name) {
        $grand_product_sales[$pr_id] = 0;
        $grand_product_returns[$pr_id] = 0;
    }

    foreach ($channels as $channel) {
        $grand_sales_qty += $channel['sales_qty'];
        $grand_return_qty += $channel['return_qty'];
        $grand_sales_amount += $channel['sales_amount'];
        $grand_return_amount += $channel['return_amount'];
        
        foreach ($channel['products'] as $pr_id => $pr_data) {
            $grand_product_sales[$pr_id] += $pr_data['sales_qty'];
            $grand_product_returns[$pr_id] += $pr_data['return_qty'];
        }
    }

    if (!empty($channels)) {
?>

<div id="overflowon">
    <table class="table table-bordered table-hover table-sm">
        <thead>
            <tr>
                <th style="min-width: 150px;">Channel</th>
                <th style="min-width: 150px; text-align: right;">Total Sales (Qty)</th>
                <th style="min-width: 180px; text-align: right;">Total Amount (₹)</th>
                <?php foreach ($products as $pr_id => $pr_name): ?>
                <th class="product-col" title="<?= htmlspecialchars($pr_name, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php 
                    $short_name = mb_strlen($pr_name) > 30 ? mb_substr($pr_name, 0, 27) . '...' : $pr_name;
                    echo htmlspecialchars($short_name, ENT_QUOTES, 'UTF-8');
                    ?>
                </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($channels as $channel): 
                $net_qty = $channel['sales_qty'] - $channel['return_qty'];
                $net_amount = $channel['sales_amount'] - $channel['return_amount'];
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                <td style="text-align: right;">
                    <span class="breakdown-value">
                        <?= inr_format($net_qty, 0); ?>
                        <i class="material-icons info-icon">info</i>
                        <span class="breakdown-tooltip">
                            (<?= inr_format($channel['sales_qty'], 0); ?> - <?= inr_format($channel['return_qty'], 0); ?>)
                        </span>
                    </span>
                </td>
                <td style="text-align: right;">
                    <span class="breakdown-value">
                        ₹<?= inr_format($net_amount, 2); ?>
                        <i class="material-icons info-icon">info</i>
                        <span class="breakdown-tooltip">
                            (₹<?= inr_format($channel['sales_amount'], 2); ?> - ₹<?= inr_format($channel['return_amount'], 2); ?>)
                        </span>
                    </span>
                </td>
                <?php foreach ($products as $pr_id => $pr_name): 
                    $pr_sales = $channel['products'][$pr_id]['sales_qty'] ?? 0;
                    $pr_returns = $channel['products'][$pr_id]['return_qty'] ?? 0;
                    $pr_net = $pr_sales - $pr_returns;
                ?>
                <td class="product-col" style="text-align: center;">
                    <?php if ($pr_net != 0 || $pr_sales != 0): ?>
                    <span class="breakdown-value">
                        <?= inr_format($pr_net, 0); ?>
                        <i class="material-icons info-icon">info</i>
                        <span class="breakdown-tooltip">
                            (<?= inr_format($pr_sales, 0); ?> - <?= inr_format($pr_returns, 0); ?>)
                        </span>
                    </span>
                    <?php else: ?>
                    <span style="color:#ccc;">—</span>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <th>GRAND TOTAL</th>
                <th style="text-align: right;">
                    <span class="breakdown-value">
                        <?= inr_format($grand_sales_qty - $grand_return_qty, 0); ?>
                        <i class="material-icons info-icon">info</i>
                        <span class="breakdown-tooltip">
                            (<?= inr_format($grand_sales_qty, 0); ?> - <?= inr_format($grand_return_qty, 0); ?>)
                        </span>
                    </span>
                </th>
                <th style="text-align: right;">
                    <span class="breakdown-value">
                        ₹<?= inr_format($grand_sales_amount - $grand_return_amount, 2); ?>
                        <i class="material-icons info-icon">info</i>
                        <span class="breakdown-tooltip">
                            (₹<?= inr_format($grand_sales_amount, 2); ?> - ₹<?= inr_format($grand_return_amount, 2); ?>)
                        </span>
                    </span>
                </th>
                <?php foreach ($products as $pr_id => $pr_name): 
                    $grand_pr_net = $grand_product_sales[$pr_id] - $grand_product_returns[$pr_id];
                ?>
                <th class="product-col" style="text-align: center;">
                    <span class="breakdown-value">
                        <?= inr_format($grand_pr_net, 0); ?>
                        <i class="material-icons info-icon">info</i>
                        <span class="breakdown-tooltip">
                            (<?= inr_format($grand_product_sales[$pr_id], 0); ?> - <?= inr_format($grand_product_returns[$pr_id], 0); ?>)
                        </span>
                    </span>
                </th>
                <?php endforeach; ?>
            </tr>
        </tfoot>
    </table>
</div>

<div class="alert alert-success">
    <div class="d-flex align-items-center">
        <i class="material-icons me-2">check_circle</i>
        <div>
            <strong>Summary:</strong> Showing <?= count($channels); ?> channel<?= count($channels) !== 1 ? 's' : ''; ?> with sales data from 
            <?= date('d M Y', strtotime($from_date)); ?> to <?= date('d M Y', strtotime($to_date)); ?>
        </div>
    </div>
</div>

<?php
    } else {
        echo '<div class="alert alert-warning">
                <i class="material-icons" style="vertical-align: middle;">warning</i>
                No channel data found for the selected date range.
              </div>';
    }
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
        'use strict';
        
        // Mobile: Click to toggle breakdown
        document.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth <= 768) {
                const breakdownValues = document.querySelectorAll('.breakdown-value');
                breakdownValues.forEach(function(elem) {
                    elem.addEventListener('click', function(e) {
                        e.preventDefault();
                        this.classList.toggle('active');
                    });
                });
            }
            
            // Date validation: Ensure from_date <= to_date
            const form = document.getElementById('filterForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const fromDate = document.querySelector('input[name="frdate"]').value;
                    const toDate = document.querySelector('input[name="todate"]').value;
                    
                    if (fromDate && toDate && fromDate > toDate) {
                        e.preventDefault();
                        alert('From Date cannot be greater than To Date');
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>