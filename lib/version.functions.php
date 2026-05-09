<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

/**
 * Read a version string from a metadata file.
 *
 * @param string $path
 * @param string $constantName
 * @return string
 */
function ReadVersionMetadata($path, $constantName = '')
{
    if (!is_file($path)) {
        return '';
    }

    $version = include $path;
    if (is_string($version) && trim($version) !== '') {
        return trim($version);
    }

    if ($constantName !== '' && defined($constantName) && trim((string) constant($constantName)) !== '') {
        return trim((string) constant($constantName));
    }

    return '';
}

/**
 * Return the Ultiorganizer application compatibility version.
 *
 * @return string
 */
function GetUltiorganizerVersion()
{
    global $include_prefix;

    $prefix = isset($include_prefix) ? $include_prefix : '';
    $version = ReadVersionMetadata($prefix . 'version.php', 'ULTIORGANIZER_VERSION');
    if ($version !== '') {
        return $version;
    }

    return '0.0';
}

/**
 * Return structured Ultiorganizer application version information.
 *
 * @return array
 */
function GetUltiorganizerVersionInfo()
{
    $version = GetUltiorganizerVersion();
    $parts = explode('.', $version);
    return [
        'version' => $version,
        'major' => isset($parts[0]) ? (int) $parts[0] : 0,
        'minor' => isset($parts[1]) ? (int) $parts[1] : 0,
    ];
}

/**
 * Return the installed database schema version recorded in the database.
 *
 * @return array
 */
function GetDatabaseVersionInfo()
{
    return [
        'version' => (int) getDBVersion(),
    ];
}

/**
 * Return the active customization identifier.
 *
 * @return string
 */
function GetCustomizationId()
{
    if (defined('CUSTOMIZATIONS')) {
        return (string) CUSTOMIZATIONS;
    }
    return 'default';
}

/**
 * Read the active customization version from cust/<id>/version.php when present.
 *
 * The optional file may either return a version string or define
 * CUSTOMIZATION_VERSION. Missing or empty customization metadata falls back to
 * 0.0.
 *
 * @return string
 */
function GetCustomizationVersion()
{
    global $include_prefix;

    $customization = GetCustomizationId();
    $prefix = isset($include_prefix) ? $include_prefix : '';
    $versionFile = $prefix . 'cust/' . $customization . '/version.php';

    $version = ReadVersionMetadata($versionFile, 'CUSTOMIZATION_VERSION');
    if ($version !== '') {
        return $version;
    }

    return '0.0';
}

/**
 * Return structured active customization version information.
 *
 * @return array
 */
function GetCustomizationVersionInfo()
{
    return [
        'id' => GetCustomizationId(),
        'version' => GetCustomizationVersion(),
    ];
}
