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
                            <i class="fas fa-edit mr-2"></i>Edit DM
                        </h1>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-search mr-2"></i>Find Consumer
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                Search by <strong>account number</strong> or <strong>name</strong>, then edit the DM:
                                PY, ARREARS, METER RENTAL, and PENALTY.
                                Saving zero amounts clears the DM (already paid).
                            </p>
                            <div class="form-row align-items-end">
                                <div class="col-md-8 mb-2">
                                    <label for="editDmSearch" class="font-weight-bold">Account No. or Name</label>
                                    <input type="text"
                                           id="editDmSearch"
                                           class="form-control"
                                           placeholder="e.g. 1433 or DELA CRUZ"
                                           autocomplete="off">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <button type="button" class="btn btn-primary btn-block" id="editDmSearchBtn">
                                        <i class="fas fa-search mr-1"></i>Search
                                    </button>
                                </div>
                            </div>
                            <div id="editDmConsumerInfo" class="alert alert-light border mt-3 mb-0 d-none">
                                <strong id="editDmAccountNo">--</strong>
                                <span class="text-muted" id="editDmAccountName">--</span>
                                <span class="ml-2 badge badge-info" id="editDmZone"></span>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-list mr-2"></i>DM Records
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="text-center">Date</th>
                                            <th class="text-center">Reference</th>
                                            <th class="text-right">PY</th>
                                            <th class="text-right">Arrears</th>
                                            <th class="text-right">Meter Rental</th>
                                            <th class="text-right">Penalty</th>
                                            <th class="text-right">Debit</th>
                                            <th class="text-center" style="width: 140px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="editDmTableBody">
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                Search a consumer to load DM records.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @include('partials.footer')
        </div>
    </div>

    <div class="modal fade" id="editDmModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit mr-2"></i>Edit Debit Memo
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editDmForm">
                        @csrf
                        <input type="hidden" id="editDmLedgerId" value="">
                        <div class="form-group">
                            <label for="editDmDate" class="font-weight-bold">Date <span class="text-danger">*</span></label>
                            <input type="date" id="editDmDate" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Reference</label>
                            <input type="text" id="editDmReference" class="form-control bg-light" readonly>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="editDmPy" class="font-weight-bold">PY (Prior Years)</label>
                                <input type="number" id="editDmPy" class="form-control edit-dm-amt" step="0.01" min="0" value="0">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="editDmArrears" class="font-weight-bold">Arrears</label>
                                <input type="number" id="editDmArrears" class="form-control edit-dm-amt" step="0.01" min="0" value="0">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="editDmOthers" class="font-weight-bold">Meter Rental</label>
                                <input type="number" id="editDmOthers" class="form-control edit-dm-amt" step="0.01" min="0" value="0">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="editDmPenalty" class="font-weight-bold">Penalty</label>
                                <input type="number" id="editDmPenalty" class="form-control edit-dm-amt" step="0.01" min="0" value="0">
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label class="font-weight-bold">Total (Debit)</label>
                            <input type="text" id="editDmTotal" class="form-control bg-light font-weight-bold" readonly>
                            <small class="text-muted">Set all amounts to 0 to clear this DM (already paid).</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="editDmDeleteBtn">
                        <i class="fas fa-trash mr-1"></i>Delete DM
                    </button>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary btn-sm" id="editDmSaveBtn">
                            <i class="fas fa-save mr-1"></i>Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            var currentAccount = '';
            var csrf = $('meta[name="csrf-token"]').attr('content');

            function money(n) {
                return (parseFloat(n) || 0).toFixed(2);
            }

            function recalcTotal() {
                var total = (parseFloat($('#editDmPy').val()) || 0)
                    + (parseFloat($('#editDmArrears').val()) || 0)
                    + (parseFloat($('#editDmOthers').val()) || 0)
                    + (parseFloat($('#editDmPenalty').val()) || 0);
                $('#editDmTotal').val(money(total));
            }

            $('.edit-dm-amt').on('input', recalcTotal);

            function renderRows(dms) {
                if (!dms || !dms.length) {
                    $('#editDmTableBody').html(
                        '<tr><td colspan="8" class="text-center text-muted py-4">No DM records for this consumer.</td></tr>'
                    );
                    return;
                }
                var html = '';
                dms.forEach(function(dm) {
                    html += '<tr>' +
                        '<td class="text-center">' + (dm.date || '') + '</td>' +
                        '<td class="text-center">' + (dm.reference || '') + '</td>' +
                        '<td class="text-right">' + money(dm.prio_years) + '</td>' +
                        '<td class="text-right">' + money(dm.current_arrears) + '</td>' +
                        '<td class="text-right">' + money(dm.others) + '</td>' +
                        '<td class="text-right">' + money(dm.penalty) + '</td>' +
                        '<td class="text-right font-weight-bold">' + money(dm.debit) + '</td>' +
                        '<td class="text-center">' +
                            '<button type="button" class="btn btn-sm btn-primary edit-dm-open"' +
                            ' data-id="' + dm.id + '"' +
                            ' data-date="' + (dm.date || '') + '"' +
                            ' data-reference="' + (dm.reference || '') + '"' +
                            ' data-py="' + money(dm.prio_years) + '"' +
                            ' data-arrears="' + money(dm.current_arrears) + '"' +
                            ' data-others="' + money(dm.others) + '"' +
                            ' data-penalty="' + money(dm.penalty) + '">' +
                            '<i class="fas fa-edit"></i> Edit</button>' +
                        '</td></tr>';
                });
                $('#editDmTableBody').html(html);
            }

            function loadDms(query) {
                $('#editDmTableBody').html(
                    '<tr><td colspan="8" class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>'
                );
                $.ajax({
                    url: '{{ route("consumer-master-list.list-dm") }}',
                    method: 'GET',
                    data: { search: query, account_no: query },
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    success: function(res) {
                        currentAccount = res.consumer.account_no || query;
                        $('#editDmAccountNo').text(res.consumer.account_no || '--');
                        $('#editDmAccountName').text(res.consumer.account_name || '--');
                        $('#editDmZone').text(res.consumer.zone_code ? ('Zone ' + res.consumer.zone_code) : '');
                        $('#editDmConsumerInfo').removeClass('d-none');
                        renderRows(res.dms || []);
                    },
                    error: function(xhr) {
                        currentAccount = '';
                        $('#editDmConsumerInfo').addClass('d-none');
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Consumer not found.';
                        $('#editDmTableBody').html(
                            '<tr><td colspan="8" class="text-center text-danger py-4">' + msg + '</td></tr>'
                        );
                    }
                });
            }

            $('#editDmSearchBtn').on('click', function() {
                var q = $.trim($('#editDmSearch').val());
                if (q.length < 1) {
                    Swal.fire({ icon: 'warning', title: 'Required', text: 'Enter an account number or name.', confirmButtonColor: '#f0ad4e' });
                    return;
                }
                loadDms(q);
            });

            $('#editDmSearch').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#editDmSearchBtn').click();
                }
            });

            $(document).on('click', '.edit-dm-open', function() {
                var btn = $(this);
                $('#editDmLedgerId').val(btn.data('id'));
                $('#editDmDate').val(btn.data('date'));
                $('#editDmReference').val(btn.data('reference'));
                $('#editDmPy').val(btn.data('py'));
                $('#editDmArrears').val(btn.data('arrears'));
                $('#editDmOthers').val(btn.data('others'));
                $('#editDmPenalty').val(btn.data('penalty'));
                recalcTotal();
                $('#editDmModal').modal('show');
            });

            $('#editDmSaveBtn').on('click', function() {
                var ledgerId = $('#editDmLedgerId').val();
                var date = $('#editDmDate').val();
                if (!ledgerId || !date) {
                    Swal.fire({ icon: 'warning', title: 'Required', text: 'Date is required.', confirmButtonColor: '#f0ad4e' });
                    return;
                }
                var payload = {
                    _token: csrf,
                    _method: 'PUT',
                    ledger_id: ledgerId,
                    date: date,
                    prio_years: parseFloat($('#editDmPy').val()) || 0,
                    current_arrears: parseFloat($('#editDmArrears').val()) || 0,
                    others: parseFloat($('#editDmOthers').val()) || 0,
                    penalty: parseFloat($('#editDmPenalty').val()) || 0
                };
                var btn = $(this);
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');
                $.ajax({
                    url: '{{ route("consumer-master-list.update-dm") }}',
                    method: 'POST',
                    data: payload,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-HTTP-Method-Override': 'PUT'
                    },
                    success: function(res) {
                        btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Changes');
                        $('#editDmModal').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'DM Updated',
                            text: res.message || 'Saved.',
                            confirmButtonColor: '#3085d6'
                        });
                        if (currentAccount) {
                            loadDms(currentAccount);
                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Changes');
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Save failed.';
                        Swal.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonColor: '#d33' });
                    }
                });
            });

            $('#editDmDeleteBtn').on('click', function() {
                var ledgerId = $('#editDmLedgerId').val();
                if (!ledgerId) return;
                Swal.fire({
                    icon: 'warning',
                    title: 'Delete this DM?',
                    text: 'The ledger balance will be rebuilt after delete.',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete'
                }).then(function(result) {
                    if (!result.isConfirmed) return;
                    $.ajax({
                        url: '{{ route("consumer-master-list.destroy-dm") }}',
                        method: 'POST',
                        data: { _token: csrf, ledger_id: ledgerId, _method: 'DELETE' },
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-HTTP-Method-Override': 'DELETE'
                        },
                        success: function(res) {
                            $('#editDmModal').modal('hide');
                            Swal.fire({ icon: 'success', title: 'Deleted', text: res.message || 'DM deleted.', confirmButtonColor: '#3085d6' });
                            if (currentAccount) {
                                loadDms(currentAccount);
                            }
                        },
                        error: function(xhr) {
                            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Delete failed.';
                            Swal.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonColor: '#d33' });
                        }
                    });
                });
            });

            var preset = new URLSearchParams(window.location.search).get('account')
                || new URLSearchParams(window.location.search).get('account_no')
                || '';
            if (preset) {
                $('#editDmSearch').val(preset);
                loadDms(preset);
            }
        });
    </script>
</body>
</html>
