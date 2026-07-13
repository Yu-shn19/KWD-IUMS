<div class="container-fluid" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); min-height: 100vh; padding: 15px;">
    <!-- Top Bar / Header -->
    <div class="card shadow-lg" style="border-radius: 8px 8px 0 0;">
        <div class="card-header bg-primary text-white" style="border-radius: 8px 8px 0 0;">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0 font-weight-bold">031-12-9999 Siarot, Thelma C.</h5>
                </div>
                <div class="col-md-6 text-right">
                    <h4 class="mb-0 font-weight-bold">Consumers</h4>
                </div>
            </div>
        </div>
        
        <!-- Navigation Tabs -->
        <div class="card-body bg-info p-2">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-primary btn-sm active">Consumer Details</button>
                        <button type="button" class="btn btn-info btn-sm">F10-Account Ledger</button>
                        <button type="button" class="btn btn-info btn-sm">LRQ Ledger</button>
                        <button type="button" class="btn btn-info btn-sm">Service History</button>
                        <button type="button" class="btn btn-info btn-sm">Meter Reading History</button>
                        <button type="button" class="btn btn-info btn-sm">Location Map</button>
                        <button type="button" class="btn btn-info btn-sm">Consumption Graph</button>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="col-md-4">
                    <div class="btn-toolbar justify-content-end" role="toolbar">
                        <div class="btn-group mr-2" role="group">
                            <button type="button" class="btn btn-primary btn-sm">K</button>
                            <button type="button" class="btn btn-primary btn-sm"><</button>
                            <button type="button" class="btn btn-primary btn-sm">></button>
                            <button type="button" class="btn btn-primary btn-sm">>|</button>
                        </div>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-success btn-sm">New</button>
                            <button type="button" class="btn btn-primary btn-sm">Edit</button>
                            <button type="button" class="btn btn-danger btn-sm">Delete</button>
                            <button type="button" class="btn btn-light btn-sm">
                                <i class="fas fa-search"></i> Search - F
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="card shadow-lg" style="border-radius: 0 0 8px 8px;">
        <div class="card-body p-4">
            <div class="row">
                
                <!-- Left Column - Information, Charges, Meter Reading -->
                <div class="col-lg-8">
                    
                    <!-- Information Section -->
                    <div class="card mb-4" style="border: 2px solid #2196F3;">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0 font-weight-bold">Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Left Column -->
                                <div class="col-md-6">
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">Installation Date:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" value="08/11/2025" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">Account #:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" value="031-12-9999" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">Meter No:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" value="A" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">Address:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" value="Purok 3, Gilda sub.," readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">Category:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" value="Residential" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">Remarks:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">Bill Disc [%]:</label>
                                        <div class="col-sm-6">
                                            <input type="text" class="form-control form-control-sm" value="0.0 OSCA IDE" readonly>
                                        </div>
                                        <div class="col-sm-1">
                                            <input type="checkbox" class="form-check-input">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Right Column -->
                                <div class="col-md-6">
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">Status:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" value="A - ACTIVE" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">Name:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" value="Siarot, Thelma C." readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">Brand:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" value="Ever" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">Zone:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" value="2A" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">CardNo:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" value="9999" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">Billing Tag:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" value="1" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">SR No:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">Meter Loc:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">AppDate:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" value="08/11/2025" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label text-muted small">ExpDate:</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control form-control-sm" value="08/11/2025" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charges Section -->
                    <div class="card mb-4" style="border: 2px solid #2196F3;">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0 font-weight-bold">Charges</h5>
                            <small>Entry</small>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="border-0">Date</th>
                                            <th class="border-0">Description</th>
                                            <th class="border-0">Charge</th>
                                            <th class="border-0">Amort</th>
                                            <th class="border-0">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="border-0"></td>
                                            <td class="border-0"></td>
                                            <td class="border-0"></td>
                                            <td class="border-0"></td>
                                            <td class="border-0"></td>
                                        </tr>
                                        <tr>
                                            <td class="border-0"></td>
                                            <td class="border-0"></td>
                                            <td class="border-0"></td>
                                            <td class="border-0"></td>
                                            <td class="border-0"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Meter Reading Section -->
                    <div class="card mb-4" style="border: 2px solid #2196F3;">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0 font-weight-bold">Meter Reading</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title text-primary font-weight-bold">Current</h6>
                                            <div class="row">
                                                <div class="col-6">
                                                    <small class="text-muted">Date:</small>
                                                    <div class="font-weight-bold">08/11/2025</div>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Reading:</small>
                                                    <div class="font-weight-bold">0</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title text-primary font-weight-bold">Previous</h6>
                                            <div class="row">
                                                <div class="col-6">
                                                    <small class="text-muted">Date:</small>
                                                    <div class="font-weight-bold">08/11/2025</div>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Reading:</small>
                                                    <div class="font-weight-bold">0</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Latest Bill -->
                <div class="col-lg-4">
                    <div class="card" style="border: 2px solid #2196F3;">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0 font-weight-bold">Latest Bill - 08-2025</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group row mb-2">
                                <label class="col-sm-6 col-form-label text-muted small">Current Bill:</label>
                                <div class="col-sm-6">
                                    <input type="text" class="form-control form-control-sm text-right" value="0.00" readonly>
                                </div>
                            </div>
                            <div class="form-group row mb-2">
                                <label class="col-sm-6 col-form-label text-muted small">Meter Rental:</label>
                                <div class="col-sm-6">
                                    <input type="text" class="form-control form-control-sm text-right" value="0.00" readonly>
                                </div>
                            </div>
                            <div class="form-group row mb-2">
                                <label class="col-sm-6 col-form-label text-muted small">Arrears:</label>
                                <div class="col-sm-6">
                                    <input type="text" class="form-control form-control-sm text-right" value="0.00" readonly>
                                </div>
                            </div>
                            <div class="form-group row mb-2">
                                <label class="col-sm-6 col-form-label text-muted small">Materials:</label>
                                <div class="col-sm-6">
                                    <input type="text" class="form-control form-control-sm text-right" value="0.00" readonly>
                                </div>
                            </div>
                            <div class="form-group row mb-2">
                                <label class="col-sm-6 col-form-label text-muted small">Septage Fee:</label>
                                <div class="col-sm-6">
                                    <input type="text" class="form-control form-control-sm text-right" value="0.00" readonly>
                                </div>
                            </div>
                            <div class="form-group row mb-2">
                                <label class="col-sm-6 col-form-label text-muted small">Others:</label>
                                <div class="col-sm-6">
                                    <input type="text" class="form-control form-control-sm text-right" value="0.00" readonly>
                                </div>
                            </div>
                            <hr>
                            <div class="form-group row mb-0">
                                <label class="col-sm-6 col-form-label font-weight-bold text-primary">TOTAL BILL:</label>
                                <div class="col-sm-6">
                                    <input type="text" class="form-control form-control-sm text-right font-weight-bold bg-primary text-white" value="0.00" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer / Status Bar -->
    <div class="card mt-3" style="border-radius: 0 0 8px 8px;">
        <div class="card-body bg-dark text-white py-2">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <span class="small">MARLO as ADMIN</span>
                    <span class="small text-muted ml-3">Jums hadonor on 192.1568.2.101</span>
                </div>
                <div class="col-md-6 text-right">
                    <span class="small mr-3">8/12/2025</span>
                    <span class="small mr-3">
                        <i class="fas fa-cloud-rain mr-1"></i>
                        1 cm of rain Wed
                    </span>
                    <span class="small mr-3">9:07 AM</span>
                    <span class="small mr-3">8/12/2025</span>
                    <i class="fas fa-battery-three-quarters mr-2"></i>
                    <i class="fas fa-wifi"></i>
                </div>
            </div>
        </div>
    </div>
</div>
