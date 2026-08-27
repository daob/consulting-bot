<?php
/**
 * Run in its own process with with_frustration = 0, because the config is read
 * once per process. Prints one line per check for the parent to assert on.
 */
declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/cc-nofrust-' . getmypid();
@mkdir($tmp . '/data', 0700, true);
file_put_contents($tmp . '/students.txt', "1234567\n");
$cfg = [
    'api_base' => 'https://example.invalid/v1', 'api_key' => 'k', 'model' => 'm',
    'site_url' => '', 'site_title' => '',
    'max_tokens' => 100, 'max_tokens_assessor' => 200,
    'temperature' => 0.8, 'temperature_assessor' => 0.2,
    'with_frustration' => 0,                       // <- the switch under test
    'persona' => 'client-01', 'max_turns' => 10, 'max_sessions_per_student' => 2,
    'class_code' => 'CODE', 'students_file' => $tmp . '/students.txt',
    'data_dir' => $tmp . '/data', 'timezone' => 'UTC', 'debug' => true,
];
file_put_contents($tmp . '/config.php', '<?php return ' . var_export($cfg, true) . ';');
putenv('CC_CONFIG=' . $tmp . '/config.php');
// Runs from a checkout (tests/ beside private/) and from the server, where
// tests/ sits inside the private directory itself.
foreach ([dirname(__DIR__) . '/private/src/bootstrap.php',
          dirname(__DIR__) . '/src/bootstrap.php'] as $bootstrap) {
    if (is_file($bootstrap)) {
        require $bootstrap;
        break;
    }
}
if (!function_exists('cc_config')) {
    fwrite(STDERR, "Cannot find src/bootstrap.php from " . __DIR__ . "\n");
    exit(1);
}

$p      = cc_persona('client-01');
$prompt = cc_system_prompt($p);

$say = fn(string $k, bool $v) => print($k . '=' . ($v ? '1' : '0') . "\n");

$say('moody',            cc_moody());
$say('has_ladder',       str_contains($prompt, 'Your patience'));
$say('has_mood_field',   str_contains($prompt, 'mood=<0-5>'));
$say('reminder_mood',    str_contains(cc_turn_reminder($p), 'mood'));

// The client still works, and a stray mood in the model output is ignored.
cc_llm_transport(fn() => [200, json_encode([
    'choices' => [['message' => ['content' => "[[stage=2; mood=4]]\nStill talking."], 'finish_reason' => 'stop']],
    'usage' => [],
])], true);
$s = cc_chat_start('1234567');
$r = cc_chat_send($s, 'hello');
$say('chat_works',       $r['say'] === 'Still talking.');
$say('mood_recorded',    ($s['state']['mood'] ?? 0) !== 0);
$say('trace_empty',      count($s['mood_trace']) === 0);

// And the report has no trace section.
$a = ['items' => [], 'takeaways' => [], 'moment' => [], 'would_return' => [], 'unverified' => []];
foreach (cc_rubric($p) as $it) {
    $a['items'][] = $it + ['verdict' => 'met', 'evidence' => '', 'verified' => true, 'comment' => ''];
}
$say('report_has_trace', str_contains(cc_report_markdown($s, $p, $a), 'How her patience moved'));

exec('rm -rf ' . escapeshellarg($tmp));
