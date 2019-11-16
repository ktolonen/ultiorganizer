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
		
		$query1 = sprintf("SELECT loc.*, 
		    inf1.locale as locale, inf1.info as locale_info,  
		    inf2.locale as default_locale, inf2.info as info
		    FROM uo_location loc 
		    LEFT JOIN uo_location_info inf1 ON (loc.id = inf1.location_id)
		    LEFT JOIN uo_location_info inf2 ON (loc.id = inf2.location_id and inf2.locale='%s' )
		    WHERE (name like '%%%s%%' OR address like '%%%s%%') ORDER BY name",
			mysql_real_escape_string($locale), mysql_real_escape_string($search), mysql_real_escape_string($search));

	} elseif (isset($_GET['id'])) {
	  $query1 = sprintf("SELECT loc.*, 
		    inf1.locale as locale, inf1.info as locale_info,  
		    inf2.locale as default_locale, inf2.info as info
		    FROM uo_location loc 
		    LEFT JOIN uo_location_info inf1 ON (loc.id = inf1.location_id)
		    LEFT JOIN uo_location_info inf2 ON (loc.id = inf2.location_id and inf2.locale='%s' )
	      WHERE id=%d ORDER BY name",
	        mysql_real_escape_string($locale),
			(int)$_GET['id']);
	} else {
	  $query1 = sprintf("SELECT loc.*, 
		    inf1.locale as locale, inf1.info as locale_info,  
		    inf2.locale as default_locale, inf2.info as info
		    FROM uo_location loc 
		    LEFT JOIN uo_location_info inf1 ON (loc.id = inf1.location_id)
		    LEFT JOIN uo_location_info inf2 ON (loc.id = inf2.location_id and inf2.locale='%s' )
	      WHERE 1 ORDER BY name",
	        mysql_real_escape_string($locale));
	}
	$result1 = mysql_query($query1);
        
	if (!$result1) { die('Invalid query: ' . mysql_error()); }
	return $result1;
}

function LocationInfo($id) {
	$locale = str_replace(".", "_", getSessionLocale());
	$query = sprintf("SELECT id, name, fields, indoor, address, inf.info as info, lat, lng 
	    FROM uo_location loc LEFT JOIN uo_location_info inf ON ( loc.id = inf.location_id and inf.locale='%s' )
	    WHERE id=%d", mysql_real_escape_string($locale), (int)$id);
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	return mysqli_fetch_assoc($result);
}

function SetLocation($id, $name, $address, $info, $fields, $indoor, $lat, $lng, $season) {
	if (isSuperAdmin()||isSeasonAdmin($season)) {
		$query = sprintf("UPDATE uo_location SET name='%s', address='%s', fields=%d, indoor=%d, lat='%s', lng='%s'  WHERE id=%d", 
			mysql_real_escape_string($name),
			mysql_real_escape_string($address),
			(int)$fields,
			(int)$indoor,
			mysql_real_escape_string($lat),
			mysql_real_escape_string($lng),
		    (int)$id);
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		updateInfos($id, $info);
	} else { die('Insufficient rights to change location'); }	
}

function updateInfos($id, $info) {
  foreach ($info as $locale => $infostr) {
    if (empty($infostr)) {
      $query = sprintf("DELETE FROM uo_location_info WHERE location_id=%d AND locale='%s'",
          (int)$id, mysql_real_escape_string($locale));
    } else {
      $query = sprintf("INSERT INTO uo_location_info (location_id, locale, info) VALUE (%d, '%s', '%s')
		    ON DUPLICATE KEY UPDATE info='%s'",
          (int)$id,
          mysql_real_escape_string($locale),
          mysql_real_escape_string($infostr),
          mysql_real_escape_string($infostr));
    }
    $result = mysql_query($query);
    if (!$result) { die('Invalid query: ' . mysql_error()); }
  }
}

function AddLocation($name, $address, $info, $fields, $indoor, $lat, $lng, $season) {
	if (isSuperAdmin()||isSeasonAdmin($season)) {
	   $query = sprintf("INSERT INTO uo_location (name, address, fields, indoor, lat, lng)
	       VALUES ('%s', '%s', %d, %d, '%s', '%s')",
	       mysql_real_escape_string($name),
	       mysql_real_escape_string($address),
	       (int)$fields,
	       (int)$indoor,
	       mysql_real_escape_string($lat),
	       mysql_real_escape_string($lng));
	       
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }

		$locationId = mysql_insert_id();

		updateInfos($locationId, $info);
		
		return $locationId;
	} else { die('Insufficient rights to add location'); }		
}

function RemoveLocation($id) {
	if (isSuperAdmin()) {
		$query = sprintf("DELETE FROM uo_location WHERE id=%d", (int)$id);
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
		$query = sprintf("DELETE FROM uo_location_info WHERE location_id=%d", (int)$id);
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		
	} else { die('Insufficient rights to remove location'); }	
}

?>