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
