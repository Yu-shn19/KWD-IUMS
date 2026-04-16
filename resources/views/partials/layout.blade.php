<!DOCTYPE html>
<html lang="en">
  @include('partials.header')
  
  <style>
    /* Additional layout-specific adjustments */
    html, body {
      height: 100%;
      background: var(--bg);
      color: var(--text);
    }

    /* Scroll to Top Button */
    .scroll-to-top {
      position: fixed;
      right: 1.5rem;
      bottom: 1.5rem;
      display: none;
      width: 48px;
      height: 48px;
      text-align: center;
      color: white;
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      line-height: 48px;
      border-radius: 50%;
      z-index: 1050;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35);
      cursor: pointer;
    }

    .scroll-to-top:hover {
      background: linear-gradient(135deg, var(--primary-dark) 0%, #0891b2 100%);
      box-shadow: 0 8px 28px rgba(59, 130, 246, 0.45);
      transform: translateY(-3px);
    }

    .scroll-to-top.show {
      display: block;
    }

    /* Animation Classes */
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .fade-in {
      animation: fadeIn 0.4s ease;
    }

    @keyframes slideInLeft {
      from {
        opacity: 0;
        transform: translateX(-20px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    .slide-in-left {
      animation: slideInLeft 0.4s ease;
    }

    /* Smooth scrolling */
    html {
      scroll-behavior: smooth;
    }
  </style>

<body id="page-top">
  <div id="wrapper">

    <!-- Sidebar -->
     @include('partials.sidebar')
    <!-- Sidebar -->
    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <!-- TopBar -->       
      @include('partials.navbar')
        <!-- Topbar -->

        <!-- Container Fluid-->
        <div class="container-fluid" id="container-wrapper">
         @include('partials.main-content')
        <!---Container Fluid-->
      </div>
    
    </div>
  </div>
  <!-- Scroll to top -->
  <a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
  </a>
</body>
  <!-- Footer -->
        @include('partials.footer')
      <!-- Footer -->
</html>