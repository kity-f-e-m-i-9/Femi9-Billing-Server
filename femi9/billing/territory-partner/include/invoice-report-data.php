<?php
// Shared data-fetch for the TP "Invoice Report" — used by both the on-screen
// report (invoice-report.php) and its Excel export (invoice-report-export.php)
// so the two never drift apart on filter logic or numbers shown.
//
// Expects $db_conn, $Login_user_IDvl, $Login_user_TYPEvl to already be set
// (via config.php) and $from/$to/$typeFilter/$statusFilter/$searchTerm to be
// resolved by the caller. Returns the filtered invoice list plus grand totals.

function tp_invoice_report_fetch(mysqli $db_conn, int $uid, string $utype, string $from, string $to, string $typeFilter, string $statusFilter, string $searchTerm): array {
    $invoices = [];

    if ($typeFilter === 'all' || $typeFilter === 'shop') {
        $stmt = $db_conn->prepare(
            "SELECT ui.inv_id, ui.inv_number, ui.date, ui.sub_total, ui.total,
                    s.name AS party_name, s.mobile_number AS party_mobile, s.country_code, s.address AS party_address,
                    COALESCE(r.received,0) AS received
             FROM user_invoice ui
             LEFT JOIN shop s ON s.temp_id = ui.to_user_id
             LEFT JOIN (SELECT inv_id, SUM(received) received FROM receipt GROUP BY inv_id) r ON r.inv_id = ui.inv_id
             WHERE ui.from_user_id = ? AND ui.from_user_type = ? AND ui.to_user_type = 'shop'
               AND ui.sub_total > 0 AND ui.date BETWEEN ? AND ?
             ORDER BY ui.date DESC, ui.id DESC"
        );
        $stmt->bind_param('isss', $uid, $utype, $from, $to);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $invoices[] = [
                'kind'     => 'shop',
                'inv_id'   => $r['inv_id'],
                'inv_no'   => $r['inv_number'],
                'date'     => $r['date'],
                'party'    => $r['party_name'] ?: 'Shop',
                'mobile'   => trim(($r['country_code'] ?? '') . ' ' . ($r['party_mobile'] ?? '')),
                'address'  => $r['party_address'] ?? '',
                'total'    => (float)$r['total'],
                'received' => (float)$r['received'],
            ];
        }
        $stmt->close();
    }

    if ($typeFilter === 'all' || $typeFilter === 'customer') {
        $stmt = $db_conn->prepare(
            "SELECT i.inv_id, i.inv_number, i.date, i.sub_total, i.total,
                    c.name AS party_name, c.mobile AS party_mobile,
                    COALESCE(r.received,0) AS received
             FROM invoice i
             LEFT JOIN customers c ON c.id = i.customer_id
             LEFT JOIN (SELECT inv_id, SUM(received) received FROM receipt GROUP BY inv_id) r ON r.inv_id = i.inv_id
             WHERE i.user_id = ? AND i.user_type = ?
               AND i.sub_total > 0 AND i.date BETWEEN ? AND ?
             ORDER BY i.date DESC, i.id DESC"
        );
        $stmt->bind_param('isss', $uid, $utype, $from, $to);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $invoices[] = [
                'kind'     => 'customer',
                'inv_id'   => $r['inv_id'],
                'inv_no'   => $r['inv_number'],
                'date'     => $r['date'],
                'party'    => $r['party_name'] ?: 'Walking Customer',
                'mobile'   => $r['party_mobile'] ?? '',
                'address'  => '',
                'total'    => (float)$r['total'],
                'received' => (float)$r['received'],
            ];
        }
        $stmt->close();
    }

    usort($invoices, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));

    $filtered = [];
    $grand_total = 0; $grand_received = 0;
    foreach ($invoices as $inv) {
        $due = max(0, $inv['total'] - $inv['received']);
        if ($inv['received'] <= 0) {
            $status = 'not_paid';
        } elseif (($inv['received'] + 0.01) >= $inv['total']) {
            $status = 'fully_paid';
        } else {
            $status = 'partially_paid';
        }

        if ($statusFilter !== 'all' && $status !== $statusFilter) continue;
        if ($searchTerm !== '' && stripos($inv['party'] . ' ' . $inv['mobile'] . ' ' . $inv['inv_no'], $searchTerm) === false) continue;

        $inv['due']    = $due;
        $inv['status'] = $status;
        $filtered[] = $inv;

        $grand_total    += $inv['total'];
        $grand_received += $inv['received'];
    }

    return [
        'rows'           => $filtered,
        'grand_total'    => $grand_total,
        'grand_received' => $grand_received,
        'grand_due'      => max(0, $grand_total - $grand_received),
        'shop_count'     => count(array_filter($filtered, fn($i) => $i['kind'] === 'shop')),
        'cust_count'     => count(array_filter($filtered, fn($i) => $i['kind'] === 'customer')),
    ];
}
