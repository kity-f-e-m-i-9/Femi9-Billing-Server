<?php
/**
 * DIAGNOSTIC: Check Company ID Issue in Multi-Company System
 */

include("checksession.php");
include("config.php");

echo "<h1>🏢 Multi-Company System Diagnostic</h1>";
echo "<hr>";

// Get current logged-in company info
echo "<h2>1. Current Session Info</h2>";
echo "<div style='background: #dbeafe; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr><th style='background: #f8fafc;'>Session Variable</th><th style='background: #f8fafc;'>Value</th></tr>";

// Check common session variables
$session_vars = [
    'onboard_userID',
    'onboard_userTYPE',
    'onboard_userName',
    'company_id',
    'user_id',
    'temp_id'
];

foreach ($session_vars as $var) {
    if (isset($_SESSION[$var])) {
        echo "<tr><td><code>$var</code></td><td><strong>" . htmlspecialchars($_SESSION[$var]) . "</strong></td></tr>";
    }
}
echo "</table>";
echo "</div>";

// Get company info from database
echo "<h2>2. Companies in System</h2>";
$query_companies = "SELECT DISTINCT to_user_id, to_user_type, COUNT(*) as payment_count, SUM(balance_amount) as total_balance
                    FROM advance_payments
                    WHERE deleted_at IS NULL
                    GROUP BY to_user_id, to_user_type
                    ORDER BY to_user_id";

$result_companies = mysqli_query($db_conn, $query_companies);

echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f8fafc;'>";
echo "<th>Company ID (to_user_id)</th>";
echo "<th>Company Type</th>";
echo "<th>Payments Received</th>";
echo "<th>Total Balance</th>";
echo "</tr>";

while ($row = mysqli_fetch_assoc($result_companies)) {
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($row['to_user_id']) . "</strong></td>";
    echo "<td>" . htmlspecialchars($row['to_user_type']) . "</td>";
    echo "<td>" . $row['payment_count'] . "</td>";
    echo "<td>₹" . number_format($row['total_balance'], 2) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";

// Test a specific Super Stockist
$test_ss_id = "71194FSPST01082444417"; // Kanmani Express from earlier screenshots

echo "<h2>3. Test Case: Super Stockist Balance</h2>";
echo "<p><strong>Testing Super Stockist ID:</strong> $test_ss_id</p>";

// Check which company this SS paid to
$query_ss = "SELECT to_user_id, to_user_type, SUM(balance_amount) as balance
             FROM advance_payments
             WHERE from_user_id = '$test_ss_id'
             AND from_user_type = 'super_stockiest'
             AND deleted_at IS NULL
             GROUP BY to_user_id, to_user_type";

$result_ss = mysqli_query($db_conn, $query_ss);

echo "<h3>Payments Made by This Super Stockist:</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr style='background: #f8fafc;'><th>Paid TO (Company ID)</th><th>Company Type</th><th>Balance</th></tr>";

$ss_balances = [];
while ($row = mysqli_fetch_assoc($result_ss)) {
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($row['to_user_id']) . "</strong></td>";
    echo "<td>" . htmlspecialchars($row['to_user_type']) . "</td>";
    echo "<td>₹" . number_format($row['balance'], 2) . "</td>";
    echo "</tr>";
    $ss_balances[$row['to_user_id']] = $row['balance'];
}
echo "</table>";

echo "<hr>";

echo "<h2>4. Query Test with Different Company IDs</h2>";

foreach (['1', '11'] as $company_id) {
    echo "<h3>Query: WHERE from_user_id = '$test_ss_id' AND to_user_id = '$company_id'</h3>";
    
    $query_test = "SELECT COALESCE(SUM(balance_amount), 0) as balance
                   FROM advance_payments
                   WHERE from_user_id = '$test_ss_id'
                   AND from_user_type = 'super_stockiest'
                   AND to_user_id = '$company_id'
                   AND deleted_at IS NULL";
    
    $result_test = mysqli_query($db_conn, $query_test);
    $row_test = mysqli_fetch_assoc($result_test);
    $balance = $row_test['balance'];
    
    $color = $balance > 0 ? '#d1fae5' : '#fee2e2';
    echo "<div style='background: $color; padding: 15px; border-radius: 8px; margin-bottom: 10px;'>";
    echo "<strong>Result:</strong> ₹" . number_format($balance, 2);
    
    if ($balance == 0) {
        echo " ❌ (Wrong! SS didn't pay to this company)";
    } else {
        echo " ✅ (Correct! SS paid to this company)";
    }
    echo "</div>";
}

echo "<hr>";

echo "<h2>🎯 CONCLUSION</h2>";

echo "<div style='background: #fef3c7; padding: 20px; border-radius: 8px;'>";
echo "<h3>The Problem:</h3>";
echo "<ol>";
echo "<li>Your system has MULTIPLE companies (ID: 1, 11, etc.)</li>";
echo "<li>Super Stockists pay to DIFFERENT companies</li>";
echo "<li>When checking balance, you MUST filter by correct company ID</li>";
echo "</ol>";

echo "<h3>The Fix:</h3>";
echo "<ol>";
echo "<li><strong>Always include <code>to_user_id</code> in the query</strong></li>";
echo "<li><strong>Pass the correct company ID from session</strong></li>";
echo "<li>In <code>get-advance-balance.php</code>, use session company ID, not '1'</li>";
echo "</ol>";

echo "<h3>Current Session Company ID:</h3>";
$current_company_id = $_SESSION['onboard_userID'] ?? 'NOT SET';
echo "<p style='font-size: 20px; font-weight: bold; color: #1e293b;'>" . htmlspecialchars($current_company_id) . "</p>";

if (isset($ss_balances[$current_company_id])) {
    echo "<p style='color: #10b981;'><strong>✅ This SS has balance with YOUR company: ₹" . number_format($ss_balances[$current_company_id], 2) . "</strong></p>";
} else {
    echo "<p style='color: #ef4444;'><strong>❌ This SS has NO payments to YOUR company!</strong></p>";
    echo "<p>This SS paid to company: " . implode(', ', array_keys($ss_balances)) . "</p>";
}

echo "</div>";
?>