<?php
require_once __DIR__ . '/db.php';

/* Shared sliding-window rate limiter (moved out of api/auth.php so other
   routes — guest order lookup, password reset — can use it too). */
function checkRateLimit($key, $maxAttempts, $windowSeconds) {
    $db = getDB();
    $now = time();
    $resetBefore = $now - $windowSeconds;

    /* One atomic upsert handles the first request, the in-window increment
       and the window reset. Doing SELECT-then-INSERT/UPDATE here let two
       concurrent first requests collide on the primary key (uncaught 500),
       and let simultaneous requests all read the same old count and slip
       past the limit. */
    $stmt = $db->prepare("INSERT INTO handel_rate_limits (`key`, attempts, window_start) VALUES (?, 1, ?)
        ON DUPLICATE KEY UPDATE
            attempts = IF(window_start <= ?, 1, attempts + 1),
            window_start = IF(window_start <= ?, VALUES(window_start), window_start)");
    $stmt->bind_param('siii', $key, $now, $resetBefore, $resetBefore);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("SELECT attempts FROM handel_rate_limits WHERE `key` = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $attempts = (int)($stmt->get_result()->fetch_assoc()['attempts'] ?? 1);
    $stmt->close();

    return $attempts <= $maxAttempts;
}
