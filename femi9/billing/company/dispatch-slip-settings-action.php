<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/DispatchSlipSettings.php';
error_reporting(0);

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: manage-products?error=csrf"); exit;
}

$box   = (int)($_POST['overall_packs_per_box'] ?? 0);
$cover = (int)($_POST['overall_packs_per_cover'] ?? 0);
if ($box < 1 || $cover < 1) {
    header("Location: manage-products?error=invalid_packs_per_box"); exit;
}

dispatchSlipEnsureSettingsTable($db_conn);
$stmt = $db_conn->prepare("UPDATE dispatch_slip_settings SET overall_packs_per_box = ?, overall_packs_per_cover = ? WHERE id = 1");
$stmt->bind_param('ii', $box, $cover);
$stmt->execute();
$stmt->close();

header("Location: manage-products?packs_per_box_saved=1");
exit;
