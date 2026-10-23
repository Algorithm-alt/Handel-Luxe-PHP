<?php $pageTitle = 'Frequently Asked Questions';
$metaDescription = 'Answers to common questions about ordering from Handel Luxe GH: delivery fees across Ghana, payment options, how to track your order, returns and authentic products.';
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'FAQ']];
require_once __DIR__ . '/../config/delivery.php';

/* Build the delivery-fee answer from the live delivery zones table, so the
   FAQ always matches whatever the admin has configured. */
function faqDeliveryAnswer() {
    $zones = getDeliveryZones(); // ordered: delivery zones first, then pickup
    $lines = [];
    foreach ($zones as $z) {
        if ($z['category'] === 'pickup') continue;
        $lines[] = $z['label'] . ': ' . ($z['fee'] > 0 ? 'GH₵' . number_format($z['fee'], 0) : 'free');
    }
    $hasPickup = false;
    foreach ($zones as $z) {
        if ($z['category'] === 'pickup') { $hasPickup = true; break; }
    }
    $answer = 'Delivery fees by area: ' . ($lines ? implode(', ', $lines) . '. ' : '');
    if ($hasPickup) $answer .= 'You can also pick up your order free at our Swedru store. ';
    $answer .= 'The exact fee for your area is shown at checkout before you pay.';
    return $answer;
}
?>
<?php require __DIR__ . '/../includes/header.php'; ?>

<div class="hero">
  <div class="container">
    <h1>Frequently Asked Questions</h1>
    <p>Everything you need to know about ordering from us</p>
  </div>
</div>

<div class="container">
  <div class="section" style="max-width:850px;margin:0 auto;">
    <?php
    $faqs = [
      ['How do I pay for my order?', 'Two options: pay online with card or mobile money through Paystack, or pay in cash on delivery. You choose the payment method at checkout.'],
      ['How long does delivery take?', 'Orders within Swedru and Accra are typically delivered within 1 to 2 days. Other regions take 2 to 4 days depending on your location. We will call or message you to confirm the exact time after your order is placed.'],
      ['Can I track my order?', 'Yes. If you checked out as a guest, use the Track Order page with your order number and the email you used at checkout. Registered customers can see live order status on the My Orders page at any time.'],
      ['Are your perfumes authentic?', 'Yes. Every fragrance we sell is sourced from authorized suppliers. Authenticity is one of the cases in which we accept returns, so if an item is ever shown to be inauthentic you get a full refund.'],
      ['Can I return a product?', 'Fragrances are personal care items, so we accept returns only if you received the wrong item, the item arrived damaged or leaking, or the item is shown to be inauthentic. Contact us within 48 hours of delivery with your order number and a photo. Full details are on our Refund Policy page.'],
      ['What is your response time?', 'We reply to messages and calls within 24 hours, and usually much faster during business hours. Call or WhatsApp 0542332244 or 0549986486, or use the contact form and we will get back to you.'],
      ['Do you have a physical store?', 'Yes, in Swedru. You can select Self-Collection at checkout to pick up your order there at no delivery charge.'],
    ];
    foreach ($faqs as $i => $faq): ?>
    <details class="faq-item" <?= $i === 0 ? 'open' : '' ?>>
      <summary><?= htmlspecialchars($faq[0]) ?></summary>
      <p><?= htmlspecialchars($faq[1]) ?></p>
    </details>
    <?php endforeach; ?>

    <div style="text-align:center;margin-top:40px;">
      <p style="color:var(--text-muted);">Still have a question?</p>
      <a href="<?= base_url('/contact') ?>" class="btn btn-primary">Contact Us</a>
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
