<?php
group('report');

fake_model([
    "[[stage=2; mood=0]]\n\nWe used the same tests twice.",
    "[[stage=2; mood=2]]\n\nSorry, I'm not following that at all.",
    "[[stage=3; mood=1]]\n\nRight, that I can do.",
]);
$s = cc_chat_start('1234567');
cc_chat_send($s, 'Tell me about the project.');
cc_chat_send($s, 'You will want a configural, metric and scalar invariance sequence.');
cc_chat_send($s, 'Sorry. Plainly: we check the test measures the same thing at both ages.');

$p = cc_persona('client-01');
$a = ['items' => [], 'takeaways' => ['One.', 'Two.', 'Three.'],
      'moment' => ['turn' => 2, 'why' => 'Jargon landed badly.'],
      'would_return' => ['answer' => true, 'because' => 'He recovered.'],
      'unverified' => ['A3']];
foreach (cc_rubric($p) as $it) {
    $a['items'][] = $it + ['verdict' => 'partial', 'evidence' => 'Tell me about the project.', 'verified' => true, 'comment' => 'A remark.'];
}

$md = cc_report_markdown($s, $p, $a);

foreach (cc_rubric_blocks() as $block) {
    ok(str_contains($md, '### ' . $block), 'has the ' . $block . ' block');
}
ok(str_contains($md, '## The conversation'), 'includes the conversation');
ok(str_contains($md, '**You (1).**'), 'numbers the consultant turns');
ok(str_contains($md, '**Sanne.**'), 'names the client');
ok(!str_contains($md, '1234567'), 'the student number is not in the body');
ok(!str_contains($md, '[['), 'no hidden state leaks into the report');
ok(str_contains($md, 'Three things to do differently'), 'has the takeaways');
ok(str_contains($md, 'Would she come back?'), 'has the closing question');
ok(str_contains($md, 'do not appear in the transcript'), 'flags unverified quotations');

group('mood trace');
ok(str_contains($md, 'How her patience moved'), 'includes the trace when the affect model is on');
ok(str_contains($md, 'rose here'), 'marks where it rose');
ok(str_contains($md, 'turn  2'), 'lists the turn numbers');

group('an unverified quotation is marked, not silently shown');
$a2 = $a;
$a2['items'][0]['verified'] = false;
$md2 = cc_report_markdown($s, $p, $a2);
ok(str_contains($md2, 'does not appear in the transcript'), 'says so plainly');
ok(!str_contains($md2, '\n_('), 'no literal backslash-n leaks into the markdown');
