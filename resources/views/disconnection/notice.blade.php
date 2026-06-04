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
                            <p class="text-muted mb-0 small">Preview only — use Disconnection Management to save orders to the mobile app</p>
                        </div>
                        <div class="btn-group" role="group">
                            <!-- Print Button -->
                            <form method="POST" action="{{ route('disconnection.print') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="list_billing_month" value="{{ $listBillingMonth ?? '' }}">
                                <input type="hidden" name="list_billing_date" value="{{ $listBillingDate ?? '' }}">
                                @foreach($consumersWithOutstanding as $consumer)
                                    <input type="hidden" name="consumer_ids[]" value="{{ $consumer->id }}">
                                    <input type="hidden" name="financials[{{ $consumer->id }}][this_month_arrears]" value="{{ number_format((float) ($consumer->this_month_arrears ?? 0), 2, '.', '') }}">
                                    <input type="hidden" name="financials[{{ $consumer->id }}][last_month_arrears]" value="{{ number_format((float) ($consumer->last_month_arrears ?? 0), 2, '.', '') }}">
                                    <input type="hidden" name="financials[{{ $consumer->id }}][others_ar]" value="{{ number_format((float) ($consumer->others_ar ?? 0), 2, '.', '') }}">
                                    @php
                                        $noticeTotalDue = round(
                                            (float) ($consumer->this_month_arrears ?? 0)
                                            + (float) ($consumer->last_month_arrears ?? 0)
                                            + (float) ($consumer->others_ar ?? 0),
                                            2
                                        );
                                    @endphp
                                    <input type="hidden" name="financials[{{ $consumer->id }}][total_outstanding]" value="{{ number_format($noticeTotalDue, 2, '.', '') }}">
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

                    <style>
                        .notice-preview-sheet-wrap {
                            max-width: 210mm;
                            margin-left: auto;
                            margin-right: auto;
                        }
                    </style>

                    <!-- Notice Preview: two identical copies per account (cut-apart sheet, matches print) -->
                    @foreach($consumersWithOutstanding as $consumer)
                        <div class="card shadow mb-4" style="page-break-after: always; background: white;">
                            <div class="card-body d-flex justify-content-center" style="padding: 20px 12px;">
                                <div class="notice-preview-sheet-wrap notices-sheet-two-up w-100">
                                    @include('disconnection.partials.notice-document', [
                                        'consumer' => $consumer,
                                        'disconnectionDate' => $disconnectionDate,
                                        'compact' => true,
                                    ])
                                    @include('disconnection.partials.notice-document', [
                                        'consumer' => $consumer,
                                        'disconnectionDate' => $disconnectionDate,
                                        'compact' => true,
                                    ])
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
</body>
</html>

