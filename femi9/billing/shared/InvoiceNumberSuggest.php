<?php
/**
 * invoiceSuggestNextNumber() — shared "next number" suggestion for invoice-add
 * pages that use a per-channel letter-prefix + financial-year + running-number
 * pattern (e.g. S/26-27/LLP210, SH/26-27/LLP008, C/26-27/LLP077).
 *
 * This is suggestion-only, matching the existing load_next_invoice_customer.php
 * behaviour: the returned number is a hint that auto-fills an editable, free-text
 * field and is never enforced or locked — callers still do their own duplicate
 * check on submit. It is NOT a transaction-safe sequence like TpInvoiceNumberService.
 *
 * Diaper vs Napkin: a product's series is decided once, by whichever product
 * type is added FIRST to a new invoice (there's no cart to inspect before that —
 * these add-pages submit one line item per request). Once a number is suggested
 * for an invoice, it is never retroactively changed even if a later line item on
 * the same invoice is the other product type — see product-action.php callers.
 *
 * $napkinPrefix / $diaperPrefix: the literal prefix used in inv_number, e.g.
 * 'S' (napkin) / 'SD' (diaper) for stockist, 'SH' / 'SHD' for shop, etc.
 * $table: table to scan for existing numbers (e.g. 'user_invoice', 'invoice').
 * $whereSql: extra WHERE conditions (already escaped) scoping the LIKE scan to
 * this channel (e.g. "from_user_type='stockiest'").
 */
function invoiceSuggestNextNumber(mysqli $db, string $table, string $whereSql, string $napkinPrefix, string $diaperPrefix, string $category): string {
    $prefix = ($category === 'diaper') ? $diaperPrefix : $napkinPrefix;

    $month = (int)date('n');
    $year  = (int)date('Y');
    $fyStartYear = $month >= 4 ? $year : $year - 1;
    $fy = substr((string)$fyStartYear, -2) . '-' . substr((string)($fyStartYear + 1), -2);

    $likePattern = $db->real_escape_string($prefix . '/' . $fy . '/LLP') . '%';
    $sql = "SELECT inv_number FROM $table WHERE ($whereSql) AND inv_number LIKE '$likePattern'";
    $res = $db->query($sql);

    $max = 0;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $suffix = preg_replace('/^.*LLP/', '', $row['inv_number']);
            if (ctype_digit($suffix)) {
                $max = max($max, (int)$suffix);
            }
        }
    }

    return $prefix . '/' . $fy . '/LLP' . ($max + 1);
}

// Looks up a product's category ('napkin'/'diaper'), treating NULL as 'napkin'
// per the existing convention (see Products.php/product-action.php).
function invoiceProductCategory(mysqli $db, int $productId): string {
    $stmt = $db->prepare("SELECT category FROM products WHERE id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($row && $row['category'] === 'diaper') ? 'diaper' : 'napkin';
}
