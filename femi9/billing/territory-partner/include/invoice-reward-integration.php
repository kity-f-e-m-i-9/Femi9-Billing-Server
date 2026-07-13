<?php
/**
 * Daily Reward Integration Helper - CORRECTED VERSION
 * For Reward Points System (NOT Wallet)
 *
 * This file provides helper functions for integrating daily login rewards
 * into the invoice submission process.
 */

/**
 * Check if user has already been rewarded today
 * and award points if eligible
 *
 * @param mysqli $db_conn Database connection
 * @param string $userType User type (e.g., 'territory_partner')
 * @param string $userId User ID (temp_id)
 * @param string $invoiceId Invoice ID
 * @param string $invoiceNumber Invoice number for reference
 * @return array Result with success status and message
 */
function checkAndAwardDailyReward($db_conn, $userType, $userId, $invoiceId, $invoiceNumber) {
    $result = [
        'success' => false,
        'message' => '',
        'points_awarded' => 0,
        'already_rewarded' => false
    ];

    try {
        $today = date('Y-m-d');
        $points = 1; // Daily reward points

        // Check if user already received reward today
        $check_stmt = mysqli_prepare($db_conn,
            "SELECT id FROM daily_login_rewards
             WHERE user_type = ? AND user_id = ? AND reward_date = ?"
        );

        if (!$check_stmt) {
            throw new Exception("Failed to prepare check statement");
        }

        mysqli_stmt_bind_param($check_stmt, "sss", $userType, $userId, $today);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            // Already rewarded today
            $result['already_rewarded'] = true;
            $result['message'] = 'Already received daily reward';
            mysqli_stmt_close($check_stmt);
            return $result;
        }
        mysqli_stmt_close($check_stmt);

        // Start transaction for atomic operation
        mysqli_begin_transaction($db_conn);

        // Insert into daily_login_rewards (ONLY THIS - NO WALLET)
        $insert_stmt = mysqli_prepare($db_conn,
            "INSERT INTO daily_login_rewards
             (user_type, user_id, reward_date, points_awarded, invoice_id, invoice_number, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );

        if (!$insert_stmt) {
            throw new Exception("Failed to prepare insert statement");
        }

        mysqli_stmt_bind_param($insert_stmt, "sssdss",
            $userType, $userId, $today, $points, $invoiceId, $invoiceNumber
        );

        if (!mysqli_stmt_execute($insert_stmt)) {
            throw new Exception("Failed to insert reward record");
        }
        mysqli_stmt_close($insert_stmt);

        // Insert audit log
        $audit_stmt = mysqli_prepare($db_conn,
            "INSERT INTO daily_reward_audit_log
             (action_type, user_type, user_id, reward_date, points_amount,
              invoice_id, invoice_number, created_at)
             VALUES ('daily_reward', ?, ?, ?, ?, ?, ?, NOW())"
        );

        if ($audit_stmt) {
            mysqli_stmt_bind_param($audit_stmt, "sssdss",
                $userType, $userId, $today, $points, $invoiceId, $invoiceNumber
            );
            mysqli_stmt_execute($audit_stmt);
            mysqli_stmt_close($audit_stmt);
        }

        // Commit transaction
        mysqli_commit($db_conn);

        // Success!
        $result['success'] = true;
        $result['points_awarded'] = $points;
        $result['message'] = "You earned $points points for today's login and billing!";

        return $result;

    } catch (Exception $e) {
        // Rollback on error
        if (mysqli_connect_errno() === 0) {
            mysqli_rollback($db_conn);
        }

        $result['success'] = false;
        $result['message'] = 'Error: ' . $e->getMessage();

        // Log error
        error_log("Daily Reward Error: " . $e->getMessage());

        return $result;
    }
}

/**
 * Display reward notification using SweetAlert2
 * Call this on the invoice print/success page
 *
 * @param array $rewardData Reward data from checkAndAwardDailyReward()
 */
function displayRewardNotification($rewardData) {
    if (!isset($rewardData['success']) || !$rewardData['success']) {
        return; // Don't show notification if reward wasn't awarded
    }

    $points = $rewardData['points_awarded'];
    $message = htmlspecialchars($rewardData['message']);

    ?>
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: 'Congratulations! 🎉',
            html: '<div style="font-size: 18px; color: #7c3aed;">' +
                  '<strong><?php echo $message; ?></strong>' +
                  '</div>' +
                  '<div style="margin-top: 15px; font-size: 16px; color: #6b7280;">' +
                  'Keep logging in daily and creating invoices to earn more points!' +
                  '</div>',
            icon: 'success',
            confirmButtonText: 'Awesome!',
            confirmButtonColor: '#7c3aed',
            customClass: {
                popup: 'reward-popup',
                title: 'reward-title',
                confirmButton: 'reward-confirm-btn'
            }
        });
    });
    </script>

    <style>
    .reward-popup {
        border-radius: 20px !important;
        padding: 30px !important;
    }
    .reward-title {
        color: #7c3aed !important;
        font-weight: 700 !important;
    }
    .reward-confirm-btn {
        padding: 12px 30px !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
    }
    </style>
    <?php
}
?>
