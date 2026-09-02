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
                                Upload the opening-balance Excel with headers
                                <strong>ZONE</strong>, <strong>ACCOUNT NAME</strong> (or <strong>LAST NAME</strong> and <strong>FIRST NAME</strong>), <strong>PY</strong>,
                                <strong>ARREARS</strong>, <strong>METER RENTAL</strong>, <strong>PENALTY</strong>, <strong>TOTAL</strong>.
                                The date you select below is used for every row.
                                Each component is stored on the consumer ledger (not only a single TOTAL DM).
                                If a DM already exists for that account and date, this upload <strong>updates</strong> it instead of creating a duplicate.
                                If the account is listed but PY / ARREARS / METER RENTAL / PENALTY / TOTAL are <strong>blank</strong> (or zero), that means the account is <strong>already paid</strong> — the existing DM for that date is deleted.
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
                                                <code>ZONE</code>, <code>ACCOUNT NAME</code> (or <code>LAST NAME</code> + <code>FIRST NAME</code>), <code>PY</code>,
                                                <code>ARREARS</code>, <code>METER RENTAL</code>, <code>PENALTY</code>, <code>TOTAL</code>
                                            </p>
                                            <small class="text-muted">
                                                Blank columns between headers are ignored.
                                                Match is by <code>ACCOUNT NAME</code>, or by <code>LAST NAME</code> and <code>FIRST NAME</code> together (both must match the consumer master exactly — one name alone is not enough), the <code>#</code> id inside the name (e.g. <code>#1093</code>), optional <code>ZONE</code>, or <code>account_no</code> if present.
                                                <strong>PY</strong> → DM <code>prio_years</code> (Prior Years only).
                                                <strong>ARREARS</strong> → DM <code>current_arrears</code> (current arrears — not added to PY).
                                                <strong>METER RENTAL</strong> → DM <code>others</code>.
                                                <strong>PENALTY</strong> → DM <code>penalty</code>.
                                                <strong>TOTAL</strong> is the check sum — it is not stored as a separate row.
                                                The classic <code>account_no</code> + <code>amount</code> file still works.
                                                Re-upload with the same DM date to <strong>update</strong> existing records.
                                                Blank or zero amounts for a named account mean <strong>already paid</strong> — the existing DM is removed.
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
                                <li class="mb-2">Use the Excel with headers: <code>ZONE</code>, <code>ACCOUNT NAME</code> or <code>LAST NAME</code> + <code>FIRST NAME</code>, <code>PY</code>, <code>ARREARS</code>, <code>METER RENTAL</code>, <code>PENALTY</code>, <code>TOTAL</code>. LAST NAME and FIRST NAME must both match the consumer exactly.</li>
                                <li class="mb-2">Select the <strong>date</strong> that will apply to every imported row.</li>
                                <li class="mb-2">
                                    Each account is stored as ledger charges:
                                    PY as <strong>prio_years</strong>, ARREARS as <strong>current_arrears</strong>, METER RENTAL as <strong>others</strong>, and PENALTY as <strong>penalty</strong> on one DM (separate columns — not combined).
                                </li>
                                <li class="mb-2">TOTAL is not imported as a fourth amount (it must equal the four parts).</li>
                                <li class="mb-2">Rows with no name and no amounts are skipped. Unknown accounts or duplicates <em>in the same file</em> are reported.</li>
                                <li class="mb-2">If the account is in the file but amounts are <strong>blank</strong> (already paid), the existing DM for that date is <strong>deleted</strong> and the ledger balance is rebuilt.</li>
                                <li class="mb-2">Re-uploading the same Excel (same account and DM date) <strong>updates</strong> PY, ARREARS, METER RENTAL, PENALTY, and Debit on the existing DM. New accounts are still inserted.</li>
                                <li>After import, open the Account Ledger and confirm <strong>Debit</strong> equals PY + ARREARS + METER RENTAL + PENALTY, with meter rental in <strong>Others</strong> and penalty in <strong>Penalty</strong>.</li>
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

                        var didSave = (res.imported || 0) > 0 || (res.updated || 0) > 0 || (res.cleared || 0) > 0;
                        Swal.fire({
                            icon: didSave ? 'success' : 'warning',
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
