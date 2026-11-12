<?php
// Marketing-side twin of company/manage-daily-assign-district-tps.php —
// lets an ASM/DM assign Territory Partners to Firkas within their own
// subordinates' territory (Select Staff is scoped to the subtree via
// get-tp-assign-staff.php; the actual save is re-checked server-side in
// tp-assign-firka-action.php regardless of what the client sends).
include("checksession.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily Assign District TPs : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <style>
    .dat-wrap { display:flex; flex-direction:column; gap:16px; }
    .dat-row { display:flex; flex-wrap:wrap; gap:16px; }
    .dat-field { flex:1; min-width:200px; max-width:280px; }
    .dat-field label { font-weight:600; font-size:13px; color:#475569; margin-bottom:4px; display:block; }
    .dat-empty { color:#aaa; font-size:13px; padding:10px 0; }
    .dat-loading { color:#aaa; font-size:13px; padding:10px 0; }
    .dat-firka-panel { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; max-width:480px; margin-top:6px; }
    .dat-vacant { color:#b45309; font-weight:600; }
    .dat-filled { color:#166534; font-weight:600; }
    .select2-container { width: 100% !important; }
    .select2-container--default .select2-selection--single { border-radius:6px; border:1px solid #d1d5db; height:auto; padding:7px 12px; font-size:0.95rem; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:1.5; padding:0; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height:100%; top:0; right:6px; }
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
                                <h1>Daily Assign District TPs</h1>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body dat-wrap">

                                    <div class="dat-row">
                                        <div class="dat-field">
                                            <label>Assignment Date</label>
                                            <input type="date" class="form-control" id="selAssignDate">
                                        </div>
                                        <div class="dat-field">
                                            <label>Select Staff (your team)</label>
                                            <select class="form-control" id="selStaff">
                                                <option value="">-- Select Staff --</option>
                                            </select>
                                        </div>
                                        <div class="dat-field">
                                            <label>Select District</label>
                                            <select class="form-control" id="selDistrict" disabled>
                                                <option value="">-- Select District --</option>
                                            </select>
                                        </div>
                                        <div class="dat-field">
                                            <label>Select Division</label>
                                            <select class="form-control" id="selDivision" disabled>
                                                <option value="">-- Select Division --</option>
                                            </select>
                                        </div>
                                        <div class="dat-field">
                                            <label>Select Firka</label>
                                            <select class="form-control" id="selFirka" disabled>
                                                <option value="">-- Select Firka --</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div id="datFirkaDetail"></div>

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
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function ($) {
    function escHtml(str) { return $('<div>').text(str).html(); }

    function todayStr() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    $('#selAssignDate').val(todayStr());

    // Switching the date reloads whichever Firka is currently open, so its
    // "Current" status/dropdown reflects THAT date's roster, not today's.
    $('#selAssignDate').on('change', function () {
        var firkaId = $('#selFirka').val();
        var firkaName = $('#selFirka').find('option:selected').text();
        if (firkaId) { loadFirkaDetail(firkaId, firkaName); }
    });

    $('#selStaff, #selDistrict, #selDivision, #selFirka').select2({ width: '100%' });

    function fillSelect($sel, nodes, placeholder) {
        $sel.empty().append($('<option>').val('').text(placeholder));
        $.each(nodes, function (_, n) {
            $sel.append($('<option>').val(n.id).text(n.name));
        });
        $sel.trigger('change.select2');
    }

    function resetFrom(level) {
        if (level === 'all') {
            $('#selDistrict').prop('disabled', true).html('<option value="">-- Select District --</option>').trigger('change.select2');
        }
        if (level === 'district' || level === 'all') {
            $('#selDivision').prop('disabled', true).html('<option value="">-- Select Division --</option>').trigger('change.select2');
        }
        if (level === 'firka' || level === 'division' || level === 'district' || level === 'all') {
            $('#selFirka').prop('disabled', true).html('<option value="">-- Select Firka --</option>').trigger('change.select2');
        }
        $('#datFirkaDetail').empty();
    }

    // ---- Staff dropdown (scoped to this person's own team) ----
    $.getJSON('get-tp-assign-staff.php', function (resp) {
        if (!resp.length) {
            $('#selStaff').html('<option value="">-- No team members --</option>');
            return;
        }
        fillSelect($('#selStaff'), resp, '-- Select Staff --');
    });

    $('#selStaff').on('change', function () {
        resetFrom('all');
        var msId = $(this).val();
        if (!msId) return;
        $('#selDistrict').prop('disabled', true).html('<option value="">Loading…</option>').trigger('change.select2');
        $.getJSON('get-tp-assign-districts.php?ms_id=' + msId, function (nodes) {
            if (!nodes.length) {
                $('#selDistrict').html('<option value="">-- No districts assigned --</option>').trigger('change.select2');
                return;
            }
            fillSelect($('#selDistrict'), nodes, '-- Select District --');
            $('#selDistrict').prop('disabled', false);
        });
    });

    $('#selDistrict').on('change', function () {
        resetFrom('district');
        var distId = $(this).val();
        if (!distId) return;
        $('#selDivision').prop('disabled', true).html('<option value="">Loading…</option>').trigger('change.select2');
        $.getJSON('get-tp-assign-divisions.php?district_id=' + distId, function (nodes) {
            if (!nodes.length) {
                $('#selDivision').html('<option value="">-- No divisions found --</option>').trigger('change.select2');
                return;
            }
            fillSelect($('#selDivision'), nodes, '-- Select Division --');
            $('#selDivision').prop('disabled', false);
        });
    });

    $('#selDivision').on('change', function () {
        resetFrom('division');
        var divId = $(this).val();
        if (!divId) return;
        $('#selFirka').prop('disabled', true).html('<option value="">Loading…</option>').trigger('change.select2');
        $.getJSON('get-tp-assign-firkas.php?division_id=' + divId, function (nodes) {
            if (!nodes.length) {
                $('#selFirka').html('<option value="">-- No firkas found --</option>').trigger('change.select2');
                return;
            }
            fillSelect($('#selFirka'), nodes, '-- Select Firka --');
            $('#selFirka').prop('disabled', false);
        });
    });

    $('#selFirka').on('change', function () {
        $('#datFirkaDetail').empty();
        var firkaId = $(this).val();
        var firkaName = $(this).find('option:selected').text();
        if (!firkaId) return;
        loadFirkaDetail(firkaId, firkaName);
    });

    // ---- TP for selected Firka ----
    function loadFirkaDetail(firkaId, firkaName) {
        var $panel = $('#datFirkaDetail');
        $panel.html('<div class="dat-loading">Loading&hellip;</div>');
        $.getJSON('get-tp-assign-tp-for-firka.php?firka_id=' + firkaId + '&assign_date=' + $('#selAssignDate').val(), function (resp) {
            var statusHtml = resp.assigned_tp_id
                ? '<span class="dat-filled">' + escHtml(resp.assigned_tp_name) + '</span>'
                : '<span class="dat-vacant">Vacant Area</span>';
            var $html = $('<div class="dat-firka-panel"></div>');
            $html.append('<div style="margin-bottom:10px;font-weight:600;">' + escHtml(firkaName) + '</div>');
            $html.append('<div style="margin-bottom:10px;">Current: ' + statusHtml + '</div>');
            $html.append('<label class="form-label">Assign Territory Partner</label>');
            var $select = $('<select class="form-control" id="firkaTpSelect"></select>');
            $select.append($('<option>').val('').text('-- Vacant Area --'));
            $.each(resp.all_tps, function (_, tp) {
                var $opt = $('<option>').val(tp.id).text(tp.name);
                if (resp.assigned_tp_id === tp.id) $opt.prop('selected', true);
                $select.append($opt);
            });
            $html.append($select);
            var $btn = $('<button type="button" class="btn btn-primary mt-2">Save</button>');
            $btn.on('click', function () {
                $btn.prop('disabled', true).text('Saving…');
                $.post('tp-assign-firka-action.php', { firka_id: firkaId, tp_id: $select.val(), assign_date: $('#selAssignDate').val() }, function (r) {
                    if (r && r.success) {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Saved', showConfirmButton: false, timer: 1800, timerProgressBar: true });
                        loadFirkaDetail(firkaId, firkaName);
                    } else {
                        $btn.prop('disabled', false).text('Save');
                        Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: (r && r.error) ? r.error : 'Failed to save', showConfirmButton: false, timer: 2200 });
                    }
                }, 'json').fail(function () {
                    $btn.prop('disabled', false).text('Save');
                    Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Failed to save', showConfirmButton: false, timer: 2200 });
                });
            });
            $html.append('<br>').append($btn);
            $panel.empty().append($html);
            $select.select2({ width: '100%', dropdownParent: $panel });
        });
    }
})(jQuery);
</script>
</body>
</html>
