<?php
include("checksession.php");
include("config.php");
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$title = "Advance Payment";

// Available advance balance — same query used everywhere else this figure
// is shown (add-purchase-order.php, dashboard.php, mis-report.php); the
// three-condition filter (balance_amount > 0 AND status != 'fully_adjusted'
// AND deleted_at IS NULL) was the subject of two prior bugfixes and must be
// kept exactly as-is.
$balStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(balance_amount), 0) AS bal
     FROM tp_advance_payments WHERE territory_partner_id = ? AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL"
);
mysqli_stmt_bind_param($balStmt, "i", $Login_user_IDvl);
mysqli_stmt_execute($balStmt);
$advBalance = (float)(mysqli_stmt_get_result($balStmt)->fetch_assoc()['bal'] ?? 0);
mysqli_stmt_close($balStmt);

// This TP's most recent in-progress draft submission (if any) — resumable
// if the TP reloads mid-upload, same as add-purchase-order.php's unlinked
// (po_id IS NULL) screenshot draft.
$draftSubmission = null;
$draftStmt = mysqli_prepare($db_conn,
    "SELECT id, amount, payment_date, payment_mode, reference_number, note
     FROM tp_advance_payment_submissions
     WHERE territory_partner_id = ? AND status = 'draft' ORDER BY id DESC LIMIT 1"
);
mysqli_stmt_bind_param($draftStmt, "i", $Login_user_IDvl);
mysqli_stmt_execute($draftStmt);
$draftSubmission = mysqli_stmt_get_result($draftStmt)->fetch_assoc() ?: null;
mysqli_stmt_close($draftStmt);

$draftScreenshots = [];
if ($draftSubmission) {
    $scrStmt = mysqli_prepare($db_conn,
        "SELECT id, status, detected_amount, reference_number, rejection_reason, file_path
         FROM tp_advance_payment_screenshots WHERE submission_id = ? ORDER BY id ASC"
    );
    mysqli_stmt_bind_param($scrStmt, "i", $draftSubmission['id']);
    mysqli_stmt_execute($scrStmt);
    $scrRes = mysqli_stmt_get_result($scrStmt);
    while ($s = mysqli_fetch_assoc($scrRes)) $draftScreenshots[] = $s;
    mysqli_stmt_close($scrStmt);
}

$today = date("Y-m-d");
$minDate = date("Y-m-d", strtotime("-2 days"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo $title;?> | Femi9</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <style>
        .apo-balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 14px; padding: 18px 22px;
            display: flex; align-items: center; gap: 14px; color: #fff; margin-bottom: 18px;
        }
        .apo-balance-card i { font-size: 32px; opacity: .9; }
        .apo-balance-card .label { font-size: 12.5px; opacity: .85; }
        .apo-balance-card .value { font-size: 24px; font-weight: 700; }

        .apo-card { background: #fff; border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; border: 1px solid #eef0f2; }
        .apo-card-title { font-size: 14.5px; font-weight: 700; color: #374151; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .apo-card-title i { font-size: 18px; color: #667eea; }

        .apo-info-row { display: flex; flex-wrap: wrap; gap: 14px; }
        .apo-info-chip { background: #f8fafc; border-radius: 8px; padding: 8px 14px; min-width: 160px; }
        .apo-info-chip label { display: block; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 2px; }
        .apo-info-chip .value { font-size: 13.5px; font-weight: 600; color: #1f2937; }

        .apo-screenshot-card {
            background: #fff; border: 1px solid #f1f5f9; border-radius: 10px; padding: 12px 14px;
            display: flex; align-items: center; gap: 12px; margin-bottom: 10px;
        }
        .apo-screenshot-card .thumb { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; border: 1px solid #e5e7eb; flex-shrink: 0; }
        .apo-screenshot-card .thumb-placeholder {
            width: 48px; height: 48px; border-radius: 8px; background: #f3f4f6; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; color: #9ca3af;
        }
        .apo-screenshot-card .details { flex: 1; min-width: 0; font-size: 12.5px; color: #4b5563; }
        .apo-status-pill { padding: 3px 10px; border-radius: 20px; font-size: 10.5px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase; }
        .apo-status-pill.accepted { background: #d1fae5; color: #065f46; }
        .apo-status-pill.rejected { background: #fee2e2; color: #991b1b; }
        .apo-status-pill.pending  { background: #fef3c7; color: #92400e; }
        .apo-remove-chip {
            border: none; background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 600;
            padding: 5px 12px; border-radius: 20px; cursor: pointer; flex-shrink: 0;
        }

        .apo-upload-row { background: #fff; border: 1px dashed #d1d5db; border-radius: 10px; padding: 14px; }

        .btn-submit-po {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: #fff;
            font-weight: 600; padding: 11px 26px; border-radius: 10px; font-size: 14.5px; transition: all .2s;
        }
        .btn-submit-po:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(102,126,234,.4); color: #fff; }
        .btn-submit-po:disabled { opacity: .6; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-secondary-sm {
            background: #667eea; border: none; color: #fff; font-weight: 600; font-size: 13px;
            padding: 7px 16px; border-radius: 8px;
        }
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
                    <div class="container-fluid">

                        <div class="row">
                            <div class="col d-flex align-items-center justify-content-between flex-wrap" style="gap:10px;">
                                <div class="page-description">
                                    <h1 style="margin:0;"><?php echo $title;?></h1>
                                </div>
                                <a href="manage-advance-payments.php" class="btn-secondary-sm" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                                    <i class="material-icons" style="font-size:17px;">list_alt</i> My Advance Payments
                                </a>
                            </div>
                        </div>
                        <br/>

                        <div class="apo-balance-card">
                            <i class="material-icons-outlined">account_balance_wallet</i>
                            <div>
                                <div class="label">Available Advance Balance</div>
                                <div class="value">&#8377;<?=inr_format($advBalance, 2)?></div>
                            </div>
                        </div>

                        <div class="apo-card">
                            <div class="apo-card-title"><i class="material-icons-outlined">badge</i>Territory Partner Details</div>
                            <div class="apo-info-row">
                                <div class="apo-info-chip">
                                    <label>Territory Partner</label>
                                    <div class="value"><?=htmlspecialchars($Login_user_name)?></div>
                                </div>
                                <div class="apo-info-chip">
                                    <label>TP Code</label>
                                    <div class="value"><?=htmlspecialchars($Login_user_tp_id)?></div>
                                </div>
                                <div class="apo-info-chip">
                                    <label>Mobile</label>
                                    <div class="value"><?=htmlspecialchars($Login_user_mobile)?></div>
                                </div>
                            </div>
                        </div>

                        <div class="apo-card">
                            <div class="apo-card-title"><i class="material-icons-outlined">payments</i>Payment Details</div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-3">
                                    <label class="form-label">Payment Date</label>
                                    <input type="date" id="adv_payment_date" class="form-control" value="<?=htmlspecialchars($draftSubmission['payment_date'] ?? $today)?>" min="<?=$minDate?>" max="<?=$today?>">
                                    <small class="text-muted">Up to 2 days back only.</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Amount Paid</label>
                                    <input type="number" min="0" step="0.01" id="adv_amount" class="form-control" placeholder="0.00" value="<?=$draftSubmission ? htmlspecialchars($draftSubmission['amount']) : ''?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Payment Mode</label>
                                    <select id="adv_payment_mode" class="form-control">
                                        <option value="">Select</option>
                                        <?php foreach (['UPI','NEFT','RTGS','IMPS','Bank Transfer','Cash','Cheque','Demand Draft','Other'] as $mode): ?>
                                        <option value="<?=$mode?>" <?=($draftSubmission['payment_mode'] ?? '') === $mode ? 'selected' : ''?>><?=$mode?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">UTR / Reference Number</label>
                                    <input type="text" id="adv_reference" class="form-control" placeholder="UTR / Transaction ID" value="<?=$draftSubmission ? htmlspecialchars($draftSubmission['reference_number']) : ''?>">
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-12">
                                    <label class="form-label">Note (optional)</label>
                                    <input type="text" id="adv_note" class="form-control" maxlength="500" placeholder="Any additional note for the reviewer" value="<?=$draftSubmission ? htmlspecialchars($draftSubmission['note'] ?? '') : ''?>">
                                </div>
                            </div>

                            <div id="advScreenshotList"></div>

                            <div class="apo-upload-row" id="advScreenshotUploadRow">
                                <input type="file" id="adv_screenshot_file" accept="image/*,.heic,.heif" class="form-control d-inline-block mb-2" style="max-width:320px;">
                                <button type="button" class="btn btn-secondary btn-sm" id="advScreenshotUploadBtn" onclick="uploadAdvanceScreenshot()">Upload</button>
                                <small class="text-muted d-block mt-1">Max file size: 10 MB per image, up to 5 images. Each screenshot is verified individually.</small>
                                <div id="advScreenshotStatus" class="mt-1" style="font-size:12.5px;"></div>
                            </div>

                            <div id="advReadyNote" style="display:none;font-size:12.5px;color:#065f46;margin-top:12px;">
                                <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">check_circle</i>
                                At least one screenshot has been uploaded — you can submit for review.
                            </div>

                            <button type="button" id="advSubmitBtn" class="btn-submit-po mt-3" onclick="submitAdvancePayment();" disabled>
                                <i class="material-icons" style="vertical-align:middle;font-size:18px;">send</i> Submit Payment
                            </button>
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
    <script src="https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js"></script>

    <script>
    var MAX_SCREENSHOT_BYTES = 10 * 1024 * 1024;
    var MAX_SCREENSHOTS = 5;
    var currentSubmissionId = <?=$draftSubmission ? (int)$draftSubmission['id'] : 'null'?>;
    var advScreenshots = <?=json_encode(array_map(function($s) {
        return [
            'id' => (int)$s['id'],
            'status' => $s['status'],
            'detected_amount' => $s['detected_amount'] !== null ? (float)$s['detected_amount'] : null,
            'reference_number' => $s['reference_number'],
            'reason' => $s['rejection_reason'],
            'file_url' => 'advance_payment_screenshots/' . $s['file_path'],
        ];
    }, $draftScreenshots))?>;

    function statusBadge(status) {
        if (status === 'accepted') return '<span class="apo-status-pill accepted">Accepted</span>';
        if (status === 'rejected') return '<span class="apo-status-pill rejected">Rejected</span>';
        // Automatic verification has already finished by the time this
        // status shows — "Pending Review" reads like the upload itself is
        // still in progress, so say explicitly that it's now waiting on a
        // person, not stuck.
        return '<span class="apo-status-pill pending">Awaiting Company Review</span>';
    }

    function renderAdvScreenshotList() {
        var list = document.getElementById('advScreenshotList');
        list.innerHTML = '';
        advScreenshots.forEach(function(s) {
            var detail = '';
            if (s.status === 'accepted') {
                detail = 'Amount: ₹' + s.detected_amount.toFixed(2) + ', Ref: ' + s.reference_number;
            } else if (s.reason) {
                detail = s.reason;
            }
            var isHeic = s.file_url && /\.hei[cf]$/i.test(s.file_url);
            var thumbHtml = '<div class="thumb-placeholder"><i class="material-icons-outlined" style="font-size:20px;">image</i></div>';
            if (s.file_url && !isHeic) {
                thumbHtml = '<a href="' + s.file_url + '" target="_blank"><img class="thumb" src="' + s.file_url + '"></a>';
            } else if (s.file_url && isHeic) {
                thumbHtml = '<a href="' + s.file_url + '" target="_blank" class="thumb-placeholder" title="Download HEIC"><i class="material-icons-outlined" style="font-size:20px;">description</i></a>';
            }

            var card = document.createElement('div');
            card.className = 'apo-screenshot-card';
            card.innerHTML =
                thumbHtml +
                '<div class="details">' + statusBadge(s.status) + '<div class="mt-1">' + detail + '</div></div>' +
                '<button type="button" class="apo-remove-chip" onclick="removeAdvanceScreenshot(' + s.id + ')">Remove</button>';
            list.appendChild(card);
        });

        document.getElementById('advReadyNote').style.display = advScreenshots.length > 0 ? '' : 'none';
        document.getElementById('advSubmitBtn').disabled = advScreenshots.length === 0;

        var uploadRow = document.getElementById('advScreenshotUploadRow');
        uploadRow.style.display = advScreenshots.length >= MAX_SCREENSHOTS ? 'none' : '';
    }

    function isHeicFile(file) {
        var name = (file.name || '').toLowerCase();
        var type = (file.type || '').toLowerCase();
        return type === 'image/heic' || type === 'image/heif' ||
            name.endsWith('.heic') || name.endsWith('.heif');
    }

    function nativeHeicToJpeg(file) {
        if (typeof createImageBitmap !== 'function') return Promise.reject(new Error('createImageBitmap unsupported'));
        return createImageBitmap(file).then(function(bitmap) {
            var canvas = document.createElement('canvas');
            canvas.width = bitmap.width;
            canvas.height = bitmap.height;
            canvas.getContext('2d').drawImage(bitmap, 0, 0);
            return new Promise(function(resolve, reject) {
                canvas.toBlob(function(blob) {
                    if (blob) resolve(blob); else reject(new Error('canvas export failed'));
                }, 'image/jpeg', 0.9);
            });
        });
    }

    function validateAdvanceForm() {
        var amount = parseFloat(document.getElementById('adv_amount').value);
        var mode = document.getElementById('adv_payment_mode').value;
        var reference = document.getElementById('adv_reference').value.trim();
        var date = document.getElementById('adv_payment_date').value;

        if (!date) { alert('Select a payment date.'); return false; }
        if (!amount || amount <= 0) { alert('Enter a valid amount.'); return false; }
        if (!mode) { alert('Select a payment mode.'); return false; }
        if (!reference) { alert('Enter the UTR / reference number.'); return false; }
        return true;
    }

    function uploadAdvanceScreenshot() {
        var input = document.getElementById('adv_screenshot_file');
        var statusEl = document.getElementById('advScreenshotStatus');
        statusEl.textContent = '';

        if (advScreenshots.length >= MAX_SCREENSHOTS) {
            alert('You can upload a maximum of ' + MAX_SCREENSHOTS + ' screenshots.');
            return;
        }
        if (!validateAdvanceForm()) return;

        var file = input.files[0];
        if (!file) { alert('Choose an image first.'); return; }
        var heic = isHeicFile(file);
        if (!heic && !file.type.startsWith('image/')) { alert('Please select an image file.'); return; }
        if (file.size > MAX_SCREENSHOT_BYTES) { alert('Image is too large. Maximum allowed size is 10 MB.'); return; }

        if (heic) {
            statusEl.textContent = 'Converting HEIC image…';
            var jpegName = file.name.replace(/\.(heic|heif)$/i, '.jpg');

            nativeHeicToJpeg(file)
                .catch(function(nativeErr) {
                    console.warn('Native HEIC decode unavailable, trying heic2any:', nativeErr);
                    return heic2any({ blob: file, toType: 'image/jpeg', quality: 0.9 })
                        .then(function(converted) {
                            return Array.isArray(converted) ? converted[0] : converted;
                        });
                })
                .then(function(jpegBlob) {
                    sendAdvanceScreenshot(jpegBlob, jpegName);
                })
                .catch(function(err) {
                    console.error('HEIC conversion failed:', err);
                    var reason = (err && err.message) ? err.message : String(err);
                    if (confirm('Could not auto-convert this HEIC image (' + reason + ').\n\n' +
                        'Upload the original file anyway for manual review by the company? ' +
                        '(Or press Cancel to try a JPG/PNG instead.)')) {
                        sendAdvanceScreenshot(file, file.name);
                    } else {
                        statusEl.textContent = 'Please try a JPG/PNG instead.';
                    }
                });
            return;
        }

        sendAdvanceScreenshot(file, file.name);
    }

    function sendAdvanceScreenshot(fileOrBlob, filename) {
        var input = document.getElementById('adv_screenshot_file');
        var statusEl = document.getElementById('advScreenshotStatus');
        var btn = document.getElementById('advScreenshotUploadBtn');
        btn.disabled = true;
        statusEl.textContent = 'Uploading and verifying…';

        var formData = new FormData();
        formData.append('screenshot', fileOrBlob, filename);
        formData.append('amount', document.getElementById('adv_amount').value);
        formData.append('payment_date', document.getElementById('adv_payment_date').value);
        formData.append('payment_mode', document.getElementById('adv_payment_mode').value);
        formData.append('reference_number', document.getElementById('adv_reference').value.trim());
        formData.append('note', document.getElementById('adv_note').value.trim());
        if (currentSubmissionId) formData.append('submission_id', currentSubmissionId);

        fetch('upload-advance-payment-screenshot.php', { method: 'POST', body: formData })
            .then(function(r) {
                return r.text().then(function(text) {
                    try {
                        return JSON.parse(text);
                    } catch (parseErr) {
                        console.error('Upload response was not valid JSON:', text);
                        throw new Error('bad_response');
                    }
                });
            })
            .then(function(data) {
                btn.disabled = false;
                if (!data.success) {
                    statusEl.textContent = data.message || 'Upload failed.';
                    return;
                }
                currentSubmissionId = data.submission_id;
                advScreenshots.push(data.screenshot);
                input.value = '';
                statusEl.textContent = '';
                renderAdvScreenshotList();
            })
            .catch(function(err) {
                btn.disabled = false;
                statusEl.textContent = (err && err.message === 'bad_response')
                    ? 'Upload could not be verified automatically — please try again.'
                    : 'Upload failed — please check your connection and try again.';
            });
    }

    function removeAdvanceScreenshot(id) {
        fetch('remove-advance-payment-screenshot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'screenshot_id=' + encodeURIComponent(id)
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) { alert(data.message || 'Could not remove screenshot.'); return; }
                advScreenshots = advScreenshots.filter(function(s) { return s.id !== id; });
                renderAdvScreenshotList();
            })
            .catch(function() { alert('Could not remove screenshot — please check your connection.'); });
    }

    function submitAdvancePayment() {
        if (!currentSubmissionId || advScreenshots.length === 0) {
            alert('Upload at least one payment screenshot before submitting.');
            return;
        }
        if (!confirm('Submit this advance payment for review?')) return;

        var btn = document.getElementById('advSubmitBtn');
        btn.disabled = true;

        fetch('submit-advance-payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'submission_id=' + encodeURIComponent(currentSubmissionId)
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    alert(data.message || 'Could not submit.');
                    btn.disabled = false;
                    return;
                }
                window.location.href = 'manage-advance-payments.php';
            })
            .catch(function() {
                alert('Could not reach the server — please try again.');
                btn.disabled = false;
            });
    }

    $(function() {
        renderAdvScreenshotList();
    });
    </script>
</body>
</html>
