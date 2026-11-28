<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpStaffFeedback.php';

// Date filter — URL-ல ?from_date=...&to_date=... வந்தா அதை எடுக்கும்,
// இல்லைனா இந்த மாதத்தின் 1st தேதி முதல் இன்னைக்கு வரைக்கும் default.
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate   = $_GET['to_date']   ?? date('Y-m-d');

// $salesBdmID ஏற்கனவே config.php-ல set ஆகிடுச்சு (logged-in BDM-ஓட id).
// 'salesbdm' nu rendavathu param pass panrathu mukkiyam — idhu than
// TP-feedback system-ல "idhu oru BDM-க்கு ratings" nu சொல்றது.
$myScore = getCumulativeTpFeedbackScore($db_conn, 'salesbdm', (int)$salesBdmID, $fromDate, $toDate);
$canSeeBreakdown = !empty($_SESSION['LOGGED_IN_VIA_COMPANY']) || !empty($_companyBridgeView);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales KPI </title>
</head>
<body>
    <div class="app-content">
        <div class="content-wrapper">
            <div class="container-fluid">
                <div class="row">
                    <h1>Salesbdm KPI</h1>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card" style="border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.08);">
                            <div class="card-body">
                                <h6>⭐ Your Total Score</h6>
                                <p class="text-muted" style="font-size:12px;">
                                    Average TP feedback score for the selected date range (out of 5 per rating).
                                </p>
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <small class="text-muted">AVERAGE SCORE</small><br>
                                        <strong style="font-size:18px;">
                                            <?php echo $myScore['count'] > 0 ? number_format($myScore['avg_score_5'], 1) . ' / 5' : 'No ratings yet'; ?>
                                        </strong>
                                    </div>
                                    <div>
                                        <small class="text-muted">RATINGS RECEIVED</small><br>
                                        <strong style="font-size:18px;"><?php echo (int)$myScore['count']; ?></strong>
                                    </div>
                                </div>
                                <?php if ($canSeeBreakdown && $myScore['count'] > 0): ?>
                                <div style="margin-top:10px;border-top:1px solid #f1f5f9;padding-top:10px;">
                                    <p style="font-size:11px;color:#9ca3af;margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px;">Per-TP breakdown (visible to Company only)</p>
                                    <div style="max-height:180px;overflow-y:auto;">
                                        <?php foreach ($myScore['rows'] as $_r): ?>
                                        <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:3px 0;border-bottom:1px dotted #f1f5f9;">
                                            <span><?php echo htmlspecialchars($_r['tp_name'] ?? '-'); ?> (<?php echo htmlspecialchars($_r['tp_code'] ?? '-'); ?>) &mdash; <?php echo htmlspecialchars($_r['period_month']); ?></span>
                                            <span style="font-weight:600;"><?php echo number_format((float)$_r['score_5'], 1); ?> / 5</span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>