<!DOCTYPE html>
<html lang="en">
@include('partials.header')

<body id="page-top">
    <style>
        #readingGuidePrintSheet { display: none; }
        @media print {
            body.print-reading-guide #wrapper,
            body.print-reading-guide .scroll-to-top,
            body.print-reading-guide .modal,
            body.print-reading-guide script { display: none !important; }
            body.print-reading-guide #readingGuidePrintSheet {
                display: block !important;
                padding: 8mm;
                font-family: Arial, Helvetica, sans-serif;
                color: #111;
            }
            body.print-reading-guide #readingGuidePrintSheet .rg-title {
                font-size: 22px;
                font-weight: 700;
                margin: 0;
                line-height: 1.2;
            }
            body.print-reading-guide #readingGuidePrintSheet .rg-subtitle {
                font-size: 18px;
                margin: 0 0 8px 0;
            }
            body.print-reading-guide #readingGuidePrintSheet .rg-meta {
                font-size: 15px;
                margin: 0 0 10px 0;
            }
            body.print-reading-guide #readingGuidePrintSheet table {
                width: 100%;
                border-collapse: collapse;
                font-size: 14px;
                border: none;
            }
            body.print-reading-guide #readingGuidePrintSheet thead th {
                border-top: 1px solid #333;
                border-bottom: 1px solid #333;
                border-left: none;
                border-right: none;
                padding: 3px 4px;
                vertical-align: top;
            }
            body.print-reading-guide #readingGuidePrintSheet tbody td {
                border: none;
                padding: 3px 4px;
                vertical-align: top;
            }
            /* Handwriting lines: PresRdg + Remarks */
            body.print-reading-guide #readingGuidePrintSheet tbody tr td:nth-child(7),
            body.print-reading-guide #readingGuidePrintSheet tbody tr td:nth-child(9) {
                border-bottom: 1px solid #333;
            }
        }
    </style>
    <div id="wrapper">
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
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
                        <div>
                            <h1 class="h3 mb-1 text-gray-800">Consumers Master List</h1>
                            <p class="text-muted mb-0">Search and export consumer information by zone, status, and other filters.</p>
                        </div>
                        <div>
                            <a href="{{ route('consumer-master-list') }}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-sync-alt mr-1"></i>Reset
                            </a>
                        </div>
                    </div>

                    <!-- Filters Card -->
                    <div class="row mb-3">
                        <div class="col-lg-12">
                            <div class="card shadow-sm">
                                <div class="card-body py-3">
                                    <form method="GET" action="{{ route('consumer-master-list') }}">
                                        <!-- Search Bar -->
                                        <div class="row mb-3">
                                            <div class="col-md-12">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text bg-primary text-white">
                                                            <i class="fas fa-search"></i>
                                                        </span>
                                                    </div>
                                                    <input type="text" 
                                                           name="search" 
                                                           class="form-control form-control-lg" 
                                                           placeholder="Search by Customer Name or Account Number (e.g., Villamonte or 011-32-001)" 
                                                           value="{{ $filters['search'] ?? '' }}"
                                                           autofocus>
                                                    @if(!empty($filters['search']))
                                                        <div class="input-group-append">
                                                            <a href="{{ route('consumer-master-list', array_filter(array_merge($filters, ['search' => null]))) }}" 
                                                               class="btn btn-outline-secondary" 
                                                               title="Clear search">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row align-items-end">
                                            <div class="col-md-2 mb-3">
                                                <label class="small font-weight-bold mb-1">Zone / Route</label>
                                                <select name="zone" class="form-control form-control-sm">
                                                    <option value="">All Zones</option>
                                                    @foreach($zones as $zone)
                                                        <option value="{{ $zone }}" {{ ($filters['zone'] ?? '') == $zone ? 'selected' : '' }}>Zone {{ $zone }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-md-2 mb-3">
                                                <label class="small font-weight-bold mb-1">Status</label>
                                                <select name="status" class="form-control form-control-sm">
                                                    <option value="">All Status</option>
                                                    <option value="Active" {{ ($filters['status'] ?? '') == 'Active' ? 'selected' : '' }}>Active</option>
                                                    <option value="Pending" {{ ($filters['status'] ?? '') == 'Pending' ? 'selected' : '' }}>Pending</option>
                    
                                                    <option value="Disconnected" {{ ($filters['status'] ?? '') == 'Disconnected' ? 'selected' : '' }}>Disconnected</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-2 mb-3">
                                                <label class="small font-weight-bold mb-1">Gender</label>
                                                <select name="gender" class="form-control form-control-sm">
                                                    <option value="">All Gender</option>
                                                    <option value="Male" {{ ($filters['gender'] ?? '') == 'Male' ? 'selected' : '' }}>Male</option>
                                                    <option value="Female" {{ ($filters['gender'] ?? '') == 'Female' ? 'selected' : '' }}>Female</option>
                                                    <option value="Others" {{ ($filters['gender'] ?? '') == 'Others' ? 'selected' : '' }}>Others</option>
                                                </select>
                                            </div>

                                            <div class="col-md-2 mb-3">
                                                <div class="custom-control custom-checkbox mt-4">
                                                    <input type="checkbox" class="custom-control-input" id="seniorCitizen" name="senior_citizen" value="1" {{ !empty($filters['senior_citizen']) ? 'checked' : '' }}>
                                                    <label class="custom-control-label small font-weight-bold" for="seniorCitizen">Senior Citizen</label>
                                                </div>
                                            </div>

                                            <div class="col-md-2 mb-3">
                                                <label class="small font-weight-bold mb-1">Meter Number</label>
                                                <input type="text" name="meter_number" class="form-control form-control-sm" placeholder="Meter Number" value="{{ $filters['meter_number'] ?? '' }}">
                                            </div>

                                            <div class="col-md-2 mb-3">
                                                <label class="small font-weight-bold mb-1">Address</label>
                                                <input type="text" name="address" class="form-control form-control-sm" placeholder="Address" value="{{ $filters['address'] ?? '' }}">
                                            </div>

                                            <div class="col-md-2 mb-3">
                                                <label class="small font-weight-bold mb-1">Meter Location</label>
                                                <input type="text" name="meter_location" class="form-control form-control-sm" placeholder="Location" value="{{ $filters['meter_location'] ?? '' }}">
                                            </div>

                                            <div class="col-md-2 mb-3">
                                                <label class="small font-weight-bold mb-1">Ledger Status</label>
                                                <select name="ledger_status" class="form-control form-control-sm">
                                                    <option value="">All</option>
                                                    <option value="missing" {{ ($filters['ledger_status'] ?? '') === 'missing' ? 'selected' : '' }}>Not Imported</option>
                                                    <option value="imported" {{ ($filters['ledger_status'] ?? '') === 'imported' ? 'selected' : '' }}>Imported</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mt-3">
                                            <div class="col-md-12 text-right">
                                                <button type="submit" class="btn btn-primary btn-sm mr-2">
                                                    <i class="fas fa-sync-alt mr-1"></i>Generate
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm mr-2" id="printReadingGuideBtn">
                                                    <i class="fas fa-file-pdf mr-1"></i>Print
                                                </button>
                                                <button type="button" class="btn btn-success btn-sm mr-2" id="exportReadingGuideExcelBtn">
                                                    <i class="fas fa-file-excel mr-1"></i>Export Excel
                                                </button>
                                                <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#importDmModal" title="Upload Excel to add DM for multiple consumers (date: 2026-02-27)">
                                                    <i class="fas fa-file-excel mr-1"></i>Upload DM (Excel)
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary by Zone -->
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <h6 class="font-weight-bold text-primary">Details</h6>
                        </div>
                        <div class="col-md-6 text-right">
                            <h6 class="font-weight-bold text-primary">Summary by Zone</h6>
                        </div>
                    </div>
                   
                    
                    <!-- Main Data Table -->
                    <div class="row">
                        <div class="col-lg-9">
                            <div class="card shadow-sm mb-4">
                                <div class="card-body p-0">
                                    <div class="px-3 pt-3 pb-2 border-bottom bg-light">
                                        <label class="small font-weight-bold mb-1 text-muted">Filter table</label>
                                        <input type="text" id="consumerTableSearch" class="form-control form-control-sm" placeholder="Type to search by Account No or Customer name...">
                                        <small class="text-muted" id="consumerTableSearchCount"></small>
                                    </div>
                                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                                        <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;" id="consumerMasterTable">
                                            <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                                                <tr>
                                                    <th class="text-center py-2 px-2" style="min-width: 30px;">#</th>
                                                    <th class="py-2 px-2" style="min-width: 120px;">Account No</th>
                                                    <th class="py-2 px-2" style="min-width: 200px;">Customer</th>
                                                    <th class="py-2 px-2" style="min-width: 220px;">Address</th>
                                                    <th class="text-center py-2 px-2" style="min-width: 80px;">Zone</th>
                                                    <th class="text-center py-2 px-2" style="min-width: 70px;">Route</th>
                                                    <th class="text-center py-2 px-2" style="min-width: 70px;">Seq</th>
                                                    <th class="text-center py-2 px-2" style="min-width: 90px;">Status</th>
                                                    <th class="text-center py-2 px-2" style="min-width: 90px;">Category</th>
                                                    <th class="text-center py-2 px-2" style="min-width: 110px;">Meter No</th>
                                                    <th class="text-center py-2 px-2" style="min-width: 130px;">Meter Location</th>
                                                </tr>
                                            </thead>
                                             <tbody id="consumerTableBody">
                                                @forelse($consumers as $index => $consumer)
                                                    <tr class="consumer-row"
                                                        data-zone-code="{{ $consumer->zone_code ?? '' }}"
                                                        data-account-no="{{ $consumer->account_no ?? '' }}"
                                                        data-account-name="{{ $consumer->account_name ?? '' }}"
                                                        data-card-no="{{ $consumer->sequence ?? '' }}"
                                                        data-category="{{ $consumer->category_code ?? $consumer->category ?? '' }}"
                                                        data-meter-number="{{ $consumer->meter_number ?? '' }}"
                                                        data-prev-date="{{ $consumer->prev_bill_date ?? '' }}"
                                                        data-pres-rdg="{{ $consumer->prev_pres_rdg ?? '' }}"
                                                        data-prev-rdg="{{ $consumer->prev_prev_rdg ?? '' }}">
                                                        <td class="text-center py-1">{{ $index + 1 }}</td>
                                                        <td class="py-1 px-2">{{ $consumer->account_no }}</td>
                                                        <td class="py-1 px-2 text-uppercase">
                                                            {{ $consumer->account_name }}
                                                            @if(!empty($consumer->is_senior_citizen))
                                                                <span class="badge badge-warning badge-pill ml-1">SC</span>
                                                            @endif
                                                        </td>
                                                        <td class="py-1 px-2">
                                                            {{ trim(($consumer->address1 ?? '') . ' ' . ($consumer->address2 ?? $consumer->address_2 ?? '')) ?: '--' }}
                                                        </td>
                                                        <td class="text-center py-1 font-weight-bold">{{ $consumer->zone_code ?? '--' }}</td>
                                                        <td class="text-center py-1">{{ $consumer->route ?? '-' }}</td>
                                                        <td class="text-center py-1">{{ $consumer->sequence ?? '-' }}</td>
                                                        <td class="text-center py-1">
                                                            @php
                                                                $statusLabel = $consumer->status_label ?? ($consumer->status_code ?? $consumer->status ?? 'N/A');
                                                                $statusBadge = match ($statusLabel) {
                                                                    'Active' => 'success',
                                                                    'Pending' => 'warning',
                                                                    'Disconnected' => 'danger',
                                                                    default => 'secondary',
                                                                };
                                                            @endphp
                                                            <span class="badge badge-pill badge-{{ $statusBadge }}">
                                                                {{ $statusLabel }}
                                                            </span>
                                                        </td>
                                                        <td class="text-center py-1">{{ $consumer->category_code ?? $consumer->category ?? '--' }}</td>
                                                        <td class="text-center py-1">{{ $consumer->meter_number ?? '--' }}</td>
                                                        <td class="py-1 px-2">{{ $consumer->meter_location ?? '--' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr id="consumerTableEmptyRow">
                                                        <td colspan="11" class="text-center text-muted py-4">
                                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                            No consumers found for the selected filters.
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="card-footer bg-light py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            @if(!empty($filters['search']))
                                                Showing results for: <strong>"{{ $filters['search'] }}"</strong>
                                            @elseif(!empty($filters['zone']))
                                                Showing records for Zone <strong>{{ $filters['zone'] }}</strong>
                                            @else
                                                Showing all records
                                            @endif
                                        </small>
                                        <small class="text-muted">
                                            Total: <strong>{{ number_format($consumers->count()) }}</strong> consumer(s)
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary by Zone - Right Side -->
                        <div class="col-lg-3">
                            <div class="card shadow-sm mb-4">
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                                        <table class="table table-sm table-bordered mb-0" style="font-size: 11px;">
                                            <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                                                <tr>
                                                    <th class="text-center py-2 px-2">Zone</th>
                                                    <th class="text-center py-2 px-2">Consumers</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($summaryByZone as $zoneSummary)
                                                    <tr>
                                                        <td class="text-center py-1 font-weight-bold">{{ $zoneSummary->zone }}</td>
                                                        <td class="text-center py-1">{{ number_format($zoneSummary->total) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="2" class="text-center text-muted py-3">
                                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                            No zone summary available for the selected filters.
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!---Container Fluid-->
            </div>
            <!-- Footer -->
            @include('partials.footer')
            <!-- Footer -->
        </div>
    </div>

    <div id="readingGuidePrintSheet" aria-hidden="true">
        <h2 class="rg-title">HAGONOY WATER DISTRICT</h2>
        <p class="rg-subtitle">Guihing, Hagonoy</p>
        <h3 class="rg-subtitle">READING GUIDE</h3>
        <p class="rg-meta">Zone No : <span id="rgZoneNo">{{ $filters['zone'] ?? 'ALL' }}</span></p>
        <p class="rg-meta">Print Date : <span id="rgPrintDate"></span></p>
        <table>
            <thead>
                <tr>
                    <th>CardNo</th>
                    <th>AccountNo</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Meter Number</th>
                    <th>Prevdate</th>
                    <th>PresRdg</th>
                    <th>PrevRdg</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody id="readingGuidePrintBody"></tbody>
        </table>
    </div>

    <!-- Add DM Modal (single consumer) -->
    <div class="modal fade" id="addDmModal" tabindex="-1" role="dialog" aria-labelledby="addDmModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="addDmModalLabel">
                        <i class="fas fa-plus-circle mr-2"></i>Add DM for Consumer
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Insert a DM (Debit Memo) for this consumer. The amount will be added to the consumer's balance.</p>
                    <div class="alert alert-light border mb-3 py-2">
                        <strong id="addDmConsumerAccount">--</strong><br>
                        <span id="addDmConsumerName" class="text-muted">--</span>
                    </div>
                    <form id="addDmForm">
                        @csrf
                        <input type="hidden" name="consumer_zone_id" id="addDmConsumerZoneId" value="">
                        <div class="form-group">
                            <label for="addDmDate" class="font-weight-bold">Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" id="addDmDate" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="addDmAmount" class="font-weight-bold">Amount (Debit) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="addDmAmount" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label for="addDmReference" class="font-weight-bold">Reference</label>
                            <input type="text" id="addDmReference" class="form-control bg-light" placeholder="Auto-generated (6 digits) when saved" readonly>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" data-bs-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-warning btn-sm" id="addDmSubmitBtn">
                        <i class="fas fa-save mr-1"></i>Add DM
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload DM (Excel) Modal -->
    <div class="modal fade" id="importDmModal" tabindex="-1" role="dialog" aria-labelledby="importDmModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="importDmModalLabel">
                        <i class="fas fa-file-excel mr-2"></i>Upload DM (Excel)
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Upload an Excel file with columns <strong>account_no</strong> and <strong>amount</strong>. All DMs will use date <strong>2026-02-27</strong>. Reference is auto-generated (6 digits) per row.</p>
                    <form id="importDmForm">
                        @csrf
                        <div class="form-group">
                            <label for="importDmFile" class="font-weight-bold">Excel File <span class="text-danger">*</span></label>
                            <input type="file" name="file" id="importDmFile" class="form-control" accept=".xlsx,.xls,.csv" required>
                            <small class="text-muted">.xlsx, .xls or .csv. Max 10MB.</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" data-bs-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-info btn-sm" id="importDmSubmitBtn">
                        <i class="fas fa-upload mr-1"></i>Upload & Import DM
                    </button>
                </div>
            </div>
        </div>
    </div>

   

    <!-- Scroll to top -->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    
    <script>
        // Debug: Log flash messages
        console.log('Flash messages:', {
            success: @json(session('success')),
            error: @json(session('error')),
            warning: @json(session('warning')),
            import_success: @json(session('import_success'))
        });

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function accountNoTailSortKey(accountNo) {
            const s = String(accountNo ?? '').trim();
            if (!s) {
                return 0;
            }
            const idx = s.lastIndexOf('-');
            const tail = idx === -1 ? s : s.slice(idx + 1);
            const n = parseInt(String(tail).replace(/\D/g, ''), 10);
            return Number.isFinite(n) ? n : 0;
        }

        function getReadingGuideRowsData() {
            const rows = Array.from(document.querySelectorAll('#consumerTableBody tr.consumer-row')).filter(
                (row) => row.style.display !== 'none'
            );
            rows.sort((a, b) => {
                const za = String(a.dataset.zoneCode ?? '').trim();
                const zb = String(b.dataset.zoneCode ?? '').trim();
                if (za !== zb) {
                    return za.localeCompare(zb);
                }
                return accountNoTailSortKey(a.dataset.accountNo) - accountNoTailSortKey(b.dataset.accountNo);
            });

            return rows.map((row) => [
                row.dataset.cardNo || '',
                row.dataset.accountNo || '',
                row.dataset.accountName || '',
                row.dataset.category || '',
                row.dataset.meterNumber || '',
                row.dataset.prevDate || '',
                row.dataset.presRdg || '',
                row.dataset.prevRdg || '',
                ''
            ]);
        }

        function printReadingGuide() {
            const bodyEl = document.getElementById('readingGuidePrintBody');
            const printableRows = [];

            getReadingGuideRowsData().forEach((rowData) => {
                printableRows.push(`
                    <tr>
                        <td>${escapeHtml(rowData[0])}</td>
                        <td>${escapeHtml(rowData[1])}</td>
                        <td>${escapeHtml(rowData[2])}</td>
                        <td>${escapeHtml(rowData[3])}</td>
                        <td>${escapeHtml(rowData[4])}</td>
                        <td>${escapeHtml(rowData[5])}</td>
                        <td>${escapeHtml(rowData[6])}</td>
                        <td>${escapeHtml(rowData[7])}</td>
                        <td>${escapeHtml(rowData[8])}</td>
                    </tr>
                `);
            });

            bodyEl.innerHTML = printableRows.length
                ? printableRows.join('')
                : '<tr><td colspan="9" style="text-align:center;">No rows to print.</td></tr>';

            const now = new Date();
            document.getElementById('rgPrintDate').textContent =
                now.toLocaleDateString() + ' ' + now.toLocaleTimeString();

            document.body.classList.add('print-reading-guide');
            window.print();
            setTimeout(() => document.body.classList.remove('print-reading-guide'), 50);
        }

        function exportReadingGuideExcel() {
            const headers = ['CardNo', 'AccountNo', 'Name', 'Category', 'Meter Number', 'Prevdate', 'PresRdg', 'PrevRdg', 'Remarks'];
            const rows = getReadingGuideRowsData();
            const worksheetData = [headers, ...rows];
            const worksheet = XLSX.utils.aoa_to_sheet(worksheetData);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'ConsumerMasterList');

            const now = new Date();
            const yyyy = now.getFullYear();
            const mm = String(now.getMonth() + 1).padStart(2, '0');
            const dd = String(now.getDate()).padStart(2, '0');
            XLSX.writeFile(workbook, `consumerMasterList-${yyyy}${mm}${dd}.xlsx`);
        }

        const printReadingGuideBtn = document.getElementById('printReadingGuideBtn');
        if (printReadingGuideBtn) {
            printReadingGuideBtn.addEventListener('click', printReadingGuide);
        }
        const exportReadingGuideExcelBtn = document.getElementById('exportReadingGuideExcelBtn');
        if (exportReadingGuideExcelBtn) {
            exportReadingGuideExcelBtn.addEventListener('click', exportReadingGuideExcel);
        }

        // Populate account dropdown for import form
        $(document).ready(function() {
            const accountSelect = $('#importAccountSelect');
            
            // Fetch all consumer accounts
            fetch('/consumer/search?search=&zone=&status=&limit=5000')
                .then(response => response.json())
                .then(data => {
                    if (data.consumers && data.consumers.length > 0) {
                        data.consumers.forEach(consumer => {
                            const option = $('<option></option>')
                                .attr('value', consumer.account_no)
                                .text(consumer.account_no + ' - ' + (consumer.account_name || ''));
                            accountSelect.append(option);
                        });
                    }
                })
                .catch(error => console.log('Error fetching accounts:', error));

            // Client-side table search: filter consumer rows by account no or customer name
            function updateTableFilterCount(visible, total) {
                var el = $('#consumerTableSearchCount');
                if (total === 0) {
                    el.text('');
                    return;
                }
                if (visible === total) {
                    el.text('Showing all ' + total + ' consumer(s)');
                } else {
                    el.text('Showing ' + visible + ' of ' + total + ' consumer(s)');
                }
            }

            $('#consumerTableSearch').on('input', function() {
                var q = $(this).val().trim().toLowerCase();
                var $rows = $('#consumerTableBody').find('tr.consumer-row');
                var $emptyRow = $('#consumerTableEmptyRow');
                var total = $rows.length;

                if (total === 0) {
                    updateTableFilterCount(0, 0);
                    return;
                }

                if (q === '') {
                    $rows.show();
                    if ($emptyRow.length) $emptyRow.hide();
                    updateTableFilterCount(total, total);
                    return;
                }

                var visible = 0;
                $rows.each(function() {
                    var accountNo = ($(this).attr('data-account-no') || '').toString().toLowerCase();
                    var accountName = ($(this).attr('data-account-name') || '').toString().toLowerCase();
                    var match = accountNo.indexOf(q) !== -1 || accountName.indexOf(q) !== -1;
                    $(this).toggle(match);
                    if (match) visible++;
                });

                if ($emptyRow.length) $emptyRow.hide();
                updateTableFilterCount(visible, total);
            });

            // Set initial count
            var initialRows = $('#consumerTableBody').find('tr.consumer-row').length;
            updateTableFilterCount(initialRows, initialRows);

            // Add DM (single consumer): set consumer and default date when modal opens
            $('#addDmModal').on('show.bs.modal', function(event) {
                var btn = $(event.relatedTarget);
                if (btn.hasClass('add-dm-btn')) {
                    $('#addDmConsumerZoneId').val(btn.data('consumer-zone-id'));
                    $('#addDmConsumerAccount').text(btn.data('account-no') || '--');
                    $('#addDmConsumerName').text(btn.data('account-name') || '--');
                    $('#addDmDate').val('2026-02-27');
                    $('#addDmAmount').val('');
                    $('#addDmReference').attr('placeholder', 'Auto-generated (6 digits) when saved').val('');
                }
            });

            // Add DM: submit for single consumer
            $('#addDmSubmitBtn').on('click', function() {
                var consumerZoneId = $('#addDmConsumerZoneId').val();
                var date = $('#addDmDate').val();
                var amount = $('#addDmAmount').val();

                if (!consumerZoneId) {
                    Swal.fire({ icon: 'warning', title: 'Error', text: 'Consumer not set.', confirmButtonColor: '#f0ad4e' });
                    return;
                }
                if (!date) {
                    Swal.fire({ icon: 'warning', title: 'Required', text: 'Please select a date.', confirmButtonColor: '#f0ad4e' });
                    return;
                }
                var amountNum = parseFloat(amount);
                if (isNaN(amountNum) || amountNum < 0) {
                    Swal.fire({ icon: 'warning', title: 'Invalid amount', text: 'Please enter a valid amount (≥ 0).', confirmButtonColor: '#f0ad4e' });
                    return;
                }

                var amountLabel = '₱ ' + amountNum.toFixed(2);
                Swal.fire({
                    icon: 'question',
                    title: 'Confirm Add DM',
                    html: 'Add a DM of <strong>' + amountLabel + '</strong> for this consumer?',
                    showCancelButton: true,
                    confirmButtonColor: '#f0ad4e',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, add DM'
                }).then(function(result) {
                    if (!result.isConfirmed) return;

                    var submitBtn = $('#addDmSubmitBtn');
                    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Adding...');

                    $.ajax({
                        url: '{{ route("consumer-master-list.store-dm") }}',
                        method: 'POST',
                        data: {
                            _token: $('#addDmForm input[name="_token"]').val(),
                            consumer_zone_id: parseInt(consumerZoneId, 10),
                            date: date,
                            amount: amountNum
                        },
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        success: function(res) {
                            $('#addDmModal').modal('hide');
                            $('#addDmForm')[0].reset();
                            submitBtn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Add DM');
                            Swal.fire({
                                icon: 'success',
                                title: 'Done',
                                text: (res.reference ? 'Reference: ' + res.reference + '\n\n' : '') + (res.message || 'DM added successfully.'),
                                confirmButtonColor: '#3085d6'
                            }).then(function() { window.location.reload(); });
                        },
                        error: function(xhr) {
                            submitBtn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Add DM');
                            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : (xhr.statusText || 'Request failed.');
                            Swal.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonColor: '#d33' });
                        }
                    });
                });
            });

            // Upload DM (Excel): submit
            $('#importDmSubmitBtn').on('click', function() {
                var fileInput = $('#importDmFile')[0];
                if (!fileInput || !fileInput.files || !fileInput.files.length) {
                    Swal.fire({ icon: 'warning', title: 'Required', text: 'Please select an Excel file.', confirmButtonColor: '#f0ad4e' });
                    return;
                }
                var formData = new FormData();
                formData.append('_token', $('#importDmForm input[name="_token"]').val());
                formData.append('file', fileInput.files[0]);
                var btn = $('#importDmSubmitBtn');
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Importing...');
                $.ajax({
                    url: '{{ route("consumer-master-list.import-dm") }}',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    success: function(res) {
                        $('#importDmModal').modal('hide');
                        $('#importDmForm')[0].reset();
                        btn.prop('disabled', false).html('<i class="fas fa-upload mr-1"></i>Upload & Import DM');
                        var msg = res.message || 'Import completed.';
                        if (res.errors && res.errors.length) {
                            msg += '\n\n' + res.errors.slice(0, 10).join('\n');
                            if (res.errors.length > 10) msg += '\n... and ' + (res.errors.length - 10) + ' more.';
                        }
                        Swal.fire({
                            icon: res.imported > 0 ? 'success' : 'warning',
                            title: 'DM Import',
                            text: msg,
                            confirmButtonColor: '#3085d6'
                        }).then(function() { window.location.reload(); });
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('<i class="fas fa-upload mr-1"></i>Upload & Import DM');
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : (xhr.statusText || 'Request failed.');
                        Swal.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonColor: '#d33' });
                    }
                });
            });
        });
        
        // Handle flash messages with SweetAlert (only for non-AJAX requests)
        // AJAX requests handle their own alerts, so skip showing flash messages for import_success
        @if(session('success') && !session('import_success'))
            console.log('Showing success alert (non-import)');
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '{{ session('success') }}',
                confirmButtonColor: '#3085d6',
                timer: 3000,
                timerProgressBar: true
            });
        @endif

        @if(session('error'))
            console.log('Showing error alert');
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '{{ session('error') }}',
                confirmButtonColor: '#d33'
            });
        @endif

        @if(session('warning'))
            Swal.fire({
                icon: 'warning',
                title: 'Warning!',
                text: '{{ session('warning') }}',
                confirmButtonColor: '#f39c12'
            });
        @endif

        // Handle ledger import modal
        $('#ledgerImportModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var consumerAccount = button.data('consumer-account');
            var consumerName = button.data('consumer-name');
            
            var modal = $(this);
            if (consumerAccount && consumerName) {
                modal.find('#modalConsumerAccount').text(consumerAccount);
                modal.find('#modalConsumerName').text(consumerName);
                modal.find('#hiddenAccountNo').val(consumerAccount); // Set the account_no in hidden field
                modal.find('#modalConsumerInfo').show();
            } else {
                modal.find('#hiddenAccountNo').val(''); // Clear if no account
                modal.find('#modalConsumerInfo').hide();
            }
        });

        // Reset form when modal is closed
        $('#ledgerImportModal').on('hidden.bs.modal', function () {
            document.getElementById('ledgerImportModalForm').reset();
            var submitBtn = $('#ledgerImportModalForm').find('button[type="submit"]');
            submitBtn.prop('disabled', false).html('<i class="fas fa-upload mr-1"></i>Import Ledger');
        });
        
        // Handle form submission with AJAX for better error handling
        $('#ledgerImportModalForm').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            
            var form = $(this);
            var formData = new FormData(this);
            var submitBtn = form.find('button[type="submit"]');
            
            console.log('Form submitting...');
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Importing...');
            
            $.ajax({
                url: form.attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    console.log('Import successful', response);
                    $('#ledgerImportModal').modal('hide');
                    
                    // Show success alert immediately from JSON response
                    if (response && response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message || 'Ledger imported successfully!',
                            confirmButtonColor: '#3085d6',
                            timer: 3000,
                            timerProgressBar: true
                        }).then(function() {
                            // Disable all import ledger buttons after successful import
                            if (response.import_success) {
                                $('.import-ledger-btn').each(function() {
                                    if (!$(this).prop('disabled')) {
                                        $(this).prop('disabled', true)
                                            .removeClass('btn-outline-primary')
                                            .addClass('btn-secondary')
                                            .html('<i class="fas fa-check mr-1"></i>Imported');
                                    }
                                });
                            }
                            // Reload page to refresh data
                            window.location.reload();
                        });
                    } else {
                        // Fallback: just reload if no JSON response
                        window.location.reload();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Import failed', {xhr: xhr, status: status, error: error});
                    submitBtn.prop('disabled', false).html('<i class="fas fa-upload mr-1"></i>Import Ledger');
                    
                    var errorMessage = 'Import failed. ';
                    
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) {
                            errorMessage += xhr.responseJSON.message;
                        }
                        if (xhr.responseJSON.errors) {
                            errorMessage += '<br>' + JSON.stringify(xhr.responseJSON.errors);
                        }
                    } else if (xhr.responseText) {
                        // Try to extract error from HTML response
                        var responseText = xhr.responseText;
                        if (responseText.includes('error') || responseText.includes('Error')) {
                            errorMessage += 'Please check the file format and ensure all account numbers exist in the system.';
                        } else {
                            errorMessage += xhr.statusText;
                        }
                    } else {
                        errorMessage += error || xhr.statusText;
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Import Failed!',
                        html: errorMessage,
                        confirmButtonColor: '#d33'
                    });
                }
            });
            
            return false;
        });
    </script>
</body>
</html>

