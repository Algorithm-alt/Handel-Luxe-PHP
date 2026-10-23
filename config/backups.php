<?php

function backupWrite($handle, $content) {
    while ($content !== '') {
        $written = fwrite($handle, $content);
        if ($written === false || $written === 0) {
            throw new RuntimeException('Could not write the backup file.');
        }
        $content = substr($content, $written);
    }
}

function writeDatabaseBackup($path) {
    $db = getDB();
    $file = fopen($path, 'wb');
    if ($file === false) throw new RuntimeException('Could not create the SQL backup.');

    $transactionStarted = false;
    try {
        backupWrite($file, "-- Handel Luxe GH database backup\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        $db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        $transactionStarted = true;

        $tablesResult = $db->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        $tables = [];
        while ($row = $tablesResult->fetch_row()) $tables[] = $row[0];
        $tablesResult->free();
        if (!$tables) throw new RuntimeException('No database tables were found to back up.');

        foreach ($tables as $table) {
            $identifier = '`' . str_replace('`', '``', $table) . '`';
            $createResult = $db->query('SHOW CREATE TABLE ' . $identifier);
            $createRow = $createResult->fetch_row();
            $createResult->free();
            if (!$createRow || !isset($createRow[1])) {
                throw new RuntimeException('Could not read a table definition for the backup.');
            }

            backupWrite($file, "\n-- Table " . $table . "\n" . $createRow[1] . ";\n");
            $result = $db->query('SELECT * FROM ' . $identifier, MYSQLI_USE_RESULT);
            $fields = $result->fetch_fields();
            $columnNames = array_map(function ($field) {
                return '`' . str_replace('`', '``', $field->name) . '`';
            }, $fields);
            $insertPrefix = 'INSERT INTO ' . $identifier . ' (' . implode(', ', $columnNames) . ') VALUES ';

            while ($row = $result->fetch_row()) {
                $values = [];
                foreach ($row as $value) {
                    $values[] = $value === null ? 'NULL' : "'" . $db->real_escape_string($value) . "'";
                }
                backupWrite($file, $insertPrefix . '(' . implode(', ', $values) . ");\n");
            }
            $result->free();
        }

        $db->commit();
        $transactionStarted = false;
        backupWrite($file, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        if (!fflush($file)) throw new RuntimeException('Could not finish writing the SQL backup.');
    } catch (Throwable $e) {
        if ($transactionStarted) $db->rollback();
        fclose($file);
        throw $e;
    }
    fclose($file);
}

function createStoreBackupArchive($archivePath) {
    if (!class_exists('PharData') || !extension_loaded('zlib')) {
        throw new RuntimeException('This PHP installation needs Phar and zlib enabled to create backups.');
    }

    $sqlPath = tempnam(sys_get_temp_dir(), 'handel-luxe-sql-');
    if ($sqlPath === false) throw new RuntimeException('Could not create a temporary SQL backup.');
    $originalUmask = umask(0077);
    $tarPath = substr($archivePath, 0, -strlen('.gz'));
    $uploadsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
    $uploadPaths = [];

    try {
        writeDatabaseBackup($sqlPath);
        if (file_exists($tarPath)) {
            if (!unlink($tarPath)) throw new RuntimeException('Could not prepare the backup archive.');
        }
        if (file_exists($archivePath)) {
            if (!unlink($archivePath)) throw new RuntimeException('Could not replace the backup archive.');
        }

        $archive = new PharData($tarPath);
        $archive->addFile($sqlPath, 'database.sql');
        $manifestPath = tempnam(sys_get_temp_dir(), 'handel-luxe-manifest-');
        if ($manifestPath === false) throw new RuntimeException('Could not create the backup manifest.');
        try {
            $manifest = "Handel Luxe GH full backup\n"
                . 'Created (UTC): ' . gmdate('Y-m-d H:i:s') . "\n"
                . "Contents: database.sql and public/uploads/ files\n"
                . "Restore note: import database.sql into an empty database, then restore public/uploads/.\n";
            if (file_put_contents($manifestPath, $manifest) === false) {
                throw new RuntimeException('Could not write the backup manifest.');
            }
            $archive->addFile($manifestPath, 'BACKUP-INFO.txt');
        } finally {
            if (file_exists($manifestPath) && !unlink($manifestPath)) {
                error_log('Could not remove temporary backup manifest: ' . $manifestPath);
            }
        }

        if (is_dir($uploadsPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploadsPath, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink()) continue;
                $relativePath = substr($file->getPathname(), strlen(dirname(__DIR__) . DIRECTORY_SEPARATOR));
                $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
                $archive->addFile($file->getPathname(), $relativePath);
                $uploadPaths[] = $relativePath;
            }
        }

        $archive->compress(Phar::GZ);
        unset($archive);
        if (!is_file($archivePath) || filesize($archivePath) === 0) {
            throw new RuntimeException('The backup archive was not created.');
        }
        $compressedArchive = new PharData($archivePath);
        if (!$compressedArchive->offsetExists('database.sql')
            || !$compressedArchive->offsetExists('BACKUP-INFO.txt')
            || $compressedArchive['database.sql']->getSize() === 0) {
            throw new RuntimeException('The completed backup archive is missing required contents.');
        }
        foreach ($uploadPaths as $uploadPath) {
            if (!$compressedArchive->offsetExists($uploadPath)) {
                throw new RuntimeException('The completed backup archive is missing uploaded files.');
            }
        }
        unset($compressedArchive);
    } catch (Throwable $e) {
        if (file_exists($tarPath) && !unlink($tarPath)) {
            error_log('Could not remove incomplete backup archive: ' . $tarPath);
        }
        if (file_exists($archivePath) && !unlink($archivePath)) {
            error_log('Could not remove incomplete compressed backup: ' . $archivePath);
        }
        throw $e;
    } finally {
        umask($originalUmask);
        if (file_exists($sqlPath) && !unlink($sqlPath)) {
            error_log('Could not remove temporary SQL backup: ' . $sqlPath);
        }
        if (file_exists($tarPath) && !unlink($tarPath)) {
            error_log('Could not remove temporary tar archive: ' . $tarPath);
        }
    }
}
