<?php
/**
 * Customer Remapping - Step 2
 * Display customers for remapping and assign to new user
 * 
 * Security improvements:
 * - SQL injection prevention with prepared statements
 * - CSRF token validation
 * - Input validation and sanitization
 * - XSS prevention with htmlspecialchars
 * - Whitelist validation for user types
 * 
 * @author Senior PHP Developer
 * @version 2.0
 */

declare(strict_types=1);

// Session and security checks
require_once "checksession.php";

// Error reporting configuration
// error_reporting(E_ALL);
// ini_set('display_errors', '1'); // Disable in production

date_default_timezone_set("Asia/Kolkata");

/**
 * Validate CSRF token
 */
function validate_csrf_token(): bool
{
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/**
 * Escape output helper function
 */
function escape_html(?string $string): string
{
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Validate user type against whitelist
 */
function validate_user_type(?string $type): bool
{
    $valid_types = ['company', 'candf', 'super_stockiest', 'stockiest', 'super_distributor', 'distributor'];
    return $type && in_array($type, $valid_types, true);
}

/**
 * Get table name and label for user type
 */
function get_user_type_info(string $userType): array
{
    $typeMap = [
        'candf' => ['table' => 'c_and_f', 'label' => 'C & F'],
        'super_stockiest' => ['table' => 'super_stockiest', 'label' => 'Super Stockist'],
        'stockiest' => ['table' => 'stockiest', 'label' => 'Stockist'],
        'super_distributor' => ['table' => 'super_distributor', 'label' => 'Super Distributor'],
        'distributor' => ['table' => 'distributor', 'label' => 'Distributor'],
        'company' => ['table' => '', 'label' => 'Company']
    ];
    
    return $typeMap[$userType] ?? ['table' => '', 'label' => ''];
}

/**
 * Fetch user details securely using prepared statements
 */
function fetch_user_details(mysqli $db_conn, string $tableName, string $userId): ?array
{
    if (empty($tableName)) {
        return null;
    }
    
    // Validate table name against whitelist to prevent SQL injection
    $allowedTables = ['c_and_f', 'super_stockiest', 'stockiest', 'super_distributor', 'distributor'];
    if (!in_array($tableName, $allowedTables, true)) {
        return null;
    }
    
    // Check which mobile column exists
    $mobileColumn = 'mobile';
    $checkColumn = $db_conn->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'mobile_number'");
    if ($checkColumn && $checkColumn->num_rows > 0) {
        $mobileColumn = 'mobile_number';
    }
    
    $query = "SELECT useridtext, name, {$mobileColumn} as mobile_number FROM `{$tableName}` WHERE temp_id = ? LIMIT 1";
    $stmt = $db_conn->prepare($query);
    
    if (!$stmt) {
        error_log("MySQL prepare error: " . $db_conn->error);
        return null;
    }
    
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data;
}

/**
 * Fetch customers for remapping
 */
function fetch_customers(mysqli $db_conn, string $userType, string $userId): array
{
    // Note: Using actual column names from customers table
    // mobile (not mobile_number), user_type (not onboard_userTYPE), user_id (not onboard_userID)
    $query = "SELECT id, name, mobile, email, address FROM customers 
              WHERE user_type = ? AND user_id = ? 
              ORDER BY name ASC";
    
    $stmt = $db_conn->prepare($query);
    
    if (!$stmt) {
        error_log("MySQL prepare error: " . $db_conn->error);
        return [];
    }
    
    $stmt->bind_param("ss", $userType, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    
    $stmt->close();
    return $customers;
}

// Initialize variables
$get_from_user_type = null;
$get_from_user_id = null;
$error_message = '';

// Validate input from POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validate_csrf_token()) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $get_from_user_type = $_POST['ssid'] ?? null;
        $get_from_user_id = $_POST['user_id'] ?? null;
        
        // Validate inputs
        if (!validate_user_type($get_from_user_type) || empty($get_from_user_id)) {
            $error_message = 'Invalid parameters provided.';
        }
    }
}

// Redirect if validation fails
if ($error_message || !$get_from_user_type || !$get_from_user_id) {
    header("Location: remapping_customer.php");
    exit;
}

// Fetch user details if not company
$user_info = null;
$user_type_info = get_user_type_info($get_from_user_type);

if ($get_from_user_type !== 'company' && !empty($user_type_info['table'])) {
    $user_info = fetch_user_details($db_conn, $user_type_info['table'], $get_from_user_id);
}

// Fetch customers
$customers = fetch_customers($db_conn, $get_from_user_type, $get_from_user_id);

// Generate new CSRF token for the form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$business_name = escape_html($business_name ?? 'Billing App');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Customer Remapping System">

    <!-- Title -->
    <title>Re-mapping : Customers : <?php echo $business_name; ?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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

    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include "logo.php"; ?>
            <?php include "femi_menu.php"; ?>
        </div>
        <div class="app-container">
           
            <?php include "app-header.php"; ?>
            
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <a href="remapping_customer.php" id="linkbackvl" class="btn btn-link">
                                        <i class="material-icons">arrow_back</i> Go Back
                                    </a>
                                    <h2>
                                        <table class="headertble margintop10">
                                            <tr>
                                                <td>Re-mapping : <i><span style="color:green;">Customers</span></i></td>
                                            </tr>
                                        </table>
                                    </h2>
                                    
                                    <?php if ($get_from_user_type !== 'company' && $user_info): ?>
                                        <p>
                                            Onboarded By: <strong><?php echo escape_html($user_type_info['label']); ?></strong> | 
                                            <strong style="color:blue;"><?php echo escape_html($user_info['useridtext']); ?></strong> | 
                                            <strong><?php echo escape_html(strtoupper($user_info['name'])); ?></strong> | 
                                            <strong><?php echo escape_html($user_info['mobile_number']); ?></strong>
                                        </p>
                                    <?php else: ?>
                                        <p>Onboarded By: <strong>Company</strong></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <?php if (count($customers) > 0): ?>
                                            <form action="remapping_action.php" method="post" id="remappingForm">
                                                <!-- CSRF Token -->
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="from_usertype" value="<?php echo escape_html($get_from_user_type); ?>">
                                                <input type="hidden" name="from_userid" value="<?php echo escape_html($get_from_user_id); ?>">
                                                
                                                <div class="example-container">
                                                    <div class="example-content">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">
                                                                <strong>Select Customers to Remap:</strong> 
                                                                (<?php echo count($customers); ?> customers found)
                                                            </label>
                                                            <div class="mb-2">
                                                                <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll()">
                                                                    Select All
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-secondary" onclick="deselectAll()">
                                                                    Deselect All
                                                                </button>
                                                            </div>
                                                        </div>
                                                        
                                                        <ul class="list-group mb-3" style="max-height: 400px; overflow-y: auto;">
                                                            <?php foreach ($customers as $customer): ?>
                                                                <li class="list-group-item">
                                                                    <input 
                                                                        class="form-check-input me-2 customer-checkbox" 
                                                                        name="customerid[]" 
                                                                        type="checkbox" 
                                                                        value="<?php echo escape_html((string)$customer['id']); ?>" 
                                                                        id="customer_<?php echo escape_html((string)$customer['id']); ?>"
                                                                        aria-label="Select customer">
                                                                    <label for="customer_<?php echo escape_html((string)$customer['id']); ?>">
                                                                        <strong><?php echo escape_html(strtoupper($customer['name'])); ?></strong> - 
                                                                        <?php echo escape_html($customer['mobile']); ?>
                                                                        <?php if (!empty($customer['email'])): ?>
                                                                            | <?php echo escape_html($customer['email']); ?>
                                                                        <?php endif; ?>
                                                                    </label>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>

                                                        <div class="mb-3">
                                                            <label class="form-label" for="toUserType">Assign to User Type *</label>
                                                            <select 
                                                                required 
                                                                name="to_usertype" 
                                                                id="toUserType"
                                                                class="form-control" 
                                                                onchange="loadTargetUsers(this.value)">
                                                                <option value="" hidden>Select User Type</option>
                                                                <option value="company">Company</option>
                                                                <option value="candf">C & F</option>
                                                                <option value="super_stockiest">Super Stockist</option>
                                                                <option value="stockiest">Stockist</option>
                                                                <option value="super_distributor">Super Distributor</option>
                                                                <option value="distributor">Distributor</option>
                                                            </select>
                                                        </div>

                                                        <div id="targetUserContainer" class="mb-3">
                                                            <label class="form-label" for="toUserId">Assign to User *</label>
                                                            <select 
                                                                class="form-control" 
                                                                id="toUserId" 
                                                                name="to_userid" 
                                                                required 
                                                                disabled>
                                                                <option value="" hidden>First select user type</option>
                                                            </select>
                                                        </div>

                                                        <button type="submit" name="REMAPPING_CUSTOMERS" class="btn btn-primary">
                                                            <i class="material-icons">check</i> Submit Remapping
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <div class="text-center p-5">
                                                <img src="../../assets/images/no-records.jpg" alt="No records found" class="img-fluid" style="max-width: 300px;">
                                                <p class="mt-3 text-muted">No customers found for this user.</p>
                                            </div>
                                        <?php endif; ?>
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
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    
    <script>
    /**
     * Select all customers
     */
    function selectAll() {
        document.querySelectorAll('.customer-checkbox').forEach(checkbox => {
            checkbox.checked = true;
        });
    }
    
    /**
     * Deselect all customers
     */
    function deselectAll() {
        document.querySelectorAll('.customer-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
    }
    
    /**
     * Load target users based on selected user type
     */
    function loadTargetUsers(userType) {
        const userSelect = document.getElementById('toUserId');
        
        if (!userType) {
            userSelect.innerHTML = '<option value="" hidden>First select user type</option>';
            userSelect.disabled = true;
            return;
        }
        
        // Show loading state
        userSelect.innerHTML = '<option value="" hidden>Loading...</option>';
        userSelect.disabled = true;
        
        // Use Fetch API
        fetch('load_customer_users.php?type=' + encodeURIComponent(userType), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.users && data.users.length > 0) {
                let options = '<option value="" hidden>Select User (Assign to)</option>';
                data.users.forEach(user => {
                    const userId = escapeHtml(user.id);
                    const userName = escapeHtml(user.name);
                    const userMobile = escapeHtml(user.mobile);
                    const userIdText = escapeHtml(user.userid_text);
                    
                    options += `<option value="${userId}">${userIdText} - ${userName} (${userMobile})</option>`;
                });
                userSelect.innerHTML = options;
                userSelect.disabled = false;
            } else {
                userSelect.innerHTML = '<option value="" hidden>No users found</option>';
                userSelect.disabled = true;
            }
        })
        .catch(error => {
            console.error('Error loading users:', error);
            userSelect.innerHTML = '<option value="" hidden>Error loading users</option>';
            userSelect.disabled = true;
        });
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Form validation before submission
     */
    document.getElementById('remappingForm')?.addEventListener('submit', function(e) {
        const selectedCustomers = document.querySelectorAll('.customer-checkbox:checked');
        const toUserType = document.getElementById('toUserType').value;
        const toUserId = document.getElementById('toUserId').value;
        
        if (selectedCustomers.length === 0) {
            e.preventDefault();
            alert('Please select at least one customer to remap.');
            return false;
        }
        
        if (!toUserType || !toUserId) {
            e.preventDefault();
            alert('Please select the target user type and user.');
            return false;
        }
        
        // Confirm action
        const confirmMsg = `You are about to remap ${selectedCustomers.length} customer(s). Are you sure?`;
        if (!confirm(confirmMsg)) {
            e.preventDefault();
            return false;
        }
    });
    </script>
</body>

</html>