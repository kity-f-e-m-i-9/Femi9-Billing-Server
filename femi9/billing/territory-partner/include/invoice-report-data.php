<?php
// Shared data-fetch for the TP "Invoice Report" — used by both the on-screen
// report (invoice-report.php) and its Excel export (invoice-report-export.php)
// so the two never drift apart on filter logic or numbers shown.
//
// Expects $db_conn, $Login_user_IDvl, $Login_user_TYPEvl to already be set
// (via config.php) and $from/$to/$typeFilter/$statusFilter/$searchTerm to be
// resolved by the caller. Returns the filtered invoice list plus grand totals.

// Classifies each invoice id as 'napkin' (every line item is napkin),
// 'diaper' (every line item is diaper), or 'mixed' (both present on the same
// invoice) — never a prorated split. An invoice's actual payment is recorded
// once against the whole invoice, not per product line, so there is no real
// fact to divide "Received"/"Due" by category for a mixed invoice; putting
// mixed invoices in their own bucket (rather than guessing a percentage)
// keeps every number this report shows an exact one. $itemsTable is always
// one of the two literal table names this file calls it with below, never
// request data.
function tp_invoice_report_categorize(mysqli $db_conn, string $itemsTable, array $invIds): array {
    if (empty($invIds)) return [];
    $placeholders = implode(',', array_fill(0, count($invIds), '?'));
    $types = str_repeat('s', count($invIds));
    $stmt = $db_conn->prepare(
        "SELECT ii.inv_id, GROUP_CONCAT(DISTINCT p.category SEPARATOR ',') AS cats
         FROM {$itemsTable} ii
         JOIN products p ON p.id = ii.pr_id
         WHERE ii.inv_id IN ($placeholders)
         GROUP BY ii.inv_id"
    );
    $stmt->bind_param($types, ...$invIds);
    $stmt->execute();
    $out = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $cats = array_values(array_filter(array_unique(explode(',', (string)($r['cats'] ?? '')))));
        if (count($cats) > 1) {
            $out[$r['inv_id']] = 'mixed';
        } elseif (count($cats) === 1) {
            $out[$r['inv_id']] = $cats[0];
        } else {
            $out[$r['inv_id']] = 'unspecified';
        }
    }
    $stmt->close();
    return $out;
}

function tp_invoice_report_fetch(mysqli $db_conn, int $uid, string $utype, string $from, string $to, string $typeFilter, string $statusFilter, string $searchTerm, string $categoryFilter = 'all'): array {
    $invoices = [];

    if ($typeFilter === 'all' || $typeFilter === 'shop') {
        $stmt = $db_conn->prepare(
            "SELECT ui.inv_id, ui.inv_number, ui.date, ui.sub_total, ui.total,
                    s.name AS party_name, s.mobile_number AS party_mobile, s.country_code, s.address AS party_address,
                    s.gstin AS party_gstin,
                    COALESCE(r.cash_received,0) AS cash_received,
                    COALESCE(r.cn_received,0) AS cn_received
             FROM user_invoice ui
             LEFT JOIN shop s ON s.temp_id = ui.to_user_id
             LEFT JOIN (
                 SELECT inv_id,
                        SUM(CASE WHEN payment_type = 'credit_note' THEN 0 ELSE received END) AS cash_received,
                        SUM(CASE WHEN payment_type = 'credit_note' THEN received ELSE 0 END) AS cn_received
                 FROM receipt GROUP BY inv_id
             ) r ON r.inv_id = ui.inv_id
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
                'gstin'    => $r['party_gstin'] ?? '',
                'total'    => (float)$r['total'],
                'cash'     => (float)$r['cash_received'],
                'cn'       => (float)$r['cn_received'],
                'received' => (float)$r['cash_received'] + (float)$r['cn_received'],
            ];
        }
        $stmt->close();
    }

    if ($typeFilter === 'all' || $typeFilter === 'customer') {
        $stmt = $db_conn->prepare(
            "SELECT i.inv_id, i.inv_number, i.date, i.sub_total, i.total,
                    c.name AS party_name, c.mobile AS party_mobile, c.gstin AS party_gstin,
                    COALESCE(r.cash_received,0) AS cash_received,
                    COALESCE(r.cn_received,0) AS cn_received
             FROM invoice i
             LEFT JOIN customers c ON c.id = i.customer_id
             LEFT JOIN (
                 SELECT inv_id,
                        SUM(CASE WHEN payment_type = 'credit_note' THEN 0 ELSE received END) AS cash_received,
                        SUM(CASE WHEN payment_type = 'credit_note' THEN received ELSE 0 END) AS cn_received
                 FROM receipt GROUP BY inv_id
             ) r ON r.inv_id = i.inv_id
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
                'gstin'    => $r['party_gstin'] ?? '',
                'total'    => (float)$r['total'],
                'cash'     => (float)$r['cash_received'],
                'cn'       => (float)$r['cn_received'],
                'received' => (float)$r['cash_received'] + (float)$r['cn_received'],
            ];
        }
        $stmt->close();
    }

    // Category classification (napkin / diaper / mixed) — shop invoices'
    // items live in user_invoice_items, customer invoices' in invoice_items,
    // so each kind is looked up against its own table and never cross-mixed.
    if ($categoryFilter !== 'all') {
        $shopInvIds = array_column(array_filter($invoices, fn($i) => $i['kind'] === 'shop'), 'inv_id');
        $custInvIds = array_column(array_filter($invoices, fn($i) => $i['kind'] === 'customer'), 'inv_id');
        $catByInvId = tp_invoice_report_categorize($db_conn, 'user_invoice_items', $shopInvIds)
                    + tp_invoice_report_categorize($db_conn, 'invoice_items', $custInvIds);
        foreach ($invoices as &$inv) {
            $inv['category'] = $catByInvId[$inv['inv_id']] ?? 'unspecified';
        }
        unset($inv);
    }

    usort($invoices, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));

    $filtered = [];
    $grand_total = 0; $grand_received = 0; $grand_cn = 0;
    foreach ($invoices as $inv) {
        if ($categoryFilter !== 'all' && ($inv['category'] ?? 'unspecified') !== $categoryFilter) continue;
        $due = max(0, $inv['total'] - $inv['received']);
        // 'received' here is cash + credit-note credit combined (see $inv['cash'] /
        // $inv['cn'] above) — an invoice settled purely by a credit note (no real
        // cash) still reads 'fully_paid' here so Due/filter behaviour is unchanged,
        // but 'returned' flags that case separately so a GST-filing read of this
        // report doesn't mistake a return for an actual payment collected.
        if ($inv['cash'] <= 0 && $inv['cn'] > 0 && ($inv['received'] + 0.01) >= $inv['total']) {
            $status = 'returned';
        } elseif ($inv['received'] <= 0) {
            $status = 'not_paid';
        } elseif (($inv['received'] + 0.01) >= $inv['total']) {
            $status = 'fully_paid';
        } else {
            $status = 'partially_paid';
        }

        if ($statusFilter !== 'all' && $status !== $statusFilter && !($statusFilter === 'fully_paid' && $status === 'returned')) continue;
        if ($searchTerm !== '' && stripos($inv['party'] . ' ' . $inv['mobile'] . ' ' . $inv['inv_no'], $searchTerm) === false) continue;

        $inv['due']    = $due;
        $inv['status'] = $status;
        $filtered[] = $inv;

        $grand_total    += $inv['total'];
        $grand_received += $inv['cash'];
        $grand_cn       += $inv['cn'];
    }

    return [
        'rows'           => $filtered,
        'grand_total'    => $grand_total,
        'grand_received' => $grand_received,
        'grand_cn'       => $grand_cn,
        'grand_due'      => max(0, $grand_total - $grand_received - $grand_cn),
        'shop_count'     => count(array_filter($filtered, fn($i) => $i['kind'] === 'shop')),
        'cust_count'     => count(array_filter($filtered, fn($i) => $i['kind'] === 'customer')),
    ];
}
