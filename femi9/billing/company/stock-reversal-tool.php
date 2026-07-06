<?php
/**
 * Stock Reversal Tool
 * Femi9 Billing Application
 *
 * Finds invoices with NO receipts (unpaid) between Jan 1 2024 – Feb 10 2026
 * and reverses the stock changes that were applied at submission time.
 *
 * Modes:
 *   dry_run  — Show what WOULD be reversed, no DB changes
 *   execute  — Actually reverse the stock
 *   rollback — Undo the last execute run (using audit log stored in session)
 */

declare(strict_types=1);

include("checksession.php");
include("config.php");
require_once("include/StockService.php");

const DATE_FROM = '2024-01-01';
const DATE_TO   = '2026-02-10';

$mode    = $_POST['mode']    ?? $_GET['mode']    ?? '';
$confirm = $_POST['confirm'] ?? '';
$results = [];
$summary = ['invoices' => 0, 'items' => 0, 'skipped' => 0, 'errors' => []];
$executed_log = [];

function getUnpaidInvoices(mysqli $db): array
{
    $invoices = [];

    $sql = "
        SELECT
            ui.inv_id,
            ui.inv_number,
            ui.date,
            ui.total,
            ui.from_user_type  AS seller_type,
            ui.from_user_id    AS seller_id,
            ui.to_user_type    AS buyer_type,
            ui.to_user_id      AS buyer_id,
            'user_invoice'     AS source_table
        FROM user_invoice ui
        LEFT JOIN receipt r ON r.inv_id = ui.inv_id
        WHERE ui.date BETWEEN '" . DATE_FROM . "' AND '" . DATE_TO . "'
          AND ui.status != 'draft'
          AND r.id IS NULL
        ORDER BY ui.date ASC
    ";
    $res = mysqli_query($db, $sql);
    while ($row = mysqli_fetch_assoc($res)) { $invoices[] = $row; }

    $sql2 = "
        SELECT
            i.inv_id,
            i.inv_number,
            i.date,
            i.total,
            i.user_type        AS seller_type,
            i.user_id          AS seller_id,
            'customer'         AS buyer_type,
            i.customer_id      AS buyer_id,
            'invoice'          AS source_table
        FROM invoice i
        LEFT JOIN receipt r ON r.inv_id = i.inv_id
        WHERE i.date BETWEEN '" . DATE_FROM . "' AND '" . DATE_TO . "'
          AND r.id IS NULL
        ORDER BY i.date ASC
    ";
    $res2 = mysqli_query($db, $sql2);
    while ($row = mysqli_fetch_assoc($res2)) { $invoices[] = $row; }

    return $invoices;
}

function getInvoiceItems(mysqli $db, string $inv_id, string $source_table): array
{
    $items_table = ($source_table === 'user_invoice') ? 'user_invoice_items' : 'invoice_items';
    $inv_id_esc  = mysqli_real_escape_string($db, $inv_id);
    $res = mysqli_query($db, "SELECT * FROM {$items_table} WHERE inv_id = '{$inv_id_esc}'");
    if (!$res) return [];
    $items = [];
    while ($row = mysqli_fetch_assoc($res)) { $items[] = $row; }
    return $items;
}

function reverseStockForItem(mysqli $db, array $item, array $invoice, bool $dry_run): array
{
    $pr_id = mysqli_real_escape_string($db, (string)($item['pr_id'] ?? $item['product_id'] ?? ''));
    $qty   = (float)($item['qty'] ?? 0);

    if (empty($pr_id) || $qty <= 0) {
        return ['success' => false, 'message' => "Skipped: invalid product/qty", 'queries' => []];
    }

    $seller_type = mysqli_real_escape_string($db, $invoice['seller_type']);
    $seller_id   = mysqli_real_escape_string($db, $invoice['seller_id']);
    $buyer_type  = mysqli_real_escape_string($db, $invoice['buyer_type']);
    $buyer_id    = mysqli_real_escape_string($db, $invoice['buyer_id']);

    $queries = [];

    $q1 = "UPDATE stock SET sales_qty = GREATEST(0, sales_qty - {$qty}), closing_qty = closing_qty + {$qty} WHERE product_id = '{$pr_id}' AND user_type = '{$seller_type}' AND user_id = '{$seller_id}'";
    $queries[] = ['label' => "Restore seller ({$seller_type} #{$seller_id})", 'sql' => $q1];

    if (!in_array($buyer_type, ['customer'], true)) {
        $q2 = "UPDATE stock SET input_qty = GREATEST(0, input_qty - {$qty}), closing_qty = GREATEST(0, closing_qty - {$qty}) WHERE product_id = '{$pr_id}' AND user_type = '{$buyer_type}' AND user_id = '{$buyer_id}'";
        $queries[] = ['label' => "Remove buyer ({$buyer_type} #{$buyer_id})", 'sql' => $q2];
    }

    if (!$dry_run) {
        foreach ($queries as &$q) {
            $ok = mysqli_query($db, $q['sql']);
            $q['ok']  = $ok;
            $q['err'] = $ok ? null : mysqli_error($db);
        }
    }

    return ['success' => true, 'message' => "OK", 'queries' => $queries];
}

function buildRollbackQuery(string $sql): string
{
    $sql = preg_replace('/sales_qty\s*=\s*GREATEST\(0,\s*sales_qty\s*-\s*(\S+)\)/', 'sales_qty = sales_qty + $1', $sql);
    $sql = preg_replace('/closing_qty\s*=\s*closing_qty\s*\+\s*(\S+)/',              'closing_qty = closing_qty - $1', $sql);
    $sql = preg_replace('/input_qty\s*=\s*GREATEST\(0,\s*input_qty\s*-\s*(\S+)\)/',  'input_qty = input_qty + $1', $sql);
    $sql = preg_replace('/closing_qty\s*=\s*GREATEST\(0,\s*closing_qty\s*-\s*(\S+)\)/', 'closing_qty = closing_qty + $1', $sql);
    return $sql;
}

if (in_array($mode, ['dry_run', 'execute'], true)) {
    $dry_run      = ($mode === 'dry_run');
    $invoices     = getUnpaidInvoices($db_conn);
    $stockService = new StockService($db_conn);
    $summary['invoices'] = count($invoices);

    foreach ($invoices as $invoice) {
        $inv_id      = $invoice['inv_id'];
        $ref_type    = $invoice['source_table']; // 'user_invoice' or 'invoice'
        $items       = getInvoiceItems($db_conn, $inv_id, $ref_type);
        $inv_row     = ['invoice' => $invoice, 'items' => [], 'item_count' => count($items)];
        $summary['items'] += count($items);

        if ($dry_run) {
            // Preview only — show what StockService would do without writing anything
            foreach ($items as $item) {
                $res = reverseStockForItem($db_conn, $item, $invoice, true);
                $inv_row['items'][] = ['item' => $item, 'result' => $res];
                if (!$res['success']) $summary['skipped']++;
            }
        } else {
            // Execute — use StockService::reverseAll() which is idempotent via ledger check
            // If this invoice was already reversed, reverseAll() skips it (returns 0).
            try {
                $reversed = $stockService->reverseAll(
                    $ref_type, $inv_id,
                    $_SESSION['LOGIN_USER'] ?? 'reversal-tool'
                );
                foreach ($items as $item) {
                    $inv_row['items'][] = [
                        'item'   => $item,
                        'result' => [
                            'success' => true,
                            'message' => $reversed > 0 ? 'reversed via ledger' : 'already reversed (skipped)',
                            'queries' => [['label' => 'StockService::reverseAll()', 'sql' => "ref_type={$ref_type} ref_id={$inv_id} reversed={$reversed}", 'ok' => true]],
                        ],
                    ];
                }
                // Store full invoice identity so rollback can re-apply stock
                if ($reversed > 0) {
                    $executed_log[] = [
                        'inv_id'      => $inv_id,
                        'ref_type'    => $ref_type,
                        'seller_type' => $invoice['seller_type'],
                        'seller_id'   => $invoice['seller_id'],
                        'buyer_type'  => $invoice['buyer_type'],
                        'buyer_id'    => $invoice['buyer_id'],
                        'reversed'    => $reversed,
                    ];
                }
            } catch (\Throwable $e) {
                foreach ($items as $item) {
                    $inv_row['items'][] = [
                        'item'   => $item,
                        'result' => ['success' => false, 'message' => $e->getMessage(), 'queries' => []],
                    ];
                    $summary['skipped']++;
                }
                $summary['errors'][] = "Invoice {$inv_id}: " . $e->getMessage();
            }
        }

        $results[] = $inv_row;
    }

    if (!$dry_run && !empty($executed_log)) {
        $_SESSION['stock_reversal_log'] = $executed_log;
        $_SESSION['stock_reversal_ts']  = date('Y-m-d H:i:s');
    }

} elseif ($mode === 'rollback' && $confirm === 'yes') {
    $log = $_SESSION['stock_reversal_log'] ?? [];
    if (empty($log)) {
        $summary['errors'][] = "No execute log found in session. Cannot rollback.";
    } else {
        $stockService = new StockService($db_conn);
        $rbUser       = $_SESSION['LOGIN_USER'] ?? 'reversal-rollback';

        foreach ($log as $entry) {
            $inv_id      = $entry['inv_id'];
            $ref_type    = $entry['ref_type'];
            $seller_type = $entry['seller_type'];
            $seller_id   = $entry['seller_id'];
            $buyer_type  = $entry['buyer_type'];
            $buyer_id    = $entry['buyer_id'];

            // Re-fetch items for this invoice
            $items = getInvoiceItems($db_conn, $inv_id, $ref_type);
            $ok    = true;
            $err   = null;

            // Re-apply stock: deduct from seller + credit to buyer
            // (after reverseAll, net=0 so hasLedgerEntry=false, re-apply proceeds)
            try {
                foreach ($items as $item) {
                    $pr_id = (int)($item['pr_id'] ?? $item['product_id'] ?? 0);
                    $qty   = (int)($item['qty'] ?? 0);
                    if (!$pr_id || $qty <= 0) continue;

                    $stockService->deductAndCredit(
                        $pr_id,
                        $seller_type, $seller_id,
                        $buyer_type,  $buyer_id,
                        $qty,
                        $ref_type, $inv_id,
                        $rbUser
                    );
                }
            } catch (\Throwable $e) {
                $ok  = false;
                $err = $e->getMessage();
                $summary['errors'][] = "Rollback failed for {$inv_id}: $err";
            }

            $results[] = [
                'inv_id'  => $inv_id,
                'sql'     => "Re-applied " . count($items) . " items via StockService::deductAndCredit()",
                'success' => $ok,
                'error'   => $err,
            ];
        }

        unset($_SESSION['stock_reversal_log'], $_SESSION['stock_reversal_ts']);
        $summary['invoices'] = count(array_unique(array_column($results, 'inv_id')));
        $summary['items']    = count($results);
    }
}

$has_rollback_log = !empty($_SESSION['stock_reversal_log']);
$rollback_ts      = $_SESSION['stock_reversal_ts'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock Reversal Tool — Femi9 Billing</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root {
    --bg:        #f7f6f3;
    --bg2:       #efede9;
    --surface:   #ffffff;
    --surface2:  #faf9f8;
    --border:    #e5e2dc;
    --border2:   #d4d0c9;

    --ink:       #1e1c19;
    --ink-mid:   #5c5750;
    --ink-dim:   #9c9790;

    --blue:      #1a56db;
    --blue-bg:   #eff4ff;
    --blue-br:   #c3d4f8;

    --amber:     #92400e;
    --amber-bg:  #fffbeb;
    --amber-br:  #fcd34d;

    --red:       #991b1b;
    --red-bg:    #fef2f2;
    --red-br:    #fecaca;

    --green:     #166534;
    --green-bg:  #f0fdf4;
    --green-br:  #bbf7d0;

    --violet:    #5b21b6;
    --violet-bg: #f5f3ff;
    --violet-br: #ddd6fe;

    --serif: 'Lora', Georgia, serif;
    --sans:  'DM Sans', sans-serif;
    --mono:  'DM Mono', 'Courier New', monospace;

    --r: 7px;
    --r-lg: 12px;
    --sh: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --sh-md: 0 4px 16px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.04);
}

html { font-size: 14px; }
body {
    font-family: var(--sans);
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
}

/* ── NAV ────────────────────────────────────────────────────────────────── */
.nav {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    height: 54px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 32px;
    position: sticky;
    top: 0;
    z-index: 40;
    box-shadow: var(--sh);
}
.nav-left { display:flex; align-items:center; gap:10px; }
.nav-logo {
    width:28px; height:28px; background:var(--blue); border-radius:6px;
    display:flex; align-items:center; justify-content:center;
    font-size:11px; font-weight:700; color:#fff; letter-spacing:0;
}
.nav-brand { font-weight:600; font-size:0.85rem; color:var(--ink); letter-spacing:-0.01em; }
.nav-divider { color:var(--border2); font-size:1rem; }
.nav-page { font-size:0.82rem; color:var(--ink-mid); font-weight:400; }
.nav-right { display:flex; align-items:center; gap:10px; }
.date-badge {
    background:var(--amber-bg);
    border:1px solid var(--amber-br);
    border-radius:100px;
    padding:3px 11px;
    font-family:var(--mono);
    font-size:0.7rem;
    font-weight:500;
    color:var(--amber);
    letter-spacing:0;
}

/* ── PAGE ───────────────────────────────────────────────────────────────── */
.page { max-width:1080px; margin:0 auto; padding:0 32px 80px; }

/* ── HERO ───────────────────────────────────────────────────────────────── */
.hero { padding:38px 0 30px; border-bottom:1px solid var(--border); margin-bottom:28px; }
.hero h1 {
    font-family:var(--serif);
    font-size:2.1rem;
    font-weight:700;
    color:var(--ink);
    letter-spacing:-0.025em;
    line-height:1.15;
    margin-bottom:8px;
}
.hero p {
    font-size:0.875rem;
    color:var(--ink-mid);
    font-weight:300;
    max-width:520px;
    line-height:1.75;
}

/* ── CARD ───────────────────────────────────────────────────────────────── */
.card {
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--r-lg);
    box-shadow:var(--sh);
    margin-bottom:20px;
    overflow:hidden;
}
.card-head {
    display:flex; align-items:center; justify-content:space-between;
    padding:14px 20px;
    background:var(--surface2);
    border-bottom:1px solid var(--border);
}
.card-label {
    font-size:0.68rem;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:0.1em;
    color:var(--ink-dim);
}
.card-body { padding:20px; }

/* ── BUTTONS ────────────────────────────────────────────────────────────── */
.btn-row { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
.btn {
    display:inline-flex; align-items:center; gap:7px;
    padding:9px 17px;
    border-radius:var(--r);
    font-family:var(--sans);
    font-size:0.825rem;
    font-weight:500;
    cursor:pointer;
    border:1px solid transparent;
    text-decoration:none;
    transition:all 0.12s ease;
    white-space:nowrap;
    letter-spacing:-0.01em;
}
.btn:active{transform:scale(0.975)}
.btn svg{flex-shrink:0}

.btn-dry    { background:var(--amber-bg); border-color:var(--amber-br); color:var(--amber); }
.btn-dry:hover { background:#fef3c7; border-color:#f59e0b; }

.btn-exec   { background:var(--red-bg); border-color:var(--red-br); color:var(--red); }
.btn-exec:hover { background:#fee2e2; border-color:#f87171; }

.btn-rb     { background:var(--violet-bg); border-color:var(--violet-br); color:var(--violet); }
.btn-rb:hover { background:#ede9fe; border-color:#a78bfa; }

.btn-ghost  { background:transparent; border-color:var(--border2); color:var(--ink-mid); }
.btn-ghost:hover { background:var(--bg2); border-color:var(--ink-dim); color:var(--ink); }

.vr { width:1px; height:28px; background:var(--border); flex-shrink:0; }

/* ── INLINE NOTICES ─────────────────────────────────────────────────────── */
.notice {
    display:inline-flex; align-items:center; gap:8px;
    padding:7px 13px; border-radius:var(--r);
    font-size:0.77rem; font-weight:500;
}
.n-violet { background:var(--violet-bg); border:1px solid var(--violet-br); color:var(--violet); }
.n-amber  { background:var(--amber-bg);  border:1px solid var(--amber-br);  color:var(--amber); }
.notice .ts { font-family:var(--mono); font-size:0.68rem; opacity:0.7; }

/* ── ALERT ──────────────────────────────────────────────────────────────── */
.alert {
    display:flex; gap:11px;
    padding:13px 17px;
    border-radius:var(--r);
    font-size:0.825rem;
    line-height:1.65;
    margin-bottom:18px;
}
.a-amber { background:var(--amber-bg); border:1px solid var(--amber-br); color:var(--amber); }
.a-red   { background:var(--red-bg);   border:1px solid var(--red-br);   color:var(--red); }
.alert strong { font-weight:600; display:block; margin-bottom:1px; }
.alert-ico { flex-shrink:0; margin-top:2px; }

/* ── MODE BADGE ─────────────────────────────────────────────────────────── */
.mode-badge {
    display:inline-flex; align-items:center; gap:8px;
    padding:6px 14px; border-radius:100px;
    font-size:0.775rem; font-weight:600;
    margin-bottom:20px;
    letter-spacing:-0.01em;
}
.mb-dry     { background:var(--amber-bg);  border:1px solid var(--amber-br);  color:var(--amber); }
.mb-execute { background:var(--red-bg);    border:1px solid var(--red-br);    color:var(--red); }
.mb-rollback{ background:var(--violet-bg); border:1px solid var(--violet-br); color:var(--violet); }
.dot { width:6px; height:6px; border-radius:50%; background:currentColor; animation:pulse 1.8s infinite; flex-shrink:0; }
@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.2}}

/* ── STATS ──────────────────────────────────────────────────────────────── */
.stats {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(155px,1fr));
    gap:12px;
    margin-bottom:22px;
}
.stat {
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--r-lg);
    padding:18px 20px;
    box-shadow:var(--sh);
}
.stat .sl { font-size:0.67rem; font-weight:600; text-transform:uppercase; letter-spacing:0.1em; color:var(--ink-dim); margin-bottom:9px; }
.stat .sv {
    font-family:var(--serif);
    font-size:2.2rem;
    font-weight:700;
    line-height:1;
    letter-spacing:-0.03em;
}
.sv-blue   { color:var(--blue); }
.sv-amber  { color:var(--amber); }
.sv-red    { color:var(--red); }
.sv-green  { color:var(--green); }
.sv-violet { color:var(--violet); }
.sv-text   { font-family:var(--sans)!important; font-size:1rem!important; font-weight:600!important; padding-top:6px; }

/* ── INVOICE CARD ───────────────────────────────────────────────────────── */
.inv {
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--r-lg);
    margin-bottom:10px;
    overflow:hidden;
    box-shadow:var(--sh);
    transition:box-shadow 0.15s;
}
.inv:hover { box-shadow:var(--sh-md); }
.inv-head {
    display:flex; align-items:center; justify-content:space-between;
    padding:13px 18px;
    background:var(--surface2);
    border-bottom:1px solid var(--border);
    cursor:pointer;
    user-select:none;
    gap:14px;
}
.inv-head:hover { background:#f5f3f0; }
.inv-meta { display:flex; flex-wrap:wrap; align-items:center; gap:9px; }
.inv-num  { font-weight:600; font-size:0.87rem; color:var(--ink); letter-spacing:-0.01em; }
.inv-dt   { font-family:var(--mono); font-size:0.73rem; color:var(--ink-dim); }
.inv-amt  {
    font-family:var(--mono); font-size:0.76rem; font-weight:500;
    color:var(--blue);
    background:var(--blue-bg); border:1px solid var(--blue-br);
    padding:2px 8px; border-radius:4px;
}
.route { display:flex; align-items:center; gap:5px; font-size:0.71rem; color:var(--ink-dim); }
.utype { background:var(--bg2); border:1px solid var(--border); border-radius:4px; padding:1px 7px; font-size:0.69rem; font-weight:500; color:var(--ink-mid); }
.inv-toggle { display:flex; align-items:center; gap:5px; font-size:0.72rem; color:var(--ink-dim); font-weight:500; flex-shrink:0; }
.chev { transition:transform 0.2s ease; display:inline-block; font-style:normal; font-size:0.6rem; }

/* ── TABLE ──────────────────────────────────────────────────────────────── */
.tbl-wrap { overflow-x:auto; }
.tbl { width:100%; border-collapse:collapse; font-size:0.79rem; }
.tbl th {
    padding:9px 16px; text-align:left;
    font-size:0.64rem; font-weight:600; text-transform:uppercase; letter-spacing:0.09em;
    color:var(--ink-dim); background:var(--bg); border-bottom:1px solid var(--border);
    white-space:nowrap;
}
.tbl td {
    padding:11px 16px; border-bottom:1px solid var(--border);
    vertical-align:top; color:var(--ink);
}
.tbl tr:last-child td { border-bottom:none; }
.tbl tbody tr:hover td { background:var(--surface2); }
.pid { font-family:var(--mono); color:var(--blue); font-size:0.79rem; font-weight:500; }
.qty { font-family:var(--mono); font-weight:600; color:var(--ink); }

/* ── QUERY LINES ────────────────────────────────────────────────────────── */
.qs { display:flex; flex-direction:column; gap:5px; }
.ql {
    border-radius:4px; padding:6px 10px;
    font-family:var(--mono); font-size:0.67rem; line-height:1.55;
    word-break:break-all;
    border-left:2px solid var(--border2);
    background:var(--bg); color:var(--ink-mid);
}
.ql.ok   { border-color:var(--green); background:var(--green-bg); color:#14532d; }
.ql.fail { border-color:var(--red);   background:var(--red-bg);   color:var(--red); }
.qlb { display:block; font-size:0.59rem; text-transform:uppercase; letter-spacing:0.08em; opacity:0.5; margin-bottom:2px; font-weight:600; }

/* ── CHIPS ──────────────────────────────────────────────────────────────── */
.chip {
    display:inline-flex; align-items:center; gap:4px;
    padding:2px 8px; border-radius:4px;
    font-size:0.68rem; font-weight:600; white-space:nowrap;
}
.c-ok     { background:var(--green-bg);  color:var(--green);  border:1px solid var(--green-br); }
.c-skip   { background:var(--amber-bg);  color:var(--amber);  border:1px solid var(--amber-br); }
.c-fail   { background:var(--red-bg);    color:var(--red);    border:1px solid var(--red-br); }
.c-sim    { background:var(--blue-bg);   color:var(--blue);   border:1px solid var(--blue-br); }

/* ── EMPTY ──────────────────────────────────────────────────────────────── */
.empty { text-align:center; padding:70px 24px; color:var(--ink-dim); }
.empty .ei { font-size:2.8rem; display:block; margin-bottom:14px; opacity:0.25; }
.empty .et { font-family:var(--serif); font-size:1.25rem; color:var(--ink-mid); margin-bottom:8px; font-weight:600; }
.empty .es { font-size:0.83rem; line-height:1.7; max-width:380px; margin:0 auto; font-weight:300; }

/* ── MODAL ──────────────────────────────────────────────────────────────── */
.overlay {
    display:none; position:fixed; inset:0;
    background:rgba(30,28,25,0.4);
    backdrop-filter:blur(3px);
    z-index:200; align-items:center; justify-content:center;
}
.overlay.open { display:flex; }
.modal {
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--r-lg);
    padding:32px; max-width:430px; width:90%;
    box-shadow:0 20px 60px rgba(0,0,0,0.15);
    animation:mi 0.2s ease both;
}
@keyframes mi{from{opacity:0;transform:translateY(8px) scale(0.97)}to{opacity:1;transform:none}}
.modal-ico { font-size:2rem; margin-bottom:14px; display:block; }
.modal h3 {
    font-family:var(--serif); font-size:1.3rem; font-weight:700;
    color:var(--ink); margin-bottom:10px; letter-spacing:-0.02em;
}
.modal p { font-size:0.83rem; color:var(--ink-mid); margin-bottom:24px; line-height:1.75; font-weight:300; }
.modal p strong { font-weight:600; color:var(--ink); }
.modal-actions { display:flex; gap:10px; justify-content:flex-end; }

/* ── ANIM ───────────────────────────────────────────────────────────────── */
.fi { animation:fi 0.3s ease both; }
.sg>* { animation:fi 0.3s ease both; }
.sg>*:nth-child(1){animation-delay:.04s} .sg>*:nth-child(2){animation-delay:.08s}
.sg>*:nth-child(3){animation-delay:.12s} .sg>*:nth-child(4){animation-delay:.16s}
.sg>*:nth-child(n+5){animation-delay:.2s}
@keyframes fi{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:none}}

.hidden { display:none!important; }
</style>
</head>
<body>

<!-- ── NAV ──────────────────────────────────────────────────────────────── -->
<nav class="nav">
    <div class="nav-left">
        <div class="nav-logo">F9</div>
        <span class="nav-brand">Femi9 Billing</span>
        <span class="nav-divider">/</span>
        <span class="nav-page">Stock Reversal Tool</span>
    </div>
    <div class="nav-right">
        <span class="date-badge">📅 <?= DATE_FROM ?> → <?= DATE_TO ?></span>
        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-ghost" style="padding:6px 12px;font-size:0.75rem">
            ↺ Reset
        </a>
    </div>
</nav>

<div class="page">

    <!-- ── HERO ─────────────────────────────────────────────────────────── -->
    <div class="hero fi">
        <h1>Stock Reversal</h1>
        <p>Find unpaid invoices with no receipts and reverse the stock movements that were incorrectly applied at submission time.</p>
    </div>

    <!-- ── CONTROLS ─────────────────────────────────────────────────────── -->
    <div class="card fi">
        <div class="card-head">
            <span class="card-label">Operations</span>
        </div>
        <div class="card-body">

            <?php if ($mode === 'execute'): ?>
            <div class="alert a-amber" style="margin-bottom:18px">
                <span class="alert-ico">⚠</span>
                <div>
                    <strong>Execute mode — database changes have been applied.</strong>
                    A rollback snapshot was saved to your session. Use the Rollback button to undo if needed. The snapshot expires when you close this browser tab.
                </div>
            </div>
            <?php endif; ?>

            <div class="btn-row">
                <form method="post" style="display:contents">
                    <input type="hidden" name="mode" value="dry_run">
                    <button type="submit" class="btn btn-dry">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        Dry Run
                    </button>
                </form>

                <button type="button" class="btn btn-exec" onclick="openM('execute')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    Execute
                </button>

                <div class="vr"></div>

                <?php if ($has_rollback_log): ?>
                    <button type="button" class="btn btn-rb" onclick="openM('rollback')">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                        Rollback
                    </button>
                    <span class="notice n-violet">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Snapshot available &nbsp;<span class="ts"><?= htmlspecialchars($rollback_ts ?? '') ?></span>
                    </span>
                <?php else: ?>
                    <button class="btn btn-rb" disabled style="opacity:0.35;cursor:not-allowed">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                        Rollback
                    </button>
                    <span style="font-size:0.75rem;color:var(--ink-dim)">No snapshot — run Execute first</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── RESULTS ───────────────────────────────────────────────────────── -->
    <?php if ($mode): ?>

    <?php
    $bm = [
        'dry_run'  => ['cls'=>'mb-dry',     'label'=>'Dry Run — No changes made to the database'],
        'execute'  => ['cls'=>'mb-execute', 'label'=>'Execute — Stock changes have been reversed'],
        'rollback' => ['cls'=>'mb-rollback','label'=>'Rollback — Reversal has been undone'],
    ];
    $b = $bm[$mode] ?? ['cls'=>'mb-dry','label'=>$mode];
    ?>
    <div class="mode-badge <?= $b['cls'] ?> fi">
        <span class="dot"></span>
        <?= htmlspecialchars($b['label']) ?>
    </div>

    <?php foreach ($summary['errors'] as $err): ?>
    <div class="alert a-red fi">
        <span class="alert-ico">✕</span>
        <div><strong>Error</strong><?= htmlspecialchars($err) ?></div>
    </div>
    <?php endforeach; ?>

    <?php if ($mode !== 'rollback'): ?>

        <div class="stats sg">
            <div class="stat">
                <div class="sl">Unpaid Invoices</div>
                <div class="sv sv-blue"><?= $summary['invoices'] ?></div>
            </div>
            <div class="stat">
                <div class="sl">Line Items</div>
                <div class="sv sv-amber"><?= $summary['items'] ?></div>
            </div>
            <div class="stat">
                <div class="sl">Skipped</div>
                <div class="sv sv-red"><?= $summary['skipped'] ?></div>
            </div>
            <div class="stat">
                <div class="sl">Mode</div>
                <div class="sv sv-text sv-green"><?= strtoupper(str_replace('_', ' ', $mode)) ?></div>
            </div>
        </div>

        <?php if (empty($results)): ?>
        <div class="empty fi">
            <span class="ei">✓</span>
            <div class="et">Nothing to reverse</div>
            <p class="es">No unpaid invoices were found between <?= DATE_FROM ?> and <?= DATE_TO ?>. All invoices have receipts.</p>
        </div>
        <?php else: ?>
        <div class="sg">
        <?php foreach ($results as $idx => $row):
            $inv        = $row['invoice'];
            $items      = $row['items'];
            $ok_count   = count(array_filter($items, fn($i) => $i['result']['success']));
            $skip_count = count($items) - $ok_count;
        ?>
        <div class="inv">
            <div class="inv-head" onclick="toggleCard(<?= $idx ?>)">
                <div class="inv-meta">
                    <span class="inv-num"><?= htmlspecialchars($inv['inv_number']) ?></span>
                    <span class="inv-dt"><?= htmlspecialchars($inv['date']) ?></span>
                    <span class="inv-amt">₹ <?= inr_format((float)$inv['total'], 2) ?></span>
                    <span class="route">
                        <span class="utype"><?= htmlspecialchars($inv['seller_type']) ?></span>
                        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        <span class="utype"><?= htmlspecialchars($inv['buyer_type']) ?></span>
                    </span>
                    <?php if ($skip_count > 0): ?>
                    <span class="chip c-skip">⚠ <?= $skip_count ?> skipped</span>
                    <?php endif; ?>
                    <span class="chip <?= $mode === 'dry_run' ? 'c-sim' : 'c-ok' ?>">
                        <?= $mode === 'dry_run' ? '◎' : '✓' ?> <?= $ok_count ?> items
                    </span>
                </div>
                <div class="inv-toggle">
                    <span id="tl-<?= $idx ?>">View</span>
                    <i id="ch-<?= $idx ?>" class="chev">▾</i>
                </div>
            </div>

            <div class="hidden" id="cb-<?= $idx ?>">
                <?php if (empty($items)): ?>
                <div style="padding:16px 18px;color:var(--ink-dim);font-size:0.79rem">No line items found for this invoice.</div>
                <?php else: ?>
                <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Qty</th>
                            <th>Seller → Buyer</th>
                            <th>Status</th>
                            <th style="min-width:300px">SQL Queries</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $idata):
                        $item   = $idata['item'];
                        $result = $idata['result'];
                        $pr_id  = $item['pr_id'] ?? $item['product_id'] ?? '—';
                        $qty    = $item['qty'] ?? '—';
                    ?>
                    <tr>
                        <td><span class="pid"><?= htmlspecialchars((string)$pr_id) ?></span></td>
                        <td><span class="qty"><?= htmlspecialchars((string)$qty) ?></span></td>
                        <td style="font-size:0.73rem;color:var(--ink-dim);font-family:var(--mono);line-height:1.8">
                            <?= htmlspecialchars($inv['seller_type'].' #'.$inv['seller_id']) ?><br>
                            → <?= htmlspecialchars($inv['buyer_type'].' #'.$inv['buyer_id']) ?>
                        </td>
                        <td>
                            <?php if (!$result['success']): ?>
                            <span class="chip c-skip">⚠ <?= htmlspecialchars($result['message']) ?></span>
                            <?php elseif ($mode === 'dry_run'): ?>
                            <span class="chip c-sim">◎ Simulated</span>
                            <?php else: ?>
                            <span class="chip c-ok">✓ Applied</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="qs">
                            <?php foreach ($result['queries'] as $q):
                                $cls = isset($q['ok']) ? ($q['ok'] ? 'ok' : 'fail') : '';
                            ?>
                            <div class="ql <?= $cls ?>">
                                <span class="qlb"><?= htmlspecialchars($q['label']) ?></span>
                                <?= htmlspecialchars($q['sql']) ?>
                                <?php if (!empty($q['err'])): ?>
                                <span style="color:var(--red);font-weight:600"> — <?= htmlspecialchars($q['err']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <?php else: /* rollback */ ?>

        <div class="stats sg">
            <div class="stat">
                <div class="sl">Invoices</div>
                <div class="sv sv-violet"><?= $summary['invoices'] ?></div>
            </div>
            <div class="stat">
                <div class="sl">Queries Run</div>
                <div class="sv sv-violet"><?= $summary['items'] ?></div>
            </div>
        </div>

        <div class="card fi">
            <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Invoice ID</th><th>Inverted SQL</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($results as $r): ?>
                <tr>
                    <td style="font-family:var(--mono);color:var(--blue);font-weight:600;white-space:nowrap"><?= htmlspecialchars($r['inv_id']) ?></td>
                    <td style="font-family:var(--mono);font-size:0.71rem;color:var(--ink-mid);word-break:break-all;line-height:1.55"><?= htmlspecialchars($r['sql']) ?></td>
                    <td>
                        <?php if ($r['success']): ?>
                        <span class="chip c-ok">✓ OK</span>
                        <?php else: ?>
                        <span class="chip c-fail">✕ <?= htmlspecialchars($r['error'] ?? 'Error') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

    <?php endif; ?>

    <?php else: ?>
    <div class="empty fi" style="padding:80px 0">
        <span class="ei" style="font-size:3.5rem">📦</span>
        <div class="et">Ready to begin</div>
        <p class="es">Run a <strong>Dry Run</strong> first to preview all affected invoices and stock adjustments before committing any changes.</p>
    </div>
    <?php endif; ?>

</div>

<!-- ── MODALS ───────────────────────────────────────────────────────────── -->
<div class="overlay" id="modal-execute">
    <div class="modal">
        <span class="modal-ico">⚡</span>
        <h3>Confirm Execute</h3>
        <p>This will <strong>permanently reverse stock changes</strong> for all unpaid invoices between <strong><?= DATE_FROM ?></strong> and <strong><?= DATE_TO ?></strong>.<br><br>A session snapshot will be saved so you can rollback immediately. Have you reviewed the Dry Run results?</p>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeM('execute')">Cancel</button>
            <form method="post" style="display:contents">
                <input type="hidden" name="mode" value="execute">
                <input type="hidden" name="confirm" value="yes">
                <button type="submit" class="btn btn-exec">⚡ Yes, Execute</button>
            </form>
        </div>
    </div>
</div>

<div class="overlay" id="modal-rollback">
    <div class="modal">
        <span class="modal-ico">↩</span>
        <h3>Confirm Rollback</h3>
        <p>This will <strong>undo the last Execute run</strong> by inverting all stock changes that were applied.<br><br>Snapshot: <strong><?= htmlspecialchars($rollback_ts ?? 'N/A') ?></strong><br><br>This action itself cannot be undone.</p>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeM('rollback')">Cancel</button>
            <form method="post" style="display:contents">
                <input type="hidden" name="mode" value="rollback">
                <input type="hidden" name="confirm" value="yes">
                <button type="submit" class="btn btn-rb">↩ Yes, Rollback</button>
            </form>
        </div>
    </div>
</div>

<script>
function toggleCard(i){
    const b=document.getElementById('cb-'+i),
          c=document.getElementById('ch-'+i),
          l=document.getElementById('tl-'+i),
          h=b.classList.toggle('hidden');
    c.style.transform = h ? '' : 'rotate(180deg)';
    l.textContent     = h ? 'View' : 'Hide';
}
function openM(t){ document.getElementById('modal-'+t).classList.add('open'); }
function closeM(t){ document.getElementById('modal-'+t).classList.remove('open'); }
document.querySelectorAll('.overlay').forEach(el=>{
    el.addEventListener('click',e=>{ if(e.target===el) el.classList.remove('open'); });
});
// All cards start collapsed
document.querySelectorAll('[id^="cb-"]').forEach(b=>b.classList.add('hidden'));
</script>
</body>
</html>