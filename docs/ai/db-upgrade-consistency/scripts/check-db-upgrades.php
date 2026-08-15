<?php

declare(strict_types=1);

// Static validator for the database schema version contract.
//
// Three declarations must agree for a schema change to install correctly on
// both existing and fresh installations:
//
//   1. DB_VERSION in lib/database.php          - the target version CheckDB() upgrades to
//   2. upgradeNN() in sql/upgrade_db.php       - the migration steps CheckDB() dispatches
//   3. the uo_database seed in sql/ultiorganizer.sql - versions a fresh install starts with
//
// CheckDB() loops from the installed version up to DB_VERSION and runs each
// upgradeNN() that is not already recorded in uo_database. A fresh install
// therefore re-runs every upgrade above the seeded maximum, even though
// sql/ultiorganizer.sql already contains those schema changes. That is harmless
// while the upgrade is guarded (hasColumn/hasTable/...) and an error when it is not.
//
// Usage:
//   php docs/ai/db-upgrade-consistency/scripts/check-db-upgrades.php [options]
//
// Options:
//   --root=<path>    Repository root (default: auto-detect)
//   -v|--verbose     Print the parsed version declarations
//   --help           Show this help

const FIRST_TRACKED_VERSION = 46; // CheckDB() never starts below this

function main(array $argv): int
{
    $opts = parseOpts($argv);
    if (!empty($opts['help'])) {
        printHelp();
        return 0;
    }

    $repo = $opts['root'] ?? autoDetectRepo();
    if ($repo === null || !is_file($repo . '/lib/database.php')) {
        fwrite(STDERR, "Cannot resolve repo root (looking for lib/database.php). Pass --root=...\n");
        return 2;
    }

    $dbVersion = parseDbVersion($repo . '/lib/database.php');
    if ($dbVersion === null) {
        fwrite(STDERR, "Cannot parse DB_VERSION from lib/database.php\n");
        return 2;
    }

    $upgrades = parseUpgradeFunctions($repo . '/sql/upgrade_db.php');
    if (!$upgrades) {
        fwrite(STDERR, "Cannot parse any upgradeNN() from sql/upgrade_db.php\n");
        return 2;
    }

    $seeded = parseSeededVersions($repo . '/sql/ultiorganizer.sql');
    if (!$seeded) {
        fwrite(STDERR, "Cannot parse the uo_database seed from sql/ultiorganizer.sql\n");
        return 2;
    }

    $maxUpgrade = max(array_keys($upgrades));
    $maxSeeded = max($seeded);

    if (!empty($opts['verbose'])) {
        echo "DB_VERSION (lib/database.php):        $dbVersion\n";
        echo "highest upgradeNN() (upgrade_db.php): $maxUpgrade\n";
        echo "highest seeded version (schema):      $maxSeeded\n\n";
    }

    $errors = [];
    $warnings = [];

    if ($maxUpgrade > $dbVersion) {
        $errors[] = sprintf(
            'upgrade%d() exists but DB_VERSION is %d; CheckDB() stops at DB_VERSION, so that upgrade never runs. Bump DB_VERSION in lib/database.php.',
            $maxUpgrade,
            $dbVersion,
        );
    } elseif ($maxUpgrade < $dbVersion) {
        $warnings[] = sprintf(
            'DB_VERSION is %d but the highest upgrade function is upgrade%d(); versions %s have no migration step.',
            $dbVersion,
            $maxUpgrade,
            rangeLabel($maxUpgrade + 1, $dbVersion),
        );
    }

    if ($maxSeeded !== $dbVersion) {
        $errors[] = sprintf(
            'sql/ultiorganizer.sql seeds uo_database up to version %d but DB_VERSION is %d; a fresh install re-runs %s on a schema that already contains those changes. Add the missing version rows to the uo_database INSERT.',
            $maxSeeded,
            $dbVersion,
            rangeLabel($maxSeeded + 1, $dbVersion),
        );
    }

    $missingSeed = [];
    for ($v = FIRST_TRACKED_VERSION; $v <= $maxSeeded; $v++) {
        if (!in_array($v, $seeded, true)) {
            $missingSeed[] = $v;
        }
    }
    if ($missingSeed) {
        $errors[] = sprintf(
            'sql/ultiorganizer.sql is missing uo_database seed rows for version(s) %s; a fresh install re-runs those upgrades.',
            implode(', ', $missingSeed),
        );
    }

    // An unguarded upgrade above the seed is the combination that actually breaks
    // a fresh install: the schema file already has the change, and the migration
    // will try to apply it again.
    foreach ($upgrades as $version => $body) {
        if ($version <= $maxSeeded) {
            continue;
        }
        if (!isGuarded($body)) {
            $errors[] = sprintf(
                'upgrade%d() has no idempotence guard (hasColumn/hasTable/hasIndex/IF NOT EXISTS) and sits above the seeded version %d; a fresh install will re-apply it and can fail.',
                $version,
                $maxSeeded,
            );
        } else {
            $warnings[] = sprintf(
                'upgrade%d() runs again on fresh installs because the seed stops at %d. It is guarded, so this is currently harmless.',
                $version,
                $maxSeeded,
            );
        }
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
        echo "Schema version contract is consistent (DB_VERSION=$dbVersion).\n";
    }
    fwrite(STDERR, "\n$errorCount error(s), $warningCount warning(s).\n");

    return $errorCount > 0 ? 1 : 0;
}

function printHelp(): void
{
    $self = 'docs/ai/db-upgrade-consistency/scripts/check-db-upgrades.php';
    echo "Static validator for the DB_VERSION / upgradeNN() / uo_database seed contract.\n\n";
    echo "Usage:\n  php $self [options]\n\n";
    echo "Options:\n";
    echo "  --root=<path>   Repository root (default: auto-detect)\n";
    echo "  -v, --verbose   Print the parsed version declarations\n";
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
        if (is_file($dir . '/lib/database.php')) {
            return $dir;
        }
        $dir = dirname($dir);
    }
    return null;
}

function parseDbVersion(string $file): ?int
{
    $contents = (string) file_get_contents($file);
    if (preg_match('/define\(\s*[\'"]DB_VERSION[\'"]\s*,\s*(\d+)\s*\)/', $contents, $m) === 1) {
        return (int) $m[1];
    }
    return null;
}

/**
 * @return array<int, string> version => function body
 */
function parseUpgradeFunctions(string $file): array
{
    $contents = (string) file_get_contents($file);
    $upgrades = [];
    if (preg_match_all('/^function\s+upgrade(\d+)\s*\(/m', $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
        return [];
    }
    $count = count($matches[0]);
    for ($i = 0; $i < $count; $i++) {
        $version = (int) $matches[1][$i][0];
        $start = (int) $matches[0][$i][1];
        $end = $i + 1 < $count ? (int) $matches[0][$i + 1][1] : strlen($contents);
        $upgrades[$version] = substr($contents, $start, $end - $start);
    }
    ksort($upgrades);
    return $upgrades;
}

/**
 * @return list<int>
 */
function parseSeededVersions(string $file): array
{
    $contents = (string) file_get_contents($file);
    if (preg_match('/INSERT\s+(?:IGNORE\s+)?INTO\s+`?uo_database`?[^;]*;/is', $contents, $m) !== 1) {
        return [];
    }
    if (preg_match_all('/\(\s*(\d+)\s*,/', $m[0], $rows) === false) {
        return [];
    }
    $versions = array_map('intval', $rows[1]);
    sort($versions);
    return array_values(array_unique($versions));
}

function isGuarded(string $body): bool
{
    return preg_match('/\b(hasColumn|hasTable|hasIndex|columnExists|tableExists)\s*\(/i', $body) === 1
        || stripos($body, 'IF NOT EXISTS') !== false
        || stripos($body, 'IF EXISTS') !== false;
}

function rangeLabel(int $from, int $to): string
{
    if ($from > $to) {
        return 'no versions';
    }
    if ($from === $to) {
        return "upgrade$from()";
    }
    return "upgrade$from()..upgrade$to()";
}

exit(main($argv));
