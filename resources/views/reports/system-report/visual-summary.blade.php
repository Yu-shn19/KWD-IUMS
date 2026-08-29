<!DOCTYPE html>
<html lang="en">
@include('partials.header')

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
                    @php
                        $totalConsumers = $totalConsumers ?? 0;
                        $monthlyRevenue = (float) ($monthlyRevenue ?? 0);
                        $collectionRate = (float) ($collectionRate ?? 0);
                        $pendingArrears = (float) ($pendingArrears ?? 0);
                        $revenueChartLabels = $revenueChartLabels ?? [];
                        $revenueChartData = $revenueChartData ?? [];
                        $consumptionChartLabels = $consumptionChartLabels ?? [];
                        $consumptionChartData = $consumptionChartData ?? [];
                        $accountsChartLabels = $accountsChartLabels ?? [];
                        $accountsChartData = $accountsChartData ?? [];
                        $statusChartData = $statusChartData ?? [0, 0, 0, 0];
                        $zoneChartLabels = $zoneChartLabels ?? ['—'];
                        $zoneChartData = $zoneChartData ?? [0];
                        $zoneUnpaidChartLabels = $zoneUnpaidChartLabels ?? ['—'];
                        $zoneUnpaidChartData = $zoneUnpaidChartData ?? [0];
                        $zoneDisconnectionChartLabels = $zoneDisconnectionChartLabels ?? ['—'];
                        $zoneDisconnectionChartData = $zoneDisconnectionChartData ?? [0];
                        $totalDisconnectionAccounts = (int) ($totalDisconnectionAccounts ?? 0);
                        $topConsumption = $topConsumption ?? collect();
                        $topOutstanding = $topOutstanding ?? collect();
                        $topTablesMonthLabel = $topTablesMonthLabel ?? \Illuminate\Support\Carbon::now()->format('F Y');
                        $collectionBarWidth = max(0, min(100, $collectionRate));
                        $zoneOptions = $zoneOptions ?? collect();
                        $filters = $filters ?? [
                            'zone_route' => '',
                            'bill_month' => \Illuminate\Support\Carbon::now()->format('Y-m'),
                            'status_month' => \Illuminate\Support\Carbon::now()->format('Y-m'),
                        ];
                        $sortZoneChartByNumber = static function (array $labels, array $data): array {
                            $zoneNumber = static function (string $label): int {
                                if (preg_match('/(\d+)/', $label, $matches)) {
                                    return (int) $matches[1];
                                }

                                return PHP_INT_MAX;
                            };
                            $items = [];
                            foreach ($labels as $i => $label) {
                                if ((string) $label === '—') {
                                    continue;
                                }
                                $items[] = [
                                    'label' => (string) $label,
                                    'value' => (float) ($data[$i] ?? 0),
                                ];
                            }
                            usort($items, fn ($a, $b) => $zoneNumber($a['label']) <=> $zoneNumber($b['label']));
                            if ($items === []) {
                                return [['—'], [0]];
                            }

                            return [array_column($items, 'label'), array_column($items, 'value')];
                        };
                        [$zoneChartLabelsSorted, $zoneChartDataSorted] = $sortZoneChartByNumber($zoneChartLabels, $zoneChartData);
                        [$zoneUnpaidChartLabelsSorted, $zoneUnpaidChartDataSorted] = $sortZoneChartByNumber($zoneUnpaidChartLabels, $zoneUnpaidChartData);
                        [$zoneDisconnectionChartLabelsSorted, $zoneDisconnectionChartDataSorted] = $sortZoneChartByNumber(
                            $zoneDisconnectionChartLabels,
                            array_map('intval', $zoneDisconnectionChartData)
                        );
                    @endphp

                    <style>
                        .visual-summary-filters {
                            background: #fff;
                            border: 1px solid #e3e6f0;
                            border-radius: 12px;
                            padding: 1.25rem 1.5rem;
                            box-shadow: 0 0.125rem 0.25rem rgba(58, 59, 69, 0.08);
                            margin-bottom: 1.5rem;
                        }
                        .visual-summary-filters label {
                            font-weight: 700;
                            color: #2c3e50;
                            font-size: 0.8rem;
                            margin-bottom: 0.35rem;
                        }
                        .visual-summary-filters .form-control {
                            border-radius: 8px;
                            border-color: #d1d3e2;
                        }
                        .vs-zone-card {
                            border: none;
                            border-radius: 0.65rem;
                            box-shadow: 0 0.15rem 1.75rem rgba(58, 59, 69, 0.1);
                            height: 100%;
                        }
                        .vs-zone-card .card-header {
                            background: #fff;
                            border-bottom: 1px solid #eaecf4;
                            padding: 1rem 1.15rem 0.65rem;
                        }
                        .vs-zone-card .card-title-text {
                            font-size: 0.95rem;
                            font-weight: 700;
                            color: #2e59d9;
                            line-height: 1.35;
                            margin: 0;
                        }
                        .vs-zone-card .card-title-text .fa-info-circle {
                            color: #b7b9cc;
                            font-size: 0.8rem;
                            margin-left: 0.25rem;
                        }
                        .vs-zone-card .card-subtitle {
                            font-size: 0.78rem;
                            color: #858796;
                            margin-top: 0.35rem;
                        }
                        .vs-zone-card .card-body {
                            padding: 0.75rem 1rem 0.5rem;
                        }
                        .vs-zone-chart-wrap {
                            position: relative;
                            min-height: 280px;
                        }
                        .vs-zone-chart-wrap-wide {
                            min-height: 300px;
                        }
                        .vs-zone-legend {
                            text-align: center;
                            font-size: 0.78rem;
                            color: #858796;
                            padding: 0.35rem 0 0.85rem;
                        }
                        .vs-zone-legend .legend-swatch {
                            display: inline-block;
                            width: 0.65rem;
                            height: 0.65rem;
                            border-radius: 2px;
                            margin-right: 0.35rem;
                            vertical-align: middle;
                        }
                        .vs-consumption-chart-wrap {
                            position: relative;
                            height: 320px;
                        }
                        .vs-zone-discon-modal-dialog {
                            max-width: min(96vw, 1420px);
                            width: 96vw;
                            margin: 1.25rem auto;
                        }
                        #zoneDisconnectionModal .modal-body .table th,
                        #zoneDisconnectionModal .modal-body .table td {
                            white-space: nowrap;
                        }
                        #zoneDisconnectionModal .modal-body .table td:nth-child(3) {
                            white-space: normal;
                            min-width: 12rem;
                        }
                        .vs-status-month-input {
                            width: 100%;
                            max-width: 148px;
                            height: calc(1.5em + 0.5rem + 2px);
                            font-size: 0.8125rem;
                            font-weight: 600;
                            color: #5a5c69;
                            border-radius: 8px;
                            border-color: #d1d3e2;
                            cursor: pointer;
                            background-color: #fff;
                        }
                        .vs-status-month-input:focus {
                            border-color: #4e73df;
                            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.15);
                        }
                        .vs-status-card .card-header {
                            background: #fff;
                            border-bottom: 1px solid #eaecf4;
                        }
                        .vs-status-card-subtitle {
                            font-size: 0.75rem;
                            color: #858796;
                            line-height: 1.35;
                            margin-top: 0.2rem;
                        }
                        .vs-status-month-wrap {
                            flex-shrink: 0;
                            min-width: 148px;
                        }
                        .vs-status-card .card-body {
                            display: flex;
                            flex-direction: column;
                            padding-top: 0.75rem;
                            padding-bottom: 0.65rem;
                        }
                        .vs-status-card .vs-status-chart-wrap {
                            flex: 1 1 auto;
                            position: relative;
                            height: 16rem;
                            min-height: 16rem;
                            padding: 0.25rem 0 0;
                        }
                        @media (min-width: 768px) {
                            .vs-status-card .vs-status-chart-wrap {
                                height: calc(20rem - 52px);
                                min-height: calc(20rem - 52px);
                            }
                        }
                        #consumerStatusLegend {
                            display: flex;
                            flex-wrap: wrap;
                            justify-content: center;
                            align-items: center;
                            gap: 0.35rem 0.85rem;
                            line-height: 1.4;
                        }
                        .vs-status-legend-item {
                            cursor: pointer;
                            white-space: nowrap;
                            display: inline-flex;
                            align-items: center;
                            font-size: 0.76rem;
                        }
                        .vs-status-legend-item .legend-swatch {
                            flex-shrink: 0;
                        }
                        .vs-charts-row > [class*="col-"] > .card {
                            height: 100%;
                        }
                    </style>

                    <form method="get" action="{{ route('visual-summary') }}" class="visual-summary-filters">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-5 mb-2 mb-md-0">
                                <label for="filter_zone_route">Zone / Route</label>
                                <select class="form-control" id="filter_zone_route" name="zone_route">
                                    <option value="">All Zones</option>
                                    @foreach ($zoneOptions as $z)
                                        <option value="{{ $z }}" @selected(($filters['zone_route'] ?? '') === (string) $z)>{{ $z }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-5 mb-2 mb-md-0">
                                <label for="filter_bill_month">Bill Month</label>
                                <input type="month" class="form-control" id="filter_bill_month" name="bill_month"
                                       value="{{ $filters['bill_month'] ?? '' }}" required>
                            </div>
                            <div class="form-group col-md-2 mb-0 d-flex flex-wrap gap-1">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter mr-1"></i>Apply
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="status_month" id="filter_status_month"
                               value="{{ $filters['status_month'] ?? ($filters['bill_month'] ?? '') }}">
                    </form>
                    
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Visual Summary</h1>
                        <div>
                           <a href="{{ route('visual-summary', array_filter(['zone_route' => $filters['zone_route'] ?? '', 'bill_month' => $filters['bill_month'] ?? '', 'status_month' => $filters['status_month'] ?? ''], fn ($v) => $v !== null && $v !== '')) }}" class="btn btn-primary btn-sm mr-2">
                                <i class="fas fa-sync-alt mr-1"></i>Refresh
                            </a>
                            <button class="btn btn-success btn-sm">
                                <i class="fas fa-download mr-1"></i>Export
                            </button>
                        </div>
                    </div>

                    <!-- Key Metrics Row -->
                    <div class="row mb-4">
                        <!-- Total Consumers -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Consumers
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($totalConsumers) }}</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Monthly Water Sales -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Monthly Water Sales
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₱ {{ number_format($monthlyRevenue, 2) }}</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Collection Efficiency -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Collection Efficiency
                                            </div>
                                            <div class="row no-gutters align-items-center">
                                                <div class="col-auto">
                                                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">{{ number_format($collectionRate, 1) }}%</div>
                                                </div>
                                                <div class="col">
                                                    <div class="progress progress-sm mr-2">
                                                        <div class="progress-bar bg-info" role="progressbar" style="width: {{ $collectionBarWidth }}%" aria-valuenow="{{ $collectionBarWidth }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Arrears -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Pending Arrears
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₱ {{ number_format($pendingArrears, 2) }}</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row mb-4 vs-charts-row">
                        <!-- Revenue Trend Chart -->
                        <div class="col-xl-8 col-lg-7">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Monthly Collection Water Sales</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-area">
                                        <canvas id="revenueChart" height="90"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Consumer Status Bar Chart -->
                        <div class="col-xl-4 col-lg-5">
                            <div class="card shadow mb-4 vs-status-card">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <div class="pr-2">
                                        <h6 class="m-0 font-weight-bold text-primary">Consumer Status</h6>
                                        <div class="vs-status-card-subtitle">Active · New connections · Disconnections · Reconnections</div>
                                    </div>
                                    <div class="vs-status-month-wrap">
                                        <input type="month"
                                               id="status_chart_bill_month"
                                               name="status_month"
                                               class="form-control form-control-sm vs-status-month-input"
                                               value="{{ $filters['status_month'] ?? ($filters['bill_month'] ?? '') }}"
                                               aria-label="Consumer Status month"
                                               title="Filter Consumer Status chart only">
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="vs-status-chart-wrap">
                                        <canvas id="statusChart"></canvas>
                                    </div>
                                    <div class="vs-zone-legend mt-2 mb-0 pt-1" id="consumerStatusLegend">
                                        <span class="vs-status-legend-item" data-status-type="active" title="Click to view list"><span class="legend-swatch" style="background:#1cc88a;"></span>Active</span>
                                        <span class="vs-status-legend-item" data-status-type="new_connection" title="Click to view list"><span class="legend-swatch" style="background:#f6c23e;"></span>New Connection</span>
                                        <span class="vs-status-legend-item" data-status-type="disconnected" title="Click to view list"><span class="legend-swatch" style="background:#e74a3b;"></span>Disconnected</span>
                                        <span class="vs-status-legend-item" data-status-type="reconnected" title="Click to view list"><span class="legend-swatch" style="background:#4e73df;"></span>Reconnected</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Consumption + Total Accounts (merged) -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <div>
                                        <h6 class="m-0 font-weight-bold text-primary">Total Water Bill Metered Consumption & Accounts Billed</h6>
                                        <div class="small text-muted">Consumption (m³) and billed accounts per month</div>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="small mr-3"><i class="fas fa-circle" style="color:#2563eb;"></i> Consumption (m³)</span>
                                        <span class="small mr-3"><i class="fas fa-circle" style="color:#1cc88a;"></i> Total Accounts</span>
                                        <button class="btn btn-sm btn-outline-secondary mr-2" type="button" onclick="resetConsumptionZoom()">Reset</button>
                                        <button class="btn btn-sm btn-outline-secondary" type="button" onclick="toggleConsumptionFilled()">Fill</button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="vs-consumption-chart-wrap">
                                        <canvas id="consumptionChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Zone charts: top row = Disconnection (left) + Unpaid (right); bottom = Zone Performance -->
                    <div class="row mb-4">
                        <div class="col-lg-6 mb-4 mb-lg-0">
                            <div class="card vs-zone-card">
                                <div class="card-header">
                                    <h6 class="card-title-text">
                                        Remaining to Disconnect by Zone
                                        <i class="fas fa-info-circle" title="Assigned orders not yet cut (matches Disconnection Management → Orders Filter, Status = Assigned)"></i>
                                    </h6>
                                    <div class="card-subtitle">Assigned Orders · Disconnection Management</div>
                                </div>
                                <div class="card-body">
                                    <div class="vs-zone-chart-wrap">
                                        <canvas id="zoneDisconnectionChart"></canvas>
                                    </div>
                                    <div class="vs-zone-legend">
                                        <span class="legend-swatch" style="background:#e74a3b;"></span>Remaining
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-4 mb-lg-0">
                            <div class="card vs-zone-card">
                                <div class="card-header">
                                    <h6 class="card-title-text">
                                        Unpaid Balance by Zone
                                        <i class="fas fa-info-circle" title="FIFO unpaid balance per zone"></i>
                                    </h6>
                                    <div class="card-subtitle">By Zone (Active Consumers · {{ $topTablesMonthLabel }})</div>
                                </div>
                                <div class="card-body">
                                    <div class="vs-zone-chart-wrap">
                                        <canvas id="zoneUnpaidBalanceChart"></canvas>
                                    </div>
                                    <div class="vs-zone-legend">
                                        <span class="legend-swatch" style="background:#f6c23e;"></span>Unpaid Balance
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card vs-zone-card">
                                <div class="card-header">
                                    <h6 class="card-title-text">
                                        Zone Performance (Collection / Revenue)
                                        <i class="fas fa-info-circle" title="Collections by zone for selected bill month"></i>
                                    </h6>
                                    <div class="card-subtitle">By Zone (Active Consumers · {{ $topTablesMonthLabel }})</div>
                                </div>
                                <div class="card-body">
                                    <div class="vs-zone-chart-wrap vs-zone-chart-wrap-wide">
                                        <canvas id="zonePerformanceChart"></canvas>
                                    </div>
                                    <div class="vs-zone-legend">
                                        <span class="legend-swatch" style="background:#4e73df;"></span>Collection (Revenue)
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Tables Row -->
                    <div class="row mb-4">
                        <!-- Top Consumers by Consumption -->
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Top 10 Consumption Rankings (Fully Paid) <span class="text-muted font-weight-normal">(active · {{ $topTablesMonthLabel }})</span></h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height: 400px; overflow: auto;">
                                        <table class="table table-sm table-hover mb-0" style="font-size: 12px;">
                                            <thead class="thead-light" style="position: sticky; top: 0;">
                                                <tr>
                                                    <th class="text-center py-2 px-3">Rank</th>
                                                    <th class="py-2 px-3">Consumer Name</th>
                                                    <th class="text-center py-2 px-3">Zone</th>
                                                    <th class="text-center py-2 px-3">Total Consumption (m³)</th>
                                                    <th class="text-center py-2 px-3">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($topConsumption as $idx => $row)
                                                    <tr>
                                                        <td class="text-center py-2 px-3">{{ $idx + 1 }}</td>
                                                        <td class="py-2 px-3">{{ $row->account_name ?? '—' }}</td>
                                                        <td class="text-center py-2 px-3">{{ $row->zone ?? '—' }}</td>
                                                        <td class="text-center py-2 px-3">{{ number_format((float) ($row->total_consumption ?? 0)) }}</td>
                                                        <td class="text-center py-2 px-3">₱ {{ number_format((float) ($row->total_amount ?? 0), 2) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="py-3 px-3 text-center text-muted">No consumption data for active accounts in {{ $topTablesMonthLabel }}.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Outstanding Accounts -->
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-danger">Top 10 Outstanding Accounts <span class="text-muted font-weight-normal">(active · {{ $topTablesMonthLabel }})</span></h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height: 400px; overflow: auto;">
                                        <table class="table table-sm table-hover mb-0" style="font-size: 12px;">
                                            <thead class="thead-light" style="position: sticky; top: 0;">
                                                <tr>
                                                    <th class="text-center py-2 px-3">Rank</th>
                                                    <th class="py-2 px-3">Consumer Name</th>
                                                    <th class="text-center py-2 px-3">Zone</th>
                                                    <th class="text-center py-2 px-3">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($topOutstanding as $idx => $row)
                                                    <tr>
                                                        <td class="text-center py-2 px-3">{{ $idx + 1 }}</td>
                                                        <td class="py-2 px-3">{{ $row->account_name ?? '—' }}</td>
                                                        <td class="text-center py-2 px-3">{{ $row->zone_code ?? '—' }}</td>
                                                        <td class="text-center py-2 px-3 text-danger">₱ {{ number_format((float) $row->balance, 2) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="py-3 px-3 text-center text-muted">No outstanding positive balances for active accounts.</td>
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
                <!---Container Fluid-->
            </div>
            <!-- Footer -->
            @include('partials.footer')
            <!-- Footer -->
        </div>
    </div>

    <!-- Zone disconnection drill-down modal -->
    <div class="modal fade" id="zoneDisconnectionModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable vs-zone-discon-modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Zone <span id="zoneDisconModalZone">—</span> — Assigned Orders
                        <small class="text-muted ml-2">(<span id="zoneDisconModalCount">0</span> records)</small>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div id="zoneDisconModalLoading" class="text-center py-5 text-muted">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <div class="mt-2">Loading...</div>
                    </div>
                    <div class="table-responsive" style="max-height: 520px;">
                        <table class="table table-sm table-hover mb-0" id="zoneDisconModalTable" style="display:none;">
                            <thead class="thead-light" style="position: sticky; top: 0;">
                                <tr>
                                    <th>Date Saved</th>
                                    <th>Account No.</th>
                                    <th>Account Name</th>
                                    <th>Assigned To</th>
                                    <th class="text-right">Current Bill + WM</th>
                                    <th class="text-right">Outstanding</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div id="zoneDisconModalEmpty" class="text-center py-5 text-muted" style="display:none;">
                        No records found for this zone.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Consumer Status drill-down modal -->
    <div class="modal fade" id="consumerStatusModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable vs-zone-discon-modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span id="consumerStatusModalLabel">Consumer Status</span> —
                        <span id="consumerStatusModalMonth">—</span>
                        <small class="text-muted ml-2">(<span id="consumerStatusModalCount">0</span> records)</small>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div id="consumerStatusModalLoading" class="text-center py-5 text-muted">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <div class="mt-2">Loading...</div>
                    </div>
                    <div class="table-responsive" style="max-height: 520px;">
                        <table class="table table-sm table-hover mb-0" id="consumerStatusModalTable" style="display:none;">
                            <thead class="thead-light" style="position: sticky; top: 0;">
                                <tr id="consumerStatusModalHeadRow"></tr>
                            </thead>
                            <tbody id="consumerStatusModalBody"></tbody>
                        </table>
                    </div>
                    <div id="consumerStatusModalEmpty" class="text-center py-5 text-muted" style="display:none;">
                        No records found.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scroll to top -->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <script>
    // Value labels on zone bar charts only (do not register globally — affects line/doughnut charts)
    const barValueLabelPlugin = {
        id: 'barValueLabel',
        afterDatasetsDraw(chart, args, pluginOptions) {
            if (chart.config.type !== 'bar') return;
            const { ctx, chartArea } = chart;
            const format = pluginOptions.format || ((v) => String(v));
            const color = pluginOptions.color || '#5a5c69';
            const horizontal = pluginOptions.horizontal === true;
            chart.data.datasets.forEach((dataset, datasetIndex) => {
                const meta = chart.getDatasetMeta(datasetIndex);
                if (meta.hidden) return;
                meta.data.forEach((element, index) => {
                    const raw = dataset.data[index];
                    if (raw === null || raw === undefined) return;
                    const text = format(raw);
                    ctx.save();
                    ctx.fillStyle = color;
                    ctx.font = '600 11px "Nunito", sans-serif';
                    if (horizontal) {
                        const x = Math.min(element.x + 6, chartArea.right - 4);
                        const y = element.y;
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(text, x, y);
                    } else {
                        const x = element.x;
                        const y = element.y - 6;
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        if (y >= 2) {
                            ctx.fillText(text, x, y);
                        }
                    }
                    ctx.restore();
                });
            });
        }
    };

    // Revenue Trend Chart 
    var ctx = document.getElementById("revenueChart");
    var revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($revenueChartLabels),
            datasets: [{
                label: "Monthly Revenue",
                lineTension: 0.3,
                backgroundColor: "rgba(78, 115, 223, 0.05)",
                borderColor: "rgba(78, 115, 223, 1)",
                pointRadius: 3,
                pointBackgroundColor: "rgba(78, 115, 223, 1)",
                pointBorderColor: "rgba(78, 115, 223, 1)",
                pointHoverRadius: 3,
                pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                pointHitRadius: 10,
                pointBorderWidth: 2,
                data: @json($revenueChartData),
            }],
        },
        options: {
            maintainAspectRatio: false,
            layout: {
                padding: { left: 8 }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ' ₱' + Number(ctx.parsed.y || 0).toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            var n = Number(value);
                            if (n >= 1000000) return '₱' + (n / 1000000).toFixed(1) + 'M';
                            if (n >= 1000) return '₱' + Math.round(n / 1000) + 'K';
                            return '₱' + n;
                        }
                    }
                }
            }
        }
    });

    // Consumer Status Pie Chart
    const STATUS_SLICE_TYPES = ['active', 'new_connection', 'disconnected', 'reconnected'];
    const STATUS_SLICE_LABELS = ['Active', 'New Connection', 'Disconnected', 'Reconnected'];
    const STATUS_CHART_LABELS = ['Active', 'New Conn.', 'Disconnected', 'Reconnected'];
    const STATUS_MODAL_COLUMNS = {
        active: [
            { key: 'account_no', label: 'Account No.' },
            { key: 'account_name', label: 'Customer' },
            { key: 'address', label: 'Address' },
            { key: 'zone_code', label: 'Zone', className: 'text-center' },
            { key: 'status', label: 'Status', className: 'text-center' },
            { key: 'category', label: 'Category', className: 'text-center' },
            { key: 'meter_number', label: 'Meter No.', className: 'text-center' },
        ],
        new_connection: [
            { key: 'account_no', label: 'Account No.' },
            { key: 'account_name', label: 'Customer' },
            { key: 'address', label: 'Address' },
            { key: 'zone_code', label: 'Zone', className: 'text-center' },
            { key: 'created_at', label: 'Created Date', className: 'text-center' },
            { key: 'status', label: 'Status', className: 'text-center' },
            { key: 'category', label: 'Category', className: 'text-center' },
            { key: 'meter_number', label: 'Meter No.', className: 'text-center' },
        ],
        disconnected: [
            { key: 'disconnected_at', label: 'Disconnected Date' },
            { key: 'account_no', label: 'Account No.' },
            { key: 'account_name', label: 'Customer' },
            { key: 'zone_code', label: 'Zone', className: 'text-center' },
            { key: 'status', label: 'Status', className: 'text-center' },
            { key: 'disconnected_by', label: 'Disconnected By' },
            { key: 'outstanding', label: 'Outstanding', className: 'text-right text-danger font-weight-bold' },
        ],
        reconnected: [
            { key: 'reconnected_at', label: 'Reconnected Date' },
            { key: 'account_no', label: 'Account No.' },
            { key: 'account_name', label: 'Customer' },
            { key: 'zone_code', label: 'Zone', className: 'text-center' },
            { key: 'reconnected_by', label: 'Reconnected By' },
            { key: 'outstanding', label: 'Outstanding', className: 'text-right text-danger font-weight-bold' },
        ],
    };

    function escapeHtml(value) {
        return String(value ?? '—')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function openConsumerStatusModal(type, label) {
        const modalEl = document.getElementById('consumerStatusModal');
        const loadingEl = document.getElementById('consumerStatusModalLoading');
        const tableEl = document.getElementById('consumerStatusModalTable');
        const headRow = document.getElementById('consumerStatusModalHeadRow');
        const tbody = document.getElementById('consumerStatusModalBody');
        const emptyEl = document.getElementById('consumerStatusModalEmpty');
        const labelEl = document.getElementById('consumerStatusModalLabel');
        const monthEl = document.getElementById('consumerStatusModalMonth');
        const countEl = document.getElementById('consumerStatusModalCount');

        if (!modalEl || !headRow || !tbody) return;

        if (labelEl) labelEl.textContent = label || 'Consumer Status';
        if (monthEl) monthEl.textContent = '—';
        if (countEl) countEl.textContent = '0';
        headRow.innerHTML = '';
        tbody.innerHTML = '';
        if (loadingEl) loadingEl.style.display = '';
        if (tableEl) tableEl.style.display = 'none';
        if (emptyEl) {
            emptyEl.textContent = 'No records found.';
            emptyEl.style.display = 'none';
        }

        if (typeof jQuery !== 'undefined') {
            jQuery(modalEl).modal('show');
        }

        const params = new URLSearchParams(window.location.search);
        params.set('type', type);
        const statusMonth = document.getElementById('status_chart_bill_month')?.value;
        if (statusMonth) {
            params.set('status_month', statusMonth);
        }

        fetch(`{{ route('visual-summary.consumer-status-list') }}?${params.toString()}`, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(async (res) => {
                let data = {};
                try {
                    data = await res.json();
                } catch (e) {
                    throw new Error('Invalid server response.');
                }
                if (!res.ok) {
                    throw new Error(data.message || 'Request failed');
                }
                return data;
            })
            .then((data) => {
                if (loadingEl) loadingEl.style.display = 'none';
                if (!data.success || !Array.isArray(data.records) || data.records.length === 0) {
                    if (emptyEl) emptyEl.style.display = '';
                    return;
                }

                if (monthEl) monthEl.textContent = data.month_label || '—';
                if (countEl) countEl.textContent = String(data.count ?? data.records.length);

                const columns = STATUS_MODAL_COLUMNS[data.type] || STATUS_MODAL_COLUMNS.active;
                columns.forEach((col) => {
                    const th = document.createElement('th');
                    th.textContent = col.label;
                    if (col.className) th.className = col.className;
                    headRow.appendChild(th);
                });

                data.records.forEach((row) => {
                    const tr = document.createElement('tr');
                    columns.forEach((col) => {
                        const td = document.createElement('td');
                        td.className = col.className || '';
                        if (col.key === 'status') {
                            const statusText = row.status || '—';
                            const badgeClass = statusText === 'Active' || statusText === 'Reconnected'
                                ? 'success'
                                : (statusText === 'Pending' || statusText === 'Assigned' || statusText === 'In Progress'
                                    ? 'warning'
                                    : (statusText === 'Disconnected' ? 'danger' : 'secondary'));
                            td.innerHTML = `<span class="badge badge-${badgeClass}">${escapeHtml(statusText)}</span>`;
                        } else {
                            td.textContent = row[col.key] ?? '—';
                        }
                        tr.appendChild(td);
                    });
                    tbody.appendChild(tr);
                });

                if (tableEl) tableEl.style.display = '';
            })
            .catch((err) => {
                if (loadingEl) loadingEl.style.display = 'none';
                if (emptyEl) {
                    emptyEl.textContent = err?.message || 'Error loading records. Please try again.';
                    emptyEl.style.display = '';
                }
            });
    }

    // Consumer Status Bar Chart
    var ctx2 = document.getElementById("statusChart");
    if (ctx2) {
        var statusChart = new Chart(ctx2, {
            type: 'bar',
            plugins: [barValueLabelPlugin],
            data: {
                labels: STATUS_CHART_LABELS,
                datasets: [{
                    label: 'Consumers',
                    data: @json($statusChartData),
                    backgroundColor: ['#1cc88a', '#f6c23e', '#e74a3b', '#4e73df'],
                    hoverBackgroundColor: ['#17a673', '#f4b619', '#e02d1b', '#2e59d9'],
                    borderColor: ['#1cc88a', '#f6c23e', '#e74a3b', '#4e73df'],
                    borderRadius: 4,
                    barThickness: 42,
                    maxBarThickness: 42,
                    minBarLength: 6,
                }],
            },
            options: {
                maintainAspectRatio: false,
                layout: { padding: { top: 26, bottom: 6, left: 4, right: 8 } },
                onClick(evt, elements) {
                    if (!elements.length) return;
                    const index = elements[0].index;
                    const type = STATUS_SLICE_TYPES[index];
                    const label = STATUS_SLICE_LABELS[index];
                    if (type) openConsumerStatusModal(type, label);
                },
                onHover(evt, elements) {
                    ctx2.style.cursor = elements.length ? 'pointer' : 'default';
                },
                plugins: {
                    legend: { display: false },
                    barValueLabel: {
                        horizontal: false,
                        color: '#5a5c69',
                        format: (v) => String(Math.round(Number(v))),
                    },
                    tooltip: {
                        padding: 10,
                        backgroundColor: 'rgba(15,23,42,.92)',
                        callbacks: {
                            title: (items) => {
                                const idx = items[0]?.dataIndex ?? 0;
                                return STATUS_SLICE_LABELS[idx] || items[0]?.label || '';
                            },
                            label: (ctx) => ` ${Number(ctx.parsed.y || 0).toLocaleString()}`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            maxRotation: 0,
                            minRotation: 0,
                            autoSkip: false,
                            font: { size: 10, weight: '600' },
                            color: '#5a5c69',
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grace: '8%',
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: {
                            precision: 0,
                            maxTicksLimit: 6,
                            font: { size: 10 },
                            color: '#858796',
                            callback: (v) => Number(v).toLocaleString(),
                        },
                    },
                },
            }
        });
    }

    document.querySelectorAll('.vs-status-legend-item').forEach((legendItem) => {
        legendItem.addEventListener('click', () => {
            const type = legendItem.getAttribute('data-status-type');
            const index = STATUS_SLICE_TYPES.indexOf(type);
            const label = index >= 0 ? STATUS_SLICE_LABELS[index] : 'Consumer Status';
            if (type) openConsumerStatusModal(type, label);
        });
    });
    
    // Metered Consumption + Total Accounts Billed (merged, dual axis)
    let consumptionChartInstance = null;
    let consumptionFilled = true;
    const consumptionCtx = document.getElementById('consumptionChart');
    if (consumptionCtx) {
        consumptionChartInstance = new Chart(consumptionCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: @json($consumptionChartLabels),
                datasets: [
                    {
                        label: 'Consumption (m³)',
                        data: @json($consumptionChartData),
                        yAxisID: 'y',
                        fill: true,
                        backgroundColor: 'rgba(37, 99, 235, 0.10)',
                        borderColor: 'rgba(37, 99, 235, 1)',
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: 'rgba(37, 99, 235, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        borderWidth: 2,
                        tension: 0.35,
                    },
                    {
                        label: 'Total Accounts',
                        data: @json($accountsChartData),
                        yAxisID: 'yAccounts',
                        fill: false,
                        backgroundColor: 'rgba(28, 200, 138, 1)',
                        borderColor: 'rgba(28, 200, 138, 1)',
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: 'rgba(28, 200, 138, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        borderWidth: 2,
                        tension: 0.35,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 12,
                        backgroundColor: 'rgba(15,23,42,.92)',
                        titleColor: '#fff',
                        bodyColor: '#e2e8f0',
                        callbacks: {
                            label: (ctx) => {
                                const value = Number(ctx.parsed.y || 0).toLocaleString();
                                if (ctx.dataset.yAxisID === 'yAccounts') {
                                    return ` ${ctx.dataset.label}: ${value}`;
                                }
                                return ` ${ctx.dataset.label}: ${value} m³`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0 }
                    },
                    y: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        grid: { color: 'rgba(15,23,42,.06)' },
                        ticks: {
                            color: '#2563eb',
                            callback: (v) => Number(v).toLocaleString() + ' m³'
                        }
                    },
                    yAccounts: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        grid: { drawOnChartArea: false },
                        ticks: {
                            color: '#1cc88a',
                            precision: 0,
                            callback: (v) => Number(v).toLocaleString()
                        }
                    }
                }
            }
        });
    }

    function toggleConsumptionFilled() {
        if (!consumptionChartInstance) return;
        consumptionFilled = !consumptionFilled;
        consumptionChartInstance.data.datasets[0].fill = consumptionFilled;
        consumptionChartInstance.update();
    }

    function resetConsumptionZoom() {
        if (!consumptionChartInstance) return;
        consumptionChartInstance.reset();
    }

    const formatPeso = (value) => '₱' + Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    const formatPesoAxis = (value) => {
        const n = Number(value);
        if (n >= 1000000) return '₱' + (n / 1000000).toFixed(1) + 'M';
        if (n >= 1000) return '₱' + Math.round(n / 1000) + 'K';
        return '₱' + n;
    };
    const zoneBarCount = Math.max(
        @json(count($zoneChartLabelsSorted)),
        @json(count($zoneUnpaidChartLabelsSorted)),
        @json(count($zoneDisconnectionChartLabelsSorted))
    );
    document.querySelectorAll('.vs-zone-chart-wrap').forEach((el) => {
        el.style.minHeight = Math.max(280, zoneBarCount * 34) + 'px';
    });

    const zoneBarOptions = (horizontal, axisFormat, labelFormat, labelColor) => ({
        maintainAspectRatio: false,
        indexAxis: horizontal ? 'y' : 'x',
        layout: { padding: horizontal ? { right: 72 } : { top: 22 } },
        plugins: {
            legend: { display: false },
            barValueLabel: {
                horizontal: horizontal,
                color: labelColor,
                format: labelFormat
            }
        },
        scales: {
            x: {
                grid: { display: horizontal, color: 'rgba(0,0,0,0.05)' },
                ticks: horizontal ? {
                    callback: axisFormat
                } : {
                    autoSkip: false,
                    font: { size: 10, weight: '600' }
                }
            },
            y: {
                beginAtZero: true,
                grace: horizontal ? 0 : '5%',
                grid: { display: !horizontal, color: 'rgba(0,0,0,0.05)' },
                ticks: horizontal ? {
                    autoSkip: false,
                    font: { size: 11, weight: '600' }
                } : {
                    callback: axisFormat
                }
            }
        }
    });

    // Zone Performance — vertical bars, collection by zone
    var ctx3 = document.getElementById("zonePerformanceChart");
    if (ctx3) {
        new Chart(ctx3, {
            type: 'bar',
            plugins: [barValueLabelPlugin],
            data: {
                labels: @json($zoneChartLabelsSorted),
                datasets: [{
                    label: "Collection (Revenue)",
                    backgroundColor: "#4e73df",
                    hoverBackgroundColor: "#2e59d9",
                    borderColor: "#4e73df",
                    borderRadius: 4,
                    maxBarThickness: 42,
                    data: @json($zoneChartDataSorted),
                }],
            },
            options: zoneBarOptions(false, formatPesoAxis, formatPeso, '#4e73df')
        });
    }

    // Unpaid Balance by zone — horizontal bars
    var ctxZoneUnpaid = document.getElementById("zoneUnpaidBalanceChart");
    if (ctxZoneUnpaid) {
        new Chart(ctxZoneUnpaid, {
            type: 'bar',
            plugins: [barValueLabelPlugin],
            data: {
                labels: @json($zoneUnpaidChartLabelsSorted),
                datasets: [{
                    label: "Unpaid Balance",
                    backgroundColor: "#f6c23e",
                    hoverBackgroundColor: "#dda20a",
                    borderColor: "#f6c23e",
                    borderRadius: { topRight: 4, bottomRight: 4 },
                    barThickness: 18,
                    data: @json($zoneUnpaidChartDataSorted),
                }],
            },
            options: zoneBarOptions(true, formatPesoAxis, formatPeso, '#d97706')
        });
    }

    function openZoneDisconnectionModal(zoneCode) {
        const modalEl = document.getElementById('zoneDisconnectionModal');
        const loadingEl = document.getElementById('zoneDisconModalLoading');
        const tableEl = document.getElementById('zoneDisconModalTable');
        const emptyEl = document.getElementById('zoneDisconModalEmpty');
        const tbody = tableEl ? tableEl.querySelector('tbody') : null;
        const zoneLabelEl = document.getElementById('zoneDisconModalZone');
        const countEl = document.getElementById('zoneDisconModalCount');

        if (!modalEl || !tbody) return;

        if (zoneLabelEl) zoneLabelEl.textContent = zoneCode;
        if (countEl) countEl.textContent = '0';
        tbody.innerHTML = '';
        if (loadingEl) loadingEl.style.display = '';
        if (tableEl) tableEl.style.display = 'none';
        if (emptyEl) {
            emptyEl.textContent = 'No records found for this zone.';
            emptyEl.style.display = 'none';
        }

        if (typeof jQuery !== 'undefined') {
            jQuery(modalEl).modal('show');
        }

        fetch(`{{ route('visual-summary.disconnection-orders-by-zone') }}?zone_code=${encodeURIComponent(zoneCode)}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((res) => res.json())
            .then((data) => {
                if (loadingEl) loadingEl.style.display = 'none';
                if (!data.success || !Array.isArray(data.orders) || data.orders.length === 0) {
                    if (emptyEl) emptyEl.style.display = '';
                    return;
                }

                if (countEl) countEl.textContent = String(data.count ?? data.orders.length);
                data.orders.forEach((row) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${row.date_saved ?? '—'}</td>
                        <td>${row.account_no ?? '—'}</td>
                        <td>${row.account_name ?? '—'}</td>
                        <td>${row.assigned_to ?? '—'}</td>
                        <td class="text-right">${row.current_billing ?? '0.00'}</td>
                        <td class="text-right text-danger font-weight-bold">${row.outstanding ?? '0.00'}</td>
                        <td><span class="badge badge-primary">${row.status ?? 'Assigned'}</span></td>
                    `;
                    tbody.appendChild(tr);
                });
                if (tableEl) tableEl.style.display = '';
            })
            .catch(() => {
                if (loadingEl) loadingEl.style.display = 'none';
                if (emptyEl) {
                    emptyEl.textContent = 'Error loading records. Please try again.';
                    emptyEl.style.display = '';
                }
            });
    }

    // Accounts for disconnection by zone — vertical bars (Zone 1 → Zone 9)
    var ctxZoneDiscon = document.getElementById("zoneDisconnectionChart");
    if (ctxZoneDiscon) {
        const disconOptions = zoneBarOptions(false, (v) => v, (v) => String(Math.round(Number(v))), '#e74a3b');
        disconOptions.scales.y.ticks = { stepSize: 1, precision: 0 };
        disconOptions.onClick = function (evt, elements, chart) {
            if (!elements.length) return;
            const index = elements[0].index;
            const label = chart.data.labels[index];
            if (!label || label === '—') return;
            const zoneCode = String(label).replace(/^Zone\s+/i, '').trim();
            if (zoneCode) openZoneDisconnectionModal(zoneCode);
        };
        disconOptions.onHover = function (evt, elements) {
            ctxZoneDiscon.style.cursor = elements.length ? 'pointer' : 'default';
        };
        new Chart(ctxZoneDiscon, {
            type: 'bar',
            plugins: [barValueLabelPlugin],
            data: {
                labels: @json($zoneDisconnectionChartLabelsSorted),
                datasets: [{
                    label: "Remaining",
                    backgroundColor: "#e74a3b",
                    hoverBackgroundColor: "#c0392b",
                    borderColor: "#e74a3b",
                    borderRadius: 4,
                    maxBarThickness: 42,
                    data: @json($zoneDisconnectionChartDataSorted),
                }],
            },
            options: disconOptions
        });
    }

    (function () {
        const statusMonthInput = document.getElementById('status_chart_bill_month');
        const hiddenStatusMonth = document.getElementById('filter_status_month');
        if (!statusMonthInput) return;

        statusMonthInput.addEventListener('change', function () {
            const statusMonth = this.value;
            if (!statusMonth) return;

            if (hiddenStatusMonth) {
                hiddenStatusMonth.value = statusMonth;
            }

            const params = new URLSearchParams(window.location.search);
            params.set('status_month', statusMonth);
            window.location.href = @json(route('visual-summary')) + '?' + params.toString();
        });
    })();
    </script>
</body>
</html>

