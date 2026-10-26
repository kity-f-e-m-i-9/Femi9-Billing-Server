<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ot_channels');
include("config.php");
date_default_timezone_set("Asia/Kolkata");

$usertype   = $Result_Log_users_Dtails134['usertype'] ?? '';
$login_user = $_SESSION['LOGIN_USER'] ?? '';
$categories = [];

if ($usertype === 'admin') {
    $stmt_cat = $db_conn->prepare("SELECT cat FROM ot_cat ORDER BY cat ASC");
    $stmt_cat->execute();
    $res_cat = $stmt_cat->get_result();
    while ($row = $res_cat->fetch_assoc()) { $categories[] = $row['cat']; }
    $stmt_cat->close();
} else {
    $stmt_cat = $db_conn->prepare(
        "SELECT oc.cat FROM admin_log_ot alo
         JOIN ot_cat oc ON oc.id = alo.ot_cat
         WHERE alo.username = ?"
    );
    $stmt_cat->bind_param('s', $login_user);
    $stmt_cat->execute();
    $res_cat = $stmt_cat->get_result();
    while ($row = $res_cat->fetch_assoc()) { $categories[] = $row['cat']; }
    $stmt_cat->close();
}

$stmt_godown = $db_conn->prepare("SELECT id, gname FROM company_godown ORDER BY id ASC");
$stmt_godown->execute();
$all_godowns = $stmt_godown->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_godown->close();

$title = "Import OT Channel Sales";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars($business_name, ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
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
                                <h1>
                                    <table class="headertble">
                                        <tr>
                                            <td><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><a href="ot-sale-view" title="Manage OT Sales">&#9776;</a></td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">

                                    <?php
                                    foreach (['errorMessageOT', 'errorMessage', 'sucMessage'] as $sessKey):
                                        if (!empty($_SESSION[$sessKey])):
                                            $isError = ($sessKey !== 'sucMessage');
                                    ?>
                                        <div class="alert <?php echo $isError ? 'alert-danger' : 'alert-success'; ?>">
                                            <?php echo htmlspecialchars($_SESSION[$sessKey], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php
                                            unset($_SESSION[$sessKey]);
                                        endif;
                                    endforeach;
                                    ?>

                                    <div class="alert alert-info">
                                        <strong>How it works:</strong>
                                        <ol class="mb-2">
                                            <li>Two sample templates, depending on the category you're importing — pick the one matching your rows:
                                                <ul class="mb-1">
                                                    <li><strong>Marketplace orders</strong> (Amazon, Flipkart, Blinkit, Jio, etc.) — leaves out Coupon Code, Customer Mobile and GST Number, which don't apply to these. Invoice Number is always typed in manually (your own order number).</li>
                                                    <li><strong>Website / ID Concept / WhatsApp Sales / Digital Orders</strong> — keeps every field, and its Invoice Number column additionally accepts the word <strong>NEXT</strong> (see below).</li>
                                                </ul>
                                            </li>
                                            <li>Each row = <strong>one product line</strong>, not one invoice. Pick the product from the dropdown and fill Qty/Rate/Discount for just that product — only the columns for the product you picked are relevant on that row, nothing else needs filling. To put several products on the same invoice, add one row per product and repeat the same <strong>Invoice Number</strong> on each row — those invoice-level columns auto-fill down from the row above once you pick a product.</li>
                                            <li>For the Website/ID Concept/WhatsApp Sales/Digital Orders template specifically: to add another product to the invoice directly above, leave Invoice Number <strong>blank</strong> on that row (don't retype or carry anything) — only pick the product. To start a brand-new invoice, type the word <strong>NEXT</strong> into Invoice Number and the next sequential number for that category + company is generated automatically, exactly like the Add Sale form suggests. You can still type an exact invoice number yourself instead of NEXT if you need to.</li>
                                            <li><strong>Warehouse Code</strong> is optional — leave it blank unless your Company Profile tracks stock separately per physical warehouse (e.g. HO, G1). If it does, stock is checked and deducted against the specific warehouse you type here, not the company's total; an unrecognized code or one not linked to that Company Profile is rejected.</li>
                                            <li>A product can only be used once per invoice — a repeated product on the same invoice is rejected.</li>
                                            <li>Products are matched to your catalog by the dropdown selection, not by typing a name — pick straight from the list so the product links to the real catalog entry.</li>
                                            <li><strong>Wallet Amount</strong> is deducted from the invoice's grand total, same as Add Sale — but only applies when Category is <strong>Website</strong> or <strong>ID Concept</strong>; it's ignored for any other category. <strong>Coupon Code</strong> (SS/S/SD/D-prefixed) triggers the same channel-partner commission credit as a manually added sale.</li>
                                            <li>Rows are validated for stock availability before anything is saved — if any row fails, the whole file is rejected and nothing is imported.</li>
                                            <li>Invoice numbers that already exist for the same category are skipped and reported after upload.</li>
                                        </ol>
                                        <a href="ot-sale-import-template.php?group=marketplace" class="btn btn-sm btn-outline-primary">
                                            <i class="material-icons-outlined" style="vertical-align:middle;font-size:16px">download</i>
                                            Download Marketplace Template
                                        </a>
                                        <a href="ot-sale-import-template.php?group=tracked" class="btn btn-sm btn-outline-primary">
                                            <i class="material-icons-outlined" style="vertical-align:middle;font-size:16px">download</i>
                                            Download Website / ID Concept / WhatsApp / Digital Orders Template
                                        </a>
                                    </div>

                                    <form action="ot-sale-import-action.php" method="post" enctype="multipart/form-data">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Default Company Profile (used only if a row leaves it blank)</label>
                                                <select name="default_godownid" class="form-control">
                                                    <option value="">-- None --</option>
                                                    <?php foreach ($all_godowns as $g): ?>
                                                        <option value="<?php echo (int)$g['id']; ?>"><?php echo htmlspecialchars($g['gname'], ENT_QUOTES, 'UTF-8'); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Default Category (used only if a row leaves it blank)</label>
                                                <select name="default_cat" class="form-control">
                                                    <option value="">-- None --</option>
                                                    <?php foreach ($categories as $c): ?>
                                                        <option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Select Excel File (.xlsx)*</label>
                                            <input type="file" name="import_file" accept=".xlsx,.xls" required class="form-control">
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="material-icons-outlined" style="vertical-align:middle;font-size:18px">upload</i>
                                            Upload &amp; Import
                                        </button>
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
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
