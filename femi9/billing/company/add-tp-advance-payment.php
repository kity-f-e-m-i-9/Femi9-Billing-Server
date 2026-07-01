<?php
include("checksession.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$logged_user_name = $_SESSION['LOGIN_USER'] ?? '';
$logged_user_type = $_SESSION['LOGIN_USER_TYPE'] ?? '';

$tps = $db_conn->query("SELECT id, tp_id, name, mobile FROM territory_partners WHERE is_active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$company_profiles = [];
$stmt_cp = $db_conn->prepare("SELECT id, gname FROM company_godown WHERE gname LIKE '%Femi%' ORDER BY id ASC");
if ($stmt_cp) { $stmt_cp->execute(); $company_profiles = $stmt_cp->get_result()->fetch_all(MYSQLI_ASSOC); $stmt_cp->close(); }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Add TP Payment Entry | <?php echo htmlspecialchars($business_name ?? 'Femi9 Billing'); ?></title>

    <!-- Styles -->
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
        .card {
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #d1d5db;
            padding: 10px 12px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .alert {
            border-radius: 6px;
            border: none;
            padding: 16px;
        }
        .required-field::after {
            content: " *";
            color: #ef4444;
        }
        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .user-info-badge {
            display: inline-block;
            background: #f3f4f6;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            color: #374151;
            margin-bottom: 20px;
        }
        /* Select2 theme overrides */
        .select2-container--default .select2-selection--single {
            border-radius: 6px;
            border: 1px solid #d1d5db;
            height: auto;
            padding: 10px 12px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            padding: 0;
            color: #374151;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 100%;
            top: 0;
            right: 8px;
        }
        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .select2-dropdown {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
        }
        .select2-search--dropdown .select2-search__field {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 6px 10px;
        }
        .select2-container { width: 100% !important; }
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
                                        <table class="headertble">
                                            <tr>
                                                <td>Add TP Payment Entry</td>
                                                <td><a href="manage-tp-advance-payments" title="View TP Advance Payments">&#9776;</a></td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <!-- Main Content -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">

                                        <!-- Success/Error Messages -->
                                        <?php if (isset($_GET['success'])): ?>
                                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                                <i class="material-icons-outlined" style="vertical-align: middle;">check_circle</i>
                                                <strong>Success!</strong> Advance payment recorded successfully.
                                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (isset($_GET['error'])): ?>
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                <i class="material-icons-outlined" style="vertical-align: middle;">error</i>
                                                <strong>Error!</strong>
                                                <?php echo htmlspecialchars($_GET['error'] ?? 'Failed to record payment.'); ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Info Box -->
                                        <div class="info-box">
                                            <i class="material-icons-outlined" style="vertical-align: middle; color: #3b82f6;">info</i>
                                            <strong>Note:</strong> Record advance payments received from Territory Partners.
                                            Select the Territory Partner and enter payment details.
                                        </div>

                                        <!-- Receiver Info Badge -->
                                        <div class="user-info-badge">
                                            <i class="material-icons-outlined" style="vertical-align: middle; font-size: 18px;">account_circle</i>
                                            <strong>Receiving As:</strong>
                                            <?php echo htmlspecialchars($logged_user_name); ?>
                                            (<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $logged_user_type))); ?>)
                                        </div>

                                        <!-- Payment Entry Form -->
                                        <form action="tp-advance-payment-action" method="POST" id="advancePaymentForm" onsubmit="return validateForm();">

                                            <!-- CSRF Token -->
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="add_tp_advance_payment" value="1">

                                            <div class="row">

                                                <!-- Company Profile -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="company_id" class="form-label required-field">Company Profile</label>
                                                    <select name="company_id" id="company_id" class="form-select" required>
                                                        <option value="" hidden>Select Company</option>
                                                        <?php foreach ($company_profiles as $cp): ?>
                                                            <option value="<?php echo $cp['id']; ?>">
                                                                <?php echo htmlspecialchars($cp['gname']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <small class="text-muted">Select the company receiving this payment</small>
                                                </div>

                                                <!-- Territory Partner -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="territory_partner_id" class="form-label required-field">Territory Partner</label>
                                                    <select name="territory_partner_id" id="territory_partner_id" class="form-select" required>
                                                        <option value=""></option>
                                                        <?php foreach ($tps as $tp): ?>
                                                            <option value="<?php echo $tp['id']; ?>"
                                                                data-tp-id="<?php echo htmlspecialchars($tp['tp_id']); ?>"
                                                                data-mobile="<?php echo htmlspecialchars($tp['mobile'] ?? ''); ?>">
                                                                <?php echo htmlspecialchars($tp['name']); ?> (<?php echo htmlspecialchars($tp['tp_id']); ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <small class="text-muted">Select the Territory Partner who made the payment</small>
                                                </div>

                                                <!-- Payment Date -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="payment_date" class="form-label required-field">Payment Date</label>
                                                    <input type="date" name="payment_date" id="payment_date" class="form-control"
                                                           value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                                    <small class="text-muted">Date when payment was received</small>
                                                </div>

                                                <!-- Amount -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="amount" class="form-label required-field">Amount (₹)</label>
                                                    <input type="number" name="amount" id="amount" class="form-control"
                                                           placeholder="Enter amount" min="1" step="0.01" required>
                                                    <small class="text-muted">Enter advance payment amount</small>
                                                </div>

                                                <!-- Payment Mode -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="payment_mode" class="form-label required-field">Payment Mode</label>
                                                    <select name="payment_mode" id="payment_mode" class="form-select" required>
                                                        <option value=""></option>
                                                        <option value="Cash">Cash</option>
                                                        <option value="Bank Transfer">Bank Transfer</option>
                                                        <option value="Cheque">Cheque</option>
                                                        <option value="UPI">UPI</option>
                                                        <option value="NEFT">NEFT</option>
                                                        <option value="RTGS">RTGS</option>
                                                        <option value="IMPS">IMPS</option>
                                                        <option value="Demand Draft">Demand Draft</option>
                                                        <option value="Other">Other</option>
                                                    </select>
                                                </div>

                                                <!-- Reference Number -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="reference_number" class="form-label">Reference Number</label>
                                                    <input type="text" name="reference_number" id="reference_number"
                                                           class="form-control" placeholder="UTR/Transaction/Cheque number" maxlength="255">
                                                    <small class="text-muted">Transaction reference (optional)</small>
                                                </div>

                                                <!-- Bank Name -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="bank_name" class="form-label">Bank Name</label>
                                                    <input type="text" name="bank_name" id="bank_name"
                                                           class="form-control" placeholder="Bank name (if applicable)" maxlength="255">
                                                    <small class="text-muted">Bank name (optional)</small>
                                                </div>

                                                <!-- Remarks -->
                                                <div class="col-md-12 mb-3">
                                                    <label for="remarks" class="form-label">Remarks</label>
                                                    <textarea name="remarks" id="remarks" class="form-control"
                                                              rows="3" placeholder="Additional notes about this payment (optional)" maxlength="1000"></textarea>
                                                </div>

                                            </div>

                                            <!-- Submit Button -->
                                            <div class="mt-4">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="material-icons" style="vertical-align: middle;">add_circle</i>
                                                    Record Payment
                                                </button>
                                                <a href="manage-tp-advance-payments" class="btn btn-secondary ms-2">
                                                    <i class="material-icons" style="vertical-align: middle;">cancel</i>
                                                    Cancel
                                                </a>
                                            </div>

                                        </form>

                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/plugins/select2/js/select2.full.min.js"></script>

    <script>
    $(document).ready(function() {
        function tpMatcher(params, data) {
            if (!params.term || params.term.trim() === '') return data;
            var q    = params.term.trim().toLowerCase();
            var text = (data.text || '').toLowerCase();
            if (text.indexOf(q) > -1) return data;
            if (data.element) {
                var tpId   = (data.element.getAttribute('data-tp-id') || '').toLowerCase();
                var mobile = (data.element.getAttribute('data-mobile') || '').toLowerCase();
                if (tpId.indexOf(q) > -1 || mobile.indexOf(q) > -1) return data;
            }
            return null;
        }

        $('#company_id').select2({
            placeholder: 'Select Company',
            minimumResultsForSearch: Infinity
        });
        $('#territory_partner_id').select2({
            placeholder: 'Select Territory Partner',
            allowClear: true,
            matcher: tpMatcher
        });
        $('#payment_mode').select2({
            placeholder: 'Select Payment Mode',
            minimumResultsForSearch: Infinity
        });
    });

    function validateForm() {
        const amount = parseFloat(document.getElementById('amount').value);

        if (!amount || amount <= 0) {
            alert('Please enter a valid amount greater than 0');
            return false;
        }

        if (amount > 10000000) {
            if (!confirm('Amount is more than ₹1 Crore. Are you sure?')) {
                return false;
            }
        }

        return confirm('Confirm adding this advance payment entry?');
    }
    </script>

</body>
</html>
