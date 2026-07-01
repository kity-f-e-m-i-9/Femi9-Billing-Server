<?php 
/**
 * Product Action Handler
 * Handles all product, district, state, and category operations
 * 
 * Security: Uses prepared statements, CSRF protection, input validation
 * Performance: Optimized queries with proper error handling
 */

declare(strict_types=1);

include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// CSRF Token Validation Function
function validateCSRFToken(): bool {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Sanitize and validate input
function sanitizeInput(?string $input): string {
    if ($input === null) {
        return '';
    }
    $input = trim($input);
    $input = str_replace("'", "&#39;", $input);
    return RemoveSpecialChar($input);
}

// Redirect with message
function redirectWithMessage(string $location, string $message = ''): void {
    $url = $location . ($message ? '?' . $message : '');
    header("Location: $url");
    exit();
}

//--------------------------------------------------------------------------------------
// INSERT PRODUCT DETAILS
//--------------------------------------------------------------------------------------
if (isset($_POST['add-product'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('Products', 'csrf_error');
    }
    
    $temp_id = sanitizeInput($_POST['temp_id'] ?? null);
    
    if (empty($temp_id)) {
        redirectWithMessage('Products', 'invalidparameters');
    }
    
    // Sanitize all inputs
    $productName = sanitizeInput($_POST['productName'] ?? '');
    $mrp = sanitizeInput($_POST['mrp'] ?? '0');
    $supersstock_price = sanitizeInput($_POST['supersstock_price'] ?? '0');
    $super_distributor_price = sanitizeInput($_POST['super_distributor_price'] ?? '0');
    $stockist_price = sanitizeInput($_POST['stockist_price'] ?? '0');
    $distributor_price = sanitizeInput($_POST['distributor_price'] ?? '0');
    $outlet_price = sanitizeInput($_POST['outlet_price'] ?? '0');
    $gst = sanitizeInput($_POST['gst'] ?? '0');
    $gst_type = in_array($_POST['gst_type'] ?? '', ['inclusive', 'exclusive']) ? $_POST['gst_type'] : 'exclusive';
    $rwpoints = sanitizeInput($_POST['rwpoints'] ?? '0');
    $hsn = sanitizeInput($_POST['hsn'] ?? '');

    // Check if product already exists
    $stmt = $db_conn->prepare("SELECT COUNT(*) as numProducts FROM products WHERE temp_id = ?");
    $stmt->bind_param("s", $temp_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['numProducts'] == 0) {
        // Insert new product
        $stmt = $db_conn->prepare(
            "INSERT INTO products (temp_id, productName, mrp, supersstock_price, super_distributor_price,
            stockist_price, distributor_price, outlet_price, gst, gst_type, hsn, rwpoints, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );

        $stmt->bind_param(
            "ssdddddddssd",
            $temp_id, $productName, $mrp, $supersstock_price, $super_distributor_price,
            $stockist_price, $distributor_price, $outlet_price, $gst, $gst_type, $hsn, $rwpoints
        );
        
        if ($stmt->execute()) {
            $stmt->close();
            redirectWithMessage('manage-products', 'addesuccess');
        } else {
            $stmt->close();
            redirectWithMessage('Products', 'error');
        }
    } else {
        redirectWithMessage('Products', 'alreadyexists');
    }
}

//--------------------------------------------------------------------------------------
// UPDATE PRODUCT DETAILS
//--------------------------------------------------------------------------------------
if (isset($_POST['update-product'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('manage-products', 'csrf_error');
    }
    
    $update_id = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    
    if (!$update_id) {
        redirectWithMessage('manage-products', 'invalidparameters');
    }
    
    // Sanitize all inputs
    $productName = sanitizeInput($_POST['productName'] ?? '');
    $mrp = sanitizeInput($_POST['mrp'] ?? '0');
    $supersstock_price = sanitizeInput($_POST['supersstock_price'] ?? '0');
    $super_distributor_price = sanitizeInput($_POST['super_distributor_price'] ?? '0');
    $stockist_price = sanitizeInput($_POST['stockist_price'] ?? '0');
    $distributor_price = sanitizeInput($_POST['distributor_price'] ?? '0');
    $outlet_price = sanitizeInput($_POST['outlet_price'] ?? '0');
    $gst = sanitizeInput($_POST['gst'] ?? '0');
    $gst_type = in_array($_POST['gst_type'] ?? '', ['inclusive', 'exclusive']) ? $_POST['gst_type'] : 'exclusive';
    $rwpoints = sanitizeInput($_POST['rwpoints'] ?? '0');
    $hsn = sanitizeInput($_POST['hsn'] ?? '');

    // Update product
    $stmt = $db_conn->prepare(
        "UPDATE products SET productName = ?, mrp = ?, supersstock_price = ?,
        super_distributor_price = ?, stockist_price = ?, distributor_price = ?,
        outlet_price = ?, gst = ?, gst_type = ?, hsn = ?, rwpoints = ?, updated_at = NOW()
        WHERE id = ?"
    );

    $stmt->bind_param(
        "sdddddddssdi",
        $productName, $mrp, $supersstock_price, $super_distributor_price,
        $stockist_price, $distributor_price, $outlet_price, $gst, $gst_type, $hsn, $rwpoints, $update_id
    );
    
    if ($stmt->execute()) {
        $stmt->close();
        redirectWithMessage('manage-products', 'updatedSuccess');
    } else {
        $stmt->close();
        redirectWithMessage('manage-products', 'error');
    }
}

//--------------------------------------------------------------------------------------
// INSERT DISTRICT DETAILS
//--------------------------------------------------------------------------------------
if (isset($_POST['add-district'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('add-district', 'csrf_error');
    }
    
    $state_id = filter_var($_POST['state_id'] ?? 0, FILTER_VALIDATE_INT);
    $dist_name = sanitizeInput($_POST['dist_name'] ?? '');
    
    if (!$state_id || empty($dist_name)) {
        redirectWithMessage('add-district', 'invalidparameters');
    }
    
    // Check if district already exists
    $stmt = $db_conn->prepare("SELECT COUNT(*) as numDistrict FROM district WHERE state_id = ? AND dist_name = ?");
    $stmt->bind_param("is", $state_id, $dist_name);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['numDistrict'] == 0) {
        // Insert new district
        $stmt = $db_conn->prepare(
            "INSERT INTO district (state_id, dist_name, usertype, userid, assigned_SSID) 
            VALUES (?, ?, ?, ?, 'Nil')"
        );
        
        $stmt->bind_param("isss", $state_id, $dist_name, $Login_user_TYPEvl, $Login_user_IDvl);
        
        if ($stmt->execute()) {
            $stmt->close();
            redirectWithMessage('manage-district', 'addesuccess');
        } else {
            $stmt->close();
            redirectWithMessage('add-district', 'error');
        }
    } else {
        redirectWithMessage('add-district', 'alreadyexists');
    }
}

//--------------------------------------------------------------------------------------
// UPDATE DISTRICT DETAILS
//--------------------------------------------------------------------------------------
if (isset($_POST['update-district'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('manage-district', 'csrf_error');
    }
    
    $update_id = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    $state_id = filter_var($_POST['state_id'] ?? 0, FILTER_VALIDATE_INT);
    $dist_name = sanitizeInput($_POST['dist_name'] ?? '');
    
    if (!$update_id || !$state_id || empty($dist_name)) {
        redirectWithMessage('manage-district', 'invalidparameters');
    }
    
    // Update district
    $stmt = $db_conn->prepare("UPDATE district SET dist_name = ?, state_id = ? WHERE id = ?");
    $stmt->bind_param("sii", $dist_name, $state_id, $update_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        redirectWithMessage('manage-district', 'updatedSuccess');
    } else {
        $stmt->close();
        redirectWithMessage('manage-district', 'error');
    }
}

//--------------------------------------------------------------------------------------
// INSERT TALUK DETAILS
//--------------------------------------------------------------------------------------
if (isset($_POST['add-taluk'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('add-taluk', 'csrf_error');
    }
    
    $state_id = filter_var($_POST['state_id'] ?? 0, FILTER_VALIDATE_INT);
    $dist_id = filter_var($_POST['dist_id'] ?? 0, FILTER_VALIDATE_INT);
    
    if (!$state_id || !$dist_id || !isset($_POST['taluk']) || !is_array($_POST['taluk'])) {
        redirectWithMessage('add-taluk', 'invalidparameters');
    }
    
    $success_count = 0;
    $already_exists = false;
    
    // Prepare statement once
    $check_stmt = $db_conn->prepare(
        "SELECT COUNT(*) as numTaluk FROM taluk WHERE state_id = ? AND dist_id = ? AND taluk = ?"
    );
    $insert_stmt = $db_conn->prepare(
        "INSERT INTO taluk (state_id, dist_id, taluk, usertype, userid, assigned_SID) 
        VALUES (?, ?, ?, ?, ?, 'Nil')"
    );
    
    foreach ($_POST['taluk'] as $taluk_value) {
        $taluk_value = sanitizeInput($taluk_value);
        
        if (empty($taluk_value)) {
            continue;
        }
        
        // Check if taluk already exists
        $check_stmt->bind_param("iis", $state_id, $dist_id, $taluk_value);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        
        if ($result['numTaluk'] == 0) {
            // Insert new taluk
            $insert_stmt->bind_param("iisss", $state_id, $dist_id, $taluk_value, $Login_user_TYPEvl, $Login_user_IDvl);
            if ($insert_stmt->execute()) {
                $success_count++;
            }
        } else {
            $already_exists = true;
        }
    }
    
    $check_stmt->close();
    $insert_stmt->close();
    
    if ($success_count > 0) {
        redirectWithMessage('manage-taluk', 'addesuccess');
    } elseif ($already_exists) {
        redirectWithMessage('add-taluk', 'alreadyexists');
    } else {
        redirectWithMessage('add-taluk', 'error');
    }
}

//--------------------------------------------------------------------------------------
// UPDATE TALUK DETAILS
//--------------------------------------------------------------------------------------
if (isset($_POST['update-taluk'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('manage-taluk', 'csrf_error');
    }
    
    $update_id = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    $state_id = filter_var($_POST['state_id'] ?? 0, FILTER_VALIDATE_INT);
    $dist_id = filter_var($_POST['dist_id'] ?? 0, FILTER_VALIDATE_INT);
    $taluk = sanitizeInput($_POST['taluk'] ?? '');
    
    if (!$update_id || !$state_id || !$dist_id || empty($taluk)) {
        redirectWithMessage('manage-taluk', 'invalidparameters');
    }
    
    // Update taluk
    $stmt = $db_conn->prepare("UPDATE taluk SET state_id = ?, dist_id = ?, taluk = ? WHERE id = ?");
    $stmt->bind_param("iisi", $state_id, $dist_id, $taluk, $update_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        redirectWithMessage('manage-taluk', 'updatedSuccess');
    } else {
        $stmt->close();
        redirectWithMessage('manage-taluk', 'error');
    }
}

//--------------------------------------------------------------------------------------
// INSERT PINCODE DETAILS
//--------------------------------------------------------------------------------------
if (isset($_POST['add-pincode'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('add-pincode', 'csrf_error');
    }
    
    $state_id = filter_var($_POST['state_id'] ?? 0, FILTER_VALIDATE_INT);
    $dist_id = filter_var($_POST['dist_id'] ?? 0, FILTER_VALIDATE_INT);
    $taluk_id = filter_var($_POST['taluk_id'] ?? 0, FILTER_VALIDATE_INT);
    
    if (!$state_id || !$dist_id || !$taluk_id || !isset($_POST['pincode']) || !is_array($_POST['pincode'])) {
        redirectWithMessage('add-pincode', 'invalidparameters');
    }
    
    $success_count = 0;
    $already_exists = false;
    
    // Prepare statements once
    $check_stmt = $db_conn->prepare(
        "SELECT COUNT(*) as numpincode FROM pincode WHERE state_id = ? AND dist_id = ? AND taluk_id = ? AND pincode = ?"
    );
    $insert_stmt = $db_conn->prepare(
        "INSERT INTO pincode (state_id, dist_id, taluk_id, pincode, usertype, userid, assigned_SID, assigned_DID) 
        VALUES (?, ?, ?, ?, ?, ?, 'Nil', 'Nil')"
    );
    
    foreach ($_POST['pincode'] as $pincode_value) {
        $pincode_value = sanitizeInput($pincode_value);
        
        if (empty($pincode_value)) {
            continue;
        }
        
        // Check if pincode already exists
        $check_stmt->bind_param("iiis", $state_id, $dist_id, $taluk_id, $pincode_value);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        
        if ($result['numpincode'] == 0) {
            // Insert new pincode
            $insert_stmt->bind_param("iiisss", $state_id, $dist_id, $taluk_id, $pincode_value, $Login_user_TYPEvl, $Login_user_IDvl);
            if ($insert_stmt->execute()) {
                $success_count++;
            }
        } else {
            $already_exists = true;
        }
    }
    
    $check_stmt->close();
    $insert_stmt->close();
    
    if ($success_count > 0) {
        redirectWithMessage('manage-pincode', 'addesuccess');
    } elseif ($already_exists) {
        redirectWithMessage('add-pincode', 'alreadyexists');
    } else {
        redirectWithMessage('add-pincode', 'error');
    }
}

//--------------------------------------------------------------------------------------
// UPDATE PINCODE DETAILS
//--------------------------------------------------------------------------------------
if (isset($_POST['update-pincode'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('manage-pincode', 'csrf_error');
    }
    
    $update_id = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    $state_id = filter_var($_POST['state_id'] ?? 0, FILTER_VALIDATE_INT);
    $dist_id = filter_var($_POST['dist_id'] ?? 0, FILTER_VALIDATE_INT);
    $taluk_id = filter_var($_POST['taluk_id'] ?? 0, FILTER_VALIDATE_INT);
    $pincode = sanitizeInput($_POST['pincode'] ?? '');
    
    if (!$update_id || !$state_id || !$dist_id || !$taluk_id || empty($pincode)) {
        redirectWithMessage('manage-pincode', 'invalidparameters');
    }
    
    // Update pincode
    $stmt = $db_conn->prepare("UPDATE pincode SET state_id = ?, dist_id = ?, taluk_id = ?, pincode = ? WHERE id = ?");
    $stmt->bind_param("iiisi", $state_id, $dist_id, $taluk_id, $pincode, $update_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        redirectWithMessage('manage-pincode', 'updatedSuccess');
    } else {
        $stmt->close();
        redirectWithMessage('manage-pincode', 'error');
    }
}

//--------------------------------------------------------------------------------------
// INSERT STATE DETAILS
//--------------------------------------------------------------------------------------
if (isset($_POST['add-state'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('add-state', 'csrf_error');
    }
    
    $st_name = sanitizeInput($_POST['st_name'] ?? '');
    
    if (empty($st_name)) {
        redirectWithMessage('add-state', 'invalidparameters');
    }
    
    // Check if state already exists
    $stmt = $db_conn->prepare("SELECT COUNT(*) as numstate FROM state WHERE st_name = ?");
    $stmt->bind_param("s", $st_name);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['numstate'] == 0) {
        // Insert new state
        $stmt = $db_conn->prepare("INSERT INTO state (st_name) VALUES (?)");
        $stmt->bind_param("s", $st_name);
        
        if ($stmt->execute()) {
            $stmt->close();
            redirectWithMessage('manage-state', 'addesuccess');
        } else {
            $stmt->close();
            redirectWithMessage('add-state', 'error');
        }
    } else {
        redirectWithMessage('add-state', 'alreadyexists');
    }
}

//--------------------------------------------------------------------------------------
// UPDATE STATE DETAILS
//--------------------------------------------------------------------------------------
if (isset($_POST['update-state'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('manage-state', 'csrf_error');
    }
    
    $update_id = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    $st_name = sanitizeInput($_POST['st_name'] ?? '');
    
    if (!$update_id || empty($st_name)) {
        redirectWithMessage('manage-state', 'invalidparameters');
    }
    
    // Update state
    $stmt = $db_conn->prepare("UPDATE state SET st_name = ? WHERE id = ?");
    $stmt->bind_param("si", $st_name, $update_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        redirectWithMessage('manage-state', 'updatedSuccess');
    } else {
        $stmt->close();
        redirectWithMessage('manage-state', 'error');
    }
}

//--------------------------------------------------------------------------------------
// INSERT OTHER CHANNEL CATEGORY
//--------------------------------------------------------------------------------------
if (isset($_POST['insertot'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('ot-sale-cat', 'csrf_error');
    }
    
    $cat = sanitizeInput($_POST['cat'] ?? '');
    
    if (empty($cat)) {
        redirectWithMessage('ot-sale-cat', 'invalidparameters');
    }
    
    // Check if category already exists
    $stmt = $db_conn->prepare("SELECT COUNT(*) as numstate FROM ot_cat WHERE cat = ?");
    $stmt->bind_param("s", $cat);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['numstate'] == 0) {
        // Insert new category
        $stmt = $db_conn->prepare("INSERT INTO ot_cat (cat) VALUES (?)");
        $stmt->bind_param("s", $cat);
        
        if ($stmt->execute()) {
            $stmt->close();
            redirectWithMessage('ot-sale-cat', 'addesuccess');
        } else {
            $stmt->close();
            redirectWithMessage('ot-sale-cat', 'error');
        }
    } else {
        redirectWithMessage('ot-sale-cat', 'alreadyexists');
    }
}

//--------------------------------------------------------------------------------------
// INSERT COUNTRY DETAILS
//--------------------------------------------------------------------------------------
if (isset($_POST['InsertCountry'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('add-country', 'csrf_error');
    }
    
    if (!isset($_POST['c_name']) || !is_array($_POST['c_name']) ||
        !isset($_POST['c_code']) || !is_array($_POST['c_code']) ||
        !isset($_POST['currency_name']) || !is_array($_POST['currency_name']) ||
        !isset($_POST['currency_ascii_code']) || !is_array($_POST['currency_ascii_code'])) {
        redirectWithMessage('add-country', 'invalidparameters');
    }
    
    $success_count = 0;
    $already_exists = false;
    
    // Prepare statements once
    $check_stmt = $db_conn->prepare("SELECT COUNT(*) as numcountry FROM country WHERE c_code = ?");
    $insert_stmt = $db_conn->prepare(
        "INSERT INTO country (c_name, c_code, currency_name, currency_ascii_code) VALUES (?, ?, ?, ?)"
    );
    
    $count = min(
        count($_POST['c_name']),
        count($_POST['c_code']),
        count($_POST['currency_name']),
        count($_POST['currency_ascii_code'])
    );
    
    for ($i = 0; $i < $count; $i++) {
        $c_name_value = sanitizeInput($_POST['c_name'][$i] ?? '');
        $c_code_value = sanitizeInput($_POST['c_code'][$i] ?? '');
        $currency_name_value = sanitizeInput($_POST['currency_name'][$i] ?? '');
        $currency_ascii_code_value = sanitizeInput($_POST['currency_ascii_code'][$i] ?? '');
        
        if (empty($c_code_value)) {
            continue;
        }
        
        // Check if country already exists
        $check_stmt->bind_param("s", $c_code_value);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        
        if ($result['numcountry'] == 0) {
            // Insert new country
            $insert_stmt->bind_param("ssss", $c_name_value, $c_code_value, $currency_name_value, $currency_ascii_code_value);
            if ($insert_stmt->execute()) {
                $success_count++;
            }
        } else {
            $already_exists = true;
        }
    }
    
    $check_stmt->close();
    $insert_stmt->close();
    
    if ($success_count > 0) {
        redirectWithMessage('manage-country', 'addesuccess');
    } elseif ($already_exists) {
        redirectWithMessage('add-country', 'alreadyexists');
    } else {
        redirectWithMessage('add-country', 'error');
    }
}

//--------------------------------------------------------------------------------------
// UPDATE COUNTRY DETAILS
//--------------------------------------------------------------------------------------
if (isset($_POST['UpdateCountry'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('manage-country', 'csrf_error');
    }
    
    $update_id = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    $c_name = sanitizeInput($_POST['c_name'] ?? '');
    $c_code = sanitizeInput($_POST['c_code'] ?? '');
    $currency_name = sanitizeInput($_POST['currency_name'] ?? '');
    $currency_ascii_code = sanitizeInput($_POST['currency_ascii_code'] ?? '');
    
    if (!$update_id) {
        redirectWithMessage('manage-country', 'invalidparameters');
    }
    
    // Update country
    $stmt = $db_conn->prepare(
        "UPDATE country SET c_name = ?, c_code = ?, currency_name = ?, currency_ascii_code = ? WHERE id = ?"
    );
    $stmt->bind_param("ssssi", $c_name, $c_code, $currency_name, $currency_ascii_code, $update_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        redirectWithMessage('manage-country', 'updatedSuccess');
    } else {
        $stmt->close();
        redirectWithMessage('manage-country', 'error');
    }
}

//--------------------------------------------------------------------------------------
// INSERT SUPER STOCKIST CATEGORY
//--------------------------------------------------------------------------------------
if (isset($_POST['InsertSuperStockistCategory'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('cat-add-ss', 'csrf_error');
    }
    
    // Get district_id instead of manual category name
    $district_id = filter_var($_POST['district_id'] ?? 0, FILTER_VALIDATE_INT);
    $target_amount = filter_var($_POST['target_amount'] ?? 0, FILTER_VALIDATE_INT);
    $ref_commission_percentage = filter_var($_POST['ref_commission_percentage'] ?? 0, FILTER_VALIDATE_INT);
    $cash_back_percentage = filter_var($_POST['cash_back_percentage'] ?? 0, FILTER_VALIDATE_INT);
    
    if (!$district_id || $target_amount < 0 || $ref_commission_percentage < 0 || $cash_back_percentage < 0) {
        redirectWithMessage('cat-add-ss', 'invalidparameters');
    }
    
    // Get district name from district table
    $stmt = $db_conn->prepare("SELECT dist_name FROM district WHERE id = ?");
    $stmt->bind_param("i", $district_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt->close();
        redirectWithMessage('cat-add-ss', 'district_not_found');
    }
    
    $district_data = $result->fetch_assoc();
    $catname = $district_data['dist_name'];
    $stmt->close();
    
    // Check if category with this district already exists
    $stmt = $db_conn->prepare("SELECT COUNT(*) as numstate FROM super_stockiest_category WHERE name = ?");
    $stmt->bind_param("s", $catname);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['numstate'] == 0) {
        // Insert new category
        $stmt = $db_conn->prepare(
            "INSERT INTO super_stockiest_category (name, target_amount, ref_commission_percentage, cash_back_percentage, created_at, updated_at) 
            VALUES (?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->bind_param("siii", $catname, $target_amount, $ref_commission_percentage, $cash_back_percentage);
        
        if ($stmt->execute()) {
            $stmt->close();
            redirectWithMessage('cat-view-ss', 'addesuccess');
        } else {
            $stmt->close();
            redirectWithMessage('cat-add-ss', 'error');
        }
    } else {
        redirectWithMessage('cat-add-ss', 'alreadyexists');
    }
}

//--------------------------------------------------------------------------------------
// INSERT STOCKIST CATEGORY
//--------------------------------------------------------------------------------------
if (isset($_POST['InsertStockistCategory'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('cat-add-st', 'csrf_error');
    }
    
    $catname = sanitizeInput($_POST['catname'] ?? '');
    $target_amount = filter_var($_POST['target_amount'] ?? 0, FILTER_VALIDATE_INT);
    $ref_commission_percentage = filter_var($_POST['ref_commission_percentage'] ?? 0, FILTER_VALIDATE_INT);
    $cash_back_percentage = filter_var($_POST['cash_back_percentage'] ?? 0, FILTER_VALIDATE_INT);
    
    if (empty($catname) || $target_amount < 0 || $ref_commission_percentage < 0 || $cash_back_percentage < 0) {
        redirectWithMessage('cat-add-st', 'invalidparameters');
    }
    
    // Check if category already exists
    $stmt = $db_conn->prepare("SELECT COUNT(*) as numstate FROM stockist_category WHERE target_amount = ?");
    $stmt->bind_param("i", $target_amount);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['numstate'] == 0) {
        // Insert new category
        $stmt = $db_conn->prepare(
            "INSERT INTO stockist_category (catname, target_amount, ref_commission_percentage, cash_back_percentage) 
            VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("siii", $catname, $target_amount, $ref_commission_percentage, $cash_back_percentage);
        
        if ($stmt->execute()) {
            $stmt->close();
            redirectWithMessage('cat-view-st', 'addesuccess');
        } else {
            $stmt->close();
            redirectWithMessage('cat-add-st', 'error');
        }
    } else {
        redirectWithMessage('cat-add-st', 'alreadyexists');
    }
}

//--------------------------------------------------------------------------------------
// INSERT SUPER DISTRIBUTOR CATEGORY
//--------------------------------------------------------------------------------------
if (isset($_POST['InsertSuperDistributorCategory'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('cat-add-sdt', 'csrf_error');
    }
    
    $catname = sanitizeInput($_POST['name'] ?? '');
    $target_amount = filter_var($_POST['target_amount'] ?? 0, FILTER_VALIDATE_INT);
    $ref_commission_percentage = filter_var($_POST['ref_commission_percentage'] ?? 0, FILTER_VALIDATE_INT);
    $cash_back_percentage = filter_var($_POST['cash_back_percentage'] ?? 0, FILTER_VALIDATE_INT);
    
    if (empty($catname) || $target_amount < 0 || $ref_commission_percentage < 0 || $cash_back_percentage < 0) {
        redirectWithMessage('cat-add-sdt', 'invalidparameters');
    }
    
    // Check if category already exists
    $stmt = $db_conn->prepare("SELECT COUNT(*) as numstate FROM super_distributor_category WHERE amount = ?");
    $stmt->bind_param("i", $target_amount);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['numstate'] == 0) {
        // Insert new category
        $stmt = $db_conn->prepare(
            "INSERT INTO super_distributor_category (name, amount, ref_commission_percentage, cash_back_percentage) 
            VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("siii", $catname, $target_amount, $ref_commission_percentage, $cash_back_percentage);
        
        if ($stmt->execute()) {
            $stmt->close();
            redirectWithMessage('cat-view-sdt', 'addesuccess');
        } else {
            $stmt->close();
            redirectWithMessage('cat-add-sdt', 'error');
        }
    } else {
        redirectWithMessage('cat-add-sdt', 'alreadyexists');
    }
}

//--------------------------------------------------------------------------------------
// INSERT DISTRIBUTOR CATEGORY
//--------------------------------------------------------------------------------------
if (isset($_POST['InsertDistributorCategory'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('cat-add-dt', 'csrf_error');
    }
    
    $catname = sanitizeInput($_POST['name'] ?? '');
    $target_amount = filter_var($_POST['target_amount'] ?? 0, FILTER_VALIDATE_INT);
    $ref_commission_percentage = filter_var($_POST['ref_commission_percentage'] ?? 0, FILTER_VALIDATE_INT);
    $cash_back_percentage = filter_var($_POST['cash_back_percentage'] ?? 0, FILTER_VALIDATE_INT);
    
    if (empty($catname) || $target_amount < 0 || $ref_commission_percentage < 0 || $cash_back_percentage < 0) {
        redirectWithMessage('cat-add-dt', 'invalidparameters');
    }
    
    // Check if category already exists
    $stmt = $db_conn->prepare("SELECT COUNT(*) as numstate FROM distributor_category WHERE amount = ?");
    $stmt->bind_param("i", $target_amount);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['numstate'] == 0) {
        // Insert new category
        $stmt = $db_conn->prepare(
            "INSERT INTO distributor_category (name, amount, ref_commission_percentage, cash_back_percentage) 
            VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("siii", $catname, $target_amount, $ref_commission_percentage, $cash_back_percentage);
        
        if ($stmt->execute()) {
            $stmt->close();
            redirectWithMessage('cat-view-dt', 'addesuccess');
        } else {
            $stmt->close();
            redirectWithMessage('cat-add-dt', 'error');
        }
    } else {
        redirectWithMessage('cat-add-dt', 'alreadyexists');
    }
}

//--------------------------------------------------------------------------------------
// UPDATE STOCKIST CATEGORY
//--------------------------------------------------------------------------------------
if (isset($_POST['UpdateStockistCategory'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('cat-view-st', 'csrf_error');
    }
    
    $update_id = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    $original_prid = $_POST['prid'] ?? '';
    
    $catname = sanitizeInput($_POST['catname'] ?? '');
    $target_amount = filter_var($_POST['target_amount'] ?? 0, FILTER_VALIDATE_INT);
    $ref_commission_percentage = filter_var($_POST['ref_commission_percentage'] ?? 0, FILTER_VALIDATE_INT);
    $cash_back_percentage = filter_var($_POST['cash_back_percentage'] ?? 0, FILTER_VALIDATE_INT);
    
    if (!$update_id || empty($catname) || $target_amount < 0) {
        redirectWithMessage('cat-view-st', 'invalidparameters');
    }
    
    // Get current values from database
    $stmt = $db_conn->prepare("SELECT target_amount FROM stockist_category WHERE id = ?");
    $stmt->bind_param("i", $update_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt->close();
        redirectWithMessage('cat-view-st', 'notfound');
    }
    
    $current_data = $result->fetch_assoc();
    $current_target_amount = $current_data['target_amount'];
    $stmt->close();
    
    // Check if target_amount has changed
    $target_amount_changed = ($current_target_amount != $target_amount);
    
    // If target_amount changed, check for duplicates excluding current record
    if ($target_amount_changed) {
        $stmt = $db_conn->prepare(
            "SELECT COUNT(*) as numstate FROM stockist_category WHERE target_amount = ? AND id != ?"
        );
        $stmt->bind_param("ii", $target_amount, $update_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['numstate'] > 0) {
            $encoded_prid = base64_encode($original_prid);
            redirectWithMessage("cat-edit-st?prid=$encoded_prid", 'alreadyexists');
        }
    }
    
    // Update category
    $stmt = $db_conn->prepare(
        "UPDATE stockist_category SET catname = ?, target_amount = ?, 
        ref_commission_percentage = ?, cash_back_percentage = ? WHERE id = ?"
    );
    $stmt->bind_param("siiii", $catname, $target_amount, $ref_commission_percentage, $cash_back_percentage, $update_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        redirectWithMessage('cat-view-st', 'updatedSuccess');
    } else {
        $stmt->close();
        redirectWithMessage('cat-view-st', 'error');
    }
}

//--------------------------------------------------------------------------------------
// UPDATE SUPER DISTRIBUTOR CATEGORY
//--------------------------------------------------------------------------------------
if (isset($_POST['UpdateSuperDistributorCategory'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('cat-view-sdt', 'csrf_error');
    }
    
    $update_id = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    $original_prid = $_POST['prid'] ?? '';
    
    $catname = sanitizeInput($_POST['catname'] ?? '');
    $target_amount = filter_var($_POST['target_amount'] ?? 0, FILTER_VALIDATE_INT);
    $ref_commission_percentage = filter_var($_POST['ref_commission_percentage'] ?? 0, FILTER_VALIDATE_INT);
    $cash_back_percentage = filter_var($_POST['cash_back_percentage'] ?? 0, FILTER_VALIDATE_INT);
    
    if (!$update_id || empty($catname) || $target_amount < 0) {
        redirectWithMessage('cat-view-sdt', 'invalidparameters');
    }
    
    // Get current amount from database
    $stmt = $db_conn->prepare("SELECT amount FROM super_distributor_category WHERE id = ?");
    $stmt->bind_param("i", $update_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt->close();
        redirectWithMessage('cat-view-sdt', 'notfound');
    }
    
    $current_data = $result->fetch_assoc();
    $current_amount = $current_data['amount'];
    $stmt->close();
    
    // Check if amount has changed
    $amount_changed = ($current_amount != $target_amount);
    
    // If amount changed, check for duplicates
    if ($amount_changed) {
        $stmt = $db_conn->prepare(
            "SELECT COUNT(*) as numstate FROM super_distributor_category WHERE amount = ? AND id != ?"
        );
        $stmt->bind_param("ii", $target_amount, $update_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['numstate'] > 0) {
            $encoded_prid = base64_encode($original_prid);
            redirectWithMessage("cat-edit-sdt?prid=$encoded_prid", 'alreadyexists');
        }
    }
    
    // Update category
    $stmt = $db_conn->prepare(
        "UPDATE super_distributor_category SET name = ?, amount = ?, 
        ref_commission_percentage = ?, cash_back_percentage = ? WHERE id = ?"
    );
    $stmt->bind_param("siiii", $catname, $target_amount, $ref_commission_percentage, $cash_back_percentage, $update_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        redirectWithMessage('cat-view-sdt', 'updatedSuccess');
    } else {
        $stmt->close();
        redirectWithMessage('cat-view-sdt', 'error');
    }
}


//--------------------------------------------------------------------------------------
// UPDATE SUPER STOCKIST CATEGORY
//--------------------------------------------------------------------------------------
if (isset($_POST['UpdateSuperStockistCategory'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('cat-view-ss', 'csrf_error');
    }
    
    $update_id = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    $original_prid = $_POST['prid'] ?? '';
    
    $catname = sanitizeInput($_POST['catname'] ?? '');
    $target_amount = filter_var($_POST['target_amount'] ?? 0, FILTER_VALIDATE_INT);
    $ref_commission_percentage = filter_var($_POST['ref_commission_percentage'] ?? 0, FILTER_VALIDATE_INT);
    $cash_back_percentage = filter_var($_POST['cash_back_percentage'] ?? 0, FILTER_VALIDATE_INT);
    
    if (!$update_id || empty($catname) || $target_amount < 0) {
        redirectWithMessage('cat-view-ss', 'invalidparameters');
    }
    
    // Get current amount from database
    $stmt = $db_conn->prepare("SELECT target_amount FROM super_stockiest_category WHERE id = ?");
    $stmt->bind_param("i", $update_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt->close();
        redirectWithMessage('cat-view-ss', 'notfound');
    }
    
    $current_data = $result->fetch_assoc();
    $current_amount = $current_data['target_amount'];
    $stmt->close();
    
    // Check if amount has changed
    $amount_changed = ($current_amount != $target_amount);
    
    // If amount changed, check for duplicates
    if ($amount_changed) {
        $stmt = $db_conn->prepare(
            "SELECT COUNT(*) as numstate FROM super_stockiest_category WHERE target_amount = ? AND id != ?"
        );
        $stmt->bind_param("ii", $target_amount, $update_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['numstate'] > 0) {
            $encoded_prid = base64_encode($original_prid);
            redirectWithMessage("cat-edit-ss?prid=$encoded_prid", 'alreadyexists');
        }
    }
    
    // Update category
    $stmt = $db_conn->prepare(
        "UPDATE super_stockiest_category SET name = ?, target_amount = ?, 
        ref_commission_percentage = ?, cash_back_percentage = ? WHERE id = ?"
    );
    $stmt->bind_param("siiii", $catname, $target_amount, $ref_commission_percentage, $cash_back_percentage, $update_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        redirectWithMessage('cat-view-ss', 'updatedSuccess');
    } else {
        $stmt->close();
        redirectWithMessage('cat-view-ss', 'error');
    }
}

//--------------------------------------------------------------------------------------
// UPDATE DISTRIBUTOR CATEGORY
//--------------------------------------------------------------------------------------
if (isset($_POST['UpdateDistributorCategory'])) {
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        redirectWithMessage('cat-view-dt', 'csrf_error');
    }
    
    $update_id = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    $original_prid = $_POST['prid'] ?? '';
    
    $catname = sanitizeInput($_POST['catname'] ?? '');
    $target_amount = filter_var($_POST['target_amount'] ?? 0, FILTER_VALIDATE_INT);
    $ref_commission_percentage = filter_var($_POST['ref_commission_percentage'] ?? 0, FILTER_VALIDATE_INT);
    $cash_back_percentage = filter_var($_POST['cash_back_percentage'] ?? 0, FILTER_VALIDATE_INT);
    
    if (!$update_id || empty($catname) || $target_amount < 0) {
        redirectWithMessage('cat-view-dt', 'invalidparameters');
    }
    
    // Get current amount from database
    $stmt = $db_conn->prepare("SELECT amount FROM distributor_category WHERE id = ?");
    $stmt->bind_param("i", $update_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt->close();
        redirectWithMessage('cat-view-dt', 'notfound');
    }
    
    $current_data = $result->fetch_assoc();
    $current_amount = $current_data['amount'];
    $stmt->close();
    
    // Check if amount has changed
    $amount_changed = ($current_amount != $target_amount);
    
    // If amount changed, check for duplicates
    if ($amount_changed) {
        $stmt = $db_conn->prepare(
            "SELECT COUNT(*) as numstate FROM distributor_category WHERE amount = ? AND id != ?"
        );
        $stmt->bind_param("ii", $target_amount, $update_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['numstate'] > 0) {
            $encoded_prid = base64_encode($original_prid);
            redirectWithMessage("cat-edit-dt?prid=$encoded_prid", 'alreadyexists');
        }
    }
    
    // Update category
    $stmt = $db_conn->prepare(
        "UPDATE distributor_category SET name = ?, amount = ?, 
        ref_commission_percentage = ?, cash_back_percentage = ? WHERE id = ?"
    );
    $stmt->bind_param("siiii", $catname, $target_amount, $ref_commission_percentage, $cash_back_percentage, $update_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        redirectWithMessage('cat-view-dt', 'updatedSuccess');
    } else {
        $stmt->close();
        redirectWithMessage('cat-view-dt', 'error');
    }
}

// If no valid action was found
redirectWithMessage('dashboard', 'invalid_action');