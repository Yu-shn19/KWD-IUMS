<!DOCTYPE html>
<html lang="en">
@include('partials.header')
@php
    $zones = $zones ?? collect();
    $selectedZone = $selectedZone ?? null;
    $fromMonthInput = $fromMonthInput ?? now()->subMonth()->format('Y-m');
    $toMonthInput = $toMonthInput ?? now()->format('Y-m');
    $fromMonth = $fromMonth ?? now()->subMonth()->startOfMonth();
    $toMonth = $toMonth ?? now()->startOfMonth();
    $chargePerConsumer = $chargePerConsumer ?? 10.00;
    $interest = $interest ?? 0;
    $description = $description ?? 'IT Services / System Generation';
    $invoiceRows = $invoiceRows ?? ($monthlyRows ?? collect());
    $detailRows = $detailRows ?? collect();
    $showDetail = $showDetail ?? false;
    $totals = $totals ?? ['consumers' => 0, 'subtotal' => 0, 'interest' => 0, 'total_due' => 0];
    $periodLabel = $fromMonth->format('F Y') . ($fromMonth->isSameMonth($toMonth) ? '' : ' – ' . $toMonth->format('F Y'));
@endphp

<style>
    .soa-stat {
        border-radius: 0.75rem;
        border: 1px solid rgba(226, 232, 240, 0.95);
        background: #fff;
        padding: 1rem 1.15rem;
    }
    .soa-stat .label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #6c757d;
        font-weight: 700;
    }
    .soa-stat .value {
        font-size: 1.35rem;
        font-weight: 700;
        color: #1b1e23;
        margin-top: 0.2rem;
    }

    .soa-invoice-preview {
        background: #fff;
        border: 1px solid #e3e6f0;
        border-radius: 0.35rem;
        padding: 1.75rem 1.5rem 1.25rem;
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
    }

    .soa-invoice-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 13px;
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
        border: 1.5px solid #000;
    }
    .soa-invoice-table th,
    .soa-invoice-table td {
        border: 1px solid #000;
        padding: 7px 10px;
        vertical-align: middle;
        background: #fff;
    }
    .soa-invoice-table thead th {
        font-weight: 700;
        text-align: center;
        background: #fff;
    }
    .soa-invoice-table .col-date { width: 18%; text-align: left; }
    .soa-invoice-table .col-desc { width: 36%; text-align: left; }
    .soa-invoice-table .col-qty { width: 12%; text-align: right; }
    .soa-invoice-table .col-x { width: 5%; text-align: center; }
    .soa-invoice-table .col-rate { width: 10%; text-align: right; }
    .soa-invoice-table .col-amt { width: 19%; text-align: right; }
    .soa-invoice-table thead th.col-date,
    .soa-invoice-table thead th.col-desc,
    .soa-invoice-table thead th.col-amt {
        text-align: center;
    }
    .soa-pad-row td {
        height: 30px;
    }

    .soa-invoice-footer {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-top: 18px;
        gap: 1.5rem;
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
    }
    .soa-thanks {
        font-style: normal;
        font-weight: 700;
        font-size: 14px;
        padding-top: 4px;
    }
    .soa-totals-wrap {
        min-width: 260px;
        width: 42%;
        max-width: 320px;
    }
    .soa-total-line {
        display: flex;
        justify-content: flex-end;
        align-items: baseline;
        gap: 28px;
        margin-bottom: 8px;
        font-size: 13px;
    }
    .soa-total-label {
        font-weight: 700;
        text-align: right;
        white-space: nowrap;
    }
    .soa-total-value {
        min-width: 110px;
        text-align: right;
        font-variant-numeric: tabular-nums;
        font-weight: 600;
    }
    .soa-total-due-box {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
        margin-top: 14px;
        border: 2.5px solid #000;
        padding: 8px 14px;
        font-size: 14px;
    }
    .soa-total-due-box .soa-total-label,
    .soa-total-due-box .soa-total-value {
        font-weight: 700;
        min-width: 0;
    }

    #soaPrintSheet {
        display: none;
    }

    @media print {
        @page {
            margin: 14mm 16mm;
            size: A4 portrait;
        }

        html, body {
            background: #fff !important;
            height: auto !important;
            overflow: visible !important;
        }

        body * {
            visibility: hidden !important;
        }

        #soaPrintSheet,
        #soaPrintSheet * {
            visibility: visible !important;
        }

        #soaPrintSheet {
            display: block !important;
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 0;
            margin: 0;
            background: #fff;
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
        }

        #wrapper,
        #sidebar,
        .navbar,
        .scroll-to-top,
        .no-print {
            display: none !important;
        }

        .soa-invoice-table {
            font-size: 12px !important;
        }
        .soa-thanks {
            font-size: 13px !important;
        }
        .soa-total-due-box {
            font-size: 13px !important;
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
                            <h1 class="h3 mb-1 text-gray-800">Statement of Account</h1>
                            <p class="mb-0 text-muted small">
                                Successful meter readings × ₱{{ number_format($chargePerConsumer, 2) }} per consumer (From–To period).
                            </p>
                        </div>
                        <div>
                            <button form="reportFilters" type="submit" class="btn btn-primary btn-sm mr-2">
                                <i class="fas fa-sync-alt mr-1"></i>Generate
                            </button>
                            <a href="{{ route('statement-of-account.export', request()->query()) }}" class="btn btn-success btn-sm mr-2">
                                <i class="fas fa-file-excel mr-1"></i>Export Excel
                            </a>
                            <button type="button" class="btn btn-danger btn-sm" onclick="window.print()">
                                <i class="fas fa-print mr-1"></i>Print SOA
                            </button>
                        </div>
                    </div>

                    @if (session('error'))
                        <div class="alert alert-danger no-print">{{ session('error') }}</div>
                    @endif

                    <div class="row mb-3 no-print">
                        <div class="col-lg-12">
                            <div class="card shadow-sm">
                                <div class="card-body py-3">
                                    <form id="reportFilters" method="GET" action="{{ route('statement-of-account') }}">
                                        <div class="form-row align-items-end">
                                            <div class="form-group col-md-2 mb-2 mb-md-0">
                                                <label class="small font-weight-bold mb-1">Zone / Route</label>
                                                <select name="zone" class="form-control form-control-sm">
                                                    <option value="">All Zones</option>
                                                    @foreach ($zones as $zone)
                                                        <option value="{{ $zone }}" {{ $zone === $selectedZone ? 'selected' : '' }}>Zone {{ $zone }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group col-md-2 mb-2 mb-md-0">
                                                <label class="small font-weight-bold mb-1">From Month</label>
                                                <input type="month" name="from_month" class="form-control form-control-sm" value="{{ $fromMonthInput }}" required>
                                            </div>
                                            <div class="form-group col-md-2 mb-2 mb-md-0">
                                                <label class="small font-weight-bold mb-1">To Month</label>
                                                <input type="month" name="to_month" class="form-control form-control-sm" value="{{ $toMonthInput }}" required>
                                            </div>
                                            <div class="form-group col-md-2 mb-2 mb-md-0">
                                                <label class="small font-weight-bold mb-1">Charge / Consumer (₱)</label>
                                                <input
                                                    type="number"
                                                    name="charge_per_consumer"
                                                    class="form-control form-control-sm"
                                                    min="0"
                                                    max="999999.99"
                                                    step="0.01"
                                                    value="{{ number_format($chargePerConsumer, 2, '.', '') }}"
                                                    required
                                                >
                                            </div>
                                            <div class="form-group col-md-2 mb-2 mb-md-0">
                                                <label class="small font-weight-bold mb-1">Interest (₱)</label>
                                                <input
                                                    type="number"
                                                    name="interest"
                                                    class="form-control form-control-sm"
                                                    min="0"
                                                    max="9999999.99"
                                                    step="0.01"
                                                    value="{{ number_format($interest, 2, '.', '') }}"
                                                >
                                            </div>
                                            <div class="form-group col-md-2 mb-0">
                                                <label class="small font-weight-bold mb-1">Description</label>
                                                <input
                                                    type="text"
                                                    name="description"
                                                    class="form-control form-control-sm"
                                                    maxlength="120"
                                                    value="{{ $description }}"
                                                >
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3 no-print">
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="soa-stat">
                                <div class="label">Successful Consumers</div>
                                <div class="value">{{ number_format($totals['consumers'] ?? 0) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="soa-stat">
                                <div class="label">Charge / Consumer</div>
                                <div class="value">₱{{ number_format($chargePerConsumer, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="soa-stat">
                                <div class="label">Subtotal</div>
                                <div class="value">₱{{ number_format($totals['subtotal'] ?? 0, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="soa-stat">
                                <div class="label">Total Due</div>
                                <div class="value text-primary">₱{{ number_format($totals['total_due'] ?? 0, 2) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3 no-print">
                        <div class="col-md-12">
                            <div class="alert alert-light border shadow-sm mb-0">
                                <strong>Zone:</strong> {{ $selectedZone ? 'Zone ' . $selectedZone : 'All Zones' }}
                                <span class="mx-3">|</span>
                                <strong>Period:</strong> {{ $periodLabel }}
                                <span class="mx-3">|</span>
                                <strong>Description:</strong> {{ $description }}
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4 no-print">
                        <div class="col-lg-12">
                            <div class="card shadow-sm">
                                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Invoice Preview</h6>
                                    <span class="small text-muted">{{ number_format($invoiceRows->count()) }} month line(s)</span>
                                </div>
                                <div class="card-body">
                                    <div class="soa-invoice-preview">
                                        @include('reports.system-report.partials.soa-invoice-body')
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($showDetail)
                        <div class="row no-print">
                            <div class="col-lg-12">
                                <div class="card shadow-sm mb-4">
                                    <div class="card-header py-3 d-flex align-items-center justify-content-between">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            Consumer Detail — {{ $fromMonth->format('F Y') }}
                                        </h6>
                                        <span class="small text-muted">{{ number_format($detailRows->count()) }} consumer(s)</span>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive" style="max-height: 520px; overflow: auto;">
                                            <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th class="text-center">Zone</th>
                                                        <th class="text-center">Account #</th>
                                                        <th>Account Name</th>
                                                        <th>Address</th>
                                                        <th class="text-center">SEDR #</th>
                                                        <th class="text-center">Reading Date</th>
                                                        <th class="text-center">Status</th>
                                                        <th class="text-right">Current Reading</th>
                                                        <th class="text-right">Consumption</th>
                                                        <th class="text-right">Charge (₱)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($detailRows as $row)
                                                        <tr>
                                                            <td class="text-center">{{ $row->zone ?? '—' }}</td>
                                                            <td class="text-center">{{ $row->account_number ?? '—' }}</td>
                                                            <td>{{ $row->account_name ?? '—' }}</td>
                                                            <td>{{ $row->address ?? '—' }}</td>
                                                            <td class="text-center">{{ $row->sedr_number ?? '—' }}</td>
                                                            <td class="text-center">{{ optional($row->reading_date)->format('m/d/Y') ?? '—' }}</td>
                                                            <td class="text-center text-capitalize">{{ $row->status ?? '—' }}</td>
                                                            <td class="text-right">{{ number_format((float) ($row->current_reading ?? 0), 0) }}</td>
                                                            <td class="text-right">{{ number_format((float) ($row->consumption ?? 0), 0) }}</td>
                                                            <td class="text-right font-weight-bold">{{ number_format($row->charge, 2) }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="10" class="text-center text-muted py-4">
                                                                No consumer detail for this reading month.
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                                @if ($detailRows->isNotEmpty())
                                                    <tfoot class="bg-light">
                                                        <tr>
                                                            <th colspan="9" class="text-right">TOTAL</th>
                                                            <th class="text-right">{{ number_format($detailRows->sum('charge'), 2) }}</th>
                                                        </tr>
                                                    </tfoot>
                                                @endif
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="row no-print">
                        <div class="col-lg-12">
                            <div class="card shadow-sm mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Report Notes</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="small text-muted mb-0">
                                        <li>Counts successful downloaded readings (has a current reading, or status <strong>completed</strong> / <strong>paid</strong>).</li>
                                        <li>Reading month uses schedule <strong>bill_month</strong>, falling back to reading date. Consumers are distinct account numbers per month.</li>
                                        <li>Amount = Successful Consumers × Charge per Consumer. Total Due = Subtotal + Interest.</li>
                                        <li>Period span is capped at 24 months. Consumer detail appears when From and To are the same month.</li>
                                        <li>Use <strong>Print SOA</strong> for a clean invoice (sidebar and filters hidden).</li>
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

    <a class="scroll-to-top rounded no-print" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    {{-- Dedicated print sheet outside app shell (matches invoice-only layout) --}}
    <div id="soaPrintSheet" aria-hidden="true">
        @include('reports.system-report.partials.soa-invoice-body')
    </div>
</body>
</html>
