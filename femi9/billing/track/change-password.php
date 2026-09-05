<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$title = "Change Password";
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $errorMessage = 'All fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = 'New password and confirm password do not match.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $newPassword)) {
        $errorMessage = 'New password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character.';
    } elseif (!password_verify($oldPassword, $result_LoGuserDtails['password'])) {
        $errorMessage = 'Current password is incorrect.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $upd = $db_conn->prepare("UPDATE track_users SET password = ? WHERE id = ?");
        $upd->bind_param('si', $hash, $Login_user_IDvl);
        $upd->execute();
        $upd->close();
        $successMessage = 'Password updated successfully.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo $title;?> : <?php echo $business_name;?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; } .cp-wrap { max-width: 420px; margin: 24px auto; padding: 0 14px; }</style>
</head>
<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>
        <div class="app-container">
            <?php include("app-header.php");?>
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="cp-wrap">
                        <h1 style="font-size:20px;margin-bottom:16px;"><?php echo $title;?></h1>
                        <?php if ($successMessage): ?><div class="alert alert-success"><?=htmlspecialchars($successMessage)?></div><?php endif; ?>
                        <?php if ($errorMessage): ?><div class="alert alert-danger"><?=htmlspecialchars($errorMessage)?></div><?php endif; ?>
                        <form method="post">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="old_password" class="form-control mb-3" required>
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control mb-3" required>
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control mb-3" required>
                            <button type="submit" class="btn btn-primary w-100">Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
