<?php
group('assessor');

/** A short finished session to grade. */
function cc_test_session(): array
{
    fake_model([
        "[[stage=2; mood=0; open=comparability]]\n\nWe used the same tests at two and a half and at four.",
        "[[stage=3; mood=1; open=missing-data]]\n\nMore than half the children only did the age four visit.",
    ]);
    $s = cc_chat_start('1234567');
    cc_chat_send($s, 'Have a seat. Tell me about the project.');
    cc_chat_send($s, 'Let me see if I have this right: you want to know if the scores are comparable.');
    return $s;
}

$s = cc_test_session();
$p = cc_persona('client-01');

$text = cc_transcript_text($s, $p);
ok(str_contains($text, 'CONSULTANT (turn 1):'), 'transcript numbers the consultant turns');
ok(str_contains($text, 'SANNE:'), 'transcript names the client');
ok(!str_contains($text, '[['), 'transcript carries no hidden state');

// Two real quotes, one invented, one item left out entirely.
$real1 = 'Have a seat. Tell me about the project.';
$real2 = 'More than half the children only did the age four visit.';
$json  = json_encode([
    'items' => [
        ['id' => 'A1', 'verdict' => 'met',     'evidence' => $real1, 'comment' => 'You settled her in.'],
        ['id' => 'A5', 'verdict' => 'partial', 'evidence' => 'Let me see if I have this right: you want to know if the scores are comparable.', 'comment' => 'Good, but you did not check she agreed.'],
        ['id' => 'A2', 'verdict' => 'met',     'evidence' => $real2, 'comment' => 'She did the talking.'],
        ['id' => 'A3', 'verdict' => 'not_met', 'evidence' => 'You never asked who else was on the project.', 'comment' => 'Invented quote.'],
        ['id' => 'B1', 'verdict' => 'nonsense','evidence' => '', 'comment' => 'Bad verdict word.'],
    ],
    'takeaways'    => ['Ask who else is involved.', 'Check she agreed with your restatement.', 'Name the next step.'],
    'moment'       => ['turn' => 2, 'why' => 'You restated but did not wait for her to confirm.'],
    'would_return' => ['answer' => true, 'because' => 'He listened, at least.'],
]);
fake_model(["```json\n" . $json . "\n```"]);

$a = cc_assess($s, $p);

$by = [];
foreach ($a['items'] as $it) { $by[$it['id']] = $it; }

is_same(count($a['items']), count(cc_rubric($p)), 'every rubric item comes back, judged or not');
is_same($by['A1']['verdict'], 'met', 'reads a verdict');
ok($by['A1']['verified'], 'a verbatim quote verifies');
ok($by['A2']['verified'], 'a second verbatim quote verifies');
ok(!$by['A3']['verified'], 'an invented quote is caught');
ok(in_array('A3', $a['unverified'], true), 'and is listed as unverified');
is_same($by['B1']['verdict'], 'missing', 'an unrecognised verdict becomes "missing"');
is_same($by['A9']['verdict'], 'missing', 'an item the assessor skipped becomes "missing"');
is_same(count($a['takeaways']), 3, 'three takeaways');
is_same($a['moment']['turn'], 2, 'reads the moment');

group('assessor json handling');
is_same(cc_json_loose('{"a":1}')['a'], 1, 'plain json');
is_same(cc_json_loose("```json\n{\"a\":2}\n```")['a'], 2, 'fenced json');
is_same(cc_json_loose("Here you go:\n{\"a\":3}\nHope that helps.")['a'], 3, 'json with prose around it');
is_same(cc_json_loose('not json at all'), null, 'gives up cleanly on rubbish');

group('quote verification is robust to cosmetics');
$hay = cc_normalise('She said: "I am not sure I follow" — and paused.');
ok(str_contains($hay, cc_normalise('“I am not sure I follow”')), 'smart quotes match straight ones');
ok(str_contains($hay, cc_normalise("I am   not sure\n  I follow")), 'whitespace differences do not matter');
ok(!str_contains($hay, cc_normalise('I am not certain I follow')), 'a paraphrase still fails');
ok(str_contains($hay, cc_normalise('I Am Not Sure I Follow')), 'a recapitalised fragment matches');
ok(str_contains(cc_normalise('Well, the project is already running, so I cannot redo it.'),
                cc_normalise('The project is already running, so I cannot redo it.')),
   'the real failure case: a mid-sentence fragment the assessor capitalised');

group('the assessment cannot be skipped');
$s2 = cc_test_session();
cc_llm_transport(fn() => [500, '{"error":{"message":"assessor is down"}}'], true);
$end = cc_chat_end($s2);
ok($end['failed'] !== null, 'a failing assessor is reported');
ok($s2['ended'], 'the session still closes');
ok(str_contains($end['markdown'], 'The conversation'), 'the transcript is still written');
ok(is_file(cc_data_dir('transcripts') . '/' . $end['filename']), 'and saved to disk');
cc_llm_transport(null, true);

group('the assessor can run on its own model');

// Build the session first: cc_test_session() installs its own fake transport.
$s3 = cc_test_session();

$seen = null;
cc_llm_transport(function ($url, $payload, $headers) use (&$seen) {
    $seen = $payload['model'];
    return [200, json_encode(['choices' => [['message' => ['content' =>
        '{"items":[],"takeaways":[],"moment":{},"would_return":{}}'], 'finish_reason' => 'stop']], 'usage' => []])];
}, true);
cc_assess($s3, $p);
is_same($seen, cc_config('model'), 'falls back to the chat model when model_assessor is unset');
cc_llm_transport(null, true);
