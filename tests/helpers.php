<?php
// Minimal zero-dependency test harness (no Composer needed).
declare(strict_types=1);

$GLOBALS['__tests'] = ['pass' => 0, 'fail' => 0, 'fails' => []];

function it(string $name, callable $fn): void {
    try {
        $fn();
        $GLOBALS['__tests']['pass']++;
        echo "  \033[32m✓\033[0m $name\n";
    } catch (Throwable $e) {
        $GLOBALS['__tests']['fail']++;
        $GLOBALS['__tests']['fails'][] = "$name — " . $e->getMessage();
        echo "  \033[31m✗\033[0m $name — " . $e->getMessage() . "\n";
    }
}

function assert_true($cond, string $msg = 'expected true'): void {
    if ($cond !== true) throw new Exception($msg);
}

function assert_eq($expected, $actual, string $msg = ''): void {
    if ($expected !== $actual) {
        $e = var_export($expected, true); $a = var_export($actual, true);
        throw new Exception(($msg ? "$msg: " : '') . "expected $e, got $a");
    }
}
