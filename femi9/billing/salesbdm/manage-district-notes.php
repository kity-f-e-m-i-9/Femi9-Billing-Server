<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
require_once("include/DistrictNotes.php");
error_reporting(0);

ensureDistrictNotesTable($db_conn);

$districts = getBdmAssignedDistrictNames($db_conn, (int)$salesBdmID);

$filter_from     = $_GET['from_date'] ?? date('Y-m-01');
$filter_to       = $_GET['to_date']   ?? date('Y-m-d');
$filter_district = $_GET['district']  ?? '';
if ($filter_district !== '' && !in_array($filter_district, $districts, true)) { $filter_district = ''; }
$filter_priority = $_GET['priority']  ?? '';
if (!in_array($filter_priority, ['high', 'priority', 'normal', ''], true)) { $filter_priority = ''; }

$where  = ["bdm_id = ?", "DATE(created_at) BETWEEN ? AND ?"];
$params = [$salesBdmID, $filter_from, $filter_to];
$types  = "iss";
if ($filter_district !== '') {
    $where[]  = "district = ?";
    $params[] = $filter_district;
    $types   .= "s";
}
if ($filter_priority !== '') {
    $where[]  = "priority = ?";
    $params[] = $filter_priority;
    $types   .= "s";
}

$sql = "SELECT * FROM salesbdm_district_notes WHERE " . implode(" AND ", $where) . " ORDER BY created_at DESC";
$stmt = $db_conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage District Notes : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .mis-filter { background:#fff; border:1px solid rgba(11,11,11,0.10); border-radius:10px; padding:14px 18px; margin-bottom:20px; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:8px 11px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:8px 11px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .dn-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap; }
        .dn-thumb { width:44px; height:44px; object-fit:cover; border-radius:6px; border:1px solid #e5e7eb; cursor:pointer; }
        .dn-issue { max-width:340px; white-space:normal; }
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
                                    <table class="headertble">
                                        <tr>
                                            <td>Manage District Notes</td>
                                            <td><a href="add-district-note.php" class="btn btn-primary btn-sm"><i class="material-icons-outlined" style="font-size:16px;vertical-align:-3px;">add</i> Add Note</a></td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <div class="mis-filter">
                        <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">From</label>
                                <input type="date" name="from_date" value="<?php echo htmlspecialchars($filter_from); ?>" class="form-control form-control-sm">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">To</label>
                                <input type="date" name="to_date" value="<?php echo htmlspecialchars($filter_to); ?>" class="form-control form-control-sm">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">District</label>
                                <select name="district" class="form-control form-control-sm">
                                    <option value="">All Districts</option>
                                    <?php foreach ($districts as $d): ?>
                                        <option value="<?php echo htmlspecialchars($d, ENT_QUOTES); ?>" <?php echo $filter_district === $d ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Priority</label>
                                <select name="priority" class="form-control form-control-sm">
                                    <option value="">All</option>
                                    <option value="high" <?php echo $filter_priority === 'high' ? 'selected' : ''; ?>>High Priority</option>
                                    <option value="priority" <?php echo $filter_priority === 'priority' ? 'selected' : ''; ?>>Priority</option>
                                    <option value="normal" <?php echo $filter_priority === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                </select>
                            </div>
                            <div><button type="submit" class="btn btn-primary btn-sm">Apply</button></div>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-body" style="overflow-x:auto;">
                            <table class="mt">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>District</th>
                                        <th>Issue</th>
                                        <th>Priority</th>
                                        <th>Photo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($notes)): ?>
                                    <tr><td colspan="5" class="text-center text-muted" style="padding:24px;">No notes found for this filter.</td></tr>
                                <?php else: foreach ($notes as $n):
                                    [$bg, $fg] = districtNotePriorityColors($n['priority']);
                                ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($n['created_at'])); ?><br><span class="text-muted" style="font-size:11px;"><?php echo date('h:i A', strtotime($n['created_at'])); ?></span></td>
                                        <td><?php echo htmlspecialchars($n['district']); ?></td>
                                        <td class="dn-issue"><?php echo nl2br(htmlspecialchars($n['issue_text'])); ?></td>
                                        <td><span class="dn-badge" style="background:<?php echo $bg; ?>;color:<?php echo $fg; ?>;"><?php echo htmlspecialchars(districtNotePriorityLabel($n['priority'])); ?></span></td>
                                        <td>
                                            <?php if (!empty($n['photo_path'])): ?>
                                                <a href="district_note_photos/<?php echo htmlspecialchars($n['photo_path'], ENT_QUOTES); ?>" target="_blank" rel="noopener">
                                                    <img class="dn-thumb" src="district_note_photos/<?php echo htmlspecialchars($n['photo_path'], ENT_QUOTES); ?>" alt="Photo">
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:11px;">&mdash;</span>
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
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
