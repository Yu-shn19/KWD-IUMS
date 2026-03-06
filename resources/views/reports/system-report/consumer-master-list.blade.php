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
                                                    <option value="Inactive" {{ ($filters['status'] ?? '') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                                                    <option value="Suspended" {{ ($filters['status'] ?? '') == 'Suspended' ? 'selected' : '' }}>Suspended</option>
                                                    <option value="Disconnected" {{ ($filters['status'] ?? '') == 'Disconnected' ? 'selected' : '' }}>Disconnected</option>
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
                                                <button type="button" class="btn btn-danger btn-sm" onclick="window.print()">
                                                    <i class="fas fa-file-pdf mr-1"></i>Print
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
                    <!-- Import Ledger Card -->
                    <div class="row mb-3">
                        <div class="col-lg-12">
                            <div class="card shadow-sm border-primary">
                                <div class="card-header bg-primary text-white py-2">
                                    <h6 class="mb-0"><i class="fas fa-file-upload mr-2"></i>Import Consumer Ledger</h6>
                                </div>
                                <div class="card-body py-3">
                                    <form action="{{ route('ledger.import') }}" method="POST" enctype="multipart/form-data" id="ledgerImportForm">
                                        @csrf
                                        <div class="row align-items-end">
                                            <div class="col-md-4">
                                                <label class="small font-weight-bold mb-1">Consumer Account <span class="text-danger">*</span></label>
                                                <select name="account_no" class="form-control form-control-sm" id="importAccountSelect" required>
                                                    <option value="">-- Select Account --</option>
                                                </select>
                                                <small class="text-muted">Required: Select account if Excel file has no account_no column</small>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="small font-weight-bold mb-1">Select Excel File <span class="text-danger">*</span></label>
                                                <input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.xls,.xltx,.csv" required>
                                                <small class="text-muted">.xlsx, .xls, .xltx or .csv</small>
                                            </div>
                                            <div class="col-md-4 text-right">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-upload mr-1"></i>Import Ledger
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Main Data Table -->
                    <div class="row">
                        <div class="col-lg-9">
                            <div class="card shadow-sm mb-4">
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                                        <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;">
                                            <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                                                <tr>
                                                    <th class="text-center py-2 px-2" style="min-width: 120px;">Action</th>
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
                                            <tbody>
                                                @forelse($consumers as $index => $consumer)
                                                    <tr>
                                                        <td class="text-center py-1">
                                                    @php
                                                        $hasLedgers = ($consumer->ledgers_count ?? 0) > 0;
                                                    @endphp
                                                    <button type="button" 
                                                            class="btn btn-sm import-ledger-btn {{ $hasLedgers ? 'btn-secondary' : 'btn-outline-primary' }}" 
                                                            data-toggle="modal" 
                                                            data-target="#ledgerImportModal"
                                                            data-consumer-account="{{ $consumer->account_no }}"
                                                            data-consumer-name="{{ $consumer->account_name }}"
                                                            data-account-no="{{ $consumer->account_no }}"
                                                            @if($hasLedgers) disabled @endif>
                                                        <i class="fas {{ $hasLedgers ? 'fa-check' : 'fa-file-upload' }} mr-1"></i>
                                                        {{ $hasLedgers ? 'Imported' : 'Import Ledger' }}
                                                    </button>
                                                        </td>
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
                                                            @endphp
                                                            <span class="badge badge-pill badge-{{ strtolower($statusLabel) === 'active' ? 'success' : 'secondary' }}">
                                                                {{ $statusLabel }}
                                                            </span>
                                                        </td>
                                                        <td class="text-center py-1">{{ $consumer->category_code ?? $consumer->category ?? '--' }}</td>
                                                        <td class="text-center py-1">{{ $consumer->meter_number ?? '--' }}</td>
                                                        <td class="py-1 px-2">{{ $consumer->meter_location ?? '--' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="12" class="text-center text-muted py-4">
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

    <!-- Import Ledger Modal -->
    <div class="modal fade" id="ledgerImportModal" tabindex="-1" role="dialog" aria-labelledby="ledgerImportModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="ledgerImportModalLabel">
                        <i class="fas fa-file-upload mr-2"></i>Import Consumer Ledger
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="modalConsumerInfo" class="alert alert-info mb-3" style="display: none;">
                        <strong>Consumer:</strong> <span id="modalConsumerName"></span><br>
                        <strong>Account No:</strong> <span id="modalConsumerAccount"></span>
                    </div>
                    <form action="{{ route('ledger.import') }}" method="POST" enctype="multipart/form-data" id="ledgerImportModalForm">
                        @csrf
                        <input type="hidden" name="account_no" id="hiddenAccountNo" value="">
                        <div class="form-group">
                            <label class="font-weight-bold mb-1">Select Excel File (.xlsx, .xls, .xltx)</label>
                            <input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.xls,.xltx" required>
                            <small class="text-muted">Upload ledger data from Excel file or Excel Template</small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm btn-block">
                            <i class="fas fa-upload mr-1"></i>Import Ledger
                        </button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancel
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
    
    <script>
        // Debug: Log flash messages
        console.log('Flash messages:', {
            success: @json(session('success')),
            error: @json(session('error')),
            warning: @json(session('warning')),
            import_success: @json(session('import_success'))
        });

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

