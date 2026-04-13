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
                        <!-- Page Header -->
                        <div class="d-sm-flex align-items-center justify-content-between mb-4">
                            <div>
                                <h1 class="h3 mb-1 text-gray-800 font-weight-bold">Billing Processes Management</h1>
                                <p class="text-muted mb-0 small">Configure and execute billing operations</p>
                            </div>
                            <div class="text-muted small">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                <span id="currentDate"></span>
                            </div>
                        </div>

                        <!-- Tabs: Billing Processes | Meter Reading Schedule Viewing -->
                        <ul class="nav nav-tabs mb-3" id="billingProcessTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link active" id="tab-billing-processes" data-toggle="tab" href="#pane-billing-processes" role="tab" aria-controls="pane-billing-processes" aria-selected="true">
                                    <i class="fas fa-cogs mr-1"></i>Billing Processes
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="tab-schedule-viewing" data-toggle="tab" href="#pane-schedule-viewing" role="tab" aria-controls="pane-schedule-viewing" aria-selected="false">
                                    <i class="fas fa-calendar-check mr-1"></i>Meter Reading Schedule Viewing
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content" id="billingProcessTabContent">
                            <div class="tab-pane fade show active" id="pane-billing-processes" role="tabpanel" aria-labelledby="tab-billing-processes">
    <!-- Data Table Section -->
    <div class="row mb-4">
                            <div class="col-lg-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-info rounded p-2 mr-3">
                                                <i class="fas fa-search-dollar text-white"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 font-weight-bold text-dark">Quick Account Lookup</h6>
                                                <small class="text-muted">Enter an account number to display the account name and most recent downloaded bill.</small>
                                            </div>
                                        </div>
                                        <form id="quickLookupForm" class="mb-0">
                                            <div class="row align-items-end">
                                                <div class="col-lg-4">
                                                    <div class="form-group mb-3">
                                                        <label for="quickLookupAccount" class="font-weight-bold text-dark small mb-2">Account Number</label>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control text-uppercase" id="quickLookupAccount" placeholder="051-12-1820">
                                                            <div class="input-group-append">
                                                                <button type="submit" class="btn btn-outline-primary" id="quickLookupButton">
                                                                    <i class="fas fa-search mr-1"></i>Lookup
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <small id="quickLookupStatus" class="form-text text-muted">Provide an account number to fetch the latest downloaded bill.</small>
                                                    </div>
                                                </div>
                                                <div class="col-lg-3">
                                                    <p class="text-muted small mb-1">Account Name</p>
                                                    <h6 class="mb-0 font-weight-bold text-dark" id="quickAccountName">—</h6>
                                                    <p class="text-muted small mb-0">Zone: <span class="text-dark font-weight-bold" id="quickAccountZone">—</span></p>
                                                </div>
                                                <div class="col-lg-3">
                                                    <p class="text-muted small mb-1">Latest Bill Amount</p>
                                                    <h5 class="mb-0 text-success font-weight-bold" id="quickLatestBillAmount">₱ 0.00</h5>
                                                    <p class="text-muted small mb-0">Bill Month: <span class="text-dark font-weight-bold" id="quickLatestBillMonth">—</span></p>
                                                </div>
                                                <div class="col-lg-2">
                                                    <p class="text-muted small mb-1">Status</p>
                                                    <span class="badge badge-secondary px-3 py-2" id="quickLatestBillStatus">No Record</span>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Control Panel Container -->
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="card mb-4 border-0 shadow">
                                    <div class="card-header bg-white border-bottom py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary rounded p-2 mr-3">
                                                <i class="fas fa-cogs text-white"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 font-weight-bold text-dark">Control Panel</h6>
                                                <small class="text-muted">Configure billing process parameters</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body bg-light p-4">
                                        <div class="row">
                                            <!-- Process Configuration Section -->
                                            <div class="col-lg-4 mb-3">
                                                <div class="card border h-100 shadow-sm">
                                                    <div class="card-body">
                                                        <h6 class="font-weight-bold text-dark mb-3 pb-2 border-bottom">
                                                            <i class="fas fa-sliders-h text-primary mr-2"></i>Process Configuration
                                                        </h6>
                                                        
                                                        <!-- Process Type -->
                                                        <div class="form-group mb-3">
                                                            <label for="processSelect" class="font-weight-bold text-dark small mb-2">
                                                                Process Type <span class="text-danger">*</span>
                                                            </label>
                                                            <select class="form-control" id="processSelect" style="font-size: 14px;">
                                                                <option selected>Meter Reading Preparation</option>
                                                                <option>Meter Reading Preparation (Single Consumer)</option>
                                                                <option>Meter Reading Preparation (Multiple Consumers)</option>
                                                                <option>Bill Printing</option>
                                                                <option>Generate Surcharge</option>
                                                                <option>Generate Penalty (Single Consumer)</option>
                                                            </select>
                                                        </div>

                                                        <!-- Multiple Consumers Accounts (only for Multiple Consumers preparation) -->
                                                        <div class="form-group mb-3" id="multipleConsumersAccountGroup" style="display: none;">
                                                            <label for="multipleConsumersAccounts" class="font-weight-bold text-dark small mb-2">
                                                                Account Numbers <span class="text-danger">*</span>
                                                            </label>
                                                            <textarea class="form-control" id="multipleConsumersAccounts" rows="4" placeholder="Enter one account per line or comma-separated&#10;e.g. 031-12-1460&#10;031-12-1461&#10;071-00001" style="font-size: 14px;"></textarea>
                                                            <small class="text-muted">One account per line or comma-separated. All will be prepared together and can be saved and assigned to one reader.</small>
                                                        </div>
                                                        <!-- Single Consumer Account (only for Single Consumer preparation) -->
                                                        <div class="form-group mb-3" id="singleConsumerAccountGroup" style="display: none;">
                                                            <label for="singleConsumerAccount" class="font-weight-bold text-dark small mb-2">
                                                                Account Number <span class="text-danger">*</span>
                                                            </label>
                                                            <input type="text" class="form-control" id="singleConsumerAccount" placeholder="e.g. 011-00001" style="font-size: 14px;">
                                                            <small class="text-muted">Consumer account to prepare meter reading for</small>
                                                        </div>

                                                        <!-- Zone Selection -->
                                                        <div class="form-group mb-3" id="zoneSelectGroup">
                                                            <label for="zoneSelect" class="font-weight-bold text-dark small mb-2">
                                                                Zone <span class="text-danger">*</span>
                                                            </label>
                                                            <select class="form-control" id="zoneSelect" style="font-size: 14px;">
                                                                <option value="">-- Select Zone --</option>
                                                                <option value="011">Zone 011</option>
                                                                <option value="021">Zone 021</option>
                                                                <option value="031">Zone 031</option>
                                                                <option value="041">Zone 041</option>
                                                                <option value="051">Zone 051</option>
                                                                <option value="061">Zone 061</option>
                                                                <option value="071">Zone 071</option>
                                                                <option value="081">Zone 081</option>
                                                                <option value="091">Zone 091</option>
                                                            
                                                            </select>
                                                        </div>

                                                        <!-- Bill Month (Hidden for Bill Printing) -->
                                                        <div class="form-group mb-0" id="billMonthGroup">
                                                            <label for="billMonth" class="font-weight-bold text-dark small mb-2">
                                                                Bill Month <span class="text-danger">*</span>
                                                            </label>
                                                            <input type="date" class="form-control" id="billMonth"  style="font-size: 14px;">
                                                        </div>

                                                        <!-- Reading Date (Only for Bill Printing) -->
                                                        <div class="form-group mb-0" id="readingDateGroup" style="display: none;">
                                                            <label for="readingDateInput" class="font-weight-bold text-dark small mb-2">
                                                                Reading Date <span class="text-danger">*</span>
                                                            </label>
                                                            <input type="date" class="form-control" id="readingDateInput" style="font-size: 14px;">
                                                            <small class="text-muted">Select the date when readings were taken</small>
                                                        </div>

                                                        <!-- Bill Date (Only for Generate Surcharge) -->
                                                        <div class="form-group mb-0" id="surchargeDateGroup" style="display: none;">
                                                            <label for="surchargeBillDate" class="font-weight-bold text-dark small mb-2">
                                                                Bill Date <span class="text-danger">*</span>
                                                            </label>
                                                            <input type="date" class="form-control" id="surchargeBillDate" style="font-size: 14px;">
                                                            <small class="text-muted">Bill date for past-due consumers (penalty/surcharge)</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Billing Dates Section (Hidden for Bill Printing and Generate Surcharge) -->
                                            <div class="col-lg-4 mb-3" id="billingDatesSection">
                                                <div class="card border h-100 shadow-sm">
                                                    <div class="card-body">
                                                        <h6 class="font-weight-bold text-dark mb-3 pb-2 border-bottom">
                                                            <i class="fas fa-calendar-alt text-primary mr-2"></i>Billing Dates
                                                        </h6>
                                                        
                                                        <!-- Bill Date -->
                                                        <div class="form-group mb-3">
                                                            <label for="billDate" class="font-weight-bold text-dark small mb-2">
                                                                Bill Date <span class="text-danger">*</span>
                                                            </label>
                                                            <input type="date" class="form-control" id="billDate"  style="font-size: 14px;">
                                                        </div>

                                                        <!-- Due Date -->
                                                        <div class="form-group mb-3">
                                                            <label for="dueDate" class="font-weight-bold text-dark small mb-2">
                                                                Due Date <span class="text-danger">*</span>
                                                            </label>
                                                            <input type="date" class="form-control" id="dueDate"  style="font-size: 14px;">
                                                        </div>

                                                        <!-- Disconnection Date -->
                                                        <div class="form-group mb-4">
                                                            <label for="disconnectionDate" class="font-weight-bold text-dark small mb-2">
                                                                Disconnection Date <span class="text-danger">*</span>
                                                            </label>
                                                            <input type="date" class="form-control" id="disconnectionDate" style="font-size: 14px;">
                                                        </div>


                                                    
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Actions Section -->
                                            <div class="col-lg-4 mb-3">
                                                <div class="card border h-100 shadow-sm">
                                                    <div class="card-body">
                                                        <h6 class="font-weight-bold text-dark mb-3 pb-2 border-bottom">
                                                            <i class="fas fa-bolt text-primary mr-2"></i>Actions
                                                        </h6>
                                                        
                                                        <div class="mb-4">
                                                            <button type="button" class="btn btn-success btn-block mb-2 font-weight-bold" style="padding: 12px;">
                                                                <i class="fas fa-play mr-2"></i>Execute Process
                                                            </button>
                                                            <button type="button" class="btn btn-primary btn-block mb-2" style="padding: 10px;">
                                                                <i class="fas fa-search mr-2"></i>Search Records
                                                            </button>
                                                            <button type="button" class="btn btn-warning btn-block mb-2" style="padding: 10px;">
                                                                <i class="fas fa-sync-alt mr-2"></i>Reset Form
                                                            </button>
                                                        </div>

                                                    
                                                    
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    

                        <!-- Data Table Section -->
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="card border-0 shadow">
                                    <div class="card-header bg-white border-bottom py-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary rounded p-2 mr-3">
                                                    <i class="fas fa-table text-white"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 font-weight-bold text-dark">Billing Records</h6>
                                                    <small class="text-muted">Detailed billing information and transaction records</small>
                                                </div>
                                            </div>
                                            <div>
                                                <span id="assignReaderGroup" class="mr-2 align-middle" style="display: none;">
                                                    <label class="mb-0 mr-1 small text-muted">Assign to reader:</label>
                                                    <select id="assignReaderSelect" class="form-control form-control-sm d-inline-block" style="width: auto; min-width: 160px;">
                                                        <option value="">-- Select reader --</option>
                                                    </select>
                                                </span>
                                                <span id="assignAfterSaveGroup" class="mr-2 align-middle" style="display: none;">
                                                    <label class="mb-0 mr-1 small text-muted">Assign saved schedules to reader:</label>
                                                    <select id="assignAfterSaveReaderSelect" class="form-control form-control-sm d-inline-block mr-1" style="width: auto; min-width: 160px;">
                                                        <option value="">-- Select reader --</option>
                                                    </select>
                                                    <button type="button" id="assignToReaderBtn" class="btn btn-sm btn-info">
                                                        <i class="fas fa-user-check mr-1"></i>Assign
                                                    </button>
                                                </span>
                                                <button id="saveSchedulesBtn" class="btn btn-sm btn-success mr-2" style="display: none;">
                                                    <i class="fas fa-save mr-1"></i>Save Schedules
                                                </button>
                                                <button id="applySurchargeBtn" class="btn btn-sm btn-warning mr-2" style="display: none;">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>Apply Surcharge
                                                </button>
                                                <button id="printBillingBtn" class="btn btn-sm btn-outline-primary mr-2">
                                                    <i class="fas fa-print mr-1"></i>Print
                                                </button>
                                                <button class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-download mr-1"></i>Export
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="px-3 py-2 border-bottom bg-light d-flex align-items-center">
                                            <label class="mb-0 mr-2 font-weight-bold text-dark small">Search:</label>
                                            <div class="input-group" style="max-width: 280px;">
                                                <input type="text" id="billingTableSearch" class="form-control form-control-sm" placeholder="Enter Account # or Account Name..." autocomplete="off">
                                                <div class="input-group-append">
                                                    <span class="input-group-text bg-white border-left-0">
                                                        <i class="fas fa-search"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                            <table class="table table-hover mb-0" style="font-size: 13px;">
                                                <thead style="position: sticky; top: 0; z-index: 10; background-color: #b1c5ff;">
                                                    <tr class="text-center border-bottom">
                                                        <th id="thIncludeSurcharge" class="py-3 px-3 font-weight-bold text-dark align-middle" style="min-width: 50px; display: none;">Include</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 70px;">SEDR</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 200px;">Account #</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark text-left" style="min-width: 180px;">Account Name</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark text-left" style="min-width: 200px;">Address</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 70px;">Zone</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 90px;">Category</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 120px;">Meter No.</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 110px;">Prev. Date</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 130px;">Prev. Read</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 100px;">Pres. Read</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 90px;">Volume</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 110px;">Current Bill</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 100px;">Water Maintenance Charge</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 100px;">Arrears</th>
                                                        <th id="thPenaltySurcharge" class="py-3 px-3 font-weight-bold text-dark" style="min-width: 100px; display: none;">Penalty (10%)</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 110px;">Total Amount</th>
                                                        <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 100px;">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                
                                                
                                                    
                                                    
                                                    <!-- Empty state when no data -->
                                                    <tr class="d-none" id="emptyState">
                                                        <td colspan="16" class="text-center text-muted py-5">
                                                            <div class="py-5">
                                                                <i class="fas fa-inbox fa-3x mb-3 text-muted opacity-50"></i>
                                                                <h6 class="text-muted">No Billing Records Found</h6>
                                                                <p class="mb-0 small">Please execute the process to load data.</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-white border-top py-3">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center">
                                                    <span class="text-muted small mr-2">Total Records:</span>
                                                    <span class="badge badge-primary px-3 py-2" id="totalRecordsBadge">0</span>
                                                    <span class="text-muted small ml-3">Zone: <strong class="text-dark" id="footerZoneValue">—</strong></span>
                                                    <span class="text-muted small ml-3">Period: <strong class="text-dark" id="footerPeriodValue">—</strong></span>
                                                </div>
                                            </div>
                                            <div class="col-md-6 text-right">
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Last updated: <strong class="text-dark" id="footerUpdatedValue">—</strong>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                            </div>
                            <!-- Tab: Meter Reading Schedule Viewing -->
                            <div class="tab-pane fade" id="pane-schedule-viewing" role="tabpanel" aria-labelledby="tab-schedule-viewing">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="card border-0 shadow">
                                            <div class="card-header bg-white border-bottom py-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-info rounded p-2 mr-3">
                                                        <i class="fas fa-calendar-check text-white"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0 font-weight-bold text-dark">Meter Reading Schedule Viewing</h6>
                                                        <small class="text-muted">All meter reading preparations (saved schedules) by Zone and billing dates</small>
                                                    </div>
                                                    <div class="ml-auto">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" id="loadScheduleBatchesBtn">
                                                            <i class="fas fa-sync-alt mr-1"></i>Load Schedules
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="row mt-3 align-items-end">
                                                    <div class="col-auto">
                                                        <label class="small font-weight-bold text-dark mb-1">Zone</label>
                                                        <select class="form-control form-control-sm" id="scheduleFilterZone" style="min-width: 120px;">
                                                            <option value="all">All Zones</option>
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
                                                    <div class="col-auto">
                                                        <label class="small font-weight-bold text-dark mb-1">Bill Month</label>
                                                        <select class="form-control form-control-sm" id="scheduleFilterBillMonth" style="min-width: 140px;">
                                                            <option value="all">All</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                                    <table class="table table-hover mb-0" style="font-size: 13px;" id="scheduleBatchesTable">
                                                        <thead style="position: sticky; top: 0; z-index: 10; background-color: #b1c5ff;">
                                                            <tr class="border-bottom">
                                                                <th class="py-3 px-3 font-weight-bold text-dark sortable schedule-sort-zone text-center" data-sort="zone" style="min-width: 90px; cursor: pointer;">Zone <i class="fas fa-sort ml-1 schedule-sort-icon" data-for="zone"></i></th>
                                                                <th class="py-3 px-3 font-weight-bold text-dark sortable schedule-sort-billmonth" data-sort="bill_month" style="min-width: 120px; cursor: pointer;">Bill Month <i class="fas fa-sort ml-1 schedule-sort-icon" data-for="bill_month"></i></th>
                                                                <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 120px;">Bill Date</th>
                                                                <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 120px;">Due Date</th>
                                                                <th class="py-3 px-3 font-weight-bold text-dark" style="min-width: 140px;">Disconnection Date</th>
                                                                <th class="py-3 px-3 font-weight-bold text-dark text-center" style="min-width: 100px;">Records</th>
                                                                <th class="py-3 px-3 font-weight-bold text-dark text-right" style="min-width: 120px;">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="scheduleBatchesTableBody">
                                                            <tr>
                                                                <td colspan="7" class="text-center text-muted py-5">
                                                                    <i class="fas fa-calendar-alt fa-3x mb-3 text-muted opacity-50"></i>
                                                                    <p class="mb-0">Click "Load Schedules" to display meter reading preparations.</p>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-white border-top py-3">
                                                <span class="text-muted small">Total batches: <strong class="text-dark" id="scheduleBatchesCount">0</strong></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Edit Schedule Batch Modal -->
                                <div class="modal fade" id="editScheduleBatchModal" tabindex="-1" role="dialog" aria-labelledby="editScheduleBatchModalLabel" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header py-2">
                                                <h6 class="modal-title font-weight-bold" id="editScheduleBatchModalLabel">Edit Schedule Batch</h6>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="small text-muted mb-3">Zone <strong id="editScheduleZoneDisplay"></strong> — <span id="editScheduleRecordsDisplay"></span> record(s). Update the dates below and save.</p>
                                                <input type="hidden" id="editScheduleZone" value="">
                                                <input type="hidden" id="editScheduleBillMonth" value="">
                                                <input type="hidden" id="editScheduleBillDate" value="">
                                                <input type="hidden" id="editScheduleDueDate" value="">
                                                <input type="hidden" id="editScheduleDisconnectionDate" value="">
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">Bill Month</label>
                                                    <input type="date" class="form-control form-control-sm" id="editScheduleNewBillMonth">
                                                </div>
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">Bill Date</label>
                                                    <input type="date" class="form-control form-control-sm" id="editScheduleNewBillDate">
                                                </div>
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">Due Date</label>
                                                    <input type="date" class="form-control form-control-sm" id="editScheduleNewDueDate">
                                                </div>
                                                <div class="form-group mb-0">
                                                    <label class="small font-weight-bold">Disconnection Date</label>
                                                    <input type="date" class="form-control form-control-sm" id="editScheduleNewDisconnectionDate">
                                                </div>
                                            </div>
                                            <div class="modal-footer py-2">
                                                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                                                <button type="button" class="btn btn-primary btn-sm" id="editScheduleBatchSaveBtn"><i class="fas fa-save mr-1"></i>Save</button>
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

        <!-- Hidden Print Template -->
        <div id="printTemplate" style="display: none;">
            <div class="print-content">
                <style>
                    @media print {
                        body * {
                            visibility: hidden;
                        }
                        #printTemplate, #printTemplate * {
                            visibility: visible;
                        }
                        #printTemplate {
                            position: absolute;
                            left: 0;
                            top: 0;
                            width: 100%;
                            display: block !important;
                        }
                        /* Show column headers only on the first page; do not repeat on continuation pages */
                        .print-table thead {
                            display: table-row-group;
                        }
                        @page {
                            size: letter portrait;
                            margin: 0.5in;
                        }
                    }
                    .print-content {
                        font-family: Arial, sans-serif;
                        padding: 20px;
                    }
                    .print-header {
                        text-align: center;
                        margin-bottom: 30px;
                    }
                    .print-header h1 {
                        font-size: 24px;
                        font-weight: bold;
                        margin: 0;
                        padding: 0;
                    }
                    .print-header h2 {
                        font-size: 18px;
                        font-weight: bold;
                        margin: 10px 0;
                    }
                    .print-header p {
                        font-size: 14px;
                        margin: 5px 0;
                    }
                    .print-zone {
                        font-size: 16px;
                        font-weight: bold;
                        margin: 10px 0;
                    }
                    .print-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 20px;
                        table-layout: fixed;
                        font-weight: bold;
                    }
                    .print-table th {
                        background-color: #f0f0f0;
                        border: 1px solid #000;
                        padding: 6px 4px;
                        text-align: left;
                        font-size: 13px;
                        font-weight: bold;
                    }
                    .print-table td {
                        border: 1px solid #000;
                        padding: 6px 4px;
                        font-size: 12px;
                        font-weight: bold;
                        word-wrap: break-word;
                        overflow-wrap: break-word;
                    }
                    .print-table td.text-right {
                        text-align: right;
                    }
                    .print-table td.text-center {
                        text-align: center;
                    }
                    .print-table thead th:nth-child(1) { width: 14%; }
                    .print-table thead th:nth-child(2) { width: 22%; }
                    .print-table thead th:nth-child(3) { width: 12%; }
                    .print-table thead th:nth-child(4) { width: 10%; }
                    .print-table thead th:nth-child(5) { width: 12%; }
                    .print-table thead th:nth-child(6) { width: 12%; }
                    .print-table thead th:nth-child(7) { width: 18%; }
                </style>

                <div class="print-header">
                    <h1>HAGONOY WATER DISTRICT</h1>
                    <p>Guihing, Hagonoy</p>
                    <h2>DAILY BILLING REPORT</h2>
                    <p id="printBillMonth"></p>
                    <p class="print-zone" id="printZone"></p>
                </div>

                <table class="print-table">
                    <thead>
                        <tr>
                            <th>CUSTOMER ACCOUNT NUMBER</th>
                            <th>CONCESSIONAIRE</th>
                            <th>WATER BILL NUMBER</th>
                            <th>CUBIC METER CONS.</th>
                            <th>METERED SALES</th>
                            <th>PENALTY CHARGES</th>
                            <th>WATER MAINTENANCE CHARGE</th>
                        </tr>
                    </thead>
                    <tbody id="printTableBody">
                        <!-- Data will be inserted here -->
                    </tbody>
                </table>

                <!-- Breakdown of Metered Sales -->
                <div style="margin-top: 40px; page-break-before: auto;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <p style="margin: 0 0 5px 0; font-size: 13px; font-weight: bold;"><strong>BILL No. SEQUENCE COVERED:</strong></p>
                            <h3 style="margin: 0 0 15px 0; font-size: 15px; font-weight: bold;">BREAKDOWN OF METERED SALES</h3>
                            
                            <table style="width: 500px; border-collapse: collapse; font-weight: bold;">
                                <thead>
                                    <tr>
                                        <th style="border: 1px solid #000; padding: 6px 4px; text-align: left; font-size: 12px; font-weight: bold; background-color: #f0f0f0;">CATEGORY</th>
                                        <th style="border: 1px solid #000; padding: 6px 4px; text-align: center; font-size: 12px; font-weight: bold; background-color: #f0f0f0;">NUMBER OF CONSUMERS</th>
                                        <th style="border: 1px solid #000; padding: 6px 4px; text-align: center; font-size: 12px; font-weight: bold; background-color: #f0f0f0;">CUBIC METER CONSUMED</th>
                                        <th style="border: 1px solid #000; padding: 6px 4px; text-align: center; font-size: 12px; font-weight: bold; background-color: #f0f0f0;">AMOUNT</th>
                                    </tr>
                                </thead>
                                <tbody id="breakdownTableBody">
                                    <!-- Breakdown data will be inserted here -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Signature Section - Classic Black & White Layout -->
                        <div style="margin-left: 40px; min-width: 340px;">
                            <div style="margin-bottom: 38px;">
                                <p style="margin: 0 0 20px 0; font-size:13px; font-weight:bold; color:#222;">PREPARED BY:</p>
                                <div style="border-bottom:1.5px solid #222; width:100%; margin-bottom: 10px;"></div>
                                <p style="margin: 0; font-size: 15px; font-weight: bold; color: #222; text-align: left;">MARLO B. PORRAS</p>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2px;">
                                    <span style="margin: 0; font-size: 13px; color: #353535;">Billing and Collection Clerk</span>
                                    <span style="margin: 0; font-size: 13px; color: #222;">Date: <span id="currentPrintDate"></span></span>
                                    <script>
                                        document.addEventListener('DOMContentLoaded', function() {
                                            const printDateSpan = document.getElementById('currentPrintDate');
                                            if (printDateSpan) {
                                                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                                                printDateSpan.textContent = new Date().toLocaleDateString('en-US', options);
                                            }
                                        });
                                    </script>
                                </div>
                            </div>
                            <div style="margin-top: 48px;">
                                <p style="margin: 0 0 20px 0; font-size:13px; font-weight:bold; color:#222;">Verified by:</p>
                                    <div style="display: flex; flex-direction: column; align-items: center;">
                                        <p style="margin:0 0 10px 0; font-size: 15px; font-weight: bold; color: #222; text-align: center;">MERAFLOR C. DOLORITOS</p>
                                        <p style="margin: 0; font-size: 13px; color: #353535; text-align: center;">Accounting Processor</p>
                                    </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Display current date
            document.addEventListener('DOMContentLoaded', function() {
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                const currentDate = new Date().toLocaleDateString('en-US', options);
                const currentDateElement = document.getElementById('currentDate');
                if (currentDateElement) {
                    currentDateElement.textContent = currentDate;
                }
            });

            // Handle Process Type Selection - Show/Hide Fields
            const processSelect = document.getElementById('processSelect');
            if (processSelect) {
                processSelect.addEventListener('change', function() {
                    const selectedProcess = this.value;
                    const billMonthGroup = document.getElementById('billMonthGroup');
                    const readingDateGroup = document.getElementById('readingDateGroup');
                    const surchargeDateGroup = document.getElementById('surchargeDateGroup');
                    const billingDatesSection = document.getElementById('billingDatesSection');
                    const singleConsumerAccountGroup = document.getElementById('singleConsumerAccountGroup');
                    const multipleConsumersAccountGroup = document.getElementById('multipleConsumersAccountGroup');
                    const zoneGroup = document.getElementById('zoneSelectGroup');

                    if (selectedProcess === 'Bill Printing') {
                        if (billMonthGroup) billMonthGroup.style.display = 'none';
                        if (readingDateGroup) readingDateGroup.style.display = 'block';
                        if (surchargeDateGroup) surchargeDateGroup.style.display = 'none';
                        if (billingDatesSection) billingDatesSection.style.display = 'none';
                        if (singleConsumerAccountGroup) singleConsumerAccountGroup.style.display = 'none';
                        if (multipleConsumersAccountGroup) multipleConsumersAccountGroup.style.display = 'none';
                        if (zoneGroup) zoneGroup.style.display = '';
                    } else if (selectedProcess === 'Generate Surcharge') {
                        if (billMonthGroup) billMonthGroup.style.display = 'none';
                        if (readingDateGroup) readingDateGroup.style.display = 'none';
                        if (surchargeDateGroup) surchargeDateGroup.style.display = 'block';
                        if (billingDatesSection) billingDatesSection.style.display = 'none';
                        if (singleConsumerAccountGroup) singleConsumerAccountGroup.style.display = 'none';
                        if (multipleConsumersAccountGroup) multipleConsumersAccountGroup.style.display = 'none';
                        if (zoneGroup) zoneGroup.style.display = '';
                    } else if (selectedProcess === 'Generate Penalty (Single Consumer)') {
                        if (billMonthGroup) billMonthGroup.style.display = 'none';
                        if (readingDateGroup) readingDateGroup.style.display = 'none';
                        if (surchargeDateGroup) surchargeDateGroup.style.display = 'block';
                        if (billingDatesSection) billingDatesSection.style.display = 'none';
                        if (singleConsumerAccountGroup) singleConsumerAccountGroup.style.display = 'block';
                        if (multipleConsumersAccountGroup) multipleConsumersAccountGroup.style.display = 'none';
                        if (zoneGroup) zoneGroup.style.display = 'none';
                    } else if (selectedProcess === 'Meter Reading Preparation (Single Consumer)') {
                        if (billMonthGroup) billMonthGroup.style.display = 'block';
                        if (readingDateGroup) readingDateGroup.style.display = 'none';
                        if (surchargeDateGroup) surchargeDateGroup.style.display = 'none';
                        if (billingDatesSection) billingDatesSection.style.display = 'block';
                        if (singleConsumerAccountGroup) singleConsumerAccountGroup.style.display = 'block';
                        if (multipleConsumersAccountGroup) multipleConsumersAccountGroup.style.display = 'none';
                        if (zoneGroup) zoneGroup.style.display = '';
                    } else if (selectedProcess === 'Meter Reading Preparation (Multiple Consumers)') {
                        if (billMonthGroup) billMonthGroup.style.display = 'block';
                        if (readingDateGroup) readingDateGroup.style.display = 'none';
                        if (surchargeDateGroup) surchargeDateGroup.style.display = 'none';
                        if (billingDatesSection) billingDatesSection.style.display = 'block';
                        if (singleConsumerAccountGroup) singleConsumerAccountGroup.style.display = 'none';
                        if (multipleConsumersAccountGroup) multipleConsumersAccountGroup.style.display = 'block';
                        if (zoneGroup) zoneGroup.style.display = '';
                    } else {
                        if (billMonthGroup) billMonthGroup.style.display = 'block';
                        if (readingDateGroup) readingDateGroup.style.display = 'none';
                        if (surchargeDateGroup) surchargeDateGroup.style.display = 'none';
                        if (billingDatesSection) billingDatesSection.style.display = 'block';
                        if (singleConsumerAccountGroup) singleConsumerAccountGroup.style.display = 'none';
                        if (multipleConsumersAccountGroup) multipleConsumersAccountGroup.style.display = 'none';
                        if (zoneGroup) zoneGroup.style.display = '';
                    }
                });
            }

            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            // Store prepared schedules temporarily
            let preparedSchedules = [];
            let canSaveSchedules = false;
            let isSingleConsumerPreparation = false;
            let isMultipleConsumerPreparation = false;
            // Schedule IDs just saved (Single/Multiple) — used by "Assign to reader" after save
            let lastSavedScheduleIds = [];

            // Store current billing data for printing
            let currentBillingData = [];
            let currentBillingZone = '';
            let currentBillingMonth = '';

            // Store surcharge candidates for Apply Surcharge (with Include checkboxes)
            let currentSurchargeData = [];
            let currentDataType = ''; // 'surcharge' | 'downloaded' | 'prepared' | ''

            /** Ascending sort by the segment after the last "-" in Account # (e.g. 081-32-625 → 625); used for Meter Reading Preparation + search */
            function sortRowsByAccountNumber(rows) {
                if (!Array.isArray(rows)) return [];
                const tailAfterLastHyphen = (accountNumber) => {
                    const s = (accountNumber || '').toString().trim();
                    const i = s.lastIndexOf('-');
                    return i === -1 ? s : s.slice(i + 1).trim();
                };
                const tailNumeric = (accountNumber) => {
                    const tail = tailAfterLastHyphen(accountNumber);
                    const n = parseInt(tail, 10);
                    return Number.isNaN(n) ? null : n;
                };
                return [...rows].sort((a, b) => {
                    const na = tailNumeric(a.account_number);
                    const nb = tailNumeric(b.account_number);
                    if (na !== null && nb !== null && na !== nb) return na - nb;
                    if (na !== null && nb === null) return -1;
                    if (na === null && nb !== null) return 1;
                    const ta = tailAfterLastHyphen(a.account_number);
                    const tb = tailAfterLastHyphen(b.account_number);
                    let c = ta.localeCompare(tb, undefined, { numeric: true, sensitivity: 'base' });
                    if (c !== 0) return c;
                    return (a.account_number || '').toString().localeCompare((b.account_number || '').toString(), undefined, { numeric: true, sensitivity: 'base' });
                });
            }

            // Quick lookup elements
            const quickLookupEndpoint = @json(route('billing-processes.account-lookup'));
            const quickLookupForm = document.getElementById('quickLookupForm');
            const quickLookupAccountField = document.getElementById('quickLookupAccount');
            const quickLookupStatus = document.getElementById('quickLookupStatus');
            const quickAccountName = document.getElementById('quickAccountName');
            const quickAccountZone = document.getElementById('quickAccountZone');
            const quickLatestBillAmount = document.getElementById('quickLatestBillAmount');
            const quickLatestBillMonth = document.getElementById('quickLatestBillMonth');
            const quickLatestBillStatus = document.getElementById('quickLatestBillStatus');
            let quickLookupController = null;
            let lastQuickLookupAccount = '';

            if (quickLookupForm && quickLookupAccountField) {
                const debouncedQuickLookup = debounce(() => handleQuickLookup(), 500);

                quickLookupForm.addEventListener('submit', function(event) {
                    event.preventDefault();
                    handleQuickLookup(true);
                });

                quickLookupAccountField.addEventListener('input', function() {
                    const rawValue = quickLookupAccountField.value || '';
                    const uppercased = rawValue.toUpperCase();
                    if (rawValue !== uppercased) {
                        quickLookupAccountField.value = uppercased;
                    }

                    if (!uppercased.trim()) {
                        resetQuickLookupDisplay();
                        return;
                    }

                    debouncedQuickLookup();
                });

                quickLookupAccountField.addEventListener('blur', function() {
                    if (quickLookupAccountField.value.trim()) {
                        handleQuickLookup();
                    }
                });
            }

            // Execute Process Button
            const executeBtn = document.querySelector('.btn-success');
            if (executeBtn) {
                executeBtn.addEventListener('click', function() {
                    const processType = document.getElementById('processSelect').value;
                    
                    if (processType === 'Meter Reading Preparation') {
                        executeMeterReadingPreparation();
                    } else if (processType === 'Meter Reading Preparation (Single Consumer)') {
                        executeMeterReadingPreparationSingleConsumer();
                    } else if (processType === 'Meter Reading Preparation (Multiple Consumers)') {
                        executeMeterReadingPreparationMultipleConsumers();
                    } else if (processType === 'Bill Printing') {
                        executeBillPrinting();
                    } else if (processType === 'Generate Surcharge') {
                        executeGenerateSurcharge();
                    } else if (processType === 'Generate Penalty (Single Consumer)') {
                        executeGenerateSingleConsumerPenalty();
                    } else {
                        showAlert('info', 'Process "' + processType + '" is not yet implemented');
                    }
                });
            }

            // Meter Reading Preparation Function
            function executeMeterReadingPreparation() {
                const zone = document.getElementById('zoneSelect').value;
                const billMonth = document.getElementById('billMonth').value;
                const billDate = document.getElementById('billDate').value;
                const dueDate = document.getElementById('dueDate').value;
                const disconnectionDate = document.getElementById('disconnectionDate').value;

                // Validate inputs
                if (!zone) {
                    showAlert('error', 'Please select a zone');
                    return;
                }
                if (!billMonth || !billDate || !dueDate || !disconnectionDate) {
                    showAlert('error', 'Please fill in all date fields');
                    return;
                }

                // Show loading state
                const executeBtn = document.querySelector('.btn-success');
                const originalText = executeBtn.innerHTML;
                executeBtn.disabled = true;
                executeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';

                // Make API call
                fetch('{{ route("billing-processes.prepare-meter-reading") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        zone: zone,
                        bill_month: billMonth,
                        bill_date: billDate,
                        due_date: dueDate,
                        disconnection_date: disconnectionDate
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const rows = sortRowsByAccountNumber(data.data);
                        // Store prepared schedules for later saving (same order as table rows / data-index)
                        preparedSchedules = rows;
                        canSaveSchedules = data.can_save;
                        
                        // Store data for printing
                        currentBillingData = rows;
                        currentBillingZone = data.summary.zone;
                        currentBillingMonth = data.summary.bill_month;
                        
                        showAlert('success', data.message);
                        populateTable(rows);
                        updateFooter(data.summary);
                        
                        // Show/hide Save button based on whether schedules already exist
                        const saveBtn = document.getElementById('saveSchedulesBtn');
                        if (canSaveSchedules && preparedSchedules.length > 0) {
                            saveBtn.style.display = 'inline-block';
                        } else {
                            saveBtn.style.display = 'none';
                            if (!canSaveSchedules && data.summary.existing_schedules > 0) {
                                showAlert('warning', 'Schedules already exist for this zone and period. Data shown from preparation only.');
                            }
                        }
                        const assignReaderGroup = document.getElementById('assignReaderGroup');
                        if (assignReaderGroup) assignReaderGroup.style.display = 'none';
                    } else {
                        showAlert('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', 'An error occurred while processing the request');
                })
                .finally(() => {
                    executeBtn.disabled = false;
                    executeBtn.innerHTML = originalText;
                });
            }

            // Meter Reading Preparation for a single consumer (same logic, scoped to one account)
            function executeMeterReadingPreparationSingleConsumer() {
                const zone = document.getElementById('zoneSelect').value;
                const accountNo = (document.getElementById('singleConsumerAccount').value || '').trim().toUpperCase();
                const billMonth = document.getElementById('billMonth').value;
                const billDate = document.getElementById('billDate').value;
                const dueDate = document.getElementById('dueDate').value;
                const disconnectionDate = document.getElementById('disconnectionDate').value;

                if (!zone) {
                    showAlert('error', 'Please select a zone');
                    return;
                }
                if (!accountNo) {
                    showAlert('error', 'Please enter the consumer account number');
                    return;
                }
                if (!billMonth || !billDate || !dueDate || !disconnectionDate) {
                    showAlert('error', 'Please fill in all date fields');
                    return;
                }

                const executeBtn = document.querySelector('.btn-success');
                const originalText = executeBtn.innerHTML;
                executeBtn.disabled = true;
                executeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';

                fetch('{{ route("billing-processes.prepare-meter-reading") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        zone: zone,
                        account_no: accountNo,
                        bill_month: billMonth,
                        bill_date: billDate,
                        due_date: dueDate,
                        disconnection_date: disconnectionDate
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const rows = sortRowsByAccountNumber(data.data);
                        preparedSchedules = rows;
                        canSaveSchedules = data.can_save;
                        currentBillingData = rows;
                        currentBillingZone = data.summary.zone;
                        currentBillingMonth = data.summary.bill_month;
                        isSingleConsumerPreparation = true;
                        showAlert('success', data.message);
                        populateTable(rows);
                        updateFooter(data.summary);
                        const saveBtn = document.getElementById('saveSchedulesBtn');
                        if (canSaveSchedules && preparedSchedules.length > 0) {
                            saveBtn.style.display = 'inline-block';
                            const assignReaderGroup = document.getElementById('assignReaderGroup');
                            if (assignReaderGroup) {
                                assignReaderGroup.style.display = 'inline-block';
                                loadAvailableReaders();
                            }
                        } else {
                            saveBtn.style.display = 'none';
                            document.getElementById('assignReaderGroup').style.display = 'none';
                            if (!canSaveSchedules && data.summary.existing_schedules > 0) {
                                showAlert('warning', 'This account already has a schedule for this period. Data shown from preparation only.');
                            }
                        }
                    } else {
                        showAlert('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', 'An error occurred while processing the request');
                })
                .finally(() => {
                    executeBtn.disabled = false;
                    executeBtn.innerHTML = originalText;
                });
            }

            // Meter Reading Preparation for multiple consumers (same logic per account; save and assign all to one reader)
            function executeMeterReadingPreparationMultipleConsumers() {
                const raw = (document.getElementById('multipleConsumersAccounts').value || '').trim();
                const accountNumbers = raw
                    .split(/[\n,]+/)
                    .map(function(s) { return s.trim().toUpperCase(); })
                    .filter(function(s) { return s.length > 0; });
                const billMonth = document.getElementById('billMonth').value;
                const billDate = document.getElementById('billDate').value;
                const dueDate = document.getElementById('dueDate').value;
                const disconnectionDate = document.getElementById('disconnectionDate').value;

                if (accountNumbers.length === 0) {
                    showAlert('error', 'Please enter at least one account number (one per line or comma-separated).');
                    return;
                }
                if (!billMonth || !billDate || !dueDate || !disconnectionDate) {
                    showAlert('error', 'Please fill in all date fields');
                    return;
                }

                const executeBtn = document.querySelector('.btn-success');
                const originalText = executeBtn.innerHTML;
                executeBtn.disabled = true;
                executeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';

                fetch('{{ route("billing-processes.prepare-meter-reading") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        account_numbers: accountNumbers,
                        bill_month: billMonth,
                        bill_date: billDate,
                        due_date: dueDate,
                        disconnection_date: disconnectionDate
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const rows = sortRowsByAccountNumber(data.data);
                        preparedSchedules = rows;
                        canSaveSchedules = data.can_save;
                        currentBillingData = rows;
                        currentBillingZone = data.summary.zone;
                        currentBillingMonth = data.summary.bill_month;
                        isMultipleConsumerPreparation = true;
                        showAlert('success', data.message);
                        populateTable(rows);
                        updateFooter(data.summary);
                        const saveBtn = document.getElementById('saveSchedulesBtn');
                        if (canSaveSchedules && preparedSchedules.length > 0) {
                            saveBtn.style.display = 'inline-block';
                            const assignReaderGroup = document.getElementById('assignReaderGroup');
                            if (assignReaderGroup) {
                                assignReaderGroup.style.display = 'inline-block';
                                loadAvailableReaders();
                            }
                        } else {
                            saveBtn.style.display = 'none';
                            const ag = document.getElementById('assignReaderGroup');
                            if (ag) ag.style.display = 'none';
                            if (!canSaveSchedules && data.summary.existing_schedules > 0) {
                                showAlert('warning', 'All these accounts already have a schedule for this period. Add different accounts or choose a different bill month to save.');
                            }
                        }
                    } else {
                        showAlert('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', 'An error occurred while processing the request');
                })
                .finally(() => {
                    executeBtn.disabled = false;
                    executeBtn.innerHTML = originalText;
                });
            }

            // Load available readers into dropdown (for single-consumer assign)
            function loadAvailableReaders() {
                const select = document.getElementById('assignReaderSelect');
                if (!select) return;
                if (select.options.length > 1) return; // already loaded
                loadReadersIntoSelect('assignReaderSelect');
            }

            // Load readers into a specific select by id (e.g. assignAfterSaveReaderSelect after save)
            function loadReadersIntoSelect(selectId) {
                const select = document.getElementById(selectId);
                if (!select) return;
                fetch('{{ route("meter-reading.available-readers") }}')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data && data.data.length) {
                            select.innerHTML = '<option value="">-- Select reader --</option>';
                            data.data.forEach(function(r) {
                                const opt = document.createElement('option');
                                opt.value = r.id;
                                opt.textContent = r.name;
                                select.appendChild(opt);
                            });
                        }
                    })
                    .catch(function() {});
            }

            // Bill Printing Function - Fetch Downloaded Readings
            function executeBillPrinting() {
                const zone = document.getElementById('zoneSelect').value;
                const readingDate = document.getElementById('readingDateInput').value;

                // Validate inputs - only zone and reading_date required
                if (!zone) {
                    showAlert('error', 'Please select a zone');
                    return;
                }
                if (!readingDate) {
                    showAlert('error', 'Please select a reading date');
                    return;
                }

                // Show loading state
                const executeBtn = document.querySelector('.btn-success');
                const originalText = executeBtn.innerHTML;
                executeBtn.disabled = true;
                executeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading Readings...';

                // Make API call to fetch downloaded readings
                // Only send zone and reading_date
                fetch('{{ route("billing-processes.get-downloaded-readings") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        zone: zone,
                        reading_date: readingDate
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message || 'Downloaded readings loaded successfully');
                        populateDownloadedReadingsTable(data.data);
                        updateFooter(data.summary);
                        
                        // Store data for printing
                        currentBillingData = data.data;
                        currentBillingZone = data.summary.zone || zone;
                        // For Bill Printing, use reading_date or bill_month
                        currentBillingMonth = data.summary.bill_month || data.summary.reading_date || readingDate;
                        
                        // Hide save button for this process
                        document.getElementById('saveSchedulesBtn').style.display = 'none';
                    } else {
                        showAlert('error', data.message);
                        clearTable();
                        currentBillingData = [];
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', 'An error occurred while fetching downloaded readings');
                    clearTable();
                })
                .finally(() => {
                    executeBtn.disabled = false;
                    executeBtn.innerHTML = originalText;
                });
            }

            // Generate Surcharge - Load past-due consumers (no system penalty shown)
            function executeGenerateSurcharge() {
                const zone = document.getElementById('zoneSelect').value;
                const billDate = document.getElementById('surchargeBillDate').value;

                if (!zone) {
                    showAlert('error', 'Please select a zone');
                    return;
                }
                if (!billDate) {
                    showAlert('error', 'Please select a Bill Date');
                    return;
                }

                const executeBtn = document.querySelector('.btn-success');
                const originalText = executeBtn.innerHTML;
                executeBtn.disabled = true;
                executeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';

                fetch('{{ route("billing-processes.surcharge-candidates") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        zone: zone,
                        bill_date: billDate
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentDataType = 'surcharge';
                        currentSurchargeData = data.data;
                        populateSurchargeTable(data.data);
                        updateFooter(data.summary);
                        document.getElementById('saveSchedulesBtn').style.display = 'none';
                        document.getElementById('applySurchargeBtn').style.display = 'inline-block';
                        showAlert('success', data.message);
                    } else {
                        showAlert('error', data.message);
                        clearTable();
                        currentSurchargeData = [];
                        currentDataType = '';
                        document.getElementById('applySurchargeBtn').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', 'An error occurred while loading surcharge candidates');
                    clearTable();
                    currentDataType = '';
                    document.getElementById('applySurchargeBtn').style.display = 'none';
                })
                .finally(() => {
                    executeBtn.disabled = false;
                    executeBtn.innerHTML = originalText;
                });
            }

            // Generate Penalty (Single Consumer) - Load one past-due consumer by account and bill date
            function executeGenerateSingleConsumerPenalty() {
                const accountNumber = (document.getElementById('singleConsumerAccount').value || '').trim();
                const billDate = document.getElementById('surchargeBillDate').value;

                if (!accountNumber) {
                    showAlert('error', 'Please enter an Account Number');
                    return;
                }
                if (!billDate) {
                    showAlert('error', 'Please select a Bill Date');
                    return;
                }

                const executeBtn = document.querySelector('.btn-success');
                const originalText = executeBtn.innerHTML;
                executeBtn.disabled = true;
                executeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';

                fetch('{{ route("billing-processes.single-penalty-candidate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        account_number: accountNumber,
                        bill_date: billDate
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentDataType = 'surcharge';
                        currentSurchargeData = data.data || [];
                        populateSurchargeTable(currentSurchargeData);
                        updateFooter(data.summary || {});
                        document.getElementById('saveSchedulesBtn').style.display = 'none';
                        document.getElementById('applySurchargeBtn').style.display = 'inline-block';
                        showAlert('success', data.message || 'Penalty candidate loaded successfully');
                    } else {
                        showAlert('error', data.message || 'Failed to load penalty candidate');
                        clearTable();
                        currentSurchargeData = [];
                        currentDataType = '';
                        document.getElementById('applySurchargeBtn').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', 'An error occurred while loading single consumer penalty candidate');
                    clearTable();
                    currentSurchargeData = [];
                    currentDataType = '';
                    document.getElementById('applySurchargeBtn').style.display = 'none';
                })
                .finally(() => {
                    executeBtn.disabled = false;
                    executeBtn.innerHTML = originalText;
                });
            }

            // Populate table with surcharge candidates (with Include checkbox per row)
            function populateSurchargeTable(data) {
                const tbody = document.querySelector('table tbody');
                const thInclude = document.getElementById('thIncludeSurcharge');
                const thPenalty = document.getElementById('thPenaltySurcharge');
                tbody.innerHTML = '';

                if (thInclude) {
                    thInclude.style.display = '';
                }
                if (thPenalty) {
                    thPenalty.style.display = '';
                }

                if (!data || data.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="18" class="text-center text-muted py-5">
                                <div class="py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 text-muted opacity-50"></i>
                                    <h6 class="text-muted">No Past-Due Consumers Found</h6>
                                    <p class="mb-0 small">No consumers past due without payment for the selected zone and bill date.</p>
                                </div>
                            </td>
                        </tr>
                    `;
                    const totalRecordsBadge = document.getElementById('totalRecordsBadge');
                    if (totalRecordsBadge) totalRecordsBadge.textContent = '0';
                    return;
                }

                data.forEach((record, index) => {
                    const row = document.createElement('tr');
                    row.className = (index % 2 === 1 ? 'bg-light' : '') + ' border-bottom';
                    row.dataset.index = index;
                    row.dataset.accountNumber = (record.account_number || '').toString().trim();
                    row.dataset.accountName = (record.account_name || '').toString().trim();
                    const checked = record.include !== false;
                    row.innerHTML = `
                        <td class="text-center py-3 px-3 align-middle">
                            <input type="checkbox" class="surcharge-include-checkbox" ${checked ? 'checked' : ''} data-index="${index}" title="Uncheck to exclude from surcharge">
                        </td>
                        <td class="text-center py-3 px-3 text-muted">${record.sedr || '-'}</td>
                        <td class="text-center py-3 px-3"><span class="font-weight-bold text-dark">${record.account_number || '-'}</span></td>
                        <td class="py-3 px-3">${record.account_name || '-'}</td>
                        <td class="py-3 px-3 text-muted">${record.address || '-'}</td>
                        <td class="text-center py-3 px-3"><span class="badge badge-light border">${record.zone || '-'}</span></td>
                        <td class="text-center py-3 px-3"><span class="badge badge-${getCategoryBadgeClass(record.category)}">${record.category || '-'}</span></td>
                        <td class="text-center py-3 px-3 text-muted">${record.meter_number || '-'}</td>
                        <td class="text-center py-3 px-3 text-muted">${record.prev_date || '-'}</td>
                        <td class="text-right py-3 px-3">${record.prev_read || '0'}</td>
                        <td class="text-right py-3 px-3 font-weight-bold">${record.pres_read || '0'}</td>
                        <td class="text-right py-3 px-3"><span class="badge badge-light">${record.volume || '0'}</span></td>
                        <td class="text-right py-3 px-3">₱ ${formatNumber(record.current_bill || 0)}</td>
                        <td class="text-right py-3 px-3 text-warning">₱ ${formatNumber((record.current_bill || 0) > 0 ? 20.00 : 0.00)}</td>
                        <td class="text-right py-3 px-3 ${(record.arrears || 0) > 0 ? 'text-warning' : 'text-muted'}">₱ ${formatNumber(record.arrears || 0)}</td>
                        <td class="text-right py-3 px-3 text-danger">
                            <span class="font-weight-bold">₱ ${formatNumber(record.calculated_penalty || 0)}</span>
                            <br><small class="text-muted" style="font-size: 10px;">Base: ₱${formatNumber(record.penalty_base || 0)}</small>
                        </td>
                        <td class="text-right py-3 px-3"><span class="font-weight-bold ${(record.total || 0) > 0 ? 'text-success' : 'text-muted'}">₱ ${formatNumber(record.total || 0)}</span></td>
                        <td class="text-center py-3 px-3"><span class="badge badge-danger px-3 py-1">${record.status || 'Past Due'}</span></td>
                    `;
                    tbody.appendChild(row);
                });

                const totalRecordsBadge = document.getElementById('totalRecordsBadge');
                if (totalRecordsBadge) totalRecordsBadge.textContent = data.length;
                const searchInputEl = document.getElementById('billingTableSearch');
                if (searchInputEl) searchInputEl.value = '';
            }

            // Populate Downloaded Readings Table
            function populateDownloadedReadingsTable(data) {
                const tbody = document.querySelector('table tbody');
                const thInclude = document.getElementById('thIncludeSurcharge');
                const thPenalty = document.getElementById('thPenaltySurcharge');
                tbody.innerHTML = '';
                currentDataType = 'downloaded';
                currentSurchargeData = [];
                if (thInclude) thInclude.style.display = 'none';
                if (thPenalty) thPenalty.style.display = 'none';
                document.getElementById('applySurchargeBtn').style.display = 'none';

                if (!data || data.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="16" class="text-center text-muted py-5">
                                <div class="py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 text-muted opacity-50"></i>
                                    <h6 class="text-muted">No Downloaded Readings Found</h6>
                                    <p class="mb-0 small">No downloaded readings available for the selected zone and period.</p>
                                </div>
                            </td>
                        </tr>
                    `;
                    return;
                }

                // Sort data by last digit of account_number (e.g., 020 from 011-12-020) in ascending order
                data.sort((a, b) => {
                    const accA = (a.account_number || '').toString().trim();
                    const accB = (b.account_number || '').toString().trim();
                    const lastDigitA = parseInt(accA.split('-').pop() || '0', 10);
                    const lastDigitB = parseInt(accB.split('-').pop() || '0', 10);
                    return lastDigitA - lastDigitB;
                });

                data.forEach((record, index) => {
                    const accNum = (record.account_number || '').toString().trim();
                    const accName = (record.account_name || '').toString().trim();
                    // Bill Printing: no arrears; Total Amount = Current Bill + Water Maintenance Charge only
                    const currentBill = parseFloat(record.current_bill) || 0;
                    const waterMaintenance = currentBill > 0 ? 20.00 : 0.00;
                    const totalAmount = currentBill + waterMaintenance;
                    const row = `
                        <tr class="${index % 2 === 1 ? 'bg-light' : ''} border-bottom" data-account-number="${accNum.replace(/"/g, '&quot;')}" data-account-name="${accName.replace(/"/g, '&quot;')}">
                            <td class="text-center py-3 px-3 text-muted">${record.sedr || '-'}</td>
                            <td class="text-center py-3 px-3">
                                <span class="font-weight-bold text-dark">${record.account_number || '-'}</span>
                            </td>
                            <td class="py-3 px-3">${record.account_name || '-'}</td>
                            <td class="py-3 px-3 text-muted">${record.address || '-'}</td>
                            <td class="text-center py-3 px-3">
                                <span class="badge badge-light border">${record.zone || '-'}</span>
                            </td>
                            <td class="text-center py-3 px-3">
                                <span class="badge badge-${getCategoryBadgeClass(record.category)}">${record.category || '-'}</span>
                            </td>
                            <td class="text-center py-3 px-3 text-muted">${record.meter_number || '-'}</td>
                            <td class="text-center py-3 px-3 text-muted">${record.prev_date || '-'}</td>
                            <td class="text-right py-3 px-3">${record.prev_read || '0'}</td>
                            <td class="text-right py-3 px-3 font-weight-bold">${record.pres_read || '0'}</td>
                            <td class="text-right py-3 px-3">
                                <span class="badge badge-light">${record.volume || '0'}</span>
                            </td>
                            <td class="text-right py-3 px-3">₱ ${formatNumber(currentBill)}</td>
                            <td class="text-right py-3 px-3 text-warning">₱ ${formatNumber(waterMaintenance)}</td>
                            <td class="text-right py-3 px-3 text-muted">—</td>
                            <td class="text-right py-3 px-3">
                                <span class="font-weight-bold ${totalAmount > 0 ? 'text-success' : 'text-muted'}">₱ ${formatNumber(totalAmount)}</span>
                            </td>
                            <td class="text-center py-3 px-3">
                                <span class="badge badge-${getStatusBadgeClass(record.status || 'Pending')} px-3 py-1">${record.status || 'Pending'}</span>
                            </td>
                        </tr>
                    `;
                    tbody.insertAdjacentHTML('beforeend', row);
                });

                // Update record count and clear search filter
                const totalRecordsBadge = document.getElementById('totalRecordsBadge');
                if (totalRecordsBadge) {
                    totalRecordsBadge.textContent = data.length;
                }
                const searchInputEl = document.getElementById('billingTableSearch');
                if (searchInputEl) searchInputEl.value = '';
            }

            // Search Records Function
            const searchBtn = document.querySelector('.btn-primary');
            if (searchBtn) {
                searchBtn.addEventListener('click', function() {
                    const zone = document.getElementById('zoneSelect').value;
                    const searchValue = prompt('Enter Account Number to search:');

                    if (!searchValue) return;

                    // Show loading state
                    const originalText = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Searching...';

                    fetch('{{ route("billing-processes.search") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            search_type: 'account',
                            search_value: searchValue,
                            zone: zone
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const rows = sortRowsByAccountNumber(data.data);
                            populateTable(rows);
                            
                            // Store data for printing
                            currentBillingData = rows;
                            currentBillingZone = zone || 'All Zones';
                            currentBillingMonth = 'Search Results';
                            
                            showAlert('success', 'Search completed');
                        } else {
                            showAlert('error', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('error', 'An error occurred while searching');
                    })
                    .finally(() => {
                        this.disabled = false;
                        this.innerHTML = originalText;
                    });
                });
            }

            // Save Schedules Button
            const saveSchedulesBtn = document.getElementById('saveSchedulesBtn');
            if (saveSchedulesBtn) {
                saveSchedulesBtn.addEventListener('click', function() {
                    if (!preparedSchedules || preparedSchedules.length === 0) {
                        showAlert('error', 'No schedules to save. Please execute the process first.');
                        return;
                    }

                    if (!confirm('Are you sure you want to save ' + preparedSchedules.length + ' meter reading schedule(s) to the database?')) {
                        return;
                    }

                    // Sync edited Prev. Read values from table back into preparedSchedules before saving
                    const tbody = document.querySelector('.table-responsive table tbody');
                    if (tbody && preparedSchedules && preparedSchedules.length > 0) {
                        const inputs = tbody.querySelectorAll('input.prev-read-input');
                        inputs.forEach(function(input) {
                            const idx = parseInt(input.getAttribute('data-index'), 10);
                            if (!isNaN(idx) && preparedSchedules[idx]) {
                                const val = input.value.trim();
                                preparedSchedules[idx].prev_read = (val === '' || isNaN(parseFloat(val))) ? 0 : parseFloat(val);
                            }
                        });
                    }

                    const originalText = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';

                    fetch('{{ route("billing-processes.save-schedules") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            schedules: preparedSchedules,
                            save_scope: (isSingleConsumerPreparation || isMultipleConsumerPreparation) ? 'accounts' : 'zone'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('success', data.message);
                            const scheduleIds = data.schedule_ids || [];
                            const readerIdPreSelected = document.getElementById('assignReaderSelect') && document.getElementById('assignReaderSelect').value;
                            if ((isSingleConsumerPreparation || isMultipleConsumerPreparation) && scheduleIds.length > 0 && readerIdPreSelected) {
                                fetch('{{ route("billing-processes.assign-to-reader") }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken
                                    },
                                    body: JSON.stringify({
                                        schedule_ids: scheduleIds,
                                        reader_id: parseInt(readerIdPreSelected, 10)
                                    })
                                })
                                .then(function(r) { return r.json(); })
                                .then(function(assignData) {
                                    if (assignData.success) {
                                        showAlert('success', assignData.message);
                                    } else {
                                        showAlert('warning', assignData.message || 'Schedule saved but assign failed.');
                                    }
                                })
                                .catch(function() {
                                    showAlert('warning', 'Schedule saved but could not assign to reader.');
                                });
                            } else if ((isSingleConsumerPreparation || isMultipleConsumerPreparation) && scheduleIds.length > 0) {
                                lastSavedScheduleIds = scheduleIds;
                                const assignAfterSaveGroup = document.getElementById('assignAfterSaveGroup');
                                if (assignAfterSaveGroup) {
                                    assignAfterSaveGroup.style.display = 'inline-block';
                                    loadReadersIntoSelect('assignAfterSaveReaderSelect');
                                }
                            }
                            this.style.display = 'none';
                            const assignReaderGroup = document.getElementById('assignReaderGroup');
                            if (assignReaderGroup) assignReaderGroup.style.display = 'none';
                            preparedSchedules = [];
                            canSaveSchedules = false;
                            isSingleConsumerPreparation = false;
                            isMultipleConsumerPreparation = false;
                        } else {
                            showAlert('error', data.message);
                            this.disabled = false;
                            this.innerHTML = originalText;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('error', 'An error occurred while saving schedules');
                        this.disabled = false;
                        this.innerHTML = originalText;
                    });
                });
            }

            // Assign to reader (after save) — use saved schedule IDs and selected reader
            const assignToReaderBtn = document.getElementById('assignToReaderBtn');
            if (assignToReaderBtn) {
                assignToReaderBtn.addEventListener('click', function() {
                    const readerId = document.getElementById('assignAfterSaveReaderSelect') && document.getElementById('assignAfterSaveReaderSelect').value;
                    if (!readerId) {
                        showAlert('error', 'Please select a reader first.');
                        return;
                    }
                    if (!lastSavedScheduleIds || lastSavedScheduleIds.length === 0) {
                        showAlert('error', 'No saved schedules to assign. Save schedules first.');
                        return;
                    }
                    const btn = this;
                    const originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Assigning...';
                    fetch('{{ route("billing-processes.assign-to-reader") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            schedule_ids: lastSavedScheduleIds,
                            reader_id: parseInt(readerId, 10)
                        })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            showAlert('success', data.message);
                            lastSavedScheduleIds = [];
                            const ag = document.getElementById('assignAfterSaveGroup');
                            if (ag) ag.style.display = 'none';
                        } else {
                            showAlert('error', data.message || 'Failed to assign schedules.');
                        }
                    })
                    .catch(function(err) {
                        console.error(err);
                        showAlert('error', 'Could not assign schedules to reader.');
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
                });
            }

            // Reset Form Function
            const resetBtn = document.querySelector('.btn-warning');
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to reset the form? All current data will be cleared.')) {
                        // Reset form fields
                        document.getElementById('processSelect').selectedIndex = 0;
                        document.getElementById('zoneSelect').selectedIndex = 0;
                        document.getElementById('singleConsumerAccount').value = '';
                        if (document.getElementById('singleConsumerAccountGroup')) {
                            document.getElementById('singleConsumerAccountGroup').style.display = 'none';
                        }
                        const multipleConsumersEl = document.getElementById('multipleConsumersAccounts');
                        if (multipleConsumersEl) multipleConsumersEl.value = '';
                        if (document.getElementById('multipleConsumersAccountGroup')) {
                            document.getElementById('multipleConsumersAccountGroup').style.display = 'none';
                        }
                        document.getElementById('billMonth').value = '';
                        document.getElementById('billDate').value = '';
                        document.getElementById('dueDate').value = '';
                        document.getElementById('disconnectionDate').value = '';
                        document.getElementById('readingDateInput').value = '';
                        const surchargeBillDate = document.getElementById('surchargeBillDate');
                        if (surchargeBillDate) surchargeBillDate.value = '';
                        // Reset visibility of fields
                        document.getElementById('billMonthGroup').style.display = 'block';
                        document.getElementById('readingDateGroup').style.display = 'none';
                        const surchargeDateGroup = document.getElementById('surchargeDateGroup');
                        if (surchargeDateGroup) surchargeDateGroup.style.display = 'none';
                        document.getElementById('billingDatesSection').style.display = 'block';
                        // Clear table and prepared data
                        clearTable();
                        preparedSchedules = [];
                        canSaveSchedules = false;
                        isSingleConsumerPreparation = false;
                        currentSurchargeData = [];
                        currentDataType = '';
                        document.getElementById('saveSchedulesBtn').style.display = 'none';
                        const assignReaderGroupEl = document.getElementById('assignReaderGroup');
                        if (assignReaderGroupEl) assignReaderGroupEl.style.display = 'none';
                        const assignAfterSaveGroupEl = document.getElementById('assignAfterSaveGroup');
                        if (assignAfterSaveGroupEl) assignAfterSaveGroupEl.style.display = 'none';
                        lastSavedScheduleIds = [];
                        document.getElementById('applySurchargeBtn').style.display = 'none';
                        showAlert('info', 'Form has been reset');
                    }
                });
            }

            // Export Functions (Excel)
            const exportExcelBtn = document.querySelector('.btn-outline-success');
            if (exportExcelBtn) {
                exportExcelBtn.addEventListener('click', function() {
                    exportExcel();
                });
            }

            function exportExcel() {
                const zone = document.getElementById('zoneSelect').value;
                
                showAlert('info', 'Exporting data to EXCEL...');

                fetch('{{ route("billing-processes.export") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        format: 'excel',
                        zone: zone
                    })
                })
                .then(response => {
                    // If server returned JSON error, try to parse and show it
                    const contentType = response.headers.get('Content-Type') || '';
                    if (!response.ok && contentType.includes('application/json')) {
                        return response.json().then(data => {
                            throw new Error(data.message || 'Failed to export data.');
                        });
                    }
                    // Otherwise assume it's an Excel file (blob)
                    return response.blob().then(blob => {
                        if (!response.ok) {
                            throw new Error('Failed to export data.');
                        }
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        // Fallback filename; server also sends a filename header
                        a.download = 'Billing-Records.xlsx';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        window.URL.revokeObjectURL(url);
                        showAlert('success', 'Data exported successfully.');
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', error.message || 'An error occurred while exporting');
                });
            }

            // Populate Table Function
            function populateTable(data) {
                const tbody = document.querySelector('table tbody');
                const thInclude = document.getElementById('thIncludeSurcharge');
                const thPenalty = document.getElementById('thPenaltySurcharge');
                tbody.innerHTML = '';
                currentDataType = 'prepared';
                currentSurchargeData = [];
                if (thInclude) thInclude.style.display = 'none';
                if (thPenalty) thPenalty.style.display = 'none';
                document.getElementById('applySurchargeBtn').style.display = 'none';

                if (!data || data.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="16" class="text-center text-muted py-5">
                                <div class="py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 text-muted opacity-50"></i>
                                    <h6 class="text-muted">No Billing Records Found</h6>
                                    <p class="mb-0 small">Please execute the process to load data.</p>
                                </div>
                            </td>
                        </tr>
                    `;
                    return;
                }

                data.forEach((record, index) => {
                    const accNum = (record.account_number || '').toString().trim();
                    const accName = (record.account_name || '').toString().trim();
                    const row = `
                        <tr class="${index % 2 === 1 ? 'bg-light' : ''} border-bottom" data-account-number="${accNum.replace(/"/g, '&quot;')}" data-account-name="${accName.replace(/"/g, '&quot;')}">
                            <td class="text-center py-3 px-3 text-muted">${record.sedr}</td>
                            <td class="text-center py-3 px-3">
                                <span class="font-weight-bold text-dark">${record.account_number}</span>
                            </td>
                            <td class="py-3 px-3">${record.account_name}</td>
                            <td class="py-3 px-3 text-muted">${record.address}</td>
                            <td class="text-center py-3 px-3">
                                <span class="badge badge-light border">${record.zone}</span>
                            </td>
                            <td class="text-center py-3 px-3">
                                <span class="badge badge-${getCategoryBadgeClass(record.category)}">${record.category}</span>
                            </td>
                            <td class="text-center py-3 px-3 text-muted">${record.meter_number}</td>
                            <td class="text-center py-3 px-3 text-muted">${record.prev_date}</td>
                            <td class="text-right py-3 px-3">
                                <input type="number" min="0" step="0.001" class="form-control form-control-sm prev-read-input text-right border" value="${record.prev_read != null && record.prev_read !== '' ? record.prev_read : '0'}" data-index="${index}" style="max-width: 100px; display: inline-block;" title="Edit previous reading before saving" />
                            </td>
                            <td class="text-right py-3 px-3 font-weight-bold">${record.pres_read}</td>
                            <td class="text-right py-3 px-3">
                                <span class="badge badge-light">${record.volume}</span>
                            </td>
                            <td class="text-right py-3 px-3">₱ ${formatNumber(record.current_bill)}</td>
                            <td class="text-right py-3 px-3 ${(record.water_maintenance_charge || 0) > 0 ? 'text-warning' : 'text-muted'}">₱ ${formatNumber(record.water_maintenance_charge || 0)}</td>
                            <td class="text-right py-3 px-3 ${(record.arrears || 0) > 0 ? 'text-warning' : 'text-muted'}">₱ ${formatNumber(record.arrears || 0)}</td>
                            <td class="text-right py-3 px-3">
                                <span class="font-weight-bold ${record.total > 0 ? 'text-success' : 'text-muted'}">₱ ${formatNumber(record.total)}</span>
                            </td>
                            <td class="text-center py-3 px-3">
                                <span class="badge badge-${getStatusBadgeClass(record.status)} px-3 py-1">${record.status}</span>
                            </td>
                        </tr>
                    `;
                    tbody.insertAdjacentHTML('beforeend', row);
                });

                // Update record count and clear search filter
                const totalRecordsBadge = document.getElementById('totalRecordsBadge');
                if (totalRecordsBadge) {
                    totalRecordsBadge.textContent = data.length;
                }
                const searchInputEl = document.getElementById('billingTableSearch');
                if (searchInputEl) searchInputEl.value = '';
            }

            // Clear Table Function
            function clearTable() {
                const tbody = document.querySelector('table tbody');
                const thInclude = document.getElementById('thIncludeSurcharge');
                const thPenalty = document.getElementById('thPenaltySurcharge');
                currentSurchargeData = [];
                currentDataType = '';
                if (thInclude) thInclude.style.display = 'none';
                if (thPenalty) thPenalty.style.display = 'none';
                document.getElementById('applySurchargeBtn').style.display = 'none';
                tbody.innerHTML = `
                    <tr>
                        <td colspan="16" class="text-center text-muted py-5">
                            <div class="py-5">
                                <i class="fas fa-inbox fa-3x mb-3 text-muted opacity-50"></i>
                                <h6 class="text-muted">No Billing Records Found</h6>
                                <p class="mb-0 small">Please execute the process to load data.</p>
                            </div>
                        </td>
                    </tr>
                `;
                const totalRecordsBadge = document.getElementById('totalRecordsBadge');
                if (totalRecordsBadge) {
                    totalRecordsBadge.textContent = '0';
                }
                const searchInput = document.getElementById('billingTableSearch');
                if (searchInput) searchInput.value = '';
            }

            // Search/Filter table by Account # or Account Name
            function filterBillingTableBySearch() {
                const searchInput = document.getElementById('billingTableSearch');
                const tbody = document.querySelector('table tbody');
                const totalRecordsBadge = document.getElementById('totalRecordsBadge');
                if (!tbody || !totalRecordsBadge) return;

                const q = (searchInput && searchInput.value) ? searchInput.value.trim().toLowerCase() : '';
                const dataRows = tbody.querySelectorAll('tr[data-account-number], tr[data-account-name]');
                const totalRows = dataRows.length;

                if (totalRows === 0) return;

                let visibleCount = 0;
                dataRows.forEach(tr => {
                    const accNum = ((tr.dataset.accountNumber || tr.getAttribute('data-account-number') || '') + '').toLowerCase();
                    const accName = ((tr.dataset.accountName || tr.getAttribute('data-account-name') || '') + '').toLowerCase();
                    const match = !q || accNum.indexOf(q) !== -1 || accName.indexOf(q) !== -1;
                    tr.style.display = match ? '' : 'none';
                    if (match) visibleCount++;
                });

                if (q) {
                    totalRecordsBadge.textContent = visibleCount + (totalRows !== visibleCount ? ' of ' + totalRows : '');
                } else {
                    totalRecordsBadge.textContent = totalRows;
                }
            }

            const billingTableSearchInput = document.getElementById('billingTableSearch');
            if (billingTableSearchInput) {
                billingTableSearchInput.addEventListener('input', function() {
                    filterBillingTableBySearch();
                });
                billingTableSearchInput.addEventListener('keyup', function(e) {
                    if (e.key === 'Escape') {
                        billingTableSearchInput.value = '';
                        filterBillingTableBySearch();
                    }
                });
            }

            // Update Footer Function
            function updateFooter(summary) {
                const zoneElement = document.getElementById('footerZoneValue');
                const periodElement = document.getElementById('footerPeriodValue');
                const updatedElement = document.getElementById('footerUpdatedValue');

                if (!zoneElement || !periodElement || !updatedElement) {
                    return;
                }

                if (!summary) {
                    zoneElement.textContent = '—';
                    periodElement.textContent = '—';
                    updatedElement.textContent = '—';
                    return;
                }

                zoneElement.textContent = summary.zone ?? '—';
                periodElement.textContent = summary.bill_month ?? '—';
                updatedElement.textContent = summary.prepared_date ?? summary.reading_date ?? '—';
            }

            function debounce(fn, delay = 400) {
                let timer;
                return function(...args) {
                    clearTimeout(timer);
                    timer = setTimeout(() => fn.apply(this, args), delay);
                };
            }

            function formatCurrencyValue(value) {
                return `₱ ${formatNumber(value || 0)}`;
            }

            function setQuickLookupDisplay(options = {}) {
                if (quickAccountName) {
                    quickAccountName.textContent = options.accountName ?? '—';
                }
                if (quickAccountZone) {
                    quickAccountZone.textContent = options.zone ?? '—';
                }
                if (quickLatestBillAmount) {
                    quickLatestBillAmount.textContent = formatCurrencyValue(options.amount ?? 0);
                }
                if (quickLatestBillMonth) {
                    quickLatestBillMonth.textContent = options.billMonth ?? '—';
                }
                if (quickLatestBillStatus) {
                    const tone = options.statusTone ?? 'secondary';
                    quickLatestBillStatus.textContent = options.statusLabel ?? 'No Record';
                    quickLatestBillStatus.className = `badge px-3 py-2 badge-${tone}`;
                }
            }

            function updateQuickLookupStatus(message, tone = 'muted') {
                if (!quickLookupStatus) {
                    return;
                }
                quickLookupStatus.textContent = message;
                quickLookupStatus.className = `form-text text-${tone}`;
            }

            function resolveStatusBadgeTone(status) {
                switch ((status || '').toLowerCase()) {
                    case 'paid':
                        return 'success';
                    case 'completed':
                        return 'info';
                    case 'pending':
                        return 'warning';
                    default:
                        return 'secondary';
                }
            }

            function resetQuickLookupDisplay() {
                if (quickLookupController) {
                    quickLookupController.abort();
                    quickLookupController = null;
                }
                lastQuickLookupAccount = '';
                setQuickLookupDisplay();
                updateQuickLookupStatus('Provide an account number to fetch the latest downloaded bill.', 'muted');
            }

            async function handleQuickLookup(force = false) {
                if (!quickLookupAccountField) {
                    return;
                }

                const accountValue = (quickLookupAccountField.value || '').trim().toUpperCase();

                if (!accountValue) {
                    resetQuickLookupDisplay();
                    return;
                }

                if (!force && accountValue === lastQuickLookupAccount) {
                    return;
                }

                if (quickLookupController) {
                    quickLookupController.abort();
                }

                quickLookupController = new AbortController();
                updateQuickLookupStatus(`Looking up ${accountValue}…`, 'info');

                try {
                    const response = await fetch(`${quickLookupEndpoint}?account_number=${encodeURIComponent(accountValue)}`, {
                        signal: quickLookupController.signal,
                        headers: { 'Accept': 'application/json' }
                    });
                    const payload = await response.json().catch(() => null);

                    if (!response.ok || !payload) {
                        const message = payload?.message || 'Unable to fetch account information.';
                        updateQuickLookupStatus(message, 'danger');
                        setQuickLookupDisplay();
                        lastQuickLookupAccount = '';
                        return;
                    }

                    if (!payload.success) {
                        updateQuickLookupStatus(payload.message || 'Account lookup failed.', 'warning');
                        setQuickLookupDisplay();
                        lastQuickLookupAccount = '';
                        return;
                    }

                    const data = payload.data || {};
                    const bill = data.latest_bill || null;
                    const statusTone = bill ? resolveStatusBadgeTone(bill.status || bill.payment_status) : 'secondary';
                    const statusLabel = bill
                        ? (bill.payment_status || bill.status || 'Pending')
                        : 'No Record';

                    setQuickLookupDisplay({
                        accountName: data.account_name,
                        zone: data.zone,
                        amount: bill?.amount ?? 0,
                        billMonth: bill?.bill_month_display ?? '—',
                        statusLabel,
                        statusTone
                    });

                    if (bill) {
                        updateQuickLookupStatus(`Latest bill loaded${bill.bill_month_display ? ' for ' + bill.bill_month_display : ''}.`, 'success');
                    } else {
                        const message = data.message || 'No downloaded readings found for this account yet.';
                        updateQuickLookupStatus(message, 'info');
                    }

                    lastQuickLookupAccount = accountValue;
                } catch (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    console.error('Quick account lookup error:', error);
                    updateQuickLookupStatus('Unexpected error while fetching account information.', 'danger');
                    setQuickLookupDisplay();
                    lastQuickLookupAccount = '';
                } finally {
                    quickLookupController = null;
                }
            }

            // Helper Functions
            function getCategoryBadgeClass(category) {
                const categoryMap = {
                    'RES': 'info',
                    'COM': 'primary',
                    'IND': 'warning',
                    'GOV': 'secondary'
                };
                return categoryMap[category] || 'info';
            }

            function getStatusBadgeClass(status) {
                const statusMap = {
                    'Active': 'success',
                    'Pending': 'warning',
                    'Prepared': 'info',
                    'Inactive': 'secondary'
                };
                return statusMap[status] || 'secondary';
            }

            function formatNumber(number) {
                return parseFloat(number || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }

            function showAlert(type, message) {
                // Create alert element
                const alertClass = type === 'success' ? 'alert-success' : 
                                type === 'error' ? 'alert-danger' : 
                                type === 'warning' ? 'alert-warning' : 'alert-info';
                
                const iconClass = type === 'success' ? 'fa-check-circle' : 
                                type === 'error' ? 'fa-exclamation-circle' : 
                                type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';

                const alert = document.createElement('div');
                alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
                alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                alert.innerHTML = `
                    <i class="fas ${iconClass} mr-2"></i>${message}
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                `;

                document.body.appendChild(alert);

                // Auto dismiss after 5 seconds
                setTimeout(() => {
                    alert.remove();
                }, 5000);
            }

            resetQuickLookupDisplay();

            // Meter Reading Schedule Viewing tab: sorting state and load
            var scheduleBatchSortBy = 'zone';
            var scheduleBatchSortDir = 'asc';

            function loadScheduleBatches() {
                const tbody = document.getElementById('scheduleBatchesTableBody');
                const countEl = document.getElementById('scheduleBatchesCount');
                if (!tbody) return;
                var zoneSel = document.getElementById('scheduleFilterZone');
                var monthSel = document.getElementById('scheduleFilterBillMonth');
                var zone = (zoneSel && zoneSel.value) ? zoneSel.value : 'all';
                var billMonth = (monthSel && monthSel.value) ? monthSel.value : 'all';
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 mb-0">Loading...</p></td></tr>';
                var url = '{{ route("billing-processes.schedule-batches") }}?sort_by=' + encodeURIComponent(scheduleBatchSortBy) + '&sort_dir=' + encodeURIComponent(scheduleBatchSortDir);
                if (zone && zone !== 'all') url += '&zone=' + encodeURIComponent(zone);
                if (billMonth && billMonth !== 'all') url += '&bill_month=' + encodeURIComponent(billMonth);
                fetch(url, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.distinct_bill_months && monthSel) {
                        var currentVal = monthSel.value;
                        monthSel.innerHTML = '<option value="all">All</option>';
                        (data.distinct_bill_months || []).forEach(function(m) {
                            var opt = document.createElement('option');
                            opt.value = m;
                            opt.textContent = formatDisplayDate(m);
                            monthSel.appendChild(opt);
                        });
                        if (currentVal && Array.prototype.some.call(monthSel.options, function(o) { return o.value === currentVal; })) {
                            monthSel.value = currentVal;
                        }
                    }
                    if (data.success && data.data && data.data.length > 0) {
                        const batches = data.data;
                        tbody.innerHTML = batches.map(function(b) {
                            const billMonthD = b.bill_month ? formatDisplayDate(b.bill_month) : '—';
                            const billDate = b.bill_date ? formatDisplayDate(b.bill_date) : '—';
                            const dueDate = b.due_date ? formatDisplayDate(b.due_date) : '—';
                            const disconnDate = b.disconnection_date ? formatDisplayDate(b.disconnection_date) : '—';
                            const zone = (b.zone || '').replace(/"/g, '&quot;');
                            const bm = (b.bill_month || '').replace(/"/g, '&quot;');
                            const bd = (b.bill_date || '').replace(/"/g, '&quot;');
                            const dd = (b.due_date || '').replace(/"/g, '&quot;');
                            const dc = (b.disconnection_date || '').replace(/"/g, '&quot;');
                            const cnt = b.schedule_count || 0;
                            return '<tr class="border-bottom schedule-batch-row">' +
                                '<td class="py-3 px-3 text-center"><span class="badge badge-light border">' + (b.zone || '—') + '</span></td>' +
                                '<td class="py-3 px-3">' + billMonthD + '</td>' +
                                '<td class="py-3 px-3">' + billDate + '</td>' +
                                '<td class="py-3 px-3">' + dueDate + '</td>' +
                                '<td class="py-3 px-3">' + disconnDate + '</td>' +
                                '<td class="py-3 px-3 text-center">' + cnt + '</td>' +
                                '<td class="py-2 px-2 text-right">' +
                                '<button type="button" class="btn btn-sm btn-outline-primary schedule-batch-edit mr-1" data-zone="' + zone + '" data-bill-month="' + bm + '" data-bill-date="' + bd + '" data-due-date="' + dd + '" data-disconnection-date="' + dc + '" data-count="' + cnt + '" title="Edit"><i class="fas fa-edit"></i></button>' +
                                '<button type="button" class="btn btn-sm btn-outline-danger schedule-batch-delete" data-zone="' + zone + '" data-bill-month="' + bm + '" data-bill-date="' + bd + '" data-due-date="' + dd + '" data-disconnection-date="' + dc + '" data-count="' + cnt + '" title="Delete"><i class="fas fa-trash-alt"></i></button>' +
                                '</td></tr>';
                        }).join('');
                        if (countEl) countEl.textContent = batches.length;
                        updateScheduleSortIcons();
                    } else {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-5"><i class="fas fa-inbox fa-3x mb-3 opacity-50"></i><p class="mb-0">No meter reading schedules found.</p></td></tr>';
                        if (countEl) countEl.textContent = '0';
                        updateScheduleSortIcons();
                    }
                })
                .catch(function(err) {
                    console.error(err);
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Failed to load schedule batches.</td></tr>';
                    if (countEl) countEl.textContent = '0';
                });
            }
            function updateScheduleSortIcons() {
                document.querySelectorAll('.schedule-sort-icon').forEach(function(icon) {
                    var col = icon.getAttribute('data-for');
                    icon.className = 'fas ml-1 schedule-sort-icon ' + (col === scheduleBatchSortBy ? (scheduleBatchSortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort');
                });
            }
            document.querySelectorAll('.schedule-sort-zone, .schedule-sort-billmonth').forEach(function(th) {
                th.addEventListener('click', function() {
                    var sort = th.getAttribute('data-sort');
                    if (scheduleBatchSortBy === sort) {
                        scheduleBatchSortDir = scheduleBatchSortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        scheduleBatchSortBy = sort;
                        scheduleBatchSortDir = 'asc';
                    }
                    loadScheduleBatches();
                });
            });
            function formatDisplayDate(isoDate) {
                if (!isoDate) return '—';
                const d = new Date(isoDate);
                if (isNaN(d.getTime())) return isoDate;
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return m + '/' + day + '/' + y;
            }
            const loadScheduleBatchesBtn = document.getElementById('loadScheduleBatchesBtn');
            if (loadScheduleBatchesBtn) {
                loadScheduleBatchesBtn.addEventListener('click', loadScheduleBatches);
            }
            var scheduleFilterZoneEl = document.getElementById('scheduleFilterZone');
            var scheduleFilterBillMonthEl = document.getElementById('scheduleFilterBillMonth');
            if (scheduleFilterZoneEl) scheduleFilterZoneEl.addEventListener('change', loadScheduleBatches);
            if (scheduleFilterBillMonthEl) scheduleFilterBillMonthEl.addEventListener('change', loadScheduleBatches);

            // Schedule batch Edit: open modal with row data
            document.getElementById('scheduleBatchesTableBody').addEventListener('click', function(e) {
                var editBtn = e.target.closest('.schedule-batch-edit');
                if (editBtn) {
                    e.preventDefault();
                    document.getElementById('editScheduleZone').value = editBtn.getAttribute('data-zone') || '';
                    document.getElementById('editScheduleBillMonth').value = editBtn.getAttribute('data-bill-month') || '';
                    document.getElementById('editScheduleBillDate').value = editBtn.getAttribute('data-bill-date') || '';
                    document.getElementById('editScheduleDueDate').value = editBtn.getAttribute('data-due-date') || '';
                    document.getElementById('editScheduleDisconnectionDate').value = editBtn.getAttribute('data-disconnection-date') || '';
                    document.getElementById('editScheduleNewBillMonth').value = editBtn.getAttribute('data-bill-month') || '';
                    document.getElementById('editScheduleNewBillDate').value = editBtn.getAttribute('data-bill-date') || '';
                    document.getElementById('editScheduleNewDueDate').value = editBtn.getAttribute('data-due-date') || '';
                    document.getElementById('editScheduleNewDisconnectionDate').value = editBtn.getAttribute('data-disconnection-date') || '';
                    document.getElementById('editScheduleZoneDisplay').textContent = editBtn.getAttribute('data-zone') || '—';
                    document.getElementById('editScheduleRecordsDisplay').textContent = editBtn.getAttribute('data-count') || '0';
                    $('#editScheduleBatchModal').modal('show');
                    return;
                }
                var delBtn = e.target.closest('.schedule-batch-delete');
                if (delBtn) {
                    e.preventDefault();
                    var zone = delBtn.getAttribute('data-zone');
                    var billMonth = delBtn.getAttribute('data-bill-month');
                    var billDate = delBtn.getAttribute('data-bill-date');
                    var dueDate = delBtn.getAttribute('data-due-date');
                    var disconnectionDate = delBtn.getAttribute('data-disconnection-date');
                    var count = delBtn.getAttribute('data-count') || '0';
                    if (!confirm('Delete this schedule batch? ' + count + ' schedule(s) and their related consumer ledger entries will be removed. This cannot be undone.')) return;
                    var url = '{{ route("billing-processes.delete-schedules") }}';
                    var body = JSON.stringify({ zone: zone, bill_month: billMonth, bill_date: billDate || null, due_date: dueDate || null, disconnection_date: disconnectionDate || null });
                    fetch(url, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                        body: body
                    }).then(function(r) { return r.json(); }).then(function(data) {
                        if (data.success) {
                            showAlert('success', data.message);
                            loadScheduleBatches();
                        } else {
                            showAlert('danger', data.message || 'Delete failed.');
                        }
                    }).catch(function(err) {
                        console.error(err);
                        showAlert('danger', 'Failed to delete schedule batch.');
                    });
                }
            });

            document.getElementById('editScheduleBatchSaveBtn').addEventListener('click', function() {
                var zone = document.getElementById('editScheduleZone').value;
                var billMonth = document.getElementById('editScheduleBillMonth').value;
                var billDate = document.getElementById('editScheduleBillDate').value;
                var dueDate = document.getElementById('editScheduleDueDate').value;
                var disconnectionDate = document.getElementById('editScheduleDisconnectionDate').value;
                var newBillMonth = document.getElementById('editScheduleNewBillMonth').value;
                var newBillDate = document.getElementById('editScheduleNewBillDate').value;
                var newDueDate = document.getElementById('editScheduleNewDueDate').value;
                var newDisconnectionDate = document.getElementById('editScheduleNewDisconnectionDate').value;
                if (!newBillMonth || !newBillDate || !newDueDate || !newDisconnectionDate) {
                    showAlert('warning', 'Please fill all date fields.');
                    return;
                }
                var btn = this;
                btn.disabled = true;
                var url = '{{ route("billing-processes.update-schedule-batch") }}';
                var payload = {
                    zone: zone,
                    bill_month: billMonth,
                    bill_date: billDate,
                    due_date: dueDate,
                    disconnection_date: disconnectionDate,
                    new_bill_month: newBillMonth,
                    new_bill_date: newBillDate,
                    new_due_date: newDueDate,
                    new_disconnection_date: newDisconnectionDate
                };
                fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                    body: JSON.stringify(payload)
                }).then(function(r) { return r.json(); }).then(function(data) {
                    btn.disabled = false;
                    if (data.success) {
                        $('#editScheduleBatchModal').modal('hide');
                        showAlert('success', data.message);
                        loadScheduleBatches();
                    } else {
                        showAlert('danger', data.message || 'Update failed.');
                    }
                }).catch(function(err) {
                    btn.disabled = false;
                    console.error(err);
                    showAlert('danger', 'Failed to update schedule batch.');
                });
            });

            $('#tab-schedule-viewing').on('shown.bs.tab', function() {
                var countEl = document.getElementById('scheduleBatchesCount');
                if (countEl && countEl.textContent === '0' && document.getElementById('scheduleBatchesTableBody').querySelector('tr td[colspan="7"]')) {
                    loadScheduleBatches();
                }
            });

            // Filter Options - SEDR and Account Number
            const filterBtns = document.querySelectorAll('.btn-outline-secondary');
            if (filterBtns.length > 0) {
                filterBtns.forEach(btn => {
                    btn.addEventListener('click', function() {
                        const filterType = this.textContent.trim().includes('SEDR') ? 'SEDR' : 'Account';
                        showAlert('info', 'Filter by ' + filterType + ' is coming soon');
                    });
                });
            }

            // Close Button
            const closeBtn = document.querySelector('.btn-outline-danger.btn-sm');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to close this page?')) {
                        window.location.href = '{{ route("dashboard") }}';
                    }
                });
            }

            // Print Billing Report Button
            const printBillingBtn = document.getElementById('printBillingBtn');
            if (printBillingBtn) {
                console.log('Print button found and event listener attached');
                printBillingBtn.addEventListener('click', function() {
                    console.log('Print button clicked');
                    console.log('Current billing data:', currentBillingData);
                    console.log('Current billing zone:', currentBillingZone);
                    console.log('Current billing month:', currentBillingMonth);
                    
                    if (!currentBillingData || currentBillingData.length === 0) {
                        showAlert('warning', 'No billing data to print. Please execute the process first to load data.');
                        return;
                    }
                    
                    try {
                        printDailyBillingReport();
                    } catch (error) {
                        console.error('Print error:', error);
                        showAlert('error', 'An error occurred while printing: ' + error.message);
                    }
                });
            } else {
                console.error('Print button (printBillingBtn) not found in the DOM!');
            }

            // Apply Surcharge Button - apply penalty to checked rows only
            const applySurchargeBtn = document.getElementById('applySurchargeBtn');
            if (applySurchargeBtn) {
                applySurchargeBtn.addEventListener('click', function() {
                    if (!currentSurchargeData || currentSurchargeData.length === 0) {
                        showAlert('warning', 'No surcharge data. Execute Generate Surcharge or Generate Penalty (Single Consumer) first.');
                        return;
                    }
                    const checkboxes = document.querySelectorAll('.surcharge-include-checkbox:checked');
                    if (!checkboxes || checkboxes.length === 0) {
                        showAlert('warning', 'Select at least one consumer to apply surcharge (check the Include box).');
                        return;
                    }
                    const items = [];
                    checkboxes.forEach(cb => {
                        const index = parseInt(cb.getAttribute('data-index'), 10);
                        const record = currentSurchargeData[index];
                        if (record && record.schedule_id) {
                            items.push({
                                schedule_id: record.schedule_id,
                                downloaded_id: record.downloaded_id || null,
                                consumer_zone_id: record.consumer_zone_id || null,
                                current_bill: record.current_bill || 0,
                                due_date: record.due_date || '',
                                account_number: record.account_number || '',
                                calculated_penalty: record.calculated_penalty != null ? record.calculated_penalty : undefined
                            });
                        }
                    });
                    if (items.length === 0) {
                        showAlert('error', 'No valid rows to apply surcharge.');
                        return;
                    }
                    if (!confirm('Apply surcharge (10% penalty) to ' + items.length + ' selected consumer(s)?')) {
                        return;
                    }
                    const btn = this;
                    const originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Applying...';
                    fetch('{{ route("billing-processes.apply-surcharge") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ items: items })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('success', data.message);
                        } else {
                            showAlert('error', data.message || 'Failed to apply surcharge');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('error', 'An error occurred while applying surcharge');
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
                });
            }

            // Print Daily Billing Report Function
            function printDailyBillingReport() {
                console.log('printDailyBillingReport() called');
                console.log('currentBillingData:', currentBillingData);
                console.log('currentBillingZone:', currentBillingZone);
                console.log('currentBillingMonth:', currentBillingMonth);
                
                // Populate print template
                const billMonthDisplay = currentBillingMonth || 'N/A';
                const zoneDisplay = `Zone ${currentBillingZone || 'N/A'}`;
                
                const billMonthElement = document.getElementById('printBillMonth');
                const zoneElement = document.getElementById('printZone');
                const printTableBody = document.getElementById('printTableBody');
                
                console.log('Print elements found:', {
                    billMonth: !!billMonthElement,
                    zone: !!zoneElement,
                    tableBody: !!printTableBody
                });
                
                if (!billMonthElement || !zoneElement || !printTableBody) {
                    console.error('Print template elements not found!');
                    showAlert('error', 'Print template is missing. Please refresh the page.');
                    return;
                }
                
                billMonthElement.textContent = billMonthDisplay;
                zoneElement.textContent = zoneDisplay;
                printTableBody.innerHTML = '';

                if (!currentBillingData || currentBillingData.length === 0) {
                    showAlert('warning', 'No billing data available to print');
                    return;
                }

                console.log(`Populating ${currentBillingData.length} rows for print`);

                currentBillingData.forEach((record, index) => {
                    const row = document.createElement('tr');
                    const accountNumber = record.account_number || record.account_no || '-';
                    const accountName = record.account_name || record.name || '-';
                    const volume = record.volume || record.consumption || 0;
                    const currentBill = record.current_bill || record.total || 0;
                    const waterMaintenance = (currentBill > 0) ? 20.00 : 0.00;
                    
                    row.innerHTML = `
                        <td>${accountNumber}</td>
                        <td>${accountName}</td>
                        <td></td>
                        <td class="text-right">${volume}</td>
                        <td class="text-right">${formatNumber(currentBill)}</td>
                        <td class="text-right"></td>
                        <td class="text-right">${formatNumber(waterMaintenance)}</td>
                    `;
                    printTableBody.appendChild(row);
                });

                console.log('Print table populated with', printTableBody.children.length, 'rows');

                // Generate breakdown by category
                generateBreakdownTable();

                console.log('Opening print dialog...');
                
                // Trigger print dialog
                setTimeout(() => {
                    window.print();
                }, 100);
            }

            // Generate Breakdown of Metered Sales Table
            function generateBreakdownTable() {
                const breakdownTableBody = document.getElementById('breakdownTableBody');
                breakdownTableBody.innerHTML = '';

                // Group data by category
                const categoryData = {};
                let totalConsumers = 0;
                let totalCubicMeters = 0;
                let totalAmount = 0;

                const resolveCategoryCode = (record) => {
                    const raw = (record.category
                        || record.category_code
                        || record.consumer_category
                        || record.category_name
                        || '').toString().trim().toUpperCase();

                    const map = {
                        'RES': '12', 'RESIDENTIAL': '12',
                        'GOV': '22', 'GOVERNMENT': '22',
                        'COM': '32', 'COMMERCIAL': '32',
                        'COMA': '33', 'COMMERCIAL A': '33',
                        'GOV1': '34', 'GOVERNMENT 1': '34',
                        'COMD': '35', 'COMMERCIAL D': '35',
                        'BULK': '36', 'WHOLESALE': '36', 'BULK/WHOLESALE': '36',
                    };

                    if (map[raw]) return map[raw];

                    // If already numeric-like, keep it; otherwise mark unknown
                    return raw || 'UNKNOWN';
                };

                const isBillPrinting = (currentDataType === 'downloaded');
                currentBillingData.forEach(record => {
                    const category = resolveCategoryCode(record);
                    const volume = parseFloat(record.volume ?? record.consumption ?? 0) || 0;
                    const currentBill = parseFloat(record.current_bill ?? record.total ?? 0) || 0;
                    const amount = isBillPrinting
                        ? currentBill + (currentBill > 0 ? 20.00 : 0.00)
                        : currentBill;

                    if (!categoryData[category]) {
                        categoryData[category] = {
                            count: 0,
                            cubicMeters: 0,
                            amount: 0
                        };
                    }

                    categoryData[category].count++;
                    categoryData[category].cubicMeters += volume;
                    categoryData[category].amount += amount;

                    totalConsumers++;
                    totalCubicMeters += volume;
                    totalAmount += amount;
                });

                // Define category order and display names
                const categoryOrder = [
                    { key: '12', display: 'RESIDENTIAL' },
                    { key: '22', display: 'GOVERNMENT' },
                    { key: '32', display: 'COMMERCIAL' },
                    { key: '33', display: 'COMMERCIAL A ' },
                    { key: '34', display: 'GOVERNMENT 1 ' },
                    { key: '35', display: 'COMMERCIAL D ' },
                    { key: '36', display: 'BULK/WHOLESALE' },
                ];

                // Add rows for each category
                categoryOrder.forEach(cat => {
                    const data = categoryData[cat.key] || { count: 0, cubicMeters: 0, amount: 0 };
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td style="border: 1px solid #000; padding: 6px 4px; font-size: 12px; font-weight: bold;">${cat.display}</td>
                        <td style="border: 1px solid #000; padding: 6px 4px; text-align: center; font-size: 12px; font-weight: bold;">${data.count}</td>
                        <td style="border: 1px solid #000; padding: 6px 4px; text-align: center; font-size: 12px; font-weight: bold;">${Math.round(data.cubicMeters)}</td>
                        <td style="border: 1px solid #000; padding: 6px 4px; text-align: right; font-size: 12px; font-weight: bold;">${formatNumber(data.amount)}</td>
                    `;
                    breakdownTableBody.appendChild(row);
                });

                // Add any other categories not in the predefined list
                Object.keys(categoryData).forEach(category => {
                    if (!categoryOrder.find(c => c.key === category)) {
                        const data = categoryData[category];
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td style="border: 1px solid #000; padding: 6px 4px; font-size: 12px; font-weight: bold;">${category}</td>
                            <td style="border: 1px solid #000; padding: 6px 4px; text-align: center; font-size: 12px; font-weight: bold;">${data.count}</td>
                            <td style="border: 1px solid #000; padding: 6px 4px; text-align: center; font-size: 12px; font-weight: bold;">${Math.round(data.cubicMeters)}</td>
                            <td style="border: 1px solid #000; padding: 6px 4px; text-align: right; font-size: 12px; font-weight: bold;">${formatNumber(data.amount)}</td>
                        `;
                        breakdownTableBody.appendChild(row);
                    }
                });

                // Add TOTAL row
                const totalRow = document.createElement('tr');
                totalRow.innerHTML = `
                    <td style="border: 1px solid #000; padding: 6px 4px; font-size: 12px; font-weight: bold;">TOTAL</td>
                    <td style="border: 1px solid #000; padding: 6px 4px; text-align: center; font-size: 12px; font-weight: bold;">${totalConsumers}</td>
                    <td style="border: 1px solid #000; padding: 6px 4px; text-align: center; font-size: 12px; font-weight: bold;">${Math.round(totalCubicMeters)}</td>
                    <td style="border: 1px solid #000; padding: 6px 4px; text-align: right; font-size: 12px; font-weight: bold;">${formatNumber(totalAmount)}</td>
                `;
                breakdownTableBody.appendChild(totalRow);
            }

            // Debug Button - Check consumers in selected zone
            const debugBtn = document.getElementById('debugBtn');
            if (debugBtn) {
                debugBtn.addEventListener('click', function() {
                    const zone = document.getElementById('zoneSelect').value;
                    
                    if (!zone) {
                        showAlert('warning', 'Please select a zone first');
                        return;
                    }

                    const originalText = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Checking...';

                    fetch('{{ route("billing-processes.debug-consumers") }}?zone=' + zone)
                        .then(response => response.json())
                        .then(data => {
                            console.log('Debug Data:', data);
                            
                            let message = `<div class="text-left">
                                <h6 class="font-weight-bold mb-3">Zone ${data.zone} Debug Information</h6>
                                <p><strong>Total Consumers:</strong> ${data.total}</p>
                                <p><strong>Status Breakdown:</strong></p>
                                <ul class="mb-3">`;
                            
                            if (data.by_status && Object.keys(data.by_status).length > 0) {
                                for (const [status, count] of Object.entries(data.by_status)) {
                                    message += `<li>${status}: ${count} consumer(s)</li>`;
                                }
                            } else {
                                message += `<li class="text-danger">No consumers found in this zone</li>`;
                            }
                            
                            message += `</ul>
                                <p class="small text-muted mb-0">Check browser console for detailed list</p>
                            </div>`;

                            // Create custom modal-like alert
                            const alertDiv = document.createElement('div');
                            alertDiv.className = 'alert alert-info alert-dismissible fade show position-fixed';
                            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 400px; max-width: 500px;';
                            alertDiv.innerHTML = `
                                ${message}
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                            `;
                            document.body.appendChild(alertDiv);

                            setTimeout(() => alertDiv.remove(), 15000);
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showAlert('error', 'Error fetching debug information');
                        })
                        .finally(() => {
                            this.disabled = false;
                            this.innerHTML = originalText;
                        });
                });
            }
        </script>
    </body>
    </html>

