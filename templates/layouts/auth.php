<?php require dirname(__DIR__) . '/components/head.php'; ?>
<body class="prospak-auth-bg">
 <main class="container-fluid">
  <div class="row align-items-center min-vh-100">
   <div class="col-lg-6 col-xl-5 d-flex justify-content-center px-4 py-5">
    <div class="card shadow-sm prospak-auth-card">
     <div class="card-body p-4 p-md-5">
      <a href="<?= \Prospak\Core\View::escape(\Prospak\Core\Config::url('/login')) ?>" class="text-decoration-none d-flex align-items-center gap-2 mb-4">
       <span class="bg-primary rounded text-white p-2 fw-bold">PA</span>
       <span class="prospak-brand fs-4">Prospak Academy</span>
      </a>
      <?= $content ?>
     </div>
    </div>
   </div>
   <div class="col-lg-6 col-xl-7 d-none d-lg-flex flex-column align-items-center text-center px-5">
    <img class="prospak-auth-image" alt="Hope UI illustration of connected academic workspace" src="<?= \Prospak\Core\View::escape(\Prospak\Core\Config::url('/assets/images/auth-01.png')) ?>">
    <h2 class="h3 mt-3">One secure place for academic access.</h2>
    <p class="text-muted">Prospak Academy Student Portal &amp; Grade Management System</p>
   </div>
  </div>
 </main>
 <?php if (str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), \Prospak\Core\Config::url('/reset-password'))): ?>
  <script src="<?= \Prospak\Core\View::escape(\Prospak\Core\Config::url('/assets/js/reset-token.js')) ?>" defer></script>
 <?php endif; ?>
</body>
</html>
