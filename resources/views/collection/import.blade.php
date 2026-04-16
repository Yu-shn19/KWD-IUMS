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
                <!-- Container Fluid-->
                <div class="container-fluid" id="container-wrapper">
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-file-upload mr-2"></i>Import Collection Data
                        </h1>
                    </div>

                    <!-- Import Form Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-upload mr-2"></i>Upload Collection Excel File
                            </h6>
                        </div>
                        <div class="card-body">
                            <!-- Success/Error Messages -->
                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            @endif

                            @if(session('warning'))
                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('warning') }}
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            @endif

                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-times-circle mr-2"></i>{{ session('error') }}
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            @endif

                            @if($errors->any())
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle mr-2"></i>
                                    <strong>Validation Errors:</strong>
                                    <ul class="mb-0 mt-2">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            @endif

                            <!-- Import Form -->
                            <form action="{{ route('collection.import') }}" method="POST" enctype="multipart/form-data" id="collectionImportForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="font-weight-bold mb-2">
                                            Select Excel File <span class="text-danger">*</span>
                                        </label>
                                        <div class="custom-file">
                                            <input type="file" 
                                                   class="custom-file-input" 
                                                   id="collectionFile" 
                                                   name="file" 
                                                   accept=".xlsx,.xls,.xltx,.csv" 
                                                   required>
                                            <label class="custom-file-label" for="collectionFile">
                                                Choose file...
                                            </label>
                                        </div>
                                        <small class="form-text text-muted mt-2">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Supported formats: .xlsx, .xls, .xltx, .csv
                                        </small>
                                    </div>
                                </div>

                                <!-- Expected Columns Info -->
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <h6 class="font-weight-bold mb-2">
                                                <i class="fas fa-info-circle mr-2"></i>Expected Excel Columns:
                                            </h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <small>
                                                        <strong>Required Fields:</strong><br>
                                                        zone_code, sequence, account_no, account_name, coll_date, coll_time,<br>
                                                        pay_mode, or_number, pay_amount, current_bill, meter_rental,<br>
                                                        arrears, penalty, materials, others, advances, sc_discount,<br>
                                                        fees_charges, materials_loan, prev_yr, cancel, username
                                                    </small>
                                                </div>
                                                <div class="col-md-6">
                                                    <small>
                                                        <strong>Optional Fields:</strong><br>
                                                        sund1_amt, sund2_amt, sund3_amt, sund4_amt, sund5_amt,<br>
                                                        sund1_code, sund2_code, sund3_code, sund4_code, sund5_code
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                            <i class="fas fa-upload mr-2"></i>Import Collection Data
                                        </button>
                                        <button type="reset" class="btn btn-secondary btn-lg ml-2">
                                            <i class="fas fa-redo mr-2"></i>Reset
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Sync to Ledger Section -->
                            <div class="row mt-5">
                                <div class="col-md-12">
                                    <hr>
                                    <h6 class="font-weight-bold mb-3">
                                        <i class="fas fa-sync-alt mr-2"></i>Sync Collection to Ledger
                                    </h6>
                                    <p class="text-muted mb-3">
                                        This will create PAYMENT transactions in Consumer Ledger for all collection records.
                                        Payments are matched by account_no and date (coll_date = DATE(txtime)).
                                    </p>
                                    <form action="{{ route('collection.sync-to-ledger') }}" method="POST" id="syncToLedgerForm">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-lg" id="syncBtn">
                                            <i class="fas fa-sync-alt mr-2"></i>Sync Collection Payments to Ledger
                                        </button>
                                    </form>
                                    <div class="mt-3">
                                        <small class="text-muted d-block mb-2">
                                            Use the button below if you only want to backfill Senior Citizen discounts (SC)
                                            into the consumer ledger without touching existing payments.
                                        </small>
                                        <form action="{{ route('collection.sync-sc-discounts') }}" method="POST" id="syncScForm">
                                            @csrf
                                            <button type="submit" class="btn btn-info btn-lg" id="syncScBtn">
                                                <i class="fas fa-user-check mr-2"></i>Sync SC Discounts Only
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Generate Penalties Section -->
                            <div class="row mt-5">
                                <div class="col-md-12">
                                    <hr>
                                    <h6 class="font-weight-bold mb-3">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>Generate Penalties for Late Payments
                                    </h6>
                                    <div class="alert alert-warning">
                                        <strong>Note:</strong> This will generate penalties for bills with due dates in December 2025 and future periods.
                                        <ul class="mb-2 mt-2">
                                            <li>Finds bills in consumer_ledgers table by checking the <code>due_date</code> field</li>
                                            <li>Due date is typically 15 days after the bill date</li>
                                            <li>Checks if collection payment date (coll_date) exceeds the due_date</li>
                                            <li>Uses the <code>penalty</code> amount from the collection table</li>
                                        </ul>
                                        <small>Penalty amount comes from the collection data, not calculated.</small>
                                    </div>
                                    <form action="{{ route('collection.generate-penalties') }}" method="POST" id="generatePenaltiesForm">
                                        @csrf
                                        <button type="submit" class="btn btn-warning btn-lg" id="penaltyBtn">
                                            <i class="fas fa-exclamation-triangle mr-2"></i>Generate Penalties for Late Payments
                                        </button>
                                    </form>
                                </div>
                            </div>
                            </form>
                        </div>
                    </div>

                    <!-- Instructions Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-question-circle mr-2"></i>Import Instructions
                            </h6>
                        </div>
                        <div class="card-body">
                            <ol>
                                <li class="mb-2">Prepare your Excel file with the required columns listed above.</li>
                                <li class="mb-2">Ensure the first row contains column headers (case-insensitive).</li>
                                <li class="mb-2">Date formats should be in formats like: <code>YYYY-MM-DD</code>, <code>DD-MMM-YY</code>, or <code>DD-MMM-YYYY</code>.</li>
                                <li class="mb-2">Time formats should be in <code>HH:MM:SS</code> or <code>HH:MM</code> format.</li>
                                <li class="mb-2">Numeric fields (amounts) should contain valid numbers.</li>
                                <li class="mb-2">Click "Import Collection Data" to upload and process your file.</li>
                                <li class="mb-2">The system will process the file in batches of 500 rows for better performance.</li>
                                <li>After import, you'll see a summary of imported and skipped records.</li>
                            </ol>
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

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        $(document).ready(function() {
            // Update custom file input label
            $('#collectionFile').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').html(fileName || 'Choose file...');
            });

            // Form submission with loading state
            $('#collectionImportForm').on('submit', function(e) {
                var fileInput = $('#collectionFile')[0];
                if (!fileInput.files || !fileInput.files[0]) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'No File Selected',
                        text: 'Please select a file to upload.'
                    });
                    return false;
                }

                // Show loading state
                var submitBtn = $('#submitBtn');
                var originalText = submitBtn.html();
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Importing...');

                // If form submission fails, restore button
                setTimeout(function() {
                    submitBtn.prop('disabled', false).html(originalText);
                }, 30000); // Restore after 30 seconds as fallback
            });

            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);

            // Sync to ledger form submission
            $('#syncToLedgerForm').on('submit', function(e) {
                e.preventDefault();
                
                var form = $(this);
                var syncBtn = $('#syncBtn');
                var originalText = syncBtn.html();
                
                Swal.fire({
                    title: 'Sync Collection to Ledger?',
                    text: 'This will create PAYMENT transactions in Consumer Ledger for all collection records. This may take a while.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, sync now',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        syncBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Syncing...');
                        
                        // Submit form via AJAX
                        $.ajax({
                            url: form.attr('action'),
                            method: 'POST',
                            data: form.serialize(),
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Sync Completed!',
                                        html: `
                                            <p>${response.message}</p>
                                            <p><strong>Synced:</strong> ${response.synced || 0} payment(s)</p>
                                            <p><strong>Skipped:</strong> ${response.skipped || 0} record(s)</p>
                                            ${response.errors && response.errors.length > 0 ? 
                                                '<p class="text-danger"><strong>Errors:</strong> ' + response.errors.length + '</p>' : 
                                                ''}
                                        `,
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Sync Failed',
                                        text: response.message || 'An error occurred during sync'
                                    });
                                    syncBtn.prop('disabled', false).html(originalText);
                                }
                            },
                            error: function(xhr) {
                                var errorMsg = 'An error occurred during sync';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMsg = xhr.responseJSON.message;
                                }
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Sync Failed',
                                    text: errorMsg
                                });
                                syncBtn.prop('disabled', false).html(originalText);
                            }
                        });
                    }
                });
            });

            // Sync SC discounts only form submission
            $('#syncScForm').on('submit', function(e) {
                e.preventDefault();
                
                var form = $(this);
                var syncScBtn = $('#syncScBtn');
                var originalText = syncScBtn.html();
                
                Swal.fire({
                    title: 'Sync SC Discounts Only?',
                    text: 'This will create SC discount PAYMENT entries (-SC) in Consumer Ledger for all collection records with sc_discount, without touching existing payments. This may take a while.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#17a2b8',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, sync SC discounts',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        syncScBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Syncing SC Discounts...');
                        
                        // Submit form via AJAX
                        $.ajax({
                            url: form.attr('action'),
                            method: 'POST',
                            data: form.serialize(),
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'SC Discount Sync Completed!',
                                        html: `
                                            <p>${response.message}</p>
                                            <p><strong>Synced:</strong> ${response.synced || 0} SC discount payment(s)</p>
                                            <p><strong>Skipped:</strong> ${response.skipped || 0} record(s)</p>
                                            ${response.errors && response.errors.length > 0 ? 
                                                '<p class="text-danger"><strong>Errors:</strong> ' + response.errors.length + '</p>' : 
                                                ''}
                                        `,
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'SC Discount Sync Failed',
                                        text: response.message || 'An error occurred during SC discount sync'
                                    });
                                    syncScBtn.prop('disabled', false).html(originalText);
                                }
                            },
                            error: function(xhr) {
                                var errorMsg = 'An error occurred during SC discount sync';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMsg = xhr.responseJSON.message;
                                }
                                Swal.fire({
                                    icon: 'error',
                                    title: 'SC Discount Sync Failed',
                                    text: errorMsg
                                });
                                syncScBtn.prop('disabled', false).html(originalText);
                            }
                        });
                    }
                });
            });

            // Generate penalties form submission
            $('#generatePenaltiesForm').on('submit', function(e) {
                e.preventDefault();
                
                var form = $(this);
                var penaltyBtn = $('#penaltyBtn');
                var originalText = penaltyBtn.html();
                
                Swal.fire({
                    title: 'Generate Penalties?',
                    text: 'This will generate penalties for bills with due date 12/03/2025 where payments were made after the due date. This may take a while.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, generate penalties',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        penaltyBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Generating Penalties...');
                        
                        // Submit form via AJAX
                        $.ajax({
                            url: form.attr('action'),
                            method: 'POST',
                            data: form.serialize(),
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Penalties Generated!',
                                        html: `
                                            <p>${response.message}</p>
                                            <p><strong>Generated:</strong> ${response.generated || 0} penalty(ies)</p>
                                            <p><strong>Skipped:</strong> ${response.skipped || 0} record(s)</p>
                                            ${response.errors && response.errors.length > 0 ? 
                                                '<p class="text-danger"><strong>Errors:</strong> ' + response.errors.length + '</p>' : 
                                                ''}
                                        `,
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Penalty Generation Failed',
                                        text: response.message || 'An error occurred during penalty generation'
                                    });
                                    penaltyBtn.prop('disabled', false).html(originalText);
                                }
                            },
                            error: function(xhr) {
                                var errorMsg = 'An error occurred during penalty generation';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMsg = xhr.responseJSON.message;
                                }
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Penalty Generation Failed',
                                    text: errorMsg
                                });
                                penaltyBtn.prop('disabled', false).html(originalText);
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
