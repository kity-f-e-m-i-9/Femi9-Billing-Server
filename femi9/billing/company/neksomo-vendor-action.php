<?php
declare(strict_types=1);

include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

function redirectWithMessage(string $location, string $message = ''): void {
    $url = $location . ($message ? '?' . $message : '');
    header("Location: $url");
    exit();
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    redirectWithMessage('neksomo-vendor-manage.php', 'error');
}

$action      = $_POST['action'] ?? '';
$vendor_name = trim($_POST['vendor_name'] ?? '');
$address     = trim($_POST['address'] ?? '');
$gstin       = trim($_POST['gstin'] ?? '');
$phone       = trim($_POST['phone'] ?? '');
$email       = trim($_POST['email'] ?? '');
$created_by  = $_SESSION['LOGIN_USER'] ?? 'system';

if ($action === 'insert-vendor') {
    if ($vendor_name === '') {
        redirectWithMessage('neksomo-vendor-add.php', 'error');
    }

    $dup = $db_conn->prepare("SELECT id FROM neksomo_vendors WHERE vendor_name = ?");
    $dup->bind_param('s', $vendor_name);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        $dup->close();
        redirectWithMessage('neksomo-vendor-add.php', 'nametaken');
    }
    $dup->close();

    $stmt = $db_conn->prepare("INSERT INTO neksomo_vendors (vendor_name, address, gstin, phone, email, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssss', $vendor_name, $address, $gstin, $phone, $email, $created_by);
    $stmt->execute();
    $stmt->close();

    redirectWithMessage('neksomo-vendor-manage.php', 'addsuccess');
} elseif ($action === 'update-vendor') {
    $id = (int)($_POST['vendor_id'] ?? 0);
    if (!$id || $vendor_name === '') {
        redirectWithMessage('neksomo-vendor-manage.php', 'error');
    }

    $dup = $db_conn->prepare("SELECT id FROM neksomo_vendors WHERE vendor_name = ? AND id != ?");
    $dup->bind_param('si', $vendor_name, $id);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        $dup->close();
        redirectWithMessage('neksomo-vendor-edit.php?id=' . $id, 'nametaken');
    }
    $dup->close();

    $stmt = $db_conn->prepare("UPDATE neksomo_vendors SET vendor_name = ?, address = ?, gstin = ?, phone = ?, email = ? WHERE id = ?");
    $stmt->bind_param('sssssi', $vendor_name, $address, $gstin, $phone, $email, $id);
    $stmt->execute();
    $stmt->close();

    redirectWithMessage('neksomo-vendor-manage.php', 'updatesuccess');
} else {
    redirectWithMessage('neksomo-vendor-manage.php');
}
