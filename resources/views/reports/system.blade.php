<!DOCTYPE html>
<html lang="en">
@include('partials.header')
@php
    $reportCards = [
        [
            'title' => 'Consumer Master List',
            'description' => 'Complete profile of registered concessionaires, contact details, and meter assignments.',
            'route' => 'consumer-master-list',
            'icon' => 'users',
            'badge' => 'Most used',
        ],
        [
            'title' => 'Monthly Billing Report',
            'description' => 'Monthly consumption, charges, and billing trends to support revenue analysis.',
            'route' => 'monthly-billing-report',
            'icon' => 'file-invoice-dollar',
        ],
        [
            'title' => 'Collection Report',
            'description' => 'Cashiering summary across payment channels, tellers, and posting dates.',
            'route' => 'collection-report',
            'icon' => 'cash-register',
        ],
        [
            'title' => 'Adjustment Report',
            'description' => 'Audit trail of bill adjustments, credit memos, and approval history.',
            'route' => 'adjustment-report',
            'icon' => 'balance-scale',
        ],
        [
            'title' => 'Penalty Charges',
            'description' => 'Accounts with incurred penalties plus the supporting amounts and references.',
            'route' => 'penalty-report',
            'icon' => 'exclamation-circle',
        ],
        [
            'title' => 'Consumer Ledger Report',
            'description' => 'Traditional ledger card per account with billings, payments, and running balances. Multi-sheet Excel export.',
            'route' => 'service-request-report',
            'icon' => 'book',
        ],
        [
            'title' => 'A/R Aging Summary',
            'description' => 'Receivables aging buckets to help focus collection strategies and follow-ups.',
            'route' => 'ar-aging-summary',
            'icon' => 'hourglass-half',
        ],
            [
            'title' => 'Disconnected consumer',
            'description' => 'Historical disconnections by actual disconnect date and time, with export to Excel.',
            'route' => 'disconnection.assignments',
            'icon' => 'unlink',
        ],
        [
            'title' => 'Visual Summary',
            'description' => 'Executive dashboards combining KPIs, trend charts, and actionable insights.',
            'route' => 'visual-summary',
            'icon' => 'chart-line',
        ],
    ];
@endphp

<style>
    .reports-hero {
        background: linear-gradient(135deg, rgba(78, 115, 223, 0.12), rgba(54, 185, 204, 0.12));
        border-radius: 1.5rem;
    }

    .report-card {
        display: block;
        height: 100%;
        background: #ffffff;
        border-radius: 1.15rem;
        padding: 1.65rem;
        color: #1b1e23;
        border: 1px solid rgba(226, 232, 240, 0.9);
        box-shadow: 0 1.25rem 2.5rem -1.5rem rgba(30, 41, 59, 0.45);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        position: relative;
    }

    .report-card:hover {
        transform: translateY(-6px);
        border-color: rgba(78, 115, 223, 0.5);
        box-shadow: 0 1.75rem 3.5rem -1.25rem rgba(30, 41, 59, 0.55);
        text-decoration: none;
        color: inherit;
    }

    .report-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.45rem;
        background: rgba(78, 115, 223, 0.14);
        color: #4e73df;
    }

    .report-card h5 {
        font-weight: 700;
        margin-top: 1.25rem;
        margin-bottom: 0.8rem;
        font-size: 1.06rem;
    }

    .report-card p {
        font-size: 0.92rem;
        color: #586174;
        min-height: 58px;
        margin-bottom: 1.4rem;
    }

    .report-card .report-footer {
        display: inline-flex;
        align-items: center;
        font-weight: 600;
        color: #4e73df;
        letter-spacing: 0.01em;
    }

    .report-card .report-footer i {
        margin-left: 0.45rem;
        transition: transform 0.18s ease;
    }

    .report-card:hover .report-footer i {
        transform: translateX(5px);
    }

    .report-badge {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        background: rgba(28, 200, 138, 0.16);
        color: #1cc88a;
        font-size: 0.7rem;
        text-transform: uppercase;
        font-weight: 700;
        padding: 0.3rem 0.7rem;
        border-radius: 999px;
        letter-spacing: 0.08em;
    }
</style>

<body id="page-top">
    <div id="wrapper">
        @include('partials.sidebar')
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('partials.navbar')

                <div class="container-fluid" id="container-wrapper">
                    <div class="reports-hero p-4 p-md-5 mb-4 shadow-sm">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                            <div class="mb-3 mb-md-0 pr-md-5">
                                <span class="badge badge-primary badge-pill mb-2 text-uppercase">Data Intelligence</span>
                                <h1 class="h3 mb-2 text-gray-800 font-weight-bold">System Reports</h1>
                                <p class="mb-0 text-muted">
                                    Navigate a curated collection of operational and financial reports. Every report is designed to help you
                                    reconcile billing, monitor payments, and act quickly on customer activity.
                                </p>
                            </div>
                            <div class="text-md-right">
                                <div class="text-uppercase text-muted small font-weight-bold mb-2">Need assistance?</div>
                                <a href="mailto:support@hwd.gov" class="btn btn-outline-primary btn-sm shadow-sm">
                                    <i class="fas fa-life-ring mr-1"></i>Contact Support
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        @foreach ($reportCards as $report)
                            <div class="col-xl-4 col-md-6 mb-4">
                                <a href="{{ route($report['route']) }}" class="report-card">
                                    @if (!empty($report['badge']))
                                        <span class="report-badge">{{ $report['badge'] }}</span>
                                    @endif
                                    <span class="report-icon">
                                        <i class="fas fa-{{ $report['icon'] }}"></i>
                                    </span>
                                    <h5 class="text-dark">{{ $report['title'] }}</h5>
                                    <p>{{ $report['description'] }}</p>
                                    <div class="report-footer">
                                        Open report <i class="fas fa-arrow-right"></i>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>

                    <div class="card border-0 shadow-sm mt-2">
                        <div class="card-body p-4">
                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                                <div class="mb-3 mb-md-0">
                                    <h6 class="text-uppercase text-muted small mb-1">Quick tip</h6>
                                    <p class="mb-0 text-muted">
                                        Bookmark your frequently accessed reports for one-click access from the browser toolbar.
                                        Need a custom report? Reach out to the support team and we’ll schedule it for you.
                                    </p>
                                </div>
                                <a href="{{ route('visual-summary') }}" class="btn btn-primary btn-sm shadow-sm">
                                    <i class="fas fa-tachometer-alt mr-1"></i>Launch dashboard
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            @include('partials.footer')
        </div>
    </div>

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>
</body>

</html>
