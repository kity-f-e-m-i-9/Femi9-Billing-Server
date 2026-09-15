<?php
/**
 * CpInvoiceSchema — self-migrating creation of cp_invoices / cp_invoice_items
 * and the cp_invoice_id link column on channel_partner_purchase_orders.
 * Same pattern as cpEnsurePurchaseOrderTables() in CpPurchaseOrderBalance.php.
 */

function cpEnsureInvoiceTables(mysqli $db): void
{
    $invTable = $db->query("SHOW TABLES LIKE 'cp_invoices'");
    if ($invTable && $invTable->num_rows === 0) {
        $db->query("
            CREATE TABLE cp_invoices (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                invoice_number VARCHAR(30) NOT NULL,
                channel_partner_id INT UNSIGNED NOT NULL,
                source_godown_id INT UNSIGNED NULL,
                product_type ENUM('napkin','diaper') NOT NULL DEFAULT 'napkin',
                invoice_date DATE NOT NULL,
                total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                use_default_delivery_address TINYINT(1) NOT NULL DEFAULT 1,
                custom_delivery_line1 VARCHAR(255) NULL,
                custom_delivery_line2 VARCHAR(255) NULL,
                custom_delivery_city VARCHAR(100) NULL,
                custom_delivery_district VARCHAR(100) NULL,
                custom_delivery_state VARCHAR(100) NULL,
                custom_delivery_country VARCHAR(100) NULL,
                custom_delivery_pincode VARCHAR(20) NULL,
                transfer_id INT UNSIGNED NULL,
                created_by VARCHAR(100) NULL,
                created_by_user_type VARCHAR(20) NOT NULL DEFAULT 'company',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cpinv_number (invoice_number),
                KEY idx_cpinv_cp (channel_partner_id),
                KEY idx_cpinv_transfer (transfer_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    $itemsTable = $db->query("SHOW TABLES LIKE 'cp_invoice_items'");
    if ($itemsTable && $itemsTable->num_rows === 0) {
        $db->query("
            CREATE TABLE cp_invoice_items (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                cp_invoice_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NOT NULL,
                quantity INT UNSIGNED NOT NULL,
                rate DECIMAL(10,2) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                KEY idx_cpinvi_invoice (cp_invoice_id),
                CONSTRAINT fk_cpinvi_invoice FOREIGN KEY (cp_invoice_id) REFERENCES cp_invoices(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    $col = $db->query("SHOW COLUMNS FROM channel_partner_purchase_orders LIKE 'cp_invoice_id'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE channel_partner_purchase_orders ADD COLUMN cp_invoice_id INT UNSIGNED NULL AFTER transfer_id");
        $db->query("ALTER TABLE channel_partner_purchase_orders ADD KEY idx_cppo_invoice (cp_invoice_id)");
    }
}
