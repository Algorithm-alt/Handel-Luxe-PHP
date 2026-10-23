<?php
/**
 * Child-process probe for canonical URL tests. It receives a hostile Host /
 * forwarded-host / script name through the environment, then asks config/app.php
 * what URL it would generate. Output is BASE_URL (or an error) only.
 */
$_SERVER['HTTP_HOST'] = getenv('TEST_HTTP_HOST') !== false ? (string)getenv('TEST_HTTP_HOST') : 'localhost';
if (getenv('TEST_XFH') !== false) {
    $_SERVER['HTTP_X_FORWARDED_HOST'] = (string)getenv('TEST_XFH');
}
if (getenv('TEST_XFP') !== false) {
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = (string)getenv('TEST_XFP');
}
$_SERVER['SCRIPT_NAME'] = getenv('TEST_SCRIPT_NAME') !== false ? (string)getenv('TEST_SCRIPT_NAME') : '/handel-luxe-php/index.php';
if (getenv('TEST_HTTPS') !== false) {
    $_SERVER['HTTPS'] = (string)getenv('TEST_HTTPS');
}

require __DIR__ . '/../config/app.php';
echo BASE_URL;
