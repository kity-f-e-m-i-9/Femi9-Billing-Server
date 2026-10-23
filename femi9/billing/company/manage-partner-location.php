<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('partner_location');
error_reporting(0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Current level ────────────────────────────────────────────────────────────
$parent_id = (isset($_GET['parent_id']) && $_GET['parent_id'] !== '')
    ? (int) $_GET['parent_id']
    : null;

// ── Load layer name map: depth => layer_name ──────────────────────────────────
$stmt_layers = $db_conn->query("SELECT depth, layer_name FROM partner_location_layers ORDER BY depth");
$layer_names = [];
if ($stmt_layers) {
    while ($lr = $stmt_layers->fetch_assoc()) {
        $layer_names[(int)$lr['depth']] = $lr['layer_name'];
    }
}

// ── Breadcrumbs: walk up parent chain ────────────────────────────────────────
$root_label  = isset($layer_names[1]) ? $layer_names[1] . 's' : 'Partner Locations';
$breadcrumbs = [['id' => null, 'name' => $root_label, 'url' => 'manage-partner-location']];
$crumb_chain = [];

if ($parent_id !== null) {
    $walk_id     = $parent_id;
    $safety      = 0;
    while ($walk_id !== null && $safety++ < 20) {
        $stmt_bc = $db_conn->prepare("SELECT id, name, depth, parent_id FROM partner_location_nodes WHERE id = ?");
        $stmt_bc->bind_param("i", $walk_id);
        $stmt_bc->execute();
        $row_bc = $stmt_bc->get_result()->fetch_assoc();
        $stmt_bc->close();
        if (!$row_bc) break;
        array_unshift($crumb_chain, $row_bc);
        $walk_id = isset($row_bc['parent_id']) ? (int) $row_bc['parent_id'] : null;
        if ($row_bc['parent_id'] === null) $walk_id = null;
    }
    foreach ($crumb_chain as $crumb) {
        $breadcrumbs[] = [
            'id'  => $crumb['id'],
            'name' => $crumb['name'],
            'url' => 'manage-partner-location?parent_id=' . $crumb['id'],
        ];
    }
}

// ── Current display depth and layer name ─────────────────────────────────────
$parent_depth = 0;
if (!empty($crumb_chain)) {
    $parent_depth = (int)$crumb_chain[count($crumb_chain) - 1]['depth'];
}
$current_display_depth = $parent_id !== null ? $parent_depth + 1 : 1;
$current_layer_name    = $layer_names[$current_display_depth] ?? null;

// ── Fetch nodes at this level ─────────────────────────────────────────────────
if ($parent_id === null) {
    $stmt_nodes = $db_conn->prepare("
        SELECT p.*,
               (SELECT COUNT(*) FROM partner_location_nodes c WHERE c.parent_id = p.id) AS children_count
        FROM partner_location_nodes p
        WHERE p.parent_id IS NULL
        ORDER BY p.name
    ");
    $stmt_nodes->execute();
} else {
    $stmt_nodes = $db_conn->prepare("
        SELECT p.*,
               (SELECT COUNT(*) FROM partner_location_nodes c WHERE c.parent_id = p.id) AS children_count
        FROM partner_location_nodes p
        WHERE p.parent_id = ?
        ORDER BY p.name
    ");
    $stmt_nodes->bind_param("i", $parent_id);
    $stmt_nodes->execute();
}
$nodes_result = $stmt_nodes->get_result();
$nodes        = [];
while ($row = $nodes_result->fetch_assoc()) {
    $nodes[] = $row;
}
$stmt_nodes->close();

// ── Deposit amount per node: the sum of deposit_amount across every LEAF
// (childless) node in its subtree — a node's own value only counts toward
// its own row if that node itself has no children (e.g. a Firka, the
// deepest level, where the real assignment deposit lives). A node that DOES
// have children (e.g. a Division with Taluks under it) never counts its own
// value on its own row even if one happens to be stored there — only what's
// genuinely at the bottom of its branches, wherever that bottom is (Division
// for a Channel Partner, Firka for a Territory Partner — depth isn't
// uniform). That bottom-level value still flows up into every ancestor's
// total as usual. One recursive CTE builds the (ancestor, descendant-or-self)
// closure for this level's node ids, then sums only the leaf descendants'
// deposit_amount per ancestor.
$amountByNode = [];
$node_ids = array_map(fn($n) => (int) $n['id'], $nodes);
if (!empty($node_ids)) {
    $placeholders = implode(',', array_fill(0, count($node_ids), '?'));
    $types = str_repeat('i', count($node_ids));
    $stmt_amt = $db_conn->prepare("
        WITH RECURSIVE closure AS (
            SELECT id AS ancestor_id, id AS descendant_id FROM partner_location_nodes
            UNION ALL
            SELECT c.ancestor_id, n.id FROM closure c JOIN partner_location_nodes n ON n.parent_id = c.descendant_id
        )
        SELECT c.ancestor_id, COALESCE(SUM(p.deposit_amount), 0) AS total
        FROM closure c
        JOIN partner_location_nodes p ON p.id = c.descendant_id
        LEFT JOIN partner_location_nodes ch ON ch.parent_id = p.id
        WHERE c.ancestor_id IN ($placeholders) AND ch.id IS NULL
        GROUP BY c.ancestor_id
    ");
    $stmt_amt->bind_param($types, ...$node_ids);
    $stmt_amt->execute();
    $res_amt = $stmt_amt->get_result();
    while ($r = $res_amt->fetch_assoc()) {
        $amountByNode[(int) $r['ancestor_id']] = (float) $r['total'];
    }
    $stmt_amt->close();
}

// ── CP Amount per node: the flip side of the Amount column above — the sum
// of deposit_amount stored directly on every NON-leaf node in its subtree
// (a node that has children, e.g. a Division with Taluks under it, where a
// Channel Partner's own deposit is recorded directly rather than at the
// bottom of the branch). Every deposit_amount value in the table falls into
// exactly one of these two columns — leaf (Amount) or non-leaf (CP Amount),
// never both — so nothing is double-counted or dropped. Same closure as
// above, but keeping only descendants that themselves have at least one
// child (EXISTS, not a JOIN, so a node with several children isn't summed
// once per child).
$cpAmountByNode = [];
if (!empty($node_ids)) {
    $placeholders = implode(',', array_fill(0, count($node_ids), '?'));
    $types = str_repeat('i', count($node_ids));
    $stmt_cpamt = $db_conn->prepare("
        WITH RECURSIVE closure AS (
            SELECT id AS ancestor_id, id AS descendant_id FROM partner_location_nodes
            UNION ALL
            SELECT c.ancestor_id, n.id FROM closure c JOIN partner_location_nodes n ON n.parent_id = c.descendant_id
        )
        SELECT c.ancestor_id, COALESCE(SUM(p.deposit_amount), 0) AS total
        FROM closure c
        JOIN partner_location_nodes p ON p.id = c.descendant_id
        WHERE c.ancestor_id IN ($placeholders)
          AND EXISTS (SELECT 1 FROM partner_location_nodes ch WHERE ch.parent_id = p.id)
        GROUP BY c.ancestor_id
    ");
    $stmt_cpamt->bind_param($types, ...$node_ids);
    $stmt_cpamt->execute();
    $res_cpamt = $stmt_cpamt->get_result();
    while ($r = $res_cpamt->fetch_assoc()) {
        $cpAmountByNode[(int) $r['ancestor_id']] = (float) $r['total'];
    }
    $stmt_cpamt->close();
}

// ── Page meta ─────────────────────────────────────────────────────────────────
$page_title = $current_layer_name
    ? htmlspecialchars($current_layer_name) . 's'
    : ($parent_id !== null ? 'Sub-locations' : 'Partner Locations');
$add_label  = $current_layer_name ? 'Add ' . $current_layer_name : 'Add Location';
$add_url    = "add-partner-location" . ($parent_id !== null ? "?parent_id=$parent_id" : "");

// Whether there is a deeper layer — determines if nodes are drill-downable
$next_layer_name = $layer_names[$current_display_depth + 1] ?? null;
// Allow drill-down if next layer exists OR if any node already has children
$allow_drill_down = ($next_layer_name !== null);

// Hide Add button when layers are configured but current depth has no layer defined
// (i.e., user navigated beyond max configured depth via direct URL)
$max_configured_depth = !empty($layer_names) ? max(array_keys($layer_names)) : PHP_INT_MAX;
$can_add = empty($layer_names) || $current_display_depth <= $max_configured_depth;

$i = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?> : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style>
        .action-link { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;text-decoration:none;padding:0; }
        .action-link:hover { background:#f3f4f6;border-color:#d1d5db; }
        .action-link.delete:hover { background:#fef2f2;border-color:#fecaca; }
        .actions-group { display:inline-flex;align-items:center;gap:5px;white-space:nowrap; }
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

                    <!-- Alerts -->
                    <div class="row">
                        <div class="col">
                            <?php if (isset($_REQUEST['addesuccess'])): ?>
                                <div class="alert alert-success">Partner location added successfully.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['updatedSuccess'])): ?>
                                <div class="alert alert-info">Changes saved successfully.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['deletedDone'])): ?>
                                <div class="alert alert-warning">Partner location deleted successfully.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['hasChildren'])): ?>
                                <div class="alert alert-danger">Cannot delete: this location has sub-locations. Delete or move them first.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['alreadyexists'])): ?>
                                <div class="alert alert-danger">A partner location with this name already exists at this level.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Page header -->
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble">
                                        <tr>
                                            <td><?php echo $page_title; ?></td>
                                            <td>
                                                <?php if ($can_add): ?>
                                                <a href="<?php echo $add_url; ?>" title="<?php echo htmlspecialchars($add_label); ?>">&#10011;</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Breadcrumbs -->
                    <?php if (count($breadcrumbs) > 1): ?>
                    <div class="row mb-2">
                        <div class="col">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb" style="background:none;padding:0;margin:0;font-size:13px;">
                                    <?php foreach ($breadcrumbs as $idx => $crumb): ?>
                                        <?php if ($idx < count($breadcrumbs) - 1): ?>
                                            <li class="breadcrumb-item">
                                                <a href="<?php echo htmlspecialchars($crumb['url']); ?>">
                                                    <?php echo htmlspecialchars($crumb['name']); ?>
                                                </a>
                                            </li>
                                        <?php else: ?>
                                            <li class="breadcrumb-item active" aria-current="page">
                                                <?php echo htmlspecialchars($crumb['name']); ?>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ol>
                            </nav>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Table -->
                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div style="overflow-x:scroll;">
                                        <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th><?php echo $current_layer_name ? htmlspecialchars($current_layer_name) : 'Name'; ?></th>
                                                    <th>Code</th>
                                                    <th><?php echo $next_layer_name ? htmlspecialchars($next_layer_name) . 's' : 'Sub-locations'; ?></th>
                                                    <th>Amount</th>
                                                    <th>CP Amount</th>
                                                    <th>Status</th>

                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($nodes as $node):
                                                $enc_id = base64_encode($node['id']);
                                            ?>
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td>
                                                        <?php if ($allow_drill_down || $node['children_count'] > 0): ?>
                                                            <a href="manage-partner-location?parent_id=<?php echo (int)$node['id']; ?>"
                                                               title="View <?php echo $next_layer_name ? htmlspecialchars($next_layer_name) . 's' : 'sub-locations'; ?>">
                                                                <?php if ($node['children_count'] > 0): ?>
                                                                    <strong><?php echo htmlspecialchars($node['name']); ?></strong>
                                                                <?php else: ?>
                                                                    <?php echo htmlspecialchars($node['name']); ?>
                                                                <?php endif; ?>
                                                                &nbsp;<small class="text-muted">&#9654;</small>
                                                            </a>
                                                        <?php else: ?>
                                                            <?php echo htmlspecialchars($node['name']); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $node['code'] ? htmlspecialchars($node['code']) : '<span class="text-muted">—</span>'; ?></td>
                                                    <td>
                                                        <?php if ($node['children_count'] > 0): ?>
                                                            <a href="manage-partner-location?parent_id=<?php echo (int)$node['id']; ?>">
                                                                <?php echo (int)$node['children_count']; ?>
                                                            </a>
                                                        <?php elseif ($allow_drill_down): ?>
                                                            <a href="manage-partner-location?parent_id=<?php echo (int)$node['id']; ?>" class="text-muted">0</a>
                                                        <?php else: ?>
                                                            <span class="text-muted">0</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>&#8377;<?php echo inr_format($amountByNode[(int)$node['id']] ?? 0, 0); ?></td>
                                                    <td>&#8377;<?php echo inr_format($cpAmountByNode[(int)$node['id']] ?? 0, 0); ?></td>
                                                    <td>
                                                        <?php if ($node['is_active']): ?>
                                                            <span class="badge badge-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="actions-group">
                                                            <a href="edit-partner-location?prid=<?php echo $enc_id; ?>" class="action-link" title="Edit"><i class="material-icons-outlined" style="font-size:17px;color:#667eea;">edit</i></a>
                                                            <?php if ($node['children_count'] > 0): ?>
                                                                <button type="button" class="action-link" title="Cannot delete: has <?php echo (int)$node['children_count']; ?> sub-location(s)" disabled style="opacity:0.35;cursor:not-allowed;"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></button>
                                                            <?php else: ?>
                                                                <a href="delete-partner-location?prid=<?php echo $enc_id; ?>&return_parent=<?php echo ($parent_id !== null ? $parent_id : ''); ?>" class="action-link delete" title="Delete" onclick="return confirm('Delete \'<?php echo addslashes(htmlspecialchars($node['name'])); ?>\'? This cannot be undone.');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
                                                            <?php endif; ?>
                                                        </div>
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
<script src="../../assets/plugins/highlight/highlight.pack.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
</body>
</html>
