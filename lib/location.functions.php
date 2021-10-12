<?php

function GetSearchLocations()
{
	$locale = str_replace(".", "_", getSessionLocale());
	if (isset($_GET['search']) || isset($_GET['query']) || isset($_GET['q'])) {
		if (isset($_GET['search']))
			$search = $_GET['search'];
		elseif (isset($_GET['query']))
			$search = $_GET['query'];
		else
			$search = $_GET['q'];

		$query1 = sprintf(
			"SELECT loc.*, 
		    inf1.locale as locale, inf1.info as locale_info,  
		    inf2.locale as default_locale, inf2.info as info
		    FROM uo_location loc 
		    LEFT JOIN uo_location_info inf1 ON (loc.id = inf1.location_id)
		    LEFT JOIN uo_location_info inf2 ON (loc.id = inf2.location_id and inf2.locale='%s' )
		    WHERE (name like '%%%s%%' OR address like '%%%s%%') ORDER BY name",
			DBEscapeString($locale),
			DBEscapeString($search),
			DBEscapeString($search)
		);
	} elseif (isset($_GET['id'])) {
		$query1 = sprintf(
			"SELECT loc.*, 
		    inf1.locale as locale, inf1.info as locale_info,  
		    inf2.locale as default_locale, inf2.info as info
		    FROM uo_location loc 
		    LEFT JOIN uo_location_info inf1 ON (loc.id = inf1.location_id)
		    LEFT JOIN uo_location_info inf2 ON (loc.id = inf2.location_id and inf2.locale='%s' )
	      WHERE id=%d ORDER BY name",
			DBEscapeString($locale),
			(int)$_GET['id']
		);
	} else {
		$query1 = sprintf(
			"SELECT loc.*, 
		    inf1.locale as locale, inf1.info as locale_info,  
		    inf2.locale as default_locale, inf2.info as info
		    FROM uo_location loc 
		    LEFT JOIN uo_location_info inf1 ON (loc.id = inf1.location_id)
		    LEFT JOIN uo_location_info inf2 ON (loc.id = inf2.location_id and inf2.locale='%s' )
	      WHERE 1 ORDER BY name",
			DBEscapeString($locale)
		);
	}
	$result1 = DBQuery($query1);

	return $result1;
}

function LocationInfo($id)
{
	$locale = str_replace(".", "_", getSessionLocale());
	$query = sprintf("SELECT id, name, fields, indoor, address, inf.info as info, lat, lng 
	    FROM uo_location loc LEFT JOIN uo_location_info inf ON ( loc.id = inf.location_id and inf.locale='%s' )
	    WHERE id=%d", DBEscapeString($locale), (int)$id);
	$result = DBQueryToRow($query);

	return $result;
}

function SetLocation($id, $name, $address, $info, $fields, $indoor, $lat, $lng, $season)
{
	if (isSuperAdmin() || isSeasonAdmin($season)) {
		$query = sprintf(
			"UPDATE uo_location SET name='%s', address='%s', fields=%d, indoor=%d, lat='%s', lng='%s'  WHERE id=%d",
			DBEscapeString($name),
			DBEscapeString($address),
			(int)$fields,
			(int)$indoor,
			DBEscapeString($lat),
			DBEscapeString($lng),
			(int)$id
		);
		DBQuery($query);

		updateInfos($id, $info);
	} else {
		die('Insufficient rights to change location');
	}
}

function updateInfos($id, $info)
{
	foreach ($info as $locale => $infostr) {
		if (empty($infostr)) {
			$query = sprintf(
				"DELETE FROM uo_location_info WHERE location_id=%d AND locale='%s'",
				(int)$id,
				DBEscapeString($locale)
			);
		} else {
			$query = sprintf(
				"INSERT INTO uo_location_info (location_id, locale, info) VALUE (%d, '%s', '%s')
		    ON DUPLICATE KEY UPDATE info='%s'",
				(int)$id,
				DBEscapeString($locale),
				DBEscapeString($infostr),
				DBEscapeString($infostr)
			);
		}
		DBQuery($query);
	}
}

function AddLocation($name, $address, $info, $fields, $indoor, $lat, $lng, $season)
{
	if (isSuperAdmin() || isSeasonAdmin($season)) {
		$query = sprintf(
			"INSERT INTO uo_location (name, address, fields, indoor, lat, lng)
	       VALUES ('%s', '%s', %d, %d, '%s', '%s')",
			DBEscapeString($name),
			DBEscapeString($address),
			(int)$fields,
			(int)$indoor,
			DBEscapeString($lat),
			DBEscapeString($lng)
		);

		$locationId = DBQueryInsert($query);

		updateInfos($locationId, $info);

		return $locationId;
	} else {
		die('Insufficient rights to add location');
	}
}

function RemoveLocation($id)
{
	if (isSuperAdmin()) {
		$query = sprintf("DELETE FROM uo_location WHERE id=%d", (int)$id);
		$result = DBQuery($query);

		$query = sprintf("DELETE FROM uo_location_info WHERE location_id=%d", (int)$id);
		$result = DBQuery($query);
	} else {
		die('Insufficient rights to remove location');
	}
}
