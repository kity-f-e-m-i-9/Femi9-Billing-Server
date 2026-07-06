<?php
/**
 * Expense Tracker — Delete an uploaded batch (and its line items via FK cascade)
 */

declare(strict_types=1);

header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Content-Type: application/json; charset=utf-8");

session_start();

require_once("checksession.php");
require_once("config.php");
require_once("include/GodownAccess.php");

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (empty($_SESSION['LOGIN_USER_ID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$import_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: (int)($_POST['id'] ?? 0);

if (!$import_id || $import_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid upload ID']);
    exit;
}

$stmt = $db_conn->prepare("SELECT id, company_id, source_filename FROM expense_imports WHERE id = ?");
$stmt->bind_param("i", $import_id);
$stmt->execute();
$batch = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$batch) {
    echo json_encode(['success' => false, 'message' => 'Upload not found']);
    exit;
}

if (!is_godown_allowed($db_conn, (int)$batch['company_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$del_stmt = $db_conn->prepare("DELETE FROM expense_imports WHERE id = ?");
$del_stmt->bind_param("i", $import_id);

if ($del_stmt->execute()) {
    error_log("Expense import deleted - ID: {$import_id}, File: {$batch['source_filename']}, User: {$_SESSION['LOGIN_USER_ID']}");
    echo json_encode(['success' => true, 'message' => 'Upload deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete upload']);
}

$del_stmt->close();
