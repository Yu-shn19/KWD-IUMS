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
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Visual Summary</h1>
                        <div>
                            <button class="btn btn-primary btn-sm mr-2">
                                <i class="fas fa-sync-alt mr-1"></i>Refresh
                            </button>
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
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">3,541</div>
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
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₱ 1,245,680</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Collection Rate -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Collection Rate
                                            </div>
                                            <div class="row no-gutters align-items-center">
                                                <div class="col-auto">
                                                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">87%</div>
                                                </div>
                                                <div class="col">
                                                    <div class="progress progress-sm mr-2">
                                                        <div class="progress-bar bg-info" role="progressbar" style="width: 87%" aria-valuenow="87" aria-valuemin="0" aria-valuemax="100"></div>
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
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₱ 345,200</div>
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
                                    <h6 class="m-0 font-weight-bold text-primary">Monthly Revenue Trend</h6>
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

                    <!-- Statistics Tables Row -->
                    <div class="row mb-4">
                        <!-- Top Consumers by Consumption -->
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Top 10 Consumers by Consumption</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height: 400px; overflow: auto;">
                                        <table class="table table-sm table-hover mb-0" style="font-size: 12px;">
                                            <thead class="thead-light" style="position: sticky; top: 0;">
                                                <tr>
                                                    <th class="py-2 px-3">#</th>
                                                    <th class="py-2 px-3">Account Name</th>
                                                    <th class="py-2 px-3">Zone</th>
                                                    <th class="text-right py-2 px-3">Consumption (m³)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td class="py-2 px-3">1</td>
                                                    <td class="py-2 px-3">ABC Hotel & Resort</td>
                                                    <td class="py-2 px-3">031</td>
                                                    <td class="text-right py-2 px-3">1,250</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">2</td>
                                                    <td class="py-2 px-3">XYZ Manufacturing Inc.</td>
                                                    <td class="py-2 px-3">081</td>
                                                    <td class="text-right py-2 px-3">980</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">3</td>
                                                    <td class="py-2 px-3">Grand Mall Complex</td>
                                                    <td class="py-2 px-3">041</td>
                                                    <td class="text-right py-2 px-3">875</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">4</td>
                                                    <td class="py-2 px-3">City Hospital</td>
                                                    <td class="py-2 px-3">011</td>
                                                    <td class="text-right py-2 px-3">760</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">5</td>
                                                    <td class="py-2 px-3">Provincial Capitol</td>
                                                    <td class="py-2 px-3">021</td>
                                                    <td class="text-right py-2 px-3">650</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">6</td>
                                                    <td class="py-2 px-3">University Campus</td>
                                                    <td class="py-2 px-3">051</td>
                                                    <td class="text-right py-2 px-3">580</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">7</td>
                                                    <td class="py-2 px-3">Sports Complex</td>
                                                    <td class="py-2 px-3">061</td>
                                                    <td class="text-right py-2 px-3">520</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">8</td>
                                                    <td class="py-2 px-3">Plaza Hotel</td>
                                                    <td class="py-2 px-3">031</td>
                                                    <td class="text-right py-2 px-3">475</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">9</td>
                                                    <td class="py-2 px-3">Food Processing Plant</td>
                                                    <td class="py-2 px-3">071</td>
                                                    <td class="text-right py-2 px-3">430</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">10</td>
                                                    <td class="py-2 px-3">Business Park Tower</td>
                                                    <td class="py-2 px-3">091</td>
                                                    <td class="text-right py-2 px-3">390</td>
                                                </tr>
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
                                    <h6 class="m-0 font-weight-bold text-danger">Top 10 Outstanding Accounts</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height: 400px; overflow: auto;">
                                        <table class="table table-sm table-hover mb-0" style="font-size: 12px;">
                                            <thead class="thead-light" style="position: sticky; top: 0;">
                                                <tr>
                                                    <th class="py-2 px-3">#</th>
                                                    <th class="py-2 px-3">Account Name</th>
                                                    <th class="py-2 px-3">Zone</th>
                                                    <th class="text-right py-2 px-3">Balance</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td class="py-2 px-3">1</td>
                                                    <td class="py-2 px-3">Garcia, David P.</td>
                                                    <td class="py-2 px-3">021</td>
                                                    <td class="text-right py-2 px-3 text-danger">₱ 15,450.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">2</td>
                                                    <td class="py-2 px-3">Santos, Maria B.</td>
                                                    <td class="py-2 px-3">031</td>
                                                    <td class="text-right py-2 px-3 text-danger">₱ 12,800.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">3</td>
                                                    <td class="py-2 px-3">Reyes, Pedro C.</td>
                                                    <td class="py-2 px-3">041</td>
                                                    <td class="text-right py-2 px-3 text-danger">₱ 11,250.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">4</td>
                                                    <td class="py-2 px-3">Rodriguez, Emily Q.</td>
                                                    <td class="py-2 px-3">011</td>
                                                    <td class="text-right py-2 px-3 text-danger">₱ 9,680.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">5</td>
                                                    <td class="py-2 px-3">Gonzales, Michael R.</td>
                                                    <td class="py-2 px-3">051</td>
                                                    <td class="text-right py-2 px-3 text-danger">₱ 8,920.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">6</td>
                                                    <td class="py-2 px-3">Aquino, Jessica S.</td>
                                                    <td class="py-2 px-3">061</td>
                                                    <td class="text-right py-2 px-3 text-danger">₱ 7,550.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">7</td>
                                                    <td class="py-2 px-3">Dizon, Kevin T.</td>
                                                    <td class="py-2 px-3">071</td>
                                                    <td class="text-right py-2 px-3 text-danger">₱ 6,430.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">8</td>
                                                    <td class="py-2 px-3">Castro, Nicole U.</td>
                                                    <td class="py-2 px-3">081</td>
                                                    <td class="text-right py-2 px-3 text-danger">₱ 5,890.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">9</td>
                                                    <td class="py-2 px-3">Perez, Oliver V.</td>
                                                    <td class="py-2 px-3">091</td>
                                                    <td class="text-right py-2 px-3 text-danger">₱ 4,760.00</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-2 px-3">10</td>
                                                    <td class="py-2 px-3">Flores, Patricia W.</td>
                                                    <td class="py-2 px-3">011</td>
                                                    <td class="text-right py-2 px-3 text-danger">₱ 4,220.00</td>
                                                </tr>
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
            labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug"],
            datasets: [{
                label: "Revenue",
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
                data: [1050000, 1100000, 1080000, 1150000, 1200000, 1180000, 1220000, 1245680],
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
                data: [3200, 261, 80],
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

    // Zone Performance Bar Chart
    var ctx3 = document.getElementById("zonePerformanceChart");
    var zoneChart = new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: ["Zone 011", "Zone 021", "Zone 031", "Zone 041", "Zone 051", "Zone 061", "Zone 071", "Zone 081", "Zone 091"],
            datasets: [{
                label: "Revenue",
                backgroundColor: "#4e73df",
                hoverBackgroundColor: "#2e59d9",
                borderColor: "#4e73df",
                data: [145000, 132000, 158000, 98000, 175000, 165000, 138000, 189000, 145680],
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

