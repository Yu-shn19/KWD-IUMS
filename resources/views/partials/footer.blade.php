 <footer class="sticky-footer bg-white" style="border-top: 1px solid #e2e8f0;">
  <style>
    /* Footer Styling */
    footer.sticky-footer {
      background: linear-gradient(90deg, #ffffff 0%, #f8fafc 100%) !important;
      border-top: 1px solid #e2e8f0;
      padding: 1.5rem;
      margin-top: auto;
      box-shadow: 0 -4px 12px rgba(59, 130, 246, 0.08);
    }

    footer.sticky-footer a {
      color: #3b82f6;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    footer.sticky-footer a:hover {
      color: #1e40af;
      text-decoration: underline;
    }

    footer.sticky-footer .text-muted {
      color: #64748b !important;
    }
  </style>
        
  <script src="{{url('WDMS/vendor/jquery/jquery.min.js')}}"></script>
  <script src="{{url('WDMS/vendor/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
  <script src="{{url('WDMS/vendor/jquery-easing/jquery.easing.min.js')}}"></script>
  <script src="{{url('WDMS/js/ruang-admin.min.js')}}"></script>
  <script src="{{url('WDMS/vendor/chart.js/Chart.min.js')}}"></script>
  <script src="{{url('WDMS/js/demo/chart-area-demo.js')}}"></script>
  
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- Global Script -->
  <script>
    // Scroll to top button functionality
    window.addEventListener('scroll', function() {
      const scrollToTopBtn = document.querySelector('.scroll-to-top');
      if (window.pageYOffset > 100) {
        scrollToTopBtn.classList.add('show');
      } else {
        scrollToTopBtn.classList.remove('show');
      }
    });

    // Smooth scroll
    document.querySelector('.scroll-to-top')?.addEventListener('click', function(e) {
      e.preventDefault();
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
  </script>
</footer>
