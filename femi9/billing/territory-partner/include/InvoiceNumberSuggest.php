<?php
// Suggests the next invoice number for a TP's new shop/customer invoice —
// one more than the HIGHEST invoice number they've actually used so far
// (not simply their most-recently-created row — those can differ whenever
// invoices weren't created in strict numeric order, e.g. C0028/C0029/C0030
// inserted out of sequence, which made an earlier "last row by id" approach
// suggest an already-used number). Only a starting suggestion: the field
// stays a free-text input the TP can still edit, and the existing
// duplicate-check (loadInvoiceNumberUSER.php / load_InvoiceNumber_customer.php)
// still runs on it either way. Confirmed 2026-09-23.
//
// $table/$typeCol/$idCol let this serve both TP invoice tables, which use
// different column names for the same "who issued this" concept:
// user_invoice (shop/B2B invoices) uses from_user_type/from_user_id, while
// invoice (direct customer invoices) uses user_type/user_id.
//
// $idPrefixMarker excludes rows whose inv_number is really a leftover
// internal inv_id (never a TP-typed number) — some historical rows never
// got a real invoice number typed in and defaulted to storing inv_id itself
// (shape: 10 random digits + "CMPSHP"/"CMPCUST" + a date/time string), which
// carries a huge trailing numeric run that would otherwise hijack the "true
// max" as if it were a real, enormous invoice number. Confirmed 2026-09-23
// (shop-invoice-add.php's own $invidprefix, e.g. "CMPSHP").
function tp_suggest_next_invoice_number(mysqli $db_conn, string $fromType, string $fromId, string $table = 'user_invoice', string $typeCol = 'from_user_type', string $idCol = 'from_user_id', ?string $idPrefixMarker = null): string {
    // Bounded to the most recent 500 — plenty to find the true max for any
    // real TP's numbering pattern without scanning their entire history.
    $stmt = $db_conn->prepare(
        "SELECT inv_number FROM `$table` WHERE `$typeCol` = ? AND `$idCol` = ? ORDER BY id DESC LIMIT 500"
    );
    $stmt->bind_param('ss', $fromType, $fromId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rows)) { return ''; }

    $bestPrefix = null;
    $bestWidth  = 0;
    $bestValue  = -1;
    foreach ($rows as $row) {
        $invNum = (string)($row['inv_number'] ?? '');
        if ($idPrefixMarker !== null && stripos($invNum, $idPrefixMarker) !== false) { continue; }
        // Needs a trailing numeric run to compare — a one-off number with no
        // such pattern (e.g. a payment-gateway reference string) is skipped
        // rather than corrupting the comparison.
        if (!preg_match('/^(.*?)(\d+)$/', $invNum, $m)) { continue; }
        $value = (int)$m[2];
        if ($value > $bestValue) {
            $bestValue  = $value;
            $bestPrefix = $m[1];
            $bestWidth  = strlen($m[2]);
        }
    }

    if ($bestPrefix === null) { return ''; }

    $nextValue = (string)($bestValue + 1);
    return $bestPrefix . str_pad($nextValue, $bestWidth, '0', STR_PAD_LEFT);
}
