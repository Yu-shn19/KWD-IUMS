<!DOCTYPE html>
<html lang="en">
@include('partials.header')

<body id="page-top">
    <div id="wrapper">
        @include('partials.sidebar')
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <div class="container-fluid" id="container-wrapper">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-file-upload mr-2"></i>Import Consumer Master List
                        </h1>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-upload mr-2"></i>Upload Consumer Zone Excel File
                            </h6>
                        </div>
                        <div class="card-body">
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

                            @if(isset($errors) && $errors->any())
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

                            <form action="{{ route('consumer.import.store') }}" method="POST" enctype="multipart/form-data" id="consumerImportForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="font-weight-bold mb-2">
                                            Select Excel File <span class="text-danger">*</span>
                                        </label>
                                        <div class="custom-file">
                                            <input type="file"
                                                   class="custom-file-input"
                                                   id="consumerFile"
                                                   name="file"
                                                   accept=".xlsx,.xls,.xltx,.csv"
                                                   required>
                                            <label class="custom-file-label" for="consumerFile">
                                                Choose file...
                                            </label>
                                        </div>
                                        <small class="form-text text-muted mt-2">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Supported formats: .xlsx, .xls, .xltx, .csv. Large files may take a few minutes to process.
                                        </small>
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <h6 class="font-weight-bold mb-2">
                                                <i class="fas fa-info-circle mr-2"></i>Expected Excel Columns (first row = headers):
                                            </h6>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <small>
                                                        <strong>Column mapping (Excel → database):</strong><br>
                                                        <code>zone_code</code> → zone_code &nbsp;|&nbsp;
                                                        <code>last_name</code> → last_name &nbsp;|&nbsp;
                                                        <code>first_name</code> → first_name &nbsp;|&nbsp;
                                                        <code>account_number</code> → account_no<br>
                                                        <code>category_code</code> → category_code &nbsp;|&nbsp;
                                                        <code>account_name</code> → account_name &nbsp;|&nbsp;
                                                        <code>address</code> → address &nbsp;|&nbsp;
                                                        <code>status_code</code> → status_code &nbsp;|&nbsp;
                                                        <code>bill_disc_percent</code> → bill_disc_percent
                                                    </small>
                                                </div>
                                            </div>
                                            <hr class="my-2">
                                            <small class="text-muted">
                                                Every row with an <code>account_number</code> or <code>account_no</code> is saved.
                                                Only columns A–I are read (extra blank columns in the spreadsheet are ignored).
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                            <i class="fas fa-upload mr-2"></i>Import Consumer Master List
                                        </button>
                                        <button type="reset" class="btn btn-secondary btn-lg ml-2">
                                            <i class="fas fa-redo mr-2"></i>Reset
                                        </button>
                                        <a href="{{ route('consumer') }}" class="btn btn-outline-primary btn-lg ml-2">
                                            <i class="fas fa-users mr-2"></i>Go to Consumers
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-question-circle mr-2"></i>Import Instructions
                            </h6>
                        </div>
                        <div class="card-body">
                            <ol>
                                <li class="mb-2">Prepare your Excel file with the column headers shown above in the first row.</li>
                                <li class="mb-2">Use <code>account_number</code> as the unique key for each consumer (maps to <code>account_no</code> in the database).</li>
                                <li class="mb-2">If <code>account_name</code> is blank, it will be built from <code>last_name</code> and <code>first_name</code>.</li>
                                <li class="mb-2">Blank <code>status_code</code> defaults to <code>A</code> (Active).</li>
                                <li class="mb-2">If import fails with a timeout or memory error, restart the dev server with:<br>
                                    <code>php -d memory_limit=512M -d max_execution_time=300 artisan serve</code>
                                </li>
                                <li>After import, you will see a summary of new, updated, and skipped records.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            @include('partials.footer')
        </div>
    </div>

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $('#consumerFile').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').html(fileName || 'Choose file...');
            });

            $('#consumerImportForm').on('submit', function() {
                var fileInput = $('#consumerFile')[0];
                if (!fileInput.files || !fileInput.files[0]) {
                    Swal.fire({
                        icon: 'error',
                        title: 'No File Selected',
                        text: 'Please select a file to upload.'
                    });
                    return false;
                }

                var submitBtn = $('#submitBtn');
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Importing...');
            });

            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 8000);
        });
    </script>
</body>
</html>
