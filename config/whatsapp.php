<?php
/**
 * Admin WhatsApp order notifications.
 *
 * WHY A PROVIDER IS NEEDED: click-to-chat links (wa.me / wa.link — what the
 * floating site button uses) can only OPEN a chat window with a pre-typed
 * message; they cannot send anything by themselves, and WhatsApp has no
 * free API for pushing messages to a personal number. Automatic
 * notifications therefore go through a messaging provider — fill in ONE of
 * the provider blocks in config/secrets.php to activate.
 *
 * Until a provider is configured, notifyAdminsWhatsApp() returns silently
 * and order placement is never affected.
 */

require_once __DIR__ . '/app.php';
requireSecrets();

/* Admin numbers and provider credentials both live in config/secrets.php:
   WHATSAPP_ADMIN_NUMBERS (international format, no "+": Ghana is 233, so
   0542332244 becomes 233542332244) plus ONE filled-in provider block.

   Provider 1, Twilio: console.twilio.com -> Account SID and Auth Token, and
   a WhatsApp-enabled sender shaped like 'whatsapp:+15551234567'.
   https://www.twilio.com/docs/whatsapp/quickstart/php

   Provider 2, Meta WhatsApp Cloud API: developers.facebook.com -> Phone
   number ID and a permanent system-user access token. Business-initiated
   messages must use a pre-approved template.
   https://developers.facebook.com/docs/whatsapp/cloud-api

   The defaults below exist only so this file stays loadable, and the module
   stays inert, when neither the environment nor secrets.php supplies a
   value: whatsappProviderReady() returns '' and notifyAdminsWhatsApp()
   returns false without touching anything, so order placement is never
   affected. */
if (!defined('WHATSAPP_ADMIN_NUMBERS')) define('WHATSAPP_ADMIN_NUMBERS', env_list_or('WHATSAPP_ADMIN_NUMBERS', []));
if (!defined('TWILIO_WHATSAPP_SID'))    define('TWILIO_WHATSAPP_SID', env_or('TWILIO_WHATSAPP_SID', ''));
if (!defined('TWILIO_WHATSAPP_TOKEN'))  define('TWILIO_WHATSAPP_TOKEN', env_or('TWILIO_WHATSAPP_TOKEN', ''));
if (!defined('TWILIO_WHATSAPP_FROM'))   define('TWILIO_WHATSAPP_FROM', env_or('TWILIO_WHATSAPP_FROM', ''));
if (!defined('WA_CLOUD_TOKEN'))         define('WA_CLOUD_TOKEN', env_or('WA_CLOUD_TOKEN', ''));
if (!defined('WA_CLOUD_PHONE_ID'))      define('WA_CLOUD_PHONE_ID', env_or('WA_CLOUD_PHONE_ID', ''));

function whatsappProviderReady() {
    if (TWILIO_WHATSAPP_SID && TWILIO_WHATSAPP_TOKEN && TWILIO_WHATSAPP_FROM) return 'twilio';
    if (WA_CLOUD_TOKEN && WA_CLOUD_PHONE_ID) return 'cloud';
    return '';
}

/* Providers fetch image URLs server-side, so media only works once the
 * site is reachable from the internet (custom domain). Localhost URLs
 * are skipped automatically. */
function whatsappMediaAllowed($url) {
    return (bool) $url
        && strpos($url, 'localhost') === false
        && strpos($url, '127.0.0.1') === false;
}

/* Send one WhatsApp message to $to (international format, no "+"). */
function sendWhatsAppMessage($to, $text, $imageUrl = '') {
    $provider = whatsappProviderReady();
    if (!$provider) return false;

    if ($provider === 'twilio') {
        $post = [
            'To' => 'whatsapp+' . $to,
            'From' => TWILIO_WHATSAPP_FROM,
            'Body' => $text,
        ];
        if (whatsappMediaAllowed($imageUrl)) $post['MediaUrl'] = [$imageUrl];
        $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_WHATSAPP_SID . '/Messages.json');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post),
            CURLOPT_USERPWD => TWILIO_WHATSAPP_SID . ':' . TWILIO_WHATSAPP_TOKEN,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    // Meta WhatsApp Cloud API
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'text',
        'text' => ['body' => $text],
    ];
    if (whatsappMediaAllowed($imageUrl)) {
        $payload['type'] = 'image';
        $payload['image'] = ['link' => $imageUrl];
    }
    $ch = curl_init('https://graph.facebook.com/v20.0/' . WA_CLOUD_PHONE_ID . '/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . WA_CLOUD_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

/**
 * Notify every admin number about a placed order. Called after the order
 * is safely committed — a notification failure must never break or delay
 * the order itself, so everything is wrapped.
 */
function notifyAdminsWhatsApp($orderId) {
    if (!whatsappProviderReady()) return false;
    try {
        $db = getDB();

        $stmt = $db->prepare(
            "SELECT o.*, COALESCE(o.guest_name, u.name) AS customer_name,
                    COALESCE(o.guest_email, u.email) AS customer_email
             FROM handel_orders o
             LEFT JOIN handel_users u ON u.id = o.user_id
             WHERE o.id = ?"
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$order) return false;

        $stmt = $db->prepare(
            "SELECT oi.quantity, oi.price, COALESCE(p.name, oi.product_name, 'Deleted product') AS name, p.image
             FROM handel_order_items oi
             LEFT JOIN handel_products p ON p.id = oi.product_id
             WHERE oi.order_id = ?"
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $zone = lookupZone($order['delivery_zone']);
        $zoneLabel = (isset($zone['category']) ? zoneCategoryLabel($zone['category']) . ': ' : '') . ($zone['label'] ?? $order['delivery_zone']);
        $subtotal = $order['total'] - $order['shipping_fee'];
        $paymentLabel = $order['payment_method'] === 'delivery'
            ? 'Pay on Delivery (' . $order['payment_status'] . ')'
            : 'Paystack (' . $order['payment_status'] . ')';

        $cedi = 'GH' . "\u{20B5}";
        $lines = [
            "*NEW ORDER #{$order['id']}*",
            "Payment: {$paymentLabel}",
            '',
            '*Customer*',
            $order['customer_name'],
            $order['customer_email'],
            $order['phone'],
            '',
            '*Delivery*',
            $zoneLabel,
            $order['shipping_address'],
            '',
            '*Items*',
        ];
        foreach ($items as $i) {
            $lines[] = "{$i['quantity']}x {$i['name']} @ {$cedi}" . number_format($i['price'], 2);
        }
        $lines[] = '';
        $lines[] = 'Subtotal: ' . $cedi . number_format($subtotal, 2);
        $lines[] = 'Delivery Fee: ' . $cedi . number_format($order['shipping_fee'], 2);
        $lines[] = '*Total: ' . $cedi . number_format($order['total'], 2) . '*';
        $text = implode("\n", $lines);

        // First product photo attached when the site is publicly reachable
        $imageUrl = !empty($items[0]['image']) ? image_url($items[0]['image']) : '';

        $sent = 0;
        foreach (WHATSAPP_ADMIN_NUMBERS as $to) {
            if (sendWhatsAppMessage($to, $text, $imageUrl)) $sent++;
        }
        return $sent > 0;
    } catch (Throwable $e) {
        return false;
    }
}
