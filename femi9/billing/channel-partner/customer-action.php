<?php require_once __DIR__ . '/../shared/db-helpers.php';
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
        "SELECT COUNT(*) AS n FROM customers WHERE mobile='".db_esc($mobile)."' AND user_type='".db_esc($user_type)."' AND user_id='".db_esc($user_id)."'"));
    if ($chk['n'] == 0) {
        $maxRow     = mysqli_fetch_array(mysqli_query($db_conn, "SELECT MAX(userid) AS numid FROM customers"));
        $userid     = (int)$maxRow['numid'] + 1;
        $useridtext = "FEMI9-" . str_pad($userid, 3, '0', STR_PAD_LEFT);

        mysqli_query($db_conn, "INSERT INTO customers
            (name,mobile,email,address,marketing_date,date,user_type,user_id,gstin,userid,useridtext,country_code)
            VALUES ('".db_esc($name)."','".db_esc($mobile)."','".db_esc($email)."','".db_esc($address)."','".db_esc($marketing_date)."','".db_esc($date)."',
            '".db_esc($user_type)."','".db_esc($user_id)."','".db_esc($gstin)."','".db_esc($userid)."','".db_esc($useridtext)."','".db_esc($country_code)."')");

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
        name='".db_esc($name)."', mobile='".db_esc($mobile)."', email='".db_esc($email)."', address='".db_esc($address)."',
        marketing_date='".db_esc($marketing_date)."', date='".db_esc($date)."', gstin='".db_esc($gstin)."', country_code='".db_esc($country_code)."'
        WHERE id='".db_esc($update_id)."'");

    echo "<script>window.location='customer-manage.php?updatedSuccess';</script>";
    exit;
}
?>
