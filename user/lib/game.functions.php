<?php 

function GameSetResult($gameId, $home, $away)
	{
	$query = sprintf("UPDATE pelik_peli SET Kotipisteet='%s', Vieraspisteet='%s' WHERE Peli_ID='%s'",
		mysql_real_escape_string($home),
		mysql_real_escape_string($away),
		mysql_real_escape_string($gameId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function GameAddPlayer($gameId, $playerId, $number)
	{
	$query = sprintf("
		INSERT INTO pelik_pelattu 
		(Pelattu_Peli_ID, Pelannut_Pelaaja_ID, Numero) 
		VALUES ('%s', '%s', '%s')",
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($playerId),
		mysql_real_escape_string($number));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function GameRemovePlayer($gameId, $playerId)
	{
	$query = sprintf("
		DELETE FROM pelik_pelattu 
		WHERE Pelattu_Peli_ID='%s' AND Pelannut_Pelaaja_ID='%s'",
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($playerId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function GameSetPlayerNumber($gameId, $playerId, $number)
	{

	$query = sprintf("
		UPDATE pelik_pelattu 
		SET Numero='%s' 
		WHERE Pelattu_Peli_ID='%s' AND Pelannut_Pelaaja_ID='%s'",
		mysql_real_escape_string($number),
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($playerId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function GameRemoveAllScores($gameId)
	{
	$query = sprintf("
		DELETE FROM pelik_maali 
		WHERE maali_peli='%s'",
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function GameAddScore($gameId, $pass, $goal, $time, $number, $hscores, $ascores, $home, $callahan)
	{
	$query = sprintf("
		INSERT INTO pelik_maali 
		(maali_peli, maali_nro, syottaja, tekija, aika, ktilanne, vtilanne, kotimaali, callahan) 
		VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')",
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($number),
		mysql_real_escape_string($pass),
		mysql_real_escape_string($goal),
		mysql_real_escape_string($time),
		mysql_real_escape_string($hscores),
		mysql_real_escape_string($ascores),
		mysql_real_escape_string($home),
		mysql_real_escape_string($callahan));
		
	$result = mysql_query($query);
	//support for old database
	if (!$result) 
		{ 
		$query = sprintf("
		INSERT INTO pelik_maali 
		(maali_peli, maali_nro, syottaja, tekija, aika, ktilanne, vtilanne, kotimaali) 
		VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')",
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($number),
		mysql_real_escape_string($pass),
		mysql_real_escape_string($goal),
		mysql_real_escape_string($time),
		mysql_real_escape_string($hscores),
		mysql_real_escape_string($ascores),
		mysql_real_escape_string($home));
		
		$result = mysql_query($query);
		}
	
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
function GameRemoveAllTimeouts($gameId)
	{
	$query = sprintf("
		DELETE FROM pelik_aikalisa 
		WHERE aikalisa_peli_id='%s'",
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function GameAddTimeout($gameId, $number, $time, $home)
	{
	$query = sprintf("
		INSERT INTO pelik_aikalisa 
		(aikalisa_peli_id, nro, aika, koti) 
		VALUES ('%s', '%s', '%s', '%s')",
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($number),
		mysql_real_escape_string($time),
		mysql_real_escape_string($home));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function GameSetScoreSheetKeeper($gameId, $name)
	{
	$query = sprintf("
		UPDATE pelik_peli 
		SET toim='%s' 
		WHERE peli_id='%s'",
		mysql_real_escape_string($name),
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function GameSetHalftime($gameId, $time)
	{
	$query = sprintf("
		UPDATE pelik_peli 
		SET puoliaika='%s' 
		WHERE peli_id='%s'",
		mysql_real_escape_string($time),
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
?>
