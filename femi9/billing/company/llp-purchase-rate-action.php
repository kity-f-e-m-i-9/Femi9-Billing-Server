<?php
declare(strict_types=1);

include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['users', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

function redirectWithMessage(string $location, string $message = ''): void {
    $url = $location . ($message ? '?' . $message : '');
    header("Location: $url");
    exit();
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    redirectWithMessage('llp-purchase-rate.php', 'error');
}

// ── ADD (batch — one or more products sharing one effective date) ─────────
if (isset($_POST['add-record'])) {
    $effective_date = $_POST['effective_date'] ?? '';
    $raw_pids       = $_POST['product_id'] ?? [];
    $raw_rates      = $_POST['rate_per_piece'] ?? [];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective_date) || empty($raw_pids)) {
        redirectWithMessage('llp-purchase-rate.php', 'error');
    }

    // Build and dedupe rows
    $rows = []; $seen = [];
    foreach ($raw_pids as $i => $rpid) {
        $product_id     = filter_var($rpid, FILTER_VALIDATE_INT);
        $rate_per_piece = filter_var($raw_rates[$i] ?? null, FILTER_VALIDATE_FLOAT);
        if (!$product_id || $rate_per_piece === false || $rate_per_piece < 0) continue;
        if (isset($seen[$product_id])) continue;
        $seen[$product_id] = true;
        $rows[] = ['product_id' => $product_id, 'rate' => $rate_per_piece];
    }

    if (empty($rows)) {
        redirectWithMessage('llp-purchase-rate.php', 'error');
    }

    // GST is never trusted from the client — snapshot each product's own
    // gst/gst_type at the moment the rate is entered, same convention as
    // neksomo_llp_piece_rates.
    $pids = array_column($rows, 'product_id');
    $placeholders = implode(',', array_fill(0, count($pids), '?'));
    $gstStmt = $db_conn->prepare("SELECT id, gst, gst_type FROM products WHERE id IN ($placeholders)");
    $gstStmt->bind_param(str_repeat('i', count($pids)), ...$pids);
    $gstStmt->execute();
    $gstByProduct = [];
    foreach ($gstStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $gstByProduct[(int)$r['id']] = ['rate' => (float)$r['gst'], 'type' => $r['gst_type'] === 'inclusive' ? 'inclusive' : 'exclusive'];
    }
    $gstStmt->close();

    $created_by = $_SESSION['LOGIN_USER'] ?? 'system';
    $stmt = $db_conn->prepare(
        "INSERT INTO femi9_llp_sale_rates (product_id, effective_date, rate_per_piece, gst_rate, gst_type, created_by)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    $added = 0; $skipped = 0;
    foreach ($rows as $row) {
        $gst_rate = $gstByProduct[$row['product_id']]['rate'] ?? 0.0;
        $gst_type = $gstByProduct[$row['product_id']]['type'] ?? 'exclusive';
        $stmt->bind_param('isddss', $row['product_id'], $effective_date, $row['rate'], $gst_rate, $gst_type, $created_by);
        try {
            $stmt->execute();
            $added++;
        } catch (\mysqli_sql_exception $e) {
            // uniq_product_date — a rate already exists for this product+date; skip, don't abort the batch
            if ($e->getCode() !== 1062) {
                $stmt->close();
                throw $e;
            }
            $skipped++;
        }
    }
    $stmt->close();

    if ($added === 0) {
        redirectWithMessage('llp-purchase-rate.php', 'error');
    }
    redirectWithMessage('llp-purchase-rate.php', "addesuccess&count=$added&skipped=$skipped");
}

// ── UPDATE ───────────────────────────────────────────────────────────────
if (isset($_POST['update-record'])) {
    $update_id      = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    $product_id     = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
    $effective_date = $_POST['effective_date'] ?? '';
    $rate_per_piece = filter_var($_POST['rate_per_piece'] ?? 0, FILTER_VALIDATE_FLOAT);

    if (!$update_id || !$product_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective_date) || $rate_per_piece === false || $rate_per_piece < 0) {
        redirectWithMessage('llp-purchase-rate-manage.php', 'error');
    }

    // Re-snapshot GST from the product's current setting whenever the rate
    // row is written, same as the insert path above.
    $gstLookup = $db_conn->prepare("SELECT gst, gst_type FROM products WHERE id = ?");
    $gstLookup->bind_param('i', $product_id);
    $gstLookup->execute();
    $gstRow = $gstLookup->get_result()->fetch_assoc();
    $gstLookup->close();
    $gst_rate = (float) ($gstRow['gst'] ?? 0);
    $gst_type = ($gstRow['gst_type'] ?? '') === 'inclusive' ? 'inclusive' : 'exclusive';

    $stmt = $db_conn->prepare(
        "UPDATE femi9_llp_sale_rates SET product_id=?, effective_date=?, rate_per_piece=?, gst_rate=?, gst_type=? WHERE id=?"
    );
    $stmt->bind_param('isddsi', $product_id, $effective_date, $rate_per_piece, $gst_rate, $gst_type, $update_id);
    try {
        $stmt->execute();
        $stmt->close();
    } catch (\mysqli_sql_exception $e) {
        $stmt->close();
        if ($e->getCode() === 1062) {
            redirectWithMessage('llp-purchase-rate-manage.php', 'duplicate');
        }
        throw $e;
    }
    redirectWithMessage('llp-purchase-rate-manage.php', 'updatedSuccess');
}

redirectWithMessage('llp-purchase-rate.php');
