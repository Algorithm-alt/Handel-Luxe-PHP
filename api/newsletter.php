<?php
// Public newsletter signup: POST /api/newsletter/subscribe
$sub = str_replace('newsletter/', '', $_GET['route'] ?? '');

if ($sub === 'subscribe' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkRateLimit('newsletter_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 3600)) {
        jsonResponse(['error' => 'Too many attempts, please try again later'], 429);
    }
    $input = getInput();
    $email = trim($input['email'] ?? '');
    $consent = ($input['consent'] ?? false) === true;

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Please enter a valid email address'], 400);
    }
    if (strlen($email) > 150) {
        jsonResponse(['error' => 'Email address must be 150 characters or fewer'], 400);
    }
    if (!$consent) {
        jsonResponse(['error' => 'Please agree to receive marketing emails and accept the Privacy Policy'], 400);
    }

    // Records consent_at/consent_version and an unsubscribe token. Idempotent:
    // subscribing twice refreshes consent and clears a previous unsubscribe.
    newsletterSubscribe($email);

    jsonResponse(['success' => true]);
} else {
    jsonResponse(['error' => 'Not found'], 404);
}
