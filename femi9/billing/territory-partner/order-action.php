<?php
include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");
error_reporting(0);

if (isset($_POST['add_order_no'])) {

    $tp_id      = (int)$_POST['tp_id'];
    $order_date = $_POST['order_date'];
    $order_id   = $_POST['order_id'];
    $shop_id    = (int)$_POST['shop_id'];

    $noorder_reason = RemoveSpecialChar($_POST['noorder_reason'] ?? '');
    $marketing_tool = RemoveSpecialChar($_POST['marketing_tool'] ?? '');

    // Confirm this shop actually belongs to the logged-in TP before writing.
    $shopCheck = mysqli_prepare($db_conn,
        "SELECT id FROM shop WHERE id=? AND onboard_userID=? AND onboard_userTYPE='territory_partner' LIMIT 1"
    );
    mysqli_stmt_bind_param($shopCheck, "is", $shop_id, $tp_id);
    mysqli_stmt_execute($shopCheck);
    $ownsShop = mysqli_stmt_get_result($shopCheck)->fetch_assoc();
    mysqli_stmt_close($shopCheck);

    if ($ownsShop) {
        $dupCheck = mysqli_prepare($db_conn, "SELECT id FROM tp_orders WHERE order_id=? LIMIT 1");
        mysqli_stmt_bind_param($dupCheck, "s", $order_id);
        mysqli_stmt_execute($dupCheck);
        $dup = mysqli_stmt_get_result($dupCheck)->fetch_assoc();
        mysqli_stmt_close($dupCheck);

        if (!$dup) {
            $ins = mysqli_prepare($db_conn,
                "INSERT INTO tp_orders (order_id, shop_id, tp_id, order_date, new_order, noorder_reason, marketing_tool, pr_id, qty)
                 VALUES (?, ?, ?, ?, 'no', ?, ?, 0, 0)"
            );
            mysqli_stmt_bind_param($ins, "siisss", $order_id, $shop_id, $tp_id, $order_date, $noorder_reason, $marketing_tool);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
        }
        $_SESSION['successMessage'] = "No order details added successfully!";
    } else {
        $_SESSION['errorMessage'] = "Selected shop is not valid for this account.";
    }

    header('Location: manage-orders.php');
    exit;
}
?>
