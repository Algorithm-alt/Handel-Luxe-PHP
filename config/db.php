<?php
require_once __DIR__ . '/app.php';
requireSecrets();

/* Connection settings live in config/secrets.php, alongside every other
   credential — or in the environment, which takes precedence over that file.
   These defaults run only when neither has supplied a value, and they stay
   empty on purpose so requireConfig() can report the problem instead of PHP
   fataling on an undefined constant.

   An empty DB_HOST or DB_USER is NOT a harmless fallback: mysqli reads an
   empty host as "localhost" and an empty user as "the current OS user", so
   defaulting to anything would mean silently connecting to whatever database
   happens to be listening, with whatever privileges the web server runs as. */
if (!defined('DB_HOST')) define('DB_HOST', env_or('DB_HOST', ''));
if (!defined('DB_PORT')) define('DB_PORT', env_or('DB_PORT', ''));
if (!defined('DB_USER')) define('DB_USER', env_or('DB_USER', ''));
if (!defined('DB_PASS')) define('DB_PASS', env_or('DB_PASS', ''));
if (!defined('DB_NAME')) define('DB_NAME', env_or('DB_NAME', ''));

/**
 * The connection settings, with the fail-closed guard in front of them.
 * Shared with setup.php, whose server-level connection (no database selected
 * yet) would otherwise slip through with empty credentials.
 *
 * DB_PASS is deliberately not required: a stock XAMPP MySQL root account has
 * no password, so an empty one is a legitimate value, not a missing one.
 */
function dbConfig() {
    $missing = 'Database is not configured: set DB_HOST, DB_PORT, DB_USER, DB_PASS and DB_NAME in config/secrets.php or as environment variables (see config/secrets.example.php).';
    requireConfig('DB_HOST', $missing);
    requireConfig('DB_USER', $missing);
    requireConfig('DB_NAME', $missing);

    return [
        'host' => DB_HOST,
        'port' => (int)(DB_PORT !== '' ? DB_PORT : 3306),
        'user' => DB_USER,
        'pass' => DB_PASS,
        'name' => DB_NAME,
    ];
}

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $cfg = dbConfig();
        $conn = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], $cfg['port']);
        if ($conn->connect_error) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
