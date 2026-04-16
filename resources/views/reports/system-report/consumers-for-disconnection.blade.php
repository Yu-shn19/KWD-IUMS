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
                        <h1 class="h3 mb-0 text-gray-800">Consumers for Disconnection</h1>
                        <div>
                            <button id="generateReportBtn" class="btn btn-primary btn-sm mr-2">
                                <i class="fas fa-sync-alt mr-1"></i>Generate
                            </button>
                            <button id="generate3ConsecutiveBtn" class="btn btn-success btn-sm mr-2">
                                <i class="fas fa-calendar-alt mr-1"></i>3 Months Consecutive
                            </button>
                            <button class="btn btn-danger btn-sm mr-2">
                                <i class="fas fa-print mr-1"></i>Print
                            </button>
                            <button class="btn btn-info btn-sm mr-2">
                                <i class="fas fa-file-alt mr-1"></i>Print Notice
                            </button>
                            <button class="btn btn-warning btn-sm">
                                <i class="fas fa-envelope mr-1"></i>Print Demand Notice
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
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Zone / Route</label>
                                            <select id="zoneSelect" class="form-control form-control-sm">
                                                <option value="">All Zones</option>
                                                @foreach($zones ?? [] as $zoneOption)
                                                    <option value="{{ $zoneOption }}" {{ ($selectedZone ?? '') == $zoneOption ? 'selected' : '' }}>{{ $zoneOption }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <!-- Bill Month -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Bill Month</label>
                                            <div class="d-flex">
                                                @php
                                                    $currentMonth = $billMonth ? (strpos($billMonth, '-') !== false ? explode('-', $billMonth)[0] : date('m')) : date('m');
                                                    $currentYear = $billMonth ? (strpos($billMonth, '-') !== false ? explode('-', $billMonth)[1] : date('Y')) : date('Y');
                                                @endphp
                                                <select id="monthSelect" class="form-control form-control-sm mr-1" style="width: 70px;">
                                                    @for($i = 1; $i <= 12; $i++)
                                                        <option value="{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}" {{ $currentMonth == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : '' }}>
                                                            {{ str_pad($i, 2, '0', STR_PAD_LEFT) }}
                                                        </option>
                                                    @endfor
                                                </select>
                                                <input type="number" id="yearInput" class="form-control form-control-sm" value="{{ $currentYear }}" style="width: 90px;">
                                            </div>
                                        </div>

                                          <!-- Status -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Status</label>
                                            <select id="statusSelect" class="form-control form-control-sm">
                                                <option value="">All Status</option>
                                                <option value="A - Active" {{ ($status ?? '') == 'A - Active' ? 'selected' : '' }}>A - Active</option>
                                                <option value="P - Pending" {{ ($status ?? '') == 'P - Pending' ? 'selected' : '' }}>P - Pending</option>
                                                <option value="X - Disconnected" {{ ($status ?? '') == 'X - Disconnected' ? 'selected' : '' }}>X - Disconnected</option>
                                            </select>
                                        </div>

                                        <!-- As of -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">As of</label>
                                            <input type="date" id="asOfInput" class="form-control form-control-sm" value="{{ ($asOf ?? now())->format('Y-m-d') }}">
                                        </div>

                                        <!-- Billing Cut-Off -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Billing Cut-Off</label>
                                            <input type="date" id="billingCutoffInput" class="form-control form-control-sm" value="{{ ($billingCutOff ?? now())->format('Y-m-d') }}">
                                        </div>

                                        <!-- Payment Cut-Off -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Payment Cut-Off</label>
                                            <input type="date" id="paymentCutoffInput" class="form-control form-control-sm" value="{{ ($paymentCutOff ?? now())->format('Y-m-d') }}">
                                        </div>
                                    </div>

                                    <div class="row mt-2">
                                        <!-- Discon Date -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Discon Date</label>
                                            <input type="date" id="disconDateInput" class="form-control form-control-sm" value="{{ ($disconDate ?? now())->format('Y-m-d') }}">
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
                                    <a class="nav-link active" data-toggle="tab" href="#details">Details</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#summaryDelinquents">Summary of Delinquents</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#noticeDisconnection">Notice for Disconnection</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#demandAccounts">Demand Accounts</a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- Details Tab -->
                        <div class="tab-pane fade show active" id="details">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body p-0">
                                            <div class="table-responsive" style="max-height: 600px; overflow: auto;">
                                                <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 10px;">
                                                    <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                                                        <tr>
                                                            <th class="text-center py-2 px-1" style="min-width: 40px;">#</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 70px;">Zone</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 70px;">Sequence</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 100px;">Account No</th>
                                                            <th class="py-2 px-1" style="min-width: 180px;">Account Name</th>
                                                            <th class="py-2 px-1" style="min-width: 200px;">Address</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 80px;">Status</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 80px;">Category</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 90px;">Current Bill</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 80px;">Arrears</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 90px;">Total Due</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 90px;">Last Payment</th>
                                                            <th class="text-center py-2 px-1" style="min-width: 100px;">Discon Date</th>
                                                            <th class="py-2 px-1" style="min-width: 100px;">Remarks</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="disconnectionTableBody">
                                                        @forelse($consumers ?? [] as $index => $consumer)
                                                            <tr>
                                                                <td class="text-center py-1">{{ $index + 1 }}</td>
                                                                <td class="text-center py-1">{{ $consumer['zone'] }}</td>
                                                                <td class="text-center py-1">{{ $consumer['sequence'] }}</td>
                                                                <td class="text-center py-1">{{ $consumer['account_number'] }}</td>
                                                                <td class="py-1 px-1">{{ $consumer['account_name'] }}</td>
                                                                <td class="py-1 px-1">{{ $consumer['address'] }}</td>
                                                                <td class="text-center py-1">{{ $consumer['status'] }}</td>
                                                                <td class="text-center py-1">{{ $consumer['category'] }}</td>
                                                                <td class="text-right py-1 px-1">₱ {{ number_format($consumer['current_bill'], 2) }}</td>
                                                                <td class="text-right py-1 px-1">₱ {{ number_format($consumer['arrears'], 2) }}</td>
                                                                <td class="text-right py-1 px-1">₱ {{ number_format($consumer['total_due'], 2) }}</td>
                                                                <td class="text-center py-1">{{ $consumer['last_payment'] }}</td>
                                                                <td class="text-center py-1">{{ $consumer['discon_date'] }}</td>
                                                                <td class="py-1 px-1">{{ $consumer['remarks'] }}</td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="14" class="text-center text-muted py-5">
                                                                    <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                                                    <p>No consumers found for disconnection (3+ months unpaid).</p>
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-light py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted" id="reportInfo">
                                                    <i class="fas fa-exclamation-triangle text-warning mr-1"></i>
                                                    @if(isset($selectedZone) && $selectedZone)
                                                        Zone {{ $selectedZone }} - Consumers for Disconnection as of {{ ($asOf ?? now())->format('m/d/Y') }}
                                                    @else
                                                        All Zones - Consumers for Disconnection as of {{ ($asOf ?? now())->format('m/d/Y') }}
                                                    @endif
                                                </small>
                                                <small class="text-danger font-weight-bold" id="summaryTotals">
                                                    Total Due: ₱ {{ number_format($totalDue ?? 0, 2) }} | Consumers: {{ $totalConsumers ?? 0 }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary of Delinquents Tab -->
                        <div class="tab-pane fade" id="summaryDelinquents">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Summary of Delinquents will be displayed here.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notice for Disconnection Tab -->
                        <div class="tab-pane fade" id="noticeDisconnection">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Notice for Disconnection will be displayed here.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Demand Accounts Tab -->
                        <div class="tab-pane fade" id="demandAccounts">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Demand Accounts will be displayed here.
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
            const generate3ConsecutiveBtn = document.getElementById('generate3ConsecutiveBtn');
            const zoneSelect = document.getElementById('zoneSelect');
            const monthSelect = document.getElementById('monthSelect');
            const yearInput = document.getElementById('yearInput');
            const statusSelect = document.getElementById('statusSelect');
            const asOfInput = document.getElementById('asOfInput');
            const billingCutoffInput = document.getElementById('billingCutoffInput');
            const paymentCutoffInput = document.getElementById('paymentCutoffInput');
            const disconDateInput = document.getElementById('disconDateInput');
            const tableBody = document.getElementById('disconnectionTableBody');
            const reportInfo = document.getElementById('reportInfo');
            const summaryTotals = document.getElementById('summaryTotals');
            const generateBtnOriginal = generateBtn.innerHTML;

            function formatCurrency(value) {
                return '₱ ' + parseFloat(value || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }

            function generateReport(filterType = 'disconnection_date') {
                generateBtn.disabled = true;
                if (generate3ConsecutiveBtn) generate3ConsecutiveBtn.disabled = true;
                generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Generating...';
                if (generate3ConsecutiveBtn) generate3ConsecutiveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Generating...';
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="14" class="text-center py-5">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3 d-block"></i>
                            <p>Loading consumers for disconnection...</p>
                        </td>
                    </tr>
                `;

                const zone = zoneSelect.value;
                const month = monthSelect.value;
                const year = yearInput.value;
                const status = statusSelect.value;
                const asOf = asOfInput.value;
                const billingCutoff = billingCutoffInput.value;
                const paymentCutoff = paymentCutoffInput.value;
                const disconDate = disconDateInput.value;

                let url = '{{ route("consumer-for-disconnection") }}?';
                url += 'filter_type=' + encodeURIComponent(filterType) + '&';
                if (zone) url += 'zone=' + encodeURIComponent(zone) + '&';
                if (month && year) url += 'bill_month=' + encodeURIComponent(month + '-' + year) + '&';
                if (status) url += 'status=' + encodeURIComponent(status) + '&';
                if (asOf) url += 'as_of=' + encodeURIComponent(asOf) + '&';
                if (billingCutoff) url += 'billing_cutoff=' + encodeURIComponent(billingCutoff) + '&';
                if (paymentCutoff) url += 'payment_cutoff=' + encodeURIComponent(paymentCutoff) + '&';
                if (disconDate) url += 'discon_date=' + encodeURIComponent(disconDate);

                // Remove trailing & if exists
                url = url.replace(/&$/, '');

                // Reload page with filters
                window.location.href = url;
            }

            generateBtn.addEventListener('click', function() {
                generateReport('disconnection_date');
            });

            if (generate3ConsecutiveBtn) {
                generate3ConsecutiveBtn.addEventListener('click', function() {
                    generateReport('3_consecutive');
                });
            }

            // Auto-generate on page load if filters are set
            @if(request()->hasAny(['zone', 'bill_month', 'status', 'as_of']))
                // Data already loaded from server
            @endif
        });
    </script>
</body>
</html>

