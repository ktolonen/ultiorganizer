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
// Agreeing version numbers are not enough on their own: a seeded version tells a
// fresh install "already applied", so any schema change behind it must be present
// in sql/ultiorganizer.sql or it is never applied at all. The checker therefore
// also replays the upgrades in order - tracking creates against drops - and
// verifies every surviving table, column, and index exists in the schema file.
// Intentional divergences live in ../expected-divergences.txt.
//
// Usage:
//   php docs/ai/db-upgrade-consistency/scripts/check-db-upgrades.php [options]
//
// Options:
//   --root=<path>    Repository root (default: auto-detect)
//   -v|--verbose     Print the parsed version declarations and replay totals
//   --help           Show this help

const FIRST_TRACKED_VERSION = 46; // CheckDB() never starts below this
const DIVERGENCES_RELATIVE = 'docs/ai/db-upgrade-consistency/expected-divergences.txt';

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

    // Replay the upgrade chain and confirm what it leaves behind is in the schema.
    $expected = replayUpgrades($upgrades);
    $divergences = parseDivergences($repo . '/' . DIVERGENCES_RELATIVE);
    $schema = (string) file_get_contents($repo . '/sql/ultiorganizer.sql');

    foreach ($divergences as $kind => $entries) {
        foreach ($entries as $name => $note) {
            if ($note === '') {
                $errors[] = sprintf(
                    '%s %s is listed in %s without a reason note. Append "# <reason>" explaining why the fresh-install schema omits it.',
                    $kind,
                    $name,
                    DIVERGENCES_RELATIVE,
                );
            }
        }
    }

    if (!empty($opts['verbose'])) {
        printf(
            "replayed %d table(s), %d column(s), %d index(es) from %d upgrade(s)\n\n",
            count($expected['tables']),
            count($expected['columns']),
            count($expected['indexes']),
            count($upgrades),
        );
    }

    foreach ($expected['tables'] as $table => $version) {
        if (isset($divergences['table'][$table])) {
            continue;
        }
        if (schemaTableBody($schema, $table) === null) {
            $errors[] = sprintf(
                'upgrade%d() creates table %s but sql/ultiorganizer.sql does not, so a fresh install never gets it. Add the table to the schema, or record it in %s.',
                $version,
                $table,
                DIVERGENCES_RELATIVE,
            );
        }
    }

    foreach ($expected['columns'] as $key => $version) {
        if (isset($divergences['column'][$key])) {
            continue;
        }
        [$table, $column] = explode('.', $key, 2);
        $body = schemaTableBody($schema, $table);
        if ($body === null) {
            continue; // The table itself is reported above or intentionally absent.
        }
        if (preg_match('/^\s+`' . preg_quote($column, '/') . '`\s/mi', $body) !== 1) {
            $errors[] = sprintf(
                'upgrade%d() adds column %s.%s but it is missing from sql/ultiorganizer.sql, so a fresh install never gets it.',
                $version,
                $table,
                $column,
            );
        }
    }

    foreach ($expected['indexes'] as $key => $index) {
        if (isset($divergences['index'][$key])) {
            continue;
        }
        [$table, $name] = explode('.', $key, 2);
        $body = schemaTableBody($schema, $table);
        if ($body === null) {
            continue;
        }
        if (stripos($body, '`' . $name . '`') === false) {
            $errors[] = sprintf(
                'upgrade%d() adds index %s on %s but it is missing from sql/ultiorganizer.sql, so a fresh install runs without it.',
                $index['version'],
                $name,
                $table,
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
    // Comments are stripped first so a commented-out migration is not replayed:
    // upgrade58() carries a disabled "drop column accreditation_id" line.
    $contents = stripComments((string) file_get_contents($file));
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

/**
 * Remove PHP comments while preserving line count.
 */
function stripComments(string $php): string
{
    $out = '';
    foreach (token_get_all($php) as $token) {
        if (is_array($token)) {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                $out .= str_repeat("\n", substr_count($token[1], "\n"));
                continue;
            }
            $out .= $token[1];
            continue;
        }
        $out .= $token;
    }
    return $out;
}

/**
 * Replay the upgrade chain in version order and return the objects it leaves behind.
 *
 * Order matters: uo_spirit_score is dropped and re-created by later upgrades, and
 * dropping a column also removes any index built over it, as upgrade92() does to
 * uo_game.pool and the index upgrade78() added over it. Only literal calls are
 * replayed; a dynamic "drop column $column" cannot be resolved statically.
 *
 * @param array<int, string> $upgrades version => body
 * @return array{tables: array<string, int>, columns: array<string, int>, indexes: array<string, array{version: int, table: string, columns: list<string>}>}
 */
function replayUpgrades(array $upgrades): array
{
    $tables = [];
    $columns = [];
    $indexes = [];

    foreach ($upgrades as $version => $body) {
        if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z0-9_]+)`?/i', $body, $m) > 0) {
            foreach ($m[1] as $table) {
                $tables[strtolower($table)] = $version;
            }
        }

        if (preg_match_all('/addColumn\(\s*[\'"]([a-z0-9_]+)[\'"]\s*,\s*[\'"]([a-z0-9_]+)[\'"]/i', $body, $m, PREG_SET_ORDER) > 0) {
            foreach ($m as $match) {
                $columns[strtolower($match[1] . '.' . $match[2])] = $version;
            }
        }

        if (preg_match_all('/addIndex\(\s*[\'"]([a-z0-9_]+)[\'"]\s*,\s*[\'"]([a-z0-9_]+)[\'"]\s*,\s*[\'"]\(([^)]*)\)[\'"]/i', $body, $m, PREG_SET_ORDER) > 0) {
            foreach ($m as $match) {
                $table = strtolower($match[1]);
                $indexColumns = array_map(
                    static fn(string $c): string => strtolower(trim($c)),
                    explode(',', $match[3]),
                );
                $indexes[$table . '.' . strtolower($match[2])] = [
                    'version' => $version,
                    'table' => $table,
                    'columns' => $indexColumns,
                ];
            }
        }

        if (preg_match_all('/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?([a-z0-9_]+)`?/i', $body, $m) > 0) {
            foreach ($m[1] as $dropped) {
                $table = strtolower($dropped);
                unset($tables[$table]);
                foreach (array_keys($columns) as $key) {
                    if (str_starts_with($key, $table . '.')) {
                        unset($columns[$key]);
                    }
                }
                foreach (array_keys($indexes) as $key) {
                    if (str_starts_with($key, $table . '.')) {
                        unset($indexes[$key]);
                    }
                }
            }
        }

        if (preg_match_all('/ALTER\s+TABLE\s+`?([a-z0-9_]+)`?\s+DROP\s+COLUMN\s+`?([a-z0-9_]+)`?/i', $body, $m, PREG_SET_ORDER) > 0) {
            foreach ($m as $match) {
                $table = strtolower($match[1]);
                $column = strtolower($match[2]);
                unset($columns[$table . '.' . $column]);
                foreach ($indexes as $key => $index) {
                    if ($index['table'] === $table && in_array($column, $index['columns'], true)) {
                        unset($indexes[$key]);
                    }
                }
            }
        }
    }

    ksort($tables);
    ksort($columns);
    ksort($indexes);

    return ['tables' => $tables, 'columns' => $columns, 'indexes' => $indexes];
}

/**
 * @return array{table: array<string, string>, column: array<string, string>, index: array<string, string>}
 */
function parseDivergences(string $file): array
{
    $result = ['table' => [], 'column' => [], 'index' => []];
    if (!is_file($file)) {
        return $result;
    }
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
        $kind = strtolower($parts[0]);
        if (!array_key_exists($kind, $result)) {
            continue;
        }
        $result[$kind][strtolower(trim($parts[1]))] = $note;
    }
    return $result;
}

function schemaTableBody(string $schema, string $table): ?string
{
    $pattern = '/CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`' . preg_quote($table, '/') . '`\s*\((.*?)^\)\s*ENGINE/msi';
    if (preg_match($pattern, $schema, $m) === 1) {
        return $m[1];
    }
    return null;
}

exit(main($argv));
