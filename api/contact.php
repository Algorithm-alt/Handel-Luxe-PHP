<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Contact submissions email the admin — rate limit the spam vector.
    if (!checkRateLimit('contact_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 3600)) {
        jsonResponse(['error' => 'Too many messages, please try again later'], 429);
    }
    $input = getInput();
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $subject = trim($input['subject'] ?? '');
    $message = trim($input['message'] ?? '');
    $consent = ($input['consent'] ?? false) === true;

    if (!$name || !$email || !$subject || !$message) {
        jsonResponse(['error' => 'All fields are required'], 400);
    }
    if (!$consent) {
        jsonResponse(['error' => 'Please agree to the Privacy Policy before sending your message'], 400);
    }
    if (strlen($name) > 100) jsonResponse(['error' => 'Name must be 100 characters or fewer'], 400);
    if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Please provide a valid email address'], 400);
    }
    if (strlen($subject) > 200) jsonResponse(['error' => 'Subject must be 200 characters or fewer'], 400);
    if (strlen($message) > 5000) jsonResponse(['error' => 'Message must be 5000 characters or fewer'], 400);

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO handel_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $name, $email, $subject, $message);
    $stmt->execute();
    $stmt->close();

    $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
        <h2 style="color:#2563eb;border-bottom:2px solid #2563eb;padding-bottom:10px;">New Message from Contact Form</h2>
        <p><strong>Name:</strong> ' . htmlspecialchars($name) . '</p>
        <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
        <p><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</p>
        <hr style="border-color:#e2e8f0;">
        <p style="line-height:1.8;">' . nl2br(htmlspecialchars($message)) . '</p>
        <hr style="border-color:#e2e8f0;">
        <p style="color:#64748b;font-size:0.85rem;">Sent from Handel Luxe GH website contact form</p>
    </div>';

    sendMail(EMAIL_TO_ADMIN, "New Contact Message: $subject", $html);

    jsonResponse(['success' => true]);
} else {
    jsonResponse(['error' => 'Invalid method'], 405);
}
