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
                            <a href="{{ route('consumption') }}" class="nav-link active">
                                <i class="fas fa-chart-line me-1"></i>Consumption Graph
                            </a>
                        </li>
                    </ul>
                </div>


                <!-- Main Content Area -->
                <div class="p-3 bg-light">
                    <!-- Controls -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <label class="fw-semibold">YEAR</label>
                            <input type="text" class="form-control form-control-sm" id="consumptionYear" value="{{ date('Y') }}"
                                style="width: 80px;">
                            <label class="fw-semibold ms-3">CHART TYPE</label>
                            <select id="chartType" class="form-select form-select-sm" style="width: 150px;">
                                <option value="line">Line Chart</option>
                                <option value="bar">Bar Chart</option>
                                <option value="area">Area Chart</option>
                            </select>
                            <button class="btn btn-primary btn-sm ms-2" id="refreshConsumptionBtn">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">F6 - Print</button>
                            <button class="btn btn-outline-secondary btn-sm" id="exportDataBtn">Export Data</button>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="small">Total Consumption</div>
                                        <div class="h5 mb-0" id="totalConsumption">0 m³</div>
                                    </div>
                                    <i class="fas fa-tint fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="small">Average/Month</div>
                                        <div class="h5 mb-0" id="averageConsumption">0 m³</div>
                                    </div>
                                    <i class="fas fa-chart-bar fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="small">Highest</div>
                                        <div class="h5 mb-0" id="highestConsumption">0 m³</div>
                                    </div>
                                    <i class="fas fa-arrow-up fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="small">Lowest</div>
                                        <div class="h5 mb-0" id="lowestConsumption">0 m³</div>
                                    </div>
                                    <i class="fas fa-arrow-down fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chart -->
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">Monthly Water Consumption (<span id="chartYear">{{ date('Y') }}</span>)</h6>
                        </div>
                        <div class="card-body">
                            <div id="consumptionChartLoading" class="text-center py-5">
                                <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3 d-block"></i>
                                <p>Loading consumption data...</p>
                            </div>
                            <canvas id="consumptionChart" height="100" style="display: none;"></canvas>
                            <div id="consumptionChartEmpty" class="text-center py-5" style="display: none;">
                                <i class="fas fa-inbox fa-2x text-muted mb-3 d-block"></i>
                                <p class="text-muted">No consumption data available. Please search for a consumer.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Comparison and Trend -->
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Year-over-Year Comparison</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="comparisonChart" height="150"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Consumption Trend Analysis</h6>
                                </div>
                                <div class="card-body">
                                    <div
                                        class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                        <span>Consumption Pattern:</span>
                                        <span class="badge bg-primary">Stable</span>
                                    </div>
                                    <div
                                        class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                        <span>Trend:</span>
                                        <span class="badge bg-success">Consistent</span>
                                    </div>
                                    <div
                                        class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                        <span>Compared to Average:</span>
                                        <span class="badge bg-warning">Normal Range</span>
                                    </div>
                                    <div
                                        class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                        <span>Meter Status:</span>
                                        <span class="badge bg-success">Working Properly</span>
                                    </div>
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Analysis:</strong> Your water consumption is within normal range and
                                        consistent.
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
    </div>

    <!-- Scroll to top -->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    @include('partials.footer')

    @include('consumer.shared-header-script')

    <!-- Chart.js Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Wait for jQuery and DOM to be ready
        (function() {
            // Wait for jQuery to be available
            function waitForJQuery(callback) {
                if (typeof jQuery !== 'undefined' && typeof $ !== 'undefined') {
                    callback();
                } else {
                    setTimeout(function() {
                        waitForJQuery(callback);
                    }, 50);
                }
            }
            
            waitForJQuery(function() {
                // Now jQuery is available, wait for DOM ready
                $(document).ready(function() {
                    // Initialize variables
                    let consumptionChart = null;
                    let comparisonChart = null;
                    const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    
                    // Request management
                    let isLoading = false;
                    let currentRequest = null;
                    let dataCache = {}; // Cache to avoid reloading same data
                    let debounceTimer = null;

                    // Load consumption data (optimized with caching and request management)
                    function loadConsumptionData(force = false) {
                        // Cancel any pending debounced request
                        if (debounceTimer) {
                            clearTimeout(debounceTimer);
                            debounceTimer = null;
                        }

                        // Prevent multiple simultaneous requests
                        if (isLoading && !force) {
                            return;
                        }

                        const storedConsumer = sessionStorage.getItem('currentConsumer');
                        if (!storedConsumer) {
                            showEmptyState();
                            return;
                        }

                        try {
                            const consumer = JSON.parse(storedConsumer);
                            const accountNo = consumer.account_no || consumer.account_number;
                            const year = $('#consumptionYear').val() || new Date().getFullYear();

                            if (!accountNo) {
                                showEmptyState();
                                return;
                            }

                            // Check cache first
                            const cacheKey = `${accountNo}_${year}`;
                            if (!force && dataCache[cacheKey]) {
                                const cachedData = dataCache[cacheKey];
                                updateCharts(cachedData);
                                updateStatistics(cachedData.statistics);
                                $('#chartYear').text(cachedData.year);
                                return;
                            }

                            // Cancel previous request if still pending
                            if (currentRequest && currentRequest.readyState !== 4) {
                                currentRequest.abort();
                            }

                            isLoading = true;

                            // Show loading
                            $('#consumptionChartLoading').show();
                            $('#consumptionChart').hide();
                            $('#consumptionChartEmpty').hide();

                            console.log('Loading consumption data for:', accountNo, 'Year:', year);
                            
                            currentRequest = $.ajax({
                                url: '{{ route("consumption.data") }}',
                                method: 'GET',
                                timeout: 30000, // 30 second timeout
                                data: {
                                    account_no: accountNo,
                                    year: year
                                },
                                success: function(response) {
                                    console.log('Consumption data received:', response);
                                    isLoading = false;
                                    currentRequest = null;
                                    
                                    if (response && response.success && response.consumption) {
                                        // Cache the response
                                        dataCache[cacheKey] = response;
                                        
                                        updateCharts(response);
                                        updateStatistics(response.statistics);
                                        $('#chartYear').text(response.year);
                                    } else {
                                        console.warn('Invalid response format:', response);
                                        showEmptyState();
                                    }
                                },
                                error: function(xhr, status, error) {
                                    isLoading = false;
                                    currentRequest = null;
                                    
                                    if (xhr.statusText !== 'abort') {
                                        console.error('Error loading consumption data:', {
                                            status: status,
                                            error: error,
                                            response: xhr.responseText,
                                            statusCode: xhr.status
                                        });
                                        
                                        // Show error message to user
                                        const retryBtn = $('<button>').addClass('btn btn-sm btn-primary mt-2').text('Retry').on('click', function() {
                                            loadConsumptionData(true);
                                        });
                                        $('#consumptionChartLoading').html(`
                                            <div class="text-center py-5">
                                                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-3 d-block"></i>
                                                <p class="text-danger">Error loading data: ${error || 'Unknown error'}</p>
                                            </div>
                                        `).append(retryBtn);
                                    }
                                }
                            });
                    } catch (e) {
                        isLoading = false;
                        currentRequest = null;
                        console.error('Error parsing consumer data:', e);
                        showEmptyState();
                    }
                    }

                    // Debounced version for input changes
                    function loadConsumptionDataDebounced() {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
                        debounceTimer = setTimeout(function() {
                            loadConsumptionData(true);
                        }, 500); // Wait 500ms after user stops typing
                    }

                    function showEmptyState() {
            $('#consumptionChartLoading').hide();
            $('#consumptionChart').hide();
            
            const storedConsumer = sessionStorage.getItem('currentConsumer');
            let message = 'No consumption data available.';
            
            if (!storedConsumer) {
                message = 'Please search for a consumer to view consumption data.';
            }
            
            $('#consumptionChartEmpty').html(`
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-2x text-muted mb-3 d-block"></i>
                    <p class="text-muted">${message}</p>
                </div>
            `).show();
            
                        updateStatistics({ total: 0, average: 0, highest: 0, lowest: 0 });
                    }

                    function updateCharts(data) {
            const consumptionData = data.consumption || [];
            const previousYearData = data.previousYearData || [];
            const currentYear = data.year || new Date().getFullYear();
            const previousYear = data.previousYear || currentYear - 1;

            // Update main consumption chart
            const ctx1 = document.getElementById('consumptionChart');
            if (consumptionChart) {
                consumptionChart.destroy();
            }

            $('#consumptionChartLoading').hide();
            $('#consumptionChart').show();
            $('#consumptionChartEmpty').hide();

            consumptionChart = new Chart(ctx1.getContext('2d'), {
                type: 'line',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: 'Water Consumption (m³)',
                        data: consumptionData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: v => v + ' m³'
                            }
                        }
                    }
                }
            });

            // Update comparison chart
            const ctx2 = document.getElementById('comparisonChart');
            if (comparisonChart) {
                comparisonChart.destroy();
            }

            comparisonChart = new Chart(ctx2.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: previousYear.toString(),
                        data: previousYearData,
                        backgroundColor: 'rgba(156,163,175,0.5)',
                        borderColor: '#9ca3af',
                        borderWidth: 1
                    },
                    {
                        label: currentYear.toString(),
                        data: consumptionData,
                        backgroundColor: 'rgba(59,130,246,0.5)',
                        borderColor: '#3b82f6',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: v => v + ' m³'
                            }
                        }
                    }
                        }
                    });
                    }

                    function updateStatistics(stats) {
            $('#totalConsumption').text(stats.total.toFixed(2) + ' m³');
            $('#averageConsumption').text(stats.average.toFixed(2) + ' m³');
            $('#highestConsumption').text(stats.highest.toFixed(2) + ' m³');
            $('#lowestConsumption').text(stats.lowest.toFixed(2) + ' m³');
        }

                    // Switch chart type
                    $('#chartType').on('change', function(e) {
                        if (!consumptionChart) return;
                        const type = e.target.value === 'area' ? 'line' : e.target.value;
                        consumptionChart.config.type = type;
                        consumptionChart.config.data.datasets[0].fill = e.target.value === 'area';
                        consumptionChart.update();
                    });

                    // Year input change (with debouncing)
                    $('#consumptionYear').on('input change', function() {
                        loadConsumptionDataDebounced();
                    });

                    // Refresh button (force reload)
                    $('#refreshConsumptionBtn').on('click', function() {
                        // Clear cache for current consumer/year
                        const storedConsumer = sessionStorage.getItem('currentConsumer');
                        if (storedConsumer) {
                            try {
                                const consumer = JSON.parse(storedConsumer);
                                const accountNo = consumer.account_no || consumer.account_number;
                                const year = $('#consumptionYear').val() || new Date().getFullYear();
                                const cacheKey = `${accountNo}_${year}`;
                                delete dataCache[cacheKey];
                            } catch (e) {
                                // Ignore parse errors
                            }
                        }
                        loadConsumptionData(true);
                    });

                    // Export data button
                    $('#exportDataBtn').on('click', function() {
                        const storedConsumer = sessionStorage.getItem('currentConsumer');
                        if (!storedConsumer) {
                            alert('Please search for a consumer first');
                            return;
                        }
                        // TODO: Implement export functionality
                        alert('Export functionality coming soon');
                    });

                    // Load data on page load and when consumer changes
                    let lastConsumerKey = null;
                    let hasLoadedOnce = false;
                    
                    // Function to check for consumer changes
                    function checkConsumerChange() {
                        const storedConsumer = sessionStorage.getItem('currentConsumer');
                        let currentKey = null;
                        
                        try {
                            if (storedConsumer) {
                                const consumer = JSON.parse(storedConsumer);
                                currentKey = consumer.account_no || consumer.account_number;
                            }
                        } catch (e) {
                            console.error('Error parsing consumer:', e);
                            currentKey = null;
                        }
                        
                        if (currentKey !== lastConsumerKey) {
                            lastConsumerKey = currentKey;
                            // Clear cache when consumer changes
                            dataCache = {};
                            if (currentKey) {
                                console.log('Consumer changed, loading data for:', currentKey);
                                loadConsumptionData(true);
                                hasLoadedOnce = true;
                            } else {
                                if (!hasLoadedOnce) {
                                    // Show message if no consumer selected on first load
                                    showEmptyState();
                                }
                            }
                        }
                    }
                    
                    // Initial load - wait a bit for sessionStorage to be ready
                    setTimeout(function() {
                        checkConsumerChange();
                    }, 500);
                    
                    // Also try immediately
                    checkConsumerChange();
                    
                    // Reload when consumer changes in sessionStorage (cross-tab)
                    window.addEventListener('storage', function(e) {
                        if (e.key === 'currentConsumer') {
                            dataCache = {}; // Clear cache
                            lastConsumerKey = null; // Force reload
                            checkConsumerChange();
                        }
                    });
                    
                    // Check for consumer changes periodically (every 2 seconds)
                    // Only if no request is in progress and we haven't loaded yet
                    setInterval(function() {
                        if (!isLoading && !hasLoadedOnce) {
                            checkConsumerChange();
                        }
                    }, 2000);
                });
            });
        })();
    </script>
</body>
</html>
