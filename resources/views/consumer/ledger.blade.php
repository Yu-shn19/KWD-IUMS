<!DOCTYPE html>
<html lang="en">
@include('partials.header')
@if(request('embed'))
<style>
    /* Embed mode: show only the Account Ledger table (no sidebar, header, tabs, or summary card) */
    body.ledger-embed #wrapper .sidebar,
    body.ledger-embed #content > div:not(#container-wrapper),
    body.ledger-embed #container-wrapper > .card.shadow.mb-3,
    body.ledger-embed .scroll-to-top { display: none !important; }
    body.ledger-embed #content-wrapper { margin-left: 0 !important; }
    body.ledger-embed #container-wrapper { padding-top: 0.5rem !important; }
    body.ledger-embed #ledgerCard .card-header .badge { font-size: 0.8rem; }
</style>
@endif

<body id="page-top" class="@if(request('embed')) ledger-embed @endif">
    <div id="wrapper">
        <!-- Sidebar -->
        @include('partials.sidebar')
        <!-- Sidebar -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">

                <!-- Consumer Header -->
                @include('consumer.header')

                <!-- Navigation Tabs -->
                <div class="card-body p-0">
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <a href="{{ route('consumer') }}" class="nav-link">
                                <i class="fas fa-user me-1"></i>Consumer Details
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('ledger') }}" class="nav-link active">
                                <i class="fas fa-file-invoice me-1"></i>Account Ledger
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('lro-ledger') }}" class="nav-link">
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
                    <!-- Filter and Summary Row -->
                    <div class="card shadow mb-3">
                        <div class="card-body py-2">
                            <div class="row align-items-center">
                                <!-- Year Filter -->
                                <div class="col-md-2">
                                    <label class="small font-weight-bold mb-1">YEAR (leave blank for all)</label>
                                    <input type="text" class="form-control form-control-sm" id="ledgerYear" placeholder="e.g., 2025">
    </div>

                                <!-- Financial Summary -->
                                <div class="col-md-10">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">TOTAL BILL</label>
                                            <input type="text" class="form-control form-control-sm text-right" id="totalBill" value="0.00" readonly>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">LATEST BILL</label>
                                            <input type="text" class="form-control form-control-sm text-right font-weight-bold" id="latestBillAmount" value="0.00" readonly>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">LOANS</label>
                                            <input type="text" class="form-control form-control-sm text-right" id="totalLoans" value="0.00" readonly>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">BALANCE</label>
                                            <input type="text" class="form-control form-control-sm text-right font-weight-bold text-danger" id="currentBalance" value="0.00" readonly>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button class="btn btn-primary btn-sm mr-2" id="printLedgerBtn">
                                                <i class="fas fa-print mr-1"></i>F6 - Print
      </button>
                                            <button class="btn btn-secondary btn-sm" id="refreshLedgerBtn">
                                                <i class="fas fa-sync-alt mr-1"></i>Refresh
      </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
    </div>
  </div>
   
  <!-- Account Ledger Table -->
                    <div id="ledgerCard" class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Account Ledger - F10</h6>
                            <span class="badge badge-primary" id="ledgerAccountBadge">No Account Selected</span>
                        </div>
                        <div class="card-body p-0">
                            <div id="ledgerTableScroll" class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                                <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;">
                                    <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                                        <tr>
                                            <th class="text-center py-2 px-2" style="min-width: 80px;">TRANS</th>
                                            <th class="text-center py-2 px-2" style="min-width: 90px;">Date</th>
                                            <th class="text-center py-2 px-2" style="min-width: 90px;">Due Date</th>
                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Reference</th>
                                            <th class="text-center py-2 px-2" style="min-width: 80px;">Reading</th>
                                            <th class="text-center py-2 px-2" style="min-width: 70px;">Volume</th>
                                            <th class="text-center py-2 px-2" style="min-width: 90px;">Bill Amount</th>
                                            <th class="text-center py-2 px-2" style="min-width: 80px;">Penalty</th>
                                            <th class="text-center py-2 px-2" style="min-width: 70px;">Others</th>
                                            <th class="text-center py-2 px-2" style="min-width: 80px;">Debit</th>
                                            <th class="text-center py-2 px-2" style="min-width: 80px;">Credit</th>
                                            <th class="text-center py-2 px-2" style="min-width: 90px;">Balance</th>
                                            <th class="text-center py-2 px-2" style="min-width: 80px;">UserName</th>
                                            <th class="text-center py-2 px-2" style="min-width: 140px;">TxTime</th>
        </tr>
      </thead>
                                    <tbody id="ledgerTableBody">
                                        <tr>
                                            <td colspan="14" class="text-center text-muted py-5">
                                                <i class="fas fa-search fa-2x mb-3 d-block"></i>
                                                <p>Please search for a consumer to view ledger entries</p>
                                            </td>
                                        </tr>
      </tbody>
    </table>
                            </div>
                        </div>
                        <div class="card-footer bg-light py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Account Ledger for Year <span id="ledgerYearDisplay">{{ date('Y') }}</span>
                                </small>
                                <small class="text-muted">
                                    Total Transactions: <span class="font-weight-bold text-primary" id="totalTransactions">0</span> | 
                                    Current Balance: <span class="font-weight-bold text-danger" id="footerBalance">₱ 0.00</span>
                                </small>
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

    @include('consumer.shared-header-script')

    <style>
        /* Reduce gap between nav tabs and filter card; prevent container from stretching (fix wide empty space) */
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
        .hover-row:hover {
            background-color: #f8f9fc !important;
        }
        .bg-penalty {
            background-color: #fff3cd !important; /* Light yellow/warning color for penalties */
        }
    </style>

    <script>
        $(document).ready(function() {
            let currentAccountNo = null;

            // Format currency
            function formatCurrency(value) {
                const amount = parseFloat(value) || 0;
                return amount.toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            // Format date
            function formatDate(dateString) {
                if (!dateString) return '';
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return '';
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const year = date.getFullYear();
                return `${month}/${day}/${year}`;
            }

            // Format datetime
            function formatDateTime(dateString) {
                if (!dateString) return '';
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return '';
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                const seconds = String(date.getSeconds()).padStart(2, '0');
                const ampm = date.getHours() >= 12 ? 'PM' : 'AM';
                const displayHours = date.getHours() % 12 || 12;
                return `${month}/${day}/${year} ${String(displayHours).padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
            }

            // Load ledger data
            function loadLedgerData(accountNo, year) {
                if (!accountNo) {
                    $('#ledgerTableBody').html(`
                        <tr>
                            <td colspan="14" class="text-center text-muted py-5">
                                <i class="fas fa-search fa-2x mb-3 d-block"></i>
                                <p>Please search for a consumer to view ledger entries</p>
                            </td>
                        </tr>
                    `);
                    return;
                }

                // Show loading
                $('#ledgerTableBody').html(`
                    <tr>
                        <td colspan="14" class="text-center py-5">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3 d-block"></i>
                            <p>Loading ledger data...</p>
                        </td>
                    </tr>
                `);

                const yearValue = year || $('#ledgerYear').val();
                console.log('Loading ledger for account:', accountNo, 'year:', yearValue);
                
                $.ajax({
                    url: '{{ route("ledger.data") }}',
                    method: 'GET',
                    data: {
                        account_no: accountNo,
                        year: yearValue
                    },
                    success: function(response) {
                        console.log('Ledger response:', response);
                        if (response.success && response.ledgers) {
                            currentAccountNo = accountNo;
                            displayLedgerData(response);
                        } else {
                            $('#ledgerTableBody').html(`
                                <tr>
                                    <td colspan="14" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                        <p>No ledger entries found for this account</p>
                                    </td>
                                </tr>
                            `);
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading ledger:', xhr);
                        console.error('Response:', xhr.responseJSON);
                        $('#ledgerTableBody').html(`
                            <tr>
                                <td colspan="14" class="text-center text-danger py-5">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-3 d-block"></i>
                                    <p>Error loading ledger data. Please try again.</p>
                                </td>
                            </tr>
                        `);
                    }
                });
            }

            // Display ledger data
            function displayLedgerData(data) {
                const ledgers = data.ledgers;
                const summary = data.summary;
                const consumer = data.consumer;

                // Update account badge and keep header in sync with ledger (fix: header showing wrong consumer)
                if (consumer) {
                    $('#ledgerAccountBadge').text(`Account: ${consumer.account_no} - ${consumer.account_name}`);
                    // Sync this consumer to sessionStorage and update header so header always matches ledger
                    try {
                        const stored = sessionStorage.getItem('currentConsumer');
                        const merged = stored ? Object.assign({}, JSON.parse(stored), consumer) : consumer;
                        sessionStorage.setItem('currentConsumer', JSON.stringify(merged));
                        if (typeof window.loadConsumerHeader === 'function') {
                            window.loadConsumerHeader();
                        }
                    } catch (e) { console.error('Ledger header sync:', e); }
                }

                // Update summary
                console.log('Summary data:', summary);
                $('#totalBill').val(formatCurrency(summary.total_bill || 0));
                $('#latestBillAmount').val(formatCurrency(summary.latest_bill_amount || 0));
                $('#totalLoans').val(formatCurrency(summary.total_loans || 0));
                // Handle negative balances correctly - use nullish coalescing to preserve negative values
                const balanceValue = summary.balance !== null && summary.balance !== undefined ? summary.balance : 0;
                $('#currentBalance').val(formatCurrency(balanceValue));
                $('#footerBalance').text('₱ ' + formatCurrency(balanceValue));
                $('#totalTransactions').text(summary.total_transactions || 0);
                $('#ledgerYearDisplay').text(summary.year || $('#ledgerYear').val() || 'All Years');

                // Build table rows
                let html = '';
                if (ledgers.length === 0) {
                    html = `
                        <tr>
                            <td colspan="14" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                <p>No ledger entries found for this year</p>
                            </td>
                        </tr>
                    `;
                } else {
                    ledgers.forEach(function(ledger) {
                        const trans = ledger.trans || '';
                        const date = formatDate(ledger.date);
                        const dueDate = formatDate(ledger.due_date);
                        const reference = ledger.reference || '';
                        const reading = ledger.reading || '';
                        const volume = ledger.volume || '';
                        const billAmount = parseFloat(ledger.billamount) || 0;
                        const penalty = parseFloat(ledger.penalty) || 0;
                        const others = parseFloat(ledger.others) || 0;
                        const debit = parseFloat(ledger.debit) || 0;
                        const credit = parseFloat(ledger.credit) || 0;
                        // Handle negative balances correctly - check for null/undefined, not falsy
                        const balance = ledger.balance !== null && ledger.balance !== undefined ? parseFloat(ledger.balance) : 0;
                        const username = ledger.username || '';
                        const txtime = formatDateTime(ledger.txtime);

                        // Determine row class based on transaction type
                        let rowClass = 'hover-row';
                        if (trans.toUpperCase() === 'PAYMENT') {
                            rowClass += ' bg-light';
                        }

                        // Determine balance color
                        const balanceClass = balance > 0 ? 'text-danger' : 'text-success';

                        html += `
                            <tr class="${rowClass}">
                                <td class="text-center py-1 px-2">${trans}</td>
                                <td class="text-center py-1 px-2">${date}</td>
                                <td class="text-center py-1 px-2">${dueDate}</td>
                                <td class="text-center py-1 px-2">${reference}</td>
                                <td class="text-center py-1 px-2">${reading}</td>
                                <td class="text-center py-1 px-2">${volume}</td>
                                <td class="text-right py-1 px-2">${billAmount > 0 ? formatCurrency(billAmount) : ''}</td>
                                <td class="text-right py-1 px-2">${formatCurrency(penalty)}</td>
                                <td class="text-right py-1 px-2">${others > 0 ? formatCurrency(others) : ''}</td>
                                <td class="text-right py-1 px-2 ${debit > 0 ? 'font-weight-bold' : ''}">${debit > 0 ? formatCurrency(debit) : ''}</td>
                                <td class="text-right py-1 px-2 ${credit > 0 ? 'font-weight-bold text-success' : ''}">${credit > 0 ? formatCurrency(credit) : ''}</td>
                                <td class="text-right py-1 px-2 font-weight-bold ${balanceClass}">${formatCurrency(balance)}</td>
                                <td class="text-center py-1 px-2">${username}</td>
                                <td class="text-center py-1 px-2">${txtime}</td>
                            </tr>
                        `;
                    });
                }

                $('#ledgerTableBody').html(html);

                // Scroll main page to ledger section and table to bottom so latest entries are visible
                if (ledgers.length > 0) {
                    setTimeout(function() {
                        var ledgerCard = document.getElementById('ledgerCard');
                        if (ledgerCard) {
                            ledgerCard.scrollIntoView({ behavior: 'smooth', block: 'end' });
                        }
                        var scrollEl = document.getElementById('ledgerTableScroll');
                        if (scrollEl) {
                            scrollEl.scrollTop = scrollEl.scrollHeight;
                        }
                    }, 100);
                }
            }

            // Load ledger when consumer is selected from sessionStorage
            function checkAndLoadLedger() {
                const storedConsumer = sessionStorage.getItem('currentConsumer');
                if (storedConsumer) {
                    try {
                        const consumer = JSON.parse(storedConsumer);
                        if (consumer.account_no || consumer.account_number) {
                            loadLedgerData(consumer.account_no || consumer.account_number);
                        }
                    } catch (e) {
                        console.error('Error parsing stored consumer:', e);
                    }
                }
            }

            // Year change handler
            $('#ledgerYear').on('change', function() {
                if (currentAccountNo) {
                    loadLedgerData(currentAccountNo, $(this).val());
                }
            });

            // Refresh button
            $('#refreshLedgerBtn').on('click', function() {
                if (currentAccountNo) {
                    loadLedgerData(currentAccountNo, $('#ledgerYear').val());
                } else {
                    checkAndLoadLedger();
                }
            });

            // Print button
            $('#printLedgerBtn').on('click', function() {
                window.print();
            });

            // Listen for consumer selection from other pages
            $(window).on('storage', function(e) {
                if (e.originalEvent.key === 'currentConsumer') {
                    checkAndLoadLedger();
                }
            });

            // Check on page load
            checkAndLoadLedger();

            // Also check periodically in case consumer was selected on same page
            let checkInterval = setInterval(function() {
                const stored = sessionStorage.getItem('currentConsumer');
                if (stored && !currentAccountNo) {
                    checkAndLoadLedger();
                }
            }, 2000); // Check every 2 seconds

            // Debug: Log what's in sessionStorage
            console.log('SessionStorage currentConsumer:', sessionStorage.getItem('currentConsumer'));
        });
    </script>
</body>
</html>
