<?php
/**
 * Everything that touches the disk. Sessions and transcripts live under
 * data_dir, which sits above the document root, so none of it is reachable
 * by URL whatever the web server is later configured to do.
 *
 * Session files are named <student>_<sid>.json, which makes "how many
 * sessions has this student had" a glob and needs no index.
 */
declare(strict_types=1);

function cc_data_dir(string $sub = ''): string
{
    $dir = rtrim(cc_config('data_dir'), '/') . ($sub !== '' ? '/' . $sub : '');
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('cannot create data directory: ' . $dir);
    }
    return $dir;
}

/** Write a file so a reader never sees a half-written one. */
function cc_write_atomic(string $path, string $contents): void
{
    $tmp = $path . '.' . getmypid() . '.tmp';
    if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
        throw new RuntimeException('cannot write ' . $path);
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('cannot replace ' . $path);
    }
    @chmod($path, 0600);
}

// ---------------------------------------------------------------- students

/**
 * The whitelist is what catches typos: a mistyped number would otherwise
 * produce a transcript filed under a student who does not exist.
 */
function cc_student_allowed(string $student): bool
{
    if ($student === '' || !preg_match('/^\d{5,9}$/', $student)) {
        return false;
    }
    $file = cc_config('students_file');
    if ($file === null) {
        return true;                       // whitelist disabled
    }
    if (!is_file($file)) {
        throw new RuntimeException('students file not found: ' . $file);
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (cc_clean_student($line) === $student) {
            return true;
        }
    }
    return false;
}

// ---------------------------------------------------------------- sessions

function cc_session_path(string $student, string $sid): string
{
    return cc_data_dir('sessions') . '/' . $student . '_' . $sid . '.json';
}

function cc_session_new(string $student, string $persona): array
{
    $now = time();
    return [
        'sid'       => cc_id(),
        'student'   => $student,
        'persona'   => $persona,
        'created'   => $now,
        'updated'   => $now,
        'turn'      => 0,
        'ended'     => false,
        'state'     => ['stage' => 1, 'mood' => 0, 'open' => []],
        'mood_trace'=> [],
        'messages'  => [],     // [['role'=>'assistant'|'user','content'=>...], ...]
        'cost'      => 0.0,
    ];
}

function cc_session_save(array $s): void
{
    $s['updated'] = time();
    cc_write_atomic(
        cc_session_path($s['student'], $s['sid']),
        json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function cc_session_load(string $student, string $sid): ?array
{
    if (!preg_match('/^[a-f0-9]{16,64}$/', $sid)) {
        return null;                        // never let a crafted id reach a path
    }
    $path = cc_session_path($student, $sid);
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

/** How many sessions this student has started, for the per-student rate limit. */
function cc_session_count(string $student): int
{
    return count(glob(cc_data_dir('sessions') . '/' . $student . '_*.json') ?: []);
}

// ------------------------------------------------------------- transcripts

/**
 * Identity goes in the filename, never in the body, so preparing a transcript
 * for class use is a copy and a rename rather than a read-through.
 */
function cc_transcript_filename(array $s): string
{
    return sprintf(
        '%s_s%s_%s_%s.md',
        gmdate('Y-m-d\THi', $s['created']),
        $s['student'],
        preg_replace('/[^a-z0-9-]/', '', $s['persona']),
        substr($s['sid'], 0, 6)
    );
}

function cc_transcript_save(array $s, string $markdown): string
{
    $name = cc_transcript_filename($s);
    cc_write_atomic(cc_data_dir('transcripts') . '/' . $name, $markdown);
    return $name;
}

/** The newest session this student has not finished, if any. */
function cc_session_latest_open(string $student): ?array
{
    $files = glob(cc_data_dir('sessions') . '/' . $student . '_*.json') ?: [];
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    foreach ($files as $f) {
        $data = json_decode((string)file_get_contents($f), true);
        if (is_array($data) && empty($data['ended'])) {
            return $data;
        }
    }
    return null;
}
