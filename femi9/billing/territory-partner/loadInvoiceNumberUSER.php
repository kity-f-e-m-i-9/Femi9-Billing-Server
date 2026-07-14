<?php
include("checksession.php");
include("config.php");

$invnumber = $_REQUEST['q'] ?? '';
// Excludes the invoice currently being edited, so retyping the same number
// it already has isn't flagged as a duplicate of itself.
$exclude_inv_id = $_REQUEST['exclude'] ?? '';

if ($exclude_inv_id !== '') {
    $stmt = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM user_invoice WHERE inv_number=? AND from_user_type=? AND from_user_id=? AND inv_id<>?"
    );
    $stmt->bind_param('ssss', $invnumber, $Login_user_TYPEvl, $Login_user_IDvl, $exclude_inv_id);
} else {
    $stmt = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM user_invoice WHERE inv_number=? AND from_user_type=? AND from_user_id=?"
    );
    $stmt->bind_param('sss', $invnumber, $Login_user_TYPEvl, $Login_user_IDvl);
}
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

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
