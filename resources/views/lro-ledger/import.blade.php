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
                            <i class="fas fa-file-upload mr-2"></i>Import LRO Ledger Data
                        </h1>
                    </div>

                    <!-- Import Form Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-upload mr-2"></i>Upload LRO Ledger Excel File
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
                            <form action="{{ route('lro-ledger.import') }}" method="POST" enctype="multipart/form-data" id="lroLedgerImportForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="font-weight-bold mb-2">
                                            Select Excel File <span class="text-danger">*</span>
                                        </label>
                                        <div class="custom-file">
                                            <input type="file" 
                                                   class="custom-file-input" 
                                                   id="lroLedgerFile" 
                                                   name="file" 
                                                   accept=".xlsx,.xls,.xltx,.csv" 
                                                   required>
                                            <label class="custom-file-label" for="lroLedgerFile">
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
                                                        account_no, account_name<br><br>
                                                        <strong>Date Fields:</strong><br>
                                                        billmonth, cba_date<br><br>
                                                        <strong>Other Fields:</strong><br>
                                                        cba_type, cba_no, zone_code, cba_remarks,<br>
                                                        ar_dm, ar_cm, lro_dm, lro_cm, cba_amount, acct_group
                                                    </small>
                                                </div>
                                                <div class="col-md-6">
                                                    <small>
                                                        <strong>Note:</strong><br>
                                                        The system will automatically link LRO Ledger entries to the Consumer Zone table using <code>account_no</code> and <code>account_name</code>.<br><br>
                                                        If a matching consumer is found, the <code>consumer_zone_id</code> will be set automatically.
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
                                            <i class="fas fa-upload mr-2"></i>Import LRO Ledger Data
                                        </button>
                                        <button type="reset" class="btn btn-secondary btn-lg ml-2">
                                            <i class="fas fa-redo mr-2"></i>Reset
                                        </button>
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
                                <li class="mb-2">Numeric fields (amounts) should contain valid numbers.</li>
                                <li class="mb-2">The <code>account_no</code> field is required and will be used to link to Consumer Zone records.</li>
                                <li class="mb-2">Click "Import LRO Ledger Data" to upload and process your file.</li>
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
            $('#lroLedgerFile').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').html(fileName || 'Choose file...');
            });

            // Form submission with loading state
            $('#lroLedgerImportForm').on('submit', function(e) {
                var fileInput = $('#lroLedgerFile')[0];
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
        });
    </script>
</body>
</html>
