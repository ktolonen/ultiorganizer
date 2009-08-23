<?php 

function AddSerieTemplate($name,$timeoutlength,$halftimelength,$gameto,$timecap,$pointcap,
	$extrapoint, $halftimepoint, $timeouts, $timeoutsfor, $timeoutsOnOvertime,
	$timeoutsAfter, $timebetweenPoints, $continuationserie)
	{
	$query = sprintf("
		INSERT INTO pelik_sarja 
		(nimi, aikalisa, puoliaika, pelipist, aikakatto, pistekatto, lisapist, puoliaikapist, aikailisia, 
		aikalisiaper, aikalisiayliajalla, aikalisiaikarajan,pisteidenvali, jatkosarja, showteams, showserstat) 
		VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 0, 0)",
		mysql_real_escape_string($name),
		mysql_real_escape_string($timeoutlength),
		mysql_real_escape_string($halftimelength),
		mysql_real_escape_string($gameto),
		mysql_real_escape_string($timecap),
		mysql_real_escape_string($pointcap),
		mysql_real_escape_string($extrapoint),
		mysql_real_escape_string($halftimepoint),
		mysql_real_escape_string($timeouts),
		mysql_real_escape_string($timeoutsfor),
		mysql_real_escape_string($timeoutsOnOvertime),
		mysql_real_escape_string($timeoutsAfter),
		mysql_real_escape_string($timebetweenPoints),
		mysql_real_escape_string($continuationserie));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return mysql_insert_id();
	}

function SetSerieTemplate($serieId, $name,$timeoutlength,$halftimelength,$gameto,$timecap,$pointcap,
	$extrapoint, $halftimepoint, $timeouts, $timeoutsfor, $timeoutsOnOvertime,
	$timeoutsAfter, $timebetweenPoints, $continuationserie)
	{
	$query = sprintf("
		UPDATE pelik_sarja SET
		nimi='%s', aikalisa='%s', puoliaika='%s', pelipist='%s', aikakatto='%s', pistekatto='%s', 
		lisapist='%s', puoliaikapist='%s', aikailisia='%s',	aikalisiaper='%s', aikalisiayliajalla='%s', 
		aikalisiaikarajan='%s', pisteidenvali='%s', jatkosarja='%s'
		WHERE sarja_id='%s'",
		mysql_real_escape_string($name),
		mysql_real_escape_string($timeoutlength),
		mysql_real_escape_string($halftimelength),
		mysql_real_escape_string($gameto),
		mysql_real_escape_string($timecap),
		mysql_real_escape_string($pointcap),
		mysql_real_escape_string($extrapoint),
		mysql_real_escape_string($halftimepoint),
		mysql_real_escape_string($timeouts),
		mysql_real_escape_string($timeoutsfor),
		mysql_real_escape_string($timeoutsOnOvertime),
		mysql_real_escape_string($timeoutsAfter),
		mysql_real_escape_string($timebetweenPoints),
		mysql_real_escape_string($continuationserie),
		mysql_real_escape_string($serieId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function DeleteSerie($serieId)
	{
	$query = sprintf("DELETE FROM pelik_sarja WHERE sarja_id='%s'",
		mysql_real_escape_string($serieId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function SerieFromSerieTemplate($season, $name, $serieTemplateId)
	{
	$query = sprintf("INSERT INTO pelik_sarja 
		(aikalisa, puoliaika, pelipist, aikakatto, pistekatto, lisapist, puoliaikapist, aikailisia, 
		aikalisiaper, aikalisiayliajalla, aikalisiaikarajan,pisteidenvali, jatkosarja, showteams, showserstat)
		SELECT aikalisa, puoliaika, pelipist, aikakatto, pistekatto, lisapist, puoliaikapist, aikailisia, 
		aikalisiaper, aikalisiayliajalla, aikalisiaikarajan,pisteidenvali, jatkosarja, showteams, showserstat 
		FROM pelik_sarja WHERE sarja_id='%s'",
		mysql_real_escape_string($serieTemplateId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$id = mysql_insert_id();
	
	$query = sprintf("
		UPDATE pelik_sarja SET
		nimi='%s', kausi='%s' WHERE sarja_id='%s'",
		mysql_real_escape_string($name),
		mysql_real_escape_string($season),
		mysql_real_escape_string($id));
	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $id;
	}
	
function SetSerie($serieId, $params)
	{
	$query = sprintf("
		UPDATE pelik_sarja SET
		nimi='%s', aikalisa='%s', puoliaika='%s', pelipist='%s', aikakatto='%s', pistekatto='%s', 
		lisapist='%s', puoliaikapist='%s', aikailisia='%s',	aikalisiaper='%s', aikalisiayliajalla='%s', 
		aikalisiaikarajan='%s', pisteidenvali='%s', jatkosarja='%s', showserstat='%s', showteams='%s'
		WHERE sarja_id='%s'",
		mysql_real_escape_string($params['nimi']),
		mysql_real_escape_string($params['aikalisa']),
		mysql_real_escape_string($params['puoliaika']),
		mysql_real_escape_string($params['pelipist']),
		mysql_real_escape_string($params['aikakatto']),
		mysql_real_escape_string($params['pistekatto']),
		mysql_real_escape_string($params['lisapist']),
		mysql_real_escape_string($params['puoliaikapist']),
		mysql_real_escape_string($params['aikailisia']),
		mysql_real_escape_string($params['aikalisiaper']),
		mysql_real_escape_string($params['aikalisiayliajalla']),
		mysql_real_escape_string($params['aikalisiaikarajan']),
		mysql_real_escape_string($params['pisteidenvali']),
		mysql_real_escape_string($params['jatkosarja']),
		mysql_real_escape_string($params['showserstat']),
		mysql_real_escape_string($params['showteams']),
		mysql_real_escape_string($serieId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function SerieGameAddTeam($serieId, $teamId, $rank)
	{
	$query = sprintf("INSERT INTO pelik_joukkue_sarja 
			(joukkue, sarja, rank) 
			VALUES	('%s','%s','%s')",
		mysql_real_escape_string($teamId),
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($rank));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$query = sprintf("UPDATE pelik_joukkue SET sarja='%s' WHERE joukkue_id='%s'",
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($teamId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function SerieGameDeleteTeam($serieId, $teamId)
	{
	$query = sprintf("DELETE FROM pelik_joukkue_sarja WHERE sarja='%s' AND joukkue='%s'",
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($teamId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$query = sprintf("UPDATE pelik_joukkue SET sarja=NULL WHERE joukkue_id='%s'",
		mysql_real_escape_string($teamId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function SerieGameSetTeam($serieId, $teamId, $rank)
	{
	$query = sprintf("UPDATE pelik_joukkue_sarja 
			SET rank='%s' WHERE sarja='%s' AND joukkue='%s'",
		mysql_real_escape_string($rank),
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($teamId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$query = sprintf("UPDATE pelik_joukkue SET sarja='%s' WHERE joukkue_id='%s'",
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($teamId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
?>
