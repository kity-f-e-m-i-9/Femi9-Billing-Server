<?php
/**
 * Input Stock Action Handler - Multi-Product
 *
 * Security & quality improvements over original:
 * ─────────────────────────────────────────────
 * 1. CSRF token validation (hash_equals to prevent timing attacks).
 * 2. All DB queries use prepared statements → SQL injection eliminated.
 * 3. Input sanitised with filter_var / intval instead of str_replace("'","").
 * 4. $_REQUEST replaced with explicit $_POST.
 * 5. Whole operation wrapped in a MySQL transaction → partial inserts rolled back on failure.
 * 6. include("config.php") added (was missing in original, relied on checksession side-effect).
 * 7. Redirects use header() instead of inline <script> window.location (cleaner, no HTML output).
 * 8. Multi-product arrays (product_id[], input_qty[], input_remarks[]) processed in a loop.
 * 9. Duplicate-product guard per godown added server-side (not just front-end).
 * 10. ob_start() guards against accidental whitespace before header().
 */

ob_start();
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
require_once("include/GodownAccess.php");

// ── Helper: safe redirect (no further output after this) ────────────────────
function redirectTo(string $url): never
{
    ob_end_clean();
    header('Location: ' . $url);
    exit;
}

// ── Only handle POST submissions with the expected button ────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['add-record'])) {
    redirectTo('add-input');
}

// ── CSRF validation ──────────────────────────────────────────────────────────
$submittedToken = $_POST['csrf_token'] ?? '';
if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $submittedToken)
) {
    redirectTo('add-input?csrferror');
}
// Rotate token after successful validation
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ── Collect & validate scalar inputs ────────────────────────────────────────
$godownId   = filter_var($_POST['godownid']    ?? 0, FILTER_VALIDATE_INT);
$inputDate  = $_POST['input_date'] ?? '';
$tempId     = preg_replace('/[^A-Z0-9\/]/', '', strtoupper($_POST['tempid'] ?? ''));

if (!$godownId || $godownId <= 0) {
    redirectTo('add-input?invalid');
}
if (!is_godown_allowed($db_conn, $godownId)) {
    redirectTo('add-input?unauthorized');
}

// Validate & normalise date
$dateObj = DateTime::createFromFormat('Y-m-d', $inputDate);
if (!$dateObj || $dateObj->format('Y-m-d') !== $inputDate) {
    redirectTo('add-input?invalid');
}
$inputDate = $dateObj->format('Y-m-d');

// ── Collect & validate array inputs ─────────────────────────────────────────
$productIds   = $_POST['product_id']     ?? [];
$inputQtys    = $_POST['input_qty']      ?? [];
$inputRemarks = $_POST['input_remarks']  ?? [];

if (
    !is_array($productIds)  ||
    !is_array($inputQtys)   ||
    !is_array($inputRemarks)||
    count($productIds) === 0 ||
    count($productIds) !== count($inputQtys) ||
    count($productIds) !== count($inputRemarks)
) {
    redirectTo('add-input?invalid');
}

// Sanitise each row
$rows = [];
$seenProducts = [];

foreach ($productIds as $i => $rawPid) {
    $pid = filter_var($rawPid, FILTER_VALIDATE_INT);
    $qty = filter_var($inputQtys[$i] ?? 0, FILTER_VALIDATE_INT);
    $rmk = htmlspecialchars(strip_tags(trim($inputRemarks[$i] ?? '')), ENT_QUOTES, 'UTF-8');

    if (!$pid || $pid <= 0 || !$qty || $qty <= 0) {
        redirectTo('add-input?invalid');
    }

    // Server-side duplicate product guard
    if (in_array($pid, $seenProducts, true)) {
        redirectTo('add-input?duplicateproduct');
    }
    $seenProducts[] = $pid;

    $rows[] = [
        'product_id'     => $pid,
        'input_qty'      => $qty,
        'input_remarks'  => $rmk,
    ];
}

$userType = 'company';
$userId   = $godownId;   // per original business logic

// ── Check opening stock exists for this godown ───────────────────────────────
$stmtChkStock = $db_conn->prepare(
    "SELECT COUNT(*) AS cnt FROM stock WHERE user_type = ? AND user_id = ?"
);
$stmtChkStock->bind_param('si', $userType, $userId);
$stmtChkStock->execute();
$cntStock = (int) $stmtChkStock->get_result()->fetch_assoc()['cnt'];
$stmtChkStock->close();

if ($cntStock === 0) {
    redirectTo("add-input?stocknotupdated&gid={$godownId}");
}

// ── Duplicate-submission guard (tempid) ──────────────────────────────────────
$stmtChkTemp = $db_conn->prepare(
    "SELECT COUNT(*) AS cnt FROM input_stock WHERE tempid = ?"
);
$stmtChkTemp->bind_param('s', $tempId);
$stmtChkTemp->execute();
$cntTemp = (int) $stmtChkTemp->get_result()->fetch_assoc()['cnt'];
$stmtChkTemp->close();

if ($cntTemp > 0) {
    redirectTo('add-input?alreadyexists');
}

// ── Begin transaction ─────────────────────────────────────────────────────────
$db_conn->begin_transaction();

try {
    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    // Prepared statements reused inside the loop
    $stmtInsertInput = $db_conn->prepare(
        "INSERT INTO input_stock (tempid, product_id, input_qty, input_date, godownid, input_remarks)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmtChkProd = $db_conn->prepare(
        "SELECT COUNT(*) AS cnt FROM stock
         WHERE product_id = ? AND user_type = ? AND user_id = ?
         FOR UPDATE"                                     // lock row during transaction
    );

    $stmtInsertStock = $db_conn->prepare(
        "INSERT INTO stock
             (product_id, opening_qty, opening_date, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id)
         VALUES (?, 0, ?, 0, 0, 0, 0, 0, ?, ?)"
    );

    $stmtGetStock = $db_conn->prepare(
        "SELECT input_qty, closing_qty FROM stock
         WHERE product_id = ? AND user_type = ? AND user_id = ?
         FOR UPDATE"
    );

    $stmtUpdateStock = $db_conn->prepare(
        "UPDATE stock SET input_qty = ?, closing_qty = ?
         WHERE product_id = ? AND user_type = ? AND user_id = ?"
    );

    foreach ($rows as $row) {
        $pid = $row['product_id'];
        $qty = $row['input_qty'];
        $rmk = $row['input_remarks'];

        // 1. Insert into input_stock
        $stmtInsertInput->bind_param(
            'siisis',
            $tempId, $pid, $qty, $inputDate, $godownId, $rmk
        );
        $stmtInsertInput->execute();

        // 2. Ensure a stock row exists for this product / godown
        $stmtChkProd->bind_param('isi', $pid, $userType, $userId);
        $stmtChkProd->execute();
        $cntProd = (int) $stmtChkProd->get_result()->fetch_assoc()['cnt'];

        if ($cntProd === 0) {
            $stmtInsertStock->bind_param('issi', $pid, $inputDate, $userType, $userId);
            $stmtInsertStock->execute();
        }

        // 3. Read current stock quantities (locked)
        $stmtGetStock->bind_param('isi', $pid, $userType, $userId);
        $stmtGetStock->execute();
        $stockRow = $stmtGetStock->get_result()->fetch_assoc();

        $newInputQty   = (int) $stockRow['input_qty']   + $qty;
        $newClosingQty = (int) $stockRow['closing_qty'] + $qty;

        // 4. Update stock
        $stmtUpdateStock->bind_param('iiisi', $newInputQty, $newClosingQty, $pid, $userType, $userId);
        $stmtUpdateStock->execute();

        // 5. Write ledger entry (audit trail)
        $qtyBefore = (int)$stockRow['closing_qty'];
        $qtyAfter  = $qtyBefore + $qty;
        $stmtLedger = $db_conn->prepare(
            "INSERT INTO stock_ledger
                (product_id, user_type, user_id, action, qty,
                 qty_before, qty_after, ref_type, ref_id, note, created_by)
             VALUES (?, ?, ?, 'credit', ?, ?, ?, 'adjustment', ?, 'input stock', ?)"
        );
        $userIdStr = (string)$userId;
        $stmtLedger->bind_param(
            'issiiiss',
            $pid, $userType, $userIdStr, $qty,
            $qtyBefore, $qtyAfter, $tempId, $createdBy
        );
        $stmtLedger->execute();
        $stmtLedger->close();
    }

    // Close prepared statements
    $stmtInsertInput->close();
    $stmtChkProd->close();
    $stmtInsertStock->close();
    $stmtGetStock->close();
    $stmtUpdateStock->close();

    $db_conn->commit();

} catch (Throwable $e) {
    $db_conn->rollback();
    // Log error server-side; never expose DB details to the browser
    error_log('input-action.php transaction failed: ' . $e->getMessage());
    redirectTo('add-input?saveerror');
}

redirectTo('manage-input?addesuccess');