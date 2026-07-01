<?php 

$select_wp_settings="select * from admin_whatsapp_configuration";
$fetch_wp_settings=mysqli_query($db_conn,$select_wp_settings);
$result_wp_settings=mysqli_fetch_array($fetch_wp_settings);

// Send data to the API
   $api_url = $result_wp_settings['url'];
    $api_secret = $result_wp_settings['api_key'];
    $api_account = $result_wp_settings['wa_id'];
	?>