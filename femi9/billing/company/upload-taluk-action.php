<?php
include("checksession.php");
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
				$taluk_name=$getData[2];
				
$selec_count_duplicate=mysqli_query($db_conn,"select count(*) as numitem from taluk where state_id='$state_id' and dist_id='$dist_id' and taluk='$taluk_name'");
$fech_count_duplicate=mysqli_fetch_array($selec_count_duplicate);
if($fech_count_duplicate['numitem']==0)
{
				   
				  $insert_products="insert into taluk (state_id,dist_id,taluk,usertype,userid,assigned_SID) values 
		('$state_id','$dist_id','$taluk_name','$Login_user_TYPEvl','$Login_user_IDvl','Nil')";
                 $result = mysqli_query($db_conn, $insert_products);
					   
}   
			
               }
          
               fclose($file);  
         }
		 
if(!isset($result))
            {
              echo "<script type=\"text/javascript\">
                  alert(\"Invalid File:Please Upload CSV File.\");
                  window.location = \"manage-taluk\"
                  </script>";    
            }
            else {
                echo "<script type=\"text/javascript\">
                alert(\"CSV File has been successfully Imported.\");
                window.location = \"manage-taluk\"
              </script>";
            }


      }   
?>
