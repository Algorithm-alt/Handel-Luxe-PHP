<?php $pageTitle = 'Privacy Policy';
$metaDescription = 'How Handel Luxe GH collects, uses and protects your personal information, in plain language.';
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'Privacy Policy']]; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>

<div class="hero">
  <div class="container">
    <h1>Privacy Policy</h1>
    <p>How we collect, use, and protect your information</p>
  </div>
</div>

<div class="container">
  <div class="section" style="max-width:850px;margin:0 auto;">
    <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:30px;">Last updated: October 2026</p>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">1. Information We Collect</h2>
      <p style="color:var(--text-muted);line-height:1.9;margin-bottom:10px;">When you use the Handel Luxe GH website, we may collect the following personal information:</p>
      <ul style="color:var(--text-muted);line-height:1.9;padding-left:25px;">
        <li>Full name</li>
        <li>Email address</li>
        <li>Phone number</li>
        <li>Shipping and delivery address</li>
        <li>Payment information (processed securely through third-party providers)</li>
      </ul>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">2. How We Use Your Information</h2>
      <p style="color:var(--text-muted);line-height:1.9;margin-bottom:10px;">We use the information we collect to:</p>
      <ul style="color:var(--text-muted);line-height:1.9;padding-left:25px;">
        <li>Process and fulfill your orders</li>
        <li>Communicate order updates and delivery status</li>
        <li>Provide customer support and respond to inquiries</li>
        <li>Improve our website, products, and services</li>
        <li>Send promotional offers and updates (only with your consent)</li>
      </ul>
      <p style="color:var(--text-muted);line-height:1.9;margin-top:10px;">Marketing emails are sent only to people who tick the marketing consent box on the newsletter form. We record when that consent was given and which wording was shown. Registering for an account, or receiving order and delivery messages, does not add you to the marketing list. Every marketing email includes an unsubscribe link, and requesting removal from a link, by email or by phone stops those emails. Order confirmations, delivery updates and password-reset messages are transactional, not marketing.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">3. Data Protection</h2>
      <p style="color:var(--text-muted);line-height:1.9;">We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. Your data is stored securely and is only accessed by authorized personnel who need it to perform their duties. We handle your personal data in line with Ghana's Data Protection Act, 2012 (Act 843).</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">4. Third-Party Sharing</h2>
      <p style="color:var(--text-muted);line-height:1.9;">We do not sell, trade, or rent your personal information to third parties. We may share your information only with trusted delivery partners and payment processors necessary to complete your transactions and deliver your orders.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">5. Cookies</h2>
      <p style="color:var(--text-muted);line-height:1.9;">Our website uses strictly necessary cookies to maintain your login status and shopping cart, and to protect form submissions. We do not use tracking or advertising cookies. Every cookie we set is listed in our <a href="<?= base_url('/cookies') ?>" class="text-link">Cookie Policy</a>. Your account, contact form, and newsletter sign-up all include a consent step that explains what you are agreeing to.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">6. Your Rights</h2>
      <p style="color:var(--text-muted);line-height:1.9;margin-bottom:10px;">Under Ghana's Data Protection Act, 2012 (Act 843) you have the right to:</p>
      <ul style="color:var(--text-muted);line-height:1.9;padding-left:25px;">
        <li>Access the personal information we hold about you</li>
        <li>Request correction of inaccurate information</li>
        <li>Request deletion of your personal data</li>
        <li>Object to processing of your personal data</li>
        <li>Opt out of marketing communications at any time</li>
      </ul>
      <p style="color:var(--text-muted);line-height:1.9;">To exercise any of these rights, email <strong>drdehandel@gmail.com</strong> or call <strong>0542332244 | 0549986486</strong> and we will respond within 30 days.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">7. Children's Privacy</h2>
      <p style="color:var(--text-muted);line-height:1.9;">Our services are not directed to individuals under the age of 16. We do not knowingly collect personal information from children. If you believe a child has provided us with personal data, please contact us and we will promptly delete it.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">8. Changes to This Policy</h2>
      <p style="color:var(--text-muted);line-height:1.9;">We may update this Privacy Policy from time to time. Any changes will be posted on this page with an updated revision date. We encourage you to review this policy periodically.</p>
    </div>

    <div>
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">9. Contact Us</h2>
      <p style="color:var(--text-muted);line-height:1.9;">If you have any questions about this Privacy Policy or how your data is handled, please reach out to us at <strong>drdehandel@gmail.com</strong> or call <strong>0542332244 | 0549986486</strong>.</p>
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
