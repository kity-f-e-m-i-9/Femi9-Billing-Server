<?php
include("checksession.php");
include("config.php");

require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/../shared/EncryptionService.php';

$isForced = isset($_GET['forced']) && $_GET['forced'] == '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $encryption     = new EncryptionService();
        $oldPassword    = trim($_POST['oldpassword']    ?? '');
        $newPassword    = trim($_POST['newpassword']    ?? '');
        $confirmPassword = trim($_POST['confirmpassword'] ?? '');

        if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception('All fields are required.');
        }
        if ($newPassword !== $confirmPassword) {
            throw new Exception('New password and confirm password do not match.');
        }

        $validation = $encryption->validatePasswordStrength($newPassword);
        if (!$validation['valid']) {
            throw new Exception($validation['message']);
        }

        // Fetch current password — territory_partners uses `mobile` column
        $stmt = mysqli_prepare($db_conn,
            "SELECT password FROM territory_partners WHERE mobile = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "s", $Login_user_mobile);
        mysqli_stmt_execute($stmt);
        $user = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);

        if (!$user) throw new Exception('User not found.');

        try {
            $storedDecrypted = $encryption->decrypt($user['password']);
        } catch (Exception $e) {
            $storedDecrypted = $user['password']; // plain text legacy
        }

        if ($oldPassword !== $storedDecrypted) {
            throw new Exception('Old password is incorrect.');
        }

        $encryptedPassword = $encryption->encrypt($newPassword);

        mysqli_begin_transaction($db_conn);
        try {
            $upd = mysqli_prepare($db_conn,
                "UPDATE territory_partners SET password = ?, must_change_password = 0 WHERE mobile = ?"
            );
            mysqli_stmt_bind_param($upd, "ss", $encryptedPassword, $Login_user_mobile);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            // Clear any forced-reset flag in forgotpassword table
            $clr = mysqli_prepare($db_conn,
                "UPDATE forgotpassword
                 SET must_change_password = 0, password_changed_at = NOW()
                 WHERE usertype = 'territory_partner' AND mobilenumber = ?"
            );
            if ($clr) {
                mysqli_stmt_bind_param($clr, "s", $Login_user_mobile);
                mysqli_stmt_execute($clr);
                mysqli_stmt_close($clr);
            }

            mysqli_commit($db_conn);

            $logEntry = sprintf("[%s] Password changed for TP: %s (mobile: %s)\n",
                date('Y-m-d H:i:s'), $Login_user_name, $Login_user_mobile);
            file_put_contents(__DIR__ . '/logs/password_changes.log', $logEntry, FILE_APPEND);

            echo "<script>alert('Password changed successfully! Please login with your new password.');
                  window.location='logout.php?action=reset';</script>";
            exit;

        } catch (Exception $e) {
            mysqli_rollback($db_conn);
            throw $e;
        }

    } catch (Exception $e) {
        $errMsg = urlencode($e->getMessage());
        header("Location: change-password.php?error=$errMsg" . ($isForced ? '&forced=1' : ''));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change Password : <?php echo $business_name; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-container" style="width:100%;">
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container" style="max-width:560px; padding-top:40px;">

                    <?php if ($isForced): ?>
                    <div class="alert" style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;">
                        <strong>Password Change Required</strong>
                        <p class="mb-0 mt-1" style="font-size:13.5px;">For security reasons, you must set a new password before continuing.</p>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars(urldecode($_GET['error'])); ?></div>
                    <?php endif; ?>

                    <div class="card" style="border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08);border:none;">
                        <div class="card-header" style="background:#fff;border-bottom:1px solid #f0f0f0;border-radius:10px 10px 0 0;padding:14px 20px;">
                            <strong style="font-size:14px;">Change Password — <?php echo htmlspecialchars($Login_user_name); ?></strong>
                        </div>
                        <div class="card-body" style="padding:24px;">

                            <div style="background:#eff6ff;border-left:4px solid #3b82f6;border-radius:6px;padding:12px 16px;margin-bottom:20px;font-size:13px;">
                                <strong>Requirements:</strong>
                                min 8 chars · uppercase · lowercase · number · special character
                            </div>

                            <form method="POST" id="cpForm">
                                <div class="mb-3">
                                    <label class="form-label" style="font-size:13px;font-weight:500;">Current Password</label>
                                    <input type="password" name="oldpassword" class="form-control" required autocomplete="current-password">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" style="font-size:13px;font-weight:500;">New Password</label>
                                    <input type="password" name="newpassword" id="newpw" class="form-control" required autocomplete="new-password">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label" style="font-size:13px;font-weight:500;">Confirm New Password</label>
                                    <input type="password" name="confirmpassword" id="confpw" class="form-control" required autocomplete="new-password">
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Update Password</button>
                                    <?php if (!$isForced): ?>
                                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script>
document.getElementById('cpForm').addEventListener('submit', function (e) {
    const np = document.getElementById('newpw').value;
    const cp = document.getElementById('confpw').value;
    if (np !== cp) { e.preventDefault(); alert('Passwords do not match.'); return; }
    const ok = np.length >= 8 && /[A-Z]/.test(np) && /[a-z]/.test(np) && /[0-9]/.test(np) && /[!@#$%^&*()_+\-=\[\]{};:'",.<>?\/\\|`~]/.test(np);
    if (!ok) { e.preventDefault(); alert('Password does not meet the requirements.'); }
});
</script>
</body>
</html>
