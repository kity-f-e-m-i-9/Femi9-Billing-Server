<?php
function getAlreadyReturnedQty(mysqli $dbConn, string $invid, string $prid, ?string $current_returnid = null): int {
    $invid = mysqli_real_escape_string($dbConn, $invid);
    $prid  = mysqli_real_escape_string($dbConn, $prid);
    $excl  = '';
    if ($current_returnid !== null && $current_returnid !== '') {
        $cr = mysqli_real_escape_string($dbConn, $current_returnid);
        $excl = " AND returnid != '$cr'";
    }
    $row = mysqli_fetch_assoc(mysqli_query($dbConn,
        "SELECT COALESCE(SUM(qty),0) AS total_returned
         FROM user_return_stock_items
         WHERE invnumber='$invid' AND prid='$prid'
           AND status IN ('pending','accept','completed') $excl"));
    return (int)($row['total_returned'] ?? 0);
}

function getReturnAvailability(mysqli $dbConn, string $invid, string $prid, string $from_usertype, ?string $current_returnid = null): array {
    $invid         = mysqli_real_escape_string($dbConn, $invid);
    $prid          = mysqli_real_escape_string($dbConn, $prid);
    $from_usertype = mysqli_real_escape_string($dbConn, $from_usertype);
    $item_table    = ($from_usertype === 'customer') ? 'invoice_items' : 'user_invoice_items';
    $res = mysqli_query($dbConn, "SELECT qty FROM $item_table WHERE inv_id='$invid' AND pr_id='$prid' LIMIT 1");
    if (!$res || mysqli_num_rows($res) === 0) {
        return ['original_qty' => 0, 'returned_qty' => 0, 'available_qty' => 0, 'error' => 'not found'];
    }
    $original_qty = (int)mysqli_fetch_assoc($res)['qty'];
    $returned_qty = getAlreadyReturnedQty($dbConn, $invid, $prid, $current_returnid);
    return [
        'original_qty'  => $original_qty,
        'returned_qty'  => $returned_qty,
        'available_qty' => max(0, $original_qty - $returned_qty),
        'error'         => null,
    ];
}
?>
