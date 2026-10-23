<?php $pageTitle = 'Create Account';
$metaDescription = 'Create a Handel Luxe GH account to order authentic fragrances faster and track your deliveries.'; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container">
  <div class="form-card">
    <h2>Create Account</h2>
    <form id="register-form">
      <div class="form-group"><label for="name">Full Name</label><input type="text" id="name" required placeholder="Your full name"></div>
      <div class="form-group"><label for="email">Email</label><input type="email" id="email" required placeholder="your@email.com"></div>
      <div class="form-group"><label for="password">Password</label><input type="password" id="password" required placeholder="Min 8 characters"></div>
      <div class="form-group form-consent"><label><input type="checkbox" id="consent" required> I agree to the <a href="<?= base_url('/terms') ?>">Terms &amp; Conditions</a> and <a href="<?= base_url('/privacy') ?>">Privacy Policy</a></label></div>
      <div id="msg"></div>
      <button type="submit" class="btn btn-primary btn-block">Register</button>
    </form>
    <p class="form-footer-links">Already have an account? <a href="<?= base_url('/login') ?>" class="text-link">Login</a></p>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  addPasswordToggle(document.getElementById('password'));
  document.getElementById('register-form').onsubmit = async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true; btn.textContent = 'Creating account...';
    try {
      await fetchJSON(B + '/api/auth/register', { method: 'POST', body: JSON.stringify({ name: document.getElementById('name').value, email: document.getElementById('email').value, password: document.getElementById('password').value, consent: document.getElementById('consent').checked }) });
      toast('Account created!', 'success');
      setTimeout(() => window.location.href = B + '/', 800);
    } catch (err) { document.getElementById('msg').innerHTML = '<p class="error-msg">' + escapeHtml(err.message) + '</p>'; btn.disabled = false; btn.textContent = 'Register'; }
  };
})();
</script>
