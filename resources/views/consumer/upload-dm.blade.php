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
                            <i class="fas fa-file-excel mr-2"></i>Upload DM (Excel)
                        </h1>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-upload mr-2"></i>Import Debit Memo
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                Upload an Excel file with columns <strong>account_no</strong> and <strong>amount</strong>.
                                All rows use the date you select below. Reference is auto-generated (6 digits) per row.
                                <strong>Positive</strong> amounts import as <strong>DM</strong> (Debit Memo);
                                <strong>negative</strong> amounts (e.g. <code>-66.05</code>) import as <strong>CM</strong> (Credit Memo).
                            </p>

                            <form id="importDmForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="importDmDate" class="font-weight-bold">
                                            DM Date <span class="text-danger">*</span>
                                        </label>
                                        <input type="date"
                                               name="date"
                                               id="importDmDate"
                                               class="form-control"
                                               value="{{ now()->format('Y-m-d') }}"
                                               required>
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <label for="importDmFile" class="font-weight-bold">
                                            Excel File <span class="text-danger">*</span>
                                        </label>
                                        <div class="custom-file">
                                            <input type="file"
                                                   class="custom-file-input"
                                                   id="importDmFile"
                                                   name="file"
                                                   accept=".xlsx,.xls,.csv"
                                                   required>
                                            <label class="custom-file-label" for="importDmFile">
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
                                                <code>account_no</code>, <code>amount</code>
                                            </p>
                                            <small class="text-muted">
                                                Also accepted: <code>account_number</code>, <code>acct_no</code> for account;
                                                <code>debit</code>, <code>amt</code> for amount.
                                                Each <code>account_no</code> may appear only once in the same file.
                                                Positive amount → DM (debit). Negative amount → CM (credit = absolute value).
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <button type="button" class="btn btn-info btn-lg" id="importDmSubmitBtn">
                                            <i class="fas fa-upload mr-2"></i>Upload &amp; Import DM
                                        </button>
                                        <button type="reset" class="btn btn-secondary btn-lg ml-2" id="importDmResetBtn">
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
                                <li class="mb-2">Prepare an Excel file with headers: <code>account_no</code>, <code>amount</code>.</li>
                                <li class="mb-2">Select the <strong>date</strong> that will apply to every imported row.</li>
                                <li class="mb-2">
                                    Upload once —
                                    <strong>positive</strong> amounts create <strong>DM</strong> (debit increases balance);
                                    <strong>negative</strong> amounts create <strong>CM</strong> (credit decreases balance).
                                </li>
                                <li class="mb-2">Example: <code>-66.05</code> → CM with credit <code>66.05</code>, balance reduced by 66.05.</li>
                                <li class="mb-2">Rows with blank or zero amount are skipped (no error). Unknown accounts or duplicates are reported.</li>
                                <li>After import, open the consumer ledger to verify the new DM/CM entries.</li>
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
            $('#importDmFile').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').html(fileName || 'Choose file...');
            });

            $('#importDmResetBtn').on('click', function() {
                setTimeout(function() {
                    $('#importDmDate').val('{{ now()->format('Y-m-d') }}');
                    $('#importDmFile').next('.custom-file-label').html('Choose file...');
                }, 0);
            });

            $('#importDmSubmitBtn').on('click', function() {
                var fileInput = $('#importDmFile')[0];
                var date = $('#importDmDate').val();

                if (!date) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Required',
                        text: 'Please select a DM date.',
                        confirmButtonColor: '#f0ad4e'
                    });
                    return;
                }

                if (!fileInput || !fileInput.files || !fileInput.files.length) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Required',
                        text: 'Please select an Excel file.',
                        confirmButtonColor: '#f0ad4e'
                    });
                    return;
                }

                var formData = new FormData();
                formData.append('_token', $('#importDmForm input[name="_token"]').val());
                formData.append('date', date);
                formData.append('file', fileInput.files[0]);

                var btn = $('#importDmSubmitBtn');
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Importing...');

                $.ajax({
                    url: '{{ route("consumer-master-list.import-dm") }}',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    success: function(res) {
                        $('#importDmForm')[0].reset();
                        $('#importDmDate').val('{{ now()->format('Y-m-d') }}');
                        $('#importDmFile').next('.custom-file-label').html('Choose file...');
                        btn.prop('disabled', false).html('<i class="fas fa-upload mr-2"></i>Upload &amp; Import DM');

                        var msg = res.message || 'Import completed.';
                        if (res.errors && res.errors.length) {
                            msg += '\n\n' + res.errors.slice(0, 10).join('\n');
                            if (res.errors.length > 10) {
                                msg += '\n... and ' + (res.errors.length - 10) + ' more.';
                            }
                        }

                        Swal.fire({
                            icon: res.imported > 0 ? 'success' : 'warning',
                            title: 'DM Import',
                            text: msg,
                            confirmButtonColor: '#3085d6'
                        });
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('<i class="fas fa-upload mr-2"></i>Upload &amp; Import DM');
                        var msg = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : (xhr.statusText || 'Request failed.');
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: msg,
                            confirmButtonColor: '#d33'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
