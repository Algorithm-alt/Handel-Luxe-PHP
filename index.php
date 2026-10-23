<?php
require_once __DIR__ . '/config/app.php';
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    // Follow the canonical URL, not the request transport: a TLS-terminating
    // proxy must not cause the session cookie to fall back to Secure=false.
    'cookie_secure' => app_is_https(),
]);

$uri = $_GET['route'] ?? '';
$uri = trim($uri, '/');

if (strpos($uri, 'api/') === 0 || $uri === 'api') {
    require __DIR__ . '/api/index.php';
    exit;
}

switch ($uri) {
    case '':
    case 'home':
        $pageTitle = 'Home';
        require __DIR__ . '/pages/index.php';
        break;
    case 'products':
        $pageTitle = 'Products';
        require __DIR__ . '/pages/products.php';
        break;
    case 'wishlist':
        $pageTitle = 'Wishlist';
        require __DIR__ . '/pages/wishlist.php';
        break;
    case 'product':
        $pageTitle = 'Product Details';
        require __DIR__ . '/pages/product-detail.php';
        break;
    case 'cart':
        $pageTitle = 'Shopping Cart';
        require __DIR__ . '/pages/cart.php';
        break;
    case 'checkout':
        $pageTitle = 'Checkout';
        require __DIR__ . '/pages/checkout.php';
        break;
    case 'login':
        $pageTitle = 'Login';
        require __DIR__ . '/pages/login.php';
        break;
    case 'register':
        $pageTitle = 'Register';
        require __DIR__ . '/pages/register.php';
        break;
    case 'forgot-password':
        $pageTitle = 'Forgot Password';
        require __DIR__ . '/pages/forgot-password.php';
        break;
    case 'reset-password':
        $pageTitle = 'Reset Password';
        require __DIR__ . '/pages/reset-password.php';
        break;
    case 'orders':
        $pageTitle = 'My Orders';
        require __DIR__ . '/pages/orders.php';
        break;
    case 'profile':
        $pageTitle = 'My Profile';
        require __DIR__ . '/pages/profile.php';
        break;
    case 'guest-order':
    case 'track-order':
        $pageTitle = 'Track Order';
        require __DIR__ . '/pages/guest-order.php';
        break;
    case 'admin':
        $pageTitle = 'Admin Dashboard';
        require __DIR__ . '/pages/admin.php';
        break;
    case 'developer':
        $pageTitle = 'Developer Dashboard';
        require __DIR__ . '/pages/developer.php';
        break;
    case 'about':
        $pageTitle = 'About Us';
        require __DIR__ . '/pages/about.php';
        break;
    case 'contact':
        $pageTitle = 'Contact Us';
        require __DIR__ . '/pages/contact.php';
        break;
    case 'faq':
        $pageTitle = 'Frequently Asked Questions';
        require __DIR__ . '/pages/faq.php';
        break;
    case 'thank-you':
        $pageTitle = 'Thank You For Your Order';
        require __DIR__ . '/pages/thank-you.php';
        break;
    case 'privacy':
        $pageTitle = 'Privacy Policy';
        require __DIR__ . '/pages/privacy.php';
        break;
    case 'cookies':
        $pageTitle = 'Cookie Policy';
        require __DIR__ . '/pages/cookies.php';
        break;
    case 'unsubscribe':
        $pageTitle = 'Unsubscribe';
        require __DIR__ . '/pages/unsubscribe.php';
        break;
    case 'refund':
        $pageTitle = 'Refund Policy';
        require __DIR__ . '/pages/refund.php';
        break;
    case 'terms':
        $pageTitle = 'Terms & Conditions';
        require __DIR__ . '/pages/terms.php';
        break;
    case 'payment-verify':
    case 'payment/verify':
        $pageTitle = 'Payment Verification';
        require __DIR__ . '/pages/payment-verify.php';
        break;
    default:
        if (preg_match('#^product/(\d+)$#', $uri, $m)) {
            $_GET['id'] = $m[1];
            $pageTitle = 'Product Details';
            require __DIR__ . '/pages/product-detail.php';
        } else {
            http_response_code(404);
            $pageTitle = '404 Not Found';
            require __DIR__ . '/pages/404.php';
        }
        break;
}
