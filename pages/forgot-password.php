<?php $pageTitle = 'Forgot Password';
$metaDescription = 'Reset your Handel Luxe GH account password with a secure one-hour email link.'; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container">
  <div class="form-card">
    <h2>Forgot Password</h2>
    <p class="form-subtitle">Enter your email and we'll send you a reset link.</p>
    <form id="forgot-form">
      <div class="form-group"><label for="email">Email</label><input type="email" id="email" required placeholder="your@email.com"></div>
      <div id="msg"></div>
      <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
    </form>
    <p class="form-footer-links"><a href="<?= base_url('/login') ?>">Back to Login</a> &nbsp;|&nbsp; New here? <a href="<?= base_url('/register') ?>" class="text-link">Create an account</a></p>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  // A GET request re-syncs the CSRF cookie with the session before the
  // form is submitted (the submit is the only API call on this page).
  loadCartCount();
  document.getElementById('forgot-form').onsubmit = async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true; btn.textContent = 'Sending...';
    try {
      const data = await fetchJSON(B + '/api/auth/forgot-password', { method: 'POST', body: JSON.stringify({ email: document.getElementById('email').value }) });
      const msg = document.getElementById('msg');
      msg.replaceChildren();
      const p = document.createElement('p');
      p.className = 'success-msg';
      p.textContent = data.message;
      msg.appendChild(p);
      document.getElementById('forgot-form').reset();
      btn.disabled = false; btn.textContent = 'Send Reset Link';
    } catch (err) { document.getElementById('msg').innerHTML = '<p class="error-msg">' + escapeHtml(err.message) + '</p>'; btn.disabled = false; btn.textContent = 'Send Reset Link'; }
  };
})();
</script>
