<?php 

function RemovePlayer($playerId)
	{
	$query = sprintf("DELETE FROM pelik_pelaaja WHERE pelaaja_id='%s'",
		mysql_real_escape_string($playerId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function AddPlayer($teamId, $member)
	{
	$query = sprintf("
		INSERT INTO pelik_pelaaja (ENimi, SNimi, Joukkue, JNro, SAika)
		SELECT etunimi, sukunimi, %s As Joukkue, jasennumero, syntaika FROM pelik_jasenet 
		WHERE jasennumero='%s'",
		mysql_real_escape_string($teamId),
		mysql_real_escape_string($member));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
		
?>
