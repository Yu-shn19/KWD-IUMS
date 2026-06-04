@php
    $cards = $cards ?? [];
    $charts = $charts ?? [];
    $recentMessages = collect($recentMessages ?? []);

    $consumptionChart = [
        'labels' => $charts['consumption']['labels'] ?? [],
        'data' => $charts['consumption']['data'] ?? [],
    ];

    $billingChart = [
        'labels' => $charts['billing_status']['labels'] ?? ['Paid', 'Unpaid'],
        'data' => $charts['billing_status']['data'] ?? [0, 0],
        'total' => (int) ($charts['billing_status']['total'] ?? 0),
        'period' => $charts['billing_status']['period'] ?? now()->format('F Y'),
    ];
@endphp

<style>
    /* ===== PREMIUM COLOR PALETTE ===== */
    :root{
        --primary: #3b82f6;           /* Bright Blue */
        --primary-dark: #1e40af;      /* Dark Blue */
        --primary-light: #60a5fa;     /* Light Blue */
        --secondary: #06b6d4;         /* Cyan/Teal */
        --secondary-light: #22d3ee;   /* Light Cyan */
        --accent: #f59e0b;            /* Amber */
        --success: #10b981;           /* Green */
        --danger: #ef4444;            /* Red */
        --dark: #1e293b;              /* Dark Slate */
        --text: #334155;              /* Slate */
        --text-light: #64748b;        /* Light Slate */
        --border: #e2e8f0;            /* Light Border */
        --bg: #f8fafc;                /* Light Background */
        --card: #ffffff;

        /* Gradients */
        --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        --gradient-warm: linear-gradient(135deg, var(--accent) 0%, #f97316 100%);
        --gradient-cool: linear-gradient(135deg, var(--secondary) 0%, #0891b2 100%);
    }

    /* Page Wrapper */
    #container-wrapper {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }

    body { 
        background: var(--bg); 
        color: var(--text);
    }

    .page-hero{
        background: 
            radial-gradient(900px 400px at 10% 0%, rgba(59, 130, 246, .15), transparent 60%),
            radial-gradient(800px 400px at 100% 0%, rgba(6, 182, 212, .12), transparent 55%),
            linear-gradient(180deg, #ffffff, rgba(255, 255, 255, .8));
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 28px 24px;
        box-shadow: 0 10px 30px rgba(59, 130, 246, 0.12);
        margin-bottom: 2rem;
        width: 100%;
        box-sizing: border-box;
    }

    .crumb a{ 
        color: var(--text-light); 
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .crumb a:hover {
        color: var(--primary);
    }

    .crumb .active { 
        color: var(--dark); 
        font-weight: 700; 
    }

    .kpi-card{
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.08);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        position: relative;
    }
    
    .kpi-card:hover{
        transform: translateY(-6px);
        box-shadow: 0 16px 40px rgba(59, 130, 246, 0.15);
        border-color: var(--primary);
    }
    
    .kpi-topbar{
        height: 4px;
        background: var(--gradient-primary);
    }
    
    .kpi-title{ 
        letter-spacing: .08em; 
        color: var(--text-light); 
        font-size: .75rem; 
        font-weight: 700;
        text-transform: uppercase;
    }
    
    .kpi-value{ 
        color: var(--dark); 
        font-size: 1.5rem; 
        font-weight: 900;
        margin: 0.5rem 0;
    }
    
    .kpi-sub{ 
        color: var(--text-light); 
        font-size: .85rem;
    }
    
    .kpi-chip{
        font-size: .75rem;
        padding: 6px 12px;
        border-radius: 25px;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(6, 182, 212, 0.1));
        color: var(--primary-dark);
        font-weight: 700;
        border: 1px solid rgba(59, 130, 246, 0.2);
        display: inline-block;
    }

    .panel{
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.08);
        transition: all 0.3s ease;
    }

    .panel:hover {
        box-shadow: 0 12px 32px rgba(59, 130, 246, 0.12);
    }

    .panel-header{
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap: 12px;
    }
    
    .panel-title{
        margin: 0;
        font-weight: 800;
        color: var(--dark);
        font-size: 1rem;
    }
    
    .panel-body{ 
        padding: 16px 20px;
    }

    .panel-actions .btn{
        border-radius: 10px;
        border: 1px solid var(--border);
        background: var(--card);
        color: var(--text);
        font-weight: 700;
        padding: 8px 14px;
        font-size: .8rem;
        transition: all 0.3s ease;
    }

    .panel-actions .btn:hover {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(6, 182, 212, 0.08));
        border-color: var(--primary);
        color: var(--primary);
    }

    .panel-actions .btn:focus{ 
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); 
    }

    .chart-wrap{ height: 320px; }
    .chart-wrap-sm{ height: 280px; }

    .message-item{
        display:flex;
        gap: 14px;
        padding: 14px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: var(--card);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .message-item:hover{
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(59, 130, 246, 0.12);
        border-color: var(--primary);
    }

    .avatar{
        width: 44px; 
        height: 44px; 
        border-radius: 12px;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(6, 182, 212, 0.15));
        display:flex; 
        align-items:center; 
        justify-content:center;
        color: var(--primary-dark);
        font-weight: 900;
        font-size: 1rem;
    }

    .msg-title{ 
        font-weight: 800; 
        color: var(--dark); 
        margin-bottom: 4px;
        font-size: 0.95rem;
    }

    .msg-text{ 
        color: var(--text-light); 
        margin: 0;
        font-size: 0.9rem;
    }

    .msg-time{ 
        color: var(--text-light); 
        font-size: .8rem; 
        margin-top: 6px;
        font-weight: 500;
    }

    .link-soft{
        color: var(--primary-dark);
        font-weight: 800;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .link-soft:hover{ 
        color: var(--primary);
        text-decoration: underline; 
    }

    /* Breadcrumb */
    .breadcrumb{ 
        background: transparent; 
        padding: 0; 
        margin: 0;
    }

    /* Alerts */
    .alert {
        border: 1px solid var(--border);
        border-radius: 12px;
        background-color: rgba(59, 130, 246, 0.05);
        color: var(--text);
    }

    .alert-info {
        border-color: var(--primary);
        background-color: rgba(59, 130, 246, 0.08);
    }

    .alert-success {
        border-color: var(--success);
        background-color: rgba(16, 185, 129, 0.08);
    }

    .alert-warning {
        border-color: var(--accent);
        background-color: rgba(245, 158, 11, 0.08);
    }

    .alert-danger {
        border-color: var(--danger);
        background-color: rgba(239, 68, 68, 0.08);
    }

    /* Footer Card */
    .card-footer {
        background: transparent;
        border-top: 1px solid var(--border);
        padding: 1rem;
    }
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4 page-hero">
    <div>
        <div class="d-flex align-items-center gap-2">
            <span class="kpi-chip">Dashboard</span>
        </div>
        <h1 class="h3 mb-0" style="font-weight:900; color: var(--dark);">
            Hagonoy Water District
        </h1>
        <div style="color:var(--text-light); font-size:.95rem; margin-top: 0.5rem;">Overview of performance and customer activity</div>
    </div>

    <ol class="breadcrumb crumb mt-3 mt-sm-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
    </ol>
</div>

{{-- KPI Cards --}}
<div class="row mb-3">
    @forelse ($cards as $card)
        @php
            $value = $card['value'] ?? 0;
            $format = $card['format'] ?? 'number';
            $formattedValue = $format === 'currency'
                ? '₱' . number_format($value, 2)
                : number_format($value);

            $changePercent = $card['change_percent'] ?? 0;
            $trend = $card['trend'] ?? 'up';
            $isUp = $trend !== 'down';
            $changeClass = $isUp ? 'text-success' : 'text-danger';
            $changeIcon = $isUp ? 'fa-arrow-up' : 'fa-arrow-down';
            $subtitle = $card['subtitle'] ?? 'vs last month';

            // Optional "progress bar" feel (clamp between 0-100)
            $progress = max(0, min(100, abs($changePercent)));
        @endphp

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="kpi-card h-100">
                <div class="kpi-topbar"></div>
                <div class="p-3">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="pr-2">
                            <div class="kpi-title text-uppercase mb-1">{{ $card['title'] }}</div>
                            <div class="kpi-value">{{ $formattedValue }}</div>

                            <div class="mt-2 kpi-sub">
                                <span class="{{ $changeClass }} mr-2" style="font-weight:800;">
                                    <i class="fa {{ $changeIcon }}"></i>
                                    {{ number_format($changePercent, 1) }}%
                                </span>
                                <span>{{ $subtitle }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="kpi-chip">
                                <i class="fas fa-{{ $card['icon'] ?? 'chart-line' }} mr-1"></i>
                                Live
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="d-flex align-items-center justify-content-between" style="font-size:.75rem; color:var(--muted);">
                            <span>Trend</span>
                            <span>{{ $isUp ? 'Improving' : 'Declining' }}</span>
                        </div>
                        <div class="progress" style="height: 7px; border-radius: 999px; background: rgba(15,23,42,.06);">
                            <div class="progress-bar" role="progressbar"
                                 style="width: {{ $progress }}%; border-radius: 999px; background: linear-gradient(90deg, var(--brand), rgba(37,99,235,.25));"
                                 aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-info mb-4">
                Dashboard metrics are not available at the moment. Please check back later.
            </div>
        </div>
    @endforelse
</div>

{{-- Charts --}}
<div class="row">
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="panel h-100">
            <div class="panel-header">
                <div>
                    <h6 class="panel-title">Monthly Water Consumption (m³)</h6>
                    <div style="color:var(--muted); font-size:.82rem;">Hover points to see exact readings</div>
                </div>

                <div class="panel-actions d-flex align-items-center gap-2">
                    <button class="btn btn-sm" type="button" onclick="resetConsumptionZoom()">Reset</button>
                    <button class="btn btn-sm" type="button" onclick="toggleConsumptionFilled()">Fill</button>
                </div>
            </div>
            <div class="panel-body">
                <div class="chart-wrap">
                    <canvas id="consumptionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="panel h-100">
            <div class="panel-header">
                <div>
                    <h6 class="panel-title">Billing Status</h6>
                    <div style="color:var(--muted); font-size:.82rem;">
                        As of {{ $billingChart['period'] }}: {{ number_format($billingChart['total']) }} billed accounts
                    </div>
                </div>
                <div class="panel-actions">
                    <button class="btn btn-sm" type="button" onclick="animateBilling()">Animate</button>
                </div>
            </div>
            <div class="panel-body">
                <div class="chart-wrap-sm">
                    <canvas id="billingChart"></canvas>
                </div>

                <div class="mt-3 text-center small">
                    @foreach ($billingChart['labels'] as $index => $label)
                        @php
                            $normalizedLabel = strtolower(trim((string) $label));
                            $colorClass = match ($normalizedLabel) {
                                'paid' => 'text-success',
                                'unpaid', 'overdue' => 'text-danger',
                                default => 'text-warning',
                            };
                        @endphp
                        <span class="mr-3">
                            <i class="fas fa-circle {{ $colorClass }}"></i> {{ $label }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Recent Messages --}}
<div class="row">
    <div class="col-xl-12 mb-4">
        <div class="panel">
            <div class="panel-header" style="background: linear-gradient(90deg, rgba(37,99,235,.10), rgba(29,78,216,.04));">
                <div>
                    <h6 class="panel-title">Recent Consumer Messages</h6>
                    <div style="color:var(--muted); font-size:.82rem;">Latest feedback and service notes</div>
                </div>
                <div class="panel-actions d-flex align-items-center gap-2">
                    <input id="messageSearch" type="text" class="form-control form-control-sm"
                           placeholder="Search messages..."
                           style="border-radius: 999px; width: 220px; border: 1px solid rgba(15,23,42,.10);">
                </div>
            </div>

            <div class="panel-body">
                <div id="messagesContainer" class="d-grid" style="gap: 10px;">
                    @forelse ($recentMessages as $message)
                        @php
                            $acct = $message['account'] ?? 'Unknown';
                            $initials = collect(explode(' ', $acct))->take(2)->map(fn($p)=> strtoupper(substr($p,0,1)))->join('');
                        @endphp

                        <div class="message-item" data-msg="{{ strtolower(($message['account'] ?? '') . ' ' . ($message['message'] ?? '')) }}">
                            <div class="avatar">{{ $initials ?: 'C' }}</div>
                            <div class="flex-grow-1" style="min-width:0;">
                                <div class="msg-title text-truncate">{{ $acct }}</div>
                                <p class="msg-text text-truncate">“{{ $message['message'] ?? '' }}”</p>
                                <div class="msg-time">{{ $message['timestamp'] ?? '' }}</div>
                            </div>
                            <div class="text-right">
                                <button class="btn btn-sm" style="border-radius:999px;" type="button"
                                        onclick="quickReply('{{ addslashes($acct) }}')">
                                    Reply
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No recent messages or service notes recorded.</p>
                    @endforelse
                </div>
            </div>

            <div class="card-footer text-center" style="background: transparent; border-top: 1px solid rgba(15,23,42,.06);">
                <a class="m-0 small link-soft" href="#">
                    View All Messages <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const consumptionConfig = @json($consumptionChart);
    const billingConfig = @json($billingChart);
    const billingColorByLabel = {
        paid: '#22c55e',
        pending: '#f59e0b',
        unpaid: '#ef4444',
        overdue: '#ef4444'
    };
    const billingSliceColors = (billingConfig.labels || []).map((label) => {
        const key = String(label || '').trim().toLowerCase();
        return billingColorByLabel[key] || '#94a3b8';
    });

    // ---------- Chart.js defaults (cleaner + modern) ----------
    Chart.defaults.font.family = "'Nunito', system-ui, -apple-system, Segoe UI, Roboto, Arial";
    Chart.defaults.color = "#475569"; // slate-600

    let consumptionChartInstance = null;
    let billingChartInstance = null;
    let consumptionFilled = true;

    // ---------- Consumption Line ----------
    const consumptionCtx = document.getElementById('consumptionChart');
    if (consumptionCtx) {
        consumptionChartInstance = new Chart(consumptionCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: consumptionConfig.labels,
                datasets: [{
                    label: 'Consumption (m³)',
                    data: consumptionConfig.data,
                    fill: true,
                    backgroundColor: 'rgba(37, 99, 235, 0.10)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: 'rgba(37, 99, 235, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    borderWidth: 2,
                    tension: 0.35,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 12,
                        backgroundColor: 'rgba(15,23,42,.92)',
                        titleColor: '#fff',
                        bodyColor: '#e2e8f0',
                        displayColors: false,
                        callbacks: {
                            label: (ctx) => ` ${ctx.parsed.y?.toLocaleString()} m³`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(15,23,42,.06)' },
                        ticks: {
                            callback: (v) => v.toLocaleString()
                        }
                    }
                }
            }
        });
    }

    function toggleConsumptionFilled() {
        if (!consumptionChartInstance) return;
        consumptionFilled = !consumptionFilled;
        consumptionChartInstance.data.datasets[0].fill = consumptionFilled;
        consumptionChartInstance.update();
    }

    function resetConsumptionZoom() {
        // Placeholder if you later add zoom plugin.
        if (!consumptionChartInstance) return;
        consumptionChartInstance.reset();
    }

    // ---------- Billing Doughnut ----------
    const billingCtx = document.getElementById('billingChart');
    if (billingCtx) {
        billingChartInstance = new Chart(billingCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: billingConfig.labels,
                datasets: [{
                    data: billingConfig.data,
                    backgroundColor: billingSliceColors,
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, pointStyle: 'circle', padding: 16 }
                    },
                    tooltip: {
                        padding: 12,
                        backgroundColor: 'rgba(15,23,42,.92)',
                        titleColor: '#fff',
                        bodyColor: '#e2e8f0',
                        callbacks: {
                            label: (ctx) => ` ${ctx.label}: ${Number(ctx.parsed).toLocaleString()}`
                        }
                    }
                }
            }
        });
    }

    function animateBilling(){
        if (!billingChartInstance) return;
        billingChartInstance.update('active');
    }

    // ---------- Messages Search ----------
    const search = document.getElementById('messageSearch');
    const container = document.getElementById('messagesContainer');

    if (search && container) {
        search.addEventListener('input', (e) => {
            const q = (e.target.value || '').toLowerCase().trim();
            container.querySelectorAll('.message-item').forEach(item => {
                const hay = item.getAttribute('data-msg') || '';
                item.style.display = hay.includes(q) ? '' : 'none';
            });
        });
    }

    function quickReply(account){
        // Replace with modal / route later
        alert("Reply to: " + account + "\\n\\n(Connect this to your messaging module.)");
    }
</script>
