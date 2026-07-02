<?php
/**
 * Add Advance Payment Entry
 * Femi9 Billing Application
 * 
 * Description: Form to record advance payments received from users in the hierarchy
 * Security: Prepared statements, XSS protection, CSRF validation, input sanitization
 * 
 * @author Femi9 Development Team
 * @version 2.0
 * @date 2025-12-29
 */

include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");

date_default_timezone_set("Asia/Kolkata");
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get logged-in user details (receiver of payment)
$logged_user_id = $_SESSION['LOGIN_USER_ID'] ?? ''; // temp_id
$logged_user_type = $_SESSION['LOGIN_USER_TYPE'] ?? ''; // user type
$logged_user_name = $_SESSION['LOGIN_USER'] ?? ''; // username

// Get user's actual name from database if needed
if (!empty($logged_user_id) && !empty($logged_user_type)) {
    // Map user type to table name
    $table_map = [
        'company' => 'admin_log',
        'super_stockiest' => 'super_stockiest',
        'stockiest' => 'stockiest',
        'distributor' => 'distributor',
        'super_distributor' => 'super_distributor',
        'c_and_f' => 'c_and_f'
    ];
    
    $table_name = $table_map[$logged_user_type] ?? null;
    
    if ($table_name && $table_name !== 'admin_log') {
        $stmt_name = $db_conn->prepare("SELECT name FROM $table_name WHERE temp_id = ? LIMIT 1");
        if ($stmt_name) {
            $stmt_name->bind_param("s", $logged_user_id);
            $stmt_name->execute();
            $result_name = $stmt_name->get_result();
            if ($row_name = $result_name->fetch_assoc()) {
                $logged_user_name = $row_name['name'];
            }
            $stmt_name->close();
        }
    }
}

// Get company profile for the logged-in user
$company_profiles = [];
$stmt_company = $db_conn->prepare("SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC");
if ($stmt_company) {
    $stmt_company->execute();
    $result_company = $stmt_company->get_result();
    while ($row = $result_company->fetch_assoc()) {
        $company_profiles[] = $row;
    }
    $stmt_company->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <title>Add Payment Entry | <?php echo htmlspecialchars($business_name ?? 'Femi9 Billing'); ?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <style>
        .card {
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #d1d5db;
            padding: 10px 12px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .alert {
            border-radius: 6px;
            border: none;
            padding: 16px;
        }
        .required-field::after {
            content: " *";
            color: #ef4444;
        }
        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .user-info-badge {
            display: inline-block;
            background: #f3f4f6;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            color: #374151;
            margin-bottom: 20px;
        }
        #user_loading {
            display: none;
            color: #3b82f6;
            font-size: 14px;
            margin-top: 8px;
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
                                                <td>Add Payment Entry</td>
                                                <td><a href="manage-advance-payments" title="View Advance Payments">&#9776;</a></td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <!-- Main Content -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        
                                        <!-- Success/Error Messages -->
                                        <?php if (isset($_GET['success'])): ?>
                                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                                <i class="material-icons-outlined" style="vertical-align: middle;">check_circle</i>
                                                <strong>Success!</strong> Advance payment recorded successfully.
                                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (isset($_GET['error'])): ?>
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                <i class="material-icons-outlined" style="vertical-align: middle;">error</i>
                                                <strong>Error!</strong> 
                                                <?php 
                                                    $error_msg = htmlspecialchars($_GET['error'] ?? 'Failed to record payment.');
                                                    echo $error_msg;
                                                ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Info Box -->
                                        <div class="info-box">
                                            <i class="material-icons-outlined" style="vertical-align: middle; color: #3b82f6;">info</i>
                                            <strong>Note:</strong> Record advance payments received from your hierarchy. 
                                            Select the user who paid and enter payment details.
                                        </div>

                                        <!-- Receiver Info Badge -->
                                        <div class="user-info-badge">
                                            <i class="material-icons-outlined" style="vertical-align: middle; font-size: 18px;">account_circle</i>
                                            <strong>Receiving As:</strong> 
                                            <?php echo htmlspecialchars($logged_user_name); ?> 
                                            (<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $logged_user_type))); ?>) 
                                        </div>

                                        <!-- Payment Entry Form -->
                                        <form action="advance-payment-action.php" method="POST" id="advancePaymentForm" onsubmit="return validateForm();">
                                            
                                            <!-- CSRF Token -->
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            
                                            <!-- Receiver Info (Hidden - Auto-filled from session) -->
                                            <input type="hidden" name="to_user_id" value="<?php echo htmlspecialchars($logged_user_id); ?>">
                                            <input type="hidden" name="to_user_type" value="<?php echo htmlspecialchars($logged_user_type); ?>">
                                            <input type="hidden" name="to_user_name" value="<?php echo htmlspecialchars($logged_user_name); ?>">

                                            <div class="row">
                                                
                                                <!-- Company Profile -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="company_id" class="form-label required-field">Company Profile</label>
                                                    <select name="company_id" id="company_id" class="form-select" required>
                                                        <option value="" hidden>Select Company</option>
                                                        <?php foreach ($company_profiles as $company): ?>
                                                            <option value="<?php echo $company['id']; ?>">
                                                                <?php echo htmlspecialchars($company['gname']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <!-- User Type (Payer) -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="from_user_type" class="form-label required-field">Payer Type</label>
                                                    <select name="from_user_type" id="from_user_type" class="form-select" required onchange="loadUsers()">
                                                        <option value="" hidden>Select User Type</option>
                                                        <option value="super_stockiest">Super Stockist</option>
                                                        <option value="stockiest">Stockist</option>
                                                    </select>
                                                    <small class="text-muted">Select the type of user who made the payment</small>
                                                </div>

                                                <!-- User Selection (Payer) - Dynamic -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="from_user_id" class="form-label required-field">Payer Name</label>
                                                    <select name="from_user_id" id="from_user_id" class="form-select" required disabled>
                                                        <option value="" hidden>First select payer type</option>
                                                    </select>
                                                    <div id="user_loading">
                                                        <i class="material-icons-outlined rotating" style="vertical-align: middle;">refresh</i>
                                                        Loading users...
                                                    </div>
                                                    <input type="hidden" name="from_user_name" id="from_user_name" value="">
                                                </div>

                                                <!-- Payment Date -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="payment_date" class="form-label required-field">Payment Date</label>
                                                    <input type="date" name="payment_date" id="payment_date" class="form-control" 
                                                           value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                                    <small class="text-muted">Date when payment was received</small>
                                                </div>

                                                <!-- Amount -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="amount" class="form-label required-field">Amount (₹)</label>
                                                    <input type="number" name="amount" id="amount" class="form-control" 
                                                           placeholder="Enter amount" min="1" step="0.01" required>
                                                    <small class="text-muted">Enter advance payment amount</small>
                                                </div>

                                                <!-- Payment Mode -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="payment_mode" class="form-label required-field">Payment Mode</label>
                                                    <select name="payment_mode" id="payment_mode" class="form-select" required>
                                                        <option value="" hidden>Select Payment Mode</option>
                                                        <option value="Cash">Cash</option>
                                                        <option value="Bank Transfer">Bank Transfer</option>
                                                        <option value="Cheque">Cheque</option>
                                                        <option value="UPI">UPI</option>
                                                        <option value="NEFT">NEFT</option>
                                                        <option value="RTGS">RTGS</option>
                                                        <option value="IMPS">IMPS</option>
                                                        <option value="Demand Draft">Demand Draft</option>
                                                        <option value="Other">Other</option>
                                                    </select>
                                                </div>

                                                <!-- Reference Number -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="reference_number" class="form-label">Reference Number</label>
                                                    <input type="text" name="reference_number" id="reference_number" 
                                                           class="form-control" placeholder="UTR/Transaction/Cheque number" maxlength="255">
                                                    <small class="text-muted">Transaction reference (optional)</small>
                                                </div>

                                                <!-- Bank Name -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="bank_name" class="form-label">Bank Name</label>
                                                    <input type="text" name="bank_name" id="bank_name" 
                                                           class="form-control" placeholder="Bank name (if applicable)" maxlength="255">
                                                    <small class="text-muted">Bank name (optional)</small>
                                                </div>

                                                <!-- Remarks -->
                                                <div class="col-md-12 mb-3">
                                                    <label for="remarks" class="form-label">Remarks</label>
                                                    <textarea name="remarks" id="remarks" class="form-control" 
                                                              rows="3" placeholder="Additional notes about this payment (optional)"></textarea>
                                                </div>

                                            </div>

                                            <!-- Submit Button -->
                                            <div class="mt-4">
                                                <button type="submit" name="add_advance_payment" class="btn btn-primary">
                                                    <i class="material-icons" style="vertical-align: middle;">add_circle</i>
                                                    Record Payment
                                                </button>
                                                <a href="manage-advance-payments" class="btn btn-secondary ms-2">
                                                    <i class="material-icons" style="vertical-align: middle;">cancel</i>
                                                    Cancel
                                                </a>
                                            </div>

                                        </form>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>

    <script>
    /**
     * Load users dynamically based on selected user type and company
     */
    function loadUsers() {
        const userType = document.getElementById('from_user_type').value;
        const companyId = document.getElementById('company_id').value;
        const userSelect = document.getElementById('from_user_id');
        const loading = document.getElementById('user_loading');

        if (!userType) {
            userSelect.disabled = true;
            userSelect.innerHTML = '<option value="" hidden>First select payer type</option>';
            return;
        }

        if (!companyId) {
            alert('Please select a company first');
            document.getElementById('from_user_type').value = '';
            return;
        }

        // Show loading
        loading.style.display = 'block';
        userSelect.disabled = true;
        userSelect.innerHTML = '<option value="" hidden>Loading...</option>';

        // AJAX call to fetch users
        $.ajax({
            url: 'get-users-by-type.php',
            method: 'POST',
            data: {
                user_type: userType,
                company_id: companyId
            },
            dataType: 'json',
            success: function(response) {
                loading.style.display = 'none';
                
                if (response.success && response.users.length > 0) {
                    let options = '<option value="" hidden>Select User</option>';
                    response.users.forEach(function(user) {
                        let txt = user.name.toUpperCase();
                        if (user.district_name) txt += ' (' + user.district_name.toUpperCase() + ')';
                        if (user.mobile_number) txt += ', ' + user.mobile_number;
                        
                        options += `<option value="${user.temp_id}" data-name="${user.name}">${txt}</option>`;
                    });
                    userSelect.innerHTML = options;
                    userSelect.disabled = false;
                } else {
                    userSelect.innerHTML = '<option value="" hidden>No users found</option>';
                    userSelect.disabled = true;
                }
            },
            error: function() {
                loading.style.display = 'none';
                userSelect.innerHTML = '<option value="" hidden>Error loading users</option>';
                alert('Error loading users. Please try again.');
            }
        });
    }

    /**
     * Update hidden from_user_name field when user is selected
     */
    document.getElementById('from_user_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const userName = selectedOption.getAttribute('data-name');
        document.getElementById('from_user_name').value = userName || '';
    });

    /**
     * Reload users when company changes
     */
    document.getElementById('company_id').addEventListener('change', function() {
        const userType = document.getElementById('from_user_type').value;
        if (userType) {
            loadUsers();
        }
    });

    /**
     * Form validation before submit
     */
    function validateForm() {
        const amount = parseFloat(document.getElementById('amount').value);
        
        if (amount <= 0) {
            alert('Please enter a valid amount greater than 0');
            return false;
        }

        if (amount > 10000000) { // 1 Crore limit
            if (!confirm('Amount is more than ₹1 Crore. Are you sure?')) {
                return false;
            }
        }

        // Confirm submission
        return confirm('Confirm adding this advance payment entry?');
    }

    /**
     * Rotating animation for loading icon
     */
    const style = document.createElement('style');
    style.textContent = `
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .rotating {
            animation: rotate 1s linear infinite;
        }
    `;
    document.head.appendChild(style);
    </script>

</body>
</html>