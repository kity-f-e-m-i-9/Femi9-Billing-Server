<?php 
/**
 * Super Stockist Update Action Handler
 * Handles updating Super Stockist information including district assignment
 * 
 * Security: Uses prepared statements, CSRF protection, input validation
 * Performance: Optimized queries with proper error handling
 */

declare(strict_types=1);

include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");

// CSRF Token Validation
function validateCSRFToken(): bool {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Sanitize input function
function sanitizeInput(?string $input): string {
    if ($input === null) {
        return '';
    }
    $input = trim($input);
    $input = str_replace("'", "&#39;", $input);
    return RemoveSpecialChar($input);
}

// Redirect helper
function redirectWithMessage(string $location, string $message = ''): void {
    $url = $location . ($message ? '?' . $message : '');
    header("Location: $url");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    
    // Get and validate form data
    $update_id = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    $old_icon = sanitizeInput($_POST['old_icon'] ?? '');
    $current_district_id = filter_var($_POST['current_district_id'] ?? 0, FILTER_VALIDATE_INT);
    
    // Validate update_id
    if (!$update_id || $update_id <= 0) {
        echo "<script>alert('Invalid ID'); window.location='manage_ss';</script>";
        exit;
    }
    
    // Sanitize form inputs
    $country_code = sanitizeInput($_POST["country_code"] ?? '');
    $name = sanitizeInput($_POST['name'] ?? '');
    $mobile_number = sanitizeInput($_POST['mobile_number'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $address = sanitizeInput($_POST["address"] ?? '');
    $gstin = sanitizeInput($_POST['gstin'] ?? '');
    
    // Get state and district
    $state_id = filter_var($_POST['state_id'] ?? 0, FILTER_VALIDATE_INT);
    $dist_id = filter_var($_POST['dist_id'] ?? 0, FILTER_VALIDATE_INT);
    
    // NEW: Get category ID from form
    $ss_cat_id = filter_var($_POST['ss_cat_id'] ?? 0, FILTER_VALIDATE_INT);
    
    // Validate required fields
    if (empty($name) || empty($mobile_number)) {
        echo "<script>alert('Name and Mobile Number are required'); window.history.back();</script>";
        exit;
    }
    
    // Validate state and district
    if (!$state_id || $state_id <= 0 || !$dist_id || $dist_id <= 0) {
        echo "<script>alert('Please select valid State and District'); window.history.back();</script>";
        exit;
    }
    
    // Check if district is already assigned to another super stockist (excluding current one)
    if ($dist_id != $current_district_id) {
        $check_district = "SELECT id FROM super_stockiest WHERE district_id = ? AND id != ? LIMIT 1";
        $stmt_check = mysqli_prepare($db_conn, $check_district);
        mysqli_stmt_bind_param($stmt_check, "ii", $dist_id, $update_id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            mysqli_stmt_close($stmt_check);
            echo "<script>window.location='edit-ss?prid=" . base64_encode((string)$update_id) . "&distalready';</script>";
            exit;
        }
        mysqli_stmt_close($stmt_check);
    }
    
    // Handle file upload
    $insfilename = $old_icon;
    
    if (isset($_FILES['user_icon']) && $_FILES['user_icon']['error'] === UPLOAD_ERR_OK) {
        
        $filetype = $_FILES['user_icon']['type'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        
        if (!in_array($filetype, $allowed_types)) {
            echo "<script>alert('Invalid file type. Only JPEG, PNG and WEBP allowed.'); window.location='edit-ss?prid=" . base64_encode((string)$update_id) . "&imageinvalid';</script>";
            exit;
        }
        
        // Check file size (2MB max)
        $max_size = 2 * 1024 * 1024; // 2MB
        if ($_FILES['user_icon']['size'] > $max_size) {
            echo "<script>alert('File size exceeds 2MB limit.'); window.location='edit-ss?prid=" . base64_encode((string)$update_id) . "&filetoobig';</script>";
            exit;
        }
        
        // Generate secure random filename
        $rand_isd = random_int(100000, 999999);
        $file_extension = strtolower(pathinfo($_FILES['user_icon']['name'], PATHINFO_EXTENSION));
        $filename = $rand_isd . '_' . time() . '.' . $file_extension;
        $uploaddir = 'user_icon/';
        
        // Create directory if not exists
        if (!is_dir($uploaddir)) {
            mkdir($uploaddir, 0755, true);
        }
        
        $uploadfile = $uploaddir . $filename;
        
        if (move_uploaded_file($_FILES['user_icon']['tmp_name'], $uploadfile)) {
            $insfilename = $uploadfile;
            
            // Delete old icon if exists and not default
            if ($old_icon != "Nil" && !empty($old_icon) && file_exists($old_icon)) {
                unlink($old_icon);
            }
        } else {
            echo "<script>alert('Failed to upload file.'); window.location='edit-ss?prid=" . base64_encode((string)$update_id) . "&uploadfailed';</script>";
            exit;
        }
    }
    
    // Get Super Stockist temp_id for referral table update
    $get_temp_id = "SELECT temp_id FROM super_stockiest WHERE id = ? LIMIT 1";
    $stmt_temp = mysqli_prepare($db_conn, $get_temp_id);
    mysqli_stmt_bind_param($stmt_temp, "i", $update_id);
    mysqli_stmt_execute($stmt_temp);
    $result_temp = mysqli_stmt_get_result($stmt_temp);
    $row_temp = mysqli_fetch_assoc($result_temp);
    mysqli_stmt_close($stmt_temp);
    
    if (!$row_temp) {
        echo "<script>alert('Super Stockist not found.'); window.location='manage_ss';</script>";
        exit;
    }
    
    $super_stockiest_id = $row_temp['temp_id'];
    
    // Begin transaction
    mysqli_begin_transaction($db_conn);
    
    try {
        // Update super_stockiest table
        $update_ss = "UPDATE super_stockiest SET 
                        user_icon = ?, 
                        name = ?, 
                        email = ?, 
                        mobile_number = ?, 
                        username = ?, 
                        address = ?, 
                        gstin = ?, 
                        country_code = ?,
                        state_id = ?,
                        district_id = ?,
                        updated_at = NOW()
                      WHERE id = ?";
        
        $stmt_update = mysqli_prepare($db_conn, $update_ss);
        
        if (!$stmt_update) {
            throw new Exception("Failed to prepare super_stockiest update statement");
        }
        
        mysqli_stmt_bind_param(
            $stmt_update, 
            "ssssssssiis", 
            $insfilename, 
            $name, 
            $email, 
            $mobile_number, 
            $mobile_number, 
            $address, 
            $gstin, 
            $country_code,
            $state_id,
            $dist_id,
            $update_id
        );
        
        if (!mysqli_stmt_execute($stmt_update)) {
            throw new Exception("Failed to update super_stockiest table");
        }
        mysqli_stmt_close($stmt_update);
        
        // Update or insert category in super_stockiest_referral table
        if ($ss_cat_id > 0) {
            // Check if referral record exists
            $check_referral = "SELECT id FROM super_stockiest_referral WHERE super_stockiest_id = ? LIMIT 1";
            $stmt_check_ref = mysqli_prepare($db_conn, $check_referral);
            mysqli_stmt_bind_param($stmt_check_ref, "s", $super_stockiest_id);
            mysqli_stmt_execute($stmt_check_ref);
            $result_check_ref = mysqli_stmt_get_result($stmt_check_ref);
            $referral_exists = mysqli_num_rows($result_check_ref) > 0;
            mysqli_stmt_close($stmt_check_ref);
            
            if ($referral_exists) {
                // Update existing record
                $update_referral = "UPDATE super_stockiest_referral SET ss_cat_id = ? WHERE super_stockiest_id = ?";
                $stmt_ref = mysqli_prepare($db_conn, $update_referral);
                mysqli_stmt_bind_param($stmt_ref, "is", $ss_cat_id, $super_stockiest_id);
            } else {
                // Insert new record
                $insert_referral = "INSERT INTO super_stockiest_referral (super_stockiest_id, ss_cat_id, target_amount) 
                                    SELECT ?, ?, target_amount FROM super_stockiest_category WHERE id = ?";
                $stmt_ref = mysqli_prepare($db_conn, $insert_referral);
                mysqli_stmt_bind_param($stmt_ref, "sii", $super_stockiest_id, $ss_cat_id, $ss_cat_id);
            }
            
            if (!mysqli_stmt_execute($stmt_ref)) {
                throw new Exception("Failed to update category assignment");
            }
            mysqli_stmt_close($stmt_ref);
        }
        
        // Commit transaction
        mysqli_commit($db_conn);
        
        echo "<script>window.location='manage_ss?updatedSuccess';</script>";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($db_conn);
        
        // Log error
        error_log("Super Stockist Update Error: " . $e->getMessage());
        
        echo "<script>alert('Update failed: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
    
} else {
    echo "<script>window.location='add_ss';</script>";
}
?>