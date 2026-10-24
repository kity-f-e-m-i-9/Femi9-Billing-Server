<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requireAnyPermission(['cus','shop','ss']);
include("config.php");
require_once("include/GodownAccess.php");

$godownid=$_REQUEST['q'];

$select_Godowndetails="select * from company_godown where id='".db_esc($godownid)."' AND " . godown_finance_filter_sql($db_conn);
$fetch_Godowndetails=mysqli_query($db_conn,$select_Godowndetails);
$result_Godown=mysqli_fetch_array($fetch_Godowndetails);

$select_count_opstock13="select count(*) as numopstock12 from stock where user_type=? and user_id=?";
$fetch_count_opstock13=db_q($db_conn,$select_count_opstock13,[$Login_user_TYPEvl,$godownid]);
$result_count_opstock13=mysqli_fetch_array($fetch_count_opstock13);
if($result_count_opstock13['numopstock12']==0)
{
?>
<div style="background:red;color:white;padding:5px;border-radius:5px;margin-bottom:15px;">Please update opening stock (<?=htmlspecialchars((string)($result_Godown['gname']), ENT_QUOTES, 'ISO-8859-1', false);?>)</div>	
<?php }?>