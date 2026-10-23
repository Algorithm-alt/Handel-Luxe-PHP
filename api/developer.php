<?php
$sub = str_replace('developer/', '', $_GET['route'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];
header('Cache-Control: no-store, private');
header('Pragma: no-cache');

switch ($sub) {
    case 'login':
        if ($method !== 'POST') jsonResponse(['error' => 'Invalid method'], 405);
        if (!checkRateLimit('developer_login_' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 900)) {
            jsonResponse(['error' => 'Too many attempts, please try again later'], 429);
        }
        handleDeveloperLogin();
        break;
    case 'me':
        if ($method !== 'GET') jsonResponse(['error' => 'Invalid method'], 405);
        requireDeveloper();
        jsonResponse(['authenticated' => true]);
        break;
    case 'audit':
        if ($method !== 'GET') jsonResponse(['error' => 'Invalid method'], 405);
        requireDeveloper();
        jsonResponse(readAuditLogs());
        break;
    case 'profile':
        requireDeveloper();
        if ($method === 'GET') {
            handleDeveloperProfileRead();
        } elseif ($method === 'PUT') {
            handleDeveloperProfileUpdate();
        } else {
            jsonResponse(['error' => 'Invalid method'], 405);
        }
        break;
    case 'logout':
        if ($method !== 'POST') jsonResponse(['error' => 'Invalid method'], 405);
        requireDeveloper();
        auditCurrentActor('developer.logout');
        unset($_SESSION['developer_dashboard']);
        jsonResponse(['success' => true]);
        break;
    case 'backups/status':
        if ($method !== 'GET') jsonResponse(['error' => 'Invalid method'], 405);
        requireDeveloper();
        handleDeveloperBackupStatus();
        break;
    case 'backups/download':
        if ($method !== 'GET') jsonResponse(['error' => 'Invalid method'], 405);
        requireDeveloper();
        handleDeveloperBackupDownload();
        break;
    default:
        jsonResponse(['error' => 'Not found'], 404);
}

function requireDeveloper() {
    if (empty($_SESSION['developer_dashboard'])) {
        jsonResponse(['error' => 'Developer dashboard login required'], 401);
    }
}

function developerCredentials() {
    $db = getDB();
    $db->query("CREATE TABLE IF NOT EXISTS handel_developer_credentials (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $result = $db->query("SELECT username, password_hash FROM handel_developer_credentials WHERE id = 1");
    $credentials = $result->fetch_assoc();
    if ($credentials) return $credentials;

    $username = defined('DEVELOPER_DASH_USERNAME')
        ? (string)DEVELOPER_DASH_USERNAME
        : env_or('DEVELOPER_DASH_USERNAME', '');
    $passwordHash = defined('DEVELOPER_DASH_PASSWORD_HASH')
        ? (string)DEVELOPER_DASH_PASSWORD_HASH
        : env_or('DEVELOPER_DASH_PASSWORD_HASH', '');
    if ($username === '' || $passwordHash === '') return null;

    $stmt = $db->prepare("INSERT IGNORE INTO handel_developer_credentials (id, username, password_hash) VALUES (1, ?, ?)");
    $stmt->bind_param('ss', $username, $passwordHash);
    $stmt->execute();
    $stmt->close();
    $result = $db->query("SELECT username, password_hash FROM handel_developer_credentials WHERE id = 1");
    return $result->fetch_assoc() ?: null;
}

function handleDeveloperLogin() {
    requireSecrets();
    $credentials = developerCredentials();
    if (!$credentials) {
        jsonResponse(['error' => 'Developer dashboard credentials are not configured on this server'], 503);
    }

    $input = getInput();
    $submittedUsername = (string)($input['username'] ?? '');
    $password = (string)($input['password'] ?? '');
    if (!hash_equals($credentials['username'], $submittedUsername) || !password_verify($password, $credentials['password_hash'])) {
        writeAuditLog('developer.login_failed', 'anonymous', null, 'Unauthenticated');
        jsonResponse(['error' => 'Invalid credentials'], 400);
    }

    session_regenerate_id(true);
    $_SESSION['developer_dashboard'] = $credentials['username'];
    auditCurrentActor('developer.login');
    jsonResponse(['success' => true]);
}

function handleDeveloperProfileRead() {
    $credentials = developerCredentials();
    if (!$credentials) {
        jsonResponse(['error' => 'Developer dashboard credentials are not configured on this server'], 503);
    }
    jsonResponse(['username' => $credentials['username']]);
}

function handleDeveloperProfileUpdate() {
    $credentials = developerCredentials();
    if (!$credentials) {
        jsonResponse(['error' => 'Developer dashboard credentials are not configured on this server'], 503);
    }

    $input = getInput();
    $username = trim((string)($input['username'] ?? ''));
    $currentPassword = (string)($input['current_password'] ?? '');
    $newPassword = (string)($input['new_password'] ?? '');
    if (strlen($username) < 3 || strlen($username) > 100 || preg_match('/[\r\n]/', $username)) {
        jsonResponse(['error' => 'Username must be between 3 and 100 characters'], 400);
    }
    if (!password_verify($currentPassword, $credentials['password_hash'])) {
        jsonResponse(['error' => 'Current password is incorrect'], 400);
    }
    if ($newPassword !== '' && (strlen($newPassword) < 12 || strlen($newPassword) > 72)) {
        jsonResponse(['error' => 'New password must be between 12 and 72 characters'], 400);
    }

    $db = getDB();
    if ($newPassword !== '') {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE handel_developer_credentials SET username = ?, password_hash = ? WHERE id = 1");
        $stmt->bind_param('ss', $username, $passwordHash);
    } else {
        $stmt = $db->prepare("UPDATE handel_developer_credentials SET username = ? WHERE id = 1");
        $stmt->bind_param('s', $username);
    }
    $stmt->execute();
    $stmt->close();
    auditCurrentActor('developer.profile_updated', [
        'username_changed' => $username !== $credentials['username'],
        'password_changed' => $newPassword !== '',
    ]);
    $_SESSION['developer_dashboard'] = $username;
    jsonResponse(['success' => true, 'username' => $username]);
}

function developerBackupDirectory() {
    $configuredDir = trim(env_or('BACKUP_DIR', ''));
    if ($configuredDir === '') return [null, 'not_configured'];

    $backupDir = realpath($configuredDir);
    $appRoot = realpath(dirname(__DIR__));
    if ($backupDir === false || $appRoot === false) return [null, 'unavailable'];

    $appPrefix = rtrim($appRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $insideApp = DIRECTORY_SEPARATOR === '\\'
        ? strncasecmp($backupDir, $appPrefix, strlen($appPrefix)) === 0
        : strncmp($backupDir, $appPrefix, strlen($appPrefix)) === 0;
    if ($backupDir === $appRoot || $insideApp || !is_dir($backupDir) || !is_writable($backupDir)) {
        return [null, 'unavailable'];
    }
    return [$backupDir, 'ready'];
}

function handleDeveloperBackupStatus() {
    list($backupDir, $state) = developerBackupDirectory();
    $status = [
        'directory_state' => $state,
        'backup_count' => 0,
        'latest_created_at' => null,
        'latest_size_bytes' => null,
        'schedule_check' => 'unknown',
    ];

    if ($backupDir !== null) {
        $archives = glob($backupDir . DIRECTORY_SEPARATOR . 'handel-luxe-backup-*.tar.gz');
        if ($archives === false) jsonResponse(['error' => 'Could not read the configured backup directory'], 500);
        $archives = array_values(array_filter($archives, function ($path) {
            return preg_match('/^handel-luxe-backup-\d{8}-\d{6}-[a-f0-9]{6}\.tar\.gz$/', basename($path)) === 1
                && is_file($path);
        }));
        rsort($archives, SORT_STRING);
        $status['backup_count'] = count($archives);
        if ($archives) {
            $status['latest_created_at'] = gmdate('Y-m-d H:i:s', filemtime($archives[0]));
            $status['latest_size_bytes'] = filesize($archives[0]);
        }
    }
    jsonResponse($status);
}

function handleDeveloperBackupDownload() {
    require_once __DIR__ . '/../config/backups.php';
    set_time_limit(0);
    session_write_close();

    $tempBase = tempnam(sys_get_temp_dir(), 'handel-luxe-backup-');
    if ($tempBase === false) jsonResponse(['error' => 'Could not prepare a temporary backup file'], 500);
    if (!unlink($tempBase)) jsonResponse(['error' => 'Could not prepare a temporary backup file'], 500);

    $archivePath = $tempBase . '.tar.gz';
    try {
        createStoreBackupArchive($archivePath);
    } catch (Throwable $e) {
        error_log('Developer full backup download failed: ' . $e->getMessage());
        jsonResponse(['error' => 'Could not create the full backup. Check the server error log for details.'], 500);
    }

    auditCurrentActor('developer.backup_downloaded', [
        'size_bytes' => filesize($archivePath),
    ]);
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="handel-luxe-backup-' . gmdate('Ymd-His') . '.tar.gz"');
    header('Content-Length: ' . filesize($archivePath));
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    if (readfile($archivePath) === false) error_log('Could not stream full backup to developer dashboard.');
    if (file_exists($archivePath) && !unlink($archivePath)) {
        error_log('Could not remove temporary store backup: ' . $archivePath);
    }
    exit;
}
