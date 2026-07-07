<?php 
session_start(); 
error_reporting(0); 

// Load shared dependencies
require_once __DIR__ . '/../shared/env-loader.php';
require_once 'include/db-connect.php';
require_once 'config.php';

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login : <?php echo $userDisplayName; ?> - Femi9</title>
    
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        .password-container {
            position: relative;
        }
        .eye-icon {
            position: absolute;
            right: 10px;
            top: 70%;
            transform: translateY(-50%);
            cursor: pointer;
        }
    </style>
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Femi9 Super Stockist">
    <meta name="theme-color" content="#f5b400">
    <link rel="apple-touch-icon" href="../../assets/images/pwa-icon-apple-touch.png">
    <script>
    if ("serviceWorker" in navigator) {
        window.addEventListener("load", function () {
            navigator.serviceWorker.register("service-worker.js");
        });
    }
    </script>
</head>
<body>
    <div id="app-preloader" style="position:fixed;inset:0;z-index:99999;background:#ffffff;display:flex;flex-direction:column;align-items:center;justify-content:center;transition:opacity .25s ease;">
        <img src="../../assets/images/pwa-icon-192.png" alt="" style="width:72px;height:72px;border-radius:50%;margin-bottom:18px;">
        <div style="width:34px;height:34px;border:3px solid #f0e2b9;border-top-color:#f5b400;border-radius:50%;animation:app-preloader-spin .8s linear infinite;"></div>
    </div>
    <style>@keyframes app-preloader-spin{to{transform:rotate(360deg)}}</style>
    <script>
    (function(){
        var el = document.getElementById('app-preloader');
        function hide(){
            if (!el) return;
            el.style.opacity = '0';
            setTimeout(function(){ el && el.remove(); }, 300);
        }
        window.addEventListener('load', hide);
        setTimeout(hide, 8000);
    })();
    </script>
    <div class="app app-auth-sign-in align-content-stretch d-flex flex-wrap justify-content-end">
        <div class="app-auth-background"></div>
        <div class="app-auth-container">
            <div class="logo">
                <a href="#">Femi9 - Happy day Everyday</a>
            </div>
            <p class="auth-description"><?php echo $userDisplayName; ?> Login</p>
            
            <?php
            if (isset($_SESSION['errorMessage'])) {
                $errorMessage = htmlspecialchars($_SESSION['errorMessage']);
            ?>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                <script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Login Failed',
                        text: '<?php echo $errorMessage; ?>',
                        confirmButtonText: 'OK'
                    });
                </script>
            <?php 
                unset($_SESSION['errorMessage']); 
            } 
            
            if (isset($_SESSION['successMessage'])) {
                $successMessage = htmlspecialchars($_SESSION['successMessage']);
            ?>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                <script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: '<?php echo $successMessage; ?>',
                        confirmButtonText: 'OK'
                    });
                </script>
            <?php 
                unset($_SESSION['successMessage']); 
            } 
            ?>

            <form method="POST" action="CheckLogin.php" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="auth-credentials m-b-xxl">
                    <div class="mb-3">
                        <label class="form-label">Mobile Number</label>
                        <input 
                            type="text" 
                            required 
                            class="form-control" 
                            id="signInEmail" 
                            name="signInEmail" 
                            autocomplete="username"
                            pattern="[0-9]{10}"
                            maxlength="10"
                            placeholder="Enter 10-digit mobile number"
                        >
                    </div>

                    <div class="mb-3 password-container">
                        <label class="form-label">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            required 
                            autocomplete="current-password" 
                            class="form-control" 
                            name="signInPassword"
                            placeholder="Enter your password"
                        >
                        <span class="eye-icon" id="togglePassword">
                            <img src="../../assets/eye.png" alt="Show/Hide" style="width:20px;">
                        </span>
                    </div>
                </div>

                <div class="auth-submit">
                    <button type="submit" class="btn btn-primary" name="login">
                        Sign In
                    </button>
                    <a href="forgot-password.php" class="auth-forgot-password float-end">Forgot password?</a>
                </div>
            </form>
            
            <div class="divider"></div>
            
            <button type="button" onclick="window.location.href='https://femi9billing.com/femi9/';" class="btn btn-success">
                Go Home Page
            </button>
        </div>
    </div>
    
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    
    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
        });
        
        document.getElementById('signInEmail').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
    </script>
</body>
</html>