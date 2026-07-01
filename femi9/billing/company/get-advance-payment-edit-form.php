<?php
/**
 * Get Advance Payment Edit Form
 * Femi9 Billing Application
 * 
 * @author Femi9 Development Team
 * @version 1.0
 * @date 2025-12-29
 */

session_start();

include("checksession.php");
include("config.php");

// Check if user is logged in
if (!isset($_SESSION['LOGIN_USER_ID'])) {
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$payment_id = intval($_POST['id'] ?? 0);

if ($payment_id <= 0) {
    echo '<div class="alert alert-danger">Invalid payment ID</div>';
    exit;
}

// Fetch payment details
$stmt = $db_conn->prepare("
    SELECT * FROM advance_payments 
    WHERE id = ? AND deleted_at IS NULL 
    LIMIT 1
");

$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger">Payment not found</div>';
    exit;
}

$payment = $result->fetch_assoc();
$stmt->close();
?>

<style>
    .edit-form-section {
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e9ecef;
    }
    .edit-form-section:last-child {
        border-bottom: none;
    }
    .info-note {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 12px;
        margin-bottom: 20px;
        border-radius: 4px;
    }
</style>

<input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

<!-- Info Note -->
<div class="info-note">
    <i class="material-icons" style="vertical-align: middle; color: #856404;">info</i>
    <strong>Note:</strong> You can only edit payment details and amounts. User information cannot be changed.
</div>

<!-- Non-editable Information -->
<div class="edit-form-section">
    <h6 class="text-muted mb-3">Payment Information</h6>
    <div class="row">
        <div class="col-md-6">
            <p><strong>Payer:</strong> <?php echo htmlspecialchars($payment['from_user_name']); ?></p>
            <p><strong>Payer Type:</strong> <?php echo ucwords(str_replace('_', ' ', $payment['from_user_type'])); ?></p>
        </div>
        <div class="col-md-6">
            <p><strong>Receiver:</strong> <?php echo htmlspecialchars($payment['to_user_name']); ?></p>
            <p><strong>Receiver Type:</strong> <?php echo ucwords(str_replace('_', ' ', $payment['to_user_type'])); ?></p>
        </div>
    </div>
</div>

<!-- Editable Fields -->
<div class="edit-form-section">
    <h6 class="text-primary mb-3">Edit Payment Details</h6>
    
    <div class="row">
        <!-- Amount -->
        <div class="col-md-6 mb-3">
            <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
            <input type="number" name="amount" class="form-control" 
                   value="<?php echo $payment['amount']; ?>" 
                   min="1" step="0.01" required>
            <small class="text-muted">Current: ₹<?php echo number_format($payment['amount'], 2); ?></small>
        </div>

        <!-- Payment Date -->
        <div class="col-md-6 mb-3">
            <label class="form-label">Payment Date <span class="text-danger">*</span></label>
            <input type="date" name="payment_date" class="form-control" 
                   value="<?php echo $payment['payment_date']; ?>" 
                   max="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <!-- Payment Mode -->
        <div class="col-md-6 mb-3">
            <label class="form-label">Payment Mode <span class="text-danger">*</span></label>
            <select name="payment_mode" class="form-select" required>
                <option value="">Select Payment Mode</option>
                <option value="Cash" <?php echo $payment['payment_mode'] === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="Bank Transfer" <?php echo $payment['payment_mode'] === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                <option value="Cheque" <?php echo $payment['payment_mode'] === 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                <option value="UPI" <?php echo $payment['payment_mode'] === 'UPI' ? 'selected' : ''; ?>>UPI</option>
                <option value="NEFT" <?php echo $payment['payment_mode'] === 'NEFT' ? 'selected' : ''; ?>>NEFT</option>
                <option value="RTGS" <?php echo $payment['payment_mode'] === 'RTGS' ? 'selected' : ''; ?>>RTGS</option>
                <option value="IMPS" <?php echo $payment['payment_mode'] === 'IMPS' ? 'selected' : ''; ?>>IMPS</option>
                <option value="Demand Draft" <?php echo $payment['payment_mode'] === 'Demand Draft' ? 'selected' : ''; ?>>Demand Draft</option>
                <option value="Other" <?php echo $payment['payment_mode'] === 'Other' ? 'selected' : ''; ?>>Other</option>
            </select>
        </div>

        <!-- Reference Number -->
        <div class="col-md-6 mb-3">
            <label class="form-label">Reference Number</label>
            <input type="text" name="reference_number" class="form-control" 
                   value="<?php echo htmlspecialchars($payment['reference_number'] ?? ''); ?>" 
                   maxlength="255" placeholder="UTR/Transaction/Cheque number">
        </div>

        <!-- Bank Name -->
        <div class="col-md-6 mb-3">
            <label class="form-label">Bank Name</label>
            <input type="text" name="bank_name" class="form-control" 
                   value="<?php echo htmlspecialchars($payment['bank_name'] ?? ''); ?>" 
                   maxlength="255" placeholder="Bank name">
        </div>

        <!-- Adjusted Amount -->
        <div class="col-md-6 mb-3">
            <label class="form-label">Adjusted Amount (₹)</label>
            <input type="number" name="adjusted_amount" class="form-control" 
                   value="<?php echo $payment['adjusted_amount']; ?>" 
                   min="0" step="0.01">
            <small class="text-muted">Amount already used/adjusted</small>
        </div>

        <!-- Balance Amount (Auto-calculated) -->
        <div class="col-md-6 mb-3">
            <label class="form-label">Balance Amount (₹)</label>
            <input type="number" name="balance_amount" class="form-control" 
                   value="<?php echo $payment['balance_amount']; ?>" 
                   min="0" step="0.01" readonly>
            <small class="text-muted">Will be auto-calculated: Amount - Adjusted</small>
        </div>

        <!-- Remarks -->
        <div class="col-md-12 mb-3">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="3" 
                      placeholder="Additional notes"><?php echo htmlspecialchars($payment['remarks'] ?? ''); ?></textarea>
        </div>
    </div>
</div>

<script>
// Auto-calculate balance when amount or adjusted amount changes
$(document).ready(function() {
    function calculateBalance() {
        const amount = parseFloat($('input[name="amount"]').val()) || 0;
        const adjusted = parseFloat($('input[name="adjusted_amount"]').val()) || 0;
        const balance = amount - adjusted;
        
        if (balance < 0) {
            $('input[name="adjusted_amount"]').val(amount);
            $('input[name="balance_amount"]').val(0);
            alert('Adjusted amount cannot be greater than total amount');
        } else {
            $('input[name="balance_amount"]').val(balance.toFixed(2));
        }
    }

    $('input[name="amount"], input[name="adjusted_amount"]').on('input', calculateBalance);
});
</script>
