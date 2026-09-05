<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
include("config.php");
require_once __DIR__ . '/../shared/user-config.php';
error_reporting(0);

ensureTrackUsersTable($db_conn);

$title = "Track Users";
$errorMessage = '';
$successMessage = '';

if (isset($_POST['add_track_user'])) {
    $name = trim($_POST['name'] ?? '');
    $mobile = preg_replace('/[^0-9]/', '', $_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || !preg_match('/^\d{10}$/', $mobile) || $password === '') {
        $errorMessage = 'Name, a valid 10-digit mobile number, and a password are required.';
    } else {
        $chk = $db_conn->prepare("SELECT id FROM track_users WHERE mobile = ?");
        $chk->bind_param('s', $mobile);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($exists) {
            $errorMessage = 'A Track account with this mobile number already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $emailOrNull = $email !== '' ? $email : null;
            $ins = $db_conn->prepare("INSERT INTO track_users (name, mobile, password, email, account_status) VALUES (?, ?, ?, ?, 'active')");
            $ins->bind_param('ssss', $name, $mobile, $hash, $emailOrNull);
            $ins->execute();
            $ins->close();
            $successMessage = 'Track account created.';
        }
    }
}

if (isset($_POST['toggle_status'])) {
    $id = (int)$_POST['id'];
    $newStatus = $_POST['toggle_status'] === 'active' ? 'active' : 'inactive';
    $upd = $db_conn->prepare("UPDATE track_users SET account_status = ? WHERE id = ?");
    $upd->bind_param('si', $newStatus, $id);
    $upd->execute();
    $upd->close();
    header("Location: track-users.php");
    exit;
}

$users = $db_conn->query("SELECT id, name, mobile, email, account_status, last_login, created_at FROM track_users ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
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
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .tu-wrap { max-width: 900px; margin: 24px auto; padding: 0 14px; }
        .tu-card { background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #eef0f2; margin-bottom: 20px; }
    </style>
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
                    <div class="tu-wrap">
                        <h1 style="font-size:20px;margin-bottom:16px;"><?php echo $title;?></h1>

                        <?php if ($successMessage): ?><div class="alert alert-success"><?=htmlspecialchars($successMessage)?></div><?php endif; ?>
                        <?php if ($errorMessage): ?><div class="alert alert-danger"><?=htmlspecialchars($errorMessage)?></div><?php endif; ?>

                        <div class="tu-card">
                            <h6 style="font-weight:700;margin-bottom:12px;">Add Track User</h6>
                            <form method="post" class="row g-2">
                                <div class="col-md-3"><input type="text" name="name" class="form-control" placeholder="Name" required></div>
                                <div class="col-md-3"><input type="text" name="mobile" class="form-control" placeholder="10-digit Mobile" pattern="[0-9]{10}" maxlength="10" required></div>
                                <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email (optional)"></div>
                                <div class="col-md-2"><input type="text" name="password" class="form-control" placeholder="Password" required></div>
                                <div class="col-md-1"><button type="submit" name="add_track_user" class="btn btn-primary w-100">Add</button></div>
                            </form>
                        </div>

                        <div class="tu-card">
                            <h6 style="font-weight:700;margin-bottom:12px;">Track Users</h6>
                            <div style="overflow-x:auto;">
                            <table class="table">
                                <thead><tr><th>Name</th><th>Mobile</th><th>Email</th><th>Status</th><th>Last Login</th><th>Created</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?=htmlspecialchars($u['name'])?></td>
                                    <td><?=htmlspecialchars($u['mobile'])?></td>
                                    <td><?=htmlspecialchars($u['email'] ?? '—')?></td>
                                    <td><span class="badge <?=$u['account_status']==='active'?'bg-success':'bg-secondary'?>"><?=htmlspecialchars($u['account_status'])?></span></td>
                                    <td><?=$u['last_login'] ? date('d-M-Y h:i A', strtotime($u['last_login'])) : '—'?></td>
                                    <td><?=date('d-M-Y', strtotime($u['created_at']))?></td>
                                    <td>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id" value="<?=$u['id']?>">
                                            <input type="hidden" name="toggle_status" value="<?=$u['account_status']==='active'?'inactive':'active'?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary"><?=$u['account_status']==='active'?'Deactivate':'Activate'?></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($users)): ?><tr><td colspan="7" class="text-muted text-center">No Track users yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
