<?php $pageTitle = 'Page Not Found';
$metaDescription = 'The page you are looking for does not exist. Browse our authentic fragrance collection instead.'; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container">
  <div class="empty-state">
    <h2>404 - Page Not Found</h2>
    <p>The page you're looking for doesn't exist or may have moved.</p>
    <div class="status-actions" style="justify-content:center;">
      <a href="<?= base_url('/') ?>" class="btn btn-primary">Go Home</a>
      <a href="<?= base_url('/products') ?>" class="btn btn-outline">Shop Fragrances</a>
      <a href="<?= base_url('/contact') ?>" class="btn btn-outline">Contact Us</a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
