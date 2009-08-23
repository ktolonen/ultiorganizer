<?php 

function AddPlaceTemplate($name,$info,$maplink)
	{
	$query = sprintf("
		INSERT INTO pelik_paikka
		(paikka, info) 
		VALUES ('%s', '%s')",
		mysql_real_escape_string($name),
		mysql_real_escape_string($maplink));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return mysql_insert_id();
	}

function SetPlaceTemplate($placeId, $name, $info)
	{
	$query = sprintf("
		UPDATE pelik_paikka SET
		paikka='%s', info='%s'
		WHERE paikka_id='%s'",
		mysql_real_escape_string($name),
		mysql_real_escape_string($info),
		mysql_real_escape_string($placeId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function DeletePlaceTemplate($placeId)
	{
	$query = sprintf("DELETE FROM pelik_paikka WHERE paikka_id='%s'",
		mysql_real_escape_string($placeId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
?>
