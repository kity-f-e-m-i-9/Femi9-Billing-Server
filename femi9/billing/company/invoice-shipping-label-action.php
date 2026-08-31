<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/ShippingLabels.php';
error_reporting(0);

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die('Session expired — please reload the page and try again.');
}

$inv_id = $_POST['invoice_id'] ?? '';
$returnUrl = $_POST['return_url'] ?? 'user-manage-invoice?invuser=super_stockiest';
if (!$inv_id) { header("Location: user-manage-invoice?invuser=super_stockiest"); exit; }

// Ownership check — this invoice must belong to this company user, billed
// to a Super Stockist (the only invuser this feature is wired for).
$own = $db_conn->prepare("SELECT inv_id FROM user_invoice WHERE inv_id = ? AND from_user_type = ? AND to_user_type = 'super_stockiest'");
$own->bind_param('ss', $inv_id, $Login_user_TYPEvl);
$own->execute();
$ownRow = $own->get_result()->fetch_assoc();
$own->close();
if (!$ownRow) { header("Location: user-manage-invoice?invuser=super_stockiest"); exit; }

invoiceShippingLabelsEnsureTables($db_conn);

$labels = $_POST['labels'] ?? [];

$db_conn->begin_transaction();
try {
    $sort = 0;
    foreach ($labels as $l) {
        $sort++;
        $labelId    = (int)($l['label_id'] ?? 0);
        $deleted    = !empty($l['deleted']) && $l['deleted'] === '1';
        $countText  = trim($l['count_text'] ?? '');
        $sourceText = trim($l['source_text'] ?? '');
        $fromAddr   = trim($l['from_address'] ?? '');
        $toAddr     = trim($l['to_address'] ?? '');
        $noteText   = trim($l['note_text'] ?? '');

        if ($deleted) {
            if ($labelId > 0) {
                $del1 = $db_conn->prepare("DELETE FROM invoice_shipping_label_items WHERE label_id = ?");
                $del1->bind_param('i', $labelId);
                $del1->execute();
                $del1->close();

                $del2 = $db_conn->prepare("DELETE FROM invoice_shipping_labels WHERE id = ? AND invoice_id = ?");
                $del2->bind_param('is', $labelId, $inv_id);
                $del2->execute();
                $del2->close();
            }
            continue;
        }

        if ($labelId > 0) {
            $upd = $db_conn->prepare("
                UPDATE invoice_shipping_labels
                SET sort_order = ?, count_text = ?, source_text = ?, from_address = ?, to_address = ?, note_text = ?
                WHERE id = ? AND invoice_id = ?
            ");
            $upd->bind_param('isssssis', $sort, $countText, $sourceText, $fromAddr, $toAddr, $noteText, $labelId, $inv_id);
            $upd->execute();
            $upd->close();
        } else {
            $ins = $db_conn->prepare("
                INSERT INTO invoice_shipping_labels (invoice_id, sort_order, count_text, source_text, from_address, to_address, note_text)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->bind_param('sisssss', $inv_id, $sort, $countText, $sourceText, $fromAddr, $toAddr, $noteText);
            $ins->execute();
            $labelId = $ins->insert_id;
            $ins->close();
        }

        // Items: full replace — delete whatever existed for this label, then
        // insert whatever was submitted. Simpler and safe at this row count
        // than tracking per-item add/remove state.
        $delItems = $db_conn->prepare("DELETE FROM invoice_shipping_label_items WHERE label_id = ?");
        $delItems->bind_param('i', $labelId);
        $delItems->execute();
        $delItems->close();

        $items = $l['items'] ?? [];
        $itemSort = 0;
        foreach ($items as $it) {
            $productText = trim($it['product_text'] ?? '');
            $packsCount  = (int)($it['packs_count'] ?? 0);
            if ($productText === '' && $packsCount === 0) continue; // skip a fully-empty row
            $itemSort++;
            $insItem = $db_conn->prepare("
                INSERT INTO invoice_shipping_label_items (label_id, sort_order, product_text, packs_count)
                VALUES (?, ?, ?, ?)
            ");
            $insItem->bind_param('iisi', $labelId, $itemSort, $productText, $packsCount);
            $insItem->execute();
            $insItem->close();
        }
    }
    $db_conn->commit();
} catch (\Throwable $e) {
    $db_conn->rollback();
    die('Could not save shipping labels: ' . htmlspecialchars($e->getMessage()));
}

header("Location: " . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . "saved=1");
exit;
