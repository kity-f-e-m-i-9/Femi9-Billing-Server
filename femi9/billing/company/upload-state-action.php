<?php
include("checksession.php");

if(isset($_POST["Import"])){
        
        $filename=$_FILES["file"]["tmp_name"];    
         if($_FILES["file"]["size"] > 0)
         {
            $file = fopen($filename, "r");
              while (($getData = fgetcsv($file, 10000, ",")) !== FALSE)
               {
				
				$st_name=$getData[0];
				
$selec_count_duplicate=mysqli_query($db_conn,"select count(*) as numitem from state where st_name='$st_name'");
$fech_count_duplicate=mysqli_fetch_array($selec_count_duplicate);
if($fech_count_duplicate['numitem']==0)
{
				   
                 $import_action = "INSERT into state (st_name) values ('$st_name')";
                 $result = mysqli_query($db_conn, $import_action);
					   
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
