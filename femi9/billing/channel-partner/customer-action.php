<?php
include("checksession.php");
include("config.php");
error_reporting(0);

if (isset($_REQUEST['add-customer'])) {
    $actionpage = $_REQUEST['actionpage'] ?? '';
    if ($actionpage == "invoiceadd") {
        $addurl  = "customer-invoice-add.php?alreadyexists";
        $viewurl = "customer-invoice-add.php?addesuccess";
    } else {
        $addurl  = "customer-add.php?alreadyexists";
        $viewurl = "customer-manage.php?addesuccess";
    }

    $name           = str_replace("'", "&#39;", $_REQUEST['name'] ?? '');
    $mobile         = str_replace("'", "&#39;", $_REQUEST['mobile'] ?? '');
    $email          = str_replace("'", "&#39;", $_REQUEST['email'] ?? '');
    $gstin          = str_replace("'", "&#39;", $_REQUEST['gstin'] ?? '');
    $address        = str_replace("'", "&#39;", $_REQUEST['address'] ?? '');
    $marketing_date = date("Y-m-d", strtotime($_REQUEST['marketing_date'] ?? date("Y-m-d")));
    $date           = date("d", strtotime($marketing_date));
    $country_code   = $_POST['country_code'] ?? '';
    $user_type      = $Login_user_TYPEvl;
    $user_id        = $Login_user_IDvl;

    $chk = mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT COUNT(*) AS n FROM customers WHERE mobile='$mobile' AND user_type='$user_type' AND user_id='$user_id'"));
    if ($chk['n'] == 0) {
        $maxRow     = mysqli_fetch_array(mysqli_query($db_conn, "SELECT MAX(userid) AS numid FROM customers"));
        $userid     = (int)$maxRow['numid'] + 1;
        $useridtext = "FEMI9-" . str_pad($userid, 3, '0', STR_PAD_LEFT);

        mysqli_query($db_conn, "INSERT INTO customers
            (name,mobile,email,address,marketing_date,date,user_type,user_id,gstin,userid,useridtext,country_code)
            VALUES ('$name','$mobile','$email','$address','$marketing_date','$date',
            '$user_type','$user_id','$gstin','$userid','$useridtext','$country_code')");

        echo "<script>window.location='$viewurl';</script>";
    } else {
        echo "<script>window.location='$addurl';</script>";
    }
    exit;
}

if (isset($_REQUEST['update-customer'])) {
    $update_id      = (int)($_REQUEST['update_id'] ?? 0);
    $name           = str_replace("'", "&#39;", $_REQUEST['name'] ?? '');
    $mobile         = str_replace("'", "&#39;", $_REQUEST['mobile'] ?? '');
    $email          = str_replace("'", "&#39;", $_REQUEST['email'] ?? '');
    $gstin          = str_replace("'", "&#39;", $_REQUEST['gstin'] ?? '');
    $address        = str_replace("'", "&#39;", $_REQUEST['address'] ?? '');
    $marketing_date = date("Y-m-d", strtotime($_REQUEST['marketing_date'] ?? date("Y-m-d")));
    $date           = date("d", strtotime($marketing_date));
    $country_code   = $_POST['country_code'] ?? '';

    mysqli_query($db_conn, "UPDATE customers SET
        name='$name', mobile='$mobile', email='$email', address='$address',
        marketing_date='$marketing_date', date='$date', gstin='$gstin', country_code='$country_code'
        WHERE id='$update_id'");

    echo "<script>window.location='customer-manage.php?updatedSuccess';</script>";
    exit;
}
?>
