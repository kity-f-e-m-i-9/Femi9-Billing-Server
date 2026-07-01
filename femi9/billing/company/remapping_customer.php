<?php
/**
 * Customer Remapping - Step 1
 * Select source user type and user for remapping customers
 * 
 * Security improvements:
 * - CSRF token implementation
 * - Input validation and sanitization
 * - XSS prevention with htmlspecialchars
 * - Session validation
 * 
 * @author Senior PHP Developer
 * @version 2.0
 */

declare(strict_types=1);

// Session and security checks
require_once "checksession.php";

// Error reporting should be enabled in development, logged in production
// error_reporting(E_ALL);
// ini_set('display_errors', '1'); // Disable in production

date_default_timezone_set("Asia/Kolkata");

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$current_date = date("Y-m-d");
$success_message = '';

// Handle success message with XSS protection
if (isset($_GET['mappingsuccess']) && $_GET['mappingsuccess'] === '1') {
    $success_message = '<div class="alert alert-success" role="alert">Customers re-mapping completed successfully.</div>';
}

// Whitelist of valid user types to prevent injection
$valid_user_types = [
    'company' => 'Company',
    'candf' => 'C & F',
    'super_stockiest' => 'Super Stockist',
    'stockiest' => 'Stockist',
    'super_distributor' => 'Super Distributor',
    'distributor' => 'Distributor'
];

// Escape output helper function
function escape_html(?string $string): string
{
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Ensure $business_name is defined and escaped
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

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
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
                                    
                                    <?php echo $success_message; ?>
                                    
                                    <h2>
                                        <table class="headertble">
                                            <tr>
                                                <td>Re-mapping : <i><span style="color:green;">Customers</span></i></td>
                                            </tr>
                                        </table>
                                    </h2>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <form action="remapping_customer2.php" method="post" id="remappingForm">
                                            <!-- CSRF Token -->
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            
                                            <div class="example-container">
                                                <div class="example-content">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label" for="userType">Onboarded by *</label>
                                                        <select 
                                                            required 
                                                            name="ssid" 
                                                            id="userType"
                                                            class="form-control" 
                                                            onchange="loadUsers(this.value)"
                                                            aria-required="true">
                                                            <option value="" hidden>Select User Type</option>
                                                            <?php foreach ($valid_user_types as $key => $label): ?>
                                                                <option value="<?php echo escape_html($key); ?>">
                                                                    <?php echo escape_html($label); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div id="userSelectContainer" class="mb-3">
                                                        <label class="form-label" for="userId">Select User *</label>
                                                        <select class="form-control" id="userId" name="user_id" required disabled>
                                                            <option value="" hidden>First select user type</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="material-icons">navigate_next</i> Next
                                                    </button>
                                                </div>
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
     * Load users based on selected user type
     * Implements proper error handling and security
     */
    function loadUsers(userType) {
        const userSelect = document.getElementById('userId');
        
        if (!userType) {
            userSelect.innerHTML = '<option value="" hidden>First select user type</option>';
            userSelect.disabled = true;
            return;
        }
        
        // Show loading state
        userSelect.innerHTML = '<option value="" hidden>Loading...</option>';
        userSelect.disabled = true;
        
        // Use Fetch API (modern alternative to XMLHttpRequest)
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
                let options = '<option value="" hidden>Select User</option>';
                data.users.forEach(user => {
                    // Escape HTML to prevent XSS
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
    
    // Form validation
    document.getElementById('remappingForm').addEventListener('submit', function(e) {
        const userType = document.getElementById('userType').value;
        const userId = document.getElementById('userId').value;
        
        if (!userType || !userId) {
            e.preventDefault();
            alert('Please select both user type and user');
            return false;
        }
    });
    </script>
</body>

</html>