{{-- Consumer sub-pages: keep ?account= on all tab links so the header reloads with the same consumer. --}}
@php
    $acctParam = (isset($consumer) && $consumer && !empty($consumer->account_no))
        ? '?account=' . urlencode($consumer->account_no)
        : '';
    $active = $activeTab ?? '';
@endphp
<div class="card-body p-0">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a href="{{ route('consumer') }}{{ $acctParam }}" class="nav-link {{ $active === 'consumer' ? 'active' : '' }}" id="consumerDetailsTab">
                <i class="fas fa-user me-1"></i>Consumer Details
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('ledger') }}{{ $acctParam }}" class="nav-link {{ $active === 'ledger' ? 'active' : '' }}">
                <i class="fas fa-file-invoice me-1"></i>Account Ledger
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('lro-ledger') }}{{ $acctParam }}" class="nav-link {{ $active === 'lro-ledger' ? 'active' : '' }}">
                <i class="fas fa-list me-1"></i>LRO Ledger
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('service') }}{{ $acctParam }}" class="nav-link {{ $active === 'service' ? 'active' : '' }}">
                <i class="fas fa-history me-1"></i>Service History
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('meter') }}{{ $acctParam }}" class="nav-link {{ $active === 'meter' ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt me-1"></i>Meter Reading
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('location') }}{{ $acctParam }}" class="nav-link {{ $active === 'location' ? 'active' : '' }}">
                <i class="fas fa-map-marker-alt me-1"></i>Location Map
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('consumption') }}{{ $acctParam }}" class="nav-link {{ $active === 'consumption' ? 'active' : '' }}">
                <i class="fas fa-chart-line me-1"></i>Consumption Graph
            </a>
        </li>
    </ul>
</div>
