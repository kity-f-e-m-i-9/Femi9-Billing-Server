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
        'address'      => $cmp($agreement['snap_address'], $live['address'] ?? ''),
        'mobile'       => $cmp($agreement['snap_mobile'], $live['mobile'] ?? ''),
        'email'        => $cmp($agreement['snap_email'], $live['email'] ?? ''),
    ];
}
