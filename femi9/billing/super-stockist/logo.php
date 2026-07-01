<?php //login user details
$log_username=$_SESSION['LOGIN_USER'];
//
$select_superstock="select * from super_stockiest where username='$log_username'";
$fetch_superstock=mysqli_query($db_conn,$select_superstock);
$result_superstock=mysqli_fetch_array($fetch_superstock);

$usericon=$result_superstock['user_icon'];

if($result_superstock['onboard_userTYPE']=="candf")
{
$usericon_path="../c-and-f/";
}else{ $usericon_path="../company/";}


if($usericon!="Nil")
{
$usericon_concat="".$usericon_path."".$usericon."";
}else{
	$usericon_concat="../../assets/images/149071.png";
}

//State Details
$loguser_StateID=$result_superstock['state_id'];
$Select_StateNamevl12="select * from state where id='$loguser_StateID'";
$Fetch_StateNamevl12=mysqli_query($db_conn,$Select_StateNamevl12);
$Retch_StateNamevl12=mysqli_fetch_array($Fetch_StateNamevl12);
$loguser_StateNAME=$Retch_StateNamevl12['st_name'];

//District Details
$loguser_DistrictID=$result_superstock['district_id'];
$select_distname12="select * from district where id='$loguser_DistrictID'";
$fetch_distname12=mysqli_query($db_conn,$select_distname12);
$result_distname12=mysqli_fetch_array($fetch_distname12);
$loguser_DistrictNAME=$result_distname12['dist_name'];

$loguser_tempid=$result_superstock['temp_id'];

?>

<style type="text/css">
.usericon{width:45px;height:45px;border-radius:100%;}
#logoTablevl{border-collapse:collapse;width:100%;}
#logoTablevl td{padding:5px;}
#logoTablevl h1{font-size:15px;text-transform:capitalize;padding:0;margin:0;color:#ff0d2a;}
#logoTablevl h2{font-size:13px;text-transform:capitalize;color:#999;padding:0;margin:0;}
#logoTablevl h3{font-size:11px;color:#003333;padding:0;margin:0;font-weight:400;}
</style>
<div class="logo">


				<ul class="navbar-nav">
                               <li class="nav-item">
                                    <a class="nav-link hide-sidebar-toggle-button" href="#">
									<img src="../../assets/images/9534625.png">
									</a>
                                </li>
                            </ul>
</div>
<!--.app-sidebar .logo .sidebar-user-switcher img-->