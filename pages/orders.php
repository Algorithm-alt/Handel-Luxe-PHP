<?php $pageTitle = 'My Orders';
$metaDescription = 'View your Handel Luxe GH order history and the live status of every delivery.';
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'My Orders']]; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container">
  <div class="section">
    <h2 class="section-title">My Orders</h2>
    <div id="orders-content"><div class="loading-placeholder"><div class="spinner"></div><p>Loading orders...</p></div></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  const STEPS = [
    { key: 'pending', label: 'Pending' },
    { key: 'processing', label: 'Processing' },
    { key: 'shipped', label: 'Shipped' },
    { key: 'delivered', label: 'Delivered' }
  ];
  /* Visual progress through the fulfilment pipeline. `cancelled` never
     advances a step — it replaces the stepper with a clear notice. */
  function orderStepper(status) {
    if (status === 'cancelled') {
      return '<div class="order-stepper-cancelled" role="status">This order was cancelled.</div>';
    }
    let current = STEPS.findIndex(s => s.key === status);
    if (current < 0) current = 0;
    return '<ol class="order-stepper" aria-label="Order progress">' + STEPS.map((s, i) => {
      const cls = i < current ? 'done' : (i === current ? 'active' : '');
      return '<li class="' + cls + '"' + (i === current ? ' aria-current="step"' : '') + '>' + escapeHtml(s.label) + '</li>';
    }).join('') + '</ol>';
  }
  (async function() {
    try {
      const u = await fetchJSON(B + '/api/auth/me');
      setAuthLinks(u);
      const orders = await fetchJSON(B + '/api/orders');
      if (orders.length === 0) {
        document.getElementById('orders-content').innerHTML = '<div class="empty-state"><h3>No orders yet</h3><p>Start shopping to see your orders here.</p><a href="' + B + '/products" class="btn btn-primary">Shop Now</a></div>';
        return;
      }
      document.getElementById('orders-content').innerHTML = orders.map(o => `
        <div class="order-card">
          <div class="order-header">
            <div><strong>Order #${o.id}</strong><br><small class="text-muted">${new Date(o.created_at).toLocaleString()}</small></div>
            <div class="order-meta">
              <span class="status status-${o.status}">${o.status}</span>
              <div class="order-payment-badge"><span class="status status-${o.payment_status === 'success' ? 'delivered' : 'pending'}">${o.payment_status === 'success' ? 'Paid' : 'Pending Payment'}</span></div>
            </div>
          </div>
          ${orderStepper(o.status)}
          <div class="order-total">${formatPrice(o.total)}</div>
          <div class="order-method">${o.payment_method === 'delivery' ? 'Pay on Delivery' : 'Paystack'} | ${escapeHtml(o.shipping_address || '')}</div>
        </div>
      `).join('');
    } catch(e) {
      document.getElementById('orders-content').innerHTML = '<div class="empty-state"><h3>Please login to view orders</h3><a href="' + B + '/login" class="btn btn-primary">Login</a></div>';
      setAuthLinks(null);
    }
  })();
})();
</script>
