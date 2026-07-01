@php
    $currentRoute = Route::currentRouteName() ?? '';
    $pageTourId = match ($currentRoute) {
        'home', 'dashboard' => 'dashboard',
        'consumer' => 'consumer',
        'billing-processes' => 'billing-processes',
        'meter-reading' => 'meter-reading',
        'download-reading' => 'download-reading',
        'disconnection.index' => 'disconnection',
        'billing-payment' => 'billing-payment',
        'billing-adjustment' => 'billing-adjustment',
        'systemreport' => 'systemreport',
        'billing-status' => 'billing-status',
        default => null,
    };
    $autoNavigationTour = in_array($currentRoute, ['home', 'dashboard'], true);
@endphp

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css">
<style>
    .driver-popover.ekwd-tour-popover {
        background: #ffffff;
        color: #334155;
        border-radius: 14px;
        box-shadow: 0 20px 50px rgba(59, 130, 246, 0.25);
        border: 1px solid #e2e8f0;
        max-width: 340px;
        z-index: 1000000001 !important;
        pointer-events: auto !important;
    }
    .driver-popover.ekwd-tour-popover .driver-popover-footer button {
        pointer-events: auto !important;
        cursor: pointer !important;
    }
    .driver-popover.ekwd-tour-popover .driver-popover-title {
        font-weight: 800;
        color: #1e293b;
        font-size: 1.05rem;
    }
    .driver-popover.ekwd-tour-popover .driver-popover-description {
        color: #64748b;
        font-size: 0.92rem;
        line-height: 1.5;
    }
    .driver-popover.ekwd-tour-popover .driver-popover-progress-text {
        color: #3b82f6;
        font-weight: 700;
    }
    .driver-popover.ekwd-tour-popover .driver-popover-next-btn,
    .driver-popover.ekwd-tour-popover .driver-popover-prev-btn,
    .driver-popover.ekwd-tour-popover .driver-popover-close-btn {
        border-radius: 8px;
        font-weight: 600;
        text-shadow: none;
    }
    .driver-popover.ekwd-tour-popover .driver-popover-next-btn {
        background: linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%);
        color: #fff;
        border: none;
    }
    .driver-popover.ekwd-tour-popover .driver-popover-prev-btn {
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #e2e8f0;
    }
    .driver-popover.ekwd-tour-popover .driver-popover-close-btn {
        color: #64748b;
    }
    #tourHelpBtn {
        color: #3b82f6 !important;
    }
    #tourHelpBtn:hover {
        color: #1e40af !important;
    }
    .tour-help-dropdown {
        min-width: 260px;
        max-height: min(80vh, 520px);
        overflow-y: auto;
        padding-bottom: 0.35rem;
    }
    .tour-help-dropdown .dropdown-header {
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        color: #64748b;
        padding-top: 0.65rem;
        padding-bottom: 0.35rem;
    }
    .tour-help-dropdown .dropdown-item {
        font-size: 0.9rem;
        padding: 0.55rem 1.1rem;
    }
    .tour-help-dropdown .dropdown-item i {
        width: 1.25rem;
        color: #3b82f6;
    }
    .tour-help-dropdown .quick-option-item {
        font-size: 0.86rem;
        padding: 0.45rem 1.1rem;
    }
    .tour-help-dropdown .quick-option-item.active {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.12) 0%, rgba(6, 182, 212, 0.08) 100%);
        color: #1e40af;
        font-weight: 600;
    }
    .tour-help-dropdown .quick-option-item:hover {
        background: rgba(59, 130, 246, 0.08);
    }
    #accordionSidebar .nav-item.ekwd-tour-nav-active > .nav-link,
    #accordionSidebar .nav-item.ekwd-tour-nav-active > .active-dashboard {
        box-shadow: 0 0 0 3px #3b82f6, 0 0 0 6px rgba(59, 130, 246, 0.28) !important;
        background: rgba(59, 130, 246, 0.14) !important;
        border-radius: 12px;
        position: relative;
        z-index: 10002;
    }
    #accordionSidebar .nav-item.ekwd-tour-nav-active {
        position: relative;
        z-index: 10001;
    }
</style>

<script>
    window.EKWD_TOUR_CONFIG = {
        userId: {{ auth()->id() ?? 'null' }},
        pageTourId: @json($pageTourId),
        autoNavigationTour: @json($autoNavigationTour),
        currentRoute: @json($currentRoute),
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js"></script>
<script src="{{ url('WDMS/js/onboarding-tours.js') }}?v=9"></script>
