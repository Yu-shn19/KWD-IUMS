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
                <span class="badge badge-danger badge-counter">3+</span>
              </a>
              <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="alertsDropdown">
                <h6 class="dropdown-header">
                  Alerts Center
                </h6>
                <a class="dropdown-item d-flex align-items-center" href="#">
                  <div class="mr-3">
                    <div class="icon-circle bg-primary">
                      <i class="fas fa-file-alt text-white"></i>
                    </div>
                  </div>
                  <div>
                    <div class="small text-gray-500">December 12, 2026</div>
                    <span class="font-weight-bold">A new monthly report is ready to download!</span>
                  </div>
                </a>
                <a class="dropdown-item d-flex align-items-center" href="#">
                  <div class="mr-3">
                    <div class="icon-circle bg-success">
                      <i class="fas fa-donate text-white"></i>
                    </div>
                  </div>
                  <div>
                    <div class="small text-gray-500">December 7, 2026</div>
                  consumer has been assigned to a meter reader
                  </div>
                </a>
                <a class="dropdown-item d-flex align-items-center" href="#">
                  <div class="mr-3">
                    <div class="icon-circle bg-warning">
                      <i class="fas fa-exclamation-triangle text-white"></i>
                    </div>
                  </div>
                  <div>
                    <div class="small text-gray-500">December 2, 2026</div>
                  Admin requested to assign a meter reader to a consumer
                  </div>
                </a>
                <a class="dropdown-item text-center small text-gray-500" href="#">Show All Alerts</a>
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
                <a class="dropdown-item" href="#">
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