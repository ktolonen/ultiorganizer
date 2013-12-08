<?php

function GetSearchLocations() {
	$locale = str_replace(".", "_", getSessionLocale());
	if (isset($_GET['search']) || isset($_GET['query']) || isset($_GET['q'])) {
		if (isset($_GET['search']))
			$search = $_GET['search'];
		elseif (isset($_GET['query']))
			$search = $_GET['query'];
		else
			$search = $_GET['q'];
		
		$query = sprintf("SELECT *, info_".$locale." as info FROM uo_location WHERE name like '%%%s%%' or address like '%%%s%%'",
			mysql_real_escape_string($search), mysql_real_escape_string($search));

	} elseif (isset($_GET['id'])) {
		$query = sprintf("SELECT *, info_".$locale." as info FROM uo_location WHERE id=%d",
			(int)$_GET['id']);
	} else {
		$query = "SELECT *, info_".$locale." as info FROM uo_location";
	}
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	return $result;
}

function LocationInfo($id) {
	$locale = str_replace(".", "_", getSessionLocale());
	$query = sprintf("SELECT id, name, fields, indoor, address, info_".$locale." as info, lat, lng FROM uo_location WHERE id=%d", (int)$id);
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	return mysql_fetch_assoc($result);
}

function SetLocation($id, $name, $address, $info, $fields, $indoor, $lat, $lng, $season) {
	if (isSuperAdmin()||isSeasonAdmin($season)) {
		$query = sprintf("UPDATE uo_location SET name='%s', address='%s', fields=%d, indoor=%d, lat='%s', lng='%s'", 
			mysql_real_escape_string($name),
			mysql_real_escape_string($address),
			(int)$fields,
			(int)$indoor,
			mysql_real_escape_string($lat),
			mysql_real_escape_string($lng));
		foreach ($info as $locale => $infostr) {
			$query .= ", info_".mysql_real_escape_string($locale)."='".mysql_real_escape_string($infostr)."'";
		}
		$query .= sprintf(" WHERE id=%d", (int)$id);
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
	} else { die('Insufficient rights to change location'); }	
}

function AddLocation($name, $address, $info, $fields, $indoor, $lat, $lng, $season) {
	if (isSuperAdmin()||isSeasonAdmin($season)) {
		$fieldsSQL = "INSERT INTO uo_location (name, address, fields, indoor, lat, lng";
		$values =  sprintf(") VALUES ('%s', '%s', %d, %d, '%s', '%s'",
			mysql_real_escape_string($name),
			mysql_real_escape_string($address),
			(int)$fields,
			(int)$indoor,
			mysql_real_escape_string($lat),
			mysql_real_escape_string($lng));
		foreach ($info as $locale => $infostr) {
			$fieldsSQL .= ", info_".$locale;
			$values .= sprintf(", '%s'", mysql_real_escape_string($infostr));
		}
		$query = $fieldsSQL.$values.")";
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		return mysql_insert_id();
	} else { die('Insufficient rights to add location'); }		
}

function RemoveLocation($id) {
	if (isSuperAdmin()) {
		$query = sprintf("DELETE FROM uo_location WHERE id=%d", (int)$id);
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
	} else { die('Insufficient rights to remove location'); }	
}

?>