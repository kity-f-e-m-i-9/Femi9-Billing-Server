<?php include("checksession.php");
$st_user_type=$_GET['q']; 
error_reporting(0);
		
		if($st_user_type=="super_stockiest"){
		$tblename="super_stockiest";
		}
		else if($st_user_type=="stockiest"){
			$tblename="stockiest";
		}
		else if($st_user_type=="super_distributor"){
			$tblename="super_distributor";
		}
		else{
			$tblename="distributor";
		}
	?>
	
	<label for="exampleInputEmail1" class="form-label">User Name, Mobile</label>
    <select required name="to_userid" class="form-control">
	<option value="" hidden="">Select</option>
	<?php 
	if($st_user_type!="super_stockiest")
	{
	$select_usrdetails="select temp_id,name,mobile_number,district_id,taluk_id 
	from ".$tblename." order by name asc";
	}
	else
	{
	$select_usrdetails="select temp_id,name,mobile_number,district_id 
	from ".$tblename." order by name asc";	
	}
	
	
		$fetch_userdetails=mysqli_query($db_conn,$select_usrdetails);
		while($result_userdetails=mysqli_fetch_array($fetch_userdetails)){
			
			//District
			$usr_district_id=$result_userdetails['district_id'] ?? 0;
			if(is_numeric($usr_district_id))
{	
$select_distict="select * from district where id='$usr_district_id'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'] ?? 'Nil';
}else{
	$district_name=$usr_district_id;
}

if($st_user_type!="super_stockiest")
{
//Taluk
			$usr_taluk_id=$result_userdetails['taluk_id'] ?? 0;
			if(is_numeric($usr_taluk_id))
{	
$select_taluk="select taluk from taluk where id='$usr_taluk_id'";
	$fetch_taluk=mysqli_query($db_conn,$select_taluk);
	$result_taluk=mysqli_fetch_array($fetch_taluk);
$talk_name=	$result_taluk['taluk'] ?? 'Nil';
}else{
	$talk_name=$usr_taluk_id;
}
}
else{
	$talk_name="Nil";
}
			
			?>
		 <option value="<?=$result_userdetails['temp_id'];?>"><?=ucwords($result_userdetails['name']);?>, <?=$result_userdetails['mobile_number'];?>, DT:<?=ucwords($district_name);?>, TLK:<?=ucwords($talk_name)?></option>
		<?php }?>
							   </select>

 