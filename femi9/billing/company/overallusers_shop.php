<?php 
// Load environment variables FIRST
require_once __DIR__ . '/../shared/env-loader.php';

// Then include session check
include("checksession.php");

// Now load encryption service
require_once __DIR__ . '/../shared/EncryptionService.php';
$encryption = new EncryptionService();

error_reporting(0);
$getinvuser = $_REQUEST['invuser'] ?? '';

$displaytitle = "Overall - Shop";
$tablename    = "shop";
$xlurl        = "ex_overallusers_shop";

// ─── Server-side pagination ───────────────────────────────────────────────────
$num_rec_per_page = 30;
$page             = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start_from       = ($page - 1) * $num_rec_per_page;

// Total record count (for page buttons)
$total_result  = mysqli_fetch_assoc(mysqli_query($db_conn, "SELECT COUNT(*) AS total FROM $tablename"));
$total_records = (int)$total_result['total'];
$total_pages   = (int)ceil($total_records / $num_rec_per_page);

// Fetch ONLY this page's rows — no full table load
$select_product_list = "SELECT * FROM $tablename ORDER BY id DESC LIMIT $start_from, $num_rec_per_page";
$fetch_product_list  = mysqli_query($db_conn, $select_product_list);
// ─────────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?=$displaytitle?> : <?php echo $business_name;?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">

    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link href="../../assets/css/vlstyle.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        #datatable1 { font-size: 14px; }

        #datatable1 thead th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
            padding: 12px 8px;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
            border-bottom: 2px solid #dee2e6;
        }

        #datatable1 tbody td {
            padding: 10px 8px;
            vertical-align: middle;
            border-bottom: 1px solid #e8e8e8;
        }

        #datatable1 tbody tr { transition: background-color 0.2s ease; }
        #datatable1 tbody tr:hover { background-color: #f8f9ff; }

        .btn-update {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-update:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }

        #linkcaption {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        #linkcaption:hover { color: #764ba2; text-decoration: underline; }

        .serial-number { font-weight: 600; color: #888; }
        .user-name     { font-weight: 600; color: #333; }

        /* ── Custom Pagination ── */
        .vl-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            padding: 16px 4px 4px;
        }
        .vl-pagination .page-info {
            font-size: 13px;
            color: #666;
        }
        .vl-pagination .page-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        .vl-pagination a,
        .vl-pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 8px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #dee2e6;
            color: #555;
            transition: all 0.2s ease;
        }
        .vl-pagination a:hover {
            background: #667eea;
            border-color: #667eea;
            color: #fff;
        }
        .vl-pagination span.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent;
            color: #fff;
        }
        .vl-pagination span.disabled {
            color: #ccc;
            pointer-events: none;
            border-color: #f0f0f0;
        }
        .vl-pagination span.ellipsis {
            border: none;
            color: #aaa;
        }
    </style>
</head>

<body>
    <div class="app align-content-stretch d-flex flex-wrap">

        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>

        <div class="app-container">
            <?php include("app-header.php");?>

            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">

                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <?php if(isset($_REQUEST['Samenumbernotaccepted'])){?>
                                        <div class="alert alert-danger">Same mobile number not accepted.</div>
                                    <?php }?>
                                    <?php if(isset($_REQUEST['MobileAlreadyExists'])){?>
                                        <div class="alert alert-danger">You entered mobile number already exists.</div>
                                    <?php }?>
                                    <?php if(isset($_REQUEST['MobileUpdatedSuccess'])){?>
                                        <div class="alert alert-success">New mobile number updated success.</div>
                                    <?php }?>
                                    <h1>
                                        <table class="headertble"><tr>
                                            <td><?=$displaytitle?></td>
                                            <td><a href="<?=$xlurl;?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
                                        </tr></table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($_SESSION['successMessage'])) {
                            $successMessage = $_SESSION['successMessage'];
                            unset($_SESSION['successMessage']); ?>
                            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Success',
                                        text: '<?php echo addslashes($successMessage); ?>',
                                        confirmButtonText: 'OK'
                                    });
                                });
                            </script>
                        <?php } ?>

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div style="overflow-x:auto;">
                                            <table id="datatable1" class="table" style="width:100%;">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>ID</th>
                                                        <th>Name</th>
                                                        <th>Mobile</th>
                                                        <th>District</th>
                                                        <th>Taluk</th>
                                                        <th>Referred&nbsp;ID</th>
                                                        <th>Referred&nbsp;Name</th>
                                                        <th>Referred&nbsp;Mobile</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                // Serial number continues correctly across pages
                                                $i = $start_from;

                                                while ($result_product_list = mysqli_fetch_array($fetch_product_list)):

                                                    // District
                                                    $district_id = $result_product_list['district_id'];
                                                    if (is_numeric($district_id)) {
                                                        $r = mysqli_fetch_array(mysqli_query($db_conn, "SELECT dist_name FROM district WHERE id='$district_id'"));
                                                        $district_name = $r['dist_name'];
                                                    } else {
                                                        $district_name = $district_id;
                                                    }

                                                    // Taluk
                                                    $Taluk_id = $result_product_list['taluk_id'];
                                                    if (is_numeric($Taluk_id)) {
                                                        $r = mysqli_fetch_array(mysqli_query($db_conn, "SELECT taluk FROM taluk WHERE id='$Taluk_id'"));
                                                        $taluk_name = $r['taluk'];
                                                    } else {
                                                        $taluk_name = $Taluk_id;
                                                    }

                                                    // Stockist referral
                                                    $result_referralDetails = mysqli_fetch_array(mysqli_query($db_conn,
                                                        "SELECT * FROM stockist_referral WHERE stockist_id='" . $result_product_list['temp_id'] . "'"
                                                    ));

                                                    // Referred user type → table name
                                                    switch ($result_product_list["onboard_userTYPE"]) {
                                                        case 'super_stockiest':
                                                            $tblename  = "super_stockiest";
                                                            $labelname = "Super&nbsp;Stockist";
                                                            break;
                                                        case 'stockiest':
                                                            $tblename  = "stockiest";
                                                            $labelname = "Stockist";
                                                            break;
                                                        case 'distributor':
                                                        case 'super_distributor':
                                                            $tblename  = "distributor";
                                                            $labelname = "Distributor";
                                                            break;
                                                        default:
                                                            $tblename  = "stockiest";
                                                            $labelname = "";
                                                    }

                                                    $result_count_REFERID = mysqli_fetch_array(mysqli_query($db_conn,
                                                        "SELECT * FROM $tblename WHERE temp_id='" . $result_product_list["onboard_userID"] . "'"
                                                    ));

                                                    $rowid = base64_encode($result_product_list["id"]);
                                                ?>
                                                <tr>
                                                    <td class="text-center serial-number"><?php echo ++$i; ?></td>
                                                    <td class="text-center"><?=$result_product_list["useridtext"];?></td>
                                                    <td><span class="user-name"><?php echo ucwords($result_product_list["name"]);?></span></td>

                                                    <td class="text-center">
                                                        <a href="#" id="linkcaption"
                                                           data-bs-toggle="modal"
                                                           data-bs-target="#modal<?php echo $result_product_list["id"];?>"
                                                           title="Click to Update Mobile Number">
                                                            <?=$result_product_list["country_code"];?>&nbsp;<?=$result_product_list["mobile_number"];?>
                                                        </a>

                                                        <div class="modal fade" id="modal<?php echo $result_product_list["id"];?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Update Mobile Number<br/><?=$result_product_list["mobile_number"];?></h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <form method="post" onsubmit="return confirm('Please confirm!');" enctype="multipart/form-data" action="update_mobile_action">
                                                                        <input type="hidden" name="old_mobile_number" value="<?=$result_product_list["mobile_number"];?>">
                                                                        <input type="hidden" name="update_usertype"   value="<?=$getinvuser;?>">
                                                                        <div class="example-content" style="padding:20px;">
                                                                            <div class="form-floating mb-3">
                                                                                <input type="number" name="new_mobile_number" placeholder="New Mobile Number" min="0" class="form-control">
                                                                                <label>New Mobile Number</label>
                                                                            </div>
                                                                            <button type="submit" name="UpdateAction" class="btn btn-primary">
                                                                                <i class="material-icons">update</i> Update
                                                                            </button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td class="text-center"><?php echo $district_name;?></td>
                                                    <td class="text-center"><?=$taluk_name;?></td>

                                                    <?php if ($result_referralDetails['st_ref_type'] == "company"): ?>
                                                        <td class="text-center">---</td>
                                                        <td>
                                                            <div style="display:flex;align-items:center;gap:8px;">
                                                                <span>Company</span>
                                                                <a href="JavaScript:newPopup('update_referral.php?stockistid=<?=$result_product_list['temp_id'];?>');">
                                                                    <button class="btn-update">Update</button>
                                                                </a>
                                                            </div>
                                                        </td>
                                                        <td class="text-center">---</td>
                                                    <?php else: ?>
                                                        <td class="text-center"><?=$result_count_REFERID["useridtext"];?></td>
                                                        <td>
                                                            <div style="display:flex;flex-direction:column;gap:4px;">
                                                                <span class="user-name"><?=$result_count_REFERID['name'];?></span>
                                                                <span style="font-size:11px;color:#888;"><?=$labelname;?></span>
                                                                <a href="JavaScript:newPopup('update_referral.php?stockistid=<?=$result_product_list['temp_id'];?>');">
                                                                    <button class="btn-update">Update</button>
                                                                </a>
                                                            </div>
                                                        </td>
                                                        <td class="text-center"><?=$result_count_REFERID['mobile_number'];?></td>
                                                    <?php endif; ?>
                                                </tr>
                                                <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- ── Pagination bar ── -->
                                        <?php
                                        $first_record = $total_records > 0 ? $start_from + 1 : 0;
                                        $last_record  = min($start_from + $num_rec_per_page, $total_records);

                                        // Build base URL preserving other GET params except 'page'
                                        $query_params = $_GET;
                                        unset($query_params['page']);
                                        $base_url = '?' . http_build_query($query_params) . (count($query_params) ? '&' : '') . 'page=';
                                        ?>
                                        <div class="vl-pagination">
                                            <div class="page-info">
                                                Showing <strong><?=$first_record?></strong> – <strong><?=$last_record?></strong>
                                                of <strong><?=$total_records?></strong> entries
                                            </div>
                                            <div class="page-buttons">

                                                <!-- Prev button -->
                                                <?php if ($page <= 1): ?>
                                                    <span class="disabled">&laquo; Prev</span>
                                                <?php else: ?>
                                                    <a href="<?=$base_url.($page-1)?>">&laquo; Prev</a>
                                                <?php endif; ?>

                                                <?php
                                                // Smart page numbers: always show first, last, current ±2, with ellipsis gaps
                                                $range = 2;
                                                $shown = [];
                                                for ($p = 1; $p <= $total_pages; $p++) {
                                                    if ($p == 1 || $p == $total_pages || abs($p - $page) <= $range) {
                                                        $shown[] = $p;
                                                    }
                                                }
                                                $prev_p = null;
                                                foreach ($shown as $p):
                                                    if ($prev_p !== null && $p - $prev_p > 1): ?>
                                                        <span class="ellipsis">…</span>
                                                    <?php endif;
                                                    if ($p == $page): ?>
                                                        <span class="active"><?=$p?></span>
                                                    <?php else: ?>
                                                        <a href="<?=$base_url.$p?>"><?=$p?></a>
                                                    <?php endif;
                                                    $prev_p = $p;
                                                endforeach; ?>

                                                <!-- Next button -->
                                                <?php if ($page >= $total_pages): ?>
                                                    <span class="disabled">Next &raquo;</span>
                                                <?php else: ?>
                                                    <a href="<?=$base_url.($page+1)?>">Next &raquo;</a>
                                                <?php endif; ?>

                                            </div>
                                        </div>
                                        <!-- ── end pagination ── -->

                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <!-- datatables.js intentionally removed — server-side pagination replaces it entirely -->

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function newPopup(url) {
        window.open(url, 'popUpWindow',
            'height=480,width=650,left=350,top=100,resizable=yes,scrollbars=yes,toolbar=yes,menubar=no,location=no,directories=no,status=yes');
    }

    function togglePassword(userId) {
        const icon  = document.getElementById('pwd-icon-' + userId);
        const text  = document.getElementById('pwd-text-' + userId);
        const value = document.getElementById('pwd-value-' + userId).value;
        if (text.textContent === '••••••') {
            text.textContent = value;
            icon.textContent = 'visibility';
        } else {
            text.textContent = '••••••';
            icon.textContent = 'visibility_off';
        }
    }

    function copyPassword(password) {
        const tmp = document.createElement('input');
        tmp.value = password;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        document.body.removeChild(tmp);
        Swal.fire({
            icon: 'success', title: 'Copied!',
            text: 'Password copied to clipboard',
            timer: 1500, showConfirmButton: false,
            toast: true, position: 'top-end'
        });
    }
    </script>
</body>
</html>