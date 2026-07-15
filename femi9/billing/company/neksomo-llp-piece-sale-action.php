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

// ── ADD (batch — one or more products sharing one effective date) ─────────
if (isset($_POST['add-record'])) {
    $effective_date = $_POST['effective_date'] ?? '';
    $raw_pids       = $_POST['product_id'] ?? [];
    $raw_rates      = $_POST['rate_per_piece'] ?? [];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective_date) || empty($raw_pids)) {
        redirectWithMessage('neksomo-llp-piece-sale.php', 'error');
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
        redirectWithMessage('neksomo-llp-piece-sale.php', 'error');
    }

    $created_by = $_SESSION['LOGIN_USER'] ?? 'system';
    $stmt = $db_conn->prepare(
        "INSERT INTO neksomo_llp_piece_rates (product_id, effective_date, rate_per_piece, created_by)
         VALUES (?, ?, ?, ?)"
    );

    $added = 0; $skipped = 0;
    foreach ($rows as $row) {
        $stmt->bind_param('isds', $row['product_id'], $effective_date, $row['rate'], $created_by);
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
        redirectWithMessage('neksomo-llp-piece-sale.php', 'error');
    }
    redirectWithMessage('neksomo-llp-piece-sale.php', "addesuccess&count=$added&skipped=$skipped");
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
    try {
        $stmt->execute();
        $stmt->close();
    } catch (\mysqli_sql_exception $e) {
        $stmt->close();
        if ($e->getCode() === 1062) {
            redirectWithMessage('neksomo-llp-piece-sale-manage.php', 'duplicate');
        }
        throw $e;
    }
    redirectWithMessage('neksomo-llp-piece-sale-manage.php', 'updatedSuccess');
}

redirectWithMessage('neksomo-llp-piece-sale.php');
