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
                            <a href="{{ route('consumer') }}" class="nav-link">
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
                            <a href="{{ route('service') }}" class="nav-link active">
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
                            <a href="{{ route('consumption') }}" class="nav-link">
                                <i class="fas fa-chart-line me-1"></i>Consumption Graph
                            </a>
                        </li>
                    </ul>
                </div>

                

                <!-- Main Content Area -->
                <div class="p-3 bg-light">
                    <!-- Filter & Actions Row -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <!-- Filter Section (Left side) -->
                        <div class="d-flex align-items-center gap-2">
                            <label class="fw-semibold">YEAR</label>
                            <input type="text" class="form-control form-control-sm" value="2025" style="width: 80px;">
                            <label class="fw-semibold ms-3">SERVICE TYPE</label>
                            <select class="form-select form-select-sm" style="width: 150px;">
                                <option>All Services</option>
                                <option>Installation</option>
                                <option>Repair</option>
                                <option>Disconnection</option>
                                <option>Reconnection</option>
                                <option>Meter Change</option>
                            </select>
                        </div>

                        <!-- Actions (Right side) -->
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold">TOTAL SERVICES:</span>
                            <input type="text" class="form-control form-control-sm" value="8" readonly style="width: 80px;">
                            <button class="btn btn-outline-secondary btn-sm">F6 - Print</button>
                            <button class="btn btn-outline-secondary btn-sm">Refresh</button>
                        </div>
                    </div>

                    <!-- Service History Table -->
                    <div class="card">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Service History</h6>
                            <span class="badge bg-light text-dark" id="serviceHistoryAccountBadge">No consumer selected — search above</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 400px;">
                                <table class="table table-hover mb-0 table-sm">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th class="text-center">Service ID</th>
                                            <th class="text-center">Date</th>
                                            <th class="text-center">Service Type</th>
                                            <th class="text-center">Description</th>
                                            <th class="text-center">Requested By</th>
                                            <th class="text-center">Performed By</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Cost</th>
                                            <th class="text-center">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="text-center">SV-2025-001</td>
                                            <td class="text-center">08/11/2025</td>
                                            <td class="text-center">Installation</td>
                                            <td>New water meter installation</td>
                                            <td class="text-center">Siarot, Thelma C.</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center"><span class="badge bg-success">Completed</span></td>
                                            <td class="text-end">2,500.00</td>
                                            <td>Initial installation</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">SV-2025-045</td>
                                            <td class="text-center">06/01/2025</td>
                                            <td class="text-center">Meter Change</td>
                                            <td>Replace defective meter</td>
                                            <td class="text-center">Siarot, Thelma C.</td>
                                            <td class="text-center">FLORAME</td>
                                            <td class="text-center"><span class="badge bg-success">Completed</span></td>
                                            <td class="text-end">1,200.00</td>
                                            <td>Old meter: Brand Ever, New meter: Brand Ever</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">SV-2025-123</td>
                                            <td class="text-center">03/15/2025</td>
                                            <td class="text-center">Repair</td>
                                            <td>Fix pipe leak</td>
                                            <td class="text-center">Siarot, Thelma C.</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center"><span class="badge bg-success">Completed</span></td>
                                            <td class="text-end">500.00</td>
                                            <td>Emergency repair</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">SV-2025-087</td>
                                            <td class="text-center">02/20/2025</td>
                                            <td class="text-center">Inspection</td>
                                            <td>Routine meter inspection</td>
                                            <td class="text-center">System</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center"><span class="badge bg-success">Completed</span></td>
                                            <td class="text-end">0.00</td>
                                            <td>No issues found</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">SV-2024-456</td>
                                            <td class="text-center">12/05/2024</td>
                                            <td class="text-center">Maintenance</td>
                                            <td>Annual maintenance check</td>
                                            <td class="text-center">System</td>
                                            <td class="text-center">FLORAME</td>
                                            <td class="text-center"><span class="badge bg-success">Completed</span></td>
                                            <td class="text-end">0.00</td>
                                            <td>Routine maintenance</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">SV-2024-389</td>
                                            <td class="text-center">09/10/2024</td>
                                            <td class="text-center">Repair</td>
                                            <td>Replace damaged valve</td>
                                            <td class="text-center">Siarot, Thelma C.</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center"><span class="badge bg-success">Completed</span></td>
                                            <td class="text-end">350.00</td>
                                            <td>Valve replacement</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">SV-2024-234</td>
                                            <td class="text-center">06/15/2024</td>
                                            <td class="text-center">Inspection</td>
                                            <td>Water quality test</td>
                                            <td class="text-center">System</td>
                                            <td class="text-center">FLORAME</td>
                                            <td class="text-center"><span class="badge bg-success">Completed</span></td>
                                            <td class="text-end">0.00</td>
                                            <td>Water quality: Good</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">SV-2024-156</td>
                                            <td class="text-center">03/20/2024</td>
                                            <td class="text-center">Maintenance</td>
                                            <td>Meter calibration</td>
                                            <td class="text-center">System</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center"><span class="badge bg-success">Completed</span></td>
                                            <td class="text-end">0.00</td>
                                            <td>Meter accuracy verified</td>
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
</div>

<!-- Scroll to top -->
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>
@include('partials.footer')
@include('consumer.shared-header-script')
<script>
    // Use search: when a consumer is selected (from header search), show them on this page
    $(document).ready(function() {
        function updateServiceHistoryConsumer() {
            var stored = sessionStorage.getItem('currentConsumer');
            var badge = $('#serviceHistoryAccountBadge');
            if (!badge.length) return;
            if (stored) {
                try {
                    var consumer = JSON.parse(stored);
                    var accountNo = consumer.account_no || consumer.account_number || '';
                    var name = consumer.account_name || consumer.full_name || '';
                    badge.text(accountNo ? (accountNo + ' - ' + name) : 'Viewing selected consumer').removeClass('bg-warning').addClass('bg-light text-dark');
                } catch (e) {
                    badge.text('Viewing selected consumer').removeClass('bg-warning').addClass('bg-light text-dark');
                }
            } else {
                badge.text('No consumer selected — search above').removeClass('bg-light text-dark').addClass('bg-warning');
            }
        }
        updateServiceHistoryConsumer();
        setTimeout(updateServiceHistoryConsumer, 200);
        setInterval(updateServiceHistoryConsumer, 1500);
        $(window).on('storage', function(e) {
            if (e.originalEvent && e.originalEvent.key === 'currentConsumer') updateServiceHistoryConsumer();
        });
    });
</script>
</body>
</html>

