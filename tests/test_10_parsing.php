<?php
group('hidden state parsing');

$prev = ['stage' => 1, 'mood' => 0, 'open' => []];

$r = cc_parse_reply("[[stage=3; mood=1; open=comparability, missing-data]]\n\nI'm not following.", $prev);
is_same($r['state']['stage'], 3, 'reads stage');
is_same($r['state']['mood'], 1, 'reads mood');
is_same($r['state']['open'], ['comparability', 'missing-data'], 'reads open tags');
is_same($r['say'], "I'm not following.", 'strips the state line');
ok($r['had_state'], 'reports that state was present');

$r = cc_parse_reply('No state line here at all.', $prev);
is_same($r['say'], 'No state line here at all.', 'passes text through when state is missing');
is_same($r['state'], $prev, 'keeps the previous state when none is given');
ok(!$r['had_state'], 'reports missing state');

$r = cc_parse_reply("[[stage=99; mood=42]]\nHello", $prev);
is_same($r['state']['stage'], 5, 'clamps stage high');
is_same($r['state']['mood'], 1, 'a wild mood value cannot skip ahead');

$r = cc_parse_reply("[[stage=-4; mood=-1]]\nHello", $prev);
is_same($r['state']['stage'], 1, 'clamps stage low');
is_same($r['state']['mood'], 0, 'clamps mood low');

$r = cc_parse_reply("[[stage=2]]\nFirst. [[stage=3]] Second.", $prev);
ok(!str_contains($r['say'], '[['), 'removes a stray state line from the middle');

$r = cc_parse_reply("[[ nonsense ]]\nStill speaks.", $prev);
is_same($r['say'], 'Still speaks.', 'survives an unparseable state line');
is_same($r['state']['stage'], 1, 'unparseable state leaves values alone');

$r = cc_parse_reply("   \n [[stage=2]]\n\n  Leading whitespace.  ", $prev);
is_same($r['say'], 'Leading whitespace.', 'tolerates whitespace around the state line');

group('mood moves one step at a time');
$at2 = ['stage' => 2, 'mood' => 2, 'open' => []];
is_same(cc_parse_reply("[[mood=5]]\nx", $at2)['state']['mood'], 3, 'a jump upward is capped at one step');
is_same(cc_parse_reply("[[mood=0]]\nx", $at2)['state']['mood'], 1, 'a plunge downward is capped at one step');
is_same(cc_parse_reply("[[mood=3]]\nx", $at2)['state']['mood'], 3, 'a single step up is allowed');
is_same(cc_parse_reply("[[mood=2]]\nx", $at2)['state']['mood'], 2, 'staying put is allowed');
$at0 = ['stage' => 1, 'mood' => 0, 'open' => []];
is_same(cc_parse_reply("[[mood=4]]\nx", $at0)['state']['mood'], 1, 'from calm, one step only');
