<?php
/**
 * READ-ONLY diagnostic for the cnote_manage "incomplete loading" issue.
 * Upload this into femi9/billing/company/ next to cnote_manage.php,
 * hit it once in the browser while logged in as company, paste the
 * output back, then DELETE this file from the server.
 *
 * Does not write anything. Only SELECTs.
 */
include("checksession.php");
include("config.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

echo "=== cnote_manage diagnostic ===\n";
echo "Login_user_TYPEvl: " . ($Login_user_TYPEvl ?? '(unset)') . "\n\n";

function check_table($db_conn, $table) {
    $r = mysqli_query($db_conn, "SHOW TABLES LIKE '$table'");
    $exists = $r && mysqli_num_rows($r) > 0;
    echo "table $table: " . ($exists ? "EXISTS" : "MISSING") . "\n";
    return $exists;
}

echo "--- table existence ---\n";
check_table($db_conn, 'user_return_stock');
check_table($db_conn, 'super_stockiest');
check_table($db_conn, 'stockiest');
check_table($db_conn, 'super_distributor');
check_table($db_conn, 'distributor');
check_table($db_conn, 'outlet');
check_table($db_conn, 'shop');
check_table($db_conn, 'c_and_f');
check_table($db_conn, 'customers');
check_table($db_conn, 'user_invoice');
check_table($db_conn, 'invoice');
check_table($db_conn, 'admin_log');
echo "\n";

echo "--- admin_log.manage_return column ---\n";
$r = mysqli_query($db_conn, "SHOW COLUMNS FROM admin_log LIKE 'manage_return'");
echo ($r && mysqli_num_rows($r) > 0 ? "EXISTS" : "MISSING") . "\n\n";

echo "--- user_return_stock row counts ---\n";
$r = mysqli_query($db_conn, "SELECT to_usertype, count(*) c FROM user_return_stock GROUP BY to_usertype");
if (!$r) { echo "QUERY ERROR: " . mysqli_error($db_conn) . "\n"; }
else { while ($row = mysqli_fetch_assoc($r)) echo "  to_usertype={$row['to_usertype']}: {$row['c']}\n"; }
echo "\n";

$loginType = $Login_user_TYPEvl ?? 'company';
echo "--- rows where to_usertype='$loginType' (what the page actually queries) ---\n";
$select_product_list = "select * from user_return_stock where to_usertype='$loginType' order by id desc";
$fetch_product_list = mysqli_query($db_conn, $select_product_list);
if (!$fetch_product_list) {
    echo "QUERY ERROR: " . mysqli_error($db_conn) . "\n";
    exit;
}
echo "total rows returned: " . mysqli_num_rows($fetch_product_list) . "\n\n";

echo "--- per-row simulation (same logic as cnote_manage.php) ---\n";
$i = 0;
$errors = 0;
$rowNum = 0;
while ($result_product_list = mysqli_fetch_array($fetch_product_list)) {
    $rowNum++;
    $getinvuser = $result_product_list['from_usertype'];
    $returnid = $result_product_list['returnid'];
    $invid = $result_product_list['invnumber'];

    $tablename = null;
    $handled = true;
    switch ($getinvuser) {
        case "candf": $tablename = "c_and_f"; break;
        case "super_stockiest": $tablename = "super_stockiest"; break;
        case "stockiest": $tablename = "stockiest"; break;
        case "super_distributor": $tablename = "super_distributor"; break;
        case "distributor": $tablename = "distributor"; break;
        case "outlet": $tablename = "outlet"; break;
        case "shop": $tablename = "shop"; break;
        case "customer": $tablename = null; break; // handled separately
        default:
            $handled = false;
            echo "  ROW $rowNum (returnid=$returnid): UNHANDLED from_usertype='$getinvuser' -> falls through to 'Customer' label/lookup (likely bug)\n";
    }

    $custOk = true;
    if ($getinvuser == "customer") {
        $CuSTID = $result_product_list['from_userid'];
        if ($CuSTID != 0) {
            $q = "select * from customers where id='$CuSTID'";
            $res = mysqli_query($db_conn, $q);
            if (!$res) { echo "  ROW $rowNum: customers QUERY ERROR: " . mysqli_error($db_conn) . "\n"; $errors++; $custOk = false; }
            elseif (mysqli_num_rows($res) == 0) { echo "  ROW $rowNum (returnid=$returnid): customers id=$CuSTID NOT FOUND (orphaned)\n"; }
        }
    } elseif ($tablename !== null) {
        $CuSTID = $result_product_list['from_userid'];
        $q = "select * from " . $tablename . " where temp_id='$CuSTID'";
        $res = mysqli_query($db_conn, $q);
        if (!$res) { echo "  ROW $rowNum: $tablename QUERY ERROR: " . mysqli_error($db_conn) . "\n"; $errors++; $custOk = false; }
        elseif (mysqli_num_rows($res) == 0) { echo "  ROW $rowNum (returnid=$returnid, from_usertype=$getinvuser): $tablename.temp_id=$CuSTID NOT FOUND (orphaned)\n"; }
    } elseif (!$handled) {
        // unhandled type falls into customers lookup on from_userid in the real page
        $CuSTID = $result_product_list['from_userid'];
        $q = "select * from " . ($tablename ?? 'customers') . " where temp_id='$CuSTID'";
    }

    // invoice join
    if ($getinvuser == "customer") {
        $qi = "select * from invoice where inv_id='$invid'";
    } else {
        $qi = "select * from user_invoice where inv_id='$invid'";
    }
    $resi = mysqli_query($db_conn, $qi);
    if (!$resi) {
        echo "  ROW $rowNum (returnid=$returnid): invoice-join QUERY ERROR: " . mysqli_error($db_conn) . "\n";
        $errors++;
    } elseif (mysqli_num_rows($resi) == 0) {
        echo "  ROW $rowNum (returnid=$returnid): invoice lookup inv_id=$invid NOT FOUND (invoice number will show blank)\n";
    }
}

echo "\n--- summary ---\n";
echo "rows in result set: $rowNum\n";
echo "hard query errors encountered: $errors\n";
echo "\nIf 'rows in result set' here differs from what you count on the actual\n";
echo "cnote_manage page, the gap is happening in rendering/JS, not the query.\n";
echo "If it matches, the issues above (orphaned/unhandled rows) are your answer.\n";
