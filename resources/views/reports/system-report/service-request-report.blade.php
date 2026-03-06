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
                        <h1 class="h3 mb-0 text-gray-800">Service Request Report</h1>
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
                                        <!-- Status -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Status</label>
                                            <select class="form-control form-control-sm">
                                                <option selected>COMPLETED</option>
                                                <option>PENDING</option>
                                                <option>IN PROGRESS</option>
                                                <option>CANCELLED</option>
                                                <option>All Status</option>
                                            </select>
                                        </div>

                                        <!-- Date Range From -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">From</label>
                                            <input type="date" class="form-control form-control-sm" value="2025-08-12">
                                        </div>

                                        <!-- Date Range To -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">To</label>
                                            <input type="date" class="form-control form-control-sm" value="2025-08-12">
                                        </div>

                                        <!-- Status Filter -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Status</label>
                                            <select class="form-control form-control-sm">
                                                <option value="">All</option>
                                                <option>Active</option>
                                                <option>Inactive</option>
                                                <option>Suspended</option>
                                            </select>
                                        </div>

                                        <!-- Complain/Request -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Complain / Request</label>
                                            <select class="form-control form-control-sm">
                                                <option value="">All</option>
                                                <option>New Connection</option>
                                                <option>Leak Repair</option>
                                                <option>Meter Change</option>
                                                <option>Disconnection</option>
                                                <option>Reconnection</option>
                                                <option>Others</option>
                                            </select>
                                        </div>

                                        <!-- Action Taken -->
                                        <div class="col-md-2">
                                            <label class="small font-weight-bold mb-1">Action Taken</label>
                                            <select class="form-control form-control-sm">
                                                <option value="">All</option>
                                                <option>Repaired</option>
                                                <option>Replaced</option>
                                                <option>Installed</option>
                                                <option>Disconnected</option>
                                                <option>For Follow-up</option>
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
                                    <a class="nav-link" data-toggle="tab" href="#summaryActionTaken">Summary by Action Taken</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#summaryRequest">Summary by Request</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#disconnectedMeters">Disconnected Meters</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#changeMeter">Change Meter</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#reconnectedMeters">Reconnected Meters</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#accomplishmentNotice">Accomplishment or Demand Notice</a>
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
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Request No</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Date Filed</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Account No</th>
                                                            <th class="py-2 px-2" style="min-width: 180px;">Account Name</th>
                                                            <th class="py-2 px-2" style="min-width: 200px;">Address</th>
                                                            <th class="py-2 px-2" style="min-width: 150px;">Complain/Request</th>
                                                            <th class="py-2 px-2" style="min-width: 150px;">Action Taken</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 100px;">Date Completed</th>
                                                            <th class="text-center py-2 px-2" style="min-width: 80px;">Status</th>
                                                            <th class="py-2 px-2" style="min-width: 150px;">Remarks</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td class="text-center py-1">1</td>
                                                            <td class="text-center py-1">SR-2025-001</td>
                                                            <td class="text-center py-1">08/01/2025</td>
                                                            <td class="text-center py-1">011-12-020</td>
                                                            <td class="py-1 px-2">Gregorio, Margarito R.</td>
                                                            <td class="py-1 px-2">Purok 5C, Guitnang, Hag DS</td>
                                                            <td class="py-1 px-2">Leak Repair</td>
                                                            <td class="py-1 px-2">Pipe Replaced</td>
                                                            <td class="text-center py-1">08/02/2025</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Completed</span></td>
                                                            <td class="py-1 px-2">Main line leak fixed</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">2</td>
                                                            <td class="text-center py-1">SR-2025-002</td>
                                                            <td class="text-center py-1">08/03/2025</td>
                                                            <td class="text-center py-1">011-12-040</td>
                                                            <td class="py-1 px-2">Duroja, Rebecca R.</td>
                                                            <td class="py-1 px-2">Natividad Road, Guitnang, Hagonoy DS</td>
                                                            <td class="py-1 px-2">Meter Change</td>
                                                            <td class="py-1 px-2">Meter Replaced</td>
                                                            <td class="text-center py-1">08/04/2025</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Completed</span></td>
                                                            <td class="py-1 px-2">Old meter defective</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">3</td>
                                                            <td class="text-center py-1">SR-2025-003</td>
                                                            <td class="text-center py-1">08/05/2025</td>
                                                            <td class="text-center py-1">011-12-050</td>
                                                            <td class="py-1 px-2">Tanguanco, Lucena</td>
                                                            <td class="py-1 px-2">Purok 5-C, Guitnang, Hag DS</td>
                                                            <td class="py-1 px-2">Low Water Pressure</td>
                                                            <td class="py-1 px-2">Pipe Cleared</td>
                                                            <td class="text-center py-1">08/05/2025</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Completed</span></td>
                                                            <td class="py-1 px-2">Blockage removed</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">4</td>
                                                            <td class="text-center py-1">SR-2025-004</td>
                                                            <td class="text-center py-1">08/06/2025</td>
                                                            <td class="text-center py-1">011-12-060</td>
                                                            <td class="py-1 px-2">Ramos, Marianne M</td>
                                                            <td class="py-1 px-2">Mc Arthur National Highway, Guitnang</td>
                                                            <td class="py-1 px-2">New Connection</td>
                                                            <td class="py-1 px-2">Connection Installed</td>
                                                            <td class="text-center py-1">08/08/2025</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Completed</span></td>
                                                            <td class="py-1 px-2">New service line installed</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">5</td>
                                                            <td class="text-center py-1">SR-2025-005</td>
                                                            <td class="text-center py-1">08/07/2025</td>
                                                            <td class="text-center py-1">011-12-070</td>
                                                            <td class="py-1 px-2">Paguinulan, Erlinda O.</td>
                                                            <td class="py-1 px-2">Mc Arthur National Highway, Guitnang</td>
                                                            <td class="py-1 px-2">Disconnection Request</td>
                                                            <td class="py-1 px-2">Service Disconnected</td>
                                                            <td class="text-center py-1">08/09/2025</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Completed</span></td>
                                                            <td class="py-1 px-2">Non-payment</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">6</td>
                                                            <td class="text-center py-1">SR-2025-006</td>
                                                            <td class="text-center py-1">08/08/2025</td>
                                                            <td class="text-center py-1">011-12-080</td>
                                                            <td class="py-1 px-2">Indino, Feliciano</td>
                                                            <td class="py-1 px-2">Mc Arthur National Highway, Guitnang</td>
                                                            <td class="py-1 px-2">Reconnection Request</td>
                                                            <td class="py-1 px-2">Service Reconnected</td>
                                                            <td class="text-center py-1">08/10/2025</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Completed</span></td>
                                                            <td class="py-1 px-2">Payment settled</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">7</td>
                                                            <td class="text-center py-1">SR-2025-007</td>
                                                            <td class="text-center py-1">08/09/2025</td>
                                                            <td class="text-center py-1">011-12-090</td>
                                                            <td class="py-1 px-2">Casañas, Temodita</td>
                                                            <td class="py-1 px-2">Mc Arthur National Highway, Guitnang</td>
                                                            <td class="py-1 px-2">Meter Reading Issue</td>
                                                            <td class="py-1 px-2">Meter Checked</td>
                                                            <td class="text-center py-1">08/11/2025</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Completed</span></td>
                                                            <td class="py-1 px-2">Meter functioning properly</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">8</td>
                                                            <td class="text-center py-1">SR-2025-008</td>
                                                            <td class="text-center py-1">08/10/2025</td>
                                                            <td class="text-center py-1">011-12-095</td>
                                                            <td class="py-1 px-2">Tapulado, Lorenzo, M</td>
                                                            <td class="py-1 px-2">Purok 5-A, Guitnang, Hag DS</td>
                                                            <td class="py-1 px-2">No Water Supply</td>
                                                            <td class="py-1 px-2">Valve Opened</td>
                                                            <td class="text-center py-1">08/10/2025</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Completed</span></td>
                                                            <td class="py-1 px-2">Valve accidentally closed</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">9</td>
                                                            <td class="text-center py-1">SR-2025-009</td>
                                                            <td class="text-center py-1">08/11/2025</td>
                                                            <td class="text-center py-1">011-12-100</td>
                                                            <td class="py-1 px-2">Cantillan, Maricel</td>
                                                            <td class="py-1 px-2">Purok 6B, Guitnang, Hag DS</td>
                                                            <td class="py-1 px-2">Billing Inquiry</td>
                                                            <td class="py-1 px-2">Explained Bill</td>
                                                            <td class="text-center py-1">08/11/2025</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Completed</span></td>
                                                            <td class="py-1 px-2">Bill clarified</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="text-center py-1">10</td>
                                                            <td class="text-center py-1">SR-2025-010</td>
                                                            <td class="text-center py-1">08/12/2025</td>
                                                            <td class="text-center py-1">011-12-110</td>
                                                            <td class="py-1 px-2">Payac, Odessa Honeto A.</td>
                                                            <td class="py-1 px-2">Mc Arthur National Highway, Guitnang</td>
                                                            <td class="py-1 px-2">Leak Repair</td>
                                                            <td class="py-1 px-2">Joint Sealed</td>
                                                            <td class="text-center py-1">08/12/2025</td>
                                                            <td class="text-center py-1"><span class="badge badge-success">Completed</span></td>
                                                            <td class="py-1 px-2">Minor leak at joint</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-light py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Service Requests from 08/12/2025 to 08/12/2025
                                                </small>
                                                <small class="text-muted">
                                                    Total Completed: <strong class="text-success">10</strong> requests
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary by Action Taken Tab -->
                        <div class="tab-pane fade" id="summaryActionTaken">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Summary by Action Taken will be displayed here.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary by Request Tab -->
                        <div class="tab-pane fade" id="summaryRequest">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Summary by Request will be displayed here.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Disconnected Meters Tab -->
                        <div class="tab-pane fade" id="disconnectedMeters">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Disconnected Meters report will be displayed here.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Change Meter Tab -->
                        <div class="tab-pane fade" id="changeMeter">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Change Meter report will be displayed here.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reconnected Meters Tab -->
                        <div class="tab-pane fade" id="reconnectedMeters">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Reconnected Meters report will be displayed here.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Accomplishment or Demand Notice Tab -->
                        <div class="tab-pane fade" id="accomplishmentNotice">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Accomplishment or Demand Notice will be displayed here.
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

