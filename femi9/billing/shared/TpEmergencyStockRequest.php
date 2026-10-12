<?php
/**
 * TP Emergency Stock Requests — when a TP's Order Assistant chat says a
 * product is needed urgently beyond what their Channel Partner (CP)
 * currently has, instead of silently refusing (or quietly routing around
 * the CP), it logs a request here for Company to manually review. Approving
 * it does NOT auto-create a purchase order — it just tells the TP they're
 * cleared to proceed (they still place the actual order themselves,
 * normally routed to Company instead of the CP once approved).
 */

function ensureTpEmergencyStockRequestsTable(mysqli $db_conn): void {
    $db_conn->query("CREATE TABLE IF NOT EXISTS tp_emergency_stock_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        territory_partner_id INT NOT NULL,
        cp_id INT NOT NULL,
        product_id INT NOT NULL,
        requested_qty INT NOT NULL,
        cp_available_qty INT NOT NULL,
        note VARCHAR(255) NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        reviewed_by VARCHAR(100) NULL,
        reviewed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (territory_partner_id, status),
        INDEX (status, created_at)
    )");
}

function createTpEmergencyStockRequest(
    mysqli $db_conn,
    int $tpId,
    int $cpId,
    int $productId,
    int $requestedQty,
    int $cpAvailableQty,
    ?string $note
): int {
    ensureTpEmergencyStockRequestsTable($db_conn);
    $stmt = $db_conn->prepare(
        "INSERT INTO tp_emergency_stock_requests
            (territory_partner_id, cp_id, product_id, requested_qty, cp_available_qty, note)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('iiiiis', $tpId, $cpId, $productId, $requestedQty, $cpAvailableQty, $note);
    $stmt->execute();
    $id = $db_conn->insert_id;
    $stmt->close();
    return $id;
}

/** @return array<array{id:int,product_id:int,product_name:string,requested_qty:int,cp_available_qty:int,note:?string,status:string,created_at:string}> */
function getTpEmergencyStockRequestsForTp(mysqli $db_conn, int $tpId, int $limit = 50): array {
    ensureTpEmergencyStockRequestsTable($db_conn);
    $stmt = $db_conn->prepare(
        "SELECT r.id, r.product_id, p.productName AS product_name, r.requested_qty,
                r.cp_available_qty, r.note, r.status, r.created_at
         FROM tp_emergency_stock_requests r
         JOIN products p ON p.id = r.product_id
         WHERE r.territory_partner_id = ?
         ORDER BY r.created_at DESC
         LIMIT ?"
    );
    $stmt->bind_param('ii', $tpId, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** @return array<array{id:int,tp_name:string,tp_code:string,cp_name:string,product_name:string,requested_qty:int,cp_available_qty:int,note:?string,status:string,created_at:string}> */
function getTpEmergencyStockRequests(mysqli $db_conn, ?string $status = null, int $limit = 200): array {
    ensureTpEmergencyStockRequestsTable($db_conn);
    $where = $status !== null ? "WHERE r.status = ?" : "";
    $stmt = $db_conn->prepare(
        "SELECT r.id, tp.name AS tp_name, tp.tp_id AS tp_code, cp.name AS cp_name,
                p.productName AS product_name, r.requested_qty, r.cp_available_qty,
                r.note, r.status, r.created_at
         FROM tp_emergency_stock_requests r
         JOIN territory_partners tp ON tp.id = r.territory_partner_id
         JOIN channel_partners cp ON cp.id = r.cp_id
         JOIN products p ON p.id = r.product_id
         $where
         ORDER BY r.created_at DESC
         LIMIT ?"
    );
    if ($status !== null) {
        $stmt->bind_param('si', $status, $limit);
    } else {
        $stmt->bind_param('i', $limit);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function reviewTpEmergencyStockRequest(mysqli $db_conn, int $requestId, string $status, string $reviewerName): bool {
    if (!in_array($status, ['approved', 'rejected'], true)) return false;
    ensureTpEmergencyStockRequestsTable($db_conn);
    $stmt = $db_conn->prepare(
        "UPDATE tp_emergency_stock_requests
         SET status = ?, reviewed_by = ?, reviewed_at = NOW()
         WHERE id = ? AND status = 'pending'"
    );
    $stmt->bind_param('ssi', $status, $reviewerName, $requestId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}
