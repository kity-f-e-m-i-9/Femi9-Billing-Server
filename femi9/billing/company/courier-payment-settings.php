<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpCourierPayment.php';
error_reporting(0);

$title = "Courier Payment QR";
tpEnsureCourierPaymentTables($db_conn);

$qrDir = __DIR__ . '/../territory-partner/courier_qr/';
if (!is_dir($qrDir)) mkdir($qrDir, 0755, true);

$errorMessage = '';
$successMessage = '';

if (isset($_POST['upload_qr'])) {
    $file = $_FILES['qr_image'] ?? null;
    $qrOk = true;

    // QR image itself is optional on this submit (a TP who already has the
    // QR from an earlier save can still get the UPI-ID field updated alone).
    if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = 'Upload failed. Please try again.';
            $qrOk = false;
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errorMessage = 'Image is too large. Maximum allowed size is 5 MB.';
            $qrOk = false;
        } elseif (@getimagesize($file['tmp_name']) === false) {
            $errorMessage = 'File must be a valid image (JPG/PNG).';
            $qrOk = false;
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) $ext = 'jpg';
            $fileName = 'courier_qr_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

            if (move_uploaded_file($file['tmp_name'], $qrDir . $fileName)) {
                // Remove the previous image so unused files don't accumulate.
                $prevStmt = $db_conn->query("SELECT qr_image_path FROM courier_payment_settings WHERE id = 1");
                $prev = $prevStmt->fetch_assoc()['qr_image_path'] ?? null;

                $upd = $db_conn->prepare("UPDATE courier_payment_settings SET qr_image_path = ? WHERE id = 1");
                $upd->bind_param('s', $fileName);
                $upd->execute();
                $upd->close();

                if ($prev && $prev !== $fileName && is_file($qrDir . $prev)) @unlink($qrDir . $prev);
            } else {
                $errorMessage = 'Could not save the uploaded file. Please try again.';
                $qrOk = false;
            }
        }
    }

    if ($qrOk) {
        // The UPI ID is stored separately from the QR image so a "Pay via
        // UPI app" deep link can be built (a TP paying from the same phone
        // that's showing this page can't scan a QR rendered on its own
        // screen — the deep link opens the UPI app chooser directly instead).
        $upiId = trim($_POST['upi_id'] ?? '');
        $payeeName = trim($_POST['upi_payee_name'] ?? '');
        $upiIdOrNull = $upiId !== '' ? $upiId : null;
        $payeeNameOrNull = $payeeName !== '' ? $payeeName : null;

        // Rates — company-editable instead of hardcoded, so a rate change
        // never requires a code deploy. Falls back to the existing saved
        // value (not a hardcoded default) if a field is left blank/invalid,
        // so a partial/garbled submit can't accidentally zero out a rate.
        $existing = tpCourierGetRateSettings($db_conn);
        $napkinRate = is_numeric($_POST['napkin_rate'] ?? null) ? round((float)$_POST['napkin_rate'], 2) : $existing['napkin_rate'];
        $napkinTier2Rate = is_numeric($_POST['napkin_tier2_rate'] ?? null) ? round((float)$_POST['napkin_tier2_rate'], 2) : $existing['napkin_tier2_rate'];
        $napkinTier2Threshold = is_numeric($_POST['napkin_tier2_threshold'] ?? null) ? max(0, (int)$_POST['napkin_tier2_threshold']) : $existing['napkin_tier2_threshold'];
        $diaperRate = is_numeric($_POST['diaper_rate'] ?? null) ? round((float)$_POST['diaper_rate'], 2) : $existing['diaper_rate'];
        $coverRate = is_numeric($_POST['cover_rate'] ?? null) ? round((float)$_POST['cover_rate'], 2) : $existing['cover_rate'];

        $upd2 = $db_conn->prepare(
            "UPDATE courier_payment_settings
             SET upi_id = ?, upi_payee_name = ?,
                 napkin_box_rate = ?, napkin_box_rate_tier2 = ?, napkin_tier2_threshold = ?, diaper_box_rate = ?, cover_rate = ?
             WHERE id = 1"
        );
        $upd2->bind_param('ssddidd', $upiIdOrNull, $payeeNameOrNull, $napkinRate, $napkinTier2Rate, $napkinTier2Threshold, $diaperRate, $coverRate);
        $upd2->execute();
        $upd2->close();

        $successMessage = 'Courier payment settings updated.';
    }
}

$currentQr = tpCourierGetQrImagePath($db_conn);
$upiDetails = tpCourierGetUpiDetails($db_conn);
$courierRates = tpCourierGetRateSettings($db_conn);
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
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .cqr-wrap { max-width: 480px; margin: 24px auto; padding: 0 14px; }
        .cqr-card { background: #fff; border-radius: 12px; padding: 22px; border: 1px solid #eef0f2; }
        .cqr-preview { text-align: center; padding: 14px 0; }
        .cqr-preview img { max-width: 240px; width: 100%; border-radius: 10px; border: 1px solid #e5e7eb; }
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
                    <div class="cqr-wrap">
                        <h1 style="font-size:20px;margin-bottom:4px;"><?php echo $title;?></h1>
                        <p style="color:#6b7280;font-size:13px;margin-bottom:16px;">
                            This QR code is shown to Territory Partners when they pay the courier fee before submitting a purchase order.
                        </p>

                        <?php if ($successMessage): ?><div class="alert alert-success"><?=htmlspecialchars($successMessage)?></div><?php endif; ?>
                        <?php if ($errorMessage): ?><div class="alert alert-danger"><?=htmlspecialchars($errorMessage)?></div><?php endif; ?>

                        <div class="cqr-card">
                            <?php if ($currentQr): ?>
                            <div class="cqr-preview"><img src="../territory-partner/courier_qr/<?=htmlspecialchars($currentQr)?>" alt="Current Courier Payment QR"></div>
                            <?php else: ?>
                            <div class="text-muted text-center" style="font-size:13px;">No QR code uploaded yet.</div>
                            <?php endif; ?>

                            <form method="post" enctype="multipart/form-data" style="margin-top:14px;">
                                <label class="form-label" style="font-size:12.5px;font-weight:600;color:#6b7280;">Upload new QR code image</label>
                                <input type="file" name="qr_image" accept="image/*" class="form-control mb-3">

                                <label class="form-label" style="font-size:12.5px;font-weight:600;color:#6b7280;">UPI ID (e.g. femi9@okhdfcbank)</label>
                                <input type="text" name="upi_id" value="<?=htmlspecialchars($upiDetails['upi_id'] ?? '')?>" placeholder="yourid@bank" class="form-control mb-1">
                                <small class="text-muted d-block mb-3">Needed for the "Pay via UPI app" button shown to a TP paying from their own phone — a QR code alone can't be scanned from the same screen it's shown on.</small>

                                <label class="form-label" style="font-size:12.5px;font-weight:600;color:#6b7280;">Payee Name (shown in the UPI app)</label>
                                <input type="text" name="upi_payee_name" value="<?=htmlspecialchars($upiDetails['payee_name'] ?? '')?>" placeholder="Femi9" class="form-control mb-3">

                                <hr>
                                <label class="form-label" style="font-size:13px;font-weight:700;color:#374151;">Courier Rates</label>

                                <div class="row g-2 mb-1">
                                    <div class="col-6">
                                        <label class="form-label" style="font-size:11.5px;color:#6b7280;">Napkin — ₹/box</label>
                                        <input type="number" step="0.01" min="0" name="napkin_rate" value="<?=htmlspecialchars($courierRates['napkin_rate'])?>" class="form-control">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" style="font-size:11.5px;color:#6b7280;">Napkin — ₹/box (over threshold)</label>
                                        <input type="number" step="0.01" min="0" name="napkin_tier2_rate" value="<?=htmlspecialchars($courierRates['napkin_tier2_rate'])?>" class="form-control">
                                    </div>
                                </div>
                                <label class="form-label" style="font-size:11.5px;color:#6b7280;">Napkin box-count threshold (order's Total Boxes strictly greater than this uses the second rate, for EVERY box in the order)</label>
                                <input type="number" step="1" min="0" name="napkin_tier2_threshold" value="<?=htmlspecialchars($courierRates['napkin_tier2_threshold'])?>" class="form-control mb-2">

                                <label class="form-label" style="font-size:11.5px;color:#6b7280;">Lumi Diaper — ₹/box (flat, no threshold)</label>
                                <input type="number" step="0.01" min="0" name="diaper_rate" value="<?=htmlspecialchars($courierRates['diaper_rate'])?>" class="form-control mb-2">

                                <label class="form-label" style="font-size:11.5px;color:#6b7280;">Cover — ₹/cover (flat, both types)</label>
                                <input type="number" step="0.01" min="0" name="cover_rate" value="<?=htmlspecialchars($courierRates['cover_rate'])?>" class="form-control mb-3">

                                <button type="submit" name="upload_qr" class="btn btn-primary w-100">Save</button>
                            </form>
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
</body>
</html>
