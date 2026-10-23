<?php
/**
 * TASK 5 — SMTP failure handling and log hygiene.
 * No mail is sent: the test only drives an unreachable socket and inspects
 * the source, so live Gmail credentials are never used to deliver a message.
 */
echo "[smtp] failure handling and secret-safe errors\n";

$src = file_get_contents(__DIR__ . '/../config/mail.php');
t_assert(strpos($src, "SMTP error after") === false, 'SMTP exceptions no longer echo the raw command (old code leaked the AUTH LOGIN password)');
t_contains($src, "'AUTH password'", 'AUTH password exchange is labelled, not printed');
t_contains($src, 'catch (Throwable $e)', 'sendMail catches Throwable before falling back');
t_contains($src, '@fwrite($socket, "QUIT\\r\\n")', 'QUIT is best-effort to avoid a duplicate on fallback');

require_once __DIR__ . '/../config/mail.php';

$secret = 'supersecretpassword-value';
$thrown = null;
try {
    smtpSend('127.0.0.1', 1, 'qa@example.test', $secret, 'Handel <qa@example.test>', 'to@example.test', 'subject', '<p>body</p>');
} catch (Throwable $e) {
    $thrown = $e;
}
t_assert($thrown !== null, 'connecting to a closed port raises an SMTP exception');
if ($thrown) {
    t_assert(strpos($thrown->getMessage(), $secret) === false, 'connection failure message does not contain the SMTP password');
    t_contains($thrown->getMessage(), 'SMTP connect failed', 'connection failure message identifies the stage without credentials');
}
