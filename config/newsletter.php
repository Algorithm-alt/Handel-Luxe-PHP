<?php
/**
 * Newsletter subscriber schema and consent handling.
 *
 * Consent is recorded explicitly, not inferred. A row only counts as a
 * marketing recipient while consent_at is set and unsubscribed_at is NULL,
 * so the admin promo sender cannot email a registered customer who never
 * opted in, nor a subscriber who has since unsubscribed.
 *
 * The legacy table (email + created_at only) is upgraded in place by
 * ensureSubscriberSchema(), following the same self-healing pattern as
 * ensureZones() and ensureOrderItemsSchema(). Existing rows keep consent_at
 * NULL on purpose: there is no evidence they consented, so they stay
 * suppressed until they subscribe again through the form.
 */
require_once __DIR__ . '/db.php';

if (!defined('NEWSLETTER_CONSENT_VERSION')) {
    // Bump when the wording on the sign-up form materially changes.
    define('NEWSLETTER_CONSENT_VERSION', '2026-10-marketing-v1');
}

function ensureSubscriberSchema() {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $db = getDB();
        $db->query("CREATE TABLE IF NOT EXISTS handel_subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(150) NOT NULL UNIQUE,
            consent_at DATETIME NULL,
            consent_version VARCHAR(32) NULL,
            unsubscribe_token VARCHAR(64) NULL,
            unsubscribed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_subscriber_token (unsubscribe_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Adds columns the pre-consent table is missing. A legacy row is left
        // with consent_at NULL rather than being credited with consent it
        // never recorded.
        $existing = [];
        $cols = $db->query("SHOW COLUMNS FROM handel_subscribers");
        while ($row = $cols->fetch_assoc()) $existing[$row['Field']] = true;
        if (!isset($existing['consent_at']))      $db->query("ALTER TABLE handel_subscribers ADD COLUMN consent_at DATETIME NULL AFTER email");
        if (!isset($existing['consent_version'])) $db->query("ALTER TABLE handel_subscribers ADD COLUMN consent_version VARCHAR(32) NULL AFTER consent_at");
        if (!isset($existing['unsubscribe_token'])) $db->query("ALTER TABLE handel_subscribers ADD COLUMN unsubscribe_token VARCHAR(64) NULL AFTER consent_version");
        if (!isset($existing['unsubscribed_at'])) $db->query("ALTER TABLE handel_subscribers ADD COLUMN unsubscribed_at DATETIME NULL AFTER unsubscribe_token");

        $idx = $db->query("SHOW INDEX FROM handel_subscribers WHERE Key_name = 'uniq_subscriber_token'");
        if ($idx && $idx->num_rows === 0) {
            $db->query("ALTER TABLE handel_subscribers ADD UNIQUE KEY uniq_subscriber_token (unsubscribe_token)");
        }
    } catch (Throwable $e) {
        /* Schema upkeep must never break the page; the sign-up endpoint will
           report a clean 500 instead of a PHP fatal if a column is missing. */
    }
}

/**
 * Record (or refresh) marketing consent and return the unsubscribe token.
 * Subscribing twice is not an error: consent_at is refreshed, consent_version
 * updated and the unsubscribe token rotated, and a previously unsubscribed
 * address is re-enabled with a fresh token.
 */
function newsletterSubscribe($email) {
    ensureSubscriberSchema();
    $db = getDB();
    $email = strtolower(trim($email));
    $token = bin2hex(random_bytes(32));
    $version = NEWSLETTER_CONSENT_VERSION;

    $stmt = $db->prepare("INSERT INTO handel_subscribers
            (email, consent_at, consent_version, unsubscribe_token, unsubscribed_at)
        VALUES (?, NOW(), ?, ?, NULL)
        ON DUPLICATE KEY UPDATE
            consent_at = NOW(),
            consent_version = VALUES(consent_version),
            unsubscribe_token = VALUES(unsubscribe_token),
            unsubscribed_at = NULL");
    $stmt->bind_param('sss', $email, $version, $token);
    $stmt->execute();
    $stmt->close();
    return $token;
}

/**
 * Marketing recipients only: a recorded consent that has not been withdrawn
 * and a token that can carry an unsubscribe link. Returns
 * [['email' => ..., 'unsubscribe_token' => ...], ...].
 */
function newsletterRecipientRows() {
    ensureSubscriberSchema();
    $db = getDB();
    $rows = [];
    $res = $db->query("SELECT email, unsubscribe_token FROM handel_subscribers
        WHERE consent_at IS NOT NULL
          AND unsubscribed_at IS NULL
          AND unsubscribe_token IS NOT NULL
          AND unsubscribe_token <> ''
        ORDER BY id");
    if ($res) {
        while ($row = $res->fetch_assoc()) $rows[] = $row;
    }
    return $rows;
}

/**
 * Status of an unsubscribe token:
 *   'pending'  - valid consent, can still be withdrawn
 *   'already'  - already unsubscribed
 *   'invalid'  - unknown or malformed token
 */
function newsletterTokenStatus($token) {
    ensureSubscriberSchema();
    $token = (string)$token;
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) return 'invalid';
    $db = getDB();
    $stmt = $db->prepare("SELECT unsubscribed_at FROM handel_subscribers WHERE unsubscribe_token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return 'invalid';
    return $row['unsubscribed_at'] === null ? 'pending' : 'already';
}

/**
 * Withdraw marketing consent. Idempotent: a repeat call reports 'already'
 * rather than changing anything. Returns the same codes as
 * newsletterTokenStatus(), plus 'unsubscribed' on the first success.
 */
function newsletterUnsubscribe($token) {
    ensureSubscriberSchema();
    $token = (string)$token;
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) return 'invalid';
    $db = getDB();
    $stmt = $db->prepare("SELECT id, unsubscribed_at FROM handel_subscribers WHERE unsubscribe_token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return 'invalid';
    if ($row['unsubscribed_at'] !== null) return 'already';

    $stmt = $db->prepare("UPDATE handel_subscribers SET unsubscribed_at = NOW() WHERE id = ? AND unsubscribed_at IS NULL");
    $stmt->bind_param('i', $row['id']);
    $stmt->execute();
    $stmt->close();
    return 'unsubscribed';
}
