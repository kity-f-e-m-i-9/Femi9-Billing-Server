<?php include("checksession.php");
include("config.php");
error_reporting(0);


//--------STOCKIST ASSIGNED TO -----------------------------
if(isset($_REQUEST['REMAPPING_SUPERSTOCKIST']))
{
$to_usertype=$_REQUEST['to_usertype'];
$to_user_id=$_REQUEST['to_user_id'];
	
$stockistid = implode("#",$_REQUEST['stockistid']);
$stockistid_ex = explode ("#",$stockistid); 

$number = count($stockistid_ex); 
for ($i=0; $i<=$number; $i++) 
{ 
$stockistid_value = $stockistid_ex[$i];
if($stockistid_value!=NULL)
{
$updatemapping="update stockiest set onboard_userID='$to_user_id',
onboard_userTYPE='$to_usertype' where id='$stockistid_value'";
mysqli_query($db_conn,$updatemapping);
}
	 
} 
echo "<script>window.location='remapping-sst?mappingsuccess';</script>";

}
	
	

//-----------DISTRIBUTOR ASSIGNED TO -------------------------
if(isset($_REQUEST['REMAPPING2']))
{	

$to_usertype=$_REQUEST['to_usertype'];
$to_user_id=$_REQUEST['to_user_id'];
	
$distributorid = implode("#",$_REQUEST['distributorid']);
$distributorid_ex = explode ("#",$distributorid); 

$number = count($distributorid_ex); 
for ($i=0; $i<=$number; $i++) 
{ 

$distributorid_value = $distributorid_ex[$i];
if($distributorid_value!=NULL)
{
$updatemapping="update distributor set onboard_userID='$to_user_id',stockiest_id='$to_user_id',onboard_userTYPE='$to_usertype' where id='$distributorid_value'";
mysqli_query($db_conn,$updatemapping);
}
	 
} 
echo "<script>window.location='remapping_distributor?mappingsuccess';</script>";

}


//---------------Super Distributor ASSIGNED TO ---------------------------------
if(isset($_REQUEST['REMAPPING3']))
{
	
$to_usertype=$_REQUEST['to_usertype'];
$to_user_id=$_REQUEST['to_user_id'];
	
$distributorid = implode("#",$_REQUEST['distributorid']);
$distributorid_ex = explode ("#",$distributorid); 

$number = count($distributorid_ex); 
for ($i=0; $i<=$number; $i++) 
{ 

$distributorid_value = $distributorid_ex[$i];
if($distributorid_value!=NULL)
{
$updatemapping="update super_distributor set onboard_userID='$to_user_id',stockiest_id='$to_user_id',onboard_userTYPE='$to_usertype' where id='$distributorid_value'";
mysqli_query($db_conn,$updatemapping);
}
	 
} 
echo "<script>window.location='remapping_superdistributor?mappingsuccess';</script>";
	
}



//---------------Shops ASSIGNED TO ---------------------------------
if(isset($_REQUEST['REMAPPING4']))
{
	
$to_usertype=$_REQUEST['to_usertype'];
$to_user_id=$_REQUEST['to_user_id'];
	
$distributorid = implode("#",$_REQUEST['distributorid']);
$distributorid_ex = explode ("#",$distributorid); 

$number = count($distributorid_ex); 
for ($i=0; $i<=$number; $i++) 
{ 

$distributorid_value = $distributorid_ex[$i];
if($distributorid_value!=NULL)
{
$updatemapping="update shop set onboard_userID='$to_user_id',distributor_id='$to_user_id',onboard_userTYPE='$to_usertype' where id='$distributorid_value'";
mysqli_query($db_conn,$updatemapping);
}
	 
} 
echo "<script>window.location='remapping_shop?mappingsuccess';</script>";
	
}

?>