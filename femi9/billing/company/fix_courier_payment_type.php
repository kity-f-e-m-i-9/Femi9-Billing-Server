<?php
/**
 * fix_courier_payment_type.php
 *
 * Tool: Fix receipt.payment_type from 'regular' → 'courier_charge'
 * Logic: If a receipt with payment_type='regular' has an invoice_amount
 *        that exactly matches the linked invoice's courier_charges,
 *        reclassify it as 'courier_charge'.
 *
 * Features:
 *  - Dry Run  : Preview affected rows with zero DB changes
 *  - Execute  : Apply changes inside a transaction
 *  - Rollback : Restore from the audit log created during Execute
 *
 * Security:
 *  - Session-based CSRF token
 *  - All DB values via prepared statements
 *  - Output escaped with htmlspecialchars()
 *  - No direct user input reaches SQL
 *
 * Requirements: PHP 8+, PDO, MySQLi extension
 *
 * @author  Senior Dev Review
 * @version 1.0.0
 */

declare(strict_types=1);

// ─── CONFIG ──────────────────────────────────────────────────────────────────
// !! Move these to an .env file or a config outside webroot in production !!
const DB_HOST = 'localhost';
const DB_NAME = 'billing0femi9_billingapp';
const DB_USER = 'billing0femi9_femi9admin';
const DB_PASS = 'mavNip-xukvyk-9veqra';
const DB_PORT = 3306;


// Audit log table – created automatically on first Execute run
const AUDIT_TABLE = 'fix_courier_payment_type_audit';

// ─── BOOTSTRAP ───────────────────────────────────────────────────────────────
session_start();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ─── DATABASE CONNECTION ──────────────────────────────────────────────────────
function getDb(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST, DB_PORT, DB_NAME
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ─── CORE LOGIC ──────────────────────────────────────────────────────────────

/**
 * Build the candidate query.
 *
 * Looks in BOTH `invoice` and `user_invoice` tables so we cover all
 * invoice types used in the Femi9 schema. We use COALESCE to prefer
 * user_invoice when both exist (adjust to taste).
 *
 * Returns rows:
 *   receiptid, inv_id, invoice_amount,
 *   courier_charges (from whichever invoice table matched),
 *   invoice_source ('invoice' | 'user_invoice')
 */
function getCandidates(PDO $pdo): array
{
    $sql = "
        SELECT
            r.receiptid,
            r.inv_id,
            r.invoice_amount,
            COALESCE(ui.courier_charges, i.courier_charges) AS courier_charges,
            CASE
                WHEN ui.inv_id IS NOT NULL THEN 'user_invoice'
                WHEN i.inv_id  IS NOT NULL THEN 'invoice'
                ELSE 'not_found'
            END AS invoice_source
        FROM receipt r
        LEFT JOIN user_invoice ui ON ui.inv_id = r.inv_id
        LEFT JOIN invoice       i  ON i.inv_id  = r.inv_id
        WHERE
            r.payment_type = 'regular'
            AND r.invoice_amount > 0
            AND COALESCE(ui.courier_charges, i.courier_charges) IS NOT NULL
            AND r.invoice_amount = COALESCE(ui.courier_charges, i.courier_charges)
        ORDER BY r.receiptid
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/**
 * Ensure the audit log table exists.
 */
function ensureAuditTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `" . AUDIT_TABLE . "` (
            `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            `run_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `receiptid`    VARCHAR(255)    NOT NULL,
            `inv_id`       VARCHAR(255)    NOT NULL,
            `invoice_amount` INT           NOT NULL,
            `old_payment_type` VARCHAR(50) NOT NULL,
            `new_payment_type` VARCHAR(50) NOT NULL,
            `invoice_source`   VARCHAR(50) NOT NULL,
            `courier_charges`  INT         NOT NULL,
            `rolled_back`  TINYINT(1)      NOT NULL DEFAULT 0,
            `rolled_back_at` DATETIME      DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_receiptid` (`receiptid`),
            KEY `idx_rolled_back` (`rolled_back`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Dry run – returns candidate rows, touches nothing.
 */
function dryRun(PDO $pdo): array
{
    return getCandidates($pdo);
}

/**
 * Execute – applies changes inside a transaction, writes audit log.
 * Returns ['updated' => int, 'errors' => string[]]
 */
function executeChanges(PDO $pdo): array
{
    ensureAuditTable($pdo);
    $candidates = getCandidates($pdo);

    if (empty($candidates)) {
        return ['updated' => 0, 'errors' => []];
    }

    $pdo->beginTransaction();
    $updated = 0;
    $errors  = [];

    try {
        // Batch-insert audit log first (single statement for performance)
        $auditSql = "
            INSERT INTO `" . AUDIT_TABLE . "`
                (receiptid, inv_id, invoice_amount, old_payment_type,
                 new_payment_type, invoice_source, courier_charges)
            VALUES (:receiptid, :inv_id, :invoice_amount, :old_pt,
                    :new_pt, :source, :courier)
        ";
        $auditStmt = $pdo->prepare($auditSql);

        $updateStmt = $pdo->prepare("
            UPDATE receipt
            SET    payment_type = 'courier_charge'
            WHERE  receiptid    = :receiptid
              AND  payment_type = 'regular'
        ");

        foreach ($candidates as $row) {
            // Write audit BEFORE update so we can always roll back
            $auditStmt->execute([
                ':receiptid'      => $row['receiptid'],
                ':inv_id'         => $row['inv_id'],
                ':invoice_amount' => $row['invoice_amount'],
                ':old_pt'         => 'regular',
                ':new_pt'         => 'courier_charge',
                ':source'         => $row['invoice_source'],
                ':courier'        => $row['courier_charges'],
            ]);

            $updateStmt->execute([':receiptid' => $row['receiptid']]);
            $updated += $updateStmt->rowCount();
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $errors[] = 'Transaction rolled back: ' . $e->getMessage();
        $updated  = 0;
    }

    return ['updated' => $updated, 'errors' => $errors];
}

/**
 * Rollback – restores all un-rolled-back rows from the latest run
 * (or all runs if $runId is null).
 *
 * Returns ['restored' => int, 'errors' => string[]]
 */
function rollbackChanges(PDO $pdo, ?int $runId = null): array
{
    ensureAuditTable($pdo);

    // Fetch audit rows that haven't been rolled back yet
    if ($runId !== null) {
        $auditRows = $pdo->prepare("
            SELECT * FROM `" . AUDIT_TABLE . "`
            WHERE rolled_back = 0 AND id = :id
        ");
        $auditRows->execute([':id' => $runId]);
    } else {
        $auditRows = $pdo->query("
            SELECT * FROM `" . AUDIT_TABLE . "`
            WHERE rolled_back = 0
            ORDER BY id DESC
        ");
    }
    $rows = $auditRows->fetchAll();

    if (empty($rows)) {
        return ['restored' => 0, 'errors' => ['No un-rolled-back audit records found.']];
    }

    $pdo->beginTransaction();
    $restored = 0;
    $errors   = [];

    try {
        $restoreStmt = $pdo->prepare("
            UPDATE receipt
            SET    payment_type = :old_pt
            WHERE  receiptid    = :receiptid
              AND  payment_type = :new_pt
        ");
        $markStmt = $pdo->prepare("
            UPDATE `" . AUDIT_TABLE . "`
            SET    rolled_back    = 1,
                   rolled_back_at = NOW()
            WHERE  id = :id
        ");

        foreach ($rows as $row) {
            $restoreStmt->execute([
                ':old_pt'     => $row['old_payment_type'],
                ':receiptid'  => $row['receiptid'],
                ':new_pt'     => $row['new_payment_type'],
            ]);
            $restored += $restoreStmt->rowCount();
            $markStmt->execute([':id' => $row['id']]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $errors[]  = 'Rollback transaction failed: ' . $e->getMessage();
        $restored  = 0;
    }

    return ['restored' => $restored, 'errors' => $errors];
}

/**
 * Fetch audit log summary for display.
 */
function getAuditLog(PDO $pdo): array
{
    try {
        ensureAuditTable($pdo);
        return $pdo->query("
            SELECT * FROM `" . AUDIT_TABLE . "`
            ORDER BY id DESC
            LIMIT 500
        ")->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

// ─── ACTION HANDLER ───────────────────────────────────────────────────────────
$action  = $_POST['action']  ?? $_GET['action'] ?? '';
$result  = null;
$dbError = null;

try {
    $pdo = getDb();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

if ($pdo && $action !== '' && $action !== 'view_log') {
    // CSRF validation for state-changing actions
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $submittedToken)) {
        $result = ['error' => 'Invalid CSRF token. Please refresh and try again.'];
        $action = '';
    }
}

if ($pdo && !isset($result['error'])) {
    match ($action) {
        'dry_run' => $result = ['mode' => 'dry_run',  'rows'    => dryRun($pdo)],
        'execute' => $result = ['mode' => 'execute',  'data'    => executeChanges($pdo)],
        'rollback'=> $result = ['mode' => 'rollback', 'data'    => rollbackChanges($pdo)],
        default   => null,
    };
}

$auditLog = ($pdo && in_array($action, ['', 'view_log', 'execute', 'rollback'], true))
    ? getAuditLog($pdo)
    : [];

// ─── HELPERS ──────────────────────────────────────────────────────────────────
function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Courier Payment Type | Femi9 Billing</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f0f4f8;
            color: #1e293b;
            min-height: 100vh;
        }

        /* ── Header ── */
        .header {
            background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);
            color: #fff;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.25);
        }
        .header h1 { font-size: 1.4rem; font-weight: 700; }
        .header p  { font-size: .85rem; opacity: .8; margin-top: .25rem; }

        /* ── Layout ── */
        .container { max-width: 1200px; margin: 0 auto; padding: 1.5rem; }

        /* ── Cards ── */
        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
            margin-bottom: 1.5rem;
        }
        .card-header {
            padding: .9rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            font-size: .95rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .card-body { padding: 1.25rem; }

        /* ── Buttons ── */
        .btn-group { display: flex; gap: .75rem; flex-wrap: wrap; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .55rem 1.2rem;
            border: none;
            border-radius: 6px;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            transition: filter .15s, transform .1s;
            text-decoration: none;
        }
        .btn:hover  { filter: brightness(1.08); }
        .btn:active { transform: scale(.97); }

        .btn-info     { background: #0ea5e9; color: #fff; }
        .btn-success  { background: #16a34a; color: #fff; }
        .btn-danger   { background: #dc2626; color: #fff; }
        .btn-secondary{ background: #64748b; color: #fff; }

        /* ── Logic explainer ── */
        .logic-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            border-radius: 0 6px 6px 0;
            padding: .85rem 1rem;
            font-size: .88rem;
            line-height: 1.6;
        }
        .logic-box code {
            background: #dbeafe;
            padding: .1em .3em;
            border-radius: 3px;
            font-size: .9em;
        }

        /* ── Alerts ── */
        .alert {
            padding: .85rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: .9rem;
        }
        .alert-error   { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
        .alert-success { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
        .alert-warning { background: #fef9c3; border: 1px solid #fde047; color: #854d0e; }
        .alert-info    { background: #e0f2fe; border: 1px solid #7dd3fc; color: #075985; }

        /* ── DB Error ── */
        .db-error {
            background: #fee2e2; border: 1px solid #f87171;
            border-radius: 8px; padding: 1rem 1.25rem;
            color: #7f1d1d; margin-bottom: 1rem;
        }

        /* ── Stats ── */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px,1fr)); gap: 1rem; }
        .stat-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: .85rem 1rem;
            text-align: center;
        }
        .stat-card .stat-val { font-size: 1.8rem; font-weight: 700; color: #1d4ed8; }
        .stat-card .stat-lbl { font-size: .78rem; color: #64748b; margin-top: .2rem; }

        /* ── Tables ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .84rem; }
        th {
            background: #f1f5f9;
            padding: .55rem .75rem;
            text-align: left;
            font-weight: 600;
            color: #475569;
            white-space: nowrap;
            position: sticky;
            top: 0;
        }
        td { padding: .5rem .75rem; border-bottom: 1px solid #f1f5f9; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }

        .badge {
            display: inline-block;
            padding: .15em .55em;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 600;
        }
        .badge-blue   { background: #dbeafe; color: #1e40af; }
        .badge-green  { background: #dcfce7; color: #166534; }
        .badge-red    { background: #fee2e2; color: #991b1b; }
        .badge-gray   { background: #f1f5f9; color: #64748b; }
        .badge-orange { background: #fff7ed; color: #9a3412; }

        .empty-state {
            text-align: center;
            padding: 2.5rem;
            color: #94a3b8;
            font-size: .9rem;
        }

        /* ── Confirm dialog overlay ── */
        .overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,.55);
            backdrop-filter: blur(2px);
            z-index: 100;
            align-items: center;
            justify-content: center;
        }
        .overlay.active { display: flex; }
        .dialog {
            background: #fff;
            border-radius: 12px;
            padding: 2rem;
            max-width: 440px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
        }
        .dialog h2 { font-size: 1.1rem; margin-bottom: .75rem; }
        .dialog p  { font-size: .9rem; color: #475569; margin-bottom: 1.25rem; line-height: 1.6; }
        .dialog .btn-group { justify-content: flex-end; }

        footer {
            text-align: center;
            font-size: .78rem;
            color: #94a3b8;
            padding: 1.5rem;
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <h1>🔧 Fix Courier Payment Type</h1>
    <p>Reclassify <code>regular</code> receipts to <code>courier_charge</code> where invoice_amount matches courier_charges</p>
</div>

<div class="container">

    <?php if ($dbError): ?>
    <div class="db-error">
        <strong>⚠️ Database Connection Failed</strong><br>
        <?= e($dbError) ?><br>
        <small>Check DB_HOST, DB_NAME, DB_USER, DB_PASS constants at the top of this file.</small>
    </div>
    <?php endif; ?>

    <!-- Logic Explainer -->
    <div class="card">
        <div class="card-header">📋 How This Tool Works</div>
        <div class="card-body">
            <div class="logic-box">
                <strong>Condition checked:</strong><br>
                <code>receipt.payment_type = 'regular'</code>
                AND the linked invoice (from <code>user_invoice</code> or <code>invoice</code> table)<br>
                has <code>courier_charges</code> that equals <code>receipt.invoice_amount</code>.<br><br>
                <strong>Action:</strong> Update <code>receipt.payment_type</code> →
                <code>'courier_charge'</code><br><br>
                <strong>Safety:</strong> Dry Run previews changes. Execute writes an audit log to
                <code><?= e(AUDIT_TABLE) ?></code> so every change can be rolled back individually.
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="card">
        <div class="card-header">⚙️ Actions</div>
        <div class="card-body">
            <div class="btn-group">
                <!-- Dry Run -->
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action"     value="dry_run">
                    <button type="submit" class="btn btn-info">
                        🔍 Dry Run
                    </button>
                </form>

                <!-- Execute -->
                <button type="button" class="btn btn-success"
                        onclick="document.getElementById('confirm-execute').classList.add('active')">
                    ✅ Execute Changes
                </button>

                <!-- Rollback -->
                <button type="button" class="btn btn-danger"
                        onclick="document.getElementById('confirm-rollback').classList.add('active')">
                    ↩️ Rollback All
                </button>

                <!-- Refresh Audit Log -->
                <a href="<?= e($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">
                    🔄 Refresh
                </a>
            </div>
        </div>
    </div>

    <!-- Result Output -->
    <?php if ($result): ?>
    <div class="card">
        <div class="card-header">
            <?php
            echo match($result['mode'] ?? '') {
                'dry_run'  => '🔍 Dry Run Results',
                'execute'  => '✅ Execute Results',
                'rollback' => '↩️ Rollback Results',
                default    => '⚠️ Result',
            };
            ?>
        </div>
        <div class="card-body">

            <?php if (isset($result['error'])): ?>
                <div class="alert alert-error"><?= e($result['error']) ?></div>

            <?php elseif (($result['mode'] ?? '') === 'dry_run'): ?>
                <?php $rows = $result['rows']; ?>
                <?php if (empty($rows)): ?>
                    <div class="alert alert-success">
                        ✅ No eligible receipts found. Nothing to change.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        ⚠️ <strong><?= count($rows) ?> receipt(s)</strong> would be updated.
                        This is a preview — no changes have been made.
                    </div>
                    <div class="stat-grid" style="margin-bottom:1rem">
                        <div class="stat-card">
                            <div class="stat-val"><?= count($rows) ?></div>
                            <div class="stat-lbl">Receipts to Update</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-val">
                                <?= array_sum(array_column($rows, 'invoice_amount')) ?>
                            </div>
                            <div class="stat-lbl">Total Amount (₹)</div>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Receipt ID</th>
                                    <th>Invoice ID</th>
                                    <th>Receipt Amount</th>
                                    <th>Courier Charges</th>
                                    <th>Invoice Source</th>
                                    <th>Change</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $i => $row): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><code><?= e($row['receiptid']) ?></code></td>
                                    <td><code><?= e($row['inv_id']) ?></code></td>
                                    <td>₹<?= number_format((float)$row['invoice_amount'], 2) ?></td>
                                    <td>₹<?= number_format((float)$row['courier_charges'], 2) ?></td>
                                    <td>
                                        <span class="badge badge-blue"><?= e($row['invoice_source']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-gray">regular</span>
                                        → <span class="badge badge-orange">courier_charge</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php elseif (($result['mode'] ?? '') === 'execute'): ?>
                <?php $data = $result['data']; ?>
                <?php if (!empty($data['errors'])): ?>
                    <?php foreach ($data['errors'] as $err): ?>
                        <div class="alert alert-error">❌ <?= e($err) ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-success">
                        ✅ <strong><?= $data['updated'] ?> receipt(s) updated</strong> successfully.
                        All changes logged to <code><?= e(AUDIT_TABLE) ?></code>.
                    </div>
                <?php endif; ?>

            <?php elseif (($result['mode'] ?? '') === 'rollback'): ?>
                <?php $data = $result['data']; ?>
                <?php if (!empty($data['errors'])): ?>
                    <?php foreach ($data['errors'] as $err): ?>
                        <div class="alert alert-error">❌ <?= e($err) ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        ↩️ <strong><?= $data['restored'] ?> receipt(s) restored</strong>
                        to <code>regular</code>. Audit log marked as rolled back.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>
    <?php endif; ?>

    <!-- Audit Log -->
    <?php if ($pdo): ?>
    <div class="card">
        <div class="card-header">📜 Audit Log <small style="font-weight:400;color:#64748b">(last 500 entries)</small></div>
        <div class="card-body">
            <?php if (empty($auditLog)): ?>
                <div class="empty-state">No audit records yet. Run <strong>Execute</strong> to create them.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Run At</th>
                                <th>Receipt ID</th>
                                <th>Invoice ID</th>
                                <th>Amount</th>
                                <th>Courier</th>
                                <th>Old Type</th>
                                <th>New Type</th>
                                <th>Source</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($auditLog as $log): ?>
                            <tr>
                                <td><?= e($log['id']) ?></td>
                                <td><?= e($log['run_at']) ?></td>
                                <td><code><?= e($log['receiptid']) ?></code></td>
                                <td><code><?= e($log['inv_id']) ?></code></td>
                                <td>₹<?= number_format((float)$log['invoice_amount'], 2) ?></td>
                                <td>₹<?= number_format((float)$log['courier_charges'], 2) ?></td>
                                <td><span class="badge badge-gray"><?= e($log['old_payment_type']) ?></span></td>
                                <td><span class="badge badge-orange"><?= e($log['new_payment_type']) ?></span></td>
                                <td><span class="badge badge-blue"><?= e($log['invoice_source']) ?></span></td>
                                <td>
                                    <?php if ($log['rolled_back']): ?>
                                        <span class="badge badge-red" title="Rolled back at <?= e($log['rolled_back_at']) ?>">
                                            ↩ Rolled Back
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-green">✅ Applied</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /container -->

<!-- ── Confirm Execute Dialog ─────────────────────────────────────────── -->
<div class="overlay" id="confirm-execute">
    <div class="dialog">
        <h2>✅ Confirm Execute</h2>
        <p>
            This will update <strong>all eligible receipts</strong> from
            <code>regular</code> → <code>courier_charge</code>.<br><br>
            A full audit log will be written — you can rollback at any time.
            Run <strong>Dry Run</strong> first if you haven't already.
        </p>
        <div class="btn-group">
            <button class="btn btn-secondary"
                    onclick="document.getElementById('confirm-execute').classList.remove('active')">
                Cancel
            </button>
            <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action"     value="execute">
                <button type="submit" class="btn btn-success">Confirm Execute</button>
            </form>
        </div>
    </div>
</div>

<!-- ── Confirm Rollback Dialog ────────────────────────────────────────── -->
<div class="overlay" id="confirm-rollback">
    <div class="dialog">
        <h2>↩️ Confirm Rollback</h2>
        <p>
            This will restore <strong>all un-rolled-back receipts</strong> from
            <code>courier_charge</code> back to <code>regular</code>
            using the audit log.<br><br>
            Only receipts updated by this tool will be touched.
        </p>
        <div class="btn-group">
            <button class="btn btn-secondary"
                    onclick="document.getElementById('confirm-rollback').classList.remove('active')">
                Cancel
            </button>
            <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action"     value="rollback">
                <button type="submit" class="btn btn-danger">Confirm Rollback</button>
            </form>
        </div>
    </div>
</div>

<footer>
    Femi9 Billing App · fix_courier_payment_type.php · <?= e(AUDIT_TABLE) ?>
</footer>

<script>
// Close overlays on backdrop click
document.querySelectorAll('.overlay').forEach(el => {
    el.addEventListener('click', e => {
        if (e.target === el) el.classList.remove('active');
    });
});
// Close on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.overlay.active')
            .forEach(el => el.classList.remove('active'));
    }
});
</script>

</body>
</html>
