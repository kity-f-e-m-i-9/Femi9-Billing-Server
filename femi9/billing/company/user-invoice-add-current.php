<?php
/**
 * Invoice Creation & Management - Enhanced with Advance Payment
 * Femi9 Billing Application
 * 
 * Features:
 * - Mandatory advance payment validation for Super Stockist & Stockist
 * - Real-time balance checking via AJAX
 * - Modern card-based UI with responsive design
 * - Traditional flow for Distributor & Super Distributor
 * - Stock validation and management
 * - CSRF protection
 * 
 * @author Femi9 Development Team
 * @version 3.0 - Advance Payment Integration
 * @date 2026-01-01
 */

include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");
require_once("advance-payment-functions.php");

date_default_timezone_set("Asia/Kolkata");
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$get_action = $_REQUEST['action'] ?? 'add';
$_SESSION['ACTIONEDIT'] = $get_action;

$getinvuser = $_REQUEST['invuser'] ?? '';

// Map user types to display information
$userTypeConfig = [
    'candf' => [
        'title' => 'Invoice - C&F',
        'label' => 'C&F Name',
        'table' => 'c_and_f',
        'prefix' => 'CMPCF',
        'mandatory_advance' => false
    ],
    'super_stockiest' => [
        'title' => 'Invoice - Super Stockist',
        'label' => 'Super Stockist Name',
        'table' => 'super_stockiest',
        'prefix' => 'CMPSS',
        'mandatory_advance' => true
    ],
    'stockiest' => [
        'title' => 'Invoice - Stockist',
        'label' => 'Stockist Name',
        'table' => 'stockiest',
        'prefix' => 'CMPST',
        'mandatory_advance' => true
    ],
    'super_distributor' => [
        'title' => 'Invoice - Super Distributor',
        'label' => 'Super Distributor Name',
        'table' => 'super_distributor',
        'prefix' => 'CMPSD',
        'mandatory_advance' => false
    ],
    'distributor' => [
        'title' => 'Invoice - Distributor',
        'label' => 'Distributor Name',
        'table' => 'distributor',
        'prefix' => 'CMPDST',
        'mandatory_advance' => false
    ],
    'outlet' => [
        'title' => 'Invoice - Outlet',
        'label' => 'Outlet Name',
        'table' => 'outlet',
        'prefix' => 'CMPOT',
        'mandatory_advance' => false
    ]
];

$config = $userTypeConfig[$getinvuser] ?? null;
if (!$config) {
    die("Invalid user type");
}

$displaytitle = $config['title'];
$lablenamedisplay = $config['label'];
$tablename = $config['table'];
$invidprefix = $config['prefix'];
$is_advance_mandatory = $config['mandatory_advance'];

// Get Godown Details
$godown_id = intval($_REQUEST['gid'] ?? 0);
$result_Godown = null;
if ($godown_id > 0 && is_godown_allowed($db_conn, $godown_id)) {
    $select_Godowndetails = $db_conn->prepare("SELECT * FROM company_godown WHERE id = ?");
    $select_Godowndetails->bind_param("i", $godown_id);
    $select_Godowndetails->execute();
    $result_Godown = $select_Godowndetails->get_result()->fetch_assoc();
    $select_Godowndetails->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <title><?php echo htmlspecialchars($displaytitle); ?> : <?php echo htmlspecialchars($business_name ?? 'Femi9 Billing'); ?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    
    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <?php include('invoice-styles.php'); ?>
    <?php include("validate-scripts.php"); ?>
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
                        <div class="row mb-4">
                            <div class="col">
                                <h1>
                                    <table class="headertble">
                                        <tr>
                                            <td>
                                                <?php if($get_action == "edit") { echo "Update > "; } ?>
                                                <?php echo htmlspecialchars($displaytitle); ?>
                                            </td>
                                            <td>
                                                <a href="user-manage-invoice?invuser=<?php echo urlencode($getinvuser); ?>" 
                                                   title="View Invoices">&#9776;</a>
                                            </td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>

                        <!-- Alert Messages -->
                        <?php include('invoice-alerts.php'); ?>

                        <!-- Main Content -->
                        <?php
                        // Check if editing existing invoice
                        if(isset($_REQUEST['InvoiceID'])) {
                            // Edit mode - will be implemented in next file
                            echo '<div class="alert alert-info">Edit mode - Implementation in progress</div>';
                        } else {
                            // New Invoice Creation
                            ?>
                            <div class="row">
                                <div class="col-12">
                                    <div class="invoice-card">
                                        <form action="user-invoice-action.php" method="POST" id="invoiceForm">
                                            <!-- CSRF Token -->
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="invuser" value="<?php echo htmlspecialchars($getinvuser); ?>">
                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($_SESSION['LOGIN_USER'] ?? ''); ?>">
                                            <input type="hidden" name="usertype" value="<?php echo htmlspecialchars($_SESSION['LOGIN_USER_TYPE'] ?? ''); ?>">
                                            
                                            <?php
                                            // Generate random invoice ID
                                            $inv_randum_number = bin2hex(random_bytes(5));
                                            $randum_number = bin2hex(random_bytes(2));
                                            $temp_date = date("dmy");
                                            $temp_time = date("gis");
                                            $inv_id = $inv_randum_number . $invidprefix . $temp_date . $temp_time;
                                            ?>
                                            <input type="hidden" name="inv_id" value="<?php echo $inv_id; ?>">
                                            <input type="hidden" name="randum_number" value="<?php echo $randum_number; ?>">
                                            
                                            <!-- Step 1: Company Profile -->
                                            <div class="invoice-step">
                                                <div class="step-header">
                                                    <div class="step-number">1</div>
                                                    <div class="step-title">Select Company Profile</div>
                                                </div>
                                                <div class="step-content">
                                                    <label class="form-label required">Company Profile</label>
                                                    <select name="godownid" id="godownSelect" class="form-select" required onchange="checkOpeningStock(this.value)">
                                                        <option value="">Select Company Profile</option>
                                                        <?php
                                                        $select_Godown = "SELECT * FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC";
                                                        $fetch_Godown = mysqli_query($db_conn, $select_Godown);
                                                        while ($row_Godown = mysqli_fetch_array($fetch_Godown)) {
                                                            $selected = ($godown_id == $row_Godown['id']) ? 'selected' : '';
                                                            echo '<option value="' . $row_Godown['id'] . '" ' . $selected . '>' . htmlspecialchars($row_Godown['gname']) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                    <div id="opstock" class="mt-2"></div>
                                                </div>
                                            </div>

                                            <!-- Step 2: Customer Selection -->
                                            <div class="invoice-step">
                                                <div class="step-header">
                                                    <div class="step-number">2</div>
                                                    <div class="step-title">Select Customer</div>
                                                </div>
                                                <div class="step-content">
                                                    <label class="form-label required"><?php echo htmlspecialchars($lablenamedisplay); ?></label>
                                                    <select name="customer_id" id="customerSelect" class="form-select js-states" required>
                                                        <option value="">Select Customer</option>
                                                        <?php
                                                        if ($getinvuser == "candf") {
                                                            $selectCusList = "SELECT * FROM " . $tablename . " WHERE account_status='active' ORDER BY name ASC";
                                                        } else {
                                                            $selectCusList = "SELECT * FROM " . $tablename . " WHERE onboard_userTYPE='$onboard_userTYPE' AND account_status='active' ORDER BY name ASC";
                                                        }
                                                        $fetch_Customers_list = mysqli_query($db_conn, $selectCusList);
                                                        while ($result_Customers_list = mysqli_fetch_array($fetch_Customers_list)) {
                                                            $user_districtID = $result_Customers_list['district_id'];
                                                            
                                                            // Get district name
                                                            $select_User_districtName = "SELECT * FROM district WHERE id='$user_districtID'";
                                                            $fetch_user_districtName = mysqli_query($db_conn, $select_User_districtName);
                                                            $result_user_districtName = mysqli_fetch_array($fetch_user_districtName);
                                                            $user_districtName = $result_user_districtName['dist_name'] ?? '';
                                                            
                                                            if ($getinvuser == "super_stockiest" || $getinvuser == "stockiest") {
                                                                $UserName_SHOW = strtoupper($result_Customers_list['name']) . " (" . strtoupper($user_districtName) . "), " . $result_Customers_list['mobile_number'];
                                                            } else {
                                                                $UserName_SHOW = strtoupper($result_Customers_list['name']) . ", " . $result_Customers_list['mobile_number'];
                                                            }
                                                            
                                                            echo '<option value="' . htmlspecialchars($result_Customers_list['temp_id']) . '">' . htmlspecialchars($UserName_SHOW) . '</option>';
                                                        }
                                                        ?>
                                                    </select>

                                                    <!-- Advance Payment Balance Display (Only for SS/ST) -->
                                                    <?php if ($is_advance_mandatory): ?>
                                                    <div id="balanceDisplay" class="balance-display-card hidden mt-3">
                                                        <div class="balance-header">
                                                            <i class="material-icons-outlined">account_balance_wallet</i>
                                                            <strong>Available Advance Balance</strong>
                                                        </div>
                                                        <div class="balance-amount" id="balanceAmount">₹ 0.00</div>
                                                        <div class="balance-breakdown">
                                                            <div>Total Paid: <span id="totalPaid">₹ 0.00</span></div>
                                                            <div>Adjusted: <span id="totalAdjusted">₹ 0.00</span></div>
                                                            <div>Payments: <span id="paymentCount">0</span></div>
                                                        </div>
                                                        <div class="balance-status sufficient" id="balanceStatus">
                                                            ✓ Ready to create invoice
                                                        </div>
                                                    </div>

                                                    <div id="balanceError" class="alert-advance-payment error hidden mt-3">
                                                        <strong><i class="material-icons-outlined">error</i> No Advance Payment Found</strong>
                                                        <p class="mb-0">This customer must have advance payment to create invoices. Please add advance payment first.</p>
                                                        <a href="add-advance-payment.php" class="btn btn-sm btn-primary mt-2">Add Advance Payment</a>
                                                    </div>

                                                    <div id="balanceLoading" class="loading-text">
                                                        <span class="spinner"></span> Loading balance...
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Step 3: Invoice Details -->
                                            <div class="invoice-step">
                                                <div class="step-header">
                                                    <div class="step-number">3</div>
                                                    <div class="step-title">Invoice Details</div>
                                                </div>
                                                <div class="step-content">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <label class="form-label required">Invoice Number</label>
                                                            <input type="text" 
                                                                   name="inv_number" 
                                                                   id="invNumber" 
                                                                   class="form-control" 
                                                                   required 
                                                                   onkeypress="restrictSpecialChars(event)"
                                                                   onkeyup="showInvoiceDuplicate(this.value)">
                                                            <span id="txtHintInvoice" class="help-text"></span>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label required">Invoice Date</label>
                                                            <input type="date" 
                                                                   name="date" 
                                                                   id="invoiceDate" 
                                                                   class="form-control date-picker" 
                                                                   value="<?php echo date('Y-m-d'); ?>" 
                                                                   required>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Step 4: Add Products -->
                                            <div class="invoice-step" id="productSection">
                                                <div class="step-header">
                                                    <div class="step-number">4</div>
                                                    <div class="step-title">Add Products</div>
                                                </div>
                                                <div class="step-content">
                                                    <div class="product-entry-row">
                                                        <div class="product-input-group">
                                                            <div>
                                                                <label class="form-label">Product</label>
                                                                <select name="pr_id" id="productSelect" class="form-select" onchange="showPrice(this.value)">
                                                                    <option value="">Select Product</option>
                                                                    <?php
                                                                    $select_Products = "SELECT * FROM products ORDER BY productName ASC";
                                                                    $fetch_Products = mysqli_query($db_conn, $select_Products);
                                                                    while ($row_Product = mysqli_fetch_array($fetch_Products)) {
                                                                        echo '<option value="' . $row_Product['id'] . '">' . htmlspecialchars($row_Product['productName']) . '</option>';
                                                                    }
                                                                    ?>
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label class="form-label">Quantity</label>
                                                                <input type="number" name="qty" id="qty" class="form-control" min="1" placeholder="Qty" onkeyup="calculateTotal()">
                                                            </div>
                                                            <div id="txtHintPrice">
                                                                <label class="form-label">Price</label>
                                                                <input type="number" name="amount" id="amount" class="form-control" min="0" step="0.01" placeholder="Price" onkeyup="calculateTotal()">
                                                            </div>
                                                            <div>
                                                                <label class="form-label">Total</label>
                                                                <input type="number" name="total" id="output" class="form-control" readonly placeholder="Total">
                                                            </div>
                                                        </div>

                                                        <div class="discount-input-group mt-3">
                                                            <div>
                                                                <label class="form-label">Discount (%)</label>
                                                                <input type="number" name="discount_percentage" id="discountpercentae" class="form-control" min="0" max="100" step="0.01" placeholder="0" onkeyup="calculateDiscount()">
                                                            </div>
                                                            <div>
                                                                <label class="form-label">Discount (₹)</label>
                                                                <input type="number" name="discount_amount" id="discountamount" class="form-control" min="0" step="0.01" placeholder="0.00" readonly>
                                                            </div>
                                                            <div class="d-flex align-items-end">
                                                                <button type="submit" name="addInvoice" class="btn btn-success w-100" id="addProductBtn">
                                                                    <i class="material-icons">add</i> Add Product
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div id="txtHintstock" class="mt-2"></div>
                                                </div>
                                            </div>

                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                        ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>

    <?php include('invoice-scripts.php'); ?>

</body>
</html>