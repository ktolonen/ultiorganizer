<?php

declare(strict_types=1);

// Static validator for playoff bracket layouts.
//
// Scans cust/<id>/layouts/<N>_teams_<R>_rounds.html and checks the placeholder
// contract consumed by PlayoffTemplate() in lib/pool.functions.php and the
// renderers in poolstatus.php / ext/poolstatus.php / ext/eventpools.php.
//
// Usage:
//   php docs/ai/review-playoff-layouts/scripts/check-playoff-layouts.php [options]
//
// Options:
//   --odd            Only validate templates whose team count is odd
//   --file=<path>    Validate a single file (repeatable)
//   --root=<path>    Repository root (default: auto-detect)
//   -v|--verbose     Print informational notes alongside warnings/errors
//   --help           Show this help

const WIDTH_TOLERANCE = 0.5; // percent

function main(array $argv): int
{
    $opts = parseOpts($argv);
    if (!empty($opts['help'])) {
        printHelp();
        return 0;
    }
    $repo = $opts['root'] ?? autoDetectRepo();
    if ($repo === null || !is_dir($repo . '/cust')) {
        fwrite(STDERR, "Cannot resolve repo root (looking for cust/). Pass --root=...\n");
        return 2;
    }
    $files = resolveFiles($repo, $opts);
    if (!$files) {
        fwrite(STDERR, "No layout files matched.\n");
        return 1;
    }

    $totalErrors = 0;
    $totalWarnings = 0;
    foreach ($files as $file) {
        $report = validateFile($file);
        printReport($repo, $file, $report, $opts);
        $totalErrors += count($report['errors']);
        $totalWarnings += count($report['warnings']);
    }

    $count = count($files);
    fwrite(STDERR, "\n$count file(s) checked, $totalErrors error(s), $totalWarnings warning(s).\n");
    return $totalErrors > 0 ? 1 : 0;
}

function printHelp(): void
{
    $self = 'docs/ai/review-playoff-layouts/scripts/check-playoff-layouts.php';
    echo "Static validator for cust/<id>/layouts/*_teams_*_rounds.html templates.\n\n";
    echo "Usage:\n  php $self [options]\n\n";
    echo "Options:\n";
    echo "  --odd            Only validate odd-team templates\n";
    echo "  --file=<path>    Validate a single file (repeatable)\n";
    echo "  --root=<path>    Repository root (default: auto-detect)\n";
    echo "  -v|--verbose     Print informational notes\n";
    echo "  --help           Show this help\n";
}

function parseOpts(array $argv): array
{
    $opts = ['verbose' => false, 'odd' => false, 'files' => [], 'root' => null, 'help' => false];
    foreach (array_slice($argv, 1) as $a) {
        if ($a === '-v' || $a === '--verbose') {
            $opts['verbose'] = true;
        } elseif ($a === '--odd') {
            $opts['odd'] = true;
        } elseif ($a === '--help' || $a === '-h') {
            $opts['help'] = true;
        } elseif (str_starts_with($a, '--file=')) {
            $opts['files'][] = substr($a, 7);
        } elseif (str_starts_with($a, '--root=')) {
            $opts['root'] = rtrim(substr($a, 7), '/');
        } else {
            fwrite(STDERR, "Unknown option: $a\n");
            exit(2);
        }
    }
    return $opts;
}

function autoDetectRepo(): ?string
{
    $dir = realpath(__DIR__);
    if ($dir === false) {
        return null;
    }
    while ($dir !== '/' && $dir !== '') {
        if (is_dir($dir . '/cust') && is_dir($dir . '/lib')) {
            return $dir;
        }
        $dir = dirname($dir);
    }
    return null;
}

function resolveFiles(string $repo, array $opts): array
{
    $files = [];
    if (!empty($opts['files'])) {
        foreach ($opts['files'] as $f) {
            if ($f[0] !== '/') {
                $f = $repo . '/' . ltrim($f, './');
            }
            if (!is_file($f)) {
                fwrite(STDERR, "Not a file: $f\n");
                continue;
            }
            $files[] = $f;
        }
    } else {
        foreach (glob("$repo/cust/*/layouts/*_teams_*_rounds.html") ?: [] as $f) {
            $files[] = $f;
        }
    }
    if ($opts['odd']) {
        $files = array_values(array_filter($files, fn($f) => isOddFilename($f)));
    }
    sort($files);
    return $files;
}

function isOddFilename(string $f): bool
{
    if (preg_match('/(\d+)_teams_(\d+)_rounds\.html$/', $f, $m)) {
        return ((int) $m[1]) % 2 === 1;
    }
    return false;
}

function validateFile(string $path): array
{
    $errors = [];
    $warnings = [];
    $info = [];

    if (!preg_match('/(\d+)_teams_(\d+)_rounds\.html$/', $path, $m)) {
        $errors[] = "Filename does not match <N>_teams_<R>_rounds.html";
        return compactReport($errors, $warnings, $info);
    }
    $teams = (int) $m[1];
    $rounds = (int) $m[2];
    $info[] = "expected: $teams teams, $rounds rounds";

    $html = @file_get_contents($path);
    if ($html === false) {
        $errors[] = "Unable to read file";
        return compactReport($errors, $warnings, $info);
    }

    [$tokenCounts, $gamesPerRound] = scanTokens($html);

    checkRoundHeaders($tokenCounts, $rounds, $errors, $warnings);
    checkPlacementHeader($tokenCounts, $errors, $warnings);
    checkTeamTokens($tokenCounts, $teams, $errors);
    checkPlacementTokens($tokenCounts, $teams, $errors, $warnings);
    checkGameTokens($tokenCounts, $gamesPerRound, $rounds, $errors, $warnings);
    checkWinnerLoserRefs($tokenCounts, $gamesPerRound, $teams, $rounds, $errors, $info);
    checkOddByeWiring($tokenCounts, $teams, $errors, $warnings);
    checkCssWidths($html, $errors);
    checkRowStructure($html, $rounds, $warnings);
    checkMoveComment($html, $teams, $rounds, $errors, $warnings);
    checkRoundCountSanity($teams, $rounds, $warnings);

    return compactReport($errors, $warnings, $info);
}

function compactReport(array $errors, array $warnings, array $info): array
{
    return ['errors' => $errors, 'warnings' => $warnings, 'info' => $info];
}

/**
 * Returns [tokenCounts, gamesPerRound]
 *   tokenCounts['team 3']         => 1
 *   tokenCounts['game 2/1']       => 1
 *   tokenCounts['placement']      => 1
 *   gamesPerRound[1] = [1,2,3,4]
 */
function scanTokens(string $html): array
{
    preg_match_all('/\[(round|team|game|winner|loser|placement)(?:\s+(\d+)(?:\/(\d+))?)?\]/', $html, $matches, PREG_SET_ORDER);
    $counts = [];
    $gamesPerRound = [];
    foreach ($matches as $m) {
        $kind = $m[1];
        $a = isset($m[2]) ? (int) $m[2] : null;
        $b = isset($m[3]) ? (int) $m[3] : null;
        if ($a !== null && $b !== null) {
            $key = "$kind $a/$b";
        } elseif ($a !== null) {
            $key = "$kind $a";
        } else {
            $key = $kind;
        }
        $counts[$key] = ($counts[$key] ?? 0) + 1;
        if ($kind === 'game' && $a !== null && $b !== null) {
            $gamesPerRound[$a][] = $b;
        }
    }
    return [$counts, $gamesPerRound];
}

function checkRoundHeaders(array $counts, int $rounds, array &$errors, array &$warnings): void
{
    for ($r = 1; $r <= $rounds; $r++) {
        $k = "round $r";
        if (!isset($counts[$k])) {
            $errors[] = "Missing header [round $r]";
        } elseif ($counts[$k] > 1) {
            $warnings[] = "Header [round $r] appears {$counts[$k]} times";
        }
    }
    foreach ($counts as $k => $_) {
        if (preg_match('/^round (\d+)$/', $k, $rm) && (int) $rm[1] > $rounds) {
            $errors[] = "[round {$rm[1]}] exceeds declared rounds ($rounds)";
        }
    }
}

function checkPlacementHeader(array $counts, array &$errors, array &$warnings): void
{
    if (!isset($counts['placement'])) {
        $errors[] = 'Missing [placement] header';
    } elseif ($counts['placement'] > 1) {
        $warnings[] = "[placement] header appears {$counts['placement']} times";
    }
}

function checkTeamTokens(array $counts, int $teams, array &$errors): void
{
    for ($t = 1; $t <= $teams; $t++) {
        if (!isset($counts["team $t"])) {
            $errors[] = "Missing [team $t]";
        }
    }
    foreach ($counts as $k => $_) {
        if (preg_match('/^team (\d+)$/', $k, $tm) && (int) $tm[1] > $teams) {
            $errors[] = "Extra [team {$tm[1]}] beyond N=$teams";
        }
    }
}

function checkPlacementTokens(array $counts, int $teams, array &$errors, array &$warnings): void
{
    for ($p = 1; $p <= $teams; $p++) {
        $k = "placement $p";
        if (!isset($counts[$k])) {
            $errors[] = "Missing [placement $p]";
        } elseif ($counts[$k] > 1) {
            $warnings[] = "[placement $p] appears {$counts[$k]} times";
        }
    }
    foreach ($counts as $k => $_) {
        if (preg_match('/^placement (\d+)$/', $k, $pm) && (int) $pm[1] > $teams) {
            $errors[] = "Extra [placement {$pm[1]}] beyond N=$teams";
        }
    }
}

function checkGameTokens(array $counts, array $gamesPerRound, int $rounds, array &$errors, array &$warnings): void
{
    foreach ($counts as $k => $cnt) {
        if (preg_match('#^game (\d+)/(\d+)$#', $k, $gm)) {
            $r = (int) $gm[1];
            $g = (int) $gm[2];
            if ($r < 1 || $r > $rounds) {
                $errors[] = "[game $r/$g] round out of range (1..$rounds)";
            }
            if ($cnt > 1) {
                $errors[] = "[game $r/$g] appears $cnt times";
            }
        }
    }
    foreach ($gamesPerRound as $r => $gs) {
        $unique = array_values(array_unique($gs));
        sort($unique);
        if ($unique && $unique !== range(1, max($unique))) {
            $warnings[] = "Round $r game numbers not contiguous starting at 1: [" . implode(',', $unique) . ']';
        }
    }
}

function checkWinnerLoserRefs(array $counts, array $gamesPerRound, int $teams, int $rounds, array &$errors, array &$info): void
{
    // For odd-team templates, the renderer fills [winner R/ceil(N/2)] with the bye-team
    // carry-over name in any round R, so a [game R/ceil(N/2)] is intentionally absent
    // when the round R pool has the bye team alone in its last slot.
    $byeG = $teams % 2 === 1 ? (int) ceil($teams / 2) : null;
    foreach ($counts as $k => $_) {
        if (!preg_match('#^(winner|loser) (\d+)/(\d+)$#', $k, $wm)) {
            continue;
        }
        $kind = $wm[1];
        $r = (int) $wm[2];
        $g = (int) $wm[3];
        if ($r < 1 || $r > $rounds) {
            $errors[] = "[$kind $r/$g] round out of range";
            continue;
        }
        $hasGame = in_array($g, $gamesPerRound[$r] ?? [], true);
        if (!$hasGame) {
            if ($kind === 'winner' && $byeG !== null && $g === $byeG) {
                $info[] = "[$k] is the BYE pseudo-winner (odd-team rendering)";
            } else {
                $errors[] = "[$kind $r/$g] references missing [game $r/$g]";
            }
        }
    }
}

function checkOddByeWiring(array $counts, int $teams, array &$errors, array &$warnings): void
{
    if ($teams % 2 !== 1) {
        return;
    }
    $byeG = (int) ceil($teams / 2);
    $byeKey = "winner 1/$byeG";
    if (!isset($counts[$byeKey]) && !isset($counts["team $teams"])) {
        $warnings[] = "Odd-team template has neither [$byeKey] nor [team $teams] in a round-2 slot; bye team may not render";
    }
}

function checkCssWidths(string $html, array &$errors): void
{
    preg_match_all('/<td\b[^>]*\bstyle=\'([^\']*)\'/i', $html, $tdMatches, PREG_SET_ORDER);
    $bad = [];
    foreach ($tdMatches as $td) {
        $style = $td[1];
        if (preg_match('/width:\s*([^;]+)/', $style, $wm)) {
            $w = trim($wm[1]);
            if (str_contains($w, ',')) {
                $bad[] = $w;
            } elseif (!preg_match('/^\d+(\.\d+)?%$/', $w)) {
                $bad[] = $w;
            }
        }
    }
    $bad = array_values(array_unique($bad));
    if ($bad) {
        $errors[] = 'Invalid CSS width value(s): ' . implode(', ', array_slice($bad, 0, 5))
            . (count($bad) > 5 ? ' (+' . (count($bad) - 5) . ' more)' : '');
    }
}

function checkRowStructure(string $html, int $rounds, array &$warnings): void
{
    preg_match_all('#<tr\b[^>]*>(.*?)</tr>#is', $html, $rowMatches);
    $expectedCols = $rounds + 1;
    $badRows = [];          // rows with wrong td count
    $badSums = [];          // distinct (sum, rowsExample) tuples
    foreach ($rowMatches[1] as $idx => $row) {
        preg_match_all('/<td\b[^>]*\bstyle=\'([^\']*)\'/i', $row, $tds);
        if (!$tds[1]) {
            continue;
        }
        $sum = 0.0;
        $count = 0;
        foreach ($tds[1] as $st) {
            if (preg_match('/width:\s*([0-9]+(?:\.[0-9]+)?)\s*%/', $st, $sm)) {
                $sum += (float) $sm[1];
                $count++;
            }
        }
        $rowNo = $idx + 1;
        if ($count > 0 && $count !== $expectedCols) {
            $badRows[$count][] = $rowNo;
        }
        if ($count > 0 && abs($sum - 100.0) > WIDTH_TOLERANCE) {
            $key = round($sum, 4);
            $badSums[(string) $key][] = $rowNo;
        }
    }
    foreach ($badRows as $cnt => $rows) {
        $warnings[] = "Row count mismatch: " . count($rows) . " row(s) have $cnt <td> instead of $expectedCols (e.g. row " . $rows[0] . ')';
    }
    foreach ($badSums as $sum => $rows) {
        $warnings[] = "Width sum=$sum% (expected 100) on " . count($rows) . " row(s) (e.g. row " . $rows[0] . ')';
    }
}

function checkMoveComment(string $html, int $teams, int $rounds, array &$errors, array &$warnings): void
{
    if (!preg_match('/^<!--\s*corresponding moves:\s*\r?\n(.*?)-->/s', $html, $m)) {
        if ($teams % 2 === 1) {
            $warnings[] = "Odd-team template lacks '<!--  corresponding moves:' block; default-mode move generation has only partial odd-team support";
        }
        return;
    }
    $body = $m[1];

    // Validate the comment by intent: each non-empty line is a permutation of 1..N
    // and there are exactly $rounds lines.
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $body)), fn($l) => $l !== ''));
    if (count($lines) !== $rounds) {
        $errors[] = 'Move comment has ' . count($lines) . " line(s), expected $rounds (one per round, last line = final ranking)";
    }
    foreach ($lines as $i => $line) {
        $tokens = array_values(array_filter(preg_split('/\s+/', $line), fn($t) => $t !== ''));
        if (count($tokens) !== $teams) {
            $errors[] = 'Move line ' . ($i + 1) . ' has ' . count($tokens) . " entries, expected $teams";
            continue;
        }
        $ints = array_map('intval', $tokens);
        $sorted = $ints;
        sort($sorted);
        if ($sorted !== range(1, $teams)) {
            $errors[] = 'Move line ' . ($i + 1) . " is not a permutation of 1..$teams: [" . implode(' ', $tokens) . ']';
        }
    }
}

function checkRoundCountSanity(int $teams, int $rounds, array &$warnings): void
{
    // Mirrors GeneratePlayoffPools(): roundsToWin = (teams+1)/2; while (>=1) { /=2; rounds++; }
    $r2w = ($teams + 1) / 2;
    if ($teams === 6) {
        $r2w = 4;
    }
    $expectedRounds = 0;
    while ($r2w >= 1) {
        $r2w /= 2;
        $expectedRounds++;
    }
    if ($expectedRounds !== $rounds) {
        $warnings[] = "Filename declares $rounds rounds but default formula yields $expectedRounds for N=$teams";
    }
}

function printReport(string $repo, string $file, array $report, array $opts): void
{
    $rel = ltrim(str_replace($repo, '', $file), '/');
    $hasErr = !empty($report['errors']);
    $hasWarn = !empty($report['warnings']);
    if (!$hasErr && !$hasWarn) {
        echo "OK   $rel\n";
        if ($opts['verbose']) {
            foreach ($report['info'] as $i) {
                echo "       info: $i\n";
            }
        }
        return;
    }
    echo ($hasErr ? 'FAIL' : 'WARN') . " $rel\n";
    foreach ($report['errors'] as $e) {
        echo "       ERROR: $e\n";
    }
    foreach ($report['warnings'] as $w) {
        echo "       WARN:  $w\n";
    }
    if ($opts['verbose']) {
        foreach ($report['info'] as $i) {
            echo "       info:  $i\n";
        }
    }
}

exit(main($argv));
