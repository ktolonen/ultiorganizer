<?php 

function PlaceInfo($placeId)
	{
	$query = sprintf("SELECT paikka,info FROM pelik_paikka WHERE Paikka_ID= '%s'",
		mysql_real_escape_string($placeId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return  mysql_fetch_assoc($result);
	}
	
function PlaceTemplates()
	{
	$query = sprintf("SELECT * FROM pelik_paikka WHERE kausi IS NULL");
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return  $result;
	}
	
?>