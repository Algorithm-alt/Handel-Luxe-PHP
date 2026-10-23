<?php $pageTitle = 'My Profile';
$metaDescription = 'Update your Handel Luxe GH name, email address and password.';
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'My Profile']]; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container">
  <div class="section">
    <h2 class="section-title">My Profile</h2>
    <div id="profile-content"><div class="loading-placeholder"><div class="spinner"></div><p>Loading your profile...</p></div></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  const content = document.getElementById('profile-content');

  function renderForm(u) {
    content.innerHTML =
      '<div class="form-card wide">' +
        '<h2>Account Details</h2>' +
        '<form id="profile-form" novalidate>' +
          '<div class="form-group"><label for="pf-name">Full Name</label>' +
            '<input type="text" id="pf-name" maxlength="100" required autocomplete="name" value="' + escapeHtml(u.name || '') + '"></div>' +
          '<div class="form-group"><label for="pf-email">Email Address</label>' +
            '<input type="email" id="pf-email" maxlength="100" required autocomplete="email" value="' + escapeHtml(u.email || '') + '"></div>' +
          '<h3 class="form-section-title">Change Password</h3>' +
          '<p class="text-muted">Leave these blank to keep your current password. Your current password is also required if you change your email.</p>' +
          '<div class="form-group"><label for="pf-current">Current Password</label>' +
            '<input type="password" id="pf-current" autocomplete="current-password" placeholder="Enter current password"></div>' +
          '<div class="form-group"><label for="pf-new">New Password</label>' +
            '<input type="password" id="pf-new" autocomplete="new-password" placeholder="Min 8 characters"></div>' +
          '<div id="pf-msg"></div>' +
          '<button type="submit" class="btn btn-primary btn-block">Save Changes</button>' +
        '</form>' +
        '<p class="profile-note">Account deletion is not available here. If you need your account removed, please <a href="' + B + '/contact" class="text-link">contact us</a>.</p>' +
        '<p class="form-footer-links"><a href="' + B + '/orders">View my orders</a></p>' +
      '</div>';

    addPasswordToggle(document.getElementById('pf-current'));
    addPasswordToggle(document.getElementById('pf-new'));

    document.getElementById('profile-form').onsubmit = async function(e) {
      e.preventDefault();
      const msg = document.getElementById('pf-msg');
      msg.innerHTML = '';
      const name = document.getElementById('pf-name').value.trim();
      const email = document.getElementById('pf-email').value.trim();
      const currentPw = document.getElementById('pf-current').value;
      const newPw = document.getElementById('pf-new').value;
      const btn = e.target.querySelector('button[type="submit"]');

      if (!name || !email) {
        msg.innerHTML = '<p class="error-msg">Name and email are required.</p>';
        return;
      }
      if (newPw && !currentPw) {
        msg.innerHTML = '<p class="error-msg">Enter your current password to set a new password.</p>';
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Saving...';
      try {
        const data = await fetchJSON(B + '/api/profile', {
          method: 'PUT',
          body: JSON.stringify({ name: name, email: email, current_password: currentPw, new_password: newPw })
        });
        setAuthLinks(data.user);
        document.getElementById('pf-current').value = '';
        document.getElementById('pf-new').value = '';
        toast('Profile updated', 'success');
      } catch (err) {
        msg.innerHTML = '<p class="error-msg">' + escapeHtml(err.message) + '</p>';
      }
      btn.disabled = false;
      btn.textContent = 'Save Changes';
    };
  }

  (async function() {
    try {
      const u = await fetchJSON(B + '/api/profile');
      setAuthLinks(u);
      renderForm(u);
    } catch (err) {
      content.innerHTML = '<div class="empty-state"><h3>Please login to view your profile</h3><p>Sign in to update your account details.</p><a href="' + B + '/login" class="btn btn-primary">Login</a></div>';
      setAuthLinks(null);
    }
  })();
})();
</script>
