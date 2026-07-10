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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['csv_file']['tmp_name'])) {
    header("Location: customer-import.php");
    exit;
}

$maxRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT MAX(userid) AS n FROM customers"));
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

        $requiredCols = ['name', 'mobile'];
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
                $mobile      = $get($row, 'mobile');
                $email       = $get($row, 'email');
                $gstin       = $get($row, 'gstin');
                $address     = $get($row, 'address');
                $countryCode = $get($row, 'country code');

                if ($name === '' || $mobile === '') {
                    $errors[] = "Row $rowNum: missing a required field (Name/Mobile).";
                    continue;
                }

                if (!preg_match('/^[1-9][0-9]{9}$/', $mobile)) {
                    $errors[] = "Row $rowNum: mobile number '$mobile' must be exactly 10 digits.";
                    continue;
                }

                if ($countryCode === '') $countryCode = '+91';

                $dupChk = mysqli_fetch_array(mysqli_query($db_conn,
                    "SELECT COUNT(*) AS n FROM customers WHERE mobile='" . esc($db_conn, $mobile) . "' AND user_type='" . esc($db_conn, $Login_user_TYPEvl) . "' AND user_id='" . esc($db_conn, $Login_user_IDvl) . "'"));
                if ((int)$dupChk['n'] > 0) {
                    $errors[] = "Row $rowNum: a customer with mobile number '$mobile' already exists — skipped.";
                    continue;
                }

                $marketingDate = date("Y-m-d");
                $day           = date("d");
                $runningUserId++;
                $useridtext    = "FEMI9-" . str_pad($runningUserId, 3, '0', STR_PAD_LEFT);

                $ok = mysqli_query($db_conn, "INSERT INTO customers
                    (name,mobile,email,address,marketing_date,date,user_type,user_id,gstin,userid,useridtext,country_code)
                    VALUES ('" . esc($db_conn, $name) . "','" . esc($db_conn, $mobile) . "','" . esc($db_conn, $email) . "','" . esc($db_conn, $address) . "',
                    '$marketingDate','$day','" . esc($db_conn, $Login_user_TYPEvl) . "','" . esc($db_conn, $Login_user_IDvl) . "',
                    '" . esc($db_conn, $gstin) . "','" . (int)$runningUserId . "','" . esc($db_conn, $useridtext) . "','" . esc($db_conn, $countryCode) . "')");

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
                                <h1><table class="headertble"><tr><td>Customer Import Results</td></tr></table></h1>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="alert alert-<?php echo $success > 0 ? 'success' : 'warning'; ?>">
                                        <?php echo (int)$success; ?> customer(s) imported successfully.
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
                                    <a href="customer-manage.php" class="btn btn-primary">Go to Manage Customers</a>
                                    <a href="customer-import.php" class="btn btn-outline-secondary">Import Another File</a>
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
