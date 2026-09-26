<?php
// femi9/billing/company/bundle-reason-manage.php
//
// AJAX backend for the "Manage Reasons" option inside the Damaged/Extra
// Pieces reason dropdowns on neksomo-piece-pack-convert.php —
// add/edit/delete (soft, via is_active) entries in the global
// damage_reason_master / extra_reason_master lists, parameterized by
// `type` (damage|extra) so one file serves both lists rather than
// duplicating this whole CRUD file twice. Mirrors machine-code-manage.php.

include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/RawMaterialBundles.php");
include("config.php");
header('Content-Type: application/json');
error_reporting(0);

// Dedicated to the neksomo login (admin retained for oversight/support),
// matching Convert Pieces<->Packs' own gate.
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$type = $_POST['type'] ?? $_GET['type'] ?? '';
if (!in_array($type, ['damage', 'extra'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid reason type.']);
    exit;
}
$table = $type === 'damage' ? 'damage_reason_master' : 'extra_reason_master';

ensure_bundle_reason_tables($db_conn);

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'list') {
    $rows = $db_conn->query(
        "SELECT id, label, is_active FROM $table ORDER BY label ASC"
    )->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'reasons' => $rows]);
    exit;
}

// Every mutating action requires CSRF — 'list' above is read-only so it's
// exempt, same convention as machine-code-manage.php.
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_csrf']);
    exit;
}

if ($action === 'add' || $action === 'edit') {
    $id    = (int) ($_POST['id'] ?? 0);
    $label = trim($_POST['label'] ?? '');

    if ($label === '') {
        echo json_encode(['success' => false, 'error' => 'Reason label is required.']);
        exit;
    }

    if ($action === 'add') {
        $stmt = $db_conn->prepare("INSERT INTO $table (label) VALUES (?)");
        $stmt->bind_param('s', $label);
    } else {
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Missing reason id.']);
            exit;
        }
        $stmt = $db_conn->prepare("UPDATE $table SET label = ? WHERE id = ?");
        $stmt->bind_param('si', $label, $id);
    }

    if (!$stmt->execute()) {
        $isDupe = $db_conn->errno === 1062;
        $stmt->close();
        echo json_encode(['success' => false, 'error' => $isDupe ? 'That reason already exists.' : 'Could not save reason.']);
        exit;
    }
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Missing reason id.']);
        exit;
    }
    // Soft delete — a reason already referenced by past adjustment log
    // rows must keep resolving for history/reporting, so it's
    // deactivated rather than removed.
    $stmt = $db_conn->prepare("UPDATE $table SET is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action.']);
