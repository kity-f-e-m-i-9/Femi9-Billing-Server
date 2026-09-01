<?php
/**
 * TP Wallet Referral Income Calculator
 *
 * Admin-facing dry-run/execute/rollback tool for the referral-commission
 * wallet crediting that normally happens silently via
 * territory-partner/insert_wallet_referral.php (fires only when the TP who
 * hit target happens to load their own dashboard — if they never log in
 * that month, the referrer's commission never gets credited).
 *
 * This tool runs the exact same eligibility rule for a chosen month across
 * ALL territory partners, on demand, and shows per-TP detail: who hit
 * target and who got credited for it (the referrer — CP/TP/SS/Stockist/
 * SD/D), who was skipped because they were already credited (either by
 * this tool earlier or by the automatic dashboard trigger), and who did not
 * qualify and why (no referrer configured, no target configured, target
 * not met).
 *
 * Rows this tool inserts into wallet_monthly_sls_report are tagged with an
 * execution_id so they can be rolled back (hard-deleted) later without
 * touching rows created by the automatic trigger. The automatic trigger's
 * own dedup check is untouched by this tool, so the two can't double-credit
 * the same TP+month.
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

$logged_user_id   = (string)($_SESSION['LOGIN_USER_ID']   ?? '');
$logged_user_type = (string)($_SESSION['LOGIN_USER_TYPE'] ?? '');
$logged_user_name = (string)($_SESSION['LOGIN_USER']      ?? '');

$title         = "TP Wallet Referral Income Calculator";
$business_name = "Femi9 Billing";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$dbConn = $db_conn;

// =====================================================================
// DATABASE SETUP — self-migrating
// =====================================================================

function ensureWalletReferralSchema(mysqli $dbConn): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $col = $dbConn->query("SHOW COLUMNS FROM `wallet_monthly_sls_report` LIKE 'execution_id'");
    if ($col && $col->num_rows === 0) {
        $dbConn->query("ALTER TABLE `wallet_monthly_sls_report`
            ADD COLUMN `execution_id` VARCHAR(50) NULL DEFAULT NULL
                COMMENT 'Set only for rows inserted via tp-wallet-referral-calculator; NULL means auto-credited by the dashboard trigger'
            AFTER `remarks`");
    }

    $dbConn->query("CREATE TABLE IF NOT EXISTS `wallet_referral_execution_log` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `execution_id` VARCHAR(50) NOT NULL UNIQUE,
        `execution_mode` ENUM('dry_run', 'execute') NOT NULL,
        `month_year` VARCHAR(7) NOT NULL COMMENT 'Format: YYYY-MM',
        `total_tps_processed` INT NOT NULL DEFAULT 0,
        `total_credited` INT NOT NULL DEFAULT 0,
        `total_already_credited` INT NOT NULL DEFAULT 0,
        `total_not_eligible` INT NOT NULL DEFAULT 0,
        `total_commission_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `executed_by_user_id` VARCHAR(50) NOT NULL,
        `executed_by_user_type` VARCHAR(50) NOT NULL,
        `executed_by_user_name` VARCHAR(255) NOT NULL,
        `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `is_rolled_back` TINYINT(1) NOT NULL DEFAULT 0,
        `rolled_back_at` TIMESTAMP NULL DEFAULT NULL,
        `rolled_back_by_user_id` VARCHAR(50) NULL,
        `rolled_back_by_user_type` VARCHAR(50) NULL,
        INDEX `idx_execution_id` (`execution_id`),
        INDEX `idx_month_year` (`month_year`),
        INDEX `idx_is_rolled_back` (`is_rolled_back`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Execution log for TP wallet referral commission crediting with rollback tracking'");
}

ensureWalletReferralSchema($dbConn);

// =====================================================================
// REFERRER TYPE MAP — mirrors territory-partner/insert_wallet_referral.php
// =====================================================================

const WALLET_REFERRAL_TYPE_MAP = [
    'CP'       => ['internal' => 'channel_partner',   'table' => 'channel_partners',    'id_col' => 'cp_id',      'wid_col' => 'id',      'name_col' => 'name'],
    'TP'       => ['internal' => 'territory_partner', 'table' => 'territory_partners',  'id_col' => 'tp_id',      'wid_col' => 'id',      'name_col' => 'name'],
    'SS'       => ['internal' => 'super_stockiest',   'table' => 'super_stockiest',      'id_col' => 'useridtext', 'wid_col' => 'temp_id', 'name_col' => 'name'],
    'Stockist' => ['internal' => 'stockiest',         'table' => 'stockiest',            'id_col' => 'useridtext', 'wid_col' => 'temp_id', 'name_col' => 'name'],
    'SD'       => ['internal' => 'super_distributor', 'table' => 'super_distributor',   'id_col' => 'useridtext', 'wid_col' => 'temp_id', 'name_col' => 'name'],
    'D'        => ['internal' => 'distributor',       'table' => 'distributor',         'id_col' => 'useridtext', 'wid_col' => 'temp_id', 'name_col' => 'name'],
];

// =====================================================================
// HELPER FUNCTIONS
// =====================================================================

function getMonthRange(string $monthYear): array
{
    $year    = (int)substr($monthYear, 0, 4);
    $month   = (int)substr($monthYear, 5, 2);
    $lastDay = (int)date('t', mktime(0, 0, 0, $month, 1, $year));

    return [
        'from'  => sprintf('%04d-%02d-01', $year, $month),
        'to'    => sprintf('%04d-%02d-%02d', $year, $month, $lastDay),
        'month' => date('M', mktime(0, 0, 0, $month, 1, $year)),
        'year'  => (string)$year,
    ];
}

function getAllTerritoryPartnersForReferral(mysqli $dbConn): array
{
    // NOTE: tp_invoices.territory_partner_id and wallet_monthly_sls_report.user_id
    // both store territory_partners.id (the internal PK — same value used as
    // $Login_user_IDvl for a TP session), NOT tp.tp_id (the external display
    // code). insert_wallet_referral.php confirms this: it looks up
    // "territory_partners WHERE id=$Login_user_IDvl" and uses that same value
    // for the tp_invoices sum and the wallet_monthly_sls_report insert/dedup.
    // tp.tp_id is display-only here.
    $stmt = $dbConn->prepare("
        SELECT
            tp.id    AS internal_id,
            tp.tp_id AS tp_code,
            tp.name  AS user_name,
            tp.referral_id,
            tp.referral_type,
            tp.referral_percentage,
            COALESCE(SUM(pln.target_amount), 0) AS target_amount
        FROM territory_partners tp
        LEFT JOIN territory_partner_locations tpl ON tpl.territory_partner_id = tp.id
        LEFT JOIN partner_location_nodes pln      ON pln.id = tpl.location_id
        GROUP BY tp.id, tp.tp_id, tp.name, tp.referral_id, tp.referral_type, tp.referral_percentage
        ORDER BY tp.name ASC
    ");
    if (!$stmt) { error_log("getAllTerritoryPartnersForReferral prepare failed: " . $dbConn->error); return []; }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getTpNetPurchase(mysqli $dbConn, string $internalId, string $from, string $to): array
{
    // Target achievement is napkin-only — diaper invoices don't count.
    $stmt = $dbConn->prepare("SELECT COALESCE(SUM(total_amount),0) AS total FROM tp_invoices
        WHERE territory_partner_id = ? AND invoice_date BETWEEN ? AND ? AND product_type = 'napkin'");
    $stmt->bind_param("sss", $internalId, $from, $to);
    $stmt->execute();
    $purchase = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    // Returns don't carry product_type directly — inherit it from the
    // originating tp_invoices row via invoice number, so diaper returns
    // stay excluded too.
    $stmt = $dbConn->prepare("SELECT COALESCE(SUM(urs.total),0) AS total FROM user_return_stock urs
        JOIN tp_invoices ti ON ti.invoice_number = urs.invnumber COLLATE utf8mb4_unicode_ci
        WHERE urs.from_usertype = 'territory_partner' AND urs.from_userid = ? AND urs.date BETWEEN ? AND ?
        AND ti.product_type = 'napkin'");
    $stmt->bind_param("sss", $internalId, $from, $to);
    $stmt->execute();
    $returns = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    return ['purchase' => $purchase, 'returns' => $returns, 'net' => $purchase - $returns];
}

function findExistingReferralCredit(mysqli $dbConn, string $internalId, string $from, string $to): ?array
{
    $stmt = $dbConn->prepare("SELECT commission_amount, execution_id FROM wallet_monthly_sls_report
        WHERE user_type = 'territory_partner' AND user_id = ?
          AND from_date = ? AND to_date = ? AND commission_type = 'Refferral' LIMIT 1");
    $stmt->bind_param("sss", $internalId, $from, $to);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function resolveReferrer(mysqli $dbConn, string $referralType, string $referralId): ?array
{
    if (!isset(WALLET_REFERRAL_TYPE_MAP[$referralType])) return null;
    $map = WALLET_REFERRAL_TYPE_MAP[$referralType];

    $stmt = $dbConn->prepare("SELECT `{$map['wid_col']}` AS wid, `{$map['name_col']}` AS wname
        FROM `{$map['table']}` WHERE `{$map['id_col']}` = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("s", $referralId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['wid'])) return null;

    return [
        'internal' => $map['internal'],
        'wid'      => (string)$row['wid'],
        'name'     => (string)($row['wname'] ?? ''),
        'type_label' => $referralType,
    ];
}

/**
 * Evaluate one TP's referral-commission eligibility for the given month.
 * Mirrors territory-partner/insert_wallet_referral.php's rule set exactly,
 * but never writes — it only reports what would happen / what already
 * happened.
 */
function evaluateTpReferral(mysqli $dbConn, array $tp, array $range): array
{
    $result = [
        'internal_id'      => (string)$tp['internal_id'],
        'tp_code'          => $tp['tp_code'],
        'user_name'        => $tp['user_name'],
        'target_amount'    => (float)$tp['target_amount'],
        'net_purchase'     => 0.00,
        'purchase'         => 0.00,
        'returns'          => 0.00,
        'referral_type'    => $tp['referral_type'] ?? '',
        'referral_percentage' => (float)($tp['referral_percentage'] ?? 0),
        'referrer_name'    => '',
        'referrer_type'    => '',
        'referrer_id'      => $tp['referral_id'] ?? '',
        'commission_amount'=> 0.00,
        'status'           => 'not_eligible',   // credited | already_credited | not_eligible
        'reason'           => '',
        'existing_source'  => '', // 'this_tool' | 'auto_dashboard' — only for already_credited
    ];

    // 1. Referral setup present?
    if (empty($tp['referral_id']) || empty($tp['referral_type']) || !$tp['referral_percentage']) {
        $result['reason'] = 'No referrer configured for this TP';
        return $result;
    }

    // 2. Target configured?
    if ($result['target_amount'] <= 0) {
        $result['reason'] = 'No monthly target configured (no location assigned)';
        return $result;
    }

    // 3. Net purchase vs target
    $net = getTpNetPurchase($dbConn, (string)$tp['internal_id'], $range['from'], $range['to']);
    $result['purchase']     = $net['purchase'];
    $result['returns']      = $net['returns'];
    $result['net_purchase'] = $net['net'];

    if ($net['net'] < $result['target_amount']) {
        $shortfall = $result['target_amount'] - $net['net'];
        $result['reason'] = sprintf('Target not met — short by ₹%s', inr_format($shortfall, 2));
        return $result;
    }

    // 4. Referrer resolvable?
    $referrer = resolveReferrer($dbConn, $tp['referral_type'], (string)$tp['referral_id']);
    if (!$referrer) {
        $result['reason'] = sprintf('Referrer account not found (type=%s, id=%s)', $tp['referral_type'], $tp['referral_id']);
        return $result;
    }
    $result['referrer_name'] = $referrer['name'];
    $result['referrer_type'] = $referrer['type_label'];

    // 5. Already credited this TP+month? (dedup — either by this tool or the auto trigger)
    $existing = findExistingReferralCredit($dbConn, (string)$tp['internal_id'], $range['from'], $range['to']);
    if ($existing) {
        $result['status']            = 'already_credited';
        $result['commission_amount'] = (float)$existing['commission_amount'];
        $result['existing_source']   = empty($existing['execution_id']) ? 'auto_dashboard' : 'this_tool';
        $result['reason']            = $result['existing_source'] === 'auto_dashboard'
            ? 'Already credited automatically (TP visited their dashboard)'
            : 'Already credited by a previous run of this tool';
        return $result;
    }

    // 6. Eligible — compute commission
    $result['status']            = 'credited';
    $result['commission_amount'] = round($net['net'] * $result['referral_percentage'] / 100, 2);
    $result['reason']            = 'Target met — commission due to referrer';

    return $result;
}

function processTpWalletReferralCalculation(mysqli $dbConn, string $monthYear, string $mode): array
{
    global $logged_user_id, $logged_user_type, $logged_user_name;

    $results = [
        'success'      => false,
        'message'      => '',
        'execution_id' => '',
        'summary'      => [
            'total_tps_processed'    => 0,
            'total_credited'         => 0,
            'total_already_credited' => 0,
            'total_not_eligible'     => 0,
            'total_commission_amount'=> 0.00,
        ],
        'details' => [],
    ];

    try {
        if (empty($logged_user_id) || empty($logged_user_type) || empty($logged_user_name)) {
            throw new Exception("Session variables are empty. Please log in again.");
        }

        $range = getMonthRange($monthYear);
        $executionId = 'WREF-' . date('YmdHis') . '-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        $results['execution_id'] = $executionId;

        $tps = getAllTerritoryPartnersForReferral($dbConn);
        $details = [];
        foreach ($tps as $tp) {
            $details[] = evaluateTpReferral($dbConn, $tp, $range);
        }

        $totalCredited = 0; $totalAlready = 0; $totalNotEligible = 0; $totalAmount = 0.00;
        foreach ($details as $d) {
            if ($d['status'] === 'credited')            { $totalCredited++;  $totalAmount += $d['commission_amount']; }
            elseif ($d['status'] === 'already_credited') { $totalAlready++; }
            else                                          { $totalNotEligible++; }
        }

        $results['summary'] = [
            'total_tps_processed'     => count($details),
            'total_credited'          => $totalCredited,
            'total_already_credited'  => $totalAlready,
            'total_not_eligible'      => $totalNotEligible,
            'total_commission_amount' => $totalAmount,
        ];
        $results['details'] = $details;

        if ($mode === 'execute') {
            if (!$dbConn->begin_transaction()) {
                throw new Exception("Failed to start transaction: " . $dbConn->error);
            }

            foreach ($details as $d) {
                if ($d['status'] !== 'credited') continue;

                $referrerMap = WALLET_REFERRAL_TYPE_MAP[$d['referral_type']] ?? null;
                if (!$referrerMap) continue; // defensive — shouldn't happen, evaluate() already validated

                $referrer = resolveReferrer($dbConn, $d['referral_type'], (string)$d['referrer_id']);
                if (!$referrer) continue; // defensive re-check inside the transaction

                $stmt = $dbConn->prepare("
                    INSERT INTO wallet_monthly_sls_report
                    (user_type, user_id, from_date, to_date, month, year,
                     total_sls_amount, target_sls_amount, target_reached,
                     refer_by_usertype, refer_by_userid, commission_percentage,
                     commission_amount, commission_type, remarks, execution_id)
                    VALUES
                    ('territory_partner', ?, ?, ?, ?, ?,
                     ?, ?, 'yes',
                     ?, ?, ?,
                     ?, 'Refferral', 'Nil', ?)
                ");
                if (!$stmt) throw new Exception("Prepare failed (wallet_monthly_sls_report): " . $dbConn->error);

                $stmt->bind_param(
                    "sssssddssdds",
                    $d['internal_id'], $range['from'], $range['to'], $range['month'], $range['year'],
                    $d['net_purchase'], $d['target_amount'],
                    $referrer['internal'], $referrer['wid'], $d['referral_percentage'],
                    $d['commission_amount'], $executionId
                );

                if (!$stmt->execute()) {
                    throw new Exception("Insert failed for TP {$d['tp_code']}: " . $stmt->error);
                }
                $stmt->close();
            }

            $stmt = $dbConn->prepare("
                INSERT INTO wallet_referral_execution_log
                (execution_id, execution_mode, month_year, total_tps_processed, total_credited,
                 total_already_credited, total_not_eligible, total_commission_amount,
                 executed_by_user_id, executed_by_user_type, executed_by_user_name)
                VALUES (?, 'execute', ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) throw new Exception("Prepare failed (wallet_referral_execution_log): " . $dbConn->error);
            $stmt->bind_param(
                "ssiiiidsss",
                $executionId, $monthYear,
                $results['summary']['total_tps_processed'],
                $totalCredited, $totalAlready, $totalNotEligible, $totalAmount,
                $logged_user_id, $logged_user_type, $logged_user_name
            );
            if (!$stmt->execute()) throw new Exception("Insert failed (execution log): " . $stmt->error);
            $stmt->close();

            if (!$dbConn->commit()) throw new Exception("Failed to commit transaction: " . $dbConn->error);

            $results['message'] = sprintf(
                'Execution complete. %d TP(s) credited (₹%s total), %d already credited, %d not eligible.',
                $totalCredited, inr_format($totalAmount, 2), $totalAlready, $totalNotEligible
            );
        } else {
            $results['message'] = sprintf(
                'Dry run complete. %d TP(s) would be credited (₹%s total), %d already credited, %d not eligible.',
                $totalCredited, inr_format($totalAmount, 2), $totalAlready, $totalNotEligible
            );
        }

        $results['success'] = true;

    } catch (Exception $e) {
        error_log("processTpWalletReferralCalculation error: " . $e->getMessage());
        if ($mode === 'execute') { $dbConn->rollback(); }
        $results['message'] = 'Error: ' . $e->getMessage();
    }

    return $results;
}

function rollbackWalletReferralExecution(mysqli $dbConn, string $executionId): array
{
    global $logged_user_id, $logged_user_type;
    $result = ['success' => false, 'message' => ''];

    try {
        $stmt = $dbConn->prepare("SELECT is_rolled_back FROM wallet_referral_execution_log WHERE execution_id = ?");
        $stmt->bind_param("s", $executionId);
        $stmt->execute();
        $exec = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$exec) throw new Exception("Execution not found.");
        if ((int)$exec['is_rolled_back'] === 1) throw new Exception("This execution has already been rolled back.");

        $dbConn->begin_transaction();

        $stmt = $dbConn->prepare("DELETE FROM wallet_monthly_sls_report WHERE execution_id = ? AND commission_type = 'Refferral'");
        $stmt->bind_param("s", $executionId);
        $stmt->execute();
        $deletedRows = $stmt->affected_rows;
        $stmt->close();

        $stmt = $dbConn->prepare("
            UPDATE wallet_referral_execution_log
            SET is_rolled_back = 1, rolled_back_at = NOW(),
                rolled_back_by_user_id = ?, rolled_back_by_user_type = ?
            WHERE execution_id = ?
        ");
        $stmt->bind_param("sss", $logged_user_id, $logged_user_type, $executionId);
        $stmt->execute();
        $stmt->close();

        $dbConn->commit();

        $result['success'] = true;
        $result['message'] = sprintf(
            'Rollback successful. Removed %d wallet credit(s). Referrer wallet balances will reflect this on next view.',
            $deletedRows
        );
    } catch (Exception $e) {
        $dbConn->rollback();
        $result['message'] = 'Rollback failed: ' . $e->getMessage();
        error_log("rollbackWalletReferralExecution error: " . $e->getMessage());
    }

    return $result;
}

function getAvailableWalletReferralExecutions(mysqli $dbConn): array
{
    $stmt = $dbConn->prepare("
        SELECT execution_id, month_year, total_credited, total_commission_amount,
               executed_by_user_name, executed_at, is_rolled_back
        FROM wallet_referral_execution_log
        WHERE execution_mode = 'execute'
        ORDER BY executed_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// =====================================================================
// HANDLE FORM SUBMISSIONS
// =====================================================================

$actionResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $actionResult = ['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page and try again.'];
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'dry_run' || $action === 'execute') {
            $monthYear = $_POST['month_year'] ?? '';
            if (empty($monthYear) || !preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
                $actionResult = ['success' => false, 'message' => 'Invalid month/year format. Please select a valid month.'];
            } else {
                $actionResult = processTpWalletReferralCalculation($dbConn, $monthYear, $action);
            }
        } elseif ($action === 'rollback') {
            $executionId = $_POST['execution_id'] ?? '';
            if (empty($executionId)) {
                $actionResult = ['success' => false, 'message' => 'Please select an execution to rollback.'];
            } else {
                $actionResult = rollbackWalletReferralExecution($dbConn, $executionId);
            }
        }
    }
}

$availableExecutions = getAvailableWalletReferralExecutions($dbConn);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars($business_name, ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />

    <style>
    body { background: linear-gradient(180deg, #f3f4f6 0%, #ffffff 100%); min-height: 100vh; }
    .page-header {
        background: white; padding: 2rem; margin-bottom: 2rem; border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #f0f0f0;
    }
    .page-title {
        font-size: 2rem; font-weight: 700;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        margin: 0; display: inline-block;
    }
    .page-subtitle { color: #6b7280; font-size: 1rem; margin-top: 0.5rem; font-weight: 500; }
    .page-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;
        padding: 0.75rem 1.5rem; border-radius: 12px; font-size: 0.9rem; font-weight: 600;
        box-shadow: 0 4px 12px rgba(102,126,234,0.3);
    }
    .card {
        border: none; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        transition: transform 0.3s ease, box-shadow 0.3s ease; overflow: hidden;
        background: white; border: 1px solid #f0f0f0;
    }
    .card:hover { transform: translateY(-5px); box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;
        border: none; padding: 1.5rem 2rem; font-weight: 600; font-size: 1.2rem;
    }
    .card-body { padding: 2rem; }
    .stat-card { border-radius: 16px; padding: 1.5rem; text-align: center; position: relative; overflow: hidden; }
    .stat-card::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
        background: linear-gradient(90deg, var(--card-color) 0%, var(--card-color-light) 100%);
    }
    .stat-card.primary { --card-color: #3b82f6; --card-color-light: #60a5fa; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); }
    .stat-card.success { --card-color: #10b981; --card-color-light: #34d399; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); }
    .stat-card.warning { --card-color: #f59e0b; --card-color-light: #fbbf24; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); }
    .stat-card.danger  { --card-color: #ef4444; --card-color-light: #f87171; background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); }
    .stat-value { font-size: 2.5rem; font-weight: 800; color: var(--card-color); margin: 0.5rem 0; }
    .stat-label { font-size: 0.9rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
    .form-control { border: 2px solid #e5e7eb; border-radius: 12px; padding: 0.75rem 1rem; font-size: 0.95rem; transition: all 0.3s ease; }
    .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 4px rgba(102,126,234,0.1); }
    .form-label { font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.9rem; }
    .btn { border-radius: 12px; padding: 0.75rem 1.5rem; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; border: none; }
    .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    .btn-primary:hover { background: linear-gradient(135deg, #5568d3 0%, #6a4291 100%); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(102,126,234,0.3); color: white; }
    .btn-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
    .btn-success:hover { background: linear-gradient(135deg, #059669 0%, #047857 100%); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(16,185,129,0.3); color: white; }
    .btn-danger  { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
    .btn-danger:hover  { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(239,68,68,0.3); color: white; }
    .alert { border-radius: 16px; border: none; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
    .alert-success { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; border-left: 4px solid #10b981; }
    .alert-danger  { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b; border-left: 4px solid #ef4444; }
    .alert-info    { background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); color: #3730a3; border-left: 4px solid #6366f1; font-size: 0.9rem; }
    .badge { padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; font-size: 0.85rem; }
    .badge-success  { background: #d1fae5; color: #065f46; }
    .badge-danger   { background: #fee2e2; color: #991b1b; }
    .badge-primary  { background: #dbeafe; color: #1e40af; }
    .badge-warning  { background: #fef3c7; color: #92400e; }
    .table thead th {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); color: #475569; font-weight: 700;
        font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 1rem; border: none;
    }
    .table tbody td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .table tbody tr:hover { background: #f8fafc; }
    .table tbody tr.row-credited { background: #f0fdf4 !important; }
    .table tbody tr.row-already { background: #fffbeb !important; }
    .table tbody tr.row-not-eligible { background: #fafafa !important; }
    .execution-item { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px; padding: 1rem; margin-bottom: 0.75rem; transition: all 0.3s ease; }
    .execution-item:hover { transform: translateX(5px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .criteria-list { list-style: none; padding-left: 0; }
    .criteria-list li { padding: 0.5rem 0; padding-left: 1.5rem; position: relative; }
    .criteria-list li::before { content: '✓'; position: absolute; left: 0; color: #10b981; font-weight: 700; }
    .filter-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .filter-tab {
        border: 2px solid #e5e7eb; background: white; border-radius: 10px; padding: 0.5rem 1rem;
        font-weight: 600; font-size: 0.85rem; color: #6b7280; cursor: pointer; transition: all 0.2s ease;
    }
    .filter-tab.active { border-color: #667eea; color: #667eea; background: #eef2ff; }
    .app-content { padding: 2rem 0; background: transparent; }
    .content-wrapper { background: transparent; padding-top: 1rem; }
    .container-fluid { max-width: 1500px; }
    </style>
</head>
<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php"); ?>
            <?php include("femi_menu.php"); ?>
        </div>

        <div class="app-container">
            <?php include("app-header.php"); ?>

            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">

                        <!-- Page Header -->
                        <div class="page-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h1 class="page-title">💰 TP Wallet Referral Income Calculator</h1>
                                    <p class="page-subtitle">Referral commission wallet crediting — dry run, execute &amp; rollback</p>
                                </div>
                                <div>
                                    <span class="page-badge">Territory Partner</span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Result Alert -->
                        <?php if ($actionResult !== null): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="alert alert-<?php echo $actionResult['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                                        <strong><?php echo $actionResult['success'] ? '✅ Success!' : '❌ Error!'; ?></strong>
                                        <?php echo htmlspecialchars($actionResult['message'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($actionResult['success'] && !empty($actionResult['execution_id'])): ?>
                                            <br><small>Execution ID: <code><?php echo htmlspecialchars($actionResult['execution_id'], ENT_QUOTES, 'UTF-8'); ?></code></small>
                                        <?php endif; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Calculation Form -->
                        <div class="row mb-4">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <i class="material-icons-outlined" style="vertical-align:middle;margin-right:8px">event</i>
                                        Select Month for Referral Commission Run
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" id="calculationForm">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" id="actionInput" value="">

                                            <div class="row">
                                                <div class="col-md-6 mb-4">
                                                    <label for="month_year" class="form-label">
                                                        <i class="material-icons-outlined" style="vertical-align:middle;font-size:18px;margin-right:4px">calendar_month</i>
                                                        Month &amp; Year
                                                    </label>
                                                    <input type="month" class="form-control" id="month_year" name="month_year" required
                                                        max="<?php echo date('Y-m'); ?>"
                                                        value="<?php echo date('Y-m', strtotime('-1 month')); ?>">
                                                    <small class="text-muted">The sales month being evaluated (TP purchases in this month vs. target)</small>
                                                </div>
                                            </div>

                                            <div class="alert alert-info" role="alert">
                                                <strong>💡 How referral income works:</strong><br>
                                                Each Territory Partner can have a referrer (CP / another TP / SS / Stockist / SD / D) set on their profile,
                                                with a referral %. If the TP's net purchases (invoices − returns) for the selected month meet or exceed
                                                their location target, the referrer's wallet is credited:
                                                <br>
                                                <code style="background:rgba(102,126,234,0.1);padding:4px 8px;border-radius:4px;color:#3730a3;">
                                                    Commission = Net Purchase × Referral % / 100
                                                </code>
                                                <br><br>
                                                <strong>⚠️ Why this tool exists:</strong> normally this credit only fires automatically when the TP who hit
                                                target loads their own dashboard. If they never log in that month, the referrer never gets paid. This tool
                                                lets you check and credit it manually, for any month, without waiting on that.
                                                <br><br>
                                                <strong>🔁 Safe to re-run:</strong> a TP already credited for a month (by this tool or automatically) shows as
                                                <em>Already Credited</em> and is skipped — no double payment.
                                            </div>

                                            <div class="d-flex gap-3">
                                                <button type="button" class="btn btn-primary" onclick="submitForm('dry_run')">
                                                    <i class="material-icons-outlined" style="vertical-align:middle;font-size:18px">preview</i>
                                                    Dry Run (Preview Only)
                                                </button>
                                                <button type="button" class="btn btn-success" onclick="submitForm('execute')">
                                                    <i class="material-icons-outlined" style="vertical-align:middle;font-size:18px">check_circle</i>
                                                    Execute (Credit Wallets)
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Rollback Section -->
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <i class="material-icons-outlined" style="vertical-align:middle;margin-right:8px">undo</i>
                                        Rollback Execution
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" id="rollbackForm" onsubmit="return confirmRollback()">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="rollback">

                                            <div class="mb-3">
                                                <label for="execution_id" class="form-label">Select Execution</label>
                                                <select class="form-control" id="execution_id" name="execution_id" required>
                                                    <option value="" disabled selected>-- Select Execution --</option>
                                                    <?php foreach ($availableExecutions as $exec): ?>
                                                        <?php if ((int)$exec['is_rolled_back'] === 0): ?>
                                                            <option value="<?php echo htmlspecialchars($exec['execution_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                <?php
                                                                echo htmlspecialchars(sprintf(
                                                                    '%s | %d credited | ₹%s | %s',
                                                                    $exec['month_year'],
                                                                    $exec['total_credited'],
                                                                    inr_format((float)$exec['total_commission_amount'], 2),
                                                                    date('d M Y H:i', strtotime($exec['executed_at']))
                                                                ), ENT_QUOTES, 'UTF-8');
                                                                ?>
                                                            </option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted">Rollback deletes only the wallet credits inserted by that run — auto-credited rows are untouched</small>
                                            </div>

                                            <button type="submit" class="btn btn-danger w-100">
                                                <i class="material-icons-outlined" style="vertical-align:middle;font-size:18px">undo</i>
                                                Rollback Execution
                                            </button>
                                        </form>

                                        <?php if (!empty($availableExecutions)): ?>
                                            <div class="mt-4">
                                                <small class="text-muted" style="font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Recent Executions:</small>
                                                <div class="mt-3" style="max-height:300px;overflow-y:auto;">
                                                    <?php foreach (array_slice($availableExecutions, 0, 5) as $exec): ?>
                                                        <div class="execution-item">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <strong style="color:#374151"><?php echo htmlspecialchars($exec['month_year'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                                <?php if ((int)$exec['is_rolled_back'] === 1): ?>
                                                                    <span class="badge badge-warning">Rolled Back</span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-success">Active</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <small class="text-muted">
                                                                💰 <?php echo (int)$exec['total_credited']; ?> credited &nbsp;|&nbsp;
                                                                ₹<?php echo inr_format((float)$exec['total_commission_amount'], 2); ?>
                                                            </small>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Results Section -->
                        <?php if ($actionResult !== null && $actionResult['success'] && isset($actionResult['summary'])): ?>

                            <?php
                            $summary   = $actionResult['summary'];
                            $isDryRun  = strpos($actionResult['message'], 'Dry run') !== false;
                            $modeLabel = $isDryRun ? ' (Preview — no changes saved)' : '';
                            ?>

                            <!-- Summary Cards -->
                            <div class="row mb-4">
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="stat-card primary">
                                        <div class="stat-label">Total TPs</div>
                                        <div class="stat-value"><?php echo (int)$summary['total_tps_processed']; ?></div>
                                        <small class="text-muted">Processed<?php echo $modeLabel; ?></small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="stat-card success">
                                        <div class="stat-label">✅ <?php echo $isDryRun ? 'Will Credit' : 'Credited'; ?></div>
                                        <div class="stat-value"><?php echo (int)$summary['total_credited']; ?></div>
                                        <small class="text-muted">Referrers getting paid</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="stat-card warning">
                                        <div class="stat-label">🕓 Already Credited</div>
                                        <div class="stat-value"><?php echo (int)$summary['total_already_credited']; ?></div>
                                        <small class="text-muted">Skipped — no double pay</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="stat-card danger">
                                        <div class="stat-label">❌ Not Eligible</div>
                                        <div class="stat-value"><?php echo (int)$summary['total_not_eligible']; ?></div>
                                        <small class="text-muted">Did not qualify</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="stat-card primary" style="text-align:left;padding:1.25rem 1.75rem;">
                                        <span class="stat-label">🎁 Total Commission <?php echo $isDryRun ? 'To Be Credited' : 'Credited'; ?></span>
                                        <span class="stat-value" style="font-size:1.8rem;">₹<?php echo inr_format((float)$summary['total_commission_amount'], 2); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Detailed Results Table -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <i class="material-icons-outlined" style="vertical-align:middle;margin-right:8px">analytics</i>
                                            Detailed Results — Who Got Paid, Who Didn't
                                            <?php if ($isDryRun): ?>
                                                <span style="font-size:0.85rem;font-weight:400;opacity:0.85;margin-left:8px">(Preview — no data saved)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body">

                                            <div class="filter-tabs">
                                                <div class="filter-tab active" data-filter="all">All (<?php echo count($actionResult['details']); ?>)</div>
                                                <div class="filter-tab" data-filter="credited">✅ Credited (<?php echo (int)$summary['total_credited']; ?>)</div>
                                                <div class="filter-tab" data-filter="already_credited">🕓 Already Credited (<?php echo (int)$summary['total_already_credited']; ?>)</div>
                                                <div class="filter-tab" data-filter="not_eligible">❌ Not Eligible (<?php echo (int)$summary['total_not_eligible']; ?>)</div>
                                            </div>

                                            <div class="table-responsive">
                                                <table class="table table-hover" id="resultsTable">
                                                    <thead>
                                                        <tr>
                                                            <th>Territory Partner</th>
                                                            <th>Target</th>
                                                            <th>Net Purchase</th>
                                                            <th>Referrer</th>
                                                            <th>Referral %</th>
                                                            <th>Commission</th>
                                                            <th>Status / Reason</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($actionResult['details'] as $detail): ?>
                                                            <?php
                                                            $rowClass = $detail['status'] === 'credited' ? 'row-credited'
                                                                : ($detail['status'] === 'already_credited' ? 'row-already' : 'row-not-eligible');
                                                            ?>
                                                            <tr class="<?php echo $rowClass; ?>" data-status="<?php echo htmlspecialchars($detail['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                <td>
                                                                    <strong style="color:#111827"><?php echo htmlspecialchars($detail['user_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($detail['tp_code'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                                </td>
                                                                <td>₹<?php echo inr_format((float)$detail['target_amount'], 0); ?></td>
                                                                <td>
                                                                    ₹<?php echo inr_format((float)$detail['net_purchase'], 0); ?>
                                                                    <?php if ((float)$detail['returns'] > 0): ?>
                                                                        <br><small class="text-muted">(₹<?php echo inr_format((float)$detail['purchase'], 0); ?> − ₹<?php echo inr_format((float)$detail['returns'], 0); ?> returns)</small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($detail['referrer_name'])): ?>
                                                                        <strong><?php echo htmlspecialchars(ucwords($detail['referrer_name']), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                                                        <small class="text-muted"><?php echo htmlspecialchars($detail['referral_type'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string)$detail['referrer_id'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                                    <?php elseif (!empty($detail['referral_type'])): ?>
                                                                        <span class="text-muted"><?php echo htmlspecialchars($detail['referral_type'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string)$detail['referrer_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">—</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?php echo $detail['referral_percentage'] > 0 ? htmlspecialchars((string)$detail['referral_percentage'], ENT_QUOTES, 'UTF-8') . '%' : '—'; ?></td>
                                                                <td>
                                                                    <?php if ((float)$detail['commission_amount'] > 0): ?>
                                                                        <strong style="color:#059669;font-size:1.05rem">₹<?php echo inr_format((float)$detail['commission_amount'], 2); ?></strong>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">—</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if ($detail['status'] === 'credited'): ?>
                                                                        <span class="badge badge-success"><?php echo $isDryRun ? '✅ Will Credit' : '✅ Credited'; ?></span>
                                                                    <?php elseif ($detail['status'] === 'already_credited'): ?>
                                                                        <span class="badge badge-warning">🕓 Already Credited</span>
                                                                        <br><small class="text-muted"><?php echo $detail['existing_source'] === 'auto_dashboard' ? 'via dashboard auto-trigger' : 'via this tool'; ?></small>
                                                                    <?php else: ?>
                                                                        <span class="badge badge-danger">❌ Not Eligible</span>
                                                                    <?php endif; ?>
                                                                    <br><small class="text-muted"><?php echo htmlspecialchars($detail['reason'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php endif; ?>

                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>

    <script>
    function submitForm(action) {
        if (action === 'execute') {
            if (!confirm('⚠️ Are you sure you want to EXECUTE and credit wallets?\n\nThis will:\n• Credit referrer wallets for every TP that hit their target this month\n• Skip TPs already credited (no double payment)\n\nThis action can be rolled back later.')) {
                return;
            }
        }
        document.getElementById('actionInput').value = action;
        document.getElementById('calculationForm').submit();
    }

    function confirmRollback() {
        const executionId = document.getElementById('execution_id').value;
        if (!executionId) {
            alert('⚠️ Please select an execution to rollback');
            return false;
        }
        return confirm('⚠️ Are you sure you want to ROLLBACK this execution?\n\nThis will permanently delete the wallet credits inserted by this run (auto-credited rows are not touched).\n\nThis action cannot be undone.\n\nContinue with rollback?');
    }

    document.querySelectorAll('.filter-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.filter-tab').forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            const filter = tab.getAttribute('data-filter');
            document.querySelectorAll('#resultsTable tbody tr').forEach(function (row) {
                row.style.display = (filter === 'all' || row.getAttribute('data-status') === filter) ? '' : 'none';
            });
        });
    });
    </script>
</body>
</html>
