<?php
/**
 * TASK 1 — canonical URL configuration.
 *
 * Every case runs config/app.php in a child process, because BASE_URL and
 * APP_URL are constants and cannot be redefined in one request.
 */

function probe_url_env(array $env) {
    $keys = ['APP_URL', 'APP_ENV', 'TEST_HTTP_HOST', 'TEST_XFH', 'TEST_XFP', 'TEST_SCRIPT_NAME', 'TEST_HTTPS'];
    foreach ($keys as $k) @putenv($k);
    foreach ($env as $k => $v) putenv($k . '=' . $v);

    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/probe_url.php') . ' 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);

    foreach ($keys as $k) @putenv($k);
    return ['code' => $code, 'out' => trim(implode("\n", $out))];
}

echo "[url] canonical configuration\n";

$r = probe_url_env([
    'APP_ENV' => 'production',
    'APP_URL' => 'https://shop.example.com/store',
    'TEST_HTTP_HOST' => 'evil.example.net',
    'TEST_XFH' => 'evil.example.net',
    'TEST_XFP' => 'http',
    'TEST_SCRIPT_NAME' => '/store/index.php',
]);
t_eq($r['out'], 'https://shop.example.com/store/', 'canonical HTTPS subdirectory wins over malicious Host and X-Forwarded-Host');

$r = probe_url_env([
    'APP_ENV' => 'local',
    'APP_URL' => 'http://localhost/handel-luxe-php',
    'TEST_HTTP_HOST' => 'attacker.example',
    'TEST_SCRIPT_NAME' => '/handel-luxe-php/index.php',
]);
t_eq($r['out'], 'http://localhost/handel-luxe-php/', 'local http://localhost config is allowed and ignores Host');

// The probe runs under the CLI SAPI, which has no web root, so the fallback
// path is the site root. The security property under test is the host: even
// with a hostile Host and X-Forwarded-Host, the result is fixed to localhost.
$r = probe_url_env([
    'APP_ENV' => 'production',
    'APP_URL' => '',
    'TEST_HTTP_HOST' => 'evil.example.net',
    'TEST_XFH' => 'evil.example.net',
    'TEST_SCRIPT_NAME' => '/handel-luxe-php/index.php',
]);
t_eq($r['out'], 'http://localhost/', 'missing APP_URL falls back to a fixed localhost host, never the request Host');

$r = probe_url_env([
    'APP_ENV' => 'production',
    'APP_URL' => 'https://shop.example.com',
    'TEST_SCRIPT_NAME' => '/index.php',
]);
t_eq($r['out'], 'https://shop.example.com/', 'root deployment canonical URL');

$r = probe_url_env([
    'APP_ENV' => 'staging',
    'APP_URL' => 'https://shop.example.com:8443/app',
    'TEST_SCRIPT_NAME' => '/app/index.php',
]);
t_eq($r['out'], 'https://shop.example.com:8443/app/', 'explicit port and subdirectory preserved');

$r = probe_url_env([
    'APP_ENV' => 'production',
    'APP_URL' => 'https://shop.example.com/store/',
    'TEST_SCRIPT_NAME' => '/store/index.php',
]);
t_eq($r['out'], 'https://shop.example.com/store/', 'trailing slash normalized');

$r = probe_url_env([
    'APP_ENV' => 'production',
    'APP_URL' => 'http://shop.example.com',
    'TEST_SCRIPT_NAME' => '/index.php',
]);
t_assert($r['code'] !== 0 && strpos($r['out'], 'Configuration error') !== false, 'plain HTTP on a public host is rejected');

$r = probe_url_env([
    'APP_ENV' => 'production',
    'APP_URL' => 'https://user:secret@shop.example.com',
    'TEST_SCRIPT_NAME' => '/index.php',
]);
t_assert($r['code'] !== 0, 'APP_URL with embedded credentials is rejected');

$r = probe_url_env([
    'APP_ENV' => 'production',
    'APP_URL' => 'https://shop.example.com/store?x=1',
    'TEST_SCRIPT_NAME' => '/store/index.php',
]);
t_assert($r['code'] !== 0, 'APP_URL with a query string is rejected');

$r = probe_url_env([
    'APP_ENV' => 'production',
    'APP_URL' => 'https://shop.example.com/../etc',
    'TEST_SCRIPT_NAME' => '/index.php',
]);
t_assert($r['code'] !== 0, 'APP_URL path traversal is rejected');
