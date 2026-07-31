<?php
// Helpers for "company invoices a DM's Get Order directly" (used when no TP
// covers that DM's area — see marketing/add_order.php's automatic fallback).
//
// Two things a DM order needs before it can become a real invoice:
//   1. A `shop` table row (company's whole invoicing pipeline is built on
//      that table, not ms_shop) — bridgeMsShopToCompanyShop() finds or
//      creates one, mirroring marketing/include/OrderTpBridge.php's pattern
//      but owned by the company instead of a TP.
//   2. Knowing whether a Channel Partner's stock should fulfil it instead of
//      the company's own godown stock — resolveCpForMsShop() walks the
//      partner_location_nodes tree (ancestors + descendants, same technique
//      territory-partner/geo_layers.php uses) from the shop's own location
//      out to find a CP assigned anywhere in that area.

function bridgeMsShopToCompanyShop($db_conn, int $ms_shop_id): ?int {
    $stmtFind = $db_conn->prepare(
        "SELECT id FROM shop WHERE source_ms_shop_id=? AND onboard_userTYPE='company' LIMIT 1"
    );
    $stmtFind->bind_param('i', $ms_shop_id);
    $stmtFind->execute();
    $found = $stmtFind->get_result()->fetch_assoc();
    $stmtFind->close();
    if ($found) { return (int)$found['id']; }

    $stmtShop = $db_conn->prepare(
        "SELECT id, name, mobile_number, address, gstin, state_name, district_name, taluk_name, latitude, longitude,
                email, shop_cat, pincode, landline, country_code
         FROM ms_shop WHERE id=? LIMIT 1"
    );
    $stmtShop->bind_param('i', $ms_shop_id);
    $stmtShop->execute();
    $msShop = $stmtShop->get_result()->fetch_assoc();
    $stmtShop->close();
    if (!$msShop) { return null; }

    $state_id = 0; $district_id = 0;
    if (!empty($msShop['state_name'])) {
        $s = $db_conn->prepare("SELECT id FROM state WHERE TRIM(LOWER(st_name)) = TRIM(LOWER(?)) LIMIT 1");
        $s->bind_param('s', $msShop['state_name']);
        $s->execute();
        $state_id = (int)($s->get_result()->fetch_assoc()['id'] ?? 0);
        $s->close();
    }
    if (!empty($msShop['district_name'])) {
        $s = $db_conn->prepare("SELECT id FROM district WHERE TRIM(LOWER(dist_name)) = TRIM(LOWER(?)) LIMIT 1");
        $s->bind_param('s', $msShop['district_name']);
        $s->execute();
        $district_id = (int)($s->get_result()->fetch_assoc()['id'] ?? 0);
        $s->close();
    }

    $randomPart = '';
    for ($x = 0; $x < 5; $x++) { $randomPart .= random_int(1, 9); }
    $tempId = $randomPart . 'CSHP' . date('dmy') . date('gis');

    $maxRow = $db_conn->query("SELECT MAX(userid) AS n FROM shop")->fetch_assoc();
    $userid = (int)($maxRow['n'] ?? 0) + 1;
    $useridtext = 'FEMI9-R-' . str_pad((string)$userid, 3, '0', STR_PAD_LEFT);

    $valid_from = date('Y-m-d');
    $valid_to   = date('Y-m-d', strtotime('+1 months'));

    $stmtIns = $db_conn->prepare(
        "INSERT INTO shop
         (state_id, temp_id, user_icon, name, district_id, email, mobile_number, username, password,
          plan_amount, valid_months, valid_from, valid_to, amount_method, amount_status, ref_number,
          account_status, merchantOrderId, merchantTransactionId, merchantUserId,
          taluk_id, firka_id, distributor_id, pincode_id, gstin, onboard_userTYPE, onboard_userID,
          address, userid, useridtext, shop_cat, country_code, landline,
          latitude, longitude, source_ms_shop_id)
         VALUES
         (?, ?, 'Nil', ?, ?, ?, ?, 'Nil', 'Nil',
          0, 1, ?, ?, 'Nil', 'Nil', 'Nil',
          'Nil', 'Nil', 'Nil', 'Nil',
          0, 0, '', ?, ?, 'company', 'company',
          ?, ?, ?, ?, ?, ?,
          ?, ?, ?)"
    );
    $name        = $msShop['name'] ?? '';
    $mobile      = $msShop['mobile_number'] ?? '';
    $address     = $msShop['address'] ?? '';
    $gstin       = $msShop['gstin'] ?? '';
    $lat         = $msShop['latitude'];
    $lng         = $msShop['longitude'];
    $email       = $msShop['email'] ?? '';
    $shop_cat    = (int)($msShop['shop_cat'] ?? 0);
    $pincode     = $msShop['pincode'] ?? '';
    $landline    = $msShop['landline'] ?? '';
    $countryCode = $msShop['country_code'] ?? '';
    $stmtIns->bind_param(
        'ississsssssisissddi',
        $state_id, $tempId, $name, $district_id, $email,
        $mobile, $valid_from, $valid_to, $pincode, $gstin,
        $address, $userid, $useridtext, $shop_cat, $countryCode, $landline,
        $lat, $lng, $ms_shop_id
    );
    $stmtIns->execute();
    $newId = $stmtIns->insert_id;
    $stmtIns->close();

    return $newId > 0 ? $newId : null;
}

// Walks ancestors + descendants of a starting node (same technique as
// territory-partner/geo_layers.php) and returns the first active Channel
// Partner assigned anywhere in that connected area, or null.
function resolveCpForMsShop($db_conn, int $ms_shop_id): ?array {
    $stmtShop = $db_conn->prepare("SELECT taluk_node_id, district_node_id FROM ms_shop WHERE id=? LIMIT 1");
    $stmtShop->bind_param('i', $ms_shop_id);
    $stmtShop->execute();
    $row = $stmtShop->get_result()->fetch_assoc();
    $stmtShop->close();
    if (!$row) { return null; }

    $startId = (int)($row['taluk_node_id'] ?: $row['district_node_id'] ?: 0);
    if ($startId <= 0) { return null; }

    $allNodes = [];
    $res = $db_conn->query("SELECT id, parent_id FROM partner_location_nodes WHERE is_active=1");
    while ($n = $res->fetch_assoc()) {
        $allNodes[(int)$n['id']] = $n['parent_id'] !== null ? (int)$n['parent_id'] : null;
    }
    if (!isset($allNodes[$startId])) { return null; }

    $allowed = [];
    // Up to root.
    $cur = $startId;
    while ($cur !== null && isset($allNodes[$cur])) {
        $allowed[$cur] = true;
        $cur = $allNodes[$cur];
    }
    // Down through every descendant.
    $childrenOf = [];
    foreach ($allNodes as $id => $parent) {
        if ($parent !== null) { $childrenOf[$parent][] = $id; }
    }
    $queue = [$startId];
    while (!empty($queue)) {
        $c = array_shift($queue);
        $allowed[$c] = true;
        foreach ($childrenOf[$c] ?? [] as $child) { $queue[] = $child; }
    }

    $idList = implode(',', array_keys($allowed));
    if ($idList === '') { return null; }

    $resCp = $db_conn->query(
        "SELECT cpl.channel_partner_id, cp.name
         FROM channel_partner_locations cpl
         JOIN channel_partners cp ON cp.id = cpl.channel_partner_id
         WHERE cpl.location_id IN ($idList) AND cp.is_active = 1
         LIMIT 1"
    );
    $cpRow = $resCp ? $resCp->fetch_assoc() : null;
    return $cpRow ? ['id' => (int)$cpRow['channel_partner_id'], 'name' => $cpRow['name']] : null;
}
