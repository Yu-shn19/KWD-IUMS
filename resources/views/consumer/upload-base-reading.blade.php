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
                            <i class="fas fa-tachometer-alt mr-2"></i>Upload Base Reading
                        </h1>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-file-excel mr-2"></i>Upload Base Reading (Excel)
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

                            @if(session('upload_errors') && count(session('upload_errors')))
                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    <strong>Row errors ({{ count(session('upload_errors')) }}):</strong>
                                    <ul class="mb-0 mt-2 small">
                                        @foreach(array_slice(session('upload_errors'), 0, 20) as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                        @if(count(session('upload_errors')) > 20)
                                            <li>... and {{ count(session('upload_errors')) - 20 }} more</li>
                                        @endif
                                    </ul>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            @endif

                            <p class="text-muted mb-3">
                                Bulk-update <strong>base_reading</strong> on consumer accounts from one Excel file.
                                Same rules as Save Base Reading on the Consumer Details page: used as the starting meter value for first billing when the account is new but the meter is not at zero.
                            </p>

                            <form action="{{ route('consumer.upload-base-reading.store') }}" method="POST" enctype="multipart/form-data" id="baseReadingUploadForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="font-weight-bold mb-2">
                                            Select Excel File <span class="text-danger">*</span>
                                        </label>
                                        <div class="custom-file">
                                            <input type="file"
                                                   class="custom-file-input"
                                                   id="baseReadingFile"
                                                   name="file"
                                                   accept=".xlsx,.xls,.csv"
                                                   required>
                                            <label class="custom-file-label" for="baseReadingFile">
                                                Choose file...
                                            </label>
                                        </div>
                                        <small class="form-text text-muted mt-2">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Supported formats: .xlsx, .xls, .csv. Max 10 MB.
                                        </small>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="alert alert-info mb-0">
                                            <h6 class="font-weight-bold mb-2">
                                                <i class="fas fa-info-circle mr-2"></i>Expected Excel Columns (first row = headers):
                                            </h6>
                                            <p class="mb-2 small">
                                                <code>account_no</code>, <code>account_name</code>, <code>base_reading</code>
                                            </p>
                                            <small class="text-muted">
                                                Each <code>account_no</code> may appear only once (no duplicates).
                                                <code>account_name</code> is optional. <code>base_reading_date</code> is set to today when saved.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                            <i class="fas fa-upload mr-2"></i>Upload Base Reading
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
                                <i class="fas fa-question-circle mr-2"></i>How it works
                            </h6>
                        </div>
                        <div class="card-body">
                            <ol>
                                <li class="mb-2">Prepare an Excel file with headers: <code>account_no</code>, <code>account_name</code>, <code>base_reading</code>.</li>
                                <li class="mb-2">Upload once — every valid row updates <code>consumer_zone.base_reading</code> in bulk.</li>
                                <li class="mb-2">The value becomes the <strong>previous reading</strong> on the first Meter Reading Preparation when there is no history yet.</li>
                                <li class="mb-2">Rows for consumers that already have reading history are <strong>locked</strong> and skipped (same as Save Base Reading on Consumer Details).</li>
                                <li>After upload, open the consumer to see the updated <strong>Saved base</strong> value.</li>
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
            $('#baseReadingFile').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').html(fileName || 'Choose file...');
            });

            $('#baseReadingUploadForm').on('submit', function() {
                var fileInput = $('#baseReadingFile')[0];
                if (!fileInput.files || !fileInput.files[0]) {
                    Swal.fire({
                        icon: 'error',
                        title: 'No File Selected',
                        text: 'Please select a file to upload.'
                    });
                    return false;
                }

                var submitBtn = $('#submitBtn');
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...');
            });

            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 12000);
        });
    </script>
</body>
</html>
