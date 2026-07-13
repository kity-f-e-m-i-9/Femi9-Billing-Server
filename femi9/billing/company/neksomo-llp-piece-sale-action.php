<?php
declare(strict_types=1);

include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

function redirectWithMessage(string $location, string $message = ''): void {
    $url = $location . ($message ? '?' . $message : '');
    header("Location: $url");
    exit();
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    redirectWithMessage('neksomo-llp-piece-sale.php', 'error');
}

// ── ADD ──────────────────────────────────────────────────────────────────
if (isset($_POST['add-record'])) {
    $product_id     = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
    $effective_date = $_POST['effective_date'] ?? '';
    $rate_per_piece = filter_var($_POST['rate_per_piece'] ?? 0, FILTER_VALIDATE_FLOAT);

    if (!$product_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective_date) || $rate_per_piece === false || $rate_per_piece < 0) {
        redirectWithMessage('neksomo-llp-piece-sale.php', 'error');
    }

    $created_by = $_SESSION['LOGIN_USER'] ?? 'system';

    $stmt = $db_conn->prepare(
        "INSERT INTO neksomo_llp_piece_rates (product_id, effective_date, rate_per_piece, created_by)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('isds', $product_id, $effective_date, $rate_per_piece, $created_by);
    $ok = $stmt->execute();
    $dupe = !$ok && $db_conn->errno === 1062; // uniq_product_date — a rate already exists for this product+date
    $stmt->close();

    if ($dupe) {
        redirectWithMessage('neksomo-llp-piece-sale.php', 'duplicate');
    }
    redirectWithMessage('neksomo-llp-piece-sale.php', $ok ? 'addesuccess' : 'error');
}

// ── UPDATE ───────────────────────────────────────────────────────────────
if (isset($_POST['update-record'])) {
    $update_id      = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    $product_id     = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
    $effective_date = $_POST['effective_date'] ?? '';
    $rate_per_piece = filter_var($_POST['rate_per_piece'] ?? 0, FILTER_VALIDATE_FLOAT);

    if (!$update_id || !$product_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective_date) || $rate_per_piece === false || $rate_per_piece < 0) {
        redirectWithMessage('neksomo-llp-piece-sale-manage.php', 'error');
    }

    $stmt = $db_conn->prepare(
        "UPDATE neksomo_llp_piece_rates SET product_id=?, effective_date=?, rate_per_piece=? WHERE id=?"
    );
    $stmt->bind_param('isdi', $product_id, $effective_date, $rate_per_piece, $update_id);
    $ok = $stmt->execute();
    $dupe = !$ok && $db_conn->errno === 1062;
    $stmt->close();

    if ($dupe) {
        redirectWithMessage('neksomo-llp-piece-sale-manage.php', 'duplicate');
    }
    redirectWithMessage('neksomo-llp-piece-sale-manage.php', $ok ? 'updatedSuccess' : 'error');
}

redirectWithMessage('neksomo-llp-piece-sale.php');
