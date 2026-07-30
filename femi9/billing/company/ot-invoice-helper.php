<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ot_channels');
include("config.php");
header('Content-Type: application/json');

// Only these three channels use the WEB/ID/WA sequential invoice-number convention.
$prefixMap = [
    'WEBSITE'        => 'WEB',
    'ID CONCEPT'     => 'ID',
    'WHATSAPP SALES' => 'WA',
];

$action     = $_GET['action'] ?? '';
$cat        = trim($_GET['cat'] ?? '');
$inv_number = trim($_GET['inv_number'] ?? '');
$godownid   = (int)($_GET['godownid'] ?? 0);

if (!isset($prefixMap[$cat])) {
    echo json_encode(['error' => 'not tracked']);
    exit;
}

$prefix = $prefixMap[$cat];

// Indian financial year (Apr–Mar), e.g. 2026-07-22 -> "26-27"
$month = (int)date('n');
$year  = (int)date('Y');
$fyStartYear = $month >= 4 ? $year : $year - 1;
$fy = substr($fyStartYear, -2) . '-' . substr($fyStartYear + 1, -2);

/**
 * Does this specific company already have ANY invoices under this channel
 * (any financial year)? ot_sales_invoice has no godownid of its own, so
 * company is resolved via ot_sales (godownid is set per invoice, one value
 * per tempid). A company with zero history gets the new tagged/padded
 * format starting at 001; a company with existing history continues its
 * plain series instead — this flag decides which branch applies.
 */
function otCompanyHasHistory(mysqli $db_conn, string $cat, int $godownid): bool
{
    if ($godownid <= 0) return false;

    $stmt = $db_conn->prepare("
        SELECT COUNT(*) AS c
        FROM ot_sales_invoice osi
        INNER JOIN ot_sales os ON os.tempid = osi.tempid
        WHERE osi.cat = ? AND os.godownid = ?
    ");
    $stmt->bind_param('si', $cat, $godownid);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    return $count > 0;
}

/**
 * Highest invoice number this specific company has already used for this
 * channel, regardless of financial year — so continuing its plain series
 * picks up from where it already stands instead of restarting.
 */
function otMaxExistingNumberForCompany(mysqli $db_conn, string $cat, int $godownid): int
{
    if ($godownid <= 0) return 0;

    $stmt = $db_conn->prepare("
        SELECT DISTINCT osi.inv_number
        FROM ot_sales_invoice osi
        INNER JOIN ot_sales os ON os.tempid = osi.tempid
        WHERE osi.cat = ? AND os.godownid = ?
    ");
    $stmt->bind_param('si', $cat, $godownid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $max = 0;
    foreach ($rows as $row) {
        if (preg_match('/(\d+)$/', (string)$row['inv_number'], $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    return $max;
}

/**
 * Short entity code for the invoice series, derived from whichever company
 * profile (company_godown) was actually selected on the form — not assumed
 * from the channel category. company_godown has no dedicated short-code
 * column, so known entities are mapped explicitly; anything unrecognized
 * falls back to initials of the company name so a new godown never breaks
 * numbering. Returns '' if no godown was selected/found (legacy numbering).
 * Only used as the default format for a company with no existing series yet.
 */
function otEntityCode(mysqli $db_conn, int $godownid): string
{
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

if ($action === 'next') {
    // ID CONCEPT's series always displays in full — prefix/FY/entityCode +
    // a zero-padded number, e.g. ID/26-27/LLP001 — never just a bare number.
    // The entity code comes from whichever company was actually selected
    // (LLP/HC/initials-fallback), same convention as
    // load_next_invoice_customer.php's LLP series (C/FY/LLP{n}).
    if ($cat === 'ID CONCEPT') {
        if (otCompanyHasHistory($db_conn, $cat, $godownid)) {
            // This company already invoices here — continue its plain
            // series exactly (no entity tag), just the next number.
            // e.g. existing ...ID/26-27/135 -> next ID/26-27/136.
            $max = otMaxExistingNumberForCompany($db_conn, $cat, $godownid);
            $nextNumber = $prefix . '/' . $fy . '/' . ($max + 1);
        } else {
            // Brand new company, zero invoices under this channel — start
            // fresh with the tagged, zero-padded format: ID/26-27/LLP001.
            $entityCode = otEntityCode($db_conn, $godownid);
            $paddedNum  = str_pad('1', 3, '0', STR_PAD_LEFT);
            $nextNumber = $entityCode !== ''
                ? $prefix . '/' . $fy . '/' . $entityCode . $paddedNum
                : $prefix . '/' . $fy . '/' . $paddedNum;
        }

        echo json_encode(['number' => $nextNumber]);
        exit;
    }

    // WEBSITE / WHATSAPP SALES — unchanged legacy behavior, shared counter
    // scoped to the current financial year, no entity marker.
    $likePattern = $prefix . '/' . $fy . '/%';
    $stmt = $db_conn->prepare(
        "SELECT inv_number FROM ot_sales_invoice WHERE cat = ? AND inv_number LIKE ?"
    );
    $stmt->bind_param('ss', $cat, $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();

    $max = 0;
    while ($row = $result->fetch_assoc()) {
        $parts = explode('/', $row['inv_number']);
        $suffix = end($parts);
        if (ctype_digit($suffix)) {
            $max = max($max, (int)$suffix);
        }
    }
    $stmt->close();

    echo json_encode(['number' => $prefix . '/' . $fy . '/' . ($max + 1)]);
    exit;
}

if ($action === 'check') {
    if ($inv_number === '') {
        echo json_encode(['exists' => false]);
        exit;
    }
    $stmt = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM ot_sales_invoice WHERE cat = ? AND inv_number = ?"
    );
    $stmt->bind_param('ss', $cat, $inv_number);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode(['exists' => (int)$row['n'] > 0]);
    exit;
}

echo json_encode(['error' => 'invalid action']);
