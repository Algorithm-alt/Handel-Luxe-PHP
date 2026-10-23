<?php
/* config/.env is loaded before anything else so early configuration such as
   APP_URL is available here, not only in the files that later call
   requireSecrets(). loadDotEnv() is declared below; PHP resolves the call at
   runtime. It never overrides a real environment variable. */
loadDotEnv();

define('APP_NAME', 'Handel Luxe GH');

// Deployment profile: 'local' keeps local-only behaviour (for example the GA
// tag stays off); 'staging' and 'production' are non-local. Production servers
// may also override this through the environment.
define('APP_ENV', strtolower(env_or('APP_ENV', 'production')));

// Google Analytics 4 measurement ID (format: 'G-XXXXXXXXXX'). Leave empty
// to keep analytics off; the header only loads the tag when this is set.
define('GA_MEASUREMENT_ID', '');

/* Public shop WhatsApp number for click-to-chat links (the "Request a
   Fragrance" flow). International format, digits only: no "+", spaces or
   punctuation. Ghana's 0542332244 is 233542332244. Kept here rather than in
   page markup so one value drives every wa.me link; the environment can
   override it per deployment. */
define('WHATSAPP_SHOP_NUMBER', env_or('WHATSAPP_SHOP_NUMBER', '233542332244'));

/* The database connection settings (DB_HOST, DB_PORT, DB_USER, DB_PASS,
   DB_NAME) are credentials, so they live in config/secrets.php with the rest.
   config/db.php loads them and refuses to connect when they are missing. */

/**
 * Parse and validate the canonical application URL.
 *
 * APP_URL must be an absolute http(s) URL. HTTP is accepted only for a local
 * host (localhost / loopback / *.test); every public deployment must use
 * HTTPS. Credentials, query strings and fragments are rejected because they
 * have no meaning in a base URL and would leak into generated links.
 *
 * Returns ['scheme' => ..., 'host' => ..., 'port' => ..., 'path' => ...] or
 * null when the value is not usable.
 */
function appParseCanonicalUrl($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    if (preg_match('/[\x00-\x1f\x7f\\\\]/', $raw)) return null;

    $parts = parse_url($raw);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return null;
    if (isset($parts['user']) || isset($parts['pass'])) return null;
    if (isset($parts['query']) || isset($parts['fragment'])) return null;

    $scheme = strtolower($parts['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') return null;

    $host = strtolower($parts['host']);
    if (preg_match('/[\s\/?#@]/', $host)) return null;

    $bareHost = trim($host, '[]');
    $isLocal = $bareHost === 'localhost'
        || $bareHost === '127.0.0.1'
        || $bareHost === '::1'
        || substr($bareHost, -10) === '.localhost'
        || substr($bareHost, -5) === '.test';
    if ($scheme !== 'https' && !$isLocal) return null;

    $port = isset($parts['port']) ? (int)$parts['port'] : null;
    if ($port !== null && ($port < 1 || $port > 65535)) return null;

    $path = rawurldecode($parts['path'] ?? '');
    if (strpos($path, '..') !== false) return null;
    $path = '/' . trim(str_replace('\\', '/', $path), '/');
    if ($path === '/') $path = '';

    return [
        'scheme' => $scheme,
        'host'   => $host,
        'port'   => $port,
        'path'   => $path,
    ];
}

/**
 * Stop the request when APP_URL is present but unsafe.
 *
 * Silently falling back would be worse: a reset link generated over HTTP, or
 * with a typo in the port, is a security or support incident. The message
 * contains no configured value and no credentials.
 */
function appInvalidCanonicalUrl() {
    $message = 'APP_URL must be an absolute https:// URL (http is allowed only for localhost/loopback/.test), without credentials, a query string or a fragment.';
    error_log('Configuration error: ' . $message);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Configuration error: ' . $message . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    if (!headers_sent()) header('Content-Type: text/plain; charset=UTF-8');
    exit('Configuration error: ' . $message . PHP_EOL);
}

$canonical = null;
$rawAppUrl = trim((string)env_or('APP_URL', ''));
if ($rawAppUrl !== '') {
    $canonical = appParseCanonicalUrl($rawAppUrl);
    if ($canonical === null) appInvalidCanonicalUrl();
}

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

if ($canonical !== null) {
    /* The canonical URL owns both the host and the subdirectory. No request
       header is consulted, so Host/X-Forwarded-Host poisoning cannot change
       a password-reset link, a Paystack callback or a canonical tag. */
    $basePath = $canonical['path'] === '' ? '/' : $canonical['path'] . '/';
    $authority = $canonical['host'] . ($canonical['port'] !== null ? ':' . $canonical['port'] : '');
    $baseUrl = $canonical['scheme'] . '://' . $authority . $basePath;
} else {
    /* No APP_URL: development fallback only. The host is fixed to localhost,
       never the request's Host header. The subdirectory is still detected from
       SCRIPT_NAME so XAMPP installs such as /handel-luxe-php keep working. */
// CLI scripts have no web root; dirname('') is '.', which would turn
// BASE_PATH into './' and BASE_URL into "http://localhost./".
$baseDir = (PHP_SAPI === 'cli' || $scriptName === '')
    ? ''
    : rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
// API requests execute api/index.php, which would make the base '/app/api'.
// The app's front controllers live one level up, so strip the /api suffix.
if (substr($baseDir, -4) === '/api') {
    $baseDir = substr($baseDir, 0, -4);
}
    $basePath = $baseDir === '' ? '/' : $baseDir . '/';
    $baseUrl = 'http://localhost' . $basePath;
}

define('BASE_PATH', $basePath);
define('BASE_URL', $baseUrl);

/** True when generated links use HTTPS (the canonical URL's scheme). */
function app_is_https() {
    return strpos(BASE_URL, 'https://') === 0;
}

/** True for local development profiles; used to keep local extras off. */
function app_is_local() {
    return APP_ENV === 'local' || APP_ENV === 'development' || APP_ENV === 'testing';
}

function base_url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

function asset_url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

/**
 * Product images are stored root-relative ('/images/...', '/uploads/...'),
 * which is how the Node original served them from the domain root. Under a
 * subdirectory install they must be prefixed with the app's base URL.
 * External/data URLs are passed through untouched — but javascript: and
 * other script-capable schemes are never valid image sources, so they fall
 * back to the placeholder instead of being echoed into markup.
 */
function image_url($path = '') {
    if ($path === null || $path === '') return $path;
    if (preg_match('#^(https?:)?//#i', $path) || strpos($path, 'data:') === 0) return $path;
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $path)) return '/images/placeholder.png';
    return BASE_URL . ltrim($path, '/');
}

/**
 * Read a credential from the environment, falling back to the value in
 * config/secrets.php (or to the built-in default when that file is absent).
 *
 * Precedence is environment > secrets.php > built-in default. The deployment
 * environment gets the final say, so one code copy can run in staging and
 * production without shipping a different secrets.php in each, and a
 * credential can be rotated by restarting a container instead of editing a
 * file on the server.
 *
 * getenv() rather than $_ENV: under mod_php and php-fpm, $_ENV is populated
 * only when variables_order contains "E" (by default it does not), whereas
 * getenv() reads the real environment either way. That makes all three of
 * these work without touching php.ini:
 *
 *   Apache    SetEnv DB_PASS ...                       (vhost or .htaccess)
 *   php-fpm   env[DB_PASS] = ...                       (pool config; inherited
 *                                                       shell vars also need
 *                                                       clear_env = no)
 *   Docker    docker run -e DB_PASS=...
 *
 * An empty variable counts as unset rather than as a deliberate blank. The
 * common accident is `DB_PASS=$SECRET` in a shell where SECRET was never
 * exported: that expands to "", and falling back to the file beats silently
 * connecting with no password. To blank a value on purpose, edit secrets.php.
 */
function env_or($name, $default = '') {
    $value = getenv($name);
    /* Apache hands a SetEnv value that was set before an internal rewrite to
       CGI/FastCGI as REDIRECT_<name>. This app rewrites every request through
       a front controller, so accept that spelling too — otherwise SetEnv in
       .htaccess would be a documented method that silently does nothing. No
       client can reach these names: a request header arrives as HTTP_<name>. */
    if ($value === false || $value === '') $value = getenv('REDIRECT_' . $name);
    if ($value === false || $value === '') return $default;
    return $value;
}

/** The same precedence for a comma-separated list (WHATSAPP_ADMIN_NUMBERS). */
function env_list_or($name, $default = []) {
    $value = env_or($name, '');
    if ($value === '') return $default;
    $items = array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
    return $items ?: $default;
}

/**
 * Populate the real environment from config/.env, if that file exists.
 *
 * env_or() reads getenv(), so on a host that already injects credentials —
 * Docker, a PaaS, Apache SetEnv, a php-fpm pool — there is nothing else to
 * do. But a bare XAMPP install or shared hosting cannot inject variables
 * without admin access, so config/.env is the per-project substitute: it
 * puts values into the real environment, which is all env_or() ever looks
 * at. No other code path changes.
 *
 * Precedence is: real environment > config/.env > config/secrets.php > the
 * built-in default inside env_or(). .env NEVER overrides a value already set
 * in the actual environment, so a production box can pin its own secret even
 * while a .env in the tree keeps local development working. A value left
 * empty in .env behaves like an unset variable (matching env_or()'s rule
 * that an empty environment variable means "fall through to the file").
 *
 * The file lives in config/ — the same directory config/.htaccess already
 * denies over HTTP — rather than at the project root, where the
 * front-controller .htaccess serves existing files directly, which would
 * expose it as plain text.
 *
 * Format: KEY=value, one per line. Surrounding whitespace, blank lines and
 * lines starting with '#' are ignored; values may be wrapped in matching
 * double or single quotes. Values are simple single-line scalars.
 */
function loadDotEnv() {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    $path = __DIR__ . '/.env';
    if (!is_file($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) continue;
        if (getenv($name) !== false) continue;
        $len = strlen($value);
        if ($len >= 2 && (($value[0] === '"' && $value[$len - 1] === '"')
                       || ($value[0] === "'" && $value[$len - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv($name . '=' . $value);
    }
}

/**
 * Load the site's credentials from config/secrets.php.
 *
 * Secrets (database credentials, payment keys, SMTP credentials, WhatsApp
 * provider tokens, the installer key) are kept out of the code so the project
 * can be shared or committed without leaking them. config/secrets.php is
 * gitignored and config/.htaccess denies HTTP access to that directory. Call
 * this from any file that needs a credential — pages that don't are
 * unaffected when the file is absent.
 *
 * Before the file is read, config/.env (if present) is loaded into the
 * environment, so a local checkout that must not expose a credentials file —
 * or a host that wants to rotate without editing disk files on the server —
 * can supply the values there. See loadDotEnv() for the precedence rule.
 *
 * A missing file is deliberately NOT fatal here: config/secrets.example.php
 * documents the expected constants, and every feature that needs one stops
 * with a clear message through requireConfig() instead.
 */
function requireSecrets() {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    loadDotEnv();
    $path = __DIR__ . '/secrets.php';
    if (is_file($path)) {
        require_once $path;
    }
}

/**
 * Halt with an actionable error when a required credential is missing.
 *
 * Never let a feature fall back to an empty secret. An empty Paystack key
 * is not merely "payments won't work" — the webhook signature is an HMAC
 * keyed on that secret, so an empty key would let anyone forge a
 * charge.success event and mark orders paid.
 */
function requireConfig($constant, $message) {
    if (!defined($constant) || (string)constant($constant) === '') {
        http_response_code(503);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        exit(json_encode(['error' => $message]));
    }
}
