<?php 

function SeasonSetCurrent($seasonId)
	{
	$query = sprintf("UPDATE pelik_asetukset SET arvo='%s' WHERE nimi='CurrentSeason'",
		mysql_real_escape_string($seasonId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

?>
