<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner_delete');
error_reporting(0);

// Soft delete — hard-deleting territory_partners had no DB-level cascade to
// the many tables that reference it (invoices, advance payments, purchase
// orders, stock ledgers, ...), so a real DELETE left those rows orphaned
// (dangling territory_partner_id with no matching TP) rather than actually
// cleaning anything up, while permanently destroying the TP's own record
// and all its historical reporting value. deleted_at hides the TP from
// manage-territory-partner.php's list (and is_active=0 reuses the same
// login-blocking gate checksession.php already enforces for a deactivated
// TP), while every other table's data stays intact and queryable.
$_col = $db_conn->query("SHOW COLUMNS FROM territory_partners LIKE 'deleted_at'");
if ($_col && $_col->num_rows === 0) {
    $db_conn->query("ALTER TABLE territory_partners ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER is_active");
}

$enc_id   = $_GET['tpid'] ?? '';
$tp_db_id = (int)base64_decode($enc_id);
if (!$tp_db_id) { header("Location: manage-territory-partner"); exit; }

// A Sales BDM session may only delete a TP inside their own assigned districts.
if (($Login_user_TYPEvl ?? '') === 'salesbdm') {
    require_once __DIR__ . '/../salesbdm/include/BdmTpScope.php';
    $_myTpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID, true);
    if (!in_array($tp_db_id, $_myTpIds, true)) { header("Location: manage-territory-partner"); exit; }
}

// Verify exists
$stmt = $db_conn->prepare("SELECT id, photo, is_active FROM territory_partners WHERE id = ?");
$stmt->bind_param("i", $tp_db_id);
$stmt->execute();
$tp = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$tp) { header("Location: manage-territory-partner"); exit; }

// Soft delete: marks deleted_at and reuses the existing is_active=0
// deactivation gate, but leaves the row (and everything referencing it) in
// place — see the comment above for why a real DELETE was unsafe here.
$stmt_del = $db_conn->prepare("UPDATE territory_partners SET deleted_at = NOW(), is_active = 0 WHERE id = ?");
$stmt_del->bind_param("i", $tp_db_id);
$stmt_del->execute();
$stmt_del->close();

// Free up this TP's assigned locations so they're immediately available to
// hand to another TP (or re-assign on a recreated one) without the company
// having to go edit territory_partner_locations by hand — search-location-
// nodes.php's "is_taken" check only looks at this table, with no awareness
// of deleted_at, so a soft-deleted TP's old rows here would otherwise keep
// showing the location as locked forever. Unlike territory_partners itself,
// this table is a pure current-assignment map with no historical reporting
// value of its own, so deleting these rows outright (not soft-deleting) is
// safe.
$stmt_loc = $db_conn->prepare("DELETE FROM territory_partner_locations WHERE territory_partner_id = ?");
$stmt_loc->bind_param("i", $tp_db_id);
$stmt_loc->execute();
$stmt_loc->close();

require_once __DIR__ . '/../shared/TpStatusHistory.php';
logTpStatusChange($db_conn, $tp_db_id, (int)$tp['is_active'], 0);

header("Location: manage-territory-partner?deletedDone=1");
exit;
