<?php
require_once __DIR__ . '/app.php';
requireSecrets();

/* SMTP credentials and the admin address live in config/secrets.php — or in
   the environment, which takes precedence. The defaults below keep this file
   usable when neither supplies a value: sendMail() then falls through to
   PHP's mail(), exactly as before. */
if (!defined('EMAIL_HOST'))     define('EMAIL_HOST', env_or('EMAIL_HOST', 'smtp.gmail.com'));
if (!defined('EMAIL_PORT'))     define('EMAIL_PORT', env_or('EMAIL_PORT', 587));
if (!defined('EMAIL_USER'))     define('EMAIL_USER', env_or('EMAIL_USER', ''));
if (!defined('EMAIL_PASS'))     define('EMAIL_PASS', env_or('EMAIL_PASS', ''));
if (!defined('EMAIL_TO_ADMIN')) define('EMAIL_TO_ADMIN', env_or('EMAIL_TO_ADMIN', ''));
/* Derived, so the display name always tracks EMAIL_USER. */
if (!defined('EMAIL_FROM'))     define('EMAIL_FROM', 'Handel Luxe GH <' . EMAIL_USER . '>');

/**
 * Send an HTML email. Uses authenticated SMTP over STARTTLS (same transport
 * as the original nodemailer setup); falls back to PHP mail() if SMTP fails.
 * $inlineImages maps a content id (e.g. 'logo') to an image file path on
 * disk; reference them from the HTML as <img src="cid:logo">. Embedding
 * beats linking for local-hosted sites because Gmail blocks remote images
 * it cannot fetch.
 */
function sendMail($to, $subject, $html, $inlineImages = []) {
    if (EMAIL_USER && EMAIL_PASS) {
        try {
            return smtpSend(EMAIL_HOST, (int)EMAIL_PORT, EMAIL_USER, EMAIL_PASS, EMAIL_FROM, $to, $subject, $html, $inlineImages);
        } catch (Throwable $e) {
            /* Log the stage/reply code only. smtpSend() deliberately omits
               commands, credentials, recipients and message content from its
               exception text. */
            error_log('Mail: SMTP delivery failed, falling back to PHP mail(): ' . $e->getMessage());
        }
    }
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . EMAIL_FROM . "\r\n";
    $headers .= "Reply-To: " . $to . "\r\n";
    $safeSubject = str_replace(["\r", "\n"], ' ', $subject);
    $safeTo = str_replace(["\r", "\n", " "], '', $to);
    return mail($safeTo, $safeSubject, $html, $headers);
}

function smtpSend($host, $port, $user, $pass, $from, $to, $subject, $html, $inlineImages = []) {
    /* Header injection guard: a CRLF smuggled into a header field splits
       the message and can add recipients/headers. Strip line breaks from
       anything interpolated into SMTP headers. */
    $subject = str_replace(["\r", "\n"], ' ', $subject);
    $to = str_replace(["\r", "\n", " "], '', $to);
    $user = str_replace(["\r", "\n", " "], '', $user);

    $socket = @fsockopen($host, $port, $errno, $errstr, 15);
    if (!$socket) {
        throw new Exception("SMTP connect failed: $errstr");
    }
    stream_set_timeout($socket, 15);

    $read = function () use ($socket) {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // final line of a multi-line reply
        }
        return $data;
    };
    /* $stage is what goes into the exception on failure. Never interpolate
       the command itself: the AUTH LOGIN exchange contains the base64-encoded
       password, and MAIL/RCPT contain addresses. Only the three-digit reply
       code is retained, which is enough to diagnose without leaking data. */
    $cmd = function ($command, $stage) use ($socket, $read) {
        fwrite($socket, $command . "\r\n");
        $resp = $read();
        if ($resp === '' || ($resp[0] !== '2' && $resp[0] !== '3')) {
            $code = preg_match('/^\d{3}/', $resp, $m) ? $m[0] : 'unknown';
            throw new Exception("SMTP error during $stage (reply code $code)");
        }
        return $resp;
    };

    // EHLO takes a hostname, never a request header. The canonical site host
    // is stable and cannot be poisoned by a client-supplied Host value.
    $hostname = parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost';
    $read(); // server banner
    $cmd('EHLO ' . $hostname, 'EHLO');
    $cmd('STARTTLS', 'STARTTLS');
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        throw new Exception('TLS negotiation failed');
    }
    $cmd('EHLO ' . $hostname, 'EHLO after STARTTLS');
    $cmd('AUTH LOGIN', 'AUTH LOGIN');
    $cmd(base64_encode($user), 'AUTH username');
    $cmd(base64_encode($pass), 'AUTH password');
    $cmd('MAIL FROM:<' . $user . '>', 'MAIL FROM');
    $cmd('RCPT TO:<' . $to . '>', 'RCPT TO');
    $cmd('DATA', 'DATA');

    $headers = 'From: ' . $from . "\r\n";
    $headers .= 'To: <' . $to . ">\r\n";
    $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";
    $headers .= 'Message-ID: <' . uniqid('handel', true) . "@handel-luxe>\r\n";

    if ($inlineImages) {
        // multipart/related: HTML body + embedded images referenced via cid:
        $boundary = 'hlx' . bin2hex(random_bytes(10));
        $headers .= "Content-Type: multipart/related; boundary=\"$boundary\"\r\n";

        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($html)) . "\r\n";

        foreach ($inlineImages as $cid => $path) {
            if (!is_file($path)) continue;
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'][$ext] ?? 'application/octet-stream';
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: $mime; name=\"" . basename($path) . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-ID: <$cid>\r\n";
            $body .= "Content-Disposition: inline; filename=\"" . basename($path) . "\"\r\n\r\n";
            $body .= chunk_split(base64_encode(file_get_contents($path))) . "\r\n";
        }
        $body .= "--$boundary--\r\n";
    } else {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";

        /* Base64-wrapping the body: SMTP-safe CRLF line endings, no leading
           dots to stuff, no line-length limits, and an unambiguous blank line
           separating headers from body. Sending raw HTML with bare \n line
           endings made some receiving servers swallow the body into the
           header block (message arrived empty). */
        $body = chunk_split(base64_encode($html));
    }

    fwrite($socket, $headers . "\r\n" . $body . ".\r\n");
    $resp = $read();
    if ($resp === '' || $resp[0] !== '2') {
        $code = preg_match('/^\d{3}/', $resp, $m) ? $m[0] : 'unknown';
        throw new Exception("SMTP error during DATA (reply code $code)");
    }
    /* The message was accepted at this point. A QUIT handshake failure must
       not throw: sendMail() would then fall back to mail() and deliver the
       same message a second time. Close best-effort and report success. */
    @fwrite($socket, "QUIT\r\n");
    fclose($socket);
    return true;
}

/**
 * Email the admin a summary of a placed order, including the product photos
 * as inline attachments (guests/users are never redirected to WhatsApp).
 * Called after the order is safely committed. Returns false when SMTP/mail
 * delivery fails; the order itself is never affected either way.
 */
function notifyAdminOrderEmail($orderId) {
    if (!EMAIL_TO_ADMIN) return false;
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
        $esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

        // Product photos embedded as inline attachments (cid:) — a remote
        // URL would be blocked by Gmail on a localhost site it cannot fetch.
        $imageCids = [];
        $thumbHtml = function ($path) use (&$imageCids) {
            if (!$path || strpos($path, '/uploads/') !== 0) return '';
            $file = imagesUploadDir() . '/' . basename($path);
            if (!is_file($file)) return '';
            static $n = 0;
            $cid = 'order-img-' . (++$n);
            $imageCids[$cid] = $file;
            return '<img src="cid:' . $cid . '" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:6px;display:block;margin:0 auto;">';
        };

        $itemRows = '';
        foreach ($items as $i) {
            $itemRows .= '<tr style="border-bottom:1px solid #e2e8f0;">'
                . '<td style="padding:10px 8px;text-align:center;">' . $thumbHtml($i['image'] ?? '') . '</td>'
                . '<td style="padding:10px 8px;">' . $esc($i['name']) . ' &times; ' . (int)$i['quantity'] . '</td>'
                . '<td style="padding:10px 8px;text-align:right;">' . $cedi . number_format($i['price'], 2) . '</td>'
                . '<td style="padding:10px 8px;text-align:right;font-weight:600;">' . $cedi . number_format($i['price'] * $i['quantity'], 2) . '</td>'
                . '</tr>';
        }
        if (!$itemRows) $itemRows = '<tr><td colspan="4" style="padding:10px;color:#64748b;">No items recorded</td></tr>';

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:0 auto;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">'
            . '<div style="background:#1e293b;color:#fff;padding:18px 22px;">'
            . '<h2 style="margin:0;font-size:18px;">New Order #' . (int)$order['id'] . '</h2>'
            . '<p style="margin:4px 0 0;color:#94a3b8;font-size:13px;">' . $esc(APP_NAME) . '</p>'
            . '</div>'
            . '<div style="padding:22px;">'
            . '<p style="margin:0 0 16px;"><strong>Payment:</strong> ' . $esc($paymentLabel) . '</p>'
            . '<h3 style="margin:0 0 6px;font-size:14px;color:#334155;">Customer</h3>'
            . '<table style="width:100%;border-collapse:collapse;margin-bottom:18px;font-size:14px;color:#0f172a;">'
            . '<tr><td style="padding:4px 0;width:110px;color:#64748b;">Name</td><td>' . $esc($order['customer_name']) . '</td></tr>'
            . '<tr><td style="padding:4px 0;color:#64748b;">Email</td><td>' . $esc($order['customer_email']) . '</td></tr>'
            . '<tr><td style="padding:4px 0;color:#64748b;">Phone</td><td>' . $esc($order['phone']) . '</td></tr>'
            . '</table>'
            . '<h3 style="margin:0 0 6px;font-size:14px;color:#334155;">Delivery</h3>'
            . '<table style="width:100%;border-collapse:collapse;margin-bottom:18px;font-size:14px;color:#0f172a;">'
            . '<tr><td style="padding:4px 0;width:110px;color:#64748b;">Zone</td><td>' . $esc($zoneLabel) . '</td></tr>'
            . '<tr><td style="padding:4px 0;color:#64748b;">Address</td><td>' . $esc($order['shipping_address']) . '</td></tr>'
            . '</table>'
            . '<h3 style="margin:0 0 8px;font-size:14px;color:#334155;">Items</h3>'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px;color:#0f172a;">'
            . '<tr style="background:#f8fafc;color:#64748b;font-size:12px;text-transform:uppercase;">'
            . '<th style="padding:8px;text-align:center;width:70px;">Image</th>'
            . '<th style="padding:8px;text-align:left;">Item</th>'
            . '<th style="padding:8px;text-align:right;">Price</th>'
            . '<th style="padding:8px;text-align:right;">Total</th></tr>'
            . $itemRows
            . '</table>'
            . '<table style="width:100%;border-collapse:collapse;margin-top:14px;font-size:14px;color:#0f172a;">'
            . '<tr><td style="padding:6px 8px;color:#64748b;text-align:right;">Subtotal</td><td style="padding:6px 8px;text-align:right;">' . $cedi . number_format($subtotal, 2) . '</td></tr>'
            . '<tr><td style="padding:6px 8px;color:#64748b;text-align:right;">Delivery Fee</td><td style="padding:6px 8px;text-align:right;">' . $cedi . number_format($order['shipping_fee'], 2) . '</td></tr>'
            . '<tr><td style="padding:8px;font-weight:700;text-align:right;">Total</td><td style="padding:8px;font-weight:700;text-align:right;">' . $cedi . number_format($order['total'], 2) . '</td></tr>'
            . '</table>'
            . '<p style="margin:18px 0 0;color:#64748b;font-size:13px;">'
            . 'View this order in the <a href="' . base_url('/admin') . '" style="color:#2563eb;">admin dashboard</a>.</p>'
            . '</div></div>';

        return sendMail(EMAIL_TO_ADMIN, 'New Order #' . $order['id'] . ' from ' . APP_NAME, $html, $imageCids);
    } catch (Throwable $e) {
        // Order id only: no customer PII, addresses or message content.
        error_log('Mail: admin order notification failed for order #' . (int)$orderId);
        return false;
    }
}
