<?php
include("checksession.php");
error_reporting(0);
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$results = [];
$like = '%' . $db_conn->real_escape_string($q) . '%';

// Channel Partners
$r = $db_conn->query("
    SELECT cp_id AS uid, name, mobile, 'CP' AS utype
    FROM channel_partners
    WHERE is_active = 1
      AND (cp_id LIKE '$like' OR name LIKE '$like' OR mobile LIKE '$like')
    LIMIT 8
");
if ($r) while ($row = $r->fetch_assoc()) $results[] = $row;

// Territory Partners
$r = $db_conn->query("
    SELECT tp_id AS uid, name, mobile, 'TP' AS utype
    FROM territory_partners
    WHERE is_active = 1
      AND (tp_id LIKE '$like' OR name LIKE '$like' OR mobile LIKE '$like')
    LIMIT 8
");
if ($r) while ($row = $r->fetch_assoc()) $results[] = $row;

// Super Stockist
$r = $db_conn->query("
    SELECT useridtext AS uid, name, mobile_number AS mobile, 'SS' AS utype
    FROM super_stockiest
    WHERE deleted_at IS NULL AND account_status = 'active'
      AND (useridtext LIKE '$like' OR name LIKE '$like' OR mobile_number LIKE '$like')
    LIMIT 8
");
if ($r) while ($row = $r->fetch_assoc()) $results[] = $row;

// Stockist
$r = $db_conn->query("
    SELECT useridtext AS uid, name, mobile_number AS mobile, 'Stockist' AS utype
    FROM stockiest
    WHERE deleted_at IS NULL AND account_status = 'active'
      AND (useridtext LIKE '$like' OR name LIKE '$like' OR mobile_number LIKE '$like')
    LIMIT 8
");
if ($r) while ($row = $r->fetch_assoc()) $results[] = $row;

// Super Distributor
$r = $db_conn->query("
    SELECT useridtext AS uid, name, mobile_number AS mobile, 'SD' AS utype
    FROM super_distributor
    WHERE deleted_at IS NULL AND account_status = 'active'
      AND (useridtext LIKE '$like' OR name LIKE '$like' OR mobile_number LIKE '$like')
    LIMIT 8
");
if ($r) while ($row = $r->fetch_assoc()) $results[] = $row;

// Distributor
$r = $db_conn->query("
    SELECT useridtext AS uid, name, mobile_number AS mobile, 'D' AS utype
    FROM distributor
    WHERE deleted_at IS NULL AND account_status = 'active'
      AND (useridtext LIKE '$like' OR name LIKE '$like' OR mobile_number LIKE '$like')
    LIMIT 8
");
if ($r) while ($row = $r->fetch_assoc()) $results[] = $row;

// Format for Select2
$out = [];
foreach ($results as $row) {
    $out[] = [
        'id'        => $row['uid'],
        'text'      => $row['name'],
        'user_type' => $row['utype'],
        'mobile'    => $row['mobile'] ?? '',
    ];
}

echo json_encode(['results' => $out]);
exit;
