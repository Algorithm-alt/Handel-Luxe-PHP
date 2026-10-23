<?php
require_once __DIR__ . '/../config/app.php';
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => app_is_https(),
]);
header('Content-Type: application/json');

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Normalize: '/api/products/' and '/api/products' route the same (Express parity)
if (substr($route, -1) === '/') {
    $route = rtrim($route, '/');
    $_GET['route'] = $route;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/delivery.php';
require_once __DIR__ . '/../config/newsletter.php';
require_once __DIR__ . '/../config/ratelimit.php';
require_once __DIR__ . '/../config/audit.php';

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function getInput() {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    // Only a top-level JSON object is a valid body. A bare list, a scalar
    // ("123", "hi"), or `null` for malformed/empty input must not be handed
    // to handlers as data: `$input['name'] ?? ''` reads offsets off strings
    // and numbers and produces junk fields instead of a clean 400.
    if (!is_array($input)) return [];
    // A JSON list (["a","b"]) also decodes to an array but has no named
    // fields — treat it as empty too so validation errors surface normally.
    $firstKey = array_key_first($input);
    if ($firstKey !== null && is_int($firstKey)) return [];
    // Field values must be scalar. A nested array/object would reach trim(),
    // (int), password_verify() and similar calls as an array and raise a
    // TypeError (HTTP 500). Treat those values as missing instead.
    foreach ($input as $key => $value) {
        if (is_array($value) || is_object($value)) $input[$key] = null;
    }
    return $input;
}

function requireAuth() {
    if (empty($_SESSION['user'])) {
        jsonResponse(['error' => 'Login required'], 401);
    }

    // Sessions cache the profile captured at login. Re-read the account so a
    // deleted user cannot keep using an old session, and so a role change
    // (especially a demoted admin) takes effect immediately instead of at
    // the next login.
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email, role FROM handel_users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user']['id']);
    $stmt->execute();
    $fresh = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fresh) {
        unset($_SESSION['user']);
        jsonResponse(['error' => 'Login required'], 401);
    }
    $_SESSION['user'] = $fresh;
}

function requireAdmin() {
    requireAuth();
    if ($_SESSION['user']['role'] !== 'admin') {
        jsonResponse(['error' => 'Admin access required'], 403);
    }
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'OPTIONS'], true)) {
        auditCurrentActor('admin.mutation_requested', [
            'method' => $_SERVER['REQUEST_METHOD'],
            'route' => substr((string)($_GET['route'] ?? ''), 0, 180),
        ]);
    }
}

function getCartKey() {
    if (!empty($_SESSION['user'])) {
        return ['column' => 'user_id', 'value' => $_SESSION['user']['id']];
    }
    if (empty($_SESSION['guest_token'])) {
        $_SESSION['guest_token'] = bin2hex(random_bytes(16));
    }
    return ['column' => 'guest_token', 'value' => $_SESSION['guest_token']];
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Send the current token cookie BEFORE the CSRF check — a rejected request
// must still refresh a stale browser cookie, otherwise the visitor stays
// wedged on an old token (e.g. after a logout destroyed the session) and
// every POST fails until some GET API call happens to fix it.
setcookie('csrf_token', $_SESSION['csrf_token'], ['path' => '/', 'httponly' => false, 'samesite' => 'Lax', 'secure' => app_is_https()]);

$safeMethods = ['GET', 'HEAD', 'OPTIONS'];
$isWebhook = (explode('/', $route)[0] ?? '') === 'webhook';
if (!$isWebhook && !in_array($method, $safeMethods)) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (!$token || !hash_equals((string)$_SESSION['csrf_token'], (string)$token)) {
        jsonResponse(['error' => 'CSRF token missing or invalid'], 403);
    }
}

require_once __DIR__ . '/../config/paystack.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../config/whatsapp.php';
require_once __DIR__ . '/../config/cart.php';
require_once __DIR__ . '/../config/images.php';
require_once __DIR__ . '/../config/orders.php';

/* Bring handel_order_items up to date before any handler can read or write
   it. A no-op once migrated (see config/orders.php). */
ensureOrderItemsSchema();

$files = [
    'auth' => __DIR__ . '/auth.php',
    'profile' => __DIR__ . '/profile.php',
    'products' => __DIR__ . '/products.php',
    'cart' => __DIR__ . '/cart.php',
    'orders' => __DIR__ . '/orders.php',
    'admin' => __DIR__ . '/admin.php',
    'payments' => __DIR__ . '/payments.php',
    'contact' => __DIR__ . '/contact.php',
    'delivery' => __DIR__ . '/delivery.php',
    'newsletter' => __DIR__ . '/newsletter.php',
    'request-fragrance' => __DIR__ . '/request-fragrance.php',
    'developer' => __DIR__ . '/developer.php',
    'webhook' => __DIR__ . '/webhook.php',
];

$base = explode('/', $route)[0] ?? '';

if (isset($files[$base])) {
    require $files[$base];
} else {
    jsonResponse(['error' => 'API endpoint not found'], 404);
}
