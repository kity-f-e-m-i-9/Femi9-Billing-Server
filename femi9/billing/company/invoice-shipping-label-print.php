<?php include("checksession.php"); require_once("include/GodownAccess.php"); date_default_timezone_set("Asia/Kolkata");
require_once __DIR__ . '/../shared/DispatchSlipSettings.php';
require_once __DIR__ . '/../shared/ShippingLabels.php';
error_reporting(0);
include("config.php");

// Only wired for Company -> Super Stockist invoices for now (the only
// invuser this was actually requested for) — a different invuser would need
// its own seller/buyer address lookup below.
$invuser = $_GET['invuser'] ?? '';
if ($invuser !== 'super_stockiest') { header("Location: user-manage-invoice?invuser=" . urlencode($invuser)); exit; }

$inv_id = base64_decode($_GET['invoiceid'] ?? '');
if (!$inv_id) { header("Location: user-manage-invoice?invuser=super_stockiest"); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stmt = $db_conn->prepare("
    SELECT inv_id, inv_number, date, to_user_id
    FROM user_invoice
    WHERE inv_id = ? AND from_user_type = ? AND to_user_type = 'super_stockiest'
");
$stmt->bind_param("ss", $inv_id, $Login_user_TYPEvl);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$invoice) { header("Location: user-manage-invoice?invuser=super_stockiest"); exit; }

// Buyer — the Super Stockist this invoice was billed to
$ss_stmt = $db_conn->prepare("SELECT * FROM super_stockiest WHERE temp_id = ? LIMIT 1");
$ss_stmt->bind_param("s", $invoice['to_user_id']);
$ss_stmt->execute();
$result_SS = $ss_stmt->get_result()->fetch_assoc();
$ss_stmt->close();

// Seller — primary godown this company user can see (same source as
// user-invoice-print.php's own Seller block)
$result_Godown = $db_conn->query("SELECT * FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " LIMIT 1")->fetch_assoc();

// Self-migrating: see db_migrations/2026_08_21_products_packs_per_cover.sql
$_ppcCol = $db_conn->query("SHOW COLUMNS FROM products LIKE 'packs_per_cover'");
if ($_ppcCol && $_ppcCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE products ADD COLUMN packs_per_cover INT UNSIGNED NULL AFTER packs_per_carton");
}

$stmt2 = $db_conn->prepare("
    SELECT uii.qty, p.productName, p.packs_per_carton, p.packs_per_cover
    FROM user_invoice_items uii
    JOIN products p ON p.id = uii.pr_id
    WHERE uii.inv_id = ?
    ORDER BY uii.id
");
$stmt2->bind_param("s", $inv_id);
$stmt2->execute();
$invoice_items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

$boxTotals = dispatchSlipComputeBoxes($db_conn, $invoice_items);
$totalBoxes = $boxTotals['TotalBoxes'];

// Default From (seller godown) / To (Super Stockist) address text — used
// only to seed a brand-new label row; once a row exists, editing it here
// never touches the row again (that's the point of it being editable).
$fromDefault = trim(implode("\n", array_filter([
    $result_Godown['gname'] ?? '',
    $result_Godown['address_line1'] ?? '',
    $result_Godown['address_line2'] ?? '',
    !empty($result_Godown['contact']) ? 'Contact: ' . $result_Godown['contact'] : '',
])));
$toDefault = trim(implode("\n", array_filter([
    $result_SS['name'] ?? '',
    $result_SS['address'] ?? '',
    !empty($result_SS['mobile_number']) ? 'Mobile: ' . $result_SS['mobile_number'] : '',
])));

invoiceShippingLabelsEnsureTables($db_conn);
invoiceShippingLabelsSeedIfEmpty($db_conn, $inv_id, $boxTotals['boxes'], $fromDefault, $toDefault);
$labels = invoiceShippingLabelsFetchForInvoice($db_conn, $inv_id);

$productNames = $db_conn->query("SELECT DISTINCT productName FROM products WHERE productName IS NOT NULL AND productName <> '' ORDER BY productName")->fetch_all(MYSQLI_ASSOC);

// Recently-used Source values (company-wide) — a convenience datalist only,
// never a restriction; Source stays free text since where a box physically
// ships from (a rack, a vehicle, a shorthand like "G2"/"H.O") isn't
// something the system tracks anywhere else to pick from.
$recentSources = $db_conn->query("
    SELECT source_text FROM invoice_shipping_labels
    WHERE source_text IS NOT NULL AND source_text <> ''
    GROUP BY source_text ORDER BY MAX(updated_at) DESC LIMIT 20
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shipping Label : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        #labelSheet { max-width: 900px; }
        .lbl-entry {
            position:relative;
            background:#fff; border:1px solid #e5e7eb; border-radius:12px;
            padding:20px 22px; margin-bottom:18px; box-shadow:0 1px 3px rgba(0,0,0,0.04);
        }
        .lbl-note-inline {
            position:absolute; top:16px; right:20px; text-align:right;
            font-family:'Poppins',sans-serif; font-size:17px; font-weight:700; color:#b91c1c;
            border:1px solid transparent; border-radius:7px; padding:4px 6px; background:transparent;
            width:230px;
        }
        .lbl-note-inline:focus { background:#fff; border-color:#764ba2; outline:none; }
        .lbl-note-inline::placeholder { color:#c4a2a5; font-weight:400; }
        .lbl-toprow { display:flex; justify-content:flex-start; align-items:flex-start; margin-bottom:14px; }
        .lbl-meta { display:flex; flex-direction:column; gap:5px; flex:0 0 auto; }
        .lbl-meta-row { display:flex; align-items:flex-start; gap:7px; }
        .lbl-meta-row .k {
            width: 52px; flex: 0 0 auto; font-size:12px; font-weight:800; color:#1f2937;
            text-transform:uppercase; letter-spacing:.04em; padding-top:6px;
        }
        .lbl-meta-row .sep { display:none; }
        @media print { .lbl-meta-row .sep { display:inline; margin-top:2px; } }
        .lbl-meta-row input[type=text] {
            font-family:'Poppins',sans-serif; font-size:13.5px; color:#1f2937;
            border:1px solid #e5e7eb; border-radius:7px; padding:6px 10px; background:#f9fafb;
            box-sizing:content-box;
        }
        .lbl-meta-row input[type=text]:focus { background:#fff; border-color:#764ba2; outline:none; }
        .lbl-meta-row.count input { width:80px; font-weight:600; }
        .lbl-meta-row.source input { width:160px; }
        .lbl-product-list { display:grid; grid-template-columns:minmax(120px,1fr) auto auto; column-gap:8px; row-gap:6px; align-items:center; }
        .lbl-product-row { display:contents; }
        .lbl-product-row .qty-unit { display:flex; align-items:center; gap:4px; }
        .lbl-product-row input[type=text] {
            font-family:'Poppins',sans-serif; font-size:13.5px; color:#1f2937;
            border:1px solid #e5e7eb; border-radius:7px; padding:6px 10px; background:#f9fafb;
            width:100%; box-sizing:border-box;
        }
        .lbl-product-row input[type=text]:focus { background:#fff; border-color:#764ba2; outline:none; }
        .lbl-product-row input[type=number] {
            font-family:'Poppins',sans-serif; font-size:13.5px; color:#1f2937;
            border:1px solid #e5e7eb; border-radius:7px; padding:6px 8px; background:#f9fafb;
            width:52px; text-align:right; box-sizing:border-box;
            -moz-appearance:textfield;
        }
        .lbl-product-row input[type=number]::-webkit-outer-spin-button,
        .lbl-product-row input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance:none; margin:0;
        }
        .lbl-product-row input[type=number]:focus { background:#fff; border-color:#764ba2; outline:none; }
        .lbl-product-row span.unit { font-size:12px; color:#9ca3af; }
        .lbl-remove-item, .lbl-remove-box {
            background:none; border:none; color:#b91c1c; cursor:pointer; font-size:14px; line-height:1; padding:4px;
        }
        .lbl-remove-box { display:inline-flex; align-items:center; gap:4px; font-size:12px; font-weight:600; }
        .lbl-product-wrap { display:flex; flex-direction:column; gap:6px; }
        .lbl-add-item {
            display:inline-flex; align-items:center; gap:4px; align-self:flex-start;
            font-size:12px; font-weight:600; color:#fff; cursor:pointer; border:none; border-radius:20px;
            padding:5px 13px; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            box-shadow:0 1px 3px rgba(118,75,162,0.35);
        }
        .lbl-add-item:hover { filter:brightness(1.06); }
        .lbl-addr-grid { display:grid; grid-template-columns:1fr 1fr; align-items:center; gap:20px; margin-top:14px; padding-top:16px; border-top:1px solid #f1f0ec; }
        @media (max-width: 700px) { .lbl-addr-grid { grid-template-columns:1fr; } }
        .lbl-addr-label { font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; margin-bottom:5px; }
        .lbl-addr-grid textarea {
            width:100%; min-height:64px; font-family:'Times New Roman',Times,serif; font-size:16px; font-weight:700; line-height:1.5;
            color:#1f2937; border:1px solid #e5e7eb; border-radius:8px; padding:9px 11px; background:#f9fafb;
            resize:none; overflow:hidden;
        }
        .lbl-addr-grid textarea:focus { background:#fff; border-color:#764ba2; outline:none; }
        .lbl-entryfoot { display:flex; justify-content:flex-end; margin-top:12px; }
        .lbl-add-box {
            display:block; margin:4px auto 20px; color:#fff; border:none; border-radius:20px;
            padding:8px 20px; font-size:13px; font-weight:600;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            box-shadow:0 2px 6px rgba(118,75,162,0.35);
        }
        .lbl-add-box:hover { filter:brightness(1.06); }

        @media print {
            .no-print { display:none !important; }
            body, .app, .app-container, .app-content, .content-wrapper, .container-fluid, form#labelsForm {
                display:block !important; margin:0 !important; padding:0 !important;
                width:100% !important; height:auto !important; min-height:0 !important;
                max-height:none !important; overflow:visible !important;
            }
            .app-sidebar, .app-header { display:none !important; }
            #labelSheet { max-width:none; }

            .lbl-entry {
                background:none; border:none; border-bottom:1px solid #000; border-radius:0;
                box-shadow:none; padding:6px 0; break-inside: avoid;
            }
            .lbl-toprow { margin-bottom:5px; }
            .lbl-meta { gap:2px; }
            .lbl-meta-row { align-items:baseline !important; gap:12px !important; }
            .lbl-meta-row .k { color:#000; padding-top:0; line-height:1; font-size:22px; text-transform:none; letter-spacing:normal; font-weight:bold; font-family:'Times New Roman',Times,serif; }
            .lbl-product-list { display:grid; grid-template-columns:1fr 1fr; column-gap:16px; row-gap:2px; max-width:640px; }
            .lbl-product-row { display:flex !important; align-items:center; gap:3px; }
            .lbl-product-row .qty-unit { margin-left:0; gap:2px; }
            .lbl-product-row:not(:last-child)::after { content: ","; margin-right:5px; font-size:22px; font-weight:bold; font-family:'Times New Roman',Times,serif; }
            .lbl-product-wrap { gap:0; }
            .lbl-addr-grid { border-top:none; padding-top:4px; gap:10px; }
            .lbl-addr-label { color:#000; font-size:12px; text-transform:none; letter-spacing:normal; margin-bottom:2px; }
            .lbl-meta-row input, .lbl-product-row input, .lbl-addr-grid textarea {
                border:none !important; background:transparent !important; padding:0 !important; -webkit-appearance:none;
            }
            .lbl-meta-row input, .lbl-product-row input {
                font-family:'Times New Roman',Times,serif !important; font-size:24px !important; font-weight:900 !important; color:#000 !important;
            }
            .lbl-product-row input[type=text] { width:auto; min-width:0 !important; }
            .lbl-product-row span.unit { font-size:17px !important; font-weight:bold !important; color:#000 !important; font-family:'Times New Roman',Times,serif !important; }
            .lbl-addr-grid textarea {
                min-height:0; line-height:1.25; font-family:'Times New Roman',Times,serif !important; font-size:18px; font-weight:700; color:#000;
            }
            .lbl-note-inline {
                top:9px; right:0; color:#000; font-weight:bold; font-family:arial; font-size:15px; width:auto;
                border:none !important; background:transparent !important; padding:0 !important;
            }
            .lbl-note-inline::placeholder { color:transparent; }
        }
    </style>
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">

                    <div class="page-description no-print" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                        <h1 style="margin:0;">
                            <i class="material-icons-outlined" style="vertical-align:middle;margin-right:6px;">local_shipping</i>
                            Shipping Labels — Invoice #<?= htmlspecialchars($invoice['inv_number']); ?>
                        </h1>
                        <div style="display:flex;gap:8px;">
                            <button type="button" onclick="window.location='user-manage-invoice?invuser=super_stockiest';" class="btn btn-secondary btn-sm">Back to Invoices</button>
                        </div>
                    </div>
                    <p class="no-print" style="color:#6b7280;font-size:13px;margin-top:4px;">
                        This invoice needs <b><?= $boxTotals['TotalBoxesDisplay']; ?></b>,
                        so <?= $totalBoxes; ?> label row<?= $totalBoxes !== 1 ? 's were' : ' was'; ?> started below with a computed Product breakdown.
                        Everything here is editable; Source is always typed in, since where a box actually ships from isn't tracked anywhere else.
                    </p>

                    <form id="labelsForm" method="post" action="invoice-shipping-label-action.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="invoice_id" value="<?= htmlspecialchars($inv_id); ?>">
                        <input type="hidden" name="return_url" value="invoice-shipping-label-print.php?invuser=super_stockiest&invoiceid=<?= urlencode(base64_encode($inv_id)); ?>">

                        <div id="labelSheet">
                        <?php $li = 0; foreach ($labels as $label): $li++; ?>
                        <div class="lbl-entry" data-idx="<?= $li; ?>">
                            <input type="hidden" name="labels[<?= $li; ?>][label_id]" value="<?= (int)$label['id']; ?>">
                            <input type="hidden" class="lbl-deleted-flag" name="labels[<?= $li; ?>][deleted]" value="0">

                            <input type="text" class="lbl-note-inline" name="labels[<?= $li; ?>][note_text]" value="<?= htmlspecialchars($label['note_text'] ?? ''); ?>" placeholder="e.g. Bill &amp; Brochure Inside">

                            <div class="lbl-toprow">
                                <div class="lbl-meta">
                                    <div class="lbl-meta-row count">
                                        <span class="k">Count</span><span class="sep">-</span>
                                        <input type="text" name="labels[<?= $li; ?>][count_text]" value="<?= htmlspecialchars($label['count_text']); ?>">
                                    </div>
                                    <div class="lbl-meta-row source">
                                        <span class="k">Source</span><span class="sep">-</span>
                                        <input type="text" list="sourceValueList" name="labels[<?= $li; ?>][source_text]" value="<?= htmlspecialchars($label['source_text']); ?>" placeholder="e.g. G2, H.O">
                                    </div>
                                    <div class="lbl-meta-row" style="align-items:flex-start;">
                                        <span class="k">Product</span><span class="sep">-</span>
                                        <div class="lbl-product-wrap">
                                            <div class="lbl-product-list">
                                            <?php $ii = 0; foreach ($label['items'] as $item): $ii++; ?>
                                                <div class="lbl-product-row">
                                                    <input type="text" list="productNameList" name="labels[<?= $li; ?>][items][<?= $ii; ?>][product_text]" value="<?= htmlspecialchars($item['product_text']); ?>" placeholder="Product">
                                                    <span class="qty-unit">
                                                        <input type="number" min="0" name="labels[<?= $li; ?>][items][<?= $ii; ?>][packs_count]" value="<?= (int)$item['packs_count']; ?>" placeholder="0">
                                                        <span class="unit">pkt</span>
                                                    </span>
                                                    <button type="button" class="lbl-remove-item no-print" title="Remove line">✕</button>
                                                </div>
                                            <?php endforeach; ?>
                                            </div>
                                            <button type="button" class="lbl-add-item no-print">+ Add product</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="lbl-addr-grid">
                                <div>
                                    <div class="lbl-addr-label">FROM:</div>
                                    <textarea name="labels[<?= $li; ?>][from_address]"><?= htmlspecialchars($label['from_address']); ?></textarea>
                                </div>
                                <div>
                                    <div class="lbl-addr-label">TO</div>
                                    <textarea name="labels[<?= $li; ?>][to_address]"><?= htmlspecialchars($label['to_address']); ?></textarea>
                                </div>
                            </div>

                            <div class="lbl-entryfoot no-print">
                                <button type="button" class="lbl-remove-box btn btn-outline-danger btn-sm" title="Remove this box">✕ Remove box</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        </div>

                        <button type="button" id="addBoxBtn" class="btn btn-outline-primary btn-sm lbl-add-box no-print">+ Add Box</button>

                        <div class="no-print" style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px;">
                            <button type="button" onclick="window.print();" class="btn btn-dark">Print</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>

                    <datalist id="productNameList">
                        <?php foreach ($productNames as $p): ?>
                        <option value="<?= htmlspecialchars($p['productName']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <datalist id="sourceValueList">
                        <?php foreach ($recentSources as $s): ?>
                        <option value="<?= htmlspecialchars($s['source_text']); ?>">
                        <?php endforeach; ?>
                    </datalist>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var boxIdx = <?= $li; ?>;

    function autoGrowTextarea(el) {
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + 'px';
    }
    function wireAutoGrow(scope) {
        scope.querySelectorAll('.lbl-addr-grid textarea').forEach(function (ta) {
            autoGrowTextarea(ta);
            ta.addEventListener('input', function () { autoGrowTextarea(ta); });
        });
    }
    wireAutoGrow(document);
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () { wireAutoGrow(document); });
    }
    window.addEventListener('beforeprint', function () { wireAutoGrow(document); });

    function autoSizeInput(el, minCh) {
        var len = (el.value || el.placeholder || '').length;
        el.style.width = Math.max(minCh, len + 1) + 'ch';
    }
    function wireAutoSize(scope) {
        scope.querySelectorAll('.lbl-meta-row.count input').forEach(function (el) {
            autoSizeInput(el, 4);
            el.addEventListener('input', function () { autoSizeInput(el, 4); });
        });
        scope.querySelectorAll('.lbl-meta-row.source input').forEach(function (el) {
            autoSizeInput(el, 6);
            el.addEventListener('input', function () { autoSizeInput(el, 6); });
        });
    }

    function sizeForPrint(scope) {
        scope.querySelectorAll('.lbl-product-row input[type=text]').forEach(function (el) {
            el.style.width = (el.value || el.placeholder || '').length + 'ch';
        });
        scope.querySelectorAll('.lbl-product-row input[type=number]').forEach(function (el) {
            el.style.width = String(el.value || el.placeholder || '0').length + 'ch';
        });
    }
    window.addEventListener('beforeprint', function () { sizeForPrint(document); });
    wireAutoSize(document);

    function itemRowHtml(boxIdx, itemIdx) {
        return '<div class="lbl-product-row">' +
            '<input type="text" list="productNameList" name="labels[' + boxIdx + '][items][' + itemIdx + '][product_text]" placeholder="Product">' +
            '<span class="qty-unit">' +
                '<input type="number" min="0" name="labels[' + boxIdx + '][items][' + itemIdx + '][packs_count]" placeholder="0">' +
                '<span class="unit">pkt</span>' +
            '</span>' +
            '<button type="button" class="lbl-remove-item no-print" title="Remove line">✕</button>' +
            '</div>';
    }

    function boxEntryHtml(boxIdx) {
        return '' +
        '<div class="lbl-entry" data-idx="' + boxIdx + '">' +
            '<input type="hidden" name="labels[' + boxIdx + '][label_id]" value="0">' +
            '<input type="hidden" class="lbl-deleted-flag" name="labels[' + boxIdx + '][deleted]" value="0">' +
            '<input type="text" class="lbl-note-inline" name="labels[' + boxIdx + '][note_text]" value="" placeholder="e.g. Bill &amp; Brochure Inside">' +
            '<div class="lbl-toprow"><div class="lbl-meta">' +
                '<div class="lbl-meta-row count"><span class="k">Count</span><span class="sep">-</span><input type="text" name="labels[' + boxIdx + '][count_text]" value=""></div>' +
                '<div class="lbl-meta-row source"><span class="k">Source</span><span class="sep">-</span><input type="text" list="sourceValueList" name="labels[' + boxIdx + '][source_text]" value="" placeholder="e.g. G2, H.O"></div>' +
                '<div class="lbl-meta-row" style="align-items:flex-start;"><span class="k">Product</span><span class="sep">-</span>' +
                    '<div class="lbl-product-wrap">' +
                        '<div class="lbl-product-list">' + itemRowHtml(boxIdx, 1) + '</div>' +
                        '<button type="button" class="lbl-add-item no-print">+ Add product</button>' +
                    '</div>' +
                '</div>' +
            '</div></div>' +
            '<div class="lbl-addr-grid">' +
                '<div><div class="lbl-addr-label">FROM:</div><textarea name="labels[' + boxIdx + '][from_address]"></textarea></div>' +
                '<div><div class="lbl-addr-label">TO</div><textarea name="labels[' + boxIdx + '][to_address]"></textarea></div>' +
            '</div>' +
            '<div class="lbl-entryfoot no-print"><button type="button" class="lbl-remove-box btn btn-outline-danger btn-sm" title="Remove this box">✕ Remove box</button></div>' +
        '</div>';
    }

    document.getElementById('addBoxBtn').addEventListener('click', function () {
        boxIdx++;
        var div = document.createElement('div');
        div.innerHTML = boxEntryHtml(boxIdx);
        var entry = div.firstChild;
        document.getElementById('labelSheet').appendChild(entry);
        wireAutoGrow(entry);
        wireAutoSize(entry);
    });

    document.getElementById('labelSheet').addEventListener('click', function (e) {
        if (e.target.classList.contains('lbl-remove-box')) {
            var entry = e.target.closest('.lbl-entry');
            var flag = entry.querySelector('.lbl-deleted-flag');
            var labelIdInput = entry.querySelector('input[name$="[label_id]"]');
            if (labelIdInput && labelIdInput.value !== '0') {
                flag.value = '1';
                entry.style.display = 'none';
            } else {
                entry.remove();
            }
        }
        if (e.target.classList.contains('lbl-remove-item')) {
            e.target.closest('.lbl-product-row').remove();
        }
        if (e.target.classList.contains('lbl-add-item')) {
            var entry = e.target.closest('.lbl-entry');
            var list = entry.querySelector('.lbl-product-list');
            var boxN = entry.getAttribute('data-idx');
            var itemN = list.querySelectorAll('.lbl-product-row').length + 1;
            list.appendChild(document.createRange().createContextualFragment(itemRowHtml(boxN, itemN)));
            wireAutoSize(list);
        }
    });
})();
</script>
</body>
</html>
