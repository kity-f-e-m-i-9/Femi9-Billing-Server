<?php 
include("checksession.php");

// Remove special character function
include("RemoveSpecialChar.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Get form data
    $update_id = isset($_REQUEST['update_id']) ? intval($_REQUEST['update_id']) : 0;
    $old_icon = isset($_REQUEST['old_icon']) ? $_REQUEST['old_icon'] : '';
    $stockistid = isset($_REQUEST['stockistid']) ? $_REQUEST['stockistid'] : '';
    $current_taluk_id = isset($_REQUEST['current_taluk_id']) ? intval($_REQUEST['current_taluk_id']) : 0;
    
    // Validate update_id
    if ($update_id <= 0) {
        echo "<script>alert('Invalid ID'); window.location='stockist-manage';</script>";
        exit;
    }
    
    // Sanitize form inputs
    $name = str_replace("'", "&#39;", $_REQUEST['name']);
    $name = RemoveSpecialChar($name);
    
    $mobile_number = str_replace("'", "&#39;", $_REQUEST['mobile_number']);
    $mobile_number = RemoveSpecialChar($mobile_number);
    
    $email = str_replace("'", "&#39;", $_REQUEST['email']);
    $email = RemoveSpecialChar($email);
    
    $address = str_replace("'", "&#39;", $_POST["address"]);
    $address = RemoveSpecialChar($address);
    
    $country_code = isset($_POST["country_code"]) ? $_POST["country_code"] : '';
    
    // Get state, district, and taluk
    $state_id = isset($_POST['state_id']) ? intval($_POST['state_id']) : 0;
    $dist_id = isset($_POST['dist_id']) ? intval($_POST['dist_id']) : 0;
    $taluk_id = isset($_POST['taluk_id']) ? intval($_POST['taluk_id']) : 0;
    
    // Validate state, district, and taluk
    if ($state_id <= 0 || $dist_id <= 0 || $taluk_id <= 0) {
        echo "<script>alert('Please select valid State, District, and Taluk'); window.history.back();</script>";
        exit;
    }
    
    // Check if taluk is already assigned to another stockist (excluding current one)
    if ($taluk_id != $current_taluk_id) {
        $check_taluk = "SELECT id FROM stockiest WHERE taluk_id = ? AND id != ? LIMIT 1";
        $stmt_check = mysqli_prepare($db_conn, $check_taluk);
        mysqli_stmt_bind_param($stmt_check, "ii", $taluk_id, $update_id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            mysqli_stmt_close($stmt_check);
            echo "<script>window.location='stockist-edit-ss?prid=" . base64_encode($update_id) . "&talukalready';</script>";
            exit;
        }
        mysqli_stmt_close($stmt_check);
    }
    
    // Handle file upload
    $small_jpg = isset($_FILES['user_icon']['name']) ? $_FILES['user_icon']['name'] : '';
    
    if (!empty($small_jpg) && $_FILES['user_icon']['error'] === UPLOAD_ERR_OK) {
        
        $filetype = $_FILES['user_icon']['type'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        
        if (!in_array($filetype, $allowed_types)) {
            $insfilename = $old_icon;
            echo "<script>alert('Invalid file type. Only JPEG and PNG allowed.'); window.location='stockist-edit-ss?prid=" . base64_encode($update_id) . "&imageinvalid';</script>";
            exit;
        }
        
        // Check file size (2MB max)
        $max_size = 2 * 1024 * 1024; // 2MB
        if ($_FILES['user_icon']['size'] > $max_size) {
            $insfilename = $old_icon;
            echo "<script>alert('File size exceeds 2MB limit.'); window.location='stockist-edit-ss?prid=" . base64_encode($update_id) . "&filetoobig';</script>";
            exit;
        }
        
        // Generate secure random filename
        $rand_isd = random_int(100000, 999999);
        $file_extension = pathinfo($small_jpg, PATHINFO_EXTENSION);
        $filename = $rand_isd . '_' . time() . '.' . $file_extension;
        $uploaddir = '../super-stockist/user_icon/';
        
        // Create directory if not exists
        if (!is_dir($uploaddir)) {
            mkdir($uploaddir, 0755, true);
        }
        
        $uploadfile = $uploaddir . $filename;
        
        if (move_uploaded_file($_FILES['user_icon']['tmp_name'], $uploadfile)) {
            $insfoldername = "user_icon/";
            $insfilename = $insfoldername . $filename;
            
            // Delete old icon if exists and not default
            if ($old_icon != "Nil" && !empty($old_icon) && file_exists("../super-stockist/" . $old_icon)) {
                unlink("../super-stockist/" . $old_icon);
            }
        } else {
            $insfilename = $old_icon;
            echo "<script>alert('Failed to upload file.'); window.location='stockist-edit-ss?prid=" . base64_encode($update_id) . "&uploadfailed';</script>";
            exit;
        }
        
    } else {
        $insfilename = $old_icon;
    }
    
    // Update Stockist details using prepared statement
    $update_ss = "UPDATE stockiest SET 
                    user_icon = ?, 
                    name = ?, 
                    email = ?, 
                    mobile_number = ?, 
                    username = ?, 
                    address = ?, 
                    country_code = ?,
                    state_id = ?,
                    district_id = ?,
                    taluk_id = ?
                  WHERE id = ?";
    
    $stmt_update = mysqli_prepare($db_conn, $update_ss);
    
    if ($stmt_update) {
        mysqli_stmt_bind_param(
            $stmt_update, 
            "sssssssiiii", 
            $insfilename, 
            $name, 
            $email, 
            $mobile_number, 
            $mobile_number, 
            $address, 
            $country_code,
            $state_id,
            $dist_id,
            $taluk_id,
            $update_id
        );
        
        if (mysqli_stmt_execute($stmt_update)) {
            mysqli_stmt_close($stmt_update);
            
            // Update Stockist Category
            $st_cat_id = isset($_REQUEST['st_cat_id']) ? intval($_REQUEST['st_cat_id']) : 0;
            
            if ($st_cat_id > 0 && !empty($stockistid)) {
                $update_ssRFR = "UPDATE stockist_referral SET st_cat_id = ? WHERE stockist_id = ?";
                $stmt_rfr = mysqli_prepare($db_conn, $update_ssRFR);
                mysqli_stmt_bind_param($stmt_rfr, "is", $st_cat_id, $stockistid);
                mysqli_stmt_execute($stmt_rfr);
                mysqli_stmt_close($stmt_rfr);
            }
            
            echo "<script>window.location='stockist-manage?updatedSuccess';</script>";
        } else {
            mysqli_stmt_close($stmt_update);
            echo "<script>alert('Update failed. Please try again.'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('Database error. Please contact support.'); window.history.back();</script>";
    }
    
} else {
    echo "<script>window.location='stockist-add';</script>";
}
?>