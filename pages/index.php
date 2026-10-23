<?php $pageTitle = 'Home';
$metaDescription = 'Shop authentic perfumes, body sprays and gift sets in Ghana. Prices from GH₵10, nationwide delivery and pay on delivery available. Order from Handel Luxe GH.';
$breadcrumbs = [['label' => 'Home']]; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="hero">
  <div class="container">
    <div class="hero-split">
      <div class="hero-text">
        <h1>Discover Your Signature Scent</h1>
        <p>Premium perfumes and fragrances for every occasion</p>
        <div class="hero-cta">
          <a href="<?= base_url('/products') ?>" class="btn btn-secondary">Shop Now</a>
          <a href="<?= base_url('/products?category=5') ?>" class="btn btn-primary">Browse Gift Sets</a>
        </div>
        <div class="hero-usp">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
          <span>Nationwide delivery &bull; Pay on delivery available</span>
        </div>
      </div>
      <div class="hero-visual">
        <div class="hero-glow"></div>
        <div class="hero-particles">
          <div class="particle"></div><div class="particle"></div><div class="particle"></div><div class="particle"></div>
          <div class="particle"></div><div class="particle"></div><div class="particle"></div><div class="particle"></div>
        </div>
        <div class="perfume-bottle">
          <img src="<?= base_url('/images/anime.png') ?>" alt="Premium Perfume" class="hero-perfume-img">
        </div>
      </div>
    </div>
  </div>
</div>
<div class="trust-bar">
  <div class="container">
    <div class="trust-grid">
      <div class="trust-item">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
        <span>Authentic Fragrances, Sourced from Authorized Suppliers</span>
      </div>
      <div class="trust-item">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="2" x2="12" y2="22"></line><path d="M12 6l6 6-6 6-6-6 6-6z" fill="none"></path></svg>
        <span>Nationwide Delivery</span>
      </div>
      <div class="trust-item">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        <span>Secure Paystack Payments</span>
      </div>
      <div class="trust-item">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
        <span>Premium Customer Service</span>
      </div>
    </div>
  </div>
</div>
<div class="container">
  <div class="section">
    <h2 class="section-title">Featured Fragrances</h2>
    <p class="section-sub">Our most sought-after perfumes</p>
    <div class="product-grid" id="featured" aria-label="Featured fragrances" aria-busy="true"><div class="loading-placeholder"><div class="spinner"></div><p>Loading fragrances...</p></div></div>
  </div>
  <div class="section">
    <h2 class="section-title">Shop by Category</h2>
    <p class="section-sub">Find the perfect fragrance for you</p>
    <div class="category-grid" id="category-grid"><div class="loading-placeholder"><div class="spinner"></div><p>Loading categories...</p></div></div>
  </div>
  <div class="section">
    <h2 class="section-title">New Arrivals</h2>
    <p class="section-sub">Just landed in our collection</p>
    <div class="product-grid" id="new-arrivals" aria-label="New arrivals" aria-busy="true"><div class="loading-placeholder"><div class="spinner"></div><p>Loading fragrances...</p></div></div>
  </div>
  <div class="section">
    <h2 class="section-title">Shop Our Bestsellers</h2>
    <p class="section-sub">The fragrances our customers keep coming back for</p>
    <div class="case-studies">
      <a href="<?= base_url('/product/15') ?>" class="case-card">
        <div class="case-img"><img src="<?= base_url('/images/products/khamrah-lattafa.jpg') ?>" alt="Lattafa Khamrah Qahwa Eau De Parfum bottle" loading="lazy"></div>
        <div class="case-body">
          <span class="case-tag">Bestseller</span>
          <h3>Lattafa Khamrah Qahwa</h3>
          <p>A warm coffee-and-spice signature scent, and the strongest long-lasting performer in our collection.</p>
          <span class="case-price">GH₵450.00</span>
        </div>
      </a>
      <a href="<?= base_url('/product/9') ?>" class="case-card">
        <div class="case-img"><img src="<?= base_url('/images/products/badee.png') ?>" alt="Lattafa Badee Al Oud Prestige Series perfume" loading="lazy"></div>
        <div class="case-body">
          <span class="case-tag">Most Gifted</span>
          <h3>Badee Al Oud Prestige</h3>
          <p>Oud and amber depth that lasts all day, our most popular gift for special occasions.</p>
          <span class="case-price">GH₵280.00</span>
        </div>
      </a>
      <a href="<?= base_url('/product/49') ?>" class="case-card">
        <div class="case-img"><img src="<?= base_url('/images/products/berry-weekend.jpg') ?>" alt="Berries Weekend Pink Edition Eau De Parfum" loading="lazy"></div>
        <div class="case-body">
          <span class="case-tag">Her Favourite</span>
          <h3>Berries Weekend Pink</h3>
          <p>A sweet, fruity everyday fragrance and a consistent favourite among our women's collection.</p>
          <span class="case-price">GH₵140.00</span>
        </div>
      </a>
    </div>
    <div style="text-align:center;margin-top:28px;">
      <a href="<?= base_url('/products') ?>" class="btn btn-primary">View All Fragrances</a>
    </div>
  </div>
  <div class="section">
    <div class="response-promise-band">
      <div class="response-promise">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <div>
          <h3>We reply within 24 hours</h3>
          <p>Call or WhatsApp <strong>0542332244</strong> or <strong>0549986486</strong>, or send a message through our contact form and we will get back to you the same day whenever possible.</p>
        </div>
      </div>
    </div>
  </div>
  <div class="section">
    <h2 class="section-title">Frequently Asked Questions</h2>
    <p class="section-sub">Quick answers about delivery, payment and returns</p>
    <div class="faq-preview">
      <?php
      require_once __DIR__ . '/../config/delivery.php';
      // Same source of truth as the FAQ page: fees come from the zones
      // table the admin manages, never hardcoded here.
      $faqZones = getDeliveryZones();
      $feeLines = [];
      foreach ($faqZones as $z) {
        if ($z['category'] === 'pickup') continue;
        $feeLines[] = $z['label'] . ': ' . ($z['fee'] > 0 ? 'GH₵' . number_format($z['fee'], 0) : 'free');
      }
      $homeFaqs = [
        ['How much is delivery?', 'Delivery fees by area: ' . implode(', ', $feeLines) . '. Pickup in Swedru is always free.'],
        ['How do I pay?', 'Pay online with card or mobile money through Paystack, or pay in cash on delivery.'],
        ['Can I track my order?', 'Yes, with your order number and email on our Track Order page.'],
      ];
      foreach ($homeFaqs as $faq): ?>
      <details class="faq-item">
        <summary><?= htmlspecialchars($faq[0]) ?></summary>
        <p><?= htmlspecialchars($faq[1]) ?></p>
      </details>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:24px;">
      <a href="<?= base_url('/faq') ?>" class="btn btn-outline">Read All FAQs</a>
    </div>
  </div>
  <div class="section">
    <div class="newsletter-band">
      <h2>Join Our Newsletter</h2>
      <p>Be the first to know about new arrivals, exclusive offers and discounts.</p>
      <form id="newsletter-form">
        <div class="newsletter-fields">
          <input type="email" id="nl-email" placeholder="Enter your email address" required aria-label="Email address">
          <button type="submit" class="btn btn-secondary">Subscribe</button>
        </div>
        <label class="newsletter-consent"><input type="checkbox" id="nl-consent" required> I agree to receive marketing emails and to the <a href="<?= base_url('/privacy') ?>">Privacy Policy</a>. You can unsubscribe at any time.</label>
      </form>
      <div id="nl-msg"></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  async function loadProductSection(endpoint, targetId, emptyMessage) {
    const target = document.getElementById(targetId);
    showProductSkeletons(target, 4);
    try {
      const items = await fetchJSON(B + endpoint);
      target.innerHTML = items.length ? items.map(productCard).join('') : '<div class="empty-state"><h3>' + emptyMessage + '</h3></div>';
    } catch (err) {
      target.innerHTML = '<div class="load-error" role="alert"><p>We couldn’t load this collection.</p><button type="button" class="btn btn-outline btn-sm load-retry">Try again</button></div>';
      target.querySelector('.load-retry').addEventListener('click', function() {
        loadProductSection(endpoint, targetId, emptyMessage);
      });
    } finally {
      target.setAttribute('aria-busy', 'false');
    }
  }
  function loadCategories() {
    const grid = document.getElementById('category-grid');
    grid.setAttribute('aria-busy', 'true');
    grid.innerHTML = Array.from({ length: 5 }, function() {
      return '<div class="category-skeleton" aria-hidden="true"><span class="skeleton-block"></span><span class="skeleton-block"></span></div>';
    }).join('');
    fetchJSON(B + '/api/products/categories').then(function(categories) {
      const withProducts = categories.filter(c => c.product_count > 0);
      grid.innerHTML = withProducts.length ? withProducts.map((c, i) =>
        '<a href="' + B + '/products?category=' + c.id + '" class="category-card" style="animation-delay:' + (i * 70) + 'ms;">' +
          '<img src="' + escapeHtml(c.image || (B + '/images/placeholder.png')) + '" alt="' + escapeHtml(c.name) + '" loading="lazy" decoding="async">' +
          '<div class="category-card-overlay"><span class="category-card-name">' + escapeHtml(c.name) + '</span><span class="category-card-count">' + c.product_count + ' product' + (c.product_count === 1 ? '' : 's') + '</span></div>' +
        '</a>'
      ).join('') : '<div class="empty-state"><h3>No categories yet</h3></div>';
    }).catch(function() {
      grid.innerHTML = '<div class="load-error" role="alert"><p>Categories are temporarily unavailable.</p><button type="button" class="btn btn-outline btn-sm load-retry">Try again</button></div>';
      grid.querySelector('.load-retry').addEventListener('click', loadCategories);
    }).finally(function() {
      grid.setAttribute('aria-busy', 'false');
    });
  }
  loadProductSection('/api/products/featured/all', 'featured', 'No featured products yet');
  loadProductSection('/api/products/new/arrivals', 'new-arrivals', 'No new arrivals yet');
  loadCategories();
  loadCartCount();
  fetchJSON(B + '/api/auth/me').then(function(u) { setAuthLinks(u); }).catch(function() { setAuthLinks(null); });

  document.getElementById('newsletter-form').onsubmit = async (e) => {
    e.preventDefault();
    const email = document.getElementById('nl-email').value.trim();
    const msg = document.getElementById('nl-msg');
    const button = e.currentTarget.querySelector('button[type="submit"]');
    const finishLoading = setButtonLoading(button, 'Subscribing');
    try {
      await fetchJSON(B + '/api/newsletter/subscribe', { method: 'POST', body: JSON.stringify({ email: email, consent: document.getElementById('nl-consent').checked }) });
      msg.innerHTML = '<p class="success-msg">Thanks for subscribing! Watch your inbox for offers and new arrivals.</p>';
      document.getElementById('newsletter-form').reset();
      finishLoading('Subscribed');
    } catch (err) {
      msg.innerHTML = '<p class="error-msg">' + escapeHtml(err.message) + '</p>';
      finishLoading();
    }
  };
})();
</script>
