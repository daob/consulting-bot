<?php
group('student whitelist and path safety');

ok(cc_student_allowed('1234567'), 'accepts a listed number');
ok(cc_student_allowed('2222222'), 'accepts a listed number with stray spaces in the file');
ok(cc_student_allowed('123456'), 'accepts a six-digit number - three of the real class have one');
ok(!cc_student_allowed('9999999'), 'rejects a number not on the list');
ok(!cc_student_allowed('123'), 'rejects something too short to be a student number');
ok(!cc_student_allowed(''), 'rejects empty');
ok(!cc_student_allowed('12a4567'), 'rejects letters');

is_same(cc_clean_student(' 12 34-567 '), '1234567', 'cleans punctuation and spaces');
is_same(cc_clean_student('../../etc/passwd'), '', 'a path traversal attempt cleans to nothing');
ok(!cc_student_allowed(cc_clean_student('../../etc/passwd')), 'and is then rejected');

// A crafted session id must never reach the filesystem.
is_same(cc_session_load('1234567', '../../../etc/passwd'), null, 'refuses a traversing session id');
is_same(cc_session_load('1234567', 'nothex!!'), null, 'refuses a non-hex session id');
