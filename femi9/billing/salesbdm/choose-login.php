<?php
// Reached only from CheckLogin.php, right after a mobile+password pair has
// already been verified against BOTH sales_bdm_staff and admin_log — this
// page never re-checks credentials, it just finishes whichever login the
// user picks. The pending_switch_* session keys are the proof that
// verification already happened; if they're missing, there's nothing to
// choose from and we bounce back to the normal login form.
session_start();
require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/include/LoginHelpers.php';

if (empty($_SESSION['pending_switch_bdm_id']) || empty($_SESSION['pending_switch_admin_id'])) {
    header('Location: index.php');
    exit;
}

$bdmName = $_SESSION['pending_switch_bdm_name'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $choice = $_POST['choice'] ?? '';

    if ($choice === 'salesbdm') {
        $stmt = mysqli_prepare($db_conn, "SELECT id, bdm_name, bdm_mobile, account_status FROM sales_bdm_staff WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['pending_switch_bdm_id']);
        mysqli_stmt_execute($stmt);
        $user = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);

        unset($_SESSION['pending_switch_bdm_id'], $_SESSION['pending_switch_bdm_mobile'], $_SESSION['pending_switch_bdm_name'], $_SESSION['pending_switch_admin_id'], $_SESSION['pending_switch_admin_username']);

        if ($user && $user['account_status'] === 'active') {
            finalizeSalesBdmSession($db_conn, $user);
            session_regenerate_id(true);
            header('Location: dashboard.php');
            exit;
        }
        header('Location: index.php?sessionexpiry');
        exit;
    }

    if ($choice === 'company') {
        $adminId = (int)$_SESSION['pending_switch_admin_id'];
        unset($_SESSION['pending_switch_bdm_id'], $_SESSION['pending_switch_bdm_mobile'], $_SESSION['pending_switch_bdm_name'], $_SESSION['pending_switch_admin_id'], $_SESSION['pending_switch_admin_username']);

        $token = createCompanySwitchToken($db_conn, $adminId);
        header('Location: ../company/switch-login.php?token=' . urlencode($token));
        exit;
    }

    header('Location: choose-login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose Account</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f7f7f6; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
        .chooser { background: #fff; border-radius: 14px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 36px 32px; width: 100%; max-width: 380px; text-align: center; }
        .chooser h1 { font-size: 18px; margin: 0 0 6px; color: #0b0b0b; }
        .chooser p { font-size: 13px; color: #6b7280; margin: 0 0 26px; }
        .opt-btn { display: flex; align-items: center; gap: 12px; width: 100%; padding: 14px 16px; margin-bottom: 12px; border: 1.5px solid #e1e0d9; border-radius: 10px; background: #fff; cursor: pointer; font-size: 14px; font-weight: 600; color: #0b0b0b; text-align: left; transition: border-color .15s, background .15s; }
        .opt-btn:hover { border-color: #2a78d6; background: #eaf2fc; }
        .opt-ico { width: 38px; height: 38px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .opt-sales .opt-ico { background: #fff3e0; color: #d97706; }
        .opt-company .opt-ico { background: #eaf2fc; color: #2a78d6; }
        .opt-sub { font-size: 11.5px; font-weight: 400; color: #898781; margin-top: 2px; }
    </style>
</head>
<body>
    <div class="chooser">
        <h1>Which login?</h1>
        <p><?php echo htmlspecialchars($bdmName); ?>, this mobile number is linked to two accounts.</p>
        <form method="post">
            <button type="submit" name="choice" value="salesbdm" class="opt-btn opt-sales">
                <span class="opt-ico">&#128100;</span>
                <span>Sales BDM<span class="opt-sub" style="display:block;">Your BDM dashboard &amp; TPs</span></span>
            </button>
            <button type="submit" name="choice" value="company" class="opt-btn opt-company">
                <span class="opt-ico">&#127970;</span>
                <span>Company<span class="opt-sub" style="display:block;">Full company admin login</span></span>
            </button>
        </form>
    </div>
</body>
</html>
