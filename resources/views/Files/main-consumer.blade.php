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
                @if($consumer)
                <script>sessionStorage.setItem('currentConsumer', JSON.stringify(@json($consumer)));</script>
                @endif

                <!-- Navigation Tabs -->
                <div class="card-body p-0">
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <a href="{{ route('consumer') }}{{ isset($consumer) && $consumer ? '?account=' . urlencode($consumer->account_no) : '' }}" class="nav-link active" id="consumerDetailsTab">
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
                            <a href="{{ route('consumption') }}" class="nav-link">
                                <i class="fas fa-chart-line me-1"></i>Consumption Graph
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Simple Action Buttons -->
                <div class="bg-white px-3 py-2 border-bottom mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <!-- Navigation Controls -->
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#newConsumerModal" data-bs-toggle="modal" data-bs-target="#newConsumerModal">
                                <i class="fas fa-plus me-1"></i>New
                            </button>
                            <button class="btn btn-primary btn-sm" id="editConsumerBtn" disabled>
                                <i class="fas fa-edit me-1"></i>Edit
                            </button>
                            <button class="btn btn-danger btn-sm" id="deleteConsumerBtn" disabled>
                                <i class="fas fa-trash me-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="p-3 bg-light">
                    <div class="row g-3">
                        <!-- Left Column -->
                        <div class="col-lg-8">
                            <!-- Information Card -->
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Consumer Information</h6>
                                </div>
                                <div class="card-body" id="consumerInfoCard">
                                    @if($consumer)
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Installation Date</label>
                                                <input type="text" class="form-control form-control-sm" id="installationDate"
                                                    value="{{ $consumer->install_date ? $consumer->install_date->format('m/d/Y') : '' }}" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Account Number</label>
                                                <input type="text" class="form-control form-control-sm" id="accountNumber"
                                                    value="{{ $consumer->account_no }}" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Meter Number</label>
                                                <input type="text" class="form-control form-control-sm" id="meterNumber"
                                                    value="{{ $consumer->meter_number ?? '' }}" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Address</label>
                                                <input type="text" class="form-control form-control-sm" id="address"
                                                    value="{{ $consumer->address1 ?? '' }}" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Category</label>
                                                <input type="text" class="form-control form-control-sm" id="displayCategory"
                                                    value="{{ $consumer->category_code ?? '' }}" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <input type="text" class="form-control form-control-sm" id="status"
                                                    value="{{ $consumer->status_label }}" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Account Name</label>
                                                <input type="text" class="form-control form-control-sm" id="accountName"
                                                    value="{{ $consumer->account_name }}" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Meter Brand</label>
                                                <input type="text" class="form-control form-control-sm" id="meterBrand"
                                                    value="{{ $consumer->meter_brand ?? '' }}" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Zone</label>
                                                <input type="text" class="form-control form-control-sm" id="displayZone"
                                                    value="{{ $consumer->zone_code ?? '' }}" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Sequence</label>
                                                <input type="text" class="form-control form-control-sm" id="sequence"
                                                    value="{{ $consumer->sequence ?? '' }}" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Rate Code</label>
                                                <input type="text" class="form-control form-control-sm" id="rateCode"
                                                    value="{{ $consumer->rate_code ?? '' }}" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Control Number</label>
                                                <input type="text" class="form-control form-control-sm" id="displayCardNumber"
                                                    value="{{ $consumer->cons_ctrl ?? '' }}" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Balance</label>
                                                <input type="text" class="form-control form-control-sm" id="balance"
                                                    value="₱ {{ number_format($consumer->balance ?? 0, 2) }}" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    @else
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-user-slash fa-3x mb-3"></i>
                                        <p>No consumer selected. Use the search box to find a consumer.</p>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Charges Table Card -->
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Charges Entry</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Description</th>
                                                    <th class="text-end">Charge</th>
                                                    <th class="text-end">Amortization</th>
                                                    <th class="text-end">Balance</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-4">
                                                        <i class="fas fa-inbox mb-2 d-block"></i>
                                                        No charges recorded
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Meter Reading Card -->
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Meter Reading</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6 class="text-success">Current Reading</h6>
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <small class="text-muted">Date</small>
                                                            <div class="fw-bold" id="meterReadingCurrentDate">{{ $meterReading && $meterReading->current_reading_date ? \Carbon\Carbon::parse($meterReading->current_reading_date)->format('m/d/Y') : '—' }}</div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Reading</small>
                                                            <div class="fw-bold" id="meterReadingCurrentValue">{{ $meterReading && $meterReading->current_reading !== null ? $meterReading->current_reading : '—' }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6 class="text-info">Previous Reading</h6>
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <small class="text-muted">Date</small>
                                                            <div class="fw-bold" id="meterReadingPrevDate">{{ $meterReading && $meterReading->previous_reading_date ? \Carbon\Carbon::parse($meterReading->previous_reading_date)->format('m/d/Y') : '—' }}</div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Reading</small>
                                                            <input type="number" min="0" step="1" class="form-control form-control-sm fw-bold d-inline-block" id="meterReadingPrevValue" value="{{ $meterReading ? $meterReading->previous_reading : 0 }}" style="max-width: 100px;" placeholder="0">
                                                        </div>
                                                    </div>
                                                    <div class="meter-reading-save-block mt-2" style="{{ ($consumer && $meterReading && $meterReading->schedule_id) ? '' : 'display:none;' }}">
                                                        <input type="hidden" id="meterReadingScheduleId" value="{{ $meterReading && $meterReading->schedule_id ? $meterReading->schedule_id : '' }}">
                                                        <input type="hidden" id="meterReadingAccountNo" value="{{ $consumer ? $consumer->account_no : '' }}">
                                                        <div class="mt-2">
                                                            <button type="button" class="btn btn-sm btn-primary" id="meterReadingSavePrevBtn"><i class="fas fa-save mr-1"></i>Save Previous Reading</button>
                                                            <span class="small text-muted ml-2" id="meterReadingSaveStatus"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <!-- Right Column - Latest Bill -->
                        @php
                            $latestBillMonthLabel = ($latestBill && $latestBill->bill_month)
                                ? \Carbon\Carbon::parse($latestBill->bill_month)->format('m-Y')
                                : '--';
                            $formatLatestCurrency = function ($value) {
                                $amount = is_numeric($value) ? (float) $value : 0;
                                return '₱ ' . number_format($amount, 2);
                            };
                            $latestBillCurrentBill = optional($latestBill)->current_bill ?? 0;
                            $latestBillMeterRental = optional($latestBill)->meter_rental ?? 0;
                            $latestBillArrears = optional($latestBill)->arrears ?? 0;
                            $latestBillMaterials = optional($latestBill)->materials ?? 0;
                            $latestBillSeptage = optional($latestBill)->septage_fee ?? 0;
                            $latestBillOthers = optional($latestBill)->others ?? 0;
                            
                            // Compute total bill by adding all components
                            $latestBillTotal = (float) $latestBillCurrentBill 
                                             + (float) $latestBillMeterRental 
                                             + (float) $latestBillArrears 
                                             + (float) $latestBillMaterials 
                                             + (float) $latestBillSeptage 
                                             + (float) $latestBillOthers;
                        @endphp
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0" id="latestBillHeader">Latest Bill - {{ $latestBillMonthLabel }}</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Current Bill:</span>
                                        <input type="text" class="form-control form-control-sm w-auto text-end"
                                            id="latestBillCurrent" value="{{ $formatLatestCurrency($latestBillCurrentBill) }}" readonly>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Water Maintenance Charge:</span>
                                        <input type="text" class="form-control form-control-sm w-auto text-end"
                                            id="latestBillMeterRental" value="{{ $formatLatestCurrency($latestBillMeterRental) }}" readonly>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Arrears:</span>
                                        <input type="text" class="form-control form-control-sm w-auto text-end"
                                            id="latestBillArrears" value="{{ $formatLatestCurrency($latestBillArrears) }}" readonly>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Materials:</span>
                                        <input type="text" class="form-control form-control-sm w-auto text-end"
                                            id="latestBillMaterials" value="{{ $formatLatestCurrency($latestBillMaterials) }}" readonly>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Septage Fee:</span>
                                        <input type="text" class="form-control form-control-sm w-auto text-end"
                                            id="latestBillSeptage" value="{{ $formatLatestCurrency($latestBillSeptage) }}" readonly>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Others:</span>
                                        <input type="text" class="form-control form-control-sm w-auto text-end"
                                            id="latestBillOthers" value="{{ $formatLatestCurrency($latestBillOthers) }}" readonly>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold text-primary">TOTAL BILL:</span>
                                        <input type="text"
                                            class="form-control form-control-sm w-auto text-end fw-bold text-primary"
                                            id="latestBillTotal" value="{{ $formatLatestCurrency($latestBillTotal) }}" readonly>
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
  </div>

    <!-- Scroll to top -->
    <a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
  </a>

    <!-- PIN required to edit (Consumer Edit PIN modal) -->
    <div class="modal fade" id="consumerEditPinModal" tabindex="-1" aria-labelledby="consumerEditPinModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="consumerEditPinModalLabel">
                        <i class="fas fa-lock me-2"></i>Enter PIN
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Enter the edit PIN to continue.</p>
                    <input type="password" class="form-control" id="consumerEditPinInput" placeholder="PIN" autocomplete="off" maxlength="20">
                    <div class="invalid-feedback" id="consumerEditPinError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="consumerEditPinVerifyBtn"><i class="fas fa-check me-1"></i>Verify</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Consumer Modal -->
    <div class="modal fade" id="editConsumerModal" tabindex="-1" aria-labelledby="editConsumerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editConsumerModalLabel">
                        <i class="fas fa-user-edit me-2"></i>Edit Consumer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editConsumerForm">
                    @csrf
                    <input type="hidden" id="edit_consumer_id" name="consumer_id">
                    <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_installation_date" class="form-label">Installation Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="edit_installation_date" name="installation_date" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_account_number" class="form-label">Account Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_account_number" name="account_number" readonly required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_meter_number" class="form-label">Meter Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_meter_number" name="meter_number" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_address" class="form-label">Address <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="edit_address" name="address" rows="3" required></textarea>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_category" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="Residential" data-code="12">Residential</option>
                                        <option value="Government" data-code="22">Government</option>
                                        <option value="Commercial/Industrial C" data-code="32">Commercial/Industrial C</option>
                                        <option value="Commercial A" data-code="33">Commercial A</option>
                                        <option value="Commercial B" data-code="34">Commercial B</option>
                                        <option value="Commercial D" data-code="35">Commercial D</option>
                                        <option value="Whole Sale/Bulk" data-code="36">Whole Sale/Bulk</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_rate_code" class="form-label">Rate Code <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_rate_code" name="rate_code" required>
                                        <option value="">Select Rate Code</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="A">A</option>
                                        <option value="P">P</option>
                                        <option value="X">X</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_first_name" name="first_name" placeholder="Enter First Name" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_last_name" name="last_name" placeholder="Enter Last Name" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_middle_name" class="form-label">Middle Name</label>
                                            <input type="text" class="form-control" id="edit_middle_name" name="middle_name" placeholder="Enter Middle Name">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="edit_extension" class="form-label">Extension</label>
                                            <select class="form-select" id="edit_extension" name="extension">
                                                <option value="">None</option>
                                                <option value="Jr.">Jr.</option>
                                                <option value="Sr.">Sr.</option>
                                                <option value="II">II</option>
                                                <option value="III">III</option>
                                                <option value="IV">IV</option>
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_contact_number" class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" id="edit_contact_number" name="contact_number" placeholder="Enter Contact Number">
                                    <div class="invalid-feedback"></div>
                                    <small class="text-muted">Note: Contact number is for reference only and not stored in the system</small>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_meter_brand" class="form-label">Meter Brand</label>
                                    <input type="text" class="form-control" id="edit_meter_brand" name="meter_brand">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_zone" class="form-label">Zone <span class="text-danger">*</span></label>
                                    <select class="form-select" id="edit_zone" name="zone" required>
                                        <option value="">Select Zone</option>
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
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_card_number" class="form-label">Sequence <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="edit_card_number" name="card_number" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_cons_ctrl" class="form-label">Control Number</label>
                                    <input type="text" class="form-control" id="edit_cons_ctrl" name="cons_ctrl" readonly>
                                    <small class="text-muted">Control number (cannot be changed)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="updateBtn">
                            <i class="fas fa-save me-1"></i>Update Consumer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New Consumer Modal -->
    <div class="modal fade" id="newConsumerModal" tabindex="-1" aria-labelledby="newConsumerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="newConsumerModalLabel">
                        <i class="fas fa-user-plus me-2"></i>Add New Consumer
                    </h5>
                    <button type="button" class="btn-close btn-close-red" id="modalCloseBtn" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="newConsumerForm">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="installation_date" class="form-label">Installation Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="installation_date" name="installation_date" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="account_number" class="form-label">Account Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="account_number" name="account_number" readonly required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="meter_number" class="form-label">Meter Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="meter_number" name="meter_number" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="Residential" data-code="12">Residential</option>
                                        <option value="Government" data-code="22">Government</option>
                                        <option value="Commercial/Industrial C" data-code="32">Commercial/Industrial C</option>
                                        <option value="Commercial A" data-code="33">Commercial A</option>
                                        <option value="Commercial B" data-code="34">Commercial B</option>
                                        <option value="Commercial D" data-code="35">Commercial D</option>
                                        <option value="Whole Sale/Bulk" data-code="36">Whole Sale/Bulk</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                     <label for="rate_code" class="form-label">Rate Code <span class="text-danger">*</span></label>
                                     <select class="form-select " id="rate_code" name="rate_code" required>
                                        <option value="">Select Rate Code</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                        
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                        
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="A" selected>A</option>
                                        <option value="P">P</option>
                                        <option value="X">X</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Enter First Name" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Enter Last Name" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="middle_name" class="form-label">Middle Name</label>
                                            <input type="text" class="form-control" id="middle_name" name="middle_name" placeholder="Enter Middle Name">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="extension" class="form-label">Extension</label>
                                            <select class="form-select" id="extension" name="extension">
                                                <option value="">None</option>
                                                <option value="Jr.">Jr.</option>
                                                <option value="Sr.">Sr.</option>
                                                <option value="II">II</option>
                                                <option value="III">III</option>
                                                <option value="IV">IV</option>
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="contact_number" class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" id="contact_number" name="contact_number" placeholder="Enter Contact Number">
                                    <div class="invalid-feedback"></div>
                                    <small class="text-muted">Note: Contact number is for reference only and not stored in the system</small>
                                </div>
                                <div class="mb-3">
                                    <label for="meter_brand" class="form-label">Meter Brand</label>
                                    <input type="text" class="form-control" id="meter_brand" name="meter_brand">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="zone" class="form-label">Zone <span class="text-danger">*</span></label>
                                    <select class="form-select" id="zone" name="zone" required>
                                        <option value="">Select Zone</option>
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
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="card_number" class="form-label">Sequence <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="card_number" name="card_number" placeholder="Enter Sequence Number" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="display_cons_ctrl" class="form-label">Control Number</label>
                                    <input type="text" class="form-control" id="display_cons_ctrl" name="display_cons_ctrl" readonly>
                                    <small class="text-muted">Auto-generated control number</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="cancelBtn" data-dismiss="modal" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save me-1"></i>Save Consumer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
  <!-- Footer -->
  @include('partials.footer')
<!-- Footer -->

@include('consumer.shared-header-script')

<script>
    // Track current consumer globally
    let currentConsumer = null;

    $(document).ready(function() {
        console.log('Consumer form script loaded');

        // Enable Edit/Delete buttons if consumer is already loaded from backend
        @if($consumer)
        currentConsumer = @json($consumer);
        $('#editConsumerBtn').prop('disabled', false);
        $('#deleteConsumerBtn').prop('disabled', false);
        console.log('Consumer loaded from backend:', currentConsumer.account_no);
        // Store in sessionStorage
        sessionStorage.setItem('currentConsumer', JSON.stringify(currentConsumer));
        @endif
        
        const initialLatestBill = @json($latestBill);
        
        // PIN required for Edit / Delete / Save Previous Reading
        var pendingEditCallback = null;
        function requestPinThenRun(callback) {
            pendingEditCallback = callback;
            $('#consumerEditPinInput').val('');
            $('#consumerEditPinError').text('').removeClass('d-block');
            $('#consumerEditPinInput').removeClass('is-invalid');
            $('#consumerEditPinModal').modal('show');
            setTimeout(function() { document.getElementById('consumerEditPinInput') && document.getElementById('consumerEditPinInput').focus(); }, 300);
        }
        document.getElementById('consumerEditPinVerifyBtn').addEventListener('click', function() {
            var pinInput = document.getElementById('consumerEditPinInput');
            var errEl = document.getElementById('consumerEditPinError');
            var pin = (pinInput && pinInput.value) ? pinInput.value.trim() : '';
            if (!pin) {
                if (errEl) { errEl.textContent = 'Enter PIN.'; errEl.classList.add('d-block'); }
                if (pinInput) pinInput.classList.add('is-invalid');
                return;
            }
            var btn = this;
            btn.disabled = true;
            fetch('{{ route("consumer.verify-edit-pin") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '' },
                body: JSON.stringify({ pin: pin })
            }).then(function(r) { return r.json(); }).then(function(data) {
                btn.disabled = false;
                if (data.success) {
                    $('#consumerEditPinModal').modal('hide');
                    var cb = pendingEditCallback;
                    pendingEditCallback = null;
                    if (typeof cb === 'function') cb();
                } else {
                    if (errEl) { errEl.textContent = data.message || 'Invalid PIN.'; errEl.classList.add('d-block'); }
                    if (pinInput) pinInput.classList.add('is-invalid');
                }
            }).catch(function() {
                btn.disabled = false;
                if (errEl) { errEl.textContent = 'Request failed.'; errEl.classList.add('d-block'); }
                if (pinInput) pinInput.classList.add('is-invalid');
            });
        });
        $('#consumerEditPinInput').on('keydown', function(e) {
            if (e.which === 13) document.getElementById('consumerEditPinVerifyBtn').click();
        });

        // Save Previous Reading (Meter Reading card) - requires PIN first
        const meterReadingSavePrevBtn = document.getElementById('meterReadingSavePrevBtn');
        if (meterReadingSavePrevBtn) {
            meterReadingSavePrevBtn.addEventListener('click', function() {
                const scheduleId = document.getElementById('meterReadingScheduleId');
                const accountNo = document.getElementById('meterReadingAccountNo');
                const input = document.getElementById('meterReadingPrevValue');
                const statusEl = document.getElementById('meterReadingSaveStatus');
                if (!scheduleId || !accountNo || !input) return;
                const prevValue = parseInt(input.value, 10);
                if (isNaN(prevValue) || prevValue < 0) {
                    if (statusEl) { statusEl.textContent = 'Enter a valid number.'; statusEl.classList.add('text-danger'); }
                    return;
                }
                requestPinThenRun(function() {
                    meterReadingSavePrevBtn.disabled = true;
                    if (statusEl) { statusEl.textContent = 'Saving...'; statusEl.classList.remove('text-success', 'text-danger'); }
                    fetch('{{ route("consumer.update-meter-reading") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : ''
                        },
                        body: JSON.stringify({
                            schedule_id: parseInt(scheduleId.value, 10),
                            account_no: accountNo.value,
                            previous_reading: prevValue
                        })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        meterReadingSavePrevBtn.disabled = false;
                        if (data.success && statusEl) {
                            statusEl.textContent = 'Saved.';
                            statusEl.classList.remove('text-danger');
                            statusEl.classList.add('text-success');
                        } else if (statusEl) {
                            statusEl.textContent = data.message || 'Save failed.';
                            statusEl.classList.add('text-danger');
                        }
                    })
                    .catch(function(err) {
                        meterReadingSavePrevBtn.disabled = false;
                        if (statusEl) { statusEl.textContent = 'Request failed.'; statusEl.classList.add('text-danger'); }
                        console.error(err);
                    });
                });
            });
        }
        // Auto-generate Account Number based on Zone, Day, and Card Number
        const categoryCodeLookup = {
            'residential': '12',
            'government': '22',
            'commercialindustrialc': '32',
            'commercial/industrial c': '32',
            'commercial c': '32',
            'industrial': '32',
            'commerciala': '33',
            'commercial a': '33',
            'commercial': '33',
            'commercialb': '34',
            'commercial b': '34',
            'commerciald': '35',
            'commercial d': '35',
            'wholesale': '36',
            'wholesale/bulk': '36',
            'wholesale / bulk': '36',
            'wholesalebulk': '36',
            'whole sale/bulk': '36',
            'whole sale bulk': '36'
        };

        function getCategoryCode(categoryValue, selectedOption) {
            if (!categoryValue) {
                return null;
            }

            // Prefer data attribute if present
            const dataCode = selectedOption.data('code');
            if (dataCode) {
                return dataCode.toString();
            }

            // Fallback to normalized lookup
            const normalized = categoryValue
                .toString()
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '');

            return categoryCodeLookup[normalized] || null;
        }

        // Generate random cons_ctrl like _5GW0NGW59
        function generateConsCtrl() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let result = '_';
            for (let i = 0; i < 9; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        }

        // Auto-generate cons_ctrl when modal opens
        $('#newConsumerModal').on('shown.bs.modal show.bs.modal', function() {
            // Always generate new cons_ctrl when opening modal
            const consCtrl = generateConsCtrl();
            // Store it in a data attribute and display it
            $('#newConsumerModal').data('cons-ctrl', consCtrl);
            $('#display_cons_ctrl').val(consCtrl);
            console.log('Generated cons_ctrl:', consCtrl);
        });

        function generateAccountNumber() {
            const zone = $('#zone').val(); // Get exact zone value from dropdown
            const selectedCategory = $('#category option:selected');
            const categoryValue = selectedCategory.val() || '';
            const categoryCode = getCategoryCode(categoryValue, selectedCategory);
            const cardNumberRaw = $('#card_number').val() || '';
            const cardNumber = cardNumberRaw.toString().trim();
            const trimmedZone = zone ? zone.toString().trim() : '';
            
            console.log('=== Account Number Generation ===');
            console.log('Zone Value (exact):', trimmedZone);
            console.log('Category Value:', categoryValue);
            console.log('Category Code:', categoryCode);
            console.log('Card Number Raw:', cardNumberRaw);
            console.log('Card Number Trimmed:', cardNumber);
            
            if (trimmedZone && categoryCode && cardNumber) {
                // Ensure card number is 4 digits
                const formattedCardNumber = String(cardNumber).padStart(4, '0');
                
                // Generate account number: zone-category-cardNumber
                const accountNumber = `${trimmedZone}-${categoryCode}-${formattedCardNumber}`;
                
                console.log('Formatted Card:', formattedCardNumber);
                console.log('Final Account Number:', accountNumber);
                console.log('=================================');
                
                $('#account_number').val(accountNumber);
            } else {
                if (!trimmedZone) console.log('ERROR: Zone not selected');
                if (!categoryCode) console.log('ERROR: Category not selected');
                if (!cardNumber) console.log('ERROR: Card number not entered');
                $('#account_number').val('');
            }
        }

        function formatCurrency(value) {
            const amount = Number(value);
            if (!Number.isFinite(amount)) {
                return '₱ 0.00';
            }
            return '₱ ' + amount.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatBillMonth(dateString) {
            if (!dateString) {
                return '--';
            }
            const date = new Date(dateString);
            if (Number.isNaN(date.getTime())) {
                return '--';
            }
            const month = String(date.getMonth() + 1).padStart(2, '0');
            return `${month}-${date.getFullYear()}`;
        }

        function updateLatestBillCard(latestBill) {
            const headerText = latestBill ? `Latest Bill - ${formatBillMonth(latestBill.bill_month || latestBill.billMonth)}` : 'Latest Bill - --';
            $('#latestBillHeader').text(headerText);

            const currentBill = parseFloat(latestBill?.current_bill ?? latestBill?.currentBill ?? 0);
            const meterRental = parseFloat(latestBill?.meter_rental ?? latestBill?.meterRental ?? 0);
            const arrears = parseFloat(latestBill?.arrears ?? 0);
            const materials = parseFloat(latestBill?.materials ?? 0);
            const septageFee = parseFloat(latestBill?.septage_fee ?? latestBill?.septageFee ?? 0);
            const others = parseFloat(latestBill?.others ?? 0);
            
            // Compute total bill by adding all components
            const totalAmount = currentBill + meterRental + arrears + materials + septageFee + others;

            $('#latestBillCurrent').val(formatCurrency(currentBill));
            $('#latestBillMeterRental').val(formatCurrency(meterRental));
            $('#latestBillArrears').val(formatCurrency(arrears));
            $('#latestBillMaterials').val(formatCurrency(materials));
            $('#latestBillSeptage').val(formatCurrency(septageFee));
            $('#latestBillOthers').val(formatCurrency(others));
            $('#latestBillTotal').val(formatCurrency(totalAmount));

            if (latestBill) {
                sessionStorage.setItem('latestBill', JSON.stringify(latestBill));
            } else {
                sessionStorage.removeItem('latestBill');
            }
        }
        window.updateLatestBillCard = updateLatestBillCard;

        function updateMeterReadingCard(meterReading) {
            const fmtDate = function(d) {
                if (!d) return '—';
                try {
                    const dt = new Date(d);
                    return isNaN(dt.getTime()) ? '—' : (dt.getMonth() + 1).toString().padStart(2, '0') + '/' + dt.getDate().toString().padStart(2, '0') + '/' + dt.getFullYear();
                } catch (e) { return '—'; }
            };
            if (!meterReading) {
                $('#meterReadingCurrentDate').text('—');
                $('#meterReadingCurrentValue').text('—');
                $('#meterReadingPrevDate').text('—');
                $('#meterReadingPrevValue').val(0);
                $('#meterReadingSaveStatus').text('');
                $('.meter-reading-save-block').hide();
                return;
            }
            $('#meterReadingCurrentDate').text(fmtDate(meterReading.current_reading_date));
            $('#meterReadingCurrentValue').text(meterReading.current_reading != null && meterReading.current_reading !== '' ? meterReading.current_reading : '—');
            $('#meterReadingPrevDate').text(fmtDate(meterReading.previous_reading_date));
            $('#meterReadingPrevValue').val(meterReading.previous_reading != null && meterReading.previous_reading !== '' ? meterReading.previous_reading : 0);
            if (meterReading.schedule_id) {
                $('#meterReadingScheduleId').val(meterReading.schedule_id);
                $('#meterReadingAccountNo').val(meterReading.account_no || '');
                $('.meter-reading-save-block').show();
            } else {
                $('.meter-reading-save-block').hide();
            }
            $('#meterReadingSaveStatus').text('');
        }
        window.updateMeterReadingCard = updateMeterReadingCard;
        
        // Auto-generate Account Number for the EDIT modal when zone, category, or sequence changes
        function generateEditAccountNumber() {
            const zone = $('#edit_zone').val();
            const selectedCategory = $('#edit_category option:selected');
            const categoryValue = selectedCategory.val() || '';
            const categoryCode = getCategoryCode(categoryValue, selectedCategory);
            const cardNumberRaw = $('#edit_card_number').val() || '';
            const cardNumber = cardNumberRaw.toString().trim();
            const trimmedZone = zone ? zone.toString().trim() : '';

            if (trimmedZone && categoryCode && cardNumber) {
                const formattedCardNumber = String(cardNumber).padStart(4, '0');
                const accountNumber = `${trimmedZone}-${categoryCode}-${formattedCardNumber}`;
                $('#edit_account_number').val(accountNumber);
            } else {
                $('#edit_account_number').val('');
            }
        }

        // Trigger account number generation when zone, category, or card number changes (New Consumer)
        $('#zone, #category, #card_number').on('change keyup input', function() {
            console.log('Zone, Category, or Card Number changed');
            generateAccountNumber();
        });

        // Trigger account number re-generation when zone, category, or sequence changes (Edit Consumer)
        $(document).on('change keyup input', '#edit_zone, #edit_category, #edit_card_number', function() {
            generateEditAccountNumber();
        });

        // Run once in case fields already have values (e.g., form repopulation)
        generateAccountNumber();
        if (initialLatestBill) {
            updateLatestBillCard(initialLatestBill);
        } else {
            const storedLatestBill = sessionStorage.getItem('latestBill');
            if (storedLatestBill) {
                try {
                    updateLatestBillCard(JSON.parse(storedLatestBill));
                } catch (error) {
                    console.error('Failed to parse stored latest bill:', error);
                    updateLatestBillCard(null);
                }
            } else {
                updateLatestBillCard(null);
            }
        }
        
        // Handle form submission
        $('#newConsumerForm').on('submit', function(e) {
            e.preventDefault();
            console.log('Form submitted');
            
            // Clear previous errors
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').text('');
            
            // Disable submit button
            $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Saving...');
            
            // Prepare data for consumer_zone table
            const consCtrl = $('#newConsumerModal').data('cons-ctrl') || generateConsCtrl();
            const formData = {
                _token: $('input[name="_token"]').val(),
                install_date: $('#installation_date').val(),
                account_no: $('#account_number').val(),
                account_name: buildAccountName($('#first_name').val(), $('#middle_name').val(), $('#last_name').val(), $('#extension').val()),
                address1: $('#address').val(),
                zone_code: $('#zone').val(),
                category_code: $('#category option:selected').data('code'),
                meter_number: $('#meter_number').val(),
                meter_brand: $('#meter_brand').val(),
                rate_code: $('#rate_code').val(),
                status_code: $('#status').val(),
                sequence: $('#card_number').val(),
                cons_ctrl: consCtrl, // Auto-generated control number
                balance: 0.00,
                consumer_deposit: 0.00,
                installation_fee: 0.00,
                installation_balance: 0.00
            };
            
            console.log('Form data prepared:', formData);
            
            $.ajax({
                url: '{{ route("consumer.store") }}',
                method: 'POST',
                data: formData,
                success: function(response) {
                    console.log('Success:', response);
                    if (response.success) {
                        // Close modal
                        $('#newConsumerModal').modal('hide');
                        
                        // Reset form
                        $('#newConsumerForm')[0].reset();
                        
                        // Show success message with SweetAlert
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            confirmButtonColor: '#28a745',
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            // Reload page to show new consumer
                            location.reload();
                        });
                    }
                },
                error: function(xhr) {
                    console.log('Error:', xhr);
                    if (xhr.status === 422) {
                        // Validation errors
                        const errors = xhr.responseJSON.errors;
                        
                        $.each(errors, function(field, messages) {
                            const input = $('[name="' + field + '"]');
                            input.addClass('is-invalid');
                            input.siblings('.invalid-feedback').text(messages[0]);
                        });
                        
                        // Show validation error alert
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please check the form fields and try again.',
                            confirmButtonColor: '#dc3545'
                        });
                    } else {
                        // Other errors
                        const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to create consumer';
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: message,
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                complete: function() {
                    // Re-enable submit button
                    $('#submitBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save Consumer');
                }
            });
        });
        
        // Handle Cancel button click
        $('#cancelBtn').on('click', function() {
            console.log('Cancel button clicked - Resetting form');
            resetForm();
        });

        // Handle Close (X) button click
        $('#modalCloseBtn').on('click', function() {
            console.log('Close X button clicked - Resetting form');
            resetForm();
        });

        // Reset form when modal is closed (works with both Bootstrap 4 and 5)
        $('#newConsumerModal').on('hidden.bs.modal hidden', function() {
            console.log('Modal closed - Resetting form');
            resetForm();
        });

        // Function to reset the form completely
        function resetForm() {
            // Reset all form fields
            $('#newConsumerForm')[0].reset();
            
            // Clear validation errors
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').text('');
            
            // Clear auto-generated fields
            $('#account_number').val('');
            $('#display_cons_ctrl').val('');
            
            // Clear stored cons_ctrl
            $('#newConsumerModal').removeData('cons-ctrl');
            
            console.log('Form reset complete');
        }

        // Override searchConsumer function for main-consumer page to update consumer info card
        // The shared-header-script provides the base search functionality
        // This page-specific override handles updating the consumer info display
        $(document).ready(function() {
            // Wait for shared script to load, then override
            setTimeout(function() {
                if (typeof window.searchConsumer === 'function') {
                    const originalSearchConsumer = window.searchConsumer;
                    window.searchConsumer = function(searchTerm) {
                        if (!searchTerm || searchTerm.length < 2) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Invalid Search',
                                text: 'Please enter at least 2 characters',
                                confirmButtonColor: '#ffc107'
                            });
                            return;
                        }

                        // Hide suggestions when searching
                        $('#consumerSuggestions').hide();
                        if (typeof selectedIndex !== 'undefined') {
                            selectedIndex = -1;
                        }

                        // Show loading state
                        $('#consumerInfoCard').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x text-primary"></i><p class="mt-3">Searching...</p></div>');

                        $.ajax({
                            url: '{{ route("consumer.search") }}',
                            method: 'GET',
                            data: { search: searchTerm },
                            success: function(response) {
                                console.log('Search success:', response);
                                if (response.success && response.consumer) {
                                    // Store consumer in sessionStorage
                                    sessionStorage.setItem('currentConsumer', JSON.stringify(response.consumer));
                                    
                                    // Update header
                                    if (typeof updateConsumerHeader === 'function') {
                                        updateConsumerHeader(response.consumer);
                                    }
                                    
                                    // Update consumer info card (page-specific)
                                    updateConsumerInfo(response.consumer);
                                    updateLatestBillCard(response.latest_bill || null);
                                    updateMeterReadingCard(response.meter_reading || null);
                                    if (response.consumer && response.consumer.account_no) {
                                        $('#meterReadingAccountNo').val(response.consumer.account_no);
                                    }
                                    
                                    // Enable Edit and Delete buttons
                                    $('#editConsumerBtn').prop('disabled', false);
                                    $('#deleteConsumerBtn').prop('disabled', false);
                                    
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Consumer Found!',
                                        text: 'Consumer information loaded successfully',
                                        timer: 1500,
                                        showConfirmButton: false
                                    });
                                }
                            },
                            error: function(xhr) {
                                console.log('Search error:', xhr);
                                let message = 'No consumer found matching your search';
                                
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    message = xhr.responseJSON.message;
                                }

                                $('#consumerInfoCard').html('<div class="text-center text-muted py-5"><i class="fas fa-user-slash fa-3x mb-3"></i><p>' + message + '</p></div>');
                                
                                Swal.fire({
                                    icon: 'info',
                                    title: 'Not Found',
                                    text: message,
                                    confirmButtonColor: '#17a2b8'
                                });
                            }
                        });
                    };
                }
            }, 100);
        });

        function updateConsumerInfo(consumer) {
            // Track current consumer for Edit/Delete
            currentConsumer = consumer;
            
            // Enable Edit and Delete buttons
            $('#editConsumerBtn').prop('disabled', false);
            $('#deleteConsumerBtn').prop('disabled', false);
            
            // Format the date - use install_date from consumer_zone table
            let formattedDate = '';
            if (consumer.install_date) {
                let installDate = new Date(consumer.install_date);
                formattedDate = (installDate.getMonth() + 1) + '/' + installDate.getDate() + '/' + installDate.getFullYear();
            }

            // Update the header dynamically
            updateConsumerHeader(consumer);

            // Format balance currency
            const balance = consumer.balance || 0;
            const formattedBalance = '₱ ' + parseFloat(balance).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            // Update the consumer information card with the new data from consumer_zone table
            const html = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Installation Date</label>
                            <input type="text" class="form-control form-control-sm" id="installationDate"
                                value="${formattedDate}" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Number</label>
                            <input type="text" class="form-control form-control-sm" id="accountNumber"
                                value="${consumer.account_no || ''}" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meter Number</label>
                            <input type="text" class="form-control form-control-sm" id="meterNumber"
                                value="${consumer.meter_number || ''}" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control form-control-sm" id="address"
                                value="${consumer.address1 || ''}" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control form-control-sm" id="displayCategory"
                                value="${consumer.category_code || ''}" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <input type="text" class="form-control form-control-sm" id="status"
                                value="${consumer.status_label || consumer.status_code || ''}" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Name</label>
                            <input type="text" class="form-control form-control-sm" id="accountName"
                                value="${consumer.account_name || ''}" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meter Brand</label>
                            <input type="text" class="form-control form-control-sm" id="meterBrand"
                                value="${consumer.meter_brand || ''}" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Zone</label>
                            <input type="text" class="form-control form-control-sm" id="displayZone"
                                value="${consumer.zone_code || ''}" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sequence</label>
                            <input type="text" class="form-control form-control-sm" id="sequence"
                                value="${consumer.sequence || ''}" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rate Code</label>
                            <input type="text" class="form-control form-control-sm" id="rateCode"
                                value="${consumer.rate_code || ''}" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Control Number</label>
                            <input type="text" class="form-control form-control-sm" id="displayCardNumber"
                                value="${consumer.cons_ctrl || ''}" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Balance</label>
                            <input type="text" class="form-control form-control-sm" id="balance"
                                value="${formattedBalance}" readonly>
                        </div>
                    </div>
                </div>
            `;

            $('#consumerInfoCard').html(html);
        }
        window.updateConsumerInfo = updateConsumerInfo;

        // Update consumer header dynamically
        function updateConsumerHeader(consumer) {
            // Use account_name from consumer_zone table
            let fullName = consumer.account_name || '';

            // Update header name - use account_no from consumer_zone table
            const headerName = document.getElementById('consumerHeaderName');
            if (headerName) {
                headerName.textContent = (consumer.account_no || '') + ' ' + fullName;
            }

            // Update header status badge - use status_label or status_code
            const headerStatus = document.getElementById('consumerHeaderStatus');
            if (headerStatus) {
                const statusText = consumer.status_label || consumer.status_code || 'N/A';
                headerStatus.textContent = statusText + ' Consumer';
                
                // Update badge color based on status
                headerStatus.className = 'badge';
                const statusUpper = (statusText || '').toUpperCase();
                if (statusUpper === 'ACTIVE' || statusUpper === 'A') {
                    headerStatus.classList.add('bg-success');
                } else if (statusUpper === 'INACTIVE' || statusUpper === 'I') {
                    headerStatus.classList.add('bg-warning');
                } else if (statusUpper === 'DISCONNECTED' || statusUpper === 'D') {
                    headerStatus.classList.add('bg-danger');
                } else if (statusUpper === 'SUSPENDED' || statusUpper === 'S') {
                    headerStatus.classList.add('bg-warning');
                } else {
                    headerStatus.classList.add('bg-secondary');
                }
            }

            // Store consumer in sessionStorage for other tabs
            sessionStorage.setItem('currentConsumer', JSON.stringify(consumer));

            console.log('Header updated:', fullName);
            console.log('Consumer saved to sessionStorage');
        }

        // Load consumer from sessionStorage on page load
        $(document).ready(function() {
            const storedConsumer = sessionStorage.getItem('currentConsumer');
            if (storedConsumer) {
                try {
                    const consumer = JSON.parse(storedConsumer);
                    updateConsumerHeader(consumer);
                    console.log('Loaded consumer from sessionStorage:', consumer.account_no || consumer.account_number);
                } catch (e) {
                    console.error('Error loading consumer from storage:', e);
                }
            }
        });

        // Helper function to parse account name into parts
        function parseAccountName(accountName) {
            if (!accountName) {
                return { lastName: '', firstName: '', middleName: '', extension: '' };
            }
            
            // Format: "LASTNAME, FIRSTNAME M." or "LASTNAME, FIRSTNAME MIDDLENAME EXT"
            const parts = accountName.split(',');
            let lastName = parts[0] ? parts[0].trim() : '';
            let firstName = '';
            let middleName = '';
            let extension = '';
            
            if (parts[1]) {
                const nameParts = parts[1].trim().split(/\s+/);
                firstName = nameParts[0] || '';
                
                // Check for middle initial (ends with .)
                if (nameParts.length > 1) {
                    if (nameParts[1].endsWith('.')) {
                        middleName = nameParts[1].replace('.', '');
                    } else {
                        middleName = nameParts[1];
                    }
                }
                
                // Check for extension (Jr., Sr., II, III, IV)
                if (nameParts.length > 2) {
                    const possibleExt = nameParts[nameParts.length - 1];
                    if (['JR.', 'SR.', 'II', 'III', 'IV', 'JR', 'SR'].includes(possibleExt.toUpperCase())) {
                        extension = possibleExt;
                    }
                }
            }
            
            return { lastName, firstName, middleName, extension };
        }

        // Edit Consumer Button - requires PIN first
        $('#editConsumerBtn').on('click', function() {
            if (!currentConsumer) {
                const storedConsumer = sessionStorage.getItem('currentConsumer');
                if (storedConsumer) {
                    currentConsumer = JSON.parse(storedConsumer);
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No Consumer Selected',
                        text: 'Please search for a consumer first',
                        confirmButtonColor: '#ffc107'
                    });
                    return;
                }
            }

            requestPinThenRun(function() {
                // Populate edit modal with current consumer data from consumer_zone table
                $('#edit_consumer_id').val(currentConsumer.id);
                
                if (currentConsumer.install_date) {
                    const date = new Date(currentConsumer.install_date);
                    const formattedDate = date.toISOString().split('T')[0];
                    $('#edit_installation_date').val(formattedDate);
                }
                
                $('#edit_account_number').val(currentConsumer.account_no || '');
                $('#edit_meter_number').val(currentConsumer.meter_number || '');
                $('#edit_address').val(currentConsumer.address1 || '');
                
                const categoryCode = currentConsumer.category_code;
                if (categoryCode) {
                    $('#edit_category option').each(function() {
                        if ($(this).data('code') == categoryCode) {
                            $(this).prop('selected', true);
                            return false;
                        }
                    });
                }
                
                $('#edit_status').val(currentConsumer.status_code || 'A');
                $('#edit_rate_code').val(currentConsumer.rate_code || 'A');
                
                const nameParts = parseAccountName(currentConsumer.account_name);
                $('#edit_first_name').val(nameParts.firstName);
                $('#edit_last_name').val(nameParts.lastName);
                $('#edit_middle_name').val(nameParts.middleName);
                $('#edit_extension').val(nameParts.extension);
                
                $('#edit_contact_number').val('');
                $('#edit_meter_brand').val(currentConsumer.meter_brand || '');
                $('#edit_zone').val(currentConsumer.zone_code || '');
                $('#edit_card_number').val(currentConsumer.sequence || '');
                $('#edit_cons_ctrl').val(currentConsumer.cons_ctrl || '');

                console.log('Edit modal populated with consumer:', currentConsumer.account_no);
                $('#editConsumerModal').modal('show');
            });
        });

        // Helper function to build account name
        function buildAccountName(firstName, middleName, lastName, extension) {
            let name = '';
            if (lastName) name += lastName.toUpperCase();
            if (firstName) name += (name ? ', ' : '') + firstName.toUpperCase();
            if (middleName) name += (name && firstName ? ' ' : '') + middleName.charAt(0).toUpperCase() + '.';
            if (extension) name += (name ? ' ' : '') + extension;
            return name.trim();
        }

        // Handle Edit Consumer Form Submission
        $('#editConsumerForm').on('submit', function(e) {
            e.preventDefault();
            console.log('Edit form submitted');
            
            const consumerId = $('#edit_consumer_id').val();
            if (!consumerId) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Consumer ID is missing',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }

            // Clear previous errors
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').text('');
            
            // Disable submit button
            $('#updateBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Updating...');
            
            // Prepare data for consumer_zone table
            const updateData = {
                _token: $('input[name="_token"]').val(),
                _method: 'PUT',
                install_date: $('#edit_installation_date').val(),
                account_no: $('#edit_account_number').val(),
                account_name: buildAccountName($('#edit_first_name').val(), $('#edit_middle_name').val(), $('#edit_last_name').val(), $('#edit_extension').val()),
                address1: $('#edit_address').val(),
                zone_code: $('#edit_zone').val(),
                category_code: $('#edit_category option:selected').data('code') || $('#edit_category').val(),
                meter_number: $('#edit_meter_number').val(),
                meter_brand: $('#edit_meter_brand').val(),
                rate_code: $('#edit_rate_code').val() || 'A',
                status_code: $('#edit_status').val(),
                sequence: $('#edit_card_number').val()
                // Note: cons_ctrl is not updated during edit to preserve the original value
            };
            
            console.log('Update data prepared:', updateData);
            
            $.ajax({
                url: '{{ route("consumer.update", ":id") }}'.replace(':id', consumerId),
                method: 'POST',
                data: updateData,
                success: function(response) {
                    console.log('Update success:', response);
                    if (response.success) {
                        // Close modal
                        $('#editConsumerModal').modal('hide');
                        
                        // Update current consumer
                        currentConsumer = response.consumer;
                        
                        // Update header
                        updateConsumerHeader(response.consumer);
                        
                        // Refresh the consumer info display - use account_no from consumer_zone table
                        searchConsumer(response.consumer.account_no || response.consumer.account_number);
                        
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            confirmButtonColor: '#28a745',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr) {
                    console.log('Update error:', xhr);
                    if (xhr.status === 422) {
                        // Validation errors
                        const errors = xhr.responseJSON.errors;
                        
                        $.each(errors, function(field, messages) {
                            const input = $('#edit_' + field);
                            if (input.length) {
                                input.addClass('is-invalid');
                                input.siblings('.invalid-feedback').text(messages[0]);
                            }
                        });
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please check the form fields and try again.',
                            confirmButtonColor: '#dc3545'
                        });
                    } else {
                        const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to update consumer';
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: message,
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                complete: function() {
                    $('#updateBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Update Consumer');
                }
            });
        });

        // Delete Consumer Button - requires PIN first
        $('#deleteConsumerBtn').on('click', function() {
            if (!currentConsumer) {
                const storedConsumer = sessionStorage.getItem('currentConsumer');
                if (storedConsumer) {
                    currentConsumer = JSON.parse(storedConsumer);
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No Consumer Selected',
                        text: 'Please search for a consumer first',
                        confirmButtonColor: '#ffc107'
                    });
                    return;
                }
            }

            requestPinThenRun(function() {
                const accountNo = currentConsumer.account_no || '';
                const accountName = currentConsumer.account_name || '';
                Swal.fire({
                    icon: 'warning',
                    title: 'Are you sure?',
                    html: `You are about to delete:<br><br><strong>${accountNo} - ${accountName}</strong><br><br>This action cannot be undone!`,
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-trash me-1"></i>Yes, Delete!',
                    cancelButtonText: '<i class="fas fa-times me-1"></i>Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        deleteConsumer(currentConsumer.id);
                    }
                });
            });
        });

        // Delete consumer function
        function deleteConsumer(consumerId) {
            $.ajax({
                url: '{{ route("consumer.destroy", ":id") }}'.replace(':id', consumerId),
                method: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    console.log('Delete success:', response);
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: response.message,
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            // Clear consumer data
                            currentConsumer = null;
                            sessionStorage.removeItem('currentConsumer');
                            
                            // Disable Edit and Delete buttons
                            $('#editConsumerBtn').prop('disabled', true);
                            $('#deleteConsumerBtn').prop('disabled', true);
                            
                            // Clear the display
                            $('#consumerInfoCard').html('<div class="text-center text-muted py-5"><i class="fas fa-user-slash fa-3x mb-3"></i><p>Consumer deleted. Search for another consumer.</p></div>');
                            
                            // Update header to show no consumer
                            $('#consumerHeaderName').text('No Consumer Selected');
                            $('#consumerHeaderStatus').removeClass().addClass('badge bg-secondary').text('Please search for a consumer');
                        });
                    }
                },
                error: function(xhr) {
                    console.log('Delete error:', xhr);
                    const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to delete consumer';
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: message,
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        }
    });
</script>

</html>
