<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms_edit');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily Assign Report : <?php echo $business_name; ?></title>
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
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <style>
    .dar-row { display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end; }
    .dar-field { flex:1; min-width:180px; max-width:260px; }
    .dar-field label { font-weight:600; font-size:13px; color:#475569; margin-bottom:4px; display:block; }
    .dar-empty { color:#aaa; font-size:13px; padding:16px 0; text-align:center; }
    .dar-vacant { color:#b45309; font-weight:600; }
    .dar-filled { color:#166534; font-weight:600; }
    .select2-container { width: 100% !important; }
    .select2-container--default .select2-selection--single { border-radius:6px; border:1px solid #d1d5db; height:auto; padding:7px 12px; font-size:0.95rem; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:1.5; padding:0; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height:100%; top:0; right:6px; }

    /* Site-standard report look (matches Retail-Report-Details-TP.php) */
    .card { border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.08); border-radius: 12px; }
    .table-bordered { border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; }
    .table-hover tbody tr:hover { background-color: rgba(0,123,255,0.05); transition: background-color 0.2s ease; }
    .table th {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        font-weight: 600; font-size: 14px; text-align: center;
        padding: 12px 8px; border-color: #dee2e6; color: #495057;
    }
    .table td { font-size: 13px; padding: 10px 8px; vertical-align: middle; border-color: #dee2e6; text-align: center; }
    .pagination .page-link { border-radius: 6px; margin: 0 2px; border: none; background: #f8f9fa; color: #495057; font-weight: 500; }
    .pagination .page-item.active .page-link { background: #0d6efd; color: white; box-shadow: 0 2px 4px rgba(13,110,253,0.3); }
    .pagination .page-item.disabled .page-link { background: #f8f9fa; color: #adb5bd; }
    .pagination .page-link:hover { background: #e9ecef; color: #495057; }
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
                                <h1>Daily Assign Report</h1>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="dar-row">
                                        <div class="dar-field">
                                            <label>DM / ASM / SM</label>
                                            <select class="form-control" id="repStaff">
                                                <option value="">-- All Staff --</option>
                                            </select>
                                        </div>
                                        <div class="dar-field">
                                            <label>From Date</label>
                                            <input type="date" class="form-control" id="repFrom">
                                        </div>
                                        <div class="dar-field">
                                            <label>To Date</label>
                                            <input type="date" class="form-control" id="repTo">
                                        </div>
                                        <div class="dar-field">
                                            <button type="button" class="btn btn-primary" id="repSearch">Search</button>
                                        </div>
                                        <div class="dar-field">
                                            <button type="button" class="btn btn-outline-success" id="repExport">Export Excel</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover table-sm" id="repTable">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>District</th>
                                                    <th>Division</th>
                                                    <th>Firka</th>
                                                    <th>TP Name</th>
                                                    <th>Assigned By</th>
                                                </tr>
                                            </thead>
                                            <tbody id="repBody">
                                                <tr><td colspan="6" class="dar-empty">Select filters and click Search.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <nav><ul class="pagination justify-content-end" id="repPagination"></ul></nav>
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

<script>
(function ($) {
    function escHtml(str) { return $('<div>').text(str).html(); }
    function pad(n) { return String(n).padStart(2, '0'); }
    function toStr(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }

    var today = new Date();
    var monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
    $('#repFrom').val(toStr(monthStart));
    $('#repTo').val(toStr(today));

    $('#repStaff').select2({ width: '100%' });

    $.getJSON('get-tp-assign-staff.php', function (resp) {
        var $sel = $('#repStaff');
        $.each(resp, function (_, s) {
            $sel.append($('<option>').val(s.id).text(s.name));
        });
        $sel.trigger('change.select2');
    });

    var PAGE_SIZE = 10;
    var PAGE_WINDOW = 5;
    var allRows = [];
    var curPage = 1;

    function renderRow(r) {
        var tpHtml = r.tp_name === 'Vacant Area'
            ? '<span class="dar-vacant">Vacant Area</span>'
            : '<span class="dar-filled">' + escHtml(r.tp_name) + '</span>';
        return '<tr>'
            + '<td>' + escHtml(r.assign_date) + '</td>'
            + '<td>' + escHtml(r.district_name) + '</td>'
            + '<td>' + escHtml(r.division_name) + '</td>'
            + '<td>' + escHtml(r.firka_name) + '</td>'
            + '<td>' + tpHtml + '</td>'
            + '<td>' + escHtml(r.assigned_by || '') + '</td>'
            + '</tr>';
    }

    function renderPage(page) {
        var totalPages = Math.max(1, Math.ceil(allRows.length / PAGE_SIZE));
        curPage = Math.min(Math.max(1, page), totalPages);

        var start = (curPage - 1) * PAGE_SIZE;
        var pageRows = allRows.slice(start, start + PAGE_SIZE);
        var $body = $('#repBody');
        if (!pageRows.length) {
            $body.html('<tr><td colspan="6" class="dar-empty">No assignment records found for this filter.</td></tr>');
        } else {
            $body.html(pageRows.map(renderRow).join(''));
        }

        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        var $pag = $('#repPagination').empty();
        if (totalPages <= 1) return;

        var windowStart = Math.floor((curPage - 1) / PAGE_WINDOW) * PAGE_WINDOW + 1;
        var windowEnd = Math.min(windowStart + PAGE_WINDOW - 1, totalPages);

        function addItem(label, page, disabled, active) {
            var li = $('<li class="page-item"></li>');
            if (disabled) li.addClass('disabled');
            if (active) li.addClass('active');
            var a = $('<a class="page-link" href="#"></a>').text(label);
            if (!disabled && !active) {
                a.on('click', function (e) { e.preventDefault(); renderPage(page); });
            } else {
                a.on('click', function (e) { e.preventDefault(); });
            }
            li.append(a);
            $pag.append(li);
        }

        addItem('Prev', curPage - 1, curPage === 1, false);
        for (var p = windowStart; p <= windowEnd; p++) {
            addItem(String(p), p, false, p === curPage);
        }
        addItem('Next', curPage + 1, windowEnd >= totalPages && curPage === totalPages, false);
    }

    function runSearch() {
        var $body = $('#repBody');
        $body.html('<tr><td colspan="6" class="dar-empty">Loading&hellip;</td></tr>');
        $('#repPagination').empty();
        $.getJSON('get-daily-assign-report.php', {
            ms_id: $('#repStaff').val(),
            from: $('#repFrom').val(),
            to: $('#repTo').val()
        }, function (rows) {
            allRows = rows || [];
            renderPage(1);
        });
    }

    $('#repSearch').on('click', runSearch);
    $('#repExport').on('click', function () {
        var qs = $.param({
            ms_id: $('#repStaff').val(),
            from: $('#repFrom').val(),
            to: $('#repTo').val()
        });
        window.location.href = 'export-daily-assign-report-xlsx.php?' + qs;
    });
    runSearch();
})(jQuery);
</script>
</body>
</html>
