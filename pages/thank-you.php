<?php $pageTitle = 'Thank You For Your Order';
$metaDescription = 'Thank you for shopping with Handel Luxe GH. Track your order, contact our team, or continue shopping our authentic fragrance collection.';
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'Thank You']]; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>

<div class="container">
  <div class="section" style="max-width:600px;margin:0 auto;">
    <div class="form-card" style="text-align:center;">
      <div class="status-icon success-icon">&#10003;</div>
      <h2>Thank You For Your Order</h2>
      <p class="text-muted" style="margin-bottom:24px;">Your order has been received and is being processed. We will contact you shortly to confirm your delivery details.</p>
      <div class="status-actions" style="justify-content:center;">
        <a href="<?= base_url('/track-order') ?>" class="btn btn-primary">Track My Order</a>
        <a href="<?= base_url('/products') ?>" class="btn btn-outline">Continue Shopping</a>
      </div>
    </div>
    <div class="response-promise compact" style="margin-top:24px;">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <p>We respond to every order within <strong>24 hours</strong>. Questions before then? Call or WhatsApp <strong>0542332244</strong> or <strong>0549986486</strong>.</p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(async function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  loadCartCount();
  try { const u = await fetchJSON(B + '/api/auth/me'); setAuthLinks(u); } catch(e) { setAuthLinks(null); }
})();
</script>
