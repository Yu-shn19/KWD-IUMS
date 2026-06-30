 <!-- Sidebar -->
 <ul class="navbar-nav sidebar sidebar-light accordion modern-sidebar" id="accordionSidebar">
  <!-- Brand Section -->
  <a class="sidebar-brand" href="/">
    <div class="sidebar-brand-icon">
      <img src="{{url('WDMS/img/logo/logo2.png')}}" alt="Logo" class="brand-logo">
    </div>
    <div class="sidebar-brand-text">eKWD-IUMS</div>
  </a>
  <div class="sidebar-divider-modern"></div>

  <!-- Dashboard -->
  <li class="nav-item">
    <a class="nav-link active-dashboard" href="/">
      <i class="fas fa-fw fa-home"></i>
      <span>Dashboard</span>
      <span class="nav-link-badge">Home</span>
    </a>
  </li>

  <div class="sidebar-divider-modern"></div>

  <!-- Main Menu Heading -->
  <div class="sidebar-heading-modern">
    <i class="fas fa-fw fa-bars"></i> MENU
  </div>

  <!-- Files Section -->
  <li class="nav-item">
    <a class="nav-link menu-link" href="#" data-toggle="collapse" data-target="#collapseBootstrap"
      aria-expanded="false" aria-controls="collapseBootstrap">
      <i class="far fa-fw fa-folder"></i>
      <span>Files</span>
      <i class="fas fa-chevron-right chevron-icon"></i>
    </a>
    <div id="collapseBootstrap" class="collapse" aria-labelledby="headingBootstrap" data-parent="#accordionSidebar">
      <div class="collapse-inner-modern">
           <a class="collapse-item" href="{{ route('consumer') }}">
          <i class="fas fa-circle small-icon"></i> Consumers
        </a>
        <a class="collapse-item" href="{{ route('consumer.import') }}">
          <i class="fas fa-circle small-icon"></i> Import Consumer Master List
        </a>
        <a class="collapse-item" href="#">
          <i class="fas fa-circle small-icon"></i> Category/Routes
        </a>
        <a class="collapse-item" href="#">
          <i class="fas fa-circle small-icon"></i> Zone/Block
        </a>
        <a class="collapse-item" href="#">
          <i class="fas fa-circle small-icon"></i> Services
        </a>
        <a class="collapse-item" href="#">
          <i class="fas fa-circle small-icon"></i> Sundies Account Title
        </a>
      </div>
    </div>
  </li>

  <!-- Transactions Section -->
  <li class="nav-item">
    <a class="nav-link menu-link" href="#" data-toggle="collapse" data-target="#collapseForm"
      aria-expanded="false" aria-controls="collapseForm">
      <i class="fas fa-fw fa-credit-card"></i>
      <span>Transactions</span>
      <i class="fas fa-chevron-right chevron-icon"></i>
    </a>
    <div id="collapseForm" class="collapse" aria-labelledby="headingForm" data-parent="#accordionSidebar">
      <div class="collapse-inner-modern">
        <a class="collapse-item" href="#">
          <i class="fas fa-circle small-icon"></i> Service Request
        </a>
        <a class="collapse-item" href="{{ route('billing-adjustment') }}">
          <i class="fas fa-circle small-icon"></i> Billing Adjustment
        </a>
        <a class="collapse-item" href="{{ route('billing-payment') }}">
          <i class="fas fa-circle small-icon"></i> Bill Payments/Collection
        </a>
        <a class="collapse-item" href="{{ route('collection.import') }}">
          <i class="fas fa-circle small-icon"></i> Import Collection
        </a>
        <a class="collapse-item" href="{{ route('lro-ledger.import') }}">
          <i class="fas fa-circle small-icon"></i> Import LRO Ledger
        </a>
      </div>
    </div>
  </li>

  <!-- Process Section -->
  <li class="nav-item">
    <a class="nav-link menu-link" href="#" data-toggle="collapse" data-target="#collapseTable"
      aria-expanded="false" aria-controls="collapseTable">
      <i class="fas fa-fw fa-cogs"></i>
      <span>Process</span>
      <i class="fas fa-chevron-right chevron-icon"></i>
    </a>
    <div id="collapseTable" class="collapse" aria-labelledby="headingTable" data-parent="#accordionSidebar">
      <div class="collapse-inner-modern">
        <a class="collapse-item" href="/billing-processes">
          <i class="fas fa-circle small-icon"></i> Billing Process
        </a>
        <a class="collapse-item" href="/meter-reading">
          <i class="fas fa-circle small-icon"></i> Meter Reading Assignment
        </a>
        <a class="collapse-item" href="{{ route('download-reading') }}">
          <i class="fas fa-circle small-icon"></i> Download Reading
        </a>
        <a class="collapse-item" href="{{ route('disconnection.index') }}">
          <i class="fas fa-circle small-icon"></i> Disconnection Management
        </a>
      </div>
    </div>
  </li>

  <!-- Reports Section -->
  <li class="nav-item">
    <a class="nav-link menu-link" href="#" data-toggle="collapse" data-target="#collapseReport"
      aria-expanded="false" aria-controls="collapseReport">
      <i class="fas fa-fw fa-chart-bar"></i>
      <span>Reports</span>
      <i class="fas fa-chevron-right chevron-icon"></i>
    </a>
    <div id="collapseReport" class="collapse" aria-labelledby="headingTable" data-parent="#accordionSidebar">
      <div class="collapse-inner-modern">
              <a class="collapse-item" href="{{ route('billing-status') }}">
          <i class="fas fa-circle small-icon"></i> Billing Status
        </a>
         <a class="collapse-item" href="{{ route('systemreport') }}">
          <i class="fas fa-circle small-icon"></i> System Reports
        </a>
         <a class="collapse-item" href="{{ route('disconnection.assignments') }}">
          <i class="fas fa-circle small-icon"></i> Disconnected consumer
        </a>
      </div>
    </div>
  </li>

  <!-- System Options Section -->
  <li class="nav-item">
    <a class="nav-link menu-link" href="#" data-toggle="collapse" data-target="#collapseOptions"
      aria-expanded="false" aria-controls="collapseOptions">
      <i class="fas fa-fw fa-sliders-h"></i>
      <span>System Options</span>
      <i class="fas fa-chevron-right chevron-icon"></i>
    </a>
    <div id="collapseOptions" class="collapse" aria-labelledby="headingTable" data-parent="#accordionSidebar">
      <div class="collapse-inner-modern">
              <a class="collapse-item" href="#">
          <i class="fas fa-circle small-icon"></i> System Setting
        </a>
        <a class="collapse-item" href="{{ route('settings.consumer-edit-pin') }}">
          <i class="fas fa-circle small-icon"></i> Consumer Edit PIN
        </a>
        <a class="collapse-item" href="{{ route('user-management') }}">
          <i class="fas fa-circle small-icon"></i> Manage Users
        </a>
        <a class="collapse-item" href="{{ route('pricing-tiers.index') }}">
          <i class="fas fa-circle small-icon"></i> Pricing Tiers
        </a>
      </div>
    </div>
  </li>
  
  <div class="sidebar-divider-modern"></div>

  <!-- Footer Section -->
  <div class="sidebar-heading-modern">
    <i class="fas fa-fw fa-calendar"></i> {{ \Carbon\Carbon::now()->format('M d, Y') }}
  </div>

  <!-- Logout -->
  <li class="nav-item logout-item">
    <a class="nav-link logout-link" href="{{ route('logout') }}" 
      onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
      <i class="fas fa-fw fa-sign-out-alt"></i>
      <span>Logout</span>
    </a>
  </li>
 
</ul>
<!-- End Sidebar -->

<script>
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.querySelector('.modern-sidebar');
    if (!sidebar || typeof window.jQuery === 'undefined') {
      return;
    }
    var $ = window.jQuery;
    $('#accordionSidebar').on('shown.bs.collapse', '.collapse', function () {
      var panel = this;
      requestAnimationFrame(function () {
        var cRect = panel.getBoundingClientRect();
        var sRect = sidebar.getBoundingClientRect();
        var pad = 16;
        var maxScroll = sidebar.scrollHeight - sidebar.clientHeight;
        if (cRect.bottom > sRect.bottom - pad) {
          var overflow = cRect.bottom - sRect.bottom + pad;
          var next = Math.min(maxScroll, sidebar.scrollTop + overflow);
          sidebar.scrollTo({ top: next, behavior: 'smooth' });
        } else if (cRect.top < sRect.top + pad) {
          var under = sRect.top + pad - cRect.top;
          var prev = Math.max(0, sidebar.scrollTop - under);
          sidebar.scrollTo({ top: prev, behavior: 'smooth' });
        }
      });
    });
  });
})();
</script>


<style>
  /* ===== PREMIUM COLOR PALETTE ===== */
  :root {
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
  }

  /* ===== MODERN SIDEBAR STYLING ===== */
  
  .modern-sidebar {
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border-right: 1px solid var(--border);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    display: flex;
    flex-direction: column;
    height: 100vh;
    overflow-y: auto;
    padding-bottom: 1rem;
  }

  /* Brand Section */
  .sidebar-brand {
    padding: 1.5rem 1rem !important;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    border-radius: 0 0 20px 0;
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.25);
    margin: 0 0 1.5rem 0;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
  }

  .sidebar-brand:hover {
    box-shadow: 0 12px 32px rgba(59, 130, 246, 0.35);
    transform: translateY(-2px);
  }

  .sidebar-brand-icon {
    width: 50px;
    height: 50px;
    min-width: 50px;
    min-height: 50px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    overflow: hidden;
  }

  .sidebar-brand-text {
    color: white !important;
    font-weight: 700 !important;
    font-size: 1.35rem !important;
    letter-spacing: 0.5px;
    white-space: nowrap;
  }

  .brand-logo {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  /* Divider */
  .sidebar-divider-modern {
    border: 0;
    border-top: 1px solid var(--border);
    margin: 1.5rem 0;
  }

  /* Heading Styling */
  .sidebar-heading-modern {
    padding: 0.85rem 1.25rem !important;
    margin: 1.25rem 0 0.75rem 0 !important;
    font-size: 0.8rem !important;
    font-weight: 800 !important;
    color: var(--text-light);
    text-transform: uppercase;
    letter-spacing: 1px;
    display: flex;
    align-items: center;
    gap: 0.6rem;
  }

  .sidebar-heading-modern i {
    font-size: 0.75rem !important;
  }

  /* Dashboard Link */
  .active-dashboard {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white !important;
    border-radius: 12px;
    margin: 0.5rem 0.75rem !important;
    padding: 1.1rem 1.5rem !important;
    font-size: 1.05rem !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .active-dashboard:hover {
    background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
    box-shadow: 0 8px 28px rgba(59, 130, 246, 0.45);
    transform: translateY(-3px);
  }

  /* Main Menu Links */
  .menu-link {
    padding: 1rem 1.25rem !important;
    color: var(--text) !important;
    font-weight: 500 !important;
    font-size: 1.05rem !important;
    border-radius: 12px;
    margin: 0.5rem 0.75rem !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    position: relative;
  }

  .menu-link i:first-child {
    font-size: 1.2rem !important;
    margin-right: 0.8rem !important;
    color: var(--primary);
    transition: all 0.3s ease;
    flex-shrink: 0;
  }

  .menu-link span {
    flex: 1;
    font-weight: 500;
  }

  .chevron-icon {
    font-size: 0.85rem !important;
    color: var(--text-light) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    margin-left: auto;
    flex-shrink: 0;
  }

  .menu-link:hover {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(6, 182, 212, 0.08) 100%);
    color: var(--primary) !important;
    padding-left: 1.5rem !important;
  }

  .menu-link:hover i:first-child {
    color: var(--primary) !important;
    transform: scale(1.2);
  }

  .menu-link[aria-expanded="true"] .chevron-icon {
    transform: rotate(90deg);
    color: var(--secondary) !important;
  }

  /* Collapse Inner Styling */
  .collapse-inner-modern {
    padding: 0.75rem 0 !important;
    background: linear-gradient(180deg, rgba(59, 130, 246, 0.03) 0%, rgba(6, 182, 212, 0.03) 100%);
    border-radius: 10px;
    margin: 0.75rem 0.75rem 1rem 0.75rem !important;
    border-left: 4px solid var(--secondary);
  }

  .collapse-item {
    padding: 0.7rem 1.5rem !important;
    margin: 0.35rem 0.5rem !important;
    color: var(--text) !important;
    font-size: 1rem !important;
    font-weight: 500 !important;
    transition: all 0.2s ease !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.75rem;
    border-radius: 8px;
  }

  .small-icon {
    font-size: 0.5rem !important;
    color: var(--secondary) !important;
    flex-shrink: 0;
  }

  .collapse-item:hover {
    color: var(--secondary) !important;
    padding-left: 1.8rem !important;
    background-color: rgba(6, 182, 212, 0.1);
    font-weight: 600;
  }

  /* Logout Section */
  .logout-item {
    margin-top: auto;
    padding-top: 2.5rem;
    border-top: 1px solid var(--border);
  }

  .logout-link {
    padding: 1rem 1.25rem !important;
    color: var(--danger) !important;
    font-weight: 600 !important;
    font-size: 1.05rem !important;
    border-radius: 12px;
    margin: 0.5rem 0.75rem !important;
    transition: all 0.3s ease !important;
    background-color: rgba(239, 68, 68, 0.08);
    display: flex;
    align-items: center;
  }

  .logout-link i {
    font-size: 1.2rem !important;
    margin-right: 0.8rem !important;
    flex-shrink: 0;
  }

  .logout-link:hover {
    background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
    color: white !important;
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.35);
    padding-left: 1.5rem !important;
    transform: translateY(-2px);
  }

  /* Collapse Animation */
  .collapse {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
  }

  /* Scrollbar Styling */
  .sidebar::-webkit-scrollbar {
    width: 6px;
  }

  .sidebar::-webkit-scrollbar-track {
    background: var(--bg);
    border-radius: 10px;
  }

  .sidebar::-webkit-scrollbar-thumb {
    background: rgba(59, 130, 246, 0.3);
    border-radius: 10px;
  }

  .sidebar::-webkit-scrollbar-thumb:hover {
    background: var(--primary);
  }

  /* Nav Link Badge */
  .nav-link-badge {
    display: inline-block;
    padding: 0.3rem 0.65rem;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(6, 182, 212, 0.1) 100%);
    color: var(--primary-dark);
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-left: 0.5rem;
    border: 1px solid rgba(59, 130, 246, 0.2);
  }

  .active-dashboard .nav-link-badge {
    background-color: rgba(255, 255, 255, 0.35);
    color: white;
    border-color: rgba(255, 255, 255, 0.5);
  }

  /* Responsive Adjustments */
  @media (max-width: 768px) {
    .sidebar-brand-text {
      font-size: 1.15rem !important;
    }

    .menu-link {
      padding: 0.85rem 1rem !important;
      font-size: 1rem !important;
    }

    .collapse-item {
      padding: 0.6rem 1.25rem !important;
      font-size: 0.95rem !important;
    }

    .chevron-icon {
      display: none !important;
    }
  }

  /* Focus and Active States */
  .menu-link:focus,
  .collapse-item:focus {
    outline: none;
  }

  .menu-link:focus-visible,
  .collapse-item:focus-visible {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
    border-radius: 8px;
  }
</style>