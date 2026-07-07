<?php
/**
 * TpInvoiceNumberService — auto-generated TP invoice number series.
 *
 * Company auto-generates from one system-wide series (source 'CO', format
 * TP/CO/{fiscal-year}/{seq} — there is only one company). Super-stockist
 * auto-generates too, but each SS account gets its OWN independent series —
 * source is 'SS{ss_id}' (e.g. 'SS8'), so two different Super Stockists both
 * issuing their "first" TP invoice each get .../001, not a shared counter,
 * and one SS's insert never lock-contends with another's (see
 * super-stockist/tp-invoice-action.php). Cross-source/account uniqueness is
 * additionally enforced by tp_invoices' uk_tp_inv_number key.
 *
 * Must be called inside an active transaction (caller locks the sequence row
 * via FOR UPDATE for the duration of the invoice insert).
 */

function tpInvoiceEnsureSequenceSchema(mysqli $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $col = $db->query("SHOW COLUMNS FROM tp_inv_sequence LIKE 'source'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE tp_inv_sequence ADD COLUMN source VARCHAR(10) NOT NULL DEFAULT '' AFTER id");
        // Preserve the existing counter's accumulated value under 'CO' so
        // already-issued numbers are never reused.
        $db->query("UPDATE tp_inv_sequence SET source='CO' WHERE id=1 AND source=''");
        $db->query("ALTER TABLE tp_inv_sequence DROP PRIMARY KEY, ADD PRIMARY KEY (source)");
    }
}

function tpInvoiceNextNumber(mysqli $db, string $source, string $invoiceDate, int $padDigits = 3): string {
    tpInvoiceEnsureSequenceSchema($db);

    $inv_month  = (int)date('n', strtotime($invoiceDate));
    $inv_year   = (int)date('Y', strtotime($invoiceDate));
    $fy_start   = $inv_month >= 4 ? $inv_year : $inv_year - 1;
    $current_fy = substr((string)$fy_start, 2) . '-' . substr((string)($fy_start + 1), 2); // e.g. "26-27"

    $source_esc = $db->real_escape_string($source);
    $db->query("INSERT IGNORE INTO tp_inv_sequence (source, last_val, fy) VALUES ('$source_esc', 0, '')");

    $db->query("SELECT last_val, fy FROM tp_inv_sequence WHERE source='$source_esc' FOR UPDATE");
    $seq_row = $db->query("SELECT last_val, fy FROM tp_inv_sequence WHERE source='$source_esc'")->fetch_assoc();

    // Sync with actual max for this source+FY to guard against an out-of-sync sequence
    $max_res = $db->query("SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '/', -1) AS UNSIGNED)) AS max_val FROM tp_invoices WHERE invoice_number LIKE 'TP/$source_esc/$current_fy/%'");
    $actual_max = (int)(($max_res->fetch_assoc())['max_val'] ?? 0);

    $seq_val  = ($seq_row && $seq_row['fy'] === $current_fy) ? (int)$seq_row['last_val'] : 0;
    $next_val = max($seq_val, $actual_max) + 1;

    $db->query("UPDATE tp_inv_sequence SET last_val=$next_val, fy='$current_fy' WHERE source='$source_esc'");

    return 'TP/' . $source . '/' . $current_fy . '/' . str_pad((string)$next_val, $padDigits, '0', STR_PAD_LEFT);
}
