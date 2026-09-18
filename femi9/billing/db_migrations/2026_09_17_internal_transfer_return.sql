-- One row per "Return Stock" action against an internal_transfer line, so
-- returns can be listed as credit notes and individually deleted (each
-- deletion re-applies the original transfer for that row's qty, undoing
-- just that return, not the whole line). Previously a return only
-- incremented internal_transfer.returned_qty with no per-event record,
-- so there was nothing to list or delete individually.
-- GST fields are a snapshot (pro-rated from the parent line at return
-- time) so the credit note stays accurate even if the parent transfer
-- line is edited later — same convention as internal_transfer's own
-- gst_type/taxable_value snapshot (see 2026_08_06_internal_transfer_gst_type.sql).
-- Applied: 2026-09-17

CREATE TABLE internal_transfer_return (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id   INT NOT NULL,
    tempid        VARCHAR(255) NOT NULL,
    product_id    INT NOT NULL,
    qty           INT NOT NULL,
    send_from     INT NOT NULL,
    send_to       INT NOT NULL,
    price         DECIMAL(12,2) NOT NULL DEFAULT 0,
    gst_type      ENUM('inclusive','exclusive') NOT NULL DEFAULT 'exclusive',
    gst           DECIMAL(5,2) NOT NULL DEFAULT 0,
    taxable_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    gst_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
    total         DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by    VARCHAR(100) NOT NULL DEFAULT '',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_transfer_id (transfer_id),
    KEY idx_tempid (tempid)
);
