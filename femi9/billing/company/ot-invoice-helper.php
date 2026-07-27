<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ot_channels');
include("config.php");
header('Content-Type: application/json');

// Only these three channels use the WEB/ID/WA sequential invoice-number convention.
$prefixMap = [
    'WEBSITE'        => 'WEB',
    'ID CONCEPT'     => 'ID',
    'WHATSAPP SALES' => 'WA',
];

$action     = $_GET['action'] ?? '';
$cat        = trim($_GET['cat'] ?? '');
$inv_number = trim($_GET['inv_number'] ?? '');

if (!isset($prefixMap[$cat])) {
    echo json_encode(['error' => 'not tracked']);
    exit;
}

$prefix = $prefixMap[$cat];

// Indian financial year (Apr–Mar), e.g. 2026-07-22 -> "26-27"
$month = (int)date('n');
$year  = (int)date('Y');
$fyStartYear = $month >= 4 ? $year : $year - 1;
$fy = substr($fyStartYear, -2) . '-' . substr($fyStartYear + 1, -2);

if ($action === 'next') {
    $likePattern = $prefix . '/' . $fy . '/%';
    $stmt = $db_conn->prepare(
        "SELECT inv_number FROM ot_sales_invoice WHERE cat = ? AND inv_number LIKE ?"
    );
    $stmt->bind_param('ss', $cat, $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();

    $max = 0;
    while ($row = $result->fetch_assoc()) {
        $parts = explode('/', $row['inv_number']);
        $suffix = end($parts);
        if (ctype_digit($suffix)) {
            $max = max($max, (int)$suffix);
        }
    }
    $stmt->close();

    echo json_encode(['number' => $prefix . '/' . $fy . '/' . ($max + 1)]);
    exit;
}

if ($action === 'check') {
    if ($inv_number === '') {
        echo json_encode(['exists' => false]);
        exit;
    }
    $stmt = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM ot_sales_invoice WHERE cat = ? AND inv_number = ?"
    );
    $stmt->bind_param('ss', $cat, $inv_number);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode(['exists' => (int)$row['n'] > 0]);
    exit;
}

echo json_encode(['error' => 'invalid action']);
