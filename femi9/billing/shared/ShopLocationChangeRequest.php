<?php
// A DM's ms_shop location can only be manually re-captured 2 times for life
// (see edit-ss-action.php) — once that's used up, the DM has to ask their
// Sales BDM to unlock it again. This table is that request queue, shared
// between marketing/ (where a DM files a request) and salesbdm/ (where the
// BDM approves/rejects it). Self-migrating like the rest of this codebase's
// ad-hoc tables.

function ensureShopLocationChangeRequestsTable($db_conn) {
    $db_conn->query("CREATE TABLE IF NOT EXISTS ms_shop_location_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shop_id INT NOT NULL,
        ms_id INT NOT NULL,
        district_name VARCHAR(100) DEFAULT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        accept_reason TEXT NULL,
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        responded_at TIMESTAMP NULL,
        responded_by_bdm_id INT NULL,
        responded_by_name VARCHAR(255) NULL,
        INDEX idx_shop (shop_id),
        INDEX idx_status (status)
    )");
}
