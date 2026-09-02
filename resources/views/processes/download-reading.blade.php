<!DOCTYPE html> 
<html lang="en">
@include('partials.header')

<body id="page-top">
    <div id="wrapper">
        @include('partials.sidebar')
        
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('partials.navbar')
                
                <div class="container-fluid" id="container-wrapper">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4" data-tour="download-reading-header">
                        <h1 class="h3 mb-0 text-gray-800">Realtime Reading Posting</h1>
                        <button class="btn btn-sm btn-info" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt mr-1"></i>Refresh
                        </button>
                    </div>

                    <!-- Instructions Card -->
                    <div class="row mb-3">
                        <div class="col-lg-12">
                            <div class="card border-left-primary shadow">
                                <div class="card-body">
                                    
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Readers and Their Assignments -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 bg-gradient-primary d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold text-white">
                                        <i class="fas fa-users mr-2"></i>Meter Readers & Download Status
                                    </h6>
                                    <small class="text-white-50" id="statusLastUpdated">Live</small>
                                </div>
                                <div class="card-body">
                                    @if($readers->count() > 0)
                                        <div class="mb-3" data-tour="download-reader-search">
                                            <label class="small font-weight-bold">Search Meter Reader</label>
                                            <div class="input-group input-group-sm" style="max-width: 320px;">
                                                <input type="text" class="form-control" id="readerSearchName" placeholder="Search by reader name...">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary" type="button" id="readerSearchClear">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover" id="readersTable">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th class="text-center" style="width: 50px;">#</th>
                                                        <th>Meter Reader</th>
                                                        <th>Email</th>
                                                        <th class="text-center">Total Routes</th>
                                                        <th class="text-center">Pending</th>
                                                        <th class="text-center">In Progress</th>
                                                        <th class="text-center">Completed</th>
                                                        <th class="text-center">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($readers as $index => $reader)
                                                        @php
                                                            $summary = $assignmentsSummary->get($reader->id);
                                                            $totalRoutes = $summary->total_routes ?? 0;
                                                            $pending = $summary->pending ?? 0;
                                                            $inProgress = $summary->in_progress ?? 0;
                                                            $completed = $summary->completed ?? 0;
                                                        @endphp
                                                        <tr data-reader-id="{{ $reader->id }}" data-reader-name="{{ strtoupper($reader->last_name) }}, {{ strtoupper($reader->first_name) }}{{ $reader->middle_name ? ' ' . strtoupper(substr($reader->middle_name, 0, 1)) . '.' : '' }}">
                                                            <td class="text-center">{{ $index + 1 }}</td>
                                                            <td>
                                                                <strong>{{ strtoupper($reader->last_name) }}, {{ strtoupper($reader->first_name) }}</strong>
                                                                @if($reader->middle_name)
                                                                    {{ strtoupper(substr($reader->middle_name, 0, 1)) }}.
                                                                @endif
                                                            </td>
                                                            <td>{{ $reader->email }}</td>
                                                            <td class="text-center">
                                                                <span class="badge badge-secondary" data-metric="total_routes">{{ $totalRoutes }}</span>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge badge-warning" data-metric="pending">{{ $pending }}</span>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge badge-info" data-metric="in_progress">{{ $inProgress }}</span>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge badge-success" data-metric="completed">{{ $completed }}</span>
                                                            </td>
                                                            <td class="text-center">
                                                                @if($totalRoutes > 0)
                                                                    <button class="btn btn-sm btn-primary view-details-btn" data-tour="download-view-routes" 
                                                                            data-reader-id="{{ $reader->id }}"
                                                                            data-reader-name="{{ strtoupper($reader->last_name) }}, {{ strtoupper($reader->first_name) }}"
                                                                            data-reader-email="{{ $reader->email }}">
                                                                        <i class="fas fa-eye mr-1"></i>View Routes
                                                                    </button>
                                                                    
                                                                @else
                                                                    <span class="text-muted">No assignments</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="text-center py-5">
                                            <i class="fas fa-user-times fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No meter readers found. Please create reader accounts first.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- <!-- API Information Card -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card shadow mb-4 border-left-info">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-info">
                                        <i class="fas fa-code mr-2"></i>Mobile App API Information
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="font-weight-bold text-dark">Download Schedules (GET)</h6>
                                            <div class="bg-light p-3 rounded mb-3">
                                                <small class="text-muted d-block mb-1">Endpoint:</small>
                                                <code class="d-block">{{ url('/api/reader/schedules') }}</code>
                                                <small class="text-muted d-block mt-2 mb-1">Method:</small>
                                                <code>GET</code>
                                                <small class="text-muted d-block mt-2 mb-1">Headers:</small>
                                                <code class="d-block">Authorization: Bearer {token}</code>
                                                <small class="text-muted d-block mt-2">Note: Reader must login first to get token</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="font-weight-bold text-dark">Upload Reading (POST)</h6>
                                            <div class="bg-light p-3 rounded mb-3">
                                                <small class="text-muted d-block mb-1">Endpoint:</small>
                                                <code class="d-block">{{ url('/api/reader/submit-reading') }}</code>
                                                <small class="text-muted d-block mt-2 mb-1">Method:</small>
                                                <code>POST</code>
                                                <small class="text-muted d-block mt-2 mb-1">Headers:</small>
                                                <code class="d-block">Authorization: Bearer {token}</code>
                                                <small class="text-muted d-block mt-2 mb-1">Body (JSON):</small>
                                                <code class="d-block">{ "schedule_id": 123, "current_reading": 4567, "consumption": 25 }</code>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        <strong>Important:</strong> All API requests require authentication. Readers must login through the mobile app first.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> --}}

                </div>
            </div>
            
            @include('partials.footer')
        </div>
    </div>

    <!-- Scroll to top -->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- View Routes Modal -->
    <div class="modal fade" id="viewRoutesModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document" style="max-width: 95vw; width: 95%;">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-route mr-2"></i>Routes for <span id="modalReaderName"></span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="max-height: 85vh; overflow-y: auto;">
                    <div class="mb-3 row">
                        <div class="col-md-2">
                            <label class="small font-weight-bold">Bill Month</label>
                            <select class="form-control form-control-sm" id="billMonthFilter">
                                <option value="">Loading bill months...</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small font-weight-bold">Zone</label>
                            <select class="form-control form-control-sm" id="routeZoneFilter">
                                <option value="">All zones</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small font-weight-bold">Status</label>
                            <select class="form-control form-control-sm" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="Completed">Completed</option>                             
                                <option value="Assigned">Assigned</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small font-weight-bold">Account Number</label>
                            <input type="text" class="form-control form-control-sm" id="routeSearchAccount" placeholder="Search account...">
                        </div>
                        <div class="col-md-2">
                            <label class="small font-weight-bold">Name</label>
                            <input type="text" class="form-control form-control-sm" id="routeSearchName" placeholder="Search by name...">
                        </div>
                        <div class="col-md-2">
                            <label class="small font-weight-bold">&nbsp;</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="routeSearchClear">
                                <i class="fas fa-times mr-1"></i>Clear
                            </button>
                        </div>
                    </div>
                    <div id="routesContent">
                        <div class="text-center py-5">
                            <i class="fas fa-spinner fa-spin fa-3x text-primary"></i>
                            <p class="mt-3">Loading routes...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="editAllScheduleDatesBtn">
                        <i class="fas fa-calendar-alt mr-1"></i>Edit All Dates
                    </button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PIN required before editing Prev. Read / schedule -->
    <div class="modal fade" id="prevReadPinModal" tabindex="-1" role="dialog" aria-labelledby="prevReadPinModalLabel" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="prevReadPinModalLabel">
                        <i class="fas fa-lock mr-2"></i>Enter PIN
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2" id="prevReadPinHelp">Enter the edit PIN (4 characters) to update Prev. Read.</p>
                    <input type="password" class="form-control" id="prevReadPinInput" placeholder="PIN" autocomplete="off" maxlength="4" inputmode="text">
                    <div class="invalid-feedback" id="prevReadPinError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="prevReadPinVerifyBtn">
                        <i class="fas fa-check mr-1"></i>Verify
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editScheduleModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Reading Schedule</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editSchedId">
                    <div class="alert alert-light border py-2 mb-3">
                        <strong id="editSchedAccount">--</strong>
                        <span class="text-muted" id="editSchedName">--</span>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="font-weight-bold small">Assigned Reader</label>
                            <select id="editSchedReader" class="form-control form-control-sm"></select>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="font-weight-bold small">Status</label>
                            <select id="editSchedStatus" class="form-control form-control-sm">
                                <option value="Prepared">Prepared</option>
                                <option value="Assigned">Assigned</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="font-weight-bold small">SEDR No.</label>
                            <input type="number" id="editSchedSedr" class="form-control form-control-sm" min="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold small">Bill Month</label>
                            <input type="date" id="editSchedBillMonth" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold small">Bill Date</label>
                            <input type="date" id="editSchedBillDate" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold small">Due Date</label>
                            <input type="date" id="editSchedDueDate" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold small">Disconnection Date</label>
                            <input type="date" id="editSchedDiscoDate" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold small">Prev. Read Date</label>
                            <input type="date" id="editSchedPrevDate" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold small">Prev. Read</label>
                            <input type="number" id="editSchedPrev" class="form-control form-control-sm" min="0">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold small">Curr. Read</label>
                            <input type="number" id="editSchedCurrent" class="form-control form-control-sm" min="0">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold small">Consumption</label>
                            <input type="number" id="editSchedConsumption" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold small">Reading Date</label>
                            <input type="date" id="editSchedReadingDate" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold small">Current Billing</label>
                            <input type="number" id="editSchedBilling" class="form-control form-control-sm" step="0.01" min="0">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold small">Arrears</label>
                            <input type="number" id="editSchedArrears" class="form-control form-control-sm" step="0.01" min="0">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="font-weight-bold small">Penalty</label>
                            <input type="number" id="editSchedPenalty" class="form-control form-control-sm" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-row mb-0">
                        <div class="form-group col-md-4 mb-0">
                            <label class="font-weight-bold small">Meter Rental Arrears</label>
                            <input type="number" id="editSchedMrArrears" class="form-control form-control-sm" step="0.01" min="0">
                        </div>
                        <div class="form-group col-md-4 mb-0">
                            <label class="font-weight-bold small">Prior Years</label>
                            <input type="number" id="editSchedPriorYears" class="form-control form-control-sm" step="0.01" min="0">
                        </div>
                        <div class="form-group col-md-4 mb-0">
                            <label class="font-weight-bold small">Total Amount</label>
                            <input type="number" id="editSchedTotal" class="form-control form-control-sm" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="editSchedSaveBtn">
                        <i class="fas fa-save mr-1"></i>Save Schedule
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editBatchDatesModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-calendar-alt mr-2"></i>Edit All Schedule Dates</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">These dates will be applied to every schedule currently shown in the list (<span id="editBatchCount">0</span> record<span id="editBatchCountPlural">s</span>).</p>
                    <div class="form-group">
                        <label class="font-weight-bold">Bill Month</label>
                        <input type="date" id="batchBillMonth" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Bill Date</label>
                        <input type="date" id="batchBillDate" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Due Date</label>
                        <input type="date" id="batchDueDate" class="form-control">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Disconnection Date</label>
                        <input type="date" id="batchDiscoDate" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="editBatchDatesSaveBtn">
                        <i class="fas fa-save mr-1"></i>Update All
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Download API Info Modal -->
    <div class="modal fade" id="downloadApiModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-mobile-alt mr-2"></i>Download Instructions for <span id="apiModalReaderName"></span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6 class="font-weight-bold"><i class="fas fa-shield-alt mr-2"></i>Authentication Required</h6>
                        <p class="mb-0">The reader must login to the mobile app first using their credentials:</p>
                        <ul class="mb-0 mt-2">
                            <li>Email: <strong id="readerEmail"></strong></li>
                            <li>Password: <em>(Reader's password)</em></li>
                        </ul>
                    </div>

                    <h6 class="font-weight-bold mt-4">Step-by-Step Instructions:</h6>
                    <ol>
                        <li class="mb-2">Open the <strong>Water District Mobile App</strong></li>
                        <li class="mb-2"><strong>Login</strong> with email and password</li>
                        <li class="mb-2">Tap on <strong>"Read and Bill"</strong> or <strong>"Download Schedules"</strong></li>
                        <li class="mb-2">Tap the <strong>"Refresh"</strong> button to download assigned routes</li>
                        <li class="mb-2">Routes will be downloaded and stored on the device</li>
                        <li class="mb-2">Collect readings <strong>offline</strong> (no internet needed)</li>
                        <li class="mb-2">When ready, tap <strong>"Upload Readings"</strong> to submit collected data</li>
                    </ol>

                    <div class="card bg-light border mt-4">
                        <div class="card-body">
                            <h6 class="font-weight-bold">API Endpoint (for developers):</h6>
                            <code class="d-block p-2 bg-white border rounded" id="apiUrl"></code>
                            <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyApiUrl()">
                                <i class="fas fa-copy mr-1"></i>Copy URL
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const meterReaders = @json($meterReaders);

        // --- Realtime status: poll summary every 30 seconds and update badges
        function updateStatusBadges() {
            fetch('{{ route("download-reading.summary") }}')
                .then(response => response.json())
                .then(data => {
                    if (!data.success || !data.summary) return;
                    document.querySelectorAll('#readersTable tbody tr[data-reader-id]').forEach(tr => {
                        const readerId = parseInt(tr.getAttribute('data-reader-id'), 10);
                        const s = data.summary[readerId];
                        if (!s) return;
                        tr.querySelector('[data-metric="total_routes"]').textContent = s.total_routes || 0;
                        tr.querySelector('[data-metric="pending"]').textContent = s.pending || 0;
                        tr.querySelector('[data-metric="in_progress"]').textContent = s.in_progress || 0;
                        tr.querySelector('[data-metric="completed"]').textContent = s.completed || 0;
                    });
                    const el = document.getElementById('statusLastUpdated');
                    if (el) el.textContent = 'Updated ' + new Date().toLocaleTimeString();
                })
                .catch(() => {});
        }
        setInterval(updateStatusBadges, 30000);
        setTimeout(updateStatusBadges, 2000);

        // --- Reader table search: filter rows by Meter Reader name
        function applyReaderSearch() {
            const el = document.getElementById('readerSearchName');
            if (!el) return;
            const q = (el.value || '').trim().toLowerCase();
            document.querySelectorAll('#readersTable tbody tr[data-reader-id]').forEach(tr => {
                const name = (tr.getAttribute('data-reader-name') || '').toLowerCase();
                tr.style.display = !q || name.includes(q) ? '' : 'none';
            });
        }
        var readerSearchEl = document.getElementById('readerSearchName');
        var readerSearchClearEl = document.getElementById('readerSearchClear');
        if (readerSearchEl) readerSearchEl.addEventListener('input', applyReaderSearch);
        if (readerSearchClearEl) readerSearchClearEl.addEventListener('click', function() {
            document.getElementById('readerSearchName').value = '';
            applyReaderSearch();
        });

        // --- View Routes: keep full list and filter by account number / name
        let currentModalRoutes = [];
        let currentModalReaderId = null;
        let currentBillMonth = null;
        let availableBillMonths = [];
        let modalRefreshInterval = null;

        function filterRoutes(routes, accountNumber, name, status, zone) {
            const acct = (accountNumber || '').trim().toLowerCase();
            const n = (name || '').trim().toLowerCase();
            const stat = (status || '').trim();
            const z = (zone || '').trim();
            return routes.filter(r => {
                const rAcct = (r.account_number || '').toString().toLowerCase();
                const rName = (r.account_name || '').toString().toLowerCase();
                const rStatus = (r.status || '').trim();
                const rZone = (r.zone != null && r.zone !== '') ? String(r.zone).trim() : '';
                const matchAcct = !acct || rAcct.includes(acct) || rAcct.replace(/-/g, '').includes(acct.replace(/-/g, ''));
                const matchName = !n || rName.includes(n);
                const matchStatus = !stat || rStatus === stat;
                const matchZone = !z || rZone === z;
                return matchAcct && matchName && matchStatus && matchZone;
            });
        }

        function populateRouteZoneFilter(routes) {
            const sel = document.getElementById('routeZoneFilter');
            if (!sel) return;
            const zones = [...new Set(
                routes.map(r => (r.zone != null && r.zone !== '') ? String(r.zone).trim() : '')
                    .filter(Boolean)
            )].sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
            const prev = sel.value;
            sel.innerHTML = '<option value="">All zones</option>';
            zones.forEach(z => {
                const opt = document.createElement('option');
                opt.value = z;
                opt.textContent = z;
                sel.appendChild(opt);
            });
            if (prev && [...sel.options].some(o => o.value === prev)) {
                sel.value = prev;
            }
        }

        function applyRouteSearch() {
            const accountNumber = document.getElementById('routeSearchAccount').value;
            const name = document.getElementById('routeSearchName').value;
            const status = document.getElementById('statusFilter').value;
            const zoneEl = document.getElementById('routeZoneFilter');
            const zone = zoneEl ? zoneEl.value : '';
            const filtered = filterRoutes(currentModalRoutes, accountNumber, name, status, zone);
            displayRoutes(filtered);
        }

        function loadBillMonths(readerId) {
            const qs = new URLSearchParams({ reader_id: String(readerId), get_bill_months: '1' });
            fetch(`{{ route('meter-reading.assignments') }}?${qs.toString()}`, { credentials: 'same-origin' })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.bill_months && data.bill_months.length > 0) {
                        availableBillMonths = data.bill_months;
                        const select = document.getElementById('billMonthFilter');
                        select.innerHTML = '';
                        
                        // Sort by date descending (latest first)
                        const sorted = [...availableBillMonths].sort((a, b) => new Date(b.date) - new Date(a.date));
                        
                        sorted.forEach(month => {
                            const option = document.createElement('option');
                            option.value = month.date;
                            option.textContent = month.label;
                            select.appendChild(option);
                        });
                        
                        // Set latest as default
                        if (sorted.length > 0) {
                            currentBillMonth = sorted[0].date;
                            select.value = currentBillMonth;
                            loadRoutesForBillMonth(readerId, currentBillMonth);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading bill months:', error);
                    document.getElementById('billMonthFilter').innerHTML = '<option value="">Error loading bill months</option>';
                });
        }

        function loadRoutesForBillMonth(readerId, billMonth) {
            currentModalReaderId = readerId;
            currentBillMonth = billMonth;
            
            document.getElementById('routesContent').innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-3x text-primary"></i>
                    <p class="mt-3">Loading routes...</p>
                </div>
            `;

            document.getElementById('routeSearchAccount').value = '';
            document.getElementById('routeSearchName').value = '';
            const zf = document.getElementById('routeZoneFilter');
            if (zf) zf.innerHTML = '<option value="">All zones</option>';
            const st = document.getElementById('statusFilter');
            if (st) st.value = '';

            if (modalRefreshInterval) clearInterval(modalRefreshInterval);

            const qsRoutes = new URLSearchParams({
                reader_id: String(readerId),
                bill_month: String(billMonth),
            });
            fetch(`{{ route('meter-reading.assignments') }}?${qsRoutes.toString()}`, { credentials: 'same-origin' })
                .then(response => response.json())
                .then(data => {
                    const rows = Array.isArray(data.data) ? data.data : (data.data != null ? [data.data] : []);
                    if (data.success && rows.length > 0) {
                        currentModalRoutes = rows;
                        populateRouteZoneFilter(currentModalRoutes);
                        applyRouteSearch();
                        // Realtime: refresh routes in modal every 45s while open
                        modalRefreshInterval = setInterval(function() {
                            const q = new URLSearchParams({
                                reader_id: String(currentModalReaderId),
                                bill_month: String(currentBillMonth),
                            });
                            fetch(`{{ route('meter-reading.assignments') }}?${q.toString()}`, { credentials: 'same-origin' })
                                .then(r => r.json())
                                .then(d => {
                                    if (d.success && d.data != null) {
                                        currentModalRoutes = Array.isArray(d.data) ? d.data : [d.data];
                                        populateRouteZoneFilter(currentModalRoutes);
                                        applyRouteSearch();
                                    }
                                }).catch(() => {});
                        }, 45000);
                    } else {
                        currentModalRoutes = [];
                        populateRouteZoneFilter([]);
                        document.getElementById('routesContent').innerHTML = `
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted"></i>
                                <p class="mt-3 text-muted">No routes found for this bill month</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('routesContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i>Error loading routes
                        </div>
                    `;
                });
        }

        // View Routes Button
        document.querySelectorAll('.view-details-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const readerId = this.getAttribute('data-reader-id');
                const readerName = this.getAttribute('data-reader-name');
                
                document.getElementById('modalReaderName').textContent = readerName;
                
                $('#viewRoutesModal').modal('show');
                
                // Load bill months and then routes
                loadBillMonths(readerId);
            });
        });

        // Bill month filter change handler
        document.getElementById('billMonthFilter').addEventListener('change', function() {
            if (this.value && currentModalReaderId) {
                loadRoutesForBillMonth(currentModalReaderId, this.value);
            }
        });

        $('#viewRoutesModal').on('hidden.bs.modal', function() {
            if (modalRefreshInterval) {
                clearInterval(modalRefreshInterval);
                modalRefreshInterval = null;
            }
        });

        function displayRoutes(routes) {
            const sorted = [...routes].sort((a, b) => {
                const na = (a.account_name != null ? String(a.account_name) : '').trim();
                const nb = (b.account_name != null ? String(b.account_name) : '').trim();
                const byName = na.localeCompare(nb, undefined, { sensitivity: 'base' });
                if (byName !== 0) return byName;

                const aa = (a.account_number || '').toString();
                const ab = (b.account_number || '').toString();
                return aa.localeCompare(ab, undefined, { numeric: true });
            });

            let html = `
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 12px;">
                        <thead class="thead-light sticky-top">
                            <tr>
                                <th style="min-width: 30px;">#</th>
                                <th style="min-width: 80px;">Account</th>
                                <th style="min-width: 130px;">Name</th>
                                <th style="min-width: 140px;">Address</th>
                                <th style="min-width: 50px;">Zone</th>
                                <th style="min-width: 80px;">Meter No.</th>
                                <th class="text-center" style="min-width: 70px;">Prev. Read</th>
                                <th class="text-center" style="min-width: 70px;">Curr. Read</th>
                                <th class="text-center" style="min-width: 70px;">Consumption</th>
                                <th class="text-center" style="min-width: 85px;">Status</th>
                                <th class="text-center" style="min-width: 80px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            sorted.forEach((route, index) => {
                const statusClass = route.status === 'Completed' ? 'success' : 
                                  route.status === 'In Progress' ? 'warning' : 'secondary';
                
                // Calculate consumption if current reading exists
                const currentReading = route.current_reading || '-';
                const prevRead = route.previous_reading ?? 0;
                const consumption = route.consumption || (route.current_reading != null && prevRead != null ?
                                   route.current_reading - prevRead : '-');
                                  
                html += `
                    <tr>
                        <td style="font-size: 11px;">${index + 1}</td>
                        <td style="font-size: 11px;">${route.account_number || '-'}</td>
                        <td style="font-size: 11px;">${route.account_name || '-'}</td>
                        <td style="font-size: 11px;">${route.address || '-'}</td>
                        <td class="text-center" style="font-size: 11px;">${route.zone || '-'}</td>
                        <td style="font-size: 11px;">${route.meter_number || '-'}</td>
                        <td class="text-center" style="font-size: 11px;">
                            <input type="number" min="0" step="1" readonly
                                class="form-control form-control-sm prev-read-input text-center border"
                                value="${route.previous_reading ?? 0}"
                                data-schedule-id="${route.id}"
                                data-account-no="${route.account_number || ''}"
                                data-original="${route.previous_reading ?? 0}"
                                title="Click to enter PIN and edit"
                                style="max-width: 85px; display: inline-block; font-size: 11px; background-color: #fff; cursor: pointer;" />
                        </td>
                        <td class="text-center ${route.current_reading ? 'font-weight-bold text-primary' : ''}" style="font-size: 11px;">
                            ${currentReading}
                        </td>
                        <td class="text-center ${route.consumption ? 'font-weight-bold text-success' : ''}" style="font-size: 11px;">
                            ${consumption}
                        </td>
                        <td class="text-center" style="font-size: 11px;">
                            <span class="badge badge-${statusClass}">${route.status}</span>
                        </td>
                        <td class="text-center" style="font-size: 11px;">
                            <button type="button" class="btn btn-sm btn-outline-primary edit-schedule-btn mr-1"
                                data-schedule-id="${route.id || ''}"
                                title="Edit schedule">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-schedule-btn"
                                data-schedule-id="${route.id || ''}"
                                data-account-no="${escapeAttr(route.account_number || '')}"
                                data-account-name="${escapeAttr(route.account_name || '')}"
                                title="Delete schedule">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
            `;

            document.getElementById('routesContent').innerHTML = html;

            document.querySelectorAll('#routesContent .delete-schedule-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    requestPinThenDelete(this);
                });
            });

            document.querySelectorAll('#routesContent .edit-schedule-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    requestPinThenEditSchedule(this);
                });
            });

            // Attach PIN gate + save handlers to prev-read inputs
            document.querySelectorAll('#routesContent .prev-read-input').forEach(function(input) {
                input.addEventListener('mousedown', function(e) {
                    if (this.readOnly) {
                        e.preventDefault();
                        requestPinThenEdit(this);
                    }
                });
                input.addEventListener('focus', function() {
                    if (this.readOnly) {
                        this.blur();
                        requestPinThenEdit(this);
                    }
                });
                input.addEventListener('blur', function() {
                    if (this.readOnly) return;
                    savePrevRead(this);
                    // Re-lock after leaving the field so next edit requires PIN again
                    this.readOnly = true;
                    this.style.cursor = 'pointer';
                });
                input.addEventListener('keydown', function(e) {
                    if (this.readOnly) {
                        e.preventDefault();
                        return;
                    }
                    if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
                });
            });
        }

        var pendingPrevReadScheduleId = null;
        var pendingDeleteSchedule = null;
        var pendingEditSchedule = null;
        var pinModalMode = 'prev-read';

        function isoDate(value) {
            if (!value) return '';
            var match = String(value).match(/^(\d{4}-\d{2}-\d{2})/);
            return match ? match[1] : '';
        }

        function currentlyDisplayedRoutes() {
            var accountNumber = document.getElementById('routeSearchAccount').value;
            var name = document.getElementById('routeSearchName').value;
            var status = document.getElementById('statusFilter').value;
            var zoneEl = document.getElementById('routeZoneFilter');
            var zone = zoneEl ? zoneEl.value : '';
            return filterRoutes(currentModalRoutes, accountNumber, name, status, zone);
        }

        function fillReaderSelect(selectedId) {
            var sel = document.getElementById('editSchedReader');
            if (!sel) return;
            sel.innerHTML = '<option value="">(Unassigned)</option>';
            (meterReaders || []).forEach(function(reader) {
                var opt = document.createElement('option');
                opt.value = reader.id;
                opt.textContent = reader.name;
                if (String(reader.id) === String(selectedId || '')) {
                    opt.selected = true;
                }
                sel.appendChild(opt);
            });
        }

        function recalcEditConsumption() {
            var prev = parseInt(document.getElementById('editSchedPrev').value, 10);
            var currRaw = document.getElementById('editSchedCurrent').value;
            if (currRaw === '' || isNaN(prev)) {
                return;
            }
            var curr = parseInt(currRaw, 10);
            if (!isNaN(curr)) {
                document.getElementById('editSchedConsumption').value = curr - prev;
            }
        }

        function openEditScheduleModal(route) {
            if (!route || !route.id) return;
            fillReaderSelect(route.assigned_reader_id);
            document.getElementById('editSchedId').value = route.id;
            document.getElementById('editSchedAccount').textContent = route.account_number || '--';
            document.getElementById('editSchedName').textContent = route.account_name || '--';
            document.getElementById('editSchedStatus').value = route.status || 'Assigned';
            document.getElementById('editSchedSedr').value = route.sedr_number != null ? route.sedr_number : '';
            document.getElementById('editSchedBillMonth').value = isoDate(route.bill_month);
            document.getElementById('editSchedBillDate').value = isoDate(route.bill_date);
            document.getElementById('editSchedDueDate').value = isoDate(route.due_date);
            document.getElementById('editSchedDiscoDate').value = isoDate(route.disconnection_date);
            document.getElementById('editSchedPrevDate').value = isoDate(route.previous_reading_date);
            document.getElementById('editSchedPrev').value = route.previous_reading != null ? route.previous_reading : 0;
            document.getElementById('editSchedCurrent').value = route.current_reading != null ? route.current_reading : '';
            document.getElementById('editSchedConsumption').value = route.consumption != null ? route.consumption : '';
            document.getElementById('editSchedReadingDate').value = isoDate(route.reading_date);
            document.getElementById('editSchedBilling').value = route.current_billing != null ? route.current_billing : 0;
            document.getElementById('editSchedArrears').value = route.arrears != null ? route.arrears : 0;
            document.getElementById('editSchedPenalty').value = route.penalty != null ? route.penalty : 0;
            document.getElementById('editSchedMrArrears').value = route.meter_rental_arrears != null ? route.meter_rental_arrears : 0;
            document.getElementById('editSchedPriorYears').value = route.prior_years != null ? route.prior_years : 0;
            document.getElementById('editSchedTotal').value = route.total_amount != null ? route.total_amount : 0;
            $('#editScheduleModal').modal('show');
        }

        function openBatchDatesModal() {
            var routes = currentlyDisplayedRoutes();
            var first = routes[0] || {};
            document.getElementById('editBatchCount').textContent = String(routes.length);
            document.getElementById('editBatchCountPlural').textContent = routes.length === 1 ? '' : 's';
            document.getElementById('batchBillMonth').value = isoDate(first.bill_month);
            document.getElementById('batchBillDate').value = isoDate(first.bill_date);
            document.getElementById('batchDueDate').value = isoDate(first.due_date);
            document.getElementById('batchDiscoDate').value = isoDate(first.disconnection_date);
            $('#editBatchDatesModal').modal('show');
        }

        function requestPinThenEditSchedule(btn) {
            var scheduleId = parseInt(btn.getAttribute('data-schedule-id'), 10);
            var route = (currentModalRoutes || []).find(function(r) {
                return parseInt(r.id, 10) === scheduleId;
            });
            if (!route) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Schedule not found in the current list.', confirmButtonColor: '#d33' });
                return;
            }
            pinModalMode = 'edit-schedule';
            pendingPrevReadScheduleId = null;
            pendingDeleteSchedule = null;
            pendingEditSchedule = route;
            setPinModalCopy('edit-schedule');
            showPinModal();
        }

        function requestPinThenEditBatch() {
            var routes = currentlyDisplayedRoutes();
            if (!routes.length) {
                Swal.fire({ icon: 'warning', title: 'No schedules', text: 'There are no schedules in the current list to update.', confirmButtonColor: '#f0ad4e' });
                return;
            }
            pinModalMode = 'edit-batch';
            pendingPrevReadScheduleId = null;
            pendingDeleteSchedule = null;
            pendingEditSchedule = null;
            setPinModalCopy('edit-batch');
            showPinModal();
        }

        function escapeAttr(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        function resetPinModalFields() {
            var pinInput = document.getElementById('prevReadPinInput');
            var errEl = document.getElementById('prevReadPinError');
            if (pinInput) {
                pinInput.value = '';
                pinInput.classList.remove('is-invalid');
            }
            if (errEl) {
                errEl.textContent = '';
                errEl.classList.remove('d-block');
            }
        }

        function setPinModalCopy(mode) {
            var titleEl = document.getElementById('prevReadPinModalLabel');
            var helpEl = document.getElementById('prevReadPinHelp');
            if (mode === 'delete') {
                if (titleEl) titleEl.innerHTML = '<i class="fas fa-lock mr-2"></i>Enter PIN to Delete';
                if (helpEl) helpEl.textContent = 'Enter the 4-digit PIN to delete this schedule.';
            } else if (mode === 'edit-schedule') {
                if (titleEl) titleEl.innerHTML = '<i class="fas fa-lock mr-2"></i>Enter PIN to Edit Schedule';
                if (helpEl) helpEl.textContent = 'Enter the edit PIN to update this reading schedule.';
            } else if (mode === 'edit-batch') {
                if (titleEl) titleEl.innerHTML = '<i class="fas fa-lock mr-2"></i>Enter PIN to Edit All Dates';
                if (helpEl) helpEl.textContent = 'Enter the edit PIN to update dates on all schedules in the current list.';
            } else {
                if (titleEl) titleEl.innerHTML = '<i class="fas fa-lock mr-2"></i>Enter PIN';
                if (helpEl) helpEl.textContent = 'Enter the edit PIN (4 characters) to update Prev. Read.';
            }
        }

        function showPinModal() {
            resetPinModalFields();
            var pinInput = document.getElementById('prevReadPinInput');
            $('#prevReadPinModal').modal('show');
            setTimeout(function() {
                if (pinInput) pinInput.focus();
            }, 300);
        }

        function requestPinThenEdit(input) {
            pinModalMode = 'prev-read';
            pendingDeleteSchedule = null;
            pendingEditSchedule = null;
            pendingPrevReadScheduleId = input.dataset.scheduleId || null;
            setPinModalCopy('prev-read');
            showPinModal();
        }

        function requestPinThenDelete(btn) {
            pinModalMode = 'delete';
            pendingPrevReadScheduleId = null;
            pendingEditSchedule = null;
            pendingDeleteSchedule = {
                schedule_id: parseInt(btn.getAttribute('data-schedule-id'), 10),
                account_no: btn.getAttribute('data-account-no') || '',
                account_name: btn.getAttribute('data-account-name') || ''
            };
            if (!pendingDeleteSchedule.schedule_id || !pendingDeleteSchedule.account_no) {
                pendingDeleteSchedule = null;
                pinModalMode = 'prev-read';
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Missing schedule or account number.',
                    confirmButtonColor: '#d33'
                });
                return;
            }
            setPinModalCopy('delete');
            showPinModal();
        }

        function confirmAndDeleteSchedule(pending) {
            Swal.fire({
                title: 'Delete schedule?',
                text: 'Remove the schedule for ' + pending.account_no + ' (' + pending.account_name + ')?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d'
            }).then(function(result) {
                if (!result.isConfirmed) return;

                fetch('{{ route("download-reading.delete-schedule") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        schedule_id: pending.schedule_id,
                        account_no: pending.account_no
                    })
                })
                .then(function(r) {
                    return r.json().then(function(data) {
                        return { ok: r.ok, data: data };
                    }).catch(function() {
                        return { ok: false, data: null };
                    });
                })
                .then(function(result) {
                    if (result.data && result.data.success) {
                        currentModalRoutes = currentModalRoutes.filter(function(route) {
                            return parseInt(route.id, 10) !== parseInt(pending.schedule_id, 10);
                        });
                        populateRouteZoneFilter(currentModalRoutes);
                        applyRouteSearch();
                        updateStatusBadges();
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: result.data.message || 'Schedule deleted successfully.',
                            confirmButtonColor: '#3085d6'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: (result.data && result.data.message) || 'Failed to delete schedule.',
                            confirmButtonColor: '#d33'
                        });
                    }
                })
                .catch(function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to delete schedule.',
                        confirmButtonColor: '#d33'
                    });
                });
            });
        }

        function unlockPrevReadInput(scheduleId) {
            var input = document.querySelector('#routesContent .prev-read-input[data-schedule-id="' + scheduleId + '"]');
            if (!input) return;
            input.readOnly = false;
            input.style.cursor = 'text';
            input.focus();
            input.select();
        }

        document.getElementById('prevReadPinVerifyBtn').addEventListener('click', function() {
            var pinInput = document.getElementById('prevReadPinInput');
            var errEl = document.getElementById('prevReadPinError');
            var pin = (pinInput && pinInput.value) ? pinInput.value.trim() : '';
            if (!pin || pin.length < 4) {
                if (errEl) { errEl.textContent = 'Enter the 4-character PIN.'; errEl.classList.add('d-block'); }
                if (pinInput) pinInput.classList.add('is-invalid');
                return;
            }
            var btn = this;
            btn.disabled = true;
            fetch('{{ route("consumer.verify-edit-pin") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                        ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        : ''
                },
                body: JSON.stringify({ pin: pin })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                if (data.success) {
                    if (pinModalMode === 'delete' && pendingDeleteSchedule) {
                        var pending = pendingDeleteSchedule;
                        pendingDeleteSchedule = null;
                        pinModalMode = 'prev-read';
                        $('#prevReadPinModal').modal('hide');
                        confirmAndDeleteSchedule(pending);
                    } else if (pinModalMode === 'edit-schedule' && pendingEditSchedule) {
                        var routeToEdit = pendingEditSchedule;
                        pendingEditSchedule = null;
                        pinModalMode = 'prev-read';
                        $('#prevReadPinModal').modal('hide');
                        openEditScheduleModal(routeToEdit);
                    } else if (pinModalMode === 'edit-batch') {
                        pinModalMode = 'prev-read';
                        $('#prevReadPinModal').modal('hide');
                        openBatchDatesModal();
                    } else {
                        var scheduleId = pendingPrevReadScheduleId;
                        pendingPrevReadScheduleId = null;
                        $('#prevReadPinModal').modal('hide');
                        if (scheduleId) unlockPrevReadInput(scheduleId);
                    }
                } else {
                    if (errEl) { errEl.textContent = data.message || 'Invalid PIN.'; errEl.classList.add('d-block'); }
                    if (pinInput) pinInput.classList.add('is-invalid');
                }
            })
            .catch(function() {
                btn.disabled = false;
                if (errEl) { errEl.textContent = 'Request failed.'; errEl.classList.add('d-block'); }
                if (pinInput) pinInput.classList.add('is-invalid');
            });
        });

        $('#prevReadPinInput').on('keydown', function(e) {
            if (e.which === 13) document.getElementById('prevReadPinVerifyBtn').click();
        });

        // Keep PIN modal stacked above the routes modal
        $('#prevReadPinModal').on('shown.bs.modal', function() {
            $(this).css('z-index', 1060);
            $('.modal-backdrop').last().css('z-index', 1055);
        });
        $('#prevReadPinModal').on('hidden.bs.modal', function() {
            pendingPrevReadScheduleId = null;
            pendingDeleteSchedule = null;
            pendingEditSchedule = null;
            pinModalMode = 'prev-read';
            setPinModalCopy('prev-read');
        });

        function savePrevRead(input) {
            const newVal = parseInt(input.value, 10);
            const original = parseInt(input.dataset.original, 10);
            if (isNaN(newVal) || newVal < 0 || newVal === original) return;

            const scheduleId = parseInt(input.dataset.scheduleId, 10);
            const accountNo = input.dataset.accountNo;
            if (!scheduleId || !accountNo) return;

            input.disabled = true;
            input.classList.remove('border-success', 'border-danger');
            input.classList.add('border-warning');

            fetch('{{ route("consumer.update-meter-reading") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    schedule_id: scheduleId,
                    account_no: accountNo,
                    previous_reading: newVal
                })
            })
            .then(r => r.json())
            .then(data => {
                input.disabled = false;
                input.readOnly = true;
                input.style.cursor = 'pointer';
                if (data.success) {
                    input.dataset.original = String(newVal);
                    input.classList.remove('border-warning');
                    input.classList.add('border-success');
                    // Sync local cache so the 45s auto-refresh keeps the saved value
                    const match = currentModalRoutes.find(r => r.id === scheduleId);
                    if (match) match.previous_reading = newVal;
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Prev. Read successfully updated.',
                        confirmButtonColor: '#3085d6'
                    });
                    setTimeout(() => input.classList.remove('border-success'), 2000);
                } else {
                    input.value = input.dataset.original;
                    input.classList.remove('border-warning');
                    input.classList.add('border-danger');
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to save previous reading.',
                        confirmButtonColor: '#d33'
                    });
                    setTimeout(() => input.classList.remove('border-danger'), 3000);
                }
            })
            .catch(() => {
                input.disabled = false;
                input.readOnly = true;
                input.style.cursor = 'pointer';
                input.value = input.dataset.original;
                input.classList.remove('border-warning');
                input.classList.add('border-danger');
                setTimeout(() => input.classList.remove('border-danger'), 3000);
            });
        }

        // Generate Download API Info
        document.querySelectorAll('.generate-download-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const readerId = this.getAttribute('data-reader-id');
                const readerName = this.getAttribute('data-reader-name');
                const readerEmail = this.getAttribute('data-reader-email');
                
                document.getElementById('apiModalReaderName').textContent = readerName;
                document.getElementById('readerEmail').textContent = readerEmail;
                document.getElementById('apiUrl').textContent = `{{ url('/api/reader/schedules') }}`;
                
                $('#downloadApiModal').modal('show');
            });
        });

        // Route search: filter modal table by account number, name, and status
        document.getElementById('routeSearchAccount').addEventListener('input', applyRouteSearch);
        document.getElementById('routeSearchName').addEventListener('input', applyRouteSearch);
        document.getElementById('statusFilter').addEventListener('change', applyRouteSearch);
        var routeZoneFilterEl = document.getElementById('routeZoneFilter');
        if (routeZoneFilterEl) routeZoneFilterEl.addEventListener('change', applyRouteSearch);
        document.getElementById('routeSearchClear').addEventListener('click', function() {
            document.getElementById('routeSearchAccount').value = '';
            document.getElementById('routeSearchName').value = '';
            document.getElementById('statusFilter').value = '';
            if (routeZoneFilterEl) routeZoneFilterEl.value = '';
            applyRouteSearch();
        });

        function scheduleJsonHeaders() {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
                'X-HTTP-Method-Override': 'PUT'
            };
        }

        document.getElementById('editSchedPrev').addEventListener('input', recalcEditConsumption);
        document.getElementById('editSchedCurrent').addEventListener('input', recalcEditConsumption);

        document.getElementById('editAllScheduleDatesBtn').addEventListener('click', function() {
            requestPinThenEditBatch();
        });

        $('#editScheduleModal, #editBatchDatesModal').on('shown.bs.modal', function() {
            $(this).css('z-index', 1058);
            $('.modal-backdrop').last().css('z-index', 1056);
        });

        document.getElementById('editSchedSaveBtn').addEventListener('click', function() {
            var btn = this;
            var scheduleId = parseInt(document.getElementById('editSchedId').value, 10);
            var billMonth = document.getElementById('editSchedBillMonth').value;
            var billDate = document.getElementById('editSchedBillDate').value;
            var dueDate = document.getElementById('editSchedDueDate').value;
            var discoDate = document.getElementById('editSchedDiscoDate').value;
            var prev = document.getElementById('editSchedPrev').value;
            var status = document.getElementById('editSchedStatus').value;
            if (!scheduleId || !billMonth || !billDate || !dueDate || !discoDate || prev === '' || !status) {
                Swal.fire({ icon: 'warning', title: 'Required', text: 'Bill month, bill date, due date, disconnection date, previous reading, and status are required.', confirmButtonColor: '#f0ad4e' });
                return;
            }
            var currentRaw = document.getElementById('editSchedCurrent').value;
            var consumptionRaw = document.getElementById('editSchedConsumption').value;
            var readerRaw = document.getElementById('editSchedReader').value;
            var payload = {
                _method: 'PUT',
                schedule_id: scheduleId,
                assigned_reader_id: readerRaw ? parseInt(readerRaw, 10) : null,
                bill_month: billMonth,
                bill_date: billDate,
                due_date: dueDate,
                disconnection_date: discoDate,
                previous_reading_date: document.getElementById('editSchedPrevDate').value || null,
                previous_reading: parseInt(prev, 10),
                current_reading: currentRaw === '' ? null : parseInt(currentRaw, 10),
                reading_date: document.getElementById('editSchedReadingDate').value || null,
                consumption: consumptionRaw === '' ? null : parseInt(consumptionRaw, 10),
                current_billing: parseFloat(document.getElementById('editSchedBilling').value) || 0,
                arrears: parseFloat(document.getElementById('editSchedArrears').value) || 0,
                penalty: parseFloat(document.getElementById('editSchedPenalty').value) || 0,
                meter_rental_arrears: parseFloat(document.getElementById('editSchedMrArrears').value) || 0,
                prior_years: parseFloat(document.getElementById('editSchedPriorYears').value) || 0,
                total_amount: parseFloat(document.getElementById('editSchedTotal').value) || 0,
                status: status,
                sedr_number: document.getElementById('editSchedSedr').value === '' ? null : parseInt(document.getElementById('editSchedSedr').value, 10)
            };
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';
            fetch('{{ route("download-reading.update-schedule") }}', {
                method: 'POST',
                headers: scheduleJsonHeaders(),
                body: JSON.stringify(payload)
            })
            .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
            .then(function(result) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save mr-1"></i>Save Schedule';
                if (result.data && result.data.success) {
                    $('#editScheduleModal').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Updated', text: result.data.message || 'Schedule updated.', confirmButtonColor: '#3085d6' });
                    if (currentModalReaderId && currentBillMonth) {
                        loadRoutesForBillMonth(currentModalReaderId, currentBillMonth);
                    }
                    updateStatusBadges();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: (result.data && result.data.message) || 'Failed to update schedule.', confirmButtonColor: '#d33' });
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save mr-1"></i>Save Schedule';
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to update schedule.', confirmButtonColor: '#d33' });
            });
        });

        document.getElementById('editBatchDatesSaveBtn').addEventListener('click', function() {
            var btn = this;
            var routes = currentlyDisplayedRoutes();
            var ids = routes.map(function(r) { return parseInt(r.id, 10); }).filter(Boolean);
            var billMonth = document.getElementById('batchBillMonth').value;
            var billDate = document.getElementById('batchBillDate').value;
            var dueDate = document.getElementById('batchDueDate').value;
            var discoDate = document.getElementById('batchDiscoDate').value;
            if (!ids.length) {
                Swal.fire({ icon: 'warning', title: 'No schedules', text: 'There are no schedules in the current list.', confirmButtonColor: '#f0ad4e' });
                return;
            }
            if (!billMonth || !billDate || !dueDate || !discoDate) {
                Swal.fire({ icon: 'warning', title: 'Required', text: 'All four dates are required.', confirmButtonColor: '#f0ad4e' });
                return;
            }
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Updating...';
            fetch('{{ route("download-reading.update-schedules-batch") }}', {
                method: 'POST',
                headers: scheduleJsonHeaders(),
                body: JSON.stringify({
                    _method: 'PUT',
                    schedule_ids: ids,
                    bill_month: billMonth,
                    bill_date: billDate,
                    due_date: dueDate,
                    disconnection_date: discoDate
                })
            })
            .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
            .then(function(result) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save mr-1"></i>Update All';
                if (result.data && result.data.success) {
                    $('#editBatchDatesModal').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Updated', text: result.data.message || 'Schedules updated.', confirmButtonColor: '#3085d6' });
                    if (currentModalReaderId) {
                        loadBillMonths(currentModalReaderId);
                    }
                    updateStatusBadges();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: (result.data && result.data.message) || 'Failed to update schedules.', confirmButtonColor: '#d33' });
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save mr-1"></i>Update All';
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to update schedules.', confirmButtonColor: '#d33' });
            });
        });

        function copyApiUrl() {
            const url = document.getElementById('apiUrl').textContent;
            navigator.clipboard.writeText(url).then(() => {
                alert('API URL copied to clipboard!');
            }).catch(() => {
                // Fallback
                const textArea = document.createElement('textarea');
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('API URL copied to clipboard!');
            });
        }
    </script>
</body>
</html>

