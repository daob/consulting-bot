<?php
group('with_frustration = 0 removes the affect model entirely');

exec('php ' . escapeshellarg(__DIR__ . '/nofrust.php') . ' 2>&1', $lines, $rc);
$got = [];
foreach ($lines as $l) {
    if (str_contains($l, '=')) {
        [$k, $v] = explode('=', trim($l), 2);
        $got[$k] = $v === '1';
    }
}
is_same($rc, 0, 'the switched-off configuration runs without error');
ok(isset($got['moody']) && !$got['moody'], 'cc_moody() reports off');
ok(!($got['has_ladder'] ?? true), 'the patience section is gone from the prompt');
ok(!($got['has_mood_field'] ?? true), 'the state line no longer asks for mood');
ok(!($got['reminder_mood'] ?? true), 'the turn reminder no longer mentions mood');
ok($got['chat_works'] ?? false, 'the client still holds a conversation');
ok(!($got['mood_recorded'] ?? true), 'a mood the model volunteers anyway is ignored');
ok($got['trace_empty'] ?? false, 'nothing is recorded in the trace');
ok(!($got['report_has_trace'] ?? true), 'the report has no trace section');
