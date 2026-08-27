<?php
/**
 * The only server endpoint. Everything is POST JSON with an "action".
 *
 * Deliberately thin: validation and shaping here, all behaviour in lib/.
 */
declare(strict_types=1);

$private = require __DIR__ . '/path.php';
require $private . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

const CC_COOKIE = 'cc_session';

function cc_out(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function cc_fail(string $message, int $status = 400): never
{
    cc_out(['ok' => false, 'error' => $message], $status);
}

/**
 * The directory this app is served from: "/" at a document root, "/consult"
 * when it sits in a subfolder of one. Scoping the cookie to it keeps the
 * session out of every unrelated request to the rest of the site.
 */
function cc_base_path(): string
{
    $dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
    return $dir === '' || $dir === '.' ? '/' : $dir;
}

/** Remember who this is, so a closed tab does not cost forty turns of work. */
function cc_cookie_set(array $s): void
{
    setcookie(CC_COOKIE, $s['student'] . '.' . $s['sid'], [
        'expires'  => time() + 60 * 60 * 24 * 30,
        'path'     => cc_base_path(),
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** The session named by the cookie, or null. The unguessable sid is the key. */
function cc_cookie_session(): ?array
{
    $raw = $_COOKIE[CC_COOKIE] ?? '';
    if (!str_contains($raw, '.')) {
        return null;
    }
    [$student, $sid] = explode('.', $raw, 2);
    return cc_session_load(cc_clean_student($student), $sid);
}

// --------------------------------------------------------------------------

try {
    $body   = json_decode(file_get_contents('php://input') ?: '', true);
    $body   = is_array($body) ? $body : [];
    $action = (string)($body['action'] ?? '');

    if ($action === 'start') {
        $student = cc_clean_student((string)($body['student'] ?? ''));
        $code    = trim((string)($body['code'] ?? ''));

        if (!hash_equals((string)cc_config('class_code'), $code)) {
            cc_fail('That class code is not right. It is on Brightspace, with the link.');
        }
        if (!cc_student_allowed($student)) {
            cc_fail('That student number is not on the list for this course. Check it and try again.');
        }

        // An unfinished session is resumed rather than replaced.
        $s = cc_session_latest_open($student);
        if ($s === null) {
            if (cc_session_count($student) >= (int)cc_config('max_sessions_per_student', 3)) {
                cc_fail('You have used all your sessions for this exercise. Email me if you need another.');
            }
            $s = cc_chat_start($student);
        }

        $p = cc_persona($s['persona']);
        cc_cookie_set($s);
        cc_out([
            'ok'        => true,
            'name'      => $p['name'],
            'label'     => $p['label'],
            'scene'     => $p['scene'],
            'turn'      => $s['turn'],
            'max_turns' => (int)cc_config('max_turns', 50),
            'messages'  => cc_visible_messages($s, $p),
            'ended'     => (bool)$s['ended'],
            'walked_out'=> !empty($s['walked_out']),
        ]);
    }

    if ($action === 'resume') {
        $s = cc_cookie_session();
        if ($s === null) {
            cc_out(['ok' => true, 'resumed' => false]);
        }
        $p = cc_persona($s['persona']);
        cc_out([
            'ok'        => true,
            'resumed'   => true,
            'student'   => $s['student'],
            'name'      => $p['name'],
            'label'     => $p['label'],
            'scene'     => $p['scene'],
            'turn'      => $s['turn'],
            'max_turns' => (int)cc_config('max_turns', 50),
            'messages'  => cc_visible_messages($s, $p),
            'ended'     => (bool)$s['ended'],
            'walked_out'=> !empty($s['walked_out']),
        ]);
    }

    if ($action === 'message') {
        $s = cc_cookie_session();
        if ($s === null) {
            cc_fail('Your session has expired. Start again and it will pick up where you left off.', 401);
        }
        // The turn the browser thinks it is on. A mismatch means a double
        // submit or a second tab, and replaying it would corrupt the order.
        $expect = $body['turn'] ?? null;
        if ($expect !== null && (int)$expect !== (int)$s['turn']) {
            cc_fail('That message was already sent.', 409);
        }

        $r = cc_chat_send($s, (string)($body['text'] ?? ''));
        cc_out([
            'ok'         => true,
            'say'        => $r['say'],
            'turn'       => $r['turn'],
            'left'       => max(0, (int)cc_config('max_turns', 50) - $r['turn']),
            'walked_out' => $r['walked_out'],
        ]);
    }

    if ($action === 'end') {
        $s = cc_cookie_session();
        if ($s === null) {
            cc_fail('Your session has expired.', 401);
        }
        if ($s['ended']) {
            $existing = cc_data_dir('transcripts') . '/' . ($s['transcript'] ?? '');
            cc_out([
                'ok'       => true,
                'markdown' => is_file($existing) ? file_get_contents($existing) : '',
                'filename' => $s['transcript'] ?? 'transcript.md',
                'note'     => 'This session was already closed.',
            ]);
        }
        $end = cc_chat_end($s);
        cc_out([
            'ok'       => true,
            'markdown' => $end['markdown'],
            'filename' => $end['filename'],
            'note'     => $end['failed'] === null
                        ? 'Saved. Your transcript has been filed for the discussion in class.'
                        : 'Your transcript was saved, but the feedback could not be produced this time.',
        ]);
    }

    cc_fail('Unknown action.', 404);

} catch (CcUserError $e) {
    cc_fail($e->getMessage());
} catch (Throwable $e) {
    error_log('[consult] ' . $e->getMessage());
    cc_fail(
        cc_config('debug', false)
            ? get_class($e) . ': ' . $e->getMessage()
            : 'Something went wrong at our end. Wait a moment and try again; nothing has been lost.',
        500
    );
}
