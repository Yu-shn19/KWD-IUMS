<!DOCTYPE html>
<html lang="en">
@include('partials.header')
@php
    use App\Services\ConsumerLedgerCardBuilder;

    $zones = $zones ?? [];
    $filters = $filters ?? [
        'status' => '',
        'zone' => '',
        'as_of' => \Illuminate\Support\Carbon::now()->format('Y-m-d'),
        'account_no' => '',
    ];
    $selectedConsumer = $selectedConsumer ?? null;
    $ledgerRows = $ledgerRows ?? collect();
    $consumerList = $consumerList ?? collect();
    $consumerCount = $consumerCount ?? 0;

    $fmt = fn ($v) => ConsumerLedgerCardBuilder::formatMoney(is_numeric($v) ? (float) $v : 0);
@endphp

<style>
    .ledger-card-table {
        font-size: 11px;
    }
    .ledger-card-table thead th {
        vertical-align: middle;
        text-align: center;
        white-space: nowrap;
    }
    .ledger-card-table tbody td.num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .ledger-card-meta dt {
        font-weight: 700;
        font-size: 0.8rem;
    }
    .ledger-card-meta dd {
        margin-bottom: 0.35rem;
    }
    @media print {
        #sidebar,
        .navbar,
        .no-print,
        .scroll-to-top {
            display: none !important;
        }
        #content-wrapper {
            margin-left: 0 !important;
        }
        .ledger-print-block {
            page-break-inside: avoid;
        }
        .ledger-card-table {
            font-size: 9px;
        }
        .ledger-card-table th,
        .ledger-card-table td {
            border: 1px solid #000 !important;
            padding: 3px 4px !important;
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
                        <h1 class="h3 mb-0 text-gray-800">Consumer Ledger Report</h1>
                        <div>
                            <a href="{{ route('service-request-report', request()->query()) }}" class="btn btn-primary btn-sm mr-2">
                                <i class="fas fa-sync-alt mr-1"></i>Generate
                            </a>
                            <a href="{{ route('service-request-report.export', request()->query()) }}" class="btn btn-success btn-sm mr-2">
                                <i class="fas fa-file-excel mr-1"></i>Export Excel
                            </a>
                            <button type="button" class="btn btn-danger btn-sm" onclick="window.print()">
                                <i class="fas fa-print mr-1"></i>Print
                            </button>
                        </div>
                    </div>

                    @if (session('error'))
                        <div class="alert alert-danger no-print">{{ session('error') }}</div>
                    @endif

                    <form method="GET" action="{{ route('service-request-report') }}" class="no-print mb-3">
                        <div class="card shadow-sm">
                            <div class="card-body py-3">
                                <div class="row align-items-end">
                                    <div class="col-md-3">
                                        <label class="small font-weight-bold mb-1">Status</label>
                                        <select name="status" class="form-control form-control-sm">
                                            <option value="">All Status</option>
                                            <option value="A - ACTIVE" @selected(($filters['status'] ?? '') === 'A - ACTIVE')>A - ACTIVE</option>
                                            <option value="P - PENDING" @selected(($filters['status'] ?? '') === 'P - PENDING')>P - PENDING</option>
                                            <option value="X - DISCONNECTED" @selected(($filters['status'] ?? '') === 'X - DISCONNECTED')>X - DISCONNECTED</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small font-weight-bold mb-1">Zone</label>
                                        <select name="zone" class="form-control form-control-sm" required>
                                            <option value="">Select Zone</option>
                                            @foreach ($zones as $zoneOption)
                                                <option value="{{ $zoneOption }}" @selected(($filters['zone'] ?? '') === (string) $zoneOption)>{{ $zoneOption }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small font-weight-bold mb-1">As of</label>
                                        <input type="date" name="as_of" class="form-control form-control-sm"
                                               value="{{ $filters['as_of'] ?? '' }}" required>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-filter mr-1"></i>Apply Filters
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        Ledger shows all transactions through the as-of date. Excel export creates one sheet per consumer (max 500). Use <strong>View ledger</strong> to preview one account.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </form>

                    @if ($selectedConsumer)
                        <div class="card shadow-sm mb-4 ledger-print-block">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Consumer Ledger Card</h6>
                            </div>
                            <div class="card-body">
                                <dl class="row ledger-card-meta mb-3">
                                    <div class="col-md-4">
                                        <dt>Consumer Name</dt>
                                        <dd>{{ $selectedConsumer->account_name ?? '—' }}</dd>
                                    </div>
                                    <div class="col-md-4">
                                        <dt>Account Number</dt>
                                        <dd>{{ $selectedConsumer->account_no ?? '—' }}</dd>
                                    </div>
                                    <div class="col-md-4">
                                        <dt>Meter No.</dt>
                                        <dd>{{ filled($selectedConsumer->meter_number) ? $selectedConsumer->meter_number : '' }}</dd>
                                    </div>
                                    <div class="col-md-4">
                                        <dt>As of</dt>
                                        <dd>{{ $filters['as_of'] ?? '—' }}</dd>
                                    </div>
                                </dl>

                                <div class="table-responsive">
                                    @include('reports.system-report.partials.consumer-ledger-card-table', ['ledgerRows' => $ledgerRows])
                                </div>
                            </div>
                            <div class="card-footer bg-light py-2 small text-muted d-flex justify-content-between flex-wrap">
                                <span>
                                    Transactions: {{ $ledgerRows->count() }}
                                    | As of: {{ $filters['as_of'] ?? '' }}
                                    @if (!empty($filters['status']))
                                        | Status: {{ $filters['status'] }}
                                    @endif
                                </span>
                                @php
                                    $footerBalance = $ledgerRows->isNotEmpty()
                                        ? (float) ($ledgerRows->last()['total_balance'] ?? 0)
                                        : 0;
                                @endphp
                                <span>
                                    Current Balance:
                                    <strong class="{{ $footerBalance > 0 ? 'text-danger' : 'text-success' }}">
                                        ₱ {{ \App\Services\ConsumerLedgerCardBuilder::formatMoney($footerBalance, false) }}
                                    </strong>
                                </span>
                            </div>
                        </div>
                    @elseif (($filters['zone'] ?? '') !== '')
                        <div class="card shadow-sm mb-4 no-print">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Consumers in selection ({{ $consumerCount }})</h6>
                                <a href="{{ route('service-request-report.export', request()->query()) }}" class="btn btn-success btn-sm">
                                    <i class="fas fa-file-excel mr-1"></i>Export all to Excel
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 400px;">
                                    <table class="table table-sm table-bordered table-hover mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Account No.</th>
                                                <th>Consumer Name</th>
                                                <th>Meter No.</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($consumerList as $c)
                                                <tr>
                                                    <td>{{ $c->account_no }}</td>
                                                    <td>{{ $c->account_name }}</td>
                                                    <td>{{ filled($c->meter_number) ? $c->meter_number : '' }}</td>
                                                    <td class="text-center">
                                                        <a href="{{ route('service-request-report', array_merge(request()->query(), ['account_no' => $c->account_no])) }}"
                                                           class="btn btn-outline-primary btn-xs btn-sm">View ledger</a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted py-4">No consumers found.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @if ($consumerCount > 500)
                                <div class="card-footer small text-warning">
                                    {{ $consumerCount }} accounts match. Excel export is limited to 500 per run — narrow Status or Zone to export.
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="alert alert-info no-print">
                            <i class="fas fa-info-circle mr-2"></i>
                            Select <strong>Status</strong>, <strong>Zone</strong>, and <strong>As of</strong>, then click Apply Filters.
                            Use <strong>Export Excel</strong> to download <code>Consumer_Ledger_Report.xlsx</code> with one sheet per consumer.
                        </div>
                    @endif
                </div>
            </div>
            @include('partials.footer')
        </div>
    </div>

    <a class="scroll-to-top rounded no-print" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>
</body>
</html>
