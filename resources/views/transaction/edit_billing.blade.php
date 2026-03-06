<!DOCTYPE html>
<html lang="en">
@include('partials.header')

<style>
    .edit-billing-shell { background: #ffffff; min-height: calc(100vh - 4rem); }
    .bam-header { background: #ffffff; border-bottom: 1px solid #e0e0e0; padding: 1rem 1.5rem; }
    .bam-tabs { background: #ffffff; border-bottom: 1px solid #e0e0e0; padding: 0 1rem; }
    .bam-tab { padding: 0.75rem 1rem; color: #666666; font-weight: 500; border-bottom: 3px solid transparent; display: inline-block; }
    .bam-tab.active { color: #333333; font-weight: 700; border-bottom-color: #2196F3; }
    .bam-form-card { background: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 1.5rem; margin: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .bam-form-card .form-label { color: #555555; font-size: 0.875rem; margin-bottom: 0.35rem; font-weight: 500; }
    .bam-form-card .form-control { background: #ffffff; border: 1px solid #dddddd; border-radius: 6px; color: #333333; }
    .bam-form-card .form-control:focus { border-color: #2196F3; box-shadow: 0 0 0 0.2rem rgba(33,150,243,0.25); }
    .bam-form-card .form-control::placeholder { color: #bbbbbb; }
    .bam-row { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
    .bam-col { flex: 1; min-width: 180px; }
    .bam-btn-save { background: #2196F3; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; }
    .bam-btn-cancel { background: #ffffff; color: #374151; border: 1px solid #dddddd; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; }
    #bamAccountSuggestions { background: #fff; }
    #bamAccountSuggestions .list-group-item { border-left: none; border-right: none; border-color: #eee; cursor: pointer; font-size: 0.9rem; }
    #bamAccountSuggestions .list-group-item:first-child { border-radius: 6px 6px 0 0; }
    #bamAccountSuggestions .list-group-item:hover { background: #E8F0FE; }
    #bamAccountSuggestions .bam-consumer-item strong { color: #333; }
    .bam-acct-dropdown { position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; background: #fff; border: 1px solid #dddddd; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-height: 260px; overflow-y: auto; margin-top: 2px; }
    .bam-acct-option { padding: 0.5rem 0.75rem; cursor: pointer; font-size: 0.9rem; border-bottom: 1px solid #eee; }
    .bam-acct-option:last-child { border-bottom: none; }
    .bam-acct-option:hover { background: #E8F0FE; }
    .bam-acct-option:first-child { color: #888; }
    .bam-success-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: none; align-items: center; justify-content: center; }
    .bam-success-modal.show { display: flex; }
    .bam-success-modal-content { background: #ffffff; border-radius: 12px; padding: 2rem; max-width: 400px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.15); text-align: center; }
    .bam-success-icon { width: 80px; height: 80px; margin: 0 auto 1.5rem; border-radius: 50%; border: 3px solid #4CAF50; display: flex; align-items: center; justify-content: center; background: #ffffff; }
    .bam-success-icon svg { width: 50px; height: 50px; color: #4CAF50; }
    .bam-success-icon.error { border-color: #f44336; }
    .bam-success-icon.error svg { color: #f44336; }
    .bam-success-title { font-size: 1.5rem; font-weight: 700; color: #333333; margin-bottom: 0.5rem; }
    .bam-success-message { font-size: 1rem; color: #666666; margin-bottom: 1.5rem; }
    .bam-success-btn { background: #2196F3; color: #fff; border: none; padding: 0.75rem 2rem; border-radius: 6px; font-weight: 600; cursor: pointer; }
    .bam-success-btn:hover { background: #1976D2; }
</style>

<body id="page-top">
    <div id="wrapper">
        @include('partials.sidebar')
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('partials.navbar')

                <div class="container-fluid py-4 edit-billing-shell">
                    <div class="bam-header d-flex align-items-center justify-content-between flex-wrap">
                        <h5 class="mb-0 font-weight-bold text-dark">Billing Adjustment</h5>
                        <div class="d-flex gap-2">
                            <button type="button" class="bam-btn-cancel" onclick="history.back()">Cancel</button>
                            <button type="button" class="bam-btn-save" id="bamSaveBtn">Save</button>
                        </div>
                    </div>

                    <div class="bam-tabs">
                        <span class="bam-tab active">Billing Adjustment Memo Entry</span>
                        <a href="{{ route('billing-payment') }}" class="bam-tab text-decoration-none">List</a>
                        <span class="bam-tab">Bank Payment Application</span>
                    </div>

                    <div class="bam-form-card">
                        <form id="editBillingForm">
                            @csrf
                            <div class="bam-row">
                                <div class="bam-col">
                                    <label class="form-label">Type</label>
                                    <select class="form-control form-control-sm" name="type" id="bamType">
                                        <option value="CM" selected>CM</option>
                                        <option value="DM">DM</option>
                                        <option value="<auto>">&lt;auto&gt;</option>
                                    </select>
                                </div>
                                <div class="bam-col">
                                    <label class="form-label">Type</label>
                                    <select class="form-control form-control-sm" name="ar" id="bamAr">
                                        <option value="AR">AR</option>
                                        <option value="LRO">LRO</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="bam-row">
                                        <div class="bam-col" style="flex: 1 1 100%;">
                                            <label class="form-label">Date</label>
                                            <input type="date" class="form-control form-control-sm" name="date" id="bamDate">
                                        </div>
                                        <div class="bam-col" style="flex: 1 1 100%; position: relative;">
                                            <label class="form-label">Account</label>
                                            <input type="text" class="form-control form-control-sm" name="account" id="bamAccount" placeholder="Search by account number or name..." autocomplete="off">
                                            <div id="bamAccountSuggestions" class="list-group" style="position: absolute; z-index: 1000; width: 100%; max-height: 260px; overflow-y: auto; display: none; margin-top: 2px; border: 1px solid #dddddd; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"></div>
                                            <small class="d-block mt-1 small text-muted">Type to search and select a consumer.</small>
                                        </div>
                                        <div class="bam-col" style="flex: 1 1 100%;">
                                            <label class="form-label">Name</label>
                                            <input type="text" class="form-control form-control-sm" name="account_name" id="bamAccountName" placeholder="Consumer name (from selection)" readonly>
                                        </div>
                                        <div class="bam-col" style="flex: 1 1 100%;">
                                            <label class="form-label">BAM No.</label>
                                            <input type="text" class="form-control form-control-sm" name="bam_no" id="bamNo" value="<auto>" readonly>
                                        </div>
                                        <div class="bam-col" style="flex: 1 1 100%;">
                                            <label class="form-label">Amount</label>
                                            <input type="text" class="form-control form-control-sm text-right" name="amount" id="bamAmount" value="0.00">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="bam-row">
                                        <div class="bam-col" style="flex: 1 1 100%; position: relative;">
                                            <label class="form-label">Acct Code</label>
                                            <input type="hidden" name="acct_code" id="bamAcctCode" value="">
                                            <input type="text" class="form-control form-control-sm" id="bamAcctCodeDisplay" readonly placeholder="— Select Acct Code —" style="cursor: pointer; background-color: #fff;">
                                            <div id="bamAcctCodeDropdown" class="bam-acct-dropdown" style="display: none;">
                                                <div class="bam-acct-option" data-code="" data-desc="">— Select Acct Code —</div>
                                                <div class="bam-acct-option" data-code="19901020" data-desc="Advances for Payroll">19901020 Advances for Payroll</div>
                                                <div class="bam-acct-option" data-code="19901030" data-desc="Advances to Special Disbursing Offices">19901030 Advances to Special Disbursing Offices</div>
                                                <div class="bam-acct-option" data-code="19901040" data-desc="Advances to Officers and Employees">19901040 Advances to Officers and Employees</div>
                                                <div class="bam-acct-option" data-code="20102040" data-desc="Loans Payable-Domestic - Non-Current">20102040 Loans Payable-Domestic - Non-Current</div>
                                                <div class="bam-acct-option" data-code="20401090" data-desc="Customer's Deposite Payable">20401090 Customer's Deposite Payable</div>
                                                <div class="bam-acct-option" data-code="40201990" data-desc="Other Service Income">40201990 Other Service Income</div>
                                                <div class="bam-acct-option" data-code="40603990" data-desc="Miscellineous Income">40603990 Miscellineous Income</div>
                                            </div>
                                        </div>
                                        <div class="bam-col" style="flex: 1 1 100%;">
                                            <label class="form-label">Reference</label>
                                            <input type="text" class="form-control form-control-sm" name="reference" id="bamReference" placeholder="">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bam-row mt-2">
                                <div class="bam-col" style="flex: 2 1 50%;">
                                    <label class="form-label">Remarks</label>
                                    <input type="text" class="form-control form-control-sm" name="remarks" id="bamRemarks" placeholder="">
                                </div>
                                <div class="bam-col" style="min-width: 140px;">
                                    <label class="form-label">Status</label>
                                    <select class="form-control form-control-sm" name="status" id="bamStatus">
                                        <option value="Pending" selected>Pending</option>
                                        <option value="Approved">Approved</option>
                                        <option value="Cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div class="bam-col" style="min-width: 120px;">
                                    <label class="form-label">Correct Reading</label>
                                    <input type="text" class="form-control form-control-sm text-right" name="correct_reading" id="bamCorrectReading" value="0">
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="bamSuccessModal" class="bam-success-modal">
        <div class="bam-success-modal-content">
            <div class="bam-success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <div class="bam-success-title" id="bamSuccessTitle">Billing Adjustment Saved!</div>
            <div class="bam-success-message" id="bamSuccessMessage">Billing adjustment information saved successfully.</div>
            <button type="button" class="bam-success-btn" onclick="document.getElementById('bamSuccessModal').classList.remove('show');">OK</button>
        </div>
    </div>

    @include('partials.footer')
    <script>
        document.getElementById('bamSaveBtn').addEventListener('click', function() {
            var accountInput = document.getElementById('bamAccount');
            var accountValue = accountInput ? (accountInput.value || '').trim() : '';
            
            if (!accountValue || accountValue === '') {
                var modal = document.getElementById('bamSuccessModal');
                var icon = modal.querySelector('.bam-success-icon');
                var iconSvg = icon.querySelector('svg');
                document.getElementById('bamSuccessTitle').textContent = 'Validation Error';
                document.getElementById('bamSuccessMessage').textContent = 'Please select an account before saving.';
                icon.classList.add('error');
                iconSvg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />';
                modal.classList.add('show');
                return;
            }
            
            var form = document.getElementById('editBillingForm');
            var token = form.querySelector('input[name="_token"]');
            var payload = {
                _token: token ? token.value : '{{ csrf_token() }}',
                type: (document.getElementById('bamType') || {}).value || 'CM',
                date: (document.getElementById('bamDate') || {}).value || null,
                account: accountValue,
                account_name: (document.getElementById('bamAccountName') || {}).value || null,
                bam_no: (document.getElementById('bamNo') || {}).value || null,
                amount: (document.getElementById('bamAmount') || {}).value || '0',
                ar: (document.getElementById('bamAr') || {}).value || 'AR',
                acct_code: (document.getElementById('bamAcctCode') || {}).value || null,
                reference: (document.getElementById('bamReference') || {}).value || null,
                remarks: (document.getElementById('bamRemarks') || {}).value || null,
                status: (document.getElementById('bamStatus') || {}).value || 'Pending',
                correct_reading: (document.getElementById('bamCorrectReading') || {}).value || '0'
            };
            var btn = document.getElementById('bamSaveBtn');
            btn.disabled = true;
            fetch("{{ route('edit-billing.save') }}", {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': payload._token
                },
                body: JSON.stringify(payload)
            })
            .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
            .then(function(result) {
                btn.disabled = false;
                var modal = document.getElementById('bamSuccessModal');
                var icon = modal.querySelector('.bam-success-icon');
                var iconSvg = icon.querySelector('svg');
                
                if (result.ok && result.data.success) {
                    icon.classList.remove('error');
                    iconSvg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />';
                    document.getElementById('bamSuccessTitle').textContent = 'Billing Adjustment Saved!';
                    document.getElementById('bamSuccessMessage').textContent = result.data.message || 'Billing adjustment information saved successfully.';
                    modal.classList.add('show');
                } else {
                    icon.classList.add('error');
                    iconSvg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />';
                    document.getElementById('bamSuccessTitle').textContent = 'Error';
                    document.getElementById('bamSuccessMessage').textContent = result.data.message || (result.data.errors ? Object.values(result.data.errors).flat().join(' ') : 'Save failed.');
                    modal.classList.add('show');
                }
            })
            .catch(function() {
                btn.disabled = false;
                var modal = document.getElementById('bamSuccessModal');
                var icon = modal.querySelector('.bam-success-icon');
                var iconSvg = icon.querySelector('svg');
                icon.classList.add('error');
                iconSvg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />';
                document.getElementById('bamSuccessTitle').textContent = 'Error';
                document.getElementById('bamSuccessMessage').textContent = 'Save failed. Please try again.';
                modal.classList.add('show');
            });
        });

        (function() {
            var accountInput = document.getElementById('bamAccount');
            var accountNameInput = document.getElementById('bamAccountName');
            var suggestionsEl = document.getElementById('bamAccountSuggestions');
            var debounceTimer = null;

            function hideSuggestions() {
                suggestionsEl.style.display = 'none';
                suggestionsEl.innerHTML = '';
            }

            function showSuggestions(items) {
                if (!items || items.length === 0) {
                    suggestionsEl.innerHTML = '<div class="list-group-item list-group-item-secondary small">No consumers found.</div>';
                } else {
                    suggestionsEl.innerHTML = items.map(function(c) {
                        return '<a href="#" class="list-group-item list-group-item-action bam-consumer-item" data-account="' + (c.account_no || '') + '" data-name="' + (c.account_name || '').replace(/"/g, '&quot;') + '" data-id="' + (c.id || '') + '">' +
                            '<strong>' + (c.account_no || '') + '</strong> — ' + (c.account_name || '') + '</a>';
                    }).join('');
                }
                suggestionsEl.style.display = 'block';
            }

            function doSearch(q) {
                if (!q || q.length < 2) return;
                var url = "{{ route('consumer.suggestions') }}?q=" + encodeURIComponent(q);
                fetch(url, { method: 'GET', credentials: 'include', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) {
                        if (!r.ok) return Promise.reject(new Error('Request failed: ' + r.status));
                        return r.json();
                    })
                    .then(function(res) {
                        if (res.success && res.data && res.data.length) showSuggestions(res.data);
                        else if (res.success && res.data && res.data.length === 0) showSuggestions([]);
                        else hideSuggestions();
                    })
                    .catch(function() {
                        showSuggestions([]);
                        suggestionsEl.innerHTML = '<div class="list-group-item list-group-item-danger small">Search unavailable. Ensure you are logged in and try again.</div>';
                        suggestionsEl.style.display = 'block';
                    });
            }

            accountInput.addEventListener('input', function() {
                var q = (this.value || '').trim();
                clearTimeout(debounceTimer);
                if (q.length < 2) {
                    hideSuggestions();
                    return;
                }
                debounceTimer = setTimeout(function() { doSearch(q); }, 250);
            });

            accountInput.addEventListener('blur', function() {
                setTimeout(hideSuggestions, 200);
            });

            accountInput.addEventListener('focus', function() {
                var q = (this.value || '').trim();
                if (q.length >= 2) doSearch(q);
            });

            suggestionsEl.addEventListener('click', function(e) {
                var item = e.target.closest('.bam-consumer-item');
                if (item) {
                    e.preventDefault();
                    var accountVal = item.getAttribute('data-account') || '';
                    accountInput.value = accountVal;
                    if (accountNameInput) accountNameInput.value = item.getAttribute('data-name') || '';
                    hideSuggestions();
                }
            });

            (function() {
                var acctDisplay = document.getElementById('bamAcctCodeDisplay');
                var acctHidden = document.getElementById('bamAcctCode');
                var acctDropdown = document.getElementById('bamAcctCodeDropdown');
                var referenceInput = document.getElementById('bamReference');
                if (!acctDisplay || !acctHidden || !acctDropdown || !referenceInput) return;
                acctDisplay.addEventListener('click', function() {
                    acctDropdown.style.display = acctDropdown.style.display === 'none' ? 'block' : 'none';
                });
                acctDropdown.querySelectorAll('.bam-acct-option').forEach(function(opt) {
                    opt.addEventListener('click', function() {
                        var code = this.getAttribute('data-code') || '';
                        var desc = this.getAttribute('data-desc') || '';
                        acctHidden.value = code;
                        acctDisplay.value = code;
                        acctDisplay.placeholder = code ? '' : '— Select Acct Code —';
                        referenceInput.value = desc;
                        acctDropdown.style.display = 'none';
                    });
                });
                document.addEventListener('click', function(e) {
                    if (!acctDisplay.contains(e.target) && !acctDropdown.contains(e.target)) {
                        acctDropdown.style.display = 'none';
                    }
                });
            })();
        })();
    </script>
</body>
</html>
