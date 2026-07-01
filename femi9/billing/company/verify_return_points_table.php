<?php
/**
 * Invoice ID Format Comparison & Fix Generator
 * This will show exactly why the JOIN is failing and how to fix it
 */

while (ob_get_level()) ob_end_clean();
header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice Format Fix</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .box { padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 5px solid; }
        .error { background: #fee; border-color: #dc2626; }
        .success { background: #dcfce7; border-color: #16a34a; }
        .warning { background: #fef3c7; border-color: #f59e0b; }
        .info { background: #dbeafe; border-color: #3b82f6; }
        h1 { color: #2563eb; }
        h2 { color: #059669; margin-top: 25px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 13px; }
        th { background: #2563eb; color: white; }
        tr:nth-child(even) { background: #f9fafb; }
        pre { background: #1f2937; color: #10b981; padding: 15px; overflow-x: auto; font-size: 12px; border-radius: 5px; }
        .code-title { background: #374151; color: white; padding: 8px; margin-top: 20px; border-radius: 5px 5px 0 0; font-weight: bold; }
        .compare { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
        .compare-box { background: white; padding: 15px; border: 2px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
<h1>🔍 Invoice ID Format Analysis & Fix</h1>

<?php
flush();

// Load database connection
if (file_exists('include/db-connect.php')) {
    include('include/db-connect.php');
} elseif (file_exists('config.php')) {
    include('config.php');
} else {
    die("<div class='box error'>Cannot find database connection file</div>");
}

if (!isset($db_conn) && isset($conn)) {
    $db_conn = $conn;
}

// Use Mary's correct ID - change this as needed
$mary_id = '26981FST28082430541';  // Change this to the correct ID from find_mary_data.php
$user_type = 'stockiest';

echo "<div class='box info'>";
echo "<p><strong>Testing with User ID:</strong> {$mary_id}</p>";
echo "<p><strong>User Type:</strong> {$user_type}</p>";
echo "<p><em>If this is not correct, edit the \$mary_id variable in this file</em></p>";
echo "</div>";
flush();

// ============================================================================
// STEP 1: Get sample invoice IDs from user_invoice_items
// ============================================================================
echo "<h2>Step 1: Invoice IDs from user_invoice_items</h2>";
flush();

$query = "
    SELECT DISTINCT inv_id, date
    FROM user_invoice_items
    WHERE to_user_id = ? AND to_user_type = ?
        AND date >= '2025-07-01'
    ORDER BY date DESC
    LIMIT 10
";

$stmt = $db_conn->prepare($query);
$stmt->bind_param('ss', $mary_id, $user_type);
$stmt->execute();
$result = $stmt->get_result();

$invoice_ids = [];
if ($result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>#</th><th>Invoice ID (inv_id)</th><th>Date</th><th>Length</th><th>Format Analysis</th></tr>";
    
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        $id = $row['inv_id'];
        $invoice_ids[] = $id;
        
        $length = strlen($id);
        $analysis = '';
        
        if (is_numeric($id)) {
            $analysis = 'Pure Numeric';
        } elseif (preg_match('/^\d+[A-Z]+\d+$/', $id)) {
            $analysis = 'Number + Letters + Number (e.g., 8627623637CMPST280325511537)';
        } elseif (preg_match('/^[A-Z]+-\d+$/', $id)) {
            $analysis = 'Prefix-Number (e.g., INV-123)';
        } else {
            $analysis = 'Complex/Mixed';
        }
        
        echo "<tr>";
        echo "<td>{$i}</td>";
        echo "<td><code>{$id}</code></td>";
        echo "<td>{$row['date']}</td>";
        echo "<td>{$length}</td>";
        echo "<td>{$analysis}</td>";
        echo "</tr>";
        $i++;
    }
    echo "</table>";
    
    echo "<div class='box success'>✓ Found " . count($invoice_ids) . " invoice IDs</div>";
} else {
    echo "<div class='box warning'>⚠ No invoices found for this user</div>";
}
flush();

// ============================================================================
// STEP 2: Get sample invoice numbers from user_return_stock_items
// ============================================================================
echo "<h2>Step 2: Invoice Numbers from user_return_stock_items</h2>";
flush();

$query = "
    SELECT DISTINCT invnumber, date
    FROM user_return_stock_items
    WHERE from_userid = ? AND from_usertype = ?
        AND date >= '2025-07-01'
    ORDER BY date DESC
";

$stmt = $db_conn->prepare($query);
$stmt->bind_param('ss', $mary_id, $user_type);
$stmt->execute();
$result = $stmt->get_result();

$return_invnumbers = [];
if ($result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>#</th><th>Invoice Number (invnumber)</th><th>Date</th><th>Length</th><th>Format Analysis</th></tr>";
    
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        $id = $row['invnumber'];
        $return_invnumbers[] = $id;
        
        $length = strlen($id);
        $analysis = '';
        
        if (is_numeric($id)) {
            $analysis = 'Pure Numeric';
        } elseif (preg_match('/^\d+[A-Z]+\d+$/', $id)) {
            $analysis = 'Number + Letters + Number (e.g., 8627623637CMPST280325511537)';
        } elseif (preg_match('/^[A-Z]+-\d+$/', $id)) {
            $analysis = 'Prefix-Number (e.g., INV-123)';
        } else {
            $analysis = 'Complex/Mixed';
        }
        
        echo "<tr>";
        echo "<td>{$i}</td>";
        echo "<td><code>{$id}</code></td>";
        echo "<td>{$row['date']}</td>";
        echo "<td>{$length}</td>";
        echo "<td>{$analysis}</td>";
        echo "</tr>";
        $i++;
    }
    echo "</table>";
    
    echo "<div class='box success'>✓ Found " . count($return_invnumbers) . " return invoice numbers</div>";
} else {
    echo "<div class='box warning'>⚠ No returns found for this user</div>";
}
flush();

// ============================================================================
// STEP 3: Compare and find matches
// ============================================================================
if (!empty($invoice_ids) && !empty($return_invnumbers)) {
    echo "<h2>Step 3: Matching Analysis</h2>";
    flush();
    
    // Try different matching strategies
    $exact_matches = array_intersect($invoice_ids, $return_invnumbers);
    
    // Try case-insensitive
    $invoice_lower = array_map('strtolower', $invoice_ids);
    $return_lower = array_map('strtolower', $return_invnumbers);
    $case_insensitive_matches = array_intersect($invoice_lower, $return_lower);
    
    // Try trimmed
    $invoice_trim = array_map('trim', $invoice_ids);
    $return_trim = array_map('trim', $return_invnumbers);
    $trimmed_matches = array_intersect($invoice_trim, $return_trim);
    
    echo "<div class='compare'>";
    
    echo "<div class='compare-box'>";
    echo "<h3>Invoice IDs (from invoices)</h3>";
    echo "<ul>";
    foreach (array_slice($invoice_ids, 0, 5) as $id) {
        echo "<li><code>{$id}</code></li>";
    }
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='compare-box'>";
    echo "<h3>Invoice Numbers (from returns)</h3>";
    echo "<ul>";
    foreach ($return_invnumbers as $id) {
        echo "<li><code>{$id}</code></li>";
    }
    echo "</ul>";
    echo "</div>";
    
    echo "</div>";
    
    echo "<table>";
    echo "<tr><th>Match Type</th><th>Matches Found</th><th>Status</th></tr>";
    echo "<tr><td>Exact Match</td><td>" . count($exact_matches) . "</td><td>" . 
         (count($exact_matches) > 0 ? '<span style="color:#16a34a;">✓ Working</span>' : '<span style="color:#dc2626;">✗ Not Working</span>') . "</td></tr>";
    echo "<tr><td>Case-Insensitive</td><td>" . count($case_insensitive_matches) . "</td><td>" . 
         (count($case_insensitive_matches) > 0 ? '<span style="color:#16a34a;">✓ Working</span>' : '<span style="color:#dc2626;">✗ Not Working</span>') . "</td></tr>";
    echo "<tr><td>Trimmed</td><td>" . count($trimmed_matches) . "</td><td>" . 
         (count($trimmed_matches) > 0 ? '<span style="color:#16a34a;">✓ Working</span>' : '<span style="color:#dc2626;">✗ Not Working</span>') . "</td></tr>";
    echo "</table>";
    flush();
    
    // Detailed comparison
    echo "<h3>Detailed Comparison:</h3>";
    echo "<table>";
    echo "<tr><th>Return Invoice Number</th><th>Exists in Invoices?</th><th>Issue</th></tr>";
    
    foreach ($return_invnumbers as $ret_inv) {
        $found = in_array($ret_inv, $invoice_ids);
        $status = $found ? '<span style="color:#16a34a;">✓ YES</span>' : '<span style="color:#dc2626;">✗ NO</span>';
        
        $issue = '';
        if (!$found) {
            // Check for partial matches
            $partial_matches = [];
            foreach ($invoice_ids as $inv_id) {
                if (strpos($inv_id, $ret_inv) !== false || strpos($ret_inv, $inv_id) !== false) {
                    $partial_matches[] = $inv_id;
                }
            }
            
            if (!empty($partial_matches)) {
                $issue = 'Partial match found: ' . implode(', ', $partial_matches);
            } else {
                $issue = 'Invoice might be from different date range';
            }
        }
        
        echo "<tr>";
        echo "<td><code>{$ret_inv}</code></td>";
        echo "<td>{$status}</td>";
        echo "<td>{$issue}</td>";
        echo "</tr>";
    }
    echo "</table>";
    flush();
}

// ============================================================================
// STEP 4: Generate Fix
// ============================================================================
echo "<h2>Step 4: Recommended Fix</h2>";
flush();

if (count($exact_matches ?? []) == 0 && !empty($invoice_ids) && !empty($return_invnumbers)) {
    echo "<div class='box error'>";
    echo "<h3>❌ Problem Confirmed: No Matches Found</h3>";
    echo "<p>The invoice IDs in <code>user_invoice_items.inv_id</code> do NOT match the invoice numbers in <code>user_return_stock_items.invnumber</code></p>";
    echo "</div>";
    
    echo "<div class='box info'>";
    echo "<h3>🔧 Solution: Modify the Admin Report Query</h3>";
    echo "<p>Since the formats don't match, we need to update the JOIN condition in your admin report query.</p>";
    echo "</div>";
    
    echo "<div class='code-title'>Option 1: Update Admin Report Query - Remove Date Filter from Returns Subquery</div>";
    echo "<pre>";
    echo "// In your admin report file (Document 2), find this section:\n\n";
    echo "LEFT JOIN (\n";
    echo "    SELECT from_userid, invnumber, SUM(rwpoints) as rwpoints\n";
    echo "    FROM user_return_stock_items\n";
    echo "    WHERE date BETWEEN :from_date2 AND :to_date2  ← REMOVE THIS LINE\n";
    echo "        AND from_usertype = :user_type2\n";
    echo "    GROUP BY from_userid, invnumber\n";
    echo ") return_data\n\n";
    echo "// Change it to:\n\n";
    echo "LEFT JOIN (\n";
    echo "    SELECT from_userid, invnumber, SUM(rwpoints) as rwpoints\n";
    echo "    FROM user_return_stock_items\n";
    echo "    WHERE from_usertype = :user_type2  ← Only filter by user type\n";
    echo "    GROUP BY from_userid, invnumber\n";
    echo ") return_data";
    echo "</pre>";
    
    echo "<div class='code-title'>Option 2: Try Case-Insensitive Match</div>";
    echo "<pre>";
    echo "// Change the JOIN condition from:\n";
    echo "ON return_data.invnumber = invoice_data.inv_id\n\n";
    echo "// To:\n";
    echo "ON LOWER(TRIM(return_data.invnumber)) = LOWER(TRIM(invoice_data.inv_id))";
    echo "</pre>";
    
    echo "<div class='code-title'>Option 3: Check if Invoice IDs Are in Different Date Range</div>";
    echo "<pre>";
    echo "-- Run this query to see when the invoices were actually created:\n\n";
    echo "SELECT inv_id, date\n";
    echo "FROM user_invoice_items\n";
    echo "WHERE inv_id IN ('" . implode("', '", $return_invnumbers) . "')\n";
    echo "ORDER BY date DESC;";
    echo "</pre>";
    
} elseif (count($exact_matches ?? []) > 0) {
    echo "<div class='box success'>";
    echo "<h3>✓ Matches Found!</h3>";
    echo "<p>The invoice IDs are matching correctly. The issue might be with the date range filter.</p>";
    echo "</div>";
    
    echo "<div class='box warning'>";
    echo "<h3>Possible Issue: Date Range Filter Too Restrictive</h3>";
    echo "<p>The returns might be in the date range, but they reference invoices from OUTSIDE the date range.</p>";
    echo "<p>This is why the JOIN finds 0 matches even though the IDs match.</p>";
    echo "</div>";
    
    echo "<div class='code-title'>Solution: Remove Date Filter from Return Subquery</div>";
    echo "<pre>";
    echo "// Current (problematic) query:\n\n";
    echo "LEFT JOIN (\n";
    echo "    SELECT from_userid, invnumber, SUM(rwpoints) as rwpoints\n";
    echo "    FROM user_return_stock_items\n";
    echo "    WHERE date BETWEEN :from_date2 AND :to_date2  ← This filters returns by date\n";
    echo "        AND from_usertype = :user_type2\n";
    echo "    GROUP BY from_userid, invnumber\n";
    echo ") return_data\n";
    echo "    ON return_data.from_userid = invoice_data.to_user_id\n";
    echo "    AND return_data.invnumber = invoice_data.inv_id  ← But invoices may be outside date range\n\n";
    echo "// Fixed query:\n\n";
    echo "LEFT JOIN (\n";
    echo "    SELECT from_userid, invnumber, SUM(rwpoints) as rwpoints\n";
    echo "    FROM user_return_stock_items\n";
    echo "    WHERE from_usertype = :user_type2  ← Remove date filter\n";
    echo "    GROUP BY from_userid, invnumber\n";
    echo ") return_data\n";
    echo "    ON return_data.from_userid = invoice_data.to_user_id\n";
    echo "    AND return_data.invnumber = invoice_data.inv_id";
    echo "</pre>";
}

// ============================================================================
// STEP 5: Test Query
// ============================================================================
if (!empty($invoice_ids) && !empty($return_invnumbers)) {
    echo "<h2>Step 5: Test Fixed Query</h2>";
    flush();
    
    echo "<h3>Test 1: With Date Filter Removed from Returns</h3>";
    
    $test_query = "
        SELECT 
            COALESCE(SUM(invoice_data.rwpoints), 0) as invoice_points,
            COALESCE(SUM(return_data.rwpoints), 0) as return_points,
            COALESCE(SUM(invoice_data.rwpoints), 0) - COALESCE(SUM(return_data.rwpoints), 0) as net_points
        FROM (
            SELECT to_user_id, inv_id, SUM(rwpoints) as rwpoints
            FROM user_invoice_items
            WHERE date BETWEEN '2025-07-01' AND '2025-11-30'
                AND to_user_type = '{$user_type}'
                AND to_user_id = '{$mary_id}'
            GROUP BY to_user_id, inv_id
        ) invoice_data
        LEFT JOIN (
            SELECT from_userid, invnumber, SUM(rwpoints) as rwpoints
            FROM user_return_stock_items
            WHERE from_usertype = '{$user_type}'
                AND from_userid = '{$mary_id}'
            GROUP BY from_userid, invnumber
        ) return_data 
            ON return_data.from_userid = invoice_data.to_user_id 
            AND return_data.invnumber = invoice_data.inv_id
    ";
    
    $result = $db_conn->query($test_query);
    
    if ($result) {
        $row = $result->fetch_assoc();
        
        echo "<table>";
        echo "<tr><th>Metric</th><th>Value</th><th>Status</th></tr>";
        echo "<tr><td>Invoice Points</td><td>" . number_format($row['invoice_points'], 2) . "</td><td>✓</td></tr>";
        echo "<tr><td>Return Points (deducted)</td><td>" . number_format($row['return_points'], 2) . "</td><td>" . 
             ($row['return_points'] > 0 ? '<span style="color:#16a34a;">✓ WORKING!</span>' : '<span style="color:#dc2626;">✗ Still 0</span>') . "</td></tr>";
        echo "<tr style='background:#dbeafe;font-weight:bold;'>";
        echo "<td>Net Points</td><td>" . number_format($row['net_points'], 2) . "</td><td>-</td>";
        echo "</tr>";
        echo "</table>";
        
        if ($row['return_points'] > 0) {
            echo "<div class='box success'>";
            echo "<h3>🎉 SUCCESS! The fix works!</h3>";
            echo "<p>Return points are now being deducted: <strong>" . number_format($row['return_points'], 2) . "</strong></p>";
            echo "<p>Net purchase points: <strong>" . number_format($row['net_points'], 2) . "</strong></p>";
            echo "<p><strong>Next step:</strong> Apply this fix to your admin report file (Document 2)</p>";
            echo "</div>";
        } else {
            echo "<div class='box error'>";
            echo "<p>Still returning 0. The invoice IDs truly don't match.</p>";
            echo "<p>You may need to investigate why the return records have different invoice numbers.</p>";
            echo "</div>";
        }
    }
}

$db_conn->close();
?>

<div class='box info'>
<h3>📋 Summary</h3>
<p>This diagnostic showed you:</p>
<ul>
<li>The exact format of invoice IDs in both tables</li>
<li>Whether they match or not</li>
<li>The recommended fix for your admin report query</li>
<li>A test of the fixed query</li>
</ul>
<p><strong>Next step:</strong> Apply the fix to your admin report PHP file</p>
</div>

</body>
</html>