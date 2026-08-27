<?php
/**
 * Upload this into the app directory on the server, open it in a browser, read
 * the output, then DELETE it. It checks everything that can be wrong with an
 * installation, in the order that makes the failures easiest to fix.
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

$ok = true;
function say(string $label, bool $good, string $detail = ''): void
{
    global $ok;
    $ok = $ok && $good;
    printf("%-34s %-4s %s\n", $label, $good ? 'OK' : 'FAIL', $detail);
}

echo "consulting client — installation check\n" . str_repeat('=', 60) . "\n\n";

echo "PHP\n";
say('version 8.1 or newer', PHP_VERSION_ID >= 80100, PHP_VERSION);
say('cURL', function_exists('curl_init'));
say('mbstring', function_exists('mb_strtolower'));
say('JSON', function_exists('json_encode'));
say('max_execution_time >= 120', (int)ini_get('max_execution_time') >= 120 || (int)ini_get('max_execution_time') === 0,
    ini_get('max_execution_time') . 's');

echo "\nPaths\n";
$docroot = $_SERVER['DOCUMENT_ROOT'] ?? '';
echo str_pad('document root', 34) . '     ' . $docroot . "\n";

$private = @require __DIR__ . '/path.php';
say('path.php returns a path', is_string($private) && $private !== '', (string)$private);

$real = is_string($private) ? realpath($private) : false;
if ($real !== false) {
    echo str_pad('  resolves to', 34) . '     ' . $real . "\n";
}
say('private directory exists', $real !== false && is_dir($real));

if ($real === false) {
    // Almost always one of two things: the directory has not been made yet, or
    // the path was copied from an SSH prompt. On this host SSH shows
    // /home/<user>/... while PHP sees /customers/..., and PHP cannot follow the
    // first. Work out what the right answer would be and say so.
    echo "\n";
    echo "The path in path.php does not exist as far as PHP is concerned.\n";
    if (str_starts_with((string)$private, '/home/')) {
        echo "It starts with /home/, which is what SSH shows you. PHP sees this\n";
        echo "server through a different root and cannot follow that path.\n";
    }
    $webspace = dirname($docroot, 2);              // .../webspace
    $guesses  = [
        __DIR__ . '/../private',
        __DIR__ . '/../../../private/consult',
        $webspace . '/private/consult',
        dirname($docroot) . '/private/consult',
    ];
    echo "\nCandidates, from where this script is actually running:\n";
    $found = null;
    $seen  = [];
    foreach ($guesses as $g) {
        $r   = realpath($g);
        $key = $r !== false ? $r : $g;          // dedupe on where it lands, not how it was spelled
        if (isset($seen[$key])) { continue; }
        $seen[$key] = true;
        $state = $r === false ? 'does not exist' : (is_dir($r) ? 'EXISTS' : 'not a directory');
        printf("  %-15s %s\n", $state, $key);
        if ($found === null && $r !== false && is_dir($r)) { $found = $r; }
    }
    if ($found !== null) {
        echo "\nPut this in path.php:\n\n    return '" . $found . "';\n";
    } else {
        echo "\nNone of them exist yet. Create one — over SSH, using the /home path,\n";
        echo "which reaches the same place:\n\n";
        echo "    mkdir -p ~/webroots/sites/webspace/private/consult\n";
        echo "    chmod 700 ~/webroots/sites/webspace/private\n\n";
        echo "then upload lib/, personas/ and config.php into it and reload this page.\n";
    }
    exit;
}

$private = $real;
say('private is OUTSIDE the docroot',
    $docroot === '' || !str_starts_with($private . '/', rtrim($docroot, '/') . '/'),
    'anything inside the docroot is reachable by URL');
say('config.php present', is_file($private . '/config.php'));
say('src/ present', is_dir($private . '/src'));
say('personas/ present', is_dir($private . '/personas'));

if (!is_file($private . '/config.php')) {
    echo "\nStopping: the directory is there but config.php is not.\n";
    echo "Copy config.example.php to config.php inside it and fill in the API key.\n";
    exit;
}

require $private . '/src/bootstrap.php';

echo "\nConfiguration\n";
try {
    $cfg = cc_config();
    say('config loads', true);
    say('api key looks set', strlen((string)$cfg['api_key']) > 20 && !str_contains((string)$cfg['api_key'], 'REPLACE'));
    say('class code changed', $cfg['class_code'] !== 'CHANGE-ME', $cfg['class_code']);
    say('model', true, $cfg['model'] . '  via  ' . $cfg['api_base']);
    say('frustration model', true, cc_moody() ? 'on' : 'off');
    say('max turns / sessions', true, $cfg['max_turns'] . ' / ' . $cfg['max_sessions_per_student']);

    $n = 0;
    if ($cfg['students_file'] !== null) {
        say('students file present', is_file($cfg['students_file']), (string)$cfg['students_file']);
        if (is_file($cfg['students_file'])) {
            foreach (file($cfg['students_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
                if (trim($l) !== '' && $l[0] !== '#') { $n++; }
            }
        }
        say('student numbers listed', $n > 0, $n . ' entries');
    } else {
        say('students file', false, 'set to null - anyone with the code can start');
    }

    echo "\nStorage\n";
    $s = cc_data_dir('sessions');
    $t = cc_data_dir('transcripts');
    say('sessions dir writable', is_writable($s), $s);
    say('transcripts dir writable', is_writable($t));
    $probe = $t . '/.probe';
    say('can actually write a file', @file_put_contents($probe, 'x') !== false);
    @unlink($probe);
    say('data dir is not web-reachable',
        $docroot === '' || !str_starts_with(rtrim($cfg['data_dir'], '/') . '/', rtrim($docroot, '/') . '/'));

    echo "\nPersona\n";
    $p = cc_persona();
    say('persona loads', true, $p['id'] . ' — ' . $p['name']);
    say('system prompt builds', strlen(cc_system_prompt($p)) > 2000, strlen(cc_system_prompt($p)) . ' characters');
    say('rubric builds', count(cc_rubric($p)) > 15, count(cc_rubric($p)) . ' items');

    echo "\nModel (one real call, costs a fraction of a cent)\n";
    $t0 = microtime(true);
    try {
        $r = cc_llm_chat(
            [['role' => 'system', 'content' => 'Reply with the single word: reachable'],
             ['role' => 'user',   'content' => 'ping']],
            ['max_tokens' => 800]
        );
        say('model answers', trim($r['content']) !== '', sprintf('%.1fs — "%s"', microtime(true) - $t0, trim(substr($r['content'], 0, 40))));
    } catch (Throwable $e) {
        say('model answers', false, $e->getMessage());
    }
} catch (Throwable $e) {
    say('configuration', false, get_class($e) . ': ' . $e->getMessage());
}

echo "\n" . str_repeat('=', 60) . "\n";
echo $ok ? "Everything checks out. Delete this file now.\n"
         : "Something above needs fixing. Delete this file when you are done.\n";
