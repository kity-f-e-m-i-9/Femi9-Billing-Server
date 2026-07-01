<?php
/**
 * Stock Reconciliation Tool
 *
 * Detects drift between:
 *   1. stock.closing_qty  (snapshot)  vs  derived formula (opening + input + return - sales - sent)
 *   2. stock.closing_qty  (snapshot)  vs  stock_ledger sum (net of all deduct/credit/reverse entries)
 *
 * Read-only — no changes are written.
 * Access is restricted to logged-in company users.
 */

declare(strict_types=1);

include("checksession.php");
include("config.php");
require_once("include/StockService.php");

// ── Query 1: Formula drift (snapshot vs column arithmetic) ────────────────
$formulaDrift = [];
$res = mysqli_query($db_conn,
    "SELECT
        s.id,
        s.product_id,
        s.user_type,
        s.user_id,
        s.opening_qty,
        s.input_qty,
        s.sales_qty,
        s.sent_qty,
        s.returnqty,
        s.closing_qty                                                         AS stored_closing,
        (s.opening_qty + s.input_qty + s.returnqty - s.sales_qty - s.sent_qty) AS calculated_closing,
        s.closing_qty -
        (s.opening_qty + s.input_qty + s.returnqty - s.sales_qty - s.sent_qty) AS drift,
        p.productName,
        s.updated_at
     FROM stock s
     LEFT JOIN products p ON p.id = s.product_id
     HAVING drift <> 0
     ORDER BY ABS(drift) DESC"
);
while ($row = mysqli_fetch_assoc($res)) {
    $formulaDrift[] = $row;
}

// ── Query 2: Ledger drift (snapshot closing_qty vs ledger net) ────────────
// Sum of all deduct/reverse_deduct/credit/reverse_credit per entity from ledger,
// then compare against current closing_qty.
$ledgerDrift = [];
$res2 = mysqli_query($db_conn,
    "SELECT
        s.product_id,
        s.user_type,
        s.user_id,
        s.closing_qty                                      AS snapshot_closing,
        COALESCE(l.net_qty, 0)                            AS ledger_net,
        s.closing_qty - COALESCE(l.net_qty, 0)           AS ledger_drift,
        p.productName,
        s.updated_at
     FROM stock s
     LEFT JOIN products p ON p.id = s.product_id
     LEFT JOIN (
         SELECT
             product_id,
             user_type,
             user_id,
             SUM(
                 CASE action
                     WHEN 'credit'          THEN  qty
                     WHEN 'reverse_credit'  THEN -qty
                     WHEN 'deduct'          THEN -qty
                     WHEN 'reverse_deduct'  THEN  qty
                     ELSE 0
                 END
             ) AS net_qty
         FROM stock_ledger
         GROUP BY product_id, user_type, user_id
     ) l ON l.product_id = s.product_id
         AND l.user_type  = s.user_type
         AND l.user_id    = s.user_id
     WHERE s.opening_qty = 0
        OR l.product_id IS NOT NULL
     HAVING ABS(ledger_drift) > 0
     ORDER BY ABS(ledger_drift) DESC"
);
while ($row = mysqli_fetch_assoc($res2)) {
    $ledgerDrift[] = $row;
}

// ── Query 3: Invoices with no ledger entry (stock never applied) ──────────
$noLedger = [];
$res3 = mysqli_query($db_conn,
    "SELECT
        ui.inv_id,
        ui.inv_number,
        ui.date,
        ui.from_user_type AS seller_type,
        ui.from_user_id   AS seller_id,
        ui.to_user_type   AS buyer_type,
        ui.to_user_id     AS buyer_id,
        ui.total,
        ui.status
     FROM user_invoice ui
     LEFT JOIN stock_ledger sl
            ON sl.ref_type = 'user_invoice' AND sl.ref_id = ui.inv_id
     WHERE ui.status != 'draft'
       AND sl.id IS NULL
     ORDER BY ui.date DESC
     LIMIT 200"
);
while ($row = mysqli_fetch_assoc($res3)) {
    $noLedger[] = $row;
}

// ── Query 4: Negative closing_qty rows ───────────────────────────────────
$negativeStock = [];
$res4 = mysqli_query($db_conn,
    "SELECT s.*, p.productName
     FROM stock s
     LEFT JOIN products p ON p.id = s.product_id
     WHERE s.closing_qty < 0
     ORDER BY s.closing_qty ASC"
);
while ($row = mysqli_fetch_assoc($res4)) {
    $negativeStock[] = $row;
}

// ── Query 5: Duplicate stock rows (should be 0 after UNIQUE constraint) ──
$duplicateRows = [];
$res5 = mysqli_query($db_conn,
    "SELECT product_id, user_type, user_id, COUNT(*) AS cnt
     FROM stock
     GROUP BY product_id, user_type, user_id
     HAVING cnt > 1"
);
while ($row = mysqli_fetch_assoc($res5)) {
    $duplicateRows[] = $row;
}

$totalIssues = count($formulaDrift) + count($negativeStock) + count($duplicateRows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock Reconciliation — Femi9 Billing</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#f7f6f3;--surface:#fff;--border:#e5e2dc;--border2:#d4d0c9;
    --ink:#1e1c19;--mid:#5c5750;--dim:#9c9790;
    --blue:#1a56db;--blue-bg:#eff4ff;--blue-br:#c3d4f8;
    --green:#166534;--green-bg:#f0fdf4;--green-br:#bbf7d0;
    --red:#991b1b;--red-bg:#fef2f2;--red-br:#fecaca;
    --amber:#92400e;--amber-bg:#fffbeb;--amber-br:#fcd34d;
    --violet:#5b21b6;--violet-bg:#f5f3ff;--violet-br:#ddd6fe;
    --sans:'DM Sans',system-ui,sans-serif;--mono:'DM Mono','Courier New',monospace;
    --r:7px;--r-lg:12px;
}
body{font-family:var(--sans);background:var(--bg);color:var(--ink);font-size:14px;-webkit-font-smoothing:antialiased}
.nav{background:var(--surface);border-bottom:1px solid var(--border);height:52px;display:flex;align-items:center;justify-content:space-between;padding:0 32px;position:sticky;top:0;z-index:40;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.nav-left{display:flex;align-items:center;gap:10px}
.logo{width:28px;height:28px;background:var(--blue);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff}
.brand{font-weight:600;font-size:.85rem}
.sep{color:var(--border2)}
.pg{font-size:.82rem;color:var(--mid)}
.page{max-width:1100px;margin:0 auto;padding:32px}
h1{font-size:1.7rem;font-weight:700;letter-spacing:-.025em;margin-bottom:6px}
.sub{font-size:.85rem;color:var(--mid);font-weight:300;margin-bottom:28px;line-height:1.7}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:28px}
.stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:18px 20px}
.sl{font-size:.65rem;font-weight:600;text-transform:uppercase;letter-spacing:.1em;color:var(--dim);margin-bottom:8px}
.sv{font-size:2.2rem;font-weight:700;letter-spacing:-.03em;line-height:1}
.sv-ok{color:var(--green)}.sv-warn{color:var(--amber)}.sv-err{color:var(--red)}.sv-blue{color:var(--blue)}
.section{margin-bottom:32px}
.section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.section-title{font-size:.95rem;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:8px}
.badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:100px;font-size:.68rem;font-weight:700;letter-spacing:0}
.b-ok{background:var(--green-bg);color:var(--green);border:1px solid var(--green-br)}
.b-err{background:var(--red-bg);color:var(--red);border:1px solid var(--red-br)}
.b-warn{background:var(--amber-bg);color:var(--amber);border:1px solid var(--amber-br)}
.b-blue{background:var(--blue-bg);color:var(--blue);border:1px solid var(--blue-br)}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:.78rem}
th{padding:9px 14px;text-align:left;font-size:.63rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--dim);background:var(--bg);border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:10px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
tr:last-child td{border-bottom:none}
tbody tr:hover td{background:#faf9f8}
.mono{font-family:var(--mono);font-size:.77rem}
.drift-pos{color:var(--red);font-weight:700}
.drift-neg{color:var(--blue);font-weight:700}
.drift-ok{color:var(--green)}
.chip{display:inline-flex;align-items:center;padding:2px 8px;border-radius:4px;font-size:.67rem;font-weight:600;white-space:nowrap}
.c-ok{background:var(--green-bg);color:var(--green);border:1px solid var(--green-br)}
.c-err{background:var(--red-bg);color:var(--red);border:1px solid var(--red-br)}
.c-warn{background:var(--amber-bg);color:var(--amber);border:1px solid var(--amber-br)}
.c-blue{background:var(--blue-bg);color:var(--blue);border:1px solid var(--blue-br)}
.empty{text-align:center;padding:40px;color:var(--dim);font-size:.82rem}
.empty strong{display:block;font-size:1.8rem;margin-bottom:8px;opacity:.2}
.ts{font-family:var(--mono);font-size:.7rem;color:var(--dim)}
.refresh-hint{font-size:.75rem;color:var(--dim);font-style:italic}
</style>
</head>
<body>

<nav class="nav">
    <div class="nav-left">
        <div class="logo">F9</div>
        <span class="brand">Femi9 Billing</span>
        <span class="sep">/</span>
        <span class="pg">Stock Reconciliation</span>
    </div>
    <span class="ts">Run at: <?= date('Y-m-d H:i:s') ?></span>
</nav>

<div class="page">

    <h1>Stock Reconciliation</h1>
    <p class="sub">
        Read-only health check. Detects drift between the <code>stock</code> snapshot, column arithmetic,
        and the <code>stock_ledger</code> audit trail. Run after any bulk operation or suspected data issue.
    </p>

    <!-- ── SUMMARY STATS ─────────────────────────────────────────────────── -->
    <div class="stats">
        <div class="stat">
            <div class="sl">Formula Drift Rows</div>
            <div class="sv <?= count($formulaDrift) === 0 ? 'sv-ok' : 'sv-err' ?>"><?= count($formulaDrift) ?></div>
        </div>
        <div class="stat">
            <div class="sl">Ledger Drift Rows</div>
            <div class="sv <?= count($ledgerDrift) === 0 ? 'sv-ok' : 'sv-warn' ?>"><?= count($ledgerDrift) ?></div>
        </div>
        <div class="stat">
            <div class="sl">Negative Stock Rows</div>
            <div class="sv <?= count($negativeStock) === 0 ? 'sv-ok' : 'sv-err' ?>"><?= count($negativeStock) ?></div>
        </div>
        <div class="stat">
            <div class="sl">Submitted, No Ledger</div>
            <div class="sv <?= count($noLedger) === 0 ? 'sv-ok' : 'sv-warn' ?>"><?= count($noLedger) ?></div>
        </div>
        <div class="stat">
            <div class="sl">Duplicate Stock Rows</div>
            <div class="sv <?= count($duplicateRows) === 0 ? 'sv-ok' : 'sv-err' ?>"><?= count($duplicateRows) ?></div>
        </div>
        <div class="stat">
            <div class="sl">Overall</div>
            <div class="sv <?= $totalIssues === 0 ? 'sv-ok' : 'sv-err' ?>">
                <?= $totalIssues === 0 ? '✓' : $totalIssues ?>
            </div>
        </div>
    </div>

    <!-- ── CHECK 1: FORMULA DRIFT ─────────────────────────────────────────── -->
    <div class="section">
        <div class="section-head">
            <span class="section-title">
                Check 1 — Formula Drift
                <span class="badge <?= count($formulaDrift) === 0 ? 'b-ok' : 'b-err' ?>">
                    <?= count($formulaDrift) === 0 ? 'PASS' : count($formulaDrift) . ' rows' ?>
                </span>
            </span>
            <span class="refresh-hint">closing_qty vs opening + input + returnqty − sales − sent</span>
        </div>
        <div class="card">
            <?php if (empty($formulaDrift)): ?>
            <div class="empty"><strong>✓</strong>All closing_qty values match their column arithmetic.</div>
            <?php else: ?>
            <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th><th>User Type</th><th>User ID</th>
                        <th>Opening</th><th>Input</th><th>Return</th>
                        <th>Sales</th><th>Sent</th>
                        <th>Stored Closing</th><th>Calculated</th><th>Drift</th>
                        <th>Updated At</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($formulaDrift as $r): $d = (int)$r['drift']; ?>
                <tr>
                    <td>
                        <span class="mono">#<?= (int)$r['product_id'] ?></span><br>
                        <span style="color:var(--mid);font-size:.73rem"><?= htmlspecialchars($r['productName'] ?? '—') ?></span>
                    </td>
                    <td><span class="chip c-blue"><?= htmlspecialchars($r['user_type']) ?></span></td>
                    <td class="mono"><?= htmlspecialchars($r['user_id']) ?></td>
                    <td class="mono"><?= (int)$r['opening_qty'] ?></td>
                    <td class="mono"><?= (int)$r['input_qty'] ?></td>
                    <td class="mono"><?= (int)$r['returnqty'] ?></td>
                    <td class="mono"><?= (int)$r['sales_qty'] ?></td>
                    <td class="mono"><?= (int)$r['sent_qty'] ?></td>
                    <td class="mono" style="font-weight:600"><?= (int)$r['stored_closing'] ?></td>
                    <td class="mono" style="font-weight:600"><?= (int)$r['calculated_closing'] ?></td>
                    <td>
                        <span class="<?= $d > 0 ? 'drift-pos' : 'drift-neg' ?>">
                            <?= $d > 0 ? '+' : '' ?><?= $d ?>
                        </span>
                    </td>
                    <td class="ts"><?= htmlspecialchars($r['updated_at'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── CHECK 2: LEDGER DRIFT ──────────────────────────────────────────── -->
    <div class="section">
        <div class="section-head">
            <span class="section-title">
                Check 2 — Ledger Drift
                <span class="badge <?= count($ledgerDrift) === 0 ? 'b-ok' : 'b-warn' ?>">
                    <?= count($ledgerDrift) === 0 ? 'PASS' : count($ledgerDrift) . ' rows' ?>
                </span>
            </span>
            <span class="refresh-hint">stock.closing_qty vs net sum of stock_ledger entries</span>
        </div>
        <div class="card">
            <?php if (empty($ledgerDrift)): ?>
            <div class="empty"><strong>✓</strong>Ledger net matches snapshot for all tracked entities.</div>
            <?php else: ?>
            <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th><th>User Type</th><th>User ID</th>
                        <th>Snapshot Closing</th><th>Ledger Net</th><th>Drift</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ledgerDrift as $r): $d = (int)$r['ledger_drift']; ?>
                <tr>
                    <td>
                        <span class="mono">#<?= (int)$r['product_id'] ?></span><br>
                        <span style="color:var(--mid);font-size:.73rem"><?= htmlspecialchars($r['productName'] ?? '—') ?></span>
                    </td>
                    <td><span class="chip c-blue"><?= htmlspecialchars($r['user_type']) ?></span></td>
                    <td class="mono"><?= htmlspecialchars($r['user_id']) ?></td>
                    <td class="mono" style="font-weight:600"><?= (int)$r['snapshot_closing'] ?></td>
                    <td class="mono" style="font-weight:600"><?= (int)$r['ledger_net'] ?></td>
                    <td>
                        <span class="<?= $d > 0 ? 'drift-pos' : 'drift-neg' ?>">
                            <?= $d > 0 ? '+' : '' ?><?= $d ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── CHECK 3: SUBMITTED INVOICES WITH NO LEDGER ENTRY ──────────────── -->
    <div class="section">
        <div class="section-head">
            <span class="section-title">
                Check 3 — Submitted Invoices With No Stock Ledger Entry
                <span class="badge <?= count($noLedger) === 0 ? 'b-ok' : 'b-warn' ?>">
                    <?= count($noLedger) === 0 ? 'PASS' : count($noLedger) . ' invoices' ?>
                </span>
            </span>
            <span class="refresh-hint">These invoices were submitted but stock was never deducted</span>
        </div>
        <div class="card">
            <?php if (empty($noLedger)): ?>
            <div class="empty"><strong>✓</strong>All submitted invoices have ledger entries.</div>
            <?php else: ?>
            <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Invoice #</th><th>Date</th><th>Seller</th><th>Buyer</th>
                        <th>Total</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($noLedger as $r): ?>
                <tr>
                    <td class="mono" style="font-weight:600;color:var(--blue)"><?= htmlspecialchars($r['inv_number']) ?></td>
                    <td class="ts"><?= htmlspecialchars($r['date']) ?></td>
                    <td>
                        <span class="chip c-blue"><?= htmlspecialchars($r['seller_type']) ?></span>
                        <span class="mono" style="font-size:.71rem;color:var(--mid);margin-left:4px"><?= htmlspecialchars($r['seller_id']) ?></span>
                    </td>
                    <td>
                        <span class="chip c-warn"><?= htmlspecialchars($r['buyer_type']) ?></span>
                        <span class="mono" style="font-size:.71rem;color:var(--mid);margin-left:4px"><?= htmlspecialchars($r['buyer_id']) ?></span>
                    </td>
                    <td class="mono">₹<?= number_format((float)$r['total'], 2) ?></td>
                    <td><span class="chip c-warn"><?= htmlspecialchars($r['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── CHECK 4: NEGATIVE STOCK ────────────────────────────────────────── -->
    <div class="section">
        <div class="section-head">
            <span class="section-title">
                Check 4 — Negative closing_qty
                <span class="badge <?= count($negativeStock) === 0 ? 'b-ok' : 'b-err' ?>">
                    <?= count($negativeStock) === 0 ? 'PASS' : count($negativeStock) . ' rows' ?>
                </span>
            </span>
            <span class="refresh-hint">closing_qty must never be below zero</span>
        </div>
        <div class="card">
            <?php if (empty($negativeStock)): ?>
            <div class="empty"><strong>✓</strong>No negative closing_qty values found.</div>
            <?php else: ?>
            <div class="tbl-wrap">
            <table>
                <thead>
                    <tr><th>Product</th><th>User Type</th><th>User ID</th><th>Closing Qty</th><th>Updated At</th></tr>
                </thead>
                <tbody>
                <?php foreach ($negativeStock as $r): ?>
                <tr>
                    <td>
                        <span class="mono">#<?= (int)$r['product_id'] ?></span>
                        <span style="color:var(--mid);font-size:.73rem;margin-left:6px"><?= htmlspecialchars($r['productName'] ?? '—') ?></span>
                    </td>
                    <td><span class="chip c-blue"><?= htmlspecialchars($r['user_type']) ?></span></td>
                    <td class="mono"><?= htmlspecialchars($r['user_id']) ?></td>
                    <td><span class="drift-neg" style="font-size:1rem;font-weight:700"><?= (int)$r['closing_qty'] ?></span></td>
                    <td class="ts"><?= htmlspecialchars($r['updated_at'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── CHECK 5: DUPLICATE STOCK ROWS ─────────────────────────────────── -->
    <div class="section">
        <div class="section-head">
            <span class="section-title">
                Check 5 — Duplicate Stock Rows
                <span class="badge <?= count($duplicateRows) === 0 ? 'b-ok' : 'b-err' ?>">
                    <?= count($duplicateRows) === 0 ? 'PASS' : count($duplicateRows) . ' duplicates' ?>
                </span>
            </span>
            <span class="refresh-hint">Each (product_id, user_type, user_id) must be unique</span>
        </div>
        <div class="card">
            <?php if (empty($duplicateRows)): ?>
            <div class="empty"><strong>✓</strong>No duplicate stock rows. UNIQUE constraint is holding.</div>
            <?php else: ?>
            <div class="tbl-wrap">
            <table>
                <thead>
                    <tr><th>Product ID</th><th>User Type</th><th>User ID</th><th>Duplicate Count</th></tr>
                </thead>
                <tbody>
                <?php foreach ($duplicateRows as $r): ?>
                <tr>
                    <td class="mono"><?= (int)$r['product_id'] ?></td>
                    <td><span class="chip c-blue"><?= htmlspecialchars($r['user_type']) ?></span></td>
                    <td class="mono"><?= htmlspecialchars($r['user_id']) ?></td>
                    <td><span class="drift-pos"><?= (int)$r['cnt'] ?> rows</span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>
</body>
</html>
