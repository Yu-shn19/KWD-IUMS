<!DOCTYPE html>
<html lang="en">
@include('partials.header')

<style>
    .edit-billing-shell { background: #ffffff; min-height: calc(100vh - 4rem); }
    .bam-header { background: #ffffff; border-bottom: 1px solid #e0e0e0; padding: 1rem 1.5rem; }
    .bam-tabs { background: #ffffff; border-bottom: 1px solid #e0e0e0; padding: 0 1rem; }
    .bam-tab { padding: 0.75rem 1rem; color: #666666; font-weight: 500; border-bottom: 3px solid transparent; display: inline-block; }
    .bam-tab.active { color: #333333; font-weight: 700; border-bottom-color: #2196F3; }
    /* Main entry card – solid dark green background like legacy screen */
    .bam-form-card {
        background: #0d5c5c;
        border: 1px solid #064e3b;
        border-radius: 8px;
        padding: 1.5rem;
        margin: 1.5rem;
        box-shadow: 0 0 0 1px rgba(6,95,70,0.4);
    }
    .bam-form-card .form-label {
        color: #f9fafb;
        font-size: 0.875rem;
        margin-bottom: 0.35rem;
        font-weight: 500;
    }
    .bam-form-card .form-control {
        background: #ffffff;
        border: 1px solid #0f766e;
        border-radius: 6px;
        color: #022c22;
    }
    .bam-form-card .form-control:focus { border-color: #065f46; box-shadow: 0 0 0 0.2rem rgba(6, 95, 70, 0.25); }
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
    /* List card match form card styling (dark green shell) */
    #bamListPanel {
        border: 1px solid #064e3b;
        border-radius: 8px;
        box-shadow: 0 0 0 1px rgba(6,95,70,0.4);
        background: #0d5c5c;
    }
    #bamListPanel .card-header {
        background: transparent;
        border-bottom-color: rgba(15,118,110,0.5);
        color: #f9fafb;
    }
    #bamListPanel .card-header .badge-primary {
        background-color: #059669;
    }
    /* Make table area all white inside the dark-green shell */
    #bamListPanel .card-body {
        background-color: #ffffff;
        border-radius: 0 0 8px 8px;
    }
    #bamListPanel .table {
        background-color: #ffffff;
        color: inherit;
    }
    #bamListPanel .table tbody tr {
        background-color: #ffffff;
    }
    #bamListPanel .table tbody tr:nth-child(even) {
        background-color: #f8f9fa;
    }
    .bam-list-header {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        column-gap: 0.5rem;
        row-gap: 0.5rem;
    }
    .bam-list-title-wrap { justify-self: start; }
    .bam-list-search-wrap { justify-self: center; width: 150%; max-width: 820px; }
    .bam-list-records-wrap { justify-self: end; }
    .bam-list-search-group {
        display: flex;
        align-items: center;
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #e5e7eb;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .bam-list-search-group:focus-within {
        border-color: #94a3b8;
        box-shadow: 0 0 0 0.2rem rgba(148, 163, 184, 0.2);
    }
    .bam-list-search-icon {
        color: #94a3b8;
        font-size: 1rem;
        padding: 0 0.9rem;
        display: inline-flex;
        align-items: center;
    }
    .bam-list-search-icon-right {
        margin-left: auto;
    }
    .bam-list-search {
        width: 100%;
        border: none;
        box-shadow: none !important;
        background: transparent;
        color: #6b7280;
        font-size: 1.05rem;
        padding: 0.55rem 0.35rem 0.55rem 0.95rem;
    }
    .bam-list-search:focus {
        outline: none;
    }
    .bam-list-actions { display: inline-flex; flex-direction: row; align-items: center; flex-wrap: nowrap; gap: 0.25rem; white-space: nowrap; }
    @media (max-width: 768px) {
        .bam-list-header {
            grid-template-columns: 1fr;
        }
        .bam-list-title-wrap,
        .bam-list-search-wrap,
        .bam-list-records-wrap {
            justify-self: stretch;
        }
        .bam-list-records-wrap {
            text-align: right;
        }
    }
</style>

<body id="page-top">
    <div id="wrapper">
        @include('partials.sidebar')
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('partials.navbar')

                <div class="container-fluid py-4 edit-billing-shell">
                    @if(session('error'))
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    @endif
                    <div class="bam-header d-flex align-items-center justify-content-between flex-wrap">
                        <h5 class="mb-0 font-weight-bold text-dark">Billing Adjustment</h5>
                        <div class="d-flex gap-2">
                            <button type="button" class="bam-btn-cancel" onclick="history.back()">Cancel</button>
                            <button type="button" class="bam-btn-save" id="bamSaveBtn">Save</button>
                        </div>
                    </div>

                    <div class="bam-tabs">
                        <span class="bam-tab active" id="bamTabEntry">Billing Adjustment Memo Entry</span>
                        <span class="bam-tab text-decoration-none" id="bamTabList">List</span>
                        <span class="bam-tab">Bank Payment Application</span>
                    </div>

                    <div class="bam-form-card" id="bamEntryPanel">
                        <form id="editBillingForm">
                            @csrf
                            <input type="hidden" id="bamLroId" value="{{ isset($lroEntry) ? $lroEntry->id : '' }}">
                            <input type="hidden" id="bamArId" value="{{ isset($billingAdjustment) ? $billingAdjustment->id : '' }}">
                            <div class="bam-row">
                                <div class="bam-col">
                                    <label class="form-label">Type</label>
                                    <select class="form-control form-control-sm" name="type" id="bamType">
                                        <option value="DM" selected>DM</option>
                                        <option value="CM">CM</option>
                                        <option value="Others">Others</option>
                                    </select>
                                </div>
                                <div class="bam-col">
                                    <label class="form-label">Ledger</label>
                                    <select class="form-control form-control-sm" name="ar" id="bamAr">
                                        <option value="AR">AR (Account ledger)</option>
                                        <option value="LRO">LRO (LRO ledger)</option>
                                    </select>
                                    <small class="d-block mt-1 small text-muted">AR = consumer ledger (Acct Code disabled). LRO = LRO ledger (Acct Code enabled, Type = DM only).</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="bam-row">
                                        <div class="bam-col" style="flex: 1 1 100%;">
                                            <label class="form-label">Date</label>
                                            <input type="date" class="form-control form-control-sm" name="date" id="bamDate" value="{{ date('Y-m-d') }}">
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
                                            <input type="text" class="form-control form-control-sm" name="bam_no" id="bamNo" value="{{ $previewBamNo ?? '<auto>' }}" readonly>
                                        </div>
                                        <div class="bam-col" style="flex: 1 1 100%;">
                                            <label class="form-label">Amount</label>
                                            <input type="text" class="form-control form-control-sm text-left" name="amount" id="bamAmount" value="0.00" onfocus="this.select()">
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
                                                <div class="bam-acct-option" data-code="106" data-desc="A/R MATERIALS">106 - A/R MATERIALS</div>
                                                <div class="bam-acct-option" data-code="107" data-desc="A/R MOTORPLAN">107 - A/R MOTORPLAN</div>
                                                <div class="bam-acct-option" data-code="110" data-desc="CELLPHONE PLAN">110 - CELLPHONE PLAN</div>
                                                <div class="bam-acct-option" data-code="101" data-desc="CONNECTION FEE">101 - CONNECTION FEE</div>
                                                <div class="bam-acct-option" data-code="105" data-desc="MATERIALS NEW CONNECTION">105 - MATERIALS NEW CONNECTION</div>
                                                <div class="bam-acct-option" data-code="100" data-desc="MEMBERSHIP FEE">100 - MEMBERSHIP FEE</div>
                                                <div class="bam-acct-option" data-code="109" data-desc="OTHERS">109 - OTHERS</div>
                                                <div class="bam-acct-option" data-code="108" data-desc="RECONNECTION FEE">108 - RECONNECTION FEE</div>
                                                <div class="bam-acct-option" data-code="102" data-desc="SECURITY DEPOSIT">102 - SECURITY DEPOSIT</div>
                                                <div class="bam-acct-option" data-code="104" data-desc="SERVICE FEE">104 - SERVICE FEE</div>
                                                <div class="bam-acct-option" data-code="103" data-desc="WATER METER">103 - WATER METER</div>
                                            </div>
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
                                        <option value="Approved" Selected>Approved</option>
                                        <option value="Pending" >Pending</option>
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

                    {{-- List of all billing adjustment transactions (old feature, data only) --}}
                    @if(isset($billingAdjustments))
                    <div class="card shadow-sm border-0 mt-3 d-none" id="bamListPanel">
                        <div class="card-header py-3 bam-list-header">
                            <div class="bam-list-title-wrap">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-list mr-2"></i>Billing Adjustment Memo Entry
                                </h6>
                            </div>
                            <div class="bam-list-search-wrap">
                                <div class="bam-list-search-group">
                                    <input type="text" id="bamListSearch" class="form-control form-control-sm bam-list-search" placeholder="Search Account No, Account Name, or Reference No.">
                                    <span class="bam-list-search-icon bam-list-search-icon-right"><i class="fas fa-search"></i></span>
                                </div>
                            </div>
                            <div class="bam-list-records-wrap">
                                <span class="badge badge-primary badge-pill">{{ $billingAdjustments->count() + (isset($lroEntries) ? $lroEntries->count() : 0) }} record(s)</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0" id="bamListTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Trans</th>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Account No</th>
                                            <th>Account Name</th>
                                            <th class="text-right">Amount</th>
                                            <th>Reference</th>
                                            <th>Status</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $allEntries = collect();
                                            foreach($billingAdjustments as $ba) {
                                                $allEntries->push([
                                                    'source'   => 'BAM',
                                                    'ledger'   => strtoupper($ba->ledger ?? 'AR'),
                                                    'data'     => $ba,
                                                    'sort_key' => ($ba->date ?? '0000-00-00') . str_pad($ba->id, 10, '0', STR_PAD_LEFT),
                                                ]);
                                            }
                                            if (isset($lroEntries)) {
                                                foreach($lroEntries as $entry) {
                                                    $allEntries->push([
                                                        'source'   => 'LRO',
                                                        'ledger'   => 'LRO',
                                                        'data'     => $entry,
                                                        'sort_key' => ($entry->date ?? '0000-00-00') . str_pad($entry->id, 10, '0', STR_PAD_LEFT),
                                                    ]);
                                                }
                                            }
                                            $allEntries = $allEntries->sortByDesc(fn (array $row) => $row['sort_key'] ?? '')->values();
                                            $paidLroBamNos = collect(isset($lroEntries) ? $lroEntries : [])
                                                ->filter(function ($entry) {
                                                    $status = strtoupper(trim((string) ($entry->status ?? '')));
                                                    $type = strtoupper(trim((string) ($entry->type ?? '')));

                                                    return $type === 'CM' && in_array($status, ['POSTED', 'PAID'], true);
                                                })
                                                ->map(fn ($entry) => trim((string) ($entry->bam_no ?? '')))
                                                ->filter()
                                                ->unique()
                                                ->values()
                                                ->all();
                                        @endphp

                                        @forelse($allEntries as $row)
                                            @php
                                                $item = $row['data'];
                                                $isOthers = ($item->type ?? '') === 'Others';
                                                $typeBadgeClass = $item->type === 'CM'
                                                    ? 'badge-success'
                                                    : ($item->type === 'DM' ? 'badge-warning' : 'badge-info');
                                                $acctCodeLabels = [
                                                    '100' => '100 - MEMBERSHIP FEE',
                                                    '101' => '101 - CONNECTION FEE',
                                                    '102' => '102 - SECURITY DEPOSIT',
                                                    '103' => '103 - WATER METER',
                                                    '104' => '104 - SERVICE FEE',
                                                    '105' => '105 - MATERIALS NEW CONNECTION',
                                                    '106' => '106 - A/R MATERIALS',
                                                    '107' => '107 - A/R MOTORPLAN',
                                                    '108' => '108 - RECONNECTION FEE',
                                                    '109' => '109 - OTHERS',
                                                    '110' => '110 - CELLPHONE PLAN',
                                                ];
                                                $acctCodeKey = (string) ($item->acct_code ?? '');
                                                $acctCodeLabel = $acctCodeLabels[$acctCodeKey]
                                                    ?? (\App\Support\AcctCodeTitles::titleFor($acctCodeKey)
                                                    ?? ($acctCodeKey !== '' ? $acctCodeKey : '-'));
                                            @endphp
                                            @if($row['source'] === 'BAM')
                                            <tr data-acct-code="{{ $acctCodeKey }}">
                                                <td>{{ $acctCodeLabel }}</td>
                                                <td>{{ $item->date ? \Carbon\Carbon::parse($item->date)->format('m/d/Y') : '-' }}</td>
                                                <td><span class="badge {{ $typeBadgeClass }}">{{ $item->type }}</span></td>
                                                <td>{{ $isOthers ? '' : ($item->consumerZone->account_no ?? $item->account_no ?? '-') }}</td>
                                                <td>{{ $isOthers ? '' : ($item->consumerZone->account_name ?? '-') }}</td>
                                                <td class="text-right font-weight-bold">{{ number_format($item->amount, 2) }}</td>
                                                <td>{{ $item->bam_no ?? '-' }}</td>
                                                <td><span class="badge badge-{{ $item->status === 'Approved' ? 'success' : ($item->status === 'Posted' ? 'primary' : ($item->status === 'Cancelled' ? 'danger' : 'secondary')) }}">{{ $item->status === 'Posted' ? 'Paid' : $item->status }}</span></td>
                                                <td class="text-center" style="white-space: nowrap;">
                                                    @php
                                                        $bamStatus = strtoupper(trim($item->status ?? ''));
                                                        $isArCmPaid = ($row['ledger'] ?? 'AR') === 'AR'
                                                            && ($item->type ?? '') === 'CM'
                                                            && in_array($bamStatus, ['POSTED', 'PAID'], true);
                                                        $isBamPaid = in_array($bamStatus, ['POSTED', 'PAID'], true);
                                                    @endphp
                                                    <div class="bam-list-actions">
                                                    @if($isArCmPaid)
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            disabled
                                                            title="Paid entries cannot be edited">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    @else
                                                    <a href="{{ route('billing-adjustment.edit', $item->id) }}" class="btn btn-sm btn-outline-primary bam-edit-btn" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    @endif
                                                    @if($isBamPaid)
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            disabled
                                                            title="Paid entries cannot be deleted">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    @else
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-danger bam-delete-btn"
                                                            data-url="{{ route('billing-adjustment.ar.destroy', $item->id) }}"
                                                            title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    @endif
                                                    </div>
                                                </td>
                                            </tr>
                                            @else
                                            <tr data-acct-code="{{ $acctCodeKey }}">
                                                <td>{{ $acctCodeLabel }}</td>
                                                <td>{{ $item->date ? \Carbon\Carbon::parse($item->date)->format('m/d/Y') : '-' }}</td>
                                                <td><span class="badge {{ $typeBadgeClass }}">{{ $item->type ?? 'CM' }}</span></td>
                                                <td>{{ $isOthers ? '' : ($item->consumerZone->account_no ?? $item->account_no ?? '') }}</td>
                                                <td>{{ $isOthers ? ($item->account_name ?? '-') : ($item->consumerZone->account_name ?? $item->account_name ?? '-') }}</td>
                                                <td class="text-right font-weight-bold">{{ number_format($item->amount ?? 0, 2) }}</td>
                                                <td>{{ $item->bam_no ?? '-' }}</td>
                                                <td><span class="badge badge-{{ ($item->status ?? 'Pending') === 'Approved' ? 'success' : (($item->status ?? 'Pending') === 'Posted' ? 'primary' : (($item->status ?? 'Pending') === 'Cancelled' ? 'danger' : 'secondary')) }}">{{ ($item->status ?? 'Pending') === 'Posted' ? 'Paid' : ($item->status ?? 'Pending') }}</span></td>
                                                <td class="text-center" style="white-space: nowrap;">
                                                    @php
                                                        $lroStatus = strtoupper(trim($item->status ?? ''));
                                                        $isLroPaid = in_array($lroStatus, ['POSTED', 'PAID'], true);
                                                        $isLroChargePaid = strtoupper(trim((string) ($item->type ?? ''))) === 'DM'
                                                            && in_array(trim((string) ($item->bam_no ?? '')), $paidLroBamNos, true);
                                                        $cannotDeleteLro = $isLroPaid || $isLroChargePaid;
                                                    @endphp
                                                    <div class="bam-list-actions">
                                                    @if($isLroPaid)
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            disabled
                                                            title="Paid entries cannot be edited">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    @else
                                                    <a href="{{ route('billing-adjustment.lro.edit', $item->id) }}" class="btn btn-sm btn-outline-primary bam-edit-btn" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    @endif
                                                    @if($cannotDeleteLro)
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-secondary"
                                                            disabled
                                                            title="Paid entries cannot be deleted">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    @else
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-danger bam-delete-btn"
                                                            data-url="{{ route('billing-adjustment.lro.destroy', $item->id) }}"
                                                            title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    @endif
                                                    </div>
                                                </td>
                                            </tr>
                                            @endif
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-4">No billing adjustment transactions recorded yet.</td>
                                            </tr>
                                        @endforelse
                                        <tr id="bamListNoMatchRow" class="d-none">
                                            <td colspan="9" class="text-center text-muted py-4">No matching records found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif
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

    <!-- PIN Modal for Edit / Delete -->
    <div class="modal fade" id="bamActionPinModal" tabindex="-1" aria-labelledby="bamActionPinModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="bamActionPinModalLabel">
                        <i class="fas fa-lock me-2"></i>Enter PIN
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2" id="bamActionPinHint">Enter the edit PIN to continue.</p>
                    <input type="password" class="form-control" id="bamActionPinInput" placeholder="PIN" autocomplete="off" maxlength="20">
                    <div class="invalid-feedback" id="bamActionPinError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="bamActionPinVerifyBtn"><i class="fas fa-check me-1"></i>Verify</button>
                </div>
            </div>
        </div>
    </div>

    @include('partials.footer')

    @isset($lroEntry)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var entry = @json($lroEntry);
            var consumer = entry.consumer_zone || entry.consumerZone || null;
            bamPopulateForm({
                type:            entry.type,
                date:            entry.date ? String(entry.date).substring(0, 10) : '',
                account:         entry.account_no || entry.account || (consumer ? consumer.account_no : ''),
                name:            entry.account_name || entry.name || (consumer ? consumer.account_name : ''),
                bam_no:          entry.bam_no,
                amount:          entry.amount,
                acct_code:       entry.acct_code,
                remarks:         entry.remarks,
                status:          entry.status,
                correct_reading: entry.correct_reading
            }, 'LRO');
        });
    </script>
    @endisset

    @isset($billingAdjustment)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var entry = @json($billingAdjustment);
            var consumer = entry.consumer_zone || entry.consumerZone || null;
            bamPopulateForm({
                type:            entry.type,
                date:            @json(optional($billingAdjustment->date)->format('Y-m-d')),
                account:         entry.account_no || (consumer ? consumer.account_no : ''),
                name:            consumer ? (consumer.account_name || '') : (entry.account_name || ''),
                bam_no:          entry.bam_no,
                amount:          entry.amount,
                acct_code:       entry.acct_code,
                remarks:         entry.remarks,
                status:          entry.status,
                correct_reading: entry.connect_reading
            }, entry.ledger || 'AR');
        });
    </script>
    @endisset

    <script>
        // ─── Helpers ────────────────────────────────────────────────────────────
        function bamSetVal(id, val) {
            var el = document.getElementById(id);
            if (el) el.value = (val !== null && val !== undefined) ? val : '';
        }
        function bamFormatAmountValue(raw) {
            var cleaned = String(raw === null || raw === undefined ? '' : raw).replace(/,/g, '').trim();
            if (cleaned === '') return '0.00';
            var n = parseFloat(cleaned);
            if (!isFinite(n) || isNaN(n)) return '0.00';
            return n.toFixed(2);
        }
        function bamNormalizeAmountForEdit(raw) {
            var cleaned = String(raw === null || raw === undefined ? '' : raw).replace(/,/g, '').trim();
            if (cleaned === '') return '0';
            var n = parseFloat(cleaned);
            if (!isFinite(n) || isNaN(n)) return '0';
            // Show plain number while typing (e.g. 0 instead of 0.00)
            return Number.isInteger(n) ? String(parseInt(n, 10)) : String(n);
        }
        function bamSetSelected(id, val) {
            var el = document.getElementById(id);
            if (!el) return;
            for (var i = 0; i < el.options.length; i++) {
                if (el.options[i].value === val) { el.selectedIndex = i; return; }
            }
        }
        function bamAcctCodeDisplayText(code) {
            var c = String(code || '').trim();
            if (!c) return '';
            var opt = document.querySelector('#bamAcctCodeDropdown .bam-acct-option[data-code="' + c.replace(/"/g, '') + '"]');
            if (opt) {
                var label = String(opt.textContent || '').trim();
                if (label) return label;
            }
            return c;
        }
        function bamLocalTodayYmd() {
            var d = new Date();
            var y = d.getFullYear();
            var m = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + day;
        }
        
        // Apply Type-specific UI rules (e.g. Others)
        var bamPrevLedger = null;
        function bamApplyTypeRules() {
            var typeEl    = document.getElementById('bamType');
            var ledgerEl  = document.getElementById('bamAr');
            var accountEl = document.getElementById('bamAccount');
            var nameEl    = document.getElementById('bamAccountName');
            var bamNoEl   = document.getElementById('bamNo');
            if (!typeEl || !ledgerEl || !accountEl || !nameEl || !bamNoEl) return;

            var isOthers = (typeEl.value === 'Others');

            if (isOthers) {
                if (bamPrevLedger === null) bamPrevLedger = ledgerEl.value || 'AR';
                ledgerEl.value = 'LRO';
                ledgerEl.disabled = true;

                accountEl.value = '';
                accountEl.disabled = true;

                // Allow manual name input for Others
                nameEl.readOnly = false;
                nameEl.placeholder = 'Enter name...';
                bamNoEl.readOnly = false;
            } else {
                ledgerEl.disabled = false;
                if (bamPrevLedger !== null) ledgerEl.value = bamPrevLedger;
                bamPrevLedger = null;

                accountEl.disabled = false;

                nameEl.readOnly = true;
                nameEl.placeholder = 'Consumer name (from selection)';
                bamNoEl.readOnly = true;
                if ((nameEl.value || '').trim() && !accountEl.value) nameEl.value = '';
            }

            bamApplySundryRules();
            bamApplyLedgerFieldRules();
            bamApplyStatusOptions();
        }

        function bamSetAcctCodeEnabled(enabled) {
            var acctDisplay = document.getElementById('bamAcctCodeDisplay');
            var acctDropdown = document.getElementById('bamAcctCodeDropdown');
            if (!acctDisplay) return;
            acctDisplay.disabled = !enabled;
            acctDisplay.style.cursor = enabled ? 'pointer' : 'not-allowed';
            acctDisplay.style.backgroundColor = enabled ? '#fff' : '#e9ecef';
            if (!enabled && acctDropdown) {
                acctDropdown.style.display = 'none';
            }
        }

        function bamApplyLedgerFieldRules() {
            var typeEl = document.getElementById('bamType');
            var ledgerEl = document.getElementById('bamAr');
            if (!typeEl || !ledgerEl) return;

            var isOthers = typeEl.value === 'Others';
            var isLro = ledgerEl.value === 'LRO';

            bamSetAcctCodeEnabled(isLro);

            Array.prototype.forEach.call(typeEl.options, function(opt) {
                if (isLro && !isOthers) {
                    var lock = opt.value !== 'DM';
                    opt.hidden = lock;
                    opt.disabled = lock;
                } else {
                    opt.hidden = false;
                    opt.disabled = false;
                }
            });

            if (isLro && !isOthers && typeEl.value !== 'DM') {
                bamSetSelected('bamType', 'DM');
            }
        }

        function bamApplySundryRules() {
            var acctHidden = document.getElementById('bamAcctCode');
            var ledgerEl = document.getElementById('bamAr');
            var typeEl = document.getElementById('bamType');
            if (!acctHidden || !ledgerEl || !typeEl) return;

            var code = (acctHidden.value || '').trim();
            var sundryCodes = ['19901020', '19901030', '19901040'];
            var isSundry = sundryCodes.indexOf(code) !== -1;
            var isOthers = typeEl.value === 'Others';

            if (isSundry && !isOthers) {
                bamSetSelected('bamAr', 'LRO');
                bamSetSelected('bamType', 'DM');
                ledgerEl.disabled = true;
            } else if (!isOthers) {
                ledgerEl.disabled = false;
            }
        }

        function bamApplyStatusOptions() {
            var typeEl = document.getElementById('bamType');
            var ledgerEl = document.getElementById('bamAr');
            var statusEl = document.getElementById('bamStatus');
            if (!typeEl || !ledgerEl || !statusEl) return;

            var paidOption = statusEl.querySelector('option[value="Posted"]');
            if (!paidOption) return;

            var showPaid = ledgerEl.value === 'LRO'
                || (ledgerEl.value === 'AR' && typeEl.value === 'CM');
            paidOption.hidden = !showPaid;
            paidOption.disabled = !showPaid;

            if (!showPaid && statusEl.value === 'Posted') {
                statusEl.value = 'Approved';
            }
        }

        // Populate all form fields from a data object
        function bamPopulateForm(entry, ledger) {
            bamSetSelected('bamType', entry.type || 'CM');
            bamSetSelected('bamAr', ledger || 'AR');
            bamSetVal('bamDate', entry.date ? String(entry.date).substring(0, 10) : '');
            bamSetVal('bamAccount', entry.account || '');
            bamSetVal('bamAccountName', entry.name || '');
            bamSetVal('bamNo', entry.bam_no || '');
            bamSetVal('bamAmount', entry.amount !== null && entry.amount !== undefined ? parseFloat(entry.amount).toFixed(2) : '0.00');
            bamSetVal('bamAcctCode', entry.acct_code || '');
            bamSetVal('bamAcctCodeDisplay', bamAcctCodeDisplayText(entry.acct_code));
            bamSetVal('bamRemarks', entry.remarks || '');
            bamSetSelected('bamStatus', entry.status || 'Approved');
            bamSetVal('bamCorrectReading', entry.correct_reading !== null && entry.correct_reading !== undefined ? entry.correct_reading : '0');
            bamApplyTypeRules();
            bamApplySundryRules();
            bamApplyLedgerFieldRules();
            bamApplyStatusOptions();
        }

        // Reset the form to a blank new-entry state; pass nextBamNo from server response
        function bamResetForm(nextBamNo) {
            bamSetSelected('bamType', 'CM');
            bamSetSelected('bamAr', 'AR');
            bamSetVal('bamDate', bamLocalTodayYmd());
            bamSetVal('bamAccount', '');
            bamSetVal('bamAccountName', '');
            bamSetVal('bamNo', nextBamNo || '');
            bamSetVal('bamAmount', '0.00');
            bamSetVal('bamAcctCode', '');
            bamSetVal('bamAcctCodeDisplay', '');
            document.getElementById('bamAcctCodeDisplay').placeholder = '— Select Acct Code —';
            bamSetVal('bamRemarks', '');
            bamSetSelected('bamStatus', 'Approved');
            bamSetVal('bamCorrectReading', '0');
            // Clear edit-mode IDs so next save is treated as new
            bamSetVal('bamLroId', '');
            bamSetVal('bamArId', '');
            bamApplyTypeRules();
        }

        // ─── Tab behavior ────────────────────────────────────────────────────────
        (function() {
            var tabEntry = document.getElementById('bamTabEntry');
            var tabList  = document.getElementById('bamTabList');
            var entryPanel = document.getElementById('bamEntryPanel');
            var listPanel  = document.getElementById('bamListPanel');

            function showEntry() {
                if (entryPanel) entryPanel.classList.remove('d-none');
                if (listPanel)  listPanel.classList.add('d-none');
                if (tabEntry) tabEntry.classList.add('active');
                if (tabList)  tabList.classList.remove('active');
            }
            function showList() {
                if (entryPanel) entryPanel.classList.add('d-none');
                if (listPanel)  listPanel.classList.remove('d-none');
                if (tabList)  tabList.classList.add('active');
                if (tabEntry) tabEntry.classList.remove('active');
            }
            if (tabEntry) tabEntry.addEventListener('click', function(e) { e.preventDefault(); showEntry(); });
            if (tabList)  tabList.addEventListener('click',  function(e) { e.preventDefault(); showList(); });
        })();

        // ─── List search (Account No, Account Name, Reference) ─────────────────
        (function() {
            var searchInput = document.getElementById('bamListSearch');
            var tableBody = document.querySelector('#bamListTable tbody');
            var noMatchRow = document.getElementById('bamListNoMatchRow');
            if (!searchInput || !tableBody || !noMatchRow) return;

            function normalize(value) {
                return String(value || '').toLowerCase().trim();
            }

            function filterRows() {
                var query = normalize(searchInput.value);
                var rows = tableBody.querySelectorAll('tr');
                var visibleCount = 0;

                rows.forEach(function(row) {
                    if (row.id === 'bamListNoMatchRow') return;
                    var cells = row.querySelectorAll('td');
                    if (cells.length < 9) return;

                    var acctCode = normalize(cells[0].textContent);
                    var acctCodeRaw = normalize(row.getAttribute('data-acct-code'));
                    var accountNo = normalize(cells[3].textContent);
                    var accountName = normalize(cells[4].textContent);
                    var reference = normalize(cells[6].textContent);
                    var matches = !query
                        || accountNo.indexOf(query) !== -1
                        || accountName.indexOf(query) !== -1
                        || reference.indexOf(query) !== -1
                        || acctCode.indexOf(query) !== -1
                        || acctCodeRaw.indexOf(query) !== -1;

                    row.style.display = matches ? '' : 'none';
                    if (matches) visibleCount++;
                });

                noMatchRow.classList.toggle('d-none', visibleCount > 0 || !query);
            }

            searchInput.addEventListener('input', filterRows);
        })();

        // ─── List edit / delete (PIN required) ───────────────────────────────────
        (function() {
            var table = document.getElementById('bamListTable');
            if (!table) return;
            var tokenEl = document.querySelector('#editBillingForm input[name="_token"]');
            var token = tokenEl ? tokenEl.value : '{{ csrf_token() }}';
            var pendingAction = null;

            function requestPinThenRun(hint, callback) {
                pendingAction = callback;
                var hintEl = document.getElementById('bamActionPinHint');
                if (hintEl) hintEl.textContent = hint || 'Enter the edit PIN to continue.';
                $('#bamActionPinInput').val('');
                $('#bamActionPinError').text('').removeClass('d-block');
                $('#bamActionPinInput').removeClass('is-invalid');
                $('#bamActionPinModal').modal('show');
                setTimeout(function() {
                    var pinInput = document.getElementById('bamActionPinInput');
                    if (pinInput) pinInput.focus();
                }, 300);
            }

            var verifyBtn = document.getElementById('bamActionPinVerifyBtn');
            if (verifyBtn) {
                verifyBtn.addEventListener('click', function() {
                    var pinInput = document.getElementById('bamActionPinInput');
                    var errEl = document.getElementById('bamActionPinError');
                    var pin = (pinInput && pinInput.value) ? pinInput.value.trim() : '';
                    if (!pin) {
                        if (errEl) { errEl.textContent = 'Enter PIN.'; errEl.classList.add('d-block'); }
                        if (pinInput) pinInput.classList.add('is-invalid');
                        return;
                    }
                    verifyBtn.disabled = true;
                    fetch('{{ route("consumer.verify-edit-pin") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token
                        },
                        body: JSON.stringify({ pin: pin })
                    }).then(function(r) { return r.json(); }).then(function(data) {
                        verifyBtn.disabled = false;
                        if (data.success) {
                            $('#bamActionPinModal').modal('hide');
                            var cb = pendingAction;
                            pendingAction = null;
                            if (typeof cb === 'function') cb();
                        } else {
                            if (errEl) { errEl.textContent = data.message || 'Invalid PIN.'; errEl.classList.add('d-block'); }
                            if (pinInput) pinInput.classList.add('is-invalid');
                        }
                    }).catch(function() {
                        verifyBtn.disabled = false;
                        if (errEl) { errEl.textContent = 'Request failed.'; errEl.classList.add('d-block'); }
                        if (pinInput) pinInput.classList.add('is-invalid');
                    });
                });
            }
            $('#bamActionPinInput').on('keydown', function(e) {
                if (e.which === 13 && verifyBtn) verifyBtn.click();
            });

            function deleteAdjustment(btn) {
                var url = btn.getAttribute('data-url');
                if (!url) return;
                btn.disabled = true;

                fetch(url, {
                    method: 'DELETE',
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token
                    }
                })
                .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
                .then(function(result) {
                    var modal = document.getElementById('bamSuccessModal');
                    var icon = modal.querySelector('.bam-success-icon');
                    var iconSvg = icon.querySelector('svg');
                    if (result.ok && result.data.success) {
                        var row = btn.closest('tr');
                        if (row) row.remove();
                        var badge = document.querySelector('#bamListPanel .badge-pill');
                        if (badge) {
                            var n = parseInt(badge.textContent, 10);
                            if (isFinite(n) && n > 0) {
                                badge.textContent = (n - 1) + ' record(s)';
                            }
                        }
                        icon.classList.remove('error');
                        iconSvg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />';
                        document.getElementById('bamSuccessTitle').textContent = 'Deleted';
                        document.getElementById('bamSuccessMessage').textContent = result.data.message || 'Billing adjustment deleted successfully.';
                    } else {
                        btn.disabled = false;
                        icon.classList.add('error');
                        iconSvg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />';
                        document.getElementById('bamSuccessTitle').textContent = 'Error';
                        document.getElementById('bamSuccessMessage').textContent = result.data.message || 'Delete failed.';
                    }
                    modal.classList.add('show');
                })
                .catch(function() {
                    btn.disabled = false;
                    var modal = document.getElementById('bamSuccessModal');
                    var icon = modal.querySelector('.bam-success-icon');
                    icon.classList.add('error');
                    icon.querySelector('svg').innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />';
                    document.getElementById('bamSuccessTitle').textContent = 'Error';
                    document.getElementById('bamSuccessMessage').textContent = 'Delete failed. Please try again.';
                    modal.classList.add('show');
                });
            }

            table.addEventListener('click', function(e) {
                var editBtn = e.target.closest('.bam-edit-btn');
                if (editBtn) {
                    e.preventDefault();
                    var href = editBtn.getAttribute('href');
                    if (!href) return;
                    requestPinThenRun('Enter the edit PIN to edit this billing adjustment.', function() {
                        window.location.href = href;
                    });
                    return;
                }

                var btn = e.target.closest('.bam-delete-btn');
                if (!btn) return;
                e.preventDefault();
                if (btn.disabled) return;
                requestPinThenRun('Enter the edit PIN to delete this billing adjustment.', function() {
                    if (!confirm('Delete this billing adjustment? This cannot be undone.')) return;
                    deleteAdjustment(btn);
                });
            });
        })();

        // ─── Save button ─────────────────────────────────────────────────────────
        document.getElementById('bamSaveBtn').addEventListener('click', function() {
            var accountInput = document.getElementById('bamAccount');
            var accountValue = accountInput ? (accountInput.value || '').trim() : '';
            var typeValue = (document.getElementById('bamType') || {}).value || '';
            var amountEl = document.getElementById('bamAmount');
            if (amountEl) {
                amountEl.value = bamFormatAmountValue(amountEl.value);
            }

            // For Type=Others, account is disabled and auto-set to "Others"
            if (!accountValue && typeValue !== 'Others') {
                var modal = document.getElementById('bamSuccessModal');
                var icon  = modal.querySelector('.bam-success-icon');
                document.getElementById('bamSuccessTitle').textContent   = 'Validation Error';
                document.getElementById('bamSuccessMessage').textContent = 'Please select an account before saving.';
                icon.classList.add('error');
                icon.querySelector('svg').innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />';
                modal.classList.add('show');
                return;
            }

            var form  = document.getElementById('editBillingForm');
            var token = form.querySelector('input[name="_token"]');
            var lroId = (document.getElementById('bamLroId') || {}).value || '';
            var arId  = (document.getElementById('bamArId')  || {}).value || '';

            var payload = {
                _token:          token ? token.value : '{{ csrf_token() }}',
                type:            (document.getElementById('bamType')           || {}).value || 'CM',
                date:            (document.getElementById('bamDate')           || {}).value || null,
                account:         accountValue,
                account_name:    (document.getElementById('bamAccountName')    || {}).value || null,
                bam_no:          (document.getElementById('bamNo')             || {}).value || null,
                amount:          (document.getElementById('bamAmount')         || {}).value || '0',
                ar:              (document.getElementById('bamAr')             || {}).value || 'AR',
                acct_code:       (document.getElementById('bamAcctCode')       || {}).value || null,
                remarks:         (document.getElementById('bamRemarks')        || {}).value || null,
                status:          (document.getElementById('bamStatus')         || {}).value || 'Pending',
                correct_reading: (document.getElementById('bamCorrectReading') || {}).value || '0'
            };

            // Determine URL and HTTP method based on edit mode
            var httpMethod, saveUrl;
            if (lroId) {
                httpMethod = 'PUT';
                saveUrl    = '/billing-adjustment/lro/' + lroId;
            } else if (arId) {
                httpMethod = 'PUT';
                saveUrl    = '/billing-adjustment/ar/' + arId;
            } else {
                httpMethod = 'POST';
                saveUrl    = "{{ route('edit-billing.save') }}";
            }

            var btn = document.getElementById('bamSaveBtn');
            btn.disabled = true;

            fetch(saveUrl, {
                method: httpMethod,
                credentials: 'include',
                headers: {
                    'Content-Type':     'application/json',
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':     payload._token
                },
                body: JSON.stringify(payload)
            })
            .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
            .then(function(result) {
                btn.disabled = false;
                var modal    = document.getElementById('bamSuccessModal');
                var icon     = modal.querySelector('.bam-success-icon');
                var iconSvg  = icon.querySelector('svg');

                if (result.ok && result.data.success) {
                    icon.classList.remove('error');
                    iconSvg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />';
                    document.getElementById('bamSuccessTitle').textContent   = 'Billing Adjustment Saved!';
                    document.getElementById('bamSuccessMessage').textContent = result.data.message || 'Billing adjustment saved successfully.';

                    if (lroId || arId) {
                        // Editing an existing record — go back to list after OK
                        document.querySelector('.bam-success-btn').onclick = function() {
                            window.location.href = "{{ route('billing-adjustment') }}";
                        };
                    } else {
                        // New entry — reset form and stay on page
                        var savedBamNo = result.data.bam_no ? parseInt(result.data.bam_no, 10) : null;
                        var nextBamNo  = savedBamNo ? String(savedBamNo + 1) : '';
                        document.querySelector('.bam-success-btn').onclick = function() {
                            document.getElementById('bamSuccessModal').classList.remove('show');
                        };
                        bamResetForm(nextBamNo);
                    }
                    modal.classList.add('show');
                } else {
                    icon.classList.add('error');
                    iconSvg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />';
                    document.getElementById('bamSuccessTitle').textContent   = 'Error';
                    document.getElementById('bamSuccessMessage').textContent = result.data.message || (result.data.errors ? Object.values(result.data.errors).flat().join(' ') : 'Save failed.');
                    modal.classList.add('show');
                }
            })
            .catch(function() {
                btn.disabled = false;
                var modal   = document.getElementById('bamSuccessModal');
                var icon    = modal.querySelector('.bam-success-icon');
                icon.classList.add('error');
                icon.querySelector('svg').innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />';
                document.getElementById('bamSuccessTitle').textContent   = 'Error';
                document.getElementById('bamSuccessMessage').textContent = 'Save failed. Please try again.';
                modal.classList.add('show');
            });
        });

        // ─── Account search ──────────────────────────────────────────────────────
        (function() {
            var accountInput    = document.getElementById('bamAccount');
            var accountNameInput = document.getElementById('bamAccountName');
            var suggestionsEl   = document.getElementById('bamAccountSuggestions');
            var debounceTimer   = null;

            function hideSuggestions() { suggestionsEl.style.display = 'none'; suggestionsEl.innerHTML = ''; }

            function showSuggestions(items) {
                if (!items || items.length === 0) {
                    suggestionsEl.innerHTML = '<div class="list-group-item list-group-item-secondary small">No consumers found.</div>';
                } else {
                    suggestionsEl.innerHTML = items.map(function(c) {
                        return '<a href="#" class="list-group-item list-group-item-action bam-consumer-item"' +
                            ' data-account="' + (c.account_no || '') + '"' +
                            ' data-name="'   + (c.account_name || '').replace(/"/g, '&quot;') + '"' +
                            ' data-id="'     + (c.id || '') + '">' +
                            '<strong>' + (c.account_no || '') + '</strong> — ' + (c.account_name || '') + '</a>';
                    }).join('');
                }
                suggestionsEl.style.display = 'block';
            }

            function doSearch(q) {
                if (!q || q.length < 2) return;
                fetch("{{ route('consumer.suggestions') }}?q=" + encodeURIComponent(q), {
                    method: 'GET', credentials: 'include',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { if (!r.ok) return Promise.reject(); return r.json(); })
                .then(function(res) {
                    if (res.success && res.data && res.data.length) showSuggestions(res.data);
                    else showSuggestions([]);
                })
                .catch(function() {
                    suggestionsEl.innerHTML = '<div class="list-group-item list-group-item-danger small">Search unavailable.</div>';
                    suggestionsEl.style.display = 'block';
                });
            }

            accountInput.addEventListener('input', function() {
                if (accountInput.disabled) { hideSuggestions(); return; }
                var q = (this.value || '').trim();
                clearTimeout(debounceTimer);
                if (q.length < 2) { hideSuggestions(); return; }
                debounceTimer = setTimeout(function() { doSearch(q); }, 250);
            });
            accountInput.addEventListener('blur',  function() { setTimeout(hideSuggestions, 200); });
            accountInput.addEventListener('focus', function() { var q = (this.value || '').trim(); if (q.length >= 2) doSearch(q); });

            suggestionsEl.addEventListener('click', function(e) {
                var item = e.target.closest('.bam-consumer-item');
                if (item) {
                    e.preventDefault();
                    accountInput.value = item.getAttribute('data-account') || '';
                    if (accountNameInput) accountNameInput.value = item.getAttribute('data-name') || '';
                    hideSuggestions();
                }
            });

            // ─── Acct Code dropdown ──────────────────────────────────────────────
            (function() {
                var acctDisplay  = document.getElementById('bamAcctCodeDisplay');
                var acctHidden   = document.getElementById('bamAcctCode');
                var acctDropdown = document.getElementById('bamAcctCodeDropdown');
                var remarksInput = document.getElementById('bamRemarks');
                if (!acctDisplay || !acctHidden || !acctDropdown) return;

                acctDisplay.addEventListener('click', function() {
                    if (acctDisplay.disabled) return;
                    acctDropdown.style.display = acctDropdown.style.display === 'none' ? 'block' : 'none';
                });
                acctDropdown.querySelectorAll('.bam-acct-option').forEach(function(opt) {
                    opt.addEventListener('click', function() {
                        var code = this.getAttribute('data-code') || '';
                        var desc = this.getAttribute('data-desc') || '';
                        var displayText = '';
                        if (code) {
                            displayText = String(this.textContent || '').trim();
                            if (!displayText) displayText = desc.trim() ? (code + ' - ' + desc) : code;
                        }
                        acctHidden.value = code;
                        acctDisplay.value = displayText;
                        acctDisplay.placeholder = displayText ? '' : '— Select Acct Code —';
                        if (remarksInput) remarksInput.value = desc;
                        acctDropdown.style.display = 'none';
                        bamApplySundryRules();
                        bamApplyStatusOptions();
                    });
                });
                document.addEventListener('click', function(e) {
                    if (!acctDisplay.contains(e.target) && !acctDropdown.contains(e.target)) {
                        acctDropdown.style.display = 'none';
                    }
                });
            })();
        })();
        
        // ─── Type change rules ──────────────────────────────────────────────────
        (function() {
            var typeEl = document.getElementById('bamType');
            var ledgerEl = document.getElementById('bamAr');
            var amountEl = document.getElementById('bamAmount');
            if (!typeEl) return;
            typeEl.addEventListener('change', bamApplyTypeRules);
            if (ledgerEl) ledgerEl.addEventListener('change', function() {
                bamApplyTypeRules();
                bamApplyLedgerFieldRules();
                bamApplyStatusOptions();
            });
            if (amountEl) {
                amountEl.addEventListener('focus', function() {
                    amountEl.value = bamNormalizeAmountForEdit(amountEl.value);
                    amountEl.select();
                });
                amountEl.addEventListener('blur', function() {
                    amountEl.value = bamFormatAmountValue(amountEl.value);
                });
            }
            document.addEventListener('DOMContentLoaded', bamApplyTypeRules);
            bamApplyTypeRules();
        })();
    </script>
</body>
</html>
