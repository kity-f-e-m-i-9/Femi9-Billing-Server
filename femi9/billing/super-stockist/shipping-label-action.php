<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/ShippingLabels.php';
error_reporting(0);

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die('Session expired — please reload the page and try again.');
}

$po_id = (int)($_POST['po_id'] ?? 0);
$returnUrl = $_POST['return_url'] ?? ('shipping-label-print.php?po_id=' . $po_id);
if (!$po_id) { header("Location: tp-today-orders"); exit; }

// Ownership check — this PO must belong to a TP onboarded by this SS.
$ss_id = $Login_user_IDvl;
$own = $db_conn->prepare("
    SELECT o.id FROM tp_purchase_orders o
    JOIN territory_partners tp ON tp.id = o.territory_partner_id
    WHERE o.id = ? AND tp.onboard_ss_id = ?
");
$own->bind_param('is', $po_id, $ss_id);
$own->execute();
$ownRow = $own->get_result()->fetch_assoc();
$own->close();
if (!$ownRow) { header("Location: tp-today-orders"); exit; }

shippingLabelsEnsureTables($db_conn);

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
                $del1 = $db_conn->prepare("DELETE FROM po_shipping_label_items WHERE label_id = ?");
                $del1->bind_param('i', $labelId);
                $del1->execute();
                $del1->close();

                $del2 = $db_conn->prepare("DELETE FROM po_shipping_labels WHERE id = ? AND po_id = ?");
                $del2->bind_param('ii', $labelId, $po_id);
                $del2->execute();
                $del2->close();
            }
            continue;
        }

        if ($labelId > 0) {
            $upd = $db_conn->prepare("
                UPDATE po_shipping_labels
                SET sort_order = ?, count_text = ?, source_text = ?, from_address = ?, to_address = ?, note_text = ?
                WHERE id = ? AND po_id = ?
            ");
            $upd->bind_param('isssssii', $sort, $countText, $sourceText, $fromAddr, $toAddr, $noteText, $labelId, $po_id);
            $upd->execute();
            $upd->close();
        } else {
            $ins = $db_conn->prepare("
                INSERT INTO po_shipping_labels (po_id, sort_order, count_text, source_text, from_address, to_address, note_text)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->bind_param('iisssss', $po_id, $sort, $countText, $sourceText, $fromAddr, $toAddr, $noteText);
            $ins->execute();
            $labelId = $ins->insert_id;
            $ins->close();
        }

        // Items: full replace — delete whatever existed for this label, then
        // insert whatever was submitted. Simpler and safe at this row count
        // than tracking per-item add/remove state.
        $delItems = $db_conn->prepare("DELETE FROM po_shipping_label_items WHERE label_id = ?");
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
                INSERT INTO po_shipping_label_items (label_id, sort_order, product_text, packs_count)
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
