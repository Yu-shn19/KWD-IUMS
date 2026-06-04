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
                        $statusChartData = $statusChartData ?? [0, 0, 0];
                        $zoneChartLabels = $zoneChartLabels ?? ['—'];
                        $zoneChartData = $zoneChartData ?? [0];
                        $zoneUnpaidChartLabels = $zoneUnpaidChartLabels ?? ['—'];
                        $zoneUnpaidChartData = $zoneUnpaidChartData ?? [0];
                        $topConsumption = $topConsumption ?? collect();
                        $topOutstanding = $topOutstanding ?? collect();
                        $topTablesMonthLabel = $topTablesMonthLabel ?? \Illuminate\Support\Carbon::now()->format('F Y');
                        $collectionBarWidth = max(0, min(100, $collectionRate));
                        $zoneOptions = $zoneOptions ?? collect();
                        $filters = $filters ?? [
                            'zone_route' => '',
                            'bill_month' => \Illuminate\Support\Carbon::now()->format('Y-m'),
                        ];
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
                    </form>
                    
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Visual Summary</h1>
                        <div>
                           <a href="{{ route('visual-summary', array_filter(['zone_route' => $filters['zone_route'] ?? '', 'bill_month' => $filters['bill_month'] ?? ''], fn ($v) => $v !== null && $v !== '')) }}" class="btn btn-primary btn-sm mr-2">
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

                        <!-- Monthly Revenue -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Monthly Revenue
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
                    <div class="row mb-4">
                        <!-- Revenue Trend Chart -->
                        <div class="col-xl-8 col-lg-7">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Monthly Collections Trend (last 12 months)</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-area">
                                        <canvas id="revenueChart" height="90"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Consumer Status Pie Chart -->
                        <div class="col-xl-4 col-lg-5">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Consumer Status</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-pie pt-4 pb-2">
                                        <canvas id="statusChart"></canvas>
                                    </div>
                                    <div class="mt-4 text-center small">
                                        <span class="mr-2">
                                            <i class="fas fa-circle text-success"></i> Active
                                        </span>
                                        <span class="mr-2">
                                            <i class="fas fa-circle text-warning"></i> Pending
                                        </span>
                                        <span class="mr-2">
                                            <i class="fas fa-circle text-danger"></i> Disconnected
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Zone Performance Row -->
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Zone Performance</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="zonePerformanceChart" height="70"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total balance per zone (same FIFO sums as AR Aging → AR Summary per Zone) -->
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Unpaid Balance</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="zoneUnpaidBalanceChart" height="70"></canvas>
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

    <!-- Scroll to top -->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <script>
    // Revenue Trend Chart
    var ctx = document.getElementById("revenueChart");
    var revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($revenueChartLabels),
            datasets: [{
                label: "Collections",
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
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // Consumer Status Pie Chart
    var ctx2 = document.getElementById("statusChart");
    var statusChart = new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Pending', 'Disconnected'],
            datasets: [{
                data: @json($statusChartData),
                backgroundColor: ['#1cc88a', '#f6c23e', '#e74a3b'],
                hoverBackgroundColor: ['#17a673', '#f4b619', '#e02d1b'],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            cutout: '80%',
        },
    });

    // Zone Performance Bar Chart (collections)
    var ctx3 = document.getElementById("zonePerformanceChart");
    var zoneChart = new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: @json($zoneChartLabels),
            datasets: [{
                label: "Total amount (bill month)",
                backgroundColor: "#4e73df",
                hoverBackgroundColor: "#2e59d9",
                borderColor: "#4e73df",
                data: @json($zoneChartData),
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    // Total balance by zone (FIFO), same per-zone totals as AR Aging → AR Summary per Zone
    var ctxZoneUnpaid = document.getElementById("zoneUnpaidBalanceChart");
    var zoneUnpaidChart = new Chart(ctxZoneUnpaid, {
        type: 'bar',
        data: {
            labels: @json($zoneUnpaidChartLabels),
            datasets: [{
                label: "Total balance",
                backgroundColor: "#f6c23e",
                hoverBackgroundColor: "#dda20a",
                borderColor: "#f6c23e",
                data: @json($zoneUnpaidChartData),
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    </script>
</body>
</html>

