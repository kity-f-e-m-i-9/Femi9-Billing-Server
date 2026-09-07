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

// EspoCRM's `user` table has no `email` column of its own — email
// addresses live in a separate email_address table, joined through
// entity_email_address (entity_type='User', primary=1 picks the user's
// primary address). Also restricted to active, regular users so the
// dropdown doesn't list API/system accounts or deactivated ex-staff.
$sql = "SELECT u.id, u.first_name, u.last_name, ea.name AS email
        FROM user u
        LEFT JOIN entity_email_address eea
            ON eea.entity_id = u.id AND eea.entity_type = 'User' AND eea.primary = 1 AND eea.deleted = 0
        LEFT JOIN email_address ea
            ON ea.id = eea.email_address_id AND ea.deleted = 0
        WHERE u.deleted = 0 AND u.is_active = 1 AND u.type = 'regular'
        ORDER BY u.first_name, u.last_name";
$result = $conn->query($sql);
$users = [];
$queryError = null;
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'email' => $row['email'],
        ];
    }
} else {
    // Surface the real failure instead of silently returning an empty
    // list with error:null — that's what made this fail invisibly before.
    $queryError = 'CRM query failed';
}
$conn->close();

echo json_encode(['error' => $queryError, 'users' => $users]);
