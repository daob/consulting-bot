<?php
group('she can end the meeting');

// She asks for 5 every turn; the one-step rule means it takes five of them.
fake_model(array_fill(0, 6, "[[stage=2; mood=5]]\n\nThis really isn't helping."));
$s = cc_chat_start('1234567');

for ($i = 1; $i <= 4; $i++) {
    $r = cc_chat_send($s, 'That design was a mistake.');
    ok(!$r['walked_out'], 'still in the room at mood ' . $i);
}

$r = cc_chat_send($s, 'It was still a mistake though.');
ok($r['walked_out'], 'the fifth bad turn ends the meeting');
is_same(array_column($s['mood_trace'], 'mood'), [1, 2, 3, 4, 5], 'and the trace climbs one step at a time');
ok(!empty($s['walked_out']), 'and is recorded on the session');

throws(fn() => cc_chat_send($s, 'Wait, come back.'), 'She has left', 'no further turns are accepted');

// Ending still works after she has walked out - that is the whole point.
fake_model(['{"items":[],"takeaways":[],"moment":{},"would_return":{}}']);
$end = cc_chat_end($s);
ok(str_contains($end['markdown'], 'The conversation'), 'the session can still be closed for feedback');
