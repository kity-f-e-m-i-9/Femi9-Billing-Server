<?php
include("checksession.php");
include("config.php");
header('Content-Type: application/json');

// Auto-suggest for Femi Nayan LLP customer invoices only — format: C/FY/LLP{n}
$month = (int)date('n');
$year  = (int)date('Y');
$fyStartYear = $month >= 4 ? $year : $year - 1;
$fy = substr($fyStartYear, -2) . '-' . substr($fyStartYear + 1, -2);

$likePattern = 'C/' . $fy . '/LLP%';
$stmt = $db_conn->prepare("SELECT inv_number FROM invoice WHERE user_type='company' AND inv_number LIKE ?");
$stmt->bind_param('s', $likePattern);
$stmt->execute();
$result = $stmt->get_result();

$max = 0;
while ($row = $result->fetch_assoc()) {
    $suffix = preg_replace('/^.*LLP/', '', $row['inv_number']);
    if (ctype_digit($suffix)) {
        $max = max($max, (int)$suffix);
    }
}
$stmt->close();

echo json_encode(['number' => 'C/' . $fy . '/LLP' . ($max + 1)]);
