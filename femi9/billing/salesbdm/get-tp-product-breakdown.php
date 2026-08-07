<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
header('Content-Type: application/json');
error_reporting(0);

$tp_id = (int)($_GET['tp_id'] ?? 0);
$type  = ($_GET['type'] ?? 'purchase') === 'downstream' ? 'downstream' : 'purchase';
$from  = isset($_GET['from']) ? date('Y-m-d', strtotime($_GET['from'])) : date('Y-m-01');
$to    = isset($_GET['to'])   ? date('Y-m-d', strtotime($_GET['to']))   : date('Y-m-t');

function respond($rows) {
    echo json_encode(['rows' => $rows]);
    exit;
}

// Only allow drilling into a TP that actually belongs to this Sales BDM.
$myTpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID);
if (!$tp_id || !in_array($tp_id, $myTpIds, true)) {
    respond([]);
}

if ($type === 'purchase') {
    // What this TP bought from Company, per product, plus what they returned.
    $sales = [];
    $stmt = $db_conn->prepare("
        SELECT p.id pid, p.productName, COALESCE(SUM(tii.quantity),0) qty, COALESCE(SUM(tii.amount),0) amt
        FROM tp_invoice_items tii
        JOIN tp_invoices ti ON ti.id = tii.tp_invoice_id
        JOIN products p ON p.id = tii.product_id
        WHERE ti.territory_partner_id = ? AND ti.invoice_date BETWEEN ? AND ?
        GROUP BY p.id, p.productName ORDER BY qty DESC LIMIT 25
    ");
    $stmt->bind_param('iss', $tp_id, $from, $to);
    $stmt->execute();
    $sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $returns = [];
    $rstmt = $db_conn->prepare("
        SELECT ri.prid pid, COALESCE(SUM(ri.qty),0) qty
        FROM user_return_stock_items ri
        WHERE ri.from_usertype='territory_partner' AND ri.from_userid=? AND ri.to_usertype='company'
          AND ri.date BETWEEN ? AND ?
        GROUP BY ri.prid
    ");
    $rstmt->bind_param('iss', $tp_id, $from, $to);
    $rstmt->execute();
    $returns = $rstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $rstmt->close();
    $returnByPid = [];
    foreach ($returns as $r) { $returnByPid[(int)$r['pid']] = (float)$r['qty']; }

    $rows = [];
    foreach ($sales as $s) {
        $rows[] = [
            'name'    => $s['productName'],
            'qty'     => number_format((float)$s['qty'], 0),
            'value'   => number_format((float)$s['amt'], 2),
            'ret_qty' => number_format($returnByPid[(int)$s['pid']] ?? 0, 0),
        ];
    }
    respond($rows);
} else {
    // What this TP sold downstream (to shops/customers), per product, plus returns.
    $sales = call_rows_local($db_conn,
        "SELECT p.id pid, p.productName, COALESCE(SUM(d.qty),0) qty, COALESCE(SUM(d.total),0) amt
         FROM (
             SELECT ii.pr_id, ii.qty, ii.total
             FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
             WHERE i.user_type='territory_partner' AND i.user_id=? AND i.date BETWEEN ? AND ?
             UNION ALL
             SELECT uii.pr_id, uii.qty, uii.total
             FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
             WHERE ui.from_user_type='territory_partner' AND ui.from_user_id=? AND ui.date BETWEEN ? AND ?
         ) d JOIN products p ON p.id=d.pr_id
         GROUP BY p.id, p.productName ORDER BY qty DESC LIMIT 25",
        'ississ', [$tp_id, $from, $to, $tp_id, $from, $to]);

    $returns = call_rows_local($db_conn,
        "SELECT ri.prid pid, COALESCE(SUM(ri.qty),0) qty FROM user_return_stock_items ri
         WHERE ri.to_usertype='territory_partner' AND ri.to_userid=? AND ri.date BETWEEN ? AND ?
         GROUP BY ri.prid",
        'iss', [$tp_id, $from, $to]);
    $returnByPid = [];
    foreach ($returns as $r) { $returnByPid[(int)$r['pid']] = (float)$r['qty']; }

    $rows = [];
    foreach ($sales as $s) {
        $rows[] = [
            'name'    => $s['productName'],
            'qty'     => number_format((float)$s['qty'], 0),
            'value'   => number_format((float)$s['amt'], 2),
            'ret_qty' => number_format($returnByPid[(int)$s['pid']] ?? 0, 0),
        ];
    }
    respond($rows);
}

function call_rows_local($db, $sql, $types, $params) {
    $s = $db->prepare($sql);
    if (!$s) return [];
    $s->bind_param($types, ...$params);
    $s->execute();
    $r = $s->get_result();
    $s->close();
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
?>
