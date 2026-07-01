<?php include("checksession.php");

$pamnet_method=$_REQUEST['q'];

if($pamnet_method=="Payment")
{
?>
<label class="form-label">Upload Payent Screenshot *</label>
<input type="file" required="" name="screenshot" class="form-control"><br/>									
<?php }?>

