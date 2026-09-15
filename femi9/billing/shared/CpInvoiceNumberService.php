<?php
/**
 * CpInvoiceNumberService — auto-generated CP invoice number series.
 *
 * Company is CP's only approver, so there is only one source, 'CO', and the
 * number carries no source tag: CPDN/{fiscal-year}/{seq}. Mirrors
 * TpInvoiceNumberService.php's locking/self-healing pattern exactly, with
 * its own independent sequence table (cp_inv_sequence) so CP and TP
 * invoice numbering never contend with or influence each other.
 *
 * Must be called inside an active transaction (caller locks the sequence
 * row via FOR UPDATE for the duration of the invoice insert).
 */

function cpInvoiceEnsureSequenceSchema(mysqli $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $table = $db->query("SHOW TABLES LIKE 'cp_inv_sequence'");
    if ($table && $table->num_rows === 0) {
        $db->query("
            CREATE TABLE cp_inv_sequence (
                source VARCHAR(10) NOT NULL,
                last_val INT UNSIGNED NOT NULL DEFAULT 0,
                fy VARCHAR(5) NOT NULL DEFAULT '',
                PRIMARY KEY (source)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}

function cpInvoiceNextNumber(mysqli $db, string $source, string $invoiceDate, int $padDigits = 3): string {
    cpInvoiceEnsureSequenceSchema($db);

    $inv_month  = (int)date('n', strtotime($invoiceDate));
    $inv_year   = (int)date('Y', strtotime($invoiceDate));
    $fy_start   = $inv_month >= 4 ? $inv_year : $inv_year - 1;
    $current_fy = substr((string)$fy_start, 2) . '-' . substr((string)($fy_start + 1), 2); // e.g. "26-27"

    $source_esc = $db->real_escape_string($source);
    $db->query("INSERT IGNORE INTO cp_inv_sequence (source, last_val, fy) VALUES ('$source_esc', 0, '')");

    $db->query("SELECT last_val, fy FROM cp_inv_sequence WHERE source='$source_esc' FOR UPDATE");
    $seq_row = $db->query("SELECT last_val, fy FROM cp_inv_sequence WHERE source='$source_esc'")->fetch_assoc();

    $like_pattern = "CPDN/$current_fy/%";
    $max_res = $db->query("SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '/', -1) AS UNSIGNED)) AS max_val FROM cp_invoices WHERE invoice_number LIKE '$like_pattern'");
    $actual_max = (int)(($max_res->fetch_assoc())['max_val'] ?? 0);

    $seq_val  = ($seq_row && $seq_row['fy'] === $current_fy) ? (int)$seq_row['last_val'] : 0;
    $next_val = max($seq_val, $actual_max) + 1;

    $db->query("UPDATE cp_inv_sequence SET last_val=$next_val, fy='$current_fy' WHERE source='$source_esc'");

    $seq_str = str_pad((string)$next_val, $padDigits, '0', STR_PAD_LEFT);
    return "CPDN/$current_fy/$seq_str";
}
