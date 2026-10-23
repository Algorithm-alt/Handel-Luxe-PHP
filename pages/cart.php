<?php $pageTitle = 'Shopping Cart';
$metaDescription = 'Review the fragrances in your Handel Luxe GH cart and proceed to checkout with pay on delivery or secure Paystack payment.';
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'Cart']]; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container">
  <div class="section">
    <h2 class="section-title">Shopping Cart</h2>
    <div id="cart-content"><div class="cart-skeleton" aria-hidden="true">
      <div class="cart-skeleton-row"><span class="skeleton-block cart-skeleton-thumb"></span><span class="skeleton-block cart-skeleton-line"></span><span class="skeleton-block cart-skeleton-line short"></span></div>
      <div class="cart-skeleton-row"><span class="skeleton-block cart-skeleton-thumb"></span><span class="skeleton-block cart-skeleton-line"></span><span class="skeleton-block cart-skeleton-line short"></span></div>
      <div class="cart-skeleton-row"><span class="skeleton-block cart-skeleton-thumb"></span><span class="skeleton-block cart-skeleton-line"></span><span class="skeleton-block cart-skeleton-line short"></span></div>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  (async function() {
    try { const u = await fetchJSON(B + '/api/auth/me'); setAuthLinks(u); } catch(e) { setAuthLinks(null); }
    loadCartCount();
    loadCartView();
  })();
  async function loadCartView() {
    try {
      const data = await fetchJSON(B + '/api/cart');
      if (data.items.length === 0) {
        document.getElementById('cart-content').innerHTML = '<div class="empty-state"><h3>Your cart is empty</h3><p>Browse our collection and find your perfect scent.</p><a href="' + B + '/products" class="btn btn-primary">Shop Now</a></div>';
        return;
      }
      let html = '<table class="cart-table"><thead><tr><th>Product</th><th>Price</th><th>Quantity</th><th>Total</th><th>Action</th></tr></thead><tbody>';
      data.items.forEach(item => {
        html += `<tr>
          <td data-label="Product" class="cart-item-cell"><img src="${escapeHtml(item.image || (typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '') + '/images/placeholder.png')}" alt="${escapeHtml(item.name)}"><span>${escapeHtml(item.name)}</span></td>
          <td data-label="Price">${formatPrice(item.price)}</td>
          <td data-label="Quantity"><input type="number" value="${item.quantity}" min="1" max="99" class="qty-input" onchange="updateCartQty(${item.id}, this.value)"></td>
          <td data-label="Total">${formatPrice(item.price * item.quantity)}</td>
          <td data-label="Action"><button class="btn btn-danger btn-sm" onclick="removeCartItem(${item.id})">Remove</button></td>
        </tr>`;
      });
      html += '</tbody></table>';
      html += `<div class="cart-total"><p>Subtotal: <span class="amount">${formatPrice(data.total)}</span></p><a href="${B}/checkout" class="btn btn-primary">Proceed to Checkout</a></div>`;
      document.getElementById('cart-content').innerHTML = html;
      loadCartCount();
    } catch(e) {
      document.getElementById('cart-content').innerHTML = '<div class="empty-state"><h3>Failed to load cart</h3><p>' + escapeHtml(e.message) + '</p></div>';
    }
  }
  async function updateCartQty(id, qty) {
    try {
      await fetchJSON(B + '/api/cart/update/' + id, { method: 'PUT', body: JSON.stringify({ quantity: parseInt(qty) }) });
      loadCartView();
    } catch(e) { toast(e.message, 'error'); }
  }
  async function removeCartItem(id) {
    try {
      await fetchJSON(B + '/api/cart/remove/' + id, { method: 'DELETE' });
      toast('Item removed', 'success');
      loadCartView();
    } catch(e) { toast(e.message, 'error'); }
  }
  // Cart rows use inline onchange/onclick, which need global references
  window.updateCartQty = updateCartQty;
  window.removeCartItem = removeCartItem;
})();
</script>
