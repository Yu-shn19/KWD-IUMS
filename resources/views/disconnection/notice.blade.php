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
                            <h1 class="h3 mb-1 text-gray-800 font-weight-bold">Disconnection Notice Preview</h1>
                            <p class="text-muted mb-0 small">Review before saving and assigning</p>
                        </div>
                        <div class="btn-group" role="group">
                            <!-- Save and Assign Button -->
                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#saveAssignModal">
                                <i class="fas fa-save"></i> Save & Assign
                            </button>
                            
                            <!-- Print Button -->
                            <form method="POST" action="{{ route('disconnection.print') }}" class="d-inline">
                                @csrf
                                @foreach($consumersWithOutstanding as $consumer)
                                    <input type="hidden" name="consumer_ids[]" value="{{ $consumer->id }}">
                                @endforeach
                                <input type="hidden" name="disconnection_date" value="{{ $disconnectionDate->format('Y-m-d') }}">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </form>
                            
                            <!-- Back Button -->
                            <a href="{{ route('disconnection.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>

                    <!-- Notice Preview -->
                    @foreach($consumersWithOutstanding as $consumer)
                        <div class="card shadow mb-4" style="page-break-after: always; background: white;">
                            <div class="card-body" style="padding: 40px; font-family: Arial, sans-serif;">
                                <!-- Header Section with Logo -->
                                <div class="row mb-4" style="align-items: center; display: flex; margin-bottom: 25px;">
                                    <div class="col-auto" style="padding-right: 20px;">
                                        <img src="{{ url('WDMS/img/logo/logo.png') }}" alt="Hagonoy Water District Logo" 
                                             style="width: 120px; height: 120px; object-fit: contain;">
                                    </div>
                                    <div class="col text-center" style="flex: 1;">
                                        <h4 class="font-weight-bold mb-1" style="font-size: 1.6rem; margin: 0; letter-spacing: 0.5px; margin-right: 50px;">HAGONOY WATER DISTRICT</h4>
                                        <p class="mb-0" style="font-size: 1rem; margin-top: 3px; margin-right: 50px;">Guihing, Hagonoy, Davao del Sur</p>
                                    </div>
                                </div>

                                <!-- Notice Title -->
                                <div class="row mb-4">
                                    <div class="col-md-12 text-center ">
                                        <h5 class="font-weight-bold  " style="font-size: 1.4rem; text-decoration: underline; margin: 25px 0; letter-spacing: 1px; ">NOTICE OF DISCONNECTION</h5>
                                    </div>
                                </div>

                                <!-- Account Information - Two Column Layout -->
                                <div class="row mb-4" style="margin-bottom: 25px;">
                                    <div class="col-md-6" style="padding-right: 30px;">
                                        <p class="mb-2" style="margin-bottom: 10px; line-height: 1.9; font-size: 13px;">
                                            <strong style="display: inline-block; width: 140px; vertical-align: top; font-weight: 600;">Acc. No :</strong>
                                            <span style="display: inline-block; font-weight: 500;">{{ $consumer->account_no }}</span>
                                        </p>
                                        <p class="mb-2" style="margin-bottom: 10px; line-height: 1.9; font-size: 13px;">
                                            <strong style="display: inline-block; width: 140px; vertical-align: top; font-weight: 600;">Acc. Name :</strong>
                                            <span style="display: inline-block; font-weight: 500;">{{ $consumer->account_name }}</span>
                                        </p>
                                        <p class="mb-2" style="margin-bottom: 10px; line-height: 1.9; font-size: 13px;">
                                            <strong style="display: inline-block; width: 140px; vertical-align: top; font-weight: 600;">Address :</strong>
                                            <span style="display: inline-block; font-weight: 500;">{{ $consumer->address1 }}</span>
                                        </p>
                                    </div>
                                    <div class="col-md-6" style="padding-left: 30px;">
                                        <p class="mb-2" style="margin-bottom: 10px; line-height: 1.9; font-size: 13px;">
                                            <strong style="display: inline-block; width: 160px; vertical-align: top; font-weight: 600;">Disconnection Date :</strong>
                                            <span style="display: inline-block; font-weight: 500;">{{ $disconnectionDate->format('m/d/Y') }}</span>
                                        </p>
                                        <p class="mb-2" style="margin-bottom: 10px; line-height: 1.9; font-size: 13px;">
                                            <strong style="display: inline-block; width: 160px; vertical-align: top; font-weight: 600;">Zone :</strong>
                                            <span style="display: inline-block; font-weight: 500;">{{ $consumer->zone_code }}</span>
                                        </p>
                                        <p class="mb-2" style="margin-bottom: 10px; line-height: 1.9; font-size: 13px;">
                                            <strong style="display: inline-block; width: 160px; vertical-align: top; font-weight: 600;">Card No. :</strong>
                                            <span style="display: inline-block; font-weight: 500;">{{ $consumer->card_number ?? $consumer->sequence ?? '1' }}</span>
                                        </p>
                                    </div>
                                </div>

                                <!-- Statement of Account -->
                                <div class="row mb-4" style="margin-bottom: 25px;">
                                    <div class="col-md-12">
                                        <h6 class="font-weight-bold text-center mb-3" style="font-size: 1.15rem; text-decoration: underline; margin-bottom: 25px; letter-spacing: 0.5px;">STATEMENT OF ACCOUNT</h6>
                                        <div class="row text-center" style="margin: 0; display: flex; align-items: stretch;">
                                            <div class="col-3" style="padding: 0 12px; display: flex; flex-direction: column; justify-content: flex-start;">
                                                <div style="margin-bottom: 10px; font-size: 0.95rem; min-height: 45px; display: flex; align-items: center; justify-content: center; font-weight: 500;">This Month/ Arrears</div>
                                                <div style="border-top: 3px solid #000; margin: 6px 0 12px 0; width: 100%;"></div>
                                                <div class="font-weight-bold" style="font-size: 1.15rem; text-align: center; margin-top: auto; font-weight: 700;">₱{{ number_format($consumer->this_month_arrears ?? 0, 2) }}</div>
                                            </div>
                                            <div class="col-3" style="padding: 0 12px; display: flex; flex-direction: column; justify-content: flex-start;">
                                                <div style="margin-bottom: 10px; font-size: 0.95rem; min-height: 45px; display: flex; align-items: center; justify-content: center; font-weight: 500;">Last Month/ Arrears CY</div>
                                                <div style="border-top: 3px solid #000; margin: 6px 0 12px 0; width: 100%;"></div>
                                                <div class="font-weight-bold" style="font-size: 1.15rem; text-align: center; margin-top: auto; font-weight: 700;">₱{{ number_format($consumer->last_month_arrears ?? 0, 2) }}</div>
                                            </div>
                                            <div class="col-3" style="padding: 0 12px; display: flex; flex-direction: column; justify-content: flex-start;">
                                                <div style="margin-bottom: 10px; font-size: 0.95rem; min-height: 45px; display: flex; align-items: center; justify-content: center; font-weight: 500;">Others - A/R</div>
                                                <div style="border-top: 3px solid #000; margin: 6px 0 12px 0; width: 100%;"></div>
                                                <div class="font-weight-bold" style="font-size: 1.15rem; text-align: center; margin-top: auto; font-weight: 700;">₱{{ number_format($consumer->others_ar ?? 0, 2) }}</div>
                                            </div>
                                            <div class="col-3" style="padding: 0 12px; display: flex; flex-direction: column; justify-content: flex-start;">
                                                <div style="margin-bottom: 10px; font-size: 0.95rem; min-height: 45px; display: flex; align-items: center; justify-content: center; font-weight: 500;">Total Amount Due</div>
                                                <div style="border-top: 3px solid #000; margin: 6px 0 12px 0; width: 100%;"></div>
                                                <div class="font-weight-bold text-danger" style="font-size: 1.3rem; text-align: center; margin-top: auto; font-weight: 700;">₱{{ number_format($consumer->total_outstanding, 2) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Important Section -->
                                <div class="row mb-4" style="margin-bottom: 25px;">
                                    <div class="col-md-12">
                                        <h6 class="font-weight-bold text-center mb-3" style="font-size: 1.15rem; text-decoration: underline; margin-bottom: 18px; letter-spacing: 0.5px;">IMPORTANT</h6>
                                        <ol style="padding-left: 28px; margin-bottom: 0; text-align: justify; font-size: 13px;">
                                            <li style="margin-bottom: 10px; line-height: 1.7;">Please pay total amount at Hagonoy Water District Office, Guihing, Hagonoy, Davao del Sur on or before <strong style="font-weight: 700;">{{ $disconnectionDate->format('m/d/Y') }}</strong>.</li>
                                            <li style="margin-bottom: 10px; line-height: 1.7;">Failure to pay on the specified date, we will be constrained to cut-off your service connection.</li>
                                            <li style="margin-bottom: 10px; line-height: 1.7;">If service is discontinued, total amount due plus <strong style="font-weight: 700;">P200.00 service fee</strong> will be required to re-establish service.</li>
                                            <li style="margin-bottom: 10px; line-height: 1.7;">Please disregard this notice if account has been paid in full.</li>
                                            <li style="margin-bottom: 10px; line-height: 1.7;">Partial payment does not relieve the disconnection of your water service connection.</li>
                                        </ol>
                                    </div>
                                </div>

                                <!-- Signature Section -->
                                <div class="row mt-5" style="margin-top: 60px;">
                                    <div class="col-md-6">
                                        <p class="mb-1" style="margin-bottom: 5px;">Prepared by:</p>
                                        {{-- Prepared by signature image --}}
                                        <img src="{{ asset('images/signatures/marlo-sign.png') }}"
                                             alt="Signature of Marlo B. Porras"
                                             style="height: 60px; margin-top: 10px; margin-bottom: 0; display: block;">
                                        <p class="mb-0 font-weight-bold" style="text-decoration: underline; margin-top: 5px;">MARLO B. PORRAS</p>
                                        <p class="mb-0 small" style="font-size: 0.85rem; margin-top: 2px;">UCSA-E</p>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <p class="mb-1" style="margin-bottom: 5px;">Approved by:</p>
                                        {{-- Approved by signature image --}}
                                        <img src="{{ asset('images/signatures/gm-sign.png') }}"
                                             alt="Signature of Engr. Joemar G. Raut"
                                             style="height: 60px; margin-top: 10px; margin-bottom: 0; display: block; margin-left: auto;">
                                        <p class="mb-0 font-weight-bold text-right" style="text-decoration: underline; margin-top: 5px;">Engr. JOEMAR G. RAUT</p>
                                        <p class="mb-0 small text-right" style="font-size: 0.85rem; margin-top: 2px;">General Manager</p>
                                    </div>
                                </div>

                                <!-- Copy Received Section -->
                                <div class="row mt-4" style="margin-top: 50px;">
                                    <div class="col-md-6">
                                        <p class="mb-1" style="margin-bottom: 5px;">Copy Received:</p>
                                        <div style="border-top: 1px solid #000; width: 100%; max-width: 300px; margin-top: 30px;"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1" style="margin-bottom: 5px;">Date Received:</p>
                                        <div style="border-top: 1px solid #000; width: 100%; max-width: 300px; margin-top: 30px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
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

    <!-- Save and Assign Modal -->
    <div class="modal fade" id="saveAssignModal" tabindex="-1" role="dialog" aria-labelledby="saveAssignModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="saveAssignModalLabel">Save Disconnection Orders</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="{{ route('disconnection.save-and-assign') }}">
                    @csrf
                    <div class="modal-body">
                        <!-- Consumer Count -->
                        <div class="alert alert-info">
                            <strong>Total Consumers:</strong> {{ count($consumersWithOutstanding) }}
                        </div>

                        <!-- Disconnection Date (Hidden) -->
                        <input type="hidden" name="disconnection_date" value="{{ $disconnectionDate->format('Y-m-d') }}">

                        <!-- Consumer IDs (Hidden) -->
                        @foreach($consumersWithOutstanding as $consumer)
                            <input type="hidden" name="consumer_ids[]" value="{{ $consumer->id }}">
                        @endforeach

                        <!-- Assign to Disconnector -->
                        <div class="form-group">
                            <label for="assignTo"><strong>Assign to Disconnector <span class="text-danger">*</span></strong></label>
                            <select name="assign_to" id="assignTo" class="form-control" required>
                                <option value="">-- Select a Disconnector --</option>
                                @php
                                    $disconnectors = \App\Models\User::where('role', 'disconnector')->orderBy('name')->get();
                                @endphp
                                @foreach($disconnectors as $disconnector)
                                    <option value="{{ $disconnector->id }}">{{ $disconnector->name }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">
                                A disconnector must be assigned to save orders.
                            </small>
                        </div>

                        <!-- Summary -->
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Summary</h6>
                                <p class="mb-1">
                                    <strong>Date:</strong> {{ $disconnectionDate->format('F d, Y') }}
                                </p>
                                <p class="mb-1">
                                    <strong>Consumers:</strong> {{ count($consumersWithOutstanding) }}
                                </p>
                                <p class="mb-0">
                                    <strong>Total Outstanding:</strong> 
                                    <span class="text-danger font-weight-bold">
                                        ₱{{ number_format($consumersWithOutstanding->sum('total_outstanding'), 2) }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Orders
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

