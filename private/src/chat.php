<?php
/**
 * The conversation itself, with no HTTP in it, so it can be driven from tests
 * and from tools/simulate.php as easily as from the browser.
 */
declare(strict_types=1);

function cc_chat_start(string $student, ?string $personaId = null): array
{
    $p = cc_persona($personaId);
    $s = cc_session_new($student, $p['id']);
    cc_session_save($s);
    return $s;
}

/**
 * Messages for one generation: the system prompt, the conversation so far, and
 * the reminder last. Recency is the point - by turn thirty the system prompt is
 * a long way back, and the reminder is what keeps the client in role.
 */
function cc_build_messages(array $s, array $p, string $next): array
{
    $msgs = [['role' => 'system', 'content' => cc_system_prompt($p)]];
    foreach ($s['messages'] as $m) {
        $msgs[] = ['role' => $m['role'], 'content' => $m['content']];
    }
    $msgs[] = ['role' => 'user',   'content' => $next];
    $msgs[] = ['role' => 'system', 'content' => cc_turn_reminder($p)];
    return $msgs;
}

/**
 * One exchange. The session is updated in place and saved.
 *
 * @return array{say:string,state:array,turn:int,had_state:bool}
 */
function cc_chat_send(array &$s, string $text): array
{
    if ($s['ended']) {
        throw new CcUserError('This session has already been closed.');
    }
    if (!empty($s['walked_out'])) {
        throw new CcUserError('She has left. Close the session to get your feedback.');
    }
    $text = trim($text);
    if ($text === '') {
        throw new CcUserError('Say something first.');
    }
    if (mb_strlen($text) > 4000) {
        throw new CcUserError('That message is too long. Keep it under 4000 characters.');
    }
    if ($s['turn'] >= (int)cc_config('max_turns', 50)) {
        throw new CcUserError('You have reached the limit for this session. Close it to get your feedback.');
    }

    $p     = cc_persona($s['persona']);
    $reply = cc_llm_chat(cc_build_messages($s, $p, $text));
    $parsed = cc_parse_reply($reply['content'], $s['state']);

    // A reply that is nothing but a state line would show as an empty bubble.
    if ($parsed['say'] === '') {
        $parsed['say'] = '...';
    }

    $s['turn']++;
    $s['messages'][] = ['role' => 'user',      'content' => $text];
    $s['messages'][] = ['role' => 'assistant', 'content' => $parsed['say']];
    $s['state']      = $parsed['state'];
    $s['cost']      += (float)($reply['usage']['cost'] ?? 0);

    if (cc_moody()) {
        $mood = (int)($s['state']['mood'] ?? 0);
        $s['mood_trace'][] = ['turn' => $s['turn'], 'mood' => $mood];
        // At 5 she has said she is leaving. Letting the conversation continue
        // past that produces stage directions and teaches the wrong lesson:
        // the consequence should be that the meeting is over.
        if ($mood >= 5) {
            $s['walked_out'] = true;
        }
    }

    cc_session_save($s);

    return [
        'say'        => $parsed['say'],
        'state'      => $s['state'],
        'turn'       => $s['turn'],
        'had_state'  => $parsed['had_state'],
        'walked_out' => !empty($s['walked_out']),
    ];
}

/**
 * Close the session, assess it, write the transcript.
 *
 * The assessment runs here, in the application, which is why it cannot be
 * skipped. If the assessor call fails the session still closes and the
 * transcript is still written, without the report - losing the conversation
 * would be much worse than losing the feedback.
 *
 * @return array{markdown:string,filename:string,failed:?string}
 */
function cc_chat_end(array &$s): array
{
    $p = cc_persona($s['persona']);

    if ($s['turn'] === 0) {
        throw new CcUserError('There is nothing to assess yet.');
    }

    $failed = null;
    try {
        $a = cc_assess($s, $p);
    } catch (Throwable $e) {
        $failed = $e->getMessage();
        $a = ['items' => [], 'takeaways' => [], 'moment' => [], 'would_return' => [], 'unverified' => []];
        foreach (cc_rubric($p) as $it) {
            $a['items'][] = $it + ['verdict' => 'missing', 'evidence' => '', 'verified' => true, 'comment' => ''];
        }
    }

    $s['ended'] = true;
    $md = cc_report_markdown($s, $p, $a);
    if ($failed !== null) {
        $md .= "\n---\n\n_The automatic feedback could not be produced this time "
             . "(" . $failed . "). The transcript above is complete and has been saved._\n";
    }

    $name = cc_transcript_save($s, $md);
    $s['transcript'] = $name;
    cc_session_save($s);

    return ['markdown' => $md, 'filename' => $name, 'failed' => $failed];
}

/** The conversation as the browser should render it. Never the hidden state. */
function cc_visible_messages(array $s, array $p): array
{
    $out = [];
    foreach ($s['messages'] as $m) {
        $out[] = [
            'who'  => $m['role'] === 'user' ? 'you' : 'client',
            'name' => $m['role'] === 'user' ? 'You' : $p['name'],
            'text' => $m['content'],
        ];
    }
    return $out;
}
