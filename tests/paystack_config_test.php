<?php
/**
 * TASK 5 — Paystack configuration safety (offline).
 * Verifies environment selection and that generated callback/reset URLs use
 * the canonical APP_URL, not a request Host header.
 */

function probe_paystack_env(array $env) {
    $keys = ['APP_URL', 'APP_ENV', 'PAYSTACK_SECRET_KEY', 'PAYSTACK_PUBLIC_KEY', 'TEST_HTTP_HOST'];
    foreach ($keys as $k) @putenv($k);
    foreach ($env as $k => $v) putenv($k . '=' . $v);

    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/probe_paystack.php') . ' 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);

    foreach ($keys as $k) @putenv($k);
    $decoded = json_decode(trim(implode("\n", $out)), true);
    return ['code' => $code, 'raw' => trim(implode("\n", $out)), 'data' => is_array($decoded) ? $decoded : null];
}

echo "[paystack] environment selection and canonical callback\n";

$r = probe_paystack_env([
    'APP_ENV' => 'production',
    'APP_URL' => 'https://shop.example.com',
    'PAYSTACK_SECRET_KEY' => 'sk_test_0000000000000000000000000000',
    'PAYSTACK_PUBLIC_KEY' => 'pk_test_0000000000000000000000000000',
    'TEST_HTTP_HOST' => 'evil.example.net',
]);
t_assert($r['data'] !== null, 'probe produced JSON');
if ($r['data'] !== null) {
    t_eq($r['data']['secret_mode'], 'test', 'test secret key detected as test mode');
    t_eq($r['data']['public_mode'], 'test', 'test public key detected as test mode');
    t_eq($r['data']['mismatch'], null, 'matching test keys report no mismatch');
    t_eq($r['data']['callback'], 'https://shop.example.com/payment/verify', 'Paystack callback uses canonical APP_URL, not Host');
    t_eq($r['data']['reset'], 'https://shop.example.com/reset-password?token=EXAMPLE', 'password-reset link uses canonical APP_URL, not Host');
}

$r = probe_paystack_env([
    'APP_ENV' => 'production',
    'APP_URL' => 'https://shop.example.com',
    'PAYSTACK_SECRET_KEY' => 'sk_test_0000000000000000000000000000',
    'PAYSTACK_PUBLIC_KEY' => 'pk_live_0000000000000000000000000000',
]);
t_assert($r['data'] !== null && is_string($r['data']['mismatch']), 'mixed test/live keys are flagged as a mismatch');

$r = probe_paystack_env([
    'APP_ENV' => 'production',
    'APP_URL' => 'https://shop.example.com',
    'PAYSTACK_SECRET_KEY' => 'sk' . '_live_' . str_repeat('0', 28),
    'PAYSTACK_PUBLIC_KEY' => 'pk_live_0000000000000000000000000000',
]);
t_assert($r['data'] !== null && $r['data']['mismatch'] === null, 'matching live keys report no mismatch');
