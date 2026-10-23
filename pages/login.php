<?php $pageTitle = 'Login';
$metaDescription = 'Log in to your Handel Luxe GH account to place orders and track your deliveries.'; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container">
  <div class="form-card">
    <h2>Login</h2>
    <form id="login-form">
      <div class="form-group"><label for="email">Email</label><input type="email" id="email" required placeholder="your@email.com"></div>
      <div class="form-group"><label for="password">Password</label><input type="password" id="password" required placeholder="Enter password"></div>
      <div id="msg"></div>
      <button type="submit" class="btn btn-primary btn-block">Login</button>
    </form>
    <p class="form-footer-links"><a href="<?= base_url('/forgot-password') ?>">Forgot password?</a></p>
    <p class="form-footer-links">Don't have an account? <a href="<?= base_url('/register') ?>" class="text-link">Register</a></p>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  addPasswordToggle(document.getElementById('password'));
  document.getElementById('login-form').onsubmit = async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true; btn.textContent = 'Logging in...';
    try {
      const data = await fetchJSON(B + '/api/auth/login', { method: 'POST', body: JSON.stringify({ email: document.getElementById('email').value, password: document.getElementById('password').value }) });
      toast('Login successful!', 'success');
      setTimeout(() => window.location.href = data.user.role === 'admin' ? B + '/admin' : B + '/', 800);
    } catch (err) { document.getElementById('msg').innerHTML = '<p class="error-msg">' + escapeHtml(err.message) + '</p>'; btn.disabled = false; btn.textContent = 'Login'; }
  };
})();
</script>
