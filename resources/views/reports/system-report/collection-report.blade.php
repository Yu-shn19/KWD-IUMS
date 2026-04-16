<!DOCTYPE html>
<html lang="en">
@include('partials.header')

<style>
    @media print {
        /* Hide browser URL and headers/footers from printed report */
        @page {
            margin: 0.4in 0.5in;
            size: A4 landscape;
        }

        /* Do not print URL after links or any URL in the report */
        a[href]::after {
            content: none !important;
        }
        #printView a[href]::after {
            content: none !important;
        }

        /* Hide all screen-only elements */
        #wrapper, #content-wrapper, .sidebar, nav, .navbar, 
        button, .btn, .nav-tabs, #filterForm, 
        .scroll-to-top, .card-footer, .tab-content > .tab-pane {
            display: none !important;
        }

        body {
            margin: 0;
            padding: 0;
            background: #fff;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.5pt;
            color: #222;
        }

        /* Print view container – breathing room */
        #printView {
            display: block !important;
            max-width: 100%;
        }

        /* Force clean page breaks between generated pages */
        .print-page {
            page-break-after: always;
            break-after: page;
            margin-top: 10px;
        }
        .print-page.is-last {
            page-break-after: auto;
            break-after: auto;
        }

        /* Print table: header repeats on each page. Column widths via colgroup – fit A4. */
        .print-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            page-break-inside: auto;
            table-layout: fixed;
            box-sizing: border-box;
        }

        .print-table td,
        .print-table th {
            box-sizing: border-box;
        }

        .print-table thead {
            display: table-header-group;
        }

        .print-table thead th,
        .print-table thead td {
            border: 1px solid #333;
            vertical-align: middle;
        }

        /* Report header block */
        .print-header-cell {
            padding: 12px 16px 14px 16px;
            vertical-align: top;
            border: none !important;
            background: #fff;
        }

        .print-header-inner {
            text-align: center;
            margin-bottom: 0;
            line-height: 1.3;
        }

        .print-header-logo {
            max-height: 52px;
            margin-bottom: 6px;
        }

        .print-header-inner h2 {
            margin: 4px 0 2px 0;
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 0.02em;
        }

        .print-header-inner h3 {
            margin: 2px 0;
            font-size: 11pt;
        }

        .print-header-inner h4 {
            margin: 6px 0 2px 0;
            font-size: 10pt;
            font-weight: 600;
        }

        /* Section titles */
        .print-section-title {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.03em;
            padding: 10px 16px !important;
            border: 1px solid #333 !important;
            border-bottom-width: 2px !important;
            background: #fafafa;
        }

        .print-subsection {
            font-size: 10pt;
            font-weight: bold;
            padding: 10px 16px !important;
            border: 1px solid #333 !important;
            background: #f5f5f5;
        }

        .print-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        /* Column headers – generous padding, no overlap; respect col widths */
        .print-table th {
            border: 1px solid #333;
            padding: 8px 6px;
            background-color: #eaeaea;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            font-size: 7.5pt;
            line-height: 1.35;
            word-wrap: break-word;
            overflow: hidden;
            min-width: 0;
        }

        /* Data cells – all content centered */
        .print-table td {
            border: 1px solid #333;
            padding: 5px 6px;
            vertical-align: middle;
            font-size: 7.5pt;
            line-height: 1.25;
            overflow: hidden;
            min-width: 0;
            text-align: center;
        }

        .print-table td.text-right,
        .print-table td.payor-cell {
            text-align: center;
        }

        /* Grand total row – clear separation */
        .grand-total-row td {
            font-weight: bold;
            background-color: #d8d8d8 !important;
            border-top: 2px solid #333 !important;
            padding: 10px 10px !important;
            font-size: 8.5pt;
        }

        /* Footer with signatures */
        .print-footer {
            margin-top: 40px;
            padding-top: 20px;
            page-break-inside: avoid;
            overflow: hidden;
            border-top: 1px solid #333;
        }

        .print-footer::after {
            content: '';
            display: table;
            clear: both;
        }

        .signature-line {
            width: 45%;
            text-align: center;
            margin-top: 20px;
        }

        .signature-line.left {
            float: left;
        }

        .signature-line.right {
            float: right;
        }

        .signature-line hr {
            border: none;
            border-top: 2px solid #333;
            margin: 0 15px 10px 15px;
        }

        .signature-name {
            font-weight: bold;
            margin-top: 4px;
            font-size: 9pt;
        }

        .signature-title {
            font-size: 8pt;
            color: #444;
        }
    }

    /* Hide print view on screen */
    #printView {
        display: none;
    }
</style>

<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        @include('partials.sidebar')
        <!-- Sidebar -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                @include('partials.navbar')
                <!-- Topbar -->

                <!-- Container Fluid-->
                <div class="container-fluid" id="container-wrapper">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Collection Report</h1>
                        <div>
                            <button type="button" class="btn btn-primary btn-sm mr-2" onclick="generateReport()">
                                <i class="fas fa-sync-alt mr-1"></i>Generate
                            </button>
                            <a href="{{ route('collection-report.export', request()->all()) }}" class="btn btn-success btn-sm mr-2">
                                <i class="fas fa-file-excel mr-1"></i>Export Excel
                            </a>
                            <button type="button" class="btn btn-danger btn-sm" onclick="window.print()">
                                <i class="fas fa-print mr-1"></i>Print
                            </button>
                        </div>
                    </div>

                    <!-- Filter Card -->
                    <form method="GET" action="{{ route('collection-report') }}" id="filterForm">
                        <div class="row mb-3">
                            <div class="col-lg-12">
                                <div class="card shadow-sm">
                                    <div class="card-body py-3">
                                        <div class="row align-items-end">
                                            <!-- Date Range From -->
                                            <div class="col-md-2">
                                                <label class="small font-weight-bold mb-1">Date From</label>
                                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                                            </div>

                                            <!-- Date Range To -->
                                            <div class="col-md-2">
                                                <label class="small font-weight-bold mb-1">Date To</label>
                                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                                            </div>

                                            <!-- Zone/Route -->
                                            <div class="col-md-2">
                                                <label class="small font-weight-bold mb-1">Zone / Route</label>
                                                <select name="zone" class="form-control form-control-sm">
                                                    <option value="">All Zones</option>
                                                    @foreach($zones as $zoneOption)
                                                        <option value="{{ $zoneOption }}" {{ $selectedZone == $zoneOption ? 'selected' : '' }}>{{ $zoneOption }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <!-- Collector -->
                                            <div class="col-md-2">
                                                <label class="small font-weight-bold mb-1">Collector</label>
                                                <select name="collector" class="form-control form-control-sm">
                                                    <option value="">All Collectors</option>
                                                    @foreach($collectors as $collectorOption)
                                                        <option value="{{ $collectorOption }}" {{ $selectedCollector == $collectorOption ? 'selected' : '' }}>{{ $collectorOption }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <!-- Payment Type -->
                                            <div class="col-md-2">
                                                <label class="small font-weight-bold mb-1">Payment Type</label>
                                                <select name="payment_type" class="form-control form-control-sm">
                                                    <option value="">All Types</option>
                                                    <option value="cash" {{ $selectedPaymentType == 'cash' ? 'selected' : '' }}>Cash</option>
                                                    <option value="check" {{ $selectedPaymentType == 'check' ? 'selected' : '' }}>Check</option>
                                                    <option value="online" {{ $selectedPaymentType == 'online' ? 'selected' : '' }}>Online</option>
                                                    <option value="bank_transfer" {{ $selectedPaymentType == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                                </select>
                                            </div>

                                            <!-- Status -->
                                            <div class="col-md-2">
                                                <label class="small font-weight-bold mb-1">Status</label>
                                                <select name="status" class="form-control form-control-sm">
                                                    <option value="">All Status</option>
                                                    <option value="paid" {{ $selectedStatus == 'paid' ? 'selected' : '' }}>Posted</option>
                                                    <option value="pending" {{ $selectedStatus == 'pending' ? 'selected' : '' }}>Pending</option>
                                                    <option value="cancelled" {{ $selectedStatus == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Report Tabs -->
                    <div class="row mb-2">
                        <div class="col-md-12">
                            <ul class="nav nav-tabs">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#detail">Detail</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#summaryCollector">Summary by Collector</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#summaryZone">Summary by Zone</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#summaryPaymentType">Summary by Payment Type</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#daily">Daily Summary</a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- Detail Tab -->
                        <div class="tab-pane fade show active" id="detail">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body p-0">
                                            <div class="table-responsive" style="max-height: 600px; overflow: auto;">
                                                <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;">
                                                    <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                                                        <tr>
                                                            <th class="text-center py-2 px-2" style="min-width: 40px;">#</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">OR Number</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Date</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Account No</th>
                                                            <th class="py-2 px-2" style="min-width: 180px;">Account Name</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 80px;">Zone</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Bill Month</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Amount</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Payment Type</th>
                                                            <th class="py-2 px-2" style="min-width: 150px;">Collector</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 80px;">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($detailRecords as $index => $record)
                                                            <tr>
                                                                <td class="text-center py-1">{{ $index + 1 }}</td>
                                                                <td class="text-center py-1">{{ $record['or_number'] }}</td>
                                                                <td class="text-center py-1">{{ $record['date'] }}</td>
                                                                <td class="text-center py-1">{{ $record['account_number'] }}</td>
                                                                <td class="py-1 px-2">{{ $record['account_name'] }}</td>
                                                                <td class="text-center py-1">{{ $record['zone'] }}</td>
                                                                <td class="text-center py-1">{{ $record['bill_month'] }}</td>
                                                                <td class="text-right py-1 px-2">{{ $record['amount'] }}</td>
                                                                <td class="text-center py-1">{{ $record['payment_type'] }}</td>
                                                                <td class="py-1 px-2">{{ $record['collector'] }}</td>
                                                                <td class="text-center py-1">
                                                                    @if(strtolower($record['status']) == 'paid')
                                                                        <span class="badge badge-success">Posted</span>
                                                                    @elseif(strtolower($record['status']) == 'pending')
                                                                        <span class="badge badge-warning">Pending</span>
                                                                    @else
                                                                        <span class="badge badge-danger">{{ $record['status'] }}</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="11" class="text-center py-4">
                                                                    <em class="text-muted">No collection records found for the selected criteria.</em>
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-light py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Collection Report: {{ \Carbon\Carbon::parse($dateFrom)->format('F d') }} - {{ \Carbon\Carbon::parse($dateTo)->format('d, Y') }}
                                                </small>
                                                <small class="text-success font-weight-bold">
                                                    Total Collections: ₱ {{ number_format($totalAmount, 2) }} | Transactions: {{ $totalTransactions }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary by Collector Tab -->
                        <div class="tab-pane fade" id="summaryCollector">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th class="text-center py-2 px-2">#</th>
                                                            <th class="py-2 px-2">Collector</th>
                                                            <th class="text-center py-2 px-2">Transactions</th>
                                                            <th class="text-right py-2 px-2">Total Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($summaryByCollector as $index => $summary)
                                                            <tr>
                                                                <td class="text-center py-1">{{ $index + 1 }}</td>
                                                                <td class="py-1 px-2">{{ $summary['collector'] }}</td>
                                                                <td class="text-center py-1">{{ $summary['transactions'] }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['total_amount'], 2) }}</td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="4" class="text-center py-4">
                                                                    <em class="text-muted">No data available.</em>
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary by Zone Tab -->
                        <div class="tab-pane fade" id="summaryZone">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th class="text-center py-2 px-2">#</th>
                                                            <th class="text-center py-2 px-2">Zone</th>
                                                            <th class="text-center py-2 px-2">Transactions</th>
                                                            <th class="text-right py-2 px-2">Total Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($summaryByZone as $index => $summary)
                                                            <tr>
                                                                <td class="text-center py-1">{{ $index + 1 }}</td>
                                                                <td class="text-center py-1">{{ $summary['zone'] }}</td>
                                                                <td class="text-center py-1">{{ $summary['transactions'] }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['total_amount'], 2) }}</td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="4" class="text-center py-4">
                                                                    <em class="text-muted">No data available.</em>
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary by Payment Type Tab -->
                        <div class="tab-pane fade" id="summaryPaymentType">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th class="text-center py-2 px-2">#</th>
                                                            <th class="py-2 px-2">Payment Type</th>
                                                            <th class="text-center py-2 px-2">Transactions</th>
                                                            <th class="text-right py-2 px-2">Total Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($summaryByPaymentType as $index => $summary)
                                                            <tr>
                                                                <td class="text-center py-1">{{ $index + 1 }}</td>
                                                                <td class="py-1 px-2">{{ $summary['payment_type'] }}</td>
                                                                <td class="text-center py-1">{{ $summary['transactions'] }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['total_amount'], 2) }}</td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="4" class="text-center py-4">
                                                                    <em class="text-muted">No data available.</em>
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Daily Summary Tab -->
                        <div class="tab-pane fade" id="daily">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th class="text-center py-2 px-2">#</th>
                                                            <th class="text-center py-2 px-2">Date</th>
                                                            <th class="text-center py-2 px-2">Transactions</th>
                                                            <th class="text-right py-2 px-2">Total Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($dailySummary as $index => $summary)
                                                            <tr>
                                                                <td class="text-center py-1">{{ $index + 1 }}</td>
                                                                <td class="text-center py-1">{{ $summary['date'] }}</td>
                                                                <td class="text-center py-1">{{ $summary['transactions'] }}</td>
                                                                <td class="text-right py-1 px-2">₱ {{ number_format($summary['total_amount'], 2) }}</td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="4" class="text-center py-4">
                                                                    <em class="text-muted">No data available.</em>
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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

    <!-- Print View - Only visible when printing. Heading + table header repeat on each page; only data rows change. -->
    <div id="printView">
        @php
            // Print pagination (fixed rows per printed page).
            // You can override via ?rows_per_page=12 in the URL when printing.
            // Default is intentionally conservative because the first page header is tall.
            $rowsPerPage = (int) request('rows_per_page', 14);
            $rowsPerPage = max(1, min($rowsPerPage, 60));
            // Let page 2+ fit a few more rows than page 1.
            $rowsPerPageNext = min($rowsPerPage + 9, 60);

            $serviceRevByOr = $serviceRevByOr ?? [];
            $detailRecordsArray = $detailRecords instanceof \Illuminate\Support\Collection ? $detailRecords->toArray() : (array) $detailRecords;
            $pages = [];
            if (!empty($detailRecordsArray)) {
                $firstPage = array_slice($detailRecordsArray, 0, $rowsPerPage);
                $pages[] = $firstPage;
                $remainingRecords = array_slice($detailRecordsArray, $rowsPerPage);
                if (!empty($remainingRecords)) {
                    $pages = array_merge($pages, array_chunk($remainingRecords, $rowsPerPageNext));
                }
            }
            if (empty($pages)) {
                $pages = [[]];
            }

            // Grand totals across all pages
            $grandTotal = 0;
            $totalCurrent = 0;
            $totalArrearsCY = 0;
            $totalArrearsPY = 0;
            $totalPenalty = 0;
            $totalMeterMaint = 0;
            $totalServiceRev = 0;
            $totalRebate = 0;
            $totalMisc = 0;
            $totalAJCs = 0;
        @endphp

        @foreach($pages as $pageIndex => $pageRecords)
            @php
                $isLastPage = ($pageIndex === count($pages) - 1);

                // Page totals (reset every page)
                $pageTotal = 0;
                $pageCurrent = 0;
                $pageArrearsCY = 0;
                $pageArrearsPY = 0;
                $pagePenalty = 0;
                $pageMeterMaint = 0;
                $pageServiceRev = 0;
                $pageRebate = 0;
                $pageMisc = 0;
                $pageAJCs = 0;
            @endphp

            <div class="print-page {{ $isLastPage ? 'is-last' : '' }}">
                <table class="print-table">
                    <colgroup>
                        <col style="width: 10%;">
                        <col style="width: 22%;">
                        <col style="width: 9%;">
                        <col style="width: 7.5%;">
                        <col style="width: 7.5%;">
                        <col style="width: 7.5%;">
                        <col style="width: 7.5%;">
                        <col style="width: 7.5%;">
                        <col style="width: 7.5%;">
                        <col style="width: 7.5%;">
                        <col style="width: 6%;">
                        <col style="width: 6%;">
                    </colgroup>
                    <thead>
                        @if($pageIndex === 0)
                            <tr>
                                <td colspan="12" class="print-header-cell">
                                    <div class="print-header-inner">
                                        <img src="{{ asset('WDMS/img/logo/logo.png') }}" alt="Hagonoy Water District" class="print-header-logo">
                                        <h2>HAGONOY WATER DISTRICT</h2>
                                        <h3>Guihing, Hagonoy</h3>
                                        <h4>CASHIER'S COLLECTION REPORT</h4>
                                        <h4>{{ \Carbon\Carbon::parse($dateFrom)->format('m/d/Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('m/d/Y') }}</h4>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="12" class="print-section-title" style="border: 1px solid #000; border-bottom-width: 2px; padding: 8px 12px;">ACCOUNTS CREDITED</td>
                            </tr>
                            <tr>
                                <td colspan="12" class="print-subsection" style="border: 1px solid #000; padding: 8px 12px;">A/R - CUSTOMER ({{ $selectedZone !== '' && $selectedZone !== null ? $selectedZone : 'All zone' }})</td>
                            </tr>
                        @endif
                        <tr>
                            <th rowspan="2">OR #</th>
                            <th rowspan="2">PAYOR</th>
                            <th rowspan="2">AMOUNT<br>COLLECTED</th>
                            <th colspan="7" style="text-align: center;">A/R - CUSTOMER ({{ $selectedZone !== '' && $selectedZone !== null ? $selectedZone : 'All zone' }})</th>
                            <th rowspan="2">Misc.</th>
                            <th rowspan="2">AJCs</th>
                        </tr>
                        <tr>
                            <th>Current</th>
                            <th>Arrears<br>(CY)</th>
                            <th>Arrears<br>(PY)</th>
                            <th>Penalty</th>
                            <th>Meter<br>Maintenance</th>
                            <th>Service Rev.<br>(648)</th>
                            <th>Rebate<br>(895)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pageRecords as $record)
                            @php
                                // Parse the amount
                                $amount = floatval(str_replace(['₱', ','], '', $record['amount'] ?? 0));

                                // Initialize breakdown values
                                $current = 0;
                                $arrearsCY = 0;
                                $arrearsPY = 0;
                                $penalty = 0;
                                $meterMaint = 0;
                                $serviceRev = 0;
                                $rebate = 0;
                                $misc = 0;
                                $ajcs = 0;

                                // Get payment details from consumer_payments
                                $payment = DB::table('consumer_payments as cp')
                                    ->where('cp.or_number', $record['or_number'] ?? null)
                                    ->first();

                                if ($payment) {
                                    // Service Rev. (648) = LRO only (from lro_ledger, provided by controller)
                                    $orNum = trim((string)($record['or_number'] ?? ''));
                                    $serviceRev = ($orNum !== '' && $orNum !== 'N/A' && isset($serviceRevByOr[$orNum]))
                                        ? (float) $serviceRevByOr[$orNum]
                                        : 0;

                                    // Only treat as sundry-only (full amount in Service Rev.) when there is no reading AND no stored breakdown
                                    $hasStoredBreakdown = ((float)($payment->penalty ?? 0) != 0
                                        || (float)($payment->meter_maintenance ?? 0) != 0
                                        || (float)($payment->arrears_cy ?? 0) != 0
                                        || (float)($payment->arrears_py ?? 0) != 0
                                        || (float)($payment->current_bill ?? 0) != 0);
                                    $readingId = $payment->reading_id ?? null;
                                    if (empty($readingId) && $amount > 0 && !$hasStoredBreakdown) {
                                        $serviceRev = $amount;
                                        $current = 0;
                                        $arrearsCY = 0;
                                        $arrearsPY = 0;
                                        $penalty = 0;
                                        $meterMaint = 0;
                                    } else {
                                        // Use consumer_payments only for print breakdown (no fallback calculation)
                                        $current = (float)($payment->current_bill ?? 0);
                                        $arrearsCY = (float)($payment->arrears_cy ?? 0);
                                        $arrearsPY = (float)($payment->arrears_py ?? 0);
                                        $penalty = (float)($payment->penalty ?? 0);
                                        $meterMaint = (float)($payment->meter_maintenance ?? 0);
                                        $rebate = (float)($payment->senior_citizen_discount ?? 0);
                                    }
                                } else {
                                    // No payment record found - show zeros for breakdown
                                    $current = 0;
                                    $arrearsCY = 0;
                                    $arrearsPY = 0;
                                    $penalty = 0;
                                    $meterMaint = 0;
                                    $rebate = 0;
                                }

                                // Page totals
                                $pageTotal += $amount;
                                $pageCurrent += $current;
                                $pageArrearsCY += $arrearsCY;
                                $pageArrearsPY += $arrearsPY;
                                $pagePenalty += $penalty;
                                $pageMeterMaint += $meterMaint;
                                $pageServiceRev += $serviceRev;
                                $pageRebate += $rebate;
                                $pageMisc += $misc;
                                $pageAJCs += $ajcs;

                                // Grand totals
                                $grandTotal += $amount;
                                $totalCurrent += $current;
                                $totalArrearsCY += $arrearsCY;
                                $totalArrearsPY += $arrearsPY;
                                $totalPenalty += $penalty;
                                $totalMeterMaint += $meterMaint;
                                $totalServiceRev += $serviceRev;
                                $totalRebate += $rebate;
                                $totalMisc += $misc;
                                $totalAJCs += $ajcs;
                            @endphp

                            <tr>
                                <td class="text-center">{{ $record['or_number'] ?? '' }}</td>
                                <td class="text-center">{{ $record['account_name'] ?? '' }}</td>
                                <td class="text-center">{{ number_format($amount, 2) }}</td>
                                <td class="text-center">{{ $current > 0 ? number_format($current, 2) : '' }}</td>
                                <td class="text-center">{{ $arrearsCY > 0 ? number_format($arrearsCY, 2) : '' }}</td>
                                <td class="text-center">{{ $arrearsPY > 0 ? number_format($arrearsPY, 2) : '' }}</td>
                                <td class="text-center">{{ $penalty > 0 ? number_format($penalty, 2) : '' }}</td>
                                <td class="text-center">{{ $meterMaint > 0 ? number_format($meterMaint, 2) : '' }}</td>
                                <td class="text-center">{{ $serviceRev > 0 ? number_format($serviceRev, 2) : '' }}</td>
                                <td class="text-center">{{ $rebate > 0 ? number_format($rebate, 2) : '' }}</td>
                                <td class="text-center">{{ $misc > 0 ? number_format($misc, 2) : '' }}</td>
                                <td class="text-center">{{ $ajcs > 0 ? number_format($ajcs, 2) : '' }}</td>
                            </tr>
                        @endforeach

                        @if(!$isLastPage)
                            <!-- Total per page row -->
                            <tr class="grand-total-row">
                                <td colspan="2" class="text-center">Total Amount:</td>
                                <td class="text-center">{{ number_format($pageTotal, 2) }}</td>
                                <td class="text-center">{{ $pageCurrent > 0 ? number_format($pageCurrent, 2) : '' }}</td>
                                <td class="text-center">{{ $pageArrearsCY > 0 ? number_format($pageArrearsCY, 2) : '' }}</td>
                                <td class="text-center">{{ $pageArrearsPY > 0 ? number_format($pageArrearsPY, 2) : '' }}</td>
                                <td class="text-center">{{ $pagePenalty > 0 ? number_format($pagePenalty, 2) : '' }}</td>
                                <td class="text-center">{{ $pageMeterMaint > 0 ? number_format($pageMeterMaint, 2) : '' }}</td>
                                <td class="text-center">{{ $pageServiceRev > 0 ? number_format($pageServiceRev, 2) : '' }}</td>
                                <td class="text-center">{{ $pageRebate > 0 ? number_format($pageRebate, 2) : '' }}</td>
                                <td class="text-center">{{ $pageMisc > 0 ? number_format($pageMisc, 2) : '' }}</td>
                                <td class="text-center">{{ $pageAJCs > 0 ? number_format($pageAJCs, 2) : '' }}</td>
                            </tr>
                        @else
                            <!-- Grand Total (only on last page) -->
                            <tr class="grand-total-row">
                                <td colspan="2" class="text-center">Grand Total :</td>
                                <td class="text-center">{{ number_format($grandTotal, 2) }}</td>
                                <td class="text-center">{{ $totalCurrent > 0 ? number_format($totalCurrent, 2) : '' }}</td>
                                <td class="text-center">{{ $totalArrearsCY > 0 ? number_format($totalArrearsCY, 2) : '' }}</td>
                                <td class="text-center">{{ $totalArrearsPY > 0 ? number_format($totalArrearsPY, 2) : '' }}</td>
                                <td class="text-center">{{ $totalPenalty > 0 ? number_format($totalPenalty, 2) : '' }}</td>
                                <td class="text-center">{{ $totalMeterMaint > 0 ? number_format($totalMeterMaint, 2) : '' }}</td>
                                <td class="text-center">{{ $totalServiceRev > 0 ? number_format($totalServiceRev, 2) : '' }}</td>
                                <td class="text-center">{{ $totalRebate > 0 ? number_format($totalRebate, 2) : '' }}</td>
                                <td class="text-center">{{ $totalMisc > 0 ? number_format($totalMisc, 2) : '' }}</td>
                                <td class="text-center">{{ $totalAJCs > 0 ? number_format($totalAJCs, 2) : '' }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endforeach

        <div class="print-footer">
            <div class="signature-line left">
                <hr>
                <div class="signature-name">FLORAME L. FLORA</div>
                <div class="signature-title">Cashier D</div>
            </div>
            <div class="signature-line right">
                <hr>
                <div class="signature-name">_____________________</div>
                <div class="signature-title">Approved By</div>
            </div>
        </div>
    </div>

    <script>
        function generateReport() {
            document.getElementById('filterForm').submit();
        }
    </script>
</body>
</html>

    