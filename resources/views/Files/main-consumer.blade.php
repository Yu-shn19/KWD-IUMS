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
                @include('consumer.nav-tabs', ['activeTab' => 'consumer'])

                <!-- Simple Action Buttons -->
                <div class="bg-white px-2 px-md-3 py-2 border-bottom mb-3">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center">
                        <!-- Navigation Controls -->
                        <div class="d-flex align-items-center justify-content-center justify-content-md-start flex-wrap mb-2 mb-md-0">
                            <button type="button" class="btn btn-outline-secondary btn-sm mr-1 mb-1">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm mr-1 mb-1">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm mr-1 mb-1">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm mr-1 mb-1">
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex align-items-center justify-content-center justify-content-md-end flex-wrap">
                            <button type="button" class="btn btn-success btn-sm mr-1 mb-1" data-toggle="modal" data-target="#newConsumerModal" data-bs-toggle="modal" data-bs-target="#newConsumerModal">
                                <i class="fas fa-plus me-1"></i>New
                            </button>
                            <button type="button" class="btn btn-primary btn-sm mr-1 mb-1" id="editConsumerBtn" disabled>
                                <i class="fas fa-edit me-1"></i>Edit
                            </button>
                            <button type="button" class="btn btn-danger btn-sm mb-1" id="deleteConsumerBtn" disabled>
                                <i class="fas fa-trash me-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="p-2 p-md-3 bg-light main-consumer-content">
                    <style>
                        .main-consumer-content { font-size: 1.05rem; }
                        @media (max-width: 575.98px) {
                            .main-consumer-content { font-size: 1rem; }
                        }
                        .main-consumer-content .card-header h6 { font-size: 1.125rem; }
                        .main-consumer-content .table { font-size: 1rem; }
                        .main-consumer-content .table thead th {
                            font-size: 0.95rem;
                            color: #0b5ed7;
                            font-weight: 600;
                        }
                        .main-consumer-content .list-group-item { font-size: 1rem; }
                        .main-consumer-content .list-group-item .text-muted { color: #2563b8 !important; }
                        .main-consumer-content .list-group-item strong { font-size: 1.05rem; }
                        .main-consumer-content .card-footer { font-size: 1.05rem; }
                        .main-consumer-content .card-footer .fs-6 { font-size: 1.15rem !important; }
                        .main-consumer-content .card.bg-light .card-body { font-size: 1rem; }
                        .main-consumer-content .card.bg-light h6 { font-size: 1.05rem; }
                        .main-consumer-content .card.bg-light .fw-bold { font-size: 1.1rem; }
                        .main-consumer-content .card.bg-light small.text-muted {
                            font-size: 0.9rem;
                            color: #2563b8 !important;
                            font-weight: 600;
                        }
                        .main-consumer-content .form-label { color: #0b5ed7; }

                        .consumer-info-card { border-radius: 0.5rem; overflow: hidden; }
                        .consumer-info-card .card-header { border-bottom: 0; }
                        .consumer-info-card .card-header h6 { font-size: 1.2rem; }
                        .consumer-info-card .consumer-info-body { background: linear-gradient(165deg, #e8f1fb 0%, #eef4f9 45%, #f5f7fb 100%); }
                        .consumer-info-card .consumer-info-panel {
                            background-color: #fff;
                            border-radius: 0.5rem;
                            border: 1px solid rgba(13, 110, 253, 0.12);
                            box-shadow: 0 0.125rem 0.35rem rgba(15, 60, 120, 0.06);
                            padding: 1.15rem 1.25rem;
                            height: 100%;
                        }
                        .consumer-info-card .consumer-info-panel .form-label {
                            font-size: 0.9rem;
                            text-transform: uppercase;
                            letter-spacing: 0.02em;
                            color: #0b5ed7;
                            margin-bottom: 0.35rem;
                            font-weight: 600;
                        }
                        .consumer-info-card .form-control.form-control-sm,
                        .consumer-info-card textarea.form-control-sm {
                            font-size: 1.05rem;
                            line-height: 1.45;
                            padding: 0.5rem 0.75rem;
                            min-height: auto;
                        }
                        .consumer-info-card textarea.form-control-sm { min-height: 4.5rem; }
                        .consumer-info-card .consumer-info-remark {
                            background-color: #fff;
                            border-radius: 0.5rem;
                            border: 1px solid rgba(13, 110, 253, 0.12);
                            border-left: 4px solid #0d6efd;
                            box-shadow: 0 0.125rem 0.35rem rgba(15, 60, 120, 0.06);
                            padding: 1.15rem 1.25rem;
                        }
                        .consumer-info-card .consumer-info-remark .form-label {
                            font-size: 0.95rem;
                            color: #0b5ed7;
                        }
                        .consumer-info-card .consumer-info-remark .form-label .text-primary { color: #0b5ed7 !important; }
                        .consumer-info-card .consumer-info-empty { min-height: 200px; font-size: 1.05rem; }
                    </style>
                    <div class="row g-3">
                        <!-- Left Column -->
                        <div class="col-md-12">
                            <!-- Information Card -->
                            <div class="card mb-3 consumer-info-card shadow-sm border-0">
                                <div class="card-header bg-primary text-white py-3 d-flex align-items-center">
                                    <i class="fas fa-user-circle me-2 opacity-75"></i>
                                    <h6 class="mb-0 fw-semibold">Consumer Information</h6>
                                </div>
                                <div class="card-body p-0 consumer-info-body" id="consumerInfoCard">
                                    @if($consumer)
                                    <div class="p-3">
                                    <div class="row g-3 mb-2">
                                        <div class="col-12">
                                            <label class="form-label fw-semibold text-primary mb-1">
                                                <i class="fas fa-calendar-check me-1"></i>Transaction Date
                                            </label>
                                            <input type="text" class="form-control form-control-sm" id="transactionDateDisplay"
                                                value="{{ $consumer->transaction_date ? $consumer->transaction_date->format('m/d/Y') : '' }}" readonly>
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="consumer-info-panel">
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
                                            @php
                                                $billDiscRaw = trim((string) ($consumer->bill_disc_percent ?? ''));
                                                $billDiscIsSc = strtoupper($billDiscRaw) === 'SC DISCOUNT'
                                                    || (is_numeric($billDiscRaw) && abs(((float) $billDiscRaw) - 5.0) < 0.001);
                                                $oscaIdRaw = trim((string) ($consumer->osca_id_no ?? ''));
                                                $oscaDateDisplay = $consumer->bill_disc_updated_at
                                                    ? $consumer->bill_disc_updated_at->format('F j, Y')
                                                    : null;
                                                $oscaDisplay = $oscaIdRaw !== ''
                                                    ? ($oscaDateDisplay ? $oscaIdRaw . ' - ' . $oscaDateDisplay : $oscaIdRaw)
                                                    : '';
                                            @endphp
                                            @if($billDiscIsSc)
                                            <div class="mb-3 mb-md-0">
                                                <label class="form-label">OSCA ID #</label>
                                                <input type="text" class="form-control form-control-sm" id="oscaIdNumber"
                                                    value="{{ $oscaDisplay }}">
                                            </div>
                                            @endif
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="consumer-info-panel">
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <input type="text" class="form-control form-control-sm" id="consumerStatusDisplay"
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
                                            <div class="mb-0">
                                                <label class="form-label">Balance</label>
                                                <input type="text" class="form-control form-control-sm" id="balance"
                                                    value="₱ {{ number_format($consumer->balance ?? 0, 2) }}" readonly>
                                            </div>
                                            </div>
                                        </div>
                                    </div>
                                    </div>
                                    @else
                                    <div class="text-center text-muted py-5 px-3 consumer-info-empty d-flex flex-column align-items-center justify-content-center">
                                        <i class="fas fa-user-slash fa-3x mb-3 opacity-50"></i>
                                        <p class="mb-0">No consumer selected. Use the search box to find a consumer.</p>
                                    </div>
                                    @endif
                                </div>  
                            </div>

                            <div class="row g-3 mb-3">
                               

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

                                <div class="col-lg-6">
                                    <div class="card shadow-sm border-0 h-100">
                                        <div class="card-header bg-primary text-white py-3">
                                            <h6 class="mb-0 fw-semibold" id="latestBillHeader">Latest Bill - {{ $latestBillMonthLabel }}</h6>
                                            <small class="d-block opacity-75 mt-1">Billing breakdown summary</small>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="list-group list-group-flush">
                                                <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                                    <span class="text-muted">Current Bill</span>
                                                    <strong id="latestBillCurrent">{{ $formatLatestCurrency($latestBillCurrentBill) }}</strong>
                                                </div>
                                                <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                                    <span class="text-muted">Water Maintenance Charge</span>
                                                    <strong id="latestBillMeterRental">{{ $formatLatestCurrency($latestBillMeterRental) }}</strong>
                                                </div>
                                                <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                                    <span class="text-muted">Arrears</span>
                                                    <strong id="latestBillArrears">{{ $formatLatestCurrency($latestBillArrears) }}</strong>
                                                </div>
                                                <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                                    <span class="text-muted">Materials</span>
                                                    <strong id="latestBillMaterials">{{ $formatLatestCurrency($latestBillMaterials) }}</strong>
                                                </div>
                                                <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                                    <span class="text-muted">Septage Fee</span>
                                                    <strong id="latestBillSeptage">{{ $formatLatestCurrency($latestBillSeptage) }}</strong>
                                                </div>
                                                <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                                    <span class="text-muted">Others</span>
                                                    <strong id="latestBillOthers">{{ $formatLatestCurrency($latestBillOthers) }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-light border-top py-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-bold text-primary mb-0">TOTAL BILL</span>
                                                <span class="fw-bold text-primary fs-6" id="latestBillTotal">{{ $formatLatestCurrency($latestBillTotal) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Meter Reading Card -->
                                <div class="col-lg-6">
                                    <div class="card shadow-sm border-0 h-100">
                                        <div class="card-header bg-primary text-white py-3">
                                            <h6 class="mb-0 fw-semibold">Meter Reading</h6>
                                            <small class="d-block opacity-75 mt-1">Current and previous reading</small>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="card bg-light h-100">
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
                                                    <div class="card bg-light h-100">
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

                                            @php
                                                $baseReading = $meterReading->base_reading ?? null;
                                                $baseReadingDate = $meterReading->base_reading_date ?? null;
                                                $hasReadingHistory = $meterReading && (
                                                    !empty($meterReading->schedule_id)
                                                    || !empty($meterReading->previous_reading_date)
                                                    || !empty($meterReading->current_reading_date)
                                                    || ($meterReading->current_reading !== null && $meterReading->current_reading !== '')
                                                    || ($meterReading->previous_reading !== null && $meterReading->previous_reading !== '' && (int) $meterReading->previous_reading > 0)
                                                );
                                                $baseDisabled = $hasReadingHistory ? 'disabled' : '';
                                            @endphp

                                            @if($consumer)
                                            <div class="card shadow-sm border mt-3 overflow-hidden {{ $hasReadingHistory ? 'base-reading-locked' : '' }}" id="meterReadingBaseBlock" data-has-history="{{ $hasReadingHistory ? '1' : '0' }}" style="border-left: 4px solid #0d6efd !important;">
                                                <div class="card-body p-3 bg-white">
                                                    <div class="row g-3 align-items-start">
                                                        <div class="col-lg-7">
                                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                                <span class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 2rem; height: 2rem;">
                                                                    <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
                                                                </span>
                                                                <div>
                                                                    <h6 class="mb-0 fw-semibold text-dark">Base reading
                                                                        <span class="badge bg-secondary ms-2 align-middle" id="meterReadingBaseLockedBadge" style="{{ $hasReadingHistory ? '' : 'display:none;' }}">
                                                                            <i class="fas fa-lock me-1"></i>Locked
                                                                        </span>
                                                                    </h6>
                                                                    <span class="small text-secondary">Starting meter value for first billing</span>
                                                                </div>
                                                            </div>
                                                            <p class="small text-dark mb-0 lh-base" style="max-width: 42rem;">
                                                                Use this when the account is new but the meter is <strong>not</strong> at zero.
                                                                The value becomes the <strong>previous reading</strong> on the first Meter Reading Preparation when there is no history yet.
                                                            </p>
                                                            <div class="alert alert-info py-2 px-3 small mb-0 mt-2" role="status" id="meterReadingBaseHistoryAlert" style="{{ $hasReadingHistory ? '' : 'display:none;' }}">
                                                                <i class="fas fa-info-circle me-1"></i>This consumer already has reading history — base reading is locked to prevent conflicts with existing billing data.
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-5">
                                                            <div class="rounded-3 border bg-light p-3 text-center h-100 d-flex flex-column justify-content-center">
                                                                <span class="text-uppercase small fw-semibold text-secondary" style="letter-spacing: .06em;">Saved base</span>
                                                                <span class="display-6 fw-bold text-primary my-1 lh-1" id="meterReadingBaseSaved">{{ $baseReading !== null ? $baseReading : '—' }}</span>
                                                                <span class="small text-secondary" id="meterReadingBaseSavedDate">
                                                                    @if($baseReadingDate)
                                                                        {{ \Carbon\Carbon::parse($baseReadingDate)->format('m/d/Y') }}
                                                                    @else
                                                                        No date set
                                                                    @endif
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <hr class="my-3 text-secondary opacity-25">
                                                    <div class="row g-3 align-items-end">
                                                        <div class="col-sm-4 col-md-3">
                                                            <label class="form-label small fw-semibold text-dark mb-1" for="meterReadingBaseValue">Base reading</label>
                                                            <input type="number" min="0" step="1" class="form-control fw-bold" id="meterReadingBaseValue" value="{{ $baseReading !== null ? $baseReading : '' }}" placeholder="e.g. 1500" inputmode="numeric" {{ $baseDisabled }}>
                                                        </div>
                                                        <div class="col-sm-4 col-md-3">
                                                            <label class="form-label small fw-semibold text-dark mb-1" for="meterReadingBaseDate">As of date</label>
                                                            <input type="date" class="form-control" id="meterReadingBaseDate" value="{{ $baseReadingDate ?? '' }}" {{ $baseDisabled }}>
                                                        </div>
                                                        <div class="col-sm-4 col-md-6 d-flex flex-wrap align-items-end gap-2">
                                                            <button type="button" class="btn btn-primary" id="meterReadingSaveBaseBtn" {{ $baseDisabled }}>
                                                                <i class="fas fa-save me-1"></i>Save base reading
                                                            </button>
                                                            <span class="small fw-medium" id="meterReadingBaseStatus"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
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
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="edit_transaction_date" class="form-label fw-semibold">
                                        <i class="fas fa-calendar-check me-1 text-primary"></i>Transaction Date
                                    </label>
                                    <input type="date" class="form-control" id="edit_transaction_date" name="transaction_date">
                                    <small class="text-muted">Date this consumer transaction was filed.</small>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_installation_date" class="form-label">Installation Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="edit_installation_date" name="installation_date" >
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_account_no" class="form-label">Account Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_account_no" name="account_no" readonly required>
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
                                        <option value="Commercial A" data-code="33" disabled>Commercial A</option>
                                        <option value="Commercial B" data-code="34" disabled>Commercial B</option>
                                        <option value="Commercial D" data-code="35">Commercial D</option>
                                        <option value="Whole Sale/Bulk" data-code="36" disabled>Whole Sale/Bulk</option>
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
                                    <label for="edit_account_name" class="form-label">Account name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_account_name" name="account_name" placeholder="e.g. DELA CRUZ, JUAN M." required>
                                    <div class="invalid-feedback"></div>
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
                            <div class="col-12">
                                <hr class="border-primary my-3" style="opacity: 0.25;">
                                <h6 class="text-primary fw-semibold mb-3"><i class="fas fa-percentage me-2"></i>Billing &amp; OSCA</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="edit_bill_disc_percent" class="form-label">Bill Disc (%)</label>
                                            <select class="form-control" id="edit_bill_disc_percent" name="bill_disc_percent">
                                                <option value="">None</option>
                                                <option value="SC DISCOUNT">SC DISCOUNT</option>
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4" id="edit_osca_wrap">
                                        <div class="mb-3">
                                            <label for="edit_osca_id_no" class="form-label">OSCA ID #</label>
                                            <input type="text" class="form-control" id="edit_osca_id_no" name="osca_id_no" maxlength="100" placeholder="OSCA ID">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="row align-items-end">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label small text-muted mb-1">Bill discount last updated</label>
                                                <p class="form-control-plaintext border rounded px-3 py-2 mb-0 bg-light small" id="edit_bill_disc_last_updated_display">—</p>
                                            </div>
                                            <div class="col-md-6 mb-3" id="edit_bill_disc_updated_at_wrap" style="display: none;">
                                                <label for="edit_bill_disc_updated_at" class="form-label">Date of bill discount change <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="edit_bill_disc_updated_at" name="bill_disc_updated_at" autocomplete="off">
                                                <div class="invalid-feedback"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
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
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="transaction_date" class="form-label fw-semibold">
                                        <i class="fas fa-calendar-check me-1 text-primary"></i>Transaction Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="transaction_date" name="transaction_date" required>
                                    <small class="text-muted">Date this consumer transaction was filed.</small>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="installation_date" class="form-label">Installation Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="installation_date" name="installation_date" >
                                    <small class="text-muted">Date that the consumer's water meter is installed.</small>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="account_no" class="form-label">Account Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="account_no" name="account_no" readonly required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="meter_number" class="form-label">Meter Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="meter_number" name="meter_number" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                 <div class="mb-3">
                                    <label for="address1" class="form-label">Address <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="address1" name="address1" rows="3" required></textarea>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="Residential" data-code="12">Residential</option>
                                        <option value="Government" data-code="22">Government</option>
                                        <option value="Commercial/Industrial C" data-code="32">Commercial/Industrial C</option>
                                        <option value="Commercial A" data-code="33" disabled>Commercial A</option>
                                        <option value="Commercial B" data-code="34" disabled>Commercial B</option>
                                        <option value="Commercial D" data-code="35">Commercial D</option>
                                        <option value="Whole Sale/Bulk" data-code="36" disabled>Whole Sale/Bulk</option>
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
                                    <label for="new_status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="new_status" name="status" required>
                                        <option value="">Select Status</option>
                                         <option value="P"selected>P</option>
                                        <option value="A">A</option>
                                        <option value="X">X</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="account_name" class="form-label">Account name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="account_name" name="account_name" placeholder="e.g. DELA CRUZ, JUAN M." required>
                                    <div class="invalid-feedback"></div>
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

        // Save Base Reading (Meter Reading card → Base Reading section) - requires PIN
        const meterReadingSaveBaseBtn = document.getElementById('meterReadingSaveBaseBtn');
        if (meterReadingSaveBaseBtn) {
            meterReadingSaveBaseBtn.addEventListener('click', function() {
                const accountNoEl = document.getElementById('meterReadingAccountNo');
                const baseInput = document.getElementById('meterReadingBaseValue');
                const baseDateInput = document.getElementById('meterReadingBaseDate');
                const statusEl = document.getElementById('meterReadingBaseStatus');
                const savedEl = document.getElementById('meterReadingBaseSaved');
                const blockEl = document.getElementById('meterReadingBaseBlock');

                // Defense in depth: refuse to save when the block is locked
                // (consumer already has Current/Previous reading history).
                if (blockEl && blockEl.getAttribute('data-has-history') === '1') {
                    if (statusEl) {
                        statusEl.textContent = 'Locked — consumer already has reading history.';
                        statusEl.classList.add('text-danger');
                    }
                    return;
                }

                // The Account # hidden input only renders when a schedule exists.
                // Fall back to the globally-tracked currentConsumer for new consumers.
                let accountNo = (accountNoEl && accountNoEl.value)
                    ? accountNoEl.value
                    : (currentConsumer && currentConsumer.account_no ? currentConsumer.account_no : '');
                if (!accountNo) {
                    if (statusEl) { statusEl.textContent = 'No consumer selected.'; statusEl.classList.add('text-danger'); }
                    return;
                }
                if (!baseInput) return;
                const baseValue = parseInt(baseInput.value, 10);
                if (isNaN(baseValue) || baseValue < 0) {
                    if (statusEl) { statusEl.textContent = 'Enter a valid number.'; statusEl.classList.add('text-danger'); }
                    return;
                }
                const baseDate = (baseDateInput && baseDateInput.value) ? baseDateInput.value : null;

                requestPinThenRun(function() {
                    meterReadingSaveBaseBtn.disabled = true;
                    if (statusEl) {
                        statusEl.textContent = 'Saving...';
                        statusEl.classList.remove('text-success', 'text-danger');
                    }
                    fetch('{{ route("consumer.update-base-reading") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : ''
                        },
                        body: JSON.stringify({
                            account_no: accountNo,
                            base_reading: baseValue,
                            base_reading_date: baseDate
                        })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        meterReadingSaveBaseBtn.disabled = false;
                        if (data.success) {
                            if (statusEl) {
                                statusEl.textContent = 'Saved.';
                                statusEl.classList.remove('text-danger');
                                statusEl.classList.add('text-success');
                            }
                            if (savedEl) {
                                savedEl.textContent = String(data.base_reading);
                            }
                            var dateEl = document.getElementById('meterReadingBaseSavedDate');
                            if (dateEl) {
                                var dateStr = data.base_reading_date
                                    ? (function () {
                                        try {
                                            var d = new Date(data.base_reading_date);
                                            if (!isNaN(d.getTime())) {
                                                var mm = String(d.getMonth() + 1).padStart(2, '0');
                                                var dd = String(d.getDate()).padStart(2, '0');
                                                return mm + '/' + dd + '/' + d.getFullYear();
                                            }
                                        } catch (e) {}
                                        return '';
                                    })()
                                    : '';
                                dateEl.textContent = dateStr || 'No date set';
                            }
                        } else if (statusEl) {
                            statusEl.textContent = data.message || 'Save failed.';
                            statusEl.classList.add('text-danger');
                        }
                    })
                    .catch(function(err) {
                        meterReadingSaveBaseBtn.disabled = false;
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

            // Default Transaction Date to today when empty so the user doesn't
            // have to retype it on every new consumer.
            const $txDate = $('#transaction_date');
            if ($txDate.length && !$txDate.val()) {
                const today = new Date();
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const dd = String(today.getDate()).padStart(2, '0');
                $txDate.val(today.getFullYear() + '-' + mm + '-' + dd);
            }
            applyRateCodeRestrictions('#category', '#rate_code');
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
                // Use sequence as entered (no padding)
                const accountNumber = `${trimmedZone}-${categoryCode}-${cardNumber}`;
                
                console.log('Final Account Number:', accountNumber);
                console.log('=================================');
                
                $('#account_no').val(accountNumber);
            } else {
                if (!trimmedZone) console.log('ERROR: Zone not selected');
                if (!categoryCode) console.log('ERROR: Category not selected');
                if (!cardNumber) console.log('ERROR: Card number not entered');
                $('#account_no').val('');
            }
        }

        const rateCodeDisableRulesByCategory = {
            'RESIDENTIAL': ['B', 'C', 'D'],
            'GOVERNMENT': ['A', 'C', 'D'],
            'COMMERCIAL/INDUSTRIAL C': ['A', 'B'],
            'COMMERCIAL D': ['A', 'B']
        };

        function applyRateCodeRestrictions(categorySelector, rateCodeSelector) {
            const category = ($(categorySelector).val() || '').toString().trim().toUpperCase();
            const disabledCodes = new Set(rateCodeDisableRulesByCategory[category] || []);
            const $rateCode = $(rateCodeSelector);

            if (!$rateCode.length) {
                return;
            }

            let selectedOptionStillAllowed = false;

            $rateCode.find('option').each(function() {
                const $option = $(this);
                const code = ($option.val() || '').toString().trim().toUpperCase();

                if (!code) {
                    $option.prop('disabled', false);
                    return;
                }

                const shouldDisable = disabledCodes.has(code);
                $option.prop('disabled', shouldDisable);

                if ($option.is(':selected') && !shouldDisable) {
                    selectedOptionStillAllowed = true;
                }
            });

            if (!selectedOptionStillAllowed) {
                const firstAllowed = $rateCode.find('option').filter(function() {
                    const value = ($(this).val() || '').toString().trim();
                    return value !== '' && !$(this).prop('disabled');
                }).first().val() || '';
                $rateCode.val(firstAllowed);
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

        function formatPlainAmount(value) {
            const amount = Number(value);
            if (!Number.isFinite(amount)) {
                return '0.0';
            }
            return amount.toFixed(1);
        }

        var SC_DISCOUNT_PERCENT = 'SC DISCOUNT';
        var SC_DISCOUNT_AMOUNT = 5;
        var editBillDiscSnapshot = null;

        function normBillDiscPercentVal(v) {
            if (v === '' || v === null || v === undefined) return null;
            const s = String(v).trim();
            if (s === '') return null;
            // Backward compatibility: handle legacy numeric values.
            const n = parseFloat(s);
            if (Number.isFinite(n) && Math.abs(n - 5) < 0.001) return SC_DISCOUNT_PERCENT;
            if (s.toUpperCase() === 'SC DISCOUNT') return SC_DISCOUNT_PERCENT;
            return s;
        }

        function normBillDiscAmountVal(v) {
            if (v === '' || v === null || v === undefined) return null;
            const n = parseFloat(String(v).trim());
            if (!Number.isFinite(n)) return null;
            return Math.round(n * 10000) / 10000;
        }

        function isEditBillDiscDirty() {
            if (!editBillDiscSnapshot) {
                return false;
            }
            return normBillDiscPercentVal($('#edit_bill_disc_percent').val()) !== editBillDiscSnapshot.bill_disc_percent
                || normBillDiscAmountVal($('#edit_bill_disc_amount').val()) !== editBillDiscSnapshot.bill_disc_amount;
        }

        function formatBillDiscUpdatedAtDisplay(raw) {
            if (!raw) {
                return '—';
            }
            const d = new Date(raw);
            if (Number.isNaN(d.getTime())) {
                return '—';
            }
            return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function refreshEditBillDiscDateUi() {
            const dirty = isEditBillDiscDirty();
            const $wrap = $('#edit_bill_disc_updated_at_wrap');
            const $input = $('#edit_bill_disc_updated_at');
            if (dirty) {
                $wrap.show();
                $input.prop('required', true);
            } else {
                $wrap.hide();
                $input.prop('required', false).val('').removeClass('is-invalid');
                $input.siblings('.invalid-feedback').text('');
            }
        }

        function applyBillDiscSelectChange(selectEl) {
            if (!selectEl || !selectEl.length) return;
            const v = selectEl.val();
            const id = selectEl.attr('id');
            if (id === 'edit_bill_disc_percent') {
                if (v === SC_DISCOUNT_PERCENT) {
                    $('#edit_bill_disc_amount').val(Number(SC_DISCOUNT_AMOUNT).toFixed(1));
                } else {
                    $('#edit_bill_disc_amount').val('');
                }
                refreshEditBillDiscDateUi();
            } else if (id === 'billDiscPercent') {
                if (v === SC_DISCOUNT_PERCENT) {
                    $('#billDiscAmount').val(formatPlainAmount(SC_DISCOUNT_AMOUNT));
                } else {
                    $('#billDiscAmount').val(formatPlainAmount(0));
                }
            }
        }

        $(document).on('change', '#billDiscPercent, #edit_bill_disc_percent', function() {
            applyBillDiscSelectChange($(this));
        });

        $(document).on('input change', '#edit_bill_disc_amount', function() {
            refreshEditBillDiscDateUi();
        });

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

            $('#latestBillCurrent').text(formatCurrency(currentBill));
            $('#latestBillMeterRental').text(formatCurrency(meterRental));
            $('#latestBillArrears').text(formatCurrency(arrears));
            $('#latestBillMaterials').text(formatCurrency(materials));
            $('#latestBillSeptage').text(formatCurrency(septageFee));
            $('#latestBillOthers').text(formatCurrency(others));
            $('#latestBillTotal').text(formatCurrency(totalAmount));

            if (latestBill) {
                const owner = latestBill.account_no || latestBill.account_number
                    || (typeof currentConsumer !== 'undefined' && currentConsumer ? currentConsumer.account_no : '');
                const payload = owner
                    ? Object.assign({}, latestBill, { account_no: latestBill.account_no || owner })
                    : latestBill;
                sessionStorage.setItem('latestBill', JSON.stringify(payload));
            } else {
                sessionStorage.removeItem('latestBill');
            }
        }
        window.updateLatestBillCard = updateLatestBillCard;

        function normalizeAccountKey(accountNo) {
            if (accountNo === null || accountNo === undefined) {
                return '';
            }
            return String(accountNo).replace(/-/g, '').replace(/\s+/g, '').toUpperCase();
        }

        function storedLatestBillMatchesConsumer(storedBill, consumerAccountNo) {
            if (!storedBill || !consumerAccountNo) {
                return false;
            }
            const onBill = storedBill.account_no || storedBill.account_number;
            if (!onBill) {
                return false;
            }
            return normalizeAccountKey(onBill) === normalizeAccountKey(consumerAccountNo);
        }

        function updateMeterReadingCard(meterReading) {
            const fmtDate = function(d) {
                if (!d) return '—';
                try {
                    const dt = new Date(d);
                    return isNaN(dt.getTime()) ? '—' : (dt.getMonth() + 1).toString().padStart(2, '0') + '/' + dt.getDate().toString().padStart(2, '0') + '/' + dt.getFullYear();
                } catch (e) { return '—'; }
            };
            const toDateInputValue = function(d) {
                if (!d) return '';
                try {
                    const dt = new Date(d);
                    if (isNaN(dt.getTime())) return '';
                    return dt.getFullYear() + '-'
                        + String(dt.getMonth() + 1).padStart(2, '0') + '-'
                        + String(dt.getDate()).padStart(2, '0');
                } catch (e) { return ''; }
            };

            // Always reset Base Reading fields first so values from a previously
            // viewed consumer NEVER leak into the currently selected one.
            const $baseValue = $('#meterReadingBaseValue');
            const $baseDate = $('#meterReadingBaseDate');
            const $baseSaved = $('#meterReadingBaseSaved');
            const $baseSavedDate = $('#meterReadingBaseSavedDate');
            const $baseStatus = $('#meterReadingBaseStatus');
            $baseValue.val('');
            $baseDate.val('');
            $baseSaved.text('—');
            $baseSavedDate.text('No date set');
            $baseStatus.text('').removeClass('text-success text-danger');

            if (!meterReading) {
                $('#meterReadingCurrentDate').text('—');
                $('#meterReadingCurrentValue').text('—');
                $('#meterReadingPrevDate').text('—');
                $('#meterReadingPrevValue').val(0);
                $('#meterReadingSaveStatus').text('');
                $('.meter-reading-save-block').hide();
                setBaseReadingLocked(false);
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

            // Repopulate Base Reading from the freshly fetched consumer
            const hasBase = meterReading.base_reading !== null && meterReading.base_reading !== undefined && meterReading.base_reading !== '';
            if (hasBase) {
                $baseValue.val(meterReading.base_reading);
                $baseSaved.text(meterReading.base_reading);
            }
            if (meterReading.base_reading_date) {
                $baseDate.val(toDateInputValue(meterReading.base_reading_date));
                $baseSavedDate.text(fmtDate(meterReading.base_reading_date));
            }

            // Lock the Base Reading editor when the consumer already has
            // Current/Previous reading data so users can't conflict with
            // existing billing history.
            const prevNumeric = parseInt(meterReading.previous_reading, 10);
            const hasHistory = !!meterReading.schedule_id
                || !!meterReading.previous_reading_date
                || !!meterReading.current_reading_date
                || (meterReading.current_reading !== null
                    && meterReading.current_reading !== undefined
                    && meterReading.current_reading !== '')
                || (!isNaN(prevNumeric) && prevNumeric > 0);
            setBaseReadingLocked(hasHistory);
        }

        function setBaseReadingLocked(locked) {
            const $block = $('#meterReadingBaseBlock');
            if ($block.length === 0) return;
            $block.attr('data-has-history', locked ? '1' : '0');
            $block.toggleClass('base-reading-locked', !!locked);
            $('#meterReadingBaseValue').prop('disabled', !!locked);
            $('#meterReadingBaseDate').prop('disabled', !!locked);
            $('#meterReadingSaveBaseBtn').prop('disabled', !!locked);
            $('#meterReadingBaseHistoryAlert').toggle(!!locked);
            $('#meterReadingBaseLockedBadge').toggle(!!locked);
            // If we just unlocked, also clear any stale "Saved." status text so
            // the user starts fresh.
            if (!locked) {
                $('#meterReadingBaseStatus').text('').removeClass('text-success text-danger');
            }
        }
        window.setBaseReadingLocked = setBaseReadingLocked;
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
                const accountNumber = `${trimmedZone}-${categoryCode}-${cardNumber}`;
                $('#edit_account_no').val(accountNumber);
            } else {
                $('#edit_account_no').val('');
            }
        }

        // Trigger account number generation when zone, category, or card number changes (New Consumer)
        $('#zone, #category, #card_number').on('change keyup input', function() {
            console.log('Zone, Category, or Card Number changed');
            generateAccountNumber();
            if (this.id === 'category') {
                applyRateCodeRestrictions('#category', '#rate_code');
            }
        });

        // Trigger account number re-generation when zone, category, or sequence changes (Edit Consumer)
        $(document).on('change keyup input', '#edit_zone, #edit_category, #edit_card_number', function() {
            generateEditAccountNumber();
            if (this.id === 'edit_category') {
                applyRateCodeRestrictions('#edit_category', '#edit_rate_code');
            }
        });

        // Run once in case fields already have values (e.g., form repopulation)
        generateAccountNumber();
        applyRateCodeRestrictions('#category', '#rate_code');
        if (initialLatestBill) {
            updateLatestBillCard(initialLatestBill);
        } else {
            const serverAccount = (typeof currentConsumer !== 'undefined' && currentConsumer && currentConsumer.account_no)
                ? currentConsumer.account_no
                : null;
            const storedLatestBill = sessionStorage.getItem('latestBill');
            if (serverAccount && storedLatestBill) {
                try {
                    const parsed = JSON.parse(storedLatestBill);
                    if (storedLatestBillMatchesConsumer(parsed, serverAccount)) {
                        updateLatestBillCard(parsed);
                    } else {
                        sessionStorage.removeItem('latestBill');
                        updateLatestBillCard(null);
                    }
                } catch (error) {
                    console.error('Failed to parse stored latest bill:', error);
                    sessionStorage.removeItem('latestBill');
                    updateLatestBillCard(null);
                }
            } else {
                if (storedLatestBill && !serverAccount) {
                    sessionStorage.removeItem('latestBill');
                }
                updateLatestBillCard(null);
            }
        }
        
        // Handle form submission
        $('#newConsumerForm').on('submit', function(e) {
            e.preventDefault();
            console.log('Form submitted');
            const $form = $(this);
            
            // Clear previous errors
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').text('');
            
            // Disable submit button
            $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Saving...');
            
            // Prepare data for consumer_zone table
            const consCtrl = $('#newConsumerModal').data('cons-ctrl') || generateConsCtrl();
            const formData = {
                _token: $form.find('input[name="_token"]').val(),
                transaction_date: $form.find('#transaction_date').val(),
                install_date: $form.find('#installation_date').val(),
                account_no: $form.find('#account_no').val(),
                account_name: $form.find('#account_name').val(),
                address1: $form.find('#address1').val(),
                zone_code: $form.find('#zone').val(),
                category_code: $form.find('#category option:selected').data('code'),
                meter_number: $form.find('#meter_number').val(),
                meter_brand: $form.find('#meter_brand').val(),
                rate_code: $form.find('#rate_code').val(),
                status_code: $form.find('#new_status').val(),
                sequence: $form.find('#card_number').val(),
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

                        // Avoid showing previous consumer's bill after reload (sessionStorage was keyed without validation)
                        try {
                            sessionStorage.removeItem('latestBill');
                        } catch (e) { /* ignore */ }

                        const created = response.consumer;
                        if (created && created.account_no) {
                            try {
                                sessionStorage.setItem('currentConsumer', JSON.stringify(created));
                            } catch (e) { /* ignore */ }
                        }
                        
                        // Show success message with SweetAlert
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            confirmButtonColor: '#28a745',
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            // Open this consumer explicitly — location.reload() kept ?account=... and hid the new record
                            const base = @json(route('consumer'));
                            if (created && created.account_no) {
                                window.location.href = base + '?account=' + encodeURIComponent(created.account_no);
                            } else {
                                window.location.href = base;
                            }
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

    // Use first validation message in Swal (e.g. "Account number already exists.")
    const firstMessage = errors.account_no && errors.account_no[0]
        ? errors.account_no[0]
        : (Object.values(errors)[0] && Object.values(errors)[0][0]) || 'Please check the form fields and try again.';

    Swal.fire({
        icon: 'error',
        title: 'Validation Error',
        text: firstMessage,
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
            $('#account_no').val('');
            $('#display_cons_ctrl').val('');
            $('#transaction_date').val('');
            
            // Clear stored cons_ctrl
            $('#newConsumerModal').removeData('cons-ctrl');
            applyRateCodeRestrictions('#category', '#rate_code');
            
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

            let formattedTransactionDate = '';
            if (consumer.transaction_date) {
                try {
                    const txDate = new Date(consumer.transaction_date);
                    if (!isNaN(txDate.getTime())) {
                        formattedTransactionDate = (txDate.getMonth() + 1) + '/' + txDate.getDate() + '/' + txDate.getFullYear();
                    }
                } catch (e) { /* keep empty */ }
            }

            // Update the header dynamically
            updateConsumerHeader(consumer);

            // Format balance currency
            const balance = consumer.balance || 0;
            const formattedBalance = '₱ ' + parseFloat(balance).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            const billDiscPercentRaw = (consumer.bill_disc_percent ?? consumer.bill_disc ?? '');
            const billDiscPercentStr = String(billDiscPercentRaw ?? '').trim();
            const billDiscPercentNum = parseFloat(billDiscPercentStr);
            const isScBillDisc = billDiscPercentStr.toUpperCase() === 'SC DISCOUNT'
                || (Number.isFinite(billDiscPercentNum) && Math.abs(billDiscPercentNum - 5) < 0.001);
            const billDiscAmountValue = parseFloat(consumer.bill_disc_amount ?? 0);
            const displayDiscAmount = isScBillDisc ? SC_DISCOUNT_AMOUNT : (Number.isFinite(billDiscAmountValue) ? billDiscAmountValue : 0);
            const formattedBillDiscAmount = formatPlainAmount(displayDiscAmount);
            const remarkRaw = consumer.remark || consumer.remarks || '';
            const remarkSafe = String(remarkRaw)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');

            // Update the consumer information card with the new data from consumer_zone table
            const html = `
                <div class="p-3">
                <div class="row g-3 mb-2">
                    <div class="col-12">
                        <label class="form-label fw-semibold text-primary mb-1">
                            <i class="fas fa-calendar-check me-1"></i>Transaction Date
                        </label>
                        <input type="text" class="form-control form-control-sm" id="transactionDateDisplay"
                            value="${formattedTransactionDate}" readonly>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="consumer-info-panel">
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
                        <div class="mb-3">
                            <label class="form-label" for="billDiscPercent">Bill Disc (%)</label>
                            <select class="form-control form-control-sm" id="billDiscPercent" name="bill_disc_percent">
                                <option value="" ${!isScBillDisc ? 'selected' : ''}>None</option>
                                <option value="SC DISCOUNT" ${isScBillDisc ? 'selected' : ''}>SC DISCOUNT</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="billDiscAmount">Bill Disc Amount</label>
                            <input type="text" class="form-control form-control-sm" id="billDiscAmount" readonly
                                value="${formattedBillDiscAmount}">
                        </div>
                        <div class="mb-3 mb-md-0">
                            <label class="form-label">OSCA ID #</label>
                            <input type="text" class="form-control form-control-sm" id="oscaIdNumber"
                                value="${consumer.osca_id_no || consumer.osca_id || ''}">
                        </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="consumer-info-panel">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <input type="text" class="form-control form-control-sm" id="consumerStatusDisplay"
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
                        <div class="mb-0">
                            <label class="form-label">Balance</label>
                            <input type="text" class="form-control form-control-sm" id="balance"
                                value="${formattedBalance}" readonly>
                        </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="consumer-info-remark">
                            <label class="form-label d-flex align-items-center">
                                <i class="fas fa-comment-alt text-primary me-2"></i> Remark
                            </label>
                            <textarea class="form-control form-control-sm bg-light" id="remark" rows="2" readonly>${remarkSafe}</textarea>
                        </div>
                    </div>
                </div>
                </div>
            `;

            $('#consumerInfoCard').html(html);
        }
        window.updateConsumerInfo = updateConsumerInfo;

        // // Update consumer header dynamically
        // function updateConsumerHeader(consumer) {
        //     // Use account_name from consumer_zone table
        //     let fullName = consumer.account_name || '';

        //     // Update header name - use account_no from consumer_zone table
        //     const headerName = document.getElementById('consumerHeaderName');
        //     if (headerName) {
        //         headerName.textContent = (consumer.account_no || '') + ' ' + fullName;
        //     }
            
        //     // Update header address
        //     const headerAddress = document.getElementById('consumerHeaderAddress');
        //     if (headerAddress) {
        //         const addr = [consumer.address1, consumer.address2 || consumer.address_2].filter(Boolean).join(' ').trim();
        //         headerAddress.textContent = addr || '—';
        //     }

        //     // Update header status badge - use status_label or status_code
        //     const headerStatus = document.getElementById('consumerHeaderStatus');
        //     if (headerStatus) {
        //         const statusText = consumer.status_label || consumer.status_code || 'N/A';
        //         headerStatus.textContent = statusText + ' Consumer';
                
        //         // Update badge color based on status
        //         headerStatus.className = 'badge';
        //         const statusUpper = (statusText || '').toUpperCase();
        //         if (statusUpper === 'ACTIVE' || statusUpper === 'A') {
        //             headerStatus.classList.add('bg-success');
        //         } else if (statusUpper === 'INACTIVE' || statusUpper === 'I') {
        //             headerStatus.classList.add('bg-warning');
        //         } else if (statusUpper === 'DISCONNECTED' || statusUpper === 'D') {
        //             headerStatus.classList.add('bg-danger');
        //         } else if (statusUpper === 'SUSPENDED' || statusUpper === 'S') {
        //             headerStatus.classList.add('bg-warning');
        //         } else {
        //             headerStatus.classList.add('bg-secondary');
        //         }
        //     }

        //     // Store consumer in sessionStorage for other tabs
        //     sessionStorage.setItem('currentConsumer', JSON.stringify(consumer));

        //     console.log('Header updated:', fullName);
        //     console.log('Consumer saved to sessionStorage');
        // }
         // Update consumer header dynamically
         
            function formatDisconnectedAtHuman(iso) {
            if (!iso) return '';
            try {
                var d = new Date(iso);
                if (isNaN(d.getTime())) return '';
                var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                var h = d.getHours();
                var m = d.getMinutes();
                var am = h >= 12 ? 'PM' : 'AM';
                h = h % 12;
                if (h === 0) h = 12;
                var mm = m < 10 ? '0' + m : m;
                return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear() + ' ' + h + ':' + mm + ' ' + am;
            } catch (e) { return ''; }
        }

        function syncDisconnectedNotice(consumer) {
            var wrap = document.getElementById('consumerHeaderInfo');
            if (!wrap) return;
            var code = String(consumer.status_code || '').trim().toUpperCase();
            var label = String(consumer.status_label || '').trim();
            var isDisc = ['X', 'D', 'DISCONNECTED'].indexOf(code) !== -1 || label === 'Disconnected';
            var el = document.getElementById('consumerDisconnectedNotice');
            if (!isDisc) {
                if (el) el.remove();
                return;
            }
            var iso = consumer.latest_disconnected_at || consumer.latestDisconnectedAt;
            var msg;
            if (iso) {
                var human = formatDisconnectedAtHuman(iso);
                msg = 'This consumer was Disconnected at ' + (human || iso) + '.';
            } else {
                msg = 'This consumer was Disconnected; no disconnect timestamp was recorded in disconnection orders.';
            }
            if (!el) {
                el = document.createElement('div');
                el.id = 'consumerDisconnectedNotice';
                el.className = 'mt-2 p-2 rounded bg-white text-danger small w-100 text-start shadow-sm';
                el.setAttribute('role', 'status');
                wrap.appendChild(el);
            }
            el.innerHTML = '<i class="fas fa-unlink mr-1" aria-hidden="true"></i>' + msg;
        }

        function updateConsumerHeader(consumer) {
            // Use account_name from consumer_zone table
            let fullName = consumer.account_name || '';

            const statusText = consumer.status_label || consumer.status_code || 'N/A';
            const statusUpper = (statusText || '').toUpperCase();

            // Update header name (warning/danger text matches status)
            const headerName = document.getElementById('consumerHeaderName');
            if (headerName) {
                headerName.textContent = (consumer.account_no || '') + ' ' + fullName;
                headerName.className = 'mb-1';
                if (statusUpper === 'PENDING' || statusUpper === 'P') {
                    headerName.classList.add('text-warning', 'fw-semibold');
                } else if (statusUpper === 'DISCONNECTED' || statusUpper === 'X' || statusUpper === 'D') {
                    headerName.classList.add('text-danger', 'fw-semibold');
                }
            }

            const headerAddress = document.getElementById('consumerHeaderAddress');
            if (headerAddress) {
                const a1 = (consumer.address1 || '').trim();
                const a2 = (consumer.address2 || consumer.address_2 || '').trim();
                headerAddress.textContent = [a1, a2].filter(Boolean).join(' ') || '—';
            }

            // Update header status badge - use status_label or status_code
            const headerStatus = document.getElementById('consumerHeaderStatus');
            if (headerStatus) {
                headerStatus.textContent = statusText + ' Consumer';
                
                // Badge colors: Active / Pending / Disconnected (matches ConsumerZoneOne::status_label)
                headerStatus.className = 'badge';
                if (statusUpper === 'ACTIVE' || statusUpper === 'A') {
                    headerStatus.classList.add('bg-success');
                } else if (statusUpper === 'PENDING' || statusUpper === 'P') {
                    headerStatus.classList.add('bg-warning');
                } else if (statusUpper === 'DISCONNECTED' || statusUpper === 'X' || statusUpper === 'D') {
                    headerStatus.classList.add('bg-danger');
                } else {
                    headerStatus.classList.add('bg-secondary');
                }
            }
           
               syncDisconnectedNotice(consumer);
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
                    console.log('Loaded consumer from sessionStorage:', consumer.account_no);
                } catch (e) {
                    console.error('Error loading consumer from storage:', e);
                }
            }
        });

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
                
                if (currentConsumer.transaction_date) {
                    try {
                        const txDate = new Date(currentConsumer.transaction_date);
                        if (!isNaN(txDate.getTime())) {
                            $('#edit_transaction_date').val(txDate.toISOString().split('T')[0]);
                        } else {
                            $('#edit_transaction_date').val('');
                        }
                    } catch (e) { $('#edit_transaction_date').val(''); }
                } else {
                    $('#edit_transaction_date').val('');
                }

                if (currentConsumer.install_date) {
                    const date = new Date(currentConsumer.install_date);
                    const formattedDate = date.toISOString().split('T')[0];
                    $('#edit_installation_date').val(formattedDate);
                }
                
                $('#edit_account_no').val(currentConsumer.account_no || '');
                $('#edit_meter_number').val(currentConsumer.meter_number || '');
                $('#edit_address').val(currentConsumer.address1 || '');
                
                $('#edit_category').val('');
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
                applyRateCodeRestrictions('#edit_category', '#edit_rate_code');
                $('#edit_account_name').val(currentConsumer.account_name || '');
                
                $('#edit_contact_number').val('');
                $('#edit_meter_brand').val(currentConsumer.meter_brand || '');
                $('#edit_zone').val(currentConsumer.zone_code || '');
                $('#edit_card_number').val(currentConsumer.sequence || '');
                $('#edit_cons_ctrl').val(currentConsumer.cons_ctrl || '');
                const edRaw = String(currentConsumer.bill_disc_percent ?? '').trim();
                const edNum = parseFloat(edRaw);
                const edIsSc = edRaw.toUpperCase() === 'SC DISCOUNT'
                    || (Number.isFinite(edNum) && Math.abs(edNum - 5) < 0.001);
                $('#edit_bill_disc_percent').val(edIsSc ? 'SC DISCOUNT' : '');
                $('#edit_bill_disc_amount').val(edIsSc ? Number(SC_DISCOUNT_AMOUNT).toFixed(1) : (function() {
                    const a = currentConsumer.bill_disc_amount;
                    if (a === undefined || a === null || a === '') return '';
                    const n = parseFloat(a);
                    return Number.isFinite(n) ? n.toFixed(1) : '';
                })());
                $('#edit_osca_id_no').val(currentConsumer.osca_id_no || currentConsumer.osca_id || '');
                $('#edit_remark').val(currentConsumer.remark || currentConsumer.remarks || '');

                $('#edit_bill_disc_last_updated_display').text(
                    formatBillDiscUpdatedAtDisplay(currentConsumer.bill_disc_updated_at)
                );
                editBillDiscSnapshot = {
                    bill_disc_percent: normBillDiscPercentVal($('#edit_bill_disc_percent').val()),
                    bill_disc_amount: normBillDiscAmountVal($('#edit_bill_disc_amount').val())
                };
                $('#edit_bill_disc_updated_at').val('').removeClass('is-invalid');
                $('#edit_bill_disc_updated_at').siblings('.invalid-feedback').text('');
                refreshEditBillDiscDateUi();

                console.log('Edit modal populated with consumer:', currentConsumer.account_no);
                $('#editConsumerModal').modal('show');
            });
        });

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
            
            if (isEditBillDiscDirty()) {
                const bdu = $('#edit_bill_disc_updated_at').val();
                if (!bdu) {
                    $('#edit_bill_disc_updated_at').addClass('is-invalid');
                    $('#edit_bill_disc_updated_at').siblings('.invalid-feedback').text('Please choose the date when the bill discount was changed.');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Date required',
                        text: 'Enter the date of the bill discount change.',
                        confirmButtonColor: '#ffc107'
                    });
                    return;
                }
            }

            // Disable submit button
            $('#updateBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Updating...');
            
            // Prepare data for consumer_zone table
            const updateData = {
                _token: $('input[name="_token"]').val(),
                _method: 'PUT',
                transaction_date: $('#edit_transaction_date').val(),
                install_date: $('#edit_installation_date').val(),
                account_no: $('#edit_account_no').val(),
                account_name: $('#edit_account_name').val(),
                address1: $('#edit_address').val(),
                zone_code: $('#edit_zone').val(),
                category_code: $('#edit_category option:selected').data('code') || $('#edit_category').val(),
                meter_number: $('#edit_meter_number').val(),
                meter_brand: $('#edit_meter_brand').val(),
                rate_code: $('#edit_rate_code').val() || 'A',
                status_code: $('#edit_status').val(),
                sequence: $('#edit_card_number').val(),
                bill_disc_percent: $('#edit_bill_disc_percent').val(),
                bill_disc_amount: $('#edit_bill_disc_amount').val(),
                osca_id_no: $('#edit_osca_id_no').val(),
                remark: $('#edit_remark').val()
                // Note: cons_ctrl is not updated during edit to preserve the original value
            };
            if (isEditBillDiscDirty()) {
                updateData.bill_disc_updated_at = $('#edit_bill_disc_updated_at').val();
            }
            
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
                        searchConsumer(response.consumer.account_no);
                        
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
                              $('#consumerHeaderName').attr('class', 'mb-1').text('No Consumer Selected');
                            $('#consumerHeaderAddress').text('—');
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
