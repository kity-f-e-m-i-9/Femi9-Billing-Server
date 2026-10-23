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

    // Company's own signature image for the "FOR FEMI9 LLP" execution block
    // on every CP/TP agreement, set once via manage-agreements.php. Added
    // via ALTER on existing installs since the table above predates this
    // column (same convention as the other ALTERs further down this file).
    $sigCol = $db_conn->query("SHOW COLUMNS FROM agreement_settings LIKE 'authorized_signatory_signature'");
    if ($sigCol && $sigCol->num_rows === 0) {
        $db_conn->query("ALTER TABLE agreement_settings ADD COLUMN authorized_signatory_signature VARCHAR(255) NULL AFTER authorized_signatory_designation");
    }

    // "Work All TPs" / "Work All CPs" -- off by default. Until Company flips
    // this on, a TP/CP can log in and use every page as usual (no agreement
    // involved at all), same as before this gate existed. Once Company
    // enables it, territory-partner/checksession.php and
    // channel-partner/checksession.php start forcing any not-yet-signed
    // partner straight to agreement.php on every page load until they sign.
    $enforceCol = $db_conn->query("SHOW COLUMNS FROM agreement_settings LIKE 'tp_sign_enforced'");
    if ($enforceCol && $enforceCol->num_rows === 0) {
        $db_conn->query("ALTER TABLE agreement_settings ADD COLUMN tp_sign_enforced TINYINT(1) NOT NULL DEFAULT 0 AFTER authorized_signatory_signature");
        $db_conn->query("ALTER TABLE agreement_settings ADD COLUMN cp_sign_enforced TINYINT(1) NOT NULL DEFAULT 0 AFTER tp_sign_enforced");
    }

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

    // PAN card upload, collected alongside the partner's own signature —
    // stores just the filename (same convention as advance_payment_screenshots
    // etc.), resolved against channel-partner/kyc_documents/ or
    // territory-partner/kyc_documents/ respectively.
    $cpPanCol = $db_conn->query("SHOW COLUMNS FROM channel_partner_agreements LIKE 'pan_card_path'");
    if ($cpPanCol && $cpPanCol->num_rows === 0) {
        $db_conn->query("ALTER TABLE channel_partner_agreements ADD COLUMN pan_card_path VARCHAR(255) NULL AFTER cp_signature");
    }
    $tpPanCol = $db_conn->query("SHOW COLUMNS FROM territory_partner_agreements LIKE 'pan_card_path'");
    if ($tpPanCol && $tpPanCol->num_rows === 0) {
        $db_conn->query("ALTER TABLE territory_partner_agreements ADD COLUMN pan_card_path VARCHAR(255) NULL AFTER tp_signature");
    }

    // Snapshot of the partner's own profile fields (name/business name/
    // address/mobile/email) as they stood at the moment of their last
    // signature — compared against the live channel_partners/
    // territory_partners row on render so a field the Company changed
    // afterwards (e.g. via Edit Channel/Territory Partner) can be
    // highlighted, the same way a Schedule-1 change already is.
    $cpSnapCol = $db_conn->query("SHOW COLUMNS FROM channel_partner_agreements LIKE 'snap_name'");
    if ($cpSnapCol && $cpSnapCol->num_rows === 0) {
        $db_conn->query("ALTER TABLE channel_partner_agreements ADD COLUMN snap_name VARCHAR(150) NULL AFTER pan_card_path");
        $db_conn->query("ALTER TABLE channel_partner_agreements ADD COLUMN snap_company_name VARCHAR(255) NULL AFTER snap_name");
        $db_conn->query("ALTER TABLE channel_partner_agreements ADD COLUMN snap_address TEXT NULL AFTER snap_company_name");
        $db_conn->query("ALTER TABLE channel_partner_agreements ADD COLUMN snap_mobile VARCHAR(20) NULL AFTER snap_address");
        $db_conn->query("ALTER TABLE channel_partner_agreements ADD COLUMN snap_email VARCHAR(150) NULL AFTER snap_mobile");
    }
    $tpSnapCol = $db_conn->query("SHOW COLUMNS FROM territory_partner_agreements LIKE 'snap_name'");
    if ($tpSnapCol && $tpSnapCol->num_rows === 0) {
        $db_conn->query("ALTER TABLE territory_partner_agreements ADD COLUMN snap_name VARCHAR(150) NULL AFTER pan_card_path");
        $db_conn->query("ALTER TABLE territory_partner_agreements ADD COLUMN snap_company_name VARCHAR(255) NULL AFTER snap_name");
        $db_conn->query("ALTER TABLE territory_partner_agreements ADD COLUMN snap_address TEXT NULL AFTER snap_company_name");
        $db_conn->query("ALTER TABLE territory_partner_agreements ADD COLUMN snap_mobile VARCHAR(20) NULL AFTER snap_address");
        $db_conn->query("ALTER TABLE territory_partner_agreements ADD COLUMN snap_email VARCHAR(150) NULL AFTER snap_mobile");
    }

    // Snapshot of the full rendered clause wording (shared template or
    // per-partner override, whichever was effective) at the moment of the
    // partner's last signature — compared against the current effective
    // wording on render so individual clauses the Company has since edited
    // (e.g. via manage-agreements.php's "Edit Agreement Wording") can be
    // highlighted paragraph-by-paragraph. See diff_highlight_agreement_body().
    $cpBodySnapCol = $db_conn->query("SHOW COLUMNS FROM channel_partner_agreements LIKE 'snap_body_html'");
    if ($cpBodySnapCol && $cpBodySnapCol->num_rows === 0) {
        $db_conn->query("ALTER TABLE channel_partner_agreements ADD COLUMN snap_body_html LONGTEXT NULL AFTER snap_email");
    }
    $tpBodySnapCol = $db_conn->query("SHOW COLUMNS FROM territory_partner_agreements LIKE 'snap_body_html'");
    if ($tpBodySnapCol && $tpBodySnapCol->num_rows === 0) {
        $db_conn->query("ALTER TABLE territory_partner_agreements ADD COLUMN snap_body_html LONGTEXT NULL AFTER snap_email");
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

/** Splits a clause-wording HTML blob into its top-level block elements
 * (each &lt;h2&gt;, &lt;p&gt;, &lt;ul&gt; etc. the template is a flat sequence of) —
 * the unit diff_highlight_agreement_body() compares and highlights at. */
function split_agreement_body_blocks(string $html): array
{
    if (trim($html) === '') return [];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');
    libxml_clear_errors();
    $root = $dom->getElementsByTagName('div')->item(0);
    $blocks = [];
    if ($root) {
        foreach ($root->childNodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $blocks[] = $dom->saveHTML($node);
            } elseif ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) !== '') {
                $blocks[] = $dom->saveHTML($node);
            }
        }
    }
    return $blocks;
}

/** Standard LCS-backtrack diff over two arrays of comparable strings (block
 * HTML, or word tokens). Returns ['new' => bool[] parallel to $new, 'old' =>
 * bool[] parallel to $old] — true wherever that element took part in the
 * match (i.e. is unchanged / shared between both sides). */
function diff_match_masks(array $old, array $new): array
{
    $m = count($old);
    $n = count($new);
    $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
    for ($i = $m - 1; $i >= 0; $i--) {
        for ($j = $n - 1; $j >= 0; $j--) {
            $dp[$i][$j] = ($old[$i] === $new[$j])
                ? $dp[$i + 1][$j + 1] + 1
                : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
        }
    }
    $unchangedNew = array_fill(0, $n, false);
    $unchangedOld = array_fill(0, $m, false);
    $i = 0; $j = 0;
    while ($i < $m && $j < $n) {
        if ($old[$i] === $new[$j]) {
            $unchangedNew[$j] = true;
            $unchangedOld[$i] = true;
            $i++; $j++;
        } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
            $i++;
        } else {
            $j++;
        }
    }
    return ['new' => $unchangedNew, 'old' => $unchangedOld];
}

/** Tokenizes an HTML fragment into tags (`<...>`), whitespace runs, and
 * non-whitespace text runs ("words", which may carry punctuation/entities)
 * — word-level diffing compares only the word tokens, while tags and
 * whitespace pass through untouched so inner markup (bold/italic etc.)
 * survives intact. */
function tokenize_html_words(string $html): array
{
    $tokens = preg_split('/(<[^>]+>|\s+)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    return $tokens ?: [];
}

function agr_is_tag_token(string $t): bool { return isset($t[0]) && $t[0] === '<'; }
function agr_is_space_token(string $t): bool { return trim($t) === ''; }

/** Highlights only the changed words within one paired old/new block (e.g.
 * one &lt;p&gt;...&lt;/p&gt;), instead of the whole block — so editing a
 * single word in a long clause only marks that word, not the entire
 * paragraph. Falls back to a whole-block highlight if $newBlockHtml isn't a
 * single clean `<tag>...</tag>` element (so its inner HTML can't be safely
 * isolated for tokenizing). */
function diff_highlight_block_words(string $oldBlockHtml, string $newBlockHtml): string
{
    if (!preg_match('/^<(\w+)([^>]*)>(.*)<\/\1>$/s', $newBlockHtml, $mNew)) {
        return '<div class="agr-wording-changed">' . $newBlockHtml . '</div>';
    }
    [, $tag, $attrs, $newInner] = $mNew;
    $oldInner = $oldBlockHtml;
    if (preg_match('/^<(\w+)([^>]*)>(.*)<\/\1>$/s', $oldBlockHtml, $mOld)) {
        $oldInner = $mOld[3];
    }

    $newTokens = tokenize_html_words($newInner);
    $oldTokens = tokenize_html_words($oldInner);

    $newWords = [];
    foreach ($newTokens as $t) {
        if (!agr_is_tag_token($t) && !agr_is_space_token($t)) $newWords[] = $t;
    }
    if (empty($newWords)) {
        return '<' . $tag . $attrs . '>' . $newInner . '</' . $tag . '>';
    }
    $oldWords = [];
    foreach ($oldTokens as $t) {
        if (!agr_is_tag_token($t) && !agr_is_space_token($t)) $oldWords[] = $t;
    }

    $unchangedNewWords = diff_match_masks($oldWords, $newWords)['new'];

    $out = '';
    $wordPos = 0;
    foreach ($newTokens as $t) {
        if (agr_is_tag_token($t) || agr_is_space_token($t)) {
            $out .= $t;
            continue;
        }
        $isUnchanged = $unchangedNewWords[$wordPos] ?? true;
        $out .= $isUnchanged ? $t : ('<span class="agr-word-changed">' . $t . '</span>');
        $wordPos++;
    }

    return '<' . $tag . $attrs . '>' . $out . '</' . $tag . '>';
}

/** Re-renders $newBody highlighting whichever wording differs from $oldBody
 * (as it stood at the partner's last signature). Changed paragraphs/headings
 * are matched back to their old counterpart (by position, among just the
 * other blocks that also changed — stable against the common case of a
 * handful of in-place edits) and diffed at word granularity, so a single
 * edited word is marked on its own instead of flooding the whole paragraph
 * yellow; a genuinely new block with no old counterpart still gets the
 * whole-block highlight. Falls back to returning $newBody untouched if
 * there's no prior snapshot to diff against, or if parsing fails for any
 * reason (never breaks the page over this). */
function diff_highlight_agreement_body(?string $oldBody, string $newBody): string
{
    if ($oldBody === null || trim($oldBody) === '' || $oldBody === $newBody) {
        return $newBody;
    }
    try {
        $oldBlocks = split_agreement_body_blocks($oldBody);
        $newBlocks = split_agreement_body_blocks($newBody);
        if (empty($oldBlocks) || empty($newBlocks)) {
            return $newBody;
        }
        $masks = diff_match_masks($oldBlocks, $newBlocks);
        $unchangedNew = $masks['new'];
        $unchangedOld = $masks['old'];

        $changedOldQueue = [];
        foreach ($oldBlocks as $idx => $block) {
            if (!$unchangedOld[$idx]) $changedOldQueue[] = $block;
        }

        $out = '';
        $oldPtr = 0;
        foreach ($newBlocks as $idx => $block) {
            if ($unchangedNew[$idx]) {
                $out .= $block;
                continue;
            }
            $pairedOld = $changedOldQueue[$oldPtr] ?? null;
            $oldPtr++;
            $out .= $pairedOld !== null
                ? diff_highlight_block_words($pairedOld, $block)
                : ('<div class="agr-wording-changed">' . $block . '</div>');
        }
        return $out;
    } catch (\Throwable $e) {
        return $newBody;
    }
}

function get_agreement_settings(mysqli $db_conn): array
{
    ensure_agreement_tables($db_conn);
    $res = $db_conn->query("SELECT * FROM agreement_settings WHERE id = 1");
    return $res->fetch_assoc() ?: [];
}

/** Toggles the "Work All TPs" / "Work All CPs" sign-enforcement switch.
 * $type is 'tp' or 'cp'. See the column comment in ensure_agreement_tables()
 * for what this actually gates. */
function set_agreement_sign_enforced(mysqli $db_conn, string $type, bool $enforced): void
{
    ensure_agreement_tables($db_conn);
    $column = $type === 'cp' ? 'cp_sign_enforced' : 'tp_sign_enforced';
    $db_conn->query("UPDATE agreement_settings SET {$column} = " . ($enforced ? 1 : 0) . " WHERE id = 1");
}

/** $signatoryImagePath is the uploaded signature filename, or null to leave
 * whatever's already saved untouched -- manage-agreements.php only sends a
 * new one when the admin actually chose a replacement file, so re-saving
 * the text fields alone (the common case) doesn't wipe out an existing
 * signature. */
function save_agreement_settings(mysqli $db_conn, string $companyAddress, string $signatoryName, string $signatoryDesignation, string $updatedBy, ?string $signatoryImagePath = null): void
{
    ensure_agreement_tables($db_conn);
    if ($signatoryImagePath !== null) {
        $stmt = $db_conn->prepare(
            "UPDATE agreement_settings SET company_address = ?, authorized_signatory_name = ?, authorized_signatory_designation = ?, authorized_signatory_signature = ?, updated_by = ? WHERE id = 1"
        );
        $stmt->bind_param('sssss', $companyAddress, $signatoryName, $signatoryDesignation, $signatoryImagePath, $updatedBy);
    } else {
        $stmt = $db_conn->prepare(
            "UPDATE agreement_settings SET company_address = ?, authorized_signatory_name = ?, authorized_signatory_designation = ?, updated_by = ? WHERE id = 1"
        );
        $stmt->bind_param('ssss', $companyAddress, $signatoryName, $signatoryDesignation, $updatedBy);
    }
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

/**
 * Which of a partner's own profile fields (name/business name/address/
 * mobile/email) differ from the snapshot taken at their last signature.
 * Returns a ['name'=>bool, 'company_name'=>bool, 'address'=>bool,
 * 'mobile'=>bool, 'email'=>bool] map — every value false until the partner
 * has signed at least once (nothing to compare against yet). $live is the
 * current channel_partners/territory_partners row, $agreement the
 * channel_partner_agreements/territory_partner_agreements row (holding the
 * snap_* columns).
 */
/** The "Address:" line shown on a CP/TP agreement, and what the
 * name/mobile/email/etc change-detector above compares against — built
 * from the Billing Address fields (branch_line1/line2/city/district/state/
 * country/pincode) that company/add-(territory|channel)-partner.php and
 * their edit- counterparts actually have a form for, NOT the plain
 * `address` TEXT column also present on both tables. That column has no
 * form anywhere that writes to it (confirmed empty for all 285 TPs in this
 * installation 2026-10-23) -- it's dead, Billing Address is what's
 * actually filled in. Works for both channel_partners and
 * territory_partners rows since the branch_* column names are identical
 * on both tables. */
function partner_billing_address_line(array $p): string
{
    $parts = array_filter([
        trim((string)($p['branch_line1'] ?? '')),
        trim((string)($p['branch_line2'] ?? '')),
        trim((string)($p['branch_city'] ?? '')),
        trim((string)($p['branch_district'] ?? '')),
        trim((string)($p['branch_state'] ?? '')),
        trim((string)($p['branch_country'] ?? '')),
        !empty($p['branch_pincode']) ? 'Pincode: ' . trim((string)$p['branch_pincode']) : '',
    ], fn($v) => $v !== '');
    return implode(', ', $parts);
}

function get_profile_change_flags(array $live, array $agreement): array
{
    $hasSnapshot = !empty($agreement['signed_at']) && $agreement['snap_name'] !== null;
    if (!$hasSnapshot) {
        return ['name' => false, 'company_name' => false, 'address' => false, 'mobile' => false, 'email' => false];
    }
    $cmp = fn($a, $b) => trim((string)($a ?? '')) !== trim((string)($b ?? ''));
    return [
        'name'         => $cmp($agreement['snap_name'], $live['name'] ?? ''),
        'company_name' => $cmp($agreement['snap_company_name'], $live['company_name'] ?? ''),
        'address'      => $cmp($agreement['snap_address'], partner_billing_address_line($live)),
        'mobile'       => $cmp($agreement['snap_mobile'], $live['mobile'] ?? ''),
        'email'        => $cmp($agreement['snap_email'], $live['email'] ?? ''),
    ];
}

/** Femi9 Nayan LLP company letterhead header -- rendered at the top of
 * every CP/TP agreement document (the partner's own agreement.php and the
 * company's read-only view-cp-agreement.php / view-tp-agreement.php) so the
 * on-screen/printed agreement looks like it was issued on company
 * letterhead. This is the ACTUAL letterhead graphic (cropped straight out
 * of the company's own "Letterhead Femi9.pdf") as a PNG, not a CSS
 * recreation -- an earlier version tried to rebuild the script wordmark/
 * diagonal stripe in CSS and it never matched exactly, so it was replaced
 * with these two image crops (agreement-letterhead-header.png /
 * -footer.png) instead. $assetPrefix is the relative path to femi9/assets/
 * from the including file; all four callers sit at the same billing/<x>/
 * depth, so the default is correct for all of them. */
function render_agreement_letterhead_header(string $assetPrefix = '../../assets/'): void
{
    $src = htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') . 'images/agreement-letterhead-header.png';
    ?>
    <div class="agr-letterhead">
        <img src="<?php echo $src; ?>" alt="FEMI NAYAN LLP" class="agr-letterhead-img">
    </div>
    <?php
}

/** Matching footer band for the letterhead above -- see
 * render_agreement_letterhead_header(). */
function render_agreement_letterhead_footer(string $assetPrefix = '../../assets/'): void
{
    $src = htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') . 'images/agreement-letterhead-footer.png';
    ?>
    <div class="agr-letterhead-footer">
        <img src="<?php echo $src; ?>" alt="support@femi9.in | www.femi9.in | +91 90429 16499" class="agr-letterhead-img">
    </div>
    <?php
}

/** Strips the blank spacer <p> that the rich-text editor behind
 * agreement_body_templates.body_html (territory_partner's customized
 * clauses) consistently inserts right after every <h2> and before the
 * clause's real paragraph. That spacer is invisible either way (it's pure
 * whitespace -- removing it changes nothing on screen), but its presence
 * breaks direct-sibling CSS adjacency: ".agr-doc h2 + p" was meant to glue
 * a heading to its paragraph (page-break-before:avoid) and ".agr-doc p"
 * carries orphans/widows so a heading is never left with zero lines of its
 * paragraph below it -- neither rule can fire when the element actually
 * adjacent to the heading is this blank spacer instead of the real
 * paragraph. Also handles the same situation when the heading is wrapped in
 * a single-child <div class="agr-wording-changed"> (diff_highlight_agreement_body()'s
 * output when the company edited just that clause's heading).
 *
 * An earlier version of this function instead wrapped the heading and its
 * whole paragraph in a page-break-inside:avoid container, forcing the
 * entire pair to the next page together the moment either didn't fit --
 * which did stop the heading from ever being orphaned, but at the cost of
 * leaving large blank gaps at the bottom of the previous page every time a
 * clause landed near a page boundary. The native CSS orphans/widows
 * approach here is what the stylesheet already had in place (just silently
 * defeated by the spacer); fixing adjacency lets dompdf do the one thing
 * it's actually designed for: let a paragraph start on the current page
 * and keep going onto the next, as long as at least `orphans` lines of it
 * stay up top with the heading. */
function wrap_headings_with_next_block_for_pdf(string $html): string
{
    if (trim($html) === '') return $html;
    // Never let a DOM quirk take the whole PDF export down with it --
    // worst case this just falls back to the untouched $html (the spacer
    // stays in place, same orphan risk as before this function existed).
    try {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>');
        libxml_clear_errors();
        // getElementById() needs a DTD declaring the attribute as an ID
        // type to work reliably after loadHTML() -- plain HTML parsing
        // doesn't guarantee that, and it's known to silently return null
        // on some PHP/libxml builds while working fine on others.
        // Matching split_agreement_body_blocks()'s own approach above
        // (which hit this same pitfall first) avoids it by just taking
        // the first <div>.
        $root = $dom->getElementsByTagName('div')->item(0);
        if (!$root) return $html;

        // Descend through any wrapper div(s) that hold nothing but a single
        // element child -- e.g. the caller's $__agrDocHtml here is actually
        // "<div class=\"agr-doc\">...every clause...</div>" (the on-screen
        // page's own ob_start()/ob_get_clean() capture includes that outer
        // div), so the real top-level clause siblings this function needs
        // to see start one level deeper than $root. Without this, every
        // <h2> search below silently finds nothing and the whole function
        // is a no-op. $descended tracks whether $root is now that real
        // .agr-doc div (must be serialized back WITH its own tag below, so
        // every ".agr-doc ..." rule in the caller's stylesheet still has
        // something to match against) rather than the throwaway synthetic
        // "#__root" wrapper this function made up on line one.
        $descended = false;
        while (true) {
            $elementChildren = [];
            foreach ($root->childNodes as $c) {
                if ($c->nodeType === XML_ELEMENT_NODE) $elementChildren[] = $c;
                elseif ($c->nodeType === XML_TEXT_NODE && trim($c->nodeValue) !== '') { $elementChildren = null; break; }
            }
            if ($elementChildren === null || count($elementChildren) !== 1) break;
            if (strtolower($elementChildren[0]->nodeName) !== 'div') break;
            $root = $elementChildren[0];
            $descended = true;
        }

        $isHeadingNode = function (DOMNode $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) return false;
            if (strtolower($node->nodeName) === 'h2') return true;
            if (strtolower($node->nodeName) === 'div'
                && strpos($node->getAttribute('class'), 'agr-wording-changed') !== false) {
                $elementChildren = [];
                foreach ($node->childNodes as $c) {
                    if ($c->nodeType === XML_ELEMENT_NODE) $elementChildren[] = $c;
                }
                return count($elementChildren) === 1 && strtolower($elementChildren[0]->nodeName) === 'h2';
            }
            return false;
        };

        // A node contributes nothing visible -- a bare text/whitespace node,
        // or a <p> whose only content is whitespace.
        $isEmptyish = function (DOMNode $node) {
            if ($node->nodeType === XML_TEXT_NODE) return trim($node->nodeValue) === '';
            if ($node->nodeType === XML_ELEMENT_NODE && strtolower($node->nodeName) === 'p') {
                return trim($node->textContent) === '' && $node->getElementsByTagName('*')->length === 0;
            }
            return false;
        };

        // Snapshot top-level children first -- we remove nodes from $root
        // while iterating, which a live NodeList doesn't tolerate.
        $children = [];
        foreach ($root->childNodes as $c) { $children[] = $c; }

        $i = 0;
        $n = count($children);
        while ($i < $n) {
            $node = $children[$i];
            if ($node->nodeType === XML_ELEMENT_NODE && $isHeadingNode($node)) {
                $j = $i + 1;
                while ($j < $n && $isEmptyish($children[$j])) {
                    $root->removeChild($children[$j]);
                    $j++;
                }
            }
            $i++;
        }

        if ($descended) {
            return $dom->saveHTML($root);
        }
        $out = '';
        foreach ($root->childNodes as $c) {
            $out .= $dom->saveHTML($c);
        }
        return $out;
    } catch (\Throwable $e) {
        return $html;
    }
}

/** Whether an uploaded filename is an image type the PAN card field can
 * show inline (as opposed to a PDF upload, which stays a plain link).
 * Shared between the screen view and inline_pan_card_image_for_pdf() below
 * so both use the same extension list. */
function pan_card_is_displayable_image(string $filename): bool
{
    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
    return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $imageExts, true);
}

/** Reads a local image file and returns it as a "data:...;base64,..." URI,
 * or null if it's missing/unreadable. Used instead of a plain filesystem
 * <img src="..."> path in the dompdf-rendered PDF export: dompdf's chroot +
 * isRemoteEnabled local-file loading has proven unreliable here across
 * Windows path-separator differences and deploy environments where the
 * referenced file didn't actually ship (confirmed 2026-10-21 — company
 * signature and PAN card images both rendering as broken-image icons in the
 * exported PDF while the exact same files displayed fine on-screen). A data
 * URI sidesteps file-path resolution entirely — same technique the TP's own
 * canvas-drawn signature already relies on (it's always a data URI, and it
 * has never had this problem). */
function image_file_as_data_uri(string $absPath): ?string
{
    if (!is_file($absPath)) { return null; }
    $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        default => null,
    };
    if ($mime === null) { return null; }
    $data = @file_get_contents($absPath);
    if ($data === false) { return null; }
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

/** A company signatory signature photo/scan is usually a full-page or
 * full-screenshot image with the actual signature occupying a small part
 * of it, surrounded by blank white/transparent margin. Displayed at the
 * small, wide "signature strip" size used throughout the agreement pages
 * (max-height ~44px), a tall image with mostly-blank content scales down
 * to a sliver only a few pixels wide -- effectively invisible. This crops
 * the image down to the actual ink (anything not near-white/transparent),
 * with a small padding margin, so it displays at a legible size. Runs the
 * bounding-box scan on a downsized copy for speed, then maps the box back
 * to full resolution -- a multi-megapixel upload scanned pixel-by-pixel in
 * PHP userland would be far too slow for a request-time upload handler.
 * Best-effort: any failure leaves the original file untouched rather than
 * blocking the settings save. */
function trim_signature_image_whitespace(string $absPath): void
{
    $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg'], true)) return;
    try {
        $img = $ext === 'png' ? @imagecreatefrompng($absPath) : @imagecreatefromjpeg($absPath);
        if (!$img) return;

        $w = imagesx($img);
        $h = imagesy($img);
        $hasAlpha = ($ext === 'png');

        $scale = min(1.0, 400 / max($w, $h));
        $sw = max(1, (int) round($w * $scale));
        $sh = max(1, (int) round($h * $scale));
        $small = imagecreatetruecolor($sw, $sh);
        if ($hasAlpha) { imagealphablending($small, false); imagesavealpha($small, true); }
        imagecopyresampled($small, $img, 0, 0, 0, 0, $sw, $sh, $w, $h);

        $minX = $sw; $minY = $sh; $maxX = -1; $maxY = -1;
        for ($y = 0; $y < $sh; $y++) {
            for ($x = 0; $x < $sw; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                if ($hasAlpha && ((($rgb >> 24) & 0x7F) >= 110)) continue; // mostly transparent
                $r = ($rgb >> 16) & 0xFF; $g = ($rgb >> 8) & 0xFF; $b = $rgb & 0xFF;
                if ($r > 235 && $g > 235 && $b > 235) continue; // near-white background
                if ($x < $minX) $minX = $x;
                if ($x > $maxX) $maxX = $x;
                if ($y < $minY) $minY = $y;
                if ($y > $maxY) $maxY = $y;
            }
        }
        imagedestroy($small);
        if ($maxX < $minX || $maxY < $minY) { imagedestroy($img); return; } // blank image, leave as-is

        $pad = 0.03;
        $x0 = max(0.0, ($minX / $sw) - $pad);
        $y0 = max(0.0, ($minY / $sh) - $pad);
        $x1 = min(1.0, (($maxX + 1) / $sw) + $pad);
        $y1 = min(1.0, (($maxY + 1) / $sh) + $pad);

        $cropX = (int) round($x0 * $w);
        $cropY = (int) round($y0 * $h);
        $cropW = min((int) round(($x1 - $x0) * $w), $w - $cropX);
        $cropH = min((int) round(($y1 - $y0) * $h), $h - $cropY);
        if ($cropW <= 0 || $cropH <= 0) { imagedestroy($img); return; }

        $cropped = imagecreatetruecolor($cropW, $cropH);
        if ($hasAlpha) { imagealphablending($cropped, false); imagesavealpha($cropped, true); }
        imagecopy($cropped, $img, 0, 0, $cropX, $cropY, $cropW, $cropH);

        if ($ext === 'png') {
            imagepng($cropped, $absPath);
        } else {
            imagejpeg($cropped, $absPath, 92);
        }
        imagedestroy($cropped);
        imagedestroy($img);
    } catch (\Throwable $e) {
        // Best-effort only.
    }
}

/** The on-screen "View Uploaded PAN Card" link is just text in the PDF
 * export -- dompdf doesn't follow links, it just renders the underlined
 * label, which isn't useful on a document meant to be printed/filed.
 * This swaps that link for the actual PAN card image, sized to fit the
 * page, whenever the uploaded file is an image (jpg/png/etc) -- the
 * overwhelming majority of uploads, since the KYC upload UI's own file
 * picker defaults to a phone camera/gallery. A PDF upload (also allowed by
 * that picker) can't be embedded this way without rasterising it first, so
 * it's left as the plain link text, which is still accurate there (the
 * uploaded file genuinely is a PDF, not an image dompdf silently failed to
 * find). $kycDocsAbsDir is the absolute filesystem directory the four
 * agreement pages' differing relative hrefs (`kyc_documents/...` or
 * `../channel-partner/kyc_documents/...` etc.) all ultimately point at. */
function inline_pan_card_image_for_pdf(string $html, string $kycDocsAbsDir): string
{
    $kycDocsAbsDir = rtrim($kycDocsAbsDir, '/\\') . '/';
    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    return preg_replace_callback(
        '#<a href="[^"]*kyc_documents/([^"/]+)" target="_blank" class="agr-value">View Uploaded PAN Card</a>#',
        function (array $m) use ($kycDocsAbsDir, $imageExts) {
            $filename = $m[1];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, $imageExts, true)) {
                return $m[0];
            }
            $dataUri = image_file_as_data_uri($kycDocsAbsDir . $filename);
            if ($dataUri === null) {
                return $m[0];
            }
            return '<img src="' . $dataUri . '" class="agr-pan-img">';
        },
        $html
    );
}
