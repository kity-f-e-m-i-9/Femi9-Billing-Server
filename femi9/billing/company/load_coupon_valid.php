<?php 
include("checksession.php");

$coupon_code = $_REQUEST['q'] ?? '';

// Extract user type code (assuming format like "FEMI9D976" where D is the type)
preg_match('/[A-Z]+\d+([A-Z]+)\d+/', $coupon_code, $matches);
$from_usertype = $matches[1] ?? '';

// Determine table name based on user type
$tablename = '';
$usertype_print = '';

switch($from_usertype) {
    case 'SS':
        $tablename = 'super_stockiest';
        $usertype_print = 'super_stockiest';
        break;
    case 'S':
        $tablename = 'stockiest';
        $usertype_print = 'stockiest';
        break;
    case 'SD':
        $tablename = 'super_distributor';
        $usertype_print = 'super_distributor';
        break;
    case 'D':
        $tablename = 'distributor';
        $usertype_print = 'distributor';
        break;
    default:
        echo "<span style='color:white;background:red;font-weight:bold;padding:4px;border-radius:4px;'>Invalid Coupon Format</span>";
        exit;
}

// Use prepared statement to prevent SQL injection
$stmt = $db_conn->prepare("SELECT id FROM $tablename WHERE useridtext = ?");
$stmt->bind_param("s", $coupon_code);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 1) {
    echo "<span style='color:white;background:green;font-weight:bold;padding:4px;border-radius:4px;'>Valid Coupon</span>";
} else {
    echo "<span style='color:white;background:red;font-weight:bold;padding:4px;border-radius:4px;'>Invalid Coupon</span>";
}

$stmt->close();
?>