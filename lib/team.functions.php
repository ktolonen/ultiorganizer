<?php 

function TeamPlayerList($teamId)
	{
	$query = sprintf("SELECT pelaaja_id FROM pelik_pelaaja WHERE Joukkue = '%s' ORDER BY SNimi ASC, ENimi ASC",
		mysql_real_escape_string($teamId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function TeamName($teamId)
	{
	$query = sprintf("SELECT Nimi FROM pelik_joukkue WHERE Joukkue_ID='%s'",
		mysql_real_escape_string($teamId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	$row = mysql_fetch_assoc($result);
	$name = $row["Nimi"]; 
	mysql_free_result($result);
	
	return $name;
	}

function TeamInfo($teamId)
	{
	$query = sprintf("SELECT pj.nimi, pj.seura, pj.sarja, ps.nimi AS snimi 
		FROM pelik_joukkue pj 
		LEFT JOIN pelik_sarja ps ON (pj.sarja=ps.sarja_id) 
		WHERE pj.joukkue_id = '%s'",
		mysql_real_escape_string($teamId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return  mysql_fetch_assoc($result);
	}

function TeamPlayedSeasons($teamName,$teamSerie)
	{
	$query = sprintf("SELECT pj.joukkue_id, ps.sarja_id, ps.kausi 
		FROM pelik_joukkue pj
		LEFT JOIN pelik_sarja ps ON (pj.sarja=ps.sarja_id) 
		WHERE pj.nimi='%s' AND ps.nimi RLIKE \"^%s\" 
		ORDER BY kausi, sarja",
		mysql_real_escape_string($teamName),
		mysql_real_escape_string($teamSerie));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}

function TeamSeason($teamId)
	{
	$query = sprintf("SELECT DISTINCT Kausi FROM pelik_sarja WHERE Sarja_ID IN (SELECT Sarja FROM pelik_joukkue_sarja WHERE Joukkue='%s')",
		mysql_real_escape_string($teamId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	$row = mysql_fetch_row($result);
	
	return $row[0];
	}

function TeamComingGames($teamId, $placeId)
	{
	$query = sprintf("
		SELECT Kj.Nimi AS KNimi, Vj.Nimi As VNimi, p.Aika, p.Peli_ID, p.Kotipisteet,p.Vieraspisteet, Kj.Joukkue_ID AS kId, Vj.Joukkue_ID AS vId  
		FROM ((pelik_peli p INNER JOIN pelik_joukkue AS Kj ON (p.Kotijoukkue=Kj.Joukkue_ID)) 
		INNER JOIN pelik_joukkue AS Vj ON (p.Vierasjoukkue=Vj.Joukkue_ID)) 
		WHERE (p.Paikka='%s') AND (p.Kotijoukkue='%s' OR p.Vierasjoukkue='%s') 
		ORDER BY Aika ASC",
		mysql_real_escape_string($placeId),
		mysql_real_escape_string($teamId),
		mysql_real_escape_string($teamId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
function TeamTournamentGames($teamId, $placeId)
	{
	$query = sprintf("
		SELECT Kj.Nimi AS KNimi, Vj.Nimi AS VNimi, p.Aika, p.Kotipisteet, p.Vieraspisteet, p.Peli_ID, Kj.Joukkue_ID AS kId, Vj.Joukkue_ID AS vId,
			p.Peli_ID IN (SELECT DISTINCT Maali_Peli FROM pelik_maali) As Maaleja		
		FROM pelik_peli AS p, pelik_joukkue AS Kj, pelik_joukkue AS Vj 
		WHERE p.Kotijoukkue = Kj.Joukkue_ID And p.Vierasjoukkue = Vj.Joukkue_ID AND p.Paikka = '%s' 
			AND (p.Vierasjoukkue = '%s' OR p.Kotijoukkue = '%s') AND (Aika < Now()) 
		ORDER BY Aika ASC",
		mysql_real_escape_string($placeId),
		mysql_real_escape_string($teamId),
		mysql_real_escape_string($teamId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function TeamGames($teamId)
	{
	$query = sprintf("SELECT pp.peli_id, pp.kotijoukkue, pp.vierasjoukkue, pp.kotipisteet, 
					pp.vieraspisteet, pp.sarja, ps.kausi, ps.nimi, pjs.activerank 
				FROM pelik_peli pp 
				LEFT JOIN pelik_sarja ps ON (ps.sarja_id=pp.sarja) 
				LEFT JOIN pelik_joukkue_sarja pjs ON(pp.sarja=pjs.sarja AND pjs.joukkue='%s') WHERE pp.valid=true 
					AND (pp.vierasjoukkue='%s' OR pp.kotijoukkue='%s') 
				ORDER BY pp.sarja",
		mysql_real_escape_string($teamId),
		mysql_real_escape_string($teamId),
		mysql_real_escape_string($teamId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function TeamPlayedGames($teamName, $serie, $sorting)
	{
	$query = sprintf("SELECT pj1.nimi AS knimi, pj2.nimi AS vnimi, pp.kotipisteet, pp.vieraspisteet, 
	ps.kausi, ps.nimi, pp.peli_id, ps.sarja_id 
	FROM pelik_peli pp 
	LEFT JOIN pelik_sarja ps ON (ps.sarja_id=pp.sarja) 
	LEFT JOIN pelik_joukkue pj1 ON(pp.kotijoukkue=pj1.joukkue_id) 
	LEFT JOIN pelik_joukkue pj2 ON (pp.vierasjoukkue=pj2.joukkue_id)
	WHERE (pj1.nimi='%s' OR pj2.nimi='%s') AND ps.nimi 
	RLIKE \"^%s\" AND pp.valid=true",
		mysql_real_escape_string($teamName),
		mysql_real_escape_string($teamName),
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

function TeamStats($serieId,$teamId)
	{
	$query = sprintf("
		SELECT COUNT(*) AS ottelut, COUNT((kotijoukkue='%s' AND (kotipisteet>vieraspisteet)) OR (vierasjoukkue='%s' AND (kotipisteet<vieraspisteet)) OR NULL) AS voitot 
		FROM pelik_peli 
		WHERE (kotipisteet != vieraspisteet) AND (kotijoukkue='%s' OR vierasJoukkue='%s') AND peli_id IN (SELECT peli FROM pelik_peli_sarja WHERE sarja='%s')",
		mysql_real_escape_string($teamId),
		mysql_real_escape_string($teamId),
		mysql_real_escape_string($teamId),
		mysql_real_escape_string($teamId),
		mysql_real_escape_string($serieId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return mysql_fetch_assoc($result);
	}

function TeamPoints($serieId,$teamId)
	{
	$query = sprintf("
		SELECT j.joukkue_id, COALESCE(k.pisteet,0) + COALESCE(v.pisteet,0) AS pisteet, COALESCE(k.vastaan,0) + COALESCE(v.vastaan,0) AS vastaan
		FROM pelik_joukkue AS j 
		LEFT JOIN (SELECT kotijoukkue, FORMAT(SUM(kotipisteet),0) AS pisteet, FORMAT(SUM(vieraspisteet),0) AS vastaan
		FROM pelik_peli 
		WHERE kotijoukkue='%s' AND peli_id IN (SELECT peli FROM pelik_peli_sarja WHERE sarja='%s') GROUP BY kotijoukkue) AS k 
		ON (j.joukkue_id=k.kotijoukkue) 
		LEFT JOIN (SELECT vierasjoukkue, FORMAT(SUM(vieraspisteet),0) AS pisteet, FORMAT(SUM(kotipisteet),0) AS vastaan 
		FROM pelik_peli WHERE vierasjoukkue='%s' AND peli_id IN (SELECT peli FROM pelik_peli_sarja WHERE sarja='%s') GROUP BY vierasjoukkue) AS v 
		ON (j.joukkue_id=v.vierasjoukkue) WHERE j.joukkue_id='%s'",
		mysql_real_escape_string($teamId),
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($teamId),
		mysql_real_escape_string($serieId),
		mysql_real_escape_string($teamId));
		
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return mysql_fetch_assoc($result);
	}

function TeamScoreBoard($teamId, $serieId, $sorting, $limit)
	{
	if($serieId)
		{
		$query = sprintf("
			SELECT p.pelaaja_id, p.enimi, p.snimi, j.nimi AS jnimi, COALESCE(t.tehty,0) AS tehty, COALESCE(s.syotetty,0) AS syotetty, 
				(COALESCE(t.tehty,0) + COALESCE(s.syotetty,0)) AS yht, COALESCE(pel.peleja,0) AS peleja 
			FROM pelik_pelaaja AS p 
			LEFT JOIN (SELECT m.tekija AS tekija, COUNT(*) AS tehty FROM pelik_maali AS m 
			LEFT JOIN pelik_peli_sarja AS ps ON (m.maali_peli=ps.peli) WHERE ps.sarja='%s' AND tekija IS NOT NULL GROUP BY tekija) AS t ON (p.Pelaaja_ID=t.tekija) 
			LEFT JOIN (SELECT m2.syottaja AS syottaja, COUNT(*) AS syotetty FROM pelik_maali AS m2 
			LEFT JOIN pelik_peli_sarja AS ps2 ON (m2.maali_peli=ps2.peli) WHERE ps2.sarja='%s' GROUP BY syottaja) AS s ON (p.Pelaaja_ID=s.syottaja) 
			LEFT JOIN pelik_joukkue AS j ON (p.joukkue=j.joukkue_id) 
			LEFT JOIN (SELECT pelannut_pelaaja_id, COUNT(*) AS peleja FROM pelik_pelattu 
				WHERE pelattu_peli_id IN (SELECT peli FROM pelik_peli_sarja WHERE sarja='%s') 
				GROUP BY pelannut_pelaaja_id) AS pel ON (p.pelaaja_id=pel.pelannut_pelaaja_id) WHERE p.Joukkue='%s'",
			mysql_real_escape_string($serieId),
			mysql_real_escape_string($serieId),
			mysql_real_escape_string($serieId),
			mysql_real_escape_string($teamId));
		
		}
	else
		{
		$query = sprintf("
			SELECT p.pelaaja_id, p.enimi, p.snimi, j.nimi AS jnimi, COALESCE(t.tehty,0) AS tehty, COALESCE(s.syotetty,0) AS syotetty, 
				(COALESCE(t.tehty,0) + COALESCE(s.syotetty,0)) AS yht, COALESCE(pel.peleja,0) AS peleja 
			FROM pelik_pelaaja AS p 
			LEFT JOIN (SELECT m.tekija AS tekija, COUNT(*) AS tehty FROM pelik_maali AS m LEFT JOIN pelik_peli AS peli ON (m.maali_peli=peli.peli_id) 
				WHERE (peli.kotijoukkue='%s' or peli.vierasjoukkue='%s') AND tekija IS NOT NULL GROUP BY tekija) AS t ON (p.Pelaaja_ID=t.tekija) 
			LEFT JOIN  (SELECT m2.syottaja AS syottaja, COUNT(*) AS syotetty FROM pelik_maali AS m2 
			LEFT JOIN pelik_peli AS peli2 ON (m2.maali_peli=peli2.peli_id) 
				WHERE (peli2.kotijoukkue='%s' or peli2.vierasjoukkue='%s') GROUP BY syottaja) AS s ON (p.Pelaaja_ID=s.syottaja) 
			LEFT JOIN pelik_joukkue AS j ON (p.joukkue=j.joukkue_id) 
			LEFT JOIN (SELECT pelannut_pelaaja_id, COUNT(*) AS peleja FROM pelik_pelattu 
				WHERE pelattu_peli_id IN (SELECT peli_id FROM pelik_peli WHERE kotijoukkue='%s' or vierasjoukkue='%s') GROUP BY pelannut_pelaaja_id) AS pel 
					ON (p.pelaaja_id=pel.pelannut_pelaaja_id) WHERE p.Joukkue='%s'",
			mysql_real_escape_string($teamId),
			mysql_real_escape_string($teamId),
			mysql_real_escape_string($teamId),
			mysql_real_escape_string($teamId),
			mysql_real_escape_string($teamId),
			mysql_real_escape_string($teamId),
			mysql_real_escape_string($teamId));
		}
		
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
?>
