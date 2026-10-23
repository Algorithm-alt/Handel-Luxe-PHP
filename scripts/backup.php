<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '';

ob_start();
register_shutdown_function(function () {
    $status = http_response_code();
    if (is_int($status) && $status >= 400) {
        $output = ob_get_clean();
        if ($output !== '') fwrite(STDERR, $output . PHP_EOL);
        exit(1);
    }
});

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/backups.php';

function runScheduledStoreBackup() {
    $configuredDir = trim(env_or('BACKUP_DIR', ''));
    if ($configuredDir === '') {
        throw new RuntimeException('Set BACKUP_DIR to a private directory outside the website document root.');
    }

    if (!is_dir($configuredDir) && !mkdir($configuredDir, 0700, true) && !is_dir($configuredDir)) {
        throw new RuntimeException('Could not create the configured backup directory.');
    }
    $backupDir = realpath($configuredDir);
    $appRoot = realpath(dirname(__DIR__));
    if ($backupDir === false || $appRoot === false) {
        throw new RuntimeException('Could not resolve the backup directory.');
    }
    $appPrefix = rtrim($appRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $insideApp = DIRECTORY_SEPARATOR === '\\'
        ? strncasecmp($backupDir, $appPrefix, strlen($appPrefix)) === 0
        : strncmp($backupDir, $appPrefix, strlen($appPrefix)) === 0;
    if ($backupDir === $appRoot || $insideApp) {
        throw new RuntimeException('BACKUP_DIR must be outside the website project directory.');
    }
    if (!chmod($backupDir, 0700)) {
        throw new RuntimeException('Could not restrict access to the backup directory.');
    }

    $filename = 'handel-luxe-backup-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.tar.gz';
    $archivePath = $backupDir . DIRECTORY_SEPARATOR . $filename;
    createStoreBackupArchive($archivePath);
    if (!chmod($archivePath, 0600)) {
        unlink($archivePath);
        throw new RuntimeException('Could not restrict access to the completed backup.');
    }

    $archives = glob($backupDir . DIRECTORY_SEPARATOR . 'handel-luxe-backup-*.tar.gz');
    if ($archives === false) throw new RuntimeException('Could not list old backups for retention cleanup.');
    $archives = array_values(array_filter($archives, function ($path) {
        return preg_match('/^handel-luxe-backup-\d{8}-\d{6}-[a-f0-9]{6}\.tar\.gz$/', basename($path)) === 1;
    }));
    rsort($archives, SORT_STRING);
    foreach (array_slice($archives, 14) as $oldArchive) {
        if (!unlink($oldArchive)) {
            throw new RuntimeException('Could not remove an expired backup: ' . basename($oldArchive));
        }
    }

    echo 'Backup created: ' . $archivePath . PHP_EOL;
}

try {
    runScheduledStoreBackup();
} catch (Throwable $e) {
    fwrite(STDERR, 'Backup failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
