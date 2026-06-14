<?php
// Usage: php tests/run.php
declare(strict_types=1);
require __DIR__ . '/helpers.php';

foreach (glob(__DIR__ . '/*_test.php') as $file) {
    echo "\n" . basename($file) . "\n";
    require $file;
}

$t = $GLOBALS['__tests'];
echo "\n" . str_repeat('-', 40) . "\n";
echo "Passed: {$t['pass']}  Failed: {$t['fail']}\n";
if ($t['fail'] > 0) { foreach ($t['fails'] as $f) echo "  FAIL: $f\n"; exit(1); }
exit(0);
