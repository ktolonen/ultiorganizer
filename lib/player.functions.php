<?php 

function PlayerInfo($playerId)
	{
	$query = sprintf("
		SELECT enimi, snimi, numero, jnro, joukkue, pelik_joukkue.nimi AS jnimi 
		FROM pelik_pelaaja p 
		LEFT JOIN pelik_joukkue ON p.joukkue=joukkue_id 
		WHERE pelaaja_id='%s'",
		mysql_real_escape_string($playerId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return mysql_fetch_assoc($result);
	}

function PlayerPlayedSeasons($playerId)
	{
	$query = sprintf("
		SELECT pelaaja_id, pelik_joukkue.nimi 
		FROM pelik_pelaaja p 
		LEFT JOIN pelik_joukkue ON p.joukkue=joukkue_id 
		WHERE jnro='%s'",
		mysql_real_escape_string($playerId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function PlayerPlayedSeries($playerId)
	{
	$query = sprintf("
		SELECT DISTINCT pps.sarja, ps.nimi, ps.kausi 
		FROM pelik_pelattu AS pp 
		LEFT JOIN pelik_peli_sarja AS pps ON (pp.pelattu_peli_id = pps.peli) 
		LEFT JOIN pelik_sarja AS ps ON(pps.sarja = ps.sarja_id)
		WHERE pp.pelannut_pelaaja_id='%s'",
		mysql_real_escape_string($playerId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function PlayerSeasonGames($playerId, $seasonId)
	{
	$query = sprintf("
		SELECT peli_id,kotijoukkue,vierasjoukkue 
		FROM pelik_peli p 
		WHERE p.sarja IN(SELECT pelik_sarja.sarja_id FROM pelik_sarja WHERE kausi='%s') 
		AND p.peli_id IN (SELECT pelik_maali.maali_peli FROM pelik_maali WHERE tekija='%s' OR syottaja='%s')",
		mysql_real_escape_string($seasonId),
		mysql_real_escape_string($playerId),
		mysql_real_escape_string($playerId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function PlayerGameEvents($playerId, $gameId)
	{
	$query = sprintf("
		SELECT aika,ktilanne,vtilanne,syottaja,tekija 
		FROM pelik_maali 
		WHERE maali_peli='%s' AND (tekija='%s' OR syottaja='%s') 
		ORDER BY aika",
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($playerId),
		mysql_real_escape_string($playerId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function PlayerPlayedGames($playerId, $serieId)
	{
	$query = sprintf("
		SELECT COUNT(*) AS games 
		FROM pelik_pelattu 
		WHERE pelattu_peli_id IN (SELECT peli FROM pelik_peli_sarja WHERE sarja='%s') 
		AND pelannut_pelaaja_id='%s'",
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($playerId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$row = mysql_fetch_row($result);
	return intval($row[0]);
	}

function PlayerPasses($playerId, $serieId)
	{
	$query = sprintf("
		SELECT COUNT(*) AS passes 
		FROM pelik_maali 
		WHERE maali_peli IN(SELECT peli FROM pelik_peli_sarja WHERE sarja='%s') 
		AND syottaja='%s'",
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($playerId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$row = mysql_fetch_row($result);
	return intval($row[0]);
	}
	
function PlayerGoals($playerId, $serieId)
	{
	$query = sprintf("
		SELECT COUNT(*) AS goals 
		FROM pelik_maali 
		WHERE maali_peli IN(SELECT peli FROM pelik_peli_sarja WHERE sarja='%s') 
		AND tekija='%s'",
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($playerId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$row = mysql_fetch_row($result);
	return intval($row[0]);

	}
	
?>
