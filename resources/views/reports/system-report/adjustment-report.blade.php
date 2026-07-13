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
                        <h1 class="h3 mb-0 text-gray-800">Adjustment Report</h1>
                        <div>
                            <button class="btn btn-primary btn-sm mr-2">
                                <i class="fas fa-sync-alt mr-1"></i>Generate
                            </button>
                            <button class="btn btn-danger btn-sm">
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
                                        <!-- Date Range From -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Date From</label>
                                            <input type="date" class="form-control form-control-sm" value="2025-08-01">
                                        </div>

                                        <!-- Date Range To -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Date To</label>
                                            <input type="date" class="form-control form-control-sm" value="2025-08-31">
                                        </div>

                                        <!-- Zone/Route -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Zone / Route</label>
                                            <select class="form-control form-control-sm">
                                                <option value="">All Zones</option>
                                                @foreach(($zones ?? \App\Models\ConsumerZone::distinctZoneCodes()) as $zoneCode)
                                                    <option value="{{ $zoneCode }}">{{ $zoneCode }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <!-- Adjustment Type -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Adjustment Type</label>
                                            <select class="form-control form-control-sm">
                                                <option value="">All Types</option>
                                                <option>Credit Adjustment</option>
                                                <option>Debit Adjustment</option>
                                                <option>Billing Correction</option>
                                                <option>Meter Reading Correction</option>
                                                <option>Penalty Waiver</option>
                                                <option>Others</option>
                                            </select>
                                        </div>

                                        <!-- Status -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Status</label>
                                            <select class="form-control form-control-sm">
                                                <option value="">All Status</option>
                                                <option>Approved</option>
                                                <option>Pending</option>
                                                <option>Rejected</option>
                                            </select>
                                        </div>

                                        <!-- Approved By -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Approved By</label>
                                            <select class="form-control form-control-sm">
                                                <option value="">All</option>
                                                <option>Manager 1</option>
                                                <option>Manager 2</option>
                                                <option>Supervisor</option>
                                            </select>
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
                                    <a class="nav-link" data-toggle="tab" href="#summaryType">Summary by Type</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#summaryZone">Summary by Zone</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#summaryApprover">Summary by Approver</a>
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
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Adj. No</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Date</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Account No</th>
                                                            <th class="py-2 px-2" style="min-width: 180px;">Account Name</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 80px;">Zone</th>
                                                            <th class="py-2 px-2" style="min-width: 150px;">Adjustment Type</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Amount</th>
                                                            <th class="py-2 px-2" style="min-width: 200px;">Reason</th>
                                                            <th class="py-2 px-2" style="min-width: 120px;">Approved By</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 80px;">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td class="text-center py-1">1</td>
                                                            <td class="text-center py-1">ADJ-2025-0001</td>
                                                            <td class="text-center py-1">08/01/2025</td>
                                                            <td class="text-center py-1">011-12-020</td>
                                                            <td class="py-1 px-2">Gregorio, Margarito R.</td>
                                                            <td class="text-center py-1">011</td>
                                                            <td class="py-1 px-2">Credit Adjustment</td>
                                                            <td class="text-right py-1 px-2 text-success">-150.00</td>
                                                            <td class="py-1 px-2">Overbilling correction</td>
                                                            <td class="py-1 px-2">Manager 1</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Approved</span></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">2</td>
                                                            <td class="text-center py-1">ADJ-2025-0002</td>
                                                            <td class="text-center py-1">08/03/2025</td>
                                                            <td class="text-center py-1">011-12-040</td>
                                                            <td class="py-1 px-2">Duroja, Rebecca R.</td>
                                                            <td class="text-center py-1">011</td>
                                                            <td class="py-1 px-2">Billing Correction</td>
                                                            <td class="text-right py-1 px-2 text-success">-250.00</td>
                                                            <td class="py-1 px-2">Meter reading error</td>
                                                            <td class="py-1 px-2">Manager 1</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Approved</span></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">3</td>
                                                            <td class="text-center py-1">ADJ-2025-0003</td>
                                                            <td class="text-center py-1">08/05/2025</td>
                                                            <td class="text-center py-1">011-12-050</td>
                                                            <td class="py-1 px-2">Tanguanco, Lucena</td>
                                                            <td class="text-center py-1">011</td>
                                                            <td class="py-1 px-2">Penalty Waiver</td>
                                                            <td class="text-right py-1 px-2 text-success">-50.00</td>
                                                            <td class="py-1 px-2">Senior citizen discount</td>
                                                            <td class="py-1 px-2">Manager 2</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Approved</span></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">4</td>
                                                            <td class="text-center py-1">ADJ-2025-0004</td>
                                                            <td class="text-center py-1">08/07/2025</td>
                                                            <td class="text-center py-1">011-12-060</td>
                                                            <td class="py-1 px-2">Ramos, Marianne M</td>
                                                            <td class="text-center py-1">011</td>
                                                            <td class="py-1 px-2">Debit Adjustment</td>
                                                            <td class="text-right py-1 px-2 text-danger">+100.00</td>
                                                            <td class="py-1 px-2">Underbilling correction</td>
                                                            <td class="py-1 px-2">Supervisor</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Approved</span></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">5</td>
                                                            <td class="text-center py-1">ADJ-2025-0005</td>
                                                            <td class="text-center py-1">08/09/2025</td>
                                                            <td class="text-center py-1">011-12-070</td>
                                                            <td class="py-1 px-2">Paguinulan, Erlinda O.</td>
                                                            <td class="text-center py-1">011</td>
                                                            <td class="py-1 px-2">Credit Adjustment</td>
                                                            <td class="text-right py-1 px-2 text-success">-200.00</td>
                                                            <td class="py-1 px-2">Leak adjustment</td>
                                                            <td class="py-1 px-2">Manager 1</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Approved</span></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">6</td>
                                                            <td class="text-center py-1">ADJ-2025-0006</td>
                                                            <td class="text-center py-1">08/11/2025</td>
                                                            <td class="text-center py-1">011-12-080</td>
                                                            <td class="py-1 px-2">Indino, Feliciano</td>
                                                            <td class="text-center py-1">011</td>
                                                            <td class="py-1 px-2">Meter Reading Correction</td>
                                                            <td class="text-right py-1 px-2 text-success">-75.50</td>
                                                            <td class="py-1 px-2">Defective meter</td>
                                                            <td class="py-1 px-2">Manager 2</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Approved</span></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">7</td>
                                                            <td class="text-center py-1">ADJ-2025-0007</td>
                                                            <td class="text-center py-1">08/13/2025</td>
                                                            <td class="text-center py-1">011-12-090</td>
                                                            <td class="py-1 px-2">Casañas, Temodita</td>
                                                            <td class="text-center py-1">011</td>
                                                            <td class="py-1 px-2">Credit Adjustment</td>
                                                            <td class="text-right py-1 px-2 text-success">-180.00</td>
                                                            <td class="py-1 px-2">System error correction</td>
                                                            <td class="py-1 px-2">Manager 1</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Approved</span></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">8</td>
                                                            <td class="text-center py-1">ADJ-2025-0008</td>
                                                            <td class="text-center py-1">08/15/2025</td>
                                                            <td class="text-center py-1">011-12-095</td>
                                                            <td class="py-1 px-2">Tapulado, Lorenzo, M</td>
                                                            <td class="text-center py-1">011</td>
                                                            <td class="py-1 px-2">Penalty Waiver</td>
                                                            <td class="text-right py-1 px-2 text-success">-25.00</td>
                                                            <td class="py-1 px-2">First-time late payment</td>
                                                            <td class="py-1 px-2">Supervisor</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Approved</span></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">9</td>
                                                            <td class="text-center py-1">ADJ-2025-0009</td>
                                                            <td class="text-center py-1">08/17/2025</td>
                                                            <td class="text-center py-1">011-12-100</td>
                                                            <td class="py-1 px-2">Cantillan, Maricel</td>
                                                            <td class="text-center py-1">011</td>
                                                            <td class="py-1 px-2">Others</td>
                                                            <td class="text-right py-1 px-2 text-success">-120.00</td>
                                                            <td class="py-1 px-2">Special consideration</td>
                                                            <td class="py-1 px-2">Manager 2</td>
                                                            <td class="text-center py-1"><span class="badge badge-warning">Pending</span></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">10</td>
                                                            <td class="text-center py-1">ADJ-2025-0010</td>
                                                            <td class="text-center py-1">08/19/2025</td>
                                                            <td class="text-center py-1">011-12-110</td>
                                                            <td class="py-1 px-2">Payac, Odessa Honeto A.</td>
                                                            <td class="text-center py-1">011</td>
                                                            <td class="py-1 px-2">Debit Adjustment</td>
                                                            <td class="text-right py-1 px-2 text-danger">+50.00</td>
                                                            <td class="py-1 px-2">Missed consumption</td>
                                                            <td class="py-1 px-2">Manager 1</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Approved</span></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-light py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Adjustment Report: August 2025
                                                </small>
                                                <small class="text-muted">
                                                    Total Adjustments: <span class="text-success">-₱ 900.50 (Credit)</span> | 
                                                    <span class="text-danger">+₱ 150.00 (Debit)</span> | 
                                                    Net: <span class="font-weight-bold text-success">-₱ 750.50</span>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary by Type Tab -->
                        <div class="tab-pane fade" id="summaryType">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Summary by Type will be displayed here.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary by Zone Tab -->
                        <div class="tab-pane fade" id="summaryZone">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Summary by Zone will be displayed here.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary by Approver Tab -->
                        <div class="tab-pane fade" id="summaryApprover">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Summary by Approver will be displayed here.
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
</body>
</html>

