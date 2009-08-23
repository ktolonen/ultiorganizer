<?php 

function SerieResolveStandings($serieId)
	{
	//query serie teams
	$query = sprintf("
		SELECT j.joukkue_id, js.activerank 
		FROM pelik_joukkue AS j INNER JOIN pelik_joukkue_sarja AS js ON (j.joukkue_id = js.joukkue) 
		WHERE js.sarja='%s' 
		ORDER BY js.activerank ASC, js.rank ASC",
		mysql_real_escape_string($serieId));
		
	$standings = mysql_query($query);
	
	$points=array();
	$i=0;
	while($row = mysql_fetch_assoc($standings))
		{
		
		$query = sprintf("
			SELECT COUNT(*) AS ottelut, COUNT((kotijoukkue='%s' AND (kotipisteet>vieraspisteet)) OR (vierasjoukkue='%s' AND (kotipisteet<vieraspisteet)) OR NULL) AS voitot 
			FROM pelik_peli 
			WHERE (kotipisteet != vieraspisteet) AND (kotijoukkue='%s' OR vierasJoukkue='%s') AND peli_id IN (SELECT peli FROM pelik_peli_sarja WHERE sarja='%s')",
			mysql_real_escape_string($row['joukkue_id']),
			mysql_real_escape_string($row['joukkue_id']),
			mysql_real_escape_string($row['joukkue_id']),
			mysql_real_escape_string($row['joukkue_id']),
			mysql_real_escape_string($serieId));
			
		$result = mysql_query($query);
			
		$stats1 = mysql_fetch_assoc($result);
		$points[$i]['joukkue'] = $row['joukkue_id'];
		$points[$i]['ottelut'] = $stats1['ottelut'];
		$points[$i]['voitot'] = $stats1['voitot'];	
		$i++;
	}
	
	//sort according wins
	$points = SerieSortAccordingPoints($points, mysql_num_rows($standings));
	
	//sort if same points accorting same wins
	$points = SerieSortSameWins($points, mysql_num_rows($standings),$serieId);
	
	//update results
	for ($i=0; $i < mysql_num_rows($standings) && !empty($points[$i]['joukkue']); $i++) 
		{	
		echo "<p>win t".$points[$i]['joukkue']." v".$points[$i]['voitot']." s".$points[$i]['arank']."</p>";
		$query = sprintf("UPDATE pelik_joukkue_sarja 
				SET activerank='%s' WHERE sarja='%s' AND joukkue='%s'",
			mysql_real_escape_string($points[$i]['arank']),
			mysql_real_escape_string($serieId),
			mysql_real_escape_string($points[$i]['joukkue']));
		
		mysql_query($query);
		}
		
	return $result;
	}

function SerieSortAccordingPoints($points, $teams)
	{
	//sort according wins
	usort($points, create_function('$a,$b','return $a[\'voitot\']==$b[\'voitot\']?0:($a[\'voitot\']>$b[\'voitot\']?-1:1);'));
	
	//update active rank
	$stand=1;
	$offset=0;
	for ($i=0; $i < $teams && !empty($points[$i]['joukkue']); $i++) 
		{
		if($i+1 < $teams && ($points[$i]['voitot']==$points[$i+1]['voitot']))
			{
			$points[$i]['arank'] = $stand;
			$offset++;
			}
		elseif($i == $teams && ($points[$i]['voitot']==$points[$i-1]['voitot']))
			{
			$points[$i]['arank'] = $stand;
			$offset++;
			}
		else
			{
			$stand+=$offset;
			$points[$i]['arank'] = $stand;
			$stand++;
			}
		}
	return $points;
	}
	
function SerieSortSameWins($points, $teams, $serieId)
	{
	$found=true;
	$i=1;
	
	//loop trougth and find teems with same active rank
	while($found)
		{
		$samerank=array();
		$total=0;
		$sharedstand=0;
		
		for ($i; $i < $teams && !empty($points[$i]['joukkue']); $i++) 
			{
			if($points[$i]['arank']==$points[$i-1]['arank'])
				{
				//if first found, then previous team was with same rank
				if(!$total)
					{
					$samerank[$total]['joukkue'] = $points[$i-1]['joukkue'];
					$samerank[$total]['voitot'] = 0;
					$samerank[$total]['arank'] = $points[$i-1]['arank'];
					$total++;
					$sharedstand=$points[$i-1]['arank'];
					$first=true;
					}
				$samerank[$total]['joukkue'] = $points[$i]['joukkue'];
				$samerank[$total]['voitot'] = 0;
				$samerank[$total]['arank'] = $points[$i]['arank'];
				
				$total++;
				}
			elseif($total)
				break;
			}
		
		//if teams with same active rank found		
		if($total)
			{
			//check out mutual matches
			for ($j=0; $j < $total && !empty($samerank[$j]['joukkue']); $j++) 
				{
				for ($k=0; $k < $total && !empty($samerank[$k]['joukkue']); $k++) 
					{
					//echo "<p>test".$samerank[$j][0]."vs".$samerank[$k][0]."</p>";
					if($samerank[$j]['joukkue']!=$samerank[$k]['joukkue'])
						{
						$query = sprintf("
							SELECT COUNT(*) AS ottelut, COUNT(kotijoukkue='%s' AND vierasjoukkue='%s' AND (kotipisteet>vieraspisteet)) AS voitot 
							FROM pelik_peli 
							WHERE (kotipisteet != vieraspisteet) AND (kotijoukkue='%s' AND vierasJoukkue='%s') AND 
							peli_id IN (SELECT peli FROM pelik_peli_sarja WHERE sarja='%s')",
							mysql_real_escape_string($samerank[$j]['joukkue']),
							mysql_real_escape_string($samerank[$k]['joukkue']),
							mysql_real_escape_string($samerank[$j]['joukkue']),
							mysql_real_escape_string($samerank[$k]['joukkue']),						
							mysql_real_escape_string($serieId));
					
						$result = mysql_query($query);
						$stats1 = mysql_fetch_assoc($result);
						if(intval($stats1['voitot']))
							{
							$samerank[$j]['voitot']++;
							//echo "<p>win t".$samerank[$j][0]." v".$samerank[$j][2]." s".$points[$i][5]."</p>";
							}
						}
					}
				}
				
			//sort according wins
			usort($samerank, create_function('$a,$b','return $a[\'voitot\']==$b[\'voitot\']?0:($a[\'voitot\']>$b[\'voitot\']?-1:1);'));
			
			//update active rank
			//$stand=1;
			$offset=0;
			for ($j=0; $j < $total && !empty($samerank[$j]['joukkue']); $j++) 
				{
				if($j+1 < $total && ($samerank[$j]['voitot']==$samerank[$j+1]['voitot']))
					{
					$samerank[$j]['arank'] = $sharedstand;
					$offset++;
					}
				elseif($j == $total && ($samerank[$j]['voitot']==$samerank[$j-1]['voitot']))
					{
					$samerank[$j]['arank'] = $sharedstand;
					$offset++;
					}
				else
					{
					$sharedstand+=$offset;
					$samerank[$j]['arank'] = $sharedstand;
					$sharedstand++;
					}
				for ($k=0; $k < $teams && !empty($points[$k]['joukkue']); $k++) 
					{
					if($samerank[$j]['joukkue']==$points[$k]['joukkue'])
						{
						$points[$k]['arank'] = $samerank[$j]['arank'];
						}
					}
				}
			}
		else
			{
			$found=false;
			}
		}
	
	return $points;
	}
	
?>
