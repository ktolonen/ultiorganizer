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

function MembershipInfo($player_id)
	{
	$query = sprintf("
		SELECT p.enimi, p.snimi, p.jnro, p.saika, jas.jasenmaksu As JMaksu, jas.ultimate_lisenssi As Lisenssi 
		FROM pelik_pelaaja AS p 
		LEFT JOIN pelik_jasenet AS jas ON (p.jnro=jas.jasennumero) 
		WHERE p.pelaaja_id='%s' 
		ORDER BY SNimi ASC, ENimi ASC",
		mysql_real_escape_string($player_id));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return mysql_fetch_assoc($result);
	}

function PlayerNumber($playerId, $gameId)
	{
	$query = sprintf("
		SELECT pel.Numero 
		FROM pelik_pelaaja AS p 
		LEFT JOIN (SELECT pelannut_pelaaja_id, Numero FROM pelik_pelattu  WHERE pelattu_peli_id='%s')
			AS pel ON (p.pelaaja_id=pel.pelannut_pelaaja_id) 
		WHERE p.Pelaaja_ID='%s'
		ORDER BY p.SNimi, p.ENimi",
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($playerId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	if(!mysql_num_rows($result))
		return -1;
		
	$row = mysql_fetch_row($result);
	
	if(is_numeric($row[0]))
		return intval($row[0]);
	else
		return -1;
	}

function PlayerSearch($membershipId, $birth, $first_name, $last_name)
	{
	$condition = "";
	
	if(strlen($membershipId))
		{
		if(strlen($condition))
			$condition .= sprintf(" AND Jasennumero='%s'",mysql_real_escape_string($membershipId));
		else
			$condition .= sprintf("Jasennumero='%s'",mysql_real_escape_string($membershipId));
		}
	if(strlen($birth))
		{
		$datetime = date_create($birth);
		$birth = date_format($datetime, 'Y-m-d H:i:s');
		
		if(strlen($condition))
			$condition .= sprintf(" AND SyntAika='%s'",mysql_real_escape_string($birth));
		else
			$condition .= sprintf("SyntAika='%s'",mysql_real_escape_string($birth));
		}
	
	if(strlen($first_name))
		{
		if(strlen($condition))
			$condition .= sprintf(" AND Etunimi LIKE '%s'",mysql_real_escape_string($first_name));
		else
			$condition .= sprintf("Etunimi LIKE '%s'",mysql_real_escape_string($first_name));
		}

	if(strlen($last_name))
		{
		if(strlen($condition))
			$condition .= sprintf(" AND Sukunimi LIKE '%s'",mysql_real_escape_string($last_name));
		else
			$condition .= sprintf("Sukunimi LIKE '%s'",mysql_real_escape_string($last_name));
		}
	
	if(strlen($condition))
		{
		$query = sprintf("
			SELECT Etunimi, Sukunimi, Jasenmaksu, Jasennumero, Email, Ultimate_lisenssi As Lisenssi, SyntAika 
			FROM pelik_jasenet 
			WHERE %s
			ORDER BY Sukunimi ASC, Etunimi ASC",
			$condition);
			
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
	
		return $result;
		}
	
	return false;
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
		WHERE pelattu_peli_id IN (SELECT peli FROM pelik_peli_sarja 
			LEFT JOIN pelik_pelattu AS pp ON (pp.pelattu_peli_id=peli) 
			WHERE sarja='%s' AND pp.pelannut_pelaaja_id='%s') 
		AND pelannut_pelaaja_id='%s'",
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($playerId),
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
		WHERE maali_peli IN(SELECT peli FROM pelik_peli_sarja
			LEFT JOIN pelik_pelattu AS pp ON (pp.pelattu_peli_id=peli) 
			WHERE sarja='%s' AND pp.pelannut_pelaaja_id='%s') 
		AND syottaja='%s'",
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($playerId),
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
		WHERE maali_peli IN(SELECT peli FROM pelik_peli_sarja
			LEFT JOIN pelik_pelattu AS pp ON (pp.pelattu_peli_id=peli) 
			WHERE sarja='%s' AND pp.pelannut_pelaaja_id='%s') 
		AND tekija='%s'",
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($playerId),
		mysql_real_escape_string($playerId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$row = mysql_fetch_row($result);
	return intval($row[0]);
	}
	
?>
