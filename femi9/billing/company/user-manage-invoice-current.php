<?php 
include("checksession.php");

$title = "Manage Invoices";
date_default_timezone_set("Asia/Kolkata");
error_reporting(0);

$getinvuser = $_REQUEST['invuser'] ?? '';
$invoice_id = base64_encode($invoice['inv_id']);

// User type configuration
$user_type_config = [
    'candf' => [
        'label' => 'C&F',
        'table' => 'c_and_f',
        'show_advance_indicator' => false
    ],
    'super_stockiest' => [
        'label' => 'Super Stockist',
        'table' => 'super_stockiest',
        'show_advance_indicator' => true
    ],
    'stockiest' => [
        'label' => 'Stockist',
        'table' => 'stockiest',
        'show_advance_indicator' => true
    ],
    'super_distributor' => [
        'label' => 'Super Distributor',
        'table' => 'super_distributor',
        'show_advance_indicator' => false
    ],
    'distributor' => [
        'label' => 'Distributor',
        'table' => 'distributor',
        'show_advance_indicator' => false
    ],
    'outlet' => [
        'label' => 'Outlet',
        'table' => 'outlet',
        'show_advance_indicator' => false
    ]
];

$config = $user_type_config[$getinvuser] ?? null;
if (!$config) {
    echo "<script>window.location='dashboard.php';</script>";
    exit;
}

$user_label = $config['label'];
$tablename = $config['table'];
$show_advance_indicator = $config['show_advance_indicator'];

// Only include advance functions if needed and file exists
if ($show_advance_indicator && file_exists("advance-payment-functions.php")) {
    require_once("advance-payment-functions.php");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?> : <?php echo $business_name; ?></title>
    
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    
    <style>
    * {
        font-family: 'Poppins', sans-serif;
    }
    
    .app-content {
        background: #f8fafc;
    }
    
    /* Page Header */
    .page-header-section {
        background: white;
        padding: 24px 32px;
        margin-bottom: 24px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .page-title {
        margin: 0;
        font-size: 26px;
        font-weight: 600;
        color: #1e293b;
    }
    
    .page-subtitle {
        margin: 4px 0 0 0;
        font-size: 14px;
        color: #64748b;
        font-weight: 400;
    }
    
    /* Create Button */
    .btn-create {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 12px 28px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 15px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        text-decoration: none;
    }
    
    .btn-create:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        color: white;
    }
    
    .btn-create i {
        font-size: 20px;
    }
    
    /* Filter Section */
    .filter-section {
        background: white;
        padding: 20px 28px;
        margin-bottom: 20px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }
    
    .filter-section .form-label {
        font-weight: 500;
        color: #475569;
        font-size: 13px;
        margin-bottom: 6px;
    }
    
    .filter-section .form-control {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 10px 12px;
        font-size: 14px;
    }
    
    .filter-section .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .btn-filter {
        background: #3b82f6;
        border: none;
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 500;
        color: white;
        transition: all 0.2s ease;
    }
    
    .btn-filter:hover {
        background: #2563eb;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    
    .btn-reset {
        padding: 10px 20px;
        border-radius: 8px;
        margin-left: 8px;
        border: 1px solid #e2e8f0;
        background: white;
        color: #64748b;
        transition: all 0.2s ease;
    }
    
    .btn-reset:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
        color: #475569;
    }
    
    /* Card Styling */
    .invoice-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }
    
    .invoice-card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px 28px;
        border-bottom: none;
    }
    
    .invoice-card-title {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: white;
    }
    
    .invoice-card-body {
        padding: 28px;
    }
    
    /* Table Styling */
    table.dataTable {
        border-collapse: separate !important;
        border-spacing: 0;
        width: 100% !important;
    }
    
    table.dataTable thead {
        background: #f8fafc;
    }
    
    table.dataTable thead th {
        background: #f8fafc !important;
        border: none !important;
        border-bottom: 2px solid #e2e8f0 !important;
        padding: 16px 12px !important;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    table.dataTable tbody td {
        padding: 16px 12px !important;
        border: none !important;
        border-bottom: 1px solid #f1f5f9 !important;
        vertical-align: middle;
        font-size: 14px;
        color: #334155;
    }
    
    table.dataTable tbody tr {
        transition: all 0.2s ease;
    }
    
    table.dataTable tbody tr:hover {
        background: #f8fafc !important;
    }
    
    /* Serial Number */
    .serial-num {
        font-weight: 600;
        color: #64748b;
    }
    
    /* Invoice Number */
    .invoice-num {
        font-weight: 600;
        color: #1e293b;
        font-family: 'Courier New', monospace;
    }
    
    /* Customer Info */
    .customer-name {
        font-weight: 500;
        color: #1e293b;
        display: block;
        margin-bottom: 4px;
    }
    
    .customer-mobile {
        font-size: 12px;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .customer-mobile i {
        font-size: 14px;
    }
    
    /* Date */
    .invoice-date {
        color: #475569;
        font-size: 14px;
    }
    
    /* Amount */
    .invoice-amount {
        font-weight: 600;
        color: #0f172a;
        font-size: 15px;
        font-family: 'Courier New', monospace;
    }
    
    /* Status Badges */
    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    
    .status-paid {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-partial {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-unpaid {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .balance-amount {
        display: block;
        font-size: 11px;
        color: #64748b;
        margin-top: 4px;
    }
    
    /* Payment Type Badges */
    .payment-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .payment-advance {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .payment-manual {
        background: #10b981;
        color: white;
    }
    
    .payment-mixed {
        background: linear-gradient(90deg, #667eea 50%, #10b981 50%);
        color: white;
    }
    
    .payment-badge i {
        font-size: 14px;
    }
    
    /* Action Buttons Container */
    .action-btns {
        display: flex;
        gap: 6px;
        flex-wrap: nowrap;
    }
    
    /* Action Buttons */
    .btn-action {
        padding: 8px 12px;
        border-radius: 8px;
        border: none;
        font-size: 12px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s ease;
        text-decoration: none;
        color: white;
        cursor: pointer;
    }
    
    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        color: white;
    }
    
    .btn-action i {
        font-size: 16px;
    }
    
    .btn-print {
        background: #3b82f6;
    }
    
    .btn-print:hover {
        background: #2563eb;
    }
    
    .btn-receipt {
        background: #10b981;
    }
    
    .btn-receipt:hover {
        background: #059669;
    }
    
    .btn-edit {
        background: #f59e0b;
    }
    
    .btn-edit:hover {
        background: #d97706;
    }
    
    .btn-delete {
        background: #ef4444;
    }
    
    .btn-delete:hover {
        background: #dc2626;
    }
    
    /* DataTables Controls */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        padding: 8px 0;
    }
    
    .dataTables_wrapper .dataTables_length select,
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 14px;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 6px 12px;
        margin: 0 2px;
        border-radius: 6px;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        color: white !important;
        border: none !important;
    }
    
    /* Alert */
    .alert {
        border-radius: 10px;
        border: none;
        padding: 16px 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 64px;
        color: #cbd5e1;
        margin-bottom: 16px;
    }
    
    .empty-state h3 {
        font-size: 18px;
        color: #475569;
        margin-bottom: 8px;
    }
    
    .empty-state p {
        color: #64748b;
        font-size: 14px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .page-header-section {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        
        .btn-create {
            width: 100%;
            justify-content: center;
        }
        
        .filter-section .row > div {
            margin-bottom: 12px;
        }
        
        .btn-filter,
        .btn-reset {
            width: 100%;
            margin-left: 0;
            margin-top: 8px;
        }
        
        .action-btns {
            flex-direction: column;
        }
        
        .btn-action {
            width: 100%;
            justify-content: center;
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
                        <div class="page-header-section">
                            <div>
                                <h1 class="page-title">Invoice Management</h1>
                                <p class="page-subtitle"><?php echo $user_label; ?> Invoices</p>
                            </div>
                            <a href="user-invoice-add.php?invuser=<?= $getinvuser; ?>" class="btn-create">
                                <i class="material-icons">add_circle</i>
                                Create New Invoice
                            </a>
                        </div>
                        
                        <!-- Date Filter Section -->
                        <div class="filter-section">
                            <form method="GET" action="" id="filterForm">
                                <input type="hidden" name="invuser" value="<?= $getinvuser; ?>">
                                <div class="row align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">From Date</label>
                                        <input type="date" name="from_date" id="from_date" class="form-control" 
                                               value="<?= $_GET['from_date'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">To Date</label>
                                        <input type="date" name="to_date" id="to_date" class="form-control" 
                                               value="<?= $_GET['to_date'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <button type="submit" class="btn btn-filter">
                                            <i class="material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 4px;">filter_list</i>
                                            Apply Filter
                                        </button>
                                        <a href="?invuser=<?= $getinvuser; ?>" class="btn btn-reset">
                                            <i class="material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 4px;">refresh</i>
                                            Reset
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Active Filter Indicator -->
                        <?php 
                        $filter_active = !empty($_GET['from_date']) || !empty($_GET['to_date']);
                        if ($filter_active) { 
                        ?>
                            <div class="alert alert-info">
                                <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">info</i>
                                <strong>Filter Active:</strong>
                                <?php if (!empty($_GET['from_date'])) echo "From: " . date('d/m/Y', strtotime($_GET['from_date'])); ?>
                                <?php if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) echo " "; ?>
                                <?php if (!empty($_GET['to_date'])) echo "To: " . date('d/m/Y', strtotime($_GET['to_date'])); ?>
                                <a href="?invuser=<?= $getinvuser; ?>" style="margin-left: 12px; color: #0c63e4; text-decoration: none; font-weight: 500;">Clear Filter</a>
                            </div>
                        <?php } ?>
                        
                        <!-- Success Alert -->
                        <?php if (isset($_REQUEST['InvoiceDeleted'])) { ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">check_circle</i>
                                Invoice deleted successfully.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php } ?>
                        
                        <!-- Invoice Table Card -->
                        <div class="invoice-card">
                            <div class="invoice-card-header">
                                <h5 class="invoice-card-title">All Invoices</h5>
                            </div>
                            <div class="invoice-card-body">
                                <table id="invoiceTable" class="display" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>S.No</th>
                                            <th>Invoice Number</th>
                                            <th>Customer Details</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <?php if ($show_advance_indicator) { ?>
                                                <th>Payment Type</th>
                                            <?php } ?>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $serial = 0;
                                        
                                        // Build WHERE clause with date filter
                                        $where_conditions = ["ui.to_user_type = '$getinvuser'"];
                                        
                                        // Add date filters if provided, otherwise default to last 7 days
                                        if (!empty($_GET['from_date'])) {
                                            $from_date = mysqli_real_escape_string($db_conn, $_GET['from_date']);
                                            $where_conditions[] = "ui.date >= '$from_date'";
                                        } elseif (empty($_GET['to_date'])) {
                                            // If no filters provided, show last 7 days by default
                                            $where_conditions[] = "ui.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                                        }
                                        
                                        if (!empty($_GET['to_date'])) {
                                            $to_date = mysqli_real_escape_string($db_conn, $_GET['to_date']);
                                            $where_conditions[] = "ui.date <= '$to_date'";
                                        }
                                        
                                        $where_clause = implode(' AND ', $where_conditions);
                                        
                                        $select_invoices = "SELECT 
                                            ui.id,
                                            ui.inv_id,
                                            ui.inv_number,
                                            ui.date,
                                            ui.total,
                                            ui.to_user_id,
                                            ui.to_user_type,
                                            u.name as customer_name,
                                            u.mobile_number
                                        FROM user_invoice ui
                                        LEFT JOIN $tablename u ON ui.to_user_id = u.temp_id
                                        WHERE $where_clause
                                        ORDER BY ui.date DESC, ui.id DESC";
                                        
                                        $fetch_invoices = mysqli_query($db_conn, $select_invoices);
                                        
                                        if ($fetch_invoices) {
                                            while ($invoice = mysqli_fetch_array($fetch_invoices)) {
                                                $serial++;
                                                $inv_id = $invoice['inv_id'];
                                                $invoice_total = floatval($invoice['total']);
                                                
                                                // Get manual receipt total
                                                $receipt_query = "SELECT IFNULL(SUM(received), 0) as manual_paid 
                                                                 FROM receipt 
                                                                 WHERE inv_id = '{$inv_id}'";
                                                $receipt_result = mysqli_query($db_conn, $receipt_query);
                                                $receipt_data = mysqli_fetch_array($receipt_result);
                                                $manual_paid = floatval($receipt_data['manual_paid']);
                                                
                                                // Get advance payment adjustments
                                                $advance_paid = 0;
                                                $payment_type = 'none';
                                                
                                                if ($show_advance_indicator && function_exists('getInvoiceAdjustments')) {
                                                    try {
                                                        $adjustments = getInvoiceAdjustments($db_conn, $inv_id);
                                                        foreach ($adjustments as $adj) {
                                                            $advance_paid += floatval($adj['adjusted_amount']);
                                                        }
                                                        
                                                        if ($advance_paid > 0 && $manual_paid > 0) {
                                                            $payment_type = 'mixed';
                                                        } elseif ($advance_paid > 0) {
                                                            $payment_type = 'advance';
                                                        } elseif ($manual_paid > 0) {
                                                            $payment_type = 'manual';
                                                        }
                                                    } catch (Exception $e) {
                                                        // Silent fail
                                                    }
                                                }
                                                
                                                $total_paid = $manual_paid + $advance_paid;
                                                $balance = $invoice_total - $total_paid;
                                                
                                                // Fixed status logic
                                                if ($invoice_total <= 0) {
                                                    // Zero or negative invoice amount = Unpaid
                                                    $status_class = 'status-unpaid';
                                                    $status_text = 'Unpaid';
                                                } elseif ($balance <= 0.01) {
                                                    // Fully paid (allowing for small rounding differences)
                                                    $status_class = 'status-paid';
                                                    $status_text = 'Paid';
                                                } elseif ($total_paid > 0) {
                                                    // Partially paid
                                                    $status_class = 'status-partial';
                                                    $status_text = 'Partial';
                                                } else {
                                                    // No payment received
                                                    $status_class = 'status-unpaid';
                                                    $status_text = 'Unpaid';
                                                }
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="serial-num"><?= $serial; ?></span>
                                                </td>
                                                <td>
                                                    <span class="invoice-num"><?= htmlspecialchars($invoice['inv_number']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="customer-name"><?= htmlspecialchars($invoice['customer_name']); ?></span>
                                                    <span class="customer-mobile">
                                                        <i class="material-icons">phone</i>
                                                        <?= htmlspecialchars($invoice['mobile_number']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="invoice-date"><?= date('d/m/Y', strtotime($invoice['date'])); ?></span>
                                                </td>
                                                <td>
                                                    <span class="invoice-amount">₹<?= inr_format($invoice_total, 2); ?></span>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?= $status_class; ?>"><?= $status_text; ?></span>
                                                    <?php if ($balance > 0) { ?>
                                                        <span class="balance-amount">Bal: ₹<?= inr_format($balance, 2); ?></span>
                                                    <?php } ?>
                                                </td>
                                                <?php if ($show_advance_indicator) { ?>
                                                    <td>
                                                        <?php 
                                                        // Show payment type based on what we know
                                                        if ($advance_paid > 0 && $manual_paid > 0) {
                                                            echo '<span class="payment-badge payment-mixed"><i class="material-icons">payments</i> Mixed</span>';
                                                        } elseif ($advance_paid > 0) {
                                                            echo '<span class="payment-badge payment-advance"><i class="material-icons">account_balance_wallet</i> Advance</span>';
                                                        } elseif ($manual_paid > 0) {
                                                            echo '<span class="payment-badge payment-manual"><i class="material-icons">receipt</i> Manual</span>';
                                                        } else {
                                                            echo '<span class="text-muted">-</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                <?php } ?>
                                                <td>
                                                    <div class="action-btns">
                                                        <a href="user-invoice-print.php?invoiceid=<?= base64_encode($invoice['inv_id']); ?>&invuser=<?= $getinvuser; ?>" 
                                                           class="btn-action btn-print" target="_blank">
                                                            <i class="material-icons">print</i>
                                                            Print
                                                        </a>
                                                        <a href="add-receipt.php?invid=<?= $invoice['inv_id']; ?>&invuser=<?= $getinvuser; ?>" 
                                                           class="btn-action btn-receipt">
                                                            <i class="material-icons">receipt_long</i>
                                                            Receipt
                                                        </a>
                                                        <a href="user-invoice-add.php?InvoiceID=<?= base64_encode($invoice['inv_id']); ?>&invuser=<?= $getinvuser; ?>&action=edit" 
                                                           class="btn-action btn-edit">
                                                            <i class="material-icons">edit</i>
                                                            Edit
                                                        </a>
                                                        <a href="delete-invoice.php?invid=<?= base64_encode($invoice['inv_id']); ?>&invuser=<?= $getinvuser; ?>" 
                                                           class="btn-action btn-delete" 
                                                           onclick="return confirm('Are you sure you want to delete this invoice?');">
                                                            <i class="material-icons">delete</i>
                                                            Delete
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php 
                                            }
                                        }
                                        
                                        if ($serial == 0) {
                                        ?>
                                            <tr>
                                                <td colspan="<?= $show_advance_indicator ? '8' : '7'; ?>">
                                                    <div class="empty-state">
                                                        <i class="material-icons-outlined">receipt_long</i>
                                                        <h3>No Invoices Found</h3>
                                                        <p>
                                                            <?php if ($filter_active) { ?>
                                                                No invoices match your filter criteria. Try adjusting your date range.
                                                            <?php } else { ?>
                                                                No invoices found in the last 7 days. Use the date filter to view older invoices or click "Create New Invoice" to get started.
                                                            <?php } ?>
                                                        </p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/datatables/datatables.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    
    <script>
    $(document).ready(function() {
        $('#invoiceTable').DataTable({
            "order": [[3, "desc"]],
            "pageLength": 25,
            "columnDefs": [
                { 
                    "orderable": false, 
                    "targets": <?= $show_advance_indicator ? '7' : '6'; ?> 
                },
                {
                    "targets": 3,  // Date column
                    "type": "date",
                    "render": function(data, type, row) {
                        if (type === 'sort' || type === 'type') {
                            // Convert dd/mm/yyyy to yyyy-mm-dd for proper sorting
                            var parts = data.split('/');
                            return parts[2] + '-' + parts[1] + '-' + parts[0];
                        }
                        return data;
                    }
                }
            ],
            "language": {
                "search": "Search:",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ invoices",
                "paginate": {
                    "previous": "Previous",
                    "next": "Next"
                }
            }
        });
    });
    </script>
</body>
</html>