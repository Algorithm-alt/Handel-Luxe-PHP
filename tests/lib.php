<?php
/**
 * Minimal test helpers. No external framework: the project ships as plain
 * PHP, so a release check must run with only `php tests/run.php`.
 */
$GLOBALS['T_RESULTS'] = ['pass' => 0, 'fail' => 0, 'skip' => 0, 'failures' => []];

function t_pass($msg = '') {
    $GLOBALS['T_RESULTS']['pass']++;
    echo "  PASS  $msg\n";
}

function t_fail($msg) {
    $GLOBALS['T_RESULTS']['fail']++;
    $GLOBALS['T_RESULTS']['failures'][] = $msg;
    echo "  FAIL  $msg\n";
}

function t_skip($msg) {
    $GLOBALS['T_RESULTS']['skip']++;
    echo "  SKIP  $msg\n";
}

function t_assert($cond, $msg) {
    if ($cond) t_pass($msg); else t_fail($msg);
}

function t_eq($actual, $expected, $msg) {
    if ($actual === $expected) {
        t_pass($msg);
    } else {
        t_fail($msg . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
    }
}

function t_contains($haystack, $needle, $msg) {
    if (strpos((string)$haystack, $needle) !== false) t_pass($msg);
    else t_fail($msg . ' (missing ' . var_export($needle, true) . ')');
}

function t_summary() {
    $r = $GLOBALS['T_RESULTS'];
    echo "\n" . str_repeat('-', 60) . "\n";
    echo "pass={$r['pass']} fail={$r['fail']} skip={$r['skip']}\n";
    if ($r['failures']) {
        echo "\nFailures:\n";
        foreach ($r['failures'] as $f) echo " - $f\n";
    }
    return $r['fail'] === 0 ? 0 : 1;
}
