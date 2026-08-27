<?php
/**
 * Loads configuration and the rest of lib/. Every entry point requires this
 * file first and nothing else.
 */
declare(strict_types=1);

const CC_ROOT = __DIR__ . '/..';

/** Read config once. Fails loudly: a half-configured app should not serve. */
function cc_config(?string $key = null, mixed $default = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        // CC_CONFIG lets the tests point at a throwaway config.
        $path = getenv('CC_CONFIG') ?: CC_ROOT . '/config.php';
        if (!is_file($path)) {
            throw new RuntimeException('config.php not found. Copy config.example.php to config.php.');
        }
        $cfg = require $path;
        if (!is_array($cfg)) {
            throw new RuntimeException('config.php must return an array.');
        }
        date_default_timezone_set($cfg['timezone'] ?? 'Europe/Amsterdam');
    }
    if ($key === null) {
        return $cfg;
    }
    return array_key_exists($key, $cfg) ? $cfg[$key] : $default;
}

require_once __DIR__ . '/llm.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/persona.php';
require_once __DIR__ . '/rubric.php';
require_once __DIR__ . '/assess.php';
require_once __DIR__ . '/report.php';
require_once __DIR__ . '/chat.php';

/** A short, URL-safe, unguessable id. */
function cc_id(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

/** Digits only. Student numbers are the only user input that reaches a path. */
function cc_clean_student(string $raw): string
{
    return preg_replace('/\D/', '', $raw) ?? '';
}

/**
 * Fold a string down to what it says, for comparison only - never for display.
 *
 * Assessors quote faithfully but not mechanically: they capitalise the first
 * letter of a fragment taken from mid-sentence, straighten quotation marks, and
 * reflow whitespace. None of that makes the quotation invented, so none of it
 * should be reported as invented.
 */
function cc_normalise(string $s): string
{
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    // Straighten quotes and dashes; models rewrite these silently.
    $s = strtr($s, [
        "\u{2018}" => "'", "\u{2019}" => "'", "\u{201C}" => '"', "\u{201D}" => '"',
        "\u{2013}" => '-', "\u{2014}" => '-', "\u{2026}" => '...', "\u{00A0}" => ' ',
    ]);
    return trim(mb_strtolower($s));
}

/** Errors the caller is allowed to show the user. */
class CcUserError extends RuntimeException {}
