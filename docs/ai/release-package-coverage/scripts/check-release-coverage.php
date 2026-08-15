<?php

declare(strict_types=1);

// Static validator for release package coverage.
//
// docs/release/build-release.sh decides what ships by asking git for the
// export-ignore attribute of each tracked file. Anything NOT marked
// export-ignore is copied into the package, so a new top-level path ships by
// default: forgetting to classify it silently leaks development files into a
// release, and marking a runtime path export-ignore silently drops it.
//
// This checker compares the tracked top-level paths against the declared
// inventory in ../inventory.txt and verifies:
//
//   dev         -> every tracked file under it is export-ignored
//   runtime     -> no tracked file under it is export-ignored
//   runtime-app -> same as runtime, and the directory appears in the gettext
//                  scan list so its translatable strings reach the catalogs
//
// Usage:
//   php docs/ai/release-package-coverage/scripts/check-release-coverage.php [options]
//
// Options:
//   --root=<path>    Repository root (default: auto-detect)
//   -v|--verbose     Print the classification of every path
//   --help           Show this help

const INVENTORY_RELATIVE = 'docs/ai/release-package-coverage/inventory.txt';
const GETTEXT_SCRIPT_RELATIVE = 'docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh';

function main(array $argv): int
{
    $opts = parseOpts($argv);
    if (!empty($opts['help'])) {
        printHelp();
        return 0;
    }

    $repo = $opts['root'] ?? autoDetectRepo();
    if ($repo === null || !is_dir($repo . '/.git')) {
        fwrite(STDERR, "Cannot resolve repo root (looking for .git). Pass --root=...\n");
        return 2;
    }

    $inventory = parseInventory($repo . '/' . INVENTORY_RELATIVE);
    if ($inventory === null) {
        fwrite(STDERR, 'Cannot read ' . INVENTORY_RELATIVE . "\n");
        return 2;
    }

    $actual = collectExportState($repo);
    if ($actual === null) {
        fwrite(STDERR, "Cannot enumerate tracked files via git.\n");
        return 2;
    }

    $gettextDirs = parseGettextScanList($repo . '/' . GETTEXT_SCRIPT_RELATIVE);

    $errors = [];
    $warnings = [];

    foreach ($actual as $path => $state) {
        $kind = $inventory[$path] ?? null;

        if ($kind === null) {
            $errors[] = sprintf(
                '%s is tracked but not classified in %s. Add a "runtime", "runtime-app", or "dev" line for it; unclassified paths ship in the release package by default.',
                $path,
                INVENTORY_RELATIVE,
            );
            continue;
        }

        if ($state['ignored'] > 0 && $state['shipped'] > 0) {
            $errors[] = sprintf(
                '%s is classified "%s" but mixes export-ignored and shipped files (%d ignored, %d shipped). Split the path or fix the .gitattributes rule.',
                $path,
                $kind,
                $state['ignored'],
                $state['shipped'],
            );
            continue;
        }

        if ($kind === 'dev' && $state['shipped'] > 0) {
            $errors[] = sprintf(
                '%s is classified "dev" but is not export-ignored, so %d file(s) would ship in the release package. Add an export-ignore rule to .gitattributes.',
                $path,
                $state['shipped'],
            );
        }

        if ($kind !== 'dev' && $state['ignored'] > 0) {
            $errors[] = sprintf(
                '%s is classified "%s" but is export-ignored, so %d file(s) are missing from the release package. Remove the .gitattributes rule or reclassify it as "dev".',
                $path,
                $kind,
                $state['ignored'],
            );
        }

        if ($kind === 'runtime-app' && $gettextDirs !== null && !in_array($path, $gettextDirs, true)) {
            $errors[] = sprintf(
                '%s is classified "runtime-app" but does not appear in the scan list in %s, so its translatable strings never reach the gettext catalogs.',
                $path,
                GETTEXT_SCRIPT_RELATIVE,
            );
        }

        if (!empty($opts['verbose'])) {
            printf("%-12s %s\n", $kind, $path);
        }
    }

    foreach (array_keys($inventory) as $path) {
        if (!isset($actual[$path])) {
            $warnings[] = sprintf('%s is listed in the inventory but is no longer tracked; remove the stale line.', $path);
        }
    }

    if ($gettextDirs === null) {
        $warnings[] = 'Could not parse the scan list from ' . GETTEXT_SCRIPT_RELATIVE . '; runtime-app coverage was not verified.';
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
        printf("All %d tracked top-level path(s) are classified and consistent.\n", count($actual));
    }
    fwrite(STDERR, "\n$errorCount error(s), $warningCount warning(s).\n");

    return $errorCount > 0 ? 1 : 0;
}

function printHelp(): void
{
    $self = 'docs/ai/release-package-coverage/scripts/check-release-coverage.php';
    echo "Static validator for release package classification of top-level paths.\n\n";
    echo "Usage:\n  php $self [options]\n\n";
    echo "Options:\n";
    echo "  --root=<path>   Repository root (default: auto-detect)\n";
    echo "  -v, --verbose   Print the classification of every path\n";
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
        if (is_dir($dir . '/.git')) {
            return $dir;
        }
        $dir = dirname($dir);
    }
    return null;
}

/**
 * @return array<string, string>|null path => kind
 */
function parseInventory(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }
    $inventory = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = preg_split('/\s+/', $line, 2);
        if ($parts === false || count($parts) !== 2) {
            continue;
        }
        [$kind, $path] = $parts;
        if (!in_array($kind, ['runtime', 'runtime-app', 'dev'], true)) {
            continue;
        }
        $inventory[trim($path)] = $kind;
    }
    return $inventory;
}

/**
 * Aggregate the export-ignore attribute of every tracked file per top-level path.
 *
 * @return array<string, array{ignored: int, shipped: int}>|null
 */
function collectExportState(string $repo): ?array
{
    $cmd = sprintf(
        'cd %s && git ls-files -z | git check-attr --stdin -z export-ignore',
        escapeshellarg($repo),
    );
    $output = shell_exec($cmd);
    if (!is_string($output) || $output === '') {
        return null;
    }

    $fields = explode("\0", $output);
    $state = [];
    // git check-attr -z emits repeating (path, attribute, value) triples.
    for ($i = 0; $i + 2 < count($fields); $i += 3) {
        $path = $fields[$i];
        $value = $fields[$i + 2];
        if ($path === '') {
            continue;
        }
        $top = explode('/', $path)[0];
        if (!isset($state[$top])) {
            $state[$top] = ['ignored' => 0, 'shipped' => 0];
        }
        if ($value === 'set') {
            $state[$top]['ignored']++;
        } else {
            $state[$top]['shipped']++;
        }
    }
    ksort($state);
    return $state;
}

/**
 * Extract the directory list the gettext catalog refresh scans.
 *
 * @return list<string>|null
 */
function parseGettextScanList(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }
    $contents = (string) file_get_contents($file);
    if (preg_match('/^\s*find\s+([a-z0-9_ \/-]+?)\s+-type\s+f\s+-name/mi', $contents, $m) !== 1) {
        return null;
    }
    $dirs = preg_split('/\s+/', trim($m[1]));
    if ($dirs === false) {
        return null;
    }
    return array_values(array_filter($dirs, static fn(string $d): bool => $d !== '' && $d !== '.'));
}

exit(main($argv));
