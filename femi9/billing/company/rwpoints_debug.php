<?php
// ============================================================================
// TEMPORARY DEBUG SCRIPT - paste this ABOVE your main page or run standalone
// Access via browser to see which query fails
// REMOVE AFTER DEBUGGING
// ============================================================================

require_once("checksession.php");
require_once("config.php");

ini_set('display_errors', 1);
error_reporting(E_ALL);

$allowed_user_types = ['super_stockiest', 'stockiest', 'super_distributor', 'distributor'];
$getinvuser = $_REQUEST['femiusr'] ?? 'distributor';
if (!in_array($getinvuser, $allowed_user_types, true)) die("Invalid user type");

date_default_timezone_set("Asia/Kolkata");
$current_month     = date('m');
$numberOfDays      = (int)date('t');
$current_from_date = date("Y-{$current_month}-01");
$current_to_date   = date("Y-{$current_month}-{$numberOfDays}");

echo "<h2>Debug: Reward Points Queries</h2>";
echo "<p>User Type: <b>{$getinvuser}</b> | From: <b>{$current_from_date}</b> | To: <b>{$current_to_date}</b></p>";
echo "<hr>";

// ---- PDO Connect ----
try {
    $pdo = new PDO(
        "mysql:host={$servername};port={$db_port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
    echo "<p style='color:green'>✅ DB Connection OK</p>";
} catch (PDOException $e) {
    die("<p style='color:red'>❌ DB Connection FAILED: " . $e->getMessage() . "</p>");
}

// ---- Helper ----
function runQuery($pdo, $label, $sql, $params) {
    echo "<h4>{$label}</h4>";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        echo "<p style='color:green'>✅ OK — " . count($rows) . " row(s) returned</p>";
        if (!empty($rows)) {
            echo "<pre style='font-size:11px;background:#f5f5f5;padding:8px'>";
            print_r(array_slice($rows, 0, 3)); // show max 3 rows
            echo "</pre>";
        }
        return $rows;
    } catch (PDOException $e) {
        echo "<p style='color:red'>❌ FAILED: " . $e->getMessage() . "</p>";
        return [];
    }
}

// ---- Q1: Purchase Points ----
runQuery($pdo, "Q1: Purchase Points", "
    SELECT 
        invoice_data.to_user_id as user_id,
        COALESCE(SUM(invoice_data.purchase_points), 0) as gross_purchase_points,
        COALESCE(SUM(return_data.return_points), 0) as deducted_purchase_points,
        GREATEST(
            COALESCE(SUM(invoice_data.purchase_points), 0) - COALESCE(SUM(return_data.return_points), 0),
            0
        ) as net_purchase_points
    FROM (
        SELECT uii.to_user_id, uii.inv_id, SUM(uii.subtotal) / 100 as purchase_points
        FROM user_invoice_items uii
        INNER JOIN user_invoice ui ON uii.inv_id = ui.inv_id
        WHERE uii.date BETWEEN :from_date1 AND :to_date1
            AND uii.to_user_type = :user_type1
            AND ui.rwpoints_enable = 1
        GROUP BY uii.to_user_id, uii.inv_id
    ) invoice_data
    LEFT JOIN (
        SELECT r.from_userid, r.invnumber, SUM(r.subtotal) / 100 as return_points
        FROM user_return_stock_items r
        WHERE r.invnumber IN (
            SELECT DISTINCT uii.inv_id 
            FROM user_invoice_items uii
            INNER JOIN user_invoice ui ON uii.inv_id = ui.inv_id
            WHERE uii.date BETWEEN :from_date2 AND :to_date2
                AND uii.to_user_type = :user_type2
                AND ui.rwpoints_enable = 1
        )
        AND r.from_usertype = :user_type3
        GROUP BY r.from_userid, r.invnumber
    ) return_data 
        ON return_data.from_userid = invoice_data.to_user_id 
        AND return_data.invnumber = invoice_data.inv_id
    GROUP BY invoice_data.to_user_id
", [
    ':from_date1' => $current_from_date, ':to_date1' => $current_to_date, ':user_type1' => $getinvuser,
    ':from_date2' => $current_from_date, ':to_date2' => $current_to_date, ':user_type2' => $getinvuser,
    ':user_type3' => $getinvuser
]);

// ---- Q2: User Sales Points ----
runQuery($pdo, "Q2: User-to-User Sales Points", "
    SELECT 
        invoice_data.from_user_id as user_id,
        COALESCE(SUM(invoice_data.sales_points), 0) as gross_user_sales_points,
        COALESCE(SUM(return_data.return_points), 0) as deducted_user_sales_points,
        GREATEST(
            COALESCE(SUM(invoice_data.sales_points), 0) - COALESCE(SUM(return_data.return_points), 0),
            0
        ) as net_user_sales_points
    FROM (
        SELECT uii.from_user_id, uii.inv_id, SUM(uii.subtotal) / 100 as sales_points
        FROM user_invoice_items uii
        INNER JOIN user_invoice ui ON uii.inv_id = ui.inv_id
        WHERE uii.date BETWEEN :from_date1 AND :to_date1
            AND uii.from_user_type = :user_type1
            AND ui.rwpoints_enable = 1
        GROUP BY uii.from_user_id, uii.inv_id
    ) invoice_data
    LEFT JOIN (
        SELECT r.to_userid, r.invnumber, SUM(r.subtotal) / 100 as return_points
        FROM user_return_stock_items r
        WHERE r.invnumber IN (
            SELECT DISTINCT uii.inv_id 
            FROM user_invoice_items uii
            INNER JOIN user_invoice ui ON uii.inv_id = ui.inv_id
            WHERE uii.date BETWEEN :from_date2 AND :to_date2
                AND uii.from_user_type = :user_type2
                AND ui.rwpoints_enable = 1
        )
        AND r.to_usertype = :user_type3
        GROUP BY r.to_userid, r.invnumber
    ) return_data 
        ON return_data.to_userid = invoice_data.from_user_id 
        AND return_data.invnumber = invoice_data.inv_id
    GROUP BY invoice_data.from_user_id
", [
    ':from_date1' => $current_from_date, ':to_date1' => $current_to_date, ':user_type1' => $getinvuser,
    ':from_date2' => $current_from_date, ':to_date2' => $current_to_date, ':user_type2' => $getinvuser,
    ':user_type3' => $getinvuser
]);

// ---- Q3: Check return_stock_items table ----
echo "<h4>Q3a: Check if return_stock_items table exists</h4>";
$table_exists = $pdo->query("SHOW TABLES LIKE 'return_stock_items'")->fetch();
if ($table_exists) {
    echo "<p style='color:green'>✅ return_stock_items EXISTS</p>";
    runQuery($pdo, "Q3: Customer Sales Points", "
        SELECT 
            invoice_data.user_id,
            COALESCE(SUM(invoice_data.sales_points), 0) as gross_customer_sales_points,
            COALESCE(SUM(return_data.return_points), 0) as deducted_customer_sales_points,
            GREATEST(
                COALESCE(SUM(invoice_data.sales_points), 0) - COALESCE(SUM(return_data.return_points), 0),
                0
            ) as net_customer_sales_points
        FROM (
            SELECT ii.user_id, ii.inv_id, SUM(ii.subtotal) / 100 as sales_points
            FROM invoice_items ii
            WHERE ii.date BETWEEN :from_date1 AND :to_date1
                AND ii.user_type = :user_type1
            GROUP BY ii.user_id, ii.inv_id
        ) invoice_data
        LEFT JOIN (
            SELECT r.user_id, r.invnumber, SUM(r.subtotal) / 100 as return_points
            FROM return_stock_items r
            WHERE r.invnumber IN (
                SELECT DISTINCT ii.inv_id FROM invoice_items ii
                WHERE ii.date BETWEEN :from_date2 AND :to_date2
                    AND ii.user_type = :user_type2
            )
            AND r.user_type = :user_type3
            GROUP BY r.user_id, r.invnumber
        ) return_data ON return_data.user_id = invoice_data.user_id 
            AND return_data.invnumber = invoice_data.inv_id
        GROUP BY invoice_data.user_id
    ", [
        ':from_date1' => $current_from_date, ':to_date1' => $current_to_date, ':user_type1' => $getinvuser,
        ':from_date2' => $current_from_date, ':to_date2' => $current_to_date, ':user_type2' => $getinvuser,
        ':user_type3' => $getinvuser
    ]);
} else {
    echo "<p style='color:orange'>⚠️ return_stock_items does NOT exist — fallback query will be used</p>";
    runQuery($pdo, "Q3 Fallback: Customer Sales Points", "
        SELECT ii.user_id,
            SUM(ii.subtotal) / 100 as gross_customer_sales_points,
            0 as deducted_customer_sales_points,
            SUM(ii.subtotal) / 100 as net_customer_sales_points
        FROM invoice_items ii
        WHERE ii.date BETWEEN :from_date AND :to_date AND ii.user_type = :user_type
        GROUP BY ii.user_id
    ", [':from_date' => $current_from_date, ':to_date' => $current_to_date, ':user_type' => $getinvuser]);
}

// ---- Q4: Daily Login Points ----
runQuery($pdo, "Q4: Daily Login Points", "
    SELECT user_id, SUM(points_awarded) as daily_points
    FROM daily_login_rewards
    WHERE user_type = :user_type
        AND reward_date BETWEEN :from_date AND :to_date
    GROUP BY user_id
    HAVING daily_points > 0
", [':user_type' => $getinvuser, ':from_date' => $current_from_date, ':to_date' => $current_to_date]);

// ---- Q5: Advance Bonus Points ----
$advance_bonus_user_types = ['super_stockiest', 'stockiest'];
if (in_array($getinvuser, $advance_bonus_user_types, true)) {
    runQuery($pdo, "Q5: Advance Bonus Points (bonus_points_history)", "
        SELECT user_id, SUM(bonus_points_awarded) as advance_bonus_points
        FROM bonus_points_history
        WHERE user_type = :user_type
            AND rolled_back_at IS NULL
        GROUP BY user_id
        HAVING advance_bonus_points > 0
    ", [':user_type' => $getinvuser]);
} else {
    echo "<h4>Q5: Advance Bonus Points</h4>";
    echo "<p style='color:gray'>⏭ Skipped — not applicable for user type: {$getinvuser}</p>";
}

echo "<hr><p><b>Debug complete.</b> Remove this file after identifying the issue.</p>";
