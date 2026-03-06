<!DOCTYPE html>
<html lang="en">
  @include('partials.header') 

     
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