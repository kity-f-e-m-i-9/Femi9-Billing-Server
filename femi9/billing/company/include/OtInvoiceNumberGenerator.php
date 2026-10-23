<?php
/**
 * Shared OT Channel Sales invoice-number auto-generation — used by the bulk
 * Excel importer (ot-sale-import-action.php) when a row's Invoice Number
 * column says "NEXT". Mirrors ot-invoice-helper.php's action=next logic
 * exactly (same prefixes/entity-code/padding conventions per category) so a
 * number generated here is indistinguishable from one the manual Add Sale
 * form would have suggested. Kept as its own copy rather than refactoring
 * ot-invoice-helper.php itself, since that endpoint is live on every manual
 * sale and isn't worth the regression risk for this.
 */

// Only these four channels use the WEB/ID/WA/DO sequential invoice-number
// convention — every other category is free/manual entry (e.g. a marketplace
// order number), same list as ot-sale-add.php's TRACKED_INVOICE_CATS.
const OT_TRACKED_INVOICE_CATS = ['WEBSITE', 'ID CONCEPT', 'WHATSAPP SALES', 'DIGITAL ORDERS'];

function ot_invoice_prefix_map(): array {
    return [
        'WEBSITE'        => 'WEB',
        'ID CONCEPT'     => 'ID',
        'WHATSAPP SALES' => 'WA',
        'DIGITAL ORDERS' => 'DO',
    ];
}

// Short entity code for the invoice series, derived from the company profile
// actually selected — see ot-invoice-helper.php::otEntityCode for the full
// rationale; kept identical here.
function ot_invoice_entity_code(mysqli $db_conn, int $godownid): string {
    if ($godownid <= 0) return '';

    static $knownCodes = [
        'FEMI NAYAN LLP'   => 'LLP',
        'FEMI HEALTH CARE' => 'HC',
    ];

    $stmt = $db_conn->prepare("SELECT gname FROM company_godown WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $godownid);
    $stmt->execute();
    $gname = $stmt->get_result()->fetch_assoc()['gname'] ?? '';
    $stmt->close();

    if ($gname === '') return '';
    if (isset($knownCodes[$gname])) return $knownCodes[$gname];

    $code = '';
    foreach (preg_split('/\s+/', trim($gname)) as $word) {
        if ($word !== '') $code .= strtoupper($word[0]);
    }
    return $code;
}

// Highest invoice number this company has already used for this channel under
// its own entity tag — see ot-invoice-helper.php::otMaxExistingNumberForCompany
// for the full rationale; kept identical here.
function ot_invoice_max_existing_number_for_company(mysqli $db_conn, string $cat, string $prefix, int $godownid, string $entityCode): int {
    if ($godownid <= 0) return 0;

    $likePattern = $entityCode !== '' ? $prefix . '/%/' . $entityCode . '%' : $prefix . '/%';
    $stmt = $db_conn->prepare("
        SELECT DISTINCT osi.inv_number
        FROM ot_sales_invoice osi
        INNER JOIN ot_sales os ON os.tempid = osi.tempid
        WHERE osi.cat = ? AND os.godownid = ? AND osi.inv_number LIKE ?
    ");
    $stmt->bind_param('sis', $cat, $godownid, $likePattern);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $max = 0;
    foreach ($rows as $row) {
        $parts = explode('/', (string)$row['inv_number']);
        $last  = end($parts);
        if ($entityCode !== '') {
            if (str_starts_with($last, $entityCode)) {
                $numPart = substr($last, strlen($entityCode));
                if (ctype_digit($numPart)) { $max = max($max, (int)$numPart); }
            }
        } elseif (ctype_digit($last)) {
            $max = max($max, (int)$last);
        }
    }
    return $max;
}

/**
 * Next invoice number for a tracked category, same convention as
 * ot-invoice-helper.php?action=next. Returns null if $cat isn't one of the
 * four tracked categories (caller should treat that as "not applicable" —
 * NEXT only makes sense for these).
 */
function ot_invoice_next_number(mysqli $db_conn, string $cat, int $godownid, string $productCategory = 'napkin'): ?string {
    $prefixMap = ot_invoice_prefix_map();
    if (!isset($prefixMap[$cat])) return null;

    $prefix = $prefixMap[$cat] . ($productCategory === 'diaper' ? 'D' : '');

    $month = (int)date('n');
    $year  = (int)date('Y');
    $fyStartYear = $month >= 4 ? $year : $year - 1;
    $fy = substr((string)$fyStartYear, -2) . '-' . substr((string)($fyStartYear + 1), -2);

    if ($cat === 'ID CONCEPT') {
        $entityCode = ot_invoice_entity_code($db_conn, $godownid);
        $max        = ot_invoice_max_existing_number_for_company($db_conn, $cat, $prefix, $godownid, $entityCode);
        $paddedNum  = str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
        return $entityCode !== ''
            ? $prefix . '/' . $fy . '/' . $entityCode . $paddedNum
            : $prefix . '/' . $fy . '/' . $paddedNum;
    }

    if ($cat === 'DIGITAL ORDERS') {
        $likePattern = $prefix . '/' . $fy . '/%';
        $stmt = $db_conn->prepare("SELECT inv_number FROM ot_sales_invoice WHERE cat = ? AND inv_number LIKE ?");
        $stmt->bind_param('ss', $cat, $likePattern);
        $stmt->execute();
        $result = $stmt->get_result();
        $max = 0;
        while ($row = $result->fetch_assoc()) {
            $parts = explode('/', $row['inv_number']);
            $suffix = end($parts);
            if (ctype_digit($suffix)) { $max = max($max, (int)$suffix); }
        }
        $stmt->close();
        return $prefix . '/' . $fy . '/' . str_pad((string)($max + 1), 2, '0', STR_PAD_LEFT);
    }

    // WEBSITE / WHATSAPP SALES — shared counter scoped to the current
    // financial year, no entity marker, no padding.
    $likePattern = $prefix . '/' . $fy . '/%';
    $stmt = $db_conn->prepare("SELECT inv_number FROM ot_sales_invoice WHERE cat = ? AND inv_number LIKE ?");
    $stmt->bind_param('ss', $cat, $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $max = 0;
    while ($row = $result->fetch_assoc()) {
        $parts = explode('/', $row['inv_number']);
        $suffix = end($parts);
        if (ctype_digit($suffix)) { $max = max($max, (int)$suffix); }
    }
    $stmt->close();
    return $prefix . '/' . $fy . '/' . ($max + 1);
}

/**
 * Increments the trailing digit run of an invoice number string, preserving
 * whatever comes before it and the digit width (zero-padding), e.g.
 * "WA/26-27/5" -> "WA/26-27/6", "ID/26-27/LLP005" -> "ID/26-27/LLP006".
 * Used to continue a "NEXT" series within the same uploaded file once its
 * first number (typed or auto-generated) is known, without re-querying the
 * database for every subsequent row. Returns null if the string has no
 * trailing digits to increment.
 */
function ot_import_increment_trailing_number(string $invNumber): ?string {
    if (!preg_match('/^(.*?)(\d+)$/', $invNumber, $m)) {
        return null;
    }
    $prefix = $m[1];
    $digits = $m[2];
    $width  = strlen($digits);
    $next   = (string)((int)$digits + 1);
    return $prefix . str_pad($next, $width, '0', STR_PAD_LEFT);
}
