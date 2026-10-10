<?php

declare(strict_types=1);

// Catalog-level terminology check.
//
// Reads every msgid in the gettext catalogs and in the PHP sources (extracted
// with xgettext) as one flat list, which exposes problems a per-page review
// cannot see:
//
//   variants     msgids that differ only in case, punctuation or spacing
//                ("Club Card" / "Club card")
//   term         msgids containing wording that docs/terminology.md discourages
//                (patterns in ../catalog-terms.txt)
//   missing      msgids absent from a locale, including new source strings
//                that no catalog has yet (refresh the catalogs)
//   untranslated entries with an empty msgstr in any locale
//   fuzzy        entries still flagged fuzzy in any locale
//
// Existing, accepted findings live in ../catalog-allow.txt so only new problems
// fail the run. Regenerate it with --update-allow after reviewing the report.
// Untranslated and fuzzy entries are never allowlisted.
//
// Usage:
//   php docs/ai/review-user-language/scripts/check-catalog-terms.php [options]
//
// Options:
//   --root=<path>    Repository root (default: auto-detect)
//   --update-allow   Rewrite catalog-allow.txt from the current findings
//   --help           Show this help

const SKILL_RELATIVE = 'docs/ai/review-user-language';
const LOCALE_GLOB = '/locale/*/LC_MESSAGES/messages.po';

/**
 * @return array<string, array{str: string, fuzzy: bool}>
 */
function parsePo(string $path): array
{
    $entries = [];
    $cur = ['field' => '', 'id' => '', 'str' => '', 'fuzzy' => false];
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    $lines[] = '';
    foreach ($lines as $line) {
        if ($cur['field'] === 'str' && ($line === '' || $line[0] === '#' || str_starts_with($line, 'msgid'))) {
            if ($cur['id'] !== '') {
                $entries[$cur['id']] = ['str' => $cur['str'], 'fuzzy' => $cur['fuzzy']];
            }
            $cur = ['field' => '', 'id' => '', 'str' => '', 'fuzzy' => false];
        }
        if (str_starts_with($line, '#,')) {
            $cur['fuzzy'] = str_contains($line, 'fuzzy');
        } elseif (str_starts_with($line, 'msgid ')) {
            $cur['field'] = 'id';
            $cur['id'] = stripcslashes(substr($line, 7, -1));
        } elseif (str_starts_with($line, 'msgstr')) {
            $cur['field'] = 'str';
            $cur['str'] = stripcslashes((string) preg_replace('/^msgstr(\[0\])? "/', '', substr($line, 0, -1)));
        } elseif ($line !== '' && $line[0] === '"' && $cur['field'] !== '') {
            $cur[$cur['field']] .= stripcslashes(substr($line, 1, -1));
        }
    }
    return $entries;
}

/**
 * @return list<string>
 */
function readLines(string $path): array
{
    $lines = [];
    foreach (is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [] as $line) {
        if ($line !== '' && $line[0] !== '#') {
            $lines[] = $line;
        }
    }
    return $lines;
}

/**
 * Msgids extracted from the PHP sources by the catalog refresh script, so a new
 * string is checked even before anyone refreshes the catalogs.
 *
 * @return array<string, array{str: string, fuzzy: bool}>|null
 */
function sourceMsgids(string $root): ?array
{
    $pot = tempnam(sys_get_temp_dir(), 'uo-pot');
    $script = $root . '/docs/ai/fix-user-language/scripts/update-gettext-catalogs.sh';
    exec('bash ' . escapeshellarg($script) . ' --pot ' . escapeshellarg((string) $pot) . ' 2>&1', $output, $status);
    $entries = $status === 0 ? parsePo((string) $pot) : null;
    @unlink((string) $pot);
    if ($entries === null) {
        fwrite(STDERR, "Extracting source msgids failed (needs gettext's xgettext):\n" . implode("\n", $output) . "\n");
    }
    return $entries;
}

function main(array $argv): int
{
    $root = null;
    $update = false;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help') {
            echo "Usage: php check-catalog-terms.php [--root=<path>] [--update-allow]\n";
            return 0;
        }
        if ($arg === '--update-allow') {
            $update = true;
        } elseif (str_starts_with($arg, '--root=')) {
            $root = rtrim(substr($arg, 7), '/');
        }
    }
    $root ??= dirname(__DIR__, 4);
    $skill = $root . '/' . SKILL_RELATIVE;
    $poFiles = glob($root . LOCALE_GLOB) ?: [];
    if ($poFiles === []) {
        fwrite(STDERR, "No catalogs found under $root/locale.\n");
        return 2;
    }

    $catalogs = [];
    foreach ($poFiles as $po) {
        $catalogs[basename(dirname($po, 2))] = parsePo($po);
    }
    $source = sourceMsgids($root);
    if ($source === null) {
        return 2;
    }
    $all = $source;
    foreach ($catalogs as $entries) {
        $all += $entries;
    }
    $ids = array_map('strval', array_keys($all));
    sort($ids, SORT_FLAG_CASE | SORT_STRING);

    $found = [];

    $groups = [];
    foreach ($ids as $id) {
        $lower = str_replace("'", '', strtolower($id));
        // Punctuation as a separator catches "All-time" / "All time"; dropping
        // it catches "Division(s)" / "Divisions".
        foreach (['/[^a-z0-9]+/' => ' ', '/[^a-z0-9 ]+/' => ''] as $pattern => $replacement) {
            $key = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace($pattern, $replacement, $lower)));
            if ($key !== '') {
                $groups[$replacement . $key][] = $id;
            }
        }
    }
    foreach ($groups as $variants) {
        if (count($variants) > 1) {
            $found[] = 'variants: ' . implode(' | ', $variants);
        }
    }

    $terms = [];
    foreach (readLines($skill . '/catalog-terms.txt') as $line) {
        [$pattern, $preferred] = array_pad(explode("\t", $line, 2), 2, '');
        $terms[] = ['/' . $pattern . '/i', $preferred];
    }
    $termHints = [];
    foreach ($ids as $id) {
        foreach ($terms as [$regex, $preferred]) {
            if (preg_match($regex, $id) === 1) {
                $found[] = 'term: ' . $id;
                $termHints['term: ' . $id] = $preferred;
            }
        }
    }

    $allowPath = $skill . '/catalog-allow.txt';
    if ($update) {
        $found = array_values(array_unique($found));
        sort($found, SORT_STRING);
        $header = "# Accepted findings of scripts/check-catalog-terms.php. Review before adding.\n"
            . "# Format: 'variants: A | B' or 'term: <msgid>'. Regenerate with --update-allow.\n";
        file_put_contents($allowPath, $header . implode("\n", $found) . "\n");
        echo 'Wrote ' . count($found) . " entries to catalog-allow.txt\n";
        return 0;
    }

    $allow = array_flip(readLines($allowPath));
    $errors = 0;
    foreach (array_unique($found) as $finding) {
        if (!isset($allow[$finding])) {
            $hint = isset($termHints[$finding]) ? '  (prefer: ' . $termHints[$finding] . ')' : '';
            echo "NEW  $finding$hint\n";
            $errors++;
        }
    }
    foreach ($catalogs as $locale => $entries) {
        foreach (array_diff_key(array_flip($ids), $entries) as $id => $_) {
            echo "MISSING  [$locale] $id\n";
            $errors++;
        }
        foreach ($entries as $id => $entry) {
            if ($entry['str'] === '') {
                echo "UNTRANSLATED  [$locale] $id\n";
                $errors++;
            } elseif ($entry['fuzzy']) {
                echo "FUZZY  [$locale] $id\n";
                $errors++;
            }
        }
    }

    echo count($ids) . ' msgids, ' . count($catalogs) . ' locales, ' . $errors . " problem(s).\n";
    return $errors > 0 ? 1 : 0;
}

exit(main($argv));
