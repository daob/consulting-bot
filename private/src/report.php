<?php
/**
 * Assembles the transcript and the report as Markdown.
 *
 * Done in PHP rather than by the model: the model supplies judgements, the
 * application decides what the document looks like. That is also why a missing
 * rubric item shows up as "missing" instead of vanishing.
 *
 * No student number appears in the body. It is in the filename, so preparing a
 * transcript for class use is a copy and a rename.
 */
declare(strict_types=1);

const CC_VERDICT_LABEL = [
    'met'            => 'yes',
    'partial'        => 'partly',
    'not_met'        => 'no',
    'no_opportunity' => 'no opportunity',
    'missing'        => 'not judged',
];

function cc_report_markdown(array $s, array $p, array $a): string
{
    $md   = [];
    $md[] = '# Consulting session: ' . $p['label'];
    $md[] = sprintf(
        "- Date: %s\n- Turns: %d\n- Session: %s",
        gmdate('Y-m-d H:i', $s['created']) . ' UTC',
        $s['turn'],
        substr($s['sid'], 0, 6)
    );

    // ---- the conversation
    $md[] = "## The conversation\n";
    $conv = [];
    $n = 0;
    foreach ($s['messages'] as $m) {
        if ($m['role'] === 'user') {
            $n++;
            $conv[] = '**You (' . $n . ').** ' . trim($m['content']);
        } else {
            $conv[] = '**' . $p['name'] . '.** ' . trim($m['content']);
        }
    }
    $md[] = implode("\n\n", $conv);

    // ---- the assessment
    $md[] = "## How it went\n";
    $md[] = 'Judged against Kirk (1991), *Statistical consulting in a university*, plus '
          . 'three questions specific to this course. This is feedback, not a grade.';

    foreach (cc_rubric_blocks() as $block) {
        $rows = array_values(array_filter($a['items'], fn($i) => $i['block'] === $block));
        if (!$rows) {
            continue;
        }
        $md[] = '### ' . $block;
        $body = [];
        foreach ($rows as $it) {
            $label = CC_VERDICT_LABEL[$it['verdict']] ?? $it['verdict'];
            $line  = '**' . $label . '** — ' . $it['text'];
            if ($it['comment'] !== '') {
                $line .= "\n\n" . $it['comment'];
            }
            if ($it['evidence'] !== '') {
                $line .= "\n\n> " . str_replace("\n", ' ', $it['evidence']);
                if (!$it['verified']) {
                    $line .= "\n>\n> _This quotation does not appear in the transcript. "
                           . "Treat the judgement above with suspicion._";
                }
            }
            $body[] = $line;
        }
        $md[] = implode("\n\n", $body);
    }

    // ---- what to do differently
    if ($a['takeaways']) {
        $md[] = "## Three things to do differently\n";
        $list = '';
        foreach ($a['takeaways'] as $i => $t) {
            $list .= ($i + 1) . '. ' . $t . "\n";
        }
        $md[] = trim($list);
    }

    if (!empty($a['moment']['why'])) {
        $md[] = "## One moment to re-read\n";
        $turn = isset($a['moment']['turn']) ? 'Turn ' . (int)$a['moment']['turn'] . '. ' : '';
        $md[] = $turn . $a['moment']['why'];
    }

    if (isset($a['would_return']['answer'])) {
        $md[] = "## Would she come back?\n";
        $yes  = filter_var($a['would_return']['answer'], FILTER_VALIDATE_BOOLEAN) ? 'Yes.' : 'No.';
        $md[] = '**' . $yes . '** ' . ($a['would_return']['because'] ?? '');
    }

    // ---- the mood trace, when the affect model is switched on
    if (cc_moody() && count($s['mood_trace']) > 1) {
        $md[] = "## How her patience moved\n";
        $md[] = 'Her mood ran from 0 (comfortable) to 5 (leaving). Where it rose, look at what '
              . 'you had just said.';
        $trace = '';
        $prev  = null;
        foreach ($s['mood_trace'] as $t) {
            $bar    = str_repeat('#', (int)$t['mood']);
            $marker = ($prev !== null && $t['mood'] > $prev) ? '  <- rose here' : '';
            $trace .= sprintf("turn %2d  %d %-5s%s\n", $t['turn'], $t['mood'], $bar, $marker);
            $prev   = $t['mood'];
        }
        $md[] = "```\n" . $trace . '```';
    }

    if ($a['unverified']) {
        $md[] = "---\n\n_Note: the assessor supplied quotations for "
              . implode(', ', $a['unverified'])
              . ' that do not appear in the transcript. Those judgements are less trustworthy '
              . 'than the rest; treat them with suspicion._';
    }

    return implode("\n\n", $md) . "\n";
}
