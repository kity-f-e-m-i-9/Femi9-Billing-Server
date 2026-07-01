<?php include("checksession.php"); 
error_reporting(0);
include("RemoveSpecialChar.php");

if(isset($_REQUEST['UpdateAction']))
{

$request_row_id = (int)($_REQUEST['request_row_id'] ?? 0);
$updated_date   = mysqli_real_escape_string($db_conn, $_REQUEST['updated_date']   ?? date('Y-m-d'));
$updated_time   = mysqli_real_escape_string($db_conn, $_REQUEST['updated_time']   ?? date('H:i:s'));
$TDS_percentage = (float)($_REQUEST['TDS_percentage'] ?? 0);
$TDS_deduction  = (float)($_REQUEST['TDS_deduction']  ?? 0);
$sent_amount    = (float)($_REQUEST['sent_amount']     ?? 0);

$remarks = str_replace("'","&#39;",$_POST["remarks"] ?? '');
$remarks = RemoveSpecialChar($remarks);
$remarks = mysqli_real_escape_string($db_conn, $remarks);

// Guard: only approve requests that are currently pending
$check = mysqli_fetch_assoc(mysqli_query($db_conn,
    "SELECT req_status FROM wallet_withdraw WHERE id='$request_row_id' LIMIT 1"));
if (!$check || $check['req_status'] !== 'pending') {
    $_SESSION['errorMessage'] = "Request is not in pending status — cannot approve.";
    echo "<script>window.location='wallet_request';</script>";
    exit;
}

$update_aproved = "UPDATE wallet_withdraw SET
    remarks='$remarks',
    updated_date='$updated_date',
    updated_time='$updated_time',
    TDS_percentage='$TDS_percentage',
    TDS_deduction='$TDS_deduction',
    sent_amount='$sent_amount',
    req_status='approved'
    WHERE id='$request_row_id' AND req_status='pending'";
mysqli_query($db_conn, $update_aproved);

$_SESSION['successMessage']="Withdraw Request Approved Success";
echo "<script>window.location='wallet_request?approvedsuccess';</script>";

//!isset - redirect page
}else{ echo "<script>window.location='dashboard';</script>";}
?>
