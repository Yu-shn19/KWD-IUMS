<!DOCTYPE html>
<html lang="en">
@include('partials.header')

<body id="page-top">
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
                    <div class="d-sm-flex align-items-center justify-content-end mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Meter Reading</h1>
                    </div>

                    <!-- Tabs -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <ul class="nav nav-tabs">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#meterReaders">Meter Readers</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link " data-toggle="tab" href="#fieldFindings">Field Findings</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#missCodes">Miss Codes</a>
                                </li>
                              
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#installPsion">Install Psion MR's</a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        

                        <!-- Meter Readers Tab -->
                        <div class="tab-pane fade show active" id="meterReaders">
                            <div class="row">
                                <div class="col-lg-9">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-header bg-primary text-white py-2 d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">
                                                <i class="fas fa-users mr-2"></i>Meter Reader Schedules
                                            </h6>
                                                <button type="button" class="btn btn-sm btn-light" data-toggle="modal" data-target="#uploadPreviousReadingModal" title="Bulk update previous reading from Excel">
                                                <i class="fas fa-file-excel mr-1"></i>Upload Previous Reading
                                            </button>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive" style="max-height: 600px; overflow: auto;">
                                                <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;">
                                                    <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                                                        <tr>
                                                            <th class="text-center py-2 px-2" style="min-width: 40px;">#</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 80px;">PDA #</th>
                                                            <th class="py-2 px-2" style="min-width: 200px;">Meter Reader</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 80px;">ZONE</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 60px;">Stat</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Download</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Upload</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($readerAssignments as $index => $assignment)
                                                        <tr>
                                                            <td class="text-center py-1">{{ $index + 1 }}</td>
                                                            <td class="text-center py-1">{{ $assignment['pda_number'] ?? '-' }}</td>
                                                            <td class="py-1 px-2">{{ $assignment['reader_name'] }}</td>
                                                            <td class="text-center py-1">{{ $assignment['zone'] }}</td>

                                                            <td class="text-center py-1">
                                                                @if($assignment['status'] == 'Assigned' || $assignment['status'] == 'Prepared')
                                                                    <span class="badge badge-danger">DN</span>
                                                                @elseif($assignment['status'] == 'Completed')
                                                                    <span class="badge badge-success">UP</span>
                                                                @elseif($assignment['status'] == 'In Progress')
                                                                    <span class="badge badge-warning">IP</span>
                                                                @else
                                                                    <span class="badge badge-secondary">-</span>
                                                                @endif
                                                            </td>
                                                            <td class="text-center py-1">
                                                                @if($assignment['status'] == 'Completed')
                                                                    <i class="fas fa-check text-success"></i>
                                                                @else
                                                                    -
                                                                @endif
                                                            </td>
                                                            <td class="text-center py-1">
                                                                @if($assignment['status'] == 'Completed')
                                                                    <i class="fas fa-check text-success"></i>
                                                                @else
                                                                    -
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        @empty
                                                        <tr>
                                                            <td colspan="7" class="text-center py-4 text-muted">
                                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                                <p class="mb-0">No meter readers assigned yet</p>
                                                                <small>Add readers and assign schedules from Billing Processes</small>
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
                                                    Total Assignments: {{ $totalAssignments ?? 0 }} | Total Readers: {{ $readers->count() }}
                                                </small>
                                                <div>
                                                    <span class="badge badge-danger mr-2">DN = Download Needed</span>
                                                    <span class="badge badge-warning mr-2">IP = In Progress</span>
                                                    <span class="badge badge-success">UP = Uploaded</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons Panel -->
                                <div class="col-lg-3">
                                    <div class="card shadow-sm border-0 mb-3">
                                        <div class="card-header bg-success text-white py-2">
                                            <h6 class="mb-0">
                                                <i class="fas fa-cog mr-2"></i>Actions
                                            </h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <button type="button" id="assignScheduleBtn" class="btn btn-success btn-block mb-2 shadow-sm" data-toggle="modal" data-target="#assignScheduleModal">
                                                <i class="fas fa-user-plus mr-2"></i>Assign to Reader
                                            </button>
                                            <button type="button" id="unassignScheduleBtn" class="btn btn-warning btn-block mb-2 shadow-sm">
                                                <i class="fas fa-user-times mr-2"></i>Unassign Reader
                                            </button>
                                            <button type="button" id="checkSchedulesBtn" class="btn btn-outline-info btn-block mb-2 shadow-sm">
                                                <i class="fas fa-search mr-2"></i>Check Schedules
                                            </button>
                                            <button type="button" id="downloadSchedulesBtn" class="btn btn-primary btn-block mb-2 shadow-sm" data-toggle="modal" data-target="#downloadModal">
                                                <i class="fas fa-download mr-2"></i>Download to Mobile
                                            </button>
                                            <button type="button" class="btn btn-info btn-block shadow-sm">
                                                <i class="fas fa-sync-alt mr-2"></i>Refresh
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Status Info Card -->
                                    <div class="card shadow-sm border-0">
                                        <div class="card-header bg-info text-white py-2">
                                            <h6 class="mb-0">
                                                <i class="fas fa-info-circle mr-2"></i>Status Info
                                            </h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <div class="mb-3">
                                                <small class="text-muted d-block mb-1">Readers Available:</small>
                                                <h5 class="mb-0 text-primary">{{ $readers->count() }}</h5>
                                            </div>
                                            <div class="mb-3">
                                                <small class="text-muted d-block mb-1">Total Assignments:</small>
                                                <h5 class="mb-0 text-success">{{ $totalAssignments ?? 0 }}</h5>
                                            </div>
                                            <div class="mb-3">
                                                <small class="text-muted d-block mb-1">Pending Download:</small>
                                                <span class="badge badge-danger">{{ collect($readerAssignments)->whereIn('status', ['Prepared', 'Assigned'])->count() }}</span>
                                            </div>
                                            <div>
                                                <small class="text-muted d-block mb-1">Completed:</small>
                                                <span class="badge badge-success">{{ collect($readerAssignments)->where('status', 'Completed')->count() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Field Findings Tab -->
                        <div class="tab-pane fade  " id="fieldFindings">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Field Findings data will be displayed here.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Miss Codes Tab -->
                        <div class="tab-pane fade" id="missCodes">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Miss Codes data will be displayed here.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Install Psion MR's Tab -->
                        <div class="tab-pane fade" id="installPsion">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Install Psion MR's data will be displayed here.
                                            </div>
                                        </div>
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

    <!-- Scroll to top -->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Assign Schedule Modal -->
    <div class="modal fade" id="assignScheduleModal" tabindex="-1" role="dialog" aria-labelledby="assignScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="assignScheduleModalLabel">
                        <i class="fas fa-user-plus mr-2"></i>Assign Schedule to Reader
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="assignScheduleForm">
                        <!-- Zone Selection -->
                        <div class="form-group">
                            <label for="assignZone" class="font-weight-bold">
                                <i class="fas fa-map-marker-alt mr-1"></i>Select Zone <span class="text-danger">*</span>
                            </label>
                            <select class="form-control" id="assignZone" style="font-size: 14px;" required>
                                <option value="">-- Select Zone --</option>
                                <option value="011">Zone 011</option>
                                <option value="021">Zone 021</option>
                                <option value="031">Zone 031</option>
                                <option value="041">Zone 041</option>
                                <option value="051">Zone 051</option>
                                <option value="061">Zone 061</option>
                                <option value="071">Zone 071</option>
                                <option value="081">Zone 081</option>
                                <option value="091">Zone 091</option>
                            </select>
                            <small id="zoneInfo" class="form-text"></small>
                        </div>

                        <!-- Bill Month -->
                        <div class="form-group">
                            <label for="assignBillMonth" class="font-weight-bold">
                                <i class="fas fa-calendar-alt mr-1"></i>Bill Month <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="assignBillMonth" required>
                        </div>

                        <!-- Reader Selection -->
                        <div class="form-group">
                            <label for="assignReader" class="font-weight-bold">
                                <i class="fas fa-user mr-1"></i>Select Meter Reader <span class="text-danger">*</span>
                            </label>
                            <select class="form-control" id="assignReader" required>
                                <option value="">-- Select Reader --</option>
                            </select>
                            <small class="form-text text-muted">Available readers with 'Reader' role</small>
                        </div>

                        <div class="alert alert-info mb-0">
                            <small>
                                <i class="fas fa-info-circle mr-1"></i>
                                All schedules in the selected zone for the specified bill month will be assigned to this reader.
                            </small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="button" id="confirmAssignBtn" class="btn btn-success">
                        <i class="fas fa-check mr-1"></i>Assign Schedules
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Unassign Schedule Modal -->
    <div class="modal fade" id="unassignScheduleModal" tabindex="-1" role="dialog" aria-labelledby="unassignScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="unassignScheduleModalLabel">
                        <i class="fas fa-user-times mr-2"></i>Unassign Schedule
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="unassignScheduleForm">
                        <!-- Zone Selection -->
                        <div class="form-group">
                            <label for="unassignZone" class="font-weight-bold">
                                <i class="fas fa-map-marker-alt mr-1"></i>Select Zone <span class="text-danger">*</span>
                            </label>
                            <select class="form-control" id="unassignZone" required>
                                <option value="">-- Select Zone --</option>
                            </select>
                        </div>

                        <!-- Bill Month -->
                        <div class="form-group">
                            <label for="unassignBillMonth" class="font-weight-bold">
                                <i class="fas fa-calendar-alt mr-1"></i>Bill Month <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="unassignBillMonth" required>
                        </div>

                        <div class="alert alert-warning mb-0">
                            <small>
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                This will remove the reader assignment from all schedules in this zone. The schedules will return to 'Prepared' status.
                            </small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="button" id="confirmUnassignBtn" class="btn btn-warning">
                        <i class="fas fa-check mr-1"></i>Unassign Schedules
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Download to Mobile Modal -->
    <div class="modal fade" id="downloadModal" tabindex="-1" role="dialog" aria-labelledby="downloadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="downloadModalLabel">
                        <i class="fas fa-mobile-alt mr-2"></i>Download Schedules to Mobile App
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-primary mb-3">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-shield-alt fa-2x mr-3"></i>
                            <div>
                                <h6 class="font-weight-bold mb-2">🔐 Authentication Required</h6>
                                <p class="mb-0">The reader MUST login to the mobile app first before downloading schedules. Only authenticated readers with valid credentials can access their assigned schedules.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Instructions:</strong> Select a reader and optionally filter by zone and bill month. The mobile app will receive all assigned schedules matching your criteria.
                    </div>

                    <form id="downloadScheduleForm">
                        <!-- Reader Selection -->
                        <div class="form-group mb-3">
                            <label for="downloadReader" class="font-weight-bold">
                                <i class="fas fa-user mr-1"></i>Select Reader <span class="text-danger">*</span>
                            </label>
                            <select class="form-control" id="downloadReader" required>
                                <option value="">-- Select Reader --</option>
                            </select>
                            <small class="form-text text-muted">Choose the meter reader who will receive the schedules</small>
                        </div>

                        <!-- Zone Selection (Optional) -->
                        <div class="form-group mb-3">
                            <label for="downloadZone" class="font-weight-bold">
                                <i class="fas fa-map-marker-alt mr-1"></i>Zone (Optional)
                            </label>
                            <select class="form-control" id="downloadZone">
                                <option value="">-- All Zones --</option>
                                <option value="011">Zone 011</option>
                                <option value="021">Zone 021</option>
                                <option value="031">Zone 031</option>
                                <option value="041">Zone 041</option>
                                <option value="051">Zone 051</option>
                                <option value="061">Zone 061</option>
                                <option value="071">Zone 071</option>
                                <option value="081">Zone 081</option>
                                <option value="091">Zone 091</option>
                            </select>
                            <small class="form-text text-muted">Leave empty to download all zones</small>
                        </div>

                        <!-- Bill Month (Optional) -->
                        <div class="form-group mb-3">
                            <label for="downloadBillMonth" class="font-weight-bold">
                                <i class="fas fa-calendar-alt mr-1"></i>Bill Month (Optional)
                            </label>
                            <input type="date" class="form-control" id="downloadBillMonth">
                            <small class="form-text text-muted">Leave empty to download all periods</small>
                        </div>

                        <!-- API Info Display -->
                        <div class="card bg-light border">
                            <div class="card-body">
                                <h6 class="font-weight-bold mb-3">
                                    <i class="fas fa-code mr-1"></i>Mobile App API Information
                                </h6>
                                <div class="mb-2">
                                    <small class="text-muted d-block">API Endpoint:</small>
                                    <code class="d-block p-2 bg-white border rounded" id="apiEndpoint">
                                        {{ url('/api/reader/schedules') }}
                                    </code>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Reader ID:</small>
                                    <code class="d-block p-2 bg-white border rounded" id="readerIdDisplay">
                                        Select a reader first
                                    </code>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Full URL:</small>
                                    <code class="d-block p-2 bg-white border rounded" style="word-break: break-all;" id="fullApiUrl">
                                        Select a reader to generate URL
                                    </code>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="button" id="copyApiUrlBtn" class="btn btn-outline-primary" disabled>
                        <i class="fas fa-copy mr-1"></i>Copy API URL
                    </button>
                    <button type="button" id="confirmDownloadBtn" class="btn btn-primary" disabled>
                        <i class="fas fa-download mr-1"></i>Generate Download Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Previous Reading Modal -->
    <div class="modal fade" id="uploadPreviousReadingModal" tabindex="-1" role="dialog" aria-labelledby="uploadPreviousReadingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="uploadPreviousReadingModalLabel">
                        <i class="fas fa-file-excel mr-2"></i>Upload Previous Reading (Excel)
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Upload an Excel file with columns: <strong>account_no</strong>, <strong>account_name</strong>, <strong>previous_reading</strong>. Each account_no may appear only once (no duplicates).</p>
                    <form id="uploadPreviousReadingForm">
                        @csrf
                        <div class="form-group">
                            <label for="previousReadingFile">Select file (xlsx, xls, csv)</label>
                            <input type="file" class="form-control-file" id="previousReadingFile" name="file" accept=".xlsx,.xls,.csv" required>
                        </div>
                    </form>
                    <div id="uploadPreviousReadingResult" class="mt-3" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" id="submitPreviousReadingUpload" class="btn btn-info">
                        <i class="fas fa-upload mr-1"></i>Upload
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // CSRF Token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Load Available Readers
        function loadAvailableReaders() {
            fetch('{{ route("meter-reading.available-readers") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('assignReader');
                        select.innerHTML = '<option value="">-- Select Reader --</option>';
                        data.data.forEach(reader => {
                            select.innerHTML += `<option value="${reader.id}">${reader.name}</option>`;
                        });
                    }
                })
                .catch(error => console.error('Error loading readers:', error));
        }

        // Load Available Zones with schedule counts
        function loadAvailableZones() {
            const billMonth = document.getElementById('assignBillMonth').value;
            if (!billMonth) return;

            fetch(`{{ route("meter-reading.available-zones") }}?bill_month=${billMonth}`)
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('assignZone');
                    const zoneInfo = document.getElementById('zoneInfo');
                    
                    if (data.success && data.data.length > 0) {
                        // Create a map of zones with schedules
                        const zonesWithSchedules = {};
                        data.data.forEach(zone => {
                            zonesWithSchedules[zone.zone] = zone.total_schedules;
                        });
                        
                        // Update the zone options with schedule info (keep all enabled)
                        const options = select.querySelectorAll('option');
                        options.forEach(option => {
                            if (option.value && zonesWithSchedules[option.value]) {
                                option.textContent = `Zone ${option.value} (✓ ${zonesWithSchedules[option.value]} schedules)`;
                                option.disabled = false;
                                option.style.fontWeight = 'bold';
                                option.style.color = '#28a745';
                            } else if (option.value) {
                                option.textContent = `Zone ${option.value}`;
                                option.disabled = false; // Keep enabled so user can select
                                option.style.fontWeight = 'normal';
                                option.style.color = '#6c757d';
                            }
                        });
                        
                        zoneInfo.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.data.length + ' zone(s) have schedules ready to assign. Other zones can still be selected if you have schedules for them.';
                        zoneInfo.className = 'form-text text-success';
                    } else {
                        // No zones with prepared schedules - but keep zones selectable
                        const options = select.querySelectorAll('option');
                        options.forEach(option => {
                            if (option.value) {
                                option.textContent = `Zone ${option.value}`;
                                option.disabled = false; // Keep all zones enabled
                                option.style.color = '';
                                option.style.fontWeight = '';
                            }
                        });
                        
                        zoneInfo.innerHTML = '<i class="fas fa-info-circle"></i> No prepared schedules found for this month. Please prepare schedules first in <strong>Billing Processes</strong> page, then return here to assign them.';
                        zoneInfo.className = 'form-text text-warning';
                    }
                })
                .catch(error => {
                    console.error('Error loading zones:', error);
                    // On error, keep zones selectable
                    const options = document.getElementById('assignZone').querySelectorAll('option');
                    options.forEach(option => {
                        if (option.value) {
                            option.disabled = false;
                            option.style.color = '';
                        }
                    });
                    document.getElementById('zoneInfo').innerHTML = '<i class="fas fa-exclamation-triangle"></i> Could not load zone availability. Zones are still selectable.';
                    document.getElementById('zoneInfo').className = 'form-text text-info';
                });
        }

        // Load zones for unassign
        function loadZonesForUnassign() {
            // Simply populate with all zones manually for unassign
            const select = document.getElementById('unassignZone');
            select.innerHTML = `
                <option value="">-- Select Zone --</option>
                <option value="011">Zone 011</option>
                <option value="021">Zone 021</option>
                <option value="031">Zone 031</option>
                <option value="041">Zone 041</option>
                <option value="051">Zone 051</option>
                <option value="061">Zone 061</option>
                <option value="071">Zone 071</option>
                <option value="081">Zone 081</option>
                <option value="091">Zone 091</option>
            `;
        }

        // When modal opens
        $('#assignScheduleModal').on('show.bs.modal', function (e) {
            // Reset zone options to original state (all enabled)
            const select = document.getElementById('assignZone');
            const options = select.querySelectorAll('option');
            options.forEach(option => {
                if (option.value) {
                    option.disabled = false;
                    option.textContent = `Zone ${option.value}`;
                }
            });
            
            loadAvailableReaders();
            // Set default bill month to current month
            const today = new Date();
            document.getElementById('assignBillMonth').value = today.toISOString().substr(0, 7) + '-01';
            loadAvailableZones();
        });

        // When bill month changes, reload zones
        document.getElementById('assignBillMonth')?.addEventListener('change', loadAvailableZones);

        // Assign Schedule Button
        document.getElementById('confirmAssignBtn')?.addEventListener('click', function() {
            const zone = document.getElementById('assignZone').value;
            const billMonth = document.getElementById('assignBillMonth').value;
            const readerId = document.getElementById('assignReader').value;

            if (!zone || !billMonth || !readerId) {
                alert('Please fill in all required fields');
                return;
            }

            const btn = this;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Assigning...';

            fetch('{{ route("meter-reading.assign") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    zone: zone,
                    bill_month: billMonth,
                    reader_id: readerId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const confirmMsg = data.message + '\n\n' +
                        '✅ Assignment Details:\n' +
                        '• Reader: ' + data.reader_name + '\n' +
                        '• Zone: ' + data.zone + '\n' +
                        '• Schedules: ' + data.assigned_count + '\n' +
                        '• Status: Stored in Database\n\n' +
                        'The page will refresh to show the updated assignments.';
                    alert(confirmMsg);
                    $('#assignScheduleModal').modal('hide');
                    
                    // Log to console for verification
                    console.log('%c✅ Assignment Stored Successfully!', 'color: green; font-weight: bold;');
                    console.log('Database Table: meter_reading_schedules');
                    console.log('Assignment Details:', data);
                    
                    location.reload(); // Refresh page to show updates
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while assigning schedules');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });

        // Unassign Button Click
        document.getElementById('unassignScheduleBtn')?.addEventListener('click', function() {
            // Load zones when opening modal
            loadZonesForUnassign();
            // Set default bill month
            const today = new Date();
            document.getElementById('unassignBillMonth').value = today.toISOString().substr(0, 7) + '-01';
            $('#unassignScheduleModal').modal('show');
        });

        // Unassign Schedule Button
        document.getElementById('confirmUnassignBtn')?.addEventListener('click', function() {
            const zone = document.getElementById('unassignZone').value;
            const billMonth = document.getElementById('unassignBillMonth').value;

            if (!zone || !billMonth) {
                alert('Please fill in all required fields');
                return;
            }

            if (!confirm('Are you sure you want to unassign all schedules in Zone ' + zone + '?')) {
                return;
            }

            const btn = this;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Unassigning...';

            fetch('{{ route("meter-reading.unassign") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    zone: zone,
                    bill_month: billMonth
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const confirmMsg = data.message + '\n\n' +
                        '✅ Unassignment Complete:\n' +
                        '• Zone: ' + zone + '\n' +
                        '• Schedules Unassigned: ' + data.unassigned_count + '\n' +
                        '• Status: Updated in Database\n\n' +
                        'The schedules have been returned to "Prepared" status.';
                    alert(confirmMsg);
                    $('#unassignScheduleModal').modal('hide');
                    
                    // Log to console for verification
                    console.log('%c✅ Unassignment Updated in Database!', 'color: orange; font-weight: bold;');
                    console.log('Database Table: meter_reading_schedules');
                    console.log('Unassignment Details:', data);
                    
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while unassigning schedules');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });

        // Check Schedules Button - Shows what's in database
        document.getElementById('checkSchedulesBtn')?.addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Checking...';

            fetch('{{ route("billing-processes.get-schedules") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSchedulesSummaryModal(data);
                        console.log('All Schedules:', data.data);
                    } else {
                        showCustomAlert('No schedules found in database', 'info');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showCustomAlert('Error checking schedules', 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        });

        // Show Schedules Summary in Professional Modal
        function showSchedulesSummaryModal(data) {
            // Group schedules by status and zone
            const byStatus = {};
            const byZone = {};
            
            data.data.forEach(schedule => {
                // By status
                if (!byStatus[schedule.status]) byStatus[schedule.status] = 0;
                byStatus[schedule.status]++;
                
                // By zone
                if (!byZone[schedule.zone]) byZone[schedule.zone] = { prepared: 0, assigned: 0, inProgress: 0, completed: 0, total: 0 };
                byZone[schedule.zone].total++;
                if (schedule.status === 'Prepared') byZone[schedule.zone].prepared++;
                if (schedule.status === 'Assigned') byZone[schedule.zone].assigned++;
                if (schedule.status === 'In Progress') byZone[schedule.zone].inProgress++;
                if (schedule.status === 'Completed') byZone[schedule.zone].completed++;
            });

            // Build modal content
            let modalContent = `
                <div class="modal fade show d-block" id="schedulesModal" tabindex="-1" role="dialog" style="background: rgba(0,0,0,0.6); z-index: 9999;">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-gradient-primary text-white">
                                <h5 class="modal-title font-weight-bold">
                                    <i class="fas fa-database mr-2"></i>Meter Reading Schedules Database
                                </h5>
                                <button type="button" class="close text-white" onclick="document.getElementById('schedulesModal').remove()">
                                    <span>&times;</span>
                                </button>
                            </div>
                            <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                                <!-- Summary Stats -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="card bg-light border-0">
                                            <div class="card-body text-center py-3">
                                                <h2 class="mb-1 text-primary font-weight-bold">${data.total}</h2>
                                                <p class="mb-0 text-muted">Total Schedules in Database</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status Breakdown -->
                                <div class="mb-4">
                                    <h6 class="font-weight-bold text-dark mb-3 pb-2 border-bottom">
                                        <i class="fas fa-chart-pie text-primary mr-2"></i>Status Breakdown
                                    </h6>
                                    <div class="row">`;
            
            // Status cards
            const statusConfig = {
                'Prepared': { icon: 'fa-clock', color: 'info', description: 'Ready to assign' },
                'Assigned': { icon: 'fa-user-check', color: 'warning', description: 'Assigned to readers' },
                'In Progress': { icon: 'fa-tasks', color: 'primary', description: 'Being collected' },
                'Completed': { icon: 'fa-check-circle', color: 'success', description: 'Finished' }
            };

            for (const [status, count] of Object.entries(byStatus)) {
                const config = statusConfig[status] || { icon: 'fa-circle', color: 'secondary', description: '' };
                modalContent += `
                    <div class="col-md-6 mb-3">
                        <div class="card border-left-${config.color} shadow-sm h-100">
                            <div class="card-body py-2">
                                <div class="d-flex align-items-center">
                                    <div class="mr-3">
                                        <i class="fas ${config.icon} fa-2x text-${config.color}"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="text-xs font-weight-bold text-${config.color} text-uppercase mb-1">${status}</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">${count}</div>
                                        <small class="text-muted">${config.description}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;
            }

            modalContent += `
                                    </div>
                                </div>

                                <!-- Zone Details -->
                                <div class="mb-3">
                                    <h6 class="font-weight-bold text-dark mb-3 pb-2 border-bottom">
                                        <i class="fas fa-map-marked-alt text-primary mr-2"></i>Zones Overview
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th class="font-weight-bold">Zone</th>
                                                    <th class="text-center font-weight-bold">Total</th>
                                                    <th class="text-center font-weight-bold">Prepared</th>
                                                    <th class="text-center font-weight-bold">Assigned</th>
                                                    <th class="text-center font-weight-bold">Progress</th>
                                                    <th class="text-center font-weight-bold">Completed</th>
                                                </tr>
                                            </thead>
                                            <tbody>`;

            const sortedZones = Object.keys(byZone).sort();
            if (sortedZones.length > 0) {
                sortedZones.forEach(zone => {
                    const zData = byZone[zone];
                    const rowClass = zData.prepared > 0 ? 'table-success' : '';
                    modalContent += `
                        <tr class="${rowClass}">
                            <td class="font-weight-bold">
                                ${zData.prepared > 0 ? '<i class="fas fa-check-circle text-success mr-1"></i>' : ''}
                                Zone ${zone}
                            </td>
                            <td class="text-center">
                                <span class="badge badge-secondary">${zData.total}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-info">${zData.prepared}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-warning">${zData.assigned}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-primary">${zData.inProgress}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-success">${zData.completed}</span>
                            </td>
                        </tr>`;
                });
            } else {
                modalContent += `
                    <tr>
                        <td colspan="6" class="text-center text-muted py-3">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                            No zones found
                        </td>
                    </tr>`;
            }

            modalContent += `
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Quick Actions Info -->
                                <div class="alert alert-light border mb-0">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-lightbulb text-warning fa-2x mr-3 mt-1"></i>
                                        <div>
                                            <strong class="d-block mb-2">Quick Actions:</strong>
                                            <ul class="mb-0 small">
                                                <li>Zones with <span class="badge badge-info">Prepared</span> status can be assigned to readers</li>
                                                <li>Use "Assign to Reader" button to assign schedules by zone</li>
                                                <li>Check browser console (F12) for detailed schedule list</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light">
                                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('schedulesModal').remove()">
                                    <i class="fas fa-times mr-1"></i>Close
                                </button>
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('schedulesModal').remove(); document.getElementById('assignScheduleBtn').click();">
                                    <i class="fas fa-user-plus mr-1"></i>Assign to Reader
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Add to body
            document.body.insertAdjacentHTML('beforeend', modalContent);

            // Auto-remove after 30 seconds
            setTimeout(() => {
                const modal = document.getElementById('schedulesModal');
                if (modal) modal.remove();
            }, 30000);
        }

        // Custom Alert Function
        function showCustomAlert(message, type) {
            const alertTypes = {
                'success': { icon: 'fa-check-circle', color: 'success' },
                'error': { icon: 'fa-exclamation-circle', color: 'danger' },
                'warning': { icon: 'fa-exclamation-triangle', color: 'warning' },
                'info': { icon: 'fa-info-circle', color: 'info' }
            };
            
            const config = alertTypes[type] || alertTypes['info'];
            
            const alert = document.createElement('div');
            alert.className = `alert alert-${config.color} alert-dismissible fade show position-fixed shadow-lg`;
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px; max-width: 400px;';
            alert.innerHTML = `
                <i class="fas ${config.icon} mr-2"></i>${message}
                <button type="button" class="close" onclick="this.parentElement.remove()">
                    <span>&times;</span>
                </button>
            `;
            
            document.body.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        // Load readers for download modal
        function loadReadersForDownload() {
            fetch('{{ route("meter-reading.available-readers") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('downloadReader');
                        select.innerHTML = '<option value="">-- Select Reader --</option>';
                        data.data.forEach(reader => {
                            select.innerHTML += `<option value="${reader.id}" data-name="${reader.name}">${reader.name}</option>`;
                        });
                    }
                })
                .catch(error => console.error('Error loading readers:', error));
        }

        // When download modal opens
        $('#downloadModal').on('show.bs.modal', function (e) {
            loadReadersForDownload();
            // Set default bill month to current month
            const today = new Date();
            document.getElementById('downloadBillMonth').value = today.toISOString().substr(0, 7) + '-01';
        });

        // Update API URL when reader changes
        document.getElementById('downloadReader')?.addEventListener('change', function() {
            const readerId = this.value;
            const readerName = this.options[this.selectedIndex]?.getAttribute('data-name');
            
            if (readerId) {
                document.getElementById('readerIdDisplay').textContent = readerId;
                
                const zone = document.getElementById('downloadZone').value;
                const billMonth = document.getElementById('downloadBillMonth').value;
                
                let url = '{{ url("/api/reader/schedules") }}?reader_id=' + readerId;
                if (zone) url += '&zone=' + zone;
                if (billMonth) url += '&bill_month=' + billMonth;
                
                document.getElementById('fullApiUrl').textContent = url;
                
                // Enable buttons
                document.getElementById('copyApiUrlBtn').disabled = false;
                document.getElementById('confirmDownloadBtn').disabled = false;
            } else {
                document.getElementById('readerIdDisplay').textContent = 'Select a reader first';
                document.getElementById('fullApiUrl').textContent = 'Select a reader to generate URL';
                document.getElementById('copyApiUrlBtn').disabled = true;
                document.getElementById('confirmDownloadBtn').disabled = true;
            }
        });

        // Update URL when zone or bill month changes
        document.getElementById('downloadZone')?.addEventListener('change', function() {
            document.getElementById('downloadReader').dispatchEvent(new Event('change'));
        });
        
        document.getElementById('downloadBillMonth')?.addEventListener('change', function() {
            document.getElementById('downloadReader').dispatchEvent(new Event('change'));
        });

        // Copy API URL Button
        document.getElementById('copyApiUrlBtn')?.addEventListener('click', function() {
            const url = document.getElementById('fullApiUrl').textContent;
            
            navigator.clipboard.writeText(url).then(() => {
                showCustomAlert('API URL copied to clipboard!', 'success');
            }).catch(() => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showCustomAlert('API URL copied to clipboard!', 'success');
            });
        });

        // Generate Download Data Button
        document.getElementById('confirmDownloadBtn')?.addEventListener('click', function() {
            const readerId = document.getElementById('downloadReader').value;
            const zone = document.getElementById('downloadZone').value;
            const billMonth = document.getElementById('downloadBillMonth').value;

            if (!readerId) {
                alert('Please select a reader');
                return;
            }

            const btn = this;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Generating...';

            let url = '{{ route("meter-reading.download") }}?reader_id=' + readerId;
            if (zone) url += '&zone=' + zone;
            if (billMonth) url += '&bill_month=' + billMonth;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showDownloadResultModal(data.data);
                        $('#downloadModal').modal('hide');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while generating download data');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        });

        // Show Download Result Modal
        function showDownloadResultModal(data) {
            const readerName = data.reader_info ? data.reader_info.name : 'Unknown';
            const totalSchedules = data.download_info.total_schedules;
            const zones = data.download_info.zones.join(', ');
            const billMonth = data.download_info.bill_month;

            let modalContent = `
                <div class="modal fade show d-block" id="downloadResultModal" tabindex="-1" role="dialog" style="background: rgba(0,0,0,0.6); z-index: 9999;">
                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title font-weight-bold">
                                    <i class="fas fa-check-circle mr-2"></i>Download Package Ready
                                </h5>
                                <button type="button" class="close text-white" onclick="document.getElementById('downloadResultModal').remove()">
                                    <span>&times;</span>
                                </button>
                            </div>
                            <div class="modal-body p-4">
                                <!-- Success Message -->
                                <div class="alert alert-success border-success">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-check-circle fa-3x mr-3"></i>
                                        <div>
                                            <h5 class="mb-1">Download Package Generated Successfully!</h5>
                                            <p class="mb-0">The schedules are now ready to be downloaded by the mobile app.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Download Info -->
                                <div class="card border mb-4">
                                    <div class="card-body">
                                        <h6 class="font-weight-bold mb-3">
                                            <i class="fas fa-info-circle text-primary mr-1"></i>Download Information
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p class="mb-2"><strong>Reader:</strong> ${readerName}</p>
                                                <p class="mb-2"><strong>Total Schedules:</strong> <span class="badge badge-success">${totalSchedules}</span></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-2"><strong>Zones:</strong> ${zones}</p>
                                                <p class="mb-2"><strong>Bill Month:</strong> ${billMonth}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- API Instructions -->
                                <div class="card bg-light border">
                                    <div class="card-body">
                                        <h6 class="font-weight-bold mb-3">
                                            <i class="fas fa-mobile-alt text-primary mr-1"></i>Mobile App Instructions
                                        </h6>
                                        <ol class="mb-3">
                                            <li class="mb-2">
                                                <strong>Open the Mobile App</strong> on the reader's device
                                            </li>
                                            <li class="mb-2">
                                                <strong>🔐 Login Required:</strong> Reader must login with credentials:
                                                <div class="bg-white p-2 mt-1 rounded border">
                                                    <small class="text-muted">Email:</small> <code>${data.reader_info ? data.reader_info.email : 'N/A'}</code><br>
                                                    <small class="text-muted">Password:</small> <code>(Reader's password)</code>
                                                </div>
                                            </li>
                                            <li class="mb-2">
                                                After login, click <strong>"Download Schedules"</strong> button
                                            </li>
                                            <li class="mb-0">
                                                The app will automatically fetch <strong>${totalSchedules} schedule(s)</strong> from the server
                                            </li>
                                        </ol>

                                        <div class="alert alert-warning mb-2">
                                            <small>
                                                <i class="fas fa-wifi mr-1"></i>
                                                <strong>Internet Required:</strong> Mobile device must have internet connection to login and download schedules.
                                            </small>
                                        </div>
                                        
                                        <div class="alert alert-success mb-0">
                                            <small>
                                                <i class="fas fa-shield-alt mr-1"></i>
                                                <strong>Secure:</strong> Authentication token expires after 24 hours. Reader will need to login again if token expires.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- JSON Preview (Collapsible) -->
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#jsonPreview">
                                        <i class="fas fa-code mr-1"></i>Show JSON Data
                                    </button>
                                    <div class="collapse mt-2" id="jsonPreview">
                                        <pre class="bg-dark text-light p-3 rounded" style="max-height: 300px; overflow-y: auto;"><code>${JSON.stringify(data, null, 2)}</code></pre>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light">
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('downloadResultModal').remove()">
                                    <i class="fas fa-times mr-1"></i>Close
                                </button>
                                <button type="button" class="btn btn-success" onclick="window.open('{{ url("/api/reader/schedules?reader_id=") }}' + ${data.reader_info.id}, '_blank')">
                                    <i class="fas fa-external-link-alt mr-1"></i>Open API in Browser
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalContent);
            
            console.log('Download Package Data:', data);
        }

        // Refresh button
        document.querySelector('.btn-info.btn-block')?.addEventListener('click', function() {
            location.reload();
        });

        // Upload Previous Reading: submit file via AJAX
        document.getElementById('submitPreviousReadingUpload')?.addEventListener('click', function() {
            const fileInput = document.getElementById('previousReadingFile');
            const resultEl = document.getElementById('uploadPreviousReadingResult');
            if (!fileInput || !fileInput.files.length) {
                alert('Please select a file.');
                return;
            }
            resultEl.style.display = 'block';
            resultEl.innerHTML = '<div class="text-center py-2"><i class="fas fa-spinner fa-spin"></i> Uploading...</div>';
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('_token', csrfToken);

            fetch('{{ route("meter-reading.upload-previous-reading") }}', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                let html = '';
                if (data.success) {
                    html = `<div class="alert alert-success"><i class="fas fa-check-circle mr-2"></i>${data.message}<br><strong>Updated:</strong> ${data.imported} | <strong>Failed:</strong> ${data.failed}</div>`;
                    if (data.errors && data.errors.length) {
                        html += '<div class="small text-left mt-2"><strong>Errors:</strong><ul class="mb-0 pl-3">';
                        data.errors.slice(0, 15).forEach(e => { html += `<li>${e}</li>`; });
                        if (data.errors.length > 15) html += `<li>... and ${data.errors.length - 15} more</li>`;
                        html += '</ul></div>';
                    }
                    fileInput.value = '';
                } else {
                    html = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>${data.message}</div>`;
                    if (data.errors && data.errors.length) {
                        html += '<ul class="mb-0 pl-3 small">';
                        data.errors.slice(0, 15).forEach(e => { html += `<li>${e}</li>`; });
                        if (data.errors.length > 15) html += `<li>... and ${data.errors.length - 15} more</li>`;
                        html += '</ul>';
                    }
                }
                resultEl.innerHTML = html;
            })
            .catch(err => {
                resultEl.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>Request failed: ${err.message}</div>`;
            });
        });
        $('#uploadPreviousReadingModal').on('hidden.bs.modal', function() {
            document.getElementById('uploadPreviousReadingResult').style.display = 'none';
            document.getElementById('uploadPreviousReadingResult').innerHTML = '';
        });

        // Show database storage status on page load
        console.log('%c✅ Meter Reading Assignment System Active', 'color: green; font-weight: bold; font-size: 14px;');
        console.log('Assignments are stored in: meter_reading_schedules table');
        console.log('Fields stored: assigned_reader_id, status, assigned_at, zone, bill_month');
        console.log('');
        console.log('%c📱 Mobile App API Ready', 'color: blue; font-weight: bold;');
        console.log('API Base URL: {{ url("/api/reader") }}');
        console.log('Endpoints:');
        console.log('  - POST /api/reader/login');
        console.log('  - GET  /api/reader/schedules?reader_id={id}');
        console.log('  - POST /api/reader/submit-reading');
        console.log('  - POST /api/reader/update-status');
        console.log('  - GET  /api/reader/stats?reader_id={id}');
        console.log('');
        console.log('Current page shows: {{ $totalAssignments ?? 0 }} assignment(s)');
        console.log('Total readers available: {{ $readers->count() }}');
    </script>
</body>
</html>

