<?php
// femi9/billing/company/machine-code-manage.php
//
// AJAX backend for the "Manage Machine Codes" modal on
// neksomo-piece-pack-convert.php — add/edit/delete (soft, via is_active)
// entries in the global machine_code_master list, and list them so the
// modal and every row's dropdown can refresh after a change.

include("checksession.php");
require_once("include/MachineCodes.php");
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

ensure_machine_code_master_table($db_conn);

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'list') {
    $rows = $db_conn->query(
        "SELECT id, code, name, is_active FROM machine_code_master ORDER BY code ASC"
    )->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'machine_codes' => $rows]);
    exit;
}

// Every mutating action requires CSRF — 'list' above is read-only so it's
// exempt, same convention as other AJAX endpoints on this page.
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_csrf']);
    exit;
}

if ($action === 'add' || $action === 'edit') {
    $id   = (int) ($_POST['id'] ?? 0);
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');

    if ($code === '') {
        echo json_encode(['success' => false, 'error' => 'Machine code is required.']);
        exit;
    }

    if ($action === 'add') {
        $stmt = $db_conn->prepare("INSERT INTO machine_code_master (code, name) VALUES (?, ?)");
        $stmt->bind_param('ss', $code, $name);
    } else {
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Missing machine code id.']);
            exit;
        }
        $stmt = $db_conn->prepare("UPDATE machine_code_master SET code = ?, name = ? WHERE id = ?");
        $stmt->bind_param('ssi', $code, $name, $id);
    }

    if (!$stmt->execute()) {
        $isDupe = $db_conn->errno === 1062;
        $stmt->close();
        echo json_encode(['success' => false, 'error' => $isDupe ? 'That machine code already exists.' : 'Could not save machine code.']);
        exit;
    }
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Missing machine code id.']);
        exit;
    }
    // Soft delete — a machine code already referenced by past stock_ledger
    // rows must keep resolving for history/reporting, so it's deactivated
    // rather than removed and simply drops out of get_active_machine_codes().
    $stmt = $db_conn->prepare("UPDATE machine_code_master SET is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action.']);
