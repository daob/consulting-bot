<?php
group('system prompt assembly');

$p      = cc_persona('client-01');
$prompt = cc_system_prompt($p);

ok(str_contains($prompt, 'Sanne'), 'names the client');
ok(str_contains($prompt, 'training simulator'), 'frames the task as a simulator');
ok(str_contains($prompt, 'Realism is the helpful act'), 'reframes realism as helpfulness');
ok(str_contains($prompt, 'You ask; you do not advise'), 'forbids advising');
ok(str_contains($prompt, 'p-value above .05'), 'carries the misconceptions');
ok(str_contains($prompt, 'measurement invariance'), 'carries her first question');
ok(str_contains($prompt, 'great question'), 'bans the tells');
ok(str_contains($prompt, '[[stage='), 'specifies the state line');
ok(!str_contains($prompt, "\n    #"), 'heredoc indentation is stripped');

ok(str_contains($prompt, 'Your patience'), 'includes the affect model when it is on');
ok(str_contains($prompt, 'mood=<0-5>'), 'state line asks for mood when it is on');

$r = cc_turn_reminder($p);
ok(str_contains($r, 'Sanne'), 'reminder names the client');
ok(str_contains($r, 'do not give advice'), 'reminder restates the core rule');
ok(strlen($r) < 400, 'reminder is short enough to be cheap on every turn');
