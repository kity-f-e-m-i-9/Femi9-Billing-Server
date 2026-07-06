<?php
ob_start();
include("checksession.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: manage-tp-invoices?error=unauthorized"); exit;
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

// ── Resolve invoice ────────────────────────────────────────────────────────────
$enc_id = $_GET['id'] ?? $_POST['id'] ?? '';
$inv_id = (int)base64_decode($enc_id);
if (!$inv_id) { header("Location: manage-tp-invoices"); exit; }

// ── Fetch invoice + TP details ─────────────────────────────────────────────────
$stmt = $db_conn->prepare("
    SELECT tpi.*, tp.name AS tp_name, tp.tp_id AS tp_code, tp.mobile AS tp_mobile,
           pln.name AS source_location
    FROM tp_invoices tpi
    JOIN territory_partners tp ON tp.id = tpi.territory_partner_id
    JOIN partner_location_nodes pln ON pln.id = tpi.source_location_id
    WHERE tpi.id = ?
");
$stmt->bind_param("i", $inv_id);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$inv) { header("Location: manage-tp-invoices"); exit; }

$courier_charges = (float)$inv['courier_charges'];
$invoice_subtotal = round((float)$inv['total_amount'] - $courier_charges, 2);
$created_by = $_SESSION['LOGIN_USER'] ?? '';

// ── DELETE receipt ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_receipt'])) {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) { die('CSRF error'); }
    $del_id = (int)base64_decode($_POST['receipt_id'] ?? '');
    if ($del_id) {
        // Guard: never delete a CN credit receipt
        $chk = $db_conn->prepare("SELECT payment_mode FROM tp_invoice_receipts WHERE id=? LIMIT 1");
        $chk->bind_param('i', $del_id); $chk->execute();
        $chk_row = $chk->get_result()->fetch_assoc(); $chk->close();
        if (($chk_row['payment_mode'] ?? '') === 'credit_note') {
            $_SESSION['errorMessage'] = "CN credit receipts cannot be deleted manually.";
            header("Location: add-tp-receipt?id=" . urlencode($enc_id)); exit;
        }
        $d = $db_conn->prepare("DELETE FROM tp_invoice_receipts WHERE id = ? AND tp_invoice_id = ?");
        $d->bind_param("ii", $del_id, $inv_id);
        $d->execute();
        $d->close();
    }
    header("Location: add-tp-receipt?id=" . urlencode($enc_id) . "&deleted=1");
    exit;
}

// ── ADD receipt ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_receipt'])) {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) { die('CSRF error'); }

    $amount      = round((float)($_POST['amount'] ?? 0), 2);
    $mode        = trim($_POST['payment_mode'] ?? '');
    $remarks     = trim($_POST['remarks'] ?? '');
    $rcpt_date   = $_POST['receipt_date'] ?? date('Y-m-d');

    // Server-side: re-derive pending from live DB
    $s = $db_conn->prepare("SELECT COALESCE(SUM(amount),0) FROM tp_invoice_receipts WHERE tp_invoice_id = ?");
    $s->bind_param("i", $inv_id);
    $s->execute();
    [$collected] = $s->get_result()->fetch_row();
    $s->close();
    $pending = round(max(0.0, $courier_charges - (float)$collected), 2);

    if ($amount <= 0 || $mode === '') {
        header("Location: add-tp-receipt?id=" . urlencode($enc_id) . "&error=invalid"); exit;
    }
    if ($amount > $pending + 0.01) {
        header("Location: add-tp-receipt?id=" . urlencode($enc_id) . "&error=overpayment&pending=" . urlencode(inr_format($pending, 2))); exit;
    }
    $amount = min($amount, $pending); // clamp floating point edge case only
    $ins = $db_conn->prepare("
        INSERT INTO tp_invoice_receipts (tp_invoice_id, invoice_number, amount, receipt_date, payment_mode, remarks, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->bind_param("isdssss", $inv_id, $inv['invoice_number'], $amount, $rcpt_date, $mode, $remarks, $created_by);
    $ins->execute();
    $ins->close();
    header("Location: add-tp-receipt?id=" . urlencode($enc_id) . "&added=1");
    exit;
}

// ── Fetch receipt history + compute totals ─────────────────────────────────────
$rh = $db_conn->prepare("SELECT * FROM tp_invoice_receipts WHERE tp_invoice_id = ? ORDER BY receipt_date ASC, id ASC");
$rh->bind_param("i", $inv_id);
$rh->execute();
$receipts = $rh->get_result()->fetch_all(MYSQLI_ASSOC);
$rh->close();

$collected     = array_sum(array_column($receipts, 'amount'));
$pending       = round(max(0.0, $courier_charges - $collected), 2);
$needs_payment = $courier_charges > 0.01 && $pending > 0.01;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Receipt : <?php echo htmlspecialchars($inv['invoice_number']); ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: 'Poppins', sans-serif; }

.alert { border-radius:10px; border:none; padding:13px 16px; margin-bottom:16px; display:flex; align-items:flex-start; gap:10px; font-size:13.5px; }
.alert .material-icons-outlined { font-size:18px; flex-shrink:0; margin-top:1px; }
.alert-success { background:#d1fae5; color:#065f46; border-left:4px solid #10b981; }
.alert-danger  { background:#fee2e2; color:#991b1b; border-left:4px solid #ef4444; }
.alert-info    { background:#dbeafe; color:#1e40af; border-left:4px solid #3b82f6; }

.card { border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.06); margin-bottom:22px; }
.card-body { padding:24px; }

.section-title { font-size:14px; font-weight:700; color:#1e293b; margin:22px 0 12px; padding-bottom:8px; border-bottom:2px solid #e5e7eb; display:flex; align-items:center; gap:8px; }
.section-title .material-icons-outlined { font-size:18px; color:#667eea; }

.inv-meta { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin-bottom:20px; }
.inv-meta-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; }
.inv-meta-box .label { font-size:11px; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:.4px; margin-bottom:4px; }
.inv-meta-box .value { font-size:15px; font-weight:700; color:#1e293b; }
.inv-meta-box.green  { border-color:#10b981; background:#ecfdf5; } .inv-meta-box.green  .value { color:#065f46; }
.inv-meta-box.orange { border-color:#f59e0b; background:#fffbeb; } .inv-meta-box.orange .value { color:#92400e; }
.inv-meta-box.red    { border-color:#ef4444; background:#fef2f2; } .inv-meta-box.red    .value { color:#991b1b; }
.inv-meta-box.blue   { border-color:#3b82f6; background:#eff6ff; } .inv-meta-box.blue   .value { color:#1e40af; }
.inv-meta-box.purple { border-color:#8b5cf6; background:#f5f3ff; } .inv-meta-box.purple .value { color:#5b21b6; }

.femi-table { width:100%; border-collapse:collapse; font-size:13.5px; }
.femi-table th { background:#f8fafc; color:#475569; font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.4px; padding:11px 14px; border-bottom:2px solid #e5e7eb; white-space:nowrap; }
.femi-table td { padding:11px 14px; border-bottom:1px solid #f1f5f9; color:#1e293b; vertical-align:middle; }
.femi-table tbody tr:hover td { background:#fafafa; }
.femi-table tfoot td { font-weight:700; background:#f1f5f9; font-size:13px; }

.form-group { margin-bottom:16px; }
.form-label { font-size:13px; font-weight:500; color:#374151; display:block; margin-bottom:5px; }
.form-control { border:2px solid #e5e7eb; border-radius:8px; padding:9px 13px; font-size:13.5px; width:100%; transition:border-color .2s; font-family:inherit; }
.form-control:focus { border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,.1); outline:none; }
.form-control:disabled { background:#f8fafc; color:#94a3b8; }

.btn-primary-custom { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; border:none; border-radius:8px; padding:10px 22px; font-size:13.5px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:7px; font-family:inherit; transition:filter .15s,transform .1s; }
.btn-primary-custom:hover { filter:brightness(1.08); }
.btn-primary-custom:active { transform:scale(.97); }

.delete-btn { color:#dc2626; font-size:12px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:3px; padding:4px 10px; border-radius:6px; background:#fee2e2; border:none; cursor:pointer; font-family:inherit; transition:background .15s; }
.delete-btn:hover { background:#fecaca; }

.paid-banner { display:inline-flex; align-items:center; gap:8px; background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; border-radius:10px; padding:10px 18px; font-size:13.5px; font-weight:600; margin-top:12px; }
.paid-banner .material-icons-outlined { font-size:20px; }

.payment-card { border:2px solid #93c5fd; background:#eff6ff; border-radius:12px; padding:22px 24px; margin-top:16px; }
.payment-card h4 { font-size:14px; font-weight:700; color:#1d4ed8; display:flex; align-items:center; gap:8px; margin-bottom:18px; }
.payment-card h4 .material-icons-outlined { font-size:20px; }

.form-row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
@media(max-width:700px){ .form-row-3 { grid-template-columns:1fr; } }

.mode-badge { display:inline-block; background:#dbeafe; color:#1e40af; border-radius:5px; padding:2px 9px; font-size:11.5px; font-weight:600; }
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

                    <!-- Page Header -->
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble"><tr>
                                        <td>TP Courier Receipt</td>
                                        <td><a href="manage-tp-invoices" title="Back to Manage TP Invoices">&#9776;</a></td>
                                    </tr></table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Flash Messages -->
                    <?php if (isset($_GET['added'])): ?>
                    <div class="alert alert-success">
                        <i class="material-icons-outlined">check_circle</i>
                        <div><strong>Receipt added successfully.</strong></div>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">
                        <i class="material-icons-outlined">delete</i>
                        <div><strong>Receipt deleted.</strong></div>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger">
                        <i class="material-icons-outlined">error_outline</i>
                        <div><strong>Invalid amount or missing payment method.</strong> Please check and try again.</div>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-lg-10 col-xl-8">
                            <div class="card">
                                <div class="card-body">

                                    <!-- Invoice Details -->
                                    <div class="section-title">
                                        <i class="material-icons-outlined">description</i> Invoice Details
                                    </div>
                                    <div class="inv-meta">
                                        <div class="inv-meta-box blue">
                                            <div class="label">Invoice Number</div>
                                            <div class="value"><?php echo htmlspecialchars($inv['invoice_number']); ?></div>
                                        </div>
                                        <div class="inv-meta-box">
                                            <div class="label">Territory Partner</div>
                                            <div class="value"><?php echo htmlspecialchars($inv['tp_name']); ?></div>
                                            <div style="font-size:11.5px;color:#64748b;margin-top:2px;"><?php echo htmlspecialchars($inv['tp_code']); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($inv['tp_mobile'] ?? ''); ?></div>
                                        </div>
                                        <div class="inv-meta-box">
                                            <div class="label">Source Location</div>
                                            <div class="value"><?php echo htmlspecialchars($inv['source_location']); ?></div>
                                        </div>
                                        <div class="inv-meta-box">
                                            <div class="label">Invoice Date</div>
                                            <div class="value"><?php echo date('d M Y', strtotime($inv['invoice_date'])); ?></div>
                                        </div>
                                        <div class="inv-meta-box purple">
                                            <div class="label">Product Amount</div>
                                            <div class="value">₹<?php echo inr_format($invoice_subtotal, 2); ?></div>
                                            <div style="font-size:11px;color:#7c3aed;margin-top:2px;">Paid via Advance</div>
                                        </div>
                                        <div class="inv-meta-box blue">
                                            <div class="label">Courier Charges</div>
                                            <div class="value">₹<?php echo inr_format($courier_charges, 2); ?></div>
                                        </div>
                                    </div>

                                    <?php if ($courier_charges < 0.01): ?>
                                    <!-- No courier charges on this invoice -->
                                    <div class="alert alert-info">
                                        <i class="material-icons-outlined">info</i>
                                        <div>This invoice has no courier charges. The product amount of <strong>₹<?php echo inr_format($invoice_subtotal, 2); ?></strong> was settled via advance payment at the time of invoicing.</div>
                                    </div>

                                    <?php else: ?>
                                    <!-- Payment Summary -->
                                    <div class="section-title">
                                        <i class="material-icons-outlined">bar_chart</i> Courier Payment Summary
                                    </div>
                                    <div class="inv-meta">
                                        <div class="inv-meta-box blue">
                                            <div class="label">Courier Due</div>
                                            <div class="value">₹<?php echo inr_format($courier_charges, 2); ?></div>
                                        </div>
                                        <div class="inv-meta-box green">
                                            <div class="label">Collected</div>
                                            <div class="value">₹<?php echo inr_format($collected, 2); ?></div>
                                        </div>
                                        <div class="inv-meta-box <?php echo $pending > 0.01 ? 'red' : 'green'; ?>">
                                            <div class="label">Balance Pending</div>
                                            <div class="value">₹<?php echo inr_format($pending, 2); ?></div>
                                        </div>
                                    </div>

                                    <!-- Receipt History -->
                                    <?php if (!empty($receipts)): ?>
                                    <div class="section-title">
                                        <i class="material-icons-outlined">receipt_long</i> Receipt History
                                    </div>
                                    <div style="overflow-x:auto;">
                                    <table class="femi-table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Balance After</th>
                                                <th>Method</th>
                                                <th>Remarks</th>
                                                <th>Added By</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $running = $courier_charges;
                                        foreach ($receipts as $idx => $r):
                                            $running = round($running - (float)$r['amount'], 2);
                                        ?>
                                        <tr>
                                            <td style="color:#9ca3af;"><?php echo $idx + 1; ?></td>
                                            <td><?php echo date('d M Y', strtotime($r['receipt_date'])); ?></td>
                                            <td><strong>₹<?php echo inr_format((float)$r['amount'], 2); ?></strong></td>
                                            <td>
                                                <strong style="color:<?php echo $running < 0.01 ? '#065f46' : '#b45309'; ?>">
                                                    ₹<?php echo inr_format(max(0, $running), 2); ?>
                                                </strong>
                                                <?php if ($running < 0.01): ?>
                                                <span style="font-size:11px;color:#065f46;margin-left:3px;">✓ Cleared</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="mode-badge"><?php echo htmlspecialchars($r['payment_mode']); ?></span></td>
                                            <td style="color:#6b7280;font-size:12.5px;"><?php echo htmlspecialchars($r['remarks'] ?: '—'); ?></td>
                                            <td style="color:#6b7280;font-size:12.5px;"><?php echo htmlspecialchars($r['created_by']); ?></td>
                                            <td>
                                                <?php if (($r['payment_mode'] ?? '') === 'credit_note'): ?>
                                                <span style="color:#7c3aed;font-size:12px;font-weight:600;">CN Credit</span>
                                                <?php else: ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($csrf); ?>">
                                                    <input type="hidden" name="delete_receipt" value="1">
                                                    <input type="hidden" name="id"          value="<?php echo htmlspecialchars($enc_id); ?>">
                                                    <input type="hidden" name="receipt_id"  value="<?php echo htmlspecialchars(base64_encode((string)$r['id'])); ?>">
                                                    <button type="submit" class="delete-btn">
                                                        <i class="material-icons-outlined" style="font-size:13px;">delete</i> Remove
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="2" style="text-align:right;font-size:13px;">Total Collected</td>
                                                <td>₹<?php echo inr_format($collected, 2); ?></td>
                                                <td colspan="5"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Add Receipt Form or Paid Banner -->
                                    <?php if (!$needs_payment): ?>
                                    <div class="paid-banner" style="margin-top:16px;">
                                        <i class="material-icons-outlined">check_circle</i>
                                        Courier charges fully collected — ₹<?php echo inr_format($courier_charges, 2); ?> received.
                                    </div>

                                    <?php else: ?>
                                    <div class="section-title" style="margin-top:6px;">
                                        <i class="material-icons-outlined">add_card</i> Add Courier Receipt
                                    </div>
                                    <div class="payment-card">
                                        <h4>
                                            <i class="material-icons-outlined">local_shipping</i>
                                            Record Courier Payment
                                        </h4>
                                        <p style="font-size:13px;color:#1d4ed8;margin-bottom:18px;">
                                            Amount pending: <strong>₹<?php echo inr_format($pending, 2); ?></strong>
                                            &nbsp;·&nbsp; Paid via Cash / UPI / Bank Transfer.
                                        </p>
                                        <form method="POST" id="receiptForm">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                            <input type="hidden" name="add_receipt" value="1">
                                            <input type="hidden" name="id"         value="<?php echo htmlspecialchars($enc_id); ?>">
                                            <div class="form-row-3">
                                                <div class="form-group">
                                                    <label class="form-label">Receipt Date</label>
                                                    <input type="date" name="receipt_date" class="form-control"
                                                           value="<?php echo date('Y-m-d'); ?>"
                                                           max="<?php echo date('Y-m-d'); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Balance Due</label>
                                                    <input type="number" class="form-control" value="<?php echo $pending; ?>" disabled>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Amount Received <span style="color:#ef4444">*</span></label>
                                                    <input type="number" id="rcpt_amount" name="amount" class="form-control" required
                                                           min="0.01" max="<?php echo $pending; ?>" step="0.01"
                                                           placeholder="Enter amount"
                                                           oninput="calcPending(this.value, <?php echo $pending; ?>)">
                                                </div>
                                            </div>
                                            <div class="form-row-3">
                                                <div class="form-group">
                                                    <label class="form-label">Payment Method <span style="color:#ef4444">*</span></label>
                                                    <select name="payment_mode" class="form-control" required>
                                                        <option value="" hidden>Select method</option>
                                                        <option>Cash</option>
                                                        <option>UPI</option>
                                                        <option>Bank Transfer</option>
                                                        <option>Deposit</option>
                                                        <option>Cheque</option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Balance After</label>
                                                    <input type="number" id="balance_after" class="form-control" readonly placeholder="—">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Remarks</label>
                                                    <input type="text" name="remarks" class="form-control" placeholder="Optional note">
                                                </div>
                                            </div>
                                            <button type="submit" class="btn-primary-custom" id="rcptSubmitBtn">
                                                <i class="material-icons-outlined">add</i>
                                                Submit Courier Receipt
                                            </button>
                                        </form>
                                    </div>
                                    <?php endif; ?>

                                    <?php endif; // end courier_charges > 0 ?>

                                    <div style="margin-top:24px;">
                                        <a href="manage-tp-invoices" style="font-size:13px;color:#667eea;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
                                            <i class="material-icons-outlined" style="font-size:16px;">arrow_back</i>
                                            Back to Manage TP Invoices
                                        </a>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
function calcPending(received, due) {
    var r = parseFloat(received) || 0;
    var bal = Math.max(0, parseFloat(due) - r);
    document.getElementById('balance_after').value = bal.toFixed(2);
}
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('receiptForm');
    if (form) {
        form.addEventListener('submit', function () {
            var btn = document.getElementById('rcptSubmitBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;"><i class="material-icons-outlined" style="animation:spin 1s linear infinite;font-size:16px;">refresh</i> Submitting…</span>';
            }
        });
    }
});
</script>
<style>@keyframes spin { to { transform: rotate(360deg); } }</style>
</body>
</html>
