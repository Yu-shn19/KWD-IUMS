<!-- Dynamic Consumer Header -->
<div class="card mb-3">
    <!-- Header -->
    <div class="card-header bg-primary text-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <div id="consumerHeaderInfo">
                @if ($consumer)
                    @php
                        $statusRaw = strtoupper(
                            trim((string) ($consumer->status_code ?? ($consumer->status_label ?? ''))),
                        );
                        $statusCode = match ($statusRaw) {
                            'A', 'ACTIVE' => 'A',
                            'P', 'PENDING' => 'P',
                            'X', 'D', 'DISCONNECTED' => 'X',
                            default => 'N/A',
                        };
                        $headerStatusBadge = match ($statusCode) {
                            'A' => 'success',
                            'P' => 'warning',
                            'X' => 'danger',
                            default => 'secondary',
                        };
                        $headerNameClass = match ($statusCode) {
                            'P' => 'text-warning fw-semibold',
                            'X' => 'text-danger fw-semibold',
                            default => '',
                        };
                    @endphp
                    <h4 class="mb-1 {{ $headerNameClass }}" id="consumerHeaderName">{{ $consumer->account_no ?? '' }}
                        {{ $consumer->account_name ?? '' }}</h4>
                    <p class="mb-1 small" id="consumerHeaderAddress" style="color: #000 !important;">
                        {{ trim(($consumer->address1 ?? '') . ' ' . ($consumer->address2 ?? ($consumer->address_2 ?? ''))) ?: '—' }}
                    </p>
                    <span class="badge bg-{{ $headerStatusBadge }}" id="consumerHeaderStatus">{{ $statusCode }}
                        Consumer</span>
                @else
                    <h4 class="mb-1" id="consumerHeaderName">No Consumer Selected</h4>
                    <p class="mb-1 small" id="consumerHeaderAddress" style="color: #000 !important;">—</p>
                    <span class="badge bg-secondary" id="consumerHeaderStatus">Please search for a consumer</span>
                @endif

            </div>
            <div class="text-end">
                <h5 class="mb-0">Consumers</h5>
                <small>Management System</small>
            </div>
        </div>
    </div>
</div>
@if(isset($consumer) && $consumer)
<script>
    sessionStorage.setItem('currentConsumer', JSON.stringify(@json($consumer)));
</script>
@endif
<!-- Consumer Search Box -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex justify-content-center">
            <div class="input-group" style="width: 800px; position: relative;">
                <span class="input-group-text">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" class="form-control" id="consumerSearch" placeholder="Search by Name, Account Number, Meter..." autocomplete="off">
                <div id="consumerSuggestions" class="list-group" style="position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; max-height: 300px; overflow-y: auto; display: none; margin-top: 2px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px; background: white; border: 1px solid #ddd;"></div>
            </div>
        </div>
    </div>
</div>

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
