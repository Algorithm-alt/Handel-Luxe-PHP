<?php
/**
 * TASK 2 — security headers.
 *
 * Checks the committed .htaccess policy and, when the local Apache is
 * reachable, that the running server actually emits the headers.
 */
echo "[headers] static policy and live response\n";

require_once __DIR__ . '/../config/app.php';

$htaccess = file_get_contents(__DIR__ . '/../.htaccess');
t_contains($htaccess, 'X-Content-Type-Options "nosniff"', 'X-Content-Type-Options is configured');
t_contains($htaccess, 'Referrer-Policy "strict-origin-when-cross-origin"', 'Referrer-Policy is configured');
t_contains($htaccess, 'X-Frame-Options "DENY"', 'framing protection is configured');
t_contains($htaccess, 'Content-Security-Policy-Report-Only', 'CSP is configured in report-only mode');

preg_match('/script-src[^;]*/', $htaccess, $m);
$scriptSrc = $m[0] ?? '';
t_assert($scriptSrc !== '' && strpos($scriptSrc, "'unsafe-inline'") === false, 'CSP script-src does not contain the unsafe-inline escape hatch');
t_contains($scriptSrc, 'https://js.paystack.co', 'CSP allows the Paystack inline script');
t_contains($htaccess, "style-src 'self' 'unsafe-inline'", 'CSP allows inline styles (documented exception)');

$hstsLines = [];
foreach (preg_split('/\R/', $htaccess) as $line) {
    if (stripos($line, 'Strict-Transport-Security') !== false) $hstsLines[] = trim($line);
}
$hstsEnabled = false;
foreach ($hstsLines as $line) {
    if ($line !== '' && $line[0] !== '#') $hstsEnabled = true;
}
t_assert(!$hstsEnabled, 'HSTS is not enabled before production HTTPS is verified');

function http_response_headers($url) {
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true]]);
    @file_get_contents($url, false, $ctx);
    return isset($http_response_header) ? $http_response_header : [];
}

$base = rtrim(BASE_URL, '/');
$headers = http_response_headers($base . '/');
if (!$headers) {
    t_skip('local web server not reachable; static policy checked only');
    return;
}

$flat = strtolower(implode("\n", $headers));
$has = function ($needle) use ($flat) { return strpos($flat, strtolower($needle)) !== false; };

t_assert($has('x-content-type-options: nosniff'), 'live response sends X-Content-Type-Options');
t_assert($has('referrer-policy: strict-origin-when-cross-origin'), 'live response sends Referrer-Policy');
t_assert($has('x-frame-options: deny'), 'live response sends X-Frame-Options');
$hasCsp = false;
$hasCspRo = false;
foreach ($headers as $h) {
    if (stripos($h, 'Content-Security-Policy-Report-Only:') === 0) $hasCspRo = true;
    elseif (stripos($h, 'Content-Security-Policy:') === 0) $hasCsp = true;
}
t_assert($hasCsp, 'live response sends the enforcing minimal CSP');
t_assert($hasCspRo, 'live response sends the report-only full CSP');

$testsHeaders = http_response_headers($base . '/tests/run.php');
$status = $testsHeaders[0] ?? '';
t_assert(stripos($status, '403') !== false, 'tests directory is denied over HTTP');
