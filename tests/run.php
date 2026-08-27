<?php
/**
 * A test runner in forty lines, because a framework would be more code than
 * the thing it tests. Run: php tests/run.php
 */
declare(strict_types=1);

$GLOBALS['cc_pass'] = 0;
$GLOBALS['cc_fail'] = [];
$GLOBALS['cc_group'] = '';

function group(string $name): void
{
    $GLOBALS['cc_group'] = $name;
    echo "\n" . $name . "\n";
}

function ok(bool $cond, string $what): void
{
    if ($cond) {
        $GLOBALS['cc_pass']++;
        echo "  ok   $what\n";
    } else {
        $GLOBALS['cc_fail'][] = $GLOBALS['cc_group'] . ' / ' . $what;
        echo "  FAIL $what\n";
    }
}

function is_same(mixed $got, mixed $want, string $what): void
{
    $good = $got === $want;
    ok($good, $what . ($good ? '' : ' (got ' . var_export($got, true) . ', want ' . var_export($want, true) . ')'));
}

function throws(callable $fn, string $needle, string $what): void
{
    try {
        $fn();
        ok(false, $what . ' (no exception)');
    } catch (Throwable $e) {
        ok(str_contains($e->getMessage(), $needle), $what . ' (got: ' . $e->getMessage() . ')');
    }
}

/** Install a fake model that replies with whatever the queue holds. */
function fake_model(array $replies): void
{
    $queue = $replies;
    cc_llm_transport(function (string $url, array $payload, array $headers) use (&$queue) {
        $next = array_shift($queue) ?? 'no more canned replies';
        if (is_int($next)) {                       // an int means "return this status"
            return [$next, json_encode(['error' => ['message' => 'simulated ' . $next]])];
        }
        return [200, json_encode([
            'choices' => [['message' => ['content' => $next], 'finish_reason' => 'stop']],
            'usage'   => ['cost' => 0.001],
        ])];
    }, true);
}

require __DIR__ . '/setup.php';

foreach (glob(__DIR__ . '/test_*.php') as $file) {
    require $file;
}

echo "\n" . str_repeat('-', 60) . "\n";
if ($GLOBALS['cc_fail']) {
    echo count($GLOBALS['cc_fail']) . " FAILED, {$GLOBALS['cc_pass']} passed\n";
    foreach ($GLOBALS['cc_fail'] as $f) {
        echo "  - $f\n";
    }
    exit(1);
}
echo "all {$GLOBALS['cc_pass']} passed\n";
