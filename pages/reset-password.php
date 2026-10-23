<?php $pageTitle = 'Reset Password';
$metaDescription = 'Choose a new password for your Handel Luxe GH account.'; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container">
  <div class="form-card">
    <h2>Reset Password</h2>
    <form id="reset-form">
      <input type="hidden" id="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">
      <div class="form-group"><label for="password">New Password</label><input type="password" id="password" required placeholder="Min 8 characters"></div>
      <div id="msg"></div>
      <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  addPasswordToggle(document.getElementById('password'));
  document.getElementById('reset-form').onsubmit = async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true; btn.textContent = 'Resetting...';
    try {
      await fetchJSON(B + '/api/auth/reset-password', { method: 'POST', body: JSON.stringify({ token: document.getElementById('token').value, password: document.getElementById('password').value }) });
      toast('Password reset successful!', 'success');
      setTimeout(() => window.location.href = B + '/login', 1500);
    } catch (err) { document.getElementById('msg').innerHTML = '<p class="error-msg">' + escapeHtml(err.message) + '</p>'; btn.disabled = false; btn.textContent = 'Reset Password'; }
  };
})();
</script>
