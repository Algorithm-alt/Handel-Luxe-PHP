<?php $pageTitle = 'About Us';
$metaDescription = 'Handel Luxe GH is a Ghanaian fragrance brand offering authentic, long-lasting perfumes at affordable prices with nationwide delivery.';
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'About Us']]; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>

<div class="hero">
  <div class="container">
    <h1>About Handel Luxe GH</h1>
    <p>Premium perfumes and fragrances for every occasion</p>
  </div>
</div>

<div class="container">
  <div class="section">
    <div class="section-title" style="text-align:center;">Who We Are</div>
    <p class="section-sub" style="text-align:center;max-width:800px;margin:0 auto 10px;">Our Story</p>
    <div style="max-width:800px;margin:0 auto;">
      <p style="color:var(--text-muted);line-height:1.9;font-size:1.05rem;text-align:center;">Handel Luxe GH is a Ghanaian fragrance brand dedicated to offering high-quality perfumes, body sprays, deodorants, body lotions, body splashes, and gift sets for both men and women. We help our customers discover long-lasting, authentic fragrances that match their personalities and lifestyles while providing excellent customer service, affordable prices, and reliable nationwide delivery across Ghana.</p>
    </div>
  </div>

  <div class="section">
    <div class="section-title" style="text-align:center;">Why Choose Us</div>
    <p class="section-sub" style="text-align:center;">What makes us different</p>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;max-width:800px;margin:0 auto;">
      <?php $i = 0; foreach (['Authentic Fragrances', 'Long-Lasting Scents', 'Affordable Prices', 'Premium Customer Service', 'Nationwide Delivery', 'Elegant Gift Sets'] as $feature): $i++; ?>
      <div class="about-card" style="animation-delay:<?= $i * 80 ?>ms;">
        <div style="flex-shrink:0;width:32px;height:32px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <span style="font-weight:600;color:var(--text);"><?= $feature ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="section">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;max-width:900px;margin:0 auto;">
      <div class="about-card about-card-lg" style="animation-delay:120ms;">
        <div style="width:50px;height:50px;background:var(--background);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
        </div>
        <h3 style="font-size:1.3rem;font-weight:700;margin-bottom:12px;">Our Mission</h3>
        <p style="color:var(--text-muted);line-height:1.8;">To make premium and long-lasting fragrances accessible to everyone in Ghana through quality products and exceptional customer service.</p>
      </div>
      <div class="about-card about-card-lg" style="animation-delay:240ms;">
        <div style="width:50px;height:50px;background:var(--background);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
        </div>
        <h3 style="font-size:1.3rem;font-weight:700;margin-bottom:12px;">Our Vision</h3>
        <p style="color:var(--text-muted);line-height:1.8;">To become one of Ghana's most trusted and preferred online fragrance brands, recognized for authenticity, reliability, and customer satisfaction.</p>
      </div>
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
