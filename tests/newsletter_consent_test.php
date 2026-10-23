<?php
/**
 * TASK 3 — newsletter consent storage, resubscription and unsubscribe.
 * Uses the local database and cleans up every row it creates.
 */
echo "[newsletter] consent, resubscribe and suppression\n";

require_once __DIR__ . '/../config/newsletter.php';

try {
    $db = getDB();
} catch (Throwable $e) {
    t_skip('database unavailable: ' . $e->getMessage());
    return;
}

ensureSubscriberSchema();

$cols = [];
$res = $db->query("SHOW COLUMNS FROM handel_subscribers");
while ($row = $res->fetch_assoc()) $cols[$row['Field']] = true;
t_assert(isset($cols['consent_at'], $cols['consent_version'], $cols['unsubscribe_token'], $cols['unsubscribed_at']),
    'schema has consent_at, consent_version, unsubscribe_token and unsubscribed_at');

$email = 'qa-newsletter-' . bin2hex(random_bytes(5)) . '@example.test';
$legacy = 'qa-legacy-' . bin2hex(random_bytes(5)) . '@example.test';

$token = newsletterSubscribe($email);
t_assert(is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1, 'subscribe issues a 64-hex unsubscribe token');

$stmt = $db->prepare("SELECT consent_at, consent_version, unsubscribe_token, unsubscribed_at FROM handel_subscribers WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
t_assert(!empty($row['consent_at']), 'consent_at is recorded at subscribe time');
t_eq($row['consent_version'], NEWSLETTER_CONSENT_VERSION, 'consent_version records the wording in force');
t_eq($row['unsubscribed_at'], null, 'new subscription is active');

$recipients = array_column(newsletterRecipientRows(), 'email');
t_assert(in_array($email, $recipients, true), 'opted-in subscriber is a marketing recipient');
t_eq(newsletterTokenStatus($token), 'pending', 'unsubscribe token is pending before use');

t_eq(newsletterUnsubscribe($token), 'unsubscribed', 'first unsubscribe succeeds');
t_eq(newsletterTokenStatus($token), 'already', 'second status lookup reports already unsubscribed');
t_eq(newsletterUnsubscribe($token), 'already', 'unsubscribe is idempotent');
$recipients = array_column(newsletterRecipientRows(), 'email');
t_assert(!in_array($email, $recipients, true), 'unsubscribed address is excluded from recipients');

$token2 = newsletterSubscribe($email);
t_assert($token2 !== $token, 'resubscribe rotates the unsubscribe token');
$stmt = $db->prepare("SELECT unsubscribed_at FROM handel_subscribers WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
t_eq($row['unsubscribed_at'], null, 'resubscribe clears the previous unsubscribe');
$recipients = array_column(newsletterRecipientRows(), 'email');
t_assert(in_array($email, $recipients, true), 'resubscribed address returns to the recipient list');

// A legacy row (created before consent was recorded) must stay suppressed.
$stmt = $db->prepare("INSERT INTO handel_subscribers (email) VALUES (?)");
$stmt->bind_param('s', $legacy);
$stmt->execute();
$stmt->close();
$recipients = array_column(newsletterRecipientRows(), 'email');
t_assert(!in_array($legacy, $recipients, true), 'legacy row with no recorded consent is suppressed');

t_eq(newsletterUnsubscribe('not-a-token'), 'invalid', 'malformed unsubscribe token is rejected');

$db->query("DELETE FROM handel_subscribers WHERE email IN ('" . $db->real_escape_string($email) . "','" . $db->real_escape_string($legacy) . "')");
