<?php
/**
 * Get Advance Payments Data (AJAX Endpoint)
 * Femi9 Billing Application
 * 
 * Description: Returns advance payment data for DataTables with filtering
 * Supports: Company and Super Stockist logins with proper authorization
 * 
 * @author Femi9 Development Team
 * @version 2.0 - Added Super Stockist support
 * @date 2025-01-02
 */

include("checksession.php"); 
include("config.php"); 

header('Content-Type: application/json');
date_default_timezone_set("Asia/Kolkata");
error_reporting(0);

$logged_user_id = $_SESSION['LOGIN_USER_ID'] ?? '';
$logged_user_type = $_SESSION['LOGIN_USER_TYPE'] ?? '';

// Get filter parameters
$from_date = $_POST['from_date'] ?? date('Y-m-01');
$to_date = $_POST['to_date'] ?? date('Y-m-d');
$filter_user_type = $_POST['user_type'] ?? '';
$filter_user_id = $_POST['user_id'] ?? '';
$filter_status = $_POST['status'] ?? '';

// Build WHERE clause
$where_conditions = ["deleted_at IS NULL"];

// Date filtering
if (!empty($from_date)) {
    $where_conditions[] = "payment_date >= '$from_date'";
}
if (!empty($to_date)) {
    $where_conditions[] = "payment_date <= '$to_date'";
}

// Authorization filtering - CRITICAL
if ($logged_user_type === 'super_stockiest') {
    // For Super Stockist: Show ONLY payments where they are the receiver
    $escaped_user_id = $db_conn->real_escape_string($logged_user_id);
    $where_conditions[] = "to_user_id = '$escaped_user_id'";
    $where_conditions[] = "to_user_type = 'super_stockiest'";
} elseif ($logged_user_type !== 'company') {
    // For other non-company users
    $escaped_user_id = $db_conn->real_escape_string($logged_user_id);
    $where_conditions[] = "(to_user_id = '$escaped_user_id' OR from_user_id = '$escaped_user_id')";
}

// Additional filters
if (!empty($filter_user_type)) {
    $escaped_user_type = $db_conn->real_escape_string($filter_user_type);
    $where_conditions[] = "from_user_type = '$escaped_user_type'";
}

if (!empty($filter_user_id)) {
    $escaped_filter_user_id = $db_conn->real_escape_string($filter_user_id);
    $where_conditions[] = "from_user_id = '$escaped_filter_user_id'";
}

if (!empty($filter_status)) {
    $escaped_status = $db_conn->real_escape_string($filter_status);
    $where_conditions[] = "status = '$escaped_status'";
}

$where_sql = implode(" AND ", $where_conditions);

// Main query with JOINs to get category and target amount
$query = "SELECT 
            ap.id,
            ap.from_user_id,
            ap.from_user_name,
            ap.from_user_type,
            ap.to_user_id,
            ap.to_user_name,
            ap.to_user_type,
            ap.amount,
            ap.payment_date,
            ap.payment_mode,
            ap.reference_number,
            ap.bank_name,
            ap.adjusted_amount,
            ap.balance_amount,
            ap.status,
            ap.remarks,
            ap.created_at,
            ap.updated_at,
            sc.catname AS category_name,
            sc.target_amount
          FROM advance_payments ap
          LEFT JOIN stockist_referral sr ON ap.from_user_id = sr.stockist_id AND ap.from_user_type = 'stockiest'
          LEFT JOIN stockist_category sc ON sr.st_cat_id = sc.id
          WHERE $where_sql
          ORDER BY ap.id DESC";

$result = $db_conn->query($query);

$data = [];
$total_amount = 0;
$total_balance = 0;
$total_adjusted = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'from_user_id' => htmlspecialchars($row['from_user_id'], ENT_QUOTES, 'UTF-8'),
            'from_user_name' => htmlspecialchars($row['from_user_name'], ENT_QUOTES, 'UTF-8'),
            'from_user_type' => $row['from_user_type'],
            'to_user_id' => htmlspecialchars($row['to_user_id'], ENT_QUOTES, 'UTF-8'),
            'to_user_name' => htmlspecialchars($row['to_user_name'], ENT_QUOTES, 'UTF-8'),
            'to_user_type' => $row['to_user_type'],
            'amount' => number_format((float)$row['amount'], 2, '.', ''),
            'payment_date' => $row['payment_date'],
            'payment_mode' => htmlspecialchars($row['payment_mode'], ENT_QUOTES, 'UTF-8'),
            'reference_number' => htmlspecialchars($row['reference_number'] ?? '', ENT_QUOTES, 'UTF-8'),
            'bank_name' => htmlspecialchars($row['bank_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'adjusted_amount' => number_format((float)$row['adjusted_amount'], 2, '.', ''),
            'balance_amount' => number_format((float)$row['balance_amount'], 2, '.', ''),
            'status' => $row['status'],
            'remarks' => htmlspecialchars($row['remarks'] ?? '', ENT_QUOTES, 'UTF-8'),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'category_name' => $row['category_name'] ? htmlspecialchars($row['category_name'], ENT_QUOTES, 'UTF-8') : '',
            'target_amount' => $row['target_amount'] ? number_format((float)$row['target_amount'], 2, '.', '') : ''
        ];
        
        $total_amount += (float)$row['amount'];
        $total_balance += (float)$row['balance_amount'];
        $total_adjusted += (float)$row['adjusted_amount'];
    }
}

// Statistics
$stats = [
    'total_payments' => count($data),
    'total_amount' => number_format($total_amount, 2, '.', ''),
    'total_balance' => number_format($total_balance, 2, '.', ''),
    'adjusted_amount' => number_format($total_adjusted, 2, '.', '')
];

// Response
echo json_encode([
    'data' => $data,
    'stats' => $stats,
    'recordsTotal' => count($data),
    'recordsFiltered' => count($data)
], JSON_UNESCAPED_UNICODE);