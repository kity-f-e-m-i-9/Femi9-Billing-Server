<!---add page--->

<?php if(isset($_REQUEST['invoicealready'])){?><div class="alert alert-danger">Invoice Number already exists!</div>
									<?php }?>


<!-------------INVOICE NUMBER------------->	
<script type="text/javascript">
function showInvoiceDuplicate(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintInvoice").innerHTML=xmlhttp.responseText;}}
xmlhttp.open("GET","load_InvoiceNumber_customer.php?q="+str,true);
xmlhttp.send();}
</script>
			<label class="form-label">Invoice Number *</label>
            <input type="text" onKeyup="showInvoiceDuplicate(this.value)"; name="inv_number" autofocus required="" onkeypress="restrictSpecialChars(event)" class="form-control">
			<br/>
			<span id="txtHintInvoice"></span>
			
			
			



<!-----action page------->
		
			
			//invoice accept=0
	if($_REQUEST['invoice_number_accept']==0)
	{
	$_SESSION['errorMessage']="Invoice Number already exists!";
	echo "<script>window.location='customer-user-invoice-add.php?invoicealready';</script>";
	}else{
	$inv_number=str_replace("'","",$_REQUEST['inv_number']);
	$id_only="0";
	//HIDE AUTO INVOICE NUMBER -> below
	//---------------	