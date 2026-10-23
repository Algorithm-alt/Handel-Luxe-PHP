<?php $pageTitle = 'Product Details';
$metaDescription = 'View full details, price and stock for this authentic fragrance from Handel Luxe GH, delivered anywhere in Ghana.';
$productId = $_GET['id'] ?? 0; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container">
  <div id="product-detail"><div class="loading-placeholder"><div class="spinner"></div><p>Loading product...</p></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<div class="pd-sticky-cta" id="pd-sticky-cta" hidden>
  <span class="pd-sticky-price" aria-live="polite"></span>
  <button type="button" class="btn btn-primary btn-sm" onclick="addDetailToCart(parseInt(this.getAttribute('data-product-id'), 10), this)">Add to Cart</button>
</div>
<script>
/* Swap the main image when a thumbnail is chosen. The source travels in a
   data attribute instead of an inline onclick, so an admin-supplied URL can
   never be parsed as JavaScript. */
function setGalleryImage(src, btn) {
  var main = document.getElementById('pd-main-image');
  if (main) main.src = src;
  var thumbs = document.querySelectorAll('.pd-thumb');
  for (var i = 0; i < thumbs.length; i++) thumbs[i].classList.remove('active');
  if (btn) btn.classList.add('active');
}

(async function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  const placeholder = (window.APP_BASE_URL || '') + '/images/placeholder.png';
  try {
    const u = await fetchJSON(B + '/api/auth/me'); setAuthLinks(u);
  } catch(e) { setAuthLinks(null); }
  loadCartCount();
  try {
    const p = await (await fetch(B + '/api/products/<?= (int)$productId ?>')).json();
    if (p.error) throw new Error(p.error);
    document.title = p.name + ' - Handel Luxe GH';

    // 'images' comes from the gallery table; fall back to the single cover
    // column so a product still renders if the gallery is unavailable.
    const gallery = (p.images && p.images.length) ? p.images : [{ image: p.image || placeholder }];
    const mainImage = gallery[0].image || placeholder;

    let thumbsHtml = '';
    if (gallery.length > 1) {
      thumbsHtml = '<div class="pd-thumbs" role="group" aria-label="Product images">' +
        gallery.map(function(g, i) {
          return '<button type="button" class="pd-thumb' + (i === 0 ? ' active' : '') + '" ' +
            'data-gallery-src="' + escapeHtml(g.image) + '" ' +
            'aria-label="View image ' + (i + 1) + ' of ' + gallery.length + '">' +
            '<img src="' + escapeHtml(g.image) + '" alt="' + escapeHtml(p.name) + ' view ' + (i + 1) + '" loading="lazy">' +
            '</button>';
        }).join('') + '</div>';
    }

    const savedOnWishlist = isWishlisted(p.id);

    document.getElementById('product-detail').innerHTML = `<div class="pd-grid">
      <div class="pd-gallery">
        <div class="pd-image">
          <img id="pd-main-image" src="${escapeHtml(mainImage)}" alt="${escapeHtml(p.name)}">
        </div>
        ${thumbsHtml}
      </div>
      <div class="pd-info">
        <div class="category">${escapeHtml(p.category_name || 'General')}</div>
        <h1>${escapeHtml(p.name)}</h1>
        <div class="price">GH₵ ${Number(p.price).toFixed(2)}</div>
        <div class="desc">${escapeHtml(p.description || '')}</div>
        <div class="pd-meta"><span>Stock: <strong>${p.stock > 0 ? p.stock + ' available' : 'Out of stock'}</strong></span></div>
        <div class="qty-control">
          <button onclick="changeQty(-1)" aria-label="Decrease quantity">-</button>
          <input type="number" id="qty" value="1" min="1" max="${p.stock}" aria-label="Quantity">
          <button onclick="changeQty(1)" aria-label="Increase quantity">+</button>
        </div>
        <div class="pd-actions">
          <button type="button" class="btn btn-primary btn-block" onclick="addDetailToCart(${p.id}, this)" ${p.stock <= 0 ? 'disabled' : ''}>${p.stock > 0 ? 'Add to Cart' : 'Out of Stock'}</button>
          <button type="button" class="btn btn-outline pd-wishlist-btn${savedOnWishlist ? ' active' : ''}"
            aria-pressed="${savedOnWishlist ? 'true' : 'false'}"
            data-product-id="${escapeHtml(p.id)}" data-product-name="${escapeHtml(p.name)}"
            data-product-price="${escapeHtml(p.price)}" data-product-image="${escapeHtml(mainImage)}"
            data-product-stock="${escapeHtml(p.stock)}" onclick="toggleWishlistBtn(this)">
            ${wishlistHeartSvg()} ${savedOnWishlist ? 'Saved to wishlist' : 'Save to wishlist'}
          </button>
        </div>
      </div>
    </div>`;

    document.querySelectorAll('.pd-thumb').forEach(function(btn) {
      btn.addEventListener('click', function() {
        setGalleryImage(this.getAttribute('data-gallery-src'), this);
      });
    });

    // Sticky mobile purchase bar (hidden on desktop by CSS).
    var sticky = document.getElementById('pd-sticky-cta');
    if (sticky) {
      sticky.querySelector('.pd-sticky-price').textContent = 'GH₵ ' + Number(p.price).toFixed(2);
      var stickyBtn = sticky.querySelector('button');
      stickyBtn.setAttribute('data-product-id', p.id);
      stickyBtn.disabled = p.stock <= 0;
      if (p.stock <= 0) stickyBtn.textContent = 'Out of Stock';
      sticky.hidden = false;
    }
    window.__pdProduct = p;
  } catch(e) {
    document.getElementById('product-detail').innerHTML = '<div class="empty-state"><h3>Product not found</h3><a href="' + B + '/products" class="btn btn-primary">Back to Products</a></div>';
  }
})();
function changeQty(d) {
  const inp = document.getElementById('qty');
  const v = Math.max(1, Math.min(parseInt(inp.max) || 99, parseInt(inp.value) + d));
  inp.value = v;
}
async function addDetailToCart(id, button) {
  const qty = parseInt(document.getElementById('qty').value) || 1;
  const finishLoading = setButtonLoading(button, 'Adding to cart');
  try {
    await fetchJSON((typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '') + '/api/cart/add', { method: 'POST', body: JSON.stringify({ product_id: id, quantity: qty }) });
    finishLoading('Added to cart');
    toast('Added to cart!', 'success');
    loadCartCount();
  } catch(e) {
    finishLoading();
    toast(e.message, 'error');
  }
}
</script>
