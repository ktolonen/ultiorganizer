<?php 

function Series($seasonId)
	{
	$query = sprintf("SELECT sarja_id, nimi FROM pelik_sarja WHERE kausi = '%s' AND ShowTeams=True ORDER BY Luokat ASC, Sarja_ID ASC",
		mysql_real_escape_string($seasonId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function SeasonSeriesInfo($seasonId)
	{
	$query = sprintf("SELECT sarja_id, nimi, showteams, jatkosarja FROM pelik_sarja WHERE kausi = '%s' ORDER BY Luokat ASC, Sarja_ID ASC",
		mysql_real_escape_string($seasonId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function CurrenSeason()
	{
	$query = sprintf("SELECT arvo FROM pelik_asetukset WHERE nimi = 'CurrentSeason'");
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }

	$row = mysql_fetch_row($result);
	
	return $row[0];
	}

function CurrenSeasonName()
	{
	$query = sprintf("SELECT arvo FROM pelik_asetukset WHERE nimi = 'CurrentSeason'");
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }

	$row = mysql_fetch_row($result);
	
	$year = strtok($row[0], "."); 
	$season = strtok(".");
	$name; 
	
	if($season=="1")
		{
		$name= "Kes&auml; $year";
		}
	elseif($season=="2")
		{
		$name = "Talvi $year";
		}
	else
		{
		$name = $row[0];
		}

	return $name;
	}
	
function Seasons()
	{
	$query = sprintf("SELECT DISTINCT kausi FROM pelik_sarja ORDER BY kausi DESC");
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function ComingTournaments($seasonId)
	{
	$mysqldate = date( 'Y-m-d H:i:s', time() );

	$query = sprintf("
		SELECT Turnaus, AikaAlku, Paikka_ID, Paikka FROM pelik_paikka 
		WHERE (AikaAlku > '$mysqldate' AND Kausi = '%s') 
		ORDER BY AikaAlku ASC, Paikka ASC",
		mysql_real_escape_string($seasonId));
	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function PlayedTournaments($seasonId)
	{
	$query = sprintf("
		SELECT DISTINCT Turnaus, AikaAlku, Paikka_ID, Paikka 
		FROM pelik_paikka 
		WHERE AikaAlku <= Now() AND Kausi = '%s'
		ORDER BY AikaAlku DESC, Paikka ASC",
		mysql_real_escape_string($seasonId));
	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function Timetable($tournamentId, $seasonId)
	{
	$query = sprintf("
		SELECT Turnaus, Paikka, AikaAlku, Paikka_ID 
		FROM pelik_paikka 
		WHERE UPPER(Turnaus) = '%s' AND kausi='%s' 
		ORDER BY AikaAlku ASC, Paikka ASC",
		mysql_real_escape_string($tournamentId),
		mysql_real_escape_string($seasonId));
	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function TournamentNames($seasonId)
	{
	$query = sprintf("
		SELECT DISTINCT Turnaus
		FROM pelik_paikka 
		WHERE Kausi = '%s' 
		ORDER BY Turnaus ASC",
		mysql_real_escape_string($seasonId));
	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function TournamentPlaces($seasonId, $serieId, $placeId)
	{
	if($serieId)
		{
		$query = sprintf("
			SELECT * 
			FROM pelik_paikka 
			WHERE paikka_id='%s' AND AikaAlku <= (Now()) AND Paikka_ID IN (SELECT pelik_peli.Paikka FROM pelik_peli 
				INNER JOIN pelik_peli_sarja ON (pelik_peli.Peli_ID=pelik_peli_sarja.Peli) 
				WHERE pelik_peli_sarja.Sarja='%s') 
			ORDER BY AikaAlku ASC",
			mysql_real_escape_string($placeId),
			mysql_real_escape_string($serieId));
		}
	else
		{
		$query = sprintf("
			SELECT * 
			FROM pelik_paikka 
			WHERE paikka_id='%s' AND Kausi='%s' 
			ORDER BY AikaAlku ASC",
			mysql_real_escape_string($placeId),
			mysql_real_escape_string($seasonId));
		}
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}	
	
function GetAllPlayedGames($team1, $team2, $serie, $sorting)
	{
	$query = sprintf("
		SELECT pj1.nimi AS knimi, pj2.nimi AS vnimi, pp.kotipisteet, pp.vieraspisteet, ps.kausi, ps.nimi, pp.peli_id, ps.sarja_id 
		FROM pelik_peli pp 
		LEFT JOIN pelik_sarja ps ON (ps.sarja_id=pp.sarja) 
		LEFT JOIN pelik_joukkue pj1 ON(pp.kotijoukkue=pj1.joukkue_id) 
		LEFT JOIN pelik_joukkue pj2 ON (pp.vierasjoukkue=pj2.joukkue_id)
		WHERE ((pj1.nimi='%s' AND pj2.nimi='%s') OR (pj1.nimi='%s' AND pj2.nimi='%s')) AND ps.nimi RLIKE '^%s' AND pp.valid=true ",
		mysql_real_escape_string($team1),
		mysql_real_escape_string($team2),
		mysql_real_escape_string($team2),
		mysql_real_escape_string($team1),
		mysql_real_escape_string($serie));
	
	switch($sorting)
		{
		case "team":
			$query .= " ORDER BY knimi ASC, vnimi ASC";
			break;
			
		case "result":
			$query .= " ORDER BY pp.kotipisteet DESC, pp.vieraspisteet DESC, knimi ASC, vnimi ASC";
			break;
			
		case "serie":
			$query .= " ORDER BY ps.kausi ASC, ps.nimi ASC, knimi ASC, vnimi ASC";
			break;
			
		default:
			$query .= " ORDER BY ps.kausi ASC, ps.nimi ASC, knimi ASC, vnimi ASC";
			break;
		}	
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}	

function SeasonTeams()
	{
	$query = sprintf("
		SELECT pelik_joukkue.Joukkue_ID, pelik_joukkue.Nimi, pelik_joukkue.Seura
		FROM pelik_joukkue 
		WHERE pelik_joukkue.Sarja IS NULL 
		ORDER BY Nimi ASC");
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
?>
