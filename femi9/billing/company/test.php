<b>&#8377; </b>
<?=$Currency_symbol;?>&nbsp;

<b>INR </b>
<?=$Currency_Name;?>&nbsp;


<!-------------------------------------->
<!-------------Currency----------------->
<?php
if($_REQUEST['crcode']=="Default" || $_REQUEST['crcode']==NULL)
{
$Currency_symbol="&#8377;";
$Currency_Name="INR";
}
else{
$get_ccode=base64_decode($_REQUEST['crcode']);
//
$select_currency223="select * from country where id='$get_ccode'";
$fetch_currency223=mysqli_query($db_conn,$select_currency223);
$result_currency223=mysqli_fetch_array($fetch_currency223);
//
$Currency_symbol="&#".$result_currency223['currency_ascii_code'].";";
$Currency_Name=$result_currency223['currency_name'];
}
?>

<div style="clear:both;"></div>
<div align="center">
<select name="currency_code" class="form-control" style="padding:5px;width:180px;margin-left:-10px;" id="currencySelect">
			<?php if($get_ccode==NULL){?>
			<option value="" hidden>Currency</option>
<?php }else{?>
<option hidden><?=ucwords($result_currency223['c_name']);?> - <?=ucwords($result_currency223['currency_name']);?></option>
<?php }?>
			<option value="Default">Default</option>
			<?php $select_currency="select * from country where currency_name!='' order by c_name asc";
$fetch_currency=mysqli_query($db_conn,$select_currency);
while($result_currency=mysqli_fetch_array($fetch_currency))
{
			?>
			<option value="<?=base64_encode($result_currency['id']);?>"><?=ucwords($result_currency['c_name']);?> - <?=ucwords($result_currency['currency_name']);?></option>
<?php }?>
			</select>
			</div>
			
			<script>
    document.getElementById("currencySelect").addEventListener("change", function() {
        let selectedValue = this.value;
        if (selectedValue) {
            window.location.href = "user-invoice-print?invoiceid=<?=$_REQUEST['invoiceid'] ?>&crcode=" + selectedValue;
        }
    });
</script>
<!------------Currency end ***---------->
<!-------------------------------------->

user-invoice-print
shop-user-invoice-print
customer-user-invoice-print

