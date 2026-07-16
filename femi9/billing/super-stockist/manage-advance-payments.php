<?php
/**
 * Manage Advance Payments
 * Femi9 Billing Application
 * 
 * Description: List all advance payment entries with filtering and export options
 * Features: Date filter, user type filter, user filter, DataTables, Excel export
 * 
 * @author Femi9 Development Team
 * @version 1.0
 * @date 2025-12-29
 */

include("checksession.php"); 
include("config.php"); 

date_default_timezone_set("Asia/Kolkata");
error_reporting(0);

// Get logged-in user details
$logged_user_id = $_SESSION['LOGIN_USER_ID'] ?? '';
$logged_user_type = $_SESSION['LOGIN_USER_TYPE'] ?? '';
$logged_user_name = $_SESSION['LOGIN_USER'] ?? '';

// Get filter parameters
$filter_from_date = $_GET['from_date'] ?? date('Y-m-01'); // First day of current month
$filter_to_date = $_GET['to_date'] ?? date('Y-m-d'); // Today
$filter_user_type = $_GET['user_type'] ?? '';
$filter_user_id = $_GET['user_id'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Get all unique users for filter dropdown (based on logged-in user permissions)
$users_for_filter = [];
$users_query = "SELECT DISTINCT from_user_id, from_user_name, from_user_type 
                FROM advance_payments 
                WHERE deleted_at IS NULL";

if ($logged_user_type === 'super_stockiest') {
    // For Super Stockist: Show only payments where they are the receiver
    $escaped_user_id = $db_conn->real_escape_string($logged_user_id);
    $users_query .= " AND to_user_id = '$escaped_user_id' AND to_user_type = 'super_stockiest'";
} elseif ($logged_user_type !== 'company') {
    // For other non-company users
    $escaped_user_id = $db_conn->real_escape_string($logged_user_id);
    $users_query .= " AND (to_user_id = '$escaped_user_id' OR from_user_id = '$escaped_user_id')";
}

$users_query .= " ORDER BY from_user_name ASC";
$users_result = $db_conn->query($users_query);

if ($users_result) {
    while ($user_row = $users_result->fetch_assoc()) {
        $users_for_filter[] = $user_row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <title>Manage Payment Entry | <?php echo htmlspecialchars($business_name ?? 'Femi9 Billing'); ?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <style>
        .card {
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .filter-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-bottom: 20px;
            border-radius: 8px;
            padding: 20px;
        }
        
        .filter-card .form-label {
            color: white;
            font-weight: 500;
        }
        
        .filter-card .form-control, .filter-card .form-select {
            background: rgba(255, 255, 255, 0.9);
            border: none;
        }
        
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stats-card h3 {
            font-size: 28px;
            font-weight: 600;
            margin: 0;
            color: #667eea;
        }
        
        .stats-card p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-partially {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-fully {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .btn-filter {
            background: white;
            color: #667eea;
            border: none;
            font-weight: 500;
        }
        
        .btn-filter:hover {
            background: rgba(255, 255, 255, 0.9);
            color: #764ba2;
        }
        
        .btn-reset {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid white;
        }
        
        .btn-reset:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        
        table.dataTable thead th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 6px;
            border: 1px solid #d1d5db;
        }
        
        .amount-cell {
            font-weight: 600;
            color: #667eea;
        }
        
        .balance-cell {
            font-weight: 600;
            color: #10b981;
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
                                                <td>Manage Payment Entry </td>
                                                <td><a href="add-advance-payment.php" title="Add New Payment">
                                                    <i class="material-icons">add_circle</i>
                                                </a></td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <h3 id="stat_total_payments">0</h3>
                                    <p>Total Payments</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <h3 id="stat_total_amount">₹0</h3>
                                    <p>Total Amount</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <h3 id="stat_total_balance">₹0</h3>
                                    <p>Total Balance</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <h3 id="stat_adjusted_amount">₹0</h3>
                                    <p>Adjusted Amount</p>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Card -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="filter-card">
                                    <form method="GET" action="" id="filterForm">
                                        <div class="row align-items-end">
                                            
                                            <!-- Date From -->
                                            <div class="col-md-2">
                                                <label class="form-label">From Date</label>
                                                <input type="date" name="from_date" id="from_date" class="form-control" 
                                                       value="<?php echo htmlspecialchars($filter_from_date); ?>">
                                            </div>

                                            <!-- Date To -->
                                            <div class="col-md-2">
                                                <label class="form-label">To Date</label>
                                                <input type="date" name="to_date" id="to_date" class="form-control" 
                                                       value="<?php echo htmlspecialchars($filter_to_date); ?>" 
                                                       max="<?php echo date('Y-m-d'); ?>">
                                            </div>

                                            <!-- User Type -->
                                            <div class="col-md-2">
                                                <label class="form-label">Payer Type</label>
                                                <select name="user_type" id="user_type" class="form-select">
                                                    <option value="">All Types</option>
                                                    <option value="super_stockiest" <?php echo $filter_user_type === 'super_stockiest' ? 'selected' : ''; ?>>Super Stockist</option>
                                                    <option value="stockiest" <?php echo $filter_user_type === 'stockiest' ? 'selected' : ''; ?>>Stockist</option>
                                                </select>
                                            </div>

                                            <!-- User Name -->
                                            <div class="col-md-2">
                                                <label class="form-label">Payer Name</label>
                                                <select name="user_id" id="user_id" class="form-select">
                                                    <option value="">All Users</option>
                                                    <?php foreach ($users_for_filter as $user): ?>
                                                        <option value="<?php echo htmlspecialchars($user['from_user_id']); ?>" 
                                                                <?php echo $filter_user_id === $user['from_user_id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($user['from_user_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <!-- Status -->
                                            <div class="col-md-2">
                                                <label class="form-label">Status</label>
                                                <select name="status" id="status" class="form-select">
                                                    <option value="">All Status</option>
                                                    <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="partially_adjusted" <?php echo $filter_status === 'partially_adjusted' ? 'selected' : ''; ?>>Partially Adjusted</option>
                                                    <option value="fully_adjusted" <?php echo $filter_status === 'fully_adjusted' ? 'selected' : ''; ?>>Fully Adjusted</option>
                                                </select>
                                            </div>

                                            <!-- Buttons -->
                                            <div class="col-md-2">
                                                <button type="submit" class="btn btn-filter w-100 mb-2">
                                                    <i class="material-icons" style="vertical-align: middle; font-size: 18px;">filter_list</i>
                                                    Filter
                                                </button>
                                                <a href="manage-advance-payments.php" class="btn btn-reset w-100">
                                                    <i class="material-icons" style="vertical-align: middle; font-size: 18px;">refresh</i>
                                                    Reset
                                                </a>
                                            </div>

                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Data Table -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table id="advancePaymentsTable" class="table table-striped table-hover" style="width:100%">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Date</th>
                                                        <th>Payer Name</th>
                                                        <th>Payer Type</th>
                                                        <th>Category</th>
                                                        <th>Target Amount</th>
                                                        <th>Amount</th>
                                                        <th>Balance</th>
                                                        <th>Adjusted</th>
                                                        <th>Mode</th>
                                                        <th>Reference</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Data loaded via AJAX -->
                                                </tbody>
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
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Advance Payment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalContent">
                    <!-- Content loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Payment Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Advance Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editPaymentForm" method="POST" action="edit-advance-payment-action.php">
                    <div class="modal-body" id="editModalContent">
                        <!-- Content loaded dynamically -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_advance_payment">
                            <i class="material-icons" style="vertical-align: middle;">save</i>
                            Update Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>

    <script>
    $(document).ready(function() {
        
        // Get filter parameters
        const urlParams = new URLSearchParams(window.location.search);
        const fromDate = urlParams.get('from_date') || '<?php echo $filter_from_date; ?>';
        const toDate = urlParams.get('to_date') || '<?php echo $filter_to_date; ?>';
        const userType = urlParams.get('user_type') || '';
        const userId = urlParams.get('user_id') || '';
        const status = urlParams.get('status') || '';

        // Initialize DataTable
        const table = $('#advancePaymentsTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: 'get-advance-payments-data.php',
                type: 'POST',
                data: {
                    from_date: fromDate,
                    to_date: toDate,
                    user_type: userType,
                    user_id: userId,
                    status: status
                },
                dataSrc: function(json) {
                    // Update statistics
                    if (json.stats) {
                        $('#stat_total_payments').text(json.stats.total_payments);
                        $('#stat_total_amount').text('₹' + formatNumber(json.stats.total_amount));
                        $('#stat_total_balance').text('₹' + formatNumber(json.stats.total_balance));
                        $('#stat_adjusted_amount').text('₹' + formatNumber(json.stats.adjusted_amount));
                    }
                    return json.data;
                }
            },
            columns: [
                { data: 'id' },
                { data: 'payment_date' },
                { data: 'from_user_name' },
                { 
                    data: 'from_user_type',
                    render: function(data) {
                        // Fix: Show 'Stockist' instead of 'Stockiest'
                        if (data === 'stockiest') {
                            return 'Stockist';
                        }
                        return data.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    }
                },
                { 
                    data: 'category_name',
                    render: function(data) {
                        return data || '<span class="text-muted">N/A</span>';
                    }
                },
                { 
                    data: 'target_amount',
                    render: function(data) {
                        if (data && data > 0) {
                            return '₹' + formatNumber(data);
                        }
                        return '<span class="text-muted">N/A</span>';
                    }
                },
                { 
                    data: 'amount',
                    className: 'amount-cell',
                    render: function(data) {
                        return '₹' + formatNumber(data);
                    }
                },
                { 
                    data: 'balance_amount',
                    className: 'balance-cell',
                    render: function(data) {
                        return '₹' + formatNumber(data);
                    }
                },
                { 
                    data: 'adjusted_amount',
                    render: function(data) {
                        return '₹' + formatNumber(data);
                    }
                },
                { data: 'payment_mode' },
                { data: 'reference_number' },
                { 
                    data: 'status',
                    render: function(data) {
                        let badgeClass = 'status-active';
                        let displayText = data;
                        
                        if (data === 'partially_adjusted') {
                            badgeClass = 'status-partially';
                            displayText = 'Partially Adjusted';
                        } else if (data === 'fully_adjusted') {
                            badgeClass = 'status-fully';
                            displayText = 'Fully Adjusted';
                        } else if (data === 'active') {
                            displayText = 'Active';
                        }
                        
                        return '<span class="status-badge ' + badgeClass + '">' + displayText + '</span>';
                    }
                },
                { 
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        return '<button class="btn btn-sm btn-primary view-btn me-1" data-id="' + row.id + '" title="View Details">' +
                               '<i class="material-icons" style="font-size: 18px;">visibility</i>' +
                               '</button>' +
                               '<button class="btn btn-sm btn-warning edit-btn" data-id="' + row.id + '" title="Edit Payment">' +
                               '<i class="material-icons" style="font-size: 18px;">edit</i>' +
                               '</button>';
                    }
                }
            ],
            order: [[1, 'desc']], // Order by date descending
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                 '<"row"<"col-sm-12"B>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            buttons: [
                {
                    extend: 'excel',
                    text: '<i class="material-icons" style="vertical-align: middle;">download</i> Export Excel',
                    className: 'btn btn-success',
                    title: 'Advance_Payments_' + fromDate + '_to_' + toDate,
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="material-icons" style="vertical-align: middle;">print</i> Print',
                    className: 'btn btn-info'
                }
            ]
        });

        // View details button click
        $('#advancePaymentsTable tbody').on('click', '.view-btn', function() {
            const id = $(this).data('id');
            loadPaymentDetails(id);
        });

        // Edit button click
        $('#advancePaymentsTable tbody').on('click', '.edit-btn', function() {
            const id = $(this).data('id');
            loadEditForm(id);
        });

        // Function to load payment details
        function loadPaymentDetails(id) {
            $.ajax({
                url: 'get-advance-payment-details.php',
                method: 'POST',
                data: { id: id },
                success: function(response) {
                    $('#modalContent').html(response);
                    $('#viewModal').modal('show');
                },
                error: function() {
                    alert('Error loading payment details');
                }
            });
        }

        // Function to load edit form
        function loadEditForm(id) {
            $.ajax({
                url: 'get-advance-payment-edit-form.php',
                method: 'POST',
                data: { id: id },
                success: function(response) {
                    $('#editModalContent').html(response);
                    $('#editModal').modal('show');
                },
                error: function() {
                    alert('Error loading edit form');
                }
            });
        }

        // Handle edit form submission
        $('#editPaymentForm').on('submit', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to update this payment?')) {
                return false;
            }

            $.ajax({
                url: 'edit-advance-payment-action.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#editModal').modal('hide');
                        alert(response.message);
                        table.ajax.reload(); // Reload DataTable
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error updating payment. Please try again.');
                }
            });
        });

        // Format number with commas — the API sends amounts already formatted
        // as Indian-locale comma strings (e.g. "6,45,15,987.00"), so strip
        // commas before parsing or parseFloat stops at the first one and
        // silently truncates the value (e.g. down to just "6").
        function formatNumber(num) {
            if (num === null || num === undefined || num === '') return '0.00';
            if (typeof num === 'string') num = num.replace(/,/g, '');
            num = parseFloat(num);
            if (isNaN(num)) return '0.00';
            return num.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

    });
    </script>

</body>
</html>