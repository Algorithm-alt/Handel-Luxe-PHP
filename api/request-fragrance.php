<?php
/**
 * "Request a Fragrance" — validate the short form and return a prefilled
 * WhatsApp deep link (https://wa.me/<number>?text=<message>).
 *
 * Nothing is stored. There is no table, migration, request log or admin view:
 * the WhatsApp chat is the only record of a request, and because the site
 * cannot confirm the message was actually sent, no status is recorded as if it
 * had been. The only state is a small per-session counter used as a light spam
 * guard (5 submissions per rolling hour), which is intentionally not a hard
 * security control — see the task brief.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Invalid method'], 405);
}

$limit = 5;
$window = 3600;

$input = getInput();
$title  = trim((string)($input['fragrance_title'] ?? ''));
$brand  = trim((string)($input['brand'] ?? ''));
$budget = trim((string)($input['budget_size'] ?? ''));
$name   = trim((string)($input['customer_name'] ?? ''));

// Server-side validation is the only validation that counts. A value that is
// only whitespace trims to '' and is treated as missing.
$errors = [];
if ($title === '') {
    $errors['fragrance_title'] = 'Please enter the perfume title.';
} elseif (strlen($title) > 150) {
    $errors['fragrance_title'] = 'Perfume title must be 150 characters or fewer.';
}

if (strlen($brand) > 100) {
    $errors['brand'] = 'Brand must be 100 characters or fewer.';
}

if (strlen($budget) > 100) {
    $errors['budget_size'] = 'Target budget / size must be 100 characters or fewer.';
}

if ($name === '') {
    $errors['customer_name'] = 'Please enter your name.';
} elseif (strlen($name) > 100) {
    $errors['customer_name'] = 'Name must be 100 characters or fewer.';
}

if ($errors) {
    jsonResponse(['error' => 'Please correct the highlighted fields.', 'errors' => $errors], 400);
}

// Session rate limit: count valid submissions in a rolling one-hour window and
// remember the time of the first one. A fresh window starts once the first is
// more than an hour old.
$now = time();
$state = $_SESSION['fragrance_request_rl'] ?? null;
if (!is_array($state)
    || !isset($state['first'], $state['count'])
    || ($now - (int)$state['first']) >= $window) {
    $state = ['first' => $now, 'count' => 0];
}
if ((int)$state['count'] >= $limit) {
    jsonResponse(['error' => 'You have sent several requests. Please try again later.'], 429);
}
$state['count'] = (int)$state['count'] + 1;
$_SESSION['fragrance_request_rl'] = $state;

// Optional fields fall back to "Not specified" in the message only; the raw
// trimmed value is used for the required fields so the WhatsApp text reads
// correctly.
$brandForMessage  = $brand === '' ? 'Not specified' : $brand;
$budgetForMessage = $budget === '' ? 'Not specified' : $budget;

$message = "Exclusive Request | Handel Luxe GH\n\n"
    . "Hello Team,\n\n"
    . "I am looking for a specific scent to add to my collection:\n"
    . "• Perfume Title: {$title}\n"
    . "• Brand: {$brandForMessage}\n"
    . "• Target Budget / Size: {$budgetForMessage}\n"
    . "• Client Name: {$name}\n\n"
    . "Please update me on sourcing availability. Thank you!";

// Build the wa.me deep link server-side. The number comes from config/app.php
// (WHATSAPP_SHOP_NUMBER), never from page markup, and is reduced to digits so
// a stray "+", space or dash cannot break the link. rawurlencode keeps the line
// breaks, bullets and the GH₵ cedi sign intact through the query string.
$number = preg_replace('/\D+/', '', (string)WHATSAPP_SHOP_NUMBER);
$whatsappUrl = 'https://wa.me/' . $number . '?text=' . rawurlencode($message);

jsonResponse([
    'success' => true,
    'message' => $message,
    'whatsapp_url' => $whatsappUrl,
    'remaining' => $limit - (int)$state['count'],
]);
