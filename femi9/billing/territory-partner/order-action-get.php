<?php
include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");
error_reporting(0);

if (isset($_POST['add_order_get'])) {

    $tp_id      = (int)$_POST['tp_id'];
    $order_date = $_POST['order_date'];
    $order_id   = $_POST['order_id'];
    $shop_id    = (int)$_POST['shop_id'];
    $marketing_tool = RemoveSpecialChar($_POST['marketing_tool'] ?? '');

    $shopCheck = mysqli_prepare($db_conn,
        "SELECT id FROM shop WHERE id=? AND onboard_userID=? AND onboard_userTYPE='territory_partner' LIMIT 1"
    );
    mysqli_stmt_bind_param($shopCheck, "is", $shop_id, $tp_id);
    mysqli_stmt_execute($shopCheck);
    $ownsShop = mysqli_stmt_get_result($shopCheck)->fetch_assoc();
    mysqli_stmt_close($shopCheck);

    if ($ownsShop) {
        $prIds     = $_POST['pr_id'] ?? [];
        $qtys      = $_POST['qty'] ?? [];
        $discPcts  = $_POST['discount_percentage'] ?? [];
        $discAmts  = $_POST['discount_amount'] ?? [];
        $insCount = 0;

        $ins = mysqli_prepare($db_conn,
            "INSERT INTO tp_orders (order_id, shop_id, tp_id, order_date, new_order, noorder_reason, marketing_tool, pr_id, qty, discount_percentage, discount_amount)
             VALUES (?, ?, ?, ?, 'yes', 'nil', ?, ?, ?, ?, ?)"
        );

        for ($i = 0; $i < count($prIds); $i++) {
            $prId     = (int)$prIds[$i];
            $qty      = (int)($qtys[$i] ?? 0);
            $discPct  = (float)($discPcts[$i] ?? 0);
            $discAmt  = (float)($discAmts[$i] ?? 0);
            if ($prId <= 0 || $qty <= 0) continue;

            // Skip if this exact line was already submitted for this order_id
            // (mirrors the duplicate guard used by the got-order/no-order flow).
            $dupCheck = mysqli_prepare($db_conn, "SELECT id FROM tp_orders WHERE order_id=? AND pr_id=? LIMIT 1");
            mysqli_stmt_bind_param($dupCheck, "si", $order_id, $prId);
            mysqli_stmt_execute($dupCheck);
            $dup = mysqli_stmt_get_result($dupCheck)->fetch_assoc();
            mysqli_stmt_close($dupCheck);
            if ($dup) continue;

            mysqli_stmt_bind_param($ins, "siissiidd", $order_id, $shop_id, $tp_id, $order_date, $marketing_tool, $prId, $qty, $discPct, $discAmt);
            mysqli_stmt_execute($ins);
            $insCount++;
        }
        mysqli_stmt_close($ins);

        $_SESSION['successMessage'] = $insCount > 0
            ? "Product order details added successfully!"
            : "No valid product/qty rows were submitted.";
    } else {
        $_SESSION['errorMessage'] = "Selected shop is not valid for this account.";
    }

    header('Location: manage-orders.php');
    exit;
}
?>
