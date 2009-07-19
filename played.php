<?php
include 'view_ids.inc.php';
include 'lib/database.php';
include 'lib/common.functions.php';
include 'lib/season.functions.php';
include 'lib/serie.functions.php';
include 'lib/team.functions.php';
include 'builder.php';

$seriesId = intval($_GET["Series"]);
$teamId = intval($_GET["Team"]);

if($teamId)
	$LAYOUT_ID = SEASONPLAYED;
elseif($seriesId)
	$LAYOUT_ID = SERIELAYED;
else
	$LAYOUT_ID = TEAMPLAYED;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();

//content
OpenConnection();

if($teamId)
	{
	$season = TeamSeason($teamId);
	$tournaments = PlayedTournaments($season);
	$prevTournament = "";
	
	if(!mysql_num_rows($tournaments))
		{
		echo "\n<p>Ei pelattuja pelej&auml;.</p>\n";	
		}
		
	while($tournament = mysql_fetch_assoc($tournaments))
		{
		$games = TeamTournamentGames($teamId, $tournament['Paikka_ID']);
				
		if(mysql_num_rows($games))
			{
			if($tournament['Turnaus'] != $prevTournament)
				{
				if($prevTournament != "")
					echo "<hr/>\n";
				echo "<h1>". htmlentities($tournament['Turnaus']) ."</h1>\n";				
				$prevTournament = $tournament['Turnaus'];
				}

			echo "<table cellpadding='2' border='0' cellspacing='0' style='width:450px'>";
			echo "<tr><th align='left' colspan='9'>";
			echo DefWeekDateFormat($tournament['AikaAlku']) ." ";
			echo "<a href='placeinfo.php?Place=".$tournament['Paikka_ID']."'>". htmlentities($tournament['Paikka']) ."</a>";
			echo "</th></tr>\n";
			
			while($game = mysql_fetch_assoc($games))
				{
				echo "<tr><td style='width:6%'>", DefHourFormat($game['Aika']) ,"</td>";
				echo "<td style='width:20%'>". htmlentities($game['KNimi']) ."</td><td style='width:2%'>-</td><td style='width:20%'>". htmlentities($game['VNimi']) ."</td>";
				echo "<td style='width:5%'>". intval($game['Kotipisteet']) ."</td><td style='width:2%'>-</td><td style='width:5%'>". intval($game['Vieraspisteet']) ."</td>";
				if (intval($game['Maaleja'])>0)
					echo "<td style='width:15%'><a href='gameplay.php?Game=". $game['Peli_ID'] ."'>pelin kulku</a></td>";
				else
					echo "<td style='width:15%'>es</td>";

				echo"<td style='width:15%'><a href='gamecard.php?Team1=". htmlentities($game['kId']) ."&amp;Team2=". htmlentities($game['vId']) . "'>otteluhistoria </a></td>";					
				echo "</tr>";
				}
			echo "</table>";
			}
		}
	}
elseif($seriesId)
	{
	$tournaments = SeriesPlayedTournaments($seriesId);
	$prevTournament = "";
	
	if(!mysql_num_rows($tournaments))
		{
		echo "\n<p>Ei pelattuja pelej&auml;.</p>\n";	
		}
		
	while($tournament = mysql_fetch_assoc($tournaments))
		{
		
		if($tournament['Turnaus'] != $prevTournament)
			{
			if($prevTournament != "")
				echo "<hr/>\n";
			echo "<h1>". htmlentities($tournament['Turnaus']) ."</h1>";
			$prevTournament = $tournament['Turnaus'];
			}
			
		$places = TournamentPlaces($season, $seriesId, $tournament['Paikka_ID']);
		
		while($place = mysql_fetch_assoc($places))
			{
			echo "<table cellpadding='2' border='0' cellspacing='0' style='width:450px'>";
			echo "<tr><th align='left' colspan='9'>";
			echo DefWeekDateFormat($tournament['AikaAlku']) ." ";
			echo "<a href='placeinfo.php?Place=".$tournament['Paikka_ID']."'>". htmlentities($tournament['Paikka']) ."</a>";
			echo "</th></tr>\n";
				
			$games = PlayedGames($season, $seriesId, $place['paikka_id']);
			if(mysql_num_rows($games))
				{
				//echo "<table cellpadding='2' border='0' cellspacing='0' style='width:500px'>\n";
				
				while($game = mysql_fetch_assoc($games))
					{
					//echo "<tr><th colspan='8' align='left'><b>". htmlentities($game['Nimi']) ."</b></th></tr>";
					$results = SeriesGames($game['Sarja_ID'],$place['paikka_id']);
					
					while($result = mysql_fetch_assoc($results))
						{
						echo "<tr><td style='width:10%'>", DefHourFormat($result['Aika']) ,"</td>";
						echo "<td style='width:25%'>". htmlentities($result['KNimi']) ."</td><td style='width:2%'>-</td><td style='width:25%'>". htmlentities($result['VNimi']) ."</td>";
						echo "<td style='width:5%'>". intval($result['Kotipisteet']) ."</td><td style='width:2%'>-</td><td style='width:10%'>". intval($result['Vieraspisteet']) ."</td>";
						if (intval($result['Maaleja'])>0)
							echo "<td style='width:15%'><a href='gameplay.php?Game=". $result['Peli_ID'] ."'>pelin kulku</a></td>";
						else
							echo "<td style='width:15%'>es</td>";

						echo "</tr>";
						}
					}
				}
			echo "</table>";
			}
		echo "<p></p>\n";
		}
	}
else
	{
	$season = CurrenSeason();
	$tournaments = PlayedTournaments($season);
	$prevTournament = "";
	
	if(!mysql_num_rows($tournaments))
		{
		echo "\n<p>Ei pelattuja pelej&auml;.</p>\n";	
		}
		
	while($tournament = mysql_fetch_assoc($tournaments))
		{
		
		if($tournament['Turnaus'] != $prevTournament)
			{
			if($prevTournament != "")
				echo "<hr/>\n";
			echo "<h1>". htmlentities($tournament['Turnaus']) ."</h1>";
			$prevTournament = $tournament['Turnaus'];
			}
			
		$places = TournamentPlaces($season, $seriesId, $tournament['Paikka_ID']);
		
		while($place = mysql_fetch_assoc($places))
			{
			echo "<table border='0'><tr><td class='placeheader'>";
			echo DefWeekDateFormat($tournament['AikaAlku']) ." ";
			echo "<a href='placeinfo.php?Place=".$tournament['Paikka_ID']."'>". htmlentities($tournament['Paikka']) ."</a>";
			echo "</td></tr></table>\n";
				
			$games = PlayedGames($season, $seriesId, $place['paikka_id']);
			if(mysql_num_rows($games))
				{
				echo "<table cellpadding='2' border='0' cellspacing='0' style='width:500px'>\n";
				
				while($game = mysql_fetch_assoc($games))
					{
					echo "<tr><th colspan='8' align='left'><b>". htmlentities($game['Nimi']) ."</b></th></tr>";
					$results = SeriesGames($game['Sarja_ID'],$place['paikka_id']);
					
					while($result = mysql_fetch_assoc($results))
						{
						echo "<tr><td style='width:10%'>", DefHourFormat($result['Aika']) ,"</td>";
						echo "<td style='width:25%'>". htmlentities($result['KNimi']) ."</td><td style='width:2%'>-</td><td style='width:25%'>". htmlentities($result['VNimi']) ."</td>";
						echo "<td style='width:5%'>". intval($result['Kotipisteet']) ."</td><td style='width:2%'>-</td><td style='width:10%'>". intval($result['Vieraspisteet']) ."</td>";
						if (intval($result['Maaleja'])>0)
							echo "<td style='width:15%' align='right'><a href='gameplay.php?Game=". $result['Peli_ID'] ."'>pelin kulku</a></td>";
						else
							echo "<td style='width:15%' align='right'>es</td>";

						//echo"<td style='width:15%'><a href='gamecard.php?Team1=". htmlentities($result['kId']) ."&amp;Team2=". htmlentities($result['vId']) . "'>otteluhistoria </a></td>";					
						echo "</tr>";
						}
					}
				echo "</table>";
				}
			}
		echo "<p></p>\n";
		}	
	}
CloseConnection();
?>
<p><a href="javascript:history.go(-1);">Palaa</a></p>
<?php
contentEnd();
pageEnd();
?>
