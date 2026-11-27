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
require_once __DIR__ . '/../shared/ShopPendingReview.php';
ensureShopPendingReviewColumn($db_conn);
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

// Match the TP's own shop list by NAME ONLY (not mobile) — 2026-12-01 v2,
// user-corrected: the earlier name+mobile exact-match requirement (kept
// below in history for context) meant a shop the TP had clearly already
// added under the same name, just with a different/old mobile number on
// file, never got offered back — the TP saw "Yes, review & invoice" (as if
// this were a brand-new shop) even though searching their own list by hand
// immediately found it. User explicitly asked to test existence by name
// alone: "tp already antha shop ahh add panni irukangalanu antha name ahh
// vechu mattum test pannu". Case/whitespace-insensitive on purpose (a TP or
// DM retyping a name with different spacing/case still counts as the same
// real shop).
//
// A DM-entered shop name can collide with MULTIPLE of the TP's own shops
// (same name, different branch/owner/mobile) — e.g. two "Eswari Medical"
// entries in the same town. In that case the TP must pick which one is
// actually meant, so every name-match is collected and:
//   0 matches  -> genuinely new shop, same "review & invoice" flow as before
//   1 match    -> that one shop auto-selected, single confirm button
//   2+ matches -> a "Choose Shop" dropdown lists every match so the TP picks
// 2026-12-01 v2, user-requested ("dm potta shop name la tp rendu moonu shop
// vechu irukanga na choose shop nu dropdown la varanum").
function ocsNameKey($name): string {
    return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string)$name)));
}

$effectiveShopId   = (int)$order['shop_id'];
$effectiveShopName = $order['shop_name'];
$nameMatches       = [];

// If the order's OWN shop_id already points at a real, confirmed shop of
// this TP's (pending_review=0, not deleted) — not a freshly-bridged
// placeholder — there is nothing to match against; it already IS the TP's
// own shop. Without this check, the name-match query below (which
// deliberately excludes the order's own shop_id to avoid matching itself)
// found zero OTHER duplicates and wrongly fell through to the "new shop,
// needs review" flow even though order-confirm-shop.php's very own "Search
// your own shop" box showed this exact shop already sitting in the list.
// 2026-12-01 v3, user-reported (order already resolved to TP's real
// "Senthil medi" shop, still showed "Yes, review & invoice" instead of the
// auto-matched confirm).
$orderShopIsReal = false;
$resSelf = $db_conn->query("
    SELECT pending_review FROM shop
    WHERE id=" . (int)$order['shop_id'] . "
      AND onboard_userID='" . $db_conn->real_escape_string((string)$tp_id) . "'
      AND onboard_userTYPE='territory_partner' AND deleted_at IS NULL
    LIMIT 1
");
if ($resSelf && ($selfRow = $resSelf->fetch_assoc())) {
    $orderShopIsReal = ((int)($selfRow['pending_review'] ?? 0) === 0);
}

$orderNameKey = $orderShopIsReal ? '' : ocsNameKey($order['shop_name'] ?? '');
if ($orderNameKey !== '') {
    $resOwn = $db_conn->query("
        SELECT s.id, s.name, s.mobile_number, COALESCE(dn.name, '') AS district
        FROM shop s
        LEFT JOIN partner_location_nodes dn ON dn.id = s.district_id
        WHERE s.onboard_userID='" . $db_conn->real_escape_string((string)$tp_id) . "'
          AND s.onboard_userTYPE='territory_partner' AND s.deleted_at IS NULL AND s.pending_review = 0
          AND s.id != " . (int)$order['shop_id'] . "
        ORDER BY s.name ASC
    ");
    while ($ownRow = $resOwn->fetch_assoc()) {
        if (ocsNameKey($ownRow['name']) === $orderNameKey) {
            $nameMatches[] = $ownRow;
        }
    }
}
$matchCount  = count($nameMatches);
$autoMatched = $orderShopIsReal || ($matchCount === 1);
if ($autoMatched && !$orderShopIsReal) {
    $effectiveShopId   = (int)$nameMatches[0]['id'];
    $effectiveShopName = $nameMatches[0]['name'];
}
// $orderShopIsReal keeps $effectiveShopId/$effectiveShopName as the order's
// own shop_id/shop_name (already set above) — that IS the real shop.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice This Order : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        .ocs-card { max-width:620px; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); padding:28px; background:#fff; }
        .ocs-shop-name { font-size:17px; font-weight:700; color:#1f2937; }
        .ocs-btn-row { display:flex; gap:10px; margin-top:18px; flex-wrap:wrap; }
        #ocsSearchBlock { margin-top:18px; border-top:1px solid #e5e7eb; padding-top:16px; }
        .ocs-result { padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; margin-bottom:8px; cursor:pointer; }
        .ocs-result:hover { background:#f8fafc; border-color:#667eea; }
        .ocs-result .name { font-weight:600; }
        .ocs-result .meta { font-size:12px; color:#6b7280; }
    </style>
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1><table class="headertble"><tr>
                                    <td>Invoice This Order</td>
                                    <td><a href="manage-orders.php" title="Manage Orders">&#9776;</a></td>
                                </tr></table></h1>
                            </div>
                            <div class="ocs-card">
                                <p style="color:#6b7280;font-size:13px;">Which shop should this invoice be billed to?</p>

                                <div style="background:#f8fafc;border-radius:8px;padding:14px 16px;margin-top:10px;">
                                    <span style="font-size:12px;color:#6b7280;">Shop added for this order</span>
                                    <div class="ocs-shop-name"><?php echo htmlspecialchars($order['shop_name'] ?? '(no name)'); ?></div>
                                    <?php if ($autoMatched): ?>
                                    <div style="margin-top:10px;padding-top:10px;border-top:1px dashed #d1d5db;">
                                        <span style="font-size:12px;color:#166534;font-weight:600;">✓ Already in your own shop list — this invoice will go there instead</span>
                                        <div class="ocs-shop-name" style="color:#166534;"><?php echo htmlspecialchars($effectiveShopName); ?></div>
                                    </div>
                                    <?php elseif ($matchCount >= 2): ?>
                                    <div style="margin-top:10px;padding-top:10px;border-top:1px dashed #d1d5db;">
                                        <span style="font-size:12px;color:#92400e;font-weight:600;">⚠ <?php echo $matchCount; ?> shops in your own list share this name — choose which one</span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($matchCount >= 2): ?>
                                <div style="margin-top:14px;">
                                    <label class="form-label" for="ocsChooseShopSelect">Choose Shop</label>
                                    <select class="form-select" id="ocsChooseShopSelect">
                                        <option value="">-- Select a shop --</option>
                                        <?php foreach ($nameMatches as $m): ?>
                                        <option value="<?php echo (int)$m['id']; ?>">
                                            <?php echo htmlspecialchars($m['name']); ?> — <?php echo htmlspecialchars($m['mobile_number']); ?><?php echo $m['district'] !== '' ? ' — ' . htmlspecialchars($m['district']) : ''; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="ocs-btn-row">
                                        <button type="button" class="btn btn-primary" id="ocsChooseShopBtn" onclick="useChosenShop()" disabled>Invoice Selected Shop</button>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="ocs-btn-row">
                                    <button type="button" class="btn btn-primary" onclick="useThisShop()"><?php echo $autoMatched ? 'Yes, use my existing shop' : '+ Add this shop to my login'; ?></button>
                                </div>
                                <?php endif; ?>

                                <!-- Always visible, pre-populated with the order's shop name and
                                     auto-searched on load — same list "No, pick a different shop"
                                     used to only reveal on click. 2026-12-01 v4, user-requested
                                     ("no pick a different shop la varura matching list, match
                                     analum varanum, click panna theva illa"). -->
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
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
            // (shop-edit.php, carrying order_id through). After saving there,
            // shop-edit-action.php now sends the TP back to THIS page instead
            // of straight into the invoice, so they confirm/invoice from
            // here. 2026-12-01 v4, user-requested ("save pannitu thirupi
            // intha page ku load agi vanthu athukku apram invoice podura
            // mathiri").
            window.location = 'shop-edit.php?prid=' + encodeURIComponent(btoa(String(ownShopId))) + '&order_id=' + encodeURIComponent(orderIdForInvoice);
        }
    }
    function useChosenShop() {
        var sel = document.getElementById('ocsChooseShopSelect');
        if (!sel || !sel.value) { return; }
        document.getElementById('ocsShopIdField').value = sel.value;
        document.getElementById('ocsForm').submit();
    }
    var chooseShopSelect = document.getElementById('ocsChooseShopSelect');
    if (chooseShopSelect) {
        chooseShopSelect.addEventListener('change', function () {
            document.getElementById('ocsChooseShopBtn').disabled = !this.value;
        });
    }
    window.useThisShop = useThisShop;
    window.useChosenShop = useChosenShop;

    var searchTimer = null;
    var searchInput = document.getElementById('ocsSearchInput');
    function runSearch() {
        var q = searchInput.value.trim();
        if (q.length < 2) { document.getElementById('ocsResults').innerHTML = ''; return; }
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
    }
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(runSearch, 250);
    });
    // Auto-run once on load, same as the old "No, pick a different shop"
    // click used to trigger — the box is always visible now, so it should
    // already be populated without waiting for a click.
    if (searchInput.value.trim().length >= 2) { runSearch(); }
})();
</script>
</body>
</html>
