<?php
if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
    session_save_path(sys_get_temp_dir());
}
session_start();
error_reporting(0);

require_once __DIR__ . '/../shared/env-loader.php';
require_once 'include/db-connect.php';

$userDisplayName = "Channel Partner";
$business_name   = "Femi9 - Happy day Everyday";

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Already logged in → go to dashboard
if (!empty($_SESSION['LOGIN_USER']) && ($_SESSION['LOGIN_USER_TYPE'] ?? '') === 'channel_partner') {
    header('Location: dashboard.php');
    exit;
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
</head>
<body>
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
