<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$advBalance = 0;

// Try to fetch contact info from admin_settings
$contact_phone = '';
$contact_email = '';
$contact_address = '';
$stmtAS = $db_conn->prepare("SELECT * FROM admin_settings WHERE id = '1'");
if ($stmtAS) {
    $stmtAS->execute();
    $rowAS = $stmtAS->get_result()->fetch_assoc();
    $stmtAS->close();
    if ($rowAS) {
        $contact_phone   = $rowAS['contact_phone'] ?? $rowAS['mobile'] ?? $rowAS['phone'] ?? '';
        $contact_email   = $rowAS['contact_email'] ?? $rowAS['email'] ?? '';
        $contact_address = $rowAS['address'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Manager : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
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
                <div class="container">
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>Account Manager</h1>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xl-12">
                            <div class="card widget widget-stats">
                                <div class="card-body">
                                    <h2 style="color:#d97706;">Femi9 Company</h2>
                                    <h4 style="color:#555;">Your Account Manager</h4>
                                    <p style="color:#777;font-size:14px;">For all queries, billing, advance payments, and support, please contact Femi9 directly.</p>
                                    <table style="font-size:15px;font-weight:bold;">
                                        <?php if (!empty($contact_phone)): ?>
                                        <tr>
                                            <td><i class="material-icons-two-tone">call</i></td>
                                            <td><a href="tel://<?php echo htmlspecialchars($contact_phone); ?>" style="text-decoration:none;color:blue;"><?php echo htmlspecialchars($contact_phone); ?></a></td>
                                        </tr>
                                        <?php else: ?>
                                        <tr>
                                            <td><i class="material-icons-two-tone">call</i></td>
                                            <td><a href="tel://+919999999999" style="text-decoration:none;color:blue;">Contact Femi9</a></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($contact_email)): ?>
                                        <tr>
                                            <td><i class="material-icons-two-tone">email</i></td>
                                            <td><a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" style="text-decoration:none;color:blue;"><?php echo htmlspecialchars($contact_email); ?></a></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                    <?php if (!empty($contact_address)): ?>
                                    <p style="margin-top:15px;font-size:14px;color:#555;"><?php echo nl2br(htmlspecialchars($contact_address)); ?></p>
                                    <?php endif; ?>
                                    <hr/>
                                    <div style="background:#eff6ff;border-radius:10px;padding:15px;margin-top:10px;">
                                        <h6 style="color:#2563eb;">Your Territory Partner Details</h6>
                                        <p style="margin:0;font-size:14px;">
                                            <b>TP ID:</b> <?php echo htmlspecialchars($Login_user_tp_id); ?><br/>
                                            <b>Name:</b> <?php echo htmlspecialchars($Login_user_name); ?><br/>
                                            <b>Mobile:</b> <?php echo htmlspecialchars($Login_user_mobile); ?>
                                        </p>
                                    </div>
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
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
