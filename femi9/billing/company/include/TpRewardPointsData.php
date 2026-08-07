<?php
/**
 * Shared data computation for the TP Reward Points company view
 * (reward_points_tp.php) and its Excel export
 * (reward_points_tp_export_xlsx.php) — kept in one place so the two never
 * drift out of sync on formula changes.
 */

// Monthly Target buckets shown in the filter dropdown and Excel export.
// Fixed, hand-picked boundaries (not computed from live min/max) so the
// dropdown's options stay stable release to release instead of shifting
// under admins as new TPs/targets are added.
function getTpTargetAmountRanges(): array {
    return [
        ''              => 'All',
        '10000-30000'   => '₹10,000 – ₹30,000',
        '30001-60000'   => '₹30,001 – ₹60,000',
        '60001-100000'  => '₹60,001 – ₹1,00,000',
        '100001-200000' => '₹1,00,001 – ₹2,00,000',
        '200001-300000' => '₹2,00,001 – ₹3,00,000',
    ];
}

// Parses one of the keys from getTpTargetAmountRanges() into [min, max]
// (max === null means "no upper bound"). Both bounds are inclusive.
// Returns null for an unknown/empty key.
function parseTpTargetAmountRange(string $key): ?array {
    if ($key === '' || !array_key_exists($key, getTpTargetAmountRanges())) return null;
    [$minStr, $maxStr] = array_pad(explode('-', $key, 2), 2, '');
    return [(float)$minStr, $maxStr === '' ? null : (float)$maxStr];
}

function getTpRewardPointsData(string $current_from_date, string $current_to_date, string $target_range = ''): array {
    global $servername, $db_port, $dbname, $username, $password;

    $combined_users = [];

    // PDO connection (uses $servername/$db_port/$username/$password/$dbname from config)
    $pdo = new PDO(
        "mysql:host={$servername};port={$db_port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // ── Purchase Points ────────────────────────────────────────────────────────
    // Only invoices with reward points enabled count — mirrors reward_points.php's
    // "ui.rwpoints_enable = 1" gate for SS/Stockist/Distributor invoices.
    $stmt = $pdo->prepare("
        SELECT territory_partner_id AS user_id,
               COALESCE(SUM(total_amount) / 100, 0) AS purchase_points
        FROM tp_invoices
        WHERE invoice_date BETWEEN :from_date AND :to_date
          AND rwpoints_enable = 1
        GROUP BY territory_partner_id
    ");
    $stmt->execute([':from_date' => $current_from_date, ':to_date' => $current_to_date]);
    $purchase_data = [];
    foreach ($stmt->fetchAll() as $row) {
        $purchase_data[$row['user_id']] = (float)$row['purchase_points'];
    }

    // ── Sales Points ───────────────────────────────────────────────────────────
    // Separate from Purchase Points: this counts what the TP sold onward to a
    // shop/customer (user_invoice, from_user_type='territory_partner'), not
    // what the TP bought from the company (tp_invoices). Same total/100
    // convention as Purchase Points. Shown as its own column and deliberately
    // NOT included in Total Points — it's informational only, per request.
    $stmt = $pdo->prepare("
        SELECT from_user_id AS user_id,
               COALESCE(SUM(total) / 100, 0) AS sales_points
        FROM user_invoice
        WHERE from_user_type = 'territory_partner'
          AND sub_total > 0
          AND date BETWEEN :from_date AND :to_date
        GROUP BY from_user_id
    ");
    $stmt->execute([':from_date' => $current_from_date, ':to_date' => $current_to_date]);
    $sales_data = [];
    foreach ($stmt->fetchAll() as $row) {
        $sales_data[$row['user_id']] = (float)$row['sales_points'];
    }

    // ── Daily Login Points ─────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT user_id, COALESCE(SUM(points_awarded), 0) AS daily_points
        FROM daily_login_rewards
        WHERE user_type = 'territory_partner'
          AND reward_date BETWEEN :from_date AND :to_date
        GROUP BY user_id
        HAVING daily_points > 0
    ");
    $stmt->execute([':from_date' => $current_from_date, ':to_date' => $current_to_date]);
    $daily_data = [];
    foreach ($stmt->fetchAll() as $row) {
        $daily_data[$row['user_id']] = (float)$row['daily_points'];
    }

    // ── Return Deductions ─────────────────────────────────────────────────────
    // Same rwpoints_enable gate — a return against an invoice that never
    // earned points shouldn't be able to deduct points either.
    $stmt = $pdo->prepare("
        SELECT r.from_userid AS user_id,
               COALESCE(SUM(r.subtotal) / 100, 0) AS return_points
        FROM user_return_stock_items r
        WHERE r.from_usertype = 'territory_partner'
          AND r.invnumber COLLATE utf8mb4_unicode_ci IN (
              SELECT invoice_number
              FROM tp_invoices
              WHERE invoice_date BETWEEN :from_date AND :to_date
                AND rwpoints_enable = 1
          )
        GROUP BY r.from_userid
    ");
    $stmt->execute([':from_date' => $current_from_date, ':to_date' => $current_to_date]);
    $return_data = [];
    foreach ($stmt->fetchAll() as $row) {
        $return_data[$row['user_id']] = (float)$row['return_points'];
    }

    // ── Team Points ────────────────────────────────────────────────────────────
    // "Team" = other TPs who registered this TP as their referrer with exactly
    // a 10% referral_percentage (one level deep only — a team member's own
    // referrals are not included). Only 0%-GST product lines count, and only
    // from the team member's invoices that themselves have rwpoints_enable=1
    // (the same per-invoice toggle used for the TP's own purchase points).
    $stmt = $pdo->prepare("
        SELECT referrer.id AS referrer_id,
               COALESCE(SUM(tii.amount) / 100, 0) AS team_points
        FROM territory_partners member
        INNER JOIN territory_partners referrer ON referrer.tp_id = member.referral_id
        INNER JOIN tp_invoices tpi        ON tpi.territory_partner_id = member.id
        INNER JOIN tp_invoice_items tii   ON tii.tp_invoice_id = tpi.id
        INNER JOIN products p             ON p.id = tii.product_id
        WHERE member.referral_type = 'TP'
          AND member.referral_percentage = 10
          AND tpi.rwpoints_enable = 1
          AND tpi.invoice_date BETWEEN :from_date AND :to_date
          AND p.gst = 0
        GROUP BY referrer.id
    ");
    $stmt->execute([':from_date' => $current_from_date, ':to_date' => $current_to_date]);
    $team_data = [];
    foreach ($stmt->fetchAll() as $row) {
        $team_data[$row['referrer_id']] = (float)$row['team_points'];
    }

    // ── Advance Bonus Points (Monthly Target Achievement Bonus) ───────────────
    // Awarded separately by tp-bonus-points-calculator.php into the shared
    // reward_points table (transaction_type='bonus_target_achievement') when a
    // TP hits all 4 weekly advance-payment thresholds for a month. That table
    // keys user_id on territory_partners.tp_id (the string TP ID), not the
    // internal id every other query here groups by — so this is joined back to
    // territory_partners to translate it into the same id space as
    // $purchase_data/$daily_data/etc. A rollback in the calculator deletes the
    // row outright, so no separate "reversed" flag needs to be excluded here;
    // is_expired/deleted_at are excluded so an expired or since-removed bonus
    // doesn't keep counting.
    $stmt = $pdo->prepare("
        SELECT tp.id AS user_id, COALESCE(SUM(rp.points), 0) AS advance_bonus_points
        FROM reward_points rp
        INNER JOIN territory_partners tp ON tp.tp_id = rp.user_id
        WHERE rp.user_type = 'territory_partner'
          AND rp.transaction_type = 'bonus_target_achievement'
          AND rp.is_expired = 0
          AND rp.deleted_at IS NULL
          AND rp.transaction_date BETWEEN :from_date AND :to_date_end
        GROUP BY tp.id
    ");
    $stmt->execute([':from_date' => $current_from_date, ':to_date_end' => $current_to_date . ' 23:59:59']);
    $advance_bonus_data = [];
    foreach ($stmt->fetchAll() as $row) {
        $advance_bonus_data[$row['user_id']] = (float)$row['advance_bonus_points'];
    }

    // ── Collect all TP IDs that have any data ─────────────────────────────────
    // array_values() is required here: array_unique() preserves the original
    // (gapped) keys after removing duplicates, but $stmt->execute() below binds
    // this array positionally (?) — PDO needs a plain sequential 0-indexed
    // array for that, or it throws "Invalid parameter number" the moment any
    // TP appears in more than one of the four source arrays (i.e. almost
    // always, since a TP that both purchased and logged in shows up in both).
    $all_ids = array_values(array_unique(array_merge(
        array_keys($purchase_data),
        array_keys($sales_data),
        array_keys($daily_data),
        array_keys($return_data),
        array_keys($team_data),
        array_keys($advance_bonus_data)
    )));

    if (empty($all_ids)) {
        return $combined_users;
    }

    $placeholders = str_repeat('?,', count($all_ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT id, tp_id, name, mobile
        FROM territory_partners
        WHERE id IN ($placeholders)
        ORDER BY name ASC
    ");
    $stmt->execute($all_ids);
    $tp_details = [];
    foreach ($stmt->fetchAll() as $row) {
        $tp_details[$row['id']] = $row;
    }

    // ── District & Monthly Target ──────────────────────────────────────────
    // A TP's assigned locations (territory_partner_locations) sit at the
    // FIRKA/AREA layer (depth 6, per partner_location_layers) — 3 levels
    // below DISTRICT (depth 3: FIRKA -> TALUK -> DIVISION -> DISTRICT), so
    // each assignment is walked up 3 parent_id hops to find its district.
    // A TP can have multiple locations, possibly across different
    // districts, so all distinct district names are joined together.
    // Monthly Target mirrors tp-bonus-points-calculator.php's own
    // definition: SUM of target_amount across every location assigned to
    // the TP (that calculator sums at the assigned depth itself, not the
    // district level, so this reuses the same target_amount column here).
    $stmt = $pdo->prepare("
        SELECT tpl.territory_partner_id AS user_id,
               GROUP_CONCAT(DISTINCT district.name ORDER BY district.name SEPARATOR ', ') AS district_names,
               COALESCE(SUM(leaf.target_amount), 0) AS target_amount
        FROM territory_partner_locations tpl
        JOIN partner_location_nodes leaf      ON leaf.id = tpl.location_id
        JOIN partner_location_nodes taluk      ON taluk.id = leaf.parent_id
        JOIN partner_location_nodes division    ON division.id = taluk.parent_id
        JOIN partner_location_nodes district    ON district.id = division.parent_id
        WHERE tpl.territory_partner_id IN ($placeholders)
        GROUP BY tpl.territory_partner_id
    ");
    $stmt->execute($all_ids);
    $tp_location_data = [];
    foreach ($stmt->fetchAll() as $row) {
        $tp_location_data[$row['user_id']] = $row;
    }

    foreach ($all_ids as $uid) {
        $purchase_pts = $purchase_data[$uid] ?? 0;
        $sales_pts    = $sales_data[$uid]    ?? 0;
        $daily_pts    = $daily_data[$uid]    ?? 0;
        $return_pts   = $return_data[$uid]   ?? 0;
        $team_pts     = $team_data[$uid]     ?? 0;
        $advance_pts  = $advance_bonus_data[$uid] ?? 0;
        // Sales Points is informational only — deliberately excluded from
        // Total Points (per request), unlike every other column here.
        $total        = max(0, $purchase_pts + $daily_pts + $team_pts + $advance_pts - $return_pts);

        $combined_users[] = [
            'user_id'         => $uid,
            'purchase_points' => $purchase_pts,
            'sales_points'    => $sales_pts,
            'daily_points'    => $daily_pts,
            'team_points'     => $team_pts,
            'advance_points'  => $advance_pts,
            'return_points'   => $return_pts,
            'total_points'    => $total,
            'details'         => $tp_details[$uid] ?? null,
            'district_names'  => $tp_location_data[$uid]['district_names'] ?? null,
            'target_amount'   => (float)($tp_location_data[$uid]['target_amount'] ?? 0),
        ];
    }

    usort($combined_users, fn($a, $b) => $b['total_points'] <=> $a['total_points']);

    $range = parseTpTargetAmountRange($target_range);
    if ($range !== null) {
        [$min, $max] = $range;
        $combined_users = array_values(array_filter($combined_users, function ($u) use ($min, $max) {
            return $u['target_amount'] >= $min && ($max === null || $u['target_amount'] <= $max);
        }));
    }

    return $combined_users;
}
