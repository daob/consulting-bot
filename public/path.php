<?php
/**
 * Where the private half of the application lives.
 *
 * The repository is laid out as two halves, public/ and private/, that mirror
 * how they are deployed. This file is the one bridge between them, and it
 * works out the answer rather than being configured, because the alternative
 * caused real trouble: this host presents the same directory under two
 * absolute paths (SSH shows /home/<user>/..., Apache sees /customers/...), and
 * PHP can only follow one of them. A path relative to this file is always
 * resolved in PHP's own view of the filesystem, so the question never arises.
 *
 * Candidates are tried in order and the first directory that exists wins:
 *
 *   ../private                   the repository, checked out as-is.
 *                                This is what `make serve` uses.
 *
 *   ../../../private/consult     the daob.nl deployment: public/ is uploaded to
 *                                <docroot>/consult/ and private/ to
 *                                <webspace>/private/consult/.
 *
 * Deploying somewhere else? Add a line. check-install.php prints which one won.
 */
declare(strict_types=1);

foreach ([
    __DIR__ . '/../private',
    __DIR__ . '/../../../private/consult',
] as $candidate) {
    if (is_dir($candidate)) {
        return realpath($candidate) ?: $candidate;
    }
}

// Nothing found. Return the first candidate so the error names something real.
return __DIR__ . '/../private';
