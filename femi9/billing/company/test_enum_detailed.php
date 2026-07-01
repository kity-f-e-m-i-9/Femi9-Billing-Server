<?php
require_once('include/db-connect.php');

echo "Testing execution_mode insert...\n\n";

// Test each ENUM value
$test_values = ['dry_run', 'execute', 'rollback', 'manual'];

foreach ($test_values as $test_mode) {
    echo "Testing mode: '{$test_mode}'\n";
    
    $stmt = $db_conn->prepare("
        INSERT INTO monthly_target_audit_log (
            action_type, execution_mode, admin_user
        ) VALUES (?, ?, ?)
    ");
    
    $action = 'test_enum';
    $admin = 'diagnostic';
    
    $stmt->bind_param('sss', $action, $test_mode, $admin);
    
    if ($stmt->execute()) {
        $insert_id = $stmt->insert_id;
        echo "  ✅ SUCCESS - Inserted ID: {$insert_id}\n";
        
        // Verify what was actually stored
        $verify = $db_conn->query("SELECT execution_mode FROM monthly_target_audit_log WHERE id = {$insert_id}");
        $row = $verify->fetch_assoc();
        echo "  Stored value: '{$row['execution_mode']}'\n";
        
        // Delete test record
        $db_conn->query("DELETE FROM monthly_target_audit_log WHERE id = {$insert_id}");
    } else {
        echo "  ❌ FAILED - Error: " . $stmt->error . "\n";
    }
    
    $stmt->close();
    echo "\n";
}

// Now test the actual logAudit call
echo "========================================\n";
echo "Testing actual logAudit call...\n\n";

require_once('include/MonthlyTargetCalculator.class.php');

try {
    $calculator = new MonthlyTargetCalculator($db_conn);
    
    $calculator->logAudit('test_full_audit', [
        'description' => 'Testing full audit log',
        'mode' => 'execute',
        'records' => 5,
        'admin' => 'test_script',
        'notes' => 'This is a test'
    ]);
    
    echo "✅ logAudit SUCCESS!\n";
    
    // Get the last inserted record
    $result = $db_conn->query("
        SELECT * FROM monthly_target_audit_log 
        WHERE action_type = 'test_full_audit' 
        ORDER BY id DESC LIMIT 1
    ");
    
    if ($row = $result->fetch_assoc()) {
        echo "\nInserted record:\n";
        echo "  ID: {$row['id']}\n";
        echo "  action_type: {$row['action_type']}\n";
        echo "  execution_mode: '{$row['execution_mode']}'\n";
        echo "  admin_user: {$row['admin_user']}\n";
        
        // Delete test record
        $db_conn->query("DELETE FROM monthly_target_audit_log WHERE id = {$row['id']}");
        echo "\n✅ Test record deleted\n";
    }
    
} catch (Exception $e) {
    echo "❌ logAudit FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

$db_conn->close();
?>