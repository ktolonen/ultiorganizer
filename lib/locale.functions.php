<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

// Windows PHP doesn't define LC_MESSAGES; alias it to LC_ALL there.
if (!defined('LC_MESSAGES')) {
    define('LC_MESSAGES', LC_ALL);
}

/**
 * setlocale() wrapper for the message category. On Windows, LC_MESSAGES is
 * aliased to LC_ALL above, so setting it also changes LC_NUMERIC and
 * LC_COLLATE; restore those after every real change so number formatting and
 * string sorting stay locale-independent there.
 */
function SetMessageLocale($locale)
{
    $result = setlocale(LC_MESSAGES, $locale);

    if ($locale !== '0' && PHP_OS_FAMILY === 'Windows') {
        setlocale(LC_NUMERIC, 'C');
        setlocale(LC_COLLATE, 'C');
    }

    return $result;
}

/**
 * Return the locale identifiers gettext should try for LANGUAGE fallback.
 */
function GettextLanguageSpec($locale)
{
    $languages = [$locale];

    $utf8Locale = preg_replace('/\.utf8$/i', '.UTF-8', $locale);
    if ($utf8Locale !== null) {
        $languages[] = $utf8Locale;
    }

    $localeWithoutEncoding = preg_replace('/\..*$/', '', $locale);
    if ($localeWithoutEncoding !== null) {
        $languages[] = $localeWithoutEncoding;
        $languageParts = explode('_', $localeWithoutEncoding);
        if (count($languageParts) > 1) {
            $languages[] = strtolower($languageParts[0]);
        }
    }

    return implode(':', array_values(array_unique(array_filter($languages))));
}

function GettextLocaleVariants($locale)
{
    $variants = [$locale];

    $utf8Locale = preg_replace('/\.utf8$/i', '.UTF-8', $locale);
    if ($utf8Locale !== null) {
        $variants[] = $utf8Locale;
    }

    $utf8Locale = preg_replace('/\.UTF-8$/', '.utf8', $locale);
    if ($utf8Locale !== null) {
        $variants[] = $utf8Locale;
    }

    return array_values(array_unique(array_filter($variants)));
}

function GettextEnvironmentLocales()
{
    $locales = [];
    foreach (['LC_ALL', 'LC_MESSAGES', 'LANG'] as $name) {
        $locale = getenv($name);
        if ($locale !== false && $locale !== '') {
            $locales[] = $locale;
        }
    }

    return $locales;
}

function GettextInstalledLocales()
{
    static $installedLocales = null;

    if ($installedLocales !== null) {
        return $installedLocales;
    }

    $installedLocales = [];
    if (!function_exists('shell_exec')) {
        return $installedLocales;
    }

    $output = @shell_exec('locale -a 2>/dev/null');
    if (!is_string($output) || $output === '') {
        return $installedLocales;
    }

    foreach (preg_split('/\R/', $output) ?: [] as $locale) {
        $locale = trim($locale);
        if ($locale !== '') {
            $installedLocales[] = $locale;
        }
    }

    return $installedLocales;
}

function GettextCarrierLocaleCandidates($candidateLocales = [])
{
    $currentLocale = SetMessageLocale("0");
    $candidates = [];

    if ($currentLocale !== false) {
        $candidates[] = $currentLocale;
    }

    $candidates = array_merge(
        $candidates,
        GettextEnvironmentLocales(),
        $candidateLocales,
        GettextInstalledLocales(),
        [
            'en_US.UTF-8',
            'en_US.utf8',
            'en_GB.UTF-8',
            'en_GB.utf8',
            'fi_FI.UTF-8',
            'fi_FI.utf8',
        ],
    );

    $variants = [];
    foreach ($candidates as $candidateLocale) {
        $variants = array_merge($variants, GettextLocaleVariants($candidateLocale));
    }

    return array_values(array_unique(array_filter($variants)));
}

function IsGettextCarrierLocale($locale)
{
    return !preg_match('/^(?:C|POSIX)(?:$|[._-])/', $locale);
}

/**
 * Native gettext can load LANGUAGE catalogs when LC_MESSAGES is any real
 * non-C locale. This lets shared hosts serve bundled translations even when
 * they have not generated every application locale.
 */
function GettextCarrierLocale($candidateLocales = [])
{
    $currentLocale = SetMessageLocale("0");

    foreach (GettextCarrierLocaleCandidates($candidateLocales) as $candidateLocale) {
        $activeLocale = SetMessageLocale($candidateLocale);
        if ($activeLocale !== false && IsGettextCarrierLocale($activeLocale)) {
            if ($currentLocale !== false) {
                SetMessageLocale($currentLocale);
            }
            return $activeLocale;
        }
    }

    if ($currentLocale !== false) {
        SetMessageLocale($currentLocale);
    }
    return false;
}

function CanServeGettextLocale($locale, $candidateLocales = [])
{
    $currentLocale = SetMessageLocale("0");
    $available = SetMessageLocale($locale) !== false
        || GettextCarrierLocale($candidateLocales) !== false
        || str_starts_with($locale, 'en_');

    if ($currentLocale !== false) {
        SetMessageLocale($currentLocale);
    }

    return $available;
}

function ActivateGettextLocale($locale, $candidateLocales = [])
{
    putenv('LANGUAGE=' . GettextLanguageSpec($locale));
    putenv("LC_MESSAGES=$locale");

    // Reset first so GNU gettext notices LANGUAGE changes in long-lived PHP workers.
    SetMessageLocale('C');
    if (SetMessageLocale($locale) !== false) {
        return true;
    }

    $carrierLocale = GettextCarrierLocale($candidateLocales);
    if ($carrierLocale === false) {
        return str_starts_with($locale, 'en_');
    }

    putenv("LC_MESSAGES=$carrierLocale");
    SetMessageLocale('C');
    return SetMessageLocale($carrierLocale) !== false;
}

/**
 * The configured locale that best matches an Accept-Language header, or null.
 * A tag matches its own locale (fi-FI), else the first configured locale of
 * its language (fi, en-US); ranges are tried in q order.
 *
 * @param string $header Accept-Language value
 * @param string[] $available Locale keys such as fi_FI.utf8
 * @return string|null
 */
function AcceptLanguageLocale($header, $available)
{
    $ranges = [];
    foreach (explode(',', (string) $header) as $i => $part) {
        $pieces = explode(';', $part);
        $tag = strtolower(str_replace('_', '-', trim($pieces[0])));
        $q = 1.0;
        foreach (array_slice($pieces, 1) as $param) {
            if (preg_match('/^\s*q\s*=\s*([01](?:\.\d{0,3})?)\s*$/i', $param, $m)) {
                $q = (float) $m[1];
            }
        }
        if ($tag !== '' && $tag !== '*' && $q > 0) {
            $ranges[] = [$q, $i, $tag];
        }
    }
    usort($ranges, fn($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

    $tags = [];
    foreach ($available as $locale) {
        $tags[$locale] = strtolower(str_replace('_', '-', (string) preg_replace('/\..*$/', '', $locale)));
    }
    foreach ($ranges as $range) {
        $match = array_search($range[2], $tags, true);
        if ($match === false) {
            $language = explode('-', $range[2])[0];
            foreach ($tags as $locale => $localeTag) {
                if (explode('-', $localeTag)[0] === $language) {
                    $match = $locale;
                    break;
                }
            }
        }
        if ($match !== false) {
            return (string) $match;
        }
    }
    return null;
}
