<?php
/**
 * Get Advance Payment Details - Modal Content
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
    .detail-row {
        padding: 12px 0;
        border-bottom: 1px solid #e9ecef;
    }
    .detail-row:last-child {
        border-bottom: none;
    }
    .detail-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 4px;
    }
    .detail-value {
        color: #6c757d;
        font-size: 15px;
    }
    .amount-highlight {
        font-size: 24px;
        font-weight: 700;
        color: #667eea;
    }
    .status-badge-large {
        padding: 8px 16px;
        border-radius: 16px;
        font-size: 14px;
        font-weight: 600;
    }
</style>

<div class="container-fluid">
    
    <!-- Amount Summary -->
    <div class="row mb-4">
        <div class="col-md-4 text-center">
            <div class="p-3" style="background: #f8f9fa; border-radius: 8px;">
                <small class="text-muted d-block mb-1">Total Amount</small>
                <span class="amount-highlight">₹<?php echo number_format($payment['amount'], 2); ?></span>
            </div>
        </div>
        <div class="col-md-4 text-center">
            <div class="p-3" style="background: #d4edda; border-radius: 8px;">
                <small class="text-muted d-block mb-1">Balance</small>
                <span class="amount-highlight text-success">₹<?php echo number_format($payment['balance_amount'], 2); ?></span>
            </div>
        </div>
        <div class="col-md-4 text-center">
            <div class="p-3" style="background: #fff3cd; border-radius: 8px;">
                <small class="text-muted d-block mb-1">Adjusted</small>
                <span class="amount-highlight text-warning">₹<?php echo number_format($payment['adjusted_amount'], 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Payment Details -->
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-primary mb-3">Payer Information</h6>
            
            <div class="detail-row">
                <div class="detail-label">Payer Name</div>
                <div class="detail-value"><?php echo htmlspecialchars($payment['from_user_name']); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Payer ID</div>
                <div class="detail-value"><?php echo htmlspecialchars($payment['from_user_id']); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Payer Type</div>
                <div class="detail-value"><?php echo ucwords(str_replace('_', ' ', $payment['from_user_type'])); ?></div>
            </div>
        </div>

        <div class="col-md-6">
            <h6 class="text-primary mb-3">Receiver Information</h6>
            
            <div class="detail-row">
                <div class="detail-label">Receiver Name</div>
                <div class="detail-value"><?php echo htmlspecialchars($payment['to_user_name']); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Receiver ID</div>
                <div class="detail-value"><?php echo htmlspecialchars($payment['to_user_id']); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Receiver Type</div>
                <div class="detail-value"><?php echo ucwords(str_replace('_', ' ', $payment['to_user_type'])); ?></div>
            </div>
        </div>
    </div>

    <hr class="my-4">

    <!-- Payment Information -->
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-primary mb-3">Payment Information</h6>
            
            <div class="detail-row">
                <div class="detail-label">Payment Date</div>
                <div class="detail-value"><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Payment Mode</div>
                <div class="detail-value"><?php echo htmlspecialchars($payment['payment_mode']); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Reference Number</div>
                <div class="detail-value"><?php echo htmlspecialchars($payment['reference_number'] ?: 'N/A'); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Bank Name</div>
                <div class="detail-value"><?php echo htmlspecialchars($payment['bank_name'] ?: 'N/A'); ?></div>
            </div>
        </div>

        <div class="col-md-6">
            <h6 class="text-primary mb-3">Status & Remarks</h6>
            
            <div class="detail-row">
                <div class="detail-label">Status</div>
                <div class="detail-value">
                    <?php
                    $status_class = 'status-active';
                    $status_text = $payment['status'];
                    
                    if ($payment['status'] === 'partially_adjusted') {
                        $status_class = 'status-partially';
                        $status_text = 'Partially Adjusted';
                    } elseif ($payment['status'] === 'fully_adjusted') {
                        $status_class = 'status-fully';
                        $status_text = 'Fully Adjusted';
                    } elseif ($payment['status'] === 'active') {
                        $status_text = 'Active';
                    }
                    ?>
                    <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                </div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Remarks</div>
                <div class="detail-value"><?php echo nl2br(htmlspecialchars($payment['remarks'] ?: 'No remarks')); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Created At</div>
                <div class="detail-value"><?php echo date('d M Y, h:i A', strtotime($payment['created_at'])); ?></div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">Last Updated</div>
                <div class="detail-value"><?php echo date('d M Y, h:i A', strtotime($payment['updated_at'])); ?></div>
            </div>
        </div>
    </div>

</div>
