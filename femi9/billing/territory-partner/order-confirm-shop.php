<?php
// Replaces the old "Convert" modal (which only let a TP link a pending
// order to an invoice they'd ALREADY separately raised) with a direct path
// into creating the real invoice right now — same concept as the existing
// "Invoice" button on a plain Field Order (order-to-invoice.php), extended
// with one extra step: confirm whether to bill the shop exactly as it was
// typed on the order, or search the TP's own real shop list instead.
// 2026-12-01, user-requested ("munnadi invoice nu oru button irunthu field
// order la antha concept ah... dm add panna shop details laiye use
// pannikuringanu kekkum, no sonna avnga add panna antha name la iruka shop
// name ah search pannura matthiri vechuru").
include("checksession.php");
include("config.php");
error_reporting(0);

$tp_id    = (int)$Login_user_IDvl;
$order_id = $_GET['order_id'] ?? '';
if ($order_id === '') {
    header('Location: manage-orders.php');
    exit;
}

$stmt = $db_conn->prepare(
    "SELECT o.shop_id, o.new_order, o.invoiced_inv_id, s.name AS shop_name, s.mobile_number AS shop_mobile
     FROM tp_orders o
     LEFT JOIN shop s ON s.id = o.shop_id
     WHERE o.order_id=? AND o.tp_id=? LIMIT 1"
);
$stmt->bind_param('si', $order_id, $tp_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order || $order['new_order'] !== 'yes') {
    $_SESSION['errorMessage'] = "This visit has no order to invoice.";
    header('Location: manage-orders.php');
    exit;
}
if (!empty($order['invoiced_inv_id'])) {
    header('Location: shop-invoice-add.php?InvoiceID=' . base64_encode($order['invoiced_inv_id']) . '&invuser=shop&action=edit');
    exit;
}

// If the TP already has their OWN shop record for this exact real shop (name
// + mobile match, same normalised key dashboard.php's own New Shop
// Onboarding dedupe uses), that one should be the default "Yes" — not the
// order's own shop_id, which for a DM-assigned order is usually a separately
// bridged/auto-created record (OrderTpBridge.php) that can end up as a
// genuine duplicate of a shop this TP already added themselves. 2026-12-01,
// user-reported real case: "jayadeepa Femi9WD" (8148193835) existed TWICE —
// once bridged from the DM's order, once the TP had added it directly —
// invoicing the bridged copy by default would have kept splitting this
// shop's real history across two records instead of consolidating it.
// Case/whitespace-insensitive on purpose ("capital letter small letter
// space ellam be careful" — a TP or DM retyping a name with different
// spacing/case must still count as the same real shop).
function ocsDedupeKey($name, $mobile): ?string {
    $digits = preg_replace('/\D+/', '', (string)$mobile);
    $nm = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string)$name)));
    if (strlen($digits) === 10 && $nm !== '') { return $nm . '|' . $digits; }
    return null;
}

$effectiveShopId   = (int)$order['shop_id'];
$effectiveShopName = $order['shop_name'];
$autoMatched       = false;
$orderKey = ocsDedupeKey($order['shop_name'], $order['shop_mobile']);
if ($orderKey !== null) {
    $resOwn = $db_conn->query("
        SELECT id, name, mobile_number
        FROM shop
        WHERE onboard_userID='" . $db_conn->real_escape_string((string)$tp_id) . "'
          AND onboard_userTYPE='territory_partner' AND deleted_at IS NULL
          AND id != " . (int)$order['shop_id']
    );
    while ($ownRow = $resOwn->fetch_assoc()) {
        if (ocsDedupeKey($ownRow['name'], $ownRow['mobile_number']) === $orderKey) {
            $effectiveShopId   = (int)$ownRow['id'];
            $effectiveShopName = $ownRow['name'];
            $autoMatched       = true;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice This Order : <?php echo $business_name; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .ocs-card { max-width:560px; margin:40px auto; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); padding:28px; background:#fff; }
        .ocs-shop-name { font-size:17px; font-weight:700; color:#1f2937; }
        .ocs-btn-row { display:flex; gap:10px; margin-top:18px; flex-wrap:wrap; }
        #ocsSearchBlock { display:none; margin-top:18px; border-top:1px solid #e5e7eb; padding-top:16px; }
        .ocs-result { padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; margin-bottom:8px; cursor:pointer; }
        .ocs-result:hover { background:#f8fafc; border-color:#667eea; }
        .ocs-result .name { font-weight:600; }
        .ocs-result .meta { font-size:12px; color:#6b7280; }
    </style>
</head>
<body>
<div class="ocs-card">
    <h4 style="font-weight:700;margin-bottom:4px;">Invoice This Order</h4>
    <p style="color:#6b7280;font-size:13px;">Which shop should this invoice be billed to?</p>

    <div style="background:#f8fafc;border-radius:8px;padding:14px 16px;margin-top:10px;">
        <span style="font-size:12px;color:#6b7280;">Shop added for this order</span>
        <div class="ocs-shop-name"><?php echo htmlspecialchars($order['shop_name'] ?? '(no name)'); ?></div>
        <?php if ($autoMatched): ?>
        <div style="margin-top:10px;padding-top:10px;border-top:1px dashed #d1d5db;">
            <span style="font-size:12px;color:#166534;font-weight:600;">✓ Already in your own shop list — this invoice will go there instead</span>
            <div class="ocs-shop-name" style="color:#166534;"><?php echo htmlspecialchars($effectiveShopName); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="ocs-btn-row">
        <button type="button" class="btn btn-primary" onclick="useThisShop()"><?php echo $autoMatched ? 'Yes, use my existing shop' : 'Yes, review & invoice'; ?></button>
        <button type="button" class="btn btn-outline-secondary" onclick="showSearch()">No, pick a different shop</button>
    </div>

    <div id="ocsSearchBlock">
        <label class="form-label">Search your own shop</label>
        <input type="text" id="ocsSearchInput" class="form-control" placeholder="Type shop name..." value="<?php echo htmlspecialchars($order['shop_name'] ?? '', ENT_QUOTES); ?>">
        <div id="ocsResults" style="margin-top:12px;"></div>
    </div>

    <form method="post" action="order-to-invoice-action.php" id="ocsForm">
        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id, ENT_QUOTES); ?>">
        <input type="hidden" name="shop_id" id="ocsShopIdField" value="">
    </form>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script>
(function () {
    var ownShopId   = <?php echo (int)$effectiveShopId; ?>;
    var autoMatched = <?php echo $autoMatched ? 'true' : 'false'; ?>;
    var orderIdForInvoice = <?php echo json_encode($order_id); ?>;
    function useThisShop() {
        if (autoMatched) {
            // Already a real, known shop in this TP's own list — straight to
            // invoicing, same as picking one from search.
            document.getElementById('ocsShopIdField').value = ownShopId;
            document.getElementById('ocsForm').submit();
        } else {
            // No match at all — this is genuinely a new shop for the TP, so
            // the DM-typed details need a human review/edit pass first
            // (shop-edit.php, carrying order_id through so it continues
            // straight into the invoice once saved). 2026-12-01, user-
            // requested ("dm add shop kudutha apo antha shop ku enna enna
            // detail la kudutharo athu ellam kattidanum editable ahh").
            window.location = 'shop-edit.php?prid=' + encodeURIComponent(btoa(String(ownShopId))) + '&order_id=' + encodeURIComponent(orderIdForInvoice);
        }
    }
    function showSearch() {
        document.getElementById('ocsSearchBlock').style.display = 'block';
        var input = document.getElementById('ocsSearchInput');
        if (input.value.trim().length >= 2) {
            input.dispatchEvent(new Event('input'));
        }
    }
    window.useThisShop = useThisShop;
    window.showSearch = showSearch;

    var searchTimer = null;
    document.getElementById('ocsSearchInput').addEventListener('input', function (e) {
        var q = e.target.value.trim();
        clearTimeout(searchTimer);
        if (q.length < 2) { document.getElementById('ocsResults').innerHTML = ''; return; }
        searchTimer = setTimeout(function () {
            fetch('get-tp-shop-search.php?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    var el = document.getElementById('ocsResults');
                    if (!rows.length) { el.innerHTML = '<div class="text-muted" style="font-size:13px;">No matching shop found.</div>'; return; }
                    el.innerHTML = rows.map(function (r) {
                        return '<div class="ocs-result" data-id="' + r.id + '"><div class="name"></div><div class="meta"></div></div>';
                    }).join('');
                    Array.prototype.forEach.call(el.querySelectorAll('.ocs-result'), function (div, idx) {
                        div.querySelector('.name').textContent = rows[idx].name;
                        div.querySelector('.meta').textContent = (rows[idx].mobile || '') + (rows[idx].district ? ' — ' + rows[idx].district : '');
                        div.addEventListener('click', function () {
                            document.getElementById('ocsShopIdField').value = rows[idx].id;
                            document.getElementById('ocsForm').submit();
                        });
                    });
                });
        }, 250);
    });
})();
</script>
</body>
</html>
