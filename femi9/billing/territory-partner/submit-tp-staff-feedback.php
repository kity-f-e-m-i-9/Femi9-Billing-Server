<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");
require_once(__DIR__ . '/../shared/TpStaffFeedback.php');

// Saves one month-end rating (5 questions, 1-5 stars each) a TP gives their
// DM or SalesBDM — see dashboard.php's tpFeedbackPending popup. Re-validates
// the target actually belongs to this TP (never trust the posted
// target_type/target_id alone) before saving, same pattern as every other
// TP-side action this session. 2026-11-24, user-requested.

$tp_id = (int)$Login_user_IDvl;
$targetType = $_POST['target_type'] ?? '';
$targetId = (int)($_POST['target_id'] ?? 0);

if (!in_array($targetType, ['dm', 'salesbdm'], true) || $targetId <= 0) {
    header('Location: dashboard.php');
    exit;
}

$validTargetIds = $targetType === 'dm'
    ? array_column(getTpAssignedDmRows($db_conn, $tp_id), 'id')
    : array_column(getTpAssignedSalesBdmRows($db_conn, $tp_id), 'id');

if (!in_array($targetId, $validTargetIds, true)) {
    $_SESSION['errorMessage'] = "That doesn't look right — please try again.";
    header('Location: dashboard.php');
    exit;
}

$scores = [];
for ($i = 1; $i <= 5; $i++) {
    $v = (int)($_POST['q' . $i] ?? 0);
    if ($v < 1 || $v > 5) {
        $_SESSION['errorMessage'] = "Please rate all 5 questions before submitting.";
        header('Location: dashboard.php');
        exit;
    }
    $scores[] = $v;
}

saveTpStaffFeedback($db_conn, $tp_id, $targetType, $targetId, date('Y-m'), $scores);

$_SESSION['successMessage'] = "Thanks for your feedback!";
header('Location: dashboard.php');
exit;
