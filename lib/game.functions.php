<?php 

function GameResult($gameId)
	{
	$query = sprintf("
		SELECT k.Nimi As KNimi, v.Nimi As VNimi, p.* 
		FROM (pelik_peli AS p INNER JOIN pelik_joukkue As k ON (p.Kotijoukkue=k.Joukkue_ID)) INNER JOIN pelik_joukkue AS v ON (p.Vierasjoukkue=v.Joukkue_ID) 
		WHERE p.Peli_ID='%s'",
		mysql_real_escape_string($gameId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return mysql_fetch_assoc($result);
	}

function GameTeamScoreBorad($gameId, $teamId)
	{
	$query = sprintf("
		SELECT p.pelaaja_id, p.enimi, p.snimi, p.jnro, COALESCE(t.tehty,0) AS tehty, COALESCE(s.syotetty,0) AS syotetty, 
		(COALESCE(t.tehty,0) + COALESCE(s.syotetty,0)) AS yht, pel.numero AS numero FROM pelik_pelaaja AS p 
		LEFT JOIN (SELECT m.tekija AS tekija, COUNT(*) AS tehty 
			FROM pelik_maali AS m WHERE m.maali_peli='%s' AND m.tekija IS NOT NULL GROUP BY tekija) AS t ON (p.Pelaaja_ID=t.tekija) 
		LEFT JOIN (SELECT m2.syottaja AS syottaja, COUNT(*) AS syotetty FROM pelik_maali AS m2 
			WHERE m2.maali_peli='%s' AND m2.syottaja IS NOT NULL GROUP BY syottaja) AS s ON (p.Pelaaja_ID=s.syottaja) 
		RIGHT JOIN (SELECT pelannut_pelaaja_id, numero FROM pelik_pelattu WHERE pelattu_peli_id='%s') as pel ON (p.pelaaja_id=pel.pelannut_pelaaja_id) 
			WHERE p.Joukkue='%s' 
		ORDER BY yht DESC, tehty DESC, syotetty DESC, snimi ASC, enimi ASC",
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($teamId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function GameScoreBorad($gameId)
	{
	$query = sprintf("
		SELECT p.jnro, p.pelaaja_id, p.enimi, p.snimi, pj.nimi AS jnimi, COALESCE(t.tehty,0) AS tehty, COALESCE(s.syotetty,0) AS syotetty, 
			(COALESCE(t.tehty,0) + COALESCE(s.syotetty,0)) AS yht 
		FROM pelik_pelaaja AS p LEFT JOIN (SELECT m.tekija AS tekija, COUNT(*) AS tehty 
		FROM pelik_maali AS m WHERE m.maali_peli='%s' AND m.tekija IS NOT NULL
			GROUP BY tekija) AS t ON (p.Pelaaja_ID=t.tekija) 
		LEFT JOIN (SELECT m2.syottaja AS syottaja, COUNT(*) AS syotetty
		FROM pelik_maali AS m2 WHERE m2.maali_peli='%s' AND m2.syottaja IS NOT NULL
			GROUP BY syottaja) AS s ON (p.Pelaaja_ID=s.syottaja) 
		RIGHT JOIN (SELECT pelannut_pelaaja_id, numero FROM pelik_pelattu
			WHERE pelattu_peli_id='%s') as pel ON (p.pelaaja_id=pel.pelannut_pelaaja_id)
		LEFT JOIN pelik_joukkue pj ON (pj.joukkue_id=p.joukkue) WHERE p.jnro IS NOT NULL AND p.snimi IS NOT NULL 
		ORDER BY p.jnro ",
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function GameGoals($gameId)
	{
	$query = sprintf("
		SELECT m.*, s.enimi AS senimi, s.snimi AS ssnimi, t.enimi AS tenimi, t.snimi AS tsnimi 
		FROM (pelik_maali AS m LEFT JOIN pelik_pelaaja AS s ON (m.syottaja = s.pelaaja_id)) 
		LEFT JOIN pelik_pelaaja AS t ON (m.tekija=t.pelaaja_id) 
		WHERE m.maali_peli='%s' 
		ORDER BY m.maali_nro",
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function GameAllGoals($gameId)
	{
	$query = sprintf("
		SELECT maali_nro,aika,kotimaali 
		FROM pelik_maali 
		WHERE maali_peli='%s' 
		ORDER BY aika",
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function GameEvents($gameId)
	{
	$query = sprintf("
		SELECT aika,koti,tyyppi 
		FROM (SELECT aika,koti,'timeout' AS tyyppi FROM `pelik_aikalisa` 
			WHERE aikalisa_peli_id='%s' UNION ALL SELECT aika,koti,tyyppi FROM pelik_pelitapahtuma WHERE peli='%s' ) AS tapahtuma 
		ORDER BY aika ",
		mysql_real_escape_string($gameId),
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function GameTimeouts($gameId)
	{
	$query = sprintf("
		SELECT nro,aika,koti 
		FROM pelik_aikalisa 
		WHERE aikalisa_peli_id='%s' 
		ORDER BY aika",
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function GameTurnovers($gameId)
	{
	$query = sprintf("
		SELECT aika, koti 
		FROM pelik_pelitapahtuma 
		WHERE peli='%s' AND tyyppi='turnover' 
		ORDER BY aika",
		mysql_real_escape_string($gameId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
?>
