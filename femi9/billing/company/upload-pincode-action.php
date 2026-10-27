<?php require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php"); require_once("include/PermissionCheck.php"); requirePermission('location');
include("config.php");

if(isset($_POST["Import"])){
        
        $filename=$_FILES["file"]["tmp_name"];    
         if($_FILES["file"]["size"] > 0)
         {
            $file = fopen($filename, "r");
              while (($getData = fgetcsv($file, 10000, ",")) !== FALSE)
               {
				
				$state_id=$getData[0];
				$dist_id=$getData[1];
				$taluk_id=$getData[2];
				$pincode=$getData[3];
				
$select_count_product="select count(*) as numpincode from pincode where state_id=? and dist_id=? and taluk_id=? and pincode=?";
$fech_count_duplicate=db_q($db_conn,$select_count_product,[$state_id,$dist_id,$taluk_id,$pincode]);
$result_count_duplicate=mysqli_fetch_array($fech_count_duplicate);
if($result_count_duplicate['numpincode']==0)
{
				   
				  $insert_products="insert into pincode 
		(state_id,dist_id,taluk_id,pincode,usertype,userid,assigned_SID,assigned_DID) values 
		(?,?,?,?,
		?,?,'Nil','Nil')";
                 $result = db_q($db_conn,$insert_products,[$state_id,$dist_id,$taluk_id,$pincode,$Login_user_TYPEvl,$Login_user_IDvl]);
					   
}   
			
               }
          
               fclose($file);  
         }
		 
if(!isset($result))
            {
              echo "<script type=\"text/javascript\">
                  alert(\"Invalid File:Please Upload CSV File.\");
                  window.location = \"manage-pincode\"
                  </script>";    
            }
            else {
                echo "<script type=\"text/javascript\">
                alert(\"CSV File has been successfully Imported.\");
                window.location = \"manage-pincode\"
              </script>";
            }


      }   
?>
