@php
use Illuminate\Support\Facades\Storage;
@endphp

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

  /* Navbar Styling */
  .topbar {
    background: linear-gradient(90deg, #ffffff 0%, #f8fafc 100%) !important;
    border-bottom: 1px solid var(--border) !important;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.08) !important;
    padding: 0.75rem 1.5rem !important;
    width: 100% !important;
    position: relative !important;
    z-index: 100 !important;
  }

  .navbar {
    display: flex !important;
    align-items: center !important;
    width: 100% !important;
  }

  .navbar-expand {
    flex-wrap: nowrap !important;
  }

  .topbar .navbar-nav .nav-item .nav-link {
    color: var(--text) !important;
    font-weight: 500;
    transition: all 0.3s ease;
    margin: 0 0.25rem;
  }

  .topbar .navbar-nav .nav-item .nav-link:hover {
    color: var(--primary) !important;
    transform: translateY(-2px);
  }

  .topbar .navbar-nav .nav-item .nav-link i {
    font-size: 1.1rem;
    color: var(--primary);
  }

  /* Badge Styling */
  .badge-danger {
    background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%) !important;
    color: white;
    border-radius: 50%;
    padding: 0.25rem 0.4rem !important;
    font-size: 0.65rem;
    font-weight: 800;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
  }

  /* Dropdown Menu Styling */
  .dropdown-menu {
    border: 1px solid var(--border) !important;
    border-radius: 14px !important;
    box-shadow: 0 10px 40px rgba(59, 130, 246, 0.15) !important;
    animation: slideDown 0.3s ease;
  }

  @keyframes slideDown {
    from {
      opacity: 0;
      transform: translateY(-10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .dropdown-header {
    color: var(--dark) !important;
    font-weight: 800 !important;
    font-size: 0.8rem !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem !important;
  }

  .dropdown-item {
    color: var(--text) !important;
    padding: 0.8rem 1rem !important;
    border-radius: 8px;
    margin: 0.25rem 0.5rem;
    transition: all 0.2s ease;
  }

  .dropdown-item:hover {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(6, 182, 212, 0.08) 100%) !important;
    color: var(--primary) !important;
    padding-left: 1.2rem;
  }

  .dropdown-item.active {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%) !important;
    color: white !important;
  }

  /* Icon Circle */
  .icon-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
  }

  .icon-circle.bg-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
  }

  .icon-circle.bg-success {
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    color: white;
  }

  .icon-circle.bg-warning {
    background: linear-gradient(135deg, var(--accent) 0%, #d97706 100%);
    color: white;
  }

  .icon-circle.bg-danger {
    background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
    color: white;
  }

  /* Search Form Styling */
  .navbar-search .form-control {
    background-color: var(--bg) !important;
    border: 1.5px solid var(--border) !important;
    border-radius: 10px !important;
    color: var(--text) !important;
    font-size: 0.9rem;
    padding: 0.6rem 1rem !important;
    transition: all 0.3s ease;
  }

  .navbar-search .form-control:focus {
    background-color: white !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
    color: var(--text) !important;
  }

  .navbar-search .form-control::placeholder {
    color: var(--text-light);
  }

  .navbar-search .btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%) !important;
    border: none !important;
    border-radius: 8px !important;
    padding: 0.6rem 1.2rem !important;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
  }

  .navbar-search .btn-primary:hover {
    background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%) !important;
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
    transform: translateY(-2px);
  }

  /* Sidebar Toggle Button */
  #sidebarToggleTop {
    color: var(--primary) !important;
    border-radius: 50% !important;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
  }

  #sidebarToggleTop:hover {
    background-color: rgba(59, 130, 246, 0.1) !important;
    color: var(--primary-dark) !important;
  }

  /* Divider Styling */
  .topbar-divider {
    border-right: 1px solid var(--border) !important;
    height: 2rem;
  }

  /* Dropdown List */
  .dropdown-list {
    max-height: 400px;
    overflow-y: auto;
  }

  .dropdown-list::-webkit-scrollbar {
    width: 6px;
  }

  .dropdown-list::-webkit-scrollbar-track {
    background: var(--bg);
  }

  .dropdown-list::-webkit-scrollbar-thumb {
    background: rgba(59, 130, 246, 0.3);
    border-radius: 10px;
  }

  .dropdown-list::-webkit-scrollbar-thumb:hover {
    background: var(--primary);
  }

  /* Time Badge */
  .small.text-gray-500 {
    color: var(--text-light) !important;
    font-size: 0.75rem;
    font-weight: 600;
  }

  /* Font Weight Bold */
  .font-weight-bold {
    color: var(--dark);
  }
</style>

 <!-- TopBar -->
        <nav class="navbar navbar-expand navbar-light bg-navbar topbar mb-4 static-top">
          <button type="button" id="sidebarToggleTop" class="btn btn-link rounded-circle mr-3" aria-label="Toggle sidebar">
            <!-- Custom SVG Hamburger Icon, always visible -->
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect y="4" width="24" height="2.5" fill="black"/>
              <rect y="10.75" width="24" height="2.5" fill="black"/>
              <rect y="17.5" width="24" height="2.5" fill="black"/>
            </svg>
          </button>
          <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown no-arrow mx-1">
              <a class="nav-link" href="#" id="tourHelpBtn" role="button" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false" title="Help, tours & quick options">
                <i class="fas fa-question-circle fa-fw"></i>
              </a>
              <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in tour-help-dropdown"
                aria-labelledby="tourHelpBtn">
                <h6 class="dropdown-header">System Guides</h6>
                <a class="dropdown-item" href="#" id="tourPageBtn">
                  <i class="fas fa-map-signs mr-2"></i>Tour this page
                </a>
                <a class="dropdown-item" href="#" id="tourNavBtn">
                  <i class="fas fa-compass mr-2"></i>Navigation overview
                </a>

                <div class="dropdown-divider"></div>
                <h6 class="dropdown-header">Quick Options</h6>
                @php $activeRoute = Route::currentRouteName() ?? ''; @endphp
                <a class="dropdown-item quick-option-item {{ in_array($activeRoute, ['home', 'dashboard']) ? 'active' : '' }}" href="{{ route('home') }}">
                  <i class="fas fa-home mr-2"></i>Dashboard
                </a>
                <a class="dropdown-item quick-option-item {{ $activeRoute === 'consumer' ? 'active' : '' }}" href="{{ route('consumer') }}">
                  <i class="fas fa-users mr-2"></i>Consumers
                </a>
                <a class="dropdown-item quick-option-item {{ $activeRoute === 'billing-processes' ? 'active' : '' }}" href="{{ route('billing-processes') }}">
                  <i class="fas fa-file-invoice-dollar mr-2"></i>Billing Process
                </a>
                <a class="dropdown-item quick-option-item {{ $activeRoute === 'meter-reading' ? 'active' : '' }}" href="{{ route('meter-reading') }}">
                  <i class="fas fa-tachometer-alt mr-2"></i>Meter Reading
                </a>
                <a class="dropdown-item quick-option-item {{ $activeRoute === 'download-reading' ? 'active' : '' }}" href="{{ route('download-reading') }}">
                  <i class="fas fa-cloud-download-alt mr-2"></i>Download Reading
                </a>
                <a class="dropdown-item quick-option-item {{ $activeRoute === 'disconnection.index' ? 'active' : '' }}" href="{{ route('disconnection.index') }}">
                  <i class="fas fa-plug mr-2"></i>Disconnection
                </a>
                <a class="dropdown-item quick-option-item {{ $activeRoute === 'billing-payment' ? 'active' : '' }}" href="{{ route('billing-payment') }}">
                  <i class="fas fa-money-bill-wave mr-2"></i>Bill Payments
                </a>
                <a class="dropdown-item quick-option-item {{ $activeRoute === 'billing-adjustment' ? 'active' : '' }}" href="{{ route('billing-adjustment') }}">
                  <i class="fas fa-sliders-h mr-2"></i>Billing Adjustment
                </a>
                <a class="dropdown-item quick-option-item {{ $activeRoute === 'billing-status' ? 'active' : '' }}" href="{{ route('billing-status') }}">
                  <i class="fas fa-chart-pie mr-2"></i>Billing Status
                </a>
                <a class="dropdown-item quick-option-item {{ $activeRoute === 'systemreport' ? 'active' : '' }}" href="{{ route('systemreport') }}">
                  <i class="fas fa-chart-bar mr-2"></i>System Reports
                </a>

                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-muted" href="#" id="tourResetBtn">
                  <i class="fas fa-redo mr-2"></i>Reset all tours
                </a>
              </div>
            </li>
            <li class="nav-item dropdown no-arrow">
              <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button" data-toggle="dropdown"
              aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-search fa-fw"></i>
              </a>
              <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in"
                aria-labelledby="searchDropdown">
                <form class="navbar-search">
                  <div class="input-group">
                    <input type="text" class="form-control bg-light border-1 small" placeholder="What do you want to look for?"
                      aria-label="Search" aria-describedby="basic-addon2" style="border-color: #060611;">
                    <div class="input-group-append">
                      <button class="btn btn-primary" type="button">
                        <i class="fas fa-search fa-sm"></i>
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            </li>
            <li class="nav-item dropdown no-arrow mx-1">
              <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bell fa-fw"></i>
                <span class="badge badge-danger badge-counter" id="disconnectionAlertBadge" style="display: none;">0</span>
              </a>
              <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="alertsDropdown">
                <h6 class="dropdown-header">
                  Alerts Center
                </h6>
                <div id="disconnectionAlertsList">
                  <div class="dropdown-item text-center small text-gray-500" id="disconnectionAlertsEmpty">
                    No new disconnection alerts
                  </div>
                </div>
                <a class="dropdown-item text-center small text-danger" href="#" id="clearDisconnectionAlerts">
                  Clear all notifications
                </a>
                <a class="dropdown-item text-center small text-gray-500" href="{{ route('disconnection.assignments') }}">View Disconnection Orders</a>
              </div>
            </li>
            <li class="nav-item dropdown no-arrow mx-1">
              <a class="nav-link dropdown-toggle" href="#" id="messagesDropdown" role="button" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-envelope fa-fw"></i>
                <span class="badge badge-warning badge-counter">2</span>
              </a>
              <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="messagesDropdown">
                <h6 class="dropdown-header">
                  Message Center
                </h6>
                <a class="dropdown-item d-flex align-items-center" href="#">
                  <div class="dropdown-list-image mr-3">
                    <img class="rounded-circle" src="img/man.png" style="max-width: 60px" alt="">
                    <div class="status-indicator bg-success"></div>
                  </div>
                  <div class="font-weight-bold">
                    <div class="text-truncate">Hi, admin has requested to assign a meter reader to a consumer</div>
                    <div class="small text-gray-500">Udin Cilok · 58m</div>
                  </div>
                </a>
                <a class="dropdown-item d-flex align-items-center" href="#">
                  <div class="dropdown-list-image mr-3">
                    <img class="rounded-circle" src="img/girl.png" style="max-width: 60px" alt="">
                    <div class="status-indicator bg-default"></div>
                  </div>
                  <div>
                        <div class="text-truncate">Meter reader has been assigned to a consumer</div>
                    <div class="small text-gray-500">Jaenab · 2w</div>
                  </div>
                </a>
                <a class="dropdown-item text-center small text-gray-500" href="#">Read More Messages</a>
              </div>
            </li>
            <li class="nav-item dropdown no-arrow mx-1">
              <a class="nav-link dropdown-toggle" href="#" id="messagesDropdown" role="button" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-tasks fa-fw"></i>
                <span class="badge badge-success badge-counter">3</span>
              </a>
              <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="messagesDropdown">
                <h6 class="dropdown-header">
                  Task
                </h6>
                <a class="dropdown-item align-items-center" href="#">
                  <div class="mb-3">
                    <div class="small text-gray-500">Design Button
                      <div class="small float-right"><b>50%</b></div>
                    </div>
                    <div class="progress" style="height: 12px;">
                      <div class="progress-bar bg-success" role="progressbar" style="width: 50%" aria-valuenow="50"
                        aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                  </div>
                </a>
                <a class="dropdown-item align-items-center" href="#">
                  <div class="mb-3">
                    <div class="small text-gray-500">Make Beautiful Transitions
                      <div class="small float-right"><b>30%</b></div>
                    </div>
                    <div class="progress" style="height: 12px;">
                      <div class="progress-bar bg-warning" role="progressbar" style="width: 30%" aria-valuenow="30"
                        aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                  </div>
                </a>
                <a class="dropdown-item align-items-center" href="#">
                  <div class="mb-3">
                    <div class="small text-gray-500">Create Pie Chart
                      <div class="small float-right"><b>75%</b></div>
                    </div>
                    <div class="progress" style="height: 12px;">
                      <div class="progress-bar bg-danger" role="progressbar" style="width: 75%" aria-valuenow="75"
                        aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                  </div>
                </a>
                <a class="dropdown-item text-center small text-gray-500" href="#">View All Taks</a>
              </div>
            </li>
            <div class="topbar-divider d-none d-sm-block"></div>
            <li class="nav-item dropdown no-arrow">
              <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
                <img class="img-profile rounded-circle" 
                     src="{{ Auth::user()->profile_picture_url ?? url('WDMS/img/boy.png') }}" 
                     style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;"
                     onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22100%22%20height%3D%22100%22%20viewBox%3D%220%200%20100%20100%22%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2250%22%20r%3D%2250%22%20fill%3D%22%23ddd%22%2F%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2238%22%20r%3D%2215%22%20fill%3D%22%23999%22%2F%3E%3Cellipse%20cx%3D%2250%22%20cy%3D%2285%22%20rx%3D%2230%22%20ry%3D%2225%22%20fill%3D%22%23999%22%2F%3E%3C%2Fsvg%3E';">
                <span class="ml-2 d-none d-lg-inline text-white small"></span>{{ Auth::user()->name }}
              </a>
              <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#profileModal">
                  <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                  Profile
                </a>
                <a class="dropdown-item" href="#">
                  <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
                  Settings
                </a>
                <a class="dropdown-item" href="{{ route('activity-logs.index') }}">
                  <i class="fas fa-list fa-sm fa-fw mr-2 text-gray-400"></i>
                  Activity Log
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="" data-toggle="modal" data-target="#logoutModal">
                  <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                  <form action="{{ route('logout') }}" method="POST" style="display:inline;">
    @csrf
    <button type="submit" style="border:none;background:none;color:blue;cursor:pointer;">
        Logout
    </button>
</form>
                </a>
                

              </div>
            </li>
          </ul>
        </nav>
        <!-- Topbar -->

        <!-- Profile Modal -->
        <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel">
                  <i class="fas fa-user-edit mr-2"></i>Edit Profile
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <form id="profileForm" enctype="multipart/form-data" onsubmit="return false;">
                <div class="modal-body">
                  @csrf
                  
                  <!-- Profile Picture Section -->
                  <div class="text-center mb-4">
                    <div class="position-relative d-inline-block">
                      <img id="profilePicturePreview" 
                           src="{{ Auth::user()->profile_picture_url ?? url('WDMS/img/boy.png') }}" 
                           alt="Profile Picture" 
                           class="rounded-circle border border-primary"
                           style="width: 150px; height: 150px; object-fit: cover; cursor: pointer;"
                           onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22100%22%20height%3D%22100%22%20viewBox%3D%220%200%20100%20100%22%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2250%22%20r%3D%2250%22%20fill%3D%22%23ddd%22%2F%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2238%22%20r%3D%2215%22%20fill%3D%22%23999%22%2F%3E%3Cellipse%20cx%3D%2250%22%20cy%3D%2285%22%20rx%3D%2230%22%20ry%3D%2225%22%20fill%3D%22%23999%22%2F%3E%3C%2Fsvg%3E';"
                           onclick="document.getElementById('profile_picture_input').click()">
                      <div class="position-absolute bottom-0 end-0 bg-primary rounded-circle p-2" style="cursor: pointer;" onclick="document.getElementById('profile_picture_input').click()">
                        <i class="fas fa-camera text-white"></i>
                      </div>
                    </div>
                    <input type="file" id="profile_picture_input" name="profile_picture" accept="image/*" style="display: none;">
                    <div class="mt-2">
                      <small class="text-muted">Click on image to change profile picture</small>
                      <br>
                      <small class="text-muted">Max size: 2MB (JPEG, PNG, JPG, GIF)</small>
                    </div>
                    <div class="invalid-feedback" id="profile_picture_error"></div>
                  </div>
                  
                  <hr>
                  
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="profile_first_name">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="profile_first_name" name="first_name" 
                               value="{{ Auth::user()->first_name }}" required>
                        <div class="invalid-feedback"></div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="profile_last_name">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="profile_last_name" name="last_name" 
                               value="{{ Auth::user()->last_name }}" required>
                        <div class="invalid-feedback"></div>
                      </div>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="profile_middle_name">Middle Name</label>
                        <input type="text" class="form-control" id="profile_middle_name" name="middle_name" 
                               value="{{ Auth::user()->middle_name ?? '' }}">
                        <div class="invalid-feedback"></div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="profile_extension">Extension</label>
                        <input type="text" class="form-control" id="profile_extension" name="extension" 
                               value="{{ Auth::user()->extension ?? '' }}" maxlength="10">
                        <div class="invalid-feedback"></div>
                      </div>
                    </div>
                  </div>
                  <div class="form-group">
                    <label for="profile_email">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="profile_email" name="email" 
                           value="{{ Auth::user()->email }}" required>
                    <div class="invalid-feedback"></div>
                  </div>
                  <hr>
                  <h6 class="mb-3">Change Password (Optional)</h6>
                  <div class="form-group">
                    <label for="profile_current_password">Current Password</label>
                    <input type="password" class="form-control" id="profile_current_password" name="current_password" 
                           placeholder="Enter current password to change password">
                    <small class="form-text text-muted">Leave blank if you don't want to change password</small>
                    <div class="invalid-feedback"></div>
                  </div>
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="profile_password">New Password</label>
                        <input type="password" class="form-control" id="profile_password" name="password" 
                               placeholder="Enter new password" minlength="8">
                        <small class="form-text text-muted">Minimum 8 characters</small>
                        <div class="invalid-feedback"></div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="profile_password_confirmation">Confirm New Password</label>
                        <input type="password" class="form-control" id="profile_password_confirmation" 
                               name="password_confirmation" placeholder="Confirm new password">
                        <div class="invalid-feedback"></div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-primary" id="profileSubmitBtn">
                    <i class="fas fa-save mr-1"></i>Save Changes
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <script>
        (function() {
          const POLL_MS = 10000;
          const ALERT_KEEP_HOURS = 8;
          const LAST_SEEN_KEY = 'hwd:last-disconnection-alert-seen-at';
          const ALERT_CACHE_KEY = 'hwd:disconnection-alert-cache';
          const routeUrl = '{{ route("disconnection.notifications.newly-disconnected") }}';
          const assignmentsUrl = '{{ route("disconnection.assignments") }}';
          const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
          const badgeEl = document.getElementById('disconnectionAlertBadge');
          const listEl = document.getElementById('disconnectionAlertsList');
          const emptyEl = document.getElementById('disconnectionAlertsEmpty');
          const clearBtn = document.getElementById('clearDisconnectionAlerts');

          if (!badgeEl || !listEl) return;

          const normalizeText = (value) => (value || '').toString().trim();

          const escapeHtml = (value) => {
            return (value || '').toString()
              .replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#039;');
          };

          const getLastSeen = () => {
            const saved = localStorage.getItem(LAST_SEEN_KEY);
            if (saved) return saved;
            const nowIso = new Date().toISOString();
            localStorage.setItem(LAST_SEEN_KEY, nowIso);
            return nowIso;
          };

          const setLastSeen = (isoDate) => {
            if (!isoDate) return;
            localStorage.setItem(LAST_SEEN_KEY, isoDate);
          };

          const setBadgeCount = (count) => {
            if (!count || count <= 0) {
              badgeEl.style.display = 'none';
              badgeEl.textContent = '0';
              return;
            }
            badgeEl.style.display = 'inline-block';
            badgeEl.textContent = count > 99 ? '99+' : String(count);
          };

          const getCutoffMs = () => Date.now() - (ALERT_KEEP_HOURS * 60 * 60 * 1000);

          const loadCachedAlerts = () => {
            try {
              const raw = localStorage.getItem(ALERT_CACHE_KEY);
              const parsed = raw ? JSON.parse(raw) : [];
              return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
              return [];
            }
          };

          const saveCachedAlerts = (items) => {
            try {
              localStorage.setItem(ALERT_CACHE_KEY, JSON.stringify(items || []));
            } catch (e) {
              // Ignore storage write issues.
            }
          };

          const isWithinRetention = (item) => {
            const iso = normalizeText(item?.disconnected_at);
            if (!iso) return false;
            const ts = new Date(iso).getTime();
            if (!Number.isFinite(ts)) return false;
            return ts >= getCutoffMs();
          };

          const pruneExpiredAlerts = (items) => {
            return (Array.isArray(items) ? items : []).filter(isWithinRetention);
          };

          const mergeAlerts = (incoming, existing) => {
            const map = new Map();
            (Array.isArray(existing) ? existing : []).forEach((item) => {
              if (item?.id != null) map.set(String(item.id), item);
            });
            (Array.isArray(incoming) ? incoming : []).forEach((item) => {
              if (item?.id != null) map.set(String(item.id), item);
            });
            return Array.from(map.values()).sort((a, b) => {
              const aTs = new Date(a?.disconnected_at || 0).getTime();
              const bTs = new Date(b?.disconnected_at || 0).getTime();
              return bTs - aTs;
            });
          };

          const playAlertSound = () => {
            try {
              const AudioCtx = window.AudioContext || window.webkitAudioContext;
              if (!AudioCtx) return;

              const ctx = new AudioCtx();
              const oscillator = ctx.createOscillator();
              const gainNode = ctx.createGain();

              // Power-off / click-style tone: short, sharp, descending pitch.
              oscillator.type = 'square';
              oscillator.frequency.setValueAtTime(920, ctx.currentTime);
              oscillator.frequency.exponentialRampToValueAtTime(220, ctx.currentTime + 0.22);

              gainNode.gain.setValueAtTime(0.0001, ctx.currentTime);
              gainNode.gain.exponentialRampToValueAtTime(0.15, ctx.currentTime + 0.015);
              gainNode.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.26);

              oscillator.connect(gainNode);
              gainNode.connect(ctx.destination);
              oscillator.start(ctx.currentTime);
              oscillator.stop(ctx.currentTime + 0.27);
            } catch (e) {
              // Silent fail if browser blocks autoplay/audio context.
            }
          };

          const speakDisconnection = (consumerName, count = 1) => {
            try {
              if (!('speechSynthesis' in window)) return;

              const safeName = normalizeText(consumerName) || 'A consumer';
              const text = count === 1
                ? `${safeName} was disconnected.`
                : `${count} consumers were disconnected. Latest is ${safeName}.`;

              const utterance = new SpeechSynthesisUtterance(text);
              utterance.rate = 0.95;
              utterance.pitch = 0.95;
              utterance.volume = 1.0;

              const voices = window.speechSynthesis.getVoices ? window.speechSynthesis.getVoices() : [];
              const preferredVoice = voices.find((voice) => /en-US|en-GB|English/i.test((voice.lang || '') + ' ' + (voice.name || '')));
              if (preferredVoice) utterance.voice = preferredVoice;

              // Prevent stacking old announcements and always read latest.
              window.speechSynthesis.cancel();
              window.speechSynthesis.speak(utterance);
            } catch (e) {
              // Silent fail if browser blocks speech synthesis.
            }
          };

          const renderAlerts = (items) => {
            listEl.innerHTML = '';
            if (!Array.isArray(items) || items.length === 0) {
              listEl.appendChild(emptyEl || document.createElement('div'));
              if (!emptyEl) {
                listEl.innerHTML = '<div class="dropdown-item text-center small text-gray-500">No new disconnection alerts</div>';
              }
              return;
            }

            items.forEach((item) => {
              const accountName = normalizeText(item.account_name) || 'Unknown consumer';
              const accountNo = normalizeText(item.account_no) || 'N/A';
              const when = normalizeText(item.disconnected_at_human) || 'just now';
              const disconnector = normalizeText(item.disconnector_name) || 'Disconnector';
              const disconnectedAt = normalizeText(item.disconnected_at);
              const dateSaved = disconnectedAt && disconnectedAt.includes('T')
                ? disconnectedAt.split('T')[0]
                : (disconnectedAt ? disconnectedAt.substring(0, 10) : '');
              const viewUrl = dateSaved
                   ? `${assignmentsUrl}?disconnected_from=${encodeURIComponent(dateSaved)}&disconnected_to=${encodeURIComponent(dateSaved)}`
              : `${assignmentsUrl}`;

              const row = document.createElement('a');
              row.className = 'dropdown-item d-flex align-items-center';
              row.href = viewUrl;
              row.innerHTML = `
                <div class="mr-3">
                  <div class="icon-circle bg-danger">
                    <i class="fas fa-unlink text-white"></i>
                  </div>
                </div>
                <div>
                  <div class="small text-gray-500">${escapeHtml(when)}</div>
                  <span class="font-weight-bold">${escapeHtml(accountName)} (${escapeHtml(accountNo)}) was disconnected by ${escapeHtml(disconnector)}.</span>
                </div>
              `;
              listEl.appendChild(row);
            });
          };

          const showPopup = (count, firstItem) => {
            const accountName = normalizeText(firstItem?.account_name) || 'A consumer';
            const accountNo = normalizeText(firstItem?.account_no);
            const disconnector = normalizeText(firstItem?.disconnector_name) || 'Unknown disconnector';
            const disconnectedAt = normalizeText(firstItem?.disconnected_at);
            const dateSaved = disconnectedAt && disconnectedAt.includes('T')
              ? disconnectedAt.split('T')[0]
              : (disconnectedAt ? disconnectedAt.substring(0, 10) : '');
            const firstLabel = accountNo ? `${accountName} (${accountNo})` : accountName;
            const viewUrl = dateSaved
              ? `${assignmentsUrl}?status=disconnected&date_saved=${encodeURIComponent(dateSaved)}`
              : `${assignmentsUrl}?status=disconnected`;
            const message = count === 1
              ? `${firstLabel} was disconnected by ${disconnector} from the mobile app.`
              : `${count} consumers were newly marked as disconnected from the mobile app. Latest: ${firstLabel}, disconnected by ${disconnector}.`;

            if (typeof Swal !== 'undefined') {
              Swal.fire({
                icon: 'warning',
                title: 'New Disconnection Alert',
                text: message,
                confirmButtonText: 'View',
                showCancelButton: true,
                cancelButtonText: 'Close'
              }).then((result) => {
                if (result.isConfirmed) {
                  window.location.href = viewUrl;
                }
              });
            } else {
              alert(message);
            }
          };

          const poll = async () => {
            try {
              let cached = pruneExpiredAlerts(loadCachedAlerts());
              const since = encodeURIComponent(getLastSeen());
              const res = await fetch(`${routeUrl}?since=${since}`, {
                method: 'GET',
                headers: {
                  'Accept': 'application/json',
                  'X-CSRF-TOKEN': csrfToken
                },
                credentials: 'same-origin'
              });
              if (!res.ok) return;
              const data = await res.json();
              if (!data?.success) return;

              const items = Array.isArray(data.items) ? data.items : [];
              const count = Number(data.count || items.length || 0);
              cached = pruneExpiredAlerts(mergeAlerts(items, cached));
              saveCachedAlerts(cached);
              renderAlerts(cached);
              setBadgeCount(cached.length);

              if (count > 0) {
                playAlertSound();
                speakDisconnection(items?.[0]?.account_name, count);
                showPopup(count, items[0]);
                const latestIso = items
                  .map((x) => x?.disconnected_at)
                  .filter(Boolean)
                  .sort()
                  .pop();
                setLastSeen(latestIso || data.server_time || new Date().toISOString());
              }
            } catch (e) {
              // Silent fail to avoid disrupting page usage.
              const cached = pruneExpiredAlerts(loadCachedAlerts());
              saveCachedAlerts(cached);
              renderAlerts(cached);
              setBadgeCount(cached.length);
            }
          };

          const initialCached = pruneExpiredAlerts(loadCachedAlerts());
          saveCachedAlerts(initialCached);
          renderAlerts(initialCached);
          setBadgeCount(initialCached.length);

          if (clearBtn) {
            clearBtn.addEventListener('click', (e) => {
              e.preventDefault();

              const clearNow = () => {
                saveCachedAlerts([]);
                renderAlerts([]);
                setBadgeCount(0);
              };

              if (typeof Swal !== 'undefined') {
                Swal.fire({
                  icon: 'question',
                  title: 'Clear notifications?',
                  text: 'This will remove all disconnection alerts from the bell.',
                  showCancelButton: true,
                  confirmButtonText: 'Clear all',
                  cancelButtonText: 'Cancel'
                }).then((result) => {
                  if (result.isConfirmed) {
                    clearNow();
                  }
                });
              } else {
                if (window.confirm('Clear all disconnection notifications?')) {
                  clearNow();
                }
              }
            });
          }

          poll();
          setInterval(poll, POLL_MS);
        })();
        </script>

        <script>
        // Wait for jQuery to be available
        (function() {
          function waitForJQuery(callback) {
            if (typeof jQuery !== 'undefined' && typeof $ !== 'undefined') {
              callback();
            } else {
              setTimeout(function() {
                waitForJQuery(callback);
              }, 50);
            }
          }
          
          waitForJQuery(function() {
            // Make previewProfilePicture available globally
            window.previewProfilePicture = function(input) {
              if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                  const preview = document.getElementById('profilePicturePreview');
                  if (preview) {
                    preview.src = e.target.result;
                  }
                };
                reader.readAsDataURL(input.files[0]);
                
                // Validate file size (2MB)
                if (input.files[0].size > 2048000) {
                  const errorDiv = document.getElementById('profile_picture_error');
                  if (errorDiv) {
                    errorDiv.textContent = 'File size must be less than 2MB';
                    errorDiv.style.display = 'block';
                  }
                  input.value = '';
                  return;
                } else {
                  const errorDiv = document.getElementById('profile_picture_error');
                  if (errorDiv) {
                    errorDiv.style.display = 'none';
                  }
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
                if (!allowedTypes.includes(input.files[0].type)) {
                  const errorDiv = document.getElementById('profile_picture_error');
                  if (errorDiv) {
                    errorDiv.textContent = 'Please select a valid image file (JPEG, PNG, JPG, GIF)';
                    errorDiv.style.display = 'block';
                  }
                  input.value = '';
                  return;
                } else {
                  const errorDiv = document.getElementById('profile_picture_error');
                  if (errorDiv) {
                    errorDiv.style.display = 'none';
                  }
                }
              }
            };

            $(document).ready(function() {
              // Attach file input change handler
              $('#profile_picture_input').on('change', function() {
                previewProfilePicture(this);
              });
              
              // Prevent form default submission
              $('#profileForm').on('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                return false;
              });
              
              // Handle form submission via button click
              $('#profileSubmitBtn').on('click', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                $('.is-invalid').removeClass('is-invalid');
                $('.invalid-feedback').text('');
                $('#profile_picture_error').hide();
                
                // Disable submit button
                $('#profileSubmitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');
                
                // Create FormData for file upload
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('first_name', $('#profile_first_name').val());
                formData.append('last_name', $('#profile_last_name').val());
                formData.append('middle_name', $('#profile_middle_name').val());
                formData.append('extension', $('#profile_extension').val());
                formData.append('email', $('#profile_email').val());
                
                // Add profile picture if selected
                const profilePictureInput = document.getElementById('profile_picture_input');
                console.log('Profile picture input:', profilePictureInput);
                console.log('Files:', profilePictureInput ? profilePictureInput.files : 'Input not found');
                if (profilePictureInput && profilePictureInput.files && profilePictureInput.files.length > 0) {
                  console.log('Adding file to FormData:', profilePictureInput.files[0].name, profilePictureInput.files[0].size);
                  formData.append('profile_picture', profilePictureInput.files[0]);
                } else {
                  console.log('No profile picture file selected');
                }
                
                // Add password fields if provided
                const currentPassword = $('#profile_current_password').val();
                const newPassword = $('#profile_password').val();
                const confirmPassword = $('#profile_password_confirmation').val();
                
                if (newPassword) {
                  if (!currentPassword) {
                    $('#profile_current_password').addClass('is-invalid');
                    $('#profile_current_password').next('.invalid-feedback').text('Current password is required to change password');
                    $('#profileSubmitBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Changes');
                    return;
                  }
                  
                  if (newPassword !== confirmPassword) {
                    $('#profile_password_confirmation').addClass('is-invalid');
                    $('#profile_password_confirmation').next('.invalid-feedback').text('Passwords do not match');
                    $('#profileSubmitBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Changes');
                    return;
                  }
                  
                  formData.append('password', newPassword);
                  formData.append('current_password', currentPassword);
                }
                
                // Debug: Log FormData contents
                console.log('FormData contents:');
                let hasFile = false;
                for (let pair of formData.entries()) {
                  const isFile = pair[1] instanceof File;
                  if (isFile) {
                    hasFile = true;
                    console.log(pair[0] + ': FILE - ' + pair[1].name + ' (' + pair[1].size + ' bytes, type: ' + pair[1].type + ')');
                  } else {
                    console.log(pair[0] + ': ' + pair[1]);
                  }
                }
                console.log('Has profile picture file:', hasFile);
                
                $.ajax({
                  url: '{{ route("profile.update") }}',
                  method: 'POST',
                  data: formData,
                  processData: false,
                  contentType: false,
                  cache: false,
                  xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                      if (evt.lengthComputable) {
                        const percentComplete = (evt.loaded / evt.total) * 100;
                        console.log('Upload progress: ' + percentComplete + '%');
                      }
                    }, false);
                    return xhr;
                  },
                  success: function(response) {
                    console.log('Profile update response:', response);
                    
                    if (response.success) {
                  // Show success message
                  if (typeof Swal !== 'undefined') {
                    Swal.fire({
                      icon: 'success',
                      title: 'Success!',
                      text: response.message || 'Profile updated successfully!',
                      timer: 2000,
                      showConfirmButton: false
                    });
                  } else {
                    alert(response.message || 'Profile updated successfully!');
                  }
                  
                  // Update profile picture preview if new one was uploaded
                  if (response.user && response.user.profile_picture_url) {
                    $('#profilePicturePreview').attr('src', response.user.profile_picture_url);
                    // Also update navbar image
                    $('.img-profile').attr('src', response.user.profile_picture_url);
                  }
                  
                  // Close modal
                  $('#profileModal').modal('hide');
                  
                  // Reload page to reflect changes
                  setTimeout(function() {
                    location.reload();
                    }, 500);
                  } else {
                    console.error('Unexpected response format:', response);
                    if (typeof Swal !== 'undefined') {
                      Swal.fire({
                        icon: 'warning',
                        title: 'Warning',
                        text: response.message || 'Profile update completed but response format was unexpected',
                        confirmButtonColor: '#ffc107'
                      });
                    }
                  }
                },
                error: function(xhr, status, error) {
                  console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    statusCode: xhr.status,
                    response: xhr.responseJSON,
                    responseText: xhr.responseText
                  });
                    
                    $('#profileSubmitBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Changes');
                    
                    if (xhr.status === 422) {
                      // Validation errors
                      const errors = xhr.responseJSON?.errors || {};
                      $.each(errors, function(field, messages) {
                        if (field === 'profile_picture') {
                          $('#profile_picture_error').text(messages[0]).show();
                        } else {
                          const input = $('#profile_' + field);
                          if (input.length) {
                            input.addClass('is-invalid');
                            const feedback = input.next('.invalid-feedback');
                            if (feedback.length) {
                              feedback.text(messages[0]);
                            } else {
                              input.after('<div class="invalid-feedback">' + messages[0] + '</div>');
                            }
                          }
                        }
                      });
                      
                      // Show error message
                      if (typeof Swal !== 'undefined') {
                        Swal.fire({
                          icon: 'error',
                          title: 'Validation Error',
                          text: xhr.responseJSON?.message || 'Please correct the errors and try again',
                          confirmButtonColor: '#dc3545'
                        });
                      }
                    } else {
                      // Other errors
                      const message = xhr.responseJSON?.message || xhr.responseText || 'An error occurred. Please try again.';
                      console.error('Error message:', message);
                      
                      if (typeof Swal !== 'undefined') {
                        Swal.fire({
                          icon: 'error',
                          title: 'Error',
                          text: message,
                          confirmButtonColor: '#dc3545'
                        });
                      } else {
                        alert(message);
                      }
                    }
                  }
                });
              });
            });
          });
        })();
        </script>

        <!-- Profile Modal -->
        <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel">
                  <i class="fas fa-user-edit mr-2"></i>Edit Profile
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <form id="profileForm">
                <div class="modal-body">
                  @csrf
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="profile_first_name">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="profile_first_name" name="first_name" 
                               value="{{ Auth::user()->first_name }}" required>
                        <div class="invalid-feedback"></div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="profile_last_name">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="profile_last_name" name="last_name" 
                               value="{{ Auth::user()->last_name }}" required>
                        <div class="invalid-feedback"></div>
                      </div>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="profile_middle_name">Middle Name</label>
                        <input type="text" class="form-control" id="profile_middle_name" name="middle_name" 
                               value="{{ Auth::user()->middle_name ?? '' }}">
                        <div class="invalid-feedback"></div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="profile_extension">Extension</label>
                        <input type="text" class="form-control" id="profile_extension" name="extension" 
                               value="{{ Auth::user()->extension ?? '' }}" maxlength="10">
                        <div class="invalid-feedback"></div>
                      </div>
                    </div>
                  </div>
                  <div class="form-group">
                    <label for="profile_email">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="profile_email" name="email" 
                           value="{{ Auth::user()->email }}" required>
                    <div class="invalid-feedback"></div>
                  </div>
                  <hr>
                  <h6 class="mb-3">Change Password (Optional)</h6>
                  <div class="form-group">
                    <label for="profile_current_password">Current Password</label>
                    <input type="password" class="form-control" id="profile_current_password" name="current_password" 
                           placeholder="Enter current password to change password">
                    <small class="form-text text-muted">Leave blank if you don't want to change password</small>
                    <div class="invalid-feedback"></div>
                  </div>
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="profile_password">New Password</label>
                        <input type="password" class="form-control" id="profile_password" name="password" 
                               placeholder="Enter new password" minlength="8">
                        <small class="form-text text-muted">Minimum 8 characters</small>
                        <div class="invalid-feedback"></div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="profile_password_confirmation">Confirm New Password</label>
                        <input type="password" class="form-control" id="profile_password_confirmation" 
                               name="password_confirmation" placeholder="Confirm new password">
                        <div class="invalid-feedback"></div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary" id="profileSubmitBtn">
                    <i class="fas fa-save mr-1"></i>Save Changes
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <script>
        $(document).ready(function() {
          // Handle profile form submission
          $('#profileForm').on('submit', function(e) {
            e.preventDefault();
            
            // Clear previous errors
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').text('');
            
            // Disable submit button
            $('#profileSubmitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');
            
            const formData = {
              _token: '{{ csrf_token() }}',
              first_name: $('#profile_first_name').val(),
              last_name: $('#profile_last_name').val(),
              middle_name: $('#profile_middle_name').val(),
              extension: $('#profile_extension').val(),
              email: $('#profile_email').val(),
            };
            
            // Add password fields if provided
            const currentPassword = $('#profile_current_password').val();
            const newPassword = $('#profile_password').val();
            const confirmPassword = $('#profile_password_confirmation').val();
            
            if (newPassword) {
              if (!currentPassword) {
                $('#profile_current_password').addClass('is-invalid');
                $('#profile_current_password').next('.invalid-feedback').text('Current password is required to change password');
                $('#profileSubmitBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Changes');
                return;
              }
              
              if (newPassword !== confirmPassword) {
                $('#profile_password_confirmation').addClass('is-invalid');
                $('#profile_password_confirmation').next('.invalid-feedback').text('Passwords do not match');
                $('#profileSubmitBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Changes');
                return;
              }
              
              formData.password = newPassword;
              formData.current_password = currentPassword;
            }
            
            $.ajax({
              url: '{{ route("profile.update") }}',
              method: 'POST',
              data: formData,
              success: function(response) {
                if (response.success) {
                  // Show success message
                  if (typeof Swal !== 'undefined') {
                    Swal.fire({
                      icon: 'success',
                      title: 'Success!',
                      text: response.message || 'Profile updated successfully!',
                      timer: 2000,
                      showConfirmButton: false
                    });
                  } else {
                    alert(response.message || 'Profile updated successfully!');
                  }
                  
                  // Close modal
                  $('#profileModal').modal('hide');
                  
                  // Reload page to reflect changes
                  setTimeout(function() {
                    location.reload();
                  }, 500);
                }
              },
              error: function(xhr) {
                $('#profileSubmitBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Changes');
                
                if (xhr.status === 422) {
                  // Validation errors
                  const errors = xhr.responseJSON.errors || {};
                  $.each(errors, function(field, messages) {
                    const input = $('#profile_' + field);
                    input.addClass('is-invalid');
                    input.next('.invalid-feedback').text(messages[0]);
                  });
                  
                  // Show error message
                  if (typeof Swal !== 'undefined') {
                    Swal.fire({
                      icon: 'error',
                      title: 'Validation Error',
                      text: xhr.responseJSON.message || 'Please correct the errors and try again',
                      confirmButtonColor: '#dc3545'
                    });
                  }
                } else {
                  // Other errors
                  const message = xhr.responseJSON?.message || 'An error occurred. Please try again.';
                  if (typeof Swal !== 'undefined') {
                    Swal.fire({
                      icon: 'error',
                      title: 'Error',
                      text: message,
                      confirmButtonColor: '#dc3545'
                    });
                  } else {
                    alert(message);
                  }
                }
              }
            });
          });
        });
        </script>