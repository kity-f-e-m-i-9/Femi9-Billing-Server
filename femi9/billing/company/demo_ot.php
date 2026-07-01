
<form action="demo_ot_action" method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">


<?php function GeraHash($qtd){ $Caracteres = '123456789'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } $randum_number=GeraHash(5);
$inv_randum_no=$randum_number=GeraHash(3);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("gis"); 
$tempid="".$randum_number."RTST/".$temp_date."/".$temp_time."";
?>

<input type="hidden" name="tempid" value="<?=$tempid?>">
<input type="hidden" name="randumnumber" value="<?=$inv_randum_no?>">

<input type="hidden" name="username" value="<?=$_SESSION['LOGIN_USER'];?>">
<input type="hidden" name="usertype" value="<?=$Result_Log_users_Dtails134['usertype'];?>">

                                        <div class="example-container">
                                        <div class="example-content">
								
						
							   <label for="exampleInputEmail1" class="form-label">Company Profile</label>
                               <select required="" name="godownid" class="form-control">
							   <option value="" hidden="">Select</option>
						       <option value="1">Femi9</option>
							   </select>
							   <br/>
<!------------------------------------GODOWN------------------------------>	

<label for="exampleInputEmail1" class="form-label">State Name*</label>
                               <select required="" name="state_id" class="form-control">
							   <option value="" hidden="">Select</option>
							   <option value="1">Tamilnadu</option>
							   </select>
							   <br/>
							   
							   <input type="hidden" name="admin_state_id" value="1">

<label class="form-label">Date*</label>
<input type="date" required name="date" value="<?php echo date("Y-m-d");?>" class="form-control">
<br/>
          
<label class="form-label">Category*</label>
<select required="" name="catname" class="form-control">
<option value="" hidden="">Select</option>
<option value="1">Amazon</option>
<option value="2">Flipkart</option>

</select>
<br/>			

			<label class="form-label">Invoice Number *</label>
            <input type="text" name="inv_number" required="">
			<br/>

 <label class="form-label">Customer Name*</label>
            <input type="text" required="" name="customer_name">
			<br/>
			
			<label class="form-label">Customer Mobile</label>
           <input type="text" name="customer_mobile">
			<br/>
			
			<label class="form-label">Billing Address*</label>
            <textarea name="customer_address" required="required"></textarea>
			<br/>
			
			<label class="form-label">Shipping Address*</label>
            <textarea name="shipping_address" required="required"></textarea>
			<br/>
			
			<label class="form-label">GST Number</label>
            <input type="text" name="gst_number">	
			<br/>
			
			<label class="form-label">Order Number</label>
            <input type="text" name="order_number">
			<br/>
			
			<label class="form-label">Order Date</label>
            <input type="date" name="order_date">
			<br/>
			
			<label class="form-label">Ship Date</label>
            <input type="date" name="ship_date">
			<br/>
			
			<label class="form-label">Courier Charges(Rs.) *</label>
            <input type="number" min="0" name="courier_charges" required>
			<br/>

<script>
        function addRow(tableID) {
	var table = document.getElementById(tableID);
	var rowCount = table.rows.length;
	if(rowCount < 100){							// limit the user from creating fields more than your limits
		var row = table.insertRow(rowCount);
		var colCount = table.rows[0].cells.length;
		for(var i=0; i<colCount; i++) {
			var newcell = row.insertCell(i);
			newcell.innerHTML = table.rows[0].cells[i].innerHTML;
		}
	}else{
		 alert("Maximum Passenger per ticket is 100.");
			   
	}
}
function deleteRow(tableID) {
	var table = document.getElementById(tableID);
	var rowCount = table.rows.length;
	for(var i=0; i<rowCount; i++) {
		var row = table.rows[i];
		var chkbox = row.cells[0].childNodes[0];
		if(null != chkbox && true == chkbox.checked) {
			if(rowCount <= 1) { 						// limit the user from removing all the fields
				alert("Cannot Remove all Field .");
				break;
			}
			table.deleteRow(i);
			rowCount--;
			i--;
		}
	}
}</script> 
				
				<p> 
					<button type="button" class="btn btn-primary btn-burger" onClick="addRow('dataTable')"><i class="material-icons">add</i></button> 
					<button type="button" class="btn btn-danger btn-burger" onClick="deleteRow('dataTable')"><i class="material-icons">delete_outline</i></button>
				</p>
				
				 <table id="dataTable" border="0">
                    <tr>
						<td><input type="checkbox" name="chk[]"/></td>
						 <td>
							<select required="" name="product_id[]" class="form-control" required="">
<option value="" hidden="">Select Product</option>
<option value="1">235mm</option>
<option value="2">280mm</option>
</select>
					     </td>
						 <td>
						 <input type="number" placeholder="Qty" min="0" name="qty[]" class="form-control" required=""/>
						 </td>
						  <td>
						 <input type="number" placeholder="Rate(Rs.)" min="0" name="rate[]" class="form-control" required=""/>
						 </td>
						 <td>
						 <input type="number" placeholder="Discount(Rs.)" min="0" name="discount[]" class="form-control" required=""/>
						 </td>
                    </tr>
                </table>
												
				<br/>					

			<span id="opstock">									
<button type="submit" name="add-record" class="btn btn-primary"><i class="material-icons">add</i>Submit</button>
</span>
												
                                            </div>
                                        </div>
										</form>
	 
									