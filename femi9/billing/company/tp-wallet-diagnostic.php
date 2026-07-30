<?php
/**
 * TP Wallet Diagnostic — read-only lookup tool.
 *
 * Purpose: investigate cases where a TP's wallet-history.php page shows a
 * non-zero "Wallet - Available Amount" balance but the Last 10 Credit /
 * Last 10 Debit lists appear empty. Since both the balance query and the
 * list queries in wallet-history.php use the identical WHERE clause on
 * wallet_monthly_sls_report / wallet_withdraw, a non-zero balance with
 * empty lists should not be possible from the query logic alone — this
 * page dumps the raw rows and the exact recomputed numbers so the actual
 * cause (bad data, wrong TP looked up, stale page, etc.) can be seen
 * directly instead of guessed at.
 *
 * Read-only: no INSERT/UPDATE/DELETE anywhere in this file.
 *
 * @author Femi9 Billing System
 * @version 1.0
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once('config.php');

$dbConn = $db_conn;
$title  = "TP Wallet Diagnostic";

function prep(mysqli $dbConn, string $sql): mysqli_stmt
{
    $stmt = $dbConn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Query prepare failed: " . $dbConn->error . " | SQL: " . $sql);
    }
    return $stmt;
}

$search = trim((string)($_GET['q'] ?? ''));
$tp      = null;
$matches = [];
$diag      = null;
$diagError = null;

if ($search !== '') {
  try {
    $like = '%' . $dbConn->real_escape_string($search) . '%';
    $stmt = prep($dbConn, "
        SELECT id, tp_id, name, mobile, referral_id, referral_type, referral_percentage, is_active
        FROM territory_partners
        WHERE name LIKE ? OR tp_id LIKE ?
        ORDER BY name ASC
        LIMIT 20
    ");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($matches) === 1) {
        $tp = $matches[0];
    } elseif (!empty($_GET['id'])) {
        $pickId = (int)$_GET['id'];
        foreach ($matches as $m) {
            if ((int)$m['id'] === $pickId) { $tp = $m; break; }
        }
    }
  } catch (Exception $e) {
    $diagError = $e->getMessage();
  }
}

if ($tp && !$diagError) {
  try {
    $internalId = (string)$tp['id'];
    $utype = 'territory_partner';

    // Exact same two queries wallet-history.php uses for the balance panel
    $stmt = prep($dbConn, "SELECT COALESCE(SUM(commission_amount),0) AS total FROM wallet_monthly_sls_report WHERE refer_by_usertype=? AND refer_by_userid=?");
    $stmt->bind_param("ss", $utype, $internalId);
    $stmt->execute();
    $totalCredits = (float)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = prep($dbConn, "SELECT COALESCE(SUM(amount),0) AS total FROM wallet_withdraw WHERE user_type=? AND user_id=? AND req_status='approved'");
    $stmt->bind_param("ss", $utype, $internalId);
    $stmt->execute();
    $totalWithdrawn = (float)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Exact same two queries wallet-history.php uses for the "Last 10" lists
    $stmt = prep($dbConn, "SELECT * FROM wallet_monthly_sls_report WHERE refer_by_usertype=? AND refer_by_userid=? ORDER BY from_date DESC LIMIT 10");
    $stmt->bind_param("ss", $utype, $internalId);
    $stmt->execute();
    $creditRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = prep($dbConn, "SELECT * FROM wallet_withdraw WHERE user_type=? AND user_id=? ORDER BY date DESC LIMIT 10");
    $stmt->bind_param("ss", $utype, $internalId);
    $stmt->execute();
    $debitRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Also: does this TP's tp_id (external code) accidentally have matching rows too?
    // (checks whether some historical rows were written against the wrong id convention)
    $stmt = prep($dbConn, "SELECT COUNT(*) AS c FROM wallet_monthly_sls_report WHERE refer_by_usertype=? AND refer_by_userid=?");
    $stmt->bind_param("ss", $utype, $tp['tp_id']);
    $stmt->execute();
    $countByTpCode = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = prep($dbConn, "SELECT COUNT(*) AS c FROM wallet_withdraw WHERE user_type=? AND user_id=?");
    $stmt->bind_param("ss", $utype, $tp['tp_id']);
    $stmt->execute();
    $withdrawCountByTpCode = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    // All wallet_monthly_sls_report rows anywhere that mention this TP's internal id or tp_id,
    // regardless of usertype match — catches usertype-string mismatches (e.g. stored value differs from 'territory_partner')
    $stmt = prep($dbConn, "
        SELECT * FROM wallet_monthly_sls_report
        WHERE refer_by_userid IN (?, ?)
        ORDER BY from_date DESC
        LIMIT 20
    ");
    $stmt->bind_param("ss", $internalId, $tp['tp_id']);
    $stmt->execute();
    $anyUsertypeRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = prep($dbConn, "
        SELECT * FROM wallet_withdraw
        WHERE user_id IN (?, ?)
        ORDER BY date DESC
        LIMIT 20
    ");
    $stmt->bind_param("ss", $internalId, $tp['tp_id']);
    $stmt->execute();
    $anyUsertypeWithdrawRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $diag = [
        'internal_id'    => $internalId,
        'utype'          => $utype,
        'totalCredits'   => $totalCredits,
        'totalWithdrawn' => $totalWithdrawn,
        'walletBalance'  => $totalCredits - $totalWithdrawn,
        'creditRows'     => $creditRows,
        'debitRows'      => $debitRows,
        'countByTpCode'         => $countByTpCode,
        'withdrawCountByTpCode' => $withdrawCountByTpCode,
        'anyUsertypeRows'         => $anyUsertypeRows,
        'anyUsertypeWithdrawRows' => $anyUsertypeWithdrawRows,
    ];
  } catch (Exception $e) {
    $diagError = $e->getMessage();
  }
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($title); ?></title>
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <style>
        body { background:#f3f4f6; }
        .wrap { max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem; }
        .card { border-radius:14px; border:1px solid #e5e7eb; margin-bottom:1.5rem; }
        .card-header { font-weight:700; background:#f8fafc; }
        table { font-size:0.85rem; }
        pre { white-space:pre-wrap; word-break:break-word; font-size:0.8rem; }
        .flag { color:#b91c1c; font-weight:700; }
        .ok { color:#059669; font-weight:700; }
    </style>
</head>
<body>
<div class="wrap">
    <h3 class="mb-3">🔎 TP Wallet Diagnostic <small class="text-muted">(read-only)</small></h3>

    <form class="card card-body mb-4" method="GET">
        <div class="input-group">
            <input type="text" class="form-control" name="q" placeholder="TP name or TP code (e.g. M.JAYABHARATHI)" value="<?php echo h($search); ?>">
            <button class="btn btn-primary" type="submit">Search</button>
        </div>
    </form>

    <?php if ($diagError): ?>
        <div class="alert alert-danger"><strong>Query error:</strong> <?php echo h($diagError); ?></div>
    <?php endif; ?>

    <?php if ($search !== '' && count($matches) > 1 && !$tp): ?>
        <div class="card">
            <div class="card-header">Multiple matches — pick one</div>
            <div class="card-body">
                <ul class="list-group">
                    <?php foreach ($matches as $m): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><?php echo h($m['name']); ?> (<?php echo h($m['tp_id']); ?>) — internal id <?php echo h($m['id']); ?></span>
                            <a class="btn btn-sm btn-outline-primary" href="?q=<?php echo urlencode($search); ?>&id=<?php echo (int)$m['id']; ?>">View</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php elseif ($search !== '' && !$tp): ?>
        <div class="alert alert-warning">No territory partner found matching "<?php echo h($search); ?>".</div>
    <?php endif; ?>

    <?php if ($tp && $diag): ?>
        <div class="card">
            <div class="card-header">TP Profile</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Name</th><td><?php echo h($tp['name']); ?></td></tr>
                    <tr><th>TP Code (tp_id, external)</th><td><?php echo h($tp['tp_id']); ?></td></tr>
                    <tr><th>Internal id (used in tp_invoices / wallet_monthly_sls_report)</th><td><?php echo h($tp['id']); ?></td></tr>
                    <tr><th>Active</th><td><?php echo (int)$tp['is_active'] === 1 ? 'Yes' : 'No'; ?></td></tr>
                    <tr><th>Referral setup</th><td>id=<?php echo h($tp['referral_id']); ?>, type=<?php echo h($tp['referral_type']); ?>, pct=<?php echo h($tp['referral_percentage']); ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Recomputed Balance (identical logic to wallet-history.php)</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Filter used</th><td>refer_by_usertype = 'territory_partner' AND refer_by_userid = '<?php echo h($diag['internal_id']); ?>'</td></tr>
                    <tr><th>Total Credits (SUM commission_amount)</th><td>₹<?php echo number_format($diag['totalCredits'], 2); ?></td></tr>
                    <tr><th>Total Withdrawn (approved)</th><td>₹<?php echo number_format($diag['totalWithdrawn'], 2); ?></td></tr>
                    <tr><th>Wallet Balance</th><td><strong>₹<?php echo number_format($diag['walletBalance'], 2); ?></strong></td></tr>
                    <tr><th>Rows returned by credit list query</th><td><?php echo count($diag['creditRows']); ?></td></tr>
                    <tr><th>Rows returned by debit list query</th><td><?php echo count($diag['debitRows']); ?></td></tr>
                </table>
                <?php if ($diag['walletBalance'] != 0 && count($diag['creditRows']) === 0 && count($diag['debitRows']) === 0): ?>
                    <div class="alert alert-danger mt-3 mb-0">
                        <strong>Confirmed anomaly:</strong> non-zero balance with zero rows in both list queries, using the identical filter.
                        This should be logically impossible from the SQL alone — see the extra checks below for what's actually in the table.
                    </div>
                <?php elseif ($diag['walletBalance'] == 0 && count($diag['creditRows']) === 0): ?>
                    <div class="alert alert-info mt-3 mb-0">
                        Balance and lists agree here (both zero/empty) — if the live page showed a non-zero balance for this TP, it may be
                        reading a stale/cached page, or you may be looking at a different TP than intended. Re-check on the live TP login.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Extra checks — data-shape mismatches</div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th>Rows matching refer_by_userid = TP CODE ('<?php echo h($tp['tp_id']); ?>') instead of internal id</th>
                        <td class="<?php echo $diag['countByTpCode'] > 0 ? 'flag' : ''; ?>">
                            <?php echo (int)$diag['countByTpCode']; ?><?php echo $diag['countByTpCode'] > 0 ? ' ⚠ found rows keyed by the wrong id — these are invisible to wallet-history.php' : ''; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>wallet_withdraw rows matching user_id = TP CODE instead of internal id</th>
                        <td class="<?php echo $diag['withdrawCountByTpCode'] > 0 ? 'flag' : ''; ?>">
                            <?php echo (int)$diag['withdrawCountByTpCode']; ?><?php echo $diag['withdrawCountByTpCode'] > 0 ? ' ⚠ found rows keyed by the wrong id' : ''; ?>
                        </td>
                    </tr>
                </table>
                <p class="text-muted mb-0">If either count above is non-zero, it means some rows for this TP were written using the wrong id convention (tp_id instead of the internal id, or vice-versa) — those rows would be silently excluded from wallet-history.php's queries even though they belong to this TP.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Raw rows — wallet_monthly_sls_report (refer_by_userid = internal id OR tp_id, any usertype)</div>
            <div class="card-body table-responsive">
                <?php if (empty($diag['anyUsertypeRows'])): ?>
                    <p class="text-muted mb-0">No rows found at all for this TP as a referrer — under either id convention. This means the referrer wallet genuinely has never been credited for this TP.</p>
                <?php else: ?>
                    <table class="table table-sm table-bordered">
                        <thead><tr><?php foreach (array_keys($diag['anyUsertypeRows'][0]) as $col) echo '<th>' . h($col) . '</th>'; ?></tr></thead>
                        <tbody>
                        <?php foreach ($diag['anyUsertypeRows'] as $row): ?>
                            <tr class="<?php echo ($row['refer_by_usertype'] !== 'territory_partner') ? 'table-warning' : ''; ?>">
                                <?php foreach ($row as $col => $val): ?><td><?php echo h($val); ?></td><?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="text-muted">Rows highlighted yellow have a <code>refer_by_usertype</code> other than <code>'territory_partner'</code> — those would NOT match wallet-history.php's filter even though the id matches.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Raw rows — wallet_withdraw (user_id = internal id OR tp_id, any usertype)</div>
            <div class="card-body table-responsive">
                <?php if (empty($diag['anyUsertypeWithdrawRows'])): ?>
                    <p class="text-muted mb-0">No withdrawal rows found at all for this TP under either id convention.</p>
                <?php else: ?>
                    <table class="table table-sm table-bordered">
                        <thead><tr><?php foreach (array_keys($diag['anyUsertypeWithdrawRows'][0]) as $col) echo '<th>' . h($col) . '</th>'; ?></tr></thead>
                        <tbody>
                        <?php foreach ($diag['anyUsertypeWithdrawRows'] as $row): ?>
                            <tr class="<?php echo ($row['user_type'] !== 'territory_partner') ? 'table-warning' : ''; ?>">
                                <?php foreach ($row as $col => $val): ?><td><?php echo h($val); ?></td><?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="text-muted">Rows highlighted yellow have a <code>user_type</code> other than <code>'territory_partner'</code> — check for a negative <code>amount</code> here too, since that alone can push the balance up with an empty-looking debit list if approved but with a bad sign.</p>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>
</body>
</html>
