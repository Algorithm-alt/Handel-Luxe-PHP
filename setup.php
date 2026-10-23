<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
]);

require_once __DIR__ . '/config/app.php';
requireSecrets();

/* SETUP_KEY normally comes from config/secrets.php; the environment can
   supply it instead (see env_or() in config/app.php), which is how the
   installer stays usable on a host where no secrets file was deployed. */
if (!defined('SETUP_KEY')) define('SETUP_KEY', env_or('SETUP_KEY', ''));

/* Security gate. setup.php DROPs and reseeds every handel_* table, so it
   takes TWO independent factors to run:
     1. SETUP_KEY — held in config/secrets.php, never in this file. Passed
        as ?key=... .
     2. setup-allow.txt — a file in this folder containing the word 'allow',
        which proves write access to the server's filesystem.
   Both are required, so a key leaked through a URL, an Apache access log or
   browser history is inert on its own: the attacker would also need to
   create a file on disk. Delete setup-allow.txt once setup finishes, and
   delete setup.php from any deployed server. */
$allowFile = __DIR__ . '/setup-allow.txt';

if (!defined('SETUP_KEY') || SETUP_KEY === '') {
    http_response_code(403);
    exit("Setup is disabled: set SETUP_KEY in config/secrets.php (see config/secrets.example.php).\n");
}

$keyOk  = isset($_GET['key']) && is_string($_GET['key']) && hash_equals(SETUP_KEY, $_GET['key']);
/* Compare the marker leniently. Windows shells do not write a bare word:
   PowerShell's `echo allow > setup-allow.txt` emits a UTF-8 BOM and CRLF
   (EF BB BF 61 6C 6C 6F 77 0D 0A), so a strict trim() === 'allow' never
   matches and the installer could never be unlocked. Strip everything that
   is not a letter before comparing — that also absorbs a UTF-16 file or a
   trailing newline. The file's *existence* is the real second factor; the
   word itself is only a deliberate-intent marker, so leniency costs nothing. */
$fileOk = is_file($allowFile)
    && strtolower(preg_replace('/[^a-z]/i', '', (string)file_get_contents($allowFile))) === 'allow';

if (!($keyOk && $fileOk)) {
    http_response_code(403);
    exit("Setup is locked. Both steps are required:\n"
       . "  1. Create a file named setup-allow.txt in this folder containing the word: allow\n"
       . "  2. Open setup.php?key=<SETUP_KEY>   (SETUP_KEY lives in config/secrets.php)\n"
       . "Delete setup-allow.txt when you are done.\n");
}

/* PHP version. Checked first, because nothing below means much if it is wrong.

   The floor enforced here is 8.1, which is what DEPLOY.md and the project
   report now state, and it is deliberately higher than the version the syntax
   needs. The code uses arrow functions (7.4) and no 8.x-only syntax, so 8.0
   would parse and, against a healthy database, would run. What breaks is
   error handling: the database helpers rely on mysqli reporting a failed
   query as an exception, which became the default in 8.1. Before that the
   same failure returns false, and the next call on that false is an uncaught
   Error rather than a catchable Exception, so the fallbacks the code intends
   never run and a recoverable database problem becomes a fatal 500.

   That is why this stops rather than warns, unlike the GD check below. GD
   costs one admin feature and the shop still runs. A version below the floor
   passes every test you would think to run and then fails only while
   something else is already going wrong, which is the worst kind of fault to
   trace back to its cause. */
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit("Setup cannot run: PHP 8.1 or newer is required, and this is PHP " . PHP_VERSION . ".\n"
       . "\n"
       . "The database helpers rely on mysqli reporting a failed query as an exception,\n"
       . "which became the default in 8.1. On older versions a database problem that the\n"
       . "code means to recover from becomes a fatal error instead.\n"
       . "\n"
       . "Upgrade PHP. The Requirements section in DEPLOY.md has the full list.\n");
}

echo "Checking PHP version...\n";
echo "  PHP " . PHP_VERSION . ", 8.1 or newer required\n\n";

/* Image uploads need GD, and a missing GD is easy to miss: the upload form,
   the extension allowlist and the magic-byte test all pass without it, because
   those live in PHP core rather than GD, so only the upload itself fails and
   the error ends up pointing at the file. Report it here, before any table is
   dropped, instead of leaving the admin to find out from their first product
   photo. */
echo "Checking image support...\n";

$gdOk = extension_loaded('gd');
if ($gdOk) {
    echo "  GD extension: loaded\n";
    echo (function_exists('imagewebp') && (imagetypes() & IMG_WEBP))
        ? "  WebP encoding: available, uploads are written as .webp\n"
        : "  WebP encoding: not in this GD build, uploads fall back to JPEG\n";
    echo function_exists('exif_read_data')
        ? "  EXIF orientation: available, phone photos are rotated upright\n"
        : "  EXIF orientation: not available, portrait photos may arrive sideways\n";
} else {
    /* Warn and carry on rather than stopping. The storefront, checkout, orders
       and payments never touch GD, and a fresh install ships its product
       images as static files under /images/products, so the site is perfectly
       usable without it. Only admin uploads need it, which is not enough to
       justify refusing to install. */
    echo "\n";
    echo "  WARNING: the GD extension is not loaded.\n";
    echo "\n";
    echo "  Product image uploads will fail. Everything else (storefront,\n";
    echo "  checkout, orders, payments) is unaffected, so the shop still looks\n";
    echo "  correct until someone uploads a photo in the admin.\n";
    echo "\n";
    echo "  To fix: uncomment \";extension=gd\" in php.ini, then restart Apache.\n";
    echo "  To verify: php -m | grep -i gd\n";
}
echo "\n";

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/delivery.php';
require_once __DIR__ . '/config/paystack.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/config/images.php';
require_once __DIR__ . '/config/audit.php';

echo "Creating database...\n";
/* dbConfig() is the fail-closed guard in front of the DB_* constants. Going
   straight to the constants here would let an empty host or user fall back
   to "localhost, current OS user" and create the schema on a database this
   installer was never pointed at. */
$cfg = dbConfig();

$mysqli = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], '', $cfg['port']);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}

$mysqli->query("CREATE DATABASE IF NOT EXISTS `" . $cfg['name'] . "`");
$mysqli->select_db($cfg['name']);
$mysqli->close();

$db = getDB();

echo "Dropping old tables...\n";
$db->query("SET FOREIGN_KEY_CHECKS = 0");
// handel_subscribers is included so a re-run is idempotent: its CREATE below
// has no IF NOT EXISTS, so a leftover table would abort the script.
$tables = ['handel_rate_limits', 'handel_audit_logs', 'handel_password_resets', 'handel_product_images', 'handel_order_items', 'handel_orders', 'handel_cart', 'handel_products', 'handel_categories', 'handel_messages', 'handel_users', 'handel_delivery_zones', 'handel_subscribers'];
foreach ($tables as $t) {
    $db->query("DROP TABLE IF EXISTS `$t`");
}
$db->query("SET FOREIGN_KEY_CHECKS = 1");

echo "Creating tables...\n";

$db->query("CREATE TABLE handel_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer','admin') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$db->query("CREATE TABLE handel_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
)");

$db->query("CREATE TABLE handel_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category_id INT,
    stock INT DEFAULT 0,
    image VARCHAR(500) DEFAULT '/images/placeholder.png',
    featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES handel_categories(id) ON DELETE SET NULL
)");

$db->query("CREATE TABLE handel_cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    guest_token VARCHAR(100),
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    UNIQUE KEY unique_cart_item (user_id, product_id),
    UNIQUE KEY unique_cart_guest (guest_token, product_id),
    FOREIGN KEY (user_id) REFERENCES handel_users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES handel_products(id) ON DELETE CASCADE
)");

$db->query("CREATE TABLE handel_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    guest_name VARCHAR(100),
    guest_email VARCHAR(100),
    total DECIMAL(10,2) NOT NULL,
    shipping_address TEXT NOT NULL,
    shipping_fee DECIMAL(10,2) NULL,
    delivery_zone VARCHAR(50) NULL,
    phone VARCHAR(20),
    status ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    payment_method ENUM('delivery','paystack') DEFAULT 'delivery',
    payment_ref VARCHAR(100),
    payment_status ENUM('pending','success','failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES handel_users(id) ON DELETE SET NULL
)");

$db->query("CREATE TABLE handel_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(200) NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES handel_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES handel_products(id) ON DELETE SET NULL
)");

$db->query("CREATE TABLE handel_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$db->query("CREATE TABLE handel_password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES handel_users(id) ON DELETE CASCADE
)");

$db->query("CREATE TABLE handel_delivery_zones (
    id VARCHAR(50) PRIMARY KEY,
    label VARCHAR(120) NOT NULL,
    fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    eta VARCHAR(120),
    category VARCHAR(20) NOT NULL DEFAULT 'delivery'
)");

$db->query("CREATE TABLE handel_product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product_sort (product_id, sort_order),
    FOREIGN KEY (product_id) REFERENCES handel_products(id) ON DELETE CASCADE
)");

$db->query("CREATE TABLE handel_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    consent_at DATETIME NULL,
    consent_version VARCHAR(32) NULL,
    unsubscribe_token VARCHAR(64) NULL,
    unsubscribed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_subscriber_token (unsubscribe_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->query("CREATE TABLE IF NOT EXISTS handel_rate_limits (
    `key` VARCHAR(255) PRIMARY KEY,
    attempts INT NOT NULL DEFAULT 1,
    window_start INT NOT NULL
)");
ensureAuditLogTable();

echo "Seeding data...\n";

$adminHash = password_hash('admin123', PASSWORD_BCRYPT);
$userHash = password_hash('password123', PASSWORD_BCRYPT);

$stmt = $db->prepare("INSERT INTO handel_users (name, email, password, role) VALUES (?, ?, ?, ?)");
$name = 'Admin'; $email = 'admin@handelluxe.com'; $role = 'admin';
$stmt->bind_param('ssss', $name, $email, $adminHash, $role);
$stmt->execute();
$stmt->close();

$stmt = $db->prepare("INSERT INTO handel_users (name, email, password, role) VALUES (?, ?, ?, ?)");
$name = 'Kwame Mensah'; $email = 'kwame@test.com'; $role = 'customer';
$stmt->bind_param('ssss', $name, $email, $userHash, $role);
$stmt->execute();
$stmt->close();

$stmt = $db->prepare("INSERT INTO handel_users (name, email, password, role) VALUES (?, ?, ?, ?)");
$name = 'Akosua Owusu'; $email = 'akosua@test.com'; $role = 'customer';
$stmt->bind_param('ssss', $name, $email, $userHash, $role);
$stmt->execute();
$stmt->close();

$categories = [
    ['Men\'s Fragrances', 'Signature fragrances crafted for the modern gentleman'],
    ['Women\'s Fragrances', 'Elegant scents that define femininity and grace'],
    ['Unisex Fragrances', 'Bold scents that transcend gender boundaries'],
    ['Body Mists & Sprays', 'Light and refreshing everyday fragrances'],
    ['Gift Sets', 'Curated collections for that perfect gift'],
    ['Deodorants & Roll-Ons', 'Reliable all-day freshness and protection'],
];

$stmt = $db->prepare("INSERT INTO handel_categories (name, description) VALUES (?, ?)");
foreach ($categories as $i => $cat) {
    $stmt->bind_param('ss', $cat[0], $cat[1]);
    $stmt->execute();
}
$stmt->close();

$products = [
    ['Premium Inspired 50ml Eau De Parfum Collection', 'An expansive array of premium, designer-inspired 50ml Eau De Parfums (GHc40 each) presenting a versatile spectrum of scent families ranging from rich fruity-sweet gourmands and elegant pink florals to commanding dark ouds and fresh marine aromatics.', 40.00, 3, 25, '/images/products/set1.png', 1],
    ['Premium Inspired Eau De Parfum Collection', 'An expansive collection of designer-inspired 100ml Eau De Parfums (GHc40 each) featuring a broad spectrum of premium fragrance families ranging from icy fresh aquatics and crisp citruses to warm exotic ouds and cozy coffee gourmands.', 40.00, 3, 25, '/images/products/set.png', 1],
    ['Touch by Gandour Concentrated Fragrance Series', 'The pocket-sized Gandour Touch Series features the Yellow Edition, Pink Edition, and Red Edition - a collection of concentrated, alcohol-free perfumes offering an array of crisp, sweet floral, and warm spicy profiles.', 10.00, 3, 30, '/images/products/touch.jpg', 0],
    ['Lattafa Pride Luxury Discovery Gift Set', 'An opulent Lattafa Pride Discovery Gift Set featuring five premium, travel-friendly Eau De Parfum bottles that showcase a versatile spectrum of rich gourmands, fresh aquatics, luxurious florals, dark leathers, and smooth woody notes.', 320.00, 5, 10, '/images/products/gift.jpg', 1],
    ['Zara Man Eau De Parfum', 'A powerful Zara Man Eau De Parfum (100ml) offering an intense, long-lasting fragrance profile of crisp citrus, warm spices, and deep woody amber for ultimate masculine confidence and alluring charm.', 140.00, 1, 20, '/images/products/zara.jpg', 1],
    ['Oniro Eau De Parfum', 'An invigorating Oniro Eau De Parfum (100ml) offering a fresh, long-lasting fragrance profile of crisp marine notes, vibrant citrus, and deep woody amber for ultimate daily confidence and energetic charm.', 140.00, 1, 20, '/images/products/oniro.jpg', 1],
    ['Extremely Unique Pista Eau De Parfum', 'A delightfully indulgent Extremely Unique Pista Eau De Parfum offering a rich, long-lasting gourmand fragrance profile of creamy roasted pistachio, sweet vanilla, and smooth almond.', 140.00, 3, 15, '/images/products/uniqu1.jpg', 1],
    ['Fragrance Avenue Suger Fragrance Series', 'The vibrant Fragrance Avenue Suger Series brings together Suger Candy, Suger Pink Intense, Suger Blend, and Suger White - a chic, color-coded collection of custom-made designer perfumes.', 160.00, 3, 20, '/images/products/sugar.jpg', 0],
    ['Lattafa Badee Al Oud Prestige Series', 'The majestic Lattafa Badee Al Oud Series (100ml Eau De Parfum) brings together Oud For Glory (Matte Black) and Amethyst (Matte Purple) - two high-performance, long-lasting unisex fragrances.', 280.00, 3, 15, '/images/products/badee.png', 1],
    ['La Maison Du Parfum Rococo Eau De Parfum', 'An opulent La Maison Du Parfum Rococo Eau De Parfum offering a rich, long-lasting unisex fragrance profile of warm exotic spices, deep precious woods, and luxurious gold-standard amber.', 140.00, 3, 18, '/images/products/rococo.jpg', 1],
    ['Perfumers Choice by Milton-Lloyd Luxury Fragrance Series', 'An opulent array of Perfumers Choice Eau De Parfums (83ml each) delivering high-concentration, long-lasting luxury fragrance profiles across a premium spectrum of fresh florals, rich spices, smoky woods, and exotic oud.', 130.00, 3, 20, '/images/products/perfumers-choice.jpg', 1],
    ['Savage Elixir Series (Prince Dior)', 'Savage Elixir Pure Red: A bold, fiery blend featuring cinnamon, nutmeg, cardamom, lavender, and smoky vetiver. Savage Elixir Green: An ultra-modern, crisp interpretation with wild mint, grapefruit, and cedarwood.', 140.00, 1, 18, '/images/products/savage.jpg', 1],
    ['Proud Of You Tobacco Eau De Parfum', 'An indulgent Proud Of You Tobacco Eau De Parfum offering a rich, long-lasting fragrance profile of warm spices, premium tobacco accords, and sweet amber vanilla.', 140.00, 1, 15, '/images/products/proud.jpg', 0],
    ['Bodycology Fragrance Mist Assortment', 'A vibrant array of Bodycology Fragrance Mists (237ml each) offering sweet gourmand, rich fruit, and fresh floral scents including Pure White Gardenia, Whipped Vanilla, Beach Berries, and Strawberry Cheesecake.', 70.00, 2, 25, '/images/products/splash.jpg', 0],
    ['Lattafa Khamrah Qahwa Eau De Parfum', 'An indulgent Lattafa Khamrah Qahwa Eau De Parfum offering a rich, long-lasting gourmand fragrance profile of warm cinnamon, roasted Arabic coffee, and sweet praline.', 450.00, 1, 8, '/images/products/khamrah-lattafa.jpg', 1],
    ['Fragrance World Lush Cherry Eau De Parfum', 'A seductive Fragrance World Lush Cherry Eau De Parfum (50ml) offering a rich, long-lasting sweet profile of dark cherry liqueur, bitter almond, and warm tonka bean.', 40.00, 2, 20, '/images/products/cherry.jpg', 0],
    ['Fragrance World Fiero Bleu Man Eau De Parfum', 'A magnetic Fragrance World Fiero Bleu Man Eau De Parfum (50ml) offering a fresh, long-lasting masculine fragrance profile of crisp citrus, aromatic lavender, and rich woody amber.', 40.00, 1, 22, '/images/products/fiero.jpg', 0],
    ['Smart Collection 15ml Pocket Perfume Series', 'This collection features sleek 15ml tear-drop shaped glass bottles with unique numbered labeling, each recreating a high-end designer scent profile. Perfect for travelers and fragrance enthusiasts.', 20.00, 2, 30, '/images/products/smart-collection.jpg', 0],
    ['Fragrance World Berry Weekend Perfumed Body Spray', 'An enchanting Fragrance World Berry Weekend Perfumed Body Spray (200ml) offering a fresh, long-lasting profile of sweet wild berries, soft florals, and clean musk.', 35.00, 2, 25, '/images/products/berry-weekend-body-spray.jpg', 0],
    ['Fragrance World Kaly Oudh Eau De Parfum', 'An opulent Fragrance World Kaly Oudh Eau De Parfum (50ml) offering a rich, long-lasting unisex fragrance profile of exotic saffron, dark agarwood (oud), and warm resinous amber.', 140.00, 3, 15, '/images/products/kaly.jpg', 1],
    ['Fragrance World Pegasus Pour Homme Eau De Parfum', 'An opulent Fragrance World Pegasus Pour Homme Eau De Parfum (50ml) offering a rich, long-lasting masculine fragrance profile of unique bitter almond, elegant lavender, and warm, creamy vanilla.', 40.00, 1, 20, '/images/products/pegasus.jpg', 0],
    ['Fragrance World Just Azraq Eau De Parfum', 'An exquisite Fragrance World Just Azraq Eau De Parfum (50ml) offering a fresh, long-lasting unisex fragrance profile of crisp marine notes, aromatic lavender, and smooth cedar-amber.', 40.00, 3, 20, '/images/products/just.jpg', 0],
    ['Fragrance World Toomford Pour Homme Eau De Parfum', 'An opulent Fragrance World Toomford Pour Homme Eau De Parfum (50ml) offering a rich, long-lasting masculine fragrance profile of exotic cardamon, precious agarwood (oud), and warm amber-vanilla.', 40.00, 1, 20, '/images/products/toomford.jpg', 0],
    ['Fragrance World Cocktail Intense Eau De Parfum', 'An opulent Fragrance World Cocktail Intense Eau De Parfum (50ml) offering a rich, long-lasting unisex fragrance profile of warm cognac, sweet cinnamon-tonka, and decadent praline-vanilla.', 40.00, 3, 18, '/images/products/cocktail.jpg', 0],
    ['Fragrance World Barakkat Rouge 540 Extrait De Parfum', 'An opulent Fragrance World Barakkat Rouge 540 Extrait De Parfum (50ml) offering an intense, long-lasting unisex fragrance profile of rich saffron, bitter almond, and precious ambergris-woods.', 40.00, 3, 20, '/images/products/barakkat2.jpg', 1],
    ['Fragrance World Barakkat Satin Oud Eau De Parfum', 'An opulent Fragrance World Barakkat Satin Oud Eau De Parfum (50ml) offering a rich, long-lasting unisex fragrance profile of romantic roses, powdery violet, dark agarwood, and warm vanilla.', 40.00, 3, 20, '/images/products/barakkat1.jpg', 1],
    ['Fragrance World La Vida Es Bella Eau De Parfum', 'An enchanting Fragrance World La Vida Es Bella Eau De Parfum (50ml) offering a rich, long-lasting feminine fragrance profile of sweet pear, precious iris, and warm praline-vanilla.', 40.00, 2, 18, '/images/products/la-vida.jpg', 0],
    ['Fragrance World Bad Lad Pour Homme Eau De Parfum', 'An exceptional Fragrance World Bad Lad Pour Homme Eau De Parfum (50ml) offering an intense, long-lasting masculine fragrance profile of sharp peppers, dark cacao, and warm tonka bean.', 40.00, 1, 20, '/images/products/bad-lad.jpg', 0],
    ['Fragrance World Rose Seduction Secret Eau De Parfum', 'An enchanting Fragrance World Rose Seduction Secret Eau De Parfum (50ml) offering a sweet, long-lasting feminine fragrance profile of fresh passion fruit, romantic peony, and soft vanilla orchid.', 40.00, 2, 18, '/images/products/rose-seduction.jpg', 0],
    ['Fragrance World Black Leather Men Eau De Parfum', 'An exquisite Fragrance World Black Leather Men Eau De Parfum (50ml) offering a rich, long-lasting masculine fragrance profile of smoky birch, fresh pineapple, and deep oakmoss.', 40.00, 1, 20, '/images/products/black-leather.jpg', 0],
    ['Fragrance World Intense Peach Eau De Parfum', 'An exquisite Fragrance World Intense Peach Eau De Parfum (50ml) offering a rich, long-lasting unisex fragrance profile of velvety peach, warm boozy notes, and deep patchouli-woods.', 40.00, 3, 18, '/images/products/intense-peach.jpg', 0],
    ['Belle Celine Scandant Women Le Parfum', 'An exquisite Belle Celine Scandant Women Le Parfum (50ml) offering a rich, long-lasting feminine fragrance profile of vibrant citruses, creamy honey, and warm caramel.', 40.00, 2, 18, '/images/products/scandant.jpg', 0],
    ['Yara Tous Eau De Parfum (35ml Mini Spray) Collection', 'An exquisite Yara Tous Eau De Parfum (35ml Mini Spray) offering a luscious, long-lasting tropical fragrance profile of sweet mango, coconut, and smooth vanilla.', 10.00, 2, 30, '/images/products/mini.jpg', 0],
    ['Efolia Oud For Nights Eau De Parfum', 'An opulent Efolia Oud For Nights Eau De Parfum (100ml) offering a rich, long-lasting oriental fragrance profile of precious agarwood, warm spices, and deep amber.', 220.00, 3, 12, '/images/products/oud-for-night.jpg', 1],
    ['Efolia After 12 Pour Homme Eau De Parfum', 'An exquisite Efolia After 12 Pour Homme Eau De Parfum (100ml) offering a rich, long-lasting masculine fragrance profile of fresh spices, warm aromatics, and deep woods.', 160.00, 1, 15, '/images/products/after-12.jpg', 1],
    ['Ramz Lattafa (Silver/Black Edition) Eau De Parfum', 'An exquisite Ramz Lattafa Eau De Parfum (100ml) offering a rich, long-lasting masculine fragrance profile of fresh fruits, aromatic lavender, and warm vanilla.', 230.00, 1, 12, '/images/products/ramz-lattafa1.jpg', 1],
    ['Ramz Lattafa (Gold/Red Edition) Eau De Parfum', 'An exquisite Ramz Lattafa Eau De Parfum (100ml) offering a rich, long-lasting fragrance profile of sweet fruits, warm vanilla, and smooth amber.', 230.00, 3, 12, '/images/products/ramz-lattafa.jpg', 1],
    ['Invicto Victorious Elixir Eau De Parfum', 'An opulent Invicto Victorious Elixir Eau De Parfum (100ml) offering an intense, long-lasting masculine fragrance profile of aromatics, rich spices, and smooth amber-woods.', 140.00, 1, 18, '/images/products/invicto.jpg', 1],
    ['Suave Eau De Parfum (100ml)', 'An exquisite Suave Eau De Parfum (100ml) offering a rich, long-lasting masculine fragrance profile of fresh citrus, sharp spices, and deep woody notes, featuring a bonus free deodorant spray.', 140.00, 1, 18, '/images/products/suave.jpg', 0],
    ['Aventos Blue For Him Eau De Parfum (100ml)', 'An exquisite Aventos Blue For Him Eau De Parfum (100ml) offering a refreshing, long-lasting masculine fragrance profile of crisp citrus, marine accords, and subtle woods.', 140.00, 1, 20, '/images/products/aventos.jpg', 1],
    ['Aventos Advance Eau De Parfum Collection', 'A magnificent collection of four distinct Aventos Advance Eau De Parfums (50ml each) offering an elite range of long-lasting, luxurious fragrances for ultimate daily sophistication.', 60.00, 3, 15, '/images/products/aventos-for-all.jpg', 0],
    ['Aventos Blue For Him Eau De Parfum (50ml)', 'An exquisite Aventos Blue For Him Eau De Parfum (50ml) offering a refreshing, long-lasting masculine fragrance profile of crisp citrus, marine accords, and subtle woods.', 40.00, 1, 20, '/images/products/aventos1.jpg', 0],
    ['Lattafa Hayaati Eau De Parfum', 'A high-energy, sweet, and clean fragrance that smells like an upscale fruity shower gel. It hits you with a juicy burst of fresh apples and citrus, smoothly warming into cinnamon and vanilla.', 160.00, 3, 15, '/images/products/hayaati.jpg', 1],
    ['Matelot Eau De Parfum', 'An evocative Matelot Eau De Parfum offering a refreshing, long-lasting maritime fragrance profile of citrus, ocean spray, and subtle musk, presented with a signature nautical pouch.', 140.00, 3, 15, '/images/products/matelot.jpg', 0],
    ['Lattafa Badee Al Oud Amethyst Eau De Parfum', 'An opulent Lattafa Badee Al Oud Amethyst Eau De Parfum offering a rich, prestigious, and deeply luxurious fragrance profile for an all-day aura of elite elegance and confidence.', 280.00, 3, 10, '/images/products/amethyst.jpg', 1],
    ['Mousuf Eau De Parfum', 'A stunning collection of Mousuf Eau De Parfums featuring three distinct, long-lasting fragrances presented with custom, elegant woven gift pouches.', 180.00, 3, 12, '/images/products/mousuf.jpg', 1],
    ['Extremely Unique Eau De Parfum', 'An exquisite Extremely Unique Eau De Parfum (100ml) offering a rich, captivating, and deeply luxurious fragrance profile for an all-day aura of distinctive elegance.', 140.00, 3, 18, '/images/products/unique.jpg', 0],
    ['Maison Barakkat Rouge 540 Extrait De Parfum', 'An opulent and intensely mesmerizing Maison Barakkat Rouge 540 Extrait De Parfum offering an ultra-premium, long-lasting, and deeply luxurious fragrance profile.', 140.00, 3, 18, '/images/products/barakkat.jpg', 1],
    ['Berries Weekend Pink Edition Eau De Parfum', 'An exquisite Berries Weekend Pink Edition Eau De Parfum (100ml) offering a sweet, vibrant, and deeply luxurious fragrance profile for an all-day aura of playful elegance.', 140.00, 2, 15, '/images/products/berry-weekend.jpg', 0],
    ['Ophylia Eau De Parfum', 'An exquisite Ophylia Eau De Parfum featuring a premium, long-lasting feminine fragrance with green mandarin, water jasmine, creamy vanilla, sea salt, and cashmere wood. Includes bonus free deodorant spray.', 140.00, 2, 15, '/images/products/ophylia.jpg', 0],
    ['Montera Rouge Tobacco Eau De Parfum', 'An opulent and intensely captivating Montera Rouge Tobacco Eau De Parfum (100ml) offering a rich, warm, and deeply luxurious fragrance profile for ultimate daily confidence.', 140.00, 3, 15, '/images/products/tobacco.jpg', 0],
    ['BCC Country Road Roll-On', 'A classic BCC Country Road roll-on deodorant delivering up to 48 hours of reliable anti-perspirant protection and crisp freshness.', 16.00, 6, 40, '/images/products/roll-on2.jpeg', 0],
    ['Power House Tungsten Strength Roll-On', 'An athletic-grade Power House Tungsten Strength roll-on deodorant providing up to 48 hours of targeted odor control and high-endurance sweat protection.', 23.00, 6, 35, '/images/products/roll-on3.jpeg', 0],
    ['My Dollar Roll-On', 'A uniquely styled My Dollar roll-on deodorant providing up to 48 hours of reliable anti-perspirant protection and long-lasting freshness.', 16.00, 6, 40, '/images/products/roll-on.jpeg', 0],
    ['Rexona Roll-On', 'A high-performance collection of Rexona roll-on deodorants offering up to 72 hours of advanced sweat and odor protection.', 25.00, 6, 40, '/images/products/rexona-roll-on.jpg', 0],
    ['Dove Roll-On', 'A nourishing collection of Dove roll-on deodorants offering up to 48-hour sweat and odor protection, infused with moisturizing care.', 25.00, 6, 40, '/images/products/roll-on1.jpeg', 0],
    ['Sure Men Deodorant Spray', 'A powerful collection of Sure Men body sprays delivering up to 48 hours of heavy-duty, quick-drying anti-perspirant protection for active men.', 40.00, 6, 25, '/images/products/rexona.jpeg', 0],
    ['Nivea Deodorant Spray', 'An essential collection of Nivea body sprays delivering up to 48 hours of reliable, quick-drying anti-perspirant protection tailored for long-lasting daily freshness.', 55.00, 6, 30, '/images/products/nivea.jpeg', 0],
    ['Nivea Roll-On', 'A versatile collection of Nivea roll-on deodorants offering up to 48-hour sweat and odor protection, tailored to deliver specialized skin care.', 25.00, 6, 35, '/images/products/nivea-roll-on.jpg', 0],
    ['Rexona Deodorant', 'A premium collection of high-performance Rexona body sprays providing up to 72 hours of advanced motion-activated sweat and odor protection.', 35.00, 6, 30, '/images/products/rexona.jpeg', 0],
    ['Dove Deodorant Spray', 'The ultimate clean aesthetic in a bottle. It smells like pure, velvety lotion, crisp white soap, and freshly laundered linens - comforting, subtle, and universally pleasant.', 37.00, 6, 30, '/images/products/dove.jpeg', 0],
];

$stmt = $db->prepare("INSERT INTO handel_products (name, description, price, category_id, stock, image, featured) VALUES (?, ?, ?, ?, ?, ?, ?)");
foreach ($products as $p) {
    $stmt->bind_param('ssdiisi', $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6]);
    $stmt->execute();
}
$stmt->close();

/* Seed each product's gallery from its single image column. These ship as
   static files under /images/products, so they are referenced in place —
   nothing is copied or re-encoded. The admin can add compressed uploads on
   top of them (up to GALLERY_MAX_IMAGES per product). */
echo "Seeding product galleries...\n";
$db->query("INSERT INTO handel_product_images (product_id, image, sort_order) SELECT id, image, 0 FROM handel_products WHERE image IS NOT NULL AND image != ''");

ensureZones();

echo "\nSetup complete!\n";
echo "Admin login: admin@handelluxe.com / admin123\n";
echo "Customer login: kwame@test.com / password123\n";
/* Repeated at the tail because this is where people actually look after a
   long install, and the warning scrolled past at the top. */
if (!$gdOk) {
    echo "\nREMINDER: GD is not loaded, so product image uploads in the admin will fail.\n";
    echo "The fix is in the warning at the top of this output.\n";
}
echo "\nNOW DO THIS: delete setup-allow.txt, and delete setup.php entirely on any deployed server.\n";
