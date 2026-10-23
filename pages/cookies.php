<?php $pageTitle = 'Cookie Policy';
$metaDescription = 'What cookies the Handel Luxe GH website uses, what they remember, and how to control them.';
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'Cookie Policy']]; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>

<div class="hero">
  <div class="container">
    <h1>Cookie Policy</h1>
    <p>What cookies this website uses and why</p>
  </div>
</div>

<div class="container">
  <div class="section" style="max-width:850px;margin:0 auto;">
    <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:30px;">Last updated: September 2026</p>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">1. What Cookies Are</h2>
      <p style="color:var(--text-muted);line-height:1.9;">Cookies are small text files that a website stores on your device. They let the site remember things between pages, such as what is in your shopping cart or whether you are logged in.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">2. Cookies We Use</h2>
      <p style="color:var(--text-muted);line-height:1.9;margin-bottom:10px;">This website uses only the cookies listed below. All of them are set by us, and none of them are used for advertising, profiling, or tracking you across other websites.</p>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Name</th><th>Purpose</th><th>Type</th><th>Duration</th></tr></thead>
          <tbody>
            <tr><td>PHPSESSID</td><td>Keeps you logged in and holds your shopping cart while you browse</td><td>Strictly necessary</td><td>Session (deleted when you close your browser)</td></tr>
            <tr><td>csrf_token</td><td>Protects form submissions against cross-site request forgery attacks</td><td>Strictly necessary</td><td>Session</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">3. No Analytics or Advertising Cookies</h2>
      <p style="color:var(--text-muted);line-height:1.9;">We do not run analytics, advertising pixels, or third-party trackers on this website. No cookie on this site is used to follow you around the internet or build a profile of you.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">4. Third-Party Services</h2>
      <p style="color:var(--text-muted);line-height:1.9;margin-bottom:10px;">Two external services can interact with the site, and each is limited to the moment you use it:</p>
      <ul style="color:var(--text-muted);line-height:1.9;padding-left:25px;">
        <li><strong>Paystack</strong>, our payment processor. Its scripts load only on the checkout page when you choose to pay online, and it may set its own cookies to secure the payment. See Paystack's privacy policy for details.</li>
        <li><strong>WhatsApp, Instagram and TikTok</strong> links in the footer and chat buttons. These only open when you click them; until then, nothing is loaded from those services.</li>
      </ul>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">5. Managing Cookies</h2>
      <p style="color:var(--text-muted);line-height:1.9;">You can delete or block cookies at any time in your browser settings. Because our cookies are strictly necessary, blocking them will stop the shopping cart, login, and checkout from working, but the rest of the site remains fully browsable.</p>
    </div>

    <div style="margin-bottom:35px;">
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">6. Changes to This Policy</h2>
      <p style="color:var(--text-muted);line-height:1.9;">If we ever add a new cookie, we will update this page.</p>
    </div>

    <div>
      <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:12px;">7. Contact Us</h2>
      <p style="color:var(--text-muted);line-height:1.9;">Questions about cookies? Email <strong>drdehandel@gmail.com</strong> or call <strong>0542332244 | 0549986486</strong>.</p>
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
