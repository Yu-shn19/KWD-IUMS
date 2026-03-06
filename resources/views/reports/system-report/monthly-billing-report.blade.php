<!DOCTYPE html>
<html lang="en">
@include('partials.header')
@php
    $zones = $zones ?? [];
    $selectedZone = $selectedZone ?? null;
    $billMonth = $billMonth ?? \Carbon\Carbon::now();
    $billMonthInput = $billMonthInput ?? $billMonth->format('Y-m');
    $asOf = $asOf ?? \Carbon\Carbon::now();
    $records = $records ?? collect();
    $totals = $totals ?? ['accounts' => 0, 'consumption' => 0, 'current_bill' => 0, 'arrears' => 0, 'total_amount' => 0];
    $summaryByCategory = $summaryByCategory ?? collect();
@endphp

<style>
    /* Print styles - hide everything except the table */
    @media print {
        /* Hide sidebar, navbar, and elements marked as no-print */
        #sidebar,
        .navbar,
        .no-print,
        .no-print * {
            display: none !important;
        }
        
        /* Show print header */
        .print-header {
            display: block !important;
            margin-bottom: 15px !important;
            padding: 10px !important;
            border-bottom: 2px solid #000 !important;
            page-break-after: avoid;
        }
        
        /* Ensure table container is visible */
        .print-table-container {
            display: block !important;
            width: 100% !important;
        }
        
        /* Make table responsive wrapper visible */
        .print-table-container .table-responsive {
            display: block !important;
            width: 100% !important;
            max-height: none !important;
            overflow: visible !important;
            height: auto !important;
        }
        
        /* Ensure table and all its elements are visible */
        .print-table-container table {
            width: 100% !important;
            border-collapse: collapse !important;
            font-size: 8px !important;
            margin: 0 !important;
            display: table !important;
        }
        
        .print-table-container table thead tr,
        .print-table-container table tbody tr,
        .print-table-container table tfoot tr {
            display: table-row !important;
        }
        
        .print-table-container table thead {
            display: table-header-group !important;
        }
        
        .print-table-container table tbody {
            display: table-row-group !important;
        }
        
        .print-table-container table tfoot {
            display: table-footer-group !important;
        }
        
        .print-table-container table tr {
            page-break-inside: avoid;
        }
        
        .print-table-container table th,
        .print-table-container table td {
            border: 1px solid #000 !important;
            padding: 4px !important;
        }
        
        .print-table-container table th {
            background-color: #f0f0f0 !important;
            font-weight: bold !important;
        }
        
        /* Remove card styling */
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        
        .card-header {
            display: none !important;
        }
        
        /* Page settings */
        @page {
            margin: 1cm;
            size: A4 landscape;
        }
    }
</style>

<body id="page-top">
    <div id="wrapper">
        @include('partials.sidebar')
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('partials.navbar')

                <div class="container-fluid" id="container-wrapper">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
                        <div>
                            <h1 class="h3 mb-1 text-gray-800">Monthly Billing Report</h1>
                            <p class="mb-0 text-muted small">Generated report is based on meter reading schedules saved in the system.</p>
                        </div>
                        <div>
                            <button form="reportFilters" type="submit" class="btn btn-primary btn-sm mr-2">
                                <i class="fas fa-sync-alt mr-1"></i>Generate
                            </button>
                            <a href="{{ route('monthly-billing-report.export', array_merge(request()->all(), ['format' => 'excel'])) }}" class="btn btn-success btn-sm mr-2">
                                <i class="fas fa-file-excel mr-1"></i>Export Excel
                            </a>
                            <button type="button" class="btn btn-danger btn-sm" onclick="window.print()">
                                <i class="fas fa-print mr-1"></i>Print
                            </button>
                        </div>
                    </div>

                    <div class="row mb-3 no-print">
                        <div class="col-lg-12">
                            <div class="card shadow-sm">
                                <div class="card-body py-3">
                                    <form id="reportFilters" method="GET" action="{{ route('monthly-billing-report') }}">
                                        <div class="form-row align-items-end">
                                            <div class="form-group col-md-2">
                                                <label class="small font-weight-bold mb-1">Zone / Route</label>
                                                <select name="zone" class="form-control form-control-sm">
                                                    <option value="">All Zones</option>
                                                    @foreach ($zones as $zone)
                                                        <option value="{{ $zone }}" {{ $zone === $selectedZone ? 'selected' : '' }}>Zone {{ $zone }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="form-group col-md-3">
                                                <label class="small font-weight-bold mb-1">Bill Month</label>
                                                <input type="month" name="bill_month" class="form-control form-control-sm" value="{{ $billMonthInput }}">
                                            </div>

                                            <div class="form-group col-md-2">
                                                <label class="small font-weight-bold mb-1">As of</label>
                                                <input type="date" name="as_of" class="form-control form-control-sm" value="{{ $asOf->format('Y-m-d') }}">
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3 no-print">
                        <div class="col-md-12">
                            <div class="alert alert-light border shadow-sm mb-0">
                                <div class="d-flex flex-wrap align-items-center justify-content-between">
                                    <div class="mb-2 mb-md-0">
                                        <strong>Selected Zone:</strong> {{ $selectedZone ? 'Zone ' . $selectedZone : 'All Zones' }}
                                        <span class="mx-3">|</span>
                                        <strong>Bill Month:</strong> {{ $billMonth->format('F Y') }}
                                        <span class="mx-3">|</span>
                                        <strong>As of:</strong> {{ $asOf->format('F d, Y') }}
                                    </div>
                                    <div class="text-muted small">
                                        Total Accounts: <strong>{{ number_format($totals['accounts']) }}</strong>
                                        <span class="mx-3">|</span>
                                        Total Consumption: <strong>{{ number_format($totals['consumption']) }} m³</strong>
                                        <span class="mx-3">|</span>
                                        Total Amount: <strong>₱{{ number_format($totals['total_amount'], 2) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card shadow-sm mb-4">
                                <div class="card-header py-3 d-flex align-items-center justify-content-between no-print">
                                    <h6 class="m-0 font-weight-bold text-primary">Billing Detail</h6>
                                    <span class="small text-muted">Showing {{ number_format($totals['accounts']) }} record(s)</span>
                                </div>
                                <div class="card-body p-0 print-table-container">
                                    <div class="print-header mb-3" style="display: none;">
                                        <h4 class="mb-1">Monthly Billing Report</h4>
                                        <p class="mb-1"><strong>Zone:</strong> {{ $selectedZone ? 'Zone ' . $selectedZone : 'All Zones' }} | <strong>Bill Month:</strong> {{ $billMonth->format('F Y') }} | <strong>As of:</strong> {{ $asOf->format('F d, Y') }}</p>
                                        <p class="mb-0"><strong>Total Accounts:</strong> {{ number_format($totals['accounts']) }} | <strong>Total Consumption:</strong> {{ number_format($totals['consumption']) }} m³ | <strong>Total Amount:</strong> ₱{{ number_format($totals['total_amount'], 2) }}</p>
                                    </div>
                                    <div class="table-responsive" style="max-height: 600px; overflow: auto;">
                                        <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;" id="billingTable">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th class="text-center">Zone</th>
                                                    <th class="text-center">Bill Month</th>
                                                    <th class="text-center">Category</th>
                                                    <th class="text-center">SEDR #</th>
                                                    <th class="text-center">Account #</th>
                                                    <th>Account Name</th>
                                                    <th>Address</th>
                                                    <th class="text-center">Previous Reading Date</th>
                                                    <th class="text-center">Current Reading Date</th>
                                                    <th class="text-right">Previous Reading</th>
                                                    <th class="text-right">Current Reading</th>
                                                    <th class="text-right">Consumption</th>
                                                    <th class="text-right">Current Bill</th>
                                                    <th class="text-right">Arrears</th>
                                                    <th class="text-right">Total Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($records as $record)
                                                    @php
                                                        $consumptionValue = (float) ($record->computed_consumption ?? $record->consumption ?? 0);
                                                        $currentBillValue = (float) ($record->computed_current_bill ?? $record->current_bill ?? 0);
                                                        $arrearsValue = (float) ($record->computed_arrears ?? $record->arrears ?? 0);
                                                        $totalValue = (float) ($record->computed_total ?? $record->total_amount ?? ($currentBillValue + $arrearsValue));
                                                    @endphp
                                                    <tr>
                                                        <td class="text-center">{{ $record->zone ?? '—' }}</td>
                                                        <td class="text-center">{{ optional($record->bill_month)->format('m-Y') }}</td>
                                                        <td class="text-center">{{ $record->category ?? '—' }}</td>
                                                        <td class="text-center">{{ $record->sedr_number ?? '—' }}</td>
                                                        <td class="text-center">{{ $record->account_number ?? '—' }}</td>
                                                        <td>{{ $record->account_name ?? optional($record->consumer)->full_name ?? '—' }}</td>
                                                        <td>{{ $record->address ?? optional($record->consumer)->address ?? '—' }}</td>
                                                        <td class="text-center">{{ optional($record->previous_reading_date)->format('m/d/Y') ?? '—' }}</td>
                                                        <td class="text-center">{{ optional($record->bill_date)->format('m/d/Y') ?? optional($record->reading_date)->format('m/d/Y') ?? '—' }}</td>
                                                        <td class="text-right">{{ number_format((float) ($record->previous_reading ?? 0), 0) }}</td>
                                                        <td class="text-right">{{ number_format((float) ($record->current_reading ?? 0), 0) }}</td>
                                                        <td class="text-right">{{ number_format($consumptionValue, 0) }}</td>
                                                        <td class="text-right">₱{{ number_format($currentBillValue, 2) }}</td>
                                                        <td class="text-right">₱{{ number_format($arrearsValue, 2) }}</td>
                                                        <td class="text-right font-weight-bold">₱{{ number_format($totalValue, 2) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="15" class="text-center text-muted py-4">No billing records found for the selected criteria.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                            <tfoot class="bg-light">
                                                <tr>
                                                    <th colspan="11" class="text-right font-weight-bold">Totals</th>
                                                    <th class="text-right font-weight-bold">{{ number_format($totals['consumption'], 0) }}</th>
                                                    <th class="text-right font-weight-bold">₱{{ number_format($totals['current_bill'], 2) }}</th>
                                                    <th class="text-right font-weight-bold">₱{{ number_format($totals['arrears'], 2) }}</th>
                                                    <th class="text-right font-weight-bold">₱{{ number_format($totals['total_amount'], 2) }}</th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row no-print">
                        <div class="col-md-6">
                            <div class="card shadow-sm mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Summary by Category</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Category</th>
                                                    <th class="text-right">Accounts</th>
                                                    <th class="text-right">Consumption</th>
                                                    <th class="text-right">Current Bill</th>
                                                    <th class="text-right">Total Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($summaryByCategory as $category => $summary)
                                                    <tr>
                                                        <td>{{ $category }}</td>
                                                        <td class="text-right">{{ number_format($summary['accounts']) }}</td>
                                                        <td class="text-right">{{ number_format($summary['consumption'], 0) }} m³</td>
                                                        <td class="text-right">₱{{ number_format($summary['current_bill'], 2) }}</td>
                                                        <td class="text-right">₱{{ number_format($summary['total_amount'], 2) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-3">No data available.</td>
                                                    </tr>
                                                @endforelse
                                                @if($summaryByCategory->isNotEmpty())
                                                    <tr class="table-primary font-weight-bold">
                                                        <td>TOTAL</td>
                                                        <td class="text-right">{{ number_format($totals['accounts']) }}</td>
                                                        <td class="text-right">{{ number_format($totals['consumption'], 0) }} m³</td>
                                                        <td class="text-right">₱{{ number_format($totals['current_bill'], 2) }}</td>
                                                        <td class="text-right">₱{{ number_format($totals['total_amount'], 2) }}</td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card shadow-sm mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Report Notes</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="small text-muted mb-0">
                                        <li>Billing records are derived from meter reading schedules marked for the selected bill month.</li>
                                        <li>Consumption values represent cubic meters calculated from the current and previous readings.</li>
                                        <li>Total amount includes current bill plus arrears for each account.</li>
                                        <li>Filters above refresh the dataset instantly using values already stored in the database.</li>
                                    </ul>
                                </div>
                            </div>
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
</body>
</html>

