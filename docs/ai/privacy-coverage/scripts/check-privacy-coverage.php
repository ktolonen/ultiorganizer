<?php

declare(strict_types=1);

// Static validator for privacy tooling coverage.
//
// Ultiorganizer stores personal data across many tables, and the privacy admin
// tools in lib/privacy.functions.php must reach every one of them for export,
// anonymization, and deletion to be complete. Nothing in the schema records
// which tables hold personal data, so a new table is invisible to the privacy
// flow until someone remembers it.
//
// This checker compares the tables in sql/ultiorganizer.sql against the
// declared classification in ../tables.txt and verifies:
//
//   personal        -> referenced somewhere in lib/privacy.functions.php
//   personal-exempt -> carries an explicit reason note
//   none            -> has no obviously personal column
//
// The "none" rule is a backstop: it re-derives personal columns from the schema
// so a table with an email or birthdate column cannot be quietly marked clean.
//
// Usage:
//   php docs/ai/privacy-coverage/scripts/check-privacy-coverage.php [options]
//
// Options:
//   --root=<path>    Repository root (default: auto-detect)
//   -v|--verbose     Print the classification of every table
//   --help           Show this help

const CLASSIFICATION_RELATIVE = 'docs/ai/privacy-coverage/tables.txt';
const PRIVACY_LIB_RELATIVE = 'lib/privacy.functions.php';
const SCHEMA_RELATIVE = 'sql/ultiorganizer.sql';

// Columns that identify or describe a natural person.
const PERSONAL_COLUMNS = [
    'firstname',
    'lastname',
    'email',
    'birthdate',
    'birthplace',
    'national_id',
    'password',
    'nickname',
    'ip',
    'userid',
];

function main(array $argv): int
{
    $opts = parseOpts($argv);
    if (!empty($opts['help'])) {
        printHelp();
        return 0;
    }

    $repo = $opts['root'] ?? autoDetectRepo();
    if ($repo === null || !is_file($repo . '/' . SCHEMA_RELATIVE)) {
        fwrite(STDERR, 'Cannot resolve repo root (looking for ' . SCHEMA_RELATIVE . "). Pass --root=...\n");
        return 2;
    }

    $classification = parseClassification($repo . '/' . CLASSIFICATION_RELATIVE);
    if ($classification === null) {
        fwrite(STDERR, 'Cannot read ' . CLASSIFICATION_RELATIVE . "\n");
        return 2;
    }

    $tables = parseSchemaTables($repo . '/' . SCHEMA_RELATIVE);
    if (!$tables) {
        fwrite(STDERR, 'Cannot parse any table from ' . SCHEMA_RELATIVE . "\n");
        return 2;
    }

    $privacyLib = @file_get_contents($repo . '/' . PRIVACY_LIB_RELATIVE);
    if (!is_string($privacyLib)) {
        fwrite(STDERR, 'Cannot read ' . PRIVACY_LIB_RELATIVE . "\n");
        return 2;
    }

    $errors = [];
    $warnings = [];

    foreach ($tables as $table => $columns) {
        $entry = $classification[$table] ?? null;

        if ($entry === null) {
            $errors[] = sprintf(
                '%s is in the schema but not classified in %s. Add a "personal", "personal-exempt", or "none" line so the privacy tools cannot silently miss it.',
                $table,
                CLASSIFICATION_RELATIVE,
            );
            continue;
        }

        $kind = $entry['kind'];

        if ($kind === 'personal' && !isReferenced($privacyLib, $table)) {
            $errors[] = sprintf(
                '%s is classified "personal" but is never referenced in %s, so its personal data is missing from the privacy export, anonymization, and deletion flows.',
                $table,
                PRIVACY_LIB_RELATIVE,
            );
        }

        if ($kind === 'personal-exempt' && $entry['note'] === '') {
            $errors[] = sprintf(
                '%s is classified "personal-exempt" without a reason note. Append "# <reason>" explaining why automated coverage is not required.',
                $table,
            );
        }

        if ($kind === 'none') {
            $found = array_values(array_intersect($columns, PERSONAL_COLUMNS));
            if ($found) {
                $errors[] = sprintf(
                    '%s is classified "none" but has personal column(s): %s. Reclassify it as "personal" or "personal-exempt".',
                    $table,
                    implode(', ', $found),
                );
            }
        }

        if (!empty($opts['verbose'])) {
            printf("%-16s %s\n", $kind, $table);
        }
    }

    foreach (array_keys($classification) as $table) {
        if (!isset($tables[$table])) {
            $warnings[] = sprintf('%s is classified but no longer exists in the schema; remove the stale line.', $table);
        }
    }

    if (!empty($opts['verbose'])) {
        echo "\n";
    }
    foreach ($errors as $message) {
        echo "ERROR: $message\n";
    }
    foreach ($warnings as $message) {
        echo "WARNING: $message\n";
    }

    $errorCount = count($errors);
    $warningCount = count($warnings);
    if ($errorCount === 0 && $warningCount === 0) {
        printf("All %d table(s) are classified and consistent with the privacy tools.\n", count($tables));
    }
    fwrite(STDERR, "\n$errorCount error(s), $warningCount warning(s).\n");

    return $errorCount > 0 ? 1 : 0;
}

function printHelp(): void
{
    $self = 'docs/ai/privacy-coverage/scripts/check-privacy-coverage.php';
    echo "Static validator for privacy tooling coverage of schema tables.\n\n";
    echo "Usage:\n  php $self [options]\n\n";
    echo "Options:\n";
    echo "  --root=<path>   Repository root (default: auto-detect)\n";
    echo "  -v, --verbose   Print the classification of every table\n";
    echo "  --help          Show this help\n";
}

function parseOpts(array $argv): array
{
    $opts = [];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
        } elseif ($arg === '--verbose' || $arg === '-v') {
            $opts['verbose'] = true;
        } elseif (str_starts_with($arg, '--root=')) {
            $opts['root'] = rtrim(substr($arg, 7), '/');
        }
    }
    return $opts;
}

function autoDetectRepo(): ?string
{
    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        if (is_file($dir . '/' . SCHEMA_RELATIVE)) {
            return $dir;
        }
        $dir = dirname($dir);
    }
    return null;
}

/**
 * @return array<string, array{kind: string, note: string}>|null
 */
function parseClassification(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }
    $classification = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $note = '';
        $hash = strpos($line, '#');
        if ($hash !== false) {
            $note = trim(substr($line, $hash + 1));
            $line = trim(substr($line, 0, $hash));
        }
        $parts = preg_split('/\s+/', $line, 2);
        if ($parts === false || count($parts) !== 2) {
            continue;
        }
        [$kind, $table] = $parts;
        if (!in_array($kind, ['personal', 'personal-exempt', 'none'], true)) {
            continue;
        }
        $classification[trim($table)] = ['kind' => $kind, 'note' => $note];
    }
    return $classification;
}

/**
 * @return array<string, list<string>> table => columns
 */
function parseSchemaTables(string $file): array
{
    $contents = (string) file_get_contents($file);
    $tables = [];
    if (preg_match_all('/CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`([a-z0-9_]+)`\s*\((.*?)^\)\s*ENGINE/msi', $contents, $matches, PREG_SET_ORDER) === false) {
        return [];
    }
    foreach ($matches as $match) {
        $table = $match[1];
        $body = $match[2];
        $columns = [];
        if (preg_match_all('/^\s+`([a-z0-9_]+)`\s+[a-z]/mi', $body, $cols) !== false) {
            $columns = array_map('strtolower', $cols[1]);
        }
        $tables[$table] = $columns;
    }
    ksort($tables);
    return $tables;
}

function isReferenced(string $haystack, string $table): bool
{
    return preg_match('/\b' . preg_quote($table, '/') . '\b/', $haystack) === 1;
}

exit(main($argv));
