<?php
// Paginated JSON list behind the "N signed" / "N pending" badges on
// manage-agreements.php — same admin/finance-only gate as that page, since
// this is just that page's counts broken out into rows.
include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");
error_reporting(0);
header('Content-Type: application/json');

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['admin', 'finance'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$type = ($_GET['type'] ?? '') === 'tp' ? 'tp' : 'cp';
$status = ($_GET['status'] ?? '') === 'pending' ? 'pending' : 'signed';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

if ($type === 'cp') {
    if ($status === 'signed') {
        $countRes = $db_conn->query("
            SELECT COUNT(*) c FROM channel_partners cp
            JOIN channel_partner_agreements cpa ON cpa.channel_partner_id = cp.id
            WHERE cpa.is_locked = 1
        ");
        $stmt = $db_conn->prepare("
            SELECT cp.id, cp.cp_id, cp.name, cp.company_name, cpa.signed_at
            FROM channel_partners cp
            JOIN channel_partner_agreements cpa ON cpa.channel_partner_id = cp.id
            WHERE cpa.is_locked = 1
            ORDER BY cpa.signed_at DESC
            LIMIT ? OFFSET ?
        ");
    } else {
        $countRes = $db_conn->query("
            SELECT COUNT(*) c FROM channel_partners cp
            LEFT JOIN channel_partner_agreements cpa ON cpa.channel_partner_id = cp.id AND cpa.is_locked = 1
            WHERE cpa.id IS NULL
        ");
        $stmt = $db_conn->prepare("
            SELECT cp.id, cp.cp_id, cp.name, cp.company_name, NULL AS signed_at
            FROM channel_partners cp
            LEFT JOIN channel_partner_agreements cpa ON cpa.channel_partner_id = cp.id AND cpa.is_locked = 1
            WHERE cpa.id IS NULL
            ORDER BY cp.name ASC
            LIMIT ? OFFSET ?
        ");
    }
    $idField = 'cp_id';
    $linkParam = 'cp_id';
} else {
    if ($status === 'signed') {
        $countRes = $db_conn->query("
            SELECT COUNT(*) c FROM territory_partners tp
            JOIN territory_partner_agreements tpa ON tpa.territory_partner_id = tp.id
            WHERE tpa.is_locked = 1 AND tp.deleted_at IS NULL
        ");
        $stmt = $db_conn->prepare("
            SELECT tp.id, tp.tp_id, tp.name, tp.company_name, tpa.signed_at
            FROM territory_partners tp
            JOIN territory_partner_agreements tpa ON tpa.territory_partner_id = tp.id
            WHERE tpa.is_locked = 1 AND tp.deleted_at IS NULL
            ORDER BY tpa.signed_at DESC
            LIMIT ? OFFSET ?
        ");
    } else {
        $countRes = $db_conn->query("
            SELECT COUNT(*) c FROM territory_partners tp
            LEFT JOIN territory_partner_agreements tpa ON tpa.territory_partner_id = tp.id AND tpa.is_locked = 1
            WHERE tpa.id IS NULL AND tp.deleted_at IS NULL
        ");
        $stmt = $db_conn->prepare("
            SELECT tp.id, tp.tp_id, tp.name, tp.company_name, NULL AS signed_at
            FROM territory_partners tp
            LEFT JOIN territory_partner_agreements tpa ON tpa.territory_partner_id = tp.id AND tpa.is_locked = 1
            WHERE tpa.id IS NULL AND tp.deleted_at IS NULL
            ORDER BY tp.name ASC
            LIMIT ? OFFSET ?
        ");
    }
    $idField = 'tp_id';
    $linkParam = 'tp_id';
}

$total = (int) ($countRes->fetch_assoc()['c'] ?? 0);
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$out = array_map(function ($r) use ($idField, $linkParam) {
    return [
        'id'            => (int) $r['id'],
        'code'          => $r[$idField],
        'name'          => $r['name'],
        'company_name'  => $r['company_name'],
        'signed_at'     => $r['signed_at'] ? date('d-M-Y h:i A', strtotime($r['signed_at'])) : null,
        'edit_url'      => 'manage-agreements.php?tab=' . ($linkParam === 'cp_id' ? 'cp' : 'tp') . '&' . $linkParam . '=' . (int) $r['id'],
    ];
}, $rows);

echo json_encode([
    'rows'      => $out,
    'total'     => $total,
    'page'      => $page,
    'per_page'  => $perPage,
    'total_pages' => max(1, (int) ceil($total / $perPage)),
]);
