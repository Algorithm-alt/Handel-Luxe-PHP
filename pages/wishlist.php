<?php $pageTitle = 'Wishlist';
$metaDescription = 'Your saved fragrances at Handel Luxe GH. Revisit your favourites and add them to your cart in one tap.';
$noIndex = true;
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'Wishlist']]; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container">
  <div class="section">
    <h2 class="section-title">My Wishlist</h2>
    <p class="section-sub" id="wishlist-summary" role="status" aria-live="polite">Loading your saved fragrances&hellip;</p>
    <div id="wishlist-content"></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  const content = document.getElementById('wishlist-content');
  const summary = document.getElementById('wishlist-summary');

  function render() {
    const list = getWishlist();
    if (!list.length) {
      summary.textContent = 'Nothing saved yet.';
      content.innerHTML = '<div class="empty-state">' +
        '<h3>Your wishlist is empty</h3>' +
        '<p>Tap the heart on any fragrance to save it here for later.</p>' +
        '<a href="' + B + '/products" class="btn btn-primary">Browse Fragrances</a></div>';
      return;
    }
    summary.textContent = list.length + (list.length === 1 ? ' fragrance saved.' : ' fragrances saved.');
    const placeholder = B + '/images/placeholder.png';
    content.innerHTML = '<div class="product-grid">' + list.map(p => `
      <div class="product-card">
        <div class="img-wrap">
          <a href="${B}/product/${p.id}" aria-label="${escapeHtml(p.name)}">
            <img loading="lazy" decoding="async" src="${escapeHtml(p.image || placeholder)}" alt="${escapeHtml(p.name)}">
          </a>
          <button type="button" class="wishlist-btn active" aria-pressed="true"
            aria-label="Remove ${escapeHtml(p.name)} from wishlist" data-product-id="${escapeHtml(p.id)}"
            onclick="removeFromWishlistPage(this)">${wishlistHeartSvg()}</button>
        </div>
        <div class="info">
          <div class="name">${escapeHtml(p.name)}</div>
          <div class="price">${formatPrice(p.price)}</div>
          <button type="button" class="btn btn-primary btn-sm" onclick="addWishlistToCart(${escapeHtml(p.id)}, this)">Add to Cart</button>
        </div>
      </div>`).join('') + '</div>';
  }

  window.removeFromWishlistPage = function(btn) {
    toggleWishlistBtn(btn);
    render();
  };

  window.addWishlistToCart = async function(id, button) {
    const finishLoading = setButtonLoading(button, 'Adding to cart');
    try {
      await fetchJSON(B + '/api/cart/add', { method: 'POST', body: JSON.stringify({ product_id: id, quantity: 1 }) });
      finishLoading('Added to cart');
      toast('Added to cart!', 'success');
      loadCartCount();
    } catch (e) {
      finishLoading();
      toast(e.message, 'error');
    }
  };

  document.addEventListener('wishlist:changed', updateWishlistCount);
  render();
  loadCartCount();
  fetchJSON(B + '/api/auth/me').then(function(u) { setAuthLinks(u); }).catch(function() { setAuthLinks(null); });
})();
</script>
