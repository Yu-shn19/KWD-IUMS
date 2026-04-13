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
    <div id="wrapper" class="ledger-no-print">
        <!-- Sidebar -->
        @include('partials.sidebar')
        <!-- Sidebar -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">

                <!-- Consumer Header -->
                @include('consumer.header')

                @include('consumer.nav-tabs', ['activeTab' => 'ledger'])

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
                                                <i class="fas fa-print mr-1"></i>Print
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
                                            <th class="text-center py-2 px-2" style="min-width: 90px;">TRANS</th>
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
    <a class="scroll-to-top rounded ledger-no-print" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    {{-- Official print layout (HWD consumer ledger) — filled by JS then window.print() --}}
    <div id="ledgerPrintRoot" class="ledger-print-root" aria-hidden="true">
        <div class="ledger-print-inner">
            <div class="ledger-print-header text-center">
                <div class="ledger-print-title">HAGONOY WATER DISTRICT</div>
                <div class="ledger-print-sub">Guihing, Hagonoy</div>
                <div class="ledger-print-heading">CONSUMER LEDGER</div>
                <div class="ledger-print-year" id="printLedgerYear">{{ date('Y') }}</div>
            </div>

            <div class="ledger-print-meta row mt-3">
                <div class="col-6">
                    <div class="ledger-print-line"><span class="lbl">Acctno</span><span class="ledger-print-colon">:</span><span id="printAcctNo" class="val"></span></div>
                    <div class="ledger-print-line"><span class="lbl">Name</span><span class="ledger-print-colon">:</span><span id="printName" class="val"></span></div>
                    <div class="ledger-print-line"><span class="lbl">Address</span><span class="ledger-print-colon">:</span><span id="printAddress" class="val"></span></div>
                </div>
                <div class="col-6">
                    <div class="ledger-print-line"><span class="lbl">Zone</span><span class="ledger-print-colon">:</span><span id="printZone" class="val"></span></div>
                    <div class="ledger-print-line"><span class="lbl">Card No</span><span class="ledger-print-colon">:</span><span id="printCard" class="val"></span></div>
                    <div class="ledger-print-line"><span class="lbl">Meter No</span><span class="ledger-print-colon">:</span><span id="printMeter" class="val"></span></div>
                </div>
            </div>

            <hr class="ledger-print-rule" />

            <table class="ledger-print-table" cellspacing="0" cellpadding="0">
                <thead>
                    <tr>
                        <th class="c-date">DATE</th>
                        <th class="c-trans">TRANS</th>
                        <th class="c-ref">REFERENCE</th>
                        <th class="c-num">READING</th>
                        <th class="c-num">VOL</th>
                        <th class="c-num">OTHERS</th>
                        <th class="c-num">BILL AMT</th>
                        <th class="c-num">DEBIT</th>
                        <th class="c-num">CREDIT</th>
                        <th class="c-num">BALANCE</th>
                        <th class="c-user"></th>
                    </tr>
                </thead>
                <tbody id="ledgerPrintRows"></tbody>
            </table>

            <div class="ledger-print-signatures row mt-4 pt-3">
                <div class="col-6">
                    <div class="ledger-print-sig-label">Prepared by:</div>
                    <div class="ledger-print-sig-line"></div>
                </div>
                <div class="col-6">
                    <div class="ledger-print-sig-label">Checked by:</div>
                    <div class="ledger-print-sig-line"></div>
                </div>
            </div>
        </div>
    </div>

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

        /* Official ledger print (screen: hidden) — larger type, A4-safe */
        .ledger-print-root {
            display: none;
        }
        @page {
            size: A4 portrait;
            /* Page margins (used when the browser honors @page) */
            margin: 12mm 11mm 14mm 11mm;
        }
        @media print {
            .ledger-no-print {
                display: none !important;
            }
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }
            .ledger-print-root {
                display: block !important;
                visibility: visible !important;
                position: static !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            /* Inner padding so content is never flush to paper edge if @page is ignored/minimized */
            .ledger-print-inner {
                font-size: 10.5pt !important;
                padding: 6mm 9mm 8mm 9mm !important;
                box-sizing: border-box !important;
            }
            .ledger-print-table {
                font-size: 9.5pt !important;
            }
            .ledger-print-table th {
                font-size: 8.5pt !important;
            }
        }
        .ledger-print-inner {
            font-family: 'Courier New', Courier, 'Liberation Mono', monospace;
            font-size: 10.5pt;
            line-height: 1.35;
            color: #000;
            max-width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
            /* Screen / forced print preview: visible inset (print uses mm above) */
            padding: 1rem 1.25rem 1.25rem 1.25rem;
        }
        .ledger-print-title {
            font-weight: 700;
            font-size: 1.35rem;
            letter-spacing: 0.02em;
        }
        .ledger-print-sub {
            font-size: 1rem;
            margin-top: 0.15rem;
        }
        .ledger-print-heading {
            font-weight: 700;
            margin-top: 0.35rem;
            font-size: 1.08rem;
        }
        .ledger-print-year {
            font-weight: 700;
            margin-top: 0.2rem;
            font-size: 1.08rem;
        }
        .ledger-print-meta .ledger-print-line {
            display: flex;
            align-items: flex-start;
            margin-bottom: 0.4rem;
            line-height: 1.4;
            font-size: 10pt;
        }
        .ledger-print-meta .lbl {
            flex: 0 0 5.75rem;
            font-weight: 700;
            text-align: left;
        }
        .ledger-print-colon {
            flex: 0 0 0.5rem;
            margin-right: 0.35rem;
        }
        .ledger-print-meta .val {
            flex: 1;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        .ledger-print-rule {
            border: none;
            border-top: 1px solid #000;
            margin: 0.65rem 0 0.45rem;
        }
        .ledger-print-table {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9.5pt;
        }
        .ledger-print-table thead {
            display: table-header-group;
        }
        .ledger-print-table thead tr {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }
        .ledger-print-table th,
        .ledger-print-table td {
            padding: 3px 4px;
            vertical-align: top;
            border: none;
            overflow-wrap: anywhere;
            word-wrap: break-word;
        }
        .ledger-print-table th {
            font-weight: 700;
            text-align: left;
            font-size: 8.5pt;
            line-height: 1.25;
        }
        .ledger-print-table th.c-num,
        .ledger-print-table td.c-num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        /* DATE: room for MM/DD/YYYY on one line (print) */
        .ledger-print-table th.c-date,
        .ledger-print-table td.c-date {
            width: 11%;
            min-width: 5.25rem;
            max-width: 6rem;
            white-space: nowrap;
            box-sizing: border-box;
        }
        .ledger-print-table th.c-trans {
            width: 11%;
        }
        .ledger-print-table th.c-ref {
            width: 13%;
        }
        .ledger-print-table td.c-trans,
        .ledger-print-table td.c-ref {
            text-align: left;
        }
        .ledger-print-table td.c-date {
            text-align: left;
        }
        .ledger-print-table th.c-user {
            width: 8%;
        }
        .ledger-print-table tbody tr {
            border-bottom: 1px solid transparent;
        }
        .ledger-print-signatures .ledger-print-sig-label {
            font-weight: 700;
            margin-bottom: 0.35rem;
            font-size: 10pt;
        }
        .ledger-print-sig-line {
            border-bottom: 1px solid #000;
            min-height: 1.85rem;
            margin-top: 0.25rem;
            max-width: 80%;
        }
    </style>

    <script>
        $(document).ready(function() {
            let currentAccountNo = null;
            /** Last successful ledger API payload (for official print layout) */
            let lastLedgerPayload = null;

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
                    lastLedgerPayload = null;
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
                            lastLedgerPayload = response;
                            displayLedgerData(response);
                        } else {
                            lastLedgerPayload = null;
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
                        lastLedgerPayload = null;
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

            /** REFERENCE column only: strip leading TRANS when duplicate (e.g. "BILLING 31" → "31") */
            function printReferenceOnly(trans, reference) {
                const t = (trans || '').trim();
                let r = (reference || '').trim();
                if (!r) return '';
                const ru = r.toUpperCase();
                const tu = t.toUpperCase();
                if (tu && ru.startsWith(tu + ' ')) {
                    return r.substring(t.length).trim();
                }
                if (tu && ru === tu) return '';
                return r;
            }

            function formatPrintNum(v, showZero) {
                const n = parseFloat(v);
                if (isNaN(n) || n === 0) return showZero ? '0.00' : '';
                return formatCurrency(n);
            }

            /** Fill official print layout and trigger print dialog */
            function printLedgerOfficial() {
                if (!lastLedgerPayload || !lastLedgerPayload.ledgers || lastLedgerPayload.ledgers.length === 0) {
                    alert('Please load a consumer ledger with transactions before printing.');
                    return;
                }
                const data = lastLedgerPayload;
                const ledgers = data.ledgers;
                const summary = data.summary || {};
                const consumer = data.consumer || {};
                let sess = {};
                try {
                    const raw = sessionStorage.getItem('currentConsumer');
                    if (raw) sess = JSON.parse(raw);
                } catch (e) {}

                const yearLabel = (summary.year !== undefined && summary.year !== null && String(summary.year).trim() !== '')
                    ? String(summary.year)
                    : ($('#ledgerYear').val() || new Date().getFullYear());
                $('#printLedgerYear').text(yearLabel);

                $('#printAcctNo').text(consumer.account_no || sess.account_no || '');
                $('#printName').text(consumer.account_name || sess.account_name || '');
                $('#printAddress').text(consumer.address1 || sess.address1 || '');
                $('#printZone').text(consumer.zone_code || sess.zone_code || '');
                const seq = consumer.sequence != null && consumer.sequence !== '' ? consumer.sequence : (sess.sequence != null && sess.sequence !== '' ? sess.sequence : '');
                $('#printCard').text(seq !== '' ? String(seq) : '');
                $('#printMeter').text(consumer.meter_number || sess.meter_number || '');

                let rows = '';
                ledgers.forEach(function(ledger) {
                    const date = formatDate(ledger.date);
                    const trans = (ledger.trans || '').trim();
                    const ref = printReferenceOnly(ledger.trans, ledger.reference);
                    const reading = ledger.reading !== null && ledger.reading !== undefined && String(ledger.reading).trim() !== ''
                        ? String(ledger.reading) : '';
                    const vol = ledger.volume !== null && ledger.volume !== undefined && String(ledger.volume).trim() !== ''
                        ? String(ledger.volume) : '';
                    const othersComb = (parseFloat(ledger.others) || 0) + (parseFloat(ledger.penalty) || 0);
                    const billAmt = parseFloat(ledger.billamount) || 0;
                    const debit = parseFloat(ledger.debit) || 0;
                    const credit = parseFloat(ledger.credit) || 0;
                    const balance = ledger.balance !== null && ledger.balance !== undefined ? parseFloat(ledger.balance) : 0;
                    const uname = (ledger.username || '').toUpperCase();

                    rows += '<tr>' +
                        '<td class="c-date">' + date + '</td>' +
                        '<td class="c-trans">' + $('<div>').text(trans).html() + '</td>' +
                        '<td class="c-ref">' + $('<div>').text(ref).html() + '</td>' +
                        '<td class="c-num">' + $('<div>').text(reading).html() + '</td>' +
                        '<td class="c-num">' + $('<div>').text(vol).html() + '</td>' +
                        '<td class="c-num">' + formatPrintNum(othersComb, false) + '</td>' +
                        '<td class="c-num">' + formatPrintNum(billAmt, false) + '</td>' +
                        '<td class="c-num">' + formatPrintNum(debit, false) + '</td>' +
                        '<td class="c-num">' + formatPrintNum(credit, false) + '</td>' +
                        '<td class="c-num">' + formatCurrency(balance) + '</td>' +
                        '<td class="c-user">' + $('<div>').text(uname).html() + '</td>' +
                        '</tr>';
                });
                $('#ledgerPrintRows').html(rows);

                window.print();
            }

            // Display ledger data
            function displayLedgerData(data) {
                const ledgers = data.ledgers;
                const summary = data.summary;
                const consumer = data.consumer;

                // Update account badge and keep header in sync with ledger (fix: header showing wrong consumer)
                if (consumer) {
                    const accountNo = (consumer.account_no || consumer.account_number || '').toString().trim();
                    const accountName = (consumer.account_name || consumer.name || '').toString().trim();
                    const accountAddress = (consumer.address || consumer.address1 || '').toString().trim();
                    const badgeParts = [accountNo, accountName];
                    if (accountAddress) badgeParts.push(accountAddress);
                    $('#ledgerAccountBadge').text(`Account: ${badgeParts.filter(Boolean).join(' - ')}`);
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

            // Print button — official HWD consumer ledger layout
            $('#printLedgerBtn').on('click', function() {
                printLedgerOfficial();
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
