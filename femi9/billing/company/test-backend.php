<?php
/**
 * Test Backend Response
 * This file helps diagnose the Ajax error
 */

session_start();

// Show all errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Backend Diagnostic Test</h2>";
echo "<hr>";

// Test 1: Session Check
echo "<h3>1. Session Check</h3>";
echo "User ID: " . (isset($_SESSION['LOGIN_USER_ID']) ? $_SESSION['LOGIN_USER_ID'] : '<strong style="color:red">NOT SET</strong>') . "<br>";
echo "User Type: " . (isset($_SESSION['LOGIN_USER_TYPE']) ? $_SESSION['LOGIN_USER_TYPE'] : '<strong style="color:red">NOT SET</strong>') . "<br>";

// Test 2: Config File
echo "<hr><h3>2. Config File Check</h3>";
if (file_exists('config.php')) {
    echo "✅ config.php exists<br>";
    include('config.php');
} else {
    echo "❌ config.php NOT FOUND<br>";
}

// Test 3: Database Connection
echo "<hr><h3>3. Database Connection</h3>";
if (isset($db_conn) && $db_conn) {
    echo "✅ Database connected<br>";
    echo "Character set: " . $db_conn->character_set_name() . "<br>";
    
    // Test 4: Check advance_payments table
    echo "<hr><h3>4. Advance Payments Table</h3>";
    $result = $db_conn->query("SELECT COUNT(*) as total FROM advance_payments WHERE deleted_at IS NULL");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✅ Total payments in DB: " . $row['total'] . "<br>";
        
        // Check company payments
        $result2 = $db_conn->query("SELECT COUNT(*) as total FROM advance_payments WHERE deleted_at IS NULL AND to_user_type = 'company'");
        if ($result2) {
            $row2 = $result2->fetch_assoc();
            echo "✅ Company payments: " . $row2['total'] . "<br>";
        }
    } else {
        echo "❌ Query failed: " . $db_conn->error . "<br>";
    }
} else {
    echo "❌ Database NOT connected<br>";
}

// Test 5: Try calling the actual endpoint
echo "<hr><h3>5. Test AJAX Endpoint</h3>";
echo '<a href="get-advance-payments-data-2.php?test=1" target="_blank">Click here to test get-advance-payments-data-2.php</a><br>';
echo '<a href="get-receivers-districts-2.php?action=get_receiver_districts" target="_blank">Click here to test get-receivers-districts-2.php</a><br>';

// Test 6: Check if files exist
echo "<hr><h3>6. File Existence Check</h3>";
$files = [
    'get-advance-payments-data-2.php',
    'get-receivers-districts-2.php',
    'checksession.php',
    'config.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file NOT FOUND<br>";
    }
}

echo "<hr>";
echo "<p><strong>Instructions:</strong> If all checks pass, the issue might be in the DataTables configuration or POST data format.</p>";
?>
