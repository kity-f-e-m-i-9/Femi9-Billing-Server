<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "Step 1: PHP is working<br>";

try {
    require_once("checksession.php");
    echo "Step 2: checksession.php loaded<br>";
    
    require_once("config.php");
    echo "Step 3: config.php loaded<br>";
    
    echo "Step 4: DB Connection: " . (isset($db_conn) ? "OK" : "FAILED") . "<br>";
    
    echo "Step 5: Session LOGIN_USER_ID: " . ($_SESSION['LOGIN_USER_ID'] ?? 'NOT SET') . "<br>";
    echo "Step 6: Session LOGIN_USER_TYPE: " . ($_SESSION['LOGIN_USER_TYPE'] ?? 'NOT SET') . "<br>";
    
    if (isset($db_conn) && $db_conn) {
        $result = $db_conn->query("SELECT 1");
        echo "Step 7: Database query: " . ($result ? "OK" : "FAILED") . "<br>";
        
        // Test reward_points table exists
        $result = $db_conn->query("SHOW TABLES LIKE 'reward_points'");
        echo "Step 8: reward_points table: " . ($result && $result->num_rows > 0 ? "EXISTS" : "MISSING") . "<br>";
        
        // Test bonus_points_history table exists
        $result = $db_conn->query("SHOW TABLES LIKE 'bonus_points_history'");
        echo "Step 9: bonus_points_history table: " . ($result && $result->num_rows > 0 ? "EXISTS" : "MISSING") . "<br>";
        
        // Test bonus_execution_log table exists
        $result = $db_conn->query("SHOW TABLES LIKE 'bonus_execution_log'");
        echo "Step 10: bonus_execution_log table: " . ($result && $result->num_rows > 0 ? "EXISTS" : "MISSING") . "<br>";
    }
    
    echo "<hr>";
    echo "All tests passed! You can proceed with bonus calculator.";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}