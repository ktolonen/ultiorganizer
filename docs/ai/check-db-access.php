<?php
declare(strict_types=1);

const RULE_FORBIDDEN_MYSQLI = 'forbidden-mysqli';
const RULE_FORBIDDEN_LOW_LEVEL_DB_CALL = 'forbidden-low-level-db-call';
const RULE_LEGACY_LIB_CURSOR_API = 'legacy-lib-cursor-api';

const APP_SCOPE_PREFIXES = array(
    'admin/',
    'user/',
    'cust/',
    'mobile/',
    'scorekeeper/',
    'spiritkeeper/',
    'ext/',
    'login/',
    'api/',
);

const INFRASTRUCTURE_EXEMPTIONS = array(
    'install.php',
    'lib/database.php',
    'sql/upgrade_db.php',
);

if (PHP_SAPI === 'cli') {
    $argv = isset($GLOBALS['argv']) && is_array($GLOBALS['argv']) ? $GLOBALS['argv'] : array(__FILE__);
    main($argv);
}

function main(array $argv): void
{
    $config = parseArguments($argv);
    $repoRoot = dirname(__DIR__, 2);
    $allowlist = loadAllowlist($repoRoot . '/docs/ai/db-access-allowlist.txt');
    $files = collectFiles($repoRoot, $config['mode'], $config['paths']);
    sort($files);
    $relevantFiles = array();

    $report = array(
        RULE_FORBIDDEN_MYSQLI => array(),
        RULE_FORBIDDEN_LOW_LEVEL_DB_CALL => array(),
        RULE_LEGACY_LIB_CURSOR_API => array(),
    );

    foreach ($files as $path) {
        $classification = classifyPath($path);
        if ($classification === null) {
            continue;
        }

        $relevantFiles[$path] = true;

        if ($classification === 'app') {
            scanAppFile($repoRoot, $path, isset($allowlist[$path]), $report);
        } elseif ($classification === 'lib') {
            scanLibFile($repoRoot, $path, $report);
        }
    }

    printReport($config['mode'], array_keys($relevantFiles), $report);
    exit(hasBlockingFindings($report) ? 1 : 0);
}

function parseArguments(array $argv): array
{
    $mode = null;
    $paths = array();

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--all') {
            $mode = 'all';
            continue;
        }

        if ($arg === '--changed') {
            $mode = 'changed';
            continue;
        }

        if ($arg === '--help' || $arg === '-h') {
            printUsage();
            exit(0);
        }

        $paths[] = $arg;
    }

    if ($mode === null) {
        fwrite(STDERR, "Missing mode.\n\n");
        printUsage();
        exit(2);
    }

    return array(
        'mode' => $mode,
        'paths' => $paths,
    );
}

function printUsage(): void
{
    $usage = <<<TXT
Usage:
  php docs/ai/check-db-access.php --all
  php docs/ai/check-db-access.php --changed [path ...]

Modes:
  --all      Scan the repository for policy violations and backlog signals.
  --changed  Scan changed PHP files from git. If paths are passed, scan only those paths.

TXT;

    fwrite(STDOUT, $usage);
}

function loadAllowlist(string $filename): array
{
    if (!is_readable($filename)) {
        return array();
    }

    $entries = array();
    $lines = file($filename, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return array();
    }

    foreach ($lines as $line) {
        $entry = preg_replace('/\s+#.*$/', '', trim($line));
        if ($entry === null || $entry === '') {
            continue;
        }
        $entries[normalizePath($entry)] = true;
    }

    return $entries;
}

function collectFiles(string $repoRoot, string $mode, array $paths): array
{
    if ($mode === 'all') {
        return collectAllPhpFiles($repoRoot);
    }

    if (!empty($paths)) {
        return normalizeInputPaths($repoRoot, $paths);
    }

    return collectChangedPhpFiles($repoRoot);
}

function collectAllPhpFiles(string $repoRoot): array
{
    $files = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($repoRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $relativePath = normalizePath(substr($fileInfo->getPathname(), strlen($repoRoot) + 1));
        if (!shouldScanPath($relativePath)) {
            continue;
        }

        $files[$relativePath] = true;
    }

    return array_keys($files);
}

function normalizeInputPaths(string $repoRoot, array $paths): array
{
    $normalized = array();

    foreach ($paths as $path) {
        $resolved = $path;
        if (!preg_match('/^([A-Za-z]:[\\\\\/]|\/)/', $path)) {
            $resolved = $repoRoot . '/' . $path;
        }

        if (!file_exists($resolved)) {
            fwrite(STDERR, "Skipping missing path: {$path}\n");
            continue;
        }

        $relativePath = normalizePath(substr(realpath($resolved) ?: $resolved, strlen(realpath($repoRoot) ?: $repoRoot) + 1));
        if (!shouldScanPath($relativePath)) {
            continue;
        }
        $normalized[$relativePath] = true;
    }

    return array_keys($normalized);
}

function collectChangedPhpFiles(string $repoRoot): array
{
    $files = array();
    $commands = array(
        'git -C %s diff --name-only --diff-filter=ACMR --relative --',
        'git -C %s diff --cached --name-only --diff-filter=ACMR --relative --',
        'git -C %s ls-files --others --exclude-standard',
    );

    foreach ($commands as $commandTemplate) {
        $command = sprintf($commandTemplate, escapeshellarg($repoRoot));
        $output = array();
        $status = 0;
        exec($command, $output, $status);
        if ($status !== 0) {
            continue;
        }

        foreach ($output as $line) {
            $relativePath = normalizePath(trim($line));
            if ($relativePath === '' || !shouldScanPath($relativePath)) {
                continue;
            }
            $files[$relativePath] = true;
        }
    }

    return array_keys($files);
}

function shouldScanPath(string $path): bool
{
    if ($path === '') {
        return false;
    }

    if (preg_match('#(^|/)vendor/#', $path)) {
        return false;
    }

    return str_ends_with($path, '.php');
}

function classifyPath(string $path): ?string
{
    if (in_array($path, INFRASTRUCTURE_EXEMPTIONS, true)) {
        return null;
    }

    if (str_starts_with($path, 'lib/')) {
        if (str_starts_with($path, 'lib/feed_generator/')
            || str_starts_with($path, 'lib/phpqrcode/')
            || str_starts_with($path, 'lib/tfpdf/')
            || str_starts_with($path, 'lib/yuiloader/')) {
            return null;
        }
        return 'lib';
    }

    foreach (APP_SCOPE_PREFIXES as $prefix) {
        if (str_starts_with($path, $prefix)) {
            return 'app';
        }
    }

    if (dirname($path) === '.') {
        return 'app';
    }

    return null;
}

function scanAppFile(string $repoRoot, string $path, bool $allowlisted, array &$report): void
{
    $content = file_get_contents($repoRoot . '/' . $path);
    if ($content === false) {
        return;
    }

    $codeLines = explode("\n", stripNonCodeText($content));

    foreach ($codeLines as $index => $line) {
        $lineNumber = $index + 1;

        if (preg_match_all('/(^|[^A-Za-z0-9_])(@?mysqli_[a-z_]+)\s*\(/', $line, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                addFinding(
                    $report,
                    RULE_FORBIDDEN_MYSQLI,
                    $path,
                    $lineNumber,
                    'Direct mysqli call: ' . $match[2] . '()',
                    $allowlisted
                );
            }
        }

        if (preg_match_all('/\b(DBQuery|DBPrepare|DBStmt[A-Za-z0-9_]*|DBQueryTo[A-Za-z0-9_]+|DBFetch[A-Za-z0-9_]+|DBNumRows|DBDataSeek|DBInsertId)\s*\(/', $line, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                addFinding(
                    $report,
                    RULE_FORBIDDEN_LOW_LEVEL_DB_CALL,
                    $path,
                    $lineNumber,
                    'Low-level DB wrapper call: ' . $match[1] . '()',
                    $allowlisted
                );
            }
        }
    }
}

function scanLibFile(string $repoRoot, string $path, array &$report): void
{
    $content = file_get_contents($repoRoot . '/' . $path);
    if ($content === false) {
        return;
    }

    $rawLines = explode("\n", $content);
    $codeLines = explode("\n", stripNonCodeText($content));

    foreach ($rawLines as $index => $line) {
        if (preg_match('/@return\s+mysqli_result\b/', $line)) {
            addFinding(
                $report,
                RULE_LEGACY_LIB_CURSOR_API,
                $path,
                $index + 1,
                'Docblock still advertises mysqli_result',
                false
            );
        }
    }

    foreach ($codeLines as $index => $line) {
        if (preg_match('/\breturn\s+DBQuery\s*\(/', $line)) {
            addFinding(
                $report,
                RULE_LEGACY_LIB_CURSOR_API,
                $path,
                $index + 1,
                'Helper still returns DBQuery() cursor directly',
                false
            );
        }
    }
}

function stripNonCodeText(string $content): string
{
    $output = '';
    $tokens = token_get_all($content);

    foreach ($tokens as $token) {
        if (is_string($token)) {
            $output .= $token;
            continue;
        }

        $tokenId = $token[0];
        $tokenText = $token[1];

        if ($tokenId === T_COMMENT
            || $tokenId === T_DOC_COMMENT
            || $tokenId === T_CONSTANT_ENCAPSED_STRING
            || $tokenId === T_ENCAPSED_AND_WHITESPACE
            || $tokenId === T_INLINE_HTML) {
            $output .= preserveLineBreaks($tokenText);
            continue;
        }

        $output .= $tokenText;
    }

    return $output;
}

function preserveLineBreaks(string $text): string
{
    $preserved = preg_replace('/[^\r\n]/', ' ', $text);
    return $preserved === null ? $text : $preserved;
}

function addFinding(array &$report, string $rule, string $path, int $lineNumber, string $message, bool $allowlisted): void
{
    $report[$rule][] = array(
        'path' => $path,
        'line' => $lineNumber,
        'message' => $message,
        'allowlisted' => $allowlisted,
    );
}

function printReport(string $mode, array $scannedFiles, array $report): void
{
    $scannedCount = count($scannedFiles);
    fwrite(STDOUT, "DB access check mode: --{$mode}\n");
    fwrite(STDOUT, "Scanned policy-relevant PHP files: {$scannedCount}\n");

    foreach (array(RULE_FORBIDDEN_MYSQLI, RULE_FORBIDDEN_LOW_LEVEL_DB_CALL, RULE_LEGACY_LIB_CURSOR_API) as $rule) {
        $findings = $report[$rule];
        fwrite(STDOUT, "\n{$rule}\n");

        if (empty($findings)) {
            fwrite(STDOUT, "  none\n");
            continue;
        }

        usort($findings, function (array $left, array $right): int {
            return [$left['path'], $left['line'], $left['message']] <=> [$right['path'], $right['line'], $right['message']];
        });

        foreach ($findings as $finding) {
            $allowlisted = $finding['allowlisted'] ? ' allowlisted' : '';
            fwrite(
                STDOUT,
                sprintf(
                    "  %s:%d [%s]%s %s\n",
                    $finding['path'],
                    $finding['line'],
                    $rule,
                    $allowlisted,
                    $finding['message']
                )
            );
        }
    }

    $blockingCount = countBlockingFindings($report);
    $allowlistedCount = countAllowlistedFindings($report);
    $legacyCount = count($report[RULE_LEGACY_LIB_CURSOR_API]);
    fwrite(
        STDOUT,
        sprintf(
            "\nSummary: blocking=%d allowlisted=%d legacy=%d\n",
            $blockingCount,
            $allowlistedCount,
            $legacyCount
        )
    );
}

function countBlockingFindings(array $report): int
{
    $count = 0;

    foreach (array(RULE_FORBIDDEN_MYSQLI, RULE_FORBIDDEN_LOW_LEVEL_DB_CALL) as $rule) {
        foreach ($report[$rule] as $finding) {
            if (!$finding['allowlisted']) {
                $count++;
            }
        }
    }

    return $count;
}

function countAllowlistedFindings(array $report): int
{
    $count = 0;

    foreach (array(RULE_FORBIDDEN_MYSQLI, RULE_FORBIDDEN_LOW_LEVEL_DB_CALL) as $rule) {
        foreach ($report[$rule] as $finding) {
            if ($finding['allowlisted']) {
                $count++;
            }
        }
    }

    return $count;
}

function hasBlockingFindings(array $report): bool
{
    return countBlockingFindings($report) > 0;
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}
