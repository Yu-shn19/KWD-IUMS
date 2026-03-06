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
                        <h1 class="h3 mb-0 text-gray-800">Penalty Report</h1>
                        <div>
                            <button class="btn btn-primary btn-sm mr-2" id="generateReportBtn">
                                <i class="fas fa-sync-alt mr-1"></i>Generate
                            </button>
                            <button class="btn btn-success btn-sm mr-2" id="exportExcelBtn">
                                <i class="fas fa-file-excel mr-1"></i>Export Excel
                            </button>
                            <button class="btn btn-danger btn-sm" id="printReportBtn">
                                <i class="fas fa-print mr-1"></i>Print
                            </button>
                        </div>
                    </div>

                    <!-- Filter Card -->
                    <div class="row mb-3">
                        <div class="col-lg-12">
                            <div class="card shadow-sm">
                                <div class="card-body py-3">
                                    <div class="row align-items-end">
                                        <!-- Zone/Route -->
                                        <div class="col-md-3">
                                            <label class="small font-weight-bold mb-1">Zone / Route</label>
                                            <select class="form-control form-control-sm" id="zoneSelect">
                                                <option value="">All Zones</option>
                                                <option value="011">011</option>
                                                <option value="021">021</option>
                                                <option value="031">031</option>
                                                <option value="041">041</option>
                                                <option value="051">051</option>
                                                <option value="061">061</option>
                                                <option value="071">071</option>
                                                <option value="081">081</option>
                                                <option value="091">091</option>
                                            </select>
                                        </div>

                                        <!-- Bill Month -->
                                        <div class="col-md-3">
                                            <label class="small font-weight-bold mb-1">Bill Month</label>
                                            <div class="d-flex">
                                                <select class="form-control form-control-sm mr-2" id="monthSelect" style="width: 70px;">
                                                    <option value="">All</option>
                                                    <option value="01">01</option>
                                                    <option value="02">02</option>
                                                    <option value="03">03</option>
                                                    <option value="04">04</option>
                                                    <option value="05">05</option>
                                                    <option value="06">06</option>
                                                    <option value="07">07</option>
                                                    <option value="08" selected>08</option>
                                                    <option value="09">09</option>
                                                    <option value="10">10</option>
                                                    <option value="11">11</option>
                                                    <option value="12">12</option>
                                                </select>
                                                <input type="number" class="form-control form-control-sm" id="yearInput" value="{{ date('Y') }}" style="width: 100px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Report Tabs -->
                    <div class="row mb-2">
                        <div class="col-md-12">
                            <ul class="nav nav-tabs">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#detail">Detail</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#summary">Summary</a>
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
                                                            <th class="text-center py-2 px-2" style="min-width: 70px;">Zone_code</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 80px;">Billmonth</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 70px;">Sequence</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Account_no</th>
                                                            <th class="py-2 px-2" style="min-width: 180px;">Account_name</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 80px;">Rate_code</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 80px;">Date</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 80px;">Rate_code1</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 90px;">Penalty</th>
                                                            <th class="py-2 px-2" style="min-width: 100px;">Ref</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="penaltyTableBody">
                                                        <tr>
                                                            <td colspan="11" class="text-center text-muted py-5">
                                                                <i class="fas fa-info-circle fa-2x mb-3 d-block"></i>
                                                                <p>Click "Generate" to load penalty report data</p>
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
                                                    <span id="reportInfo">Select filters and click Generate</span>
                                                </small>
                                                <small class="text-muted">
                                                    Total Penalties: <strong class="text-danger" id="totalPenalties">₱ 0.00</strong> | Accounts: <strong id="totalAccounts">0</strong>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Tab -->
                        <div class="tab-pane fade" id="summary">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <h5 class="font-weight-bold text-primary mb-4" id="summaryTitle">Penalty Summary</h5>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="card bg-light mb-3">
                                                        <div class="card-body">
                                                            <h6 class="text-muted mb-3">Summary by Zone</h6>
                                                            <table class="table table-sm table-bordered mb-0">
                                                                <thead class="thead-light">
                                                                    <tr>
                                                                        <th>Zone</th>
                                                                        <th class="text-center">Accounts</th>
                                                                        <th class="text-right">Total Penalty</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="summaryByZoneBody">
                                                                    <tr>
                                                                        <td colspan="3" class="text-center text-muted py-3">
                                                                            <small>Click "Generate" to load summary data</small>
                                                                        </td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="card bg-light mb-3">
                                                        <div class="card-body">
                                                            <h6 class="text-muted mb-3">Summary by Penalty Type</h6>
                                                            <table class="table table-sm table-bordered mb-0">
                                                                <thead class="thead-light">
                                                                    <tr>
                                                                        <th>Penalty Type</th>
                                                                        <th class="text-center">Accounts</th>
                                                                        <th class="text-right">Total Amount</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="summaryByPenaltyTypeBody">
                                                                    <tr>
                                                                        <td colspan="3" class="text-center text-muted py-3">
                                                                            <small>Click "Generate" to load summary data</small>
                                                                        </td>
                                                                    </tr>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const generateBtn = document.getElementById('generateReportBtn');
            const zoneSelect = document.getElementById('zoneSelect');
            const monthSelect = document.getElementById('monthSelect');
            const yearInput = document.getElementById('yearInput');
            const tableBody = document.getElementById('penaltyTableBody');
            const reportInfo = document.getElementById('reportInfo');
            const totalPenalties = document.getElementById('totalPenalties');
            const totalAccounts = document.getElementById('totalAccounts');
            const penaltyReportEndpoint = @json(route('billing-processes.penalty-report'));

            function formatCurrency(value) {
                return '₱ ' + parseFloat(value || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }

            function formatDate(dateString) {
                if (!dateString) return '';
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return dateString;
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const year = date.getFullYear();
                return `${month}/${day}/${year}`;
            }

            function generateReport() {
                const zone = zoneSelect.value || '';
                const month = monthSelect.value || '';
                const year = yearInput.value || new Date().getFullYear();

                // Show loading
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="11" class="text-center py-5">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3 d-block"></i>
                            <p>Generating penalty report...</p>
                        </td>
                    </tr>
                `;

                const generateBtnOriginal = generateBtn.innerHTML;
                generateBtn.disabled = true;
                generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Generating...';

                // Build URL with query parameters
                let url = penaltyReportEndpoint + '?';
                if (zone) url += 'zone=' + encodeURIComponent(zone) + '&';
                if (month) url += 'bill_month=' + encodeURIComponent(month) + '&';
                if (year) url += 'bill_year=' + encodeURIComponent(year);

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data && data.data.length > 0) {
                            populateTable(data.data);
                            updateSummary(data.summary);
                        } else {
                            tableBody.innerHTML = `
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                        <p>${data.message || 'No penalized consumers found for the selected criteria.'}</p>
                                    </td>
                                </tr>
                            `;
                            reportInfo.textContent = data.summary ? `${data.summary.zone} - ${data.summary.bill_month} Penalty Report` : 'No data available';
                            totalPenalties.textContent = formatCurrency(0);
                            totalAccounts.textContent = '0';
                            
                            // Clear summary tables
                            const summaryByZoneBody = document.getElementById('summaryByZoneBody');
                            const summaryByPenaltyTypeBody = document.getElementById('summaryByPenaltyTypeBody');
                            if (summaryByZoneBody) {
                                summaryByZoneBody.innerHTML = `
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">
                                            <small>No data available</small>
                                        </td>
                                    </tr>
                                `;
                            }
                            if (summaryByPenaltyTypeBody) {
                                summaryByPenaltyTypeBody.innerHTML = `
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">
                                            <small>No data available</small>
                                        </td>
                                    </tr>
                                `;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        let errorMessage = 'Error loading penalty report. Please try again.';
                        if (error.message) {
                            errorMessage += '<br><small>' + error.message + '</small>';
                        }
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="11" class="text-center text-danger py-5">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-3 d-block"></i>
                                    <p>${errorMessage}</p>
                                    <small class="text-muted">Check browser console for details.</small>
                                </td>
                            </tr>
                        `;
                        reportInfo.textContent = 'Error loading report';
                        totalPenalties.textContent = formatCurrency(0);
                        totalAccounts.textContent = '0';
                    })
                    .finally(() => {
                        generateBtn.disabled = false;
                        generateBtn.innerHTML = generateBtnOriginal;
                    });
            }

            function populateTable(data) {
                let html = '';
                data.forEach((record, index) => {
                    html += `
                        <tr>
                            <td class="text-center py-1">${index + 1}</td>
                            <td class="text-center py-1">${record.zone_code || ''}</td>
                            <td class="text-center py-1">${record.bill_month || ''}</td>
                            <td class="text-center py-1">${record.sequence || ''}</td>
                            <td class="text-center py-1">${record.account_number || ''}</td>
                            <td class="py-1 px-2">${record.account_name || ''}</td>
                            <td class="text-center py-1">${record.rate_code || 'P1'}</td>
                            <td class="text-center py-1">${record.date || ''}</td>
                            <td class="text-center py-1">${record.rate_code1 || 'LP'}</td>
                            <td class="text-right py-1 px-2">${formatCurrency(record.penalty)}</td>
                            <td class="py-1 px-2">${record.ref || 'Late Payment'}</td>
                        </tr>
                    `;
                });
                tableBody.innerHTML = html;
            }

            function updateSummary(summary) {
                const zoneText = summary.zone || 'All Zones';
                const monthText = summary.bill_month || 'All Months';
                reportInfo.textContent = `${zoneText} - ${monthText} Penalty Report`;
                totalPenalties.textContent = formatCurrency(summary.total_penalty || 0);
                totalAccounts.textContent = summary.total_penalized || 0;
                
                // Update summary title
                const summaryTitle = document.getElementById('summaryTitle');
                if (summaryTitle) {
                    summaryTitle.textContent = `Penalty Summary - ${monthText}`;
                }
                
                // Populate summary by zone
                populateSummaryByZone(summary.by_zone || []);
                
                // Populate summary by penalty type
                populateSummaryByPenaltyType(summary.by_penalty_type || []);
            }
            
            function populateSummaryByZone(zoneData) {
                const tbody = document.getElementById('summaryByZoneBody');
                if (!tbody) return;
                
                if (!zoneData || zoneData.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">
                                <small>No data available</small>
                            </td>
                        </tr>
                    `;
                    return;
                }
                
                let html = '';
                let totalAccounts = 0;
                let totalPenalty = 0;
                
                zoneData.forEach(item => {
                    totalAccounts += item.accounts || 0;
                    totalPenalty += item.total_penalty || 0;
                    html += `
                        <tr>
                            <td class="font-weight-bold">${item.zone || 'Unknown'}</td>
                            <td class="text-center">${item.accounts || 0}</td>
                            <td class="text-right">${formatCurrency(item.total_penalty || 0)}</td>
                        </tr>
                    `;
                });
                
                // Add total row
                html += `
                    <tr class="table-primary font-weight-bold">
                        <td>TOTAL</td>
                        <td class="text-center">${totalAccounts}</td>
                        <td class="text-right">${formatCurrency(totalPenalty)}</td>
                    </tr>
                `;
                
                tbody.innerHTML = html;
            }
            
            function populateSummaryByPenaltyType(penaltyTypeData) {
                const tbody = document.getElementById('summaryByPenaltyTypeBody');
                if (!tbody) return;
                
                if (!penaltyTypeData || penaltyTypeData.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">
                                <small>No data available</small>
                            </td>
                        </tr>
                    `;
                    return;
                }
                
                let html = '';
                let totalAccounts = 0;
                let totalAmount = 0;
                
                penaltyTypeData.forEach(item => {
                    totalAccounts += item.accounts || 0;
                    totalAmount += item.total_amount || 0;
                    const penaltyTypeLabel = item.type === 'LP' ? 'Late Payment (LP)' : item.type;
                    html += `
                        <tr>
                            <td>${penaltyTypeLabel}</td>
                            <td class="text-center">${item.accounts || 0}</td>
                            <td class="text-right">${formatCurrency(item.total_amount || 0)}</td>
                        </tr>
                    `;
                });
                
                // Add total row
                html += `
                    <tr class="table-primary font-weight-bold">
                        <td>TOTAL</td>
                        <td class="text-center">${totalAccounts}</td>
                        <td class="text-right">${formatCurrency(totalAmount)}</td>
                    </tr>
                `;
                
                tbody.innerHTML = html;
            }

            generateBtn.addEventListener('click', generateReport);

            // Export Excel functionality
            document.getElementById('exportExcelBtn').addEventListener('click', function() {
                const zone = zoneSelect.value || '';
                const month = monthSelect.value || '';
                const year = yearInput.value || new Date().getFullYear();
                
                // Build URL with query parameters
                let url = @json(route('billing-processes.penalty-report.export')) + '?';
                if (zone) url += 'zone=' + encodeURIComponent(zone) + '&';
                if (month) url += 'bill_month=' + encodeURIComponent(month) + '&';
                if (year) url += 'bill_year=' + encodeURIComponent(year);
                
                // Open in new window to trigger download
                window.location.href = url;
            });

            // Print functionality
            document.getElementById('printReportBtn').addEventListener('click', function() {
                window.print();
            });
        });
    </script>
</body>
</html>

