<?php $pageTitle = 'Terms & Conditions';
$metaDescription = 'The rules that govern shopping with Handel Luxe GH: products, pricing, delivery and payment terms in plain language.';
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'Terms & Conditions']]; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>

<div class="hero">
  <div class="container">
    <h1>Terms & Conditions</h1>
    <p>The rules that govern shopping with Handel Luxe GH</p>
  </div>
</div>

<div class="container">
  <div class="section" style="max-width:850px;margin:0 auto;">
    <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:30px;">Last updated: September 2026</p>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">1. About These Terms</h2>
      <p style="color:var(--text-muted);line-height:1.9;">By placing an order on the Handel Luxe GH website, you agree to these Terms & Conditions. If you do not agree with any part of them, please do not use the website to make purchases.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">2. Products and Availability</h2>
      <p style="color:var(--text-muted);line-height:1.9;">We sell perfumes, body sprays, deodorants, body lotions, body splashes and gift sets. All products are sourced from authorized suppliers. Because stock changes daily, an item may sell out after you place an order. If that happens, we will contact you to arrange a replacement or a full refund.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">3. Pricing</h2>
      <p style="color:var(--text-muted);line-height:1.9;">All prices are quoted in Ghana cedis (GH₵) and include applicable taxes. Delivery fees are shown separately at checkout and depend on your chosen delivery zone. We may change prices at any time, but the price shown at the moment you complete your order is the price you pay.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">4. Payment</h2>
      <p style="color:var(--text-muted);line-height:1.9;">Online payments are processed securely through Paystack. We accept mobile money and card payments. We do not store your card details on our servers. Orders paid online are confirmed once we receive payment confirmation from Paystack.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">5. Delivery</h2>
      <p style="color:var(--text-muted);line-height:1.9;">Delivery is available across Ghana. Fees and estimated times for each zone are displayed at checkout before you pay. Delivery times are estimates and may vary with weather, traffic and courier availability. Delivery is made to the address you provide at checkout, so please check it carefully before completing your order.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">6. Returns and Refunds</h2>
      <p style="color:var(--text-muted);line-height:1.9;margin-bottom:10px;">Because fragrances are personal care items, we accept returns only in these cases:</p>
      <ul style="color:var(--text-muted);line-height:1.9;padding-left:25px;">
        <li>You received the wrong item</li>
        <li>The item arrived damaged or leaking</li>
        <li>The item is proven to be inauthentic</li>
      </ul>
      <p style="color:var(--text-muted);line-height:1.9;margin-top:10px;">To request a return, contact us within 48 hours of delivery with your order number and a photo of the item. Approved returns are refunded in full, including the delivery fee. Items that have been opened, used or damaged after delivery cannot be returned. Full details are in our <a href="<?= base_url('/refund') ?>" class="text-link">Refund Policy</a>.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">7. Accounts</h2>
      <p style="color:var(--text-muted);line-height:1.9;">You may browse and order as a guest, but creating an account lets you track orders and check out faster. You are responsible for keeping your account password confidential. Do not share your account with others.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">8. Acceptable Use</h2>
      <p style="color:var(--text-muted);line-height:1.9;">Do not use this website for fraud, to submit false orders, to scrape product content without permission, or to interfere with its normal operation. We may suspend accounts that abuse the service.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">9. Product Information</h2>
      <p style="color:var(--text-muted);line-height:1.9;">We describe every product as accurately as possible, including size, scent family and brand. Fragrance perception varies from person to person, and the way a scent performs depends on skin type, weather and activity. Scent descriptions are guides, not guarantees.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">10. Limitation of Liability</h2>
      <p style="color:var(--text-muted);line-height:1.9;">To the fullest extent permitted by Ghanaian law, Handel Luxe GH is not liable for indirect losses arising from use of this website or from delayed deliveries. Our liability for any order is limited to the amount you paid for that order.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">11. Changes to These Terms</h2>
      <p style="color:var(--text-muted);line-height:1.9;">We may update these Terms & Conditions from time to time. The current version is always available on this page with its revision date. Changes take effect when posted.</p>
    </div>

    <div>
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">12. Contact Us</h2>
      <p style="color:var(--text-muted);line-height:1.9;">Questions about these terms? Email <strong>drdehandel@gmail.com</strong> or call <strong>0542332244 | 0549986486</strong>.</p>
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
