<?php
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

function GetServerConf()
{
	$query = "SELECT * FROM uo_setting ORDER BY setting_id";
	return DBQueryToArray($query);
}

function GetSimpleServerConf()
{
	$query = "SELECT * FROM uo_setting ORDER BY setting_id";
	$result = DBQueryToArray($query);

	$retarray = array();
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
				DBEscapeString($setting['name'])
			);
			$result = DBQueryToValue($query);

			if ($result) {
				$query = sprintf(
					"UPDATE uo_setting SET value='%s' WHERE setting_id=%d",
					DBEscapeString($setting['value']),
					(int)$result
				);
				$result = DBQuery($query);
			} else {
				$query = sprintf(
					"INSERT INTO uo_setting (name, value) VALUES ('%s', '%s')",
					DBEscapeString($setting['name']),
					DBEscapeString($setting['value'])
				);
				$result = DBQuery($query);
			}
		}
	} else {
		die('Insufficient rights to configure server');
	}
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
	$customizations = array();
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
	$localizations = array();
	$temp = scandir($include_prefix . "locale/");
	$currentLocale = setlocale(LC_MESSAGES, 0);
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
