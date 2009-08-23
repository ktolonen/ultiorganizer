<?php 

function Teams($serieId)
	{
	$query = sprintf("
		SELECT pelik_joukkue.Joukkue_ID, pelik_joukkue.Nimi, pelik_joukkue.Seura, pelik_joukkue_sarja.Rank 
		FROM pelik_joukkue 
		LEFT JOIN pelik_joukkue_sarja ON (pelik_joukkue.Joukkue_ID=pelik_joukkue_sarja.Joukkue) 
		WHERE pelik_joukkue_sarja.Sarja = '%s' 
		ORDER BY pelik_joukkue_sarja.Rank ASC",
		mysql_real_escape_string($serieId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function TeamsByName($serieId)
	{
	$query = sprintf("
		SELECT pelik_joukkue.Joukkue_ID, pelik_joukkue.Nimi, pelik_joukkue.Seura
		FROM pelik_joukkue 
		WHERE pelik_joukkue.Sarja = '%s' 
		ORDER BY Nimi ASC",
		mysql_real_escape_string($serieId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function SerieName($serieId)
	{
	$query = sprintf("
		SELECT Nimi, Sarja_ID FROM pelik_sarja WHERE Sarja_ID='%s' ORDER BY Luokat ASC, Sarja_ID ASC",
		mysql_real_escape_string($serieId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$row = mysql_fetch_row($result);
	
	return $row[0];
	}

function SerieScoreBoard($serieId, $sorting, $limit)
	{
	$query = sprintf("
		SELECT p.pelaaja_id, p.enimi, p.snimi, j.nimi AS jnimi, COALESCE(t.tehty,0) AS tehty, 
		COALESCE(s.syotetty,0) AS syotetty, (COALESCE(t.tehty,0) + COALESCE(s.syotetty,0)) AS yht, pel.peleja 
		FROM pelik_pelaaja AS p 
		LEFT JOIN (SELECT m.tekija AS tekija, COUNT(*) AS tehty FROM pelik_maali AS m LEFT JOIN pelik_peli_sarja AS ps ON (m.maali_peli=ps.peli)
		WHERE ps.sarja='%s' AND tekija IS NOT NULL GROUP BY tekija) AS t ON (p.Pelaaja_ID=t.tekija) 
		LEFT JOIN  (SELECT m2.syottaja AS syottaja, COUNT(*) AS syotetty 
		FROM pelik_maali AS m2 LEFT JOIN pelik_peli_sarja AS ps2 ON (m2.maali_peli=ps2.peli) 
		WHERE ps2.sarja='%s' GROUP BY syottaja) AS s ON (p.Pelaaja_ID=s.syottaja) 
		LEFT JOIN pelik_joukkue AS j ON (p.joukkue=j.joukkue_id) 
		LEFT JOIN (SELECT pelannut_pelaaja_id, COUNT(*) AS peleja 
		FROM pelik_pelattu 
		WHERE pelattu_peli_id IN (SELECT peli FROM pelik_peli_sarja WHERE sarja='%s') 
		GROUP BY pelannut_pelaaja_id) AS pel ON (p.pelaaja_id=pel.pelannut_pelaaja_id) 
		WHERE p.Joukkue IN (SELECT Joukkue FROM pelik_joukkue_sarja WHERE Sarja='%s')",
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($serieId));
	
	switch($sorting)
		{
		case "total":
			$query .= " ORDER BY yht DESC, tehty DESC, syotetty DESC, snimi ASC";
			break;
	
		case "goal":
			$query .= " ORDER BY tehty DESC, yht DESC, syotetty DESC, snimi ASC";
			break;

		case "pass":
			$query .= " ORDER BY syotetty DESC, yht DESC, tehty DESC, snimi ASC";
			break;

		case "games":
			$query .= " ORDER BY peleja DESC, yht DESC, tehty DESC, syotetty DESC, snimi ASC";
			break;

		case "team":
			$query .= " ORDER BY jnimi ASC, yht DESC, tehty DESC, syotetty DESC, snimi ASC";
			break;

		case "name":
			$query .= " ORDER BY snimi ASC, yht DESC, tehty DESC, syotetty DESC";
			break;
			
		default:
			$query .= " ORDER BY yht DESC, tehty DESC, syotetty DESC, snimi ASC";
			break;
		}
		
	if($limit > 0)
		{
		$query .= " limit $limit";
		}
	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function SerieStandings($serieId)
	{
	$query = sprintf("
		SELECT j.joukkue_id, j.nimi, js.activerank 
		FROM pelik_joukkue AS j INNER JOIN pelik_joukkue_sarja AS js ON (j.joukkue_id = js.joukkue) 
		WHERE js.sarja='%s' 
		ORDER BY js.activerank ASC, js.rank ASC",
		mysql_real_escape_string($serieId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function SeriesPlayed($fieldId, $seasonId)
	{
	$query = sprintf("
		SELECT s.Sarja_ID, s.Nimi 
		FROM (pelik_peli p INNER JOIN pelik_peli_sarja ps ON (p.Peli_ID=ps.Peli)) 
		INNER JOIN pelik_sarja s ON (ps.Sarja=s.Sarja_ID AND s.kausi='%s') 
		WHERE p.Paikka='%s' AND ps.TimeTable=true 
		GROUP BY s.Sarja_ID, s.Nimi, s.Luokat 
		ORDER BY s.Luokat ASC ",
		mysql_real_escape_string($seasonId),
		mysql_real_escape_string($fieldId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function SerieTotalPlayedGames($serieId)
	{
	$query = sprintf("
		SELECT p.Peli_ID IN (SELECT DISTINCT Maali_Peli FROM pelik_maali) As Maaleja,
			Kj.Joukkue_ID AS kId, Vj.Joukkue_ID AS vId
		FROM ((pelik_peli p INNER JOIN pelik_joukkue AS Kj ON (p.Kotijoukkue=Kj.Joukkue_ID)) 
		INNER JOIN pelik_joukkue AS Vj ON (p.Vierasjoukkue=Vj.Joukkue_ID)) 
		LEFT JOIN pelik_peli_sarja ps ON (p.Peli_ID=ps.Peli) 
		WHERE ps.Sarja = '%s' 
		ORDER BY Aika ASC",
		mysql_real_escape_string($serieId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function SeriesPlayedTournaments($serieId)
	{
	$query = sprintf("
		SELECT DISTINCT Turnaus, AikaAlku, Paikka_ID, Paikka 
		FROM pelik_paikka 
		WHERE AikaAlku <= Now() AND Paikka_ID IN (SELECT pelik_peli.Paikka FROM pelik_peli INNER JOIN pelik_peli_sarja 
			ON (pelik_peli.Peli_ID=pelik_peli_sarja.Peli) 
			WHERE pelik_peli_sarja.Sarja='%s') 
		ORDER BY AikaAlku ASC, Paikka ASC",
		mysql_real_escape_string($serieId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function SeriesGames($serieId, $fieldId)
	{
	$query = sprintf("
		SELECT Kj.Nimi AS KNimi, Vj.Nimi As VNimi, p.Aika, p.Peli_ID, p.Kotipisteet, p.Vieraspisteet, 
			p.Peli_ID IN (SELECT DISTINCT Maali_Peli FROM pelik_maali) As Maaleja,
			Kj.Joukkue_ID AS kId, Vj.Joukkue_ID AS vId
		FROM ((pelik_peli p INNER JOIN pelik_joukkue AS Kj ON (p.Kotijoukkue=Kj.Joukkue_ID)) 
		INNER JOIN pelik_joukkue AS Vj ON (p.Vierasjoukkue=Vj.Joukkue_ID)) 
		LEFT JOIN pelik_peli_sarja ps ON (p.Peli_ID=ps.Peli) 
		WHERE p.Paikka = '%s' AND ps.Sarja = '%s' 
		ORDER BY Aika ASC ",
		mysql_real_escape_string($fieldId),
		mysql_real_escape_string($serieId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
		
function PlayedGames($seasonId, $serieId, $tournamentId)
	{
	if($serieId > 0)
		{
		$query = sprintf("
			SELECT pelik_sarja.Sarja_ID, pelik_sarja.Nimi 
			FROM pelik_sarja 
			WHERE pelik_sarja.Sarja_ID='%s' 
			ORDER BY pelik_sarja.Luokat ASC, pelik_sarja.Sarja_ID ASC",
			mysql_real_escape_string($serieId));
		}
	else
		{
		$query = sprintf("
			SELECT pelik_sarja.Sarja_ID, pelik_sarja.Nimi 
			FROM (pelik_peli INNER JOIN pelik_peli_sarja ON (pelik_peli.Peli_ID = pelik_peli_sarja.Peli)) 
			INNER JOIN pelik_sarja ON (pelik_peli_sarja.Sarja=pelik_sarja.Sarja_ID) 
			WHERE pelik_peli.Paikka='%s' AND pelik_peli_sarja.TimeTable=true AND pelik_sarja.Kausi='%s' 
			GROUP BY pelik_sarja.Sarja_ID, pelik_sarja.Nimi, pelik_sarja.Luokat 
			ORDER BY pelik_sarja.Luokat ASC, pelik_sarja.Sarja_ID ASC",
			mysql_real_escape_string($tournamentId),
			mysql_real_escape_string($seasonId));
		}
	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function SerieRules($serieId)
	{
	$query = sprintf("
		SELECT AikaKatto, PeliPist, PuoliAika, aikalisa FROM pelik_sarja WHERE sarja_id='%s'",
		mysql_real_escape_string($serieId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return mysql_fetch_assoc($result);
	}

function SerieTemplates()
	{
	$query = sprintf("SELECT * FROM pelik_sarja WHERE kausi IS NULL ORDER BY nimi ASC");
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function SerieInfo($serieId)
	{
	$query = sprintf("SELECT * FROM pelik_sarja WHERE sarja_id='%s'",
		mysql_real_escape_string($serieId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return mysql_fetch_assoc($result);
	}	
?>
