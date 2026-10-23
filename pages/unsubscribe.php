<?php
$pageTitle = 'Unsubscribe';
$metaDescription = 'Stop receiving Handel Luxe GH marketing emails.';
$noIndex = true;
// The token is a capability in the URL. Keep it out of the canonical tag.
$canonicalPath = '/unsubscribe';
require_once __DIR__ . '/../config/newsletter.php';

$token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // The unguessable token is the authority for a sessionless request; a
    // CSRF token would not add protection here and would break email links
    // opened in a clean browser.
    $result = newsletterUnsubscribe($token);
} else {
    $result = newsletterTokenStatus($token);
}
?>
<?php require __DIR__ . '/../includes/header.php'; ?>

<div class="hero">
  <div class="container">
    <h1>Email Preferences</h1>
    <p>Manage your Handel Luxe GH marketing subscription</p>
  </div>
</div>

<div class="container">
  <div class="section" style="max-width:620px;margin:0 auto;">
    <div class="form-card">
      <?php if ($result === 'unsubscribed'): ?>
        <h2 style="margin-top:0;">You have been unsubscribed</h2>
        <p class="text-muted">You will no longer receive marketing emails from Handel Luxe GH. Order confirmations and other transactional messages are unaffected.</p>
      <?php elseif ($result === 'already'): ?>
        <h2 style="margin-top:0;">Already unsubscribed</h2>
        <p class="text-muted">This address is not on our marketing list.</p>
      <?php elseif ($result === 'invalid'): ?>
        <h2 style="margin-top:0;">Link not recognised</h2>
        <p class="text-muted">This unsubscribe link is invalid or has expired. If you keep receiving marketing emails, contact us at <strong>drdehandel@gmail.com</strong> and we will remove your address.</p>
      <?php else: ?>
        <h2 style="margin-top:0;">Unsubscribe from marketing emails?</h2>
        <p class="text-muted">This stops promotional emails. Order confirmations, delivery updates and password-reset messages are transactional and will still be sent.</p>
        <form method="post" action="<?= base_url('/unsubscribe') ?>">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="btn btn-primary">Unsubscribe</button>
        </form>
      <?php endif; ?>
      <p class="text-muted" style="margin-top:20px;"><a class="text-link" href="<?= base_url('/') ?>">Return to the store</a></p>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
