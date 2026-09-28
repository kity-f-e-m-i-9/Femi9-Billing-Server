<?php
/**
 * Input Stock Action Handler - Users (Multi-Product)
 *
 * Improvements over original:
 * ─────────────────────────────────────────────────
 * 1. CSRF token validation (hash_equals, timing-attack safe).
 * 2. All DB queries use prepared statements → SQL injection eliminated.
 * 3. str_replace("'","") sanitisation removed; replaced with filter_var / intval / strip_tags.
 * 4. $_REQUEST replaced with explicit $_POST.
 * 5. Whole operation wrapped in MySQL transaction → partial inserts rolled back on failure.
 * 6. include("config.php") added (missing in original).
 * 7. Redirects use header() + exit instead of inline <script> window.location.
 * 8. ob_start() prevents whitespace before header().
 * 9. Multi-product arrays (product_id[], input_qty[], input_remarks[]) processed in a loop.
 * 10. Server-side duplicate-product guard per user added.
 * 11. user_type whitelisted to prevent arbitrary values being stored.
 */

ob_start();
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

// ── Helper: safe redirect ────────────────────────────────────────────────────
function redirectTo(string $url): never
{
    ob_end_clean();
    header('Location: ' . $url);
    exit;
}

// ── Only handle POST with expected button ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['add-record'])) {
    redirectTo('add-input-users');
}

// ── CSRF validation ──────────────────────────────────────────────────────────
$submittedToken = $_POST['csrf_token'] ?? '';
if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $submittedToken)
) {
    redirectTo('add-input-users?csrferror');
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // rotate

// ── Whitelist for user_type (plain array — const not valid in runtime scope) ─
$allowedUserTypes = [
    'super_stockiest',
    'stockiest',
    'super_distributor',
    'distributor',
];

// ── Collect & validate scalar inputs ────────────────────────────────────────
$toUserType = trim($_POST['to_usertype'] ?? '');
// to_userid is an alphanumeric string (e.g. "37893FST27082454527") — NOT an integer
$toUserId   = trim($_POST['to_userid']   ?? '');
$tempId     = preg_replace('/[^A-Z0-9]/', '', strtoupper($_POST['tempid'] ?? ''));
$inputDate  = trim($_POST['input_date']  ?? '');

// Each check redirects with a unique error code so you can identify exactly what failed
if (!in_array($toUserType, $allowedUserTypes, true)) {
    redirectTo('add-input-users?err=usertype');
}

if (empty($toUserId)) {
    redirectTo('add-input-users?err=userid');
}

$dateObj = DateTime::createFromFormat('Y-m-d', $inputDate);
if (!$dateObj || $dateObj->format('Y-m-d') !== $inputDate) {
    redirectTo('add-input-users?err=date');
}
$inputDate = $dateObj->format('Y-m-d');

// ── Collect & validate array inputs ─────────────────────────────────────────
$productIds   = $_POST['product_id']    ?? [];
$inputQtys    = $_POST['input_qty']     ?? [];
$inputRemarks = $_POST['input_remarks'] ?? [];

if (
    !is_array($productIds)   ||
    !is_array($inputQtys)    ||
    !is_array($inputRemarks) ||
    count($productIds) === 0 ||
    count($productIds) !== count($inputQtys) ||
    count($productIds) !== count($inputRemarks)
) {
    redirectTo('add-input-users?err=products_array');
}

$rows         = [];
$seenProducts = [];

foreach ($productIds as $i => $rawPid) {
    $pid = filter_var($rawPid, FILTER_VALIDATE_INT);
    $qty = filter_var($inputQtys[$i] ?? 0, FILTER_VALIDATE_INT);
    $rmk = htmlspecialchars(strip_tags(trim($inputRemarks[$i] ?? '')), ENT_QUOTES, 'UTF-8');

    if (!$pid || $pid <= 0) {
        redirectTo('add-input-users?err=product_id_row' . $i);
    }
    if (!$qty || $qty <= 0) {
        redirectTo('add-input-users?err=qty_row' . $i);
    }

    if (in_array($pid, $seenProducts, true)) {
        redirectTo('add-input-users?err=duplicate_product');
    }
    $seenProducts[] = $pid;

    $rows[] = [
        'product_id'    => $pid,
        'input_qty'     => $qty,
        'input_remarks' => $rmk,
    ];
}

// ── Check opening stock exists for this user ─────────────────────────────────
$stmtChkStock = $db_conn->prepare(
    "SELECT COUNT(*) AS cnt FROM stock WHERE user_type = ? AND user_id = ?"
);
// Both are strings — user_id in stock table stores the alphanumeric ID as string
$stmtChkStock->bind_param('ss', $toUserType, $toUserId);
$stmtChkStock->execute();
$cntStock = (int) $stmtChkStock->get_result()->fetch_assoc()['cnt'];
$stmtChkStock->close();

if ($cntStock === 0) {
    redirectTo('add-input-users?stocknotupdated');
}

// ── Duplicate-submission guard (tempid) ──────────────────────────────────────
$stmtChkTemp = $db_conn->prepare(
    "SELECT COUNT(*) AS cnt FROM input_stock_users WHERE tempid = ?"
);
$stmtChkTemp->bind_param('s', $tempId);
$stmtChkTemp->execute();
$cntTemp = (int) $stmtChkTemp->get_result()->fetch_assoc()['cnt'];
$stmtChkTemp->close();

if ($cntTemp > 0) {
    redirectTo('add-input-users?alreadyexists');
}

// ── Enable mysqli exceptions so prepare()/execute() failures throw instead of returning false ──
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ── Begin transaction ─────────────────────────────────────────────────────────
$db_conn->begin_transaction();

try {
    // Prepared statements — compiled once, executed per product row

    // Columns: tempid(s), usertype(s), userid(s), product_id(i), input_qty(i), input_date(s), remarks(s)
    // = 7 params → type string 'sssiiiss' was WRONG (8 chars). Correct = 'sssiiss' (7 chars)
    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    $stmtInsertInput = $db_conn->prepare(
        "INSERT INTO input_stock_users
             (tempid, usertype, userid, product_id, input_qty, input_date, remarks, still_maintain)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
    );

    foreach ($rows as $row) {
        $pid = $row['product_id'];
        $qty = $row['input_qty'];
        $rmk = $row['input_remarks'];

        // 1. Insert into input_stock_users
        // 7 params: s=tempId, s=toUserType, s=toUserId, i=pid, i=qty, s=inputDate, s=rmk
        $stmtInsertInput->bind_param(
            'sssiiss',
            $tempId, $toUserType, $toUserId, $pid, $qty, $inputDate, $rmk
        );
        $stmtInsertInput->execute();

        // 2/3/4. Credit stock + write ledger entry — via StockService so this
        // is scoped consistently (product_id/user_type/user_id, unassigned
        // warehouse row) and audited, instead of a hand-rolled UPDATE +
        // manual ledger insert that could drift apart from StockService's
        // own bookkeeping.
        $stockService->credit(
            $pid, $toUserType, $toUserId, $qty,
            'adjustment', $tempId, $createdBy, true
        );
    }

    $stmtInsertInput->close();

    $db_conn->commit();

} catch (Throwable $e) {
    $db_conn->rollback();
    // Log the FULL error message so you can see exactly what failed
    error_log('input-action-users.php transaction failed: ' . $e->getMessage());
    // Temporarily expose error in redirect query string for debugging
    // REMOVE the &msg= part once issue is resolved
    redirectTo('add-input-users?saveerror&msg=' . urlencode($e->getMessage()));
}

redirectTo('manage-input-users?addesuccess');