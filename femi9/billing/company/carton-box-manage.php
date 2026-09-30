<?php
// femi9/billing/company/carton-box-manage.php
//
// AJAX backend for manage-cartons.php's "Manage Carton Types" and
// "Manage Damage Reasons" controls — add/edit/delete (soft, via
// is_active) entries against carton_box_types or
// carton_box_damage_reason_master, parameterized by `list` (type|reason)
// so one file serves both lists rather than duplicating this whole CRUD
// file twice. Mirrors bundle-reason-manage.php.

include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/CartonBoxes.php");
include("config.php");
header('Content-Type: application/json');
error_reporting(0);

// Dedicated to the neksomo login (admin retained for oversight/support),
// matching every other carton/bundle page's gate.
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$list = $_POST['list'] ?? $_GET['list'] ?? '';
if (!in_array($list, ['type', 'reason'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid list.']);
    exit;
}
$table = $list === 'type' ? 'carton_box_types' : 'carton_box_damage_reason_master';
$labelCol = $list === 'type' ? 'name' : 'label';

if ($list === 'type') {
    ensure_carton_box_types_table($db_conn);
} else {
    ensure_carton_box_damage_reason_table($db_conn);
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'list') {
    $rows = $db_conn->query(
        "SELECT id, $labelCol AS label, is_active FROM $table ORDER BY $labelCol ASC"
    )->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'items' => $rows]);
    exit;
}

// Every mutating action requires CSRF — 'list' above is read-only so it's
// exempt, same convention as bundle-reason-manage.php.
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_csrf']);
    exit;
}

if ($action === 'add' || $action === 'edit') {
    $id    = (int) ($_POST['id'] ?? 0);
    $label = trim($_POST['label'] ?? '');

    if ($label === '') {
        echo json_encode(['success' => false, 'error' => 'Label is required.']);
        exit;
    }

    if ($action === 'add') {
        $stmt = $db_conn->prepare("INSERT INTO $table ($labelCol) VALUES (?)");
        $stmt->bind_param('s', $label);
    } else {
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Missing id.']);
            exit;
        }
        $stmt = $db_conn->prepare("UPDATE $table SET $labelCol = ? WHERE id = ?");
        $stmt->bind_param('si', $label, $id);
    }

    if (!$stmt->execute()) {
        $isDupe = $db_conn->errno === 1062;
        $stmt->close();
        echo json_encode(['success' => false, 'error' => $isDupe ? 'That entry already exists.' : 'Could not save.']);
        exit;
    }
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Missing id.']);
        exit;
    }
    // Soft delete — a type/reason already referenced by past balance or
    // adjustment rows must keep resolving for history/reporting, so it's
    // deactivated rather than removed.
    $stmt = $db_conn->prepare("UPDATE $table SET is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action.']);
