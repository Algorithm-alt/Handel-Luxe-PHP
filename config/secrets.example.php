<?php
/**
 * TEMPLATE — copy this file to config/secrets.php and fill in real values:
 *
 *     cp config/secrets.example.php config/secrets.php
 *
 * config/secrets.php is gitignored and must never be committed, zipped into
 * a code share, or copied to another machine. It covers everything the app
 * needs in order to run — the database, Paystack, SMTP mail, WhatsApp and
 * the installer key — so nothing else in this project needs editing to
 * point at your credentials.
 *
 * Every value below is wrapped in env_or() / env_list_or(), which prefer a
 * real environment variable of the same name over what is written in the
 * file. Two consequences worth knowing:
 *
 *   1. A host that injects credentials — Docker, a PaaS, an Apache SetEnv
 *      or a php-fpm pool — can run this project with NO secrets.php on
 *      disk at all. The empty placeholders below are harmless there.
 *   2. On such a host, rotating a credential means restarting the service,
 *      not editing a file on the server.
 *
 * Precedence is environment > this file > built-in default. See env_or() in
 * config/app.php for the syntax of each injection method.
 *
 * No environment access at all (bare XAMPP, shared hosting)? Copy
 * config/.env.example to config/.env instead: config/app.php loads that file
 * into the environment before this one runs, so only the values that differ
 * on the machine need listing there. It is gitignored and HTTP-denied like
 * this file.
 *
 * Generate a fresh SETUP_KEY with:
 *     php -r "echo bin2hex(random_bytes(24));"
 */

/* --- Paystack (dashboard -> Settings -> API Keys & Webhooks) ---------- */
define('PAYSTACK_SECRET_KEY', env_or('PAYSTACK_SECRET_KEY', ''));   // sk_live_... or sk_test_...
define('PAYSTACK_PUBLIC_KEY', env_or('PAYSTACK_PUBLIC_KEY', ''));   // pk_live_... or pk_test_...

/* --- Outgoing mail (Gmail SMTP with an App Password) ------------------
 * The password must be a Google App Password, not the account password.
 * https://myaccount.google.com/apppasswords                              */
define('EMAIL_HOST', env_or('EMAIL_HOST', 'smtp.gmail.com'));
define('EMAIL_PORT', env_or('EMAIL_PORT', 587));
define('EMAIL_USER', env_or('EMAIL_USER', ''));            // full Gmail address
define('EMAIL_PASS', env_or('EMAIL_PASS', ''));            // 16-character app password
define('EMAIL_TO_ADMIN', env_or('EMAIL_TO_ADMIN', ''));    // where contact-form alerts go

/* --- Database ---------------------------------------------------------
 * The values below match this project's local XAMPP setup (MySQL on port
 * 1471, root, no password), so a straight copy of this file runs as-is on
 * a development machine. Change them for production.
 *
 * DB_HOST, DB_USER and DB_NAME must be filled in: config/db.php refuses to
 * connect while any of them is empty, because mysqli reads an empty host as
 * "localhost" and an empty user as "the current OS user" — which would mean
 * connecting to the wrong database with the wrong privileges. DB_PASS may
 * legitimately be empty (XAMPP root has no password).                    */
define('DB_HOST', env_or('DB_HOST', '127.0.0.1'));
define('DB_PORT', env_or('DB_PORT', '1471'));
define('DB_USER', env_or('DB_USER', 'root'));
define('DB_PASS', env_or('DB_PASS', ''));
define('DB_NAME', env_or('DB_NAME', 'handel_luxe_db'));

/* --- Separate developer dashboard ------------------------------------
 * These credentials are independent of customer/admin accounts and never
 * appear in the store's users table. Generate a password hash with:
 *     php -r "echo password_hash('a-long-unique-password', PASSWORD_DEFAULT);"
 * Set both values before using /developer. Leaving either empty disables
 * developer dashboard login.                                     */
define('DEVELOPER_DASH_USERNAME', env_or('DEVELOPER_DASH_USERNAME', ''));
define('DEVELOPER_DASH_PASSWORD_HASH', env_or('DEVELOPER_DASH_PASSWORD_HASH', ''));

/* --- WhatsApp order notifications (see config/whatsapp.php) -----------
 * Numbers that receive every order, international format without "+"
 * (Ghana is 233, so 0542332244 becomes 233542332244). Filling in either
 * provider block activates automatic notifications. In the environment it
 * is a comma-separated list:
 *     WHATSAPP_ADMIN_NUMBERS=233542332244,233549986486                    */
define('WHATSAPP_ADMIN_NUMBERS', env_list_or('WHATSAPP_ADMIN_NUMBERS', []));

/* Provider 1: Twilio — console.twilio.com, the Account SID and Auth Token,
 * plus a WhatsApp-enabled sender shaped like 'whatsapp:+15551234567'.    */
define('TWILIO_WHATSAPP_SID',   env_or('TWILIO_WHATSAPP_SID', ''));
define('TWILIO_WHATSAPP_TOKEN', env_or('TWILIO_WHATSAPP_TOKEN', ''));
define('TWILIO_WHATSAPP_FROM',  env_or('TWILIO_WHATSAPP_FROM', ''));

/* Provider 2: Meta WhatsApp Cloud API — developers.facebook.com, the Phone
 * number ID and a permanent system-user access token.                    */
define('WA_CLOUD_TOKEN',    env_or('WA_CLOUD_TOKEN', ''));
define('WA_CLOUD_PHONE_ID', env_or('WA_CLOUD_PHONE_ID', ''));

/* --- Installer key (see setup.php) ------------------------------------
 * Leave empty to disable the installer completely. Even with a key set,
 * setup.php demands a setup-allow.txt file on disk, so a key leaked
 * through a URL or access log cannot wipe the database by itself.        */
define('SETUP_KEY', env_or('SETUP_KEY', ''));
