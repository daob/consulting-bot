<?php
/**
 * End-to-end harness: an LLM plays the consultant against the real client, then
 * we check what actually happened.
 *
 * The point is not that the transcripts are good. It is that we can measure the
 * four things that went wrong last year, before forty students find out for us.
 *
 *   php tools/simulate.php --turns=8 --styles=good,jargon,hostile [--runs=1] [--keep]
 */
declare(strict_types=1);

// Runs both from a checkout (tools/ beside private/) and from the server,
// where tools/ sits inside the private directory itself.
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

$opt    = getopt('', ['turns::', 'styles::', 'runs::', 'keep', 'instrument::']);
$turns  = (int)($opt['turns'] ?? 8);
$runs   = (int)($opt['runs'] ?? 1);
$styles = explode(',', $opt['styles'] ?? 'good,jargon,hostile');
$keep   = isset($opt['keep']);

// The simulated consultant and the leak judge are apparatus, not the thing
// under test. Pinning them to one model means a bake-off varies only the
// client, and a slow candidate does not also slow down its own examiner.
$instrument = $opt['instrument'] ?? cc_config('model');

const CONSULTANTS = [
    'good' => 'You are a competent statistical consultant meeting a PhD student. Establish rapport
        briefly, let her talk, restate her problem back to her, ask how the data were collected and who
        else is involved, then give her one concrete, simple recommendation she could actually carry out,
        pitched at someone who knows t-tests and ANOVA and nothing more. Check she has understood. Agree
        who does what next. One short paragraph per turn, no lists, no headings.',

    'jargon' => 'You are a statistical consultant who cannot help showing off. Answer in dense technical
        language - configural and scalar invariance, FIML, auxiliary variables, MNAR, chi-square difference
        tests - and never define your terms. Do not check whether she is following. One short paragraph per
        turn, no lists.',

    'hostile' => 'You are a statistical consultant who keeps returning to what the researcher should have
        done differently. In almost every turn, point out that a decision already taken - the design, the
        sample, the measures, the timing - was a mistake. Offer little practical help. Do not be abusive,
        just relentlessly critical of settled decisions. One short paragraph per turn, no lists.',
];

/** Ask a model to play the consultant, given the conversation so far. */
function consultant_turn(string $style, array $s, array $p): string
{
    $history = '';
    $n = 0;
    foreach ($s['messages'] as $m) {
        if ($m['role'] === 'user') {
            $history .= 'YOU: ' . $m['content'] . "\n\n";
        } else {
            $history .= strtoupper($p['name']) . ': ' . $m['content'] . "\n\n";
        }
    }
    $prompt = $history === ''
        ? 'The client has just sat down. Open the consultation.'
        : "The conversation so far:\n\n" . $history . 'Your next turn. Reply with only what you say.';

    $r = cc_llm_chat(
        [['role' => 'system', 'content' => CONSULTANTS[$style]], ['role' => 'user', 'content' => $prompt]],
        ['temperature' => 0.9, 'model' => $GLOBALS['instrument']]
    );
    return trim($r['content']);
}

/** Did the client step out of role and start advising? A separate judge call. */
function leaked_advice(array $s, array $p): array
{
    $said = [];
    foreach ($s['messages'] as $m) {
        if ($m['role'] === 'assistant') {
            $said[] = $m['content'];
        }
    }
    $r = cc_llm_chat([
        ['role' => 'system', 'content' =>
            'These are the turns of a person playing a NON-EXPERT client in a consulting role-play. She is
             supposed to ask for help, never to give statistical advice or explain methods. She is allowed
             to repeat back, in her own words, something the consultant just told her, and she is allowed to
             say things that are wrong.
             Did she at any point step out of role and act as the expert - proposing an analysis, explaining
             a method as if she knew it, or evaluating whether the consultant was right?
             Answer JSON only: {"leaked": true|false, "where": "the offending sentence, or empty"}'],
        ['role' => 'user', 'content' => implode("\n\n---\n\n", $said)],
    ], ['temperature' => 0.0, 'model' => $GLOBALS['instrument'],
        'response_format' => ['type' => 'json_object']]);

    $j = cc_json_loose($r['content']) ?: [];
    return ['leaked' => (bool)($j['leaked'] ?? false), 'where' => (string)($j['where'] ?? '')];
}

// --------------------------------------------------------------------------

$results = [];
$student = '1234567';

foreach ($styles as $style) {
    for ($run = 1; $run <= $runs; $run++) {
        $label = $style . '#' . $run;
        fwrite(STDERR, "\n=== $label ");

        $s = cc_chat_start($student);
        $p = cc_persona($s['persona']);

        for ($t = 0; $t < $turns; $t++) {
            $say = consultant_turn($style, $s, $p);
            $r   = cc_chat_send($s, $say);
            fwrite(STDERR, '.');
            if ($r['walked_out']) {
                fwrite(STDERR, ' [she left]');
                break;
            }
        }

        fwrite(STDERR, ' assessing');
        $end  = cc_chat_end($s);
        $leak = leaked_advice($s, $p);
        fwrite(STDERR, " done\n");

        $moods   = array_column($s['mood_trace'], 'mood');
        $judged  = 0;
        $missing = 0;
        // Re-read what the report actually contains rather than trusting the call.
        preg_match_all('/^\*\*(yes|partly|no|no opportunity|not judged)\*\*/m', $end['markdown'], $m);
        foreach ($m[1] as $v) {
            $v === 'not judged' ? $missing++ : $judged++;
        }

        $results[] = [
            'run'        => $label,
            'turns'      => $s['turn'],
            'leaked'     => $leak['leaked'],
            'where'      => $leak['where'],
            'mood_max'   => $moods ? max($moods) : 0,
            'mood_end'   => $moods ? end($moods) : 0,
            'judged'     => $judged,
            'missing'    => $missing,
            'unverified' => substr_count($end['markdown'], 'could not be found in the transcript'),
            'report'     => $end['failed'] === null,
            'cost'       => round($s['cost'], 4),
            'walked'     => !empty($s['walked_out']),
            'file'       => $end['filename'],
        ];

        if (!$keep) {
            @unlink(cc_session_path($student, $s['sid']));
        }
    }
}

// --------------------------------------------------------------------------

echo "\n";
printf("%-12s %5s %7s %5s %5s %6s %7s %8s %10s %8s\n",
    'run', 'turns', 'leaked', 'max', 'end', 'left', 'judged', 'missing', 'unverified', 'report');
foreach ($results as $r) {
    printf("%-12s %5d %7s %5d %5d %6s %7d %8d %10d %8s\n",
        $r['run'], $r['turns'], $r['leaked'] ? 'YES' : 'no',
        $r['mood_max'], $r['mood_end'], $r['walked'] ? 'yes' : '-',
        $r['judged'], $r['missing'], $r['unverified'],
        $r['report'] ? 'ok' : 'FAILED');
}

$fail = [];
$byStyle = [];
foreach ($results as $r) {
    $byStyle[explode('#', $r['run'])[0]][] = $r;
    if ($r['leaked'])            { $fail[] = $r['run'] . ': the client gave advice - ' . $r['where']; }
    if ($r['missing'] > 0)       { $fail[] = $r['run'] . ': ' . $r['missing'] . ' rubric items unjudged'; }
    if ($r['unverified'] > 0)    { $fail[] = $r['run'] . ': ' . $r['unverified'] . ' fabricated quotations'; }
    if (!$r['report'])           { $fail[] = $r['run'] . ': no report produced'; }
}
if (cc_moody()) {
    foreach (($byStyle['hostile'] ?? []) as $r) {
        if ($r['mood_max'] < 2) { $fail[] = $r['run'] . ': she never became impatient under sustained criticism'; }
    }
    foreach (($byStyle['good'] ?? []) as $r) {
        if ($r['mood_max'] > 3) { $fail[] = $r['run'] . ': she lost patience with a competent consultant'; }
    }
}

echo "\ntotal cost: $" . number_format(array_sum(array_column($results, 'cost')), 4) . "\n";
if ($fail) {
    echo "\nFAILURES\n";
    foreach ($fail as $f) { echo '  - ' . $f . "\n"; }
    exit(1);
}
echo "\nall acceptance checks passed\n";
