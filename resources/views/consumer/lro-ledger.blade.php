<!DOCTYPE html>
<html lang="en">
@include('partials.header')

<style>
    /* Reduce gap between nav tabs and content; prevent container from stretching (fix wide empty space) */
    #container-wrapper {
        flex: 0 0 auto !important;
        padding-top: 0.5rem !important;
        padding-left: 1rem !important;
        padding-right: 1rem !important;
        padding-bottom: 1rem !important;
    }
    #content > .card-body.p-0 {
        margin-bottom: 0 !important;
    }
</style>

<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        @include('partials.sidebar')
        <!-- Sidebar -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Simple Header -->
                @include('consumer.header')

                <!-- Navigation Tabs -->
                <div class="card-body p-0">
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <a href="{{ route('consumer') }}" class="nav-link ">
                                <i class="fas fa-user me-1"></i>Consumer Details
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('ledger') }}" class="nav-link">
                                <i class="fas fa-file-invoice me-1"></i>Account Ledger
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('lro-ledger') }}" class="nav-link active">
                                <i class="fas fa-list me-1"></i>LRO Ledger
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('service') }}" class="nav-link">
                                <i class="fas fa-history me-1"></i>Service History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('meter') }}" class="nav-link">
                                <i class="fas fa-tachometer-alt me-1"></i>Meter Reading
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('location') }}" class="nav-link">
                                <i class="fas fa-map-marker-alt me-1"></i>Location Map
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('consumption') }}" class="nav-link">
                                <i class="fas fa-chart-line me-1"></i>Consumption Graph
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Container Fluid-->
                <div class="container-fluid" id="container-wrapper">
                    <!-- Filter and Balance Row -->
                    <div class="card shadow mb-3">
                        <div class="card-body py-2">
                            <div class="row align-items-center">
                                <!-- Year Filter -->
                                <div class="col-md-2">
                                    <label class="small font-weight-bold mb-1">YEAR (Optional - Leave blank for all)</label>
                                    <input type="text" class="form-control form-control-sm" id="lroLedgerYear" placeholder="All years" value="">
                                </div>

                                <!-- Balance and Actions (Right side) -->
                                <div class="col-md-10">
                                    <div class="d-flex justify-content-end align-items-center gap-3">
                                        <label class="mb-0 font-weight-bold">BALANCE</label>
                                        <input type="text" class="form-control form-control-sm text-right font-weight-bold" id="lroLedgerBalance" value="0.00" readonly style="width: 120px;">
                                        <button class="btn btn-outline-secondary btn-sm" id="printLroLedgerBtn">
                                            <i class="fas fa-print mr-1"></i>Print
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm" id="refreshLroLedgerBtn">
                                            <i class="fas fa-sync-alt mr-1"></i>Refresh
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- LRO Ledger Transaction Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">LRO Ledger Transactions</h6>
                            <span class="badge badge-primary" id="lroLedgerAccountBadge">No Account Selected</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;">
                                    <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                                        <tr>
                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Trans</th>
                                            <th class="text-center py-2 px-2" style="min-width: 90px;">Date</th>
                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Reference</th>
                                            <th class="text-center py-2 px-2" style="min-width: 90px;">Debit</th>
                                            <th class="text-center py-2 px-2" style="min-width: 90px;">Credit</th>
                                            <th class="text-center py-2 px-2" style="min-width: 90px;">Balance</th>
                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Username</th>
                                        </tr>
                                    </thead>
                                    <tbody id="lroLedgerTableBody">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-5">
                                                <i class="fas fa-search fa-2x mb-3 d-block"></i>
                                                <p>Please search for a consumer to view LRO ledger entries</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">SUMMARY</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0" style="font-size: 11px;">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="text-center py-2 px-2" style="min-width: 120px;">Acct Code</th>
                                            <th class="text-left py-2 px-2" style="min-width: 250px;">Acct Title</th>
                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Charges</th>
                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Payments</th>
                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody id="lroLedgerSummaryBody">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">
                                                No summary data available
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!---Container Fluid-->
            </div>
        </div>
    </div>

<!-- Scroll to top -->
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

@include('partials.footer')
<!-- Footer -->

@include('consumer.shared-header-script')

<script>
// Ensure jQuery is loaded before running the script
jQuery(document).ready(function($) {
    let currentAccountNo = null;

    // Year filter change - allow empty to show all
    $('#lroLedgerYear').on('change blur', function() {
        if (currentAccountNo) {
            const yearValue = $(this).val().trim();
            loadLroLedgerData(currentAccountNo, yearValue || '');
        }
    });

    // Refresh button
    $('#refreshLroLedgerBtn').on('click', function() {
        if (currentAccountNo) {
            loadLroLedgerData(currentAccountNo, $('#lroLedgerYear').val());
        }
    });

    // Print button
    $('#printLroLedgerBtn').on('click', function() {
        window.print();
    });

    // Function to load LRO Ledger data
    function loadLroLedgerData(accountNo, year) {
        if (!accountNo) {
            console.log('LRO Ledger: No account number provided');
            return;
        }

        // Only send year if explicitly set and not empty, otherwise get all records
        const yearValue = year !== undefined ? year : $('#lroLedgerYear').val();
        // Ensure account_no is sent as string so leading zeros (e.g. 011-12-130) are preserved
        const requestData = {
            account_no: (accountNo != null && accountNo !== undefined) ? String(accountNo).trim() : ''
        };
        
        // Only add year filter if user explicitly entered a year (not empty)
        if (yearValue && yearValue.toString().trim() !== '') {
            requestData.year = yearValue.toString().trim();
        }
        
        console.log('LRO Ledger: Loading data for account:', accountNo, 'year filter:', requestData.year || 'ALL (showing all records)');

        $.ajax({
            url: '{{ route("lro-ledger.data") }}',
            method: 'GET',
            data: requestData,
            beforeSend: function() {
                $('#lroLedgerTableBody').html('<tr><td colspan="7" class="text-center py-3"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</td></tr>');
                $('#lroLedgerSummaryBody').html('<tr><td colspan="5" class="text-center py-3"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</td></tr>');
            },
            success: function(response) {
                console.log('LRO Ledger: Response received:', response);
                
                if (response.success) {
                    currentAccountNo = accountNo;
                    displayLroLedgerTransactions(response.transactions || []);
                    displayLroLedgerSummary(response.summary || []);
                    $('#lroLedgerBalance').val(response.balance || '0.00');
                    
                    if (response.consumer) {
                        $('#lroLedgerAccountBadge').text(response.consumer.account_no + ' - ' + response.consumer.account_name);
                        // Sync consumer to sessionStorage and header so search selection stays in sync
                        try {
                            const stored = sessionStorage.getItem('currentConsumer');
                            const merged = stored ? Object.assign({}, JSON.parse(stored), response.consumer) : response.consumer;
                            sessionStorage.setItem('currentConsumer', JSON.stringify(merged));
                            if (typeof window.loadConsumerHeader === 'function') window.loadConsumerHeader();
                        } catch (e) { console.error('LRO Ledger header sync:', e); }
                    }
                    
                    console.log('LRO Ledger: Data loaded successfully. Transactions:', (response.transactions || []).length);
                } else {
                    console.error('LRO Ledger: Response not successful:', response.message);
                    $('#lroLedgerTableBody').html('<tr><td colspan="7" class="text-center text-danger py-3">' + (response.message || 'Error loading data') + '</td></tr>');
                    $('#lroLedgerSummaryBody').html('<tr><td colspan="5" class="text-center text-danger py-3">No summary data available</td></tr>');
                }
            },
            error: function(xhr) {
                console.error('LRO Ledger: AJAX Error:', xhr);
                console.error('LRO Ledger: Status:', xhr.status);
                console.error('LRO Ledger: Response:', xhr.responseJSON);
                
                let errorMsg = 'Error loading LRO ledger data';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.status === 404) {
                    errorMsg = 'Consumer not found';
                } else if (xhr.status === 500) {
                    errorMsg = 'Server error. Please check the console for details.';
                }
                
                $('#lroLedgerTableBody').html('<tr><td colspan="7" class="text-center text-danger py-3">' + errorMsg + '</td></tr>');
                $('#lroLedgerSummaryBody').html('<tr><td colspan="5" class="text-center text-danger py-3">No summary data available</td></tr>');
            }
        });
    }

    // Display transactions
    function displayLroLedgerTransactions(transactions) {
        const tbody = $('#lroLedgerTableBody');
        tbody.empty();

        if (transactions.length === 0) {
            tbody.html('<tr><td colspan="7" class="text-center text-muted py-5">No LRO ledger transactions found</td></tr>');
            return;
        }

        transactions.forEach(function(transaction) {
            const row = $('<tr></tr>');
            row.append('<td class="text-center">' + (transaction.trans || '') + '</td>');
            row.append('<td class="text-center">' + (transaction.date || '') + '</td>');
            row.append('<td class="text-center">' + (transaction.reference || '') + '</td>');
            row.append('<td class="text-end">' + (transaction.debit || '') + '</td>');
            row.append('<td class="text-end">' + (transaction.credit || '') + '</td>');
            row.append('<td class="text-end">' + (transaction.balance || '0.00') + '</td>');
            // Username column is fixed to FLORAME as requested
            row.append('<td class="text-center">FLORAME</td>');
            tbody.append(row);
        });
    }

    // Display summary
    function displayLroLedgerSummary(summary) {
        const tbody = $('#lroLedgerSummaryBody');
        tbody.empty();

        if (summary.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center text-muted py-3">No summary data available</td></tr>');
            return;
        }

        summary.forEach(function(item) {
            const row = $('<tr></tr>');
            row.append('<td class="text-center">' + (item.acct_code || '') + '</td>');
            row.append('<td class="text-left">' + (item.acct_title || '') + '</td>');
            row.append('<td class="text-end">' + (item.charges || '0.00') + '</td>');
            row.append('<td class="text-end">' + (item.payments || '0.00') + '</td>');
            row.append('<td class="text-end">' + (item.balance || '0.00') + '</td>');
            tbody.append(row);
        });
    }

    // Function to check and load LRO ledger from sessionStorage
    function checkAndLoadLroLedger() {
        const storedConsumer = sessionStorage.getItem('currentConsumer');
        console.log('LRO Ledger: Checking sessionStorage:', storedConsumer);
        
        if (storedConsumer) {
            try {
                const consumer = JSON.parse(storedConsumer);
                console.log('LRO Ledger: Parsed consumer:', consumer);
                
                if (consumer && (consumer.account_no || consumer.account_number)) {
                    // Only load if it's a different account or not loaded yet
                    const accountNo = consumer.account_no || consumer.account_number;
                    if (!currentAccountNo || currentAccountNo !== accountNo) {
                        console.log('LRO Ledger: Loading data for account:', accountNo);
                        // Pass empty string to get all records by default
                        loadLroLedgerData(accountNo, '');
                    } else {
                        console.log('LRO Ledger: Already loaded for account:', currentAccountNo);
                    }
                } else {
                    console.log('LRO Ledger: Consumer missing account_no');
                }
            } catch (e) {
                console.error('LRO Ledger: Error parsing stored consumer:', e);
            }
        } else {
            console.log('LRO Ledger: No consumer in sessionStorage');
        }
    }

    // Listen for consumer selection from other pages/tabs
    $(window).on('storage', function(e) {
        if (e.originalEvent.key === 'currentConsumer') {
            console.log('LRO Ledger: Storage event detected');
            checkAndLoadLroLedger();
        }
    });

    // Check on page load immediately
    checkAndLoadLroLedger();

    // Also check after a short delay to catch any race conditions
    setTimeout(function() {
        checkAndLoadLroLedger();
    }, 500);

    // Also check periodically in case consumer was selected on same page
    // This handles the case when the page doesn't reload after consumer search
    let checkInterval = setInterval(function() {
        const stored = sessionStorage.getItem('currentConsumer');
        if (stored) {
            try {
                const consumer = JSON.parse(stored);
                if (consumer && (consumer.account_no || consumer.account_number) && (!currentAccountNo || currentAccountNo !== (consumer.account_no || consumer.account_number))) {
                    console.log('LRO Ledger: Periodic check - loading data for:', consumer.account_no || consumer.account_number);
                    checkAndLoadLroLedger();
                }
            } catch (e) {
                // Ignore parse errors
            }
        }
    }, 2000); // Check every 2 seconds

    // Debug: Log what's in sessionStorage on page load
    console.log('LRO Ledger: Page loaded. SessionStorage currentConsumer:', sessionStorage.getItem('currentConsumer'));
    console.log('LRO Ledger: Current path:', window.location.pathname);
});
</script>

</body>
</html>
