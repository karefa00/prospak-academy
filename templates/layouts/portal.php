<?php require dirname(__DIR__) . '/components/head.php'; ?>
<body>
 <div class="d-lg-flex">
  <?php require dirname(__DIR__) . '/components/sidebar.php'; ?>
  <div class="prospak-main flex-grow-1 d-flex flex-column">
   <?php require dirname(__DIR__) . '/components/header.php'; ?>
   <main class="container-fluid p-4 p-lg-5 flex-grow-1">
    <?= $content ?>
   </main>
   <?php require dirname(__DIR__) . '/components/footer.php'; ?>
  </div>
 </div>
 <script src="<?= \Prospak\Core\View::escape(\Prospak\Core\Config::url('/assets/js/libs.min.js')) ?>" defer></script>
 <script src="<?= \Prospak\Core\View::escape(\Prospak\Core\Config::url('/assets/js/hope-ui.js')) ?>" defer></script>
</body>
</html>
