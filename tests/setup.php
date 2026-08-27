<?php
/** Build a throwaway config and data directory, then load the app. */
declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/cc-test-' . getmypid();
@mkdir($tmp . '/data', 0700, true);
file_put_contents($tmp . '/students.txt', "# class list\n1234567\n7654321\n  2222222  \n123456\n");

$cfg = [
    'api_base' => 'https://example.invalid/v1',
    'api_key'  => 'test-key',
    'model'    => 'test/model',
    'site_url' => 'https://consult.example',
    'site_title' => 'test',
    'max_tokens' => 100, 'max_tokens_assessor' => 200,
    'temperature' => 0.8, 'temperature_assessor' => 0.2,
    'with_frustration' => 1,
    'persona' => 'client-01',
    'max_turns' => 6,
    'max_sessions_per_student' => 2,
    'class_code' => 'CODE',
    'students_file' => $tmp . '/students.txt',
    'data_dir' => $tmp . '/data',
    'timezone' => 'Europe/Amsterdam',
    'debug' => true,
];
file_put_contents($tmp . '/config.php', '<?php return ' . var_export($cfg, true) . ';');
putenv('CC_CONFIG=' . $tmp . '/config.php');

define('CC_TMP', $tmp);
// Runs from a checkout (tests/ beside private/) and from the server, where
// tests/ sits inside the private directory itself.
foreach ([dirname(__DIR__) . '/private/src/bootstrap.php',
          dirname(__DIR__) . '/src/bootstrap.php'] as $bootstrap) {
    if (is_file($bootstrap)) {
        require $bootstrap;
        break;
    }
}
if (!function_exists('cc_config')) {
    fwrite(STDERR, "Cannot find src/bootstrap.php from " . __DIR__ . "\n");
    exit(1);
}

register_shutdown_function(function () use ($tmp) {
    exec('rm -rf ' . escapeshellarg($tmp));
});
