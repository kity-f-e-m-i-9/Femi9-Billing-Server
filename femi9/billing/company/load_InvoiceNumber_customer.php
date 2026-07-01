<?php include("checksession.php");
include("config.php");

$invnumber=$_REQUEST['q'];

$Select_Count_Invoice="select * from invoice where inv_number='$invnumber' and user_type='$Login_user_TYPEvl'";
$Fetch_Count_Invoice=mysqli_query($db_conn,$Select_Count_Invoice);
$Result_Count_Invoice=mysqli_num_rows($Fetch_Count_Invoice);
if($Result_Count_Invoice==0){?>
<input type="hidden" name="invoice_number_accept" value="1">

<?php }else{?>
<input type="hidden" name="invoice_number_accept" value="0">

<div class="alert alert-custom" role="alert">
                                                    <div class="custom-alert-icon icon-danger"><i class="material-icons-outlined">error</i></div>
                                                    <div class="alert-content">
                                                        <span class="alert-title">Warning !</span>
														<span class="alert-text">Invoice Number already exists.</span>
                                                    </div>
                                                </div>
<?php }?>