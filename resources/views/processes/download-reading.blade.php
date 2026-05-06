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
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
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
                                        <div class="mb-3">
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
                                                                    <button class="btn btn-sm btn-primary view-details-btn" 
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
                        <div class="col-md-3">
                            <label class="small font-weight-bold">Name</label>
                            <input type="text" class="form-control form-control-sm" id="routeSearchName" placeholder="Search by name...">
                        </div>
                        <div class="col-md-1">
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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

        function filterRoutes(routes, accountNumber, name, status) {
            const acct = (accountNumber || '').trim().toLowerCase();
            const n = (name || '').trim().toLowerCase();
            const stat = (status || '').trim();
            return routes.filter(r => {
                const rAcct = (r.account_number || '').toString().toLowerCase();
                const rName = (r.account_name || '').toString().toLowerCase();
                const rStatus = (r.status || '').trim();
                const matchAcct = !acct || rAcct.includes(acct) || rAcct.replace(/-/g, '').includes(acct.replace(/-/g, ''));
                const matchName = !n || rName.includes(n);
                const matchStatus = !stat || rStatus === stat;
                return matchAcct && matchName && matchStatus;
            });
        }

        function applyRouteSearch() {
            const accountNumber = document.getElementById('routeSearchAccount').value;
            const name = document.getElementById('routeSearchName').value;
            const status = document.getElementById('statusFilter').value;
            const filtered = filterRoutes(currentModalRoutes, accountNumber, name, status);
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
                        displayRoutes(currentModalRoutes);
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
                                        applyRouteSearch();
                                    }
                                }).catch(() => {});
                        }, 45000);
                    } else {
                        currentModalRoutes = [];
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
                            </tr>
                        </thead>
                        <tbody>
            `;

            routes.forEach((route, index) => {
                const statusClass = route.status === 'Completed' ? 'success' : 
                                  route.status === 'In Progress' ? 'warning' : 'secondary';
                
                // Calculate consumption if current reading exists
                const currentReading = route.current_reading || '-';
                const consumption = route.consumption || (route.current_reading && route.previous_reading ? 
                                   route.current_reading - route.previous_reading : '-');
                                  
                html += `
                    <tr>
                        <td style="font-size: 11px;">${index + 1}</td>
                        <td style="font-size: 11px;">${route.account_number || '-'}</td>
                        <td style="font-size: 11px;">${route.account_name || '-'}</td>
                        <td style="font-size: 11px;">${route.address || '-'}</td>
                        <td class="text-center" style="font-size: 11px;">${route.zone || '-'}</td>
                        <td style="font-size: 11px;">${route.meter_number || '-'}</td>
                        <td class="text-center" style="font-size: 11px;">${route.previous_reading || '0'}</td>
                        <td class="text-center ${route.current_reading ? 'font-weight-bold text-primary' : ''}" style="font-size: 11px;">
                            ${currentReading}
                        </td>
                        <td class="text-center ${route.consumption ? 'font-weight-bold text-success' : ''}" style="font-size: 11px;">
                            ${consumption}
                        </td>
                        <td class="text-center" style="font-size: 11px;">
                            <span class="badge badge-${statusClass}">${route.status}</span>
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
        document.getElementById('routeSearchClear').addEventListener('click', function() {
            document.getElementById('routeSearchAccount').value = '';
            document.getElementById('routeSearchName').value = '';
            document.getElementById('statusFilter').value = '';
            applyRouteSearch();
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

