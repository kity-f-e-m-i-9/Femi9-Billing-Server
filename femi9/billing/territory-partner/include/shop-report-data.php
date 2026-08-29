<?php
// Shared data-fetch for the TP "Shop Report" — used by both the on-screen
// report (shop-report.php) and its Excel export (shop-report-export.php) so
// the two never drift apart on filter logic or numbers shown.
//
// Expects $db_conn to already be set (via config.php). Returns the filtered
// shop list plus grand totals.

function tp_shop_report_fetch(mysqli $db_conn, int $uid, string $utype, string $from, string $to, string $statusFilter, string $searchTerm, bool $withInvoices = false): array {
    $shop_rows = mysqli_query($db_conn, "
        SELECT s.id, s.temp_id, s.useridtext, s.name, s.mobile_number, s.country_code, s.gstin, s.address,
               sc.catlable,
               COUNT(ui.id)                                   AS inv_count,
               COALESCE(SUM(CASE WHEN ui.sub_total>0 THEN ui.total ELSE 0 END),0) AS total_billed,
               COALESCE(SUM(r.received),0)                    AS total_received
        FROM shop s
        LEFT JOIN shop_category sc ON sc.id = s.shop_cat
        LEFT JOIN user_invoice ui
               ON ui.to_user_id = s.temp_id AND ui.to_user_type = 'shop'
              AND ui.from_user_id = '$uid' AND ui.from_user_type = '$utype'
              AND ui.sub_total > 0 AND ui.date BETWEEN '$from' AND '$to'
        LEFT JOIN (
            SELECT inv_id, SUM(received) received FROM receipt GROUP BY inv_id
        ) r ON r.inv_id = ui.inv_id
        WHERE s.onboard_userID = '$uid' AND s.onboard_userTYPE = '$utype'
        GROUP BY s.id, s.temp_id, s.useridtext, s.name, s.mobile_number, s.country_code, s.gstin, s.address, sc.catlable
        ORDER BY total_billed DESC, s.name ASC
    ");

    $shops = [];
    $grand_billed = 0; $grand_received = 0; $grand_invoices = 0;
    while ($row = mysqli_fetch_assoc($shop_rows)) {
        $billed   = (float)$row['total_billed'];
        $received = (float)$row['total_received'];
        $due      = max(0, $billed - $received);

        if ($billed <= 0) {
            $status = 'no_invoices';
        } elseif ($received <= 0) {
            $status = 'not_paid';
        } elseif (($received + 0.01) >= $billed) {
            $status = 'fully_paid';
        } else {
            $status = 'partially_paid';
        }

        if ($statusFilter !== 'all' && $status !== $statusFilter) continue;
        if ($searchTerm !== '' && stripos($row['name'] . ' ' . $row['mobile_number'] . ' ' . $row['useridtext'], $searchTerm) === false) continue;

        $row['billed']   = $billed;
        $row['received'] = $received;
        $row['due']      = $due;
        $row['status']   = $status;
        $shops[] = $row;

        $grand_billed    += $billed;
        $grand_received  += $received;
        $grand_invoices  += (int)$row['inv_count'];
    }
    $grand_due = max(0, $grand_billed - $grand_received);
    $shops_with_sales = count(array_filter($shops, fn($s) => $s['billed'] > 0));

    // ── Per-invoice breakdown, keyed by shop temp_id ────────────────────────
    // Only fetched when asked for (the export needs it; the on-screen summary
    // table doesn't), so the normal page load stays a single query.
    $invoices_by_shop = [];
    if ($withInvoices && !empty($shops)) {
        $stmt = $db_conn->prepare(
            "SELECT ui.to_user_id AS shop_temp_id, ui.inv_id, ui.inv_number, ui.date, ui.total,
                    COALESCE(r.received,0) AS received
             FROM user_invoice ui
             LEFT JOIN (SELECT inv_id, SUM(received) received FROM receipt GROUP BY inv_id) r ON r.inv_id = ui.inv_id
             WHERE ui.from_user_id = ? AND ui.from_user_type = ? AND ui.to_user_type = 'shop'
               AND ui.sub_total > 0 AND ui.date BETWEEN ? AND ?
             ORDER BY ui.to_user_id, ui.date ASC, ui.id ASC"
        );
        $stmt->bind_param('isss', $uid, $utype, $from, $to);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $billed   = (float)$r['total'];
            $received = (float)$r['received'];
            $due      = max(0, $billed - $received);
            if ($received <= 0) {
                $inv_status = 'not_paid';
            } elseif (($received + 0.01) >= $billed) {
                $inv_status = 'fully_paid';
            } else {
                $inv_status = 'partially_paid';
            }
            $invoices_by_shop[$r['shop_temp_id']][] = [
                'inv_id'   => $r['inv_id'],
                'inv_no'   => $r['inv_number'],
                'date'     => $r['date'],
                'billed'   => $billed,
                'received' => $received,
                'due'      => $due,
                'status'   => $inv_status,
            ];
        }
        $stmt->close();
    }

    return [
        'rows'              => $shops,
        'grand_billed'      => $grand_billed,
        'grand_received'    => $grand_received,
        'grand_due'         => $grand_due,
        'grand_invoices'    => $grand_invoices,
        'shops_with_sales'  => $shops_with_sales,
        'invoices_by_shop'  => $invoices_by_shop,
    ];
}
