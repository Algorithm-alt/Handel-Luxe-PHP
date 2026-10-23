<?php
/* Customer profile: read and update your own account.
   Deliberately GET + PUT only — account deletion is not offered here. */

$method = $_SERVER['REQUEST_METHOD'];

requireAuth();

if (!in_array($method, ['GET', 'PUT'], true)) {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$db = getDB();
$userId = (int)$_SESSION['user']['id'];

if ($method === 'GET') {
    $stmt = $db->prepare("SELECT id, name, email, role, created_at FROM handel_users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) jsonResponse(['error' => 'User not found'], 404);
    jsonResponse($row);
}

$input = getInput();
$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$currentPw = (string)($input['current_password'] ?? '');
$newPw = (string)($input['new_password'] ?? '');

if (!$name) jsonResponse(['error' => 'Name is required'], 400);
if (strlen($name) > 100) jsonResponse(['error' => 'Name must be 100 characters or fewer'], 400);
if (!$email || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Please provide a valid email address'], 400);
}
if (strlen($currentPw) > 72 || strlen($newPw) > 72) {
    jsonResponse(['error' => 'Password is too long'], 400);
}

$stmt = $db->prepare("SELECT * FROM handel_users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) jsonResponse(['error' => 'User not found'], 404);

$emailChanged = strcasecmp((string)$user['email'], $email) !== 0;
$passwordChange = $newPw !== '';

// Email is the login identifier, so a change to it (or to the password) must
// be confirmed with the current password.
if (($emailChanged || $passwordChange) && $currentPw === '') {
    jsonResponse(['error' => 'Enter your current password to change your email or password'], 400);
}
if ($currentPw !== '' && !password_verify($currentPw, $user['password'])) {
    jsonResponse(['error' => 'Current password is incorrect'], 400);
}
if ($passwordChange) {
    if (strlen($newPw) < 8) jsonResponse(['error' => 'New password must be at least 8 characters'], 400);
    if ($newPw === $currentPw) jsonResponse(['error' => 'New password must be different from your current password'], 400);
}

if ($emailChanged) {
    // Reject an email already used by another account before updating, so the
    // unique index never fires and the session never switches to an email that
    // was not saved.
    $stmt = $db->prepare("SELECT id FROM handel_users WHERE email = ? AND id != ?");
    $stmt->bind_param('si', $email, $userId);
    $stmt->execute();
    $taken = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($taken) jsonResponse(['error' => 'That email is already in use by another account'], 400);
}

if ($passwordChange) {
    $hash = password_hash($newPw, PASSWORD_BCRYPT);
    $stmt = $db->prepare("UPDATE handel_users SET name = ?, email = ?, password = ? WHERE id = ?");
    $stmt->bind_param('sssi', $name, $email, $hash, $userId);
} else {
    $stmt = $db->prepare("UPDATE handel_users SET name = ?, email = ? WHERE id = ?");
    $stmt->bind_param('ssi', $name, $email, $userId);
}
try {
    $stmt->execute();
} catch (mysqli_sql_exception $e) {
    $stmt->close();
    if ((int)$e->getCode() === 1062) jsonResponse(['error' => 'That email is already in use by another account'], 400);
    jsonResponse(['error' => 'Could not update your profile'], 500);
}
$stmt->close();

$_SESSION['user']['name'] = $name;
$_SESSION['user']['email'] = $email;

auditCurrentActor('profile.updated', [
    'email_changed' => $emailChanged,
    'password_changed' => $passwordChange,
]);

jsonResponse([
    'success' => true,
    'user' => ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => $user['role']],
]);
