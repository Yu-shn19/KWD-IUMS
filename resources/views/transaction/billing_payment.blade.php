<!DOCTYPE html>
<html lang="en">
@include('partials.header')

@php
    $today = now()->format('Y-m-d');
    $defaultBillMonth = now()->format('m-Y');
@endphp

<style>
    .payment-shell {
        background: #f3f4f6;
        min-height: calc(100vh - 4rem);
    }
    .payment-card {
        border-radius: 0.85rem;
    }
    .payment-card .card-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        background: linear-gradient(135deg, rgba(78, 115, 223, 0.08), rgba(63, 103, 199, 0.18));
        border-top-left-radius: 0.85rem;
        border-top-right-radius: 0.85rem;
    }
    .form-section-title {
        font-size: 0.8rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #6b7280;
        font-weight: 600;
        margin-bottom: 0.4rem;
    }
    .account-box {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1.5rem;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.4);
    }
    .account-box .input-group-text {
        font-weight: 600;
        background: #f9fafb;
    }
    .divider-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #9ca3af;
        font-weight: 600;
        margin: 1.25rem 0 0.75rem;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 0.35rem;
    }
    .payment-grid {
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        overflow: hidden;
    }
    .payment-grid table {
        margin: 0;
    }
    .payment-grid thead th {
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
        font-size: 0.85rem;
        font-weight: 600;
        color: #4b5563;
    }
    .payment-grid td {
        vertical-align: middle;
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }
    .payment-grid input[type="number"] {
        text-align: right;
    }
    /* Hide number input spinner arrows */
    input[type="number"]::-webkit-outer-spin-button,
    input[type="number"]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    input[type="number"] {
        -moz-appearance: textfield;
    }
    .payment-grid tfoot td {
        background: #f9fafb;
        font-weight: 700;
        color: #1f2937;
    }
    .totals-strip {
        background: #111827;
        color: #f9fafb;
        border-radius: 0.75rem;
    }
    .totals-strip label {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .totals-strip input {
        background: transparent;
        color: inherit;
        border: none;
        font-size: 1.3rem;
        font-weight: 600;
    }
    .totals-strip input:focus {
        box-shadow: none;
    }
    .btn-soft {
        border-radius: 999px;
        padding: 0.65rem 1.5rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        font-size: 0.75rem;
    }
    .btn-soft-primary {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #ffffff;
        border: none;
    }
    .btn-soft-secondary {
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #e5e7eb;
    }
    .sc-discount-ledger-text {
        display: inline-flex;
        align-items: center;
        border: 1px solid #dc3545;
        color: #dc3545;
        background: transparent;
        border-radius: 0.2rem;
        padding: 0.24rem 0.5rem;
        margin-left: 0.5rem;
        font-size: 0.8rem;
        font-weight: 600;
        line-height: 1.4;
        white-space: nowrap;
    }
    .signature-box {
        height: 70px;
        border-bottom: 1px solid #d1d5db;
    }
    .senior-discount-checkbox {
        margin-right: 0.5rem;
        cursor: pointer;
    }
    .senior-discount-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        margin-top: 2px;
    }
    .senior-discount-label {
        cursor: pointer;
        user-select: none;
    }

    /* Ledger modal */
    .ledger-modal-iframe {
        width: 100%;
        height: 80vh;
        border: 0;
    }

    /* SUNDRIES Acct Code dropdown */
    .bam-acct-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1000;
        background: #fff;
        border: 1px solid #dddddd;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        max-height: 260px;
        overflow-y: auto;
        margin-top: 2px;
    }
    .bam-acct-option {
        padding: 0.5rem 0.75rem;
        cursor: pointer;
        font-size: 0.9rem;
        border-bottom: 1px solid #eee;
    }
    .bam-acct-option:last-child {
        border-bottom: none;
    }
    .bam-acct-option:hover {
        background: #E8F0FE;
    }
    .bam-acct-option:first-child {
        color: #888;
    }
    
    /* Account Number / Account Name field styling */
    #accountNumber {
        font-size: 1.1rem;
        padding: 0.75rem 1rem;
        padding-right: 3rem; /* Space for icon */
        border: 2px solid #e5e7eb;
        border-radius: 0.5rem;
        transition: all 0.3s ease;
        background: linear-gradient(to bottom, #ffffff, #fafbfc);
    }
    #accountNumber:focus {
        border-color: #4e73df;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        background: #ffffff;
        outline: none;
    }
    #accountNumber::placeholder {
        color: #9ca3af;
        font-weight: 400;
    }
    
    /* Account suggestions dropdown */
    #accountSuggestions {
        margin-top: 4px;
        border: 2px solid #4e73df;
        border-radius: 0.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        overflow: hidden;
    }
    #accountSuggestions .list-group-item {
        padding: 0.75rem 1rem;
        border: none;
        border-bottom: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }
    #accountSuggestions .list-group-item:hover {
        background: linear-gradient(135deg, #f0f4ff, #e8f0fe);
        transform: translateX(2px);
        border-left: 3px solid #4e73df;
    }
    #accountSuggestions .list-group-item:last-child {
        border-bottom: none;
    }
    #accountSuggestions .list-group-item strong {
        color: #1f2937;
        font-size: 0.95rem;
        letter-spacing: 0.02em;
    }
    #accountSuggestions .list-group-item .text-muted {
        color: #6b7280;
        font-size: 0.85rem;
    }
    
    /* Account label styling above Current Balance */
    #currentBalanceAccountLabel {
        display: flex;
        align-items: center;
        padding: 0.6rem 0.9rem;
        background: linear-gradient(135deg, rgba(78, 115, 223, 0.12), rgba(63, 103, 199, 0.18));
        border-left: 4px solid #4e73df;
        border-radius: 0.5rem;
        font-weight: 600;
        color: #1f2937;
        font-size: 0.95rem;
        letter-spacing: 0.02em;
        margin-bottom: 0.75rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }
    #currentBalanceAccountLabel:empty {
        display: none;
    }
    #currentBalanceAccountLabel::before {
        content: '\f2bb';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        color: #4e73df;
        margin-right: 0.65rem;
        font-size: 1rem;
        opacity: 0.9;
    }
    #currentBalanceAccountLabel:hover {
        background: linear-gradient(135deg, rgba(78, 115, 223, 0.15), rgba(63, 103, 199, 0.22));
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
        transform: translateX(2px);
    }
    
    
    /* Account address styling (below name, same look as name) */
    #currentBalanceAccountAddress {
        display: flex;
        align-items: center;
        padding: 0.6rem 0.9rem;
        background: linear-gradient(135deg, rgba(78, 115, 223, 0.12), rgba(63, 103, 199, 0.18));
        border-left: 4px solid #4e73df;
        border-radius: 0.5rem;
        font-weight: 600;
        color: #1f2937;
        font-size: 0.95rem;
        letter-spacing: 0.02em;
        margin-bottom: 0.75rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }
    #currentBalanceAccountAddress:empty {
        display: none;
    }
    #currentBalanceAccountAddress::before {
        content: '\f3c5';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        color: #4e73df;
        margin-right: 0.65rem;
        font-size: 1rem;
        opacity: 0.9;
    }
    #currentBalanceAccountAddress:hover {
        background: linear-gradient(135deg, rgba(78, 115, 223, 0.15), rgba(63, 103, 199, 0.22));
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
        transform: translateX(2px);
    }
</style>

<body id="page-top">
    <div id="wrapper">
        @include('partials.sidebar')
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('partials.navbar')

                <div class="container-fluid py-4 payment-shell">
                    <div class="card shadow payment-card">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="mb-1 text-primary text-uppercase fw-bold">Payments and Collections</h5>
                                <small class="text-muted">Record official receipts and update customer ledger balances.</small>
                            </div>
                            <div class="d-flex flex-column flex-md-row gap-2">
                                <div class="form-inline">
                                    <label class="mr-2 text-muted">Bill Month</label>
                                    <input type="text" class="form-control form-control-sm text-center font-weight-bold" id="billMonth" value="{{ $defaultBillMonth }}">
                                </div>
                                <div class="form-inline mt-2 mt-md-0 ml-md-3">
                                    <label class="mr-2 text-muted">Date</label>
                                    <input type="date" class="form-control form-control-sm text-center font-weight-bold" id="transactionDate" value="{{ $today }}">
                                </div>
                                <div class="form-inline mt-2 mt-md-0 ml-md-3">
                                    <label class="mr-2 text-muted">OR #</label>
                                    <input type="text" class="form-control form-control-sm text-center font-weight-bold" id="officialReceipt" placeholder="Enter OR # to search">
                                </div>
                                <div class="form-inline mt-2 mt-md-0 ml-md-3 d-flex align-items-center">
                                    <label class="mr-2 text-muted mb-0">Payment status (this month)</label>
                                    <span id="paymentStatusForMonth" class="badge badge-secondary px-3 py-2" style="font-size: 0.8rem;">—</span>
                                </div>
                            </div>
                        </div>

                        <form id="paymentCollectionForm">
                            @csrf
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-5">
                                        <div class="account-box mb-4">
                                            <div class="form-section-title">Account Information</div>
                                            <div class="form-group" style="position: relative;">
                                                <label class="text-muted small mb-2 d-flex align-items-center" style="font-weight: 600; color: #4b5563;">
                                                    <i class="fas fa-search mr-2" style="color: #4e73df;"></i>
                                                    Account Number / Account Name
                                                </label>
                                                <div class="position-relative">
                                                    <input type="text" class="form-control text font-weight-bold" id="accountNumber" placeholder="Type account number or account name (e.g., 011)" autocomplete="off">
                                                    <i class="fas fa-user-circle position-absolute" style="right: 1rem; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none;"></i>
                                                </div>
                                                <div id="accountSuggestions" class="list-group" style="position: absolute; z-index: 1000; width: 100%; max-height: 300px; overflow-y: auto; display: none;">
                                                </div>
                                                <small id="accountLookupStatus" class="d-block mt-2 small text-muted d-none" aria-hidden="true">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Enter account number or account name to load billing data.
                                                </small>
                                            </div>
                                        
                                            <div class="form-group">
                                                <div id="currentBalanceAccountRow" style="display: none;">
                                                    <div id="currentBalanceAccountLabel" class="current-balance-account-label"></div>
                                                    <div id="currentBalanceAccountAddress" class="current-balance-account-address"></div>
                                                </div>
                                                <small class="text-muted d-block mb-2">Current Balance: <span class="font-weight-bold text-danger" id="currentBalanceDisplay">₱ 0.00</span></small>
                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-outline-primary btn-sm" id="viewLedgerBtn" title="View Account Ledger" disabled>
                                                        <i class="fas fa-file-invoice mr-1"></i>View Ledger
                                                    </button>
                                                    <span id="scDiscountLedgerText" class="sc-discount-ledger-text d-none"></span>
                                                </div>
                                                <input type="hidden" id="currentBalance" value="₱ 0.00">
                                            </div>
                                            
                            

                                            <div class="divider-label">Payment Details</div>
                                            <div class="form-group">
                                                <label class="text-muted small mb-1" for="paymentType">Type <span class="text-danger">*</span></label>
                                                <select class="form-control" id="paymentType" name="paymentType" required>
                                                    <option value="" disabled selected>Select payment type</option>
                                                    <option value="cash">Cash</option>
                                                    <option value="check">Check</option>
                                                    <option value="gcash">GCash</option>
                                                    <option value="bank">Bank Transfer</option>
                                                    <option value="palawan">Palawan Pay-OTC</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label class="text-muted small mb-1">Payment Amount</label>
                                                <input type="text" class="form-control text-right font-weight-bold" id="paymentAmount" value="₱ 0.00" readonly>
                                            </div>
                                            <div class="form-group">
                                                <label class="text-muted small mb-1">Remarks</label>
                                                <textarea class="form-control" rows="3" id="paymentRemarks" placeholder="Optional notes..."></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-7">
                                        <div class="form-section-title">Payment Breakdown</div>
                                        <div class="payment-grid mb-4">
                                            <table class="table table-borderless mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Particulars</th>
                                                        <th class="text-right" style="width: 160px;">Amount (₱)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td>Current Bill</td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right" id="fieldCurrentBill" data-charge value="216.60"></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Arrears — Current Year</td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right" id="fieldArrearsCurrent" data-charge value="0.00"></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Arrears — Previous Year</td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right" id="fieldArrearsPrevious" data-charge value="0.00"></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Penalty</td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right" id="fieldPenalty" data-charge value="0.00"></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Water Maintenance Charge</td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right" id="fieldMaintenance" data-charge value="0.00"></td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="senior-discount-checkbox">
                                                                    <input type="checkbox" id="enableSeniorDiscount">
                                                                </div>
                                                                <label for="enableSeniorDiscount" class="senior-discount-label mb-0">
                                                                    Sr. Citizen Discount <small class="text-muted">(5% of Current Bill)</small>
                                                                </label>
                                                            </div>
                                                        </td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right" id="fieldSeniorDiscount" data-discount value="0.00" placeholder="0.00" readonly></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Advances</td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right" id="fieldAdvances" data-charge value="20.00"></td>
                                                    </tr>
                                                    
                                                    <tr>
                                                        <td colspan="2" style="padding-top: 1rem; padding-bottom: 0.5rem;">
                                                            <div class="d-flex align-items-center">
                                                                <span class="text-danger font-weight-bold mr-2">F11</span>
                                                                <span class="font-weight-bold">SUNDRIES:</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0.5rem;">
                                                            <div style="position: relative;">
                                                                <input type="hidden" name="sundry_acct_code_1" id="sundryAcctCode1" value="">
                                                                <input type="hidden" id="sundryBamNo1" value="">
                                                                <input type="text" class="form-control form-control-sm" id="sundryAcctCodeDisplay1" readonly placeholder="— Select Acct Code —" style="cursor: pointer; background-color: #fff; font-size: 0.875rem;">
                                                                <div id="sundryAcctCodeDropdown1" class="bam-acct-dropdown" style="display: none;">
                                                                    <div class="bam-acct-option" data-code="" data-desc="">— Select Acct Code —</div>
                                                                    <div class="bam-acct-option" data-code="19901020" data-desc="Advances for Payroll">19901020 Advances for Payroll</div>
                                                                    <div class="bam-acct-option" data-code="19901030" data-desc="Advances to Special Disbursing Offices">19901030 Advances to Special Disbursing Offices</div>
                                                                    <div class="bam-acct-option" data-code="19901040" data-desc="Advances to Officers and Employees">19901040 Advances to Officers and Employees</div>
                                                                    <div class="bam-acct-option" data-code="20102040" data-desc="Loans Payable-Domestic - Non-Current">20102040 Loans Payable-Domestic - Non-Current</div>
                                                                    <div class="bam-acct-option" data-code="20401090" data-desc="Customer's Deposit Payable">20401090 Customer's Deposit Payable</div>
                                                                    <div class="bam-acct-option" data-code="40201990" data-desc="Other Service Income">40201990 Other Service Income</div>
                                                                    <div class="bam-acct-option" data-code="40603990" data-desc="Miscellineous Income">40603990 Miscellineous Income</div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right" id="sundryAmount1" data-charge data-sundry value="0.00"></td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0.5rem;">
                                                            <div style="position: relative;">
                                                                <input type="hidden" name="sundry_acct_code_2" id="sundryAcctCode2" value="">
                                                                <input type="hidden" id="sundryBamNo2" value="">
                                                                <input type="text" class="form-control form-control-sm" id="sundryAcctCodeDisplay2" readonly placeholder="— Select Acct Code —" style="cursor: pointer; background-color: #fff; font-size: 0.875rem;">
                                                                <div id="sundryAcctCodeDropdown2" class="bam-acct-dropdown" style="display: none;">
                                                                    <div class="bam-acct-option" data-code="" data-desc="">— Select Acct Code —</div>
                                                                    <div class="bam-acct-option" data-code="19901020" data-desc="Advances for Payroll">19901020 Advances for Payroll</div>
                                                                    <div class="bam-acct-option" data-code="19901030" data-desc="Advances to Special Disbursing Offices">19901030 Advances to Special Disbursing Offices</div>
                                                                    <div class="bam-acct-option" data-code="19901040" data-desc="Advances to Officers and Employees">19901040 Advances to Officers and Employees</div>
                                                                    <div class="bam-acct-option" data-code="20102040" data-desc="Loans Payable-Domestic - Non-Current">20102040 Loans Payable-Domestic - Non-Current</div>
                                                                    <div class="bam-acct-option" data-code="20401090" data-desc="Customer's Deposit Payable">20401090 Customer's Deposit Payable</div>
                                                                    <div class="bam-acct-option" data-code="40201990" data-desc="Other Service Income">40201990 Other Service Income</div>
                                                                    <div class="bam-acct-option" data-code="40603990" data-desc="Miscellineous Income">40603990 Miscellineous Income</div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right" id="sundryAmount2" data-charge data-sundry value="0.00"></td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0.5rem;">
                                                            <div style="position: relative;">
                                                                <input type="hidden" name="sundry_acct_code_3" id="sundryAcctCode3" value="">
                                                                <input type="hidden" id="sundryBamNo3" value="">
                                                                <input type="text" class="form-control form-control-sm" id="sundryAcctCodeDisplay3" readonly placeholder="— Select Acct Code —" style="cursor: pointer; background-color: #fff; font-size: 0.875rem;">
                                                                <div id="sundryAcctCodeDropdown3" class="bam-acct-dropdown" style="display: none;">
                                                                    <div class="bam-acct-option" data-code="" data-desc="">— Select Acct Code —</div>
                                                                    <div class="bam-acct-option" data-code="19901020" data-desc="Advances for Payroll">19901020 Advances for Payroll</div>
                                                                    <div class="bam-acct-option" data-code="19901030" data-desc="Advances to Special Disbursing Offices">19901030 Advances to Special Disbursing Offices</div>
                                                                    <div class="bam-acct-option" data-code="19901040" data-desc="Advances to Officers and Employees">19901040 Advances to Officers and Employees</div>
                                                                    <div class="bam-acct-option" data-code="20102040" data-desc="Loans Payable-Domestic - Non-Current">20102040 Loans Payable-Domestic - Non-Current</div>
                                                                    <div class="bam-acct-option" data-code="20401090" data-desc="Customer's Deposit Payable">20401090 Customer's Deposit Payable</div>
                                                                    <div class="bam-acct-option" data-code="40201990" data-desc="Other Service Income">40201990 Other Service Income</div>
                                                                    <div class="bam-acct-option" data-code="40603990" data-desc="Miscellineous Income">40603990 Miscellineous Income</div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right" id="sundryAmount3" data-charge data-sundry value="0.00"></td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 0.5rem;">
                                                            <div style="position: relative;">
                                                                <input type="hidden" name="sundry_acct_code_4" id="sundryAcctCode4" value="">
                                                                <input type="hidden" id="sundryBamNo4" value="">
                                                                <input type="text" class="form-control form-control-sm" id="sundryAcctCodeDisplay4" readonly placeholder="— Select Acct Code —" style="cursor: pointer; background-color: #fff; font-size: 0.875rem;">
                                                                <div id="sundryAcctCodeDropdown4" class="bam-acct-dropdown" style="display: none;">
                                                                    <div class="bam-acct-option" data-code="" data-desc="">— Select Acct Code —</div>
                                                                    <div class="bam-acct-option" data-code="19901020" data-desc="Advances for Payroll">19901020 Advances for Payroll</div>
                                                                    <div class="bam-acct-option" data-code="19901030" data-desc="Advances to Special Disbursing Offices">19901030 Advances to Special Disbursing Offices</div>
                                                                    <div class="bam-acct-option" data-code="19901040" data-desc="Advances to Officers and Employees">19901040 Advances to Officers and Employees</div>
                                                                    <div class="bam-acct-option" data-code="20102040" data-desc="Loans Payable-Domestic - Non-Current">20102040 Loans Payable-Domestic - Non-Current</div>
                                                                    <div class="bam-acct-option" data-code="20401090" data-desc="Customer's Deposit Payable">20401090 Customer's Deposit Payable</div>
                                                                    <div class="bam-acct-option" data-code="40201990" data-desc="Other Service Income">40201990 Other Service Income</div>
                                                                    <div class="bam-acct-option" data-code="40603990" data-desc="Miscellineous Income">40603990 Miscellineous Income</div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right" id="sundryAmount4" data-charge data-sundry value="0.00"></td>
                                                    </tr>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td class="text-right">Subtotal</td>
                                                        <td><input type="text" class="form-control form-control-sm text-right font-weight-bold" id="fieldSubtotal" value="₱ 0.00" readonly></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-right">Overall Total</td>
                                                        <td><input type="text" class="form-control form-control-sm text-right font-weight-bold text-primary" id="fieldTotal" value="₱ 0.00" readonly></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>

                                        <div class="totals-strip px-4 py-3 mb-4">
                                            <div class="row align-items-center">
                                                <div class="col-sm-4">
                                                    <label>Cash Tendered</label>
                                                    <input type="number" step="0.01" min="0" class="form-control form-control-lg text-right bg-white text-dark" id="cashTendered" placeholder="Enter amount">
                                                </div>
                                                <div class="col-sm-4 mt-3 mt-sm-0">
                                                    <label>Change</label>
                                                    <input type="number" step="0.01" min="0" class="form-control form-control-lg text-right bg-white text-dark" id="cashChange" placeholder="0.00" readonly>
                                                </div>
                                                <div class="col-sm-4 mt-3 mt-sm-0 text-sm-right">
                                                    <label class="d-block">Total Payment</label>
                                                    <input type="text" class="form-control form-control-lg text-right text-dark" id="paymentTotal" value="₱ 0.00" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="text-muted small mb-1">Prepared By</label> <a href="#">{{ Auth::user()->first_name }} </a>
                                            <div class="signature-box"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="text-muted small mb-1">Received By</label>
                                            <div class="signature-box"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center">
                                <div class="text-muted small">
                                    <i class="fas fa-info-circle mr-1 text-primary"></i>
                                    <span id="formStatusText">Review all fields before saving. Totals are computed automatically.</span>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <button type="button" class="btn btn-outline-primary btn-soft mr-2" id="openBamSearchModalBtn" title="Search BAM No. in Sundries table">
                                        <i class="fas fa-search mr-1"></i>Search BAM No.
                                    </button>
                                    <button type="button" class="btn btn-soft-secondary mr-2" id="resetFormBtn">
                                        <i class="fas fa-trash mr-1"></i>Cancelled OR#
                                    </button>
                                    <button type="button" class="btn btn-danger btn-soft mr-2" id="deletePaymentBtn" disabled title="Delete payment by OR #">
                                        <i class="fas fa-trash mr-1"></i>Delete Payment
                                    </button>
                                    <button type="button" class="btn btn-warning btn-soft mr-2" id="updatePaymentBtn" disabled title="Load a record by OR # or account to update existing payment">
                                        <i class="fas fa-edit mr-1"></i>Update Payment
                                    </button>
                                    <button type="submit" class="btn btn-soft-primary" id="savePaymentBtn">
                                        <i class="fas fa-save mr-1"></i>Save Payment
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @include('partials.footer')
        </div>
    </div>

    <!-- Ledger Preview Modal -->
    <div class="modal fade" id="ledgerModal" tabindex="-1" role="dialog" aria-labelledby="ledgerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ledgerModalLabel">Account Ledger</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div id="ledgerLoading" class="d-flex align-items-center justify-content-center py-5">
                        <div class="spinner-border text-primary mr-2" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <span>Loading ledger...</span>
                    </div>
                    <iframe id="ledgerIframe" class="ledger-modal-iframe d-none"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <a id="openLedgerNewTab" href="#" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt mr-1"></i>Open in New Tab
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- PIN Modal for Delete Payment -->
    <div class="modal fade" id="paymentDeletePinModal" tabindex="-1" aria-labelledby="paymentDeletePinModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="paymentDeletePinModalLabel">
                        <i class="fas fa-lock me-2"></i>Enter PIN
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Enter the edit PIN to delete payment.</p>
                    <input type="password" class="form-control" id="paymentDeletePinInput" placeholder="PIN" autocomplete="off" maxlength="20">
                    <div class="invalid-feedback" id="paymentDeletePinError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="paymentDeletePinVerifyBtn"><i class="fas fa-check me-1"></i>Verify</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal for Search BAM No -->
    <div class="modal fade" id="bamSearchModal" tabindex="-1" aria-labelledby="bamSearchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="bamSearchModalLabel">
                        <i class="fas fa-search me-2"></i>Search BAM No.
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Enter BAM No. to search in the Sundries table.</p>
                    <input type="text" class="form-control" id="bamSearchInput" placeholder="BAM No." autocomplete="off" maxlength="50">
                    <div class="invalid-feedback" id="bamSearchError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="bamSearchVerifyBtn"><i class="fas fa-search me-1"></i>Search</button>
                </div>
            </div>
        </div>
    </div>

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const chargeInputs = document.querySelectorAll('[data-charge]');
            const discountInputs = document.querySelectorAll('[data-discount]');
            const subtotalField = document.getElementById('fieldSubtotal');
            const totalField = document.getElementById('fieldTotal');
            const paymentAmountField = document.getElementById('paymentAmount');
            const paymentTotalField = document.getElementById('paymentTotal');
            const cashTenderedField = document.getElementById('cashTendered');
            const cashChangeField = document.getElementById('cashChange');
            const resetButton = document.getElementById('resetFormBtn');
            const form = document.getElementById('paymentCollectionForm');
            const billMonthField = document.getElementById('billMonth');
            const transactionDateField = document.getElementById('transactionDate');
            const accountNumberField = document.getElementById('accountNumber');
            const accountNameField = document.getElementById('accountName');
            const paymentTypeField = document.getElementById('paymentType');
            const paymentRemarksField = document.getElementById('paymentRemarks');
            const lookupStatus = document.getElementById('accountLookupStatus');
            const lookupEndpoint = @json(route('billing-payment.lookup'));
            const accountSuggestionsEndpoint = @json(route('billing-payment.account-suggestions'));
            const bamLookupEndpoint = @json(route('billing-payment.bam-search'));
            const savePaymentEndpoint = @json(route('billing-processes.mark-paid'));
            const cancelledOrEndpoint = @json(route('billing-payment.cancelled-or'));
            const deletePaymentEndpoint = @json(route('billing-payment.delete'));
            const ledgerRoute = @json(route('ledger'));
            let currentLookupController = null;
            let lastLookupKey = null;
            let currentDownloadedId = null;
            let currentBalanceValue = 0;
            let latestBillMonth = null; // Store the latest/current bill month
            let isLoadingFromMonthSelector = false; // Flag to prevent populateFromLookup from overwriting penalty
            let arrearsPreviousManuallyEdited = false; // Flag to track if user manually edited Arrears — Previous Month
            
            const disableWheelStepChange = (input) => {
                if (!input || input.type !== 'number') return;
                input.addEventListener('wheel', (event) => {
                    // Prevent native number-input wheel increment/decrement.
                    event.preventDefault();
                }, { passive: false });
            };

            const wheelProtectedNumberInputs = [
                ...chargeInputs,
                ...discountInputs,
                cashTenderedField
            ];
            wheelProtectedNumberInputs.forEach(disableWheelStepChange);
            // Only include OR number in breakdown endpoint when user explicitly loaded by OR.
            let useOrForBreakdownLookup = false;
            // When true, keep breakdown exactly from the paid OR payload and prevent recompute/zero overwrite.
            let lockPaidOrBreakdown = false;
            const viewLedgerBtn = document.getElementById('viewLedgerBtn');
            const ledgerModal = $('#ledgerModal');
            const ledgerIframe = document.getElementById('ledgerIframe');
            const ledgerLoading = document.getElementById('ledgerLoading');
            const openLedgerNewTab = document.getElementById('openLedgerNewTab');
            const formStatusText = document.getElementById('formStatusText');
            const scDiscountLedgerText = document.getElementById('scDiscountLedgerText');
            
            // Search BAM No. inside SUNDRIES table (sundryBamNo1..4)
            const openBamSearchModalBtn = document.getElementById('openBamSearchModalBtn');
            const bamSearchModal = $('#bamSearchModal');
            const bamSearchInput = document.getElementById('bamSearchInput');
            const bamSearchVerifyBtn = document.getElementById('bamSearchVerifyBtn');
            const bamSearchError = document.getElementById('bamSearchError');
            let bamSearchAccountName = null; // Stores the name from BAM "Others" search (no real consumer)

            const clearBamHits = () => {
                for (let i = 1; i <= 4; i++) {
                    const acctDisplay = document.getElementById('sundryAcctCodeDisplay' + i);
                    const row = acctDisplay ? acctDisplay.closest('tr') : null;
                    if (row) row.classList.remove('bam-search-hit');
                }
            };

            const searchBamNoInTable = (query) => {
                const q = String(query || '').trim();
                clearBamHits();
                if (!q) {
                    if (formStatusText) formStatusText.textContent = 'Enter a BAM No. to search in the Sundries table.';
                    return;
                }

                let firstHitRow = null;
                let hitCount = 0;
                for (let i = 1; i <= 4; i++) {
                    const bamHidden = document.getElementById('sundryBamNo' + i);
                    const bamVal = bamHidden ? String(bamHidden.value || '').trim() : '';
                    if (!bamVal) continue;

                    if (bamVal.toLowerCase().includes(q.toLowerCase())) {
                        const acctDisplay = document.getElementById('sundryAcctCodeDisplay' + i);
                        const row = acctDisplay ? acctDisplay.closest('tr') : null;
                        if (row) {
                            if (!firstHitRow) firstHitRow = row;
                            hitCount++;
                        }
                    }
                }

                if (firstHitRow) {
                    firstHitRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (formStatusText) formStatusText.textContent = `Found ${hitCount} Sundries row(s) matching BAM No. "${q}".`;
                } else {
                    if (formStatusText) formStatusText.textContent = `No Sundries rows found matching BAM No. "${q}".`;
                }
            };

            const setBamSearchError = (msg) => {
                if (!bamSearchError || !bamSearchInput) return;
                bamSearchError.textContent = msg || '';
                if (msg) {
                    bamSearchError.classList.add('d-block');
                    bamSearchInput.classList.add('is-invalid');
                } else {
                    bamSearchError.classList.remove('d-block');
                    bamSearchInput.classList.remove('is-invalid');
                }
            };
            
            const formatLongDate = (raw) => {
                if (!raw) return '';
                const d = new Date(raw);
                if (Number.isNaN(d.getTime())) return '';
                return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
            };

            const renderScDiscountLedgerText = (account) => {
                if (!scDiscountLedgerText) return;
                const rawPercent = String(account?.bill_disc_percent ?? '').trim();
                const normalizedPercent = rawPercent.toUpperCase();
                const percentNum = parseFloat(rawPercent);
                const isSc = normalizedPercent === 'SC DISCOUNT'
                    || (Number.isFinite(percentNum) && Math.abs(percentNum - 5) < 0.001);
                const oscaId = String(account?.osca_id_no ?? account?.osca_id ?? '').trim();
                if (!isSc || !oscaId) {
                    scDiscountLedgerText.textContent = '';
                    scDiscountLedgerText.classList.add('d-none');
                    return;
                }
                const dateDisplay = formatLongDate(account?.bill_disc_updated_at);
                scDiscountLedgerText.textContent = dateDisplay ? `${oscaId} - ${dateDisplay}` : oscaId;
                scDiscountLedgerText.classList.remove('d-none');
            };

            if (openBamSearchModalBtn) {
                openBamSearchModalBtn.addEventListener('click', function() {
                    setBamSearchError('');
                    if (bamSearchInput) bamSearchInput.value = '';
                    bamSearchModal.modal('show');
                    setTimeout(() => { if (bamSearchInput) bamSearchInput.focus(); }, 150);
                });
            }
            if (bamSearchInput) {
                bamSearchInput.addEventListener('input', () => setBamSearchError(''));
                bamSearchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (bamSearchVerifyBtn) bamSearchVerifyBtn.click();
                    }
                });
            }
            if (bamSearchVerifyBtn) {
                bamSearchVerifyBtn.addEventListener('click', function() {
                    const q = bamSearchInput ? String(bamSearchInput.value || '').trim() : '';
                    if (!q) {
                        setBamSearchError('Enter BAM No.');
                        return;
                    }
                    // Fetch from LRO Ledger (lro_ledger) by BAM No and populate account + sundries
                    fetch(bamLookupEndpoint + '?bam_no=' + encodeURIComponent(q), {
                        method: 'GET',
                        credentials: 'include',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(r => r.json().then(data => ({ ok: r.ok, data })))
                    .then(result => {
                        if (!result.ok || !result.data || !result.data.success) {
                            const msg = (result.data && result.data.message) ? result.data.message : 'Unable to fetch BAM No.';
                            setBamSearchError(msg);
                            bamSearchModal.modal('show');
                            return;
                        }

                        const payload = result.data.data || {};
                        const account = payload.account || {};
                        const paymentInfo = payload.payment || {};
                        const sundries = Array.isArray(payload.sundries) ? payload.sundries : [];

                        // Hard-refresh payment context for BAM mode so stale account/OR lookup state is cleared.
                        if (currentLookupController) {
                            currentLookupController.abort();
                            currentLookupController = null;
                        }
                        lastLookupKey = null;
                        currentDownloadedId = null;
                        latestBillMonth = null;
                        isLoadingFromMonthSelector = false;
                        arrearsPreviousManuallyEdited = false;
                        if (updatePaymentBtn) updatePaymentBtn.disabled = true;
                        if (viewLedgerBtn) viewLedgerBtn.disabled = true;

                        // Reset non-sundry payment fields before applying BAM payload.
                        if (typeof clearPaymentBreakdown === 'function') clearPaymentBreakdown();
                        const billMonthSelectorGroup = document.getElementById('billMonthSelectorGroup');
                        if (billMonthSelectorGroup) billMonthSelectorGroup.style.display = 'none';

                        // Reset current balance display/label for BAM search.
                        const currentBalanceField = document.getElementById('currentBalance');
                        const currentBalanceDisplay = document.getElementById('currentBalanceDisplay');
                        const currentBalanceAccountRow = document.getElementById('currentBalanceAccountRow');
                        const currentBalanceAccountLabel = document.getElementById('currentBalanceAccountLabel');
                        const currentBalanceAccountAddress = document.getElementById('currentBalanceAccountAddress');
                        currentBalanceValue = 0;
                        if (currentBalanceField) currentBalanceField.value = formatCurrency(0);
                        if (currentBalanceDisplay) currentBalanceDisplay.textContent = formatCurrency(0);
                        if (currentBalanceAccountLabel) {
                            currentBalanceAccountLabel.textContent = '';
                            currentBalanceAccountLabel.style.display = 'none';
                        }
                        if (currentBalanceAccountAddress) {
                            currentBalanceAccountAddress.textContent = '';
                            currentBalanceAccountAddress.style.display = 'none';
                        }
                        if (currentBalanceAccountRow) currentBalanceAccountRow.style.display = 'none';
                        renderScDiscountLedgerText(null);

                        // Set account field (for Others use `name` instead of `account`)
                        bamSearchAccountName = (account && account.name != null && String(account.name).trim() !== '')
                            ? String(account.name).trim()
                            : null;
                        if (accountNumberField) {
                            accountNumberField.value = (account && account.number != null && String(account.number).trim() !== '')
                                ? String(account.number).trim()
                                : (bamSearchAccountName || '');
                        }
                        if (accountNameField) accountNameField.value = bamSearchAccountName || '';

                        // Set Date + Bill Month from LRO date; reset OR# and status (BAM search is not OR lookup)
                        const lroDateRaw = (account && account.date != null) ? String(account.date || '').trim() : '';
                        if (lroDateRaw && transactionDateField) {
                            // Accept "YYYY-MM-DD" or full datetime; keep the date part
                            const datePart = lroDateRaw.substring(0, 10);
                            if (/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
                                transactionDateField.value = datePart;
                                if (billMonthField) {
                                    const y = datePart.substring(0, 4);
                                    const m = datePart.substring(5, 7);
                                    billMonthField.value = `${m}-${y}`;
                                }
                            }
                        }
                        // If BAM is already paid, auto-fill OR # and set status to Paid.
                        // If not paid, keep current OR as-is and show neutral status.
                        const officialReceiptField = document.getElementById('officialReceipt');
                        const paidOr = paymentInfo && paymentInfo.or_number != null ? String(paymentInfo.or_number || '').trim() : '';
                        const isPaid = !!(paymentInfo && paymentInfo.is_paid && paidOr);
                        if (isPaid && officialReceiptField) {
                            officialReceiptField.value = paidOr;
                            officialReceiptField.dispatchEvent(new Event('input', { bubbles: true }));
                            officialReceiptField.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        if (typeof setPaymentStatusForMonth === 'function') {
                            setPaymentStatusForMonth(isPaid ? 'paid' : null);
                        }

                        // Clear and populate sundries slots
                        clearBamHits();
                        for (let i = 1; i <= 4; i++) {
                            const acctHidden = document.getElementById('sundryAcctCode' + i);
                            const acctDisplay = document.getElementById('sundryAcctCodeDisplay' + i);
                            const bamHidden = document.getElementById('sundryBamNo' + i);
                            const amountField = document.getElementById('sundryAmount' + i);

                            if (acctHidden) acctHidden.value = '';
                            if (acctDisplay) {
                                acctDisplay.value = '';
                                acctDisplay.placeholder = '— Select Acct Code —';
                            }
                            if (bamHidden) bamHidden.value = '';
                            if (amountField) setNumberFieldValue(amountField, 0);

                            const entry = sundries[i - 1] || null;
                            if (entry && (entry.acct_code || entry.amount)) {
                                const code = String(entry.acct_code || '').trim();
                                const bamNo = String(entry.bam_no || q).trim();
                                const amount = entry.amount ?? 0;

                                if (acctHidden) acctHidden.value = code;
                                if (bamHidden) bamHidden.value = bamNo;
                                if (amountField) setNumberFieldValue(amountField, amount);

                                // Display title from dropdown (data-desc) when possible
                                if (acctDisplay) {
                                    const opt = code ? document.querySelector('.bam-acct-option[data-code="' + code.replace(/"/g, '&quot;') + '"]') : null;
                                    const desc = opt && opt.getAttribute('data-desc') ? String(opt.getAttribute('data-desc') || '').trim() : '';
                                    const displayText = desc ? desc : code;
                                    acctDisplay.value = displayText;
                                    acctDisplay.placeholder = displayText ? '' : '— Select Acct Code —';

                                    const row = acctDisplay.closest('tr');
                                    
                                }
                            }
                        }

                        if (typeof updateSundryTotals === 'function') updateSundryTotals();
                        if (typeof updateTotals === 'function') updateTotals();

                        bamSearchModal.modal('hide');
                        if (formStatusText) formStatusText.textContent = result.data.message || `BAM No. "${q}" loaded.`;
                    })
                    .catch(() => {
                        setBamSearchError('Search failed. Please try again.');
                        bamSearchModal.modal('show');
                    });
                });
            }
            const updatePaymentBtn = document.getElementById('updatePaymentBtn');

            const formatCurrency = value => {
                const amount = Number(value) || 0;
                return new Intl.NumberFormat('en-PH', {
                    style: 'currency',
                    currency: 'PHP',
                    minimumFractionDigits: 2
                }).format(amount);
            };

            const parseNumeric = input => {
                if (input === null || input === undefined || input === '') {
                    return 0;
                }
                const value = typeof input === 'number' ? input : parseFloat(input);
                return Number.isFinite(value) ? value : 0;
            };

            const debounce = (fn, delay = 400) => {
                let timer;
                return (...args) => {
                    clearTimeout(timer);
                    timer = setTimeout(() => fn(...args), delay);
                };
            };

            const updateLookupStatus = (message, tone = 'muted') => {
                if (!lookupStatus) {
                    return;
                }
                lookupStatus.textContent = message;
                lookupStatus.className = `d-none mt-2 small text-${tone}`;
            };

            // Update "Payment status (this month)" badge: 'paid' | 'unpaid' | null (show —)
            const setPaymentStatusForMonth = (status) => {
                const el = document.getElementById('paymentStatusForMonth');
                if (!el) return;
                if (status === 'paid') {
                    el.textContent = 'Paid';
                    el.className = 'badge badge-success px-3 py-2';
                } else if (status === 'unpaid') {
                    el.textContent = 'Unpaid';
                    el.className = 'badge badge-warning px-3 py-2';
                } else {
                    el.textContent = '—';
                    el.className = 'badge badge-secondary px-3 py-2';
                }
                el.style.fontSize = '0.8rem';
            };

            const getAccountNumber = () => {
                return accountNumberField?.value?.trim() || '';
            };

            const getAccountName = () => {
                return accountNameField?.value?.trim() || '';
            };

            // Enable/disable form fields
            const disableFormFields = (disabled) => {
                const fieldsToDisable = [
                    'fieldCurrentBill', 'fieldPenalty', 'fieldMaintenance', 'fieldAdvances',
                    'fieldArrearsCurrent', 'fieldArrearsPrevious', 'fieldSeniorDiscount',
                    'fieldOthers', 'fieldMaterials', 'fieldFees', 'fieldInspection',
                    'paymentType', 'paymentRemarks', 'cashTendered', 'enableSeniorDiscount'
                ];
                
                fieldsToDisable.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        if (field.type === 'checkbox') {
                            field.disabled = disabled;
                        } else {
                            field.readOnly = disabled;
                        }
                    }
                });
            };

            const setNumberFieldValue = (field, value) => {
                if (!field) {
                    return;
                }
                const numeric = parseNumeric(value);
                field.value = numeric.toFixed(2);
            };
            
            // ArrowUp / ArrowDown navigation between amount fields (e.g. Penalty -> Maintenance).
            const arrowNavigationFieldIds = [
                'fieldCurrentBill',
                'fieldArrearsCurrent',
                'fieldArrearsPrevious',
                'fieldPenalty',
                'fieldMaintenance',
                'fieldAdvances',
                'sundryAmount1',
                'sundryAmount2',
                'sundryAmount3',
                'sundryAmount4',
                'cashTendered'
            ];
            const isArrowNavigableField = (element) =>
                !!(element && arrowNavigationFieldIds.includes(element.id));
            const getArrowNavigableInputs = () =>
                arrowNavigationFieldIds
                    .map(id => document.getElementById(id))
                    .filter(input =>
                        input &&
                        !input.disabled &&
                        !input.readOnly &&
                        input.offsetParent !== null
                    );
            if (form) {
                form.addEventListener('keydown', (event) => {
                    if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;
                    if (event.altKey || event.ctrlKey || event.metaKey) return;
                    const target = event.target;
                    if (!isArrowNavigableField(target)) return;
                    // Always block native number-input increment/decrement.
                    event.preventDefault();
                    event.stopImmediatePropagation();

                    const inputs = getArrowNavigableInputs();
                    const currentIndex = inputs.indexOf(target);
                    if (currentIndex === -1) return;

                    const direction = event.key === 'ArrowDown' ? 1 : -1;
                    const nextInput = inputs[currentIndex + direction];
                    if (!nextInput) return;

                    nextInput.focus();
                    if (typeof nextInput.select === 'function') {
                        nextInput.select();
                    }
                }, true);
            }

            const updateTotals = () => {
                // Subtotal = Current Bill + Arrears Current Year + Arrears Previous Year + Penalty + Water Maintenance Charge + Advances − Sr. Citizen Discount
                const subtotalChargeIds = ['fieldCurrentBill', 'fieldArrearsCurrent', 'fieldArrearsPrevious', 'fieldPenalty', 'fieldMaintenance', 'fieldAdvances'];
                let subtotal = 0;
                subtotalChargeIds.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) subtotal += Math.max(parseNumeric(el.value), 0);
                });
                const seniorDiscount = Math.max(parseNumeric(document.getElementById('fieldSeniorDiscount')?.value), 0);
                subtotal = Math.max(subtotal - seniorDiscount, 0);

                subtotalField.value = formatCurrency(subtotal);

                // Sundries = sum of the four sundry amount fields
                let sundriesSum = 0;
                for (let i = 1; i <= 4; i++) {
                    const amountField = document.getElementById('sundryAmount' + i);
                    if (amountField) sundriesSum += Math.max(parseNumeric(amountField.value), 0);
                }

                // Overall Total = Subtotal + SUNDRIES
                const overallTotal = subtotal + sundriesSum;
                totalField.value = formatCurrency(overallTotal);
                paymentAmountField.value = formatCurrency(overallTotal);
                paymentTotalField.value = formatCurrency(overallTotal);

                // Change calculation based on overall total
                const tendered = parseNumeric(cashTenderedField.value) || 0;
                const change = Math.max(tendered - overallTotal, 0);
                if (cashChangeField) {
                    cashChangeField.value = change > 0 ? change.toFixed(2) : '0.00';
                }
            };

            // SUNDRIES from LRO Ledger remain visible even when current balance is zero.
            const clearPaymentBreakdown = () => {
                chargeInputs.forEach(field => {
                    if (field.hasAttribute('data-sundry')) {
                        return;
                    }
                    setNumberFieldValue(field, 0);
                });
                discountInputs.forEach(field => {
                    setNumberFieldValue(field, 0);
                });

                // Also clear Cash Tendered and Change fields
                if (cashTenderedField) {
                    cashTenderedField.value = '';
                }
                if (cashChangeField) {
                    cashChangeField.value = '0.00';
                }
                
                bamSearchAccountName = null;
                updateTotals();
            };

            // SUNDRIES Acct Code dropdowns and total calculation
            const updateSundryTotals = () => {
                const sundryTotalsField = document.getElementById('sundryTotals');
                if (!sundryTotalsField) return;
                let sundryTotal = 0;
                for (let i = 1; i <= 4; i++) {
                    const amountField = document.getElementById('sundryAmount' + i);
                    if (amountField) {
                        sundryTotal += Math.max(parseNumeric(amountField.value), 0);
                    }
                }
                sundryTotalsField.value = formatCurrency(sundryTotal);
                updateTotals(); // Refresh Overall Total (Subtotal + SUNDRIES)
            };

            for (let i = 1; i <= 4; i++) {
                (function(index) {
                    const acctDisplay = document.getElementById('sundryAcctCodeDisplay' + index);
                    const acctHidden = document.getElementById('sundryAcctCode' + index);
                    const acctDropdown = document.getElementById('sundryAcctCodeDropdown' + index);
                    if (!acctDisplay || !acctHidden || !acctDropdown) return;

                    acctDisplay.addEventListener('click', function() {
                        acctDropdown.style.display = acctDropdown.style.display === 'none' ? 'block' : 'none';
                    });

                    acctDropdown.querySelectorAll('.bam-acct-option').forEach(function(opt) {
                        opt.addEventListener('click', function() {
                            var code = this.getAttribute('data-code') || '';
                            var desc = this.getAttribute('data-desc') || '';
                            var displayText = desc.trim() ? desc : code;
                            acctHidden.value = code;
                            acctDisplay.value = displayText;
                            acctDisplay.placeholder = displayText ? '' : '— Select Acct Code —';
                            acctDropdown.style.display = 'none';
                        });
                    });

                    const amountField = document.getElementById('sundryAmount' + index);
                    if (amountField) {
                        amountField.addEventListener('input', updateSundryTotals);
                    }
                })(i);
            }

            document.addEventListener('click', function(e) {
                for (let i = 1; i <= 4; i++) {
                    const acctDisplay = document.getElementById('sundryAcctCodeDisplay' + i);
                    const acctDropdown = document.getElementById('sundryAcctCodeDropdown' + i);
                    if (acctDisplay && acctDropdown && !acctDisplay.contains(e.target) && !acctDropdown.contains(e.target)) {
                        acctDropdown.style.display = 'none';
                    }
                }
            });

            // Manual Senior Citizen Discount (5% of eligible water charges only)
            // User must enable it via checkbox - not all consumers are senior citizens
            const applySeniorCitizenDiscount = () => {
                const seniorDiscountField = document.getElementById('fieldSeniorDiscount');
                const currentBillField = document.getElementById('fieldCurrentBill');
                const enableCheckbox = document.getElementById('enableSeniorDiscount');
                
                if (!seniorDiscountField || !currentBillField || !enableCheckbox) {
                    return;
                }

                // Base for Senior Citizen Discount:
                // Apply ONLY to:
                // - Current Bill
                // - Arrears — Current Year
                // - Arrears — Previous Year
                //
                // Do NOT apply discount to:
                // - Penalty
                // - Water Maintenance Charge
                // - Advances
                // - Other fees/materials/charges
                const discountBaseIds = [
                    'fieldCurrentBill',
                    'fieldArrearsCurrent',
                    'fieldArrearsPrevious'
                ];

                if (enableCheckbox.checked) {
                    let discountBaseTotal = 0;
                    discountBaseIds.forEach(id => {
                        const el = document.getElementById(id);
                        if (el) {
                            discountBaseTotal += Math.max(parseNumeric(el.value), 0);
                        }
                    });

                    if (discountBaseTotal > 0) {
                        // Calculate 5% of the computed total charges
                        const discountAmount = discountBaseTotal * 0.05;
                    setNumberFieldValue(seniorDiscountField, discountAmount);
                    seniorDiscountField.readOnly = false; // Allow manual adjustment
                    } else {
                        setNumberFieldValue(seniorDiscountField, 0);
                        seniorDiscountField.readOnly = true;
                    }
                } else {
                    // Set to 0 when disabled
                    setNumberFieldValue(seniorDiscountField, 0);
                    seniorDiscountField.readOnly = true; // Disable field when unchecked
                }
                
                updateTotals();
            };
            
            // Fetch unpaid bill months for a consumer
            const fetchUnpaidBillMonths = async (accountNumber) => {
                const billMonthSelectorGroup = document.getElementById('billMonthSelectorGroup');
                const unpaidBillMonth = document.getElementById('unpaidBillMonth');
                
                if (!unpaidBillMonth) return;
                
                try {
                    const response = await fetch(`{{ route('billing-payment.unpaid-months') }}?account_number=${encodeURIComponent(accountNumber)}`);
                    const result = await response.json();
                    
                    if (result.success && result.data && result.data.length > 0) {
                        unpaidBillMonth.innerHTML = '<option value="">-- Select Month --</option>';
                        result.data.forEach(month => {
                            const option = document.createElement('option');
                            option.value = month.key;
                            option.textContent = month.display;
                            unpaidBillMonth.appendChild(option);
                        });
                        if (billMonthSelectorGroup) {
                            billMonthSelectorGroup.style.display = 'block';
                        }
                    } else {
                        if (billMonthSelectorGroup) {
                            billMonthSelectorGroup.style.display = 'none';
                        }
                    }
                } catch (error) {
                    console.error('Error fetching unpaid bill months:', error);
                    if (billMonthSelectorGroup) {
                        billMonthSelectorGroup.style.display = 'none';
                    }
                }
            };
            
            // Fetch and populate payment fields for selected bill month.
            // Always prefer bill month for date-meaning methods; only use date-range when bill month is not set.
            const loadBillMonthDetails = async (accountNumber, billMonth, fromDate, toDate) => {
                const useDateRange = !billMonth && fromDate && toDate;
                if (!billMonth && !useDateRange) return;
                
                isLoadingFromMonthSelector = true;
                
                let url = `{{ route('billing-payment.bill-month-details') }}?account_number=${encodeURIComponent(accountNumber)}`;
                if (useDateRange) {
                    url += `&from_date=${encodeURIComponent(fromDate)}&to_date=${encodeURIComponent(toDate)}`;
                } else {
                    url += `&bill_month_from=${encodeURIComponent(billMonth)}&bill_month_to=${encodeURIComponent(billMonth)}`;
                }
                const txDateValue = transactionDateField?.value?.trim();
                if (txDateValue) {
                    url += `&transaction_date=${encodeURIComponent(txDateValue)}`;
                }
                const rawBalance = document.getElementById('currentBalance')?.value;
                const balanceForPy = rawBalance != null && rawBalance !== '' ? parseFloat(String(rawBalance).replace(/[^\d.-]/g, '')) : (typeof currentBalanceValue !== 'undefined' ? currentBalanceValue : null);
                if (balanceForPy != null && !isNaN(balanceForPy) && balanceForPy >= 0) {
                    url += `&current_balance=${encodeURIComponent(balanceForPy)}`;
                }
                const orFieldForDetails = document.getElementById('officialReceipt');
                const orValueForDetails = orFieldForDetails ? String(orFieldForDetails.value || '').trim() : '';
                if (useOrForBreakdownLookup && orValueForDetails) {
                    url += `&or_number=${encodeURIComponent(orValueForDetails)}`;
                }
                try {
                    const response = await fetch(url);
                    const result = await response.json();
                    
                    if (result.success && result.data) {
                        const data = result.data;
                        if (data.downloaded_id) {
                            currentDownloadedId = data.downloaded_id;
                            if (updatePaymentBtn) updatePaymentBtn.disabled = false;
                        }
                        // Enable Delete Payment if OR number is present
                        const deletePaymentBtn = document.getElementById('deletePaymentBtn');
                        if (deletePaymentBtn) {
                            const orNumber = (document.getElementById('officialReceipt') || {}).value?.trim();
                            deletePaymentBtn.disabled = !orNumber;
                        }
                        // Do not override status while OR field has a value.
                        // OR status is handled by OR lookup flow (used OR = paid, unused OR = unpaid).
                        if (typeof setPaymentStatusForMonth === 'function' && !orValueForDetails) {
                            setPaymentStatusForMonth(data.payment_status === 'paid' ? 'paid' : 'unpaid');
                        }
                        setNumberFieldValue(document.getElementById('fieldCurrentBill'), data.current_bill || 0);
                        const penaltyField = document.getElementById('fieldPenalty');
                        if (penaltyField) {
                            setNumberFieldValue(penaltyField, data.penalty || 0);
                            penaltyField.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        setNumberFieldValue(document.getElementById('fieldMaintenance'), data.maintenance || 0);
                        setNumberFieldValue(document.getElementById('fieldAdvances'), 0);
                        setNumberFieldValue(document.getElementById('fieldArrearsCurrent'), data.arrears_cy ?? 0);
                        setNumberFieldValue(document.getElementById('fieldArrearsPrevious'), data.arrears_py ?? 0);
                        setNumberFieldValue(document.getElementById('fieldSeniorDiscount'), data.senior_citizen_discount ?? 0);
                        setNumberFieldValue(document.getElementById('fieldOthers'), data.others || 0);
                        setNumberFieldValue(document.getElementById('fieldMaterials'), 0);
                        setNumberFieldValue(document.getElementById('fieldFees'), 0);
                        setNumberFieldValue(document.getElementById('fieldInspection'), 0);
                        const enableSeniorDiscountCheckbox = document.getElementById('enableSeniorDiscount');
                        if (enableSeniorDiscountCheckbox) {
                            enableSeniorDiscountCheckbox.checked = (parseFloat(data.senior_citizen_discount) || 0) > 0;
                        }
                        if ((parseFloat(currentBalanceValue) || 0) <= 0.009 && !lockPaidOrBreakdown) {
                            clearPaymentBreakdown();
                        }
                        updateTotals();
                    }
                } catch (error) {
                    console.error('Error loading bill month details:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load bill month details.',
                        confirmButtonColor: '#e74a3b'
                    });
                } finally {
                    setTimeout(() => {
                        isLoadingFromMonthSelector = false;
                    }, 1000);
                }
            };

            const populateFromLookup = data => {
                if (!data) {
                    return;
                }

                const { account = {}, billing = {}, payment = {}, downloaded_reading = {}, sundries = [], lro_entries_by_or = [] } = data;
                
                // Store downloaded_id for payment submission
                currentDownloadedId = downloaded_reading.id || data.downloaded_id || null;
                
                // Enable Update Payment when a billing record is loaded (by account or OR #), or when loaded by OR # without linked reading (account + OR present)
                if (updatePaymentBtn) {
                    const hasAccountAndOr = !!(account && account.number && (document.getElementById('officialReceipt') || {}).value);
                    updatePaymentBtn.disabled = !currentDownloadedId && !hasAccountAndOr;
                }
                // Enable Delete Payment when OR number is present
                const deletePaymentBtn = document.getElementById('deletePaymentBtn');
                if (deletePaymentBtn) {
                    const orNumber = (document.getElementById('officialReceipt') || {}).value?.trim();
                    deletePaymentBtn.disabled = !orNumber;
                }

                // Populate account information
                if (accountNumberField && account.number) {
                    accountNumberField.value = account.number;
                }

                if (billMonthField && billing.bill_month_input) {
                    billMonthField.value = billing.bill_month_input;
                }
                
                // Store the latest bill month for validation
                // This is used to determine if user is paying current bill or past bill
                // Only update latestBillMonth if it's not already set (first load) or if this is clearly the latest
                // When no bill month is specified in lookup, the returned bill is the latest
                if (billing.bill_month) {
                    const billMonthFormatted = billing.bill_month; // Format: YYYY-MM
                    // Only set if not already set, or if this month is newer
                    if (!latestBillMonth || billMonthFormatted >= latestBillMonth) {
                        latestBillMonth = billMonthFormatted;
                    }
                } else if (billing.bill_month_input) {
                    // Convert MM-YYYY to YYYY-MM for comparison
                    const parts = billing.bill_month_input.split('-');
                    if (parts.length === 2) {
                        const billMonthFormatted = `20${parts[1]}-${parts[0].padStart(2, '0')}`;
                        // Only set if not already set, or if this month is newer
                        if (!latestBillMonth || billMonthFormatted >= latestBillMonth) {
                            latestBillMonth = billMonthFormatted;
                        }
                    }
                }

                if (accountNameField) {
                    accountNameField.value = (account.name || '').toUpperCase();
                }

                // Populate current balance (update display span to match ledger format)
                const currentBalanceField = document.getElementById('currentBalance');
                const currentBalanceDisplay = document.getElementById('currentBalanceDisplay');
                const currentBalanceAccountLabel = document.getElementById('currentBalanceAccountLabel');
                if (account.current_balance !== undefined) {
                    const balance = parseNumeric(account.current_balance);
                    const formattedBalance = formatCurrency(balance);
                    if (currentBalanceDisplay) {
                        currentBalanceDisplay.textContent = formattedBalance;
                    }
                    if (currentBalanceField) {
                        currentBalanceField.value = formattedBalance;
                    }
                    currentBalanceValue = balance;
                } else {
                    const formattedZero = formatCurrency(0);
                    if (currentBalanceDisplay) {
                        currentBalanceDisplay.textContent = formattedZero;
                    }
                    if (currentBalanceField) {
                        currentBalanceField.value = formattedZero;
                    }
                    currentBalanceValue = 0;
                }

                // Show either Account Name or Account Number above Current Balance; show Address below when available
                const currentBalanceAccountRow = document.getElementById('currentBalanceAccountRow');
                const currentBalanceAccountAddress = document.getElementById('currentBalanceAccountAddress');
                const labelSource = (account.name && account.name.trim())
                    ? account.name.trim()
                    : (account.number || '').trim();
                const addressSource = (account.address && typeof account.address === 'string') ? account.address.trim() : '';
                if (currentBalanceAccountLabel) {
                    if (labelSource) {
                        currentBalanceAccountLabel.textContent = labelSource;
                        currentBalanceAccountLabel.style.display = 'flex';
                    } else {
                        currentBalanceAccountLabel.textContent = '';
                        currentBalanceAccountLabel.style.display = 'none';
                    }
                }
                if (currentBalanceAccountAddress) {
                    if (addressSource) {
                        currentBalanceAccountAddress.textContent = addressSource;
                        currentBalanceAccountAddress.style.display = 'flex';
                    } else {
                        currentBalanceAccountAddress.textContent = '';
                        currentBalanceAccountAddress.style.display = 'none';
                    }
                }
                if (currentBalanceAccountRow) {
                    currentBalanceAccountRow.style.display = (labelSource || addressSource) ? 'block' : 'none';
                }
                renderScDiscountLedgerText(account);
                
                // Fetch unpaid bill months if there's a current balance
                if (currentBalanceValue > 0.01 && account.number) {
                    fetchUnpaidBillMonths(account.number);
                } else {
                    // Hide bill month selector if no balance
                    const billMonthSelectorGroup = document.getElementById('billMonthSelectorGroup');
                    if (billMonthSelectorGroup) {
                        billMonthSelectorGroup.style.display = 'none';
                    }
                }

                // Enable action buttons based on account data
                if (viewLedgerBtn && account.number) {
                    viewLedgerBtn.disabled = false;
                }

                // Use current date (today) as the transaction date so it always reflects the updated date (e.g. 31/01/2026 today, 01/02/2026 tomorrow)
                if (transactionDateField) {
                    const now = new Date();
                    const y = now.getFullYear();
                    const m = String(now.getMonth() + 1).padStart(2, '0');
                    const d = String(now.getDate()).padStart(2, '0');       
                    transactionDateField.value = `${y}-${m}-${d}`;
                    // Only set bill month from today's date if it wasn't already set from lookup
                    if (billMonthField && !billing.bill_month_input) {
                        billMonthField.value = `${m}-${y}`;
                    }
                }

                // Populate billing fields from downloaded_readings
                // Only populate Current Bill and Water Maintenance Charge automatically
                const currentBillField = document.getElementById('fieldCurrentBill');
                if (currentBillField) {
                    const currentBillValue = parseNumeric(billing.current_bill);
                    currentBillField.value = currentBillValue > 0 ? currentBillValue.toFixed(2) : '0.00';
                    // Trigger input event to ensure updateTotals picks it up
                    currentBillField.dispatchEvent(new Event('input', { bubbles: true }));
                }
                
                // Penalty comes from penalties DB: billing.penalty is from lookup (penalties table by schedule); month selector uses bill-month-details (penalties table + ledger)
                // Only set penalty from billing.penalty if no month range is selected AND we're not loading from month selector
                // If month range is selected, penalty will be set by loadBillMonthDetails (penalties DB + ledger)
                // Do not overwrite penalty if bill month selector has a value (user explicitly selected a month)
                const penaltyField = document.getElementById('fieldPenalty');
                const unpaidBillMonth = document.getElementById('unpaidBillMonth');
                const hasBillMonthSelected = unpaidBillMonth && unpaidBillMonth.value && unpaidBillMonth.value !== '';
                const isPaidForPenalty = payment.paid_at != null && String(payment.paid_at).trim() !== '';
                
                // Only set penalty from billing.penalty if:
                // 1. No bill month is currently selected (user hasn't used the month selector)
                // 2. We're not currently loading data from the month selector
                // 3. Bill is not paid (when paid, show penalty as 0.00 and do not fetch from API so async cannot overwrite)
                if (penaltyField && !hasBillMonthSelected && !isLoadingFromMonthSelector) {
                    if (isPaidForPenalty) {
                        setNumberFieldValue(penaltyField, 0);
                        penaltyField.dispatchEvent(new Event('input', { bubbles: true }));
                    } else {
                    const penaltyValue = parseNumeric(billing.penalty) || 0;
                    setNumberFieldValue(penaltyField, penaltyValue);
                        // If lookup returned penalty 0, fetch penalty from ledger for this bill month (so 62.48 from PENALTY row appears)
                        if (penaltyValue === 0 && account.number && (billing.bill_month_input || billing.bill_month)) {
                            const billMonth = billing.bill_month_input || billing.bill_month || '';
                            if (billMonth) {
                                fetch(`{{ route('billing-payment.bill-month-details') }}?account_number=${encodeURIComponent(account.number)}&bill_month_from=${encodeURIComponent(billMonth)}&bill_month_to=${encodeURIComponent(billMonth)}`)
                                    .then(r => r.json())
                                    .then(result => {
                                        if (result.success && result.data && (parseFloat(result.data.penalty) || 0) > 0) {
                                            setNumberFieldValue(penaltyField, result.data.penalty);
                                            penaltyField.dispatchEvent(new Event('input', { bubbles: true }));
                                            if (typeof updateTotals === 'function') updateTotals();
                                        }
                                    })
                                    .catch(() => {});
                            }
                        }
                    }
                } else if (hasBillMonthSelected) {
                    // If bill month is selected, do NOT overwrite the penalty (set by loadBillMonthDetails)
                }
                
                // Water Maintenance Charge (₱20 meter rental) - separate from current bill
                const maintenanceCharge = billing.meter_maintenance_charge || 20.00;
                setNumberFieldValue(document.getElementById('fieldMaintenance'), maintenanceCharge);
                
                // Do NOT auto-populate arrears - leave at 0.00 for manual entry
                setNumberFieldValue(document.getElementById('fieldArrearsCurrent'), 0);
                setNumberFieldValue(document.getElementById('fieldArrearsPrevious'), 0);
                setNumberFieldValue(document.getElementById('fieldAdvances'), 0);
                
                // Senior Citizen Discount: Reset to disabled state
                const enableSeniorDiscountCheckbox = document.getElementById('enableSeniorDiscount');
                if (enableSeniorDiscountCheckbox) {
                    enableSeniorDiscountCheckbox.checked = false;
                }
                const seniorDiscountField = document.getElementById('fieldSeniorDiscount');
                if (seniorDiscountField) {
                    setNumberFieldValue(seniorDiscountField, 0);
                    seniorDiscountField.readOnly = true;
                }
                
                setNumberFieldValue(document.getElementById('fieldOthers'), 0);
                setNumberFieldValue(document.getElementById('fieldMaterials'), 0);
                setNumberFieldValue(document.getElementById('fieldFees'), 0);
                setNumberFieldValue(document.getElementById('fieldInspection'), 0);

                // Populate SUNDRIES:
                // 1) normal account lookup uses `sundries`
                // 2) OR lookup for paid entries uses `lro_entries_by_or`
                const sundryList = (Array.isArray(sundries) && sundries.length > 0)
                    ? sundries
                    : (Array.isArray(lro_entries_by_or) ? lro_entries_by_or : []);
                // Resolve account title from dropdown by acct_code (API "name" can be consumer name, not account title)
                const getAccountTitleByCode = function(acctCode) {
                    if (!acctCode) return '';
                    const opt = document.querySelector('.bam-acct-option[data-code="' + String(acctCode).replace(/"/g, '&quot;') + '"]');
                    return (opt && opt.getAttribute('data-desc')) ? opt.getAttribute('data-desc').trim() : '';
                };
                for (let i = 1; i <= 4; i++) {
                    const entry = sundryList[i - 1] || null;
                    const acctDisplay = document.getElementById('sundryAcctCodeDisplay' + i);
                    const acctHidden = document.getElementById('sundryAcctCode' + i);
                    const acctDropdown = document.getElementById('sundryAcctCodeDropdown' + i);
                    const amountField = document.getElementById('sundryAmount' + i);

                    if (!acctDisplay || !acctHidden || !amountField) {
                        continue;
                    }

                    const bamNoHidden = document.getElementById('sundryBamNo' + i);
                    if (entry) {
                        const code = entry.acct_code || '';
                        const ref = entry.reference || '';
                        const titleFromDropdown = getAccountTitleByCode(code);
                        const title = titleFromDropdown || entry.acct_title || '';
                        const displayText = title.trim() ? title.trim() : (code && ref ? `${code} ${ref}` : (code || ref));

                        acctHidden.value = code;
                        acctDisplay.value = displayText;
                        acctDisplay.placeholder = displayText ? '' : '— Select Acct Code —';
                        setNumberFieldValue(amountField, entry.amount ?? 0);
                        if (bamNoHidden) bamNoHidden.value = entry.bam_no || entry.reference || '';
                    } else {
                        acctHidden.value = '';
                        acctDisplay.value = '';
                        acctDisplay.placeholder = '— Select Acct Code —';
                        setNumberFieldValue(amountField, 0);
                        if (bamNoHidden) bamNoHidden.value = '';
                    }

                    if (acctDropdown) {
                        acctDropdown.style.display = 'none';
                    }
                }
                updateSundryTotals();

                // Payment status priority:
                // 1) In explicit OR lookup mode, mark Paid only when that exact OR matches lookup payload.
                // 2) In account lookup mode, ignore generated/typed OR and use bill-month payment record state.
                const typedOrNumber = String((document.getElementById('officialReceipt') || {}).value || '').trim();
                const downloadedOr = (downloaded_reading && downloaded_reading.official_receipt_number != null)
                    ? String(downloaded_reading.official_receipt_number).trim()
                    : '';
                const paymentRefOr = (payment && payment.reference != null) ? String(payment.reference).trim() : '';
                const hasMonthPaidRecord = downloadedOr !== '' || paymentRefOr !== '';
                const hasExactOrMatch = typedOrNumber !== '' && (typedOrNumber === downloadedOr || typedOrNumber === paymentRefOr);
                const isExplicitOrLookup = useOrForBreakdownLookup === true;
                const isPaid = isExplicitOrLookup ? hasExactOrMatch : hasMonthPaidRecord;
                const hasPaymentBreakdown = payment && (payment.current_bill !== undefined || payment.penalty !== undefined || payment.meter_maintenance !== undefined);
                const hasCurrentBalance = (parseFloat(currentBalanceValue) || 0) > 0.009;
                // Keep saved paid breakdown for paid-month account lookup (including zero balance).
                // For remaining-balance + explicit OR flow, `isPaid` is false until OR matches, so API recompute still runs.
                const keepPaidMonthOrBreakdown = isPaid && hasPaymentBreakdown;
                if (keepPaidMonthOrBreakdown) {
                    // Paid in selected month + still has balance: keep the saved OR/payment breakdown.
                    lockPaidOrBreakdown = true;
                }
                let displayedBillMonthKey = billing.bill_month_input || (billing.bill_month ? (() => {
                    const s = String(billing.bill_month).trim();
                    if (/^\d{4}-\d{2}/.test(s)) {
                        const [y, m] = s.split('-');
                        return `${m}-${y}`;
                    }
                    return s;
                })() : '');
                // Use header Bill Month when lookup does not provide a bill month, so breakdown still loads
                if (!displayedBillMonthKey && billMonthField && billMonthField.value) {
                    displayedBillMonthKey = String(billMonthField.value).trim();
                }
                if (!keepPaidMonthOrBreakdown && !lockPaidOrBreakdown && account.number && (displayedBillMonthKey || transactionDateField?.value)) {
                    // Always use bill month for date-meaning methods; only fall back to date-range when bill month is missing.
                    const txDate = transactionDateField?.value?.trim();
                    let url = `{{ route('billing-payment.bill-month-details') }}?account_number=${encodeURIComponent(account.number)}`;
                    if (displayedBillMonthKey) {
                        url += `&bill_month_from=${encodeURIComponent(displayedBillMonthKey)}&bill_month_to=${encodeURIComponent(displayedBillMonthKey)}`;
                    } else if (txDate) {
                        url += `&from_date=${encodeURIComponent(txDate)}&to_date=${encodeURIComponent(txDate)}`;
                    }
                    if (txDate) {
                        url += `&transaction_date=${encodeURIComponent(txDate)}`;
                    }
                    const rawBalance = document.getElementById('currentBalance')?.value;
                    const balanceForPy = rawBalance != null && rawBalance !== '' ? parseFloat(String(rawBalance).replace(/[^\d.-]/g, '')) : (typeof currentBalanceValue !== 'undefined' ? currentBalanceValue : null);
                    if (balanceForPy != null && !isNaN(balanceForPy) && balanceForPy >= 0) {
                        url += `&current_balance=${encodeURIComponent(balanceForPy)}`;
                    }
                    const orFieldForLookup = document.getElementById('officialReceipt');
                    const orValueForLookup = orFieldForLookup ? String(orFieldForLookup.value || '').trim() : '';
                    if (useOrForBreakdownLookup && orValueForLookup) {
                        url += `&or_number=${encodeURIComponent(orValueForLookup)}`;
                    }
                    fetch(url)
                        .then(r => r.json())
                        .then(result => {
                            if (result.success && result.data) {
                // Payment status: use consumer_payment only. Paid when bill-month-details returns paid (OR used); otherwise Unpaid.
                if (typeof setPaymentStatusForMonth === 'function') {
                    const paymentStatusFromApi = result.data.payment_status === 'paid';
                    setPaymentStatusForMonth(paymentStatusFromApi ? 'paid' : 'unpaid');
                }
                                setNumberFieldValue(document.getElementById('fieldCurrentBill'), result.data.current_bill ?? 0);
                                const penaltyField = document.getElementById('fieldPenalty');
                                if (penaltyField) {
                                    setNumberFieldValue(penaltyField, result.data.penalty ?? 0);
                                    penaltyField.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                                setNumberFieldValue(document.getElementById('fieldMaintenance'), result.data.maintenance ?? 0);
                                setNumberFieldValue(document.getElementById('fieldAdvances'), 0);
                                setNumberFieldValue(document.getElementById('fieldArrearsCurrent'), result.data.arrears_cy ?? 0);
                                setNumberFieldValue(document.getElementById('fieldArrearsPrevious'), result.data.arrears_py ?? 0);
                                setNumberFieldValue(document.getElementById('fieldSeniorDiscount'), result.data.senior_citizen_discount ?? 0);
                                setNumberFieldValue(document.getElementById('fieldOthers'), result.data.others ?? 0);
                                setNumberFieldValue(document.getElementById('fieldMaterials'), 0);
                                setNumberFieldValue(document.getElementById('fieldFees'), 0);
                                setNumberFieldValue(document.getElementById('fieldInspection'), 0);
                                const enableSeniorDiscountCheckbox = document.getElementById('enableSeniorDiscount');
                                if (enableSeniorDiscountCheckbox) {
                                    enableSeniorDiscountCheckbox.checked = (parseFloat(result.data.senior_citizen_discount) || 0) > 0;
                                }
                                if ((parseFloat(currentBalanceValue) || 0) <= 0.009 && !lockPaidOrBreakdown) {
                                    clearPaymentBreakdown();
                                }
                                if (typeof updateTotals === 'function') updateTotals();
                            }
                        })
                        .catch(() => {
                            setPaymentStatusForMonth(isPaid ? 'paid' : 'unpaid');
                        });
                } else {
                    setPaymentStatusForMonth(isPaid ? 'paid' : 'unpaid');
                }

                if (isPaid) {
                    // When paid: show breakdown from payment record (consumer_payments) when available
                    const hasPaymentBreakdown = payment && (payment.current_bill !== undefined || payment.penalty !== undefined || payment.meter_maintenance !== undefined);
                    if (hasPaymentBreakdown) {
                        setNumberFieldValue(document.getElementById('fieldCurrentBill'), payment.current_bill ?? 0);
                        const penaltyField = document.getElementById('fieldPenalty');
                        if (penaltyField) {
                            setNumberFieldValue(penaltyField, payment.penalty ?? 0);
                            penaltyField.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        setNumberFieldValue(document.getElementById('fieldMaintenance'), payment.meter_maintenance ?? 0);
                        setNumberFieldValue(document.getElementById('fieldAdvances'), payment.advances ?? 0);
                        setNumberFieldValue(document.getElementById('fieldArrearsCurrent'), payment.arrears_cy ?? 0);
                        setNumberFieldValue(document.getElementById('fieldArrearsPrevious'), payment.arrears_py ?? 0);
                        setNumberFieldValue(document.getElementById('fieldSeniorDiscount'), payment.senior_citizen_discount ?? 0);
                        setNumberFieldValue(document.getElementById('fieldOthers'), payment.others ?? 0);
                        const enableSeniorDiscountCheckboxPaid = document.getElementById('enableSeniorDiscount');
                        if (enableSeniorDiscountCheckboxPaid) {
                            enableSeniorDiscountCheckboxPaid.checked = (parseFloat(payment.senior_citizen_discount) || 0) > 0;
                        }
                        setNumberFieldValue(document.getElementById('fieldMaterials'), 0);
                        setNumberFieldValue(document.getElementById('fieldFees'), 0);
                        setNumberFieldValue(document.getElementById('fieldInspection'), 0);
                        
                        // Paid month with remaining balance:
                        // - if bill <= balance: keep bill in Current Bill, put remainder in Arrears CY
                        // - if bill > balance: put full balance in Arrears CY and set Current Bill to 0
                        const balanceNow = parseNumeric(currentBalanceValue);
                        const monthBill = parseNumeric(payment.current_bill ?? 0);
                        if (balanceNow > 0.009) {
                            if (monthBill > 0 && monthBill <= balanceNow) {
                                setNumberFieldValue(document.getElementById('fieldCurrentBill'), monthBill);
                                setNumberFieldValue(document.getElementById('fieldArrearsCurrent'), balanceNow - monthBill);
                            } else if (monthBill > balanceNow) {
                                setNumberFieldValue(document.getElementById('fieldCurrentBill'), 0);
                                setNumberFieldValue(document.getElementById('fieldArrearsCurrent'), balanceNow);
                            }
                        }
                    }
                    
                    
                    // When paid but no breakdown in payload: leave billing-populated values (don't force 0)
                    updateTotals();

                    // Payment exists: Don't populate payment-specific fields, allow new payment entry
                    const existingOrNumber = downloaded_reading.official_receipt_number || payment.reference || 'N/A';
                    
                    // Reset payment method to default
                    paymentTypeField.value = 'cash';
                    
                    // Clear payment remarks (don't populate from existing payment)
                    paymentRemarksField.value = '';
                    
                    // Don't populate payment amount - show 0.00 for paid bill
                    if (paymentAmountField) {
                        paymentAmountField.value = formatCurrency(0);
                    }
                    if (paymentTotalField) {
                        paymentTotalField.value = formatCurrency(0);
                    }
                    
                    // OR behavior for paid month:
                    // - zero balance: show the paid OR from record (bill-month result)
                    // - with remaining balance: keep current/new OR for next payment entry
                    const orField = document.getElementById('officialReceipt');
                    const isZeroBalancePaidMonth = (parseFloat(currentBalanceValue) || 0) <= 0.009;
                    if (orField && isZeroBalancePaidMonth && existingOrNumber && existingOrNumber !== 'N/A') {
                        orField.value = String(existingOrNumber).trim();
                        useOrForBreakdownLookup = false;
                    } else if (orField && (!orField.value || orField.value.trim() === '')) {
                        generateOrNumber();
                    }
                    
                    // Clear cash tendered and change
                    if (cashTenderedField) {
                        cashTenderedField.value = '';
                    }
                    if (cashChangeField) {
                        cashChangeField.value = '0.00';
                    }
                    
                    // Do not show "previous payment exists" banner; allow new payments for unpaid months.
                } else {
                    // No payment exists: Populate payment fields normally
                const method = (payment.method || '').toLowerCase();
                if (['cash', 'check', 'gcash', 'bank','palawan'].includes(method)) {
                    paymentTypeField.value = method;
                } else {
                    paymentTypeField.value = 'cash';
                }

                // Format remarks like "19901020 Advances for Payroll" (code + space + remark text)
                const remarkCode = (downloaded_reading && downloaded_reading.id != null && downloaded_reading.id !== '') ? String(downloaded_reading.id) : (payment && payment.id != null && payment.id !== '' ? String(payment.id) : '');
                const remarkText = payment.remarks || (downloaded_reading && downloaded_reading.reader_notes) || '';
                paymentRemarksField.value = (remarkCode ? remarkCode + ' ' : '') + (remarkText || '');

                // Populate payment amount from downloaded_readings
                if (paymentAmountField && payment.amount !== undefined && payment.amount !== null) {
                    const formattedAmount = formatCurrency(payment.amount);
                    paymentAmountField.value = formattedAmount;
                    paymentTotalField.value = formattedAmount;
                }

                    // Generate OR number if empty
                const orField = document.getElementById('officialReceipt');
                if (orField) {
                        if (!orField.value || orField.value.trim() === '') {
                        generateOrNumber();
                    }
                }

                // Populate cash tendered and change if payment was already made
                if (payment.tendered && payment.tendered > 0) {
                    cashTenderedField.value = payment.tendered.toFixed(2);
                }
                if (payment.change !== undefined && payment.change !== null) {
                    cashChangeField.value = payment.change.toFixed(2);
                    } else {
                        cashChangeField.value = '0.00';
                    }
                    
                    if (formStatusText) {
                        formStatusText.textContent = 'Review all fields before saving. Totals are computed automatically.';
                    }
                    }
                    
                // Update totals
                updateTotals();
                    
                // Always enable form submission to allow multiple payments
                    const submitBtn = document.getElementById('savePaymentBtn');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('disabled');
                    }
                    
                // Always enable all form fields for new payment entry
                    disableFormFields(false);
                
                // Recalculate change after populating tendered amount
                updateTotals();

                // Final safety: if current balance is zero, force Payment Breakdown to 0.00
                if (currentBalanceValue <= 0.009 && !lockPaidOrBreakdown) {
                    clearPaymentBreakdown();
                }
            };

            const performLookup = async () => {
                const accountNumber = getAccountNumber();
                const accountName = getAccountName();
                const billMonthRaw = billMonthField?.value?.trim() || '';
                const currentOrValue = String((document.getElementById('officialReceipt') || {}).value || '').trim();
                if (paymentRemarksField) {
                    paymentRemarksField.value = '';
                }

                // If Account Number field has a value, use it to search both account_number and account_name
                // If Account Name field has a value (and Account Number is empty), use it to search account_name
                const searchValue = accountNumber || accountName;
                
                if (!searchValue) {
                    useOrForBreakdownLookup = false;
                    lockPaidOrBreakdown = false;
                    updateLookupStatus('Enter account number or account name to load billing data.', 'muted');
                    setPaymentStatusForMonth(null);
                    lastLookupKey = null;
                    latestBillMonth = null; // Reset when account changes
                    return;
                }

                // If account number/name changed, reset latest bill month and lastLookupKey
                const previousAccount = lastLookupKey ? lastLookupKey.split('|')[0] : null;
                const currentAccount = searchValue;
                if (previousAccount && previousAccount !== currentAccount) {
                    latestBillMonth = null; // Reset when switching accounts
                    lastLookupKey = null; // Reset lookup key to allow new search
                }

                const lookupKey = `${searchValue}|${billMonthRaw || 'AUTO'}|${currentOrValue || 'NO_OR'}`;
                if (lookupKey === lastLookupKey && lastLookupKey !== null) {
                    return; // Skip if same lookup, but allow if lastLookupKey was reset
                }

                if (currentLookupController) {
                    currentLookupController.abort();
                }

                currentLookupController = new AbortController();
                lockPaidOrBreakdown = false;
                // Keep OR-based breakdown mode only when user explicitly searched by OR #.
                // A generated/typed OR value alone should not override bill-month breakdown.
                useOrForBreakdownLookup = useOrForBreakdownLookup && currentOrValue !== '';
                updateLookupStatus(`Looking up billing for ${searchValue}…`, 'info');

                // Clear name and address so they refresh with the new account (avoid showing previous account's location)
                const currentBalanceAccountLabel = document.getElementById('currentBalanceAccountLabel');
                const currentBalanceAccountAddress = document.getElementById('currentBalanceAccountAddress');
                const currentBalanceAccountRow = document.getElementById('currentBalanceAccountRow');
                if (currentBalanceAccountLabel) {
                    currentBalanceAccountLabel.textContent = '';
                    currentBalanceAccountLabel.style.display = 'none';
                }
                if (currentBalanceAccountAddress) {
                    currentBalanceAccountAddress.textContent = '';
                    currentBalanceAccountAddress.style.display = 'none';
                }
                if (currentBalanceAccountRow) {
                    currentBalanceAccountRow.style.display = 'none';
                }
                renderScDiscountLedgerText(null);

                try {
                    let url = `${lookupEndpoint}?`;
                    // If Account Number field has value, search both account_number and account_name
                    if (accountNumber) {
                        url += `account_number=${encodeURIComponent(accountNumber)}`;
                        url += `&account_name=${encodeURIComponent(accountNumber)}`; // Also search as account name
                    } else if (accountName) {
                        // If only Account Name field has value, search account_name
                        url += `account_name=${encodeURIComponent(accountName)}`;
                    }
                    if (billMonthRaw) {
                        url += `&bill_month=${encodeURIComponent(billMonthRaw)}`;
                    }
                    const response = await fetch(url, { signal: currentLookupController.signal });
                    const payload = await response.json().catch(() => null);

                    if (!response.ok || !payload) {
                        const message = payload?.message || 'Unable to fetch billing record.';
                        updateLookupStatus(message, 'danger');
                        setPaymentStatusForMonth(null);
                        lastLookupKey = null;
                        return;
                    }

                    if (!payload.success) {
                        updateLookupStatus(payload.message || 'Billing record not found.', 'warning');
                        setPaymentStatusForMonth(null);
                        lastLookupKey = null;
                        return;
                    }
                    
                    const paidWithBreakdownForMonth = payload.data?.payment?.status === 'paid'
                        && (payload.data?.payment?.current_bill !== undefined
                            || payload.data?.payment?.penalty !== undefined
                            || payload.data?.payment?.meter_maintenance !== undefined);
                    const balanceFromPayload = parseNumeric(payload.data?.account?.current_balance);
                    const hasOutstandingBalance = (parseFloat(balanceFromPayload) || 0) > 0.009;
                    const shouldUseOrBreakdownForPaidMonth = currentOrValue !== '' && paidWithBreakdownForMonth && hasOutstandingBalance;
                    useOrForBreakdownLookup = shouldUseOrBreakdownForPaidMonth;
                    // Keep paid payload lock only when we are NOT intentionally switching to OR-based breakdown.
                    lockPaidOrBreakdown = !shouldUseOrBreakdownForPaidMonth && paidWithBreakdownForMonth && hasOutstandingBalance;

                    populateFromLookup(payload.data);
                    if (paymentRemarksField) {
                        paymentRemarksField.value = '';
                    }

                    const resolvedBillMonth = payload.data?.billing?.bill_month_input || '';
                    const resolvedBillMonthDisplay = payload.data?.billing?.bill_month_display || resolvedBillMonth;
                    const effectiveBillMonth = resolvedBillMonth || billMonthRaw || '';
                    const resolvedAccount = payload.data?.account?.number || searchValue;

                    const statusMessage = payload.message
                        ?? `Billing record loaded for ${resolvedAccount}${resolvedBillMonthDisplay ? ` (${resolvedBillMonthDisplay})` : ''}.`;

                    const messageLower = (payload.message || '').toLowerCase();
                    let toneBase = resolvedBillMonth ? 'success' : 'info';
                    if (messageLower.includes('no billing history')) {
                        toneBase = 'warning';
                    } else if (messageLower.includes('schedule')) {
                        toneBase = 'info';
                    }

                    updateLookupStatus(statusMessage, toneBase);
                    lastLookupKey = `${searchValue}|${effectiveBillMonth || 'AUTO'}`;
                    // Load full breakdown after account lookup - use same logic as date picker
                    // Use setTimeout to ensure populateFromLookup has finished setting transaction date
                    setTimeout(() => {
                        if (lockPaidOrBreakdown) {
                            return;
                        }
                        if (resolvedAccount) {
                            const dateVal = transactionDateField?.value?.trim();
                            if (!dateVal) return;
                            
                            // Prefer dropdown's selected month if set; otherwise derive from date (same as date picker)
                            const unpaidBillMonth = document.getElementById('unpaidBillMonth');
                            const selectedMonth = unpaidBillMonth?.value?.trim();
                            let billMonthKey = selectedMonth;
                            if (!billMonthKey) {
                                const [y, m] = dateVal.split('-');
                                if (m && y) {
                                    billMonthKey = `${m}-${y}`;
                                }
                            }
                            
                            // Sync bill month field
                            if (billMonthKey && billMonthField) {
                                billMonthField.value = billMonthKey;
                            }
                            
                            // Use bill month (from dropdown if set, else from date) so breakdown reflects whether this date is before or after due_date
                            if (billMonthKey) {
                                loadBillMonthDetails(resolvedAccount, billMonthKey, dateVal, dateVal);
                            }
                        }
                    }, 100);
                } catch (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    console.error('Billing lookup error:', error);
                    updateLookupStatus('Unexpected error while fetching billing data.', 'danger');
                    setPaymentStatusForMonth(null);
                    lastLookupKey = null;
                } finally {
                    currentLookupController = null;
                }
            };

            // Search billing data by OR # (uses consumer_payments table)
            const performLookupByOr = async () => {
                const orField = document.getElementById('officialReceipt');
                const orNumber = orField ? String(orField.value || '').trim() : '';
                if (paymentRemarksField) {
                    paymentRemarksField.value = '';
                }
                if (!orNumber) {
                    useOrForBreakdownLookup = false;
                    lockPaidOrBreakdown = false;
                    updateLookupStatus('Enter an OR number to search.', 'muted');
                    return;
                }
                if (currentLookupController) {
                    currentLookupController.abort();
                }
                currentLookupController = new AbortController();
                updateLookupStatus(`Looking up by OR # ${orNumber}…`, 'info');

                // Clear name and address so they refresh with the account for this OR
                const rowEl = document.getElementById('currentBalanceAccountRow');
                const labelEl = document.getElementById('currentBalanceAccountLabel');
                const addrEl = document.getElementById('currentBalanceAccountAddress');
                if (labelEl) { labelEl.textContent = ''; labelEl.style.display = 'none'; }
                if (addrEl) { addrEl.textContent = ''; addrEl.style.display = 'none'; }
                if (rowEl) rowEl.style.display = 'none';

                try {
                    const url = `${lookupEndpoint}?or_number=${encodeURIComponent(orNumber)}`;
                    const response = await fetch(url, { signal: currentLookupController.signal });
                    const payload = await response.json().catch(() => null);
                    if (!response.ok || !payload) {
                        useOrForBreakdownLookup = false;
                        lockPaidOrBreakdown = false;
                        updateLookupStatus(payload?.message || 'Unable to fetch billing record.', 'danger');
                        setPaymentStatusForMonth('unpaid');
                        lastLookupKey = null;
                        return;
                    }
                    if (!payload.success) {
                        useOrForBreakdownLookup = false;
                        lockPaidOrBreakdown = false;
                        updateLookupStatus(payload.message || 'OR number not found.', 'warning');
                        setPaymentStatusForMonth('unpaid'); // OR # not in consumer_payment = unused → Unpaid
                        lastLookupKey = null;
                        return;
                    }
                    useOrForBreakdownLookup = true;
                    const paidWithBreakdown = payload.data?.payment?.status === 'paid' && (payload.data?.payment?.current_bill !== undefined || payload.data?.payment?.penalty !== undefined);
                    lockPaidOrBreakdown = !!paidWithBreakdown;
                    populateFromLookup(payload.data);
                    if (paymentRemarksField) {
                        paymentRemarksField.value = '';
                    }
                    // Keep the OR # the user entered (e.g. 100017) instead of overwriting with generated or server value
                    const orFieldAfter = document.getElementById('officialReceipt');
                    if (orFieldAfter) orFieldAfter.value = orNumber;
                    const resolvedAccount = payload.data?.account?.number || '';
                    const resolvedBillMonth = payload.data?.billing?.bill_month_input || payload.data?.billing?.bill_month_display || '';
                    updateLookupStatus(payload.message || `Billing record loaded for OR # ${orNumber} (${resolvedAccount}).`, 'success');
                    lastLookupKey = `${orNumber}|OR|${resolvedAccount}`;
                    // When paid with breakdown from OR # search, skip loadBillMonthDetails to avoid modal and overwriting breakdown
                    if (!paidWithBreakdown) {
                        setTimeout(() => {
                            if (resolvedAccount) {
                                const dateVal = transactionDateField?.value?.trim();
                                if (!dateVal) return;
                                const unpaidBillMonth = document.getElementById('unpaidBillMonth');
                                const selectedMonth = unpaidBillMonth?.value?.trim();
                                let billMonthKey = selectedMonth;
                                if (!billMonthKey) {
                                    const [y, m] = dateVal.split('-');
                                    if (m && y) billMonthKey = `${m}-${y}`;
                                }
                                if (billMonthKey && billMonthField) billMonthField.value = billMonthKey;
                                if (billMonthKey) loadBillMonthDetails(resolvedAccount, billMonthKey, dateVal, dateVal);
                            }
                        }, 100);
                    }
                } catch (e) {
                    if (e.name === 'AbortError') return;
                    useOrForBreakdownLookup = false;
                    lockPaidOrBreakdown = false;
                    console.error('Lookup by OR error:', e);
                    updateLookupStatus('Error looking up by OR number.', 'danger');
                    setPaymentStatusForMonth('unpaid');
                    lastLookupKey = null;
                } finally {
                    currentLookupController = null;
                }
            };

           
            // Generate unique OR number
            const generateOrNumber = async () => {
                const orField = document.getElementById('officialReceipt');
                if (!orField) return;

                try {
                    const response = await fetch('{{ route("billing-payment.generate-or") }}');
                    const data = await response.json();

                    if (data.success && data.or_number) {
                        orField.value = data.or_number;
                    } else {
                        // Fallback: generate a random 6-digit number
                        const fallbackOr = String(Math.floor(100000 + Math.random() * 900000));
                        orField.value = fallbackOr;
                    }
                } catch (error) {
                    console.error('Error generating OR number:', error);
                    // Fallback: generate a random 6-digit number
                    const fallbackOr = String(Math.floor(100000 + Math.random() * 900000));
                    orField.value = fallbackOr;
                }
            };

                const resetForm = () => {
                if (currentLookupController) {
                    currentLookupController.abort();
                    currentLookupController = null;
                }

                lastLookupKey = null;
                currentDownloadedId = null; // Clear downloaded_id on reset
                latestBillMonth = null; // Reset latest bill month
                arrearsPreviousManuallyEdited = false; // Reset manual edit flag
                useOrForBreakdownLookup = false;
                lockPaidOrBreakdown = false;
                
                // Disable Update Payment when form is cleared (no loaded record)
                if (updatePaymentBtn) {
                    updatePaymentBtn.disabled = true;
                }
                
                // Disable Delete Payment when form is cleared
                const deletePaymentBtn = document.getElementById('deletePaymentBtn');
                if (deletePaymentBtn) {
                    deletePaymentBtn.disabled = true;
                }
                
                // Re-enable submit button on reset
                const submitBtn = document.getElementById('savePaymentBtn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('disabled');
                    submitBtn.innerHTML = '<i class="fas fa-save mr-1"></i>Save Payment';
                }
                if (formStatusText) {
                    formStatusText.textContent = 'Review all fields before saving. Totals are computed automatically.';
                }
                
                // Reset Senior Citizen Discount checkbox
                const enableSeniorDiscountCheckbox = document.getElementById('enableSeniorDiscount');
                if (enableSeniorDiscountCheckbox) {
                    enableSeniorDiscountCheckbox.checked = false;
                }
                
                // Enable all form fields
                disableFormFields(false);
                
                form.reset();

                // Clear all fields to empty/zero
                const fieldsToClear = [
                    'fieldCurrentBill', 'fieldPenalty', 'fieldMaintenance', 'fieldAdvances',
                    'fieldArrearsCurrent', 'fieldArrearsPrevious', 'fieldSeniorDiscount',
                    'fieldOthers', 'fieldMaterials', 'fieldFees', 'fieldInspection',
                    'accountNumber', 'accountName', 'billMonth', 'transactionDate',
                    'paymentType', 'paymentRemarks', 'cashTendered', 'cashChange',
                    'officialReceipt', 'referenceNumber'
                ];

                fieldsToClear.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        if (field.type === 'number') {
                            setNumberFieldValue(field, 0);
                        } else if (field.type === 'checkbox') {
                            field.checked = false;
                        } else {
                            field.value = '';
                        }
                    }
                });

                // Clear SUNDRIES fields
                for (let i = 1; i <= 4; i++) {
                    const acctDisplay = document.getElementById('sundryAcctCodeDisplay' + i);
                    const acctHidden = document.getElementById('sundryAcctCode' + i);
                    const acctDropdown = document.getElementById('sundryAcctCodeDropdown' + i);
                    const amountField = document.getElementById('sundryAmount' + i);
                    const bamNoHidden = document.getElementById('sundryBamNo' + i);
                    if (acctDisplay) {
                        acctDisplay.value = '';
                        acctDisplay.placeholder = '— Select Acct Code —';
                    }
                    if (acctHidden) acctHidden.value = '';
                    if (bamNoHidden) bamNoHidden.value = '';
                    if (acctDropdown) acctDropdown.style.display = 'none';
                    if (amountField) setNumberFieldValue(amountField, 0);
                }
                updateSundryTotals();

                // Reset current balance
                const currentBalanceField = document.getElementById('currentBalance');
                const currentBalanceDisplay = document.getElementById('currentBalanceDisplay');
                const currentBalanceAccountLabel = document.getElementById('currentBalanceAccountLabel');
                const formattedZero = formatCurrency(0);
                if (currentBalanceField) {
                    currentBalanceField.value = formattedZero;
                }
                if (currentBalanceDisplay) {
                    currentBalanceDisplay.textContent = formattedZero;
                }
                if (currentBalanceAccountLabel) {
                    currentBalanceAccountLabel.textContent = '';
                    currentBalanceAccountLabel.style.display = 'none';
                }
                const currentBalanceAccountAddress = document.getElementById('currentBalanceAccountAddress');
                if (currentBalanceAccountAddress) {
                    currentBalanceAccountAddress.textContent = '';
                    currentBalanceAccountAddress.style.display = 'none';
                }
                const currentBalanceAccountRow = document.getElementById('currentBalanceAccountRow');
                if (currentBalanceAccountRow) {
                    currentBalanceAccountRow.style.display = 'none';
                }
                renderScDiscountLedgerText(null);
                currentBalanceValue = 0;
                
                // Hide and reset bill month selector
                const billMonthSelectorGroup = document.getElementById('billMonthSelectorGroup');
                const unpaidBillMonth = document.getElementById('unpaidBillMonth');
                if (billMonthSelectorGroup) {
                    billMonthSelectorGroup.style.display = 'none';
                }
                if (unpaidBillMonth) {
                    unpaidBillMonth.innerHTML = '<option value="">-- Select Month --</option>';
                    unpaidBillMonth.value = '';
                }

                // Disable View Ledger button when form is reset
                if (viewLedgerBtn) {
                    viewLedgerBtn.disabled = true;
                }

                // Ensure Senior Citizen Discount field is readonly and reset
                const seniorDiscountField = document.getElementById('fieldSeniorDiscount');
                if (seniorDiscountField) {
                    seniorDiscountField.readOnly = true;
                }

                if (cashTenderedField) {
                    cashTenderedField.value = '';
                }
                if (cashChangeField) {
                    cashChangeField.value = '';
                }

                // Clear lookup status and payment status badge
                updateLookupStatus('Form cleared. Enter account number and bill month to load billing data.', 'muted');
                setPaymentStatusForMonth(null);
                
                // Reset totals
                updateTotals();
                
                // Regenerate OR number when form is reset
                generateOrNumber();
            };

            // Format charge inputs:
            // - On focus: show/select 0 so user can type immediately.
            // - On blur: always format to 2 decimals (e.g. 40 -> 40.00).
            chargeInputs.forEach(input => {
                input.addEventListener('input', updateTotals);

                input.addEventListener('focus', () => {
                    const raw = (input.value ?? '').toString().trim();
                    const numeric = parseNumeric(raw);
                    if (raw === '' || numeric === 0) {
                        input.value = '0';
                    }
                    // Selecting makes typing replace the current value (fast entry).
                    if (typeof input.select === 'function') {
                        input.select();
                    }
                });

                input.addEventListener('blur', () => {
                    setNumberFieldValue(input, parseNumeric(input.value));
                    updateTotals();
                });
            });
            discountInputs.forEach(input => input.addEventListener('input', updateTotals));
            if (cashTenderedField) {
                cashTenderedField.addEventListener('input', updateTotals);
                cashTenderedField.addEventListener('focus', () => {
                    const raw = (cashTenderedField.value ?? '').toString().trim();
                    const numeric = parseNumeric(raw);
                    if (raw === '' || numeric === 0) {
                        cashTenderedField.value = '0';
                    }
                    if (typeof cashTenderedField.select === 'function') {
                        cashTenderedField.select();
                    }
                });
                cashTenderedField.addEventListener('blur', () => {
                    setNumberFieldValue(cashTenderedField, parseNumeric(cashTenderedField.value));
                    updateTotals();
                });
            }
            
            // Track manual edits to Arrears — Previous Month field
            const arrearsPreviousField = document.getElementById('fieldArrearsPrevious');
            if (arrearsPreviousField) {
                arrearsPreviousField.addEventListener('input', () => {
                    arrearsPreviousManuallyEdited = true;
                    updateTotals();
                });
                // Also track on focus to allow manual editing
                arrearsPreviousField.addEventListener('focus', () => {
                    // Allow manual editing - don't auto-calculate while user is editing
                });
            }
            
            // Manual Senior Citizen Discount - enable/disable via checkbox
            const enableSeniorDiscountCheckbox = document.getElementById('enableSeniorDiscount');
            const seniorDiscountField = document.getElementById('fieldSeniorDiscount');
            const currentBillField = document.getElementById('fieldCurrentBill');
            
            if (enableSeniorDiscountCheckbox) {
                // When checkbox is toggled, apply or remove discount
                enableSeniorDiscountCheckbox.addEventListener('change', applySeniorCitizenDiscount);
            }
            
            // When Current Bill changes and discount is enabled, recalculate
            if (currentBillField && enableSeniorDiscountCheckbox) {
                currentBillField.addEventListener('input', () => {
                    if (enableSeniorDiscountCheckbox.checked) {
                        applySeniorCitizenDiscount();
                    } else {
                        updateTotals();
                    }
                });
            }
            
            // Allow manual adjustment of discount amount when enabled
            if (seniorDiscountField) {
                seniorDiscountField.addEventListener('input', updateTotals);
            }

            const debouncedLookup = debounce(performLookup, 500);
            let suggestionsController = null;
            const suggestionsDropdown = document.getElementById('accountSuggestions');
            
            // Fetch account suggestions for autocomplete
            async function fetchAccountSuggestions(searchTerm) {
                if (!searchTerm || searchTerm.length < 2) {
                    if (suggestionsDropdown) {
                        suggestionsDropdown.style.display = 'none';
                    }
                    return;
                }
                
                if (suggestionsController) {
                    suggestionsController.abort();
                }
                
                suggestionsController = new AbortController();
                
                try {
                    const response = await fetch(`${accountSuggestionsEndpoint}?q=${encodeURIComponent(searchTerm)}`, {
                        signal: suggestionsController.signal
                    });
                    const result = await response.json();
                    
                    if (result.success && result.data && result.data.length > 0) {
                        displaySuggestions(result.data);
                    } else {
                        if (suggestionsDropdown) {
                            suggestionsDropdown.style.display = 'none';
                        }
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        console.error('Error fetching suggestions:', error);
                    }
                    if (suggestionsDropdown) {
                        suggestionsDropdown.style.display = 'none';
                    }
                } finally {
                    suggestionsController = null;
                }
            }
            
            // Display suggestions in dropdown
            const displaySuggestions = (suggestions) => {
                if (!suggestionsDropdown) return;
                
                suggestionsDropdown.innerHTML = '';
                
                suggestions.forEach(suggestion => {
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action';
                    item.style.cursor = 'pointer';
                    item.innerHTML = `
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>${suggestion.account_number}</strong>
                            </div>
                            <div class="text-muted">
                                ${suggestion.account_name || ''}
                            </div>
                        </div>
                    `;
                    
                    item.addEventListener('click', (e) => {
                        e.preventDefault();
                        accountNumberField.value = suggestion.account_number;
                        if (accountNameField) {
                            accountNameField.value = suggestion.account_name || '';
                        }
                        suggestionsDropdown.style.display = 'none';
                        // Trigger lookup for selected account
                        lastLookupKey = null;
                        performLookup();
                    });
                    
                    suggestionsDropdown.appendChild(item);
                });
                
                suggestionsDropdown.style.display = 'block';
            };
            
            const debouncedSuggestions = debounce(fetchAccountSuggestions, 300);
            
            // Account Number field can search both Account Number and Account Name
            if (accountNumberField) {
                accountNumberField.addEventListener('input', (e) => {
                    const value = e.target.value.trim();
                    
                    // Clear account name when typing in account number field (to avoid confusion)
                    if (accountNameField && accountNameField.value) {
                        accountNameField.value = '';
                    }
                    
                    // Show suggestions as user types
                    if (value.length >= 2) {
                        debouncedSuggestions(value);
                    } else {
                        if (suggestionsDropdown) {
                            suggestionsDropdown.style.display = 'none';
                        }
                    }
                    
                    // Reset lastLookupKey to allow new search
                    lastLookupKey = null;
                });
                
                accountNumberField.addEventListener('blur', (e) => {
                    // Hide suggestions after a short delay to allow click events
                    setTimeout(() => {
                        if (suggestionsDropdown) {
                            suggestionsDropdown.style.display = 'none';
                        }
                        // Perform lookup if there's a value
                        if (accountNumberField.value.trim()) {
                            debouncedLookup();
                        }
                    }, 200);
                });
                
                accountNumberField.addEventListener('focus', (e) => {
                    // Show suggestions again if there's a value
                    const value = e.target.value.trim();
                    if (value.length >= 2) {
                        debouncedSuggestions(value);
                    }
                });
            }
            
            // Hide suggestions when clicking outside
            document.addEventListener('click', (e) => {
                if (suggestionsDropdown && !accountNumberField.contains(e.target) && !suggestionsDropdown.contains(e.target)) {
                    suggestionsDropdown.style.display = 'none';
                }
            });
            
            // Account Name field can also search (but Account Number field takes priority)
            if (accountNameField) {
                accountNameField.addEventListener('input', (e) => {
                    // Clear account number when typing in account name field (to avoid confusion)
                    if (accountNumberField && accountNumberField.value) {
                        accountNumberField.value = '';
                    }
                    // Reset lastLookupKey to allow new search
                    lastLookupKey = null;
                    debouncedLookup();
                });
                accountNameField.addEventListener('blur', debouncedLookup);
            }

            if (billMonthField) {
                const handleBillMonthChange = () => {
                    debouncedLookup();
                    const accountNumber = accountNumberField?.value?.trim();
                    const billMonthKey = billMonthField.value?.trim();
                    const txDate = transactionDateField?.value?.trim();
                    if (accountNumber && billMonthKey) {
                        loadBillMonthDetails(accountNumber, billMonthKey, txDate, txDate);
                    }
                };
                billMonthField.addEventListener('change', handleBillMonthChange);
                billMonthField.addEventListener('blur', handleBillMonthChange);
            }

            // When user changes the Date, use that date for the breakdown (consumer_ledgers date/due_date).
            // Always prefer the dropdown's selected month if set; otherwise use date to derive bill month.
            // e.g. 23/1/2026 is after due 20/1 → Current 0, Arrears CY 390, Arrears PY 0; before due → Current 195, Arrears PY 195.
            if (transactionDateField) {
                transactionDateField.addEventListener('change', () => {
                    const dateVal = transactionDateField.value;
                    const accountNumber = accountNumberField?.value?.trim();
                    if (!dateVal || !accountNumber) return;
                    // Prefer dropdown's selected month if set; otherwise derive from date
                    const unpaidBillMonth = document.getElementById('unpaidBillMonth');
                    const selectedMonth = unpaidBillMonth?.value?.trim();
                    let billMonthKey = selectedMonth;
                    if (!billMonthKey) {
                        const [y, m] = dateVal.split('-');
                        if (m && y) {
                            billMonthKey = `${m}-${y}`;
                        }
                    }
                    if (billMonthKey && billMonthField) {
                        billMonthField.value = billMonthKey;
                    }
                    // Use bill month (from dropdown if set, else from date) so breakdown reflects whether this date is before or after due_date
                    if (billMonthKey) {
                        loadBillMonthDetails(accountNumber, billMonthKey, dateVal, dateVal);
                    }
                });
            }

            // Payment status: based on consumer_payment only. Unpaid when OR # empty or not found; Paid when OR # exists in consumer_payment.
            const orReceiptField = document.getElementById('officialReceipt');
            if (orReceiptField && typeof setPaymentStatusForMonth === 'function') {
                orReceiptField.addEventListener('input', () => {
                    const val = orReceiptField.value != null ? String(orReceiptField.value).trim() : '';
                    // When OR # is cleared → Unpaid. When OR has value, status is set by API (bill-month-details or lookup-by-OR from consumer_payment).
                    if (val === '') setPaymentStatusForMonth('unpaid');
                });
                orReceiptField.addEventListener('blur', () => {
                    const val = orReceiptField.value != null ? String(orReceiptField.value).trim() : '';
                    if (val === '') setPaymentStatusForMonth('unpaid');
                });
                orReceiptField.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (typeof performLookupByOr === 'function') performLookupByOr();
                    }
                });
            }
            // Auto-search by OR # when user enters a number (on blur or after typing stops)
            if (orReceiptField && typeof performLookupByOr === 'function') {
                let orSearchDebounce = null;
                orReceiptField.addEventListener('input', () => {
                    const val = orReceiptField.value != null ? String(orReceiptField.value).trim() : '';
                    if (val.length < 3) return;
                    clearTimeout(orSearchDebounce);
                    orSearchDebounce = setTimeout(() => performLookupByOr(), 600);
                });
                // Trigger lookup immediately when field value is committed (paste/select/date-entry behavior)
                orReceiptField.addEventListener('change', () => {
                    const val = orReceiptField.value != null ? String(orReceiptField.value).trim() : '';
                    if (val !== '') performLookupByOr();
                });
                orReceiptField.addEventListener('blur', () => {
                    const val = orReceiptField.value != null ? String(orReceiptField.value).trim() : '';
                    if (val !== '') performLookupByOr();
                });
                // If OR is already present on load, fetch right away.
                const initialOr = orReceiptField.value != null ? String(orReceiptField.value).trim() : '';
                if (initialOr !== '') {
                    setTimeout(() => performLookupByOr(), 100);
                }
            }

            resetButton.addEventListener('click', async event => {
                event.preventDefault();

                const orNumberRaw = document.getElementById('officialReceipt')?.value ?? '';
                const orNumber = String(orNumberRaw).trim().replace(/-SC$/i, '');
                if (!orNumber) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'OR Number Required',
                        text: 'Enter an OR number first before marking it as cancelled.',
                        confirmButtonColor: '#4e73df'
                    });
                    return;
                }

                const confirmResult = await Swal.fire({
                    icon: 'warning',
                    title: 'Mark OR as Cancelled?',
                    html: `<p>This will add OR # <strong>${orNumber}</strong> to Collection Report as <strong>Cancelled</strong>.</p>`,
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Mark Cancelled',
                    cancelButtonText: 'No',
                    confirmButtonColor: '#e74a3b'
                });

                if (!confirmResult.isConfirmed) return;

                try {
                    const response = await fetch(cancelledOrEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value
                        },
                        body: JSON.stringify({ or_number: orNumber })
                    });
                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Unable to save cancelled OR.');
                    }

                    await Swal.fire({
                        icon: 'success',
                        title: 'Cancelled OR Saved',
                        html: `<p>OR # <strong>${orNumber}</strong> was saved in Collection Report as <strong>Cancelled</strong>.</p>`,
                        confirmButtonColor: '#1cc88a'
                    });

                    resetForm();
                    generateOrNumber();
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed to Save Cancelled OR',
                        text: error.message || 'Please try again.',
                        confirmButtonColor: '#e74a3b'
                    });
                }
            });

            form.addEventListener('submit', async event => {
                event.preventDefault();
                updateTotals();

                const accountNumberForPayment = (accountNumberField && accountNumberField.value) ? accountNumberField.value.trim() : '';
                const amountDueForValidation = parseNumeric(totalField.value.replace(/[₱,\s]/g, ''));
                const hasAccountAndAmount = accountNumberForPayment !== '' && amountDueForValidation > 0;
                if (!currentDownloadedId && !hasAccountAndAmount) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Billing Data Required',
                        text: 'Please load billing data first by entering an account number.',
                        confirmButtonColor: '#4e73df'
                    });
                    return;
                }

                const orNumber = document.getElementById('officialReceipt')?.value?.trim();
                if (!orNumber) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'OR Number Required',
                        text: 'OR number is required. Please generate an OR number.',
                        confirmButtonColor: '#4e73df'
                    });
                    return;
                }

                const amountDue = parseNumeric(totalField.value.replace(/[₱,\s]/g, ''));
                const amountTendered = parseNumeric(cashTenderedField.value);

                
                
                if (!amountTendered || amountTendered <= 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Cash Tendered Required',
                        text: 'Please enter the cash tendered amount.',
                        confirmButtonColor: '#4e73df'
                    });
                    return;
                }

                if (amountTendered < amountDue) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Insufficient Amount',
                        text: 'Amount tendered must be equal to or greater than the amount due.',
                        confirmButtonColor: '#e74a3b'
                    });
                    return;
                }

                const paymentMethod = paymentTypeField.value || 'cash';
                const referenceNumber = document.getElementById('referenceNumber')?.value?.trim() || null;
                const remarks = paymentRemarksField.value?.trim() || null;

                // Get current bill and breakdown values from the form for consumer_payments
                const currentBillValue = parseNumeric(document.getElementById('fieldCurrentBill')?.value || 0);
                const penaltyValue = parseNumeric(document.getElementById('fieldPenalty')?.value || 0);
                const meterMaintenanceValue = parseNumeric(document.getElementById('fieldMaintenance')?.value || 0);
                const advancesValue = parseNumeric(document.getElementById('fieldAdvances')?.value || 0);
                const arrearsCyValue = parseNumeric(document.getElementById('fieldArrearsCurrent')?.value || 0);
                const arrearsPyValue = parseNumeric(document.getElementById('fieldArrearsPrevious')?.value || 0);
                const othersValue = parseNumeric(document.getElementById('fieldOthers')?.value || 0);
                const materialsValue = parseNumeric(document.getElementById('fieldMaterials')?.value || 0);
                const feesChargesValue = parseNumeric(document.getElementById('fieldFees')?.value || 0);
                const inspectionFeeValue = parseNumeric(document.getElementById('fieldInspection')?.value || 0);

                // Check if Senior Citizen Discount is enabled
                const enableSeniorDiscountCheckbox = document.getElementById('enableSeniorDiscount');
                const hasSeniorCitizenDiscount = enableSeniorDiscountCheckbox && enableSeniorDiscountCheckbox.checked;
                
                // Get Senior Citizen Discount amount
                const seniorDiscountField = document.getElementById('fieldSeniorDiscount');
                const scDiscountAmount = seniorDiscountField ? parseNumeric(seniorDiscountField.value || 0) : 0;

                // Ensure base OR number doesn't have -SC suffix
                const baseOrNumber = orNumber.replace(/-SC$/i, '');
                const transactionDateValue = transactionDateField ? transactionDateField.value : null;

                // Disable submit button
                const submitBtn = document.getElementById('savePaymentBtn');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';

                // Function to save a single payment entry
                const savePaymentEntry = async (paymentPayload) => {
                    const response = await fetch(savePaymentEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value
                        },
                        body: JSON.stringify(paymentPayload)
                    });
                    return await response.json();
                };

                try {
                    // Collect paid sundries (only rows with acct_code and amount > 0)
                    const sundriesToSave = [];
                    for (let i = 1; i <= 4; i++) {
                        const code   = (document.getElementById('sundryAcctCode' + i) || {}).value || '';
                        const bamNo  = (document.getElementById('sundryBamNo'    + i) || {}).value || '';
                        const amount = parseNumeric((document.getElementById('sundryAmount' + i) || {}).value || 0);
                        if (code && amount > 0) {
                            sundriesToSave.push({ acct_code: code, bam_no: bamNo, amount: amount });
                        }
                    }

                    // Single payload: backend creates one main PAYMENT row + one -SC row when senior_citizen_discount > 0
                    const payload = {
                        downloaded_id: currentDownloadedId || null,
                        account_number: currentDownloadedId ? undefined : accountNumberForPayment,
                        account_name: bamSearchAccountName || undefined,
                        amount_due: amountDue,
                        amount_tendered: amountTendered,
                        payment_method: paymentMethod,
                        reference_number: referenceNumber,
                        remarks: remarks,
                        official_receipt_number: baseOrNumber,
                        is_update: false,
                        current_bill: currentBillValue,
                        senior_citizen_discount: (hasSeniorCitizenDiscount && scDiscountAmount > 0) ? scDiscountAmount : 0,
                        penalty: penaltyValue,
                        meter_maintenance: meterMaintenanceValue,
                        advances: advancesValue,
                        arrears_cy: arrearsCyValue,
                        arrears_py: arrearsPyValue,
                        others: othersValue,
                        materials: materialsValue,
                        fees_charges: feesChargesValue,
                        inspection_fee: inspectionFeeValue,
                        transaction_date: transactionDateValue || undefined,
                        sundries: sundriesToSave.length > 0 ? sundriesToSave : undefined,
                    };

                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving Payment...';
                    const result = await savePaymentEntry(payload);

                    if (result.success) {
                        const discountLine = (hasSeniorCitizenDiscount && scDiscountAmount > 0)
                            ? `<p><strong>Senior Citizen Discount:</strong> ${formatCurrency(scDiscountAmount)}</p>`
                            : '';
                        Swal.fire({
                            icon: 'success',
                            title: 'Payment Saved Successfully!',
                            html: `
                                <div class="text-left">
                                    <p><strong>OR Number:</strong> ${result.data.official_receipt_number || baseOrNumber}</p>
                                    <p><strong>Amount:</strong> ${formatCurrency(amountDue)}</p>
                                    ${discountLine}
                                </div>
                            `,
                            confirmButtonColor: '#1cc88a',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            resetForm();
                            generateOrNumber();
                        });
                    } else {
                        // Check if it's a duplicate payment error
                        const isDuplicateError = result.message && (
                            result.message.toLowerCase().includes('duplicate') ||
                            result.message.toLowerCase().includes('already exists') ||
                            result.message.toLowerCase().includes('already has a recorded payment')
                        );
                        
                        Swal.fire({
                            icon: isDuplicateError ? 'warning' : 'error',
                            title: isDuplicateError ? 'Duplicate Payment Detected' : 'Payment Failed',
                            html: result.message ? `
                                <div class="text-left">
                                    <p>${result.message}</p>
                                    ${isDuplicateError ? '<p class="mt-2 text-danger"><strong>Please verify the account number and bill month.</strong></p>' : ''}
                                </div>
                            ` : 'Failed to save payment.',
                            confirmButtonColor: isDuplicateError ? '#f39c12' : '#e74a3b'
                        });
                    }
                } catch (error) {
                    console.error('Payment submission error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'Failed to save payment. Please try again.',
                        confirmButtonColor: '#e74a3b'
                    });
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            });

            // Update Payment button: update existing payment by OR # (no new ledger lines)
            if (updatePaymentBtn) {
                updatePaymentBtn.addEventListener('click', async function() {
                    const accountNumberForPayment = (accountNumberField && accountNumberField.value) ? accountNumberField.value.trim() : '';
                    const orNumber = document.getElementById('officialReceipt')?.value?.trim();
                    const canUpdateWithoutDownloaded = !!(accountNumberForPayment && orNumber);
                    if (!currentDownloadedId && !canUpdateWithoutDownloaded) {
                    Swal.fire({
                            icon: 'warning',
                            title: 'Load Record First',
                            text: 'Load a billing record by OR # or account number first, then use Update Payment to change existing payment data.',
                            confirmButtonColor: '#4e73df'
                        });
                        return;
                    }
                    if (!orNumber) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'OR Number Required',
                            text: 'OR number is required for update.',
                            confirmButtonColor: '#4e73df'
                        });
                        return;
                    }
                    updateTotals();
                    const amountDue = parseNumeric(totalField.value.replace(/[₱,\s]/g, ''));
                    const amountTendered = parseNumeric(cashTenderedField.value);
                    if (!amountTendered || amountTendered <= 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Cash Tendered Required',
                            text: 'Please enter the cash tendered amount.',
                            confirmButtonColor: '#4e73df'
                        });
                        return;
                    }
                    if (amountTendered < amountDue) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Insufficient Amount',
                            text: 'Amount tendered must be equal to or greater than the amount due.',
                            confirmButtonColor: '#e74a3b'
                        });
                        return;
                    }
                    const paymentMethod = paymentTypeField.value || 'cash';
                    const referenceNumber = document.getElementById('referenceNumber')?.value?.trim() || null;
                    const remarks = paymentRemarksField.value?.trim() || null;
                    const currentBillValue = parseNumeric(document.getElementById('fieldCurrentBill')?.value || 0);
                    const penaltyValue = parseNumeric(document.getElementById('fieldPenalty')?.value || 0);
                    const meterMaintenanceValue = parseNumeric(document.getElementById('fieldMaintenance')?.value || 0);
                    const advancesValue = parseNumeric(document.getElementById('fieldAdvances')?.value || 0);
                    const arrearsCyValue = parseNumeric(document.getElementById('fieldArrearsCurrent')?.value || 0);
                    const arrearsPyValue = parseNumeric(document.getElementById('fieldArrearsPrevious')?.value || 0);
                    const othersValue = parseNumeric(document.getElementById('fieldOthers')?.value || 0);
                    const materialsValue = parseNumeric(document.getElementById('fieldMaterials')?.value || 0);
                    const feesChargesValue = parseNumeric(document.getElementById('fieldFees')?.value || 0);
                    const inspectionFeeValue = parseNumeric(document.getElementById('fieldInspection')?.value || 0);
                    const enableSeniorDiscountCheckbox = document.getElementById('enableSeniorDiscount');
                    const hasSeniorCitizenDiscount = enableSeniorDiscountCheckbox && enableSeniorDiscountCheckbox.checked;
                    const seniorDiscountField = document.getElementById('fieldSeniorDiscount');
                    const scDiscountAmount = seniorDiscountField ? parseNumeric(seniorDiscountField.value || 0) : 0;
                    const baseOrNumber = orNumber.replace(/-SC$/i, '');
                    const transactionDateValue = transactionDateField ? transactionDateField.value : null;
                    const sundriesToSave = [];
                    for (let i = 1; i <= 4; i++) {
                        const code   = (document.getElementById('sundryAcctCode' + i) || {}).value || '';
                        const bamNo  = (document.getElementById('sundryBamNo'    + i) || {}).value || '';
                        const amount = parseNumeric((document.getElementById('sundryAmount' + i) || {}).value || 0);
                        if (code && amount > 0) {
                            sundriesToSave.push({ acct_code: code, bam_no: bamNo, amount: amount });
                        }
                    }
                    const payload = {
                        downloaded_id: currentDownloadedId || null,
                        account_number: currentDownloadedId ? undefined : accountNumberForPayment || undefined,
                        account_name: bamSearchAccountName || undefined,
                        amount_due: amountDue,
                        amount_tendered: amountTendered,
                        payment_method: paymentMethod,
                        reference_number: referenceNumber,
                        remarks: remarks,
                        official_receipt_number: baseOrNumber,
                        is_update: true,
                        current_bill: currentBillValue,
                        senior_citizen_discount: (hasSeniorCitizenDiscount && scDiscountAmount > 0) ? scDiscountAmount : 0,
                        penalty: penaltyValue,
                        meter_maintenance: meterMaintenanceValue,
                        advances: advancesValue,
                        arrears_cy: arrearsCyValue,
                        arrears_py: arrearsPyValue,
                        others: othersValue,
                        materials: materialsValue,
                        fees_charges: feesChargesValue,
                        inspection_fee: inspectionFeeValue,
                        transaction_date: transactionDateValue || undefined,
                        sundries: sundriesToSave.length > 0 ? sundriesToSave : undefined,
                    };
                    updatePaymentBtn.disabled = true;
                    const originalUpdateText = updatePaymentBtn.innerHTML;
                    updatePaymentBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Updating...';
                    try {
                        const response = await fetch(savePaymentEndpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value
                            },
                            body: JSON.stringify(payload)
                        });
                        const result = await response.json();
                        if (result.success) {
                            const discountLine = (hasSeniorCitizenDiscount && scDiscountAmount > 0)
                                ? `<p><strong>Senior Citizen Discount:</strong> ${formatCurrency(scDiscountAmount)}</p>`
                                : '';
                            Swal.fire({
                                icon: 'success',
                                title: 'Payment Updated Successfully!',
                                html: `
                                    <div class="text-left">
                                        <p><strong>OR Number:</strong> ${result.data.official_receipt_number || baseOrNumber}</p>
                                        <p><strong>Amount:</strong> ${formatCurrency(amountDue)}</p>
                                        ${discountLine}
                                    </div>
                                `,
                                confirmButtonColor: '#1cc88a',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                resetForm();
                                generateOrNumber();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Update Failed',
                                text: result.message || 'Failed to update payment.',
                                confirmButtonColor: '#e74a3b'
                            });
                        }
                    } catch (error) {
                        console.error('Update payment error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message || 'Failed to update payment. Please try again.',
                            confirmButtonColor: '#e74a3b'
                        });
                    } finally {
                        const hasAccountAndOr = !!(accountNumberField && accountNumberField.value && document.getElementById('officialReceipt') && document.getElementById('officialReceipt').value.trim());
                        updatePaymentBtn.disabled = !currentDownloadedId && !hasAccountAndOr;
                        updatePaymentBtn.innerHTML = originalUpdateText;
                        // Update Delete Payment button state
                        const deletePaymentBtn = document.getElementById('deletePaymentBtn');
                        if (deletePaymentBtn) {
                            const orNumber = document.getElementById('officialReceipt')?.value?.trim();
                            deletePaymentBtn.disabled = !orNumber;
                        }
                    }
                });
            }

            // Initialize Senior Citizen Discount field as readonly
            const seniorDiscountFieldInit = document.getElementById('fieldSeniorDiscount');
            if (seniorDiscountFieldInit) {
                seniorDiscountFieldInit.readOnly = true;
            }

            // Generate OR number when page loads
            generateOrNumber();

            // View Ledger button handler
            if (viewLedgerBtn) {
                viewLedgerBtn.addEventListener('click', function() {
                    const accountNumber = getAccountNumber();
                    const accountName = getAccountName();
                    const currentBalanceAccountAddress = document.getElementById('currentBalanceAccountAddress');
                    const accountAddress = (currentBalanceAccountAddress && currentBalanceAccountAddress.textContent)
                        ? currentBalanceAccountAddress.textContent.trim()
                        : '';
                    
                    if (!accountNumber && !accountName) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'No Account Selected',
                            text: 'Please load an account first to view the ledger.',
                            confirmButtonColor: '#4e73df'
                        });
                        return;
                    }
                    
                    // Store consumer data in sessionStorage for ledger page
                    const consumerData = {
                        account_no: accountNumber || accountName,
                        account_number: accountNumber || accountName,
                        account_name: accountName || accountNumber,
                        address: accountAddress,
                        address1: accountAddress
                    };
                    
                    sessionStorage.setItem('currentConsumer', JSON.stringify(consumerData));

                    // Update modal link for new tab option
                    if (openLedgerNewTab) {
                        openLedgerNewTab.href = ledgerRoute;
                    }

                    // Show loading state
                    if (ledgerLoading) {
                        ledgerLoading.classList.remove('d-none');
                    }
                    if (ledgerIframe) {
                        ledgerIframe.classList.add('d-none');
                        var sep = (ledgerRoute && ledgerRoute.indexOf('?') !== -1) ? '&' : '?';
                        ledgerIframe.src = ledgerRoute + sep + 'embed=1';
                        // When iframe loads, hide spinner
                        ledgerIframe.onload = () => {
                            if (ledgerLoading) {
                                ledgerLoading.classList.add('d-none');
                            }
                            ledgerIframe.classList.remove('d-none');
                        };
                    }

                    // Open modal
                    if (ledgerModal) {
                        ledgerModal.modal('show');
                    }
                });
            }

            // Reset iframe when modal closes
            if (ledgerModal) {
                ledgerModal.on('hidden.bs.modal', () => {
                    if (ledgerIframe) {
                        ledgerIframe.src = 'about:blank';
                        ledgerIframe.classList.add('d-none');
                    }
                    if (ledgerLoading) {
                        ledgerLoading.classList.remove('d-none');
                    }
                });
            }

            // Handle bill month selection (single month). Use transaction date when set so 12/16 cutoff applies: before due = Current 195, Penalty 0, Maintenance 20; on/after 12/16 = Current 0, Arrears CY 195, Penalty 19.5, Maintenance 20.
            const unpaidBillMonth = document.getElementById('unpaidBillMonth');
            const handleBillMonthSelection = () => {
                const billMonth = unpaidBillMonth?.value;
                const accountNumber = accountNumberField?.value?.trim();
                // Always sync the top "Bill Month" field with the dropdown selection
                if (billMonth && billMonthField) {
                    billMonthField.value = billMonth;
                }
                if (billMonth && accountNumber) {
                    const txDate = transactionDateField?.value?.trim();
                    if (txDate) {
                        loadBillMonthDetails(accountNumber, billMonth, txDate, txDate);
                    } else {
                        loadBillMonthDetails(accountNumber, billMonth);
                    }
                }
            };
            if (unpaidBillMonth) {
                unpaidBillMonth.addEventListener('change', handleBillMonthSelection);
            }

            // PIN required for Delete Payment
            var pendingDeleteCallback = null;
            function requestPinThenDelete(callback) {
                pendingDeleteCallback = callback;
                $('#paymentDeletePinInput').val('');
                $('#paymentDeletePinError').text('').removeClass('d-block');
                $('#paymentDeletePinInput').removeClass('is-invalid');
                $('#paymentDeletePinModal').modal('show');
                setTimeout(function() { document.getElementById('paymentDeletePinInput') && document.getElementById('paymentDeletePinInput').focus(); }, 300);
            }
            document.getElementById('paymentDeletePinVerifyBtn').addEventListener('click', function() {
                var pinInput = document.getElementById('paymentDeletePinInput');
                var errEl = document.getElementById('paymentDeletePinError');
                var pin = (pinInput && pinInput.value) ? pinInput.value.trim() : '';
                if (!pin) {
                    if (errEl) { errEl.textContent = 'Enter PIN.'; errEl.classList.add('d-block'); }
                    if (pinInput) pinInput.classList.add('is-invalid');
                    return;
                }
                var btn = this;
                btn.disabled = true;
                fetch('{{ route("consumer.verify-edit-pin") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '' },
                    body: JSON.stringify({ pin: pin })
                }).then(function(r) { return r.json(); }).then(function(data) {
                    btn.disabled = false;
                    if (data.success) {
                        $('#paymentDeletePinModal').modal('hide');
                        var cb = pendingDeleteCallback;
                        pendingDeleteCallback = null;
                        if (typeof cb === 'function') cb();
                    } else {
                        if (errEl) { errEl.textContent = data.message || 'Invalid PIN.'; errEl.classList.add('d-block'); }
                        if (pinInput) pinInput.classList.add('is-invalid');
                    }
                }).catch(function() {
                    btn.disabled = false;
                    if (errEl) { errEl.textContent = 'Request failed.'; errEl.classList.add('d-block'); }
                    if (pinInput) pinInput.classList.add('is-invalid');
                });
            });
            $('#paymentDeletePinInput').on('keydown', function(e) {
                if (e.which === 13) document.getElementById('paymentDeletePinVerifyBtn').click();
            });

            // Delete Payment Button - requires PIN first
            const deletePaymentBtn = document.getElementById('deletePaymentBtn');
            if (deletePaymentBtn) {
                deletePaymentBtn.addEventListener('click', function() {
                    const orNumber = document.getElementById('officialReceipt')?.value?.trim();
                    if (!orNumber) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'OR Number Required',
                            text: 'Please enter or load a record with an OR number to delete.',
                            confirmButtonColor: '#4e73df'
                        });
                        return;
                    }

                    requestPinThenDelete(function() {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Are you sure?',
                            html: `You are about to delete payment with OR #:<br><br><strong>${orNumber}</strong><br><br>This action cannot be undone!`,
                            showCancelButton: true,
                            confirmButtonColor: '#dc3545',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="fas fa-trash me-1"></i>Yes, Delete!',
                            cancelButtonText: '<i class="fas fa-times me-1"></i>Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                deletePayment(orNumber);
                            }
                        });
                    });
                });
            }

            // Delete payment function
            async function deletePayment(orNumber) {
                deletePaymentBtn.disabled = true;
                const originalDeleteText = deletePaymentBtn.innerHTML;
                deletePaymentBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Deleting...';
                try {
                    const response = await fetch(deletePaymentEndpoint, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value
                        },
                        body: JSON.stringify({ or_number: orNumber })
                    });
                    const result = await response.json();
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Payment Deleted!',
                            text: result.message || 'Payment has been deleted successfully.',
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            resetForm();
                            generateOrNumber();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Delete Failed',
                            text: result.message || 'Failed to delete payment.',
                            confirmButtonColor: '#e74a3b'
                        });
                    }
                } catch (error) {
                    console.error('Delete payment error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'Failed to delete payment. Please try again.',
                        confirmButtonColor: '#e74a3b'
                    });
                } finally {
                    deletePaymentBtn.disabled = !orNumber;
                    deletePaymentBtn.innerHTML = originalDeleteText;
                }
            }

            // Enable/disable delete button based on OR number
            const officialReceiptField = document.getElementById('officialReceipt');
            if (officialReceiptField && deletePaymentBtn) {
                const updateDeleteButtonState = () => {
                    const orNumber = officialReceiptField.value?.trim();
                    deletePaymentBtn.disabled = !orNumber;
                };
                officialReceiptField.addEventListener('input', updateDeleteButtonState);
                officialReceiptField.addEventListener('change', updateDeleteButtonState);
            }

            resetForm();
        });
    </script>
</body>
</html>