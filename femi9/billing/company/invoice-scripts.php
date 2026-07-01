<script>
/**
 * Invoice Balance Handler - JavaScript
 * Femi9 Billing Application
 * 
 * Handles:
 * - Real-time advance balance fetching
 * - Product addition and calculation
 * - Form validation
 * - AJAX interactions
 * 
 * @version 3.0
 * @date 2026-01-01
 */

// ============================================
// Configuration
// ============================================
const IS_ADVANCE_MANDATORY = <?php echo $is_advance_mandatory ? 'true' : 'false'; ?>;
const INVOICE_USER_TYPE = '<?php echo $getinvuser; ?>';

let customerBalanceData = null;
let currentInvoiceTotal = 0;

// ============================================
// Initialize on DOM Ready
// ============================================
$(document).ready(function() {
    console.log('Invoice form initialized');
    console.log('Advance payment mandatory:', IS_ADVANCE_MANDATORY);

    // Initialize Select2 for customer dropdown
    $('#customerSelect').select2({
        placeholder: "Select customer",
        allowClear: true,
        width: '100%'
    });

    // Customer selection handler
    $('#customerSelect').on('change', function() {
        const customerId = $(this).val();
        const companyId = $('#godownSelect').val();

        if (customerId && IS_ADVANCE_MANDATORY) {
            fetchCustomerBalance(customerId, INVOICE_USER_TYPE, companyId);
        } else if (customerId && !IS_ADVANCE_MANDATORY) {
            // For Distributor/SD, enable product section immediately
            enableProductSection(true);
        } else {
            resetBalanceDisplay();
            enableProductSection(false);
        }
    });

    // Initialize Flatpickr for date input
    flatpickr(".date-picker", {
        dateFormat: "Y-m-d",
        maxDate: "today"
    });

    // Form submission validation
    $('#invoiceForm').on('submit', function(e) {
        if (IS_ADVANCE_MANDATORY) {
            return validateInvoiceSubmission(e);
        }
        return true;
    });

    // Disable product section initially if advance payment is mandatory
    if (IS_ADVANCE_MANDATORY) {
        enableProductSection(false);
    }
});

// ============================================
// Fetch Customer Balance via AJAX
// ============================================
function fetchCustomerBalance(customerId, customerType, companyId) {
    console.log('Fetching balance for:', customerId, customerType);

    // Show loading
    $('#balanceLoading').addClass('show');
    $('#balanceDisplay').addClass('hidden');
    $('#balanceError').addClass('hidden');
    enableProductSection(false);

    $.ajax({
        url: 'get-advance-balance.php',
        type: 'POST',
        dataType: 'json',
        data: {
            customer_id: customerId,
            customer_type: customerType,
            company_id: companyId
        },
        success: function(response) {
            console.log('Balance response:', response);
            $('#balanceLoading').removeClass('show');

            if (response.success && response.data) {
                customerBalanceData = response.data;
                displayBalanceInfo(response.data);

                // Enable/disable product section based on balance
                const canCreate = parseFloat(response.data.available_balance) > 0;
                enableProductSection(canCreate);

                if (!canCreate) {
                    showBalanceError();
                }
            } else {
                console.error('Balance fetch failed:', response.message);
                showBalanceError(response.message);
                enableProductSection(false);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            $('#balanceLoading').removeClass('show');
            showBalanceError('Failed to load balance. Please try again.');
            enableProductSection(false);
        }
    });
}

// ============================================
// Display Balance Information
// ============================================
function displayBalanceInfo(data) {
    const availableBalance = parseFloat(data.available_balance);
    const totalPaid = parseFloat(data.total_paid);
    const totalAdjusted = parseFloat(data.total_adjusted);
    const paymentCount = parseInt(data.payment_count);

    // Update balance display
    $('#balanceAmount').text('₹ ' + formatCurrency(availableBalance));
    $('#totalPaid').text('₹ ' + formatCurrency(totalPaid));
    $('#totalAdjusted').text('₹ ' + formatCurrency(totalAdjusted));
    $('#paymentCount').text(paymentCount);

    // Update status badge
    const statusElement = $('#balanceStatus');
    if (availableBalance > 0) {
        statusElement.removeClass('zero').addClass('sufficient');
        statusElement.html('✓ Ready to create invoice');
    } else {
        statusElement.removeClass('sufficient').addClass('zero');
        statusElement.html('✗ No balance available');
    }

    // Show balance card
    $('#balanceDisplay').removeClass('hidden');
    $('#balanceError').addClass('hidden');
}

// ============================================
// Show Balance Error
// ============================================
function showBalanceError(message) {
    $('#balanceDisplay').addClass('hidden');
    $('#balanceError').removeClass('hidden');
    
    if (message) {
        $('#balanceError').find('p').text(message);
    }
}

// ============================================
// Reset Balance Display
// ============================================
function resetBalanceDisplay() {
    $('#balanceDisplay').addClass('hidden');
    $('#balanceError').addClass('hidden');
    $('#balanceLoading').removeClass('show');
    customerBalanceData = null;
}

// ============================================
// Enable/Disable Product Section
// ============================================
function enableProductSection(enable) {
    const productSection = $('#productSection');
    const productSelect = $('#productSelect');
    const qtyInput = $('#qty');
    const amountInput = $('#amount');
    const discountPercentInput = $('#discountpercentae');
    const addButton = $('#addProductBtn');

    if (enable) {
        productSelect.prop('disabled', false);
        qtyInput.prop('disabled', false);
        amountInput.prop('disabled', false);
        discountPercentInput.prop('disabled', false);
        addButton.prop('disabled', false);
        productSection.css('opacity', '1');
    } else {
        productSelect.prop('disabled', true).val('');
        qtyInput.prop('disabled', true).val('');
        amountInput.prop('disabled', true).val('');
        discountPercentInput.prop('disabled', true).val('');
        addButton.prop('disabled', true);
        productSection.css('opacity', '0.6');
    }
}

// ============================================
// Calculate Product Total
// ============================================
function calculateTotal() {
    const qty = parseFloat($('#qty').val()) || 0;
    const amount = parseFloat($('#amount').val()) || 0;
    const total = qty * amount;

    $('#output').val(total.toFixed(2));
    calculateDiscount();
}

// ============================================
// Calculate Discount
// ============================================
function calculateDiscount() {
    const total = parseFloat($('#output').val()) || 0;
    const discountPercent = parseFloat($('#discountpercentae').val()) || 0;
    const discountAmount = (total * discountPercent / 100).toFixed(2);

    $('#discountamount').val(discountAmount);

    // If advance payment is mandatory, validate against balance
    if (IS_ADVANCE_MANDATORY && customerBalanceData) {
        validateProductTotal(total - discountAmount);
    }
}

// ============================================
// Validate Product Total Against Balance
// ============================================
function validateProductTotal(productTotal) {
    if (!customerBalanceData) return;

    const availableBalance = parseFloat(customerBalanceData.available_balance);

    // Remove any existing validation message
    $('#productValidation').remove();

    if (productTotal > availableBalance) {
        const shortage = productTotal - availableBalance;
        const message = `
            <div id="productValidation" class="balance-validation insufficient mt-3">
                <i class="material-icons-outlined icon">error</i>
                <div>
                    <strong>Insufficient Balance</strong><br>
                    Product total: ₹${formatCurrency(productTotal)} | 
                    Available: ₹${formatCurrency(availableBalance)} | 
                    Shortage: ₹${formatCurrency(shortage)}
                </div>
            </div>
        `;
        $('.product-entry-row').after(message);
        $('#addProductBtn').prop('disabled', true);
    } else if (productTotal > 0) {
        const remaining = availableBalance - productTotal;
        const message = `
            <div id="productValidation" class="balance-validation sufficient mt-3">
                <i class="material-icons-outlined icon">check_circle</i>
                <div>
                    <strong>✓ Sufficient Balance</strong><br>
                    Product total: ₹${formatCurrency(productTotal)} | 
                    Remaining after: ₹${formatCurrency(remaining)}
                </div>
            </div>
        `;
        $('.product-entry-row').after(message);
        $('#addProductBtn').prop('disabled', false);
    } else {
        $('#addProductBtn').prop('disabled', false);
    }
}

// ============================================
// Validate Invoice Submission
// ============================================
function validateInvoiceSubmission(e) {
    if (!customerBalanceData) {
        e.preventDefault();
        alert('Please select a customer first.');
        return false;
    }

    const availableBalance = parseFloat(customerBalanceData.available_balance);
    
    if (availableBalance <= 0) {
        e.preventDefault();
        alert('Cannot create invoice. Customer has no advance payment balance.');
        return false;
    }

    // Additional validation can be added here for invoice total vs balance
    return true;
}

// ============================================
// Load Product Price via AJAX
// ============================================
function showPrice(productId) {
    if (!productId) {
        $('#txtHintPrice').html('<label class="form-label">Price</label><input type="number" name="amount" id="amount" class="form-control" min="0" step="0.01" placeholder="Price" onkeyup="calculateTotal()">');
        return;
    }

    $.ajax({
        url: 'loadPrice.php',
        type: 'GET',
        data: {
            q: productId,
            invuser: INVOICE_USER_TYPE
        },
        success: function(response) {
            // Wrap response with modern styling
            const styledResponse = '<label class="form-label">Price</label>' + response.replace(
                '<input',
                '<input class="form-control" min="0" step="0.01"'
            );
            $('#txtHintPrice').html(styledResponse);
            
            // Reattach event handler
            $('#amount').on('keyup', calculateTotal);
        },
        error: function() {
            console.error('Failed to load price');
        }
    });
}

// ============================================
// Check Stock Availability
// ============================================
function showstockavailable(customerId) {
    if (!customerId) {
        $('#txtHintstock').html('');
        return;
    }

    $.ajax({
        url: 'loadstockcheck.php',
        type: 'GET',
        data: {
            q: customerId,
            invuser: INVOICE_USER_TYPE
        },
        success: function(response) {
            $('#txtHintstock').html(response);
        },
        error: function() {
            console.error('Failed to load stock info');
        }
    });
}

// ============================================
// Check Invoice Number Duplicate
// ============================================
function showInvoiceDuplicate(invNumber) {
    if (!invNumber) {
        $('#txtHintInvoice').html('');
        return;
    }

    $.ajax({
        url: 'loadInvoiceNumberUSER.php',
        type: 'GET',
        data: { q: invNumber },
        success: function(response) {
            $('#txtHintInvoice').html(response);
        },
        error: function() {
            console.error('Failed to check invoice number');
        }
    });
}

// ============================================
// Check Opening Stock
// ============================================
function checkOpeningStock(godownId) {
    if (!godownId) {
        $('#opstock').html('');
        return;
    }

    $.ajax({
        url: 'loadopeningstock.php',
        type: 'GET',
        data: { q: godownId },
        success: function(response) {
            $('#opstock').html(response);
        },
        error: function() {
            console.error('Failed to load opening stock info');
        }
    });
}

// ============================================
// Utility: Format Currency
// ============================================
function formatCurrency(amount) {
    return parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// ============================================
// Utility: Restrict Special Characters
// ============================================
function restrictSpecialChars(event) {
    const regex = new RegExp("^[a-zA-Z0-9-_/ ]+$");
    const key = String.fromCharCode(!event.charCode ? event.which : event.charCode);
    if (!regex.test(key)) {
        event.preventDefault();
        return false;
    }
    return true;
}

// ============================================
// Console Logging for Debugging
// ============================================
console.log('Invoice management scripts loaded successfully');
console.log('User type:', INVOICE_USER_TYPE);
console.log('Advance payment mandatory:', IS_ADVANCE_MANDATORY);
</script>
