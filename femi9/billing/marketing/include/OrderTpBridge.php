<?php
// Mirrors a DM's "Get Order" (ms_shop/ms_orders) into the Territory Partner's
// own field-order pipeline (shop/tp_orders) so the TP's existing
// manage-orders.php / order-to-invoice.php / shop-invoice-*.php work
// completely unmodified. Best-effort: failures here must never lose the
// DM's own ms_orders rows, which are the source of truth.
//
// $lines: array of ['pr_id' => int, 'qty' => int]

function bridgeOrderToTp($db_conn, int $tp_id, $ms_id, $shop_id, string $order_id, string $order_date, array $lines): void
{
    if ($tp_id <= 0 || empty($lines)) {
        return;
    }

    try {
        $shop_id_int = (int)$shop_id;
        $stmtShop = $db_conn->prepare(
            "SELECT id, name, mobile_number, address, gstin, state_name, district_name, taluk_name, latitude, longitude,
                    email, shop_cat, pincode, landline, country_code
             FROM ms_shop WHERE id = ? LIMIT 1"
        );
        $stmtShop->bind_param('i', $shop_id_int);
        $stmtShop->execute();
        $msShop = $stmtShop->get_result()->fetch_assoc();
        $stmtShop->close();
        if (!$msShop) {
            return;
        }

        // Find an already-bridged shop row for this (ms_shop, tp) pair, else create one.
        $stmtFind = $db_conn->prepare(
            "SELECT id FROM shop WHERE source_ms_shop_id = ? AND onboard_userTYPE = 'territory_partner' AND onboard_userID = ? LIMIT 1"
        );
        $tp_id_str = (string)$tp_id;
        $stmtFind->bind_param('is', $shop_id_int, $tp_id_str);
        $stmtFind->execute();
        $found = $stmtFind->get_result()->fetch_assoc();
        $stmtFind->close();

        if ($found) {
            $bridgedShopId = (int)$found['id'];
        } else {
            // Best-effort text match against the state/district master tables.
            // If unmatched, left as 0 — order-to-invoice.php's inner/outer GST
            // detection will default to "outer" in that case (known limitation).
            $state_id = 0;
            $district_id = 0;
            if (!empty($msShop['state_name'])) {
                $stmtState = $db_conn->prepare("SELECT id FROM state WHERE TRIM(LOWER(st_name)) = TRIM(LOWER(?)) LIMIT 1");
                $stmtState->bind_param('s', $msShop['state_name']);
                $stmtState->execute();
                $state_id = (int)($stmtState->get_result()->fetch_assoc()['id'] ?? 0);
                $stmtState->close();
            }
            if (!empty($msShop['district_name'])) {
                $stmtDist = $db_conn->prepare("SELECT id FROM district WHERE TRIM(LOWER(dist_name)) = TRIM(LOWER(?)) LIMIT 1");
                $stmtDist->bind_param('s', $msShop['district_name']);
                $stmtDist->execute();
                $district_id = (int)($stmtDist->get_result()->fetch_assoc()['id'] ?? 0);
                $stmtDist->close();
            }

            $randomPart = '';
            for ($x = 0; $x < 5; $x++) { $randomPart .= random_int(1, 9); }
            $tempId = $randomPart . 'FSHP' . date('dmy') . date('gis');

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
                  0, 0, '', ?, ?, 'territory_partner', ?,
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
            // Placeholder order: state_id, temp_id, name, district_id, email,
            // mobile, valid_from, valid_to, pincode, gstin, onboard_userID,
            // address, userid, useridtext, shop_cat, country_code, landline,
            // latitude, longitude, source_ms_shop_id
            $stmtIns->bind_param(
                'ississssssssisissddi',
                $state_id, $tempId, $name, $district_id, $email,
                $mobile, $valid_from, $valid_to, $pincode, $gstin, $tp_id_str,
                $address, $userid, $useridtext, $shop_cat, $countryCode, $landline,
                $lat, $lng, $shop_id_int
            );
            $stmtIns->execute();
            $bridgedShopId = $stmtIns->insert_id;
            $stmtIns->close();
        }

        $ms_id_int = (int)$ms_id;
        // Carries the DM's own per-product discount % across so
        // order-to-invoice.php pre-fills the TP's invoice with whatever
        // discount the DM already promised the shop — the TP can still
        // freely change it (more discount, extra products, etc.) once
        // they're in the invoice; this is only the starting point.
        $stmtTpOrder = $db_conn->prepare(
            "INSERT INTO tp_orders (order_id, shop_id, tp_id, order_date, new_order, pr_id, qty, discount_percentage, assigned_by_ms_id)
             VALUES (?, ?, ?, ?, 'yes', ?, ?, ?, ?)"
        );
        foreach ($lines as $line) {
            $pr_id = (int)$line['pr_id'];
            $qty   = (int)$line['qty'];
            $discount_percentage = (float)($line['discount_percentage'] ?? 0);
            if ($pr_id <= 0 || $qty <= 0) { continue; }
            $stmtTpOrder->bind_param('siisiidi', $order_id, $bridgedShopId, $tp_id, $order_date, $pr_id, $qty, $discount_percentage, $ms_id_int);
            $stmtTpOrder->execute();
        }
        $stmtTpOrder->close();
    } catch (\Throwable $e) {
        // Best-effort mirror — the DM's own ms_orders rows are already saved
        // and remain the source of truth even if this bridge fails.
        return;
    }
}
