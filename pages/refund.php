<?php $pageTitle = 'Refund Policy';
$metaDescription = 'When and how you can return a fragrance to Handel Luxe GH, and how quickly refunds are processed.';
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'Refund Policy']]; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>

<div class="hero">
  <div class="container">
    <h1>Refund Policy</h1>
    <p>Our returns and refunds process, in plain language</p>
  </div>
</div>

<div class="container">
  <div class="section" style="max-width:850px;margin:0 auto;">
    <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:30px;">Last updated: September 2026</p>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">1. When You Can Return an Item</h2>
      <p style="color:var(--text-muted);line-height:1.9;margin-bottom:10px;">Fragrances are personal care items, so we accept returns only in these cases:</p>
      <ul style="color:var(--text-muted);line-height:1.9;padding-left:25px;">
        <li>You received the wrong item</li>
        <li>The item arrived damaged or leaking</li>
        <li>The item is shown to be inauthentic</li>
      </ul>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">2. How to Request a Return</h2>
      <p style="color:var(--text-muted);line-height:1.9;">Contact us within <strong>48 hours of delivery</strong> with your order number and a photo of the item. Email <strong>drdehandel@gmail.com</strong> or call <strong>0542332244 | 0549986486</strong>. You can also message us on WhatsApp from any page of this website.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">3. What We Refund</h2>
      <p style="color:var(--text-muted);line-height:1.9;">Approved returns are refunded <strong>in full, including the delivery fee</strong>. If you paid online through Paystack, the money is returned to the same card or mobile money account you paid with, usually within a few business days of the return being received and checked. Pay-on-delivery orders are refunded by mobile money to a number you provide.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">4. What Cannot Be Returned</h2>
      <p style="color:var(--text-muted);line-height:1.9;">Items that have been opened, used, or damaged after delivery cannot be returned. This is standard for fragrances for hygiene and safety reasons, and because a scent cannot be resold once it has been opened.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">5. Cancelled and Failed Payments</h2>
      <p style="color:var(--text-muted);line-height:1.9;">If you cancel an order before it is dispatched, or an online payment fails or is abandoned, nothing is owed and any payment that did go through is reversed. You are never charged for an order that was not delivered.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">6. Damaged or Missing Deliveries</h2>
      <p style="color:var(--text-muted);line-height:1.9;">If your parcel arrives damaged, or an item is missing from it, tell the delivery agent at the door if you can, and contact us within 48 hours with your order number. We will replace the item or refund you.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">7. How Long Refunds Take</h2>
      <p style="color:var(--text-muted);line-height:1.9;">Once a return is approved and received, refunds are processed within 5 business days. Mobile money refunds usually land the same day they are sent; card refunds depend on your bank and can take 3 to 10 business days to appear.</p>
    </div>

    <div>
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">8. Questions</h2>
      <p style="color:var(--text-muted);line-height:1.9;">Email <strong>drdehandel@gmail.com</strong> or call <strong>0542332244 | 0549986486</strong>. This policy is part of our <a href="<?= base_url('/terms') ?>" class="text-link">Terms &amp; Conditions</a>.</p>
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
