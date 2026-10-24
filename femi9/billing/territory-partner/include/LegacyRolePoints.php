<?php
/**
 * Legacy (pre-TP) role points lookup.
 *
 * Territory Partners onboarded in July 2026 were, before that, Stockists /
 * Super Stockists / Distributors / Super Distributors — and some held more
 * than one of those roles at once under the same phone number (e.g. both
 * Super Stockist and Stockist). This matches the TP's mobile number against
 * all four old-role tables and returns the June-2026 purchase/sales points
 * earned under EACH matched role (the last month before the TP migration),
 * so the Reward Points pages can show it labelled with the matched role(s).
 */

if (!defined('LEGACY_ROLE_POINTS_FROM_DATE')) {
    define('LEGACY_ROLE_POINTS_FROM_DATE', '2026-06-01');
}
if (!defined('LEGACY_ROLE_POINTS_TO_DATE')) {
    define('LEGACY_ROLE_POINTS_TO_DATE', '2026-06-30');
}

if (!function_exists('getLegacyRolePointsAll')) {

    // Returns one entry per old-role table this mobile number matches
    // (usually zero or one, but can be more if the same person held
    // multiple roles). Each entry: role_label, purchase, daily_login, sales.
    function getLegacyRolePointsAll(mysqli $conn, string $mobile): array
    {
        if (empty($mobile)) {
            return [];
        }

        $fromDate = LEGACY_ROLE_POINTS_FROM_DATE;
        $toDate   = LEGACY_ROLE_POINTS_TO_DATE;

        $roleTables = [
            'super_stockiest'   => 'Super Stockist',
            'stockiest'         => 'Stockist',
            'super_distributor' => 'Super Distributor',
            'distributor'       => 'Distributor',
        ];

        $results = [];

        foreach ($roleTables as $table => $label) {
            $stmt = $conn->prepare("SELECT temp_id FROM `$table` WHERE mobile_number = ? LIMIT 1");
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('s', $mobile);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                continue;
            }

            $userId = $row['temp_id'];

            // June-2026 purchase points (as buyer: to_user side), net of returns.
            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(uii.subtotal)/100,0) AS val
                 FROM user_invoice_items uii
                 INNER JOIN user_invoice ui ON uii.inv_id = ui.inv_id
                 WHERE uii.to_user_type = ? AND uii.to_user_id = ?
                   AND uii.date BETWEEN ? AND ? AND ui.rwpoints_enable = 1"
            );
            $stmt->bind_param('ssss', $table, $userId, $fromDate, $toDate);
            $stmt->execute();
            $purchase = (float)($stmt->get_result()->fetch_assoc()['val'] ?? 0);
            $stmt->close();

            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(r.subtotal)/100,0) AS val
                 FROM user_return_stock_items r
                 WHERE r.invnumber IN (
                     SELECT DISTINCT uii.inv_id FROM user_invoice_items uii
                     INNER JOIN user_invoice ui ON uii.inv_id = ui.inv_id
                     WHERE uii.to_user_type = ? AND uii.to_user_id = ?
                       AND uii.date BETWEEN ? AND ? AND ui.rwpoints_enable = 1
                 ) AND r.from_usertype = ? AND r.from_userid = ?"
            );
            $stmt->bind_param('ssssss', $table, $userId, $fromDate, $toDate, $table, $userId);
            $stmt->execute();
            $purchaseReturn = (float)($stmt->get_result()->fetch_assoc()['val'] ?? 0);
            $stmt->close();

            $netPurchase = max(0, $purchase - $purchaseReturn);

            // June-2026 sales points (as seller: from_user side + end-customer sales), net of returns.
            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(subtotal)/100,0) AS val FROM user_invoice_items
                 WHERE from_user_type = ? AND from_user_id = ? AND date BETWEEN ? AND ?"
            );
            $stmt->bind_param('ssss', $table, $userId, $fromDate, $toDate);
            $stmt->execute();
            $salesUsers = (float)($stmt->get_result()->fetch_assoc()['val'] ?? 0);
            $stmt->close();

            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(subtotal)/100,0) AS val FROM invoice_items
                 WHERE user_type = ? AND user_id = ? AND date BETWEEN ? AND ?"
            );
            $stmt->bind_param('ssss', $table, $userId, $fromDate, $toDate);
            $stmt->execute();
            $salesCustomers = (float)($stmt->get_result()->fetch_assoc()['val'] ?? 0);
            $stmt->close();

            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(r.subtotal)/100,0) AS val
                 FROM user_return_stock_items r
                 WHERE r.invnumber IN (
                     SELECT DISTINCT inv_id FROM user_invoice_items
                     WHERE from_user_type = ? AND from_user_id = ? AND date BETWEEN ? AND ?
                 ) AND r.to_usertype = ? AND r.to_userid = ?"
            );
            $stmt->bind_param('ssssss', $table, $userId, $fromDate, $toDate, $table, $userId);
            $stmt->execute();
            $salesReturn = (float)($stmt->get_result()->fetch_assoc()['val'] ?? 0);
            $stmt->close();

            $netSales = max(0, $salesUsers + $salesCustomers - $salesReturn);

            // June-2026 daily login & billing reward points under this role.
            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(points_awarded),0) AS val FROM daily_login_rewards
                 WHERE user_type = ? AND user_id = ? AND reward_date BETWEEN ? AND ?"
            );
            $stmt->bind_param('ssss', $table, $userId, $fromDate, $toDate);
            $stmt->execute();
            $dailyLogin = round((float)($stmt->get_result()->fetch_assoc()['val'] ?? 0), 2);
            $stmt->close();

            $results[] = [
                'role_label'  => $label,
                'temp_id'     => $userId,
                'purchase'    => round($netPurchase, 2),
                'daily_login' => $dailyLogin,
                'sales'       => round($netSales, 2),
            ];
        }

        return $results;
    }

}

if (!function_exists('getLegacyRolePoints')) {

    // Back-compat single-role wrapper (first matched role only) for callers
    // that only need to show one legacy role.
    function getLegacyRolePoints(mysqli $conn, string $mobile): ?array
    {
        $all = getLegacyRolePointsAll($conn, $mobile);
        return $all[0] ?? null;
    }

}
