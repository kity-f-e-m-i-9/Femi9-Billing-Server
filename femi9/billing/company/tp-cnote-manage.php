<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: manage-tp-invoices"); exit;
}

// Load all TP credit notes
$cns = $db_conn->query("
    SELECT urs.returnid, urs.invnumber, urs.date, urs.total, urs.status,
           tp.name AS tp_name, tp.tp_id AS tp_code, tp.id AS tp_db_id
    FROM user_return_stock urs
    JOIN territory_partners tp ON tp.id = CAST(urs.from_userid AS UNSIGNED)
    WHERE urs.from_usertype = 'territory_partner' AND urs.to_usertype = 'company'
    ORDER BY urs.id DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Credit Notes : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        .badge-accept  { display:inline-block; background:#d1fae5; color:#065f46; padding:3px 12px; border-radius:20px; font-size:11px; font-weight:700; }
        .badge-pending { display:inline-block; background:#fef3c7; color:#92400e; padding:3px 12px; border-radius:20px; font-size:11px; font-weight:700; }
        .action-btn { display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;transition:background .15s;text-decoration:none; }
        .action-btn.view   { color:#667eea; } .action-btn.view:hover   { background:#ede9fe; }
        .action-btn.delete { color:#dc2626; } .action-btn.delete:hover { background:#fee2e2; }

        #cnTable { border-collapse:collapse; width:100%; }
        #cnTable thead th {
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff; font-size:11.5px; font-weight:600;
            text-transform:uppercase; letter-spacing:.5px;
            padding:12px 14px; white-space:nowrap; border:none;
        }
        #cnTable tbody tr { border-bottom:1px solid #f1f5f9; transition:background .12s; }
        #cnTable tbody tr:hover { background:#f8f7ff; }
        #cnTable tbody td { padding:11px 14px; font-size:13px; color:#374151; vertical-align:middle; border:none; }
        #cnTable tfoot td { padding:10px 14px; font-size:12px; color:#9ca3af; border-top:2px solid #e5e7eb; }

        .cn-ref  { font-family:monospace; font-size:12px; color:#4b5563; background:#f3f4f6; padding:2px 8px; border-radius:4px; }
        .inv-num { font-weight:600; color:#1e40af; font-size:12.5px; }
        .tp-name { font-weight:500; }
        .tp-code { font-size:11px; color:#6b7280; display:block; }
        .amount  { font-weight:700; color:#111827; }
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
                    <div class="row"><div class="col">
                        <div class="page-description">
                            <h1><table class="headertble"><tr>
                                <td>TP Credit Notes</td>
                                <td><a href="manage-tp-invoices" title="TP Invoices">&#x21A9;</a></td>
                            </tr></table></h1>
                        </div>
                    </div></div>

                    <?php if (!empty($_SESSION['successMessage'])): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['successMessage']); unset($_SESSION['successMessage']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['errorMessage'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['errorMessage']); unset($_SESSION['errorMessage']); ?></div>
                    <?php endif; ?>

                    <div class="card" style="border-radius:10px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.07);">
                        <div class="card-body p-0">
                            <table id="cnTable">
                                <thead>
                                    <tr>
                                        <th style="width:40px;">#</th>
                                        <th>CN Reference</th>
                                        <th>Invoice</th>
                                        <th>Territory Partner</th>
                                        <th>Date</th>
                                        <th style="text-align:right;">CN Total</th>
                                        <th style="text-align:center;">Status</th>
                                        <th style="width:80px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php $i=0; foreach ($cns as $cn):
                                    $s2 = $db_conn->prepare("SELECT id FROM tp_invoices WHERE invoice_number=? LIMIT 1");
                                    $s2->bind_param('s', $cn['invnumber']);
                                    $s2->execute();
                                    $tpi = $s2->get_result()->fetch_assoc(); $s2->close();
                                    $inv_id_link = $tpi['id'] ?? 0;
                                    $enc_r = base64_encode($cn['returnid']);
                                ?>
                                <tr>
                                    <td style="color:#9ca3af;font-size:12px;"><?php echo ++$i; ?></td>
                                    <td><span class="cn-ref"><?php echo htmlspecialchars($cn['returnid']); ?></span></td>
                                    <td><span class="inv-num"><?php echo htmlspecialchars($cn['invnumber']); ?></span></td>
                                    <td>
                                        <span class="tp-name"><?php echo htmlspecialchars($cn['tp_name']); ?></span>
                                        <span class="tp-code"><?php echo htmlspecialchars($cn['tp_code']); ?></span>
                                    </td>
                                    <td style="white-space:nowrap;color:#6b7280;"><?php echo $cn['date'] ? date('d M Y', strtotime($cn['date'])) : '—'; ?></td>
                                    <td style="text-align:right;"><span class="amount">&#8377;<?php echo inr_format($cn['total'], 2); ?></span></td>
                                    <td style="text-align:center;">
                                        <?php if ($cn['status'] === 'accept'): ?>
                                            <span class="badge-accept">Accepted</span>
                                        <?php else: ?>
                                            <span class="badge-pending">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space:nowrap;text-align:center;">
                                        <a href="tp-cnote-new?inv_id=<?php echo $inv_id_link; ?>&returnid=<?php echo $enc_r; ?>" class="action-btn view" title="View CN">
                                            <i class="material-icons-outlined" style="font-size:19px;">visibility</i>
                                        </a>
                                        <?php if ($cn['status'] === 'pending'): ?>
                                        <a href="tp-cnote-del?returnid=<?php echo $enc_r; ?>" class="action-btn delete" title="Delete Draft" onclick="return confirm('Delete this draft CN?')">
                                            <i class="material-icons-outlined" style="font-size:19px;">delete_outline</i>
                                        </a>
                                        <?php else: ?>
                                        <a href="tp-cnote-new?inv_id=<?php echo $inv_id_link; ?>&returnid=<?php echo $enc_r; ?>" class="action-btn delete" title="Delete Accepted CN (remove items first)">
                                            <i class="material-icons-outlined" style="font-size:19px;">delete_outline</i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($cns)): ?>
                                <tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:40px;font-size:13px;">No credit notes found.</td></tr>
                                <?php endif; ?>
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
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
