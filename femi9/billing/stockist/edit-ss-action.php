<?php 
include("checksession.php");
include("config.php");

// ENABLE errors temporarily to debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("RemoveSpecialChar.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    $update_id = mysqli_real_escape_string($db_conn, $_REQUEST['update_id']);
    $old_icon = mysqli_real_escape_string($db_conn, $_REQUEST['old_icon']);
    
    $name = str_replace("'","&#39;", $_REQUEST['name']);
    $name = mysqli_real_escape_string($db_conn, $name);
    
    $mobile_number = str_replace("'","&#39;", $_REQUEST['mobile_number']);
    $mobile_number = mysqli_real_escape_string($db_conn, $mobile_number);
    
    $email = str_replace("'","&#39;", $_REQUEST['email']);
    $email = mysqli_real_escape_string($db_conn, $email);
    
    $address = str_replace("'","&#39;", $_POST["address"]);
    $address = mysqli_real_escape_string($db_conn, $address);
    
    $country_code = mysqli_real_escape_string($db_conn, $_POST["country_code"]);
    
    $gstin = str_replace("'","&#39;", $_REQUEST['gstin']);
    $gstin = RemoveSpecialChar($gstin);
    $gstin = mysqli_real_escape_string($db_conn, $gstin);
    
    $state_id = str_replace("'","&#39;", $_POST["state_id"]);
    $state_id = RemoveSpecialChar($state_id);
    $state_id = mysqli_real_escape_string($db_conn, $state_id);
    
    $district_id = str_replace("'","&#39;", $_POST["district_id"]);
    $district_id = RemoveSpecialChar($district_id);
    $district_id = mysqli_real_escape_string($db_conn, $district_id);
    
    $taluk_id = str_replace("'","&#39;", $_POST["taluk_id"]);
    $taluk_id = RemoveSpecialChar($taluk_id);
    $taluk_id = mysqli_real_escape_string($db_conn, $taluk_id);
    
    $pincode_id = str_replace("'","&#39;", $_POST["pincode_id"]);
    $pincode_id = RemoveSpecialChar($pincode_id);
    $pincode_id = mysqli_real_escape_string($db_conn, $pincode_id);
    
    // FIX: Check if checkbox exists
    $shop_onboard = isset($_POST["shop_onboard"]) ? $_POST["shop_onboard"] : 0;
    
    // Upload user icon
    $small_jpg = $_FILES['user_icon']['name'];
    
    if(!empty($small_jpg)) {
        
        $filetype = $_FILES['user_icon']['type'];
        
        if($filetype != 'image/jpeg' && $filetype != 'image/jpg' && $filetype != 'image/png') {
            $insfilename = $old_icon;
            echo "<script>window.location='edit-ss.php?prid=".base64_encode($update_id)."&imageinvalid';</script>";
            exit;
        } else {
            $rand_isd = rand(1, 9899989);
            $filename = $rand_isd . $small_jpg;
            $uploaddir = 'user_icon/';
            
            // Create directory if not exists
            if(!is_dir($uploaddir)) {
                mkdir($uploaddir, 0755, true);
            }
            
            $uploadfile = $uploaddir . $filename;
            
            if(move_uploaded_file($_FILES['user_icon']['tmp_name'], $uploadfile)) {
                $insfilename = $uploadfile;
                
                // Delete old icon
                if($old_icon != "Nil" && file_exists($old_icon)) {
                    unlink($old_icon);
                }
            } else {
                // FIX: Handle upload error
                echo "<script>alert('File upload failed!'); window.location='edit-ss.php?prid=".base64_encode($update_id)."';</script>";
                exit;
            }
        }
    } else {
        $insfilename = $old_icon;
    }
    
    // FIX: Added gstin to the update query
    $update_ss = "UPDATE distributor SET 
        user_icon='$insfilename',
        name='$name',
        email='$email',
        mobile_number='$mobile_number',
        username='$mobile_number',
        address='$address',
        country_code='$country_code',
        state_id='$state_id',
        district_id='$district_id',
        taluk_id='$taluk_id',
        pincode_id='$pincode_id',
        gstin='$gstin',
        shop_onboard='$shop_onboard' 
        WHERE id='$update_id'";
    
    // FIX: Check if query executed successfully
    if(!mysqli_query($db_conn, $update_ss)) {
        die("Error updating distributor: " . mysqli_error($db_conn));
    }
    
    // Update target amount
    $target_amount = mysqli_real_escape_string($db_conn, $_REQUEST['target_amount']);
    $distributor_id = mysqli_real_escape_string($db_conn, $_REQUEST['distributor_id']);
    
    // Update Distributor Referral
    $select_count_referral = "SELECT id FROM distributor_referral WHERE distributor_id='$distributor_id'";
    $fetch_count_referral = mysqli_query($db_conn, $select_count_referral);
    $result_count_referral = mysqli_num_rows($fetch_count_referral);
    
    if($result_count_referral == 0) {
        $insert_referral = "INSERT INTO distributor_referral 
            (distributor_id, target_amount, ref_by_user_type, ref_by_user_id, updated) 
            VALUES ('$distributor_id', '$target_amount', 'company', '', '0')";
        
        if(!mysqli_query($db_conn, $insert_referral)) {
            die("Error inserting referral: " . mysqli_error($db_conn));
        }
    } else {
        $update_referral = "UPDATE distributor_referral 
            SET target_amount='$target_amount' 
            WHERE distributor_id='$distributor_id'";
        
        if(!mysqli_query($db_conn, $update_referral)) {
            die("Error updating referral: " . mysqli_error($db_conn));
        }
    }
    
    echo "<script>window.location='manage_ss.php?updatedSuccess';</script>";
    exit;
    
} else {
    echo "<script>window.location='add_ss.php';</script>";
    exit;
}
?>