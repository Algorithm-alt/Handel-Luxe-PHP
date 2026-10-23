<?php
require_once __DIR__ . '/app.php';
requireSecrets();

/* PAYSTACK_SECRET_KEY and PAYSTACK_PUBLIC_KEY live in config/secrets.php —
   or in the environment, which takes precedence. The empty defaults below
   mean an install with none of those fails through requireConfig() with a
   clear message, rather than fataling on an undefined constant somewhere
   deeper in the request. */
if (!defined('PAYSTACK_SECRET_KEY')) {
    define('PAYSTACK_SECRET_KEY', env_or('PAYSTACK_SECRET_KEY', ''));
}
if (!defined('PAYSTACK_PUBLIC_KEY')) {
    define('PAYSTACK_PUBLIC_KEY', env_or('PAYSTACK_PUBLIC_KEY', ''));
}

/* The API endpoint is configuration, not a credential. */
if (!defined('PAYSTACK_BASE_URL')) {
    define('PAYSTACK_BASE_URL', 'https://api.paystack.co');
}

/**
 * Which Paystack environment a key belongs to: 'test', 'live' or 'unknown'.
 * Paystack prefixes keys with sk_test_/pk_test_ or sk_live_/pk_live_.
 */
function paystackKeyMode($key) {
    if (preg_match('/^(?:sk|pk)_(test|live)_/i', (string)$key, $m)) {
        return strtolower($m[1]);
    }
    return 'unknown';
}

/**
 * A non-empty message when the secret and public keys come from different
 * Paystack environments, otherwise null. Mixing a test secret with a live
 * public key initializes a transaction the inline widget can never pay.
 */
function paystackEnvironmentMismatch() {
    $secret = defined('PAYSTACK_SECRET_KEY') ? (string)PAYSTACK_SECRET_KEY : '';
    $public = defined('PAYSTACK_PUBLIC_KEY') ? (string)PAYSTACK_PUBLIC_KEY : '';
    if ($secret === '' || $public === '') return null;
    $secretMode = paystackKeyMode($secret);
    $publicMode = paystackKeyMode($public);
    if ($secretMode === 'unknown' || $publicMode === 'unknown') return null;
    if ($secretMode !== $publicMode) {
        return 'Paystack secret and public keys belong to different environments (test vs live).';
    }
    return null;
}
