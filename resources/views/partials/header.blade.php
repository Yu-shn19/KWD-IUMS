<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="Hagonoy Water District - Integrated Utility Management System">
  <meta name="author" content="Hagonoy Water District">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#3b82f6">
  
  <link href="{{url('WDMS/img/logo/logo.png')}}" rel="icon">
  <title>HagunoyWD-IUMS | Water Management System</title>
  
  <!-- Font Awesome -->
  <link href="{{url('WDMS/vendor/fontawesome-free/css/all.min.css')}}" rel="stylesheet" type="text/css">
  
  <!-- Bootstrap CSS -->
  <link href="{{url('WDMS/vendor/bootstrap/css/bootstrap.min.css')}}" rel="stylesheet" type="text/css">
  
  <!-- Theme CSS -->
  <link href="{{url('WDMS/css/ruang-admin.min.css')}}" rel="stylesheet">
  
  <!-- SweetAlert2 -->
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <style>
    /* ===== PREMIUM COLOR PALETTE - GLOBAL ===== */
    :root {
      --primary: #3b82f6;
      --primary-dark: #1e40af;
      --primary-light: #60a5fa;
      --secondary: #06b6d4;
      --secondary-light: #22d3ee;
      --accent: #f59e0b;
      --success: #10b981;
      --danger: #ef4444;
      --dark: #1e293b;
      --text: #334155;
      --text-light: #64748b;
      --border: #e2e8f0;
      --bg: #f8fafc;
      --card: #ffffff;
    }

    /* Global Font */
    * {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
    }

    /* Font Awesome Icons - Preserve Font Awesome Font */
    .fa, .fas, .far, .fab, .fal {
      font-family: "Font Awesome 5 Free", "Font Awesome 5 Brands" !important;
      font-weight: 400;
    }

    .fas {
      font-weight: 900 !important;
    }

    html, body {
      background-color: var(--bg);
      color: var(--text);
      font-weight: 500;
    }

    /* Wrapper Layout */
    body #page-top {
      background: var(--bg);
    }

    body #wrapper {
      display: flex;
      width: 100%;
      min-height: 100vh;
    }

    /* Sidebar Fixed Width */
    body #wrapper .navbar-nav.sidebar {
      position: fixed !important;
      display: flex !important;
      flex-direction: column !important;
      left: 0;
      top: 0;
      width: 280px !important;
      min-width: 280px !important;
      max-width: 280px !important;
      height: 100vh !important;
      padding: 0 !important;
      margin: 0 !important;
      flex-shrink: 0 !important;
      overflow-y: auto !important;
      overflow-x: hidden !important;
      z-index: 1000 !important;
    }

    /* Content Wrapper */
    body #wrapper #content-wrapper {
      flex: 1 1 auto;
      display: flex !important;
      flex-direction: column !important;
      width: calc(100% - 280px);
      margin-left: 280px;
      min-height: auto;
      background: var(--bg);
      overflow: visible;
      min-width: 0;
    }

    body #wrapper #content {
      flex: 1;
      display: flex !important;
      flex-direction: column !important;
      overflow: visible;
    }

    /* Navbar */
    body .topbar {
      flex-shrink: 0;
      width: 100%;
      padding: 0.75rem 1.5rem !important;
      box-sizing: border-box;
      background: linear-gradient(90deg, #ffffff 0%, #f8fafc 100%) !important;
      border-bottom: 1px solid var(--border) !important;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.08) !important;
    }

    /* Container Wrapper */
    body #container-wrapper {
      flex: 1;
      padding: 1.5rem;
      overflow-y: auto;
      overflow-x: hidden;
      width: 100%;
      box-sizing: border-box;
      min-height: auto;
    }

    /* Cards Styling */
    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.08);
      transition: all 0.3s ease;
    }

    .card:hover {
      box-shadow: 0 8px 20px rgba(59, 130, 246, 0.12);
    }

    .card-header {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(6, 182, 212, 0.05) 100%);
      border-bottom: 1px solid var(--border);
      color: var(--dark);
    }

    /* Button Styling */
    .btn-primary {
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%) !important;
      border: none !important;
      color: white !important;
      font-weight: 600;
      border-radius: 10px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, var(--primary-dark) 0%, #0891b2 100%) !important;
      box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
      transform: translateY(-2px);
    }

    .btn-success {
      background: linear-gradient(135deg, var(--success) 0%, #059669 100%) !important;
      border: none !important;
      font-weight: 600;
      border-radius: 10px;
    }

    .btn-danger {
      background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%) !important;
      border: none !important;
      font-weight: 600;
      border-radius: 10px;
    }

    .btn-secondary {
      background: linear-gradient(135deg, var(--text-light) 0%, var(--border) 100%) !important;
      border: none !important;
      font-weight: 600;
      border-radius: 10px;
      color: var(--dark) !important;
    }

    /* Form Controls */
    .form-control, .form-select {
      border: 1px solid var(--border) !important;
      border-radius: 10px !important;
      padding: 0.75rem 1rem !important;
      background-color: white !important;
      color: var(--text) !important;
      transition: all 0.3s ease;
      box-sizing: border-box !important;
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary) !important;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
    }

    /* Select dropdown - content fits in field, options visible */
    select.form-control,
    select.form-control-sm,
    select.form-select,
    select {
      width: 100% !important;
      max-width: 100% !important;
      min-width: 0 !important;
      min-height: 2.25rem !important;
      padding: 0.375rem 2rem 0.375rem 0.75rem !important;
      box-sizing: border-box !important;
      background-color: #ffffff !important;
      color: #1e293b !important;
      appearance: auto;
      font-size: inherit;
      line-height: 1.5;
    }
    select.form-control-sm {
      min-height: 2rem !important;
      padding: 0.25rem 1.75rem 0.25rem 0.5rem !important;
    }
    select.form-control option,
    select.form-control-sm option,
    select.form-select option,
    select option {
      background-color: #ffffff !important;
      background: #ffffff !important;
      color: #1e293b !important;
      font-weight: 500;
      padding: 0.25em 0.5em;
    }
    /* Ensure selected option displays with dark text when dropdown is closed */
    select.form-control:not([multiple]),
    select.form-control-sm:not([multiple]),
    select.form-select:not([multiple]) {
      color: #1e293b !important;
    }

    /* Table Styling */
    .table {
      color: var(--text);
    }

    .table thead th {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(6, 182, 212, 0.08) 100%);
      color: var(--dark);
      border-bottom: 2px solid var(--border);
      font-weight: 700;
      padding: 1rem;
    }

    .table tbody td {
      border-color: var(--border);
      padding: 0.85rem 1rem;
      vertical-align: middle;
    }

    .table tbody tr:hover {
      background-color: rgba(59, 130, 246, 0.05);
    }

    /* Badge Styling */
    .badge {
      padding: 0.5rem 0.75rem;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.75rem;
    }

    .badge-primary {
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      color: white;
    }

    .badge-success {
      background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
      color: white;
    }

    .badge-danger {
      background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
      color: white;
    }

    /* Links */
    a {
      color: var(--primary);
      text-decoration: none;
      transition: all 0.3s ease;
    }

    a:hover {
      color: var(--primary-dark);
    }

    /* Text Utilities */
    .text-primary {
      color: var(--primary) !important;
    }

    .text-secondary {
      color: var(--secondary) !important;
    }

    .text-success {
      color: var(--success) !important;
    }

    .text-danger {
      color: var(--danger) !important;
    }

    .text-warning {
      color: var(--accent) !important;
    }

    .text-muted {
      color: var(--text-light) !important;
    }

    .text-dark {
      color: var(--dark) !important;
    }

    /* Headings */
    h1, h2, h3, h4, h5, h6 {
      color: var(--dark);
      font-weight: 800;
    }

    .h1, .h2, .h3, .h4, .h5, .h6 {
      color: var(--dark);
      font-weight: 800;
    }

    /* Scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    ::-webkit-scrollbar-track {
      background: var(--bg);
    }

    ::-webkit-scrollbar-thumb {
      background: rgba(59, 130, 246, 0.3);
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: var(--primary);
    }

    /* Modals */
    .modal-content {
      border: 1px solid var(--border);
      border-radius: 12px;
      box-shadow: 0 10px 40px rgba(59, 130, 246, 0.15);
    }

    .modal-header {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(6, 182, 212, 0.05) 100%);
      border-bottom: 1px solid var(--border);
    }

    /* Alerts */
    .alert {
      border-radius: 12px;
      border: 1px solid var(--border);
      padding: 1rem;
    }
    .alert .close {
      color: var(--dark);
      opacity: 0.8;
    }

    .alert-primary {
      background-color: rgba(59, 130, 246, 0.08);
      border-color: var(--primary);
      color: var(--primary-dark) !important;
    }
    .alert-primary .fas, .alert-primary .far, .alert-primary .fab { color: var(--primary-dark) !important; }

    .alert-success {
      background-color: rgba(16, 185, 129, 0.12);
      border-color: var(--success);
      color: #065f46 !important;
    }
    .alert-success .fas, .alert-success .far, .alert-success .fab { color: #065f46 !important; }

    .alert-warning {
      background-color: rgba(245, 158, 11, 0.12);
      border-color: var(--accent);
      color: #92400e !important;
    }
    .alert-warning .fas, .alert-warning .far, .alert-warning .fab { color: #92400e !important; }

    .alert-danger {
      background-color: rgba(239, 68, 68, 0.12);
      border-color: var(--danger);
      color: #7f1d1d !important;
    }
    .alert-danger .fas, .alert-danger .far, .alert-danger .fab { color: #7f1d1d !important; }

    .alert-info {
      background-color: rgba(6, 182, 212, 0.12);
      border-color: var(--secondary);
      color: #0e7490 !important;
    }
    .alert-info .fas, .alert-info .far, .alert-info .fab { color: #0e7490 !important; }

    /* Toaster / fixed alerts - ensure text and icon are always visible (not white) */
    .alert.position-fixed,
    .alert.position-fixed * {
      color: inherit !important;
    }
    .alert.position-fixed {
      white-space: normal;
      word-wrap: break-word;
    }
    .alert-success.position-fixed { color: #065f46 !important; }
    .alert-success.position-fixed * { color: #065f46 !important; }
    .alert-success.position-fixed .close { color: #1e293b !important; }
    .alert-danger.position-fixed { color: #7f1d1d !important; }
    .alert-danger.position-fixed * { color: #7f1d1d !important; }
    .alert-danger.position-fixed .close { color: #1e293b !important; }
    .alert-warning.position-fixed { color: #92400e !important; }
    .alert-warning.position-fixed * { color: #92400e !important; }
    .alert-warning.position-fixed .close { color: #1e293b !important; }
    .alert-info.position-fixed { color: #0e7490 !important; }
    .alert-info.position-fixed * { color: #0e7490 !important; }
    .alert-info.position-fixed .close { color: #1e293b !important; }

    /* SweetAlert2 / Toaster - ensure message text is not white */
    .swal2-popup .swal2-title,
    .swal2-popup .swal2-html-container,
    .swal2-popup .swal2-content {
      color: #1e293b !important;
    }
    .swal2-toast .swal2-title,
    .swal2-toast .swal2-html-container {
      color: #1e293b !important;
    }

    /* Smooth transitions */
    * {
      transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
    }

    button, a, input, select {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Sidebar toggle: when toggled, collapse sidebar and expand content (overrides fixed width) */
    body.sidebar-toggled #wrapper .navbar-nav.sidebar,
    body #wrapper .navbar-nav.sidebar.toggled {
      width: 0 !important;
      min-width: 0 !important;
      max-width: 0 !important;
      overflow: hidden !important;
      padding: 0 !important;
      margin: 0 !important;
      border: none !important;
      transition: width 0.3s ease, min-width 0.3s ease, max-width 0.3s ease;
    }
    body.sidebar-toggled #wrapper #content-wrapper {
      margin-left: 0 !important;
      width: 100% !important;
      transition: margin-left 0.3s ease, width 0.3s ease;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
      body #wrapper .navbar-nav.sidebar {
        width: 250px !important;
        min-width: 250px !important;
        max-width: 250px !important;
      }

      body #wrapper #content-wrapper {
        width: calc(100% - 250px);
      }

      body #container-wrapper {
        padding: 1rem;
      }
    }
  </style>
</head>