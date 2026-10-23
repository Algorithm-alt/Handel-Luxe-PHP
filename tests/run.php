<?php
/**
 * Release-readiness regression runner.
 *
 * Usage:  php tests/run.php
 *
 * Covers the five audit tasks: canonical URL configuration, security headers,
 * newsletter consent, delivery fee parity, and Paystack/SMTP safety. Tests that
 * need the local database or web server skip cleanly when those are absent, so
 * the suite is safe to run on a laptop or in CI.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/lib.php';

$tests = [
    'url_config_test.php'        => 'Task 1  canonical URL configuration',
    'headers_policy_test.php'    => 'Task 2  security headers',
    'newsletter_consent_test.php' => 'Task 3  newsletter consent',
    'delivery_parity_test.php'   => 'Task 4  delivery fee parity',
    'paystack_config_test.php'   => 'Task 5  Paystack configuration',
    'paystack_webhook_test.php'  => 'Task 5  Paystack webhook processing',
    'smtp_test.php'              => 'Task 5  SMTP failure handling',
];

echo "Handel Luxe GH — release readiness\n";
echo str_repeat('=', 60) . "\n";

foreach ($tests as $file => $label) {
    echo "\n" . $label . "\n";
    $path = __DIR__ . '/' . $file;
    if (!is_file($path)) {
        t_skip("$file not found");
        continue;
    }
    try {
        require $path;
    } catch (Throwable $e) {
        t_fail("$file threw: " . $e->getMessage());
    }
}

exit(t_summary());
