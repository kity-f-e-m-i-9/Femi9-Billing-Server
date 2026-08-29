<?php
/**
 * Per-box shipping labels for a TP purchase order — separate from the
 * Dispatch Slip. The Dispatch Slip's Box column only produces a shipment-
 * wide COUNT of how many boxes are needed (see DispatchSlipSettings.php);
 * it has no idea which physical box a given product's packs end up in —
 * that's a packing-floor decision, not something derivable from order data.
 *
 * So a shipping label row per box is created with sensible defaults (From/To
 * address copied from the PO, Count numbered against the Dispatch Slip's own
 * Total Boxes), and Source is always free text typed in by whoever is
 * packing/dispatching it (which exact godown/rack/vehicle a box ships from
 * isn't tracked anywhere in the system, so there's no list to pick from).
 *
 * See db_migrations/2026_08_25_shipping_labels.sql for the real migration.
 */

function shippingLabelsEnsureTables(mysqli $db): void
{
    $db->query("
        CREATE TABLE IF NOT EXISTS po_shipping_labels (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          po_id INT UNSIGNED NOT NULL,
          sort_order INT UNSIGNED NOT NULL DEFAULT 0,
          count_text VARCHAR(20) NOT NULL DEFAULT '',
          source_text VARCHAR(100) NOT NULL DEFAULT '',
          from_address TEXT NULL,
          to_address TEXT NULL,
          note_text VARCHAR(100) NOT NULL DEFAULT '',
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          KEY idx_psl_po (po_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    // Self-migrating: see db_migrations/2026_08_26_shipping_label_note.sql
    $noteCol = $db->query("SHOW COLUMNS FROM po_shipping_labels LIKE 'note_text'");
    if ($noteCol && $noteCol->num_rows === 0) {
        $db->query("ALTER TABLE po_shipping_labels ADD COLUMN note_text VARCHAR(100) NOT NULL DEFAULT '' AFTER to_address");
    }
    $db->query("
        CREATE TABLE IF NOT EXISTS po_shipping_label_items (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          label_id INT UNSIGNED NOT NULL,
          sort_order INT UNSIGNED NOT NULL DEFAULT 0,
          product_text VARCHAR(255) NOT NULL,
          packs_count INT UNSIGNED NOT NULL DEFAULT 0,
          KEY idx_psli_label (label_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

// Product names in this catalog are typically "SHORT FORM - Long marketing
// suffix" (e.g. "330mm XL (9 PCS) - Femi9 Premium Sanitary Napkin"). A
// shipping label only has room for the short form — same abbreviated style
// used on the physical labels this feature replaces — so this keeps
// whatever comes before the first " - " and drops the rest. A name with no
// " - " (e.g. "Lumi9 Baby Diaper NB(3)") is already short and passes through
// unchanged.
// Common brand/category phrases that repeat across every line of a
// multi-line label (e.g. every Diaper line starts "Lumi9 Baby Diaper ") —
// stripping them once here means the Product list only shows what actually
// varies between lines ("L(24)" instead of "Lumi9 Baby Diaper L(24)").
const SHIPPING_LABEL_STRIP_PREFIXES = ['Lumi9 Baby Diaper '];

// The bill and brochure ship with the LAST box only (not one per box), so
// that box's label gets a note to flag it — pre-filled once when the labels
// are first seeded, still freely editable afterward like everything else.
const SHIPPING_LABEL_LAST_BOX_NOTE = 'Bill & Brochure Inside';

function shippingLabelShortProductName(string $fullName): string
{
    $short = trim(explode(' - ', $fullName, 2)[0]);
    foreach (SHIPPING_LABEL_STRIP_PREFIXES as $prefix) {
        if (stripos($short, $prefix) === 0) {
            $short = trim(substr($short, strlen($prefix)));
            break;
        }
    }
    return $short;
}

/**
 * If this PO has no label rows yet, creates one row per entry in $boxes
 * (from dispatchSlipComputeBoxes()'s 'boxes' key — minimum 1 row even if
 * that list is empty), pre-filled with the given From/To address text,
 * numbered "1 / N" etc., and its Product lines pre-filled from that box's
 * own computed contents (still fully editable afterward — see
 * dispatchSlipComputeBoxes()'s docblock for how that breakdown is derived).
 * Never runs again once at least one row exists — later re-numbering the
 * count, editing contents, or adding/removing boxes is a manual, per-row
 * action from then on.
 */
function shippingLabelsSeedIfEmpty(mysqli $db, int $poId, array $boxes, string $fromAddress, string $toAddress): void
{
    $existing = $db->query("SELECT COUNT(*) AS c FROM po_shipping_labels WHERE po_id = " . (int)$poId)->fetch_assoc();
    if ((int)($existing['c'] ?? 0) > 0) return;

    if (empty($boxes)) $boxes = [['contents' => []]];
    $total = count($boxes);

    $stmt = $db->prepare("
        INSERT INTO po_shipping_labels (po_id, sort_order, count_text, from_address, to_address, note_text)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $itemStmt = $db->prepare("
        INSERT INTO po_shipping_label_items (label_id, sort_order, product_text, packs_count)
        VALUES (?, ?, ?, ?)
    ");
    $i = 0;
    foreach ($boxes as $box) {
        $i++;
        $countText = $i . ' / ' . $total;
        $noteText = ($i === $total) ? SHIPPING_LABEL_LAST_BOX_NOTE : '';
        $stmt->bind_param('iissss', $poId, $i, $countText, $fromAddress, $toAddress, $noteText);
        $stmt->execute();
        $labelId = $stmt->insert_id;

        $j = 0;
        foreach (($box['contents'] ?? []) as $line) {
            $j++;
            $productText = shippingLabelShortProductName($line['product'] ?? '');
            $packs = (int)($line['packs'] ?? 0);
            $itemStmt->bind_param('iisi', $labelId, $j, $productText, $packs);
            $itemStmt->execute();
        }
    }
    $stmt->close();
    $itemStmt->close();
}

/** @return array<int,array{id:int,sort_order:int,count_text:string,source_text:string,from_address:string,to_address:string,note_text:string,items:array}> */
function shippingLabelsFetchForPo(mysqli $db, int $poId): array
{
    $labels = [];
    $stmt = $db->prepare("
        SELECT id, sort_order, count_text, source_text, from_address, to_address, note_text
        FROM po_shipping_labels WHERE po_id = ? ORDER BY sort_order, id
    ");
    $stmt->bind_param('i', $poId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $r) {
        $r['id'] = (int)$r['id'];
        $r['sort_order'] = (int)$r['sort_order'];
        $r['items'] = [];
        $labels[$r['id']] = $r;
    }
    if (empty($labels)) return [];

    $ids = implode(',', array_map('intval', array_keys($labels)));
    $itemRows = $db->query("
        SELECT id, label_id, product_text, packs_count
        FROM po_shipping_label_items WHERE label_id IN ($ids) ORDER BY sort_order, id
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($itemRows as $ir) {
        $labels[(int)$ir['label_id']]['items'][] = [
            'id'           => (int)$ir['id'],
            'product_text' => $ir['product_text'],
            'packs_count'  => (int)$ir['packs_count'],
        ];
    }

    return array_values($labels);
}

/**
 * Same per-box shipping label concept as above, but for a direct Company ->
 * Super Stockist invoice (user-manage-invoice.php?invuser=super_stockiest)
 * instead of a TP Purchase Order — there's no PO behind this kind of
 * invoice, so the box breakdown is computed from the invoice's own line
 * items (user_invoice_items) rather than tp_purchase_order_items. Kept as a
 * separate pair of tables (invoice_id here is user_invoice.inv_id, a
 * string, not an int) rather than reusing po_shipping_labels, since po_id
 * there has no real FK and reusing it would risk an invoice's numeric-looking
 * id colliding with an unrelated PO's id.
 */
function invoiceShippingLabelsEnsureTables(mysqli $db): void
{
    $db->query("
        CREATE TABLE IF NOT EXISTS invoice_shipping_labels (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          invoice_id VARCHAR(64) NOT NULL,
          sort_order INT UNSIGNED NOT NULL DEFAULT 0,
          count_text VARCHAR(20) NOT NULL DEFAULT '',
          source_text VARCHAR(100) NOT NULL DEFAULT '',
          from_address TEXT NULL,
          to_address TEXT NULL,
          note_text VARCHAR(100) NOT NULL DEFAULT '',
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          KEY idx_isl_invoice (invoice_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $db->query("
        CREATE TABLE IF NOT EXISTS invoice_shipping_label_items (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          label_id INT UNSIGNED NOT NULL,
          sort_order INT UNSIGNED NOT NULL DEFAULT 0,
          product_text VARCHAR(255) NOT NULL,
          packs_count INT UNSIGNED NOT NULL DEFAULT 0,
          KEY idx_isli_label (label_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

/** Same seeding behavior as shippingLabelsSeedIfEmpty(), keyed by invoice_id (string) instead of po_id. */
function invoiceShippingLabelsSeedIfEmpty(mysqli $db, string $invoiceId, array $boxes, string $fromAddress, string $toAddress): void
{
    $stmt0 = $db->prepare("SELECT COUNT(*) AS c FROM invoice_shipping_labels WHERE invoice_id = ?");
    $stmt0->bind_param('s', $invoiceId);
    $stmt0->execute();
    $existing = $stmt0->get_result()->fetch_assoc();
    $stmt0->close();
    if ((int)($existing['c'] ?? 0) > 0) return;

    if (empty($boxes)) $boxes = [['contents' => []]];
    $total = count($boxes);

    $stmt = $db->prepare("
        INSERT INTO invoice_shipping_labels (invoice_id, sort_order, count_text, from_address, to_address, note_text)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $itemStmt = $db->prepare("
        INSERT INTO invoice_shipping_label_items (label_id, sort_order, product_text, packs_count)
        VALUES (?, ?, ?, ?)
    ");
    $i = 0;
    foreach ($boxes as $box) {
        $i++;
        $countText = $i . ' / ' . $total;
        $noteText = ($i === $total) ? SHIPPING_LABEL_LAST_BOX_NOTE : '';
        $stmt->bind_param('sissss', $invoiceId, $i, $countText, $fromAddress, $toAddress, $noteText);
        $stmt->execute();
        $labelId = $stmt->insert_id;

        $j = 0;
        foreach (($box['contents'] ?? []) as $line) {
            $j++;
            $productText = shippingLabelShortProductName($line['product'] ?? '');
            $packs = (int)($line['packs'] ?? 0);
            $itemStmt->bind_param('iisi', $labelId, $j, $productText, $packs);
            $itemStmt->execute();
        }
    }
    $stmt->close();
    $itemStmt->close();
}

/** Same shape as shippingLabelsFetchForPo(), keyed by invoice_id (string) instead of po_id. */
function invoiceShippingLabelsFetchForInvoice(mysqli $db, string $invoiceId): array
{
    $labels = [];
    $stmt = $db->prepare("
        SELECT id, sort_order, count_text, source_text, from_address, to_address, note_text
        FROM invoice_shipping_labels WHERE invoice_id = ? ORDER BY sort_order, id
    ");
    $stmt->bind_param('s', $invoiceId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $r) {
        $r['id'] = (int)$r['id'];
        $r['sort_order'] = (int)$r['sort_order'];
        $r['items'] = [];
        $labels[$r['id']] = $r;
    }
    if (empty($labels)) return [];

    $ids = implode(',', array_map('intval', array_keys($labels)));
    $itemRows = $db->query("
        SELECT id, label_id, product_text, packs_count
        FROM invoice_shipping_label_items WHERE label_id IN ($ids) ORDER BY sort_order, id
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($itemRows as $ir) {
        $labels[(int)$ir['label_id']]['items'][] = [
            'id'           => (int)$ir['id'],
            'product_text' => $ir['product_text'],
            'packs_count'  => (int)$ir['packs_count'],
        ];
    }

    return array_values($labels);
}
