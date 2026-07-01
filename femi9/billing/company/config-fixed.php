<?php 
// Fixed configuration for cron job - command line compatible
// Database connection parameters - ACTUAL VALUES FROM db-connect.php
$host = "localhost";
$username = "femi9software_crm24vl";
$password = "cPJn1Hgm+0~1Vel#755010.,";
$database = "femi9software_crm24";

// Create database connection
$db_conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$db_conn) {
    error_log("Cron Auto-Update: Database connection failed: " . mysqli_connect_error());
    die("Database connection failed: " . mysqli_connect_error() . "\n");
}

// Set charset
mysqli_set_charset($db_conn, "utf8");

// Make connection available globally
$GLOBALS['db_conn'] = $db_conn;

// Configuration variables needed by the cron script
$Login_user_TYPEvl = "company";
$Login_user_IDvl = "company"; 
$DummySuperStockistID = "DMYSS/CMP/001";
$DummyStockistID = "DMYSTKST/CMP/001";
$DummyDistributorID = "DMYDSTBTRS/CMP/001";
$onboard_userTYPE = "company";
$onboard_userID = "company";

// Since this runs from command line, we'll set a default admin state
$Config_Admin_State = "Tamil Nadu"; // Update this if needed

// Create dummy variables that your original config expects
$Log_users_Dtails134 = "select * from admin_log where username='company'";
$Fetch_Log_users_Dtails134 = mysqli_query($db_conn, $Log_users_Dtails134);
if ($Fetch_Log_users_Dtails134) {
    $Result_Log_users_Dtails134 = mysqli_fetch_array($Fetch_Log_users_Dtails134);
} else {
    $Result_Log_users_Dtails134 = array();
}

$adminDetailsCONFGI = "select state from admin_log where usertype='admin'";
$fetchDetailsCONFGI = mysqli_query($db_conn, $adminDetailsCONFGI);
if ($fetchDetailsCONFGI) {
    $resultDetailsConfig = mysqli_fetch_array($fetchDetailsCONFGI);
    if ($resultDetailsConfig && isset($resultDetailsConfig['state'])) {
        $Config_Admin_State = $resultDetailsConfig['state'];
    }
}

?>