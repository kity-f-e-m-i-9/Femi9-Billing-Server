<?php
// Shared backend for the CP/TP Agreement e-sign feature — self-migrating
// tables, one row per Channel Partner / Territory Partner, holding both the
// company-set "Schedule-1" commercial particulars (Security Deposit /
// Monthly Purchase Commitment / Division / Firka etc., entered once by
// finance/admin on company/manage-agreements.php) and the partner's own
// filled-in execution details (date, place, witnesses, signatures).
// A row is locked (is_locked=1) the moment the partner submits their
// signature — from then on their own agreement.php renders it read-only.
// Only company/manage-agreements-action.php can unlock it again.

function ensure_agreement_tables(mysqli $db_conn): void
{
    $db_conn->query("
        CREATE TABLE IF NOT EXISTS agreement_settings (
            id INT NOT NULL PRIMARY KEY,
            company_address TEXT NULL,
            authorized_signatory_name VARCHAR(150) NULL,
            authorized_signatory_designation VARCHAR(150) NULL,
            updated_by VARCHAR(100) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $db_conn->query("INSERT IGNORE INTO agreement_settings (id) VALUES (1)");

    $db_conn->query("
        CREATE TABLE IF NOT EXISTS channel_partner_agreements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            channel_partner_id INT UNSIGNED NOT NULL,
            security_deposit DECIMAL(12,2) NULL,
            approved_divisions TEXT NULL,
            division_codes TEXT NULL,
            approved_warehouse_address TEXT NULL,
            stock_holding_capacity VARCHAR(255) NULL,
            commercial_category VARCHAR(255) NULL,
            effective_date DATE NULL,
            other_particulars TEXT NULL,
            schedule_set_by VARCHAR(100) NULL,
            schedule_set_at TIMESTAMP NULL,
            agreement_date DATE NULL,
            agreement_place VARCHAR(255) NULL,
            deposit_mode VARCHAR(100) NULL,
            deposit_txn_ref VARCHAR(150) NULL,
            cp_designation VARCHAR(150) NULL,
            cp_signature LONGTEXT NULL,
            witness1_name VARCHAR(150) NULL,
            witness1_address TEXT NULL,
            witness1_signature LONGTEXT NULL,
            witness2_name VARCHAR(150) NULL,
            witness2_address TEXT NULL,
            witness2_signature LONGTEXT NULL,
            signed_at TIMESTAMP NULL,
            is_locked TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cp_agreement (channel_partner_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $db_conn->query("
        CREATE TABLE IF NOT EXISTS territory_partner_agreements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            territory_partner_id INT UNSIGNED NOT NULL,
            monthly_purchase_commitment DECIMAL(12,2) NULL,
            territory_firka TEXT NULL,
            territory_code TEXT NULL,
            taluk_block VARCHAR(255) NULL,
            effective_date DATE NULL,
            other_particulars TEXT NULL,
            schedule_set_by VARCHAR(100) NULL,
            schedule_set_at TIMESTAMP NULL,
            agreement_date DATE NULL,
            agreement_place VARCHAR(255) NULL,
            tp_designation VARCHAR(150) NULL,
            tp_signature LONGTEXT NULL,
            witness1_name VARCHAR(150) NULL,
            witness1_address TEXT NULL,
            witness1_signature LONGTEXT NULL,
            witness2_name VARCHAR(150) NULL,
            witness2_address TEXT NULL,
            witness2_signature LONGTEXT NULL,
            signed_at TIMESTAMP NULL,
            is_locked TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tp_agreement (territory_partner_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // Per-partner override of the agreement's legal wording (clauses), set
    // via manage-agreements.php's optional "custom wording for this partner
    // only" editor. NULL/empty means "use the shared template" (see
    // get_effective_agreement_body()). Added via ALTER on existing installs
    // since the two tables above predate this column.
    $cpCol = $db_conn->query("SHOW COLUMNS FROM channel_partner_agreements LIKE 'custom_body_html'");
    if ($cpCol && $cpCol->num_rows === 0) {
        $db_conn->query("ALTER TABLE channel_partner_agreements ADD COLUMN custom_body_html LONGTEXT NULL AFTER witness2_signature");
    }
    $tpCol = $db_conn->query("SHOW COLUMNS FROM territory_partner_agreements LIKE 'custom_body_html'");
    if ($tpCol && $tpCol->num_rows === 0) {
        $db_conn->query("ALTER TABLE territory_partner_agreements ADD COLUMN custom_body_html LONGTEXT NULL AFTER witness2_signature");
    }

    // Shared master template -- one row per agreement type ('channel_partner'
    // / 'territory_partner'), edited via manage-agreements.php's "Edit
    // Agreement Wording" editor. No row yet (fresh install, or nobody has
    // edited wording so far) means "use the hardcoded default" -- see
    // get_default_agreement_body() / get_effective_agreement_body().
    $db_conn->query("
        CREATE TABLE IF NOT EXISTS agreement_body_templates (
            type VARCHAR(30) NOT NULL PRIMARY KEY,
            body_html LONGTEXT NOT NULL,
            updated_by VARCHAR(100) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

/** The original hardcoded clause text for a given agreement type, moved out
 * of territory-partner/agreement.php and channel-partner/agreement.php
 * verbatim into shared/agreement-templates/{cp,tp}-default.html -- this is
 * what renders until someone actually edits the wording via
 * manage-agreements.php (and what a fresh "Edit Agreement Wording" editor
 * starts from). */
function get_default_agreement_body(string $type): string
{
    $file = __DIR__ . '/agreement-templates/' . ($type === 'channel_partner' ? 'cp' : 'tp') . '-default.html';
    return is_file($file) ? file_get_contents($file) : '';
}

function get_agreement_body_template(mysqli $db_conn, string $type): ?string
{
    ensure_agreement_tables($db_conn);
    $stmt = $db_conn->prepare("SELECT body_html FROM agreement_body_templates WHERE type = ?");
    $stmt->bind_param('s', $type);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['body_html'] ?? null;
}

function save_agreement_body_template(mysqli $db_conn, string $type, string $html, string $updatedBy): void
{
    ensure_agreement_tables($db_conn);
    $stmt = $db_conn->prepare(
        "INSERT INTO agreement_body_templates (type, body_html, updated_by) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE body_html = VALUES(body_html), updated_by = VALUES(updated_by)"
    );
    $stmt->bind_param('sss', $type, $html, $updatedBy);
    $stmt->execute();
    $stmt->close();
}

/** What actually renders for a given partner's agreement: their own
 * per-partner override if set, else the shared master template if anyone
 * has edited it, else the original hardcoded default. $type is
 * 'channel_partner' or 'territory_partner'. */
function get_effective_agreement_body(mysqli $db_conn, array $agreement, string $type): string
{
    if (!empty($agreement['custom_body_html'])) {
        return $agreement['custom_body_html'];
    }
    $template = get_agreement_body_template($db_conn, $type);
    return $template !== null ? $template : get_default_agreement_body($type);
}

function get_agreement_settings(mysqli $db_conn): array
{
    ensure_agreement_tables($db_conn);
    $res = $db_conn->query("SELECT * FROM agreement_settings WHERE id = 1");
    return $res->fetch_assoc() ?: [];
}

function save_agreement_settings(mysqli $db_conn, string $companyAddress, string $signatoryName, string $signatoryDesignation, string $updatedBy): void
{
    ensure_agreement_tables($db_conn);
    $stmt = $db_conn->prepare(
        "UPDATE agreement_settings SET company_address = ?, authorized_signatory_name = ?, authorized_signatory_designation = ?, updated_by = ? WHERE id = 1"
    );
    $stmt->bind_param('ssss', $companyAddress, $signatoryName, $signatoryDesignation, $updatedBy);
    $stmt->execute();
    $stmt->close();
}

/** Row is created on first access (all Schedule-1 fields NULL) so the admin
 * list page always has a row per partner to edit, and the partner's own
 * agreement.php always has a row to read. */
function get_or_create_cp_agreement(mysqli $db_conn, int $cpId): array
{
    ensure_agreement_tables($db_conn);
    $stmt = $db_conn->prepare("SELECT * FROM channel_partner_agreements WHERE channel_partner_id = ?");
    $stmt->bind_param('i', $cpId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return $row;

    $ins = $db_conn->prepare("INSERT IGNORE INTO channel_partner_agreements (channel_partner_id) VALUES (?)");
    $ins->bind_param('i', $cpId);
    $ins->execute();
    $ins->close();

    $stmt = $db_conn->prepare("SELECT * FROM channel_partner_agreements WHERE channel_partner_id = ?");
    $stmt->bind_param('i', $cpId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: [];
}

/** True once a partner has signed at least once (signed_at set) but the
 * agreement is currently unlocked again -- i.e. company changed the
 * Schedule-1 particulars after that signature, so the signed copy on file
 * no longer matches what's shown. Drives the "please review & sign again"
 * banner on the partner's own dashboard. */
function agreement_needs_resign(array $agreement): bool
{
    return !empty($agreement['signed_at']) && (int) ($agreement['is_locked'] ?? 0) === 0;
}

function get_or_create_tp_agreement(mysqli $db_conn, int $tpId): array
{
    ensure_agreement_tables($db_conn);
    $stmt = $db_conn->prepare("SELECT * FROM territory_partner_agreements WHERE territory_partner_id = ?");
    $stmt->bind_param('i', $tpId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return $row;

    $ins = $db_conn->prepare("INSERT IGNORE INTO territory_partner_agreements (territory_partner_id) VALUES (?)");
    $ins->bind_param('i', $tpId);
    $ins->execute();
    $ins->close();

    $stmt = $db_conn->prepare("SELECT * FROM territory_partner_agreements WHERE territory_partner_id = ?");
    $stmt->bind_param('i', $tpId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: [];
}
