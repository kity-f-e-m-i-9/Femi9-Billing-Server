<?php
if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
    session_save_path(sys_get_temp_dir());
}
session_start();
error_reporting(0);

require_once 'include/db-connect.php';
require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/account-lib.php';

if (empty($_SESSION['PENDING_LOGIN']) || empty($_SESSION['LINKED_ACCOUNTS'])) {
    header('Location: index.php');
    exit;
}

$accounts = $_SESSION['LINKED_ACCOUNTS'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['choose_type'])) {
    try {
        if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception('Invalid request. Please try again.');
        }

        $chosenType = $_POST['choose_type'];
        $chosen = null;
        foreach ($accounts as $acct) {
            if ($acct['type'] === $chosenType) {
                $chosen = $acct;
                break;
            }
        }
        if (!$chosen) {
            throw new Exception('Invalid selection.');
        }

        activateAccountSession($chosen);
        unset($_SESSION['PENDING_LOGIN']);
        session_regenerate_id(true);
        header('Location: ../' . $chosen['folder'] . '/dashboard.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['errorMessage'] = $e->getMessage();
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose Account - Femi9</title>
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="app app-auth-sign-in align-content-stretch d-flex flex-wrap justify-content-end">
        <div class="app-auth-background"></div>
        <div class="app-auth-container">
            <div class="logo">
                <a href="#">Femi9 - Happy day Everyday</a>
            </div>
            <p class="auth-description">This mobile number is linked to more than one account. Choose which to sign in to:</p>

            <?php if (isset($_SESSION['errorMessage'])): ?>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                <script>
                    Swal.fire({ icon: 'error', title: 'Error', text: '<?php echo htmlspecialchars($_SESSION['errorMessage']); ?>', confirmButtonText: 'OK' });
                </script>
                <?php unset($_SESSION['errorMessage']); ?>
            <?php endif; ?>

            <form method="POST" action="select-account.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="list-group m-b-xxl">
                    <?php foreach ($accounts as $acct): ?>
                        <button type="submit" name="choose_type" value="<?php echo htmlspecialchars($acct['type']); ?>"
                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="margin-bottom:10px;border-radius:8px;">
                            <span>
                                <strong><?php echo htmlspecialchars($acct['display_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($acct['name']); ?></small>
                            </span>
                            <span class="material-icons-outlined">arrow_forward</span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
    </div>
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
