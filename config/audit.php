<?php

function ensureAuditLogTable() {
    static $ready = false;
    if ($ready) return;

    getDB()->query("CREATE TABLE IF NOT EXISTS handel_audit_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        actor_type VARCHAR(20) NOT NULL,
        actor_id INT UNSIGNED NULL,
        actor_name VARCHAR(100) NOT NULL,
        action VARCHAR(64) NOT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audit_created_at (created_at),
        INDEX idx_audit_action (action)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

function writeAuditLog($action, $actorType, $actorId, $actorName, $details = []) {
    ensureAuditLogTable();

    $detailsJson = $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : null;
    if ($details && $detailsJson === false) {
        throw new RuntimeException('Could not encode audit log details');
    }
    $ipAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    if ($ipAddress === '') $ipAddress = null;
    $actorName = substr((string)$actorName, 0, 100);

    $stmt = getDB()->prepare("INSERT INTO handel_audit_logs
        (actor_type, actor_id, actor_name, action, details, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sissss', $actorType, $actorId, $actorName, $action, $detailsJson, $ipAddress);
    $stmt->execute();
    $stmt->close();
}

function auditCurrentActor($action, $details = []) {
    if (!empty($_SESSION['developer_dashboard'])) {
        $developerName = is_string($_SESSION['developer_dashboard'])
            ? $_SESSION['developer_dashboard']
            : 'Developer';
        writeAuditLog($action, 'developer', null, $developerName, $details);
        return;
    }

    $user = $_SESSION['user'] ?? [];
    $actorType = ($user['role'] ?? '') === 'admin' ? 'admin' : 'user';
    writeAuditLog(
        $action,
        $actorType,
        isset($user['id']) ? (int)$user['id'] : null,
        (string)($user['name'] ?? 'Unknown'),
        $details
    );
}

function readAuditLogs($limit = 100) {
    ensureAuditLogTable();
    $limit = max(1, min(200, (int)$limit));
    $stmt = getDB()->prepare("SELECT id, actor_type, actor_id, actor_name, action, details, ip_address, created_at
        FROM handel_audit_logs ORDER BY id DESC LIMIT ?");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $row['details'] = $row['details'] ? json_decode($row['details'], true) : [];
        if (!is_array($row['details'])) $row['details'] = [];
        $logs[] = $row;
    }
    $stmt->close();
    return $logs;
}
