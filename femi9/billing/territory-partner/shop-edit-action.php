<?php require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
include("config.php");
require_once __DIR__ . '/include/ShopStateIdResolver.php';
require_once __DIR__ . '/../shared/ShopPendingReview.php';
ensureShopPendingReviewColumn($db_conn);
error_reporting(0);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: shop-manage.php"); exit;
}

$update_id    = (int)$_POST["update_id"];
$shop_cat     = $_POST["shop_cat"]      ?? '';
$name         = str_replace("'", "&#39;", $_POST["name"]         ?? '');
$mobile       = str_replace("'", "&#39;", $_POST["mobile_number"]?? '');
$landline     = str_replace("'", "&#39;", $_POST["landline"]     ?? '');
$email        = str_replace("'", "&#39;", $_POST["email"]        ?? '');
$address      = str_replace("'", "&#39;", $_POST["address"]      ?? '');
$gstin        = str_replace("'", "&#39;", $_POST["gstin"]        ?? '');
$country_code = $_POST["country_code"]  ?? '';
$state_id    = resolveRealStateId($db_conn, (int)($_POST["state_id"] ?? 0));
$district_id = (int)($_POST["district_id"] ?? 0);
$taluk_id    = (int)($_POST["taluk_id"]    ?? 0);
$firka_id    = (int)($_POST["firka_id"]    ?? 0);

// pending_review cleared unconditionally on every save, not just the
// order-confirm-shop.php continuation path below — a no-op for a shop that
// was never pending, and the thing that makes a freshly order-bridged shop
// (shared/ShopPendingReview.php) finally show up in Manage Shop once the TP
// has reviewed/corrected it here. 2026-12-01, user-requested.
mysqli_query($db_conn,
    "UPDATE shop SET
        name='".db_esc($name)."', email='".db_esc($email)."', mobile_number='".db_esc($mobile)."',
        address='".db_esc($address)."', shop_cat='".db_esc($shop_cat)."', gstin='".db_esc($gstin)."',
        country_code='".db_esc($country_code)."', landline='".db_esc($landline)."',
        state_id='".db_esc($state_id)."', district_id='".db_esc($district_id)."',
        taluk_id='".db_esc($taluk_id)."', firka_id='".db_esc($firka_id)."', pending_review=0
     WHERE id='".db_esc($update_id)."'"
);

// order-confirm-shop.php's "new shop, review details first" path
// (shop-edit.php carries order_id through as a hidden field) — once the
// reviewed/corrected shop details are saved, go BACK to order-confirm-shop.php
// instead of straight into the invoice, so the TP sees the confirm screen
// (now auto-matched against the shop they just reviewed/saved) and clicks
// Invoice themselves from there. 2026-12-01 v4, user-corrected: originally
// this auto-submitted straight into order-to-invoice-action.php, skipping
// the confirm step entirely ("save pannitu thirupi intha page ku load agi
// vanthu athukku apram invoice podura mathiri").
$order_id_for_invoice = $_POST['order_id'] ?? '';
if ($order_id_for_invoice !== '') {
    header('Location: order-confirm-shop.php?order_id=' . urlencode($order_id_for_invoice));
    exit;
}

echo "<script>window.location='shop-manage.php?updatedSuccess';</script>";
