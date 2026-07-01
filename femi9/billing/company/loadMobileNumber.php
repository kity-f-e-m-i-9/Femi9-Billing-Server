<?php include("checksession.php");

$mobilenumber=$_REQUEST['q'];
$invuser=$_REQUEST['invuser'];

if($invuser=="super_stockiest")
{
	$tablename="super_stockiest";
	}
else if($invuser=="stockiest")
{
	$tablename="stockiest";
	}
else if($invuser=="candf")
{
	$tablename="c_and_f";
	}
else 
{
	$tablename="distributor";
}
	

$Select_Count_MobilenUmber="select count(*) as numMob from ".$tablename." where mobile_number='$mobilenumber'";
$Fetch_Count_MobilenUmber=mysqli_query($db_conn,$Select_Count_MobilenUmber);
$Result_Count_MobilenUmber=mysqli_fetch_array($Fetch_Count_MobilenUmber);
if($Result_Count_MobilenUmber['numMob']==0){?>

<div class="alert alert-custom" role="alert">
                                                    <div class="custom-alert-icon icon-success"><i class="material-icons-outlined">done</i></div>
                                                    <div class="alert-content">
                                                        <span class="alert-title">Success !</span>
														<span class="alert-text">Mobile Number Available.</span>
                                                    </div>
                                                </div>
												
<?php }else{?>

<div class="alert alert-custom" role="alert">
                                                    <div class="custom-alert-icon icon-danger"><i class="material-icons-outlined">error</i></div>
                                                    <div class="alert-content">
                                                        <span class="alert-title">Warning !</span>
														<span class="alert-text">Mobile Number already exists.</span>
                                                    </div>
                                                </div>

<?php }?>