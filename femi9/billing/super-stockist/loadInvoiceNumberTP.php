<?php include("checksession.php");
include("config.php");
header('Content-Type: application/json');

// Self-migrating: mirrors the schema check in tp-invoice-action.php so this
// endpoint works even if it's hit before any invoice has been submitted yet.
$col = $db_conn->query("SHOW COLUMNS FROM tp_invoices LIKE 'created_by_user_type'");
if ($col && $col->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN created_by_user_type VARCHAR(30) NOT NULL DEFAULT '' AFTER created_by");
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN created_by_user_id VARCHAR(30) NOT NULL DEFAULT '' AFTER created_by_user_type");
    $db_conn->query("ALTER TABLE tp_invoices ADD INDEX idx_tpi_creator (created_by_user_type, created_by_user_id, invoice_number)");
    $db_conn->query("UPDATE tp_invoices SET created_by_user_type='super_stockiest', created_by_user_id=SUBSTRING_INDEX(SUBSTRING(invoice_number,6),'/',1) WHERE invoice_number LIKE 'TP/SS%'");
    $db_conn->query("UPDATE tp_invoices SET created_by_user_type='company' WHERE created_by_user_type=''");
    $idx = $db_conn->query("SHOW INDEX FROM tp_invoices WHERE Key_name='uk_tp_inv_number'");
    if ($idx && $idx->num_rows > 0) {
        $db_conn->query("ALTER TABLE tp_invoices DROP INDEX uk_tp_inv_number");
    }
}

$invnumber     = trim($_REQUEST['q'] ?? '');
$ss_account_id = (int)($result_LoGuserDtails['id'] ?? 0);

$duplicate = false;
if ($invnumber !== '' && $ss_account_id > 0) {
    $stmt = $db_conn->prepare("SELECT id FROM tp_invoices WHERE invoice_number=? AND created_by_user_type='super_stockiest' AND created_by_user_id=?");
    $stmt->bind_param("ss", $invnumber, $ss_account_id);
    $stmt->execute();
    $duplicate = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}

echo json_encode(['duplicate' => $duplicate]);
