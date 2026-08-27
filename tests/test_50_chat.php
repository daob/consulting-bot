<?php
group('one exchange, with a fake model');

fake_model([
    "[[stage=2; mood=0; open=comparability]]\n\nI've got test scores at two ages and I don't know if they're comparable.",
    "[[stage=2; mood=1; open=comparability]]\n\nSorry, what does invariance mean here?",
]);

$s = cc_chat_start('1234567');
$r = cc_chat_send($s, 'Come in, have a seat. What brings you here?');

is_same($r['turn'], 1, 'turn increments');
ok(!str_contains($r['say'], '[['), 'the student never sees the state line');
is_same($r['state']['stage'], 2, 'state is carried into the session');
is_same(count($s['messages']), 2, 'both sides are recorded');
is_same($s['messages'][0]['role'], 'user', 'the consultant speaks first');
is_same($s['mood_trace'][0]['mood'], 0, 'mood trace records turn one');

$r = cc_chat_send($s, 'Tell me about the study.');
is_same($r['turn'], 2, 'turn increments again');
is_same($s['mood_trace'][1]['mood'], 1, 'mood trace records the rise');
ok($s['cost'] > 0, 'cost accumulates');

$reloaded = cc_session_load('1234567', $s['sid']);
is_same($reloaded['turn'], 2, 'the session was saved after each turn');

throws(fn() => cc_chat_send($s, '   '), 'Say something', 'rejects an empty message');
throws(fn() => cc_chat_send($s, str_repeat('x', 5000)), 'too long', 'rejects an overlong message');

group('turn limit');
$limit = (int)cc_config('max_turns');
fake_model(array_fill(0, $limit + 4, "[[stage=2]]\nStill here."));
$s2 = cc_chat_start('7654321');
for ($i = 0; $i < $limit; $i++) {
    cc_chat_send($s2, 'turn ' . $i);
}
is_same($s2['turn'], $limit, 'reaches the limit');
throws(fn() => cc_chat_send($s2, 'one more'), 'reached the limit', 'refuses to go past it');

group('messages sent to the model');
$captured = null;
cc_llm_transport(function ($url, $payload, $headers) use (&$captured) {
    $captured = $payload;
    return [200, json_encode(['choices' => [['message' => ['content' => "[[stage=1]]\nHm."], 'finish_reason' => 'stop']], 'usage' => []])];
}, true);
$s3 = cc_chat_start('1234567');
cc_chat_send($s3, 'Hello there.');

is_same($captured['messages'][0]['role'], 'system', 'system prompt goes first');
$last = end($captured['messages']);
is_same($last['role'], 'system', 'the reminder goes last, closest to generation');
ok(str_contains($last['content'], 'Stay in role'), 'and it is the reminder');
is_same($captured['messages'][1]['content'], 'Hello there.', 'the student message is in place');
