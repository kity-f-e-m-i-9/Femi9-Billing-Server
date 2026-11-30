<?php
// Lets a Channel Partner (qty only) or Company (qty + price) adjust a DM's
// Get Order's product lines BEFORE it's invoiced (company/ms-order-invoice-
// add.php), with a mandatory reason and a full audit trail. 2026-11-13, user
// request: "avanga pottta product oda qty ah avanga change pannikulam ...
// save kudutha enna reason nala edit panninganu kekkum".
//
// Edits are applied directly onto ms_orders.qty (and the new price_override
// column) in place -- ms-order-invoice-add.php already reads straight from
// ms_orders for its invoice lines, so it automatically picks up whatever was
// last edited/saved here without needing any change on its own end. The
// ms_order_edits table is the audit trail company reviews via the "Changes"
// view -- it never drives the invoice itself, only explains what changed.
function msOrderEditsEnsureSchema($db_conn): void {
    $db_conn->query("CREATE TABLE IF NOT EXISTS ms_order_edits (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id VARCHAR(255) NOT NULL,
        pr_id INT UNSIGNED NOT NULL,
        old_qty INT NOT NULL,
        new_qty INT NOT NULL,
        old_price DECIMAL(10,2) NULL DEFAULT NULL,
        new_price DECIMAL(10,2) NULL DEFAULT NULL,
        reason TEXT NOT NULL,
        edited_by_type ENUM('cp','company') NOT NULL,
        edited_by_name VARCHAR(100) NULL DEFAULT NULL,
        edited_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_moe_order (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $chk = $db_conn->query("SHOW COLUMNS FROM ms_orders LIKE 'price_override'");
    if ($chk && $chk->num_rows === 0) {
        $db_conn->query("ALTER TABLE ms_orders ADD COLUMN price_override DECIMAL(10,2) NULL DEFAULT NULL AFTER qty");
    }
}

// $qtyChanges: [pr_id => newQty, ...] -- only products actually present on
// this order_id are touched; anything else in the array is ignored.
// $priceChanges: same shape, company-only (null for a CP edit). Returns
// ['success' => bool, 'error' => ?string, 'changed' => int].
function msOrderApplyEdit(
    $db_conn,
    string $orderId,
    string $editedByType,
    string $editedByName,
    array $qtyChanges,
    ?array $priceChanges,
    string $reason
): array {
    if (trim($reason) === '') {
        return ['success' => false, 'error' => 'A reason is required.', 'changed' => 0];
    }
    if (!in_array($editedByType, ['cp', 'company'], true)) {
        return ['success' => false, 'error' => 'Invalid editor type.', 'changed' => 0];
    }

    msOrderEditsEnsureSchema($db_conn);

    $stmt = $db_conn->prepare("SELECT id, pr_id, qty, price_override FROM ms_orders WHERE order_id = ?");
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rows)) {
        return ['success' => false, 'error' => 'Order not found.', 'changed' => 0];
    }

    $changed = 0;
    $db_conn->begin_transaction();
    try {
        foreach ($rows as $r) {
            $prId = (int)$r['pr_id'];
            $oldQty = (int)$r['qty'];
            $newQty = array_key_exists($prId, $qtyChanges) ? (int)$qtyChanges[$prId] : $oldQty;
            if ($newQty < 0) { $newQty = 0; }

            $oldPrice = $r['price_override'] !== null ? (float)$r['price_override'] : null;
            $newPrice = $oldPrice;
            if ($priceChanges !== null && array_key_exists($prId, $priceChanges) && $priceChanges[$prId] !== null) {
                $newPrice = (float)$priceChanges[$prId];
            }

            $qtyDiffers = $newQty !== $oldQty;
            $priceDiffers = $editedByType === 'company' && $newPrice !== $oldPrice;
            if (!$qtyDiffers && !$priceDiffers) { continue; }

            $stmtUpd = $db_conn->prepare("UPDATE ms_orders SET qty = ?, price_override = ? WHERE id = ?");
            $stmtUpd->bind_param('idi', $newQty, $newPrice, $r['id']);
            $stmtUpd->execute();
            $stmtUpd->close();

            $stmtLog = $db_conn->prepare(
                "INSERT INTO ms_order_edits (order_id, pr_id, old_qty, new_qty, old_price, new_price, reason, edited_by_type, edited_by_name)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmtLog->bind_param('siiiddsss', $orderId, $prId, $oldQty, $newQty, $oldPrice, $newPrice, $reason, $editedByType, $editedByName);
            $stmtLog->execute();
            $stmtLog->close();

            $changed++;
        }
        $db_conn->commit();
    } catch (\Throwable $e) {
        $db_conn->rollback();
        return ['success' => false, 'error' => 'Could not save the edit. Please try again.', 'changed' => 0];
    }

    if ($changed === 0) {
        return ['success' => false, 'error' => 'No quantities were actually changed.', 'changed' => 0];
    }

    return ['success' => true, 'error' => null, 'changed' => $changed];
}

function msOrderFetchEditLog($db_conn, string $orderId): array {
    msOrderEditsEnsureSchema($db_conn);
    $stmt = $db_conn->prepare(
        "SELECT moe.*, p.productName
         FROM ms_order_edits moe
         LEFT JOIN products p ON p.id = moe.pr_id
         WHERE moe.order_id = ?
         ORDER BY moe.edited_at ASC"
    );
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Whether $orderId already has a real invoice (ms-order-invoice-add.php's
// own "already invoiced" check, duplicated here as a read-only guard) --
// edits must be blocked once the order is invoiced, since the invoice lines
// are already fixed by then.
function msOrderIsInvoiced($db_conn, string $orderId): bool {
    $stmt = $db_conn->prepare("SELECT 1 FROM user_invoice WHERE source_ms_order_id = ? LIMIT 1");
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$found;
}
