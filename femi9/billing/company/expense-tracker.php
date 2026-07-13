<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('report');
require_once("include/GodownAccess.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Self-migrating: create expense tracker tables if they don't exist yet.
$db_conn->query("
    CREATE TABLE IF NOT EXISTS expense_imports (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_id INT UNSIGNED NOT NULL,
        expense_month DATE NOT NULL,
        source_filename VARCHAR(255) NOT NULL,
        group_name VARCHAR(255) DEFAULT NULL,
        period_label VARCHAR(255) DEFAULT NULL,
        total_debit DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_credit DECIMAL(15,2) NOT NULL DEFAULT 0,
        net_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        uploaded_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_company_month (company_id, expense_month)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$db_conn->query("
    CREATE TABLE IF NOT EXISTS expense_import_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        import_id INT UNSIGNED NOT NULL,
        particulars VARCHAR(255) NOT NULL,
        debit DECIMAL(15,2) NOT NULL DEFAULT 0,
        credit DECIMAL(15,2) NOT NULL DEFAULT 0,
        net_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        KEY idx_import (import_id),
        CONSTRAINT fk_expense_import_items_import FOREIGN KEY (import_id) REFERENCES expense_imports(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Company profiles (finance-only restricted, same pattern as TP Advance Payments).
// Not filtered by name — godown_finance_filter_sql() already encodes the full
// access rule per login type (finance sees all, neksomo sees only its own
// entity, everyone else sees only the non-finance-only entity).
$company_profiles = $db_conn->query("SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$default_company_id = $company_profiles[0]['id'] ?? 0;

$filter_company = isset($_GET['company_id']) ? (int)$_GET['company_id'] : $default_company_id;
if (!is_godown_allowed($db_conn, $filter_company)) {
    $filter_company = $default_company_id;
}

$filter_month = $_GET['expense_month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    $filter_month = date('Y-m');
}
$expense_month_date = $filter_month . '-01';

$upload_msg = $_GET['msg'] ?? '';
$upload_err = $_GET['err'] ?? '';

// Uploaded batches for this company + month
$stmt = $db_conn->prepare("
    SELECT * FROM expense_imports
    WHERE company_id = ? AND expense_month = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("is", $filter_company, $expense_month_date);
$stmt->execute();
$batches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$batch_ids = array_column($batches, 'id');

// Aggregated breakdown across all batches for the month
$breakdown = [];
if (!empty($batch_ids)) {
    $placeholders = implode(',', array_fill(0, count($batch_ids), '?'));
    $types = str_repeat('i', count($batch_ids));
    $stmt = $db_conn->prepare("
        SELECT particulars, SUM(debit) AS debit, SUM(credit) AS credit, SUM(net_amount) AS net_amount
        FROM expense_import_items
        WHERE import_id IN ($placeholders)
        GROUP BY particulars
        ORDER BY net_amount DESC
    ");
    $stmt->bind_param($types, ...$batch_ids);
    $stmt->execute();
    $breakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$total_debit = array_sum(array_column($batches, 'total_debit'));
$total_credit = array_sum(array_column($batches, 'total_credit'));
$total_net = array_sum(array_column($batches, 'net_amount'));
$upload_count = count($batches);

$i = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Expense Tracker : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style>
        .filter-card { background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border-radius:10px; padding:20px; margin-bottom:20px; }
        .filter-card .form-label { color:#fff; font-weight:500; margin-bottom:5px; }
        .filter-card .form-control, .filter-card select { background:rgba(255,255,255,0.95); border:none; border-radius:6px; }
        .stats-card { background:#fff; border-radius:10px; padding:18px 20px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-left:4px solid #667eea; }
        .stats-card h3 { font-size:26px; font-weight:700; margin:0; color:#667eea; }
        .stats-card p { margin:4px 0 0 0; color:#6b7280; font-size:13px; font-weight:500; }
        .upload-card { background:#fff; border-radius:10px; padding:20px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-left:4px solid #10b981; }
        .upload-card h6 { color:#10b981; font-weight:700; }
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

                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble"><tr>
                                        <td>Expense Tracker</td>
                                    </tr></table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <?php if ($upload_msg): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($upload_msg); ?></div>
                    <?php endif; ?>
                    <?php if ($upload_err): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($upload_err); ?></div>
                    <?php endif; ?>

                    <!-- Stats -->
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <h3>₹<?php echo inr_format($total_net, 2); ?></h3>
                                <p>Total Expense (Net)</p>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <h3>₹<?php echo inr_format($total_debit, 2); ?></h3>
                                <p>Total Debit</p>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <h3>₹<?php echo inr_format($total_credit, 2); ?></h3>
                                <p>Total Credit</p>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <h3><?php echo $upload_count; ?></h3>
                                <p>Files Uploaded</p>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="row">
                        <div class="col-12">
                            <div class="filter-card">
                                <form method="GET" action="">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label">Month</label>
                                            <input type="month" name="expense_month" class="form-control" value="<?php echo htmlspecialchars($filter_month); ?>" max="<?php echo date('Y-m'); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Company Profile</label>
                                            <select name="company_id" class="form-control">
                                                <?php foreach ($company_profiles as $cp): ?>
                                                <option value="<?php echo $cp['id']; ?>" <?php echo $filter_company == $cp['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cp['gname']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 d-flex gap-2">
                                            <button type="submit" class="btn btn-light font-weight-bold">
                                                <i class="material-icons" style="vertical-align:middle;font-size:17px;">filter_list</i> View
                                            </button>
                                            <a href="expense-tracker" class="btn" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.5);">
                                                <i class="material-icons" style="vertical-align:middle;font-size:17px;">refresh</i> Reset
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Upload -->
                    <div class="row">
                        <div class="col-12">
                            <div class="upload-card">
                                <h6><i class="material-icons" style="vertical-align:middle;">upload_file</i> Upload Tally Group Summary (Expenses)</h6>
                                <form method="POST" action="expense-tracker-upload-action.php" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-2">
                                            <label class="form-label">Month</label>
                                            <input type="month" name="expense_month" class="form-control" value="<?php echo htmlspecialchars($filter_month); ?>" max="<?php echo date('Y-m'); ?>" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Company Profile</label>
                                            <select name="company_id" class="form-control" required>
                                                <?php foreach ($company_profiles as $cp): ?>
                                                <option value="<?php echo $cp['id']; ?>" <?php echo $filter_company == $cp['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cp['gname']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Tally Excel File (.xlsx/.xls)</label>
                                            <input type="file" name="tally_file" class="form-control" accept=".xlsx,.xls" required>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-success">
                                                <i class="material-icons" style="vertical-align:middle;">cloud_upload</i> Upload
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-2">Export a "Group Summary" report from Tally (e.g. Indirect Expenses) as Excel and upload it here. Re-uploading for the same month adds to existing totals — it does not replace them.</small>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Uploaded Files -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Uploaded Files</h5></div>
                                <div class="card-body">
                                    <div style="overflow-x:auto;">
                                        <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>File Name</th>
                                                    <th>Debit (₹)</th>
                                                    <th>Credit (₹)</th>
                                                    <th>Net Amount (₹)</th>
                                                    <th>Uploaded By</th>
                                                    <th>Uploaded At</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php if (empty($batches)): ?>
                                                <tr><td colspan="8" class="text-center text-muted">No files uploaded for this month yet.</td></tr>
                                            <?php endif; ?>
                                            <?php foreach ($batches as $b): $i++; ?>
                                                <tr>
                                                    <td><?php echo $i; ?></td>
                                                    <td><?php echo htmlspecialchars($b['source_filename']); ?>
                                                        <?php if ($b['period_label']): ?><br><small class="text-muted"><?php echo htmlspecialchars($b['period_label']); ?></small><?php endif; ?>
                                                    </td>
                                                    <td class="text-right"><?php echo inr_format($b['total_debit'], 2); ?></td>
                                                    <td class="text-right"><?php echo inr_format($b['total_credit'], 2); ?></td>
                                                    <td class="text-right font-weight-bold"><?php echo inr_format($b['net_amount'], 2); ?></td>
                                                    <td><?php echo htmlspecialchars($b['uploaded_by']); ?></td>
                                                    <td><?php echo date('d/m/Y h:i A', strtotime($b['created_at'])); ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-danger btn-action delete-batch-btn" data-id="<?php echo $b['id']; ?>" title="Delete Upload">
                                                            <i class="material-icons" style="font-size:16px;vertical-align:middle;">delete</i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Expense Breakdown -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Expense Breakdown (this month, all uploads combined)</h5></div>
                                <div class="card-body">
                                    <div style="overflow-x:auto;">
                                        <table id="datatable2" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Particulars</th>
                                                    <th>Debit (₹)</th>
                                                    <th>Credit (₹)</th>
                                                    <th>Net Amount (₹)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php if (empty($breakdown)): ?>
                                                <tr><td colspan="4" class="text-center text-muted">No expense data for this month yet.</td></tr>
                                            <?php endif; ?>
                                            <?php foreach ($breakdown as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['particulars']); ?></td>
                                                    <td class="text-right"><?php echo inr_format($row['debit'], 2); ?></td>
                                                    <td class="text-right"><?php echo inr_format($row['credit'], 2); ?></td>
                                                    <td class="text-right font-weight-bold"><?php echo inr_format($row['net_amount'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                            <?php if (!empty($breakdown)): ?>
                                            <tfoot>
                                                <tr>
                                                    <td>Total</td>
                                                    <td class="text-right"><?php echo inr_format($total_debit, 2); ?></td>
                                                    <td class="text-right"><?php echo inr_format($total_credit, 2); ?></td>
                                                    <td class="text-right"><?php echo inr_format($total_net, 2); ?></td>
                                                </tr>
                                            </tfoot>
                                            <?php endif; ?>
                                        </table>
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
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
<script>
$(document).ready(function () {
    $('#datatable1').on('click', '.delete-batch-btn', function () {
        const id = $(this).data('id');
        if (!confirm('Delete this uploaded file and all its expense line items? This cannot be undone.')) {
            return;
        }
        const btn = $(this);
        btn.prop('disabled', true);
        $.post('expense-tracker-delete-action.php', {
            id: id,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
        .done(function (response) {
            if (response.success) {
                window.location.reload();
            } else {
                alert('Error: ' + (response.message || 'Failed to delete'));
                btn.prop('disabled', false);
            }
        })
        .fail(function () {
            alert('Error deleting upload. Please try again.');
            btn.prop('disabled', false);
        });
    });
});
</script>
</body>
</html>
