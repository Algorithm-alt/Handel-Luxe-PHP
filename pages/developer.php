<?php
$pageTitle = 'Developer Dashboard';
$metaDescription = 'Private maintenance dashboard.';
$noIndex = true;
header('Cache-Control: no-store, private');
require __DIR__ . '/../includes/header.php';
$developerAuthenticated = !empty($_SESSION['developer_dashboard']);
?>
<main class="developer-page">
  <div class="container developer-container">
  <?php if ($developerAuthenticated): ?>
    <div class="developer-dashboard">
      <header class="developer-header">
        <div>
          <p class="developer-eyebrow">Private maintenance area</p>
          <h1>Developer Dashboard</h1>
          <p class="developer-intro">Manage backups, review activity, and update your developer profile.</p>
        </div>
        <button type="button" id="developer-logout" class="btn btn-outline">Log Out</button>
      </header>
      <nav class="developer-tabs" role="tablist" aria-label="Developer dashboard sections">
        <button type="button" class="developer-tab active" id="developer-tab-dashboard" role="tab" aria-selected="true" aria-controls="developer-panel-dashboard" data-tab="dashboard">Dashboard</button>
        <button type="button" class="developer-tab" id="developer-tab-audit" role="tab" aria-selected="false" aria-controls="developer-panel-audit" data-tab="audit">Audit Logs</button>
      </nav>
      <div id="developer-panel-dashboard" class="developer-panel" role="tabpanel" aria-labelledby="developer-tab-dashboard">
      <section class="developer-section developer-backup-section" aria-labelledby="backup-heading">
        <div class="developer-section-heading">
          <span class="developer-section-icon" aria-hidden="true">&#8595;</span>
          <div>
            <h2 id="backup-heading">Download a Backup Now</h2>
            <p class="text-muted">This makes and downloads one backup to your device.</p>
          </div>
        </div>
        <p class="developer-notice">The file contains your store database and uploaded product images. It may include private customer and order information, so keep it somewhere secure.</p>
        <div id="backup-status" class="alert alert-info" role="status">Checking scheduled backup status...</div>
        <a href="<?= base_url('/api/developer/backups/download') ?>" class="btn btn-primary developer-download-button">Create &amp; Download Backup</a>
      </section>
      <section class="developer-section" aria-labelledby="schedule-heading">
        <div class="developer-section-heading">
          <span class="developer-section-icon" aria-hidden="true">&#8635;</span>
          <div>
            <h2 id="schedule-heading">Set Up Automatic Daily Backups</h2>
            <p class="text-muted">This is optional. It requires access to your hosting settings, or help from your hosting provider.</p>
          </div>
        </div>
        <ol class="developer-steps">
          <li><strong>Choose a private folder.</strong> Create a writable folder for backups that is <strong>outside your website's public folder</strong>, so visitors cannot download the files. Set <code>BACKUP_DIR</code> in <code>config/.env</code> to that folder's full server path.</li>
          <li><strong>Schedule the daily task.</strong> In your hosting control panel, add a cron job that runs once a day. Ask your hosting provider for the exact PHP and website paths, then use them in this command:</li>
        </ol>
        <pre class="developer-command">/path/to/php /absolute/path/to/site/scripts/backup.php</pre>
        <p class="developer-command-note">The paths above are examples, not a ready-to-run command. Replace both with the real paths for your hosting account.</p>
        <ol class="developer-steps" start="3">
          <li><strong>Check that it worked.</strong> After the first scheduled run, return here and check the status above. It reports backup files found; your hosting panel is where you confirm the cron job ran successfully. The system keeps the latest 14 scheduled backups.</li>
        </ol>
        <p class="developer-footnote">Not sure how to set this up? Send these steps to your hosting provider and ask them to configure a daily backup job. For extra safety, keep a copy outside your hosting account too.</p>
      </section>
      <section class="developer-section" aria-labelledby="developer-profile-heading">
        <div class="developer-section-heading">
          <span class="developer-section-icon" aria-hidden="true">&#9881;</span>
          <div>
            <h2 id="developer-profile-heading">Developer Profile</h2>
            <p class="text-muted">Update your private dashboard username or password.</p>
          </div>
        </div>
        <form id="developer-profile-form" class="developer-profile-form">
          <div class="form-group">
            <label for="developer-profile-username">Username</label>
            <input type="text" id="developer-profile-username" autocomplete="username" minlength="3" maxlength="100" required>
          </div>
          <div class="form-group">
            <label for="developer-current-password">Current password</label>
            <input type="password" id="developer-current-password" autocomplete="current-password" required>
          </div>
          <div class="form-group">
            <label for="developer-new-password">New password</label>
            <input type="password" id="developer-new-password" autocomplete="new-password" minlength="12" maxlength="72">
            <small class="text-muted">Leave blank to keep your current password. New passwords must be 12 to 72 characters.</small>
          </div>
          <div class="form-group">
            <label for="developer-confirm-password">Confirm new password</label>
            <input type="password" id="developer-confirm-password" autocomplete="new-password">
          </div>
          <div id="developer-profile-message" aria-live="polite"></div>
          <button type="submit" class="btn btn-primary">Save Profile</button>
        </form>
      </section>
      </div>
      <section id="developer-panel-audit" class="developer-panel developer-section" role="tabpanel" aria-labelledby="developer-tab-audit" hidden>
        <div class="developer-section-heading">
          <span class="developer-section-icon" aria-hidden="true">&#128339;</span>
          <div>
            <h2 id="audit-heading">Recent Activity</h2>
            <p class="text-muted">Latest developer events and administrator mutation requests.</p>
          </div>
        </div>
        <div id="developer-audit-status" class="alert alert-info" role="status">Loading audit logs...</div>
        <div id="developer-audit-table"></div>
      </section>
      <div id="developer-message" aria-live="polite"></div>
    </div>
  <?php else: ?>
    <div class="form-card developer-login-card">
      <p class="developer-eyebrow">Private maintenance area</p>
      <h1>Developer Sign In</h1>
      <p class="form-subtitle">Use the separate developer credentials configured on the server.</p>
      <form id="developer-login-form">
        <div class="form-group"><label for="developer-username">Username</label><input type="text" id="developer-username" autocomplete="username" required></div>
        <div class="form-group"><label for="developer-password">Password</label><input type="password" id="developer-password" autocomplete="current-password" required></div>
        <div id="developer-login-message" aria-live="polite"></div>
        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
      </form>
    </div>
  <?php endif; ?>
</div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  const message = (id, text, error) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = text;
    el.className = error ? 'error-msg' : 'text-muted';
  };
  <?php if ($developerAuthenticated): ?>
  fetchJSON(B + '/api/developer/me').then(() => {
    loadBackupStatus();
    loadDeveloperProfile();
  }).catch(() => {
    window.location.href = B + '/developer';
  });
  document.querySelectorAll('.developer-tab[data-tab]').forEach(function(tab) {
    tab.addEventListener('click', function() {
      showDeveloperTab(this.getAttribute('data-tab'));
    });
  });
  function showDeveloperTab(name) {
    document.querySelectorAll('.developer-tab[data-tab]').forEach(function(tab) {
      const active = tab.getAttribute('data-tab') === name;
      tab.classList.toggle('active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('.developer-panel[role="tabpanel"]').forEach(function(panel) {
      panel.hidden = panel.id !== 'developer-panel-' + name;
    });
    if (name === 'audit') loadDeveloperAudit();
  }
  async function loadDeveloperAudit() {
    const status = document.getElementById('developer-audit-status');
    const table = document.getElementById('developer-audit-table');
    status.className = 'alert alert-info';
    status.textContent = 'Loading audit logs...';
    table.innerHTML = '';
    try {
      const logs = await fetchJSON(B + '/api/developer/audit');
      if (!logs.length) {
        status.className = 'alert alert-info';
        status.textContent = 'No audit activity has been recorded yet.';
        return;
      }
      status.className = 'alert alert-info';
      status.textContent = 'Showing the latest ' + logs.length + ' audit event(s).';
      table.innerHTML = '<div class="admin-table-wrap"><table class="admin-table developer-audit-table"><thead><tr><th>Date</th><th>Actor</th><th>Action</th><th>Details</th><th>IP address</th></tr></thead><tbody>' +
        logs.map(function(log) {
          const details = log.details && Object.keys(log.details).length ? JSON.stringify(log.details) : '—';
          return '<tr><td>' + escapeHtml(log.created_at) + '</td><td>' + escapeHtml(log.actor_name) + ' <small>(' + escapeHtml(log.actor_type) + ')</small></td><td><code>' + escapeHtml(log.action) + '</code></td><td class="developer-audit-details">' + escapeHtml(details) + '</td><td>' + escapeHtml(log.ip_address || '—') + '</td></tr>';
        }).join('') + '</tbody></table></div>';
    } catch (e) {
      status.className = 'alert alert-warning';
      status.textContent = 'Could not load audit logs: ' + e.message;
    }
  }
  async function loadBackupStatus() {
    const el = document.getElementById('backup-status');
    if (!el) return;
    try {
      const status = await fetchJSON(B + '/api/developer/backups/status');
      if (status.directory_state === 'not_configured') {
        el.className = 'alert alert-warning';
        el.textContent = 'Automatic backups are not set up yet. The download button above still works for a one-time backup.';
      } else if (status.directory_state !== 'ready') {
        el.className = 'alert alert-warning';
        el.textContent = 'The backup folder is missing, not writable, or inside the public website folder. Ask your hosting provider to check the BACKUP_DIR setting.';
      } else {
        el.className = status.backup_count ? 'alert alert-info' : 'alert alert-warning';
        el.textContent = status.backup_count
          ? status.backup_count + ' automatic backup file(s) found. Latest: ' + (status.latest_created_at || 'unknown time') + ' UTC (' + (Number(status.latest_size_bytes) / 1048576).toFixed(2) + ' MB). Check your hosting panel to confirm the daily task is enabled.'
          : 'The private backup folder is ready, but no automatic backups have been found yet. Ask your hosting provider to run the scheduled task once, then check here again.';
      }
    } catch (e) {
      el.className = 'alert alert-warning';
      el.textContent = 'Could not check backup files: ' + e.message;
    }
  }
  async function loadDeveloperProfile() {
    try {
      const profile = await fetchJSON(B + '/api/developer/profile');
      document.getElementById('developer-profile-username').value = profile.username;
    } catch (e) {
      message('developer-profile-message', e.message, true);
    }
  }
  ['developer-current-password', 'developer-new-password', 'developer-confirm-password'].forEach(function(id) {
    addPasswordToggle(document.getElementById(id));
  });
  document.getElementById('developer-profile-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const button = form.querySelector('button[type="submit"]');
    const newPassword = document.getElementById('developer-new-password').value;
    if (newPassword !== document.getElementById('developer-confirm-password').value) {
      message('developer-profile-message', 'New password and confirmation do not match.', true);
      return;
    }
    button.disabled = true;
    try {
      const profile = await fetchJSON(B + '/api/developer/profile', {
        method: 'PUT',
        body: JSON.stringify({
          username: document.getElementById('developer-profile-username').value,
          current_password: document.getElementById('developer-current-password').value,
          new_password: newPassword
        })
      });
      document.getElementById('developer-profile-username').value = profile.username;
      document.getElementById('developer-current-password').value = '';
      document.getElementById('developer-new-password').value = '';
      document.getElementById('developer-confirm-password').value = '';
      message('developer-profile-message', 'Developer profile updated.', false);
      document.getElementById('developer-profile-message').className = 'success-msg';
    } catch (e) {
      message('developer-profile-message', e.message, true);
    } finally {
      button.disabled = false;
    }
  });
  document.getElementById('developer-logout').addEventListener('click', async function() {
    if (!window.confirm('Are you sure you want to log out of the developer dashboard?')) return;
    this.disabled = true;
    try {
      await fetchJSON(B + '/api/developer/logout', { method: 'POST', body: '{}' });
      window.location.href = B + '/developer';
    } catch (e) {
      message('developer-message', e.message, true);
      this.disabled = false;
    }
  });
  <?php else: ?>
  addPasswordToggle(document.getElementById('developer-password'));
  fetchJSON(B + '/api/developer/me').then(() => {
    window.location.reload();
  }).catch((e) => {
    if (e.message.indexOf('not configured') !== -1) message('developer-login-message', e.message, true);
  });
  document.getElementById('developer-login-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Signing in...';
    try {
      await fetchJSON(B + '/api/developer/login', {
        method: 'POST',
        body: JSON.stringify({
          username: document.getElementById('developer-username').value,
          password: document.getElementById('developer-password').value
        })
      });
      window.location.reload();
    } catch (err) {
      message('developer-login-message', err.message, true);
      btn.disabled = false;
      btn.textContent = 'Sign In';
    }
  });
  <?php endif; ?>
})();
</script>
