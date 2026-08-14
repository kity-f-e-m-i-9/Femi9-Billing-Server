<?php
include("checksession.php");
$title = "Change Password";
include("config.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $oldPassword = trim($_POST['oldpassword'] ?? '');
        $newPassword = trim($_POST['newpassword'] ?? '');
        $confirmPassword = trim($_POST['confirmpassword'] ?? '');

        if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception('All fields are required');
        }
        if ($newPassword !== $confirmPassword) {
            throw new Exception('New password and confirm password do not match');
        }
        if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword)
            || !preg_match('/[0-9]/', $newPassword) || !preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\\\|`~]/', $newPassword)) {
            throw new Exception('New password does not meet the requirements below');
        }

        // sales_bdm_staff.password is always plaintext by design (see CheckLogin.php).
        $stmt = mysqli_prepare($db_conn, "SELECT password FROM sales_bdm_staff WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $salesBdmID);
        mysqli_stmt_execute($stmt);
        $user = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);

        if (!$user) {
            throw new Exception('User not found');
        }
        if ($oldPassword !== $user['password']) {
            throw new Exception('Old password is incorrect');
        }

        $stmt = mysqli_prepare($db_conn, "UPDATE sales_bdm_staff SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $newPassword, $salesBdmID);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo "<script>
            alert('Password changed successfully! Please login with your new password.');
            window.location='logout.php';
        </script>";
        exit;

    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        header("Location: change-password.php?error=" . urlencode($errorMessage));
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
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        .password-requirements { background:#f8f9fa; padding:15px; border-radius:5px; margin:15px 0; border-left:4px solid #007bff; }
        .password-requirements h6 { margin-bottom:10px; color:#007bff; }
        .password-requirements ul { margin:0; padding-left:20px; }
        .password-requirements li { margin:5px 0; font-size:14px; }
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
                                <?php if (isset($_GET['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <?php echo htmlspecialchars($_GET['error']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php endif; ?>
                                <h1><table class="headertble"><tr><td><?php echo $title; ?></td></tr></table></h1>
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

                                    <form method="POST" action="change-password.php" id="changePasswordForm">
                                        <div class="example-container">
                                            <div class="example-content">
                                                <div class="mb-3">
                                                    <label for="oldpassword" class="form-label">Old Password *</label>
                                                    <input type="password" required name="oldpassword" id="oldpassword" class="form-control" autocomplete="current-password">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="newpassword" class="form-label">New Password *</label>
                                                    <input type="password" required name="newpassword" id="newpassword" class="form-control" autocomplete="new-password">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="confirmpassword" class="form-label">Confirm New Password *</label>
                                                    <input type="password" required name="confirmpassword" id="confirmpassword" class="form-control" autocomplete="new-password">
                                                </div>
                                                <button type="submit" class="btn btn-primary"><i class="material-icons">lock</i> Update Password</button>
                                                <a href="dashboard.php" class="btn btn-secondary"><i class="material-icons">arrow_back</i> Cancel</a>
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
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    var newPass = document.getElementById('newpassword').value;
    var confirmPass = document.getElementById('confirmpassword').value;
    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('New password and confirm password do not match!');
        return false;
    }
    var hasUpper = /[A-Z]/.test(newPass);
    var hasLower = /[a-z]/.test(newPass);
    var hasNumber = /[0-9]/.test(newPass);
    var hasSpecial = /[!@#$%^&*()_+\-=\[\]{};:'",.<>?\/\\|`~]/.test(newPass);
    var isLongEnough = newPass.length >= 8;
    if (!hasUpper || !hasLower || !hasNumber || !hasSpecial || !isLongEnough) {
        e.preventDefault();
        var errors = [];
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
</script>
</body>
</html>
