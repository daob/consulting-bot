<?php
/**
 * The assessor: a second, separate model call over the finished transcript.
 *
 * It is a separate call on purpose. Asking the client character to grade at the
 * end of its own conversation is what made last year's version skip the
 * evaluation; here the application makes the call, so it cannot be forgotten.
 *
 * Three things beyond the obvious, all from the evidence review:
 *  - items are shuffled, because rubric item order shifts judgements
 *  - every verdict must carry a verbatim quote, which we then check ourselves
 *  - the report is assembled in PHP, not by the model
 */
declare(strict_types=1);

/** The conversation as the assessor sees it: no state lines, no system prompt. */
function cc_transcript_text(array $s, array $p): string
{
    $lines = [];
    $n = 0;
    foreach ($s['messages'] as $m) {
        if ($m['role'] === 'user') {
            $n++;
            $lines[] = 'CONSULTANT (turn ' . $n . '): ' . trim($m['content']);
        } else {
            $lines[] = strtoupper($p['name']) . ': ' . trim($m['content']);
        }
    }
    return implode("\n\n", $lines);
}

function cc_assessor_prompt(array $p, array $items): string
{
    $rubric = '';
    foreach ($items as $it) {
        $rubric .= "- {$it['id']}: {$it['text']}";
        if (!empty($it['guidance'])) {
            $rubric .= "\n      ({$it['guidance']})";
        }
        $rubric .= "\n";
    }
    $questions = '';
    foreach ($p['questions'] as $q) {
        $questions .= "- {$q['tag']}: {$q['ask']}\n";
    }

    $prompt = <<<TXT
    You are an experienced statistical consultant reviewing a transcript of a training
    consultation, for the trainee's benefit. The client, {$p['name']}, was simulated; the
    consultant is a master's student in methodology and statistics.

    What the client came in wanting:
    {$questions}
    Judge the transcript against each rubric item below, independently.

    For every item return:
      verdict  - one of: met, partial, not_met, no_opportunity
                 Use no_opportunity only when the conversation genuinely never reached
                 the point where the item could apply. Not as a way to avoid judging.
      evidence - a quotation copied EXACTLY from the transcript, character for character,
                 that shows what you are judging. Never paraphrase, never repair, never
                 invent. If nothing in the transcript bears on the item, use "".
      comment  - one or two sentences addressed to the consultant, in the second person.
                 Say what happened and what would have been better. No praise sandwiches.

    Two mistakes to avoid, in this order of importance:
      - Partial satisfaction: do not award "met" for a fragment that gestures at the item.
      - Requirement expansion: do not mark someone down against a standard the item
        does not state. Judge the item as written.

    A short, plain, correct answer pitched at this client's level is a success, not a
    shortfall. Do not treat brevity or simplicity as a defect.

    Rubric:
    {$rubric}
    Then, separately:
      takeaways   - exactly three things this consultant should do differently next time,
                    each one sentence, concrete, drawn from what actually happened.
      moment      - the single turn number most worth re-reading, and one sentence saying why.
      would_return- true or false: on this evidence, would {$p['name']} consult this person
                    again? And one sentence of reason, in her voice, first person.

    Return JSON only, no prose around it, in exactly this shape:

    {"items":[{"id":"A1","verdict":"met","evidence":"...","comment":"..."}],
     "takeaways":["...","...","..."],
     "moment":{"turn":7,"why":"..."},
     "would_return":{"answer":true,"because":"..."}}
    TXT;

    return cc_dedent($prompt);
}

/**
 * Run the assessment.
 *
 * @return array{items:array,takeaways:array,moment:array,would_return:array,unverified:array}
 */
function cc_assess(array $s, array $p): array
{
    $items      = cc_rubric($p);
    $shuffled   = $items;
    shuffle($shuffled);                       // order shifts judgements; do not feed a fixed one
    $transcript = cc_transcript_text($s, $p);

    $reply = cc_llm_chat(
        [
            ['role' => 'system', 'content' => cc_assessor_prompt($p, $shuffled)],
            ['role' => 'user',   'content' => "TRANSCRIPT\n\n" . $transcript],
        ],
        [
            // The client speaks forty times a session; the assessor once. That
            // makes the chat model ~95% of the bill and the assessor almost
            // free, so they are worth choosing separately.
            'model'           => cc_config('model_assessor') ?: cc_config('model'),
            'max_tokens'      => cc_config('max_tokens_assessor', 8000),
            'temperature'     => cc_config('temperature_assessor', 0.2),
            'response_format' => ['type' => 'json_object'],
        ]
    );

    $data = cc_json_loose($reply['content']);
    if (!is_array($data) || !isset($data['items'])) {
        throw new CcLlmError('assessor did not return usable JSON');
    }

    // Index what came back, then walk the rubric in report order so a missing
    // item shows up as missing rather than silently disappearing.
    $byId = [];
    foreach ($data['items'] as $row) {
        if (isset($row['id'])) {
            $byId[strtoupper(trim((string)$row['id']))] = $row;
        }
    }

    $haystack   = cc_normalise($transcript);
    $out        = [];
    $unverified = [];

    foreach ($items as $it) {
        $row      = $byId[$it['id']] ?? null;
        $verdict  = strtolower(trim((string)($row['verdict'] ?? '')));
        $allowed  = ['met', 'partial', 'not_met', 'no_opportunity'];
        if (!in_array($verdict, $allowed, true)) {
            $verdict = 'missing';
        }
        $evidence = trim((string)($row['evidence'] ?? ''));
        $verified = true;
        if ($evidence !== '') {
            $verified = str_contains($haystack, cc_normalise($evidence));
            if (!$verified) {
                $unverified[] = $it['id'];
            }
        }
        $out[] = $it + [
            'verdict'  => $verdict,
            'evidence' => $evidence,
            'verified' => $verified,
            'comment'  => trim((string)($row['comment'] ?? '')),
        ];
    }

    return [
        'items'        => $out,
        'takeaways'    => array_values(array_filter(array_map('strval', (array)($data['takeaways'] ?? [])))),
        'moment'       => (array)($data['moment'] ?? []),
        'would_return' => (array)($data['would_return'] ?? []),
        'unverified'   => $unverified,
        'usage'        => $reply['usage'],
    ];
}

/** Models fence JSON, prepend apologies, and add trailing prose. Cope with it. */
function cc_json_loose(string $raw): mixed
{
    $s = trim($raw);
    if (preg_match('/```(?:json)?\s*(.*?)```/s', $s, $m)) {
        $s = $m[1];
    }
    $direct = json_decode($s, true);
    if (is_array($direct)) {
        return $direct;
    }
    $start = strpos($s, '{');
    $end   = strrpos($s, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }
    return json_decode(substr($s, $start, $end - $start + 1), true);
}
