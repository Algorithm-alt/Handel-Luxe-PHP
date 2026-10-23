<?php $pageTitle = 'Contact Us';
$metaDescription = 'Contact Handel Luxe GH by phone, WhatsApp or email. We reply to every message within 24 hours. Call 0542332244 or 0549986486.';
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'Contact']]; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container">
  <div class="contact-layout">
    <div class="form-card">
      <h2>Contact Us</h2>
      <div class="response-promise compact" style="margin-bottom:20px;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <p>We reply to every message within <strong>24 hours</strong>. Call or WhatsApp <strong>0542332244</strong> or <strong>0549986486</strong> for faster help.</p>
      </div>
      <form id="contact-form">
      <div class="form-group"><label for="c-name">Name</label><input type="text" id="c-name" required placeholder="Your name"></div>
      <div class="form-group"><label for="c-email">Email</label><input type="email" id="c-email" required placeholder="your@email.com"></div>
      <div class="form-group"><label for="c-subject">Subject</label><input type="text" id="c-subject" required placeholder="How can we help?"></div>
      <div class="form-group"><label for="c-message">Message</label><textarea id="c-message" rows="5" required placeholder="Your message..."></textarea></div>
      <div class="form-group form-consent"><label><input type="checkbox" id="c-consent" required> I agree that my details are stored so my message can be answered, as described in the <a href="<?= base_url('/privacy') ?>">Privacy Policy</a></label></div>
      <div id="msg"></div>
      <button type="submit" class="btn btn-primary btn-block">Send Message</button>
    </form>
    <p class="form-footer-links">Looking for a quick answer? Check the <a href="<?= base_url('/faq') ?>">FAQ</a>.</p>
  </div>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(async function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  loadCartCount();
  try { const u = await fetchJSON(B + '/api/auth/me'); setAuthLinks(u); } catch(e) { setAuthLinks(null); }
  document.getElementById('contact-form').onsubmit = async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true; btn.textContent = 'Sending...';
    try {
      await fetchJSON(B + '/api/contact', { method: 'POST', body: JSON.stringify({ name: document.getElementById('c-name').value, email: document.getElementById('c-email').value, subject: document.getElementById('c-subject').value, message: document.getElementById('c-message').value, consent: document.getElementById('c-consent').checked }) });
      document.getElementById('msg').innerHTML = '<p class="success-msg">Message sent! We\'ll get back to you soon.</p>';
      document.getElementById('contact-form').reset();
      btn.disabled = false; btn.textContent = 'Send Message';
    } catch (err) { document.getElementById('msg').innerHTML = '<p class="error-msg">' + escapeHtml(err.message) + '</p>'; btn.disabled = false; btn.textContent = 'Send Message'; }
  };
})();
</script>
