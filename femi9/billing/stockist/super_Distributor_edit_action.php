<?php 
include("checksession.php");
include("config.php"); // Make sure this is included!

// Enable temporarily for debugging, then disable
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("RemoveSpecialChar.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    $update_id = mysqli_real_escape_string($db_conn, $_REQUEST['update_id']);
    $old_icon = mysqli_real_escape_string($db_conn, $_REQUEST['old_icon']);
    
    $name = str_replace("'","&#39;", $_REQUEST['name']);
    $name = RemoveSpecialChar($name);
    $name = mysqli_real_escape_string($db_conn, $name);
    
    $country_code = mysqli_real_escape_string($db_conn, $_POST["country_code"]);
    
    $mobile_number = str_replace("'","&#39;", $_REQUEST['mobile_number']);
    $mobile_number = RemoveSpecialChar($mobile_number);
    $mobile_number = mysqli_real_escape_string($db_conn, $mobile_number);
    
    $email = str_replace("'","&#39;", $_REQUEST['email']);
    $email = RemoveSpecialChar($email);
    $email = mysqli_real_escape_string($db_conn, $email);
    
    $address = str_replace("'","&#39;", $_POST["address"]);
    $address = RemoveSpecialChar($address);
    $address = mysqli_real_escape_string($db_conn, $address);
    
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
    
    // FIX: Check if checkbox exists before using it
    $shop_onboard = isset($_POST["shop_onboard"]) ? $_POST["shop_onboard"] : 0;
    
    // Upload user icon
    $small_jpg = $_FILES['user_icon']['name'];
    
    if(!empty($small_jpg)) {
        
        $filetype = $_FILES['user_icon']['type'];
        
        if($filetype != 'image/jpeg' && $filetype != 'image/jpg' && $filetype != 'image/png') {
            $insfilename = $old_icon;
            echo "<script>window.location='super_Distributor_edit?prid=".base64_encode($update_id)."&imageinvalid';</script>";
            exit;
        } else {
            $rand_isd = rand(1, 9899989);
            $filename = $rand_isd . $small_jpg;
            $uploaddir = '../super_distributor/user_icon/';
            
            // Create directory if not exists
            if(!is_dir($uploaddir)) {
                mkdir($uploaddir, 0755, true);
            }
            
            $uploadfile = $uploaddir . $filename;
            
            if(move_uploaded_file($_FILES['user_icon']['tmp_name'], $uploadfile)) {
                $insfoldername = "user_icon/";
                $insfilename = $insfoldername . $filename;
                
                // Delete old icon
                if($old_icon != "Nil" && file_exists("../super_distributor/" . $old_icon)) {
                    unlink("../super_distributor/" . $old_icon);
                }
            } else {
                // Handle upload error
                echo "<script>alert('File upload failed!'); window.location='super_Distributor_edit?prid=".base64_encode($update_id)."';</script>";
                exit;
            }
        }
    } else {
        $insfilename = $old_icon;
    }
    
    // Update process
    $update_ss = "UPDATE super_distributor SET 
        user_icon='$insfilename',
        name='$name',
        email='$email',
        address='$address',
        gstin='$gstin',
        country_code='$country_code',
        state_id='$state_id',
        district_id='$district_id',
        taluk_id='$taluk_id',
        pincode_id='$pincode_id',
        shop_onboard='$shop_onboard' 
        WHERE id='$update_id'";
    
    if(!mysqli_query($db_conn, $update_ss)) {
        die("Error updating super_distributor: " . mysqli_error($db_conn));
    }
    
    // Update target amount
    $target_amount = mysqli_real_escape_string($db_conn, $_REQUEST['target_amount']);
    $sd_id = mysqli_real_escape_string($db_conn, $_REQUEST['sd_id']);
    
    $update_referral = "UPDATE super_distributor_referral 
        SET target_amount='$target_amount' 
        WHERE sd_id='$sd_id'";
    
    if(!mysqli_query($db_conn, $update_referral)) {
        die("Error updating referral: " . mysqli_error($db_conn));
    }
    
    echo "<script>window.location='super_Distributor_manage?updatedSuccess';</script>";
    exit;
    
} else {
    echo "<script>window.location='dashboard';</script>";
    exit;
}
?>