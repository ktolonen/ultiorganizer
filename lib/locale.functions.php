<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

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

/**
 * Native gettext can load LANGUAGE catalogs when LC_MESSAGES is any real
 * non-C locale. This lets shared hosts serve bundled translations even when
 * they have not generated every application locale.
 */
function GettextCarrierLocale($candidateLocales = [])
{
    $currentLocale = setlocale(LC_MESSAGES, "0");
    $candidates = [];

    if ($currentLocale !== false) {
        $candidates[] = $currentLocale;
    }

    $candidates = array_merge(
        $candidates,
        $candidateLocales,
        [
            'en_US.UTF-8',
            'en_US.utf8',
            'en_GB.UTF-8',
            'en_GB.utf8',
            'fi_FI.UTF-8',
            'fi_FI.utf8',
        ],
    );

    foreach (array_unique(array_filter($candidates)) as $candidateLocale) {
        $activeLocale = setlocale(LC_MESSAGES, $candidateLocale);
        if ($activeLocale !== false && !preg_match('/^C(?:$|[._-])/', $activeLocale)) {
            if ($currentLocale !== false) {
                setlocale(LC_MESSAGES, $currentLocale);
            }
            return $activeLocale;
        }
    }

    if ($currentLocale !== false) {
        setlocale(LC_MESSAGES, $currentLocale);
    }
    return false;
}

function CanServeGettextLocale($locale, $candidateLocales = [])
{
    $currentLocale = setlocale(LC_MESSAGES, "0");
    $available = setlocale(LC_MESSAGES, $locale) !== false
        || GettextCarrierLocale($candidateLocales) !== false
        || str_starts_with($locale, 'en_');

    if ($currentLocale !== false) {
        setlocale(LC_MESSAGES, $currentLocale);
    }

    return $available;
}

function ActivateGettextLocale($locale, $candidateLocales = [])
{
    putenv('LANGUAGE=' . GettextLanguageSpec($locale));
    putenv("LC_MESSAGES=$locale");

    // Reset first so GNU gettext notices LANGUAGE changes in long-lived PHP workers.
    setlocale(LC_MESSAGES, 'C');
    if (setlocale(LC_MESSAGES, $locale) !== false) {
        return true;
    }

    $carrierLocale = GettextCarrierLocale($candidateLocales);
    if ($carrierLocale === false) {
        return str_starts_with($locale, 'en_');
    }

    putenv("LC_MESSAGES=$carrierLocale");
    setlocale(LC_MESSAGES, 'C');
    return setlocale(LC_MESSAGES, $carrierLocale) !== false;
}
