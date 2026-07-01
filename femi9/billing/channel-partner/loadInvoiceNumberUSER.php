<?php
include("checksession.php");
include("config.php");

$invnumber = $_REQUEST['q'] ?? '';

$result = mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COUNT(*) AS n FROM user_invoice WHERE inv_number='$invnumber' AND from_user_type='$Login_user_TYPEvl' AND from_user_id='$Login_user_IDvl'"));
if ((int)$result['n'] === 0) { ?>
<input type="hidden" name="invoice_number_accept" value="1">
<?php } else { ?>
<input type="hidden" name="invoice_number_accept" value="0">
<div class="alert alert-custom" role="alert">
    <div class="custom-alert-icon icon-danger"><i class="material-icons-outlined">error</i></div>
    <div class="alert-content">
        <span class="alert-title">Warning !</span>
        <span class="alert-text">Invoice Number already exists.</span>
    </div>
</div>
<?php }
