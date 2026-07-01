<?php
/**
 * Return Validation Helper Functions for Stockist
 * Provides quantity validation for credit notes/returns
 * NO advance payment functions for stockist
 */

/**
 * Get already returned quantity for a specific invoice-product combination
 * 
 * @param mysqli $dbConn Database connection
 * @param string $invid Invoice ID
 * @param string $prid Product ID
 * @param string|null $current_returnid Current return ID to exclude from calculation
 * @return int Total quantity already returned
 */
function getAlreadyReturnedQty(
    mysqli $dbConn,
    string $invid,
    string $prid,
    ?string $current_returnid = null
): int {
    $invid = mysqli_real_escape_string($dbConn, $invid);
    $prid = mysqli_real_escape_string($dbConn, $prid);
    
    $exclude_clause = '';
    if ($current_returnid !== null && $current_returnid !== '') {
        $current_returnid = mysqli_real_escape_string($dbConn, $current_returnid);
        $exclude_clause = " AND returnid != '$current_returnid'";
    }
    
    $query = "
        SELECT COALESCE(SUM(qty), 0) AS total_returned
        FROM user_return_stock_items
        WHERE invnumber = '$invid'
          AND prid = '$prid'
          AND status IN ('pending', 'accept', 'completed')
          $exclude_clause
    ";
    
    $result = mysqli_query($dbConn, $query);
    
    if (!$result) {
        error_log("getAlreadyReturnedQty ERROR: " . mysqli_error($dbConn));
        return 0;
    }
    
    $row = mysqli_fetch_assoc($result);
    return (int)($row['total_returned'] ?? 0);
}

/**
 * Get detailed return availability information
 * 
 * @param mysqli $dbConn Database connection
 * @param string $invid Invoice ID  
 * @param string $prid Product ID
 * @param string $from_usertype User type
 * @param string|null $current_returnid Current return ID to exclude
 * @return array Contains original_qty, returned_qty, available_qty, error
 */
function getReturnAvailability(
    mysqli $dbConn,
    string $invid,
    string $prid,
    string $from_usertype,
    ?string $current_returnid = null
): array {
    $invid = mysqli_real_escape_string($dbConn, $invid);
    $prid = mysqli_real_escape_string($dbConn, $prid);
    $from_usertype = mysqli_real_escape_string($dbConn, $from_usertype);
    
    $item_table = ($from_usertype === 'customer') ? 'invoice_items' : 'user_invoice_items';
    
    $inv_query = "
        SELECT qty 
        FROM $item_table 
        WHERE inv_id = '$invid' 
          AND pr_id = '$prid'
        LIMIT 1
    ";
    
    $inv_result = mysqli_query($dbConn, $inv_query);
    
    if (!$inv_result || mysqli_num_rows($inv_result) === 0) {
        return [
            'original_qty' => 0,
            'returned_qty' => 0,
            'available_qty' => 0,
            'error' => 'Product not found in invoice'
        ];
    }
    
    $inv_item = mysqli_fetch_assoc($inv_result);
    $original_qty = (int)$inv_item['qty'];
    
    $returned_qty = getAlreadyReturnedQty($dbConn, $invid, $prid, $current_returnid);
    
    $available_qty = $original_qty - $returned_qty;
    
    return [
        'original_qty' => $original_qty,
        'returned_qty' => $returned_qty,
        'available_qty' => max(0, $available_qty),
        'error' => null
    ];
}
?>
