<?php
session_start();

// Load dependencies
require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/../shared/WhatsAppService.php';
require_once __DIR__ . '/../shared/EncryptionService.php';
require_once 'include/db-connect.php';

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Simple rate limiting
function checkRateLimit($key, $maxAttempts = 5, $timeWindow = 3600) {
    $rateLimitFile = __DIR__ . '/logs/rate_limit.json';
    
    if (!file_exists($rateLimitFile)) {
        $dir = dirname($rateLimitFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($rateLimitFile, json_encode([]));
    }
    
    $rateLimits = json_decode(file_get_contents($rateLimitFile), true);
    $now = time();
    
    // Clean old entries
    $rateLimits = array_filter($rateLimits, function($data) use ($now, $timeWindow) {
        return ($now - $data['time']) < $timeWindow;
    });
    
    // Check limit
    if (isset($rateLimits[$key])) {
        if ($rateLimits[$key]['count'] >= $maxAttempts) {
            if (($now - $rateLimits[$key]['time']) < $timeWindow) {
                return false;
            }
        }
    }
    
    // Increment counter
    if (!isset($rateLimits[$key])) {
        $rateLimits[$key] = ['count' => 0, 'time' => $now];
    }
    $rateLimits[$key]['count']++;
    $rateLimits[$key]['time'] = $now;
    
    file_put_contents($rateLimitFile, json_encode($rateLimits));
    return true;
}

// Generate secure password
function generateSecurePassword($length = 10) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@$';
    $password = '';
    $maxIndex = strlen($chars) - 1;
    
    // Ensure password meets requirements
    $password .= chr(rand(65, 90));  // One uppercase
    $password .= chr(rand(97, 122)); // One lowercase
    $password .= chr(rand(48, 57));  // One number
    $password .= $chars[rand(62, $maxIndex)]; // One special char
    
    // Fill remaining length with random chars
    for ($i = 4; $i < $length; $i++) {
        $password .= $chars[random_int(0, $maxIndex)];
    }
    
    // Shuffle to make it random
    return str_shuffle($password);
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subforgotbutton'])) {
    
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid request. Please refresh and try again.');
    }
    
    // Rate limiting
    $rateLimitKey = 'forgot_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!checkRateLimit($rateLimitKey, 5, 3600)) {
        $message = 'Too many attempts. Please try again after 1 hour.';
        $messageType = 'danger';
    } else {
        
        try {
            $whatsapp = new WhatsAppService();
            $encryption = new EncryptionService();
            
            // Get and validate mobile number
            $mobileNumber = preg_replace('/[^0-9]/', '', $_POST['frmobilenumber'] ?? '');
            
            if (!preg_match('/^\d{10}$/', $mobileNumber)) {
                throw new Exception('Invalid mobile number');
            }
            
            // Configuration
            $userTable = 'super_stockiest';
            $userType = 'super_stockiest';
            
            // Fetch user
            $stmt = mysqli_prepare($db_conn, 
                "SELECT id, name, email, mobile_number FROM {$userTable} WHERE mobile_number = ? LIMIT 1"
            );
            mysqli_stmt_bind_param($stmt, "s", $mobileNumber);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            if (!$user) {
                // Security: Don't reveal if user exists
                throw new Exception('If registered, you will receive a reset message');
            }
            
            // Generate new secure password
            $newPassword = generateSecurePassword(10);
            
            // Encrypt the password before storing
            $encryptedPassword = $encryption->encrypt($newPassword);
            
            // Begin transaction
            mysqli_begin_transaction($db_conn);
            
            try {
                // Update password with encrypted version
                $updateStmt = mysqli_prepare($db_conn, 
                    "UPDATE {$userTable} SET password = ? WHERE mobile_number = ?"
                );
                mysqli_stmt_bind_param($updateStmt, "ss", $encryptedPassword, $mobileNumber);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
                
                // Log password reset and set must_change_password flag
                $logStmt = mysqli_prepare($db_conn, 
                    "INSERT INTO forgotpassword (usertype, mobilenumber, reset_at, ip_address, must_change_password) 
                     VALUES (?, ?, NOW(), ?, 1)
                     ON DUPLICATE KEY UPDATE 
                        reset_at = NOW(), 
                        attempts = attempts + 1,
                        must_change_password = 1,
                        password_changed_at = NULL"
                );
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                mysqli_stmt_bind_param($logStmt, "sss", $userType, $mobileNumber, $ipAddress);
                mysqli_stmt_execute($logStmt);
                mysqli_stmt_close($logStmt);
                
                mysqli_commit($db_conn);
                
                // Send WhatsApp message
                $whatsappResult = $whatsapp->sendPasswordReset($mobileNumber, $newPassword);
                
                // Send email as backup
                if (!empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                    $adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'noreply@femi9.com';
                    $to = $user['email'];
                    $subject = 'Femi9 | Password Reset Successfully';
                    $emailBody = "Dear {$user['name']},\n\n";
                    $emailBody .= "Your password has been reset successfully.\n";
                    $emailBody .= "Your new password is: {$newPassword}\n\n";
                    $emailBody .= "Please login and change your password immediately.\n\n";
                    $emailBody .= "If you did not request this reset, please contact support.\n\n";
                    $emailBody .= "Best regards,\nFemi9 Team";
                    
                    $headers = "From: {$adminEmail}\r\n";
                    $headers .= "Reply-To: {$adminEmail}\r\n";
                    
                    mail($to, $subject, $emailBody, $headers);
                }
                
                // Log the password reset
                $logFile = __DIR__ . '/logs/password_resets.log';
                $timestamp = date('Y-m-d H:i:s');
                file_put_contents(
                    $logFile,
                    "[$timestamp] Password reset for mobile: $mobileNumber (User: {$user['name']})\n",
                    FILE_APPEND
                );
                
                // Redirect to success page
                header('Location: forgot-password.php?success=1');
                exit;
                
            } catch (Exception $e) {
                mysqli_rollback($db_conn);
                throw $e;
            }
            
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'danger';
            
            // Log error
            $logFile = __DIR__ . '/logs/forgot_password_error.log';
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($logFile, "[$timestamp] " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}

// Check for success from redirect
if (isset($_GET['success'])) {
    $message = 'Password reset successful! Please check your WhatsApp for the new password.';
    $messageType = 'success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password : Femi9 - Pengalulagam</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
</head>
<body>
    <div class="app app-auth-sign-in align-content-stretch d-flex flex-wrap justify-content-end">
        <div class="app-auth-background"></div>
        <div class="app-auth-container">
            <div class="logo">
                <a href="#">Femi9 - Pengalulagam</a>
            </div>
            <p class="auth-description">Forgot Password : Super Stockist</p>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="forgot-password.php" id="forgotPasswordForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="auth-credentials m-b-xxl">
                    <label for="mobileNumber" class="form-label">Enter Mobile Number</label>
                    <input 
                        type="text" 
                        required 
                        class="form-control m-b-md" 
                        id="mobileNumber" 
                        name="frmobilenumber" 
                        autocomplete="off" 
                        pattern="[0-9]{10}"
                        maxlength="10"
                        placeholder="Enter 10-digit mobile number"
                        title="Please enter a valid 10-digit mobile number"
                    >
                    <small class="form-text text-muted">Enter your registered 10-digit mobile number</small>
                </div>

                <div class="auth-submit">
                    <button type="submit" class="btn btn-primary" name="subforgotbutton">
                        Reset Password
                    </button>
                    <a href="index.php" class="auth-forgot-password float-end">Login</a>
                </div>
            </form>
            
            <div class="divider"></div>
        </div>
    </div>
    
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script>
        // Client-side validation
        document.getElementById('mobileNumber').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
        
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            const mobile = document.getElementById('mobileNumber').value;
            if (!/^\d{10}$/.test(mobile)) {
                e.preventDefault();
                alert('Please enter a valid 10-digit mobile number');
            }
        });
    </script>
</body>
</html>