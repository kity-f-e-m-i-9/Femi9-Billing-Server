<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);

$db_conn->query("
    CREATE TABLE IF NOT EXISTS ms_awareness_campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ms_id INT NOT NULL,
        campaign_date DATE NOT NULL,
        location VARCHAR(150) NOT NULL,
        topic VARCHAR(200) NOT NULL,
        people_reached INT NOT NULL DEFAULT 0,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ms_date (ms_id, campaign_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$msId = (int)$markeingSTFID;

// ASM/SM sees their whole team's entries (read-only for anyone else's row —
// the delete button only ever posts for the logged-in person's own ms_id,
// enforced again server-side in awareness-campaign-action.php); a DM sees
// only their own. Same "every District-level staffer anywhere below me"
// walk as marketing/dashboard.php's own $myDms.
$_staffRows = $db_conn->query("
    SELECT ms.id, ms.ms_name, ms.manager_id
    FROM marketing_staff ms
    WHERE ms.deleted_at IS NULL
")->fetch_all(MYSQLI_ASSOC);
$_byManager = [];
foreach ($_staffRows as $row) {
    $_byManager[$row['manager_id'] ? (int)$row['manager_id'] : 0][] = $row;
}
$teamMsIds = [$msId];
$msNameById = [$msId => $result_LoGuserDtails['ms_name'] ?? 'You'];
$_queue = [$msId];
while (!empty($_queue)) {
    $_cur = array_shift($_queue);
    foreach ($_byManager[$_cur] ?? [] as $_child) {
        $teamMsIds[] = (int)$_child['id'];
        $msNameById[(int)$_child['id']] = $_child['ms_name'];
        $_queue[] = (int)$_child['id'];
    }
}

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-t');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to   = date('Y-m-t'); }

$idList = implode(',', array_map('intval', $teamMsIds));
$stmt = $db_conn->prepare(
    "SELECT * FROM ms_awareness_campaigns
     WHERE ms_id IN ($idList) AND campaign_date BETWEEN ? AND ?
     ORDER BY campaign_date DESC, id DESC"
);
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function fv($val) { return htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Awareness Campaign : <?php echo fv($business_name ?? 'Femi9'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <style>
        .ac-card { border:none; border-radius:14px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
        .ac-table-wrap { border-radius:12px; border:1px solid #e5e7eb; overflow-x:auto; -webkit-overflow-scrolling:touch; }
        .ac-table { margin-bottom:0; font-size:13px; }
        .ac-table thead th {
            background:linear-gradient(180deg,#4f6ef7,#3d5ae0); color:#fff; font-weight:600;
            white-space:nowrap; border:none; padding:12px 14px; text-align:center;
        }
        .ac-table tbody td { padding:10px 14px; vertical-align:middle; border-color:#eef0f4; }
        .ac-table tbody tr:nth-child(even) { background:#f8f9fc; }
        .ac-filter-row .form-control, .ac-filter-row .btn { height:40px; }
        .ac-filter-row .btn { display:inline-flex; align-items:center; justify-content:center; line-height:1; }
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

                    <h1 style="font-size:20px;font-weight:600;color:#1f2937;margin-bottom:16px;">Awareness Campaign</h1>

                    <?php if (isset($_GET['added'])): ?>
                    <div class="alert alert-success">Campaign entry added.</div>
                    <?php elseif (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">Entry removed.</div>
                    <?php elseif (isset($_GET['err'])): ?>
                    <div class="alert alert-danger">Location and Topic are required.</div>
                    <?php endif; ?>

                    <div class="card ac-card mb-3">
                        <div class="card-body">
                            <h6 class="mb-3">Log a Campaign</h6>
                            <form method="post" action="awareness-campaign-action.php" class="row g-3">
                                <input type="hidden" name="action" value="add">
                                <div class="col-md-2">
                                    <label class="form-label">Date</label>
                                    <input type="date" name="campaign_date" class="form-control" value="<?php echo fv(date('Y-m-d')); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Location</label>
                                    <input type="text" name="location" class="form-control" placeholder="Village / Area name" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Topic</label>
                                    <input type="text" name="topic" class="form-control" placeholder="What was covered" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">People Reached</label>
                                    <input type="number" name="people_reached" class="form-control" min="0" value="0">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">Add Entry</button>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Any other details"></textarea>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card ac-card mb-3">
                        <div class="card-body">
                            <form method="get" class="row g-3 align-items-end ac-filter-row">
                                <div class="col-md-3">
                                    <label class="form-label">From</label>
                                    <input type="date" name="from" class="form-control" value="<?php echo fv($from); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">To</label>
                                    <input type="date" name="to" class="form-control" value="<?php echo fv($to); ?>">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card ac-card">
                        <div class="card-body">
                            <div class="ac-table-wrap">
                            <table class="table ac-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th><th>DM</th><th>Location</th><th>Topic</th><th>People Reached</th><th>Notes</th><th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No campaigns logged in this range.</td></tr>
                                    <?php else: foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?php echo fv(date('d-M-Y', strtotime($r['campaign_date']))); ?></td>
                                        <td><?php echo fv($msNameById[(int)$r['ms_id']] ?? ('#' . $r['ms_id'])); ?></td>
                                        <td><?php echo fv($r['location']); ?></td>
                                        <td><?php echo fv($r['topic']); ?></td>
                                        <td style="text-align:center;"><?php echo (int)$r['people_reached']; ?></td>
                                        <td><?php echo fv($r['notes']); ?></td>
                                        <td>
                                            <?php if ((int)$r['ms_id'] === $msId): ?>
                                            <form method="post" action="awareness-campaign-action.php" onsubmit="return confirm('Remove this entry?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
