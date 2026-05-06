<!DOCTYPE html>
<html lang="en">
@include('partials.header')

<body id="page-top">
    <style>
        @media screen {
            .disconnection-print-sheet { display: none !important; }
        }
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            html, body { background: #fff !important; color: #222 !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .disconnection-print-sheet { display: block !important; }
            .disconnection-print-sheet .print-report {
                padding: 0;
                font-family: Arial, Helvetica, sans-serif;
                color: #222;
                width: 100%;
            }
            .disconnection-print-sheet .print-header {
                text-align: center;
                margin-bottom: 10px;
                padding-bottom: 6px;
            }
            .disconnection-print-sheet .print-title {
                font-size: 16px;
                font-weight: 700;
                margin: 0 0 1px 0;
                letter-spacing: 0.3px;
            }
            .disconnection-print-sheet .print-subtitle {
                font-size: 13px;
                font-weight: 600;
                margin: 3px 0 2px;
            }
            .disconnection-print-sheet .print-meta {
                font-size: 12px;
                color: #444;
                margin: 0;
            }
            .disconnection-print-sheet .print-criteria {
                margin: 0 0 6px;
                font-size: 9px;
                color: #444;
            }
            .disconnection-print-sheet .print-zone-line {
                font-size: 10px;
                font-weight: 700;
                margin: 6px 0 3px;
            }
            .disconnection-print-sheet table.print-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 4px;
                margin-bottom: 8px;
                font-size: 12px;
                table-layout: fixed;
            }
            .disconnection-print-sheet table.print-table th,
            .disconnection-print-sheet table.print-table td {
                border: none !important;
                padding: 5px 4px;
                text-align: left;
                vertical-align: top;
                color: #333;
            }
            .disconnection-print-sheet table.print-table td {
                font-size: 12px;
                line-height: 1.2;
            }
            .disconnection-print-sheet table.print-table th {
                background: #ffffff !important;
                font-weight: 700;
                font-size: 10px;
                text-transform: none;
            }
            .disconnection-print-sheet table.print-table tbody tr {
                height: 11mm;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            /* Target around 13 rows per printed page */
            .disconnection-print-sheet table.print-table tbody tr:nth-child(13n) {
                break-after: page;
                page-break-after: always;
            }
            .disconnection-print-sheet table.print-table td:nth-child(1),
            .disconnection-print-sheet table.print-table td:nth-child(3),
            .disconnection-print-sheet table.print-table td:nth-child(4),
            .disconnection-print-sheet table.print-table td:nth-child(5),
            .disconnection-print-sheet table.print-table td:nth-child(6),
            .disconnection-print-sheet table.print-table td:nth-child(7),
            .disconnection-print-sheet table.print-table td:nth-child(8),
            .disconnection-print-sheet table.print-table td:nth-child(9),
            .disconnection-print-sheet table.print-table td:nth-child(10) {
                white-space: nowrap;
            }
            .disconnection-print-sheet table.print-table td:nth-child(2) {
                white-space: normal;
                line-height: 1.15;
                word-break: break-word;
            }
            .disconnection-print-sheet table.print-table td:nth-child(11) {
                white-space: normal;
            }
            .disconnection-print-sheet table.print-table thead { display: table-header-group; }
            .disconnection-print-sheet .print-table .text-center { text-align: center; }
            .disconnection-print-sheet .print-table .text-right { text-align: right; }
            .disconnection-print-sheet .print-zone-heading {
                font-size: 9px;
                font-weight: 700;
                margin: 4px 0 2px;
                page-break-after: avoid;
            }
            .disconnection-print-sheet .print-footer {
                margin-top: 4px;
                padding-top: 4px;
                border-top: 0.5px solid #b9b9b9;
                font-size: 11px;
                color: #444;
            }
            .disconnection-print-sheet #printSearchNote {
                font-size: 8px;
                color: #555;
                font-style: italic;
                margin: 2px 0 3px;
            }
        }
    </style>
    <div id="wrapper" class="no-print">
        <!-- Sidebar -->
        @include('partials.sidebar')
        <!-- Sidebar -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                @include('partials.navbar')
                <!-- Topbar -->

                <!-- Container Fluid-->
                <div class="container-fluid" id="container-wrapper">
                    <!-- Page Header -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h1 class="h3 mb-1 text-dark font-weight-bold">Disconnection Management</h1>
                            <p class="text-muted mb-0 small">
                                @if(isset($filterType) && $filterType === '3_consecutive')
                                    List of consumers with 3 consecutive months without payment
                                @else
                                    List of consumers with passed disconnection dates
                                @endif
                            </p>
                        </div>
                        @if(isset($totalConsumers) && $totalConsumers > 0)
                            <div class="text-right">
                                <div class="small text-muted">Total Consumers</div>
                                <div class="h4 mb-0 text-primary font-weight-bold">{{ number_format($totalConsumers) }}</div>
                                <div class="small text-muted">Total Outstanding</div>
                                <div class="h5 mb-0 text-danger font-weight-bold">{{ number_format($totalOutstanding ?? 0, 2) }}</div>
                            </div>
                        @endif
                    </div>

                    <!-- Filter Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-filter mr-2"></i>Filters
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="{{ route('disconnection.index') }}" id="filterForm">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="small font-weight-bold">Zone</label>
                                        <select name="zone" class="form-control form-control-sm" id="zoneFilter">
                                            <option value="">All Zones</option>
                                            @foreach($zones as $zoneCode)
                                                <option value="{{ $zoneCode }}" {{ ($zone ?? '') == $zoneCode ? 'selected' : '' }}>
                                                    Zone {{ $zoneCode }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small font-weight-bold">Filter Type</label>
                                        <select name="filter_type" class="form-control form-control-sm">
                                            <option value="disconnection_date" {{ (!isset($filterType) || $filterType == 'disconnection_date') ? 'selected' : '' }}>
                                                Disconnection Date
                                            </option>
                                            <option value="3_consecutive" {{ (isset($filterType) && $filterType == '3_consecutive') ? 'selected' : '' }}>
                                                3 Months Consecutive
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small font-weight-bold">
                                            Billing Month
                                            <i class="fas fa-info-circle text-info ml-1" 
                                               data-toggle="tooltip" 
                                               title="Select a month to automatically filter consumers with disconnection dates for that billing month"></i>
                                        </label>
                                        <input type="month" name="billing_month" class="form-control form-control-sm" 
                                               id="billingMonthInput" 
                                               value="{{ $billingMonth ?? request('billing_month', '') }}"
                                               title="Select billing month to filter by corresponding disconnection dates">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small font-weight-bold">
                                            Billing Date (Optional)
                                            <i class="fas fa-info-circle text-info ml-1" 
                                               data-toggle="tooltip" 
                                               title="Or enter a specific billing date for precise filtering"></i>
                                        </label>
                                        <input type="date" name="billing_date" class="form-control form-control-sm" 
                                               id="billingDateInput" 
                                               value="{{ $billingDate ?? request('billing_date', '') }}"
                                               title="Enter specific billing date (optional)">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="small font-weight-bold">Search</label>
                                        <input type="text" class="form-control form-control-sm" id="searchInput" 
                                               placeholder="Account No, Name, or Address...">
                                    </div>
                                    <div class="col-md-1 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-filter"></i> Filter
                                        </button>
                                        <a href="{{ route('disconnection.index') }}" class="btn btn-secondary btn-sm ml-2">
                                            <i class="fas fa-redo"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Consumers List by Zone -->
                    @if($consumersByZone->isEmpty())
                        <div class="card shadow mb-4">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h5 class="text-muted">No consumers found for disconnection</h5>
                                <p class="text-muted">All consumers are up to date with their payments.</p>
                            </div>
                        </div>
                    @else
                        <form id="disconnectionForm" method="POST" action="{{ route('disconnection.generate-notice') }}">
                            @csrf
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="small font-weight-bold">Disconnection Date</label>
                                    <input type="date" name="disconnection_date" class="form-control form-control-sm" 
                                           value="{{ date('Y-m-d', strtotime('+7 days')) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="small font-weight-bold">Assign to Disconnector (Optional)</label>
                                    <select name="assign_to" class="form-control form-control-sm">
                                        <option value="">-- No Assignment Yet --</option>
                                        @foreach($disconnectors ?? [] as $disconnector)
                                            <option value="{{ $disconnector->id }}">{{ $disconnector->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="printListBtn" title="Print the list for current filters (respects search)">
                                        <i class="fas fa-print"></i> Print List
                                    </button>
                                    <button type="button" class="btn btn-success btn-sm" id="saveBtn">
                                        <i class="fas fa-save"></i> Save & Send to Mobile App
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-sm" name="action" value="generate">
                                        <i class="fas fa-file-alt"></i> Generate Notice for Preview
                                    </button>
                                </div>
                            </div>

                            @foreach($consumersByZone as $zoneCode => $zoneConsumers)
                                <div class="card shadow mb-4 zone-card" data-zone="{{ $zoneCode }}">
                                    <div class="card-header py-3 d-flex justify-content-between align-items-center" 
                                         style="cursor: pointer;" 
                                         data-toggle="collapse" 
                                         data-target="#zoneCollapse{{ $zoneCode }}"
                                         aria-expanded="true">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-chevron-down mr-2"></i>
                                            Zone {{ $zoneCode }} - {{ $zoneConsumers->count() }} Consumer(s)
                                            <span class="badge badge-info ml-2">{{ number_format($zoneConsumers->sum('total_outstanding'), 2) }}</span>
                                        </h6>
                                        <div>
                                            <input type="checkbox" class="select-all-zone" data-zone="{{ $zoneCode }}" 
                                                   onclick="event.stopPropagation();">
                                            <label class="small ml-2" onclick="event.stopPropagation();">Select All</label>
                                        </div>
                                    </div>
                                    <div id="zoneCollapse{{ $zoneCode }}" class="collapse show">
                                        <div class="card-body p-0">
                                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                                <table class="table table-bordered table-hover table-sm mb-0" id="zoneTable{{ $zoneCode }}">
                                                    <thead class="bg-primary text-white" style="position: sticky; top: 0; z-index: 10;">
                                                        <tr>
                                                            <th width="30">
                                                                <input type="checkbox" class="select-all-zone" data-zone="{{ $zoneCode }}">
                                                            </th>
                                                            <th>Account No.</th>
                                                            <th>Account Name</th>
                                                            <th>Address</th>
                                                            <th>Meter No.</th>
                                                            <th>Unpaid Months</th>
                                                            <th>Total Outstanding</th>
                                                            <th>Oldest Unpaid</th>
                                                            <th>Latest Unpaid</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="zone-tbody" data-zone="{{ $zoneCode }}">
                                                        @foreach($zoneConsumers as $consumer)
                                                            <tr class="consumer-row" 
                                                                data-zone="{{ $zoneCode }}"
                                                                data-account-no="{{ strtolower($consumer->account_no) }}"
                                                                data-account-name="{{ strtolower($consumer->account_name) }}"
                                                                data-address="{{ strtolower($consumer->address1 ?? '') }}">
                                                                <td>
                                                                    <input type="checkbox" name="consumer_ids[]" 
                                                                           value="{{ $consumer->id }}" 
                                                                           class="consumer-checkbox" 
                                                                           data-zone="{{ $zoneCode }}">
                                                                </td>
                                                                <td>{{ $consumer->account_no }}</td>
                                                                <td>{{ $consumer->account_name }}</td>
                                                                <td>{{ $consumer->address1 }}</td>
                                                                <td>{{ $consumer->meter_number }}</td>
                                                                <td>
                                                                    <span class="badge badge-danger">{{ $consumer->unpaid_months }}</span>
                                                                </td>
                                                                <td class="text-right font-weight-bold text-danger">
                                                                    {{ number_format($consumer->total_outstanding, 2) }}
                                                                </td>
                                                                <td>{{ $consumer->oldest_unpaid_date ? (is_string($consumer->oldest_unpaid_date) ? \Carbon\Carbon::parse($consumer->oldest_unpaid_date)->format('M d, Y') : $consumer->oldest_unpaid_date->format('M d, Y')) : 'N/A' }}</td>
                                                                <td>{{ $consumer->latest_unpaid_date ? (is_string($consumer->latest_unpaid_date) ? \Carbon\Carbon::parse($consumer->latest_unpaid_date)->format('M d, Y') : $consumer->latest_unpaid_date->format('M d, Y')) : 'N/A' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </form>
                    @endif
                </div>
                <!---Container Fluid-->
            </div>
            <!-- Footer -->
            @include('partials.footer')
            <!-- Footer -->
        </div>
    </div>

    <!-- Scroll to top -->
    <a class="scroll-to-top rounded no-print" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    @if(!$consumersByZone->isEmpty())
        @php
            $filterTypeLabel = (isset($filterType) && $filterType === '3_consecutive')
                ? '3 consecutive months without payment'
                : 'Passed disconnection dates';
            $zoneLabel = !empty($zone ?? null) ? 'Zone ' . $zone : 'All zones';
            $billingMonthLabel = '—';
            if (!empty($billingMonth ?? null)) {
                try {
                    $billingMonthLabel = \Carbon\Carbon::parse($billingMonth . '-01')->format('F Y');
                } catch (\Exception $e) {
                    $billingMonthLabel = $billingMonth;
                }
            }
            $billingMonthHeader = $billingMonthLabel !== '—'
                ? str_replace(' ', '-', $billingMonthLabel)
                : \Carbon\Carbon::now()->format('F-Y');
        @endphp
        <div id="disconnection-print-sheet" class="disconnection-print-sheet" aria-hidden="true">
            <div class="print-report">
                <div class="print-header">
                    <h1 class="print-title">HAGONOY WATER DISTRICT</h1>
                    <p class="print-meta">Guihing, Hagonoy</p>
                    <h2 class="print-subtitle">CONSUMERS FOR DISCONNECTION</h2>
                    <p class="print-meta">{{ $billingMonthHeader }}</p>
                </div>

                <p class="print-meta" id="printSearchNote" style="display: none;"></p>

                @foreach($consumersByZone as $zoneCode => $zoneConsumers)
                <div class="print-zone-block" data-print-zone="{{ $zoneCode }}">
                    <table class="print-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 8%;">AccNo</th>
                                <th style="width: 24%;">Name</th>
                                <th class="text-center" style="width: 9%;">Meter #</th>
                                <th class="text-right" style="width: 7%;">LastRdg</th>
                                <th class="text-right" style="width: 7%;">CURRENT</th>
                                <th class="text-right" style="width: 7%;">30 DAYS</th>
                                <th class="text-right" style="width: 7%;">60 DAYS</th>
                                <th class="text-right" style="width: 7%;">90 DAYS</th>
                                <th class="text-right" style="width: 8%;">OVER 90</th>
                                <th class="text-right" style="width: 9%;">BALANCE</th>
                                <th style="width: 7%;">REMARKS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($zoneConsumers as $consumer)
                                <tr class="print-consumer-row"
                                    data-zone="{{ $zoneCode }}"
                                    data-account-no="{{ strtolower((string) $consumer->account_no) }}">
                                    <td>{{ $consumer->account_no }}</td>
                                    <td>
                                        {{ $consumer->account_name }}<br>
                                        <small>{{ $consumer->address1 }}</small>
                                    </td>
                                    <td class="text-center">{{ $consumer->meter_number }}</td>
                                    <td class="text-right">{{ number_format((float)($consumer->last_reading ?? 0), 0) }}</td>
                                    <td class="text-right">{{ number_format((float)($consumer->aging_current ?? 0), 2) }}</td>
                                    <td class="text-right">{{ ((float)($consumer->aging_30_days ?? 0) > 0) ? number_format((float)$consumer->aging_30_days, 2) : '' }}</td>
                                    <td class="text-right">{{ ((float)($consumer->aging_60_days ?? 0) > 0) ? number_format((float)$consumer->aging_60_days, 2) : '' }}</td>
                                    <td class="text-right">{{ ((float)($consumer->aging_90_days ?? 0) > 0) ? number_format((float)$consumer->aging_90_days, 2) : '' }}</td>
                                    <td class="text-right">{{ ((float)($consumer->aging_over_90 ?? 0) > 0) ? number_format((float)$consumer->aging_over_90, 2) : '' }}</td>
                                    <td class="text-right">{{ number_format($consumer->total_outstanding, 2) }}</td>
                                    <td></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endforeach

                <div class="print-footer">
                    <span>Total outstanding: <strong>{{ number_format($totalOutstanding ?? 0, 2) }}</strong></span>
                    <span style="margin-left: 24px;">Consumers: <strong>{{ number_format($totalConsumers ?? 0) }}</strong></span>
                </div>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Select all checkboxes in a zone
            document.querySelectorAll('.select-all-zone').forEach(function(checkbox) {
                checkbox.addEventListener('change', function(e) {
                    e.stopPropagation();
                    const zone = this.getAttribute('data-zone');
                    const checkboxes = document.querySelectorAll(`.consumer-checkbox[data-zone="${zone}"]`);
                    const isChecked = this.checked;
                    checkboxes.forEach(function(cb) {
                        cb.checked = isChecked;
                    });
                    updateSelectAllState(zone);
                });
            });

            // Update select all state when individual checkboxes change
            document.querySelectorAll('.consumer-checkbox').forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    const zone = this.getAttribute('data-zone');
                    updateSelectAllState(zone);
                });
            });

            function updateSelectAllState(zone) {
                const checkboxes = document.querySelectorAll(`.consumer-checkbox[data-zone="${zone}"]`);
                const selectAllCheckboxes = document.querySelectorAll(`.select-all-zone[data-zone="${zone}"]`);
                const checkedCount = document.querySelectorAll(`.consumer-checkbox[data-zone="${zone}"]:checked`).length;
                const allChecked = checkedCount === checkboxes.length && checkboxes.length > 0;
                
                selectAllCheckboxes.forEach(function(cb) {
                    cb.checked = allChecked;
                    cb.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
                });
            }

            // Search functionality
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    const searchTerm = this.value.toLowerCase().trim();
                    
                    searchTimeout = setTimeout(function() {
                        document.querySelectorAll('.consumer-row').forEach(function(row) {
                            const accountNo = row.getAttribute('data-account-no') || '';
                            const accountName = row.getAttribute('data-account-name') || '';
                            const address = row.getAttribute('data-address') || '';
                            
                            if (searchTerm === '' || 
                                accountNo.includes(searchTerm) || 
                                accountName.includes(searchTerm) || 
                                address.includes(searchTerm)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                        
                        // Update zone visibility
                        document.querySelectorAll('.zone-card').forEach(function(card) {
                            const zone = card.getAttribute('data-zone');
                            const visibleRows = card.querySelectorAll(`.consumer-row:not([style*="display: none"])`);
                            
                            if (visibleRows.length === 0 && searchTerm !== '') {
                                card.style.display = 'none';
                            } else {
                                card.style.display = '';
                            }
                        });
                    }, 300);
                });
            }

            const disconnectionForm = document.getElementById('disconnectionForm');

            // Save & Send to Mobile App: submit to save-and-assign so orders are stored in disconnection_orders
            const saveBtn = document.getElementById('saveBtn');
            if (saveBtn && disconnectionForm) {
                saveBtn.addEventListener('click', function() {
                    const checked = document.querySelectorAll('input[name="consumer_ids[]"]:checked');
                    if (checked.length === 0) {
                        alert('Please select at least one consumer.');
                        return;
                    }
                    disconnectionForm.action = '{{ route("disconnection.save-and-assign") }}';
                    disconnectionForm.submit();
                });
            }

            // Form submission validation (for Generate Notice button)
            if (disconnectionForm) {
                disconnectionForm.addEventListener('submit', function(e) {
                    // When submitting to save-and-assign, validation already done by Save button
                    if (this.action.indexOf('save-and-assign') !== -1) {
                        return;
                    }
                    const checked = document.querySelectorAll('input[name="consumer_ids[]"]:checked');
                    if (checked.length === 0) {
                        e.preventDefault();
                        alert('Please select at least one consumer.');
                        return false;
                    }
                    // Show loading state
                    const submitButtons = this.querySelectorAll('button[type="submit"], #saveBtn');
                    submitButtons.forEach(function(btn) {
                        btn.disabled = true;
                        const originalText = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Processing...';
                        btn.setAttribute('data-original-text', originalText);
                    });
                });
            }

            // Collapse/expand zones
            document.querySelectorAll('[data-toggle="collapse"]').forEach(function(trigger) {
                trigger.addEventListener('click', function() {
                    const icon = this.querySelector('.fa-chevron-down, .fa-chevron-up');
                    if (icon) {
                        setTimeout(function() {
                            const target = document.querySelector(trigger.getAttribute('data-target'));
                            if (target && target.classList.contains('show')) {
                                icon.classList.remove('fa-chevron-down');
                                icon.classList.add('fa-chevron-up');
                            } else {
                                icon.classList.remove('fa-chevron-up');
                                icon.classList.add('fa-chevron-down');
                            }
                        }, 100);
                    }
                });
            });

            // Initialize select all states
            document.querySelectorAll('.zone-card').forEach(function(card) {
                const zone = card.getAttribute('data-zone');
                updateSelectAllState(zone);
            });

            // Initialize tooltips
            if (typeof $ !== 'undefined' && $.fn.tooltip) {
                $('[data-toggle="tooltip"]').tooltip();
            }

            // Auto-submit filter form when billing month changes for better UX
            const billingMonthInput = document.getElementById('billingMonthInput');
            if (billingMonthInput) {
                billingMonthInput.addEventListener('change', function() {
                    // Clear billing date if month is selected
                    const billingDateInput = document.getElementById('billingDateInput');
                    if (billingDateInput && this.value) {
                        billingDateInput.value = '';
                    }
                    // Auto-filter when month is selected
                    document.getElementById('filterForm').submit();
                });
            }

            // Clear month when specific date is selected
            const billingDateInput = document.getElementById('billingDateInput');
            if (billingDateInput) {
                billingDateInput.addEventListener('change', function() {
                    if (this.value) {
                        const billingMonthInput = document.getElementById('billingMonthInput');
                        if (billingMonthInput) {
                            billingMonthInput.value = '';
                        }
                    }
                });
            }

            // Print list (professional sheet; mirrors search visibility)
            const printSheet = document.getElementById('disconnection-print-sheet');
            function syncPrintSheetFromTable() {
                if (!printSheet) return;
                const searchInput = document.getElementById('searchInput');
                const printSearchNote = document.getElementById('printSearchNote');
                document.querySelectorAll('.print-consumer-row').forEach(function(prow) {
                    const zone = prow.getAttribute('data-zone');
                    const acc = prow.getAttribute('data-account-no');
                    const mainRow = document.querySelector(
                        '.consumer-row[data-zone="' + zone + '"][data-account-no="' + acc + '"]'
                    );
                    if (!mainRow) {
                        prow.style.display = '';
                        return;
                    }
                    prow.style.display = mainRow.style.display === 'none' ? 'none' : '';
                });
                document.querySelectorAll('#disconnection-print-sheet .print-zone-block').forEach(function(block) {
                    const rows = block.querySelectorAll('.print-consumer-row');
                    let anyVisible = false;
                    rows.forEach(function(r) {
                        if (r.style.display !== 'none') anyVisible = true;
                    });
                    block.style.display = anyVisible ? '' : 'none';
                });
                if (printSearchNote) {
                    var shown = 0;
                    document.querySelectorAll('.print-consumer-row').forEach(function(r) {
                        if (r.style.display !== 'none') shown++;
                    });
                    if (searchInput && searchInput.value.trim() !== '') {
                        printSearchNote.textContent = 'Search is active: this printout includes ' + shown + ' visible account(s). Totals above are for the full list from your filters (not only search results).';
                        printSearchNote.style.display = 'block';
                    } else {
                        printSearchNote.textContent = '';
                        printSearchNote.style.display = 'none';
                    }
                }
            }
            function resetPrintSheetDisplay() {
                if (!printSheet) return;
                document.querySelectorAll('.print-consumer-row').forEach(function(r) { r.style.display = ''; });
                document.querySelectorAll('.print-zone-block').forEach(function(b) { b.style.display = ''; });
                var printSearchNote = document.getElementById('printSearchNote');
                if (printSearchNote) {
                    printSearchNote.textContent = '';
                    printSearchNote.style.display = 'none';
                }
            }
            const printListBtn = document.getElementById('printListBtn');
            if (printListBtn && printSheet) {
                printListBtn.addEventListener('click', function() {
                    syncPrintSheetFromTable();
                    window.addEventListener('afterprint', function onAfterPrint() {
                        window.removeEventListener('afterprint', onAfterPrint);
                        resetPrintSheetDisplay();
                    });
                    window.print();
                });
            }
        });
    </script>
</body>
</html>
