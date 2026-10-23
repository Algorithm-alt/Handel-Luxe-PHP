<?php
$method = $_SERVER['REQUEST_METHOD'];
$sub = str_replace('auth/', '', $_GET['route'] ?? '');

switch ($sub) {
    case 'register':
        if (!checkRateLimit('register_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 900)) {
            jsonResponse(['error' => 'Too many attempts, please try again later'], 429);
        }
        handleRegister();
        break;
    case 'login':
        if (!checkRateLimit('login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 900)) {
            jsonResponse(['error' => 'Too many attempts, please try again later'], 429);
        }
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'me':
        handleMe();
        break;
    case 'forgot-password':
        if (!checkRateLimit('forgot_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 3600)) {
            jsonResponse(['error' => 'Too many reset requests, please try again later'], 429);
        }
        handleForgotPassword();
        break;
    case 'reset-password':
        if (!checkRateLimit('resetpw_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 900)) {
            jsonResponse(['error' => 'Too many attempts, please try again later'], 429);
        }
        handleResetPassword();
        break;
    default:
        jsonResponse(['error' => 'Not found'], 404);
}

function handleRegister() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Invalid method'], 405);
    $input = getInput();
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $consent = !empty($input['consent']);

    if (!$name || !$email || !$password) jsonResponse(['error' => 'All fields are required'], 400);
    if (!$consent) jsonResponse(['error' => 'Please accept the Terms and Privacy Policy'], 400);
    if (strlen($name) > 100) jsonResponse(['error' => 'Name must be 100 characters or fewer'], 400);
    if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'Please provide a valid email address'], 400);
    if (strlen($password) < 8 || strlen($password) > 72) jsonResponse(['error' => 'Password must be between 8 and 72 characters'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM handel_users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        jsonResponse(['error' => 'Email already registered'], 400);
    }
    $stmt->close();

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $role = 'customer';
    $stmt = $db->prepare("INSERT INTO handel_users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $name, $email, $hash, $role);
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        if ((int)$e->getCode() === 1062) jsonResponse(['error' => 'Email already registered'], 400);
        jsonResponse(['error' => 'Could not create account'], 500);
    }
    $userId = $stmt->insert_id;
    $stmt->close();

    mergeGuestCart($userId);

    // Fresh session id on privilege change (prevents session fixation)
    session_regenerate_id(true);

    $_SESSION['user'] = ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => $role];
    jsonResponse(['success' => true, 'user' => $_SESSION['user']]);
}

function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Invalid method'], 405);
    $input = getInput();
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM handel_users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) jsonResponse(['error' => 'Invalid credentials'], 400);

    $user = $result->fetch_assoc();
    if (!password_verify($password, $user['password'])) {
        jsonResponse(['error' => 'Invalid credentials'], 400);
    }

    // Fresh session id on privilege change (prevents session fixation)
    session_regenerate_id(true);

    $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
    // A visitor who filled a cart before signing in keeps it, exactly as the
    // registration path already does.
    mergeGuestCart((int)$user['id']);
    jsonResponse(['success' => true, 'user' => $_SESSION['user']]);
}

function handleLogout() {
    session_destroy();
    jsonResponse(['success' => true]);
}

function handleMe() {
    requireAuth();
    jsonResponse($_SESSION['user']);
}

function handleForgotPassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Invalid method'], 405);
    $input = getInput();
    $email = trim($input['email'] ?? '');

    if (!$email || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Please provide a valid email address'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, name FROM handel_users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    // Same response whether or not the account exists — prevents
    // harvesting registered email addresses. It never echoes the submitted
    // address, so the response cannot be used as an HTML/JS injection sink.
    $genericResponse = ['success' => true, 'message' => 'If an account exists for that email address, a password reset link has been sent. It expires in 1 hour. Please check your inbox and spam folder.'];

    if ($result->num_rows === 0) {
        jsonResponse($genericResponse);
    }

    $user = $result->fetch_assoc();
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);

    // A new request invalidates earlier tokens for the same account (and
    // purges expired rows) — only one live reset link at a time.
    $stmt = $db->prepare("UPDATE handel_password_resets SET used = 1 WHERE user_id = ? AND used = 0");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $stmt->close();
    $stmt = $db->prepare("DELETE FROM handel_password_resets WHERE expires_at < NOW()");
    $stmt->execute();
    $stmt->close();

    // Store only the SHA-256 of the token: a database leak must not
    // expose live reset links. The raw token travels solely by email.
    $tokenHash = hash('sha256', $token);
    $stmt = $db->prepare("INSERT INTO handel_password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $user['id'], $tokenHash, $expires);
    $stmt->execute();
    $stmt->close();

    $resetUrl = base_url('/reset-password?token=' . $token);
    $firstName = explode(' ', trim($user['name'] ?: ''))[0];

    // Logo embedded in the email (cid:) so it renders without a public site
    $inlineImages = ['logo' => __DIR__ . '/../public/images/logo.png'];
    $logoTag = '<img src="cid:logo" alt="Handel Luxe GH" style="height:40px;max-width:180px;margin:0 auto 10px;display:block;">';

    // Branded to match the site: blue/amber palette, Segoe UI, rounded cards
    $html = '<div style="font-family:\'Segoe UI\',Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#f1f5f9;border-radius:12px;">
        <div style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
            <div style="background:#1e40af;background:linear-gradient(135deg,#2563eb 0%,#1e40af 100%);padding:30px;text-align:center;">
                ' . $logoTag . '
                <h1 style="color:#ffffff;margin:0;font-size:1.35rem;font-weight:700;">Password Reset</h1>
            </div>
            <div style="padding:30px;">
                <p style="color:#1e293b;font-size:15px;line-height:1.7;margin:0 0 14px;">Hello <strong>' . htmlspecialchars($firstName) . '</strong>,</p>
                <p style="color:#64748b;font-size:15px;line-height:1.7;margin:0 0 14px;">We received a request to reset the password for your Handel Luxe GH account. Click the button below to choose a new password.</p>
                <div style="text-align:center;margin:28px 0;">
                    <a href="' . $resetUrl . '" style="background:#2563eb;color:#ffffff;padding:13px 34px;text-decoration:none;border-radius:8px;font-weight:700;font-size:15px;display:inline-block;">Reset Password</a>
                </div>
                <p style="color:#64748b;font-size:13px;line-height:1.7;margin:0 0 14px;">This link expires in 1 hour and can only be used once. If you did not request a password reset, you can safely ignore this email and your password will not change.</p>
                <p style="color:#64748b;font-size:13px;line-height:1.7;margin:0;">If the button does not work, paste this link into your browser:<br><a href="' . $resetUrl . '" style="color:#2563eb;word-break:break-all;">' . $resetUrl . '</a></p>
            </div>
            <div style="background:#f1f5f9;padding:16px 30px;text-align:center;">
                <p style="color:#64748b;font-size:12px;line-height:1.7;margin:0;">Handel Luxe GH: Your premier destination for authentic perfumes and fragrances in Ghana<br>0542332244 | 0549986486</p>
            </div>
        </div>
    </div>';

    if (!sendMail($email, 'Password Reset - Handel Luxe GH', $html, $inlineImages)) {
        // Deliberately no address and no token: a log must not become a second
        // copy of the reset link. The API still returns the same generic reply.
        error_log('Mail: password reset email could not be delivered');
    }

    jsonResponse($genericResponse);
}

function handleResetPassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Invalid method'], 405);
    $input = getInput();
    $token = $input['token'] ?? '';
    $password = $input['password'] ?? '';

    if (!$token || !$password) jsonResponse(['error' => 'Token and password are required'], 400);
    if (strlen($password) < 8 || strlen($password) > 72) jsonResponse(['error' => 'Password must be between 8 and 72 characters'], 400);

    $db = getDB();
    $db->begin_transaction();
    try {
        // Tokens are stored hashed — look up by SHA-256 of the submitted
        // value. FOR UPDATE serialises two requests racing with the same
        // token, so only the first can consume it.
        $stmt = $db->prepare("SELECT id, user_id, expires_at, used FROM handel_password_resets WHERE token = ? FOR UPDATE");
        $tokenHash = hash('sha256', $token);
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows === 0) {
            $db->rollback();
            jsonResponse(['error' => 'Invalid reset token'], 400);
        }

        $reset = $result->fetch_assoc();
        if ($reset['used']) {
            $db->rollback();
            jsonResponse(['error' => 'Reset token has already been used'], 400);
        }
        if (strtotime($reset['expires_at']) < time()) {
            $db->rollback();
            jsonResponse(['error' => 'Reset token has expired'], 400);
        }

        // Consume the token conditionally before changing the password. The
        // row lock above plus this guard means a duplicate request can never
        // apply a second password change with the same link.
        $stmt = $db->prepare("UPDATE handel_password_resets SET used = 1 WHERE id = ? AND used = 0");
        $stmt->bind_param('i', $reset['id']);
        $stmt->execute();
        $consumed = $stmt->affected_rows;
        $stmt->close();
        if ($consumed !== 1) {
            $db->rollback();
            jsonResponse(['error' => 'Reset token has already been used'], 400);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE handel_users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hash, $reset['user_id']);
        $stmt->execute();
        $stmt->close();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        jsonResponse(['error' => 'Could not reset the password'], 500);
    }

    jsonResponse(['success' => true, 'message' => 'Password reset successful']);
}

function mergeGuestCart($userId) {
    if (empty($_SESSION['guest_token'])) return;
    $token = $_SESSION['guest_token'];
    $db = getDB();

    $stmt = $db->prepare("SELECT product_id, quantity FROM handel_cart WHERE guest_token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    while ($gi = $result->fetch_assoc()) {
        $check = $db->prepare("SELECT * FROM handel_cart WHERE user_id = ? AND product_id = ?");
        $check->bind_param('ii', $userId, $gi['product_id']);
        $check->execute();
        $existing = $check->get_result();
        $check->close();

        if ($existing->num_rows > 0) {
            $update = $db->prepare("UPDATE handel_cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?");
            $update->bind_param('iii', $gi['quantity'], $userId, $gi['product_id']);
            $update->execute();
            $update->close();
        } else {
            $ins = $db->prepare("INSERT INTO handel_cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $ins->bind_param('iii', $userId, $gi['product_id'], $gi['quantity']);
            $ins->execute();
            $ins->close();
        }
    }

    $del = $db->prepare("DELETE FROM handel_cart WHERE guest_token = ?");
    $del->bind_param('s', $token);
    $del->execute();
    $del->close();
}
