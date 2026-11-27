<?php
// Mirrors a DM's "Get Order" (ms_shop/ms_orders) into the Territory Partner's
// own field-order pipeline (shop/tp_orders) so the TP's existing
// manage-orders.php / order-to-invoice.php / shop-invoice-*.php work
// completely unmodified. Best-effort: failures here must never lose the
// DM's own ms_orders rows, which are the source of truth.
//
// $lines: array of ['pr_id' => int, 'qty' => int]

// Same normalised name + clean 10-digit mobile dedupe key as
// territory-partner/order-confirm-shop.php's ocsDedupeKey() / marketing/
// dashboard.php's shopOnboardDedupeKey() — kept as its own copy (same
// convention as those two) rather than a shared include, matching this
// codebase's existing per-file-copy pattern for this exact small helper.
function otbDedupeKey($name, $mobile): ?string {
    $digits = preg_replace('/\D+/', '', (string)$mobile);
    $nm = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string)$name)));
    if (strlen($digits) === 10 && $nm !== '') { return $nm . '|' . $digits; }
    return null;
}

require_once __DIR__ . '/../../shared/ShopPendingReview.php';

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
            // Before creating a new row, check whether this TP already has a
            // shop that's really the SAME real shop — added independently via
            // their own "Add Shop" (source_ms_shop_id NULL, no way for the
            // source_ms_shop_id lookup above to have found it). Without this,
            // every DM whose ms_shop happens to be a shop the TP already
            // onboarded themselves would get a second, duplicate shop row on
            // the TP's own Manage Shop the moment a Get Order bridges it.
            // Confirmed 2026-09-23.
            //
            // Matched by mobile number ALONE until 2026-12-01 — user-reported
            // real failure: the SAME mobile number can legitimately belong to
            // several genuinely different shops a TP has onboarded (one person
            // running more than one shop on one phone isn't rare), so a
            // mobile-only match silently hijacked an unrelated existing shop
            // ("jayadeepa Femi9WD") for a DM order actually meant for a
            // different shop ("Testing2") that just happened to share that
            // phone number — the invoice then went out under the WRONG shop's
            // name with no visible warning. Now requires BOTH the normalised
            // name AND the mobile to match (same dedupe key
            // territory-partner/order-confirm-shop.php's own shop-matching
            // and marketing/dashboard.php's New Shop Onboarding dedupe both
            // already use) — same real shop re-contacted only has the SAME
            // name, not just the same number.
            $bridgedShopId = null;
            $mobile_for_match = trim((string)($msShop['mobile_number'] ?? ''));
            $key_for_match = otbDedupeKey($msShop['name'] ?? '', $mobile_for_match);
            if ($mobile_for_match !== '' && $key_for_match !== null) {
                $stmtMobile = $db_conn->prepare(
                    "SELECT id, name FROM shop WHERE onboard_userTYPE = 'territory_partner' AND onboard_userID = ?
                     AND mobile_number = ?"
                );
                $stmtMobile->bind_param('ss', $tp_id_str, $mobile_for_match);
                $stmtMobile->execute();
                $candidates = $stmtMobile->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmtMobile->close();
                foreach ($candidates as $candidate) {
                    if (otbDedupeKey($candidate['name'], $mobile_for_match) === $key_for_match) {
                        $bridgedShopId = (int)$candidate['id'];
                        break;
                    }
                }
                if ($bridgedShopId !== null) {
                    // Backfill so the cheap source_ms_shop_id lookup above
                    // finds this row directly next time, instead of
                    // re-matching by name+mobile on every single order.
                    $stmtBackfill = $db_conn->prepare("UPDATE shop SET source_ms_shop_id = ? WHERE id = ? AND source_ms_shop_id IS NULL");
                    $stmtBackfill->bind_param('ii', $shop_id_int, $bridgedShopId);
                    $stmtBackfill->execute();
                    $stmtBackfill->close();
                }
            }
        }

        if ($bridgedShopId === null) {
            // state_id: best-effort text match against the `state` MASTER
            // table — shop.state_id is a `state` table id (e.g. 7=Tamilnadu),
            // confirmed from shared/ShopInvoiceData.php's own comment and
            // every GST-calculation file that reads it. Correct as-is.
            //
            // district_id: NOT the legacy `district` table — shop.district_id
            // is a partner_location_nodes id everywhere else this column is
            // read (TpInvoiceAmount.php, the whole district-attribution fix
            // earlier this session, shop-edit.php's own geo_layers.php
            // cascade). The legacy `district` table uses a COMPLETELY
            // different id space (confirmed: Erode=8 there, but
            // partner_location_nodes' real ERODE node is id=18) — matching
            // against it here wrote the WRONG id into shop.district_id for
            // every newly-bridged shop, which then couldn't resolve on
            // territory-partner/shop-edit.php's District dropdown at all
            // (the id simply doesn't exist among the TP's own real districts)
            // — same class of bug as the shop.state_id fix (2026-12-01),
            // just the district twin of it. 2026-12-01, user-reported
            // ("yes review & invoice button ah click panna district name ah
            // kanam thaluk ah kanam").
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
                $stmtDist = $db_conn->prepare("SELECT id FROM partner_location_nodes WHERE depth = 3 AND TRIM(LOWER(name)) = TRIM(LOWER(?)) LIMIT 1");
                $stmtDist->bind_param('s', $msShop['district_name']);
                $stmtDist->execute();
                $district_id = (int)($stmtDist->get_result()->fetch_assoc()['id'] ?? 0);
                $stmtDist->close();
            }
            // taluk_id deliberately left at 0, NOT resolved from
            // ms_shop.taluk_name — tried this 2026-12-01, reverted same day:
            // a Taluk name can be a real partner_location_nodes match
            // somewhere under the matched district while still falling
            // OUTSIDE this specific TP's own narrower assigned coverage
            // (e.g. TP-0150 covers only Gobichettipalayam Division's two
            // Taluks under Erode district, not Erode district's own
            // same-named Taluk) — silently writing that mismatched id in
            // looked plausible but was wrong, worse than leaving it for the
            // TP to pick themselves on the review screen (same reasoning
            // already applied to firka_id, which was never attempted here
            // at all for the identical reason — ms_shop doesn't even carry a
            // firka name to try).
            $taluk_id = 0;

            ensureShopPendingReviewColumn($db_conn);

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
                  latitude, longitude, source_ms_shop_id, pending_review)
                 VALUES
                 (?, ?, 'Nil', ?, ?, ?, ?, 'Nil', 'Nil',
                  0, 1, ?, ?, 'Nil', 'Nil', 'Nil',
                  'Nil', 'Nil', 'Nil', 'Nil',
                  0, 0, '', ?, ?, 'territory_partner', ?,
                  ?, ?, ?, ?, ?, ?,
                  ?, ?, ?, 1)"
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
