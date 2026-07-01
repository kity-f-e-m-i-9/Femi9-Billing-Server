<?php
include("checksession.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$enc_id   = $_GET['tpid'] ?? '';
$tp_db_id = (int)base64_decode($enc_id);
if (!$tp_db_id) { header("Location: manage-territory-partner"); exit; }

// Referral percentage options — distinct values already saved across both partner tables
$pct_opts = [];
try {
    $_pct_res = $db_conn->query("
        SELECT DISTINCT referral_percentage AS percentage FROM channel_partners WHERE referral_percentage IS NOT NULL
        UNION
        SELECT DISTINCT referral_percentage FROM territory_partners WHERE referral_percentage IS NOT NULL
        ORDER BY percentage ASC
    ");
    if ($_pct_res) while ($r = $_pct_res->fetch_assoc()) $pct_opts[] = (string)(float)$r['percentage'];
} catch (Exception $_e) { /* column not yet created — dropdown will be empty */ }

// ── Fetch partner ─────────────────────────────────────────────────────────────
$stmt = $db_conn->prepare("SELECT * FROM territory_partners WHERE id = ?");
$stmt->bind_param("i", $tp_db_id);
$stmt->execute();
$tp = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$tp) { header("Location: manage-territory-partner"); exit; }

// ── Currently assigned locations (with names for the picker) ─────────────────
$stmt_cur = $db_conn->prepare("
    SELECT n.id, n.name, COALESCE(n.target_amount, 0) AS target_amount
    FROM territory_partner_locations tpl
    JOIN partner_location_nodes n ON n.id = tpl.location_id
    WHERE tpl.territory_partner_id = ?
    ORDER BY n.name
");
$stmt_cur->bind_param("i", $tp_db_id);
$stmt_cur->execute();
$current_locations = $stmt_cur->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_cur->close();

// Referral user pre-select lookup
$referral_preselect = null;
if (!empty($tp['referral_id'])) {
    $tables = [
        ['channel_partners',  'cp_id',      'name', 'mobile',        'CP'],
        ['territory_partners','tp_id',       'name', 'mobile',        'TP'],
        ['super_stockiest',   'useridtext',  'name', 'mobile_number', 'SS'],
        ['stockiest',         'useridtext',  'name', 'mobile_number', 'Stockist'],
        ['super_distributor', 'useridtext',  'name', 'mobile_number', 'SD'],
        ['distributor',       'useridtext',  'name', 'mobile_number', 'D'],
    ];
    $esc_rid = $db_conn->real_escape_string($tp['referral_id']);
    foreach ($tables as [$tbl, $id_col, $name_col, $mob_col, $utype]) {
        $rr = $db_conn->query("SELECT `$id_col` AS uid, `$name_col` AS name, '$utype' AS utype FROM `$tbl` WHERE `$id_col` = '$esc_rid' LIMIT 1");
        if ($rr && $row = $rr->fetch_assoc()) {
            $referral_preselect = $row;
            break;
        }
    }
}

// JSON for JS preselect
$preselected_json = json_encode(array_map(function($loc) {
    return ['id' => (int)$loc['id'], 'name' => $loc['name'], 'target' => (float)$loc['target_amount']];
}, $current_locations));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Territory Partner : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style>
    .lp-wrapper { position: relative; }
    .lp-control {
        display: flex; align-items: center; min-height: 38px;
        border: 1px solid #ced4da; border-radius: 4px; background: #fff;
        padding: 2px 8px; cursor: pointer; user-select: none;
    }
    .lp-control:hover { border-color: #adb5bd; }
    .lp-control.open { border-color: #80bdff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
    .lp-value {
        flex: 1; display: flex; flex-wrap: wrap; gap: 4px;
        min-height: 28px; align-items: center; padding: 2px 0;
    }
    .lp-placeholder { color: #aaa; font-size: 13px; }
    .lp-arrow { margin-left: 6px; display: flex; align-items: center; }
    .lp-chip {
        display: inline-flex; align-items: center; gap: 4px;
        background: #e9ecef; border-radius: 3px;
        padding: 2px 6px; font-size: 12px; max-width: 180px;
    }
    .lp-chip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .lp-chip-remove {
        cursor: pointer; font-size: 15px; line-height: 1;
        color: #888; flex-shrink: 0;
    }
    .lp-chip-remove:hover { color: #dc3545; }
    .lp-panel {
        position: absolute; top: calc(100% + 3px); left: 0; right: 0;
        background: #fff; border: 1px solid #ced4da; border-radius: 4px;
        z-index: 1050; box-shadow: 0 4px 16px rgba(0,0,0,.12);
        display: flex; flex-direction: column; max-height: 280px;
    }
    .lp-back {
        display: flex; align-items: center; gap: 6px;
        padding: 8px 12px; border-bottom: 1px solid #e9ecef;
        cursor: pointer; font-size: 13px; color: #007bff;
        flex-shrink: 0;
    }
    .lp-back:hover { background: #f8f9fa; }
    .lp-body { overflow-y: auto; flex: 1; }
    .lp-row {
        display: flex; align-items: center; gap: 8px;
        padding: 9px 14px; font-size: 13px;
        border-bottom: 1px solid #f5f5f5; cursor: pointer;
    }
    .lp-row:last-child { border-bottom: none; }
    .lp-row-nav { justify-content: space-between; color: #333; padding: 0; }
    .lp-row-select { flex: 1; display: flex; align-items: center; gap: 8px; padding: 9px 14px; }
    .lp-row-select:hover { background: #f8f9fa; }
    .lp-row-into { display: flex; align-items: center; padding: 9px 10px; border-left: 1px solid #efefef; color: #aaa; flex-shrink: 0; }
    .lp-row-into:hover { background: #f0f4ff; color: #1a73e8; }
    .lp-row-selectable { color: #333; }
    .lp-row-selectable:hover { background: #f8f9fa; }
    .lp-row-selected { background: #e8f0fe; color: #1a73e8; font-weight: 500; }
    .lp-row-selected:hover { background: #d2e3fc; }
    .lp-row-taken { color: #bbb; cursor: not-allowed; }
    .lp-row-taken:hover { background: #fff; }
    .lp-row .lp-check { color: #1a73e8; flex-shrink: 0; font-size: 16px; }
    .lp-row .lp-lock { color: #ccc; flex-shrink: 0; font-size: 16px; }
    .lp-empty, .lp-loading {
        padding: 16px; text-align: center;
        font-size: 13px; color: #aaa;
    }
    .lp-tabs { display: flex; border-bottom: 1px solid #e9ecef; flex-shrink: 0; }
    .lp-tab {
        flex: 1; padding: 7px 6px; border: none; background: none;
        font-size: 12px; font-weight: 500; color: #666; cursor: pointer;
        border-bottom: 2px solid transparent; transition: all .15s;
        display: flex; align-items: center; justify-content: center; gap: 4px;
    }
    .lp-tab:hover { color: #1a73e8; background: #f8f9fa; }
    .lp-tab.lp-tab-active { color: #1a73e8; border-bottom-color: #1a73e8; background: #fff; }
    .lp-search-box { padding: 7px 10px; border-bottom: 1px solid #f0f0f0; flex-shrink: 0; display: flex; align-items: center; gap: 6px; }
    .lp-search-box input {
        flex: 1; border: 1px solid #ced4da; border-radius: 4px;
        padding: 5px 10px; font-size: 13px; outline: none; font-family: inherit;
    }
    .lp-search-box input:focus { border-color: #80bdff; box-shadow: 0 0 0 .15rem rgba(0,123,255,.2); }
    .lp-result-path { font-size: 11px; color: #999; margin-top: 1px; line-height: 1.3; }
    .lp-target-amt { margin-left: auto; font-size: 11px; color: #78909c; white-space: nowrap; padding-left: 8px; flex-shrink: 0; }
    #lpTargetSummary { margin-top: 8px; padding: 8px 12px; background: #fff8e1; border-left: 4px solid #f59e0b; border-radius: 4px; font-size: 13px; }
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
                                            <td>Edit Territory Partner</td>
                                            <td><a href="manage-territory-partner" title="Manage Territory Partners">&#9776;</a></td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <form action="territory-partner-action" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="update-territory-partner">
                        <input type="hidden" name="tp_db_id" value="<?php echo $enc_id; ?>">

                    <div class="row">

                        <!-- Personal Details -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">

                                    <?php if (isset($_REQUEST['mobiletaken'])): ?>
                                        <div class="alert alert-danger">This mobile number is already registered to another partner.</div>
                                    <?php endif; ?>
                                    <?php if (isset($_REQUEST['error'])): ?>
                                        <div class="alert alert-danger">An error occurred while saving. Please try again.</div>
                                    <?php endif; ?>

                                    <p class="text-muted mb-3" style="font-size:13px;">
                                        <i class="material-icons-two-tone" style="vertical-align:middle;font-size:16px;">map</i>
                                        ID: <strong><?php echo htmlspecialchars($tp['tp_id']); ?></strong>
                                    </p>

                                    <?php include("validate-scripts.php"); ?>

                                    <div class="example-container">
                                        <div class="example-content">

                                            <div class="mb-3">
                                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                                <input type="text" name="tp_name" class="form-control" maxlength="100" required
                                                       value="<?php echo htmlspecialchars($tp['name']); ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                                <input type="text" name="tp_company_name" class="form-control" maxlength="255" required
                                                       value="<?php echo htmlspecialchars($tp['company_name'] ?? ''); ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Referral ID <span class="text-muted" style="font-size:12px;">(optional)</span></label>
                                                <select name="tp_referral_id" id="referralUserSelect" class="form-control" style="width:100%;">
                                                    <option value=""></option>
                                                    <?php if ($referral_preselect): ?>
                                                        <option value="<?php echo htmlspecialchars($referral_preselect['uid']); ?>"
                                                                data-user-type="<?php echo htmlspecialchars($referral_preselect['utype']); ?>"
                                                                selected>
                                                            <?php echo htmlspecialchars($referral_preselect['name']); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                </select>
                                                <input type="hidden" name="tp_referral_type" id="referralTypeHidden"
                                                       value="<?php echo htmlspecialchars($tp['referral_type'] ?? ''); ?>">
                                                <small class="text-muted">Search by name, ID or mobile across CP, TP, SS, Stockist, SD, D.</small>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Referral % <span class="text-muted" style="font-size:12px;">(optional)</span></label>
                                                <select name="tp_referral_percentage" id="referralPctSelect" class="form-control" style="width:100%;">
                                                    <option value=""></option>
                                                    <?php
                                                    $saved_pct = isset($tp['referral_percentage']) && $tp['referral_percentage'] !== null
                                                        ? (string)(float)$tp['referral_percentage'] : '';
                                                    foreach ($pct_opts as $p):
                                                    ?>
                                                        <option value="<?php echo $p; ?>" <?php echo ($saved_pct !== '' && $p === $saved_pct) ? 'selected' : ''; ?>><?php echo $p; ?>%</option>
                                                    <?php endforeach; ?>
                                                    <?php if ($saved_pct !== '' && !in_array($saved_pct, $pct_opts)): ?>
                                                        <option value="<?php echo htmlspecialchars($saved_pct); ?>" selected><?php echo htmlspecialchars($saved_pct); ?>%</option>
                                                    <?php endif; ?>
                                                </select>
                                                <small class="text-muted">Select a preset or type any value.</small>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Mobile <span class="text-danger">*</span></label>
                                                <input type="text" name="tp_mobile" class="form-control" maxlength="15"
                                                       pattern="[0-9]{10,15}" required
                                                       value="<?php echo htmlspecialchars($tp['mobile']); ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Email <span class="text-muted" style="font-size:12px;">(optional)</span></label>
                                                <input type="email" name="tp_email" class="form-control" maxlength="100"
                                                       value="<?php echo htmlspecialchars($tp['email'] ?? ''); ?>">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">GST Number <span class="text-muted" style="font-size:12px;">(optional)</span></label>
                                                <input type="text" name="tp_gstin" class="form-control" maxlength="20"
                                                       value="<?php echo htmlspecialchars($tp['gstin'] ?? ''); ?>">
                                            </div>

                                            <!-- Billing Address -->
                                            <div class="mb-1">
                                                <label class="form-label fw-semibold">Billing Address <span class="text-danger">*</span></label>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-12">
                                                    <input type="text" name="tp_branch_line1" class="form-control" maxlength="255" placeholder="Line 1 (Building / Street) *" required
                                                           value="<?php echo htmlspecialchars($tp['branch_line1'] ?? ''); ?>">
                                                </div>
                                                <div class="col-12">
                                                    <input type="text" name="tp_branch_line2" class="form-control" maxlength="255" placeholder="Line 2 (Area / Landmark)"
                                                           value="<?php echo htmlspecialchars($tp['branch_line2'] ?? ''); ?>">
                                                </div>
                                                <div class="col-6">
                                                    <input type="text" name="tp_branch_city" class="form-control" maxlength="100" placeholder="City"
                                                           value="<?php echo htmlspecialchars($tp['branch_city'] ?? ''); ?>">
                                                </div>
                                                <div class="col-6">
                                                    <input type="text" name="tp_branch_district" class="form-control" maxlength="100" placeholder="District"
                                                           value="<?php echo htmlspecialchars($tp['branch_district'] ?? ''); ?>">
                                                </div>
                                                <div class="col-6">
                                                    <input type="text" name="tp_branch_state" class="form-control" maxlength="100" placeholder="State"
                                                           value="<?php echo htmlspecialchars($tp['branch_state'] ?? ''); ?>">
                                                </div>
                                                <div class="col-6">
                                                    <input type="text" name="tp_branch_country" class="form-control" maxlength="100" placeholder="Country"
                                                           value="<?php echo htmlspecialchars($tp['branch_country'] ?? ''); ?>">
                                                </div>
                                                <div class="col-6">
                                                    <input type="text" name="tp_branch_pincode" id="tp_branch_pincode" class="form-control" maxlength="20" placeholder="Pincode"
                                                           value="<?php echo htmlspecialchars($tp['branch_pincode'] ?? ''); ?>">
                                                </div>
                                            </div>

                                            <!-- Delivery Address -->
                                            <div class="mb-1 d-flex align-items-center gap-2">
                                                <label class="form-label fw-semibold mb-0">Delivery Address <span class="text-danger">*</span></label>
                                                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="copyBranchToDelivery" style="font-size:11px;padding:2px 8px;">
                                                    <i class="material-icons" style="font-size:13px;vertical-align:middle;">content_copy</i> Same as Billing
                                                </button>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-12">
                                                    <input type="text" name="tp_delivery_line1" id="tp_delivery_line1" class="form-control" maxlength="255" placeholder="Line 1 (Building / Street) *" required
                                                           value="<?php echo htmlspecialchars($tp['delivery_line1'] ?? ''); ?>">
                                                </div>
                                                <div class="col-12">
                                                    <input type="text" name="tp_delivery_line2" id="tp_delivery_line2" class="form-control" maxlength="255" placeholder="Line 2 (Area / Landmark)"
                                                           value="<?php echo htmlspecialchars($tp['delivery_line2'] ?? ''); ?>">
                                                </div>
                                                <div class="col-6">
                                                    <input type="text" name="tp_delivery_city" id="tp_delivery_city" class="form-control" maxlength="100" placeholder="City"
                                                           value="<?php echo htmlspecialchars($tp['delivery_city'] ?? ''); ?>">
                                                </div>
                                                <div class="col-6">
                                                    <input type="text" name="tp_delivery_district" id="tp_delivery_district" class="form-control" maxlength="100" placeholder="District"
                                                           value="<?php echo htmlspecialchars($tp['delivery_district'] ?? ''); ?>">
                                                </div>
                                                <div class="col-6">
                                                    <input type="text" name="tp_delivery_state" id="tp_delivery_state" class="form-control" maxlength="100" placeholder="State"
                                                           value="<?php echo htmlspecialchars($tp['delivery_state'] ?? ''); ?>">
                                                </div>
                                                <div class="col-6">
                                                    <input type="text" name="tp_delivery_country" id="tp_delivery_country" class="form-control" maxlength="100" placeholder="Country"
                                                           value="<?php echo htmlspecialchars($tp['delivery_country'] ?? ''); ?>">
                                                </div>
                                                <div class="col-6">
                                                    <input type="text" name="tp_delivery_pincode" id="tp_delivery_pincode" class="form-control" maxlength="20" placeholder="Pincode"
                                                           value="<?php echo htmlspecialchars($tp['delivery_pincode'] ?? ''); ?>">
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Photo <span class="text-muted" style="font-size:12px;">(leave blank to keep current)</span></label>
                                                <?php if ($tp['photo']): ?>
                                                    <div class="mb-2">
                                                        <img src="tp_photo/<?php echo htmlspecialchars($tp['photo']); ?>"
                                                             style="width:52px;height:52px;border-radius:6px;object-fit:cover;border:1px solid #e0e0e0;">
                                                    </div>
                                                <?php endif; ?>
                                                <input type="file" name="tp_photo" class="form-control" accept="image/*">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select name="tp_active" class="form-control">
                                                    <option value="1" <?php echo $tp['is_active'] ? 'selected' : ''; ?>>Active</option>
                                                    <option value="0" <?php echo !$tp['is_active'] ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Location Picker -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">

                                    <p class="text-muted mb-3" style="font-size:13px;">
                                        <i class="material-icons-two-tone" style="vertical-align:middle;font-size:16px;">place</i>
                                        Select the locations this partner will manage.
                                    </p>

                                    <div class="mb-3">
                                        <label class="form-label">Assigned Locations</label>

                                        <div class="lp-wrapper" id="locationPickerWrapper">
                                            <div class="lp-control" id="locationPickerControl">
                                                <div class="lp-value" id="lpValue">
                                                    <span class="lp-placeholder">Select locations&hellip;</span>
                                                </div>
                                                <div class="lp-arrow">
                                                    <i class="material-icons" style="font-size:18px;color:#999;">arrow_drop_down</i>
                                                </div>
                                            </div>
                                            <div class="lp-panel" id="locationPanel" style="display:none;">
                                                <div class="lp-search-box">
                                                    <i class="material-icons" style="font-size:18px;color:#aaa;">search</i>
                                                    <input type="text" id="lpSearchInput" placeholder="Search locations&hellip;" autocomplete="off">
                                                </div>
                                                <div class="lp-body" id="lpBody">
                                                    <div class="lp-loading">Loading&hellip;</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="lpHiddenInputs"></div>
                                        <div id="lpTargetSummary" style="display:none;">
                                            <span style="color:#92400e;">Total Target for selected locations:</span>
                                            <strong id="lpTargetTotal" style="color:#b45309; margin-left:6px;">₹0</strong>
                                        </div>
                                        <small class="text-muted">Already-assigned locations are shown locked and cannot be selected.</small>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div><!-- /.row -->

                    <!-- Submit row -->
                    <div class="row mt-1 mb-3">
                        <div class="col">
                            <button type="submit" name="update-territory-partner" class="btn btn-primary">
                                <i class="material-icons">save</i> Save Changes
                            </button>
                            <a href="manage-territory-partner" class="btn btn-secondary ms-2">Cancel</a>
                        </div>
                    </div>

                    </form>

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
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script>
// ── Referral user search (Select2 AJAX) ───────────────────────────────────────
$(document).ready(function () {
    var typeBadge = {
        'CP':       '<span style="background:#2563eb;color:#fff;font-size:10px;padding:1px 5px;border-radius:3px;margin-right:5px;">CP</span>',
        'TP':       '<span style="background:#d97706;color:#fff;font-size:10px;padding:1px 5px;border-radius:3px;margin-right:5px;">TP</span>',
        'SS':       '<span style="background:#059669;color:#fff;font-size:10px;padding:1px 5px;border-radius:3px;margin-right:5px;">SS</span>',
        'Stockist': '<span style="background:#0891b2;color:#fff;font-size:10px;padding:1px 5px;border-radius:3px;margin-right:5px;">Stockist</span>',
        'SD':       '<span style="background:#7c3aed;color:#fff;font-size:10px;padding:1px 5px;border-radius:3px;margin-right:5px;">SD</span>',
        'D':        '<span style="background:#dc2626;color:#fff;font-size:10px;padding:1px 5px;border-radius:3px;margin-right:5px;">D</span>',
    };

    function formatResult(item) {
        if (item.loading) return $('<span>Searching…</span>');
        var badge = typeBadge[item.user_type] || '';
        return $('<div style="line-height:1.3;padding:2px 0;">' +
            badge + '<strong>' + $('<span>').text(item.text).html() + '</strong>' +
            ' <small style="color:#888;">(' + $('<span>').text(item.id).html() + ')</small></div>');
    }

    function formatSelection(item) {
        if (!item.id) return item.text;
        var utype = item.user_type || $(item.element).data('user-type') || '';
        var badge = typeBadge[utype] || '';
        return $('<span>' + badge + $('<span>').text(item.text).html() +
            ' <small style="color:#888;">(' + $('<span>').text(item.id).html() + ')</small></span>');
    }

    $('#referralUserSelect').select2({
        placeholder: 'Search by name, ID or mobile…',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: 'search-referral-user.php',
            dataType: 'json',
            delay: 300,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data.results || [] }; },
            cache: true
        },
        templateResult: formatResult,
        templateSelection: formatSelection
    });
    $('#referralUserSelect').on('select2:select', function (e) {
        $('#referralTypeHidden').val(e.params.data.user_type || '');
    });
    $('#referralUserSelect').on('select2:clear', function () {
        $('#referralTypeHidden').val('');
    });

    $('#referralPctSelect').select2({
        placeholder: 'Select or type a percentage…',
        allowClear: true,
        tags: true,
        createTag: function (params) {
            var val = $.trim(params.term);
            if (val === '' || isNaN(val) || +val <= 0 || +val > 100) return null;
            return { id: val, text: val + '%', newTag: true };
        },
        templateResult: function (item) {
            return item.newTag
                ? $('<span style="color:#2563eb;">+ Add <strong>' + item.text + '</strong></span>')
                : item.text;
        }
    });
});
</script>
<script>
(function ($) {
    var allNodes = [];
    var selected = <?php echo $preselected_json; ?>;
    var open     = false;
    var loaded   = false;
    var EXCLUDE_TP = <?php echo (int)$tp_db_id; ?>;

    renderChips();
    updateHiddenInputs();
    updateTargetSummary();

    function escHtml(str) { return $('<div>').text(str).html(); }

    function fmtAmount(v) {
        if (!v || v <= 0) return '';
        return '₹' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 });
    }

    function isSelected(id) {
        for (var i = 0; i < selected.length; i++) { if (selected[i].id === id) return true; }
        return false;
    }

    function toggleSelect(node) {
        var idx = -1;
        for (var i = 0; i < selected.length; i++) { if (selected[i].id === node.id) { idx = i; break; } }
        if (idx >= 0) { selected.splice(idx, 1); } else {
            selected.push({ id: node.id, name: node.name, target: parseFloat(node.target_amount) || 0 });
        }
        renderChips();
        renderList($.trim($('#lpSearchInput').val()));
        updateHiddenInputs();
        updateTargetSummary();
    }

    function renderList(q) {
        var $body = $('#lpBody').empty();
        var nodes = allNodes;
        if (q) {
            var ql = q.toLowerCase();
            nodes = nodes.filter(function (n) { return n.name.toLowerCase().indexOf(ql) >= 0; });
        }
        if (nodes.length === 0) {
            $body.html('<div class="lp-empty">' + (q ? 'No results for "' + escHtml(q) + '".' : 'No locations available.') + '</div>');
            return;
        }
        var curLayer = null;
        $.each(nodes, function (_, node) {
            if (node.layer_name && node.layer_name !== curLayer) {
                curLayer = node.layer_name;
                $body.append('<div style="padding:5px 14px 4px;font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:0.8px;border-bottom:1px solid #f0f0f0;">' + escHtml(curLayer) + '</div>');
            }
            var $row = $('<div class="lp-row"></div>');
            if (node.is_taken) {
                $row.addClass('lp-row-taken');
                $row.append('<i class="material-icons lp-lock" style="font-size:16px;">lock</i>');
                $row.append($('<span>').text(node.name));
            } else if (isSelected(node.id)) {
                $row.addClass('lp-row-selectable lp-row-selected');
                $row.append('<i class="material-icons lp-check" style="font-size:16px;">check</i>');
                $row.append($('<span>').text(node.name));
                var _ta = parseFloat(node.target_amount) || 0;
                if (_ta > 0) $row.append($('<span class="lp-target-amt">').text(fmtAmount(_ta)));
                $row.on('click', function () { toggleSelect(node); });
            } else {
                $row.addClass('lp-row-selectable');
                $row.append($('<span>').text(node.name));
                var _ta = parseFloat(node.target_amount) || 0;
                if (_ta > 0) $row.append($('<span class="lp-target-amt">').text(fmtAmount(_ta)));
                $row.on('click', function () { toggleSelect(node); });
            }
            $body.append($row);
        });
    }

    function renderChips() {
        var $val = $('#lpValue').empty();
        if (selected.length === 0) { $val.html('<span class="lp-placeholder">Select locations&hellip;</span>'); return; }
        $.each(selected, function (_, s) {
            var $chip = $('<span class="lp-chip"></span>');
            $chip.append($('<span>').text(s.name));
            var $x = $('<span class="lp-chip-remove">&times;</span>');
            $x.on('click', function (e) {
                e.stopPropagation();
                selected = selected.filter(function (r) { return r.id !== s.id; });
                renderChips();
                renderList($.trim($('#lpSearchInput').val()));
                updateHiddenInputs();
                updateTargetSummary();
            });
            $chip.append($x);
            $val.append($chip);
        });
    }

    function updateHiddenInputs() {
        var $c = $('#lpHiddenInputs').empty();
        $.each(selected, function (_, s) { $c.append('<input type="hidden" name="location_ids[]" value="' + s.id + '">'); });
    }

    function updateTargetSummary() {
        var total = 0;
        $.each(selected, function (_, s) { total += (s.target || 0); });
        if (total > 0) { $('#lpTargetTotal').text(fmtAmount(total)); $('#lpTargetSummary').show(); }
        else { $('#lpTargetSummary').hide(); }
    }

    function loadNodes() {
        $('#lpBody').html('<div class="lp-loading">Loading&hellip;</div>');
        $.getJSON('get-tp-flat-nodes.php?exclude_tp_id=' + EXCLUDE_TP, function (nodes) {
            allNodes = nodes;
            loaded = true;
            renderList($.trim($('#lpSearchInput').val()));
        }).fail(function () {
            $('#lpBody').html('<div class="lp-empty">Failed to load. Please try again.</div>');
        });
    }

    $('#locationPanel').on('click', function (e) { e.stopPropagation(); });

    $('#locationPickerControl').on('click', function (e) {
        e.stopPropagation();
        if (!open) {
            open = true;
            $('#locationPickerControl').addClass('open');
            $('#locationPanel').show();
            if (!loaded) loadNodes();
            setTimeout(function () { $('#lpSearchInput').focus(); }, 50);
        } else {
            open = false;
            $('#locationPickerControl').removeClass('open');
            $('#locationPanel').hide();
        }
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#locationPickerWrapper').length && open) {
            open = false;
            $('#locationPickerControl').removeClass('open');
            $('#locationPanel').hide();
        }
    });

    $('#lpSearchInput').on('input', function (e) {
        e.stopPropagation();
        renderList($.trim($(this).val()));
    }).on('click', function (e) { e.stopPropagation(); });

}(jQuery));

document.getElementById('copyBranchToDelivery').addEventListener('click', function () {
    var map = [
        ['tp_branch_line1',    'tp_delivery_line1'],
        ['tp_branch_line2',    'tp_delivery_line2'],
        ['tp_branch_city',     'tp_delivery_city'],
        ['tp_branch_district', 'tp_delivery_district'],
        ['tp_branch_state',    'tp_delivery_state'],
        ['tp_branch_country',  'tp_delivery_country'],
        ['tp_branch_pincode',  'tp_delivery_pincode'],
    ];
    map.forEach(function (pair) {
        var src = document.querySelector('[name="' + pair[0] + '"]');
        var dst = document.getElementById(pair[1]);
        if (src && dst) dst.value = src.value;
    });
});
</script>
</body>
</html>
