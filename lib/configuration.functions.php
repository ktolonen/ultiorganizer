<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

$serverConf = GetSimpleServerConf();
$locales = getAvailableLocalizations();

function GetPageTitle()
{
    global $serverConf;
    return utf8entities($serverConf['PageTitle']);
}

function GetDefaultLocale()
{
    global $serverConf;
    return $serverConf['DefaultLocale'];
}

function GetDefTimeZone()
{
    global $serverConf;
    return $serverConf['DefaultTimezone'];
}


function IsGameRSSEnabled()
{
    global $serverConf;
    return ($serverConf['GameRSSEnabled'] == "true");
}

function ShowDefenseStats()
{
    global $serverConf;
    return ($serverConf['ShowDefenseStats'] == "true");
}

function ReadOnlyServer()
{
    global $serverConf;
    return ($serverConf['ReadOnlyServer'] == "true");
}

function SoftMaintenanceMode()
{
    global $serverConf;
    return isset($serverConf['SoftMaintenanceMode']) && $serverConf['SoftMaintenanceMode'] == "true";
}

function SoftMaintenanceRequestPrefersJson()
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

    if (strpos($scriptName, '/api/') !== false || substr($scriptName, -8) === 'json.php') {
        return true;
    }

    return stripos($accept, 'application/json') !== false;
}

function SoftMaintenanceText($text)
{
    return function_exists('_') ? _($text) : $text;
}

function RenderSoftMaintenanceResponse($seasonId = "")
{
    $title = SoftMaintenanceText("Maintenance");
    $message = SoftMaintenanceText("Ultiorganizer is currently under maintenance. Please try again later.");

    if (!empty($seasonId) && function_exists('SeasonName')) {
        $seasonName = SeasonName($seasonId);
        if (!empty($seasonName)) {
            $message = sprintf(
                SoftMaintenanceText("%s is currently under maintenance. Please try again later."),
                $seasonName,
            );
        }
    }

    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 60');
    }

    if (SoftMaintenanceRequestPrefersJson()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode([
            'error' => 'maintenance',
            'title' => $title,
            'message' => $message,
        ]);
        exit();
    }

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }

    echo "<!DOCTYPE html>\n";
    echo "<html lang='en'>\n";
    echo "<head><meta charset='UTF-8'/><meta name='viewport' content='width=device-width, initial-scale=1'/><title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title></head>\n";
    echo "<body><main style='max-width:42rem;margin:4rem auto;padding:0 1rem;font-family:sans-serif;line-height:1.5'>";
    echo "<h1>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h1>";
    echo "<p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "</main></body></html>\n";
    exit();
}

function ReadBooleanSystemFlag($name, $default = false)
{
    if (!defined($name)) {
        return $default;
    }

    $flag = constant($name);
    if (is_bool($flag)) {
        return $flag;
    }

    $normalized = strtolower(trim((string) $flag));
    return in_array($normalized, ["1", "true", "yes", "on", "enabled"], true);
}

function IsSelfRegistrationDisabled()
{
    return ReadBooleanSystemFlag('DISABLE_SELF_REGISTRATION', false);
}

function IsEmailDisabled()
{
    return ReadBooleanSystemFlag('NO_EMAIL', false);
}

function IsPublicRegistrationDisabled()
{
    return IsSelfRegistrationDisabled() || IsEmailDisabled();
}

function IsSelfRegistrationEnabled()
{
    return !IsPublicRegistrationDisabled();
}

function GetServerConf()
{
    $query = "SELECT * FROM uo_setting ORDER BY setting_id";
    return DBQueryToArray($query);
}

function GetSimpleServerConf()
{
    $query = "SELECT * FROM uo_setting ORDER BY setting_id";
    $result = DBQueryToArray($query);

    $retarray = [];
    foreach ($result as $row) {
        $retarray[$row['name']] = $row['value'];
    }
    return $retarray;
}

function SetServerConf($settings)
{
    if (isSuperAdmin()) {
        foreach ($settings as $setting) {
            $query = sprintf(
                "SELECT setting_id FROM uo_setting WHERE name='%s'",
                DBEscapeString($setting['name']),
            );
            $result = DBQueryToValue($query);

            if ($result !== null && $result !== false) {
                $query = sprintf(
                    "UPDATE uo_setting SET value='%s' WHERE setting_id=%d",
                    DBEscapeString($setting['value']),
                    (int) $result,
                );
                $result = DBQuery($query);
            } else {
                $query = sprintf(
                    "INSERT INTO uo_setting (name, value) VALUES ('%s', '%s')",
                    DBEscapeString($setting['name']),
                    DBEscapeString($setting['value']),
                );
                $result = DBQuery($query);
            }
        }
    } else {
        die('Insufficient rights to configure server');
    }
}

/**
 * Convenience wrapper to update a single server setting.
 *
 * @param string $name
 * @param string $value
 */
function SetServerConfValue($name, $value)
{
    SetServerConf([['name' => $name, 'value' => $value]]);
}

function GetGoogleMapsAPIKey()
{
    global $serverConf;
    return $serverConf['GoogleMapsAPIKey'];
}

function isRespTeamHomeTeam()
{
    $query = "SELECT value FROM uo_setting WHERE name = 'HomeTeamResponsible'";
    $result = DBQueryToValue($query);

    return $result == 'yes';
}

/**
 * Scans directory /cust/* and returns list of customizations avialable.
 *
 */
function getAvailableCustomizations()
{
    global $include_prefix;
    $customizations = [];
    $temp = scandir($include_prefix . "cust/");

    foreach ($temp as $fh) {
        if (is_dir($include_prefix . "cust/$fh") && $fh != '.' && $fh != '..') {
            $customizations[] = $fh;
        }
    }

    return $customizations;
}

/**
 * Return list of localizations available under /locale that the system can serve.
 * Filters out locales not installed on the system and always includes English.
 */
function getAvailableLocalizations()
{
    global $include_prefix;
    $localizations = [];
    $temp = scandir($include_prefix . "locale/");
    $currentLocale = setlocale(LC_MESSAGES, "0");
    $fallbackEnglishLocale = 'en_GB.utf8';

    foreach ($temp as $fh) {
        if (is_dir($include_prefix . "locale/$fh") && $fh != '.' && $fh != '..') {
            // Only list locales that are available on the system so gettext works.
            if (setlocale(LC_MESSAGES, $fh) !== false) {
                $localizations[$fh] = $fh;
            }
        }
    }
    // English does not require translations, so keep it available even if the locale is missing.
    if (!isset($localizations[$fallbackEnglishLocale])) {
        $localizations[$fallbackEnglishLocale] = $fallbackEnglishLocale;
    }
    if ($currentLocale !== false) {
        setlocale(LC_MESSAGES, $currentLocale);
    }

    return $localizations;
}

function IsPersistentCacheEnabled()
{
    global $serverConf;
    return !isset($serverConf['PersistentCacheEnabled']) || $serverConf['PersistentCacheEnabled'] === 'true';
}

function GetPersistentCacheTtlSeconds()
{
    global $serverConf;
    $ttl = (int) ($serverConf['PersistentCacheTtlSeconds'] ?? 3);
    return $ttl > 0 ? $ttl : 3;
}
