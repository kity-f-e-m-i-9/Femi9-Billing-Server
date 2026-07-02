<?php include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");

$godownid=$_REQUEST['q'];

$select_Godowndetails="select * from company_godown where id='".$godownid."' AND " . godown_finance_filter_sql($db_conn);
$fetch_Godowndetails=mysqli_query($db_conn,$select_Godowndetails);
$result_Godown=mysqli_fetch_array($fetch_Godowndetails);

$select_count_opstock13="select count(*) as numopstock12 from stock where user_type='$Login_user_TYPEvl' and user_id='$godownid'";
$fetch_count_opstock13=mysqli_query($db_conn,$select_count_opstock13);
$result_count_opstock13=mysqli_fetch_array($fetch_count_opstock13);
if($result_count_opstock13['numopstock12']==0)
{
?>
<div style="background:red;color:white;padding:5px;border-radius:5px;margin-bottom:15px;">Please update opening stock (<?=$result_Godown['gname'];?>)</div>	
<?php }else{?>
<button type="submit" name="add-record" class="btn btn-primary"><i class="material-icons">add</i>Submit</button>
<?php }?>