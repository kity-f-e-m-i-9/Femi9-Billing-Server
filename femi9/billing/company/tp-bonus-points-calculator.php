<?php
/**
 * TP Bonus Points Calculator - Monthly Target Achievement Bonus System
 *
 * Territory Partner adaptation of bonus-points-calculator.php (Super Stockist /
 * Stockist). Same calculation logic (weekly cumulative thresholds, bonus
 * formula, deactivation/rollback flow) — reuses the shared bonus_points_history
 * / bonus_execution_log / bonus_deactivation_log / reward_points tables with
 * 'territory_partner' added to their user_type ENUMs.
 *
 * Differences from the original, required by TP's data shape:
 *   - Population: territory_partners (not super_stockiest/stockiest).
 *   - Monthly target: SUM of partner_location_nodes.target_amount across all
 *     locations assigned to the TP (via territory_partner_locations) — TP has
 *     no category/target lookup table like SS/Stockiest do.
 *   - Real cash paid: read from tp_advance_payments (TP's own advance-payment
 *     table), not the polymorphic advance_payments table.
 *   - Active/inactive flag: territory_partners.is_active (0/1), not an
 *     account_status ENUM string — normalized to 'active'/'deactive' so the
 *     shared eligibility/deactivation logic is unchanged.
 *   - Week 4 threshold: 100% of target (matches Stockist's rule).
 *
 * Deactivation Rule:
 *   Territory Partner : week4_cumulative < 100% of target AND currently active
 *
 * Rollback restores is_active = 1 for all TPs deactivated by that execution.
 *
 * @author Femi9 Billing System
 * @version 1.0
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once("checksession.php");
require_once('config.php');

$logged_user_id   = (string)($_SESSION['LOGIN_USER_ID']   ?? '');
$logged_user_type = (string)($_SESSION['LOGIN_USER_TYPE'] ?? '');
$logged_user_name = (string)($_SESSION['LOGIN_USER']      ?? '');

$title         = "TP Bonus Points Calculator";
$business_name = "Femi9 Billing";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$dbConn = $db_conn;

// =====================================================================
// DATABASE SETUP — self-migrating: extend shared ENUMs with 'territory_partner'
// =====================================================================

function ensureTpBonusSchema(mysqli $dbConn): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $targets = [
        'bonus_points_history'   => "MODIFY COLUMN `user_type` ENUM('super_stockiest','stockiest','territory_partner') NOT NULL",
        'bonus_deactivation_log' => "MODIFY COLUMN `user_type` ENUM('super_stockiest','stockiest','territory_partner') NOT NULL",
        'reward_points'          => "MODIFY COLUMN `user_type` ENUM('super_stockiest','stockiest','distributor','super_distributor','c_and_f','territory_partner') NOT NULL",
    ];

    foreach ($targets as $table => $alterClause) {
        $col = $dbConn->query("SHOW COLUMNS FROM `$table` LIKE 'user_type'");
        if (!$col) continue;
        $row = $col->fetch_assoc();
        if ($row && strpos($row['Type'], 'territory_partner') === false) {
            $dbConn->query("ALTER TABLE `$table` $alterClause");
        }
    }
}

ensureTpBonusSchema($dbConn);

// =====================================================================
// HELPER FUNCTIONS
// =====================================================================

function getWeekRanges(string $monthYear): array
{
    $year    = (int)substr($monthYear, 0, 4);
    $month   = (int)substr($monthYear, 5, 2);
    $lastDay = (int)date('t', mktime(0, 0, 0, $month, 1, $year));

    return [
        'week1' => [
            'start' => sprintf('%04d-%02d-01', $year, $month),
            'end'   => sprintf('%04d-%02d-07', $year, $month),
            'label' => 'Week 1 (Day 1-7)',
        ],
        'week2' => [
            'start' => sprintf('%04d-%02d-08', $year, $month),
            'end'   => sprintf('%04d-%02d-14', $year, $month),
            'label' => 'Week 2 (Day 8-14)',
        ],
        'week3' => [
            'start' => sprintf('%04d-%02d-15', $year, $month),
            'end'   => sprintf('%04d-%02d-21', $year, $month),
            'label' => 'Week 3 (Day 15-21)',
        ],
        'week4' => [
            'start' => sprintf('%04d-%02d-22', $year, $month),
            'end'   => sprintf('%04d-%02d-%02d', $year, $month, $lastDay),
            'label' => sprintf('Week 4 (Day 22-%d)', $lastDay),
        ],
    ];
}

function getTpAdvancePaymentInRange(
    mysqli $dbConn,
    string $tpCode,
    string $startDate,
    string $endDate
): float {
    $stmt = $dbConn->prepare("
        SELECT COALESCE(SUM(tap.amount), 0) AS total
        FROM tp_advance_payments tap
        INNER JOIN territory_partners tp ON tp.id = tap.territory_partner_id
        WHERE tp.tp_id = ?
          AND tap.payment_date >= ?
          AND tap.payment_date <= ?
    ");

    if (!$stmt) {
        error_log("getTpAdvancePaymentInRange prepare failed: " . $dbConn->error);
        return 0.00;
    }

    $stmt->bind_param("sss", $tpCode, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();

    return (float)($row['total'] ?? 0.00);
}

function getTerritoryPartnersWithTargets(mysqli $dbConn): array
{
    $stmt = $dbConn->prepare("
        SELECT
            tp.id,
            tp.tp_id                        AS user_id,
            tp.name                         AS user_name,
            tp.is_active                    AS is_active,
            COALESCE(SUM(pln.target_amount), 0) AS target_amount
        FROM territory_partners tp
        LEFT JOIN territory_partner_locations tpl ON tpl.territory_partner_id = tp.id
        LEFT JOIN partner_location_nodes pln      ON pln.id = tpl.location_id
        GROUP BY tp.id, tp.tp_id, tp.name, tp.is_active
        ORDER BY tp.name ASC
    ");

    if (!$stmt) {
        error_log("getTerritoryPartnersWithTargets prepare failed: " . $dbConn->error);
        return [];
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $users  = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Normalize is_active (0/1) to the account_status string convention used
    // by the shared eligibility/deactivation logic.
    foreach ($users as &$u) {
        $u['account_status'] = ((int)$u['is_active'] === 1) ? 'active' : 'deactive';
        $u['category_id']    = 0;
        $u['category_name']  = 'Territory Partner';
    }
    unset($u);

    return $users;
}

/**
 * Determine whether a TP should be deactivated based on their calculation result.
 *
 * Territory Partner: week4_cumulative < 100% of target AND currently active
 *
 * Returns empty string if no deactivation needed, or the reason string if deactivation needed.
 */
function getTpDeactivationReason(array $result, string $currentAccountStatus): string
{
    // Only deactivate currently active TPs
    if ($currentAccountStatus !== 'active') {
        return '';
    }

    $week4Cumulative = (float)$result['week4_cumulative'];
    $monthlyTarget   = (float)$result['monthly_target'];
    $threshold       = $monthlyTarget * 1.00;

    if ($week4Cumulative < $threshold) {
        return sprintf(
            'Total paid ₹%s is below 100%% target threshold ₹%s',
            inr_format($week4Cumulative, 2),
            inr_format($threshold, 2)
        );
    }

    return '';
}

function calculateTpBonusPoints(
    mysqli $dbConn,
    array  $tp,
    string $monthYear
): array {
    $weekRanges   = getWeekRanges($monthYear);
    $targetAmount = (float)$tp['target_amount'];

    $week1Required = $targetAmount * 0.25;
    $week2Required = $targetAmount * 0.50;
    $week3Required = $targetAmount * 0.75;
    $week4Required = $targetAmount * 1.00;

    $week1Amount = getTpAdvancePaymentInRange($dbConn, $tp['user_id'], $weekRanges['week1']['start'], $weekRanges['week1']['end']);
    $week2Amount = getTpAdvancePaymentInRange($dbConn, $tp['user_id'], $weekRanges['week2']['start'], $weekRanges['week2']['end']);
    $week3Amount = getTpAdvancePaymentInRange($dbConn, $tp['user_id'], $weekRanges['week3']['start'], $weekRanges['week3']['end']);
    $week4Amount = getTpAdvancePaymentInRange($dbConn, $tp['user_id'], $weekRanges['week4']['start'], $weekRanges['week4']['end']);

    $week1Cumulative = $week1Amount;
    $week2Cumulative = $week1Amount + $week2Amount;
    $week3Cumulative = $week1Amount + $week2Amount + $week3Amount;
    $week4Cumulative = $week1Amount + $week2Amount + $week3Amount + $week4Amount;

    $week1Pass = $week1Cumulative >= $week1Required;
    $week2Pass = $week2Cumulative >= $week2Required;
    $week3Pass = $week3Cumulative >= $week3Required;
    $week4Pass = $week4Cumulative >= $week4Required;

    $isEligible = $week1Pass && $week2Pass && $week3Pass && $week4Pass;

    $totalAdvancePaid  = $week4Cumulative;
    $bonusPoints       = 0.00;
    $bonusCalculation  = '';

    if ($isEligible && $totalAdvancePaid > 0) {
        $bonusPoints      = ($totalAdvancePaid / 100) * 0.10;
        $bonusCalculation = sprintf(
            '(%.2f / 100) × 10%% = %.2f points',
            $totalAdvancePaid,
            $bonusPoints
        );
    }

    $currentAccountStatus = (string)($tp['account_status'] ?? 'active');

    $calcResult = [
        'user_id'              => $tp['user_id'],
        'user_name'            => $tp['user_name'],
        'user_type'            => 'territory_partner',
        'account_status'       => $currentAccountStatus,
        'category_id'          => (int)$tp['category_id'],
        'category_name'        => $tp['category_name'],
        'monthly_target'       => $targetAmount,
        'total_advance_paid'   => $totalAdvancePaid,
        'week1_amount'         => $week1Amount,
        'week1_cumulative'     => $week1Cumulative,
        'week1_required'       => $week1Required,
        'week1_status'         => $week1Pass ? 'pass' : 'fail',
        'week2_amount'         => $week2Amount,
        'week2_cumulative'     => $week2Cumulative,
        'week2_required'       => $week2Required,
        'week2_status'         => $week2Pass ? 'pass' : 'fail',
        'week3_amount'         => $week3Amount,
        'week3_cumulative'     => $week3Cumulative,
        'week3_required'       => $week3Required,
        'week3_status'         => $week3Pass ? 'pass' : 'fail',
        'week4_amount'         => $week4Amount,
        'week4_cumulative'     => $week4Cumulative,
        'week4_required'       => $week4Required,
        'week4_status'         => $week4Pass ? 'pass' : 'fail',
        'eligibility_status'   => $isEligible ? 'eligible' : 'not_eligible',
        'bonus_points_awarded' => $bonusPoints,
        'bonus_calculation'    => $bonusCalculation,
        'will_be_deactivated'  => false,
        'deactivation_reason'  => '',
    ];

    $deactivationReason = getTpDeactivationReason($calcResult, $currentAccountStatus);
    if (!empty($deactivationReason)) {
        $calcResult['will_be_deactivated'] = true;
        $calcResult['deactivation_reason'] = $deactivationReason;
    }

    return $calcResult;
}

// =====================================================================
// MAIN PROCESSING FUNCTION
// =====================================================================

function processTpBonusCalculation(
    mysqli $dbConn,
    string $monthYear,
    string $mode = 'dry_run',
    bool   $applyDeactivation = true
): array {
    error_log("=== Starting processTpBonusCalculation v1.0 ===");
    error_log("Month: $monthYear, Mode: $mode");

    $results = [
        'success'      => false,
        'message'      => '',
        'execution_id' => '',
        'summary'      => [
            'total_users_processed'  => 0,
            'total_eligible'         => 0,
            'total_ineligible'       => 0,
            'total_bonus_points'     => 0.00,
            'total_deactivated'      => 0,
            'total_already_deactive' => 0,
        ],
        'details'      => [],
    ];

    try {
        global $logged_user_id, $logged_user_type, $logged_user_name;

        $logged_user_id   = strval($logged_user_id);
        $logged_user_type = strval($logged_user_type);
        $logged_user_name = strval($logged_user_name);

        if (empty($logged_user_id) || empty($logged_user_type) || empty($logged_user_name)) {
            throw new Exception("Session variables are empty. Please log in again.");
        }

        $executionId              = 'TPBNS-' . date('YmdHis') . '-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        $results['execution_id']  = $executionId;

        error_log("Execution ID: $executionId");
        error_log("Fetching territory partners...");

        $territoryPartners = getTerritoryPartnersWithTargets($dbConn);

        error_log("Territory Partners: " . count($territoryPartners));

        $allUsers = [];

        foreach ($territoryPartners as $tp) {
            $allUsers[] = calculateTpBonusPoints($dbConn, $tp, $monthYear);
        }

        error_log("Total calculated: " . count($allUsers));

        $totalEligible        = 0;
        $totalIneligible      = 0;
        $totalBonusPoints     = 0.00;
        $totalWillDeactivate  = 0;
        $totalAlreadydeactive = 0;

        foreach ($allUsers as $u) {
            if ($u['eligibility_status'] === 'eligible') {
                $totalEligible++;
                $totalBonusPoints += (float)$u['bonus_points_awarded'];
            } else {
                $totalIneligible++;
            }
            if ($u['will_be_deactivated']) {
                $totalWillDeactivate++;
            }
            if ($u['account_status'] !== 'active') {
                $totalAlreadydeactive++;
            }
        }

        $results['summary'] = [
            'total_users_processed'  => count($allUsers),
            'total_eligible'         => $totalEligible,
            'total_ineligible'       => $totalIneligible,
            'total_bonus_points'     => $totalBonusPoints,
            'total_deactivated'      => $totalWillDeactivate,
            'total_already_deactive' => $totalAlreadydeactive,
        ];

        $results['details'] = $allUsers;

        // ----------------------------------------------------------------
        // EXECUTE MODE — write to DB
        // ----------------------------------------------------------------
        if ($mode === 'execute') {

            error_log("Starting transaction...");

            if (!$dbConn->begin_transaction()) {
                throw new Exception("Failed to start transaction: " . $dbConn->error);
            }

            $actualDeactivated = 0;

            foreach ($allUsers as $userResult) {
                // ── 1. Insert bonus_points_history ───────────────────────
                $stmt = $dbConn->prepare("
                    INSERT INTO bonus_points_history (
                        user_id, user_type, user_name, month_year,
                        category_id, category_name, monthly_target, total_advance_paid,
                        week1_amount, week1_cumulative, week1_required, week1_status,
                        week2_amount, week2_cumulative, week2_required, week2_status,
                        week3_amount, week3_cumulative, week3_required, week3_status,
                        week4_amount, week4_cumulative, week4_required, week4_status,
                        eligibility_status, bonus_points_awarded, bonus_calculation,
                        execution_id, executed_by_user_id, executed_by_user_type, executed_by_user_name
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?, ?
                    )
                ");

                if (!$stmt) {
                    throw new Exception("Prepare failed (bonus_points_history): " . $dbConn->error);
                }

                $userId           = $userResult['user_id'];
                $userType         = $userResult['user_type'];
                $userName         = $userResult['user_name'];
                $categoryId       = (int)$userResult['category_id'];
                $categoryName     = $userResult['category_name'];
                $monthlyTarget    = (float)$userResult['monthly_target'];
                $totalAdvancePaid = (float)$userResult['total_advance_paid'];
                $w1a  = (float)$userResult['week1_amount'];
                $w1c  = (float)$userResult['week1_cumulative'];
                $w1r  = (float)$userResult['week1_required'];
                $w1s  = $userResult['week1_status'];
                $w2a  = (float)$userResult['week2_amount'];
                $w2c  = (float)$userResult['week2_cumulative'];
                $w2r  = (float)$userResult['week2_required'];
                $w2s  = $userResult['week2_status'];
                $w3a  = (float)$userResult['week3_amount'];
                $w3c  = (float)$userResult['week3_cumulative'];
                $w3r  = (float)$userResult['week3_required'];
                $w3s  = $userResult['week3_status'];
                $w4a  = (float)$userResult['week4_amount'];
                $w4c  = (float)$userResult['week4_cumulative'];
                $w4r  = (float)$userResult['week4_required'];
                $w4s  = $userResult['week4_status'];
                $elig = $userResult['eligibility_status'];
                $bpts = (float)$userResult['bonus_points_awarded'];
                $bcal = $userResult['bonus_calculation'];

                $bindParams = [
                    $userId, $userType, $userName, $monthYear,
                    $categoryId, $categoryName, $monthlyTarget, $totalAdvancePaid,
                    $w1a, $w1c, $w1r, $w1s,
                    $w2a, $w2c, $w2r, $w2s,
                    $w3a, $w3c, $w3r, $w3s,
                    $w4a, $w4c, $w4r, $w4s,
                    $elig, $bpts, $bcal,
                    $executionId, $logged_user_id, $logged_user_type, $logged_user_name
                ];

                $types = '';
                foreach ($bindParams as $param) {
                    if (is_int($param)) $types .= 'i';
                    elseif (is_float($param)) $types .= 'd';
                    else $types .= 's';
                }

                $stmt->bind_param($types, ...$bindParams);

                if (!$stmt->execute()) {
                    throw new Exception("Insert failed (bonus_points_history) for TP $userId: " . $stmt->error);
                }
                $stmt->close();

                // ── 2. Insert reward_points for eligible TPs ──────────────
                if ($userResult['eligibility_status'] === 'eligible' && $userResult['bonus_points_awarded'] > 0) {
                    $description = sprintf(
                        'Monthly Target Achievement Bonus - %s (Target: Rs.%s, Paid: Rs.%s)',
                        $monthYear,
                        inr_format($userResult['monthly_target'], 2),
                        inr_format($userResult['total_advance_paid'], 2)
                    );

                    $stmt = $dbConn->prepare("
                        INSERT INTO reward_points (
                            user_id, user_type, points, transaction_type,
                            transaction_id, transaction_date, description,
                            created_by_user_id, created_by_user_type
                        ) VALUES (?, ?, ?, 'bonus_target_achievement', ?, NOW(), ?, ?, ?)
                    ");

                    if (!$stmt) {
                        throw new Exception("Prepare failed (reward_points): " . $dbConn->error);
                    }

                    $stmt->bind_param(
                        "ssdssss",
                        $userId, $userType, $bpts,
                        $executionId, $description,
                        $logged_user_id, $logged_user_type
                    );

                    if (!$stmt->execute()) {
                        throw new Exception("Insert failed (reward_points) for TP $userId: " . $stmt->error);
                    }
                    $stmt->close();
                }

                // ── 3. Deactivation logic (optional) ───────────────────────
                if ($applyDeactivation && $userResult['will_be_deactivated']) {
                    error_log("Deactivating TP: {$userId} — {$userResult['deactivation_reason']}");

                    $stmt = $dbConn->prepare("
                        UPDATE territory_partners
                        SET is_active = 0
                        WHERE tp_id = ?
                          AND is_active = 1
                    ");

                    if (!$stmt) {
                        throw new Exception("Prepare failed (deactivation UPDATE) for TP $userId: " . $dbConn->error);
                    }

                    $stmt->bind_param("s", $userId);

                    if (!$stmt->execute()) {
                        throw new Exception("Update failed (deactivation) for TP $userId: " . $stmt->error);
                    }

                    $rowsAffected = $stmt->affected_rows;
                    $stmt->close();

                    if ($rowsAffected > 0) {
                        $actualDeactivated++;
                        $deactivationReason = $userResult['deactivation_reason'];

                        $stmt = $dbConn->prepare("
                            INSERT INTO bonus_deactivation_log (
                                execution_id, user_id, user_type, user_name,
                                previous_status, deactivation_reason
                            ) VALUES (?, ?, ?, ?, 'active', ?)
                        ");

                        if (!$stmt) {
                            throw new Exception("Prepare failed (bonus_deactivation_log): " . $dbConn->error);
                        }

                        $stmt->bind_param(
                            "sssss",
                            $executionId, $userId, $userType, $userName,
                            $deactivationReason
                        );

                        if (!$stmt->execute()) {
                            throw new Exception("Insert failed (bonus_deactivation_log) for TP $userId: " . $stmt->error);
                        }
                        $stmt->close();
                    }
                }
            }

            // ── 4. Insert execution log ───────────────────────────────────
            $totalUsersProcessed = (int)$results['summary']['total_users_processed'];
            $totalEligibleCount  = (int)$results['summary']['total_eligible'];
            $totalIneligCount    = (int)$results['summary']['total_ineligible'];
            $totalBonusPts       = (float)$results['summary']['total_bonus_points'];

            $stmt = $dbConn->prepare("
                INSERT INTO bonus_execution_log (
                    execution_id, execution_mode, month_year,
                    total_users_processed, total_eligible_users, total_ineligible_users,
                    total_bonus_points_awarded, total_accounts_deactivated,
                    executed_by_user_id, executed_by_user_type, executed_by_user_name
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception("Prepare failed (bonus_execution_log): " . $dbConn->error);
            }

            $stmt->bind_param(
                "sssiiidisss",
                $executionId, $mode, $monthYear,
                $totalUsersProcessed, $totalEligibleCount, $totalIneligCount,
                $totalBonusPts, $actualDeactivated,
                $logged_user_id, $logged_user_type, $logged_user_name
            );

            if (!$stmt->execute()) {
                throw new Exception("Insert failed (bonus_execution_log): " . $stmt->error);
            }
            $stmt->close();

            if (!$dbConn->commit()) {
                throw new Exception("Failed to commit transaction: " . $dbConn->error);
            }

            $results['summary']['total_deactivated'] = $actualDeactivated;

            error_log("Transaction committed. Deactivated: $actualDeactivated TPs.");
            $results['message'] = $applyDeactivation
                ? sprintf(
                    'Bonus points calculated and awarded successfully! %d account(s) deactivated.',
                    $actualDeactivated
                )
                : 'Bonus points calculated and awarded successfully! Account deactivation was skipped (not applied for this execution).';

        } else {
            $results['message'] = sprintf(
                'Dry run completed. No database changes made. %d account(s) would be deactivated if deactivation is applied.',
                $totalWillDeactivate
            );
        }

        $results['success'] = true;
        error_log("=== processTpBonusCalculation completed ===");

    } catch (Exception $e) {
        error_log("=== EXCEPTION in processTpBonusCalculation ===");
        error_log($e->getMessage() . " | " . $e->getFile() . ":" . $e->getLine());
        error_log($e->getTraceAsString());

        if ($mode === 'execute') {
            $dbConn->rollback();
            error_log("Transaction rolled back.");
        }

        $results['message'] = 'Error: ' . $e->getMessage();
    }

    return $results;
}

// =====================================================================
// ROLLBACK FUNCTION
// =====================================================================

function rollbackTpExecution(mysqli $dbConn, string $executionId): array
{
    $result = ['success' => false, 'message' => ''];

    try {
        global $logged_user_id, $logged_user_type;

        $stmt = $dbConn->prepare("
            SELECT id, is_rolled_back, total_bonus_points_awarded, total_accounts_deactivated
            FROM bonus_execution_log
            WHERE execution_id = ?
        ");
        $stmt->bind_param("s", $executionId);
        $stmt->execute();
        $execution = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$execution) {
            throw new Exception("Execution not found.");
        }

        if ((int)$execution['is_rolled_back'] === 1) {
            throw new Exception("This execution has already been rolled back.");
        }

        $dbConn->begin_transaction();

        // ── 1. Soft-delete bonus_points_history rows ─────────────────────
        $stmt = $dbConn->prepare("
            UPDATE bonus_points_history
            SET rolled_back_at           = NOW(),
                rolled_back_by_user_id   = ?,
                rolled_back_by_user_type = ?
            WHERE execution_id = ?
              AND rolled_back_at IS NULL
        ");
        $stmt->bind_param("sss", $logged_user_id, $logged_user_type, $executionId);
        $stmt->execute();
        $historyRows = $stmt->affected_rows;
        $stmt->close();

        // ── 2. Delete reward_points entries ──────────────────────────────
        $stmt = $dbConn->prepare("
            DELETE FROM reward_points
            WHERE transaction_type = 'bonus_target_achievement'
              AND transaction_id   = ?
        ");
        $stmt->bind_param("s", $executionId);
        $stmt->execute();
        $rewardRows = $stmt->affected_rows;
        $stmt->close();

        // ── 3. Restore deactivated TPs ────────────────────────────────────
        $stmt = $dbConn->prepare("
            SELECT user_id, user_type, user_name
            FROM bonus_deactivation_log
            WHERE execution_id = ?
              AND restored_at  IS NULL
        ");
        $stmt->bind_param("s", $executionId);
        $stmt->execute();
        $deactivatedUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $restoredCount = 0;

        foreach ($deactivatedUsers as $deactivatedUser) {
            $userId = $deactivatedUser['user_id'];

            $updateStmt = $dbConn->prepare("
                UPDATE territory_partners
                SET is_active = 1
                WHERE tp_id = ?
            ");

            if (!$updateStmt) {
                throw new Exception("Prepare failed (restore account) for TP $userId: " . $dbConn->error);
            }

            $updateStmt->bind_param("s", $userId);

            if (!$updateStmt->execute()) {
                throw new Exception("Update failed (restore account) for TP $userId: " . $updateStmt->error);
            }
            $updateStmt->close();
            $restoredCount++;
        }

        // ── 4. Mark deactivation log entries as restored ─────────────────
        if ($restoredCount > 0) {
            $stmt = $dbConn->prepare("
                UPDATE bonus_deactivation_log
                SET restored_at           = NOW(),
                    restored_by_user_id   = ?,
                    restored_by_user_type = ?
                WHERE execution_id = ?
                  AND restored_at  IS NULL
            ");
            $stmt->bind_param("sss", $logged_user_id, $logged_user_type, $executionId);
            $stmt->execute();
            $stmt->close();
        }

        // ── 5. Mark execution log as rolled back ──────────────────────────
        $stmt = $dbConn->prepare("
            UPDATE bonus_execution_log
            SET is_rolled_back           = 1,
                rolled_back_at           = NOW(),
                rolled_back_by_user_id   = ?,
                rolled_back_by_user_type = ?
            WHERE execution_id = ?
        ");
        $stmt->bind_param("sss", $logged_user_id, $logged_user_type, $executionId);
        $stmt->execute();
        $stmt->close();

        $dbConn->commit();

        $result['success'] = true;
        $result['message'] = sprintf(
            'Rollback successful. Removed %d bonus records, %d reward point entries, and restored %d deactivated account(s) to active.',
            $historyRows,
            $rewardRows,
            $restoredCount
        );

        error_log("Rollback complete for $executionId — restored: $restoredCount TPs");

    } catch (Exception $e) {
        $dbConn->rollback();
        $result['message'] = 'Rollback failed: ' . $e->getMessage();
        error_log("Rollback error for $executionId: " . $e->getMessage());
    }

    return $result;
}

// =====================================================================
// GET AVAILABLE EXECUTIONS (this feature's own, i.e. TPBNS- prefixed)
// =====================================================================

function getAvailableTpExecutions(mysqli $dbConn): array
{
    $stmt = $dbConn->prepare("
        SELECT
            execution_id,
            month_year,
            total_users_processed,
            total_eligible_users,
            total_bonus_points_awarded,
            total_accounts_deactivated,
            executed_by_user_name,
            executed_at,
            is_rolled_back
        FROM bonus_execution_log
        WHERE execution_mode = 'execute'
          AND execution_id LIKE 'TPBNS-%'
        ORDER BY executed_at DESC
        LIMIT 20
    ");

    $stmt->execute();
    $result     = $stmt->get_result();
    $executions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $executions;
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
                $applyDeactivation = isset($_POST['apply_deactivation']);
                $actionResult = processTpBonusCalculation($dbConn, $monthYear, $action, $applyDeactivation);
            }
        } elseif ($action === 'rollback') {
            $executionId = $_POST['execution_id'] ?? '';

            if (empty($executionId)) {
                $actionResult = ['success' => false, 'message' => 'Please select an execution to rollback.'];
            } else {
                $actionResult = rollbackTpExecution($dbConn, $executionId);
            }
        }
    }
}

$availableExecutions = getAvailableTpExecutions($dbConn);

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
    body {
        background: linear-gradient(180deg, #f3f4f6 0%, #ffffff 100%);
        min-height: 100vh;
    }
    .page-header {
        background: white;
        padding: 2rem;
        margin-bottom: 2rem;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        border: 1px solid #f0f0f0;
    }
    .page-title {
        font-size: 2rem;
        font-weight: 700;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0;
        display: inline-block;
    }
    .page-subtitle { color: #6b7280; font-size: 1rem; margin-top: 0.5rem; font-weight: 500; }
    .page-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-size: 0.9rem;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(102,126,234,0.3);
    }
    .card {
        border: none;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
        background: white;
        border: 1px solid #f0f0f0;
    }
    .card:hover { transform: translateY(-5px); box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 1.5rem 2rem;
        font-weight: 600;
        font-size: 1.2rem;
    }
    .card-body { padding: 2rem; }
    .stat-card {
        border-radius: 16px;
        padding: 1.5rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--card-color) 0%, var(--card-color-light) 100%);
    }
    .stat-card.primary   { --card-color: #3b82f6; --card-color-light: #60a5fa; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); }
    .stat-card.success   { --card-color: #10b981; --card-color-light: #34d399; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); }
    .stat-card.danger    { --card-color: #ef4444; --card-color-light: #f87171; background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); }
    .stat-card.warning   { --card-color: #f59e0b; --card-color-light: #fbbf24; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); }
    .stat-card.dark-red  { --card-color: #b91c1c; --card-color-light: #dc2626; background: linear-gradient(135deg, #fce7e7 0%, #fbd0d0 100%); }
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
    .badge-dark-red { background: #fce7e7; color: #7f1d1d; }
    .table thead th {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        color: #475569; font-weight: 700; font-size: 0.85rem;
        text-transform: uppercase; letter-spacing: 0.5px;
        padding: 1rem; border: none;
    }
    .table tbody td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .table tbody tr:hover { background: #f8fafc; }
    .table tbody tr.row-deactivate { background: #fff5f5 !important; }
    .table tbody tr.row-deactivate:hover { background: #fee2e2 !important; }
    .execution-item { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px; padding: 1rem; margin-bottom: 0.75rem; transition: all 0.3s ease; }
    .execution-item:hover { transform: translateX(5px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .criteria-list { list-style: none; padding-left: 0; }
    .criteria-list li { padding: 0.5rem 0; padding-left: 1.5rem; position: relative; }
    .criteria-list li::before { content: '✓'; position: absolute; left: 0; color: #10b981; font-weight: 700; }
    .week-status { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; font-size: 1.1rem; }
    .week-status.pass { background: #d1fae5; }
    .week-status.fail { background: #fee2e2; }
    .deactivation-tooltip { font-size: 0.78rem; color: #b91c1c; display: block; margin-top: 4px; font-style: italic; }
    .app-content { padding: 2rem 0; background: transparent; }
    .content-wrapper { background: transparent; padding-top: 1rem; }
    .container-fluid { max-width: 1400px; }
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
                                    <h1 class="page-title">🎯 TP Bonus Points Calculator</h1>
                                    <p class="page-subtitle">Monthly Target Achievement Bonus System</p>
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
                                        Select Month for Bonus Calculation
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
                                                    <input
                                                        type="month"
                                                        class="form-control"
                                                        id="month_year"
                                                        name="month_year"
                                                        required
                                                        max="<?php echo date('Y-m'); ?>"
                                                        value="<?php echo date('Y-m', strtotime('-1 month')); ?>"
                                                    >
                                                    <small class="text-muted">Select the month to calculate bonus points for</small>
                                                </div>
                                            </div>

                                            <div class="alert alert-info" role="alert">
                                                <strong>💡 Bonus Calculation Formula:</strong><br>
                                                <code style="background:rgba(102,126,234,0.1);padding:4px 8px;border-radius:4px;color:#3730a3;">
                                                    Bonus Points = (Total Advance Payment / 100) × 10%
                                                </code>
                                                <br><br>
                                                <strong>✅ Eligibility Criteria (ALL weeks must pass):</strong>
                                                <ul class="criteria-list mb-2 mt-2">
                                                    <li><strong>Week 1 (Day 1-7):</strong> Cumulative ≥ 25% of monthly target</li>
                                                    <li><strong>Week 2 (Day 1-14):</strong> Cumulative ≥ 50% of monthly target</li>
                                                    <li><strong>Week 3 (Day 1-21):</strong> Cumulative ≥ 75% of monthly target</li>
                                                    <li><strong>Week 4 (Day 22-end):</strong> Cumulative ≥ <span style="color:#667eea;font-weight:700">100% of monthly target</span></li>
                                                </ul>
                                                <strong>🔴 Account Deactivation Rule:</strong>
                                                <ul class="criteria-list mb-0 mt-2">
                                                    <li><strong>Territory Partner:</strong> Total paid &lt; 100% of target → account set to <em>inactive</em></li>
                                                    <li>Only currently <em>active</em> TPs are affected. Rollback restores them.</li>
                                                </ul>
                                                <br>
                                                <strong>📍 Monthly Target:</strong> sum of target_amount across all locations assigned to the TP.
                                            </div>

                                            <div class="form-check mb-3">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    id="apply_deactivation"
                                                    name="apply_deactivation"
                                                    value="1"
                                                    checked
                                                >
                                                <label class="form-check-label" for="apply_deactivation">
                                                    Deactivate accounts that did not meet target (applies only to Execute)
                                                </label>
                                                <div><small class="text-muted">Uncheck to award bonus points only, without deactivating any accounts.</small></div>
                                            </div>

                                            <div class="d-flex gap-3">
                                                <button type="button" class="btn btn-primary" onclick="submitForm('dry_run')">
                                                    <i class="material-icons-outlined" style="vertical-align:middle;font-size:18px">preview</i>
                                                    Dry Run (Preview Only)
                                                </button>
                                                <button type="button" class="btn btn-success" onclick="submitForm('execute')">
                                                    <i class="material-icons-outlined" style="vertical-align:middle;font-size:18px">check_circle</i>
                                                    Execute (Award Points)
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
                                                                    '%s | %s pts | %s deact. | %s',
                                                                    $exec['month_year'],
                                                                    inr_format((float)$exec['total_bonus_points_awarded'], 2),
                                                                    $exec['total_accounts_deactivated'] ?? 0,
                                                                    date('d M Y H:i', strtotime($exec['executed_at']))
                                                                ), ENT_QUOTES, 'UTF-8');
                                                                ?>
                                                            </option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted">Rollback will also restore deactivated TPs to active</small>
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
                                                                👤 <?php echo (int)$exec['total_eligible_users']; ?> eligible &nbsp;|&nbsp;
                                                                ⭐ <?php echo inr_format((float)$exec['total_bonus_points_awarded'], 2); ?> pts
                                                                <?php if (($exec['total_accounts_deactivated'] ?? 0) > 0): ?>
                                                                    &nbsp;|&nbsp; 🔴 <?php echo (int)$exec['total_accounts_deactivated']; ?> deactivated
                                                                <?php endif; ?>
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
                            $summary    = $actionResult['summary'];
                            $isDryRun   = !isset($actionResult['execution_id']) || empty($actionResult['execution_id'])
                                          || strpos($actionResult['message'], 'Dry run') !== false;
                            $modeLabel  = $isDryRun ? ' (Preview — no changes saved)' : '';
                            ?>

                            <!-- Summary Cards -->
                            <div class="row mb-4">
                                <div class="col-md-2 col-sm-6 mb-3">
                                    <div class="stat-card primary">
                                        <div class="stat-label">Total TPs</div>
                                        <div class="stat-value"><?php echo (int)$summary['total_users_processed']; ?></div>
                                        <small class="text-muted">Processed<?php echo $modeLabel; ?></small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-6 mb-3">
                                    <div class="stat-card success">
                                        <div class="stat-label">✅ Eligible</div>
                                        <div class="stat-value"><?php echo (int)$summary['total_eligible']; ?></div>
                                        <small class="text-muted">Qualified TPs</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-6 mb-3">
                                    <div class="stat-card danger">
                                        <div class="stat-label">❌ Not Eligible</div>
                                        <div class="stat-value"><?php echo (int)$summary['total_ineligible']; ?></div>
                                        <small class="text-muted">Did not qualify</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-6 mb-3">
                                    <div class="stat-card warning">
                                        <div class="stat-label">🎁 Bonus Points</div>
                                        <div class="stat-value"><?php echo inr_format((float)$summary['total_bonus_points'], 2); ?></div>
                                        <small class="text-muted">Total awarded</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-6 mb-3">
                                    <div class="stat-card dark-red">
                                        <div class="stat-label">🔴 <?php echo $isDryRun ? 'Will Deactivate' : 'Deactivated'; ?></div>
                                        <div class="stat-value"><?php echo (int)$summary['total_deactivated']; ?></div>
                                        <small class="text-muted">Accounts</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-6 mb-3">
                                    <div class="stat-card warning">
                                        <div class="stat-label">⚠️ Already inactive</div>
                                        <div class="stat-value"><?php echo (int)$summary['total_already_deactive']; ?></div>
                                        <small class="text-muted">Skipped</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Deactivation preview banner for dry run -->
                            <?php if ($isDryRun && (int)$summary['total_deactivated'] > 0): ?>
                                <div class="alert alert-danger mb-4" role="alert">
                                    <strong>⚠️ Dry Run Warning:</strong>
                                    <?php echo (int)$summary['total_deactivated']; ?> account(s) highlighted in red below <strong>would be deactivated</strong> if you click Execute.
                                    Rows marked 🔴 are affected TPs.
                                </div>
                            <?php endif; ?>

                            <!-- Detailed Results Table -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <i class="material-icons-outlined" style="vertical-align:middle;margin-right:8px">analytics</i>
                                            Detailed Results
                                            <?php if ($isDryRun): ?>
                                                <span style="font-size:0.85rem;font-weight:400;opacity:0.85;margin-left:8px">(Preview — no data saved)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Territory Partner</th>
                                                            <th>Target</th>
                                                            <th>Total Paid</th>
                                                            <th>Week 1</th>
                                                            <th>Week 2</th>
                                                            <th>Week 3</th>
                                                            <th>Week 4</th>
                                                            <th>Status</th>
                                                            <th>Bonus Pts</th>
                                                            <th>Account</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($actionResult['details'] as $detail): ?>
                                                            <tr class="<?php echo $detail['will_be_deactivated'] ? 'row-deactivate' : ''; ?>">
                                                                <td>
                                                                    <strong style="color:#111827"><?php echo htmlspecialchars($detail['user_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($detail['user_id'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                                </td>
                                                                <td><strong>₹<?php echo inr_format((float)$detail['monthly_target'], 0); ?></strong></td>
                                                                <td><strong style="color:#059669">₹<?php echo inr_format((float)$detail['total_advance_paid'], 0); ?></strong></td>
                                                                <?php foreach ([1, 2, 3, 4] as $w): ?>
                                                                    <td>
                                                                        <span class="week-status <?php echo $detail["week{$w}_status"]; ?>">
                                                                            <?php echo $detail["week{$w}_status"] === 'pass' ? '✅' : '❌'; ?>
                                                                        </span><br>
                                                                        <small class="text-muted">₹<?php echo inr_format((float)$detail["week{$w}_cumulative"], 0); ?></small>
                                                                    </td>
                                                                <?php endforeach; ?>
                                                                <td>
                                                                    <?php if ($detail['eligibility_status'] === 'eligible'): ?>
                                                                        <span class="badge badge-success">✅ Eligible</span>
                                                                    <?php else: ?>
                                                                        <span class="badge badge-danger">❌ Not Eligible</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <strong style="color:#f59e0b;font-size:1.1rem"><?php echo inr_format((float)$detail['bonus_points_awarded'], 2); ?></strong>
                                                                    <?php if ($detail['bonus_points_awarded'] > 0): ?>
                                                                        <br><small class="text-muted"><?php echo htmlspecialchars($detail['bonus_calculation'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if ($detail['will_be_deactivated']): ?>
                                                                        <span class="badge badge-dark-red">🔴 <?php echo $isDryRun ? 'Will Deactivate' : 'Deactivated'; ?></span>
                                                                        <span class="deactivation-tooltip"><?php echo htmlspecialchars($detail['deactivation_reason'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                                    <?php elseif ($detail['account_status'] !== 'active'): ?>
                                                                        <span class="badge badge-warning">⚠️ Already inactive</span>
                                                                    <?php else: ?>
                                                                        <span class="badge badge-success">✅ Active</span>
                                                                    <?php endif; ?>
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
            const willDeactivate = document.getElementById('apply_deactivation').checked;
            const deactivationLine = willDeactivate
                ? '• Deactivate accounts that did not meet targets\n'
                : '';
            if (!confirm('⚠️ Are you sure you want to EXECUTE and award bonus points?\n\nThis will:\n• Calculate bonus points for all territory partners\n• Award reward points to eligible TPs\n' + deactivationLine + '\nThis action can be rolled back later.')) {
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
        return confirm('⚠️ Are you sure you want to ROLLBACK this execution?\n\nThis will:\n• Mark all bonus point records as rolled back\n• Remove bonus points from reward_points table\n• Restore deactivated TPs back to active\n\nThis action cannot be undone.\n\nContinue with rollback?');
    }
    </script>
</body>
</html>
