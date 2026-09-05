<?php
// femi9/billing/company/get-espo-users.php
// JSON endpoint listing EspoCRM users, for the Sales BDM <-> CRM user mapping dropdown.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
require_once __DIR__ . '/../includes/EspoDb.php';

header('Content-Type: application/json');

$conn = getEspoDbConnection();
if ($conn === null) {
    echo json_encode(['error' => 'CRM data unavailable', 'users' => []]);
    exit;
}

$result = $conn->query("SELECT id, first_name, last_name, email FROM user WHERE deleted = 0 ORDER BY first_name, last_name");
$users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'email' => $row['email'],
        ];
    }
}
$conn->close();

echo json_encode(['error' => null, 'users' => $users]);
