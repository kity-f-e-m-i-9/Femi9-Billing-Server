<?php require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
include("config.php");
require_once __DIR__ . '/include/ShopStateIdResolver.php';
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

mysqli_query($db_conn,
    "UPDATE shop SET
        name='".db_esc($name)."', email='".db_esc($email)."', mobile_number='".db_esc($mobile)."',
        address='".db_esc($address)."', shop_cat='".db_esc($shop_cat)."', gstin='".db_esc($gstin)."',
        country_code='".db_esc($country_code)."', landline='".db_esc($landline)."',
        state_id='".db_esc($state_id)."', district_id='".db_esc($district_id)."',
        taluk_id='".db_esc($taluk_id)."', firka_id='".db_esc($firka_id)."'
     WHERE id='".db_esc($update_id)."'"
);

// order-confirm-shop.php's "new shop, review details first" path
// (shop-edit.php carries order_id through as a hidden field) — once the
// reviewed/corrected shop details are saved, continue straight into the
// invoice instead of going back to Manage Shop. A real POST (not a GET
// redirect) since order-to-invoice-action.php only accepts POST; order_id
// ownership/pending-state is re-validated there regardless of what's passed
// here. 2026-12-01, user-requested.
$order_id_for_invoice = $_POST['order_id'] ?? '';
if ($order_id_for_invoice !== '') {
    ?>
    <!DOCTYPE html><html><body>
    <form id="ocsContinueForm" method="post" action="order-to-invoice-action.php">
        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id_for_invoice, ENT_QUOTES); ?>">
        <input type="hidden" name="shop_id" value="<?php echo (int)$update_id; ?>">
    </form>
    <script>document.getElementById('ocsContinueForm').submit();</script>
    </body></html>
    <?php
    exit;
}

echo "<script>window.location='shop-manage.php?updatedSuccess';</script>";
