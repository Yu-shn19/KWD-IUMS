<!-- Dynamic Consumer Header -->
<style>
  .consumer-page-header {
    position: sticky;
    top: 0;
    z-index: 1030;
    background: #fff;
    transition: box-shadow .2s ease-in-out;
  }
  .consumer-page-header.is-stuck > .card-header {
    box-shadow: 0 4px 8px rgba(0, 0, 0, .08);
  }
  .consumer-page-header .consumer-header-title h4 {
    font-size: 1.1rem;
    line-height: 1.35;
  }
  @media (min-width: 768px) {
    .consumer-page-header .consumer-header-title h4 {
      font-size: 1.5rem;
    }
  }
  .consumer-nav-tabs-scroll {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    margin: 0 -0.25rem;
    padding-bottom: 2px;
  }
  .consumer-nav-tabs-scroll .nav.nav-tabs {
    flex-wrap: nowrap;
    white-space: nowrap;
    display: flex;
    border-bottom: 1px solid #dee2e6;
  }
  .consumer-nav-tabs-scroll .nav.nav-tabs .nav-item {
    flex: 0 0 auto;
  }
  .consumer-nav-tabs-scroll .nav.nav-tabs .nav-link {
    white-space: nowrap;
    font-size: 0.85rem;
    padding: 0.5rem 0.65rem;
  }
  @media (min-width: 768px) {
    .consumer-nav-tabs-scroll .nav.nav-tabs .nav-link {
      font-size: 1rem;
      padding: 0.5rem 1rem;
    }
  }
</style>
<div class="card mb-3 consumer-page-header">
    <!-- Header -->
    <div class="card-header bg-primary text-white py-2 py-md-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-2 gap-md-3">
            <div class="d-flex align-items-start gap-2 min-w-0 flex-grow-1">
                <button type="button" class="btn btn-light btn-sm align-self-start d-md-none flex-shrink-0 shadow-sm" id="consumerMobileNavToggle" aria-label="Open menu">
                    <i class="fas fa-bars text-primary"></i>
                </button>
                <div id="consumerHeaderInfo" class="min-w-0 flex-grow-1">
                @if($consumer)
                    @php
                        $st = $consumer->status_label ?? $consumer->status_code ?? '';
                        $headerStatusBadge = match ($st) {
                            'Active' => 'success',
                            'Pending' => 'warning',
                            'Disconnected' => 'danger',
                            default => 'secondary',
                        };
                        $headerNameClass = match ($st) {
                            'Pending' => 'text-warning fw-semibold',
                            'Disconnected' => 'text-danger fw-semibold',
                            default => '',
                        };
                    @endphp
                    <h4 class="mb-1 text-break {{ $headerNameClass }} consumer-header-title" id="consumerHeaderName">{{ $consumer->account_no ?? '' }} {{ $consumer->account_name ?? '' }}</h4>
                    <p class="mb-1 small text-break" id="consumerHeaderAddress" style="color: #000 !important;">{{ trim($consumer->address ?? '') ?: '—' }}</p>
                    <span class="badge bg-{{ $headerStatusBadge }}" id="consumerHeaderStatus">{{ $consumer->status_label ?? $consumer->status_code ?? 'N/A' }} Consumer</span>
                      @if($consumer->isDisconnectedStatus())
                        @php
                            $headerDisconnectedAt = $consumer->latestDisconnectedAtForDisplay();
                        @endphp
                        <div class="mt-2 p-2 rounded bg-white text-danger small w-100 text-start shadow-sm" role="status" id="consumerDisconnectedNotice">
                            <i class="fas fa-unlink mr-1" aria-hidden="true"></i>
                            @if($headerDisconnectedAt)
                                This consumer was Disconnected at {{ $headerDisconnectedAt->format('M d, Y h:i A') }}.
                            @else
                                This consumer was Disconnected; no disconnect timestamp was recorded in disconnection orders.
                            @endif
                        </div>
                    @endif
                 @else
                    <h4 class="mb-1 consumer-header-title" id="consumerHeaderName">No Consumer Selected</h4>
                    <p class="mb-1 small" id="consumerHeaderAddress" style="color: #000 !important;">—</p>
                    <span class="badge bg-secondary" id="consumerHeaderStatus">Please search for a consumer</span>
                @endif
                </div>
            </div>
            <div class="text-md-end flex-shrink-0 align-self-center align-self-md-start pt-1 pt-md-0 d-none d-md-block">
                <h5 class="mb-0">Consumers</h5>
                <small>Management System</small>
            </div>
            <div class="text-center d-md-none border-top border-white border-opacity-25 pt-2 w-100">
                <span class="small opacity-90">Consumers — Management System</span>
            </div>
        </div>
    </div>
</div>
@if(isset($consumer) && $consumer)
@php
    $consumerPayloadForSession = array_merge($consumer->toArray(), [
        'latest_disconnected_at' => optional($consumer->latestDisconnectedAtForDisplay())->toIso8601String(),
    ]);
@endphp
<script>
     sessionStorage.setItem('currentConsumer', JSON.stringify(@json($consumerPayloadForSession)));
</script>
@endif
<!-- Consumer Search Box -->
<div class="card mb-3">
    <div class="card-body py-2 px-2 px-md-3">
        <div class="d-flex justify-content-center w-100">
            <div class="input-group position-relative w-100" style="max-width: 800px;">
                <span class="input-group-text flex-shrink-0">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" class="form-control" id="consumerSearch" placeholder="Search by Name, Account Number, Meter..." autocomplete="off">
                <div id="consumerSuggestions" class="list-group" style="position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; max-height: 300px; overflow-y: auto; display: none; margin-top: 2px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px; background: white; border: 1px solid #ddd;"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('consumerMobileNavToggle');
    if (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        document.body.classList.toggle('sidebar-toggled');
        var sb = document.querySelector('#wrapper .navbar-nav.sidebar');
        if (sb) sb.classList.toggle('toggled');
      });
    }

    var stickyCard = document.querySelector('.consumer-page-header');
    if (stickyCard && 'IntersectionObserver' in window && !stickyCard.dataset.stickyBound) {
      stickyCard.dataset.stickyBound = '1';
      var sentinel = document.createElement('div');
      sentinel.setAttribute('aria-hidden', 'true');
      sentinel.style.cssText = 'height:1px;width:100%;';
      stickyCard.parentNode.insertBefore(sentinel, stickyCard);
      new IntersectionObserver(function (entries) {
        stickyCard.classList.toggle('is-stuck', !entries[0].isIntersecting);
      }, { threshold: 0 }).observe(sentinel);
    }
  });
})();
</script>

<!-- F1 Search Modal (shared across all consumer tabs) -->
<div class="modal fade" id="f1SearchModal" tabindex="-1" aria-labelledby="f1SearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="f1SearchModalLabel">
                    <i class="fas fa-search me-2"></i>Search Consumer
                </h5>
                <button type="button" class="btn-close btn-close-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="f1SearchInput" class="form-label">Search by Name, Account Number, Meter...</label>
                    <div class="position-relative">
                        <input type="text" class="form-control" id="f1SearchInput" placeholder="Search by Name, Account Number, Meter..." autocomplete="off">
                        <div id="f1SearchSuggestions" class="list-group position-absolute w-100" style="max-height: 300px; overflow-y: auto; z-index: 1000; display: none; top: 100%; margin-top: 2px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px; background: white; border: 1px solid #ddd;">
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">Minimum 2 characters required</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>
