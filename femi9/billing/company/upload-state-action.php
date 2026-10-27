<?php require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php"); require_once("include/PermissionCheck.php"); requirePermission('location');

if(isset($_POST["Import"])){
        
        $filename=$_FILES["file"]["tmp_name"];    
         if($_FILES["file"]["size"] > 0)
         {
            $file = fopen($filename, "r");
              while (($getData = fgetcsv($file, 10000, ",")) !== FALSE)
               {
				
				$st_name=$getData[0];
				
$selec_count_duplicate=mysqli_query($db_conn,"select count(*) as numitem from state where st_name='".db_esc($st_name)."'");
$fech_count_duplicate=mysqli_fetch_array($selec_count_duplicate);
if($fech_count_duplicate['numitem']==0)
{
				   
                 $import_action = "INSERT into state (st_name) values (?)";
                 $result = db_q($db_conn,$import_action,[$st_name]);
					   
}   
			
               }
          
               fclose($file);  
         }
		 
if(!isset($result))
            {
              echo "<script type=\"text/javascript\">
                  alert(\"Invalid File:Please Upload CSV File.\");
                  window.location = \"manage-state\"
                  </script>";    
            }
            else {
                echo "<script type=\"text/javascript\">
                alert(\"CSV File has been successfully Imported.\");
                window.location = \"manage-state\"
              </script>";
            }


      }   
?>
