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
                            <a href="{{ route('service') }}" class="nav-link">
                                <i class="fas fa-history me-1"></i>Service History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('meter') }}" class="nav-link active">
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
                    <!-- Year + Statistics Row -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <!-- Year Filter (Left side) -->
                        <div class="d-flex align-items-center gap-2">
                            <label class="fw-semibold">YEAR</label>
                            <input type="text" class="form-control form-control-sm" value="2025" style="width: 80px;">
                        </div>

                        <!-- Statistics & Actions (Right side) -->
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold">TOTAL READINGS</span>
                            <input type="text" class="form-control form-control-sm" value="12" readonly style="width: 100px;">
                            <span class="fw-semibold">AVG VOLUME</span>
                            <input type="text" class="form-control form-control-sm" value="24.2" readonly style="width: 100px;">
                            <button class="btn btn-outline-secondary btn-sm">F6 - Print</button>
                            <button class="btn btn-outline-secondary btn-sm">Refresh</button>
                        </div>
                    </div>

                    <!-- Meter Reading History Table -->
                    <div class="card">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Meter Reading History</h6>
                            <span class="badge bg-light text-dark" id="meterReadingAccountBadge">No consumer selected — search above</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 400px;">
                                <table class="table table-hover mb-0 table-sm">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th class="text-center">Period</th>
                                            <th class="text-center">Reading Date</th>
                                            <th class="text-center">Reader</th>
                                            <th class="text-center">Previous Reading</th>
                                            <th class="text-center">Current Reading</th>
                                            <th class="text-center">Volume (m³)</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Photo</th>
                                            <th class="text-center">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="text-center">01-2025</td>
                                            <td class="text-center">01/03/2025</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center">10,122.0</td>
                                            <td class="text-center">10,163.0</td>
                                            <td class="text-center">41.0</td>
                                            <td class="text-center"><span class="badge bg-success">READ</span></td>
                                            <td class="text-center"><i class="fas fa-camera text-primary"></i></td>
                                            <td class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">02-2025</td>
                                            <td class="text-center">02/03/2025</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center">10,163.0</td>
                                            <td class="text-center">10,204.0</td>
                                            <td class="text-center">41.0</td>
                                            <td class="text-center"><span class="badge bg-success">READ</span></td>
                                            <td class="text-center"><i class="fas fa-camera text-primary"></i></td>
                                            <td class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">03-2025</td>
                                            <td class="text-center">03/03/2025</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center">10,204.0</td>
                                            <td class="text-center">10,222.0</td>
                                            <td class="text-center">18.0</td>
                                            <td class="text-center"><span class="badge bg-success">READ</span></td>
                                            <td class="text-center"><i class="fas fa-camera text-primary"></i></td>
                                            <td class="text-center"></td>
                                        </tr>
                                        <tr class="table-warning">
                                            <td class="text-center">04-2025</td>
                                            <td class="text-center">04/02/2025</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center">10,222.0</td>
                                            <td class="text-center">10,222.0</td>
                                            <td class="text-center">0.0</td>
                                            <td class="text-center"><span class="badge bg-warning">AVERAGE</span></td>
                                            <td class="text-center">-</td>
                                            <td class="text-center">Consumer not home</td>
                                        </tr>
                                        <tr class="table-warning">
                                            <td class="text-center">05-2025</td>
                                            <td class="text-center">05/02/2025</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center">10,222.0</td>
                                            <td class="text-center">10,222.0</td>
                                            <td class="text-center">0.0</td>
                                            <td class="text-center"><span class="badge bg-warning">AVERAGE</span></td>
                                            <td class="text-center">-</td>
                                            <td class="text-center">Locked gate</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">06-2025</td>
                                            <td class="text-center">06/02/2025</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center">0.0</td>
                                            <td class="text-center">35.0</td>
                                            <td class="text-center">35.0</td>
                                            <td class="text-center"><span class="badge bg-info">NEW METER</span></td>
                                            <td class="text-center"><i class="fas fa-camera text-primary"></i></td>
                                            <td class="text-center">Meter replaced</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">07-2025</td>
                                            <td class="text-center">07/02/2025</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center">35.0</td>
                                            <td class="text-center">66.0</td>
                                            <td class="text-center">31.0</td>
                                            <td class="text-center"><span class="badge bg-success">READ</span></td>
                                            <td class="text-center"><i class="fas fa-camera text-primary"></i></td>
                                            <td class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">08-2025</td>
                                            <td class="text-center">08/02/2025</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center">66.0</td>
                                            <td class="text-center">97.0</td>
                                            <td class="text-center">31.0</td>
                                            <td class="text-center"><span class="badge bg-success">READ</span></td>
                                            <td class="text-center"><i class="fas fa-camera text-primary"></i></td>
                                            <td class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">09-2025</td>
                                            <td class="text-center">09/02/2025</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center">97.0</td>
                                            <td class="text-center">128.0</td>
                                            <td class="text-center">31.0</td>
                                            <td class="text-center"><span class="badge bg-success">READ</span></td>
                                            <td class="text-center"><i class="fas fa-camera text-primary"></i></td>
                                            <td class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">10-2025</td>
                                            <td class="text-center">10/02/2025</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center">128.0</td>
                                            <td class="text-center">159.0</td>
                                            <td class="text-center">31.0</td>
                                            <td class="text-center"><span class="badge bg-success">READ</span></td>
                                            <td class="text-center"><i class="fas fa-camera text-primary"></i></td>
                                            <td class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">11-2025</td>
                                            <td class="text-center">11/02/2025</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center">159.0</td>
                                            <td class="text-center">190.0</td>
                                            <td class="text-center">31.0</td>
                                            <td class="text-center"><span class="badge bg-success">READ</span></td>
                                            <td class="text-center"><i class="fas fa-camera text-primary"></i></td>
                                            <td class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td class="text-center">12-2025</td>
                                            <td class="text-center">12/02/2025</td>
                                            <td class="text-center">MARLO</td>
                                            <td class="text-center">190.0</td>
                                            <td class="text-center">221.0</td>
                                            <td class="text-center">31.0</td>
                                            <td class="text-center"><span class="badge bg-success">READ</span></td>
                                            <td class="text-center"><i class="fas fa-camera text-primary"></i></td>
                                            <td class="text-center"></td>
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
        function updateMeterReadingConsumer() {
            var stored = sessionStorage.getItem('currentConsumer');
            var badge = $('#meterReadingAccountBadge');
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
        updateMeterReadingConsumer();
        setTimeout(updateMeterReadingConsumer, 200);
        setInterval(updateMeterReadingConsumer, 1500);
        $(window).on('storage', function(e) {
            if (e.originalEvent && e.originalEvent.key === 'currentConsumer') updateMeterReadingConsumer();
        });
    });
</script>
</body>
</html>

