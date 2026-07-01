<style>
/* ============================================
   Femi9 Billing - Invoice Management Styles
   Modern, Clean, Responsive Design
   ============================================ */

:root {
    --primary-color: #3b82f6;
    --primary-dark: #2563eb;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --bg-light: #f9fafb;
    --text-dark: #1f2937;
    --text-gray: #6b7280;
    --border-color: #e5e7eb;
    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
}

/* ============================================
   Card Components
   ============================================ */
.invoice-card {
    background: white;
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    padding: 28px;
    margin-bottom: 24px;
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
}

.invoice-card:hover {
    box-shadow: var(--shadow-md);
}

/* ============================================
   Step-based Form Layout
   ============================================ */
.invoice-step {
    margin-bottom: 32px;
}

.step-header {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--border-color);
}

.step-number {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    margin-right: 16px;
    flex-shrink: 0;
}

.step-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
}

.step-content {
    padding-left: 56px;
}

@media (max-width: 768px) {
    .step-content {
        padding-left: 0;
    }
}

/* ============================================
   Balance Display Card - Premium Design
   ============================================ */
.balance-display-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 16px;
    padding: 28px;
    margin: 24px 0;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.35);
    transition: all 0.3s ease;
}

.balance-display-card.hidden {
    display: none;
}

.balance-display-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(102, 126, 234, 0.45);
}

.balance-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 500;
    opacity: 0.95;
    margin-bottom: 12px;
}

.balance-header .material-icons-outlined {
    font-size: 20px;
}

.balance-amount {
    font-size: 42px;
    font-weight: 800;
    margin: 12px 0;
    letter-spacing: -0.5px;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.balance-breakdown {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    font-size: 14px;
    opacity: 0.92;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.balance-breakdown > div {
    display: flex;
    flex-direction: column;
}

.balance-breakdown strong,
.balance-breakdown span {
    font-weight: 600;
}

.balance-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 24px;
    font-size: 13px;
    font-weight: 600;
    margin-top: 16px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
}

.balance-status.sufficient {
    background: rgba(16, 185, 129, 0.25);
    border: 1px solid rgba(16, 185, 129, 0.4);
}

.balance-status.zero {
    background: rgba(239, 68, 68, 0.25);
    border: 1px solid rgba(239, 68, 68, 0.4);
}

/* ============================================
   Alert Boxes - Advanced Styling
   ============================================ */
.alert-advance-payment {
    background: #fef3c7;
    border-left: 4px solid var(--warning-color);
    padding: 18px 20px;
    border-radius: 10px;
    margin: 20px 0;
    box-shadow: var(--shadow-sm);
}

.alert-advance-payment.error {
    background: #fee2e2;
    border-left-color: var(--danger-color);
}

.alert-advance-payment.success {
    background: #d1fae5;
    border-left-color: var(--success-color);
}

.alert-advance-payment.hidden {
    display: none;
}

.alert-advance-payment strong {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.alert-advance-payment .material-icons-outlined {
    font-size: 20px;
}

.alert-advance-payment p {
    margin: 8px 0;
    line-height: 1.6;
}

/* ============================================
   Form Controls - Enhanced
   ============================================ */
.form-label {
    font-weight: 500;
    color: var(--text-dark);
    margin-bottom: 8px;
    display: block;
    font-size: 14px;
}

.form-label.required::after {
    content: " *";
    color: var(--danger-color);
    font-weight: 700;
}

.form-control,
.form-select {
    border-radius: 8px;
    border: 1.5px solid var(--border-color);
    padding: 11px 14px;
    transition: all 0.3s ease;
    font-size: 14px;
    background-color: white;
}

.form-control:focus,
.form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
    outline: none;
    background-color: white;
}

.form-control:disabled {
    background-color: #f3f4f6;
    cursor: not-allowed;
    opacity: 0.7;
}

.form-control::placeholder {
    color: #9ca3af;
}

/* ============================================
   Product Entry Section
   ============================================ */
.product-entry-row {
    background: var(--bg-light);
    padding: 24px;
    border-radius: 12px;
    margin: 20px 0;
    border: 1px solid var(--border-color);
}

.product-input-group {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

.discount-input-group {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
}

@media (max-width: 992px) {
    .product-input-group {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 576px) {
    .product-input-group,
    .discount-input-group {
        grid-template-columns: 1fr;
    }
}

/* ============================================
   Balance Validation Indicator
   ============================================ */
.balance-validation {
    padding: 14px 18px;
    border-radius: 10px;
    margin: 20px 0;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
    box-shadow: var(--shadow-sm);
}

.balance-validation.sufficient {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
}

.balance-validation.insufficient {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #ef4444;
}

.balance-validation .icon {
    font-size: 24px;
}

/* ============================================
   Buttons - Modern Gradient Design
   ============================================ */
.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 12px 28px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.25);
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

.btn-primary:active:not(:disabled) {
    transform: translateY(0);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    color: white;
    box-shadow: 0 4px 8px rgba(16, 185, 129, 0.25);
}

.btn-success:hover {
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    border: none;
    color: white;
    box-shadow: 0 4px 8px rgba(239, 68, 68, 0.25);
}

.btn-danger:hover {
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
}

/* ============================================
   Loading States
   ============================================ */
.spinner {
    display: inline-block;
    width: 18px;
    height: 18px;
    border: 3px solid rgba(59, 130, 246, 0.3);
    border-top-color: var(--primary-color);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.loading-text {
    display: none;
    color: var(--primary-color);
    font-size: 14px;
    font-weight: 500;
    margin-top: 12px;
    padding: 12px;
    background: #eff6ff;
    border-radius: 8px;
    text-align: center;
}

.loading-text.show {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

/* ============================================
   Invoice Summary Table
   ============================================ */
.invoice-summary-table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    margin-top: 28px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
}

.invoice-summary-table table {
    width: 100%;
    margin-bottom: 0;
}

.invoice-summary-table thead th {
    background: var(--bg-light);
    font-weight: 600;
    padding: 14px 16px;
    border-bottom: 2px solid var(--border-color);
    color: var(--text-dark);
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.invoice-summary-table tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.invoice-summary-table tbody tr:last-child td {
    border-bottom: none;
}

.invoice-summary-table tbody tr:hover {
    background: var(--bg-light);
}

/* ============================================
   Badge Styling
   ============================================ */
.badge-custom {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.badge-info {
    background: #dbeafe;
    color: #1e40af;
}

.badge-success {
    background: #d1fae5;
    color: #065f46;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}

/* ============================================
   Help Text
   ============================================ */
.help-text {
    font-size: 13px;
    color: var(--text-gray);
    margin-top: 6px;
    display: block;
    line-height: 1.4;
}

/* ============================================
   Utility Classes
   ============================================ */
.hidden {
    display: none !important;
}

.text-muted {
    color: var(--text-gray);
}

.mb-0 {
    margin-bottom: 0 !important;
}

.mt-2 {
    margin-top: 0.5rem !important;
}

.mt-3 {
    margin-top: 1rem !important;
}

.mt-4 {
    margin-top: 1.5rem !important;
}

/* ============================================
   Responsive Tweaks
   ============================================ */
@media (max-width: 768px) {
    .invoice-card {
        padding: 20px;
    }

    .balance-amount {
        font-size: 32px;
    }

    .step-title {
        font-size: 18px;
    }

    .balance-breakdown {
        flex-direction: column;
        gap: 12px;
    }
}
</style>
