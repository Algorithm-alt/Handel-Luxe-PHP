<?php
/**
 * Child-process probe for Paystack configuration. It prints only derived
 * modes and a boolean mismatch flag — never a key value.
 */
if (getenv('TEST_HTTP_HOST') !== false) {
    $_SERVER['HTTP_HOST'] = (string)getenv('TEST_HTTP_HOST');
    $_SERVER['HTTP_X_FORWARDED_HOST'] = (string)getenv('TEST_HTTP_HOST');
}

require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/paystack.php';

echo json_encode([
    'secret_mode' => paystackKeyMode(PAYSTACK_SECRET_KEY),
    'public_mode' => paystackKeyMode(PAYSTACK_PUBLIC_KEY),
    'mismatch' => paystackEnvironmentMismatch(),
    'callback' => base_url('/payment/verify'),
    'reset' => base_url('/reset-password?token=EXAMPLE'),
]);
