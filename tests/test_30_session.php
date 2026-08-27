<?php
group('sessions on disk');

$s = cc_chat_start('1234567');
ok(preg_match('/^[a-f0-9]{32}$/', $s['sid']) === 1, 'session id is 32 hex characters');
is_same($s['turn'], 0, 'starts at turn zero');
is_same($s['ended'], false, 'starts open');

$again = cc_session_load('1234567', $s['sid']);
is_same($again['sid'], $s['sid'], 'round-trips through disk');
is_same(cc_session_load('7654321', $s['sid']), null, 'another student cannot load it');

is_same(cc_session_count('1234567'), 1, 'counts one session');
cc_chat_start('1234567');
is_same(cc_session_count('1234567'), 2, 'counts two');
is_same(cc_session_count('7654321'), 0, 'counts per student');

$file = cc_session_path('1234567', $s['sid']);
is_same(substr(sprintf('%o', fileperms($file)), -3), '600', 'session file is not group or world readable');

$name = cc_transcript_filename($s);
ok(str_contains($name, '_s1234567_'), 'student number goes in the filename');
ok(str_ends_with($name, '.md'), 'transcript is markdown');
