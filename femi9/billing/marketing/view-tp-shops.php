<?php
// Read-only view of a Territory Partner's own shop list (the `shop` table,
// onboard_userTYPE='territory_partner') — opened from Get Order's Shop*
// field once a firka's auto-detected TP is known, so a DM can check what
// shops that TP already services before picking/adding one of their own.
// Deliberately read-only and a SEPARATE page/table from ms_shop: shop.id and
// ms_shop.id are different id-spaces, and order_action_get.php's shop_id
// strictly means ms_shop.id — mixing the two into one dropdown would silently
// submit a wrong/nonexistent shop, so this never feeds back into the order
// form's own Shop select.
include("checksession.php");
include("config.php");
require_once("include/AssignedLocations.php");
error_reporting(0);

$tp_id = (int)($_GET['tp_id'] ?? 0);

// Only allow viewing a TP that actually covers part of THIS DM's own assigned
// territory (same scoping Get Order's own firka->TP auto-detect uses) — a DM
// can't browse an arbitrary TP's shop list by guessing ids.
$assignedDistricts = getMsAssignedDistricts($db_conn, (int)$markeingSTFID);
$allTalukIds = [];
foreach ($assignedDistricts as $d) {
    foreach ($d['taluks'] as $t) { $allTalukIds[] = $t['id']; }
}

$allowed = false;
$tpName = '';
if ($tp_id > 0 && !empty($allTalukIds)) {
    $talukIdList = implode(',', array_map('intval', $allTalukIds));
    $chk = $db_conn->query(
        "SELECT tp.name FROM territory_partner_locations tpl
         JOIN partner_location_nodes n ON n.id = tpl.location_id
         JOIN territory_partners tp ON tp.id = tpl.territory_partner_id
         WHERE tpl.territory_partner_id = $tp_id AND n.parent_id IN ($talukIdList) AND tp.is_active = 1
         LIMIT 1"
    );
    if ($chk && $chk->num_rows > 0) {
        $allowed = true;
        $tpName = $chk->fetch_assoc()['name'];
    }
}

$shops = [];
if ($allowed) {
    $shops = $db_conn->query(
        "SELECT id, name, mobile_number, district_name, taluk_name
         FROM shop
         WHERE onboard_userID = $tp_id AND onboard_userTYPE = 'territory_partner' AND deleted_at IS NULL
         ORDER BY name ASC"
    )->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Shops : <?php echo htmlspecialchars($business_name ?? '', ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
    <style>
        body { font-family: 'Poppins', sans-serif; padding: 24px; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:8px 11px; text-align:left; border-bottom:1px solid #e1e0d9; }
        .mt td { padding:8px 11px; border-bottom:1px solid #e1e0d9; }
    </style>
</head>
<body>
    <h1 style="font-size:20px;">
        <?php if ($allowed): ?>
            <?php echo htmlspecialchars($tpName, ENT_QUOTES, 'UTF-8'); ?>'s Shops (<?= count($shops) ?>)
        <?php else: ?>
            TP Shops
        <?php endif; ?>
    </h1>

    <?php if (!$allowed): ?>
        <div class="alert alert-warning">This Territory Partner doesn't cover your assigned area, or is no longer active.</div>
    <?php elseif (empty($shops)): ?>
        <div class="alert alert-warning">This TP hasn't added any shops yet.</div>
    <?php else: ?>
    <table class="mt">
        <thead>
            <tr><th>#</th><th>Shop Name</th><th>Mobile</th><th>District</th><th>Taluk</th></tr>
        </thead>
        <tbody>
            <?php foreach ($shops as $i => $s): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><b><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></b></td>
                <td><?= htmlspecialchars($s['mobile_number'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($s['district_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($s['taluk_name'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</body>
</html>
