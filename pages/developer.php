<?php
$pageTitle = 'Developer Dashboard';
$metaDescription = 'Private maintenance dashboard.';
$noIndex = true;
header('Cache-Control: no-store, private');
require __DIR__ . '/../includes/header.php';
$developerAuthenticated = !empty($_SESSION['developer_dashboard']);
$developerName = htmlspecialchars((string)($_SESSION['developer_dashboard'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<main class="developer-page">
  <div class="container developer-container">
  <?php if ($developerAuthenticated): ?>
    <div class="developer-dashboard">
      <header class="developer-header">
        <div class="developer-header-copy">
          <span class="developer-kicker"><span class="developer-kicker-dot" aria-hidden="true"></span> Private maintenance area</span>
          <h1>Developer dashboard</h1>
          <p class="developer-intro">Backups, activity, and access for <strong><?= $developerName ?></strong>.</p>
        </div>
        <div class="developer-header-actions">
          <span class="developer-session-pill"><span class="developer-session-dot" aria-hidden="true"></span> Secure session</span>
          <button type="button" id="developer-logout" class="btn btn-outline developer-logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
            Log out
          </button>
        </div>
      </header>

      <aside class="developer-sidebar">
        <nav class="developer-tabs" role="tablist" aria-label="Developer dashboard sections">
          <button type="button" class="developer-tab active" id="developer-tab-dashboard" role="tab" aria-selected="true" aria-controls="developer-panel-dashboard" data-tab="dashboard" tabindex="0">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
            <span>Overview</span>
          </button>
          <button type="button" class="developer-tab" id="developer-tab-audit" role="tab" aria-selected="false" aria-controls="developer-panel-audit" data-tab="audit" tabindex="-1">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg>
            <span>Audit log</span>
          </button>
          <button type="button" class="developer-tab" id="developer-tab-profile" role="tab" aria-selected="false" aria-controls="developer-panel-profile" data-tab="profile" tabindex="-1">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/></svg>
            <span>Profile</span>
          </button>
        </nav>
        <p class="developer-sidebar-note">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
          Only server-configured credentials can open this area.
        </p>
      </aside>

      <div id="developer-panel-dashboard" class="developer-panel" role="tabpanel" aria-labelledby="developer-tab-dashboard" tabindex="0">
        <div class="developer-stats" aria-label="Backup summary">
          <article class="developer-stat">
            <span class="developer-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg></span>
            <div>
              <p class="developer-stat-label">Backup folder</p>
              <strong class="developer-stat-value" id="stat-backup-state">Checking…</strong>
              <span class="developer-stat-hint" id="stat-backup-hint">Reading server settings…</span>
            </div>
          </article>
          <article class="developer-stat">
            <span class="developer-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8"/><path d="M3 4h18v4H3z"/><path d="M10 12h4"/></svg></span>
            <div>
              <p class="developer-stat-label">Automatic backups</p>
              <strong class="developer-stat-value" id="stat-backup-count">—</strong>
              <span class="developer-stat-hint">Scheduled archives found</span>
            </div>
          </article>
          <article class="developer-stat">
            <span class="developer-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></svg></span>
            <div>
              <p class="developer-stat-label">Latest backup</p>
              <strong class="developer-stat-value" id="stat-latest-backup">—</strong>
              <span class="developer-stat-hint">UTC timestamp</span>
            </div>
          </article>
          <article class="developer-stat">
            <span class="developer-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg></span>
            <div>
              <p class="developer-stat-label">Archive size</p>
              <strong class="developer-stat-value" id="stat-latest-size">—</strong>
              <span class="developer-stat-hint">Most recent file</span>
            </div>
          </article>
        </div>

        <section class="developer-section developer-backup-section" aria-labelledby="backup-heading">
          <div class="developer-section-heading">
            <span class="developer-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg></span>
            <div class="developer-section-copy">
              <h2 id="backup-heading">On-demand backup</h2>
              <p>Create a complete archive of the store database and uploaded product images, then download it to this device.</p>
            </div>
          </div>
          <div class="developer-callout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 2.4 18a2 2 0 0 0 1.8 3h15.6a2 2 0 0 0 1.8-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            <p>The archive may contain private customer and order information. Store it somewhere secure and delete local copies when you are done.</p>
          </div>
          <div id="backup-status" class="alert alert-info" role="status">Checking scheduled backup status…</div>
          <div class="developer-button-row">
            <a href="<?= base_url('/api/developer/backups/download') ?>" class="btn btn-primary developer-download-button">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
              Create &amp; download backup
            </a>
            <button type="button" id="developer-refresh-backup" class="btn btn-outline">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
              Refresh status
            </button>
          </div>
        </section>

        <section class="developer-section" aria-labelledby="schedule-heading">
          <div class="developer-section-heading">
            <span class="developer-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
            <div class="developer-section-copy">
              <h2 id="schedule-heading">Automatic daily backups</h2>
              <p>Optional. This needs access to your hosting settings, or help from your hosting provider.</p>
            </div>
          </div>
          <ol class="developer-steps">
            <li><strong>Choose a private folder.</strong> Create a writable folder for backups that is <strong>outside your website's public folder</strong>, so visitors cannot download the files. Set <code>BACKUP_DIR</code> in <code>config/.env</code> to that folder's full server path.</li>
            <li><strong>Schedule the daily task.</strong> In your hosting control panel, add a cron job that runs once a day. Ask your hosting provider for the exact PHP and website paths, then use them in this command:</li>
          </ol>
          <div class="developer-command-box">
            <div class="developer-command-bar">
              <span>Cron command example</span>
              <button type="button" class="developer-copy" data-copy-target="developer-cron-command">Copy</button>
            </div>
            <pre class="developer-command" id="developer-cron-command">/path/to/php /absolute/path/to/site/scripts/backup.php</pre>
          </div>
          <p class="developer-command-note">The paths above are examples, not a ready-to-run command. Replace both with the real paths for your hosting account.</p>
          <ol class="developer-steps" start="3">
            <li><strong>Check that it worked.</strong> After the first scheduled run, return here and check the status above. It reports backup files found; your hosting panel is where you confirm the cron job ran successfully. The system keeps the latest 14 scheduled backups.</li>
          </ol>
          <p class="developer-footnote">Not sure how to set this up? Send these steps to your hosting provider and ask them to configure a daily backup job. For extra safety, keep a copy outside your hosting account too.</p>
        </section>
      </div>

      <div id="developer-panel-audit" class="developer-panel" role="tabpanel" aria-labelledby="developer-tab-audit" tabindex="0" hidden>
        <section class="developer-section developer-audit-section" aria-labelledby="audit-heading">
          <div class="developer-section-heading">
            <span class="developer-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg></span>
            <div class="developer-section-copy">
              <h2 id="audit-heading">Audit log</h2>
              <p>Recent developer sign-ins, profile changes, and administrator mutations.</p>
            </div>
            <button type="button" id="developer-refresh-audit" class="btn btn-outline btn-sm developer-refresh-audit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
              Refresh
            </button>
          </div>
          <div id="developer-audit-status" class="alert alert-info" role="status">Loading audit logs…</div>
          <div id="developer-audit-table"></div>
        </section>
      </div>

      <div id="developer-panel-profile" class="developer-panel" role="tabpanel" aria-labelledby="developer-tab-profile" tabindex="0" hidden>
        <section class="developer-section" aria-labelledby="developer-profile-heading">
          <div class="developer-section-heading">
            <span class="developer-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v5c0 4.5 3 8 7 10 4-2 7-5.5 7-10V6l-7-3Z"/><path d="m9.5 12 1.8 1.8 3.4-3.6"/></svg></span>
            <div class="developer-section-copy">
              <h2 id="developer-profile-heading">Developer profile</h2>
              <p>Update the username or password used to open this private dashboard.</p>
            </div>
          </div>
          <form id="developer-profile-form" class="developer-profile-form">
            <div class="developer-form-grid">
              <div class="form-group">
                <label for="developer-profile-username">Username</label>
                <input type="text" id="developer-profile-username" autocomplete="username" minlength="3" maxlength="100" required>
                <small class="developer-field-hint">3 to 100 characters. Used only for this dashboard.</small>
              </div>
              <div class="form-group">
                <label for="developer-current-password">Current password</label>
                <input type="password" id="developer-current-password" autocomplete="current-password" required>
                <small class="developer-field-hint">Required to save any change.</small>
              </div>
              <div class="form-group">
                <label for="developer-new-password">New password</label>
                <input type="password" id="developer-new-password" autocomplete="new-password" minlength="12" maxlength="72">
                <small class="developer-field-hint">Leave blank to keep the current password. Minimum 12 characters.</small>
              </div>
              <div class="form-group">
                <label for="developer-confirm-password">Confirm new password</label>
                <input type="password" id="developer-confirm-password" autocomplete="new-password">
                <small class="developer-field-hint">Must match the new password above.</small>
              </div>
            </div>
            <div id="developer-profile-message" aria-live="polite"></div>
            <div class="developer-form-actions">
              <button type="submit" class="btn btn-primary">Save profile</button>
            </div>
          </form>
        </section>
      </div>

      <div id="developer-message" aria-live="polite"></div>
    </div>
  <?php else: ?>
    <div class="developer-login">
      <div class="developer-login-card">
        <span class="developer-login-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v5c0 4.5 3 8 7 10 4-2 7-5.5 7-10V6l-7-3Z"/><rect x="9.5" y="11" width="5" height="4" rx="1"/><path d="M10.5 11V9.8a1.5 1.5 0 0 1 3 0V11"/></svg></span>
        <span class="developer-kicker">Private maintenance area</span>
        <h1>Developer sign in</h1>
        <p class="developer-login-subtitle">Use the separate credentials configured on the server. This area is not linked from the storefront.</p>
        <form id="developer-login-form" class="developer-login-form">
          <div class="form-group">
            <label for="developer-username">Username</label>
            <input type="text" id="developer-username" autocomplete="username" required>
          </div>
          <div class="form-group">
            <label for="developer-password">Password</label>
            <input type="password" id="developer-password" autocomplete="current-password" required>
          </div>
          <div id="developer-login-message" aria-live="polite"></div>
          <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        </form>
        <p class="developer-login-note">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
          Sessions are private and end when you sign out.
        </p>
      </div>
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
  const formatUtc = (value) => {
    if (!value) return '—';
    const raw = String(value);
    const date = new Date(raw.replace(' ', 'T') + (/[zZ]$|[+-]\d\d:?\d\d$/.test(raw) ? '' : 'Z'));
    if (isNaN(date.getTime())) return raw;
    return date.toLocaleString('en-GB', {
      day: '2-digit', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit', timeZone: 'UTC'
    }) + ' UTC';
  };
  const formatUtcParts = (value) => {
    if (!value) return { date: '—', time: '' };
    const raw = String(value);
    const date = new Date(raw.replace(' ', 'T') + (/[zZ]$|[+-]\d\d:?\d\d$/.test(raw) ? '' : 'Z'));
    if (isNaN(date.getTime())) return { date: raw, time: '' };
    return {
      date: date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' }),
      time: date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: 'UTC' }) + ' UTC'
    };
  };
  <?php if ($developerAuthenticated): ?>
  fetchJSON(B + '/api/developer/me').then(() => {
    loadBackupStatus();
    loadDeveloperProfile();
    loadDeveloperAudit();
  }).catch(() => {
    window.location.href = B + '/developer';
  });

  const tabs = Array.from(document.querySelectorAll('.developer-tab[data-tab]'));
  const panels = Array.from(document.querySelectorAll('.developer-panel[role="tabpanel"]'));
  function showDeveloperTab(name, focus) {
    tabs.forEach(function(tab) {
      const active = tab.getAttribute('data-tab') === name;
      tab.classList.toggle('active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
      if (active && focus) tab.focus();
    });
    panels.forEach(function(panel) {
      panel.hidden = panel.id !== 'developer-panel-' + name;
    });
    if (name === 'audit') loadDeveloperAudit();
  }
  tabs.forEach(function(tab) {
    tab.addEventListener('click', function() {
      showDeveloperTab(this.getAttribute('data-tab'));
    });
    tab.addEventListener('keydown', function(e) {
      const keys = ['ArrowDown', 'ArrowRight', 'ArrowUp', 'ArrowLeft', 'Home', 'End'];
      if (keys.indexOf(e.key) === -1) return;
      e.preventDefault();
      const current = tabs.indexOf(this);
      let next = current;
      if (e.key === 'ArrowDown' || e.key === 'ArrowRight') next = (current + 1) % tabs.length;
      if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') next = (current - 1 + tabs.length) % tabs.length;
      if (e.key === 'Home') next = 0;
      if (e.key === 'End') next = tabs.length - 1;
      showDeveloperTab(tabs[next].getAttribute('data-tab'), true);
    });
  });

  let auditCache = null;
  async function fetchAudit(force) {
    if (auditCache && !force) return auditCache;
    auditCache = await fetchJSON(B + '/api/developer/audit');
    return auditCache;
  }
  const auditActionLabels = {
    'developer.login': 'Sign-in',
    'developer.logout': 'Sign-out',
    'developer.login_failed': 'Failed sign-in',
    'developer.profile_updated': 'Profile updated',
    'developer.backup_downloaded': 'Backup saved',
    'profile.updated': 'Profile updated',
    'admin.mutation_requested': 'Admin change',
    'payment.amount_mismatch': 'Payment mismatch',
    'payment.restock_failed': 'Restock failed'
  };
  function auditActionLabel(action) {
    return auditActionLabels[action] || String(action || '—');
  }
  function humanizeAdminRoute(method, route) {
    const path = String(route || '').replace(/^admin\//, '').replace(/\/+$/, '');
    if (!path) return 'Admin change';
    if (path === 'profile') return 'Updated the admin profile';
    if (path === 'newsletter') return 'Sent a newsletter';
    if (path === 'products') return 'Created a product';
    if (path === 'categories') return 'Created a category';
    if (path === 'delivery-zones') return 'Created a delivery zone';
    const parts = path.split('/');
    const resource = parts[0];
    const id = parts[1] || '';
    if (resource === 'products' && id) {
      if (parts[2] === 'stock') return 'Updated stock for product #' + id;
      if (parts[2] === 'images') {
        if (parts[4] === 'cover') return 'Changed the cover image for product #' + id;
        return (method === 'DELETE' ? 'Removed' : 'Updated') + ' an image for product #' + id;
      }
      return (method === 'DELETE' ? 'Deleted' : 'Updated') + ' product #' + id;
    }
    if (resource === 'categories' && id) {
      return (method === 'DELETE' ? 'Deleted' : 'Updated') + ' category #' + id;
    }
    if (resource === 'orders' && id) {
      if (parts[2] === 'status') return 'Updated status for order #' + id;
      if (parts[2] === 'payment') return 'Updated payment for order #' + id;
      return (method === 'DELETE' ? 'Deleted' : 'Updated') + ' order #' + id;
    }
    if (resource === 'users' && id) {
      if (parts[2] === 'role') return 'Changed the role for user #' + id;
      return (method === 'DELETE' ? 'Deleted' : 'Updated') + ' user #' + id;
    }
    if (resource === 'messages' && id) {
      if (parts[2] === 'read') return 'Marked message #' + id + ' as read';
      if (parts[2] === 'unread') return 'Marked message #' + id + ' as unread';
      return 'Deleted message #' + id;
    }
    if (resource === 'delivery-zones' && id) {
      return (method === 'DELETE' ? 'Deleted' : 'Updated') + ' delivery zone “' + id + '”';
    }
    const verb = method === 'DELETE' ? 'Deleted' : 'Updated';
    return verb + ' ' + path.replace(/[-/]/g, ' ');
  }
  function humanizeSize(bytes) {
    const size = Number(bytes);
    if (!isFinite(size) || size < 0) return '';
    if (size >= 1048576) return (size / 1048576).toFixed(1).replace(/\.0$/, '') + ' MB';
    if (size >= 1024) return Math.round(size / 1024) + ' KB';
    return size + ' B';
  }
  function humanizeDetails(details) {
    return Object.keys(details).map(function(key) {
      let value = details[key];
      if (value === true) value = 'Yes';
      else if (value === false) value = 'No';
      else if (value === null || value === '') value = '—';
      else if (typeof value === 'object') value = JSON.stringify(value);
      const label = key.replace(/_/g, ' ').replace(/\b\w/g, function(letter) { return letter.toUpperCase(); });
      return label + ': ' + value;
    }).join(' · ');
  }
  function auditDetailsHtml(details) {
    if (!details || typeof details !== 'object' || !Object.keys(details).length) {
      return '<span class="text-muted">—</span>';
    }
    const full = JSON.stringify(details);
    if (details.method && details.route) {
      const summary = humanizeAdminRoute(String(details.method).toUpperCase(), details.route);
      return '<span class="dev-detail-text" title="' + escapeHtml(full) + '">' + escapeHtml(summary) + '</span>';
    }
    const hasCredentialFlags = Object.prototype.hasOwnProperty.call(details, 'username_changed')
      || Object.prototype.hasOwnProperty.call(details, 'email_changed')
      || Object.prototype.hasOwnProperty.call(details, 'password_changed');
    if (hasCredentialFlags) {
      const changes = [];
      if (details.username_changed) changes.push('Username');
      if (details.email_changed) changes.push('Email');
      if (details.password_changed) changes.push('Password');
      if (!changes.length) {
        return '<span class="dev-detail-text text-muted" title="' + escapeHtml(full) + '">No credential changes</span>';
      }
      return '<span class="dev-detail" title="' + escapeHtml(full) + '" aria-label="' + escapeHtml(changes.join(' and ') + ' changed') + '">' +
        changes.map(function(label) { return '<span class="dev-change">' + label + '</span>'; }).join('') +
        '<span>changed</span></span>';
    }
    if (Object.prototype.hasOwnProperty.call(details, 'size_bytes')) {
      return '<span class="dev-detail-text" title="' + escapeHtml(full) + '">Downloaded a backup (' + escapeHtml(humanizeSize(details.size_bytes)) + ')</span>';
    }
    if (Object.prototype.hasOwnProperty.call(details, 'expected_minor') && Object.prototype.hasOwnProperty.call(details, 'paid_minor')) {
      const currency = details.currency ? String(details.currency) : 'GHS';
      const prefix = details.order_id ? 'Order #' + details.order_id + ': ' : '';
      const paid = (Number(details.paid_minor) / 100).toFixed(2);
      const expected = (Number(details.expected_minor) / 100).toFixed(2);
      return '<span class="dev-detail-text" title="' + escapeHtml(full) + '">' +
        escapeHtml(prefix + 'paid ' + currency + ' ' + paid + ', expected ' + currency + ' ' + expected) + '</span>';
    }
    if (details.order_id && details.reference) {
      return '<span class="dev-detail-text" title="' + escapeHtml(full) + '">' +
        escapeHtml('Order #' + details.order_id + ': restock failed after payment') + '</span>';
    }
    return '<span class="dev-detail-text" title="' + escapeHtml(full) + '">' + escapeHtml(humanizeDetails(details)) + '</span>';
  }
  async function loadDeveloperAudit(force) {
    const status = document.getElementById('developer-audit-status');
    const table = document.getElementById('developer-audit-table');
    const button = document.getElementById('developer-refresh-audit');
    if (!status || !table) return;
    if (button && force) button.disabled = true;
    status.hidden = false;
    status.className = 'alert alert-info';
    status.textContent = 'Loading audit logs…';
    table.innerHTML = '';
    try {
      const logs = await fetchAudit(force);
      if (!logs.length) {
        status.hidden = true;
        table.innerHTML = '<div class="developer-empty">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/></svg>' +
          '<strong>No activity yet</strong><span>Developer sign-ins and changes will appear here.</span></div>';
        return;
      }
      status.hidden = false;
      status.className = 'developer-table-note';
      status.textContent = 'Showing the latest ' + logs.length + ' event' + (logs.length === 1 ? '' : 's') + '.';
      table.innerHTML = '<div class="admin-table-wrap"><table class="admin-table developer-audit-table"><thead><tr><th>Date</th><th>Actor</th><th>Action</th><th>Details</th><th>IP address</th></tr></thead><tbody>' +
        logs.map(function(log) {
          const actorType = String(log.actor_type || '').toLowerCase();
          const badge = ['developer', 'admin', 'anonymous', 'user', 'system'].indexOf(actorType) !== -1 ? actorType : 'developer';
          const detailsCell = auditDetailsHtml(log.details);
          const actionHtml = escapeHtml(auditActionLabel(log.action));
          const when = formatUtcParts(log.created_at);
          return '<tr>' +
            '<td class="dev-date"><span class="dev-date-day">' + escapeHtml(when.date) + '</span>' + (when.time ? '<span class="dev-date-time">' + escapeHtml(when.time) + '</span>' : '') + '</td>' +
            '<td><span class="dev-actor-name">' + escapeHtml(log.actor_name || 'Unknown') + '</span><span class="dev-actor-type"><span class="dev-badge dev-badge-' + badge + '">' + escapeHtml(log.actor_type || 'unknown') + '</span></span></td>' +
            '<td><code class="dev-action" title="' + escapeHtml(log.action || '') + '">' + actionHtml + '</code></td>' +
            '<td class="developer-audit-details">' + detailsCell + '</td>' +
            '<td class="dev-ip">' + escapeHtml(log.ip_address || '—') + '</td>' +
            '</tr>';
        }).join('') + '</tbody></table></div>';
    } catch (e) {
      status.hidden = false;
      status.className = 'alert alert-warning';
      status.textContent = 'Could not load audit logs: ' + e.message;
    } finally {
      if (button) button.disabled = false;
    }
  }

  async function loadBackupStatus() {
    const el = document.getElementById('backup-status');
    if (!el) return;
    const stateEl = document.getElementById('stat-backup-state');
    const hintEl = document.getElementById('stat-backup-hint');
    const countEl = document.getElementById('stat-backup-count');
    const latestEl = document.getElementById('stat-latest-backup');
    const sizeEl = document.getElementById('stat-latest-size');
    try {
      const status = await fetchJSON(B + '/api/developer/backups/status');
      const count = Number(status.backup_count) || 0;
      if (countEl) countEl.textContent = String(count);
      if (latestEl) latestEl.textContent = status.latest_created_at ? formatUtc(status.latest_created_at) : 'None yet';
      if (sizeEl) sizeEl.textContent = status.latest_size_bytes ? (Number(status.latest_size_bytes) / 1048576).toFixed(2) + ' MB' : '—';
      if (status.directory_state === 'not_configured') {
        el.className = 'alert alert-warning';
        el.textContent = 'Automatic backups are not set up yet. The download button above still works for a one-time backup.';
        if (stateEl) stateEl.textContent = 'Not set up';
        if (hintEl) hintEl.textContent = 'Set BACKUP_DIR to enable';
      } else if (status.directory_state !== 'ready') {
        el.className = 'alert alert-warning';
        el.textContent = 'The backup folder is missing, not writable, or inside the public website folder. Ask your hosting provider to check the BACKUP_DIR setting.';
        if (stateEl) stateEl.textContent = 'Needs attention';
        if (hintEl) hintEl.textContent = 'Check BACKUP_DIR permissions';
      } else {
        el.className = count ? 'alert alert-info' : 'alert alert-warning';
        el.textContent = count
          ? count + ' automatic backup file(s) found. Latest: ' + formatUtc(status.latest_created_at) + ' (' + (Number(status.latest_size_bytes) / 1048576).toFixed(2) + ' MB). Check your hosting panel to confirm the daily task is enabled.'
          : 'The private backup folder is ready, but no automatic backups have been found yet. Ask your hosting provider to run the scheduled task once, then check here again.';
        if (stateEl) stateEl.textContent = 'Ready';
        if (hintEl) hintEl.textContent = 'Private and writable';
      }
    } catch (e) {
      el.className = 'alert alert-warning';
      el.textContent = 'Could not check backup files: ' + e.message;
      if (stateEl) stateEl.textContent = 'Unavailable';
      if (hintEl) hintEl.textContent = 'Status check failed';
    }
  }

  const refreshBackup = document.getElementById('developer-refresh-backup');
  if (refreshBackup) {
    refreshBackup.addEventListener('click', async function() {
      const original = this.innerHTML;
      this.disabled = true;
      this.textContent = 'Refreshing…';
      await loadBackupStatus();
      this.disabled = false;
      this.innerHTML = original;
    });
  }
  const refreshAudit = document.getElementById('developer-refresh-audit');
  if (refreshAudit) {
    refreshAudit.addEventListener('click', function() {
      loadDeveloperAudit(true);
    });
  }

  document.querySelectorAll('[data-copy-target]').forEach(function(button) {
    button.addEventListener('click', async function() {
      const target = document.getElementById(this.getAttribute('data-copy-target'));
      if (!target) return;
      try {
        if (!navigator.clipboard || !navigator.clipboard.writeText) throw new Error('Clipboard unavailable');
        await navigator.clipboard.writeText(target.textContent);
        const original = this.textContent;
        this.textContent = 'Copied';
        const self = this;
        setTimeout(function() { self.textContent = original; }, 1600);
      } catch (e) {
        message('developer-message', 'Could not copy automatically. Select the command and copy it manually.', true);
      }
    });
  });

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
    btn.textContent = 'Signing in…';
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
      btn.textContent = 'Sign in';
    }
  });
  <?php endif; ?>
})();
</script>
