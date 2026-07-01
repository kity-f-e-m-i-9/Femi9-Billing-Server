<?php 
include("checksession.php");
$title = "Change Password";
include("config.php");

// Load encryption service
require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/../shared/EncryptionService.php';

// Check if this is a forced password change
$isForced = isset($_GET['forced']) && $_GET['forced'] == '1';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $encryption = new EncryptionService();
        
        $oldPassword = trim($_POST['oldpassword'] ?? '');
        $newPassword = trim($_POST['newpassword'] ?? '');
        $confirmPassword = trim($_POST['confirmpassword'] ?? '');
        
        // Validate inputs
        if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception('All fields are required');
        }
        
        // Check if new passwords match
        if ($newPassword !== $confirmPassword) {
            throw new Exception('New password and confirm password do not match');
        }
        
        // Validate password strength
        $validation = $encryption->validatePasswordStrength($newPassword);
        if (!$validation['valid']) {
            throw new Exception($validation['message']);
        }
        
        // Verify old password
        $stmt = mysqli_prepare($db_conn, 
            "SELECT password FROM admin_log WHERE username = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "s", $log_username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Decrypt stored password and verify
        try {
            $storedDecrypted = $encryption->decrypt($user['password']);
        } catch (Exception $e) {
            // If decryption fails, password might be plain text (old format)
            $storedDecrypted = $user['password'];
        }
        
        if ($oldPassword !== $storedDecrypted) {
            throw new Exception('Old password is incorrect');
        }
        
        // Encrypt new password
        $encryptedPassword = $encryption->encrypt($newPassword);
        
        // Begin transaction
        mysqli_begin_transaction($db_conn);
        
        try {
            // Update password
            $updateStmt = mysqli_prepare($db_conn,
                "UPDATE admin_log SET password = ? WHERE username = ?"
            );
            mysqli_stmt_bind_param($updateStmt, "ss", $encryptedPassword, $log_username);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
            
            // Clear the must_change_password flag
            $clearFlagStmt = mysqli_prepare($db_conn,
                "UPDATE forgotpassword 
                 SET must_change_password = 0, password_changed_at = NOW() 
                 WHERE usertype = 'company' AND mobilenumber = ?"
            );
            $userMobile = $_SESSION['LOGIN_USER'];
            mysqli_stmt_bind_param($clearFlagStmt, "s", $userMobile);
            mysqli_stmt_execute($clearFlagStmt);
            mysqli_stmt_close($clearFlagStmt);
            
            mysqli_commit($db_conn);
            
            // Log the password change
            $logFile = __DIR__ . '/logs/password_changes.log';
            $timestamp = date('Y-m-d H:i:s');
            $logEntry = "[$timestamp] Password changed for user: $log_username (Mobile: $userMobile)\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND);
            
            // Redirect to logout with success message
            echo "<script>
                alert('Password changed successfully! Please login with your new password.');
                window.location='logout.php?action=reset';
            </script>";
            exit;
            
        } catch (Exception $e) {
            mysqli_rollback($db_conn);
            throw $e;
        }
        
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        header("Location: change-password.php?error=" . urlencode($errorMessage) . ($isForced ? '&forced=1' : ''));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?> : <?php echo $business_name; ?></title>
    
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    
    <style>
        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #007bff;
        }
        .password-requirements h6 {
            margin-bottom: 10px;
            color: #007bff;
        }
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
        }
        .password-requirements li {
            margin: 5px 0;
            font-size: 14px;
        }
        .forced-change-alert {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
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
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    
                                    <?php if ($isForced): ?>
                                    <div class="forced-change-alert">
                                        <h5>⚠️ Password Change Required</h5>
                                        <p>For security reasons, you must change your password before continuing. You cannot access the dashboard until you set a new password.</p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($_GET['error'])): ?>
                                    <div class="alert alert-danger alert-dismissible fade show">
                                        <?php echo htmlspecialchars($_GET['error']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <h1>
                                        <table class="headertble">
                                            <tr>
                                                <td><?php echo $title; ?></td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        
                                        <div class="password-requirements">
                                            <h6>Password Requirements:</h6>
                                            <ul>
                                                <li>Minimum 8 characters long</li>
                                                <li>At least one uppercase letter (A-Z)</li>
                                                <li>At least one lowercase letter (a-z)</li>
                                                <li>At least one number (0-9)</li>
                                                <li>At least one special character (!@#$%^&* etc.)</li>
                                            </ul>
                                        </div>
                                        
                                        <form method="POST" action="change-password.php<?php echo $isForced ? '?forced=1' : ''; ?>" id="changePasswordForm">
                                            <div class="example-container">
                                                <div class="example-content">
                                                    
                                                    <div class="mb-3">
                                                        <label for="oldpassword" class="form-label">Old Password *</label>
                                                        <input type="password" required name="oldpassword" id="oldpassword" class="form-control" autocomplete="current-password">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="newpassword" class="form-label">New Password *</label>
                                                        <input type="password" required name="newpassword" id="newpassword" class="form-control" autocomplete="new-password">
                                                        <small class="form-text text-muted">Must meet all requirements above</small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="confirmpassword" class="form-label">Confirm New Password *</label>
                                                        <input type="password" required name="confirmpassword" id="confirmpassword" class="form-control" autocomplete="new-password">
                                                    </div>
                                                    
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="material-icons">lock</i> Update Password
                                                    </button>
                                                    
                                                    <?php if (!$isForced): ?>
                                                    <a href="dashboard.php" class="btn btn-secondary">
                                                        <i class="material-icons">arrow_back</i> Cancel
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                </div>
                                            </div>
                                        </form>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    
    <script>
        // Client-side password validation
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            const newPass = document.getElementById('newpassword').value;
            const confirmPass = document.getElementById('confirmpassword').value;
            
            // Check if passwords match
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('New password and confirm password do not match!');
                return false;
            }
            
            // Check password strength
            const hasUpper = /[A-Z]/.test(newPass);
            const hasLower = /[a-z]/.test(newPass);
            const hasNumber = /[0-9]/.test(newPass);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};:'",.<>?\/\\|`~]/.test(newPass);
            const isLongEnough = newPass.length >= 8;
            
            if (!hasUpper || !hasLower || !hasNumber || !hasSpecial || !isLongEnough) {
                e.preventDefault();
                let errors = [];
                if (!isLongEnough) errors.push('- At least 8 characters');
                if (!hasUpper) errors.push('- One uppercase letter');
                if (!hasLower) errors.push('- One lowercase letter');
                if (!hasNumber) errors.push('- One number');
                if (!hasSpecial) errors.push('- One special character');
                
                alert('Password does not meet requirements:\n' + errors.join('\n'));
                return false;
            }
            
            return true;
        });
        
        // Show password strength indicator
        document.getElementById('newpassword').addEventListener('input', function() {
            const pass = this.value;
            let strength = 0;
            
            if (pass.length >= 8) strength++;
            if (/[A-Z]/.test(pass)) strength++;
            if (/[a-z]/.test(pass)) strength++;
            if (/[0-9]/.test(pass)) strength++;
            if (/[!@#$%^&*()_+\-=\[\]{};:'",.<>?\/\\|`~]/.test(pass)) strength++;
            
            // Visual feedback (optional - you can style this better)
            this.style.borderColor = strength < 3 ? '#dc3545' : (strength < 5 ? '#ffc107' : '#28a745');
        });
    </script>
</body>
</html>