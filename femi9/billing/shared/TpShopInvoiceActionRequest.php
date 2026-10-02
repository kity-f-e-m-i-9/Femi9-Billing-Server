<?php
/**
 * Per-invoice Return/Remove permission for a TP's own shop invoices — a TP
 * can no longer Return or Remove freely; instead they raise a "Request to
 * Return" / "Request to Remove" for a SPECIFIC invoice, which routes to
 * whichever Sales BDM covers that TP's district (same reverse-lookup as
 * tp_courier_amount_requests — see tpFindBdmIdsForTp() in
 * TpCourierAmountRequest.php, reused here rather than duplicated). Once
 * approved (by that BDM, or by Company directly), the corresponding button
 * becomes available on that one invoice only — Return and Remove are
 * independent requests, so an invoice can have either, both, or neither
 * enabled. Confirmed with the business owner 2026-10-01.
 *
 * A BDM only sees/can act on these requests if Company has separately
 * marked them "eligible" (sales_bdm_staff.shop_invoice_request_eligible) —
 * see tpEnsureShopInvoiceEligibilityColumn() below.
 */

function tpEnsureShopInvoiceActionRequestTable(mysqli $db): void
{
    $db->query("
        CREATE TABLE IF NOT EXISTS tp_shop_invoice_action_requests (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          territory_partner_id INT UNSIGNED NOT NULL,
          inv_id VARCHAR(100) NOT NULL,
          action_type ENUM('return','remove') NOT NULL,
          status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
          reviewed_by_bdm_id INT UNSIGNED NULL,
          reviewed_by_name VARCHAR(255) NULL,
          reviewed_at TIMESTAMP NULL,
          reason VARCHAR(500) NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uk_tsiar_tp_inv_action (territory_partner_id, inv_id, action_type),
          KEY idx_tsiar_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $col = $db->query("SHOW COLUMNS FROM tp_shop_invoice_action_requests LIKE 'reason'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE tp_shop_invoice_action_requests ADD COLUMN reason VARCHAR(500) NULL AFTER reviewed_at");
    }
}

function tpEnsureShopInvoiceEligibilityColumn(mysqli $db): void
{
    $col = $db->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'shop_invoice_request_eligible'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE sales_bdm_staff ADD COLUMN shop_invoice_request_eligible TINYINT(1) NOT NULL DEFAULT 0 AFTER account_status");
    }
}

function tpIsBdmEligibleForShopInvoiceRequests(mysqli $db, int $bdmId): bool
{
    tpEnsureShopInvoiceEligibilityColumn($db);
    $stmt = $db->prepare("SELECT shop_invoice_request_eligible FROM sales_bdm_staff WHERE id = ?");
    $stmt->bind_param('i', $bdmId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return !empty($row['shop_invoice_request_eligible']);
}

/**
 * 'pending' | 'approved' | 'rejected' | null (no request raised yet, or the
 * invoice/action combo was never requested).
 */
function tpShopInvoiceActionStatus(mysqli $db, int $tpId, string $invId, string $actionType): ?string
{
    tpEnsureShopInvoiceActionRequestTable($db);
    $stmt = $db->prepare("
        SELECT status FROM tp_shop_invoice_action_requests
        WHERE territory_partner_id = ? AND inv_id = ? AND action_type = ?
    ");
    $stmt->bind_param('iss', $tpId, $invId, $actionType);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['status'] ?? null;
}

function tpShopInvoiceActionApproved(mysqli $db, int $tpId, string $invId, string $actionType): bool
{
    return tpShopInvoiceActionStatus($db, $tpId, $invId, $actionType) === 'approved';
}

/**
 * Raises a new request, or — if this TP/invoice/action combo was already
 * requested and since rejected — resets it back to 'pending' for a fresh
 * look (never silently reuses a stale decision). An already-pending or
 * already-approved request is left untouched (no-op), so a TP spamming the
 * button can't create duplicates or clobber an approval.
 */
function tpShopInvoiceActionRequestUpsert(mysqli $db, int $tpId, string $invId, string $actionType): bool
{
    tpEnsureShopInvoiceActionRequestTable($db);
    $stmt = $db->prepare("
        INSERT INTO tp_shop_invoice_action_requests (territory_partner_id, inv_id, action_type, status)
        VALUES (?, ?, ?, 'pending')
        ON DUPLICATE KEY UPDATE
          status = IF(status = 'rejected', 'pending', status),
          reviewed_by_bdm_id = IF(status = 'rejected', NULL, reviewed_by_bdm_id),
          reviewed_by_name   = IF(status = 'rejected', NULL, reviewed_by_name),
          reviewed_at        = IF(status = 'rejected', NULL, reviewed_at),
          reason             = IF(status = 'rejected', NULL, reason),
          created_at         = IF(status = 'rejected', CURRENT_TIMESTAMP, created_at)
    ");
    $stmt->bind_param('iss', $tpId, $invId, $actionType);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// $status != current status is the only guard — this intentionally allows
// re-reviewing an already-decided request (e.g. revoking an 'approved' back
// to 'rejected' once a TP is found to have misused it, or the reverse), not
// just the original pending->decided transition. A click that doesn't
// actually change anything (approving an already-approved row) is a no-op.
function tpShopInvoiceActionRequestReview(mysqli $db, int $requestId, string $status, ?int $bdmId, string $reviewerName, ?string $reason = null): bool
{
    $reason = ($reason !== null && trim($reason) !== '') ? trim($reason) : null;
    $stmt = $db->prepare("
        UPDATE tp_shop_invoice_action_requests
        SET status = ?, reviewed_by_bdm_id = ?, reviewed_by_name = ?, reviewed_at = NOW(), reason = ?
        WHERE id = ? AND status != ?
    ");
    $stmt->bind_param('sissis', $status, $bdmId, $reviewerName, $reason, $requestId, $status);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}
