<?php
// Per-visit Marketing Staff (MS) daily shop-visit detail — one row per
// shop visit (ms_orders row, grouped by order_id so a multi-product order
// is still one visit), not a Date x District x Taluk rollup: a Taluk can
// have several shops on the same day each tied to a DIFFERENT TP, so a
// rolled-up row couldn't show one TP/Firka value correctly. No separate
// manual visit-logging needed: every add_order.php submission, whether an
// actual order or a "no order" visit, already writes an ms_orders row for
// that shop/date.
//
// ms_shop has no Firka-level column (only district_name/taluk_name) and
// ms_orders never stores which Firka a visit's shop sits in (the Firka
// picker on add_order.php is only used client-side to auto-detect/lock
// the TP field — never persisted). So Firka Name here is DERIVED: when the
// order carries a tp_id, it's whichever Firka(s) that TP is assigned to
// within the shop's own Taluk (territory_partner_locations joined to
// partner_location_nodes).
//
// tp_id is ONLY ever captured for an actual "Get Order" submission
// (order_action_get.php) — a "No Order" visit (order_action.php) never
// asks for or stores a tp_id at all, so o.tp_id being empty does NOT by
// itself mean the shop's area has no TP; it just as often means this
// particular visit simply never captured one. Treating every empty tp_id
// as "Vacant" would wrongly label areas that do have a TP. So when tp_id
// is empty, this falls back to checking whether ANY active TP covers a
// Firka in the shop's own Taluk (territory_partner_locations): if none
// do, the Taluk really is vacant; if exactly one TP covers the whole
// Taluk, that TP is used with no ambiguity; if the Taluk is split across
// several TPs' Firkas and this visit didn't capture which one, TP Name
// shows "Not Captured" (true but unknown) rather than a false "Vacant".
//
// "New" vs "Existing" shop: ms_shop/ms_orders carry no first-order-date
// column, so a shop's first-ever order date is computed as
// MIN(order_date) over ALL its ms_orders rows (not just the filtered
// range) — a visit is "New" only if its date IS that shop's first-ever
// order date.
//
// Order value is an ESTIMATE (qty x products.outlet_price x
// (1 - discount_percentage/100)), matching the exact convention already
// used for ms_orders value in marketing/manager_order_csv.php — only
// new_order='yes' rows carry real product lines, so 'no order' visit rows
// contribute 0 value. Split into Femi9 (Napkin) vs Lumi9 (Diaper) via
// products.category, matching company/run-napkin-diaper-migration.php's
// convention (category='diaper' -> Diaper, anything else -> Napkin).

function getMsDailyVisitRows(mysqli $db_conn, string $from, string $to, array $opts = []): array {
    $msId       = (int) ($opts['ms_id'] ?? 0);
    $districtId = (int) ($opts['district_node_id'] ?? 0);

    $where = ["o.order_date BETWEEN ? AND ?"];
    $types = 'ss';
    $params = [$from, $to];
    if ($msId > 0) { $where[] = 'o.ms_id = ?'; $types .= 'i'; $params[] = $msId; }
    if ($districtId > 0) { $where[] = 's.district_node_id = ?'; $types .= 'i'; $params[] = $districtId; }
    $whereSql = implode(' AND ', $where);

    $sql = "
        SELECT
            o.order_id, o.order_date, o.shop_id, o.tp_id, o.new_order, o.pr_id, o.qty, o.discount_percentage,
            s.name AS shop_name, s.district_name, s.taluk_name,
            p.outlet_price, p.category,
            tp.name AS tp_name
        FROM ms_orders o
        JOIN ms_shop s ON s.id = o.shop_id
        LEFT JOIN products p ON p.id = o.pr_id
        LEFT JOIN territory_partners tp ON tp.id = o.tp_id
        WHERE $whereSql
        ORDER BY o.order_date ASC, s.district_name ASC, s.taluk_name ASC, o.order_id ASC
    ";
    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $lineRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($lineRows)) return [];

    // Each shop's first-ever order date, over ALL time (not just the
    // filtered range), optionally narrowed by the same ms_id/district
    // filter so a different MS's/district's history on a shared shop
    // doesn't leak in.
    $firstOrderWhere = [];
    $foTypes = '';
    $foParams = [];
    if ($msId > 0) { $firstOrderWhere[] = 'o.ms_id = ?'; $foTypes .= 'i'; $foParams[] = $msId; }
    if ($districtId > 0) { $firstOrderWhere[] = 's.district_node_id = ?'; $foTypes .= 'i'; $foParams[] = $districtId; }
    $foWhereSql = $firstOrderWhere ? ('WHERE ' . implode(' AND ', $firstOrderWhere)) : '';
    $foSql = "SELECT o.shop_id, MIN(o.order_date) AS first_date FROM ms_orders o JOIN ms_shop s ON s.id = o.shop_id $foWhereSql GROUP BY o.shop_id";
    if ($foTypes) {
        $foStmt = $db_conn->prepare($foSql);
        $foStmt->bind_param($foTypes, ...$foParams);
        $foStmt->execute();
        $foResult = $foStmt->get_result();
    } else {
        $foResult = $db_conn->query($foSql);
    }
    $firstOrderDate = [];
    while ($r = $foResult->fetch_assoc()) { $firstOrderDate[(int) $r['shop_id']] = $r['first_date']; }

    // Batch-resolve, for every distinct Taluk seen in this result set,
    // every (active TP, Firka) pair assigned anywhere in that Taluk — used
    // both to derive Firka name for a known tp_id, and to tell a genuinely
    // vacant Taluk apart from one whose TP just wasn't captured on this
    // particular visit (see file header).
    $taluks = array_values(array_unique(array_filter(array_column($lineRows, 'taluk_name'))));
    $firkaLookup = [];   // "tpId|taluk" => [firka names]
    $talukTps = [];      // taluk => [tpId => tpName]
    if (!empty($taluks)) {
        $talukList = "'" . implode("','", array_map(fn($t) => $db_conn->real_escape_string($t), $taluks)) . "'";
        $firkaRes = $db_conn->query("
            SELECT tpl.territory_partner_id AS tp_id, tp.name AS tp_name, t.name AS taluk_name, f.name AS firka_name
            FROM territory_partner_locations tpl
            JOIN partner_location_nodes f ON f.id = tpl.location_id AND f.depth = 6
            JOIN partner_location_nodes t ON t.id = f.parent_id AND t.depth = 5
            JOIN territory_partners tp ON tp.id = tpl.territory_partner_id AND tp.is_active = 1
            WHERE t.name IN ($talukList)
        ");
        while ($fr = $firkaRes->fetch_assoc()) {
            $tpId = (int) $fr['tp_id'];
            $firkaLookup[$tpId . '|' . $fr['taluk_name']][] = $fr['firka_name'];
            $talukTps[$fr['taluk_name']][$tpId] = $fr['tp_name'];
        }
    }

    // Group order lines back into one row per visit (order_id).
    $visits = [];
    $order = [];
    foreach ($lineRows as $r) {
        $oid = $r['order_id'];
        if (!isset($visits[$oid])) {
            $shopId = (int) $r['shop_id'];
            $isNew = ($firstOrderDate[$shopId] ?? null) === $r['order_date'];
            $tpId = (int) ($r['tp_id'] ?? 0);
            $taluk = $r['taluk_name'];

            if ($tpId > 0) {
                // Captured directly on this order — the most reliable source.
                $tpName = $r['tp_name'] ?: '—';
                $firkaNames = $firkaLookup[$tpId . '|' . $taluk] ?? [];
                $firka = !empty($firkaNames) ? implode(', ', array_unique($firkaNames)) : '—';
            } else {
                $tpsInTaluk = $talukTps[$taluk] ?? [];
                if (empty($tpsInTaluk)) {
                    // No active TP covers any Firka in this Taluk at all.
                    $tpName = 'Vacant';
                    $firka = '—';
                } elseif (count($tpsInTaluk) === 1) {
                    // Only one TP in the whole Taluk — no ambiguity even
                    // though this visit itself didn't capture a tp_id.
                    $onlyTpId = array_key_first($tpsInTaluk);
                    $tpName = $tpsInTaluk[$onlyTpId];
                    $firkaNames = $firkaLookup[$onlyTpId . '|' . $taluk] ?? [];
                    $firka = !empty($firkaNames) ? implode(', ', array_unique($firkaNames)) : '—';
                } else {
                    // Taluk is split across several TPs' Firkas and this
                    // visit didn't record which — true but unknown, not
                    // the same as vacant.
                    $tpName = 'Not Captured';
                    $firka = '—';
                }
            }

            $visits[$oid] = [
                'date' => $r['order_date'], 'shop_name' => $r['shop_name'],
                'district' => $r['district_name'] ?: '—', 'taluk' => $taluk ?: '—',
                'tp_name' => $tpName, 'firka' => $firka,
                'is_new' => $isNew,
                // 'order' = an actual product order was taken this visit;
                // 'no_order' = the MS visited but took no order (reason in
                // noorder_reason, not currently surfaced here). Exposed so
                // callers can split/filter the two instead of always
                // showing every no-order visit inline in the main table.
                'visit_type' => $r['new_order'] === 'yes' ? 'order' : 'no_order',
                'napkin_value' => 0.0, 'diaper_value' => 0.0,
            ];
            $order[] = $oid;
        }
        if ($r['new_order'] === 'yes' && $r['pr_id'] !== null) {
            $value = (float) $r['qty'] * (float) ($r['outlet_price'] ?? 0) * (1 - ((float) ($r['discount_percentage'] ?? 0) / 100));
            $value = max(0, $value);
            if (($r['category'] ?? '') === 'diaper') {
                $visits[$oid]['diaper_value'] += $value;
            } else {
                $visits[$oid]['napkin_value'] += $value;
            }
        }
    }

    $out = [];
    foreach ($order as $oid) {
        $v = $visits[$oid];
        $v['total_value'] = $v['napkin_value'] + $v['diaper_value'];
        $out[] = $v;
    }
    return $out;
}

// Windowed page-number list for a pager — at most $window numbers
// centered on the current page (e.g. [3,4,5,6,7] instead of printing
// every page 1..33, which was overflowing the card sideways).
function mdvPageWindow(int $page, int $totalPages, int $window = 5): array {
    $half = (int) floor($window / 2);
    $start = max(1, $page - $half);
    $end = min($totalPages, $start + $window - 1);
    $start = max(1, $end - $window + 1);
    return range($start, $end);
}
