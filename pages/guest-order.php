<?php $pageTitle = 'Track Your Order';
$metaDescription = 'Track your Handel Luxe GH order with your order number and the email you used at checkout.';
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'Track Order']]; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container">
  <div class="form-card">
    <h2>Track Guest Order</h2>
    <form id="track-form">
      <div class="form-group"><label for="order-id">Order ID</label><input type="number" id="order-id" required placeholder="e.g. 1"></div>
      <div class="form-group"><label for="track-email">Email used at checkout</label><input type="email" id="track-email" required placeholder="your@email.com"></div>
      <div id="msg"></div>
      <button type="submit" class="btn btn-primary btn-block">Track Order</button>
    </form>
    <div id="order-result" class="order-result"></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(async function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  try { const u = await fetchJSON(B + '/api/auth/me'); setAuthLinks(u); } catch(e) { setAuthLinks(null); }
  document.getElementById('track-form').onsubmit = async (e) => {
    e.preventDefault();
    const msgEl = document.getElementById('msg');
    const resultEl = document.getElementById('order-result');
    msgEl.innerHTML = '';
    resultEl.innerHTML = '';
    try {
      const data = await fetchJSON(B + '/api/orders/guest/lookup?id=' + encodeURIComponent(document.getElementById('order-id').value) + '&email=' + encodeURIComponent(document.getElementById('track-email').value));
      let itemsHtml = data.items ? data.items.map(i => `<div class="order-item-row"><img src="${escapeHtml(i.image || (typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '') + '/images/placeholder.png')}" alt="${escapeHtml(i.name)}"><div><strong>${escapeHtml(i.name)}</strong><br><small>Qty: ${i.quantity} x ${formatPrice(i.price)}</small></div></div>`).join('') : '';
      resultEl.innerHTML = `
        <div class="order-card">
          <div class="order-header"><strong>Order #${data.id}</strong><span class="status status-${data.status}">${data.status}</span></div>
          <p>Date: ${new Date(data.created_at).toLocaleString()}</p>
          <p>Total: <strong>${formatPrice(data.total)}</strong></p>
          <p>Payment: ${data.payment_method === 'delivery' ? 'Pay on Delivery' : 'Paystack'} (${data.payment_status})</p>
          ${itemsHtml ? '<div class="order-items-list">' + itemsHtml + '</div>' : ''}
        </div>`;
    } catch (err) {
      msgEl.innerHTML = '<p class="error-msg">' + escapeHtml(err.message) + '</p>';
      resultEl.innerHTML = '';
    }
  };
})();
</script>
