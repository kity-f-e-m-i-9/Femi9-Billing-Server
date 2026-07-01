<?php
/**
 * Invoice Alert Messages
 * Centralized alert handling for invoice operations
 */
?>

<?php if(isset($_REQUEST['AddedSuccess'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="material-icons-outlined" style="vertical-align: middle;">check_circle</i>
    <strong>Success!</strong> Product added to invoice successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if(isset($_REQUEST['ItemAlreadyExists'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="material-icons-outlined" style="vertical-align: middle;">error</i>
    <strong>Duplicate Product!</strong> This product already exists in the invoice.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if(isset($_REQUEST['InvalidStock'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="material-icons-outlined" style="vertical-align: middle;">inventory_2</i>
    <strong>Insufficient Stock!</strong> The requested quantity exceeds available stock.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if(isset($_REQUEST['DeleteSuccess'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="material-icons-outlined" style="vertical-align: middle;">delete</i>
    <strong>Deleted!</strong> Product removed from invoice successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if(isset($_REQUEST['stocknotupdated'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="material-icons-outlined" style="vertical-align: middle;">warning</i>
    <strong>Stock Not Updated!</strong> Please update opening stock for 
    <strong><?php echo htmlspecialchars($result_Godown['gname'] ?? 'the selected godown'); ?></strong> before creating invoices.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if(isset($_REQUEST['invoicealready'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="material-icons-outlined" style="vertical-align: middle;">content_copy</i>
    <strong>Duplicate Invoice!</strong> This invoice number already exists. Please use a different number.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if(isset($_REQUEST['InvoiceUpdatedSuccess'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="material-icons-outlined" style="vertical-align: middle;">update</i>
    <strong>Updated!</strong> Invoice updated successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if(isset($_REQUEST['InsufficientBalance'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="material-icons-outlined" style="vertical-align: middle;">account_balance_wallet</i>
    <strong>Insufficient Advance Payment!</strong> 
    <?php 
    $shortage = $_REQUEST['shortage'] ?? '0';
    $available = $_REQUEST['available'] ?? '0';
    $required = $_REQUEST['required'] ?? '0';
    ?>
    <p class="mb-0 mt-2">
        Available Balance: <strong>₹<?php echo number_format($available, 2); ?></strong><br>
        Required Amount: <strong>₹<?php echo number_format($required, 2); ?></strong><br>
        Shortage: <strong class="text-danger">₹<?php echo number_format($shortage, 2); ?></strong>
    </p>
    <a href="add-advance-payment.php" class="btn btn-sm btn-primary mt-2">
        <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">add</i>
        Add Advance Payment
    </a>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if(isset($_REQUEST['InvoiceCreatedSuccess'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="material-icons-outlined" style="vertical-align: middle;">receipt</i>
    <strong>Invoice Created!</strong> 
    <?php if($is_advance_mandatory && isset($_REQUEST['deducted_amount'])): ?>
    <p class="mb-0 mt-2">
        Amount Deducted: <strong>₹<?php echo number_format($_REQUEST['deducted_amount'], 2); ?></strong><br>
        Remaining Balance: <strong>₹<?php echo number_format($_REQUEST['remaining_balance'] ?? 0, 2); ?></strong>
    </p>
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if(isset($_REQUEST['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="material-icons-outlined" style="vertical-align: middle;">error</i>
    <strong>Error!</strong> <?php echo htmlspecialchars($_REQUEST['error']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if(isset($_REQUEST['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="material-icons-outlined" style="vertical-align: middle;">check_circle</i>
    <strong>Success!</strong> <?php echo htmlspecialchars($_REQUEST['success']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<style>
/* Alert Icons */
.alert .material-icons-outlined {
    font-size: 20px;
    margin-right: 8px;
}

.alert {
    border-radius: 10px;
    border: none;
    padding: 16px 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

.alert-warning {
    background: #fef3c7;
    color: #92400e;
    border-left: 4px solid #f59e0b;
}

.alert-info {
    background: #dbeafe;
    color: #1e40af;
    border-left: 4px solid #3b82f6;
}

.alert strong {
    font-weight: 600;
}

.alert p {
    margin: 8px 0;
    line-height: 1.6;
}

.alert .btn {
    margin-top: 8px;
}
</style>
