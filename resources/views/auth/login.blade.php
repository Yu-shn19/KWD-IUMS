<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="KIBLAWAN WATER DISTRICT-IUMS | Water Management System">
  <title>Login - KiblawanWD-IUMS</title>
  <link rel="icon" href="{{ url('WDMS\img\logo\KlogoC.png') }}">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #0077b6;
      --primary-dark: #023e8a;
      --primary-light: #48cae4;
      --surface: rgba(255, 255, 255, 0.92);
      --text: #1e293b;
      --text-muted: #64748b;
      --border: #e2e8f0;
      --shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
    }

    * {
      box-sizing: border-box;
    }

    body {
      min-height: 100vh;
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      position: relative;
      overflow-x: hidden;
      background: #0a1628;
    }

    .bg-layer {
      position: fixed;
      inset: 0;
      background: url('{{ asset('WDMS/img/logo/hero.jpg') }}') no-repeat center center / cover;
      transform: scale(1.03);
      z-index: 0;
    }

    .bg-overlay {
      position: fixed;
      inset: 0;
      background:
        linear-gradient(135deg, rgba(2, 62, 138, 0.82) 0%, rgba(0, 119, 182, 0.65) 45%, rgba(10, 22, 40, 0.75) 100%);
      z-index: 1;
    }

    .bg-pattern {
      position: fixed;
      inset: 0;
      opacity: 0.04;
      background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0);
      background-size: 28px 28px;
      z-index: 2;
      pointer-events: none;
    }

    .login-wrapper {
      position: relative;
      z-index: 3;
      width: 100%;
      max-width: 440px;
      animation: slideUp 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .login-card {
      background: var(--surface);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.6);
      border-radius: 20px;
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .login-header {
      text-align: center;
      padding: 2.25rem 2rem 1.5rem;
      background: linear-gradient(180deg, rgba(0, 119, 182, 0.06) 0%, transparent 100%);
      border-bottom: 1px solid rgba(0, 119, 182, 0.08);
    }

    .logo-wrap {
      width: 88px;
      height: 88px;
      margin: 0 auto 1rem;
      padding: 10px;
      border-radius: 50%;
      background: #fff;
      box-shadow: 0 8px 24px rgba(0, 119, 182, 0.18);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .logo-wrap img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .login-header h1 {
      font-size: 1.35rem;
      font-weight: 700;
      color: var(--primary-dark);
      margin: 0 0 0.35rem;
      letter-spacing: -0.02em;
    }

    .login-header p {
      margin: 0;
      font-size: 0.875rem;
      color: var(--text-muted);
      font-weight: 500;
    }

    .login-body {
      padding: 1.75rem 2rem 2rem;
    }

    .alert-error {
      display: flex;
      align-items: flex-start;
      gap: 0.65rem;
      padding: 0.75rem 1rem;
      margin-bottom: 1.25rem;
      border-radius: 12px;
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      font-size: 0.875rem;
      line-height: 1.45;
    }

    .alert-error i {
      flex-shrink: 0;
      margin-top: 0.1rem;
    }

    .form-label {
      font-size: 0.8125rem;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 0.45rem;
    }

    .input-group-custom {
      margin-bottom: 1.15rem;
    }

    .input-field {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-icon {
      position: absolute;
      left: 0.95rem;
      top: 0;
      bottom: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--text-muted);
      font-size: 1.05rem;
      line-height: 1;
      pointer-events: none;
      z-index: 2;
      transition: color 0.2s ease;
    }

    .form-control-custom {
      width: 100%;
      height: 48px;
      padding: 0 2.75rem 0 2.65rem;
      font-size: 0.9375rem;
      font-family: inherit;
      color: var(--text);
      background: #f8fafc;
      border: 1.5px solid var(--border);
      border-radius: 12px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }

    .form-control-custom::placeholder {
      color: #94a3b8;
    }

    .form-control-custom:hover {
      border-color: #cbd5e1;
      background: #fff;
    }

    .form-control-custom:focus {
      outline: none;
      border-color: var(--primary);
      background: #fff;
      box-shadow: 0 0 0 4px rgba(0, 119, 182, 0.12);
    }

    .input-field:focus-within .input-icon {
      color: var(--primary);
    }

    .form-control-custom.is-invalid {
      border-color: #ef4444;
      background: #fff;
    }

    .form-control-custom.is-invalid:focus {
      box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12);
    }

    .toggle-password {
      position: absolute;
      right: 0.65rem;
      top: 0;
      bottom: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 2rem;
      background: none;
      border: none;
      padding: 0;
      color: var(--text-muted);
      cursor: pointer;
      border-radius: 8px;
      font-size: 1.05rem;
      line-height: 1;
      z-index: 2;
      transition: color 0.2s ease, background 0.2s ease;
    }

    .toggle-password:hover {
      color: var(--primary);
      background: rgba(0, 119, 182, 0.08);
    }

    .btn-login {
      width: 100%;
      height: 48px;
      margin-top: 0.5rem;
      border: none;
      border-radius: 12px;
      font-size: 0.9375rem;
      font-weight: 600;
      font-family: inherit;
      color: #fff;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      box-shadow: 0 4px 14px rgba(0, 119, 182, 0.35);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
    }

    .btn-login:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 20px rgba(0, 119, 182, 0.4);
      color: #fff;
    }

    .btn-login:active {
      transform: translateY(0);
    }

    .btn-login:disabled {
      opacity: 0.75;
      cursor: not-allowed;
      transform: none;
    }

    .login-footer {
      text-align: center;
      padding: 1rem 2rem 1.5rem;
      border-top: 1px solid rgba(0, 0, 0, 0.05);
      background: rgba(248, 250, 252, 0.6);
    }

    .login-footer span {
      font-size: 0.75rem;
      color: var(--text-muted);
      font-weight: 500;
    }

    .brand-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      margin-top: 1.25rem;
      padding: 0.4rem 0.85rem;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.12);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: rgba(255, 255, 255, 0.85);
      font-size: 0.75rem;
      font-weight: 500;
      backdrop-filter: blur(8px);
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(24px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 480px) {
      body {
        padding: 1rem;
      }

      .login-header,
      .login-body {
        padding-left: 1.35rem;
        padding-right: 1.35rem;
      }

      .login-header h1 {
        font-size: 1.2rem;
      }
    }
  </style>
</head>
<body>
  <div class="bg-layer" aria-hidden="true"></div>
  <div class="bg-overlay" aria-hidden="true"></div>
  <div class="bg-pattern" aria-hidden="true"></div>

  <div class="login-wrapper">
    <div class="login-card">
      <div class="login-header">
        <div class="logo-wrap">
          <img src="{{ url('WDMS\img\logo\KlogoC.png') }}" alt="Kiblawan Water District Logo">
        </div>
        <h1>KIBLAWAN WATER DISTRICT</h1>
        <p>Integrated Utility Management System</p>
      </div>

      <div class="login-body">
        @if ($errors->any())
          <div class="alert-error" role="alert">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span>{{ $errors->first() }}</span>
          </div>
        @endif

        <form method="POST" action="{{ route('login') }}" id="loginForm">
          @csrf

          <div class="input-group-custom">
            <label for="email" class="form-label">Username / Email</label>
            <div class="input-field">
              <i class="bi bi-person input-icon" aria-hidden="true"></i>
              <input
                type="email"
                name="email"
                id="email"
                class="form-control-custom @error('email') is-invalid @enderror"
                value="{{ old('email') }}"
                placeholder="Enter your username or email"
                autocomplete="username"
                required
              >
            </div>
          </div>

          <div class="input-group-custom">
            <label for="password" class="form-label">Password</label>
            <div class="input-field">
              <i class="bi bi-lock input-icon" aria-hidden="true"></i>
              <input
                type="password"
                name="password"
                id="password"
                class="form-control-custom @error('password') is-invalid @enderror"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
              >
              <button type="button" class="toggle-password" id="togglePassword" aria-label="Show password">
                <i class="bi bi-eye" id="toggleIcon"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-login" id="loginBtn">
            <span>Sign In</span>
            <i class="bi bi-arrow-right"></i>
          </button>
        </form>
      </div>

      <div class="login-footer">
        <span>&copy; {{ date('Y') }} Kiblawan Water District. All rights reserved.</span>
      </div>
    </div>

    <div class="brand-badge mx-auto d-flex justify-content-center">
      <i class="bi bi-droplet-fill"></i>
      <span>Secure staff portal</span>
    </div>
  </div>

  <script>
    (function () {
      const passwordInput = document.getElementById('password');
      const toggleBtn = document.getElementById('togglePassword');
      const toggleIcon = document.getElementById('toggleIcon');
      const form = document.getElementById('loginForm');
      const loginBtn = document.getElementById('loginBtn');

      toggleBtn.addEventListener('click', function () { 
        const isHidden = passwordInput.type === 'password';
        passwordInput.type = isHidden ? 'text' : 'password';
        toggleIcon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
        toggleBtn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
      });

      form.addEventListener('submit', function () {
        loginBtn.disabled = true;
        loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span><span>Signing in...</span>';
      });
    })();
  </script>
</body>
</html>
