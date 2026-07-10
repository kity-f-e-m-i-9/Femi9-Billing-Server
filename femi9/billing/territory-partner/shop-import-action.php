<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$errors  = [];
$success = 0;
$rowNum  = 1; // header row

function esc($db_conn, $val) {
    return mysqli_real_escape_string($db_conn, trim((string)$val));
}

function normalizeHeader($val) {
    $val = (string)$val;
    $val = preg_replace('/^\xEF\xBB\xBF/', '', $val); // strip UTF-8 BOM
    $val = str_replace("\xC2\xA0", ' ', $val); // non-breaking space -> regular space
    return strtolower(trim($val));
}

function findNode($geoNodes, $depth, $name, $parentId = null) {
    $name = trim($name);
    if ($name === '') return null;
    foreach ($geoNodes as $node) {
        if ($node['depth'] !== $depth) continue;
        if ($parentId !== null && $node['parent_id'] !== $parentId) continue;
        if (strcasecmp($node['name'], $name) === 0) return $node;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['csv_file']['tmp_name'])) {
    header("Location: shop-import.php");
    exit;
}

include("geo_layers.php"); // gives $geoNodes restricted to this TP's territory

$shopCategories = [];
$catRes = mysqli_query($db_conn, "SELECT id, catlable FROM shop_category");
while ($catRow = mysqli_fetch_array($catRes)) {
    $shopCategories[strtolower(trim($catRow['catlable']))] = (int)$catRow['id'];
}

// Category is optional in the CSV — fall back to a general-purpose category when omitted.
$defaultCategoryId = null;
foreach ($shopCategories as $catName => $catId) {
    if (str_contains($catName, 'general')) { $defaultCategoryId = $catId; break; }
}
if ($defaultCategoryId === null && !empty($shopCategories)) {
    $defaultCategoryId = reset($shopCategories);
}

$maxRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT MAX(userid) AS n FROM shop"));
$runningUserId = (int)$maxRow['n'];

$handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
if ($handle === false) {
    $errors[] = "Could not read the uploaded file.";
} else {
    $header = fgetcsv($handle);
    if ($header === false) {
        $errors[] = "The file is empty.";
    } else {
        $colIndex = [];
        foreach ($header as $idx => $col) {
            $colIndex[normalizeHeader($col)] = $idx;
        }

        $requiredCols = ['name', 'state', 'district', 'mobile number', 'address'];
        $missingCols  = array_diff($requiredCols, array_keys($colIndex));

        if (!empty($missingCols)) {
            $errors[] = "Missing required column(s) in CSV header: " . implode(', ', $missingCols)
                . ". Columns detected in your file: " . implode(', ', array_map('trim', $header));
        } else {
            $get = function ($row, $key) use ($colIndex) {
                return isset($colIndex[$key], $row[$colIndex[$key]]) ? trim($row[$colIndex[$key]]) : '';
            };

            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;

                if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                    continue; // skip blank rows
                }

                $name        = $get($row, 'name');
                $categoryTxt = $get($row, 'category');
                $stateTxt    = $get($row, 'state');
                $districtTxt = $get($row, 'district');
                $talukTxt    = $get($row, 'taluk');
                $pincode     = $get($row, 'pincode');
                $countryCode = $get($row, 'country code');
                $mobile      = $get($row, 'mobile number');
                $landline    = $get($row, 'landline');
                $email       = $get($row, 'email id');
                $address     = $get($row, 'address');
                $gstin       = $get($row, 'gstin');

                if ($name === '' || $stateTxt === '' || $districtTxt === '' || $mobile === '' || $address === '') {
                    $errors[] = "Row $rowNum: missing a required field (Name/State/District/Mobile Number/Address).";
                    continue;
                }

                if (!preg_match('/^[1-9][0-9]{9}$/', $mobile)) {
                    $errors[] = "Row $rowNum: mobile number '$mobile' must be exactly 10 digits.";
                    continue;
                }

                if ($categoryTxt === '') {
                    $shopCatId = $defaultCategoryId;
                    if ($shopCatId === null) {
                        $errors[] = "Row $rowNum: no Category given and no default category is configured — please add a Category column.";
                        continue;
                    }
                } else {
                    $shopCatId = $shopCategories[strtolower($categoryTxt)] ?? null;
                    if ($shopCatId === null) {
                        $errors[] = "Row $rowNum: category '$categoryTxt' does not match any existing shop category.";
                        continue;
                    }
                }

                $stateNode = findNode($geoNodes, 2, $stateTxt);
                if ($stateNode === null) {
                    $errors[] = "Row $rowNum: state '$stateTxt' not found in your assigned territory.";
                    continue;
                }

                $districtNode = findNode($geoNodes, 3, $districtTxt, $stateNode['id']);
                if ($districtNode === null) {
                    $errors[] = "Row $rowNum: district '$districtTxt' not found under state '$stateTxt' in your assigned territory.";
                    continue;
                }

                $talukId = '';
                $firkaId = '';
                if ($talukTxt !== '') {
                    $talukNode = findNode($geoNodes, 4, $talukTxt, $districtNode['id']);
                    if ($talukNode !== null) {
                        $talukId = $talukNode['id'];
                    } else {
                        $firkaNode = findNode($geoNodes, 5, $talukTxt, null);
                        if ($firkaNode !== null) {
                            $firkaId = $firkaNode['id'];
                        } else {
                            $errors[] = "Row $rowNum: taluk '$talukTxt' not found under district '$districtTxt' — imported without it.";
                        }
                    }
                }

                if ($countryCode === '') $countryCode = '+91';

                $dupChk = mysqli_fetch_array(mysqli_query($db_conn,
                    "SELECT COUNT(*) AS n FROM shop WHERE mobile_number='" . esc($db_conn, $mobile) . "' AND name='" . esc($db_conn, $name) . "' AND onboard_userID='" . esc($db_conn, $Login_user_IDvl) . "' AND onboard_userTYPE='" . esc($db_conn, $Login_user_TYPEvl) . "'"));
                if ((int)$dupChk['n'] > 0) {
                    $errors[] = "Row $rowNum: a shop named '$name' with mobile number '$mobile' already exists for your account — skipped.";
                    continue;
                }

                $randNum = mt_rand(10000, 99999);
                $tempId  = $randNum . "FSHP" . date("dmy") . date("His") . $rowNum;

                $runningUserId++;
                $useridtext = "FEMI9-R-" . str_pad($runningUserId, 3, '0', STR_PAD_LEFT);
                $validFrom  = date("Y-m-d");
                $validTo    = date("Y-m-d", strtotime("+1 months"));

                $ok = mysqli_query($db_conn, "INSERT INTO shop
                    (state_id,temp_id,user_icon,name,district_id,email,mobile_number,username,password,
                     plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,ref_number,
                     account_status,merchantOrderId,merchantTransactionId,merchantUserId,
                     taluk_id,firka_id,distributor_id,pincode_id,gstin,onboard_userTYPE,onboard_userID,
                     address,userid,useridtext,shop_cat,country_code,landline)
                    VALUES
                    ('" . (int)$stateNode['id'] . "','" . esc($db_conn, $tempId) . "','Nil','" . esc($db_conn, $name) . "','" . (int)$districtNode['id'] . "',
                     '" . esc($db_conn, $email) . "','" . esc($db_conn, $mobile) . "','Nil','Nil','0','1','$validFrom','$validTo','Nil','Nil','Nil','Nil','Nil','Nil','Nil',
                     '" . esc($db_conn, $talukId) . "','" . esc($db_conn, $firkaId) . "','','" . esc($db_conn, $pincode) . "','" . esc($db_conn, $gstin) . "',
                     '" . esc($db_conn, $Login_user_TYPEvl) . "','" . esc($db_conn, $Login_user_IDvl) . "',
                     '" . esc($db_conn, $address) . "','" . (int)$runningUserId . "','" . esc($db_conn, $useridtext) . "','" . (int)$shopCatId . "','" . esc($db_conn, $countryCode) . "','" . esc($db_conn, $landline) . "')");

                if ($ok) {
                    $success++;
                } else {
                    $runningUserId--;
                    $errors[] = "Row $rowNum: database error while inserting.";
                }
            }
        }
    }
    fclose($handle);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Import Results : <?php echo $business_name; ?></title>
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
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
                <div class="container-fluid">
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1><table class="headertble"><tr><td>Shop Import Results</td></tr></table></h1>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="alert alert-<?php echo $success > 0 ? 'success' : 'warning'; ?>">
                                        <?php echo (int)$success; ?> shop(s) imported successfully.
                                    </div>
                                    <?php if (!empty($errors)): ?>
                                        <div class="alert alert-danger">
                                            <strong><?php echo count($errors); ?> row(s) had issues:</strong>
                                            <ul class="mb-0">
                                                <?php foreach ($errors as $e): ?>
                                                    <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    <a href="shop-manage.php" class="btn btn-primary">Go to Manage Shop</a>
                                    <a href="shop-import.php" class="btn btn-outline-secondary">Import Another File</a>
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
<script src="../../assets/js/main.min.js"></script>
</body>
</html>
