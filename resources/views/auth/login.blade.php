<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Water District System</title>
  <!-- Bootstrap 5 CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      height: 100vh;
      background: url('{{ asset('WDMS/img/logo/hero.jpg') }}') no-repeat center center/cover;
      display: flex;
      justify-content: center;
      align-items: center;
      position: relative;
    }

    .overlay {
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0, 0, 50, 0.5);
      z-index: 0;
    }

    .login-card {
      position: relative;
      z-index: 1;
      background: rgba(255, 255, 255, 0.95);
      border-radius: 15px;
      box-shadow: 0px 8px 25px rgba(0, 0, 0, 0.3);
      padding: 40px;
      width: 100%;
      max-width: 400px;
      animation: fadeIn 0.8s ease-in-out;
    }

    .login-card h2 {
      color: #0077b6;
      margin-bottom: 20px;
    }

    .btn-custom {
      background: #0077b6;
      color: white;
    }

    .btn-custom:hover {
      background: #023e8a;
      color: white;
    }

    .logo {
      width: 80px;
      height: 80px;
      margin-bottom: 15px;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>
  <div class="overlay"></div>
  <div class="login-card text-center">
    <!-- Logo Placeholder -->
    <img src="{{url('WDMS/img/logo/logo.png')}}" 
         alt="Water District Logo" class="logo">

    <h2>HagunoyWD-IUMS Login</h2>
    <form method="POST" action="/login">
      @csrf
      <div class="mb-3 text-start">
        <label for="email" class="form-label">Username/Email</label>
        <input type="email" name="email" id="email" class="form-control" placeholder="Enter your username or email" required>
      </div>
      <div class="mb-3 text-start">
        <label for="password" class="form-label">Password</label>
        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
      </div>
      <button type="submit" class="btn btn-custom w-100">Login</button>
    </form>
    {{-- <div class="text-center mt-3">
      <a href="/register">Don’t have an account? Register</a>
    </div> --}}
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
